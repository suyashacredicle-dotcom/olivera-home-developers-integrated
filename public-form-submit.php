
<?php

// --------------------------------------------------
// DATABASE AND SESSION
// --------------------------------------------------

require_once __DIR__ . '/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --------------------------------------------------
// REDIRECT HELPER
// --------------------------------------------------

function publicSubmitRedirect(string $page, string $status): void
{
    $parts = explode('#', $page, 2);

    $destination = $parts[0] . '?submitted=' . rawurlencode($status);

    if (isset($parts[1]) && $parts[1] !== '') {
        $destination .= '#' . $parts[1];
    }

    header('Location: ' . $destination, true, 303);
    exit;
}

// --------------------------------------------------
// INPUT HELPER
// --------------------------------------------------

function publicSubmitValue(string $key, int $max = 10000): string
{
    $value = $_POST[$key] ?? '';

    if (!is_string($value)) {
        return '';
    }

    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }

    return substr($value, 0, $max);
}

// --------------------------------------------------
// POST REQUEST ONLY
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

// --------------------------------------------------
// FORM TYPE AND REDIRECT VALIDATION
// --------------------------------------------------

$type = publicSubmitValue('form_type', 30);

$targets = [
    'callback'   => 'contactus.php',
    'site_visit' => 'booksitevisit.php',
    'partner'    => 'becomeourpartner.php'
];

$returnTargets = [
    'contactus.php'           => 'contactus.php',
    'booksitevisit.php'       => 'booksitevisit.php',
    'becomeourpartner.php'    => 'becomeourpartner.php',
    'index.php#partner-form'  => 'index.php#partner-form',
    'index.php#callback-form' => 'index.php#callback-form'
];

$returnTo = publicSubmitValue('return_to', 80);

$target = $returnTargets[$returnTo] ?? ($targets[$type] ?? 'index.php');

if (!isset($targets[$type])) {
    http_response_code(400);
    exit('Invalid form.');
}

// --------------------------------------------------
// CSRF VALIDATION
// --------------------------------------------------

$csrf = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrf) ||
    empty($_SESSION['public_form_csrf']) ||
    !is_string($_SESSION['public_form_csrf']) ||
    !hash_equals($_SESSION['public_form_csrf'], $csrf)
) {
    publicSubmitRedirect($target, 'error');
}

// --------------------------------------------------
// COLLECT FORM DATA
// --------------------------------------------------

$name = publicSubmitValue('name', 150);

$phoneInput = publicSubmitValue('phone', 20);

$phone = preg_replace('/[^0-9+]/', '', $phoneInput);

$email = strtolower(publicSubmitValue('email', 190));

$city = publicSubmitValue('city', 120);

$propertyType = publicSubmitValue('property_type', 100);

$plotSize = publicSubmitValue('plot_size', 100);

if ($plotSize === '') {
    $plotSize = publicSubmitValue('size', 100);
}

$visitDate = publicSubmitValue('visit_date', 10);

$message = publicSubmitValue('message', 5000);

$partnership = publicSubmitValue('partnership', 100);

$investment = publicSubmitValue('investment', 100);

// --------------------------------------------------
// COMMON VALIDATION
// --------------------------------------------------

$nameLength = function_exists('mb_strlen')
    ? mb_strlen($name, 'UTF-8')
    : strlen($name);

if (
    $name === '' ||
    $nameLength > 100 ||
    !preg_match("/^[\p{L}\p{M} .'-]+$/u", $name)
) {
    publicSubmitRedirect($target, 'error');
}

// --------------------------------------------------
// PHONE VALIDATION
// --------------------------------------------------

$digits = preg_replace('/\D+/', '', $phone);

if (
    !is_string($digits) ||
    strlen($digits) !== 10
) {
    publicSubmitRedirect($target, 'error');
}

// --------------------------------------------------
// EMAIL VALIDATION
// --------------------------------------------------

if (
    $email !== '' &&
    (
        strlen($email) > 190 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    )
) {
    publicSubmitRedirect($target, 'error');
}

// --------------------------------------------------
// PARTNERSHIP-SPECIFIC VALIDATION
// --------------------------------------------------

