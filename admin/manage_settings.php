
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("manage_settings");

// --------------------------------------------------
// HELPER FUNCTION
// --------------------------------------------------

function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// --------------------------------------------------
// CREATE CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
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
        // LOGO UPLOAD VALIDATION
        // --------------------------------------------------

        if ($message === "") {

            if (
                isset($_FILES["website_logo"]) &&
                $_FILES["website_logo"]["error"] !== UPLOAD_ERR_NO_FILE
            ) {

                $file = $_FILES["website_logo"];

                if (
                    !isset($file["error"]) ||
                    !isset($file["tmp_name"]) ||
                    !isset($file["size"]) ||
                    !isset($file["name"]) ||
                    !is_string($file["tmp_name"])
                ) {

                    $message = "Invalid logo upload.";

                } elseif ($file["error"] !== UPLOAD_ERR_OK) {

                    $message = "Logo upload failed. Please try again.";

                } elseif (!is_uploaded_file($file["tmp_name"])) {

                    $message = "Invalid uploaded file.";

                } elseif ($file["size"] > 2 * 1024 * 1024) {

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
                        !isset($allowedTypes[$mimeType]) ||
                        $imageInfo["mime"] !== $mimeType
                    ) {

                        $message =
                            "Only JPG, PNG and WebP images are allowed.";

                    } else {

                        // --------------------------------------------------
                        // SAVE LOGO FILE
                        // --------------------------------------------------

                        $uploadDirectory = __DIR__ . "/../uploads/";

                        if (!is_dir($uploadDirectory)) {

                            $message = "Upload directory does not exist.";

                        } elseif (!is_writable($uploadDirectory)) {

                            $message = "Upload directory is not writable.";

                        } else {

                            $fileName = bin2hex(random_bytes(16))
                                . "." . $allowedTypes[$mimeType];

                            $destination = $uploadDirectory . $fileName;

                            if (
                                move_uploaded_file(
                                    $file["tmp_name"],
                                    $destination
                                )
                            ) {

                                $newLogoPath = "uploads/" . $fileName;

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

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($e->getMessage());

                if ($newLogoPath !== null) {

                    $newFile = __DIR__ . "/../" . $newLogoPath;

                    if (is_file($newFile)) {
                        unlink($newFile);
                    }
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

$validLogo = "";

if (
    is_string($websiteLogo) &&
    preg_match(
        '/^uploads\/[a-f0-9]{32}\.(jpg|png|webp)$/i',
        $websiteLogo
    ) &&
    is_file(__DIR__ . "/../" . $websiteLogo)
) {
    $validLogo = $websiteLogo;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Website Settings | Admin Panel</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>

        * {
            box-sizing: border-box;
        }

        .settings-page {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            padding: 12px 8px 40px;
            color: #1e293b;
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 28px;
            padding: 26px 28px;
            background: linear-gradient(120deg, #ffffff, #f1f6ff);
            border: 1px solid #e2e8f0;
            border-radius: 15px;
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
            background: #e8efff;
            color: #2454b8;
            border-radius: 12px;
            font-size: 23px;
        }

        .settings-header h1 {
            color: #172554;
            font-size: 26px;
            font-weight: 750;
            margin: 0 0 7px;
            letter-spacing: -0.5px;
        }

        .settings-header p {
            color: #64748b;
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
            background: #ffffff;
            color: #334155;
            border: 1px solid #dbe3ef;
            text-decoration: none;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 650;
            transition: 0.2s ease;
        }

        .settings-back-btn:hover {
            background: #eff4ff;
            border-color: #b8c9ef;
            color: #1e40af;
        }

        .settings-alert {
            padding: 15px 18px;
            margin-bottom: 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .settings-alert.success {
            background: #ecfdf3;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .settings-alert.error {
            background: #fff1f2;
            color: #9f1239;
            border: 1px solid #fecdd3;
        }

        .settings-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 23px;
            box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
            overflow: hidden;
        }

        .settings-panel-header {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 21px 25px;
            background: #fbfcfe;
            border-bottom: 1px solid #edf0f5;
        }

        .settings-section-icon {
            width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #2454b8;
            background: #eaf0ff;
            border-radius: 10px;
            font-size: 18px;
        }

        .settings-panel-header h2 {
            color: #172554;
            font-size: 16px;
            font-weight: 750;
            margin: 0 0 5px;
        }

        .settings-panel-header p {
            color: #8290a3;
            font-size: 12px;
            margin: 0;
            line-height: 1.5;
        }

        .settings-panel-body {
            padding: 26px;
        }

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
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .settings-form-group input,
        .settings-form-group textarea {
            display: block;
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d8e0eb;
            border-radius: 9px;
            font-family: inherit;
            font-size: 13px;
            color: #334155;
            background: #ffffff;
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
            border-color: #4776d0;
            box-shadow: 0 0 0 3px rgba(59, 111, 205, 0.10);
        }

        .settings-form-group input::placeholder,
        .settings-form-group textarea::placeholder {
            color: #a0acbc;
        }

        .settings-help {
            display: block;
            color: #8794a7;
            font-size: 11px;
            margin-top: 8px;
            line-height: 1.5;
        }

        .settings-logo-box {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            padding: 21px;
            background: #f8faff;
            border: 1px dashed #cbd7e9;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .settings-logo-preview {
            width: 210px;
            height: 125px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 13px;
            background: #ffffff;
            border: 1px solid #e3e9f2;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .settings-logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .settings-logo-empty {
            color: #9aa7b8;
            font-size: 12px;
            text-align: center;
        }

        .settings-logo-info {
            flex: 1;
            min-width: 190px;
        }

        .settings-logo-info h3 {
            color: #172554;
            font-size: 15px;
            font-weight: 750;
            margin: 0 0 9px;
        }

        .settings-logo-info p {
            color: #64748b;
            font-size: 12px;
            margin: 0;
            line-height: 1.9;
        }

        .settings-file-input {
            padding: 10px !important;
            background: #f8fafc !important;
            border: 1px dashed #cbd5e1 !important;
        }

        .settings-file-input::file-selector-button {
            padding: 8px 12px;
            margin-right: 12px;
            border: 0;
            border-radius: 6px;
            background: #e8efff;
            color: #244fa8;
            font-size: 12px;
            font-weight: 650;
            cursor: pointer;
        }

        .settings-save-area {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            padding: 19px 22px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .settings-save-note {
            margin-right: auto;
            color: #8491a3;
            font-size: 12px;
        }

        .settings-save-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 23px;
            background: #2454b8;
            color: #ffffff;
            border: 1px solid #2454b8;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .settings-save-btn:hover {
            background: #1d4295;
            border-color: #1d4295;
            box-shadow: 0 4px 12px rgba(36, 84, 184, 0.18);
            transform: translateY(-1px);
        }

        @media (max-width: 760px) {

            .settings-page {
                padding: 8px 2px 25px;
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

        @media (max-width: 480px) {

            .settings-heading {
                gap: 10px;
            }

            .settings-heading-icon {
                width: 40px;
                height: 40px;
                font-size: 19px;
            }

            .settings-header {
                align-items: flex-start;
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

<div class="dashboard-container">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <h2>Admin Panel</h2>

        <a href="dashboard.php">Dashboard</a>

        <?php
        require __DIR__ . "/includes/gallery_sidebar_link.php";
        ?>

        <a href="manage_admins.php">Manage Admins</a>

        <a href="manage_users.php">Manage Users</a>

        <a href="manage_customers.php">Manage Customers</a>

        <a href="manage_content.php">Manage Content</a>

        <a href="manage_settings.php">Website Settings</a>

        <a href="activity_logs.php">Activity Logs</a>

        <a href="profile.php">My Profile</a>

        <a href="change_password.php">Change Password</a>

        <form action="logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION["csrf_token"]); ?>"
            >

            <button type="submit">Logout</button>

        </form>

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
                            <?php echo e($_SESSION["admin_name"] ?? "Admin"); ?>.
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

                <div class="settings-alert <?php
                    echo $messageType === "success"
                        ? "success"
                        : "error";
                ?>">

                    <?php echo e($message); ?>

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
                    value="<?php echo e($_SESSION["csrf_token"]); ?>"
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
                                    value="<?php echo e($websiteName); ?>"
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
                                    value="<?php echo e($websiteEmail); ?>"
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
                                    value="<?php echo e($websitePhone); ?>"
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
                                ><?php echo e($websiteAddress); ?></textarea>

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
                                        src="../<?php echo e($validLogo); ?>"
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

</div>

</body>

</html>
