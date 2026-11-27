<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// --------------------------------------------------
// INITIALIZE
// --------------------------------------------------

$message = "";
$messageType = "";

$adminId = (int)($_SESSION["admin_id"] ?? 0);

if ($adminId <= 0) {
    header("Location: login.php");
    exit;
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

// --------------------------------------------------
// CHANGE PASSWORD
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

    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (
        !is_string($currentPassword) ||
        !is_string($newPassword) ||
        !is_string($confirmPassword)
    ) {

        $message = "Invalid password input.";
        $messageType = "error";

    } elseif (
        strlen($currentPassword) > 4096 ||
        strlen($newPassword) > 4096 ||
        strlen($confirmPassword) > 4096
    ) {

        $message = "Password is too long.";
        $messageType = "error";

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT password
                FROM admins
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$adminId]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {

                $message = "Admin account not found.";
                $messageType = "error";

            } elseif (
                !password_verify(
                    $currentPassword,
                    $admin["password"]
                )
            ) {

                $message = "Current password is incorrect.";
                $messageType = "error";

            } elseif (strlen($newPassword) < 12) {

                $message = "New password must contain at least 12 characters.";
                $messageType = "error";

            } elseif ($newPassword !== $confirmPassword) {

                $message = "New password and confirmation do not match.";
                $messageType = "error";

            } elseif ($currentPassword === $newPassword) {

                $message = "New password must be different from current password.";
                $messageType = "error";

            } elseif (!preg_match('/[A-Z]/', $newPassword)) {

                $message = "Password must contain at least one uppercase letter.";
                $messageType = "error";

            } elseif (!preg_match('/[a-z]/', $newPassword)) {

                $message = "Password must contain at least one lowercase letter.";
                $messageType = "error";

            } elseif (!preg_match('/[0-9]/', $newPassword)) {

                $message = "Password must contain at least one number.";
                $messageType = "error";

            } elseif (!preg_match('/[^a-zA-Z0-9]/', $newPassword)) {

                $message = "Password must contain at least one special character.";
                $messageType = "error";

            } else {

                $hashedPassword = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {

                    $message = "Password could not be secured. Please try again.";
                    $messageType = "error";

                } else {

                    $stmt = $pdo->prepare("
                        UPDATE admins
                        SET password = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $hashedPassword,
                        $adminId
                    ]);

                    // Read back the saved password hash and verify that it
                    // matches the new password before reporting success.
                    // This confirms the actual stored value, not just that
                    // the admin record still exists.

                    $checkStmt = $pdo->prepare("
                        SELECT password
                        FROM admins
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $checkStmt->execute([$adminId]);

                    $savedPasswordHash = $checkStmt->fetchColumn();

                    $passwordUpdateConfirmed = (
                        is_string($savedPasswordHash) &&
                        $savedPasswordHash !== "" &&
                        password_verify($newPassword, $savedPasswordHash)
                    );

                    if ($passwordUpdateConfirmed) {

                        // Regenerate session ID after password change.
                        session_regenerate_id(true);

                        // Rotate CSRF token.
                        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                        $message = "Password changed successfully!";
                        $messageType = "success";

                    } else {

                        $message = "Password could not be updated. Please try again.";
                        $messageType = "error";
                    }
                }
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $message = "Something went wrong. Please try again.";
            $messageType = "error";
        }
    }
}

