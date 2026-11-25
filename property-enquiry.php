
<?php

// --------------------------------------------------
// PROPERTY ENQUIRY FORM AND CRM INTEGRATION
// NO CUSTOMER LOGIN REQUIRED
// --------------------------------------------------

require_once __DIR__ . "/config/database.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --------------------------------------------------
// ESCAPE OUTPUT
// --------------------------------------------------

function propertyEnquiryEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// --------------------------------------------------
// CUSTOMER SESSION CHECK
// --------------------------------------------------

$customerId = filter_var(
    $_SESSION["customer_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$customerId || $customerId <= 0) {
    header("Location: property-enquiry-start.php");
    exit;
}

// --------------------------------------------------
// LOAD CUSTOMER DETAILS
// --------------------------------------------------

$customerQuery = $pdo->prepare("
    SELECT id, name, email, phone, status
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$customerQuery->execute([$customerId]);

$customer = $customerQuery->fetch(PDO::FETCH_ASSOC);

if (!$customer || $customer["status"] !== "active") {

    unset(
        $_SESSION["customer_id"],
        $_SESSION["customer_name"],
        $_SESSION["customer_email"]
    );

    header("Location: property-enquiry-start.php");
    exit;
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
        "Property enquiry logo loading failed: " .
        $exception->getMessage()
    );
}

// --------------------------------------------------
// DROPDOWN OPTIONS
// --------------------------------------------------

$budgetOptions = [
    "Below ₹10 Lakhs",
    "₹10–20 Lakhs",
    "₹20–40 Lakhs",
    "₹40–60 Lakhs",
    "₹60 Lakhs–₹1 Crore",
    "₹1–2 Crore",
    "Above ₹2 Crore"
];

$locationOptions = [
    "Delhi",
    "Noida",
    "Gurgaon",
    "Jewar",
    "Vrindavan",
    "Uttarakhand",
    "Goa",
    "Dubai"
];

$plotSizeOptions = [
    "50 Gaj",
    "100 Gaj",
    "150 Gaj",
    "200 Gaj",
    "300 Gaj",
    "500 Gaj",
    "1000 Gaj"
];

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

$message = "";
$messageType = "error";
$submittedSuccessfully = false;

// --------------------------------------------------
// FORM SUBMISSION
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {

        $message = "Invalid form submission. Please refresh the page and try again.";

    } else {

        $budgetSelection = $_POST["budget"] ?? "";
        $locationSelection = $_POST["preferred_location"] ?? "";
        $plotSizeSelection = $_POST["plot_size"] ?? "";

        $budgetCustom = $_POST["budget_custom"] ?? "";
        $locationCustom = $_POST["location_custom"] ?? "";
        $plotSizeCustom = $_POST["plot_size_custom"] ?? "";

        $customerAddress = $_POST["customer_address"] ?? "";

        $allStringValues = [
            $budgetSelection,
            $locationSelection,
            $plotSizeSelection,
            $budgetCustom,
            $locationCustom,
            $plotSizeCustom,
            $customerAddress
        ];

        foreach ($allStringValues as $value) {

            if (!is_string($value)) {
                $message = "Invalid form information. Please try again.";
                break;
            }
        }

        if ($message === "") {

            $budgetSelection = trim($budgetSelection);
            $locationSelection = trim($locationSelection);
            $plotSizeSelection = trim($plotSizeSelection);

            $budgetCustom = trim($budgetCustom);
            $locationCustom = trim($locationCustom);
            $plotSizeCustom = trim($plotSizeCustom);

            $customerAddress = trim($customerAddress);

            // --------------------------------------------------
            // VALIDATE BUDGET
            // --------------------------------------------------

            if ($budgetSelection === "custom") {

                if ($budgetCustom === "") {

                    $message = "Please enter your custom budget.";

                } elseif (strlen($budgetCustom) > 90) {

                    $message = "Your custom budget is too long.";

                } else {

                    $budget = "Custom: " . $budgetCustom;
                }

            } elseif (in_array($budgetSelection, $budgetOptions, true)) {

                $budget = $budgetSelection;

            } else {

                $message = "Please select a valid budget.";
            }

            // --------------------------------------------------
            // VALIDATE LOCATION
            // --------------------------------------------------

            if ($message === "") {

                if ($locationSelection === "custom") {

                    if ($locationCustom === "") {

                        $message = "Please enter your custom preferred location.";

                    } elseif (strlen($locationCustom) > 140) {

                        $message = "Your custom location is too long.";

                    } else {

                        $preferredLocation = "Custom: " . $locationCustom;
                    }

                } elseif (in_array($locationSelection, $locationOptions, true)) {

                    $preferredLocation = $locationSelection;

                } else {

                    $message = "Please select a valid preferred location.";
                }
            }

            // --------------------------------------------------
            // VALIDATE PLOT SIZE
            // --------------------------------------------------

            if ($message === "") {

                if ($plotSizeSelection === "custom") {

                    if ($plotSizeCustom === "") {

                        $message = "Please enter your custom plot size.";

                    } elseif (strlen($plotSizeCustom) > 90) {

                        $message = "Your custom plot size is too long.";

                    } else {

                        $plotSize = "Custom: " . $plotSizeCustom;
                    }

                } elseif (in_array($plotSizeSelection, $plotSizeOptions, true)) {

                    $plotSize = $plotSizeSelection;

                } else {

                    $message = "Please select a valid plot size.";
                }
            }

            // --------------------------------------------------
            // VALIDATE ADDRESS
            // --------------------------------------------------

            if ($message === "") {

                if ($customerAddress === "") {

                    $message = "Please enter your full address.";

                } elseif (strlen($customerAddress) > 10000) {

                    $message = "Your address exceeds the allowed length.";
                }
            }

            // --------------------------------------------------
            // SAVE ENQUIRY AND CREATE CRM LEAD
            // --------------------------------------------------

            if ($message === "") {

                $sourcePlatform = $_SESSION["ad_source_platform"] ?? "website";

                $allowedSources = [
                    "website",
                    "facebook",
                    "instagram",
                    "google",
                    "youtube",
                    "other"
                ];

                if (
                    !is_string($sourcePlatform) ||
                    !in_array($sourcePlatform, $allowedSources, true)
                ) {
                    $sourcePlatform = "website";
                }

                $sourceCampaign = $_SESSION["ad_source_campaign"] ?? "";

                if (!is_string($sourceCampaign)) {
                    $sourceCampaign = "";
                }

                $sourceCampaign = substr(
                    trim($sourceCampaign),
                    0,
                    255
                );

                // --------------------------------------------------
                // UTM AND CLICK TRACKING
                // --------------------------------------------------

                $trackingFields = [
                    "utm_medium" => 150,
                    "utm_content" => 255,
                    "utm_term" => 255,
                    "fbclid" => 255,
                    "gclid" => 255,
                    "gbraid" => 255,
                    "wbraid" => 255,
                    "ttclid" => 255
                ];

                $tracking = [];

                foreach ($trackingFields as $field => $maxLength) {

                    $value = $_SESSION["ad_" . $field] ?? "";

                    if (!is_string($value)) {
                        $value = "";
                    }

                    $value = trim($value);

                    $value = preg_replace(
                        '/[\x00-\x1F\x7F]/u',
                        '',
                        $value
                    );

                    $tracking[$field] = substr(
                        $value,
                        0,
                        $maxLength
                    );

                    if ($tracking[$field] === "") {
                        $tracking[$field] = null;
                    }
                }

                // --------------------------------------------------
                // MAP SOURCE TO CRM
                // --------------------------------------------------

                $leadSourceMap = [
                    "website" => "Website",
                    "facebook" => "Facebook",
                    "instagram" => "Instagram",
                    "google" => "Google Ads",
                    "youtube" => "YouTube",
                    "other" => "Other"
                ];

                $leadSource = $leadSourceMap[$sourcePlatform];

                // --------------------------------------------------
                // CRM LEAD MESSAGE
                // --------------------------------------------------

                $leadMessage =
                    "Property Enquiry Details:\n\n" .
                    "Budget: " . $budget . "\n" .
                    "Plot Size: " . $plotSize . "\n" .
                    "Preferred Location: " . $preferredLocation . "\n" .
                    "Full Address: " . $customerAddress . "\n" .
                    "Advertisement Source: " . $leadSource . "\n" .
                    "Campaign: " . $sourceCampaign;

                try {

                    $pdo->beginTransaction();

                    // --------------------------------------------------
                    // SAVE PROPERTY ENQUIRY
                    // --------------------------------------------------

                    $enquiryInsert = $pdo->prepare("
                        INSERT INTO property_enquiries (
                            customer_id,
                            budget,
                            preferred_location,
                            plot_size,
                            customer_address,
                            source_platform,
                            source_campaign,
                            utm_medium,
                            utm_content,
                            utm_term,
                            fbclid,
                            gclid,
                            gbraid,
                            wbraid,
                            ttclid,
                            status
                        ) VALUES (
                            :customer_id,
                            :budget,
                            :preferred_location,
                            :plot_size,
                            :customer_address,
                            :source_platform,
                            :source_campaign,
                            :utm_medium,
                            :utm_content,
                            :utm_term,
                            :fbclid,
                            :gclid,
                            :gbraid,
                            :wbraid,
                            :ttclid,
                            'new'
                        )
                    ");

                    $enquiryInsert->execute([
                        ":customer_id" => $customerId,
                        ":budget" => $budget,
                        ":preferred_location" => $preferredLocation,
                        ":plot_size" => $plotSize,
                        ":customer_address" => $customerAddress,
                        ":source_platform" => $sourcePlatform,
                        ":source_campaign" => $sourceCampaign !== ""
                            ? $sourceCampaign
                            : null,
                        ":utm_medium" => $tracking["utm_medium"],
                        ":utm_content" => $tracking["utm_content"],
                        ":utm_term" => $tracking["utm_term"],
                        ":fbclid" => $tracking["fbclid"],
                        ":gclid" => $tracking["gclid"],
                        ":gbraid" => $tracking["gbraid"],
                        ":wbraid" => $tracking["wbraid"],
                        ":ttclid" => $tracking["ttclid"]
                    ]);

                    $propertyEnquiryId = (int)$pdo->lastInsertId();

                    // --------------------------------------------------
                    // CREATE LINKED CRM LEAD
                    // --------------------------------------------------

                    $leadInsert = $pdo->prepare("
                        INSERT INTO leads (
                            customer_id,
                            property_enquiry_id,
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
                        ) VALUES (
                            ?, ?, ?, ?, ?, 'Plot', ?,
                            NULL, NULL, ?, ?, 'new', 'medium',
                            NULL, NULL
                        )
                    ");

                    $leadInsert->execute([
                        $customerId,
                        $propertyEnquiryId,
                        $customer["name"],
                        $customer["phone"],
                        $customer["email"],
                        $preferredLocation,
                        $leadMessage,
                        $leadSource
                    ]);

                    // --------------------------------------------------
                    // COMMIT
                    // --------------------------------------------------

                    $pdo->commit();

                    // --------------------------------------------------
                    // ROTATE CSRF TOKEN
                    // --------------------------------------------------

                    $_SESSION["csrf_token"] = bin2hex(
                        random_bytes(32)
                    );

                    // --------------------------------------------------
                    // CLEAR COMPLETED ADVERTISEMENT SESSION
                    // --------------------------------------------------

                    unset(
                        $_SESSION["property_enquiry_flow"],
                        $_SESSION["ad_source_platform"],
                        $_SESSION["ad_source_campaign"],
                        $_SESSION["ad_utm_medium"],
                        $_SESSION["ad_utm_content"],
                        $_SESSION["ad_utm_term"],
                        $_SESSION["ad_fbclid"],
                        $_SESSION["ad_gclid"],
                        $_SESSION["ad_gbraid"],
                        $_SESSION["ad_wbraid"],
                        $_SESSION["ad_ttclid"],
                        $_SESSION["ad_source_platform"],
                        $_SESSION["landing_tracking"]
                    );

                    header("Location: property-enquiry.php?submitted=1");
                    exit;

                } catch (Throwable $exception) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log(
                        "Property enquiry and CRM lead creation failed: " .
                        $exception->getMessage()
                    );

                    $message = "Your enquiry could not be submitted. Please try again.";
                }
            }
        }
    }
}

// --------------------------------------------------
// SUCCESS MESSAGE
// --------------------------------------------------

if (
    isset($_GET["submitted"]) &&
    $_GET["submitted"] === "1"
) {

    $message = "Thank you! Your property enquiry has been submitted successfully. Our team will contact you soon.";

    $messageType = "success";
    $submittedSuccessfully = true;
}

// --------------------------------------------------
// PRESERVE FORM VALUES
// --------------------------------------------------

$oldBudget = is_string($_POST["budget"] ?? null)
    ? $_POST["budget"]
    : "";

$oldLocation = is_string($_POST["preferred_location"] ?? null)
    ? $_POST["preferred_location"]
    : "";

$oldPlotSize = is_string($_POST["plot_size"] ?? null)
    ? $_POST["plot_size"]
    : "";

$oldBudgetCustom = is_string($_POST["budget_custom"] ?? null)
    ? $_POST["budget_custom"]
    : "";

$oldLocationCustom = is_string($_POST["location_custom"] ?? null)
    ? $_POST["location_custom"]
    : "";

$oldPlotSizeCustom = is_string($_POST["plot_size_custom"] ?? null)
    ? $_POST["plot_size_custom"]
    : "";

$oldAddress = is_string($_POST["customer_address"] ?? null)
    ? $_POST["customer_address"]
    : "";

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
    <title>Property Enquiry | Olivera Home Developers</title>

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
            max-width: 1150px;
            min-height: 650px;
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(15, 35, 60, 0.12);
        }

        /* LEFT BRAND PANEL */

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

        /* RIGHT FORM PANEL */

        .form-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 45px;
        }

        .form-panel h1 {
            margin: 0 0 10px;
            color: #071D42;
            font-size: 29px;
        }

        .form-description {
            margin: 0 0 25px;
            color: #64748b;
            line-height: 1.7;
        }

        .form-group {
            margin-bottom: 19px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            background: #ffffff;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #D49B27;
            box-shadow: 0 0 0 3px rgba(212, 155, 39, 0.16);
        }

        textarea {
            min-height: 105px;
            resize: vertical;
        }

        .custom-input {
            display: none;
            margin-top: 10px;
        }

        .custom-input.visible {
            display: block;
        }

        /* RIGHT-ALIGNED SUBMIT BUTTON */

        .button-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 28px;
        }

        .submit-button {
            min-width: 190px;
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #F8D365, #D49B27);
            color: #08152B;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .submit-button:hover {
            background: #F8D365;
            transform: translateY(-1px);
        }

        /* MESSAGES */

        .message {
            margin-bottom: 20px;
            padding: 14px;
            border-radius: 8px;
            line-height: 1.6;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success-content {
            padding: 25px 10px;
            text-align: center;
        }

        .success-content h2 {
            color: #166534;
            margin-bottom: 12px;
        }

        .footer-note {
            margin-top: 25px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.6;
        }

        /* MOBILE */

        @media (max-width: 800px) {

            .page-wrapper {
                padding: 15px;
            }

            .enquiry-card {
                grid-template-columns: 1fr;
                max-width: 560px;
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
                padding: 30px 24px;
            }

            .form-panel h1 {
                font-size: 25px;
            }
        }

        @media (max-width: 420px) {

            .form-panel {
                padding: 25px 18px;
            }

            .submit-button {
                width: 100%;
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
                    src="<?php echo propertyEnquiryEscape($websiteLogo); ?>"
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

        <!-- RIGHT SIDE: PROPERTY ENQUIRY -->

        <section class="form-panel">

            <h1>Property Enquiry</h1>

            <p class="form-description">
                Please share your property requirements.
                Our team will contact you with suitable options.
            </p>

            <?php if ($message !== ""): ?>

                <div class="message <?php echo propertyEnquiryEscape($messageType); ?>">
                    <?php echo propertyEnquiryEscape($message); ?>
                </div>

            <?php endif; ?>

            <?php if (!$submittedSuccessfully): ?>

                <form method="POST" action="property-enquiry.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo propertyEnquiryEscape($csrfToken); ?>"
                    >

                    <!-- BUDGET -->

                    <div class="form-group">

                        <label for="budget">Your Budget *</label>

                        <select
                            id="budget"
                            name="budget"
                            required
                        >

                            <option value="">Select Your Budget</option>

                            <?php foreach ($budgetOptions as $option): ?>

                                <option
                                    value="<?php echo propertyEnquiryEscape($option); ?>"
                                    <?php echo $oldBudget === $option ? "selected" : ""; ?>
                                >
                                    <?php echo propertyEnquiryEscape($option); ?>
                                </option>

                            <?php endforeach; ?>

                            <option
                                value="custom"
                                <?php echo $oldBudget === "custom" ? "selected" : ""; ?>
                            >
                                Custom / Other
                            </option>

                        </select>

                        <input
                            type="text"
                            id="budget_custom"
                            name="budget_custom"
                            class="custom-input <?php echo $oldBudget === "custom" ? "visible" : ""; ?>"
                            maxlength="90"
                            placeholder="Enter your budget"
                            value="<?php echo propertyEnquiryEscape($oldBudgetCustom); ?>"
                            <?php echo $oldBudget === "custom" ? "required" : "disabled"; ?>
                        >

                    </div>

                    <!-- PREFERRED LOCATION -->

                    <div class="form-group">

                        <label for="preferred_location">Preferred Location *</label>

                        <select
                            id="preferred_location"
                            name="preferred_location"
                            required
                        >

                            <option value="">Select Preferred Location</option>

                            <?php foreach ($locationOptions as $option): ?>

                                <option
                                    value="<?php echo propertyEnquiryEscape($option); ?>"
                                    <?php echo $oldLocation === $option ? "selected" : ""; ?>
                                >
                                    <?php echo propertyEnquiryEscape($option); ?>
                                </option>

                            <?php endforeach; ?>

                            <option
                                value="custom"
                                <?php echo $oldLocation === "custom" ? "selected" : ""; ?>
                            >
                                Custom / Other
                            </option>

                        </select>

                        <input
                            type="text"
                            id="location_custom"
                            name="location_custom"
                            class="custom-input <?php echo $oldLocation === "custom" ? "visible" : ""; ?>"
                            maxlength="140"
                            placeholder="Enter your preferred location"
                            value="<?php echo propertyEnquiryEscape($oldLocationCustom); ?>"
                            <?php echo $oldLocation === "custom" ? "required" : "disabled"; ?>
                        >

                    </div>

                    <!-- REQUIRED PLOT SIZE -->

                    <div class="form-group">

                        <label for="plot_size">Target Property / Required Plot Size *</label>

                        <select
                            id="plot_size"
                            name="plot_size"
                            required
                        >

                            <option value="">Select Required Plot Size</option>

                            <?php foreach ($plotSizeOptions as $option): ?>

                                <option
                                    value="<?php echo propertyEnquiryEscape($option); ?>"
                                    <?php echo $oldPlotSize === $option ? "selected" : ""; ?>
                                >
                                    <?php echo propertyEnquiryEscape($option); ?>
                                </option>

                            <?php endforeach; ?>

                            <option
                                value="custom"
                                <?php echo $oldPlotSize === "custom" ? "selected" : ""; ?>
                            >
                                Custom / Other
                            </option>

                        </select>

                        <input
                            type="text"
                            id="plot_size_custom"
                            name="plot_size_custom"
                            class="custom-input <?php echo $oldPlotSize === "custom" ? "visible" : ""; ?>"
                            maxlength="90"
                            placeholder="Enter your required property or plot size"
                            value="<?php echo propertyEnquiryEscape($oldPlotSizeCustom); ?>"
                            <?php echo $oldPlotSize === "custom" ? "required" : "disabled"; ?>
                        >

                    </div>

                    <!-- CUSTOMER ADDRESS -->

                    <div class="form-group">

                        <label for="customer_address">Your Full Address *</label>

                        <textarea
                            id="customer_address"
                            name="customer_address"
                            maxlength="10000"
                            placeholder="Enter your complete address"
                            required
                        ><?php echo propertyEnquiryEscape($oldAddress); ?></textarea>

                    </div>

                    <!-- RIGHT-ALIGNED SUBMIT BUTTON -->

                    <div class="button-row">

                        <button type="submit" class="submit-button">
                            Submit Enquiry &nbsp; →
                        </button>

                    </div>

                </form>

            <?php else: ?>

                <div class="success-content">

                    <h2>Thank You!</h2>

                    <p>
                        Your property enquiry has been submitted successfully.
                        Our team will contact you soon.
                    </p>

                </div>

            <?php endif; ?>

            <p class="footer-note">
                Olivera Home Developers Pvt. Ltd.
                <br>
                Building Your Better Tomorrow
            </p>

        </section>

    </main>

</div>

<script>

// --------------------------------------------------
// CUSTOM DROPDOWN INPUTS
// --------------------------------------------------

function setupCustomDropdown(selectId, inputId) {

    const dropdown = document.getElementById(selectId);
    const customInput = document.getElementById(inputId);

    if (!dropdown || !customInput) {
        return;
    }

    function updateCustomInput() {

        const isCustom = dropdown.value === "custom";

        customInput.classList.toggle("visible", isCustom);

        customInput.disabled = !isCustom;
        customInput.required = isCustom;

        if (!isCustom) {
            customInput.value = "";
        }
    }

    dropdown.addEventListener("change", updateCustomInput);

    updateCustomInput();
}

setupCustomDropdown("budget", "budget_custom");

setupCustomDropdown(
    "preferred_location",
    "location_custom"
);

setupCustomDropdown(
    "plot_size",
    "plot_size_custom"
);

</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
