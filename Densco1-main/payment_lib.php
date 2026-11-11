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
 *  - "GCash / QR PH": 12 or 13 digits (GCash Send Money is 12-13; GCash bank
 *    transfer is 13).
 *  - "Bank Transfer": BDO/BPI/QRPH formats vary by transaction type, so a
 *    lenient check: 6-30 letters, numbers or dashes.
 *  - "Cash": no reference number (there is no receipt), always valid.
 *  - anything else: required, up to 100 characters.
 */
function reference_number_error($payment, $ref) {
    $ref = (string)$ref;

    if ($payment === 'Cash') {
        return null;
    }

    if ($ref === '') {
        return 'Payment reference number is required.';
    }

    switch ($payment) {
        case 'GCash / QR PH':
            if (!preg_match('/^\d{12,13}$/', $ref)) {
                return 'GCash reference numbers are 12 or 13 digits (numbers only). Check your receipt.';
            }
            return null;

        case 'Bank Transfer':
            if (!preg_match('/^[A-Za-z0-9-]{6,30}$/', $ref)) {
                return 'Bank reference numbers must be 6 to 30 characters (letters, numbers or dashes only). Check your receipt or deposit slip.';
            }
            return null;

        default:
            if (mb_strlen($ref) > 100) {
                return 'Payment reference number is too long (100 characters maximum).';
            }
            return null;
    }
}