// --------------------------------------------------
// OUTPUT ESCAPING
// --------------------------------------------------

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Change Password | Admin Panel</title>

    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">

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
            background: var(--ivory);
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* MAIN CONTENT */

        .main-content {
            flex: 1;
            min-width: 0;
            margin-left: 255px;
            padding: 35px;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 27px;
        }

        .page-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #f3e8ca;
            color: var(--navy);
            font-size: 23px;
        }

        .page-header h1 {
            margin: 0 0 7px;
            font-size: 27px;
            color: var(--navy);
        }

        .page-header p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .breadcrumb {
            font-size: 12px;
            color: #8995a7;
            margin-top: 6px;
        }

        /* PASSWORD CARD */

        .password-card {
            background: var(--surface);
            border-radius: 13px;
            padding: 30px;
            max-width: 850px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 18px rgba(20,35,60,0.045);
        }

        .card-heading {
            border-bottom: 1px solid #edf0f5;
            padding-bottom: 19px;
            margin-bottom: 25px;
        }

        .card-heading h2 {
            margin: 0 0 7px;
            font-size: 19px;
            color: var(--navy);
        }

        .card-heading p {
            margin: 0;
            color: #8290a3;
            font-size: 13px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
        }

        /* PASSWORD INPUTS */

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            display: block;
            width: 100%;
            padding: 13px 75px 13px 14px;
            border: 1px solid #dce3ed;
            border-radius: 8px;
            font-size: 14px;
            color: var(--text);
            background: #ffffff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .password-wrapper input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,0.15);
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--navy);
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            padding: 7px;
        }

        .toggle-password:hover {
            color: #9b741f;
        }

        /* PASSWORD REQUIREMENTS */

        .requirements-box {
            background: #fbf8ef;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 19px 21px;
            margin: 26px 0;
        }

        .requirements-box h3 {
            margin: 0 0 13px;
            font-size: 14px;
            color: var(--navy);
        }

        .requirements-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px 20px;
            margin: 0;
            padding: 0;
            list-style: none;
            color: var(--muted);
            font-size: 13px;
        }

        .requirements-list li::before {
            content: "✓";
            color: #a47b22;
            font-weight: bold;
            margin-right: 9px;
        }

        /* MESSAGES */

        .message {
            padding: 14px 17px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.5;
        }

        .message.success {
            background: #eaf7ef;
            color: #187444;
            border: 1px solid #c7ead5;
        }

        .message.error {
            background: #fff0f0;
            color: #b42323;
            border: 1px solid #f3cccc;
        }

        /* BUTTONS */

        .form-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
            padding-top: 23px;
            border-top: 1px solid #edf0f5;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--gold);
            color: var(--navy);
        }

        .btn-primary:hover {
            background: var(--gold-light);
        }

        .btn-secondary {
            background: #f0eee8;
            color: var(--navy);
        }

        .btn-secondary:hover {
            background: #e8e1d2;
        }

        .forgot-password {
            margin-top: 18px;
            text-align: right;
        }

        .forgot-password a,
        .account-links a {
            color: #96701e;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .forgot-password a:hover,
        .account-links a:hover {
            text-decoration: underline;
        }

        .account-links {
            margin-top: 23px;
            padding-top: 20px;
            border-top: 1px solid #edf0f5;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {
            .main-content {
                margin-left: 220px;
                padding: 22px;
            }
        }

        @media (max-width: 700px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 600px) {

            .main-content {
                padding: 17px;
            }

            .password-card {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .requirements-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 500px) {

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .form-actions .btn {
                width: 100%;
                text-align: center;
            }

            .forgot-password {
                text-align: left;
            }
        }

    </style>

</head>

<body>

<div class="admin-layout">

    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <main class="main-content">

        <div class="page-header">

            <div class="page-icon">🔒</div>

            <div>
                <h1>Change Password</h1>

                <p>
                    Keep your administrator account secure by updating your password.
                </p>

                <div class="breadcrumb">
                    Admin Panel / My Account / Change Password
                </div>
            </div>

        </div>

        <?php if ($message !== ""): ?>

            <div class="message <?php echo e($messageType); ?>">
                <?php echo e($message); ?>
            </div>

        <?php endif; ?>

        <section class="password-card">

            <div class="card-heading">

                <h2>Update Your Password</h2>

                <p>
                    Enter your current password and choose a strong new password.
                </p>

            </div>

            <form method="POST" action="change_password.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo e($_SESSION["csrf_token"]); ?>"
                >

                <div class="form-group">

                    <label for="current_password">
                        Current Password
                    </label>

                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                            maxlength="4096"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            data-target="current_password"
                            aria-label="Show current password"
                            aria-pressed="false"
                        >
                            Show
                        </button>

                    </div>

                </div>

                <div class="form-group">

                    <label for="new_password">
                        New Password
                    </label>

                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            autocomplete="new-password"
                            minlength="12"
                            maxlength="4096"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            data-target="new_password"
                            aria-label="Show new password"
                            aria-pressed="false"
                        >
                            Show
                        </button>

                    </div>

                </div>

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            autocomplete="new-password"
                            minlength="12"
                            maxlength="4096"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            data-target="confirm_password"
                            aria-label="Show confirm password"
                            aria-pressed="false"
                        >
                            Show
                        </button>

                    </div>

                </div>

                <div class="requirements-box">

                    <h3>Your new password must contain:</h3>

                    <ul class="requirements-list">
                        <li>At least 12 characters</li>
                        <li>One uppercase letter (A-Z)</li>
                        <li>One lowercase letter (a-z)</li>
                        <li>One number (0-9)</li>
                        <li>One special character</li>
                    </ul>

                </div>

                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Change Password
                    </button>

                    <a
                        href="profile.php"
                        class="btn btn-secondary"
                    >
                        Back to My Profile
                    </a>

                </div>

                <div class="forgot-password">

                    <a href="forgot-password.php">
                        Forgot your current password?
                    </a>

                </div>

            </form>

            <div class="account-links">

                <a href="dashboard.php">
                    ← Back to Dashboard
                </a>

            </div>

        </section>

    </main>

</div>

<script>

    document.querySelectorAll(".toggle-password").forEach(function(button) {

        button.addEventListener("click", function() {

            const targetId = this.getAttribute("data-target");
            const passwordInput = document.getElementById(targetId);

            if (!passwordInput) {
                return;
            }

            const isHidden = passwordInput.type === "password";

            passwordInput.type = isHidden ? "text" : "password";

            this.textContent = isHidden ? "Hide" : "Show";

            this.setAttribute(
                "aria-label",
                isHidden ? "Hide password" : "Show password"
            );

            this.setAttribute(
                "aria-pressed",
                isHidden ? "true" : "false"
            );

        });

    });

</script>

</body>
</html>