<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const CHAT_STAFF_ROLES = ['owner', 'admin', 'inventory'];

function chat_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function chat_fail(int $status, string $message): never {
    chat_json($status, ['error' => $message]);
}

function chat_install(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS chat_conversations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id BIGINT UNSIGNED NOT NULL UNIQUE,
        status ENUM('waiting', 'answered', 'closed') NOT NULL,
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        INDEX chat_queue (status, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS chat_messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversation_id BIGINT UNSIGNED NOT NULL,
        sender ENUM('customer', 'staff') NOT NULL,
        sender_id BIGINT UNSIGNED NOT NULL,
        request_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        text TEXT NOT NULL,
        sent_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY chat_request (conversation_id, sender, sender_id, request_id),
        INDEX chat_history (conversation_id, id),
        FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS chat_staff_presence (
        staff_id BIGINT UNSIGNED PRIMARY KEY,
        seen_at DATETIME(6) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function chat_rows(mysqli $db, string $sql, array $params = []): array {
    return $db->execute_query($sql, $params)->fetch_all(MYSQLI_ASSOC);
}

function chat_thread(mysqli $db, bool $staff, int $id, int $after, int $knownThread): array {
    $column = $staff ? 'id' : 'customer_id';
    // A single consistent snapshot keeps a close or reply aligned with its messages.
    $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try {
        $thread = chat_rows($db, "SELECT id, status FROM chat_conversations WHERE $column = ?", [$id])[0] ?? null;
        if ($thread) {
            $thread['id'] = (int) $thread['id'];
            if (!$staff && $thread['id'] !== $knownThread) $after = 0;
            $thread['messages'] = chat_rows($db,
                "SELECT id, sender, text FROM chat_messages
                 WHERE conversation_id = ? AND id > ? ORDER BY id",
                [$thread['id'], $after]);
        }
        $available = !$staff && (bool) chat_rows($db,
            "SELECT 1 FROM chat_staff_presence p JOIN staff s ON s.staff_id = p.staff_id
             WHERE s.role IN (?, ?, ?) AND p.seen_at >= UTC_TIMESTAMP(6) - INTERVAL 90 SECOND LIMIT 1",
            CHAT_STAFF_ROLES);
        $db->commit();
        return ['thread' => $thread, 'available' => $available];
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}

function chat_write(mysqli $db, bool $staff, int $actor, int $id, int $after, int $knownThread, string $action, string $text, string $token): ?array {
    $db->begin_transaction();
    try {
        if ($action === 'request') {
            $db->execute_query(
                "INSERT INTO chat_conversations (customer_id, status) VALUES (?, 'waiting')
                 ON DUPLICATE KEY UPDATE customer_id = customer_id", [$actor]);
        }
        $column = $staff ? 'id' : 'customer_id';
        $thread = chat_rows($db, "SELECT id, status FROM chat_conversations WHERE $column = ? FOR UPDATE", [$id])[0] ?? null;
        if (!$thread) {
            $db->rollback();
            return [404, 'Conversation not found.'];
        }

        $conversationId = (int) $thread['id'];
        if (!$staff && $action === 'send' && $conversationId !== $knownThread) {
            $db->rollback();
            return [409, 'Your conversation changed. Review it before sending again.'];
        }
        $sender = $staff ? 'staff' : 'customer';
        if ($action !== 'close' && chat_rows($db,
            "SELECT id FROM chat_messages WHERE conversation_id = ? AND sender = ? AND sender_id = ? AND request_id = ?",
            [$conversationId, $sender, $actor, $token])) {
            $db->commit();
            return null;
        }
        if ($action === 'request' && strcasecmp($text, 'Talk to staff') === 0 && $thread['status'] !== 'closed'
            && chat_rows($db, "SELECT id FROM chat_messages WHERE conversation_id = ? LIMIT 1", [$conversationId])) {
            $db->commit();
            return null;
        }
        if ($thread['status'] === 'closed' && $action !== 'request' && $action !== 'close') {
            $db->rollback();
            return [409, 'This conversation has ended.'];
        }
        if ($action === 'close' && $thread['status'] === 'closed') {
            $db->commit();
            return null;
        }
        if ($action === 'close' && chat_rows($db,
            "SELECT id FROM chat_messages WHERE conversation_id = ? AND id > ? LIMIT 1",
            [$conversationId, $after])) {
            $db->rollback();
            return [409, 'New messages arrived. Review them before ending the conversation.'];
        }

        if ($action !== 'close') {
            $db->execute_query(
                "INSERT INTO chat_messages (conversation_id, sender, sender_id, request_id, text) VALUES (?, ?, ?, ?, ?)",
                [$conversationId, $sender, $actor, $token, $text]);
        }
        $status = $action === 'close' ? 'closed' : ($staff ? 'answered' : 'waiting');
        $db->execute_query("UPDATE chat_conversations SET status = ?, updated_at = UTC_TIMESTAMP(6) WHERE id = ?",
            [$status, $conversationId]);
        $db->commit();
        return null;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}

function chat_handle(mysqli $db, bool $staff, int $actor, int $id, int $after, int $knownThread, string $action, string $text, string $token): array {
    switch ($action) {
        case 'summary':
            $row = chat_rows($db, "SELECT COUNT(*) AS waiting FROM chat_conversations WHERE status = 'waiting'")[0];
            return ['waiting' => (int) $row['waiting']];
        case 'queue':
            return ['conversations' => chat_rows($db,
                "SELECT c.id, c.status, COALESCE(u.full_name, CONCAT('Customer #', c.customer_id)) AS customer,
                        m.text AS preview
                 FROM chat_conversations c
                 LEFT JOIN users u ON u.user_id = c.customer_id
                 LEFT JOIN chat_messages m ON m.id = (
                    SELECT id FROM chat_messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1
                 )
                 ORDER BY FIELD(c.status, 'waiting', 'answered', 'closed'), c.updated_at DESC, c.id DESC")];
        case 'presence':
            $db->execute_query("INSERT INTO chat_staff_presence (staff_id, seen_at) VALUES (?, UTC_TIMESTAMP(6))
                                ON DUPLICATE KEY UPDATE seen_at = UTC_TIMESTAMP(6)", [$actor]);
            return ['ok' => true];
        case 'thread':
            break;
        default:
            $failure = chat_write($db, $staff, $actor, $id, $after, $knownThread, $action, $text, $token);
            if ($failure) chat_fail(...$failure);
    }
    return chat_thread($db, $staff, $id, $after, $knownThread);
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    chat_fail(405, 'Method not allowed.');
}
$staff = ($_GET['scope'] ?? '') === 'staff';
session_start(['read_and_close' => true]);
$actor = (int) ($_SESSION[$staff ? 'staff_id' : 'user_id'] ?? 0);
if (!$actor) chat_fail(401, 'Please log in to use staff chat.');

$body = [];
if ($method === 'POST') {
    // Requiring JSON prevents a cross-origin HTML form from acting with the session cookie.
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') chat_fail(415, 'Send a JSON request.');
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) chat_fail(400, 'Invalid request.');
}
$action = ($method === 'POST' ? ($body['action'] ?? '') : ($_GET['action'] ?? 'thread'));
$allowed = $method === 'GET'
    ? ($staff ? ['thread', 'queue', 'summary'] : ['thread'])
    : ($staff ? ['send', 'close', 'presence'] : ['request', 'send']);
if (!in_array($action, $allowed, true)) chat_fail(400, 'Invalid action.');

$id = $staff ? filter_var($method === 'POST' ? ($body['id'] ?? null) : ($_GET['id'] ?? null), FILTER_VALIDATE_INT) : $actor;
if (in_array($action, ['thread', 'send', 'close'], true) && $id < 1) chat_fail(400, 'Invalid conversation.');
$after = filter_var($_GET['after'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($after === false) chat_fail(400, 'Invalid message cursor.');
$knownThread = filter_var($_GET['known'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($knownThread === false) chat_fail(400, 'Invalid conversation.');

$text = '';
$token = '';
if ($action === 'send' || $action === 'request') {
    if (!is_string($body['text'] ?? null) || !is_string($body['token'] ?? null)) {
        chat_fail(400, 'A message and request ID are required.');
    }
    $text = trim($body['text']);
    $token = $body['token'];
    if ($text === '' || strlen($text) > 8000 || !preg_match('/\A[0-9a-f]{32}\z/', $token)) {
        chat_fail(400, 'Enter a message of up to 2,000 characters.');
    }
    if (preg_match_all('/./us', $text) > 2000) chat_fail(400, 'Enter a message of up to 2,000 characters.');
}

try {
    $throwDatabaseConnectionErrors = true;
    require_once __DIR__ . '/../config.php';
    if ($staff) {
        $row = chat_rows($conn, 'SELECT role FROM staff WHERE staff_id = ?', [$actor])[0] ?? [];
        if (!in_array($row['role'] ?? '', CHAT_STAFF_ROLES, true)) chat_fail(403, 'Staff chat access is unavailable.');
    } else {
        // Match the storefront requirement for accounts that predate email verification.
        try {
            $verified = chat_rows($conn, 'SELECT 1 FROM users WHERE user_id = ? AND email_verified_at IS NOT NULL', [$actor]);
        } catch (mysqli_sql_exception $error) {
            if ($error->getCode() !== 1054) throw $error;
            $verified = chat_rows($conn, 'SELECT 1 FROM users WHERE user_id = ?', [$actor]);
        }
        if (!$verified) {
            chat_fail(401, 'Please sign in with a verified customer account.');
        }
    }
    try {
        $result = chat_handle($conn, $staff, $actor, (int) $id, $after, $knownThread, $action, $text, $token);
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() !== 1146) throw $error;
        chat_install($conn);
        $result = chat_handle($conn, $staff, $actor, (int) $id, $after, $knownThread, $action, $text, $token);
    }
    chat_json(200, $result);
} catch (Throwable $error) {
    error_log('Staff chat: ' . $error->getMessage());
    chat_fail(503, 'Staff chat is temporarily unavailable. Please try again.');
}