if ($type === 'partner') {

    $allowedPartnerships = [
        'Channel Partner',
        'Landowner',
        'Real Estate Associate',
        'Business Investor',
        'Other'
    ];

    $allowedInvestments = [
        '',
        'Below ₹10 Lakhs',
        '₹10 - ₹25 Lakhs',
        '₹25 - ₹50 Lakhs',
        'Above ₹50 Lakhs',
        'Not Applicable'
    ];

    if ($email === '') {
        publicSubmitRedirect($target, 'error');
    }

    if (!in_array($partnership, $allowedPartnerships, true)) {
        publicSubmitRedirect($target, 'error');
    }

    if (!in_array($investment, $allowedInvestments, true)) {
        publicSubmitRedirect($target, 'error');
    }

    if (
        $nameLength > 100 ||
        strlen($city) > 120 ||
        strlen($message) > 5000
    ) {
        publicSubmitRedirect($target, 'error');
    }
}

// --------------------------------------------------
// SITE VISIT DATE VALIDATION
// --------------------------------------------------

if ($type === 'site_visit' && $visitDate !== '') {

    $date = DateTime::createFromFormat('!Y-m-d', $visitDate);

    if (!$date || $date->format('Y-m-d') !== $visitDate) {
        publicSubmitRedirect($target, 'error');
    }
}

// --------------------------------------------------
// LEAD SOURCE TRACKING
// --------------------------------------------------

$sourcePlatform =
    $_SESSION['ad_source_platform'] ??
    (
        $_SESSION['landing_tracking']['utm_source'] ??
        (
            $_SESSION['landing_tracking']['source'] ??
            'website'
        )
    );

if (!is_string($sourcePlatform)) {
    $sourcePlatform = 'website';
}

$sourcePlatform = strtolower(trim($sourcePlatform));

$sourceMap = [
    'fb'        => 'Facebook',
    'meta'      => 'Facebook',
    'facebook'  => 'Facebook',
    'ig'        => 'Instagram',
    'instagram' => 'Instagram',
    'google'    => 'Google Ads',
    'adwords'   => 'Google Ads',
    'youtube'   => 'YouTube',
    'other'     => 'Other'
];

$leadSource = $sourceMap[$sourcePlatform] ?? 'Website';

$campaign = $_SESSION['landing_tracking']['utm_campaign'] ?? '';

if (!is_string($campaign)) {
    $campaign = '';
}

// --------------------------------------------------
// SAVE FORM DATA
// --------------------------------------------------

