<?php
// Sends email through Brevo's HTTPS API (free plan: 300 emails/day).
// An API is used instead of PHP mail()/SMTP because Render's free tier blocks
// outbound SMTP ports and the server has no mail daemon of its own.
//
// Settings are read from environment variables (set them on Render / Railway):
//   BREVO_API_KEY      - the API key from Brevo (SMTP & API > API Keys)
//   MAIL_FROM_EMAIL    - a sender address you verified in Brevo
//   MAIL_FROM_NAME     - display name (optional, defaults to Densco Health Products)
//
// For local XAMPP testing you can instead create config/mail.php that returns
// an array: ['api_key' => '...', 'from_email' => '...', 'from_name' => '...']

function mail_settings() {
    $cfg = [
        'api_key'    => getenv('BREVO_API_KEY') ?: '',
        'from_email' => getenv('MAIL_FROM_EMAIL') ?: '',
        'from_name'  => getenv('MAIL_FROM_NAME') ?: 'Densco Health Products',
    ];
    $local = __DIR__ . '/config/mail.php';
    if ((!$cfg['api_key'] || !$cfg['from_email']) && is_file($local)) {
        $extra = include $local;
        if (is_array($extra)) $cfg = array_merge($cfg, array_filter($extra));
    }
    return $cfg;
}

function mail_is_configured() {
    $c = mail_settings();
    return $c['api_key'] !== '' && $c['from_email'] !== '';
}

// Returns true when Brevo accepted the message.
function send_email($toEmail, $toName, $subject, $html, $text = '') {
    $c = mail_settings();
    if ($c['api_key'] === '' || $c['from_email'] === '') {
        error_log('mail_lib: BREVO_API_KEY / MAIL_FROM_EMAIL are not set.');
        return false;
    }

    $payload = [
        'sender'      => ['name' => $c['from_name'], 'email' => $c['from_email']],
        'to'          => [['email' => $toEmail, 'name' => $toName !== '' ? $toName : $toEmail]],
        'subject'     => $subject,
        'htmlContent' => $html,
    ];
    if ($text !== '') $payload['textContent'] = $text;

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $c['api_key'],
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 300) return true;
    error_log('mail_lib: Brevo returned HTTP ' . $code . ' ' . $err . ' ' . substr((string)$resp, 0, 300));
    return false;
}
