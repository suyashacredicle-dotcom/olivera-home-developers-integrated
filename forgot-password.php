
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

// --------------------------------------------------
// FIXED ACCOUNT TYPE
// --------------------------------------------------

if (!defined("PASSWORD_RESET_ACCOUNT_TYPE")) {
    define("PASSWORD_RESET_ACCOUNT_TYPE", "customer");
}

$accountType = PASSWORD_RESET_ACCOUNT_TYPE;

if (
    !is_string($accountType) ||
    !in_array($accountType, ["admin", "customer"], true)
) {
    http_response_code(403);
    exit("Invalid password recovery page.");
}

$isAdmin = ($accountType === "admin");

// --------------------------------------------------
// LOAD APPLICATION CONFIGURATION
// --------------------------------------------------

require_once __DIR__ . "/config/app.php";

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

// --------------------------------------------------
// LOAD DATABASE AND EMAIL
// --------------------------------------------------

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/mail.php";
// --------------------------------------------------
// WEBSITE BRANDING
// --------------------------------------------------

$settingsStmt = $pdo->query("
    SELECT setting_key, setting_value
    FROM settings
    WHERE setting_key IN ('website_name')
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
// RATE LIMIT SETTINGS
// --------------------------------------------------

$maximumIpRequests = 5;
$maximumEmailRequests = 3;

// --------------------------------------------------
// PAGE VARIABLES
// --------------------------------------------------

$message = "";
$messageType = "info";
$email = "";

$pageTitle = $isAdmin
    ? "Admin Forgot Password?"
    : "Customer Forgot Password?";

$emailLabel = $isAdmin
    ? "Registered Admin Email Address"
    : "Registered Customer Email Address";

$description = $isAdmin
    ? "Enter your registered admin email address. If an active admin account exists, we will send you a secure password reset link."
    : "Enter your registered customer email address. If an active customer account exists, we will send you a secure password reset link.";

$loginLink = "login.php";

$loginText = $isAdmin
    ? "Back to Admin Login"
    : "Back to Customer Login";

// --------------------------------------------------
// PROCESS FORM
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $emailInput = $_POST["email"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    $email = is_string($emailInput)
        ? strtolower(trim($emailInput))
        : "";

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
        $messageType = "error";

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 254
    ) {

        $message = "Please enter a valid email address.";
        $messageType = "error";

    } else {

        $rateLimitLocks = [];

        try {

            // --------------------------------------------------
            // CREATE RATE LIMIT IDENTIFIERS
            // --------------------------------------------------

            $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "";

            if (
                !is_string($ipAddress) ||
                $ipAddress === ""
            ) {
                $ipAddress = "unknown";
            }

            $ipHash = hash("sha256", $ipAddress);
            $emailHash = hash("sha256", $email);

            // --------------------------------------------------
            // ACQUIRE DATABASE LOCKS
            // --------------------------------------------------
            // These locks serialize requests for the same IP
            // and email address.
            // Sorting prevents lock-order deadlocks.

            $lockKeys = [
                "pwreset:" . substr(
                    hash("sha256", $accountType . "|ip|" . $ipHash),
                    0,
                    56
                ),

                "pwreset:" . substr(
                    hash("sha256", $accountType . "|email|" . $emailHash),
                    0,
                    56
                )
            ];

            sort($lockKeys, SORT_STRING);

            foreach ($lockKeys as $lockKey) {

                $lockStmt = $pdo->prepare("
                    SELECT GET_LOCK(?, 5)
                ");

                $lockStmt->execute([$lockKey]);

                $lockResult = $lockStmt->fetchColumn();

                if ((int) $lockResult !== 1) {

                    throw new RuntimeException(
                        "Unable to acquire password reset rate limit lock."
                    );
                }

                $rateLimitLocks[] = $lockKey;
            }

            // --------------------------------------------------
            // RECORD REQUEST
            // --------------------------------------------------

            $requestStmt = $pdo->prepare("
                INSERT INTO password_reset_requests (
                    account_type,
                    ip_hash,
                    email_hash
                )
                VALUES (?, ?, ?)
            ");

            $requestStmt->execute([
                $accountType,
                $ipHash,
                $emailHash
            ]);

            // --------------------------------------------------
            // COUNT IP REQUESTS
            // --------------------------------------------------

            $ipCountStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM password_reset_requests
                WHERE account_type = ?
                  AND ip_hash = ?
                  AND requested_at >= DATE_SUB(
                      NOW(),
                      INTERVAL 15 MINUTE
                  )
            ");

            $ipCountStmt->execute([
                $accountType,
                $ipHash
            ]);

            $ipRequestCount = (int) $ipCountStmt->fetchColumn();

            // --------------------------------------------------
            // COUNT EMAIL REQUESTS
            // --------------------------------------------------

            $emailCountStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM password_reset_requests
                WHERE account_type = ?
                  AND email_hash = ?
                  AND requested_at >= DATE_SUB(
                      NOW(),
                      INTERVAL 15 MINUTE
                  )
            ");

            $emailCountStmt->execute([
                $accountType,
                $emailHash
            ]);

            $emailRequestCount = (int) $emailCountStmt->fetchColumn();

            // --------------------------------------------------
            // ENFORCE RATE LIMITS
            // --------------------------------------------------

            if (
                $ipRequestCount > $maximumIpRequests ||
                $emailRequestCount > $maximumEmailRequests
            ) {

                $message = "Too many password reset requests. Please try again after 15 minutes.";

                $messageType = "error";

            } else {

                // --------------------------------------------------
                // FIND ACCOUNT OF FIXED TYPE ONLY
                // --------------------------------------------------

                if ($isAdmin) {

                    $stmt = $pdo->prepare("
                        SELECT id, name, email
                        FROM admins
                        WHERE email = ?
                          AND status = 'active'
                        LIMIT 1
                    ");

                } else {

                    $stmt = $pdo->prepare("
                        SELECT id, name, email
                        FROM customers
                        WHERE email = ?
                          AND status = 'active'
                        LIMIT 1
                    ");
                }

                $stmt->execute([$email]);

                $account = $stmt->fetch(PDO::FETCH_ASSOC);

                // --------------------------------------------------
                // GENERATE RESET TOKEN
                // --------------------------------------------------

                if ($account) {

                    $token = bin2hex(random_bytes(32));

                    $tokenHash = hash("sha256", $token);

                    $expiresAt = date(
                        "Y-m-d H:i:s",
                        time() + 3600
                    );

                    // --------------------------------------------------
                    // TRANSACTION: REPLACE OLD RESET TOKENS
                    // --------------------------------------------------

                    $pdo->beginTransaction();

                    try {

                        // Lock the account row before replacing tokens.

                        if ($isAdmin) {

                            $accountLockStmt = $pdo->prepare("
                                SELECT id
                                FROM admins
                                WHERE id = ?
                                  AND email = ?
                                  AND status = 'active'
                                LIMIT 1
                                FOR UPDATE
                            ");

                        } else {

                            $accountLockStmt = $pdo->prepare("
                                SELECT id
                                FROM customers
                                WHERE id = ?
                                  AND email = ?
                                  AND status = 'active'
                                LIMIT 1
                                FOR UPDATE
                            ");
                        }

                        $accountLockStmt->execute([
                            $account["id"],
                            $account["email"]
                        ]);

                        $lockedAccount = $accountLockStmt->fetch(
                            PDO::FETCH_ASSOC
                        );

                        if (!$lockedAccount) {

                            throw new RuntimeException(
                                "Account is no longer available."
                            );
                        }

                        // Remove previous unused tokens.

                        $deleteStmt = $pdo->prepare("
                            DELETE FROM password_resets
                            WHERE account_type = ?
                              AND account_id = ?
                              AND used_at IS NULL
                        ");

                        $deleteStmt->execute([
                            $accountType,
                            $account["id"]
                        ]);

                        // Store hashed token.

                        $insertStmt = $pdo->prepare("
                            INSERT INTO password_resets (
                                account_type,
                                account_id,
                                email,
                                token_hash,
                                expires_at
                            )
                            VALUES (?, ?, ?, ?, ?)
                        ");

                        $insertStmt->execute([
                            $accountType,
                            $account["id"],
                            $account["email"],
                            $tokenHash,
                            $expiresAt
                        ]);

                        $pdo->commit();

                    } catch (Throwable $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        throw $e;
                    }

                    // --------------------------------------------------
                    // CREATE RESET LINK
                    // --------------------------------------------------

                    $resetLink =
                        rtrim(APP_BASE_URL, "/") .
                        "/reset-password.php" .
                        "?type=" . urlencode($accountType) .
                        "&token=" . urlencode($token);

                    // --------------------------------------------------
                    // SEND EMAIL
                    // --------------------------------------------------

                    $emailSent = sendPasswordResetEmail(
                        $account["email"],
                        $account["name"],
                        $resetLink
                    );

                    // --------------------------------------------------
                    // HANDLE EMAIL FAILURE
                    // --------------------------------------------------

                    if (!$emailSent) {

                        $cleanupStmt = $pdo->prepare("
                            DELETE FROM password_resets
                            WHERE token_hash = ?
                              AND used_at IS NULL
                        ");

                        $cleanupStmt->execute([
                            $tokenHash
                        ]);

                        error_log(
                            ucfirst($accountType) .
                            " password reset email could not be sent."
                        );
                    }
                }

                // --------------------------------------------------
                // GENERIC RESPONSE
                // --------------------------------------------------

                $message = "If an active account exists with that email address, a password reset link will be sent. Please check your inbox.";

                $messageType = "success";
            }

            // --------------------------------------------------
            // CLEAN OLD RATE LIMIT RECORDS
            // --------------------------------------------------

            if (random_int(1, 100) === 1) {

                $cleanupRequestsStmt = $pdo->prepare("
                    DELETE FROM password_reset_requests
                    WHERE requested_at < DATE_SUB(
                        NOW(),
                        INTERVAL 24 HOUR
                    )
                ");

                $cleanupRequestsStmt->execute();
            }

        } catch (Throwable $e) {

            error_log(
                ucfirst($accountType) .
                " password reset request error: " .
                $e->getMessage()
            );

            $message = "We could not process your request right now. Please try again later.";

            $messageType = "error";

        } finally {

            // --------------------------------------------------
            // RELEASE DATABASE LOCKS
            // --------------------------------------------------

            foreach ($rateLimitLocks as $lockKey) {

                try {

                    $releaseStmt = $pdo->prepare("
                        SELECT RELEASE_LOCK(?)
                    ");

                    $releaseStmt->execute([$lockKey]);

                } catch (Throwable $e) {

                    error_log(
                        "Password reset lock release error: " .
                        $e->getMessage()
                    );
                }
            }
        }
    }
}

// --------------------------------------------------
// ROTATE CSRF TOKEN
// --------------------------------------------------

$_SESSION["csrf_token"] = bin2hex(random_bytes(32));

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

<title><?= e($pageTitle) ?> - <?= e($websiteName) ?></title>
    <style>

        * {
            box-sizing: border-box;
        }

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
    --success: #27845b;
    --danger: #c44d52;
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
        rgba(214, 166, 59, 0.15),
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

            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.20);

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

        .message.info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 9px;

            color: #334155;
            font-size: 13px;
            font-weight: 600;
        }

        input[type="email"] {
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

        input[type="email"]::placeholder {
            color: #94a3b8;
        }

        input[type="email"]:hover {
            border-color: #94a3b8;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: var(--primary);

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        button {
    background: var(--gold);
    color: var(--navy);
}

button:hover {
    background: var(--gold-light);
    box-shadow: 0 5px 14px rgba(214, 166, 59, 0.25);
}

        button:active {
            transform: translateY(1px);
        }

        button:focus-visible {
            outline: 3px solid #93c5fd;
            outline-offset: 3px;
        }

        .links {
            margin-top: 25px;
            padding-top: 20px;

            border-top: 1px solid #f1f5f9;

            text-align: center;
        }

        .links a {
            color: var(--primary);
            font-size: 13px;
            font-weight: 600;

            text-decoration: none;

            transition: color 0.2s ease;
        }

        .links a:hover {
            color: var(--primary-hover);
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
                <?= $isAdmin ? "Administration Portal" : "Customer Portal" ?>
            </div>

        </div>

        <section class="container">

            <div class="heading">

                <h2><?= e($pageTitle) ?></h2>

            </div>

            <p class="description">
                <?= e($description) ?>
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

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION["csrf_token"]) ?>"
                >

                <div class="form-group">

                    <label for="email">
                        <?= e($emailLabel) ?>
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e($email) ?>"
                        maxlength="254"
                        placeholder="Enter your registered email"
                        autocomplete="email"
                        required
                    >

                </div>

                <button type="submit">
                    Send Password Reset Link
                </button>

            </form>

            <div class="links">

                <a href="<?= e($loginLink) ?>">
                    &larr; <?= e($loginText) ?>
                </a>

            </div>

        </section>

        <div class="footer">
    &copy; <?= date("Y") ?>
    <?= e($websiteName) ?>. All rights reserved.
</div>
    </main>

</body>

</html>
