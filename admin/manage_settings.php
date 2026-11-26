
<?php

// --------------------------------------------------
// WEBSITE SETTINGS
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

requirePermission("manage_settings");

// --------------------------------------------------
// HELPER FUNCTION
// --------------------------------------------------

function manageSettingsEscape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    $_SESSION["csrf_token"] === ""
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

// --------------------------------------------------
// SAVE SETTINGS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";
    $sessionToken = $_SESSION["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        $sessionToken === "" ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(403);
        exit("Invalid request. Please reload the page.");
    }

    $websiteNameInput = $_POST["website_name"] ?? "";
    $websiteEmailInput = $_POST["website_email"] ?? "";
    $websitePhoneInput = $_POST["website_phone"] ?? "";
    $websiteAddressInput = $_POST["website_address"] ?? "";

    if (
        !is_string($websiteNameInput) ||
        !is_string($websiteEmailInput) ||
        !is_string($websitePhoneInput) ||
        !is_string($websiteAddressInput)
    ) {

        $message = "Invalid settings input.";
        $messageType = "error";

    } else {

        $settings = [
            "website_name" => trim($websiteNameInput),
            "website_email" => trim($websiteEmailInput),
            "website_phone" => trim($websitePhoneInput),
            "website_address" => trim($websiteAddressInput)
        ];

        $newLogoPath = null;
        $newLogoAbsolutePath = null;

        // --------------------------------------------------
        // VALIDATE TEXT SETTINGS
        // --------------------------------------------------

        if ($settings["website_name"] === "") {

            $message = "Website name is required.";

        } elseif (strlen($settings["website_name"]) > 150) {

            $message = "Website name cannot exceed 150 characters.";

        } elseif (strlen($settings["website_email"]) > 254) {

            $message = "Email address is too long.";

        } elseif (
            $settings["website_email"] !== "" &&
            !filter_var(
                $settings["website_email"],
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $message = "Please enter a valid email address.";

        } elseif (strlen($settings["website_phone"]) > 30) {

            $message = "Phone number cannot exceed 30 characters.";

        } elseif (strlen($settings["website_address"]) > 2000) {

            $message = "Website address is too long.";
        }

        // --------------------------------------------------
        // VALIDATE LOGO UPLOAD
        // --------------------------------------------------

        if ($message === "") {

            if (
                isset($_FILES["website_logo"]) &&
                is_array($_FILES["website_logo"]) &&
                ($_FILES["website_logo"]["error"] ?? UPLOAD_ERR_NO_FILE)
                    !== UPLOAD_ERR_NO_FILE
            ) {

                $file = $_FILES["website_logo"];

                if (
                    !isset($file["error"]) ||
                    !isset($file["tmp_name"]) ||
                    !isset($file["size"]) ||
                    !is_string($file["tmp_name"]) ||
                    !is_int($file["error"]) ||
                    !is_int($file["size"])
                ) {

                    $message = "Invalid logo upload.";

                } elseif ($file["error"] !== UPLOAD_ERR_OK) {

                    $message = "Logo upload failed. Please try again.";

                } elseif (!is_uploaded_file($file["tmp_name"])) {

                    $message = "Invalid uploaded file.";

                } elseif ($file["size"] <= 0 || $file["size"] > 2 * 1024 * 1024) {

                    $message = "Logo must not be larger than 2 MB.";

                } else {

                    $imageInfo = @getimagesize($file["tmp_name"]);

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($file["tmp_name"]);

                    $allowedTypes = [
                        "image/jpeg" => "jpg",
                        "image/png" => "png",
                        "image/webp" => "webp"
                    ];

                    if (
                        !$imageInfo ||
                        !is_string($mimeType) ||
                        !isset($allowedTypes[$mimeType]) ||
                        ($imageInfo["mime"] ?? "") !== $mimeType
                    ) {

                        $message =
                            "Only JPG, PNG and WebP images are allowed.";

                    } elseif (
                        ($imageInfo[0] ?? 0) > 5000 ||
                        ($imageInfo[1] ?? 0) > 5000
                    ) {

                        $message = "Logo image dimensions are too large.";

                    } else {

                        // --------------------------------------------------
                        // SAVE LOGO FILE
                        // --------------------------------------------------

                        $uploadDirectory = __DIR__ . "/../uploads/";

                        $realUploadDirectory = realpath($uploadDirectory);

                        if (
                            $realUploadDirectory === false ||
                            !is_dir($realUploadDirectory)
                        ) {

                            $message = "Upload directory does not exist.";

                        } elseif (!is_writable($realUploadDirectory)) {

                            $message = "Upload directory is not writable.";

                        } else {

                            $fileName = bin2hex(random_bytes(16))
                                . "." . $allowedTypes[$mimeType];

                            $destination =
                                $realUploadDirectory .
                                DIRECTORY_SEPARATOR .
                                $fileName;

                            if (
                                move_uploaded_file(
                                    $file["tmp_name"],
                                    $destination
                                )
                            ) {

                                $newLogoPath = "uploads/" . $fileName;
                                $newLogoAbsolutePath = $destination;

                            } else {

                                $message =
                                    "Unable to save the logo. Please try again.";
                            }
                        }
                    }
                }
            }
        }

        // --------------------------------------------------
        // SAVE SETTINGS TO DATABASE
        // --------------------------------------------------

        if ($message === "") {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO settings (
                        setting_key,
                        setting_value
                    )
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value)
                ");

                foreach ($settings as $key => $value) {

                    $stmt->execute([
                        $key,
                        $value
                    ]);
                }

                if ($newLogoPath !== null) {

                    $stmt->execute([
                        "website_logo",
                        $newLogoPath
                    ]);
                }

                $pdo->commit();

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                $message = "Website settings saved successfully!";
                $messageType = "success";

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($exception->getMessage());

                // Remove newly uploaded logo if database save failed.
                if (
                    $newLogoAbsolutePath !== null &&
                    is_file($newLogoAbsolutePath)
                ) {
                    @unlink($newLogoAbsolutePath);
                }

                $message = "Something went wrong. Please try again.";
                $messageType = "error";
            }
        }

        if ($message !== "" && $messageType === "") {
            $messageType = "error";
        }
    }
}

