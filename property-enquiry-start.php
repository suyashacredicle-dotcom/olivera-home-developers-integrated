
<?php

// --------------------------------------------------
// PROPERTY ENQUIRY - CUSTOMER DETAILS PAGE
// --------------------------------------------------

require_once __DIR__ . "/config/database.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --------------------------------------------------
// SAFE PARAMETER READER
// --------------------------------------------------

function enquiryStartGetParameter($key, $maxLength = 255)
{
    $value = $_GET[$key] ?? "";

    if (!is_string($value)) {
        return "";
    }

    $value = trim($value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

    return substr($value, 0, $maxLength);
}

// --------------------------------------------------
// ADVERTISEMENT SOURCE TRACKING
// Keep attribution captured on the homepage when a visitor later opens an enquiry form.
$trackingKeys = ["utm_source", "source", "utm_medium", "utm_campaign", "utm_content", "utm_term", "fbclid", "gclid", "gbraid", "wbraid", "ttclid"];
$hasIncomingTracking = false;
foreach ($trackingKeys as $trackingKey) {
    if (isset($_GET[$trackingKey]) && is_string($_GET[$trackingKey]) && trim($_GET[$trackingKey]) !== "") {
        $hasIncomingTracking = true;
        break;
    }
}
if ($hasIncomingTracking) {
    $source = strtolower(enquiryStartGetParameter("utm_source", 50));
    if ($source === "") $source = strtolower(enquiryStartGetParameter("source", 50));
    if ($source === "") {
        if (enquiryStartGetParameter("fbclid", 255) !== "") $source = "facebook";
        elseif (enquiryStartGetParameter("gclid", 255) !== "" || enquiryStartGetParameter("gbraid", 255) !== "" || enquiryStartGetParameter("wbraid", 255) !== "") $source = "google";
        elseif (enquiryStartGetParameter("ttclid", 255) !== "") $source = "other";
        else $source = "website";
    }
    $sourceAliases = ["fb"=>"facebook", "meta"=>"facebook", "facebook_ads"=>"facebook", "ig"=>"instagram", "instagram_ads"=>"instagram", "adwords"=>"google", "google_ads"=>"google", "youtube_ads"=>"youtube"];
    if (isset($sourceAliases[$source])) $source = $sourceAliases[$source];
    $allowedSources = ["website", "facebook", "instagram", "google", "youtube", "other"];
    if (!in_array($source, $allowedSources, true)) $source = "other";
    $_SESSION["ad_source_platform"] = $source;
    $_SESSION["ad_source_campaign"] = enquiryStartGetParameter("utm_campaign", 255) ?: null;
    $_SESSION["ad_utm_medium"] = enquiryStartGetParameter("utm_medium", 150);
    $_SESSION["ad_utm_content"] = enquiryStartGetParameter("utm_content", 255);
    $_SESSION["ad_utm_term"] = enquiryStartGetParameter("utm_term", 255);
    foreach (["fbclid", "gclid", "gbraid", "wbraid", "ttclid"] as $clickId) {
        $_SESSION["ad_" . $clickId] = enquiryStartGetParameter($clickId, 255);
    }
} elseif (!isset($_SESSION["ad_source_platform"])) {
    $_SESSION["ad_source_platform"] = "website";
}
$_SESSION["property_enquiry_flow"] = true;

// --------------------------------------------------
// ESCAPE OUTPUT
// --------------------------------------------------

function enquiryStartEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// --------------------------------------------------
// LOAD WEBSITE LOGO
// --------------------------------------------------

$websiteLogo = "";

try {

    $logoQuery = $pdo->prepare("
        SELECT setting_value
        FROM settings
        WHERE setting_key = 'website_logo'
        LIMIT 1
    ");

    $logoQuery->execute();

    $logoPath = $logoQuery->fetchColumn();

    if (
        is_string($logoPath) &&
        preg_match(
            '/\Auploads\/[a-f0-9]{32}\.(jpg|jpeg|png|webp)\z/i',
            $logoPath
        )
    ) {

        $logoFullPath = __DIR__ . DIRECTORY_SEPARATOR .
            str_replace("/", DIRECTORY_SEPARATOR, $logoPath);

        if (is_file($logoFullPath)) {
            $websiteLogo = $logoPath;
        }
    }

} catch (Throwable $exception) {

    error_log(
        "Customer enquiry landing logo error: " .
        $exception->getMessage()
    );
}

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// --------------------------------------------------
// FORM DEFAULTS
// --------------------------------------------------

$message = "";
$messageType = "error";

$name = "";
$email = "";
$phone = "";

// --------------------------------------------------
// CUSTOMER DETAILS SUBMISSION
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {

        $message = "Your form session has expired. Please refresh the page and try again.";

    } else {

        $nameInput = $_POST["name"] ?? "";
        $emailInput = $_POST["email"] ?? "";
        $phoneInput = $_POST["phone"] ?? "";

        if (
            !is_string($nameInput) ||
            !is_string($emailInput) ||
            !is_string($phoneInput)
        ) {

            $message = "Invalid information. Please enter your details again.";

        } else {

            $name = trim($nameInput);
            $email = strtolower(trim($emailInput));
            $phone = trim($phoneInput);

            // Remove control characters.
            $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
            $phone = preg_replace('/[\x00-\x1F\x7F]/u', '', $phone);

            // --------------------------------------------------
            // VALIDATE NAME
            // --------------------------------------------------

            if ($name === "") {

                $message = "Please enter your name.";

            } elseif (strlen($name) > 100) {

                $message = "Your name is too long.";

            } elseif (!preg_match("/^[\p{L}\p{M} .'-]+$/u", $name)) {

                $message = "Please enter a valid name.";

            }

            // --------------------------------------------------
            // VALIDATE EMAIL
            // --------------------------------------------------

            if ($message === "") {

                if (
                    $email === "" ||
                    strlen($email) > 254 ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL)
                ) {

                    $message = "Please enter a valid email address.";
                }
            }

            // --------------------------------------------------
            // VALIDATE MOBILE NUMBER
            // --------------------------------------------------

            if ($message === "") {

                $digitsOnly = preg_replace('/\D/', '', $phone);

                if (
                    $phone === "" ||
                    !preg_match('/^[0-9+\-\s()]+$/', $phone) ||
                    strlen($digitsOnly) < 7 ||
                    strlen($digitsOnly) > 15 ||
                    strlen($phone) > 20
                ) {

                    $message = "Please enter a valid mobile number.";
                }
            }

            // --------------------------------------------------
            // SAVE OR REUSE CUSTOMER
            // --------------------------------------------------

            if ($message === "") {

                try {

                    $pdo->beginTransaction();

                    // Find existing customer by email.
                    $existingCustomerQuery = $pdo->prepare("
                        SELECT id, name, email, phone, status
                        FROM customers
                        WHERE email = ?
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $existingCustomerQuery->execute([$email]);

                    $existingCustomer = $existingCustomerQuery->fetch(
                        PDO::FETCH_ASSOC
                    );

                    if ($existingCustomer) {

                        if ($existingCustomer["status"] !== "active") {

                            throw new RuntimeException(
                                "This email address belongs to an inactive customer record. Please contact our team."
                            );
                        }

                        $customerId = (int)$existingCustomer["id"];

                        // Update contact details without changing password.
                        $updateCustomer = $pdo->prepare("
                            UPDATE customers
                            SET name = ?,
                                phone = ?
                            WHERE id = ?
                            LIMIT 1
                        ");

                        $updateCustomer->execute([
                            $name,
                            $phone,
                            $customerId
                        ]);

                    } else {

                        // Create customer without a password.
                        $insertCustomer = $pdo->prepare("
                            INSERT INTO customers (
                                name,
                                email,
                                phone,
                                password,
                                status
                            ) VALUES (
                                ?, ?, ?, NULL, 'active'
                            )
                        ");

                        $insertCustomer->execute([
                            $name,
                            $email,
                            $phone
                        ]);

                        $customerId = (int)$pdo->lastInsertId();
                    }

                    $pdo->commit();

                    // --------------------------------------------------
                    // START CUSTOMER ENQUIRY SESSION
                    // --------------------------------------------------

                    session_regenerate_id(true);

                    $_SESSION["customer_id"] = $customerId;
                    $_SESSION["customer_name"] = $name;
                    $_SESSION["customer_email"] = $email;
                    $_SESSION["property_enquiry_flow"] = true;

                    // Rotate CSRF token.
                    $_SESSION["csrf_token"] = bin2hex(
                        random_bytes(32)
                    );

                    // Proceed to second page.
                    header("Location: property-enquiry.php");
                    exit;

                } catch (Throwable $exception) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log(
                        "Customer enquiry details saving failed: " .
                        $exception->getMessage()
                    );

                    $message = $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : "We could not save your details. Please try again.";

                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="CSS/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <title>Customer Details | Olivera Home Developers</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Montserrat', sans-serif;
            background: #f5f2eb;
            color: #17243a;
        }

        .page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 125px 20px 45px;
        }

        .enquiry-card {
            width: 100%;
            max-width: 1050px;
            min-height: 560px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(15, 35, 60, 0.12);
        }

        /* LEFT SIDE */

        .brand-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 45px;
            background: linear-gradient(145deg, #04132C, #071D42);
            border-right: 3px solid #D49B27;
            color: #ffffff;
            text-align: center;
        }

        .brand-logo {
            width: 100%;
            max-width: 280px;
            height: 220px;
            object-fit: contain;
            background: #ffffff;
            border-radius: 12px;
            padding: 12px;
        }

        .brand-name {
            margin-top: 25px;
            font-size: 23px;
            font-weight: bold;
            line-height: 1.5;
        }

        .brand-tagline {
            margin-top: 10px;
            color: #F8D365;
            font-size: 13px;
            letter-spacing: 2px;
        }

        /* RIGHT SIDE */

        .form-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 55px 45px;
        }

        .form-panel h1 {
            margin: 0 0 12px;
            color: #071D42;
            font-size: 29px;
        }

        .form-description {
            margin: 0 0 32px;
            color: #64748b;
            line-height: 1.7;
        }

        .form-group {
            margin-bottom: 23px;
        }

        label {
            display: block;
            margin-bottom: 9px;
            color: #334155;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            transition: 0.2s;
        }

        input:focus {
            border-color: #D49B27;
            box-shadow: 0 0 0 3px rgba(212, 155, 39, 0.16);
        }

        /* BUTTON ALIGNED TO RIGHT */

        .button-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 35px;
        }

        .next-button {
            min-width: 155px;
            padding: 15px 28px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #F8D365, #D49B27);
            color: #08152B;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .next-button:hover {
            background: #F8D365;
            transform: translateY(-1px);
        }

        /* MESSAGE */

        .message {
            padding: 13px 15px;
            margin-bottom: 22px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .footer-note {
            margin-top: 28px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.6;
        }

        /* MOBILE */

        @media (max-width: 750px) {

            .page-wrapper {
                padding: 15px;
            }

            .enquiry-card {
                grid-template-columns: 1fr;
                max-width: 520px;
            }

            .brand-panel {
                padding: 25px 20px;
            }

            .brand-logo {
                max-width: 180px;
                height: 125px;
            }

            .brand-name {
                margin-top: 15px;
                font-size: 19px;
            }

            .brand-tagline {
                font-size: 11px;
            }

            .form-panel {
                padding: 32px 24px;
            }

            .form-panel h1 {
                font-size: 25px;
            }

            .button-row {
                margin-top: 28px;
            }

            .next-button {
                width: auto;
                min-width: 150px;
            }
        }

    </style>

</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-wrapper">

    <main class="enquiry-card">

        <!-- LEFT SIDE: COMPANY LOGO -->

        <section class="brand-panel">

            <?php if ($websiteLogo !== ""): ?>

                <img
                    src="<?php echo enquiryStartEscape($websiteLogo); ?>"
                    alt="Olivera Home Developers Logo"
                    class="brand-logo"
                >

            <?php else: ?>

                <div class="brand-name">
                    OLIVERA HOME DEVELOPERS
                </div>

            <?php endif; ?>

            <div class="brand-name">
                OLIVERA HOME DEVELOPERS PVT. LTD.
            </div>

            <div class="brand-tagline">
                BUILDING YOUR BETTER TOMORROW
            </div>

        </section>

        <!-- RIGHT SIDE: CUSTOMER DETAILS -->

        <section class="form-panel">

            <h1>Let's Get Started</h1>

            <p class="form-description">
                Please share your contact details so our property team
                can assist you with suitable property options.
            </p>

            <?php if ($message !== ""): ?>

                <div class="message error">
                    <?php echo enquiryStartEscape($message); ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="property-enquiry-start.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo enquiryStartEscape($csrfToken); ?>"
                >

                <div class="form-group">

                    <label for="name">Full Name *</label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="100"
                        placeholder="Enter your full name"
                        value="<?php echo enquiryStartEscape($name); ?>"
                        autocomplete="name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="email">Email ID *</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="254"
                        placeholder="Enter your email address"
                        value="<?php echo enquiryStartEscape($email); ?>"
                        autocomplete="email"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="phone">Mobile Number *</label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        maxlength="20"
                        placeholder="Enter your mobile number"
                        value="<?php echo enquiryStartEscape($phone); ?>"
                        autocomplete="tel"
                        required
                    >

                </div>

                <div class="button-row">

                    <button type="submit" class="next-button">
                        Next &nbsp; →
                    </button>

                </div>

            </form>

            <p class="footer-note">
                Your information will be saved securely and used
                by Olivera Home Developers to respond to your enquiry.
            </p>

        </section>

    </main>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