try {

    $pdo->beginTransaction();

    // ==================================================
    // PARTNERSHIP INQUIRY
    // ==================================================

    if ($type === 'partner') {

        // Partnership inquiries go ONLY into their own table.

        $partnerInsert = $pdo->prepare("
            INSERT INTO partnership_inquiries (
                name,
                phone,
                email,
                city,
                partnership_type,
                investment_range,
                message,
                status,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                'new',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $partnerInsert->execute([
            $name,
            $digits,
            $email,
            $city,
            $partnership,
            $investment !== '' ? $investment : null,
            $message !== '' ? $message : null
        ]);

    } else {

        // ==================================================
        // ORDINARY CUSTOMER ENQUIRIES
        // ==================================================

        $customerId = null;

        // Link ordinary enquiries to the customer directory
        // when an email address is provided.

        if ($email !== '') {

            $customerLookup = $pdo->prepare("
                SELECT id, status
                FROM customers
                WHERE email = ?
                LIMIT 1
                FOR UPDATE
            ");

            $customerLookup->execute([$email]);

            $existingCustomer = $customerLookup->fetch(PDO::FETCH_ASSOC);

            if ($existingCustomer) {

                if (($existingCustomer['status'] ?? '') !== 'active') {
                    $pdo->rollBack();
                    publicSubmitRedirect($target, 'error');
                }

                $customerId = (int) $existingCustomer['id'];

                $customerUpdate = $pdo->prepare("
                    UPDATE customers
                    SET
                        name = ?,
                        phone = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $customerUpdate->execute([
                    $name,
                    $digits,
                    $customerId
                ]);

            } else {

                $customerInsert = $pdo->prepare("
                    INSERT INTO customers (
                        name,
                        email,
                        phone,
                        password,
                        status,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        ?, ?, ?, NULL,
                        'active',
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");

                $customerInsert->execute([
                    $name,
                    $email,
                    $digits
                ]);

                $customerId = (int) $pdo->lastInsertId();
            }
        }

        // --------------------------------------------------
        // BUILD ORDINARY LEAD DETAILS
        // --------------------------------------------------

        $details = [];

        if ($city !== '') {
            $details[] = 'Preferred location: ' . $city;
        }

        if ($propertyType !== '') {
            $details[] = 'Property type: ' . $propertyType;
        }

        if ($plotSize !== '') {
            $details[] = 'Preferred plot size: ' . $plotSize;
        }

        if ($type === 'site_visit') {

            $details[] = 'Request type: Site visit request (pending team confirmation)';

            if ($visitDate !== '') {
                $details[] = 'Preferred visit date: ' . $visitDate;
            }
        }

        if ($campaign !== '') {
            $details[] = 'Advertisement campaign: ' .
                substr($campaign, 0, 255);
        }

        $trackingDetails = [
            'utm_medium'  => $_SESSION['ad_utm_medium'] ?? '',
            'utm_content' => $_SESSION['ad_utm_content'] ?? '',
            'utm_term'    => $_SESSION['ad_utm_term'] ?? '',
            'fbclid'      => $_SESSION['ad_fbclid'] ?? '',
            'gclid'       => $_SESSION['ad_gclid'] ?? '',
            'gbraid'      => $_SESSION['ad_gbraid'] ?? '',
            'wbraid'      => $_SESSION['ad_wbraid'] ?? '',
            'ttclid'      => $_SESSION['ad_ttclid'] ?? ''
        ];

        foreach ($trackingDetails as $trackingName => $trackingValue) {

            if (
                is_string($trackingValue) &&
                $trackingValue !== ''
            ) {
                $details[] = $trackingName . ': ' .
                    substr($trackingValue, 0, 255);
            }
        }

        if ($message !== '') {
            $details[] = 'Message: ' . $message;
        }

        $leadMessage = implode("\n", $details);

        if ($leadMessage === '') {
            $leadMessage = 'Website callback request.';
        }

        $crmPropertyType =
            $propertyType !== ''
                ? $propertyType
                : (
                    $type === 'site_visit'
                        ? 'Site Visit Request'
                        : 'Callback Request'
                );

        // --------------------------------------------------
        // INSERT ORDINARY LEAD
        // --------------------------------------------------

        $leadInsert = $pdo->prepare("
            INSERT INTO leads (
                customer_id,
                customer_name,
                phone,
                email,
                property_type,
                interested_location,
                budget_min,
                budget_max,
                message,
                lead_source,
                lead_status,
                priority,
                assigned_to,
                created_by
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, NULL, NULL, ?,
                ?, 'new', 'medium', NULL, NULL
            )
        ");

        $leadInsert->execute([
            $customerId,
            $name,
            $digits,
            $email !== '' ? $email : null,
            $crmPropertyType,
            $city !== '' ? $city : null,
            $leadMessage,
            $leadSource
        ]);
    }

    // --------------------------------------------------
    // COMMIT TRANSACTION
    // --------------------------------------------------

    $pdo->commit();

    // --------------------------------------------------
    // CLEAR TRACKING DATA
    // --------------------------------------------------

    unset(
        $_SESSION['landing_tracking'],
        $_SESSION['ad_source_platform'],
        $_SESSION['ad_source_campaign'],
        $_SESSION['ad_utm_medium'],
        $_SESSION['ad_utm_content'],
        $_SESSION['ad_utm_term'],
        $_SESSION['ad_fbclid'],
        $_SESSION['ad_gclid'],
        $_SESSION['ad_gbraid'],
        $_SESSION['ad_wbraid'],
        $_SESSION['ad_ttclid']
    );

    // --------------------------------------------------
    // ROTATE CSRF TOKEN
    // --------------------------------------------------

    $_SESSION['public_form_csrf'] = bin2hex(random_bytes(32));

    publicSubmitRedirect($target, 'success');

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Public CRM form submission failed: ' .
        $exception->getMessage()
    );

    publicSubmitRedirect($target, 'error');
}

?>
