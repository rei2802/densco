<?php
// Shared rules for payment reference numbers.
// checkout.php enforces these on the server; the same rules are mirrored in
// cart.php (REF_RULES) so customers get instant feedback. Keep the two in sync.

/**
 * Reference numbers are often copied with spaces (GCash shows them as
 * "1234 567 890123"), so whitespace is removed before checking and saving.
 */
function normalize_reference_number($ref) {
    return preg_replace('/\s+/', '', trim((string)$ref));
}

/**
 * Returns an error message if $ref is not a valid reference number for the
 * given payment method, or null when it is valid. Pass the normalized value.
 *
 *  - "GCash / QR PH": 12 or 13 characters, letters and numbers only
 *    (GCash shows 13-digit numbers, but some receipts include letters).
 *  - "Cash": no reference number (there is no receipt), always valid.
 *  - anything else: required, up to 100 characters.
 *
 * Bank transfer is no longer offered at checkout (long validation process).
 */
function reference_number_error($payment, $ref) {
    $ref = (string)$ref;

    if ($payment === 'Cash') {
        return null;
    }

    if ($ref === '') {
        return 'Enter the payment reference number from your receipt.';
    }

    switch ($payment) {
        case 'GCash / QR PH':
            if (!preg_match('/^[A-Za-z0-9]{12,13}$/', $ref)) {
                return 'The reference number must be 12 or 13 characters (letters and numbers only). Check your GCash receipt.';
            }
            return null;

        default:
            if (mb_strlen($ref) > 100) {
                return 'Payment reference number is too long (100 characters maximum).';
            }
            return null;
    }
}