// --------------------------------------------------
// LOAD SAVED SETTINGS
// --------------------------------------------------

$stmt = $pdo->query("
    SELECT setting_key, setting_value
    FROM settings
");

$savedSettings = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $savedSettings[$row["setting_key"]] = $row["setting_value"];
}

$websiteName = $savedSettings["website_name"] ?? "";
$websiteEmail = $savedSettings["website_email"] ?? "";
$websitePhone = $savedSettings["website_phone"] ?? "";
$websiteAddress = $savedSettings["website_address"] ?? "";
$websiteLogo = $savedSettings["website_logo"] ?? "";

// --------------------------------------------------
// VALIDATE SAVED LOGO PATH
// --------------------------------------------------

$validLogo = "";

if (
    is_string($websiteLogo) &&
    preg_match(
        '/\Auploads\/[a-f0-9]{32}\.(jpg|png|webp)\z/i',
        $websiteLogo
    )
) {

    $logoAbsolutePath = __DIR__ . "/../" . $websiteLogo;

    if (is_file($logoAbsolutePath) && is_readable($logoAbsolutePath)) {
        $validLogo = $websiteLogo;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Website Settings | Olivera Home Developers</title>

    <style>

        :root {
            --navy: #071b38;
            --navy-light: #102b50;
            --gold: #d6a63b;
            --gold-light: #edca76;
            --ivory: #f7f5ef;
            --text: #172b46;
            --muted: #68768a;
            --border: #e9e2d4;
            --surface: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--ivory);
            color: var(--text);
            line-height: 1.5;
        }

        /* SIDEBAR */

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--navy);
            color: white;
            padding: 25px 15px;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-brand {
            padding: 0 12px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.13);
            margin-bottom: 23px;
        }

        .sidebar-brand h2 {
            font-size: 19px;
            margin: 0 0 6px;
            color: white;
        }

        .sidebar-brand p {
            margin: 0;
            font-size: 11px;
            color: #bdcde0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 6px;
        }

        .sidebar-menu a,
        .sidebar-logout-button {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 7px;
            color: #dbe5f0;
            text-decoration: none;
            font-size: 13px;
            transition: 0.2s ease;
            background: transparent;
            border: none;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
        }

        .sidebar-menu a:hover,
        .sidebar-logout-button:hover {
            background: var(--navy-light);
            color: white;
        }

        .sidebar-menu a.active {
            background: var(--navy-light);
            color: var(--gold-light);
            font-weight: 600;
            border-left: 3px solid var(--gold);
            padding-left: 11px;
        }

        .menu-icon {
            width: 20px;
            text-align: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .sidebar-logout-form {
            margin: 0;
        }

        /* MAIN CONTENT */

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .settings-page {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 0 40px;
        }

        /* HEADER */

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 25px;
            padding: 25px 28px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .settings-heading {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .settings-heading-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: var(--ivory);
            color: var(--gold);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 23px;
        }

        .settings-header h1 {
            color: var(--navy);
            font-size: 26px;
            margin: 0 0 7px;
            letter-spacing: -0.4px;
        }

        .settings-header p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
            margin: 0;
        }

        .settings-back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            background: var(--navy);
            color: white;
            border: 1px solid var(--navy);
            text-decoration: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .settings-back-btn:hover {
            background: var(--navy-light);
        }

        /* ALERTS */

        .settings-alert {
            padding: 14px 17px;
            margin-bottom: 22px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .settings-alert.success {
            background: #edf7f1;
            color: #256746;
            border: 1px solid #d6ecdf;
        }

        .settings-alert.error {
            background: #fff0ef;
            color: #a83d3d;
            border: 1px solid #f2d6d3;
        }

        /* PANELS */

        .settings-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 11px;
            margin-bottom: 22px;
            box-shadow: 0 4px 18px rgba(7, 27, 56, 0.05);
            overflow: hidden;
        }

        .settings-panel-header {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 21px 25px;
            background: var(--ivory);
            border-bottom: 1px solid var(--border);
        }

        .settings-section-icon {
            width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--gold);
            background: white;
            border: 1px solid var(--border);
            border-radius: 9px;
            font-size: 18px;
        }

        .settings-panel-header h2 {
            color: var(--navy);
            font-size: 16px;
            margin: 0 0 5px;
        }

        .settings-panel-header p {
            color: var(--muted);
            font-size: 12px;
            margin: 0;
            line-height: 1.5;
        }

        .settings-panel-body {
            padding: 26px;
        }

        /* FORM */

        .settings-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px 23px;
        }

        .settings-form-group {
            min-width: 0;
            margin-bottom: 20px;
        }

        .settings-form-group.full-width {
            grid-column: 1 / -1;
        }

        .settings-form-group label {
            display: block;
            color: var(--navy);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .settings-form-group input,
        .settings-form-group textarea {
            display: block;
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            color: var(--text);
            background: white;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .settings-form-group input {
            min-height: 44px;
        }

        .settings-form-group textarea {
            min-height: 115px;
            resize: vertical;
            line-height: 1.6;
        }

        .settings-form-group input:focus,
        .settings-form-group textarea:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214, 166, 59, 0.14);
        }

        .settings-form-group input::placeholder,
        .settings-form-group textarea::placeholder {
            color: #a0acbc;
        }

        .settings-help {
            display: block;
            color: var(--muted);
            font-size: 11px;
            margin-top: 8px;
            line-height: 1.5;
        }

        /* LOGO */

        .settings-logo-box {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            padding: 21px;
            background: var(--ivory);
            border: 1px dashed #d6c9a9;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .settings-logo-preview {
            width: 210px;
            height: 125px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 13px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .settings-logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .settings-logo-empty {
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        .settings-logo-info {
            flex: 1;
            min-width: 190px;
        }

        .settings-logo-info h3 {
            color: var(--navy);
            font-size: 15px;
            margin: 0 0 9px;
        }

        .settings-logo-info p {
            color: var(--muted);
            font-size: 12px;
            margin: 0;
            line-height: 1.9;
        }

        .settings-file-input {
            padding: 10px !important;
            background: #fbfaf7 !important;
            border: 1px dashed #d6c9a9 !important;
        }

        .settings-file-input::file-selector-button {
            padding: 8px 12px;
            margin-right: 12px;
            border: 0;
            border-radius: 6px;
            background: var(--navy);
            color: white;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        /* SAVE BUTTON */

        .settings-save-area {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            padding: 19px 22px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .settings-save-note {
            margin-right: auto;
            color: var(--muted);
            font-size: 12px;
        }

        .settings-save-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 23px;
            background: var(--navy);
            color: white;
            border: 1px solid var(--gold);
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .settings-save-btn:hover {
            background: var(--navy-light);
            color: var(--gold-light);
        }

        /* RESPONSIVE */

        @media (max-width: 760px) {

            .main-content {
                padding: 20px;
            }

            .settings-header {
                padding: 20px;
            }

            .settings-header h1 {
                font-size: 22px;
            }

            .settings-form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .settings-form-group.full-width {
                grid-column: auto;
            }

            .settings-panel-body {
                padding: 19px;
            }

            .settings-logo-box {
                gap: 16px;
                padding: 16px;
            }

            .settings-logo-preview {
                width: 100%;
                max-width: 230px;
            }
        }

        @media (max-width: 650px) {

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 17px;
            }

            .sidebar-brand {
                margin-bottom: 14px;
                padding-bottom: 14px;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }

            .sidebar-menu li {
                margin: 0;
            }

            .sidebar-menu a,
            .sidebar-logout-button {
                padding: 10px 8px;
                font-size: 11px;
                gap: 7px;
            }

            .main-content {
                margin-left: 0;
                padding: 17px;
            }

            .settings-page {
                padding-bottom: 25px;
            }

            .settings-heading {
                gap: 10px;
            }

            .settings-heading-icon {
                width: 40px;
                height: 40px;
                font-size: 19px;
            }

            .settings-back-btn {
                width: 100%;
            }

            .settings-panel-header {
                padding: 17px;
            }

            .settings-save-area {
                align-items: stretch;
                flex-direction: column;
            }

            .settings-save-note {
                margin: 0 0 5px;
            }

            .settings-save-btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="sidebar-brand">
        <h2>Olivera Home Developers</h2>
        <p>Admin Control Panel</p>
    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="dashboard.php">
                <span class="menu-icon">▦</span>
                Dashboard
            </a>
        </li>

        <li>
            <a href="manage_gallery.php">
                <span class="menu-icon">▧</span>
                Manage Gallery
            </a>
        </li>

        <li>
            <a href="manage_admins.php">
                <span class="menu-icon">♙</span>
                Manage Admins
            </a>
        </li>

        <li>
            <a href="manage_users.php">
                <span class="menu-icon">♧</span>
                Manage Users
            </a>
        </li>

        <li>
            <a href="manage_customers.php">
                <span class="menu-icon">♧</span>
                Manage Customers
            </a>
        </li>

        <li>
            <a href="manage_content.php">
                <span class="menu-icon">▤</span>
                Manage Content
            </a>
        </li>

        <li>
            <a href="manage_settings.php" class="active">
                <span class="menu-icon">⚙</span>
                Website Settings
            </a>
        </li>

        <li>
            <a href="activity_logs.php">
                <span class="menu-icon">◷</span>
                Activity Logs
            </a>
        </li>

        <li>
            <a href="profile.php">
                <span class="menu-icon">♙</span>
                My Profile
            </a>
        </li>

        <li>
            <a href="change_password.php">
                <span class="menu-icon">🔒</span>
                Change Password
            </a>
        </li>

        <li>
            <form action="logout.php" method="POST" class="sidebar-logout-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo manageSettingsEscape($_SESSION["csrf_token"]); ?>"
                >

                <button type="submit" class="sidebar-logout-button">
                    <span class="menu-icon">↪</span>
                    Logout
                </button>

            </form>
        </li>

    </ul>

</aside>

<!-- MAIN CONTENT -->

<main class="main-content">

    <div class="settings-page">

        <!-- PAGE HEADER -->

        <div class="settings-header">

            <div class="settings-heading">

                <div class="settings-heading-icon">⚙</div>

                <div>

                    <h1>Website Settings</h1>

                    <p>
                        Welcome,
                        <?php echo manageSettingsEscape($_SESSION["admin_name"] ?? "Admin"); ?>.
                        Manage your website information and branding.
                    </p>

                </div>

            </div>

            <a href="dashboard.php" class="settings-back-btn">
                ← Back to Dashboard
            </a>

        </div>

        <!-- SUCCESS / ERROR MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="settings-alert <?php echo manageSettingsEscape($messageType === "success" ? "success" : "error"); ?>">

                <?php echo manageSettingsEscape($message); ?>

            </div>

        <?php endif; ?>

        <!-- SETTINGS FORM -->

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo manageSettingsEscape($_SESSION["csrf_token"]); ?>"
            >

            <!-- GENERAL INFORMATION -->

            <section class="settings-panel">

                <div class="settings-panel-header">

                    <div class="settings-section-icon">▤</div>

                    <div>

                        <h2>General Website Information</h2>

                        <p>
                            Update your website's basic contact information.
                        </p>

                    </div>

                </div>

                <div class="settings-panel-body">

                    <div class="settings-form-grid">

                        <div class="settings-form-group">

                            <label for="website_name">
                                Website Name
                            </label>

                            <input
                                type="text"
                                id="website_name"
                                name="website_name"
                                value="<?php echo manageSettingsEscape($websiteName); ?>"
                                maxlength="150"
                                placeholder="Enter website name"
                                required
                            >

                        </div>

                        <div class="settings-form-group">

                            <label for="website_email">
                                Website Email
                            </label>

                            <input
                                type="email"
                                id="website_email"
                                name="website_email"
                                value="<?php echo manageSettingsEscape($websiteEmail); ?>"
                                maxlength="254"
                                placeholder="Enter website email"
                            >

                        </div>

                        <div class="settings-form-group">

                            <label for="website_phone">
                                Contact Number
                            </label>

                            <input
                                type="text"
                                id="website_phone"
                                name="website_phone"
                                value="<?php echo manageSettingsEscape($websitePhone); ?>"
                                maxlength="30"
                                placeholder="Enter contact number"
                            >

                        </div>

                        <div class="settings-form-group full-width">

                            <label for="website_address">
                                Website Address
                            </label>

                            <textarea
                                id="website_address"
                                name="website_address"
                                rows="4"
                                maxlength="2000"
                                placeholder="Enter your website address"
                            ><?php echo manageSettingsEscape($websiteAddress); ?></textarea>

                        </div>

                    </div>

                </div>

            </section>

            <!-- WEBSITE LOGO -->

            <section class="settings-panel">

                <div class="settings-panel-header">

                    <div class="settings-section-icon">▧</div>

                    <div>

                        <h2>Website Logo</h2>

                        <p>
                            Manage the logo displayed on your website.
                        </p>

                    </div>

                </div>

                <div class="settings-panel-body">

                    <div class="settings-logo-box">

                        <div class="settings-logo-preview">

                            <?php if ($validLogo !== ""): ?>

                                <img
                                    src="../<?php echo manageSettingsEscape($validLogo); ?>"
                                    alt="Current Website Logo"
                                >

                            <?php else: ?>

                                <div class="settings-logo-empty">
                                    No logo uploaded yet
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="settings-logo-info">

                            <h3>Current Website Logo</h3>

                            <p>

                                <?php if ($validLogo !== ""): ?>

                                    Your website logo is currently saved.

                                <?php else: ?>

                                    Upload a logo to display your website branding.

                                <?php endif; ?>

                                <br>
                                Supported formats: JPG, PNG and WebP.
                                <br>
                                Maximum file size: 2 MB.

                            </p>

                        </div>

                    </div>

                    <div class="settings-form-group">

                        <label for="website_logo">
                            Upload New Website Logo
                        </label>

                        <input
                            type="file"
                            id="website_logo"
                            name="website_logo"
                            class="settings-file-input"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >

                        <span class="settings-help">
                            Leave this field empty to keep your current logo.
                        </span>

                    </div>

                </div>

            </section>

            <!-- SAVE BUTTON -->

            <div class="settings-save-area">

                <span class="settings-save-note">
                    Save your changes when you're finished.
                </span>

                <button
                    type="submit"
                    class="settings-save-btn"
                >
                    ✓ Save Website Settings
                </button>

            </div>

        </form>

    </div>

</main>

</body>
</html>
