
<?php

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Referrer-Policy: no-referrer");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

// --------------------------------------------------
// SECURE SESSION CONFIGURATION
// --------------------------------------------------

$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off" &&
    $_SERVER["HTTPS"] !== ""
);

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}

require_once __DIR__ . "/config/database.php";

// --------------------------------------------------
// WEBSITE BRANDING
// --------------------------------------------------

$settingsStmt = $pdo->query("
    SELECT setting_key, setting_value
    FROM settings
    WHERE setting_key = 'website_name'
");

$brandingSettings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$websiteName = $brandingSettings["website_name"]
    ?? "Olivera Home Developers";

// --------------------------------------------------
// HELPER FUNCTION
// --------------------------------------------------

function e($value)
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
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// --------------------------------------------------
// PAGE VARIABLES
// --------------------------------------------------

$message = "";
$messageType = "error";
$success = false;

$accountType = $_GET["type"] ?? $_POST["type"] ?? "";
$token = $_GET["token"] ?? $_POST["token"] ?? "";

$validRequest = true;
$resetRecord = false;

$isAdmin = (
    is_string($accountType) &&
    $accountType === "admin"
);

$isCustomer = (
    is_string($accountType) &&
    $accountType === "customer"
);

$loginLink = $isAdmin
    ? "admin/login.php"
    : "login.php";

$forgotPasswordLink = $isAdmin
    ? "admin/forgot-password.php"
    : "forgot-password.php";

$loginText = $isAdmin
    ? "Go to Admin Login"
    : "Go to Customer Login";

$pageTitle = $isAdmin
    ? "Admin Password Reset"
    : "Customer Password Reset";

// --------------------------------------------------
// VALIDATE ACCOUNT TYPE AND TOKEN
// --------------------------------------------------

if (
    !is_string($accountType) ||
    !in_array($accountType, ["admin", "customer"], true) ||
    !is_string($token) ||
    !preg_match('/\A[a-f0-9]{64}\z/', $token)
) {
    $validRequest = false;
}

// --------------------------------------------------
// VALIDATE RESET TOKEN
// --------------------------------------------------

if ($validRequest) {
    try {
        $tokenHash = hash("sha256", $token);

        $stmt = $pdo->prepare("
            SELECT
                id,
                account_type,
                account_id,
                email
            FROM password_resets
            WHERE token_hash = ?
              AND account_type = ?
              AND used_at IS NULL
              AND expires_at > NOW()
            LIMIT 1
        ");

        $stmt->execute([
            $tokenHash,
            $accountType
        ]);

        $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRecord) {
            $validRequest = false;
        }

    } catch (Throwable $e) {
        error_log(
            "Password reset validation error: " .
            $e->getMessage()
        );

        $validRequest = false;
    }
}

// --------------------------------------------------
// INVALID OR EXPIRED RESET LINK
// --------------------------------------------------

if (!$validRequest) {
    $message =
        "This password reset link is invalid, expired, or has already been used. Please request a new reset link.";

    $messageType = "error";
}

// --------------------------------------------------
// PROCESS NEW PASSWORD
// --------------------------------------------------

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $validRequest
) {
    $csrfToken = $_POST["csrf_token"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // --------------------------------------------------
    // CSRF VALIDATION
    // --------------------------------------------------

    if (
        !is_string($csrfToken) ||
        !is_string($_SESSION["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $csrfToken)
    ) {
        http_response_code(403);

        $message = "Invalid request. Please refresh the page.";

    } elseif (
        !is_string($newPassword) ||
        !is_string($confirmPassword)
    ) {
        $message = "Invalid password information.";

    } elseif (
        strlen($newPassword) < 12 ||
        strlen($newPassword) > 72
    ) {
        $message = "Password must be between 12 and 72 characters.";

    } elseif (
        !preg_match('/[A-Z]/', $newPassword) ||
        !preg_match('/[a-z]/', $newPassword) ||
        !preg_match('/[0-9]/', $newPassword) ||
        !preg_match('/[^a-zA-Z0-9]/', $newPassword)
    ) {
        $message =
            "Password must contain an uppercase letter, lowercase letter, number and special character.";

    } elseif ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match.";

    } else {
        try {

            // --------------------------------------------------
            // START DATABASE TRANSACTION
            // --------------------------------------------------

            $pdo->beginTransaction();

            // --------------------------------------------------
            // LOCK ACCOUNT FIRST
            // --------------------------------------------------

            if ($isAdmin) {
                $accountStmt = $pdo->prepare("
                    SELECT id
                    FROM admins
                    WHERE id = ?
                      AND email = ?
                      AND status = 'active'
                    LIMIT 1
                    FOR UPDATE
                ");

            } else {
                $accountStmt = $pdo->prepare("
                    SELECT id
                    FROM customers
                    WHERE id = ?
                      AND email = ?
                      AND status = 'active'
                    LIMIT 1
                    FOR UPDATE
                ");
            }

            $accountStmt->execute([
                $resetRecord["account_id"],
                $resetRecord["email"]
            ]);

            $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                throw new RuntimeException(
                    "The account is no longer available."
                );
            }

            // --------------------------------------------------
            // LOCK AND REVALIDATE RESET TOKEN
            // --------------------------------------------------

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    account_type,
                    account_id,
                    email
                FROM password_resets
                WHERE id = ?
                  AND token_hash = ?
                  AND account_type = ?
                  AND used_at IS NULL
                  AND expires_at > NOW()
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                $resetRecord["id"],
                hash("sha256", $token),
                $accountType
            ]);

            $lockedRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$lockedRecord ||
                (int) $lockedRecord["account_id"] !==
                    (int) $resetRecord["account_id"] ||
                $lockedRecord["email"] !== $resetRecord["email"]
            ) {
                throw new RuntimeException(
                    "The reset link is invalid or expired."
                );
            }

            // --------------------------------------------------
            // HASH NEW PASSWORD
            // --------------------------------------------------

            $passwordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            if (!$passwordHash) {
                throw new RuntimeException(
                    "Unable to secure the new password."
                );
            }

            // --------------------------------------------------
            // UPDATE ACCOUNT PASSWORD
            // --------------------------------------------------

            if ($isAdmin) {
                $updateStmt = $pdo->prepare("
                    UPDATE admins
                    SET password = ?
                    WHERE id = ?
                ");

            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE customers
                    SET password = ?
                    WHERE id = ?
                ");
            }

            $updateStmt->execute([
                $passwordHash,
                $lockedRecord["account_id"]
            ]);

            // --------------------------------------------------
            // MARK ALL OUTSTANDING RESET LINKS AS USED
            // --------------------------------------------------

            $usedStmt = $pdo->prepare("
                UPDATE password_resets
                SET used_at = NOW()
                WHERE account_type = ?
                  AND account_id = ?
                  AND used_at IS NULL
            ");

            $usedStmt->execute([
                $accountType,
                $lockedRecord["account_id"]
            ]);

            if ($usedStmt->rowCount() < 1) {
                throw new RuntimeException(
                    "Unable to invalidate reset links."
                );
            }

            // --------------------------------------------------
            // COMMIT TRANSACTION
            // --------------------------------------------------

            $pdo->commit();

            // --------------------------------------------------
            // REGENERATE SESSION ID
            // --------------------------------------------------

            session_regenerate_id(true);

            // --------------------------------------------------
            // ROTATE CSRF TOKEN
            // --------------------------------------------------

            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            // --------------------------------------------------
            // SUCCESS RESPONSE
            // --------------------------------------------------

            $success = true;

            $message =
                "Your password has been changed successfully. You can now log in using your new password.";

            $messageType = "success";

            // Remove the token from page variables.
            $token = "";

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                "Password reset update error: " .
                $e->getMessage()
            );

            $message =
                "Unable to reset your password. The reset link may have expired or already been used.";

            $messageType = "error";
        }
    }
}

// --------------------------------------------------
// ROTATE CSRF TOKEN AFTER FAILED POST
// --------------------------------------------------

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !$success
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>
        <?= e($pageTitle) ?> - <?= e($websiteName) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --navy: #071b38;
            --navy-light: #102b50;
            --primary: #d6a63b;
            --primary-hover: #edca76;
            --text: #172b46;
            --muted: #68768a;
            --border: #e9e2d4;
            --surface: #ffffff;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 30px 18px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-family: "Segoe UI", Arial, sans-serif;

            background:
                radial-gradient(
                    circle at 15% 15%,
                    rgba(214, 166, 59, 0.18),
                    transparent 35%
                ),
                linear-gradient(
                    135deg,
                    var(--navy),
                    var(--navy-light)
                );
        }

        .page-wrapper {
            width: 100%;
            max-width: 460px;
        }

        .brand {
            text-align: center;
            margin-bottom: 25px;
            color: #ffffff;
        }

        .brand-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 14px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 16px;

            background: rgba(214, 166, 59, 0.16);
            border: 1px solid rgba(214, 166, 59, 0.55);

            color: var(--primary-hover);
            font-size: 27px;
            font-weight: 700;
        }

        .brand-name {
            font-size: 21px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .brand-subtitle {
            margin-top: 6px;
            color: #cbd5e1;
            font-size: 13px;
        }

        .container {
            width: 100%;
            padding: 38px;

            background: var(--surface);
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 16px;

            box-shadow:
                0 25px 60px rgba(0,0,0,0.25),
                0 5px 15px rgba(0,0,0,0.08);
        }

        .heading {
            margin-bottom: 10px;
            text-align: center;
        }

        .heading h2 {
            margin: 0;
            color: var(--text);
            font-size: 25px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .description {
            margin: 12px 0 28px;
            color: var(--muted);
            text-align: center;
            font-size: 14px;
            line-height: 1.7;
        }

        .message {
            padding: 14px 15px;
            margin-bottom: 22px;

            border-radius: 9px;
            font-size: 13px;
            line-height: 1.6;
        }

        .success {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 9px;

            color: #334155;
            font-size: 13px;
            font-weight: 600;
        }

        input[type="password"],
        input[type="text"] {
            width: 100%;
            height: 49px;
            padding: 0 15px;

            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 8px;

            color: var(--text);
            font-family: inherit;
            font-size: 14px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        input[type="password"]:focus,
        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);

            box-shadow:
                0 0 0 3px rgba(214, 166, 59, 0.18);
        }

        .password-rules {
            padding: 15px;
            margin-bottom: 20px;

            background: #faf8f2;
            border: 1px solid #e9e2d4;
            border-radius: 9px;

            color: #475569;
            font-size: 12px;
            line-height: 1.9;
        }

        .rules-heading {
            display: block;
            margin-bottom: 5px;

            color: #334155;
            font-weight: 700;
        }

        .show-password {
            display: flex;
            align-items: center;
            gap: 9px;

            margin: 0 0 20px;

            color: #475569;
            font-size: 13px;
            font-weight: 500;

            cursor: pointer;
        }

        .show-password input {
            width: 16px;
            height: 16px;
            margin: 0;

            accent-color: var(--primary);
            cursor: pointer;
        }

        button {
            width: 100%;
            min-height: 49px;
            padding: 13px 18px;

            border: none;
            border-radius: 8px;

            background: var(--primary);
            color: var(--navy);

            font-family: inherit;
            font-size: 14px;
            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        button:hover {
            background: var(--primary-hover);

            box-shadow:
                0 5px 14px rgba(214, 166, 59, 0.25);
        }

        button:active {
            transform: translateY(1px);
        }

        button:focus-visible {
            outline: 3px solid #edca76;
            outline-offset: 3px;
        }

        .links {
            margin-top: 25px;
            padding-top: 20px;

            border-top: 1px solid #f1f5f9;

            text-align: center;
        }

        .links a {
            color: #9a701b;
            font-size: 13px;
            font-weight: 600;

            text-decoration: none;

            transition: color 0.2s ease;
        }

        .links a:hover {
            color: var(--navy);
            text-decoration: underline;
        }

        .footer {
            margin-top: 24px;
            text-align: center;

            color: #cbd5e1;
            font-size: 12px;
        }

        @media (max-width: 480px) {

            body {
                padding: 22px 15px;
            }

            .container {
                padding: 29px 23px;
                border-radius: 13px;
            }

            .heading h2 {
                font-size: 22px;
            }

            .brand-name {
                font-size: 19px;
            }

        }

    </style>

</head>

<body>

    <main class="page-wrapper">

        <div class="brand">

            <div class="brand-icon" aria-hidden="true">
                <?= e(strtoupper(substr($websiteName, 0, 1))) ?>
            </div>

            <div class="brand-name">
                <?= e($websiteName) ?>
            </div>

            <div class="brand-subtitle">
                <?= $isAdmin
                    ? "Administration Portal"
                    : "Customer Portal" ?>
            </div>

        </div>

        <section class="container">

            <div class="heading">

                <h2>
                    <?= $success
                        ? "Password Updated"
                        : "Reset Your Password" ?>
                </h2>

            </div>

            <p class="description">

                <?php if ($success): ?>

                    Your account password has been successfully updated.

                <?php elseif ($validRequest): ?>

                    Create a strong new password for your account.

                <?php else: ?>

                    Your password reset link needs to be renewed.

                <?php endif; ?>

            </p>

            <?php if ($message !== ""): ?>

                <div
                    class="message <?= e($messageType) ?>"
                    role="alert"
                    aria-live="polite"
                >
                    <?= e($message) ?>
                </div>

            <?php endif; ?>

            <?php if ($success): ?>

                <div class="links">

                    <a href="<?= e($loginLink) ?>">
                        <?= e($loginText) ?>
                    </a>

                </div>

            <?php elseif ($validRequest): ?>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION["csrf_token"]) ?>"
                    >

                    <input
                        type="hidden"
                        name="type"
                        value="<?= e($accountType) ?>"
                    >

                    <input
                        type="hidden"
                        name="token"
                        value="<?= e($token) ?>"
                    >

                    <div class="form-group">

                        <label for="new_password">
                            New Password
                        </label>

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            minlength="12"
                            maxlength="72"
                            autocomplete="new-password"
                            placeholder="Enter your new password"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="12"
                            maxlength="72"
                            autocomplete="new-password"
                            placeholder="Confirm your new password"
                            required
                        >

                    </div>

                    <label class="show-password">

                        <input
                            type="checkbox"
                            id="showPasswords"
                        >

                        Show passwords

                    </label>

                    <div class="password-rules">

                        <span class="rules-heading">
                            Your password must contain:
                        </span>

                        • At least 12 characters
                        <br>

                        • One uppercase letter
                        <br>

                        • One lowercase letter
                        <br>

                        • One number
                        <br>

                        • One special character

                    </div>

                    <button type="submit">
                        Reset Password
                    </button>

                </form>

            <?php else: ?>

                <div class="links">

                    <a href="<?= e($forgotPasswordLink) ?>">
                        Request a New Password Reset Link
                    </a>

                </div>

            <?php endif; ?>

        </section>

        <div class="footer">
            &copy; <?= date("Y") ?>
            <?= e($websiteName) ?>. All rights reserved.
        </div>

    </main>

    <script>

        // --------------------------------------------------
        // REMOVE TOKEN FROM BROWSER ADDRESS BAR
        // --------------------------------------------------

        if (
            window.history &&
            window.history.replaceState
        ) {

            const cleanUrl =
                window.location.pathname +
                "?type=" +
                encodeURIComponent(
                    <?= json_encode($accountType) ?>
                );

            window.history.replaceState(
                null,
                document.title,
                cleanUrl
            );

        }

        // --------------------------------------------------
        // SHOW / HIDE PASSWORD
        // --------------------------------------------------

        const showPasswords =
            document.getElementById("showPasswords");

        if (showPasswords) {

            showPasswords.addEventListener(
                "change",
                function () {

                    const fields = [
                        document.getElementById("new_password"),
                        document.getElementById("confirm_password")
                    ];

                    fields.forEach(function (field) {

                        if (field) {

                            field.type = showPasswords.checked
                                ? "text"
                                : "password";

                        }

                    });

                }
            );

        }

    </script>

</body>

</html>
