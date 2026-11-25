
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

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/activity_logger.php";

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
// REDIRECT ALREADY LOGGED-IN ADMIN
// --------------------------------------------------

if (isset($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit;
}

// --------------------------------------------------
// LOAD WEBSITE BRANDING
// --------------------------------------------------

$brandingStmt = $pdo->prepare("
    SELECT setting_key, setting_value
    FROM settings
    WHERE setting_key IN ('website_name', 'website_logo')
");

$brandingStmt->execute();

$brandingSettings = $brandingStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$websiteName = $brandingSettings["website_name"]
    ?? "Olivera Home Developers";

$websiteLogo = "";
$savedLogo = $brandingSettings["website_logo"] ?? "";

if (
    is_string($savedLogo) &&
    preg_match(
        '/^uploads\/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i',
        $savedLogo
    ) &&
    is_file(__DIR__ . "/../" . $savedLogo)
) {
    $websiteLogo = "../" . $savedLogo;
}

$error = "";

// --------------------------------------------------
// CREATE CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// --------------------------------------------------
// LOGIN PROCESS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {
        http_response_code(403);
        exit("Invalid request. Please reload the page.");
    }

    $emailInput = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";

    $email = is_string($emailInput)
        ? strtolower(trim($emailInput))
        : "";

    $password = is_string($password)
        ? $password
        : "";

    $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";

    $email = substr($email, 0, 254);
    $ipAddress = substr($ipAddress, 0, 45);

    $validInput = (
        filter_var($email, FILTER_VALIDATE_EMAIL) &&
        strlen($email) <= 254 &&
        $password !== "" &&
        strlen($password) <= 4096
    );

    // --------------------------------------------------
    // CHECK FAILED LOGIN ATTEMPTS
    // --------------------------------------------------

    $emailAttempts = 0;
    $ipAttempts = 0;

    if ($validInput) {

        $attemptStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM admin_login_attempts
            WHERE email = ?
              AND ip_address = ?
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");

        $attemptStmt->execute([
            $email,
            $ipAddress
        ]);

        $emailAttempts = (int) $attemptStmt->fetchColumn();
    }

    $ipStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM admin_login_attempts
        WHERE ip_address = ?
          AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");

    $ipStmt->execute([$ipAddress]);

    $ipAttempts = (int) $ipStmt->fetchColumn();

    // --------------------------------------------------
    // BLOCK EXCESSIVE LOGIN ATTEMPTS
    // --------------------------------------------------

    if ($emailAttempts >= 5 || $ipAttempts >= 20) {

        $error = "Too many login attempts. Please wait 1 minute before trying again.";

    } else {

        $admin = false;

        // --------------------------------------------------
        // VERIFY ADMIN ACCOUNT
        // --------------------------------------------------

        if ($validInput) {

            $stmt = $pdo->prepare("
                SELECT id, name, email, password, role, status
                FROM admins
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // --------------------------------------------------
        // CHECK PASSWORD AND ACCOUNT STATUS
        // --------------------------------------------------

        if (
            $admin &&
            $admin["status"] === "active" &&
            password_verify($password, $admin["password"])
        ) {

            session_regenerate_id(true);

            $_SESSION["admin_id"] = (int) $admin["id"];
            $_SESSION["admin_name"] = $admin["name"];
            $_SESSION["admin_role"] = $admin["role"];

            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            // Clear failed attempts.

            $clearStmt = $pdo->prepare("
                DELETE FROM admin_login_attempts
                WHERE email = ?
                  AND ip_address = ?
            ");

            $clearStmt->execute([
                $email,
                $ipAddress
            ]);

            logActivity(
                $pdo,
                "ADMIN_LOGIN",
                "Admin " . $admin["name"] . " logged in successfully."
            );

            header("Location: dashboard.php");
            exit;

        } else {

            // Record failed attempt.

            $insertStmt = $pdo->prepare("
                INSERT INTO admin_login_attempts
                    (email, ip_address)
                VALUES (?, ?)
            ");

            $insertStmt->execute([
                $email,
                $ipAddress
            ]);

            $error = "Invalid email or password, or account is inactive.";
        }
    }
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
        Admin Login | <?= e($websiteName) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --navy: #071b38;
            --navy-light: #102b50;
            --gold: #d6a63b;
            --gold-light: #edca76;
            --text: #172b46;
            --muted: #68768a;
            --border: #e9e2d4;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 25px 15px;

            display: flex;
            justify-content: center;
            align-items: center;

            font-family: "Segoe UI", Arial, Helvetica, sans-serif;
            color: var(--text);

            background:
                radial-gradient(
                    circle at 15% 20%,
                    rgba(214, 166, 59, 0.18),
                    transparent 35%
                ),
                radial-gradient(
                    circle at 85% 80%,
                    rgba(214, 166, 59, 0.12),
                    transparent 35%
                ),
                linear-gradient(
                    135deg,
                    #071b38,
                    #102b50 55%,
                    #173c65
                );
        }

        .login-container {
            width: 100%;
            max-width: 460px;
            padding: 43px 42px 35px;

            background: #ffffff;
            border: 1px solid rgba(255,255,255,0.65);
            border-radius: 17px;

            box-shadow: 0 25px 75px rgba(0,0,0,0.30);

            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;

            background: linear-gradient(
                90deg,
                #a9823e,
                #edca76,
                #a9823e
            );
        }

        .logo {
            width: 100%;
            min-height: 88px;
            margin: 0 auto 22px;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo img {
            max-width: 195px;
            max-height: 88px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .brand-divider {
            width: 55px;
            height: 3px;
            margin: 0 auto 23px;

            border-radius: 5px;
            background: var(--gold);
        }

        h1 {
            margin: 0;
            text-align: center;

            color: var(--navy);
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .subtitle {
            margin: 11px 0 30px;

            text-align: center;
            color: var(--muted);

            font-size: 13px;
            line-height: 1.7;
        }

        .form-group {
            margin-bottom: 21px;
        }

        label {
            display: block;
            margin-bottom: 9px;

            color: #26364a;
            font-size: 13px;
            font-weight: 700;
        }

        input {
            width: 100%;
            height: 49px;
            padding: 0 15px;

            border: 1px solid var(--border);
            border-radius: 8px;

            background: #faf9f6;
            color: #1e293b;

            font-size: 14px;

            transition:
                border-color 0.2s,
                box-shadow 0.2s,
                background 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--gold);

            box-shadow: 0 0 0 3px rgba(214,166,59,0.18);

            background: #ffffff;
        }

        input::placeholder {
            color: #a0a9b7;
        }

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            padding-right: 75px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);

            border: none;
            background: transparent;

            color: #987638;
            font-size: 12px;
            font-weight: 700;

            cursor: pointer;
            padding: 7px;
        }

        .toggle-password:hover {
            color: var(--navy);
        }

        .forgot-password {
            margin-top: -7px;
            margin-bottom: 22px;
            text-align: right;
        }

        .forgot-password a {
            color: #987638;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .forgot-password a:hover {
            color: var(--navy);
            text-decoration: underline;
        }

        .login-button {
            width: 100%;
            min-height: 50px;
            margin-top: 5px;

            border: 1px solid var(--gold);
            border-radius: 8px;

            background: linear-gradient(
                110deg,
                var(--gold),
                var(--gold-light)
            );

            color: var(--navy);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.2px;

            cursor: pointer;

            transition:
                background 0.2s,
                transform 0.2s,
                box-shadow 0.2s;
        }

        .login-button:hover {
            background: linear-gradient(
                110deg,
                #c4932d,
                #edca76
            );

            transform: translateY(-1px);

            box-shadow: 0 7px 17px rgba(214,166,59,0.22);
        }

        .login-button:focus-visible,
        .toggle-password:focus-visible {
            outline: 3px solid var(--gold-light);
            outline-offset: 3px;
        }

        .error-message {
            padding: 13px 14px;
            margin-bottom: 21px;

            border: 1px solid #fecaca;
            border-radius: 8px;

            background: #fef2f2;
            color: #b91c1c;

            font-size: 13px;
            line-height: 1.6;
            text-align: center;
        }

        .footer {
            margin-top: 27px;
            padding-top: 19px;

            border-top: 1px solid #edf0f5;

            text-align: center;
            color: #8b95a5;

            font-size: 11px;
            line-height: 1.7;
        }

        .footer strong {
            display: block;
            margin-bottom: 4px;

            color: #987638;
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        @media (max-width: 480px) {

            body {
                padding: 18px 13px;
            }

            .login-container {
                padding: 35px 24px 28px;
            }

            h1 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

    <main class="login-container">

        <?php if ($websiteLogo !== ""): ?>

            <div class="logo">

                <img
                    src="<?= e($websiteLogo) ?>"
                    alt="<?= e($websiteName) ?> Logo"
                >

            </div>

        <?php else: ?>

            <div class="logo" aria-hidden="true">

                <div style="
                    width: 66px;
                    height: 66px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 14px;
                    background: #071b38;
                    color: #edca76;
                    font-size: 30px;
                    font-weight: 700;
                ">
                    <?= e(strtoupper(substr($websiteName, 0, 1))) ?>
                </div>

            </div>

        <?php endif; ?>

        <div class="brand-divider"></div>

        <h1>Admin Portal</h1>

        <p class="subtitle">
            Welcome back. Sign in to access your
            <?= e($websiteName) ?> administration panel.
        </p>

        <?php if ($error !== ""): ?>

            <div class="error-message" role="alert" aria-live="polite">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["csrf_token"]) ?>"
            >

            <div class="form-group">

                <label for="admin_email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="admin_email"
                    name="email"
                    placeholder="Enter your email address"
                    autocomplete="username"
                    maxlength="254"
                    required
                >

            </div>

            <div class="form-group">

                <label for="admin_password">
                    Password
                </label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="admin_password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        maxlength="4096"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        Show
                    </button>

                </div>

            </div>

            <div class="forgot-password">

                <a href="forgot-password.php">
                    Forgot Password?
                </a>

            </div>

            <button type="submit" class="login-button">
                Login to Admin Panel
            </button>

        </form>

        <div class="footer">

            <strong><?= e($websiteName) ?></strong>

            Secure Administration Portal

        </div>

    </main>

    <script>

        const toggleButton =
            document.querySelector(".toggle-password");

        const passwordInput =
            document.getElementById("admin_password");

        if (toggleButton && passwordInput) {

            toggleButton.addEventListener("click", function () {

                const isHidden =
                    passwordInput.type === "password";

                passwordInput.type =
                    isHidden ? "text" : "password";

                this.textContent =
                    isHidden ? "Hide" : "Show";

                this.setAttribute(
                    "aria-label",
                    isHidden ? "Hide password" : "Show password"
                );

                this.setAttribute(
                    "aria-pressed",
                    isHidden ? "true" : "false"
                );

            });

        }

    </script>

</body>

</html>
