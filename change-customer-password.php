
<?php

require_once "config/database.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --------------------------------------------------
// CUSTOMER LOGIN CHECK
// --------------------------------------------------

$customerId = (int) ($_SESSION["customer_id"] ?? 0);

if ($customerId <= 0) {
    header("Location: login.php");
    exit;
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

$csrfToken = $_SESSION["csrf_token"];

$message = "";
$messageType = "error";

// --------------------------------------------------
// HELPER FUNCTION
// --------------------------------------------------

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// --------------------------------------------------
// VERIFY CUSTOMER ACCOUNT
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT id, password, status
        FROM customers
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $customerId
    ]);

    $customer = $stmt->fetch();

    if (!$customer || $customer["status"] !== "active") {

        unset(
            $_SESSION["customer_id"],
            $_SESSION["customer_name"],
            $_SESSION["customer_email"]
        );

        header("Location: login.php");
        exit;
    }

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit("Unable to load your account. Please try again later.");

}

// --------------------------------------------------
// CHANGE PASSWORD
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify CSRF token
    if (
        !isset($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"]) ||
        !isset($_POST["csrf_token"]) ||
        !is_string($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {

        $message = "Invalid request. Please refresh the page and try again.";

    } else {

        $currentPassword = $_POST["current_password"] ?? null;
        $newPassword = $_POST["new_password"] ?? null;
        $confirmPassword = $_POST["confirm_password"] ?? null;

        if (
            !is_string($currentPassword) ||
            !is_string($newPassword) ||
            !is_string($confirmPassword)
        ) {

            $message = "Invalid form information.";

        } elseif (
            $currentPassword === "" ||
            $newPassword === "" ||
            $confirmPassword === ""
        ) {

            $message = "Please fill in all fields.";

        } elseif (
            strlen($currentPassword) > 4096 ||
            strlen($newPassword) > 4096 ||
            strlen($confirmPassword) > 4096
        ) {

            $message = "Password is too long.";

        } elseif (!password_verify($currentPassword, $customer["password"])) {

            $message = "Your current password is incorrect.";

        } elseif (strlen($newPassword) < 12) {

            $message = "New password must contain at least 12 characters.";

        } elseif ($newPassword === $currentPassword) {

            $message = "Your new password must be different from your current password.";

        } elseif (!preg_match('/[A-Z]/', $newPassword)) {

            $message = "New password must contain an uppercase letter.";

        } elseif (!preg_match('/[a-z]/', $newPassword)) {

            $message = "New password must contain a lowercase letter.";

        } elseif (!preg_match('/[0-9]/', $newPassword)) {

            $message = "New password must contain a number.";

        } elseif (!preg_match('/[^a-zA-Z0-9]/', $newPassword)) {

            $message = "New password must contain a special character.";

        } elseif ($newPassword !== $confirmPassword) {

            $message = "New passwords do not match.";

        } else {

            try {

                // Hash new password
                $hashedPassword = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {
                    throw new RuntimeException("Password hashing failed.");
                }

                // Update password
                $update = $pdo->prepare("
                    UPDATE customers
                    SET password = :password
                    WHERE id = :id
                    AND status = 'active'
                ");

                $update->execute([
                    ":password" => $hashedPassword,
                    ":id" => $customerId
                ]);

                if ($update->rowCount() !== 1) {
                    throw new RuntimeException("Password update failed.");
                }

                // Regenerate session ID
                session_regenerate_id(true);

                // Rotate CSRF token
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                // Redirect after success
                $_SESSION["customer_password_message"] =
                    "Your password has been changed successfully.";

                header("Location: change-customer-password.php?updated=1");
                exit;

            } catch (Throwable $e) {

                error_log($e->getMessage());

                $message = "Unable to change your password. Please try again.";

            }

        }

        // Rotate token after a valid-CSRF submission
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        $csrfToken = $_SESSION["csrf_token"];
    }
}

// --------------------------------------------------
// SUCCESS MESSAGE
// --------------------------------------------------

if (
    isset($_GET["updated"]) &&
    $_GET["updated"] === "1" &&
    isset($_SESSION["customer_password_message"])
) {

    $message = $_SESSION["customer_password_message"];
    $messageType = "success";

    unset($_SESSION["customer_password_message"]);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Change Customer Password</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
        }

        .container {
            width: 100%;
            max-width: 460px;
            padding: 35px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        }

        h1 {
            text-align: center;
            margin-top: 0;
            color: #222;
        }

        .subtitle {
            text-align: center;
            color: #777;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        /* PASSWORD SHOW / HIDE */

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            padding-right: 75px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #2563eb;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            padding: 6px;
            width: auto;
        }

        .toggle-password:hover {
            color: #1d4ed8;
        }

        .update-button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .update-button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .help {
            color: #777;
            font-size: 12px;
            margin-top: 6px;
        }

        .links {
            text-align: center;
            margin-top: 22px;
            font-size: 14px;
        }

        .links a {
            color: #2563eb;
            text-decoration: none;
            margin: 0 8px;
        }

    </style>

</head>

<body>

    <div class="container">

        <h1>Change Password</h1>

        <p class="subtitle">
            Keep your customer account secure
        </p>

        <?php if ($message !== ""): ?>

            <div class="message <?php echo e($messageType); ?>">
                <?php echo e($message); ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="change-customer-password.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($csrfToken); ?>"
            >

            <div class="form-group">

                <label for="current_password">Current Password</label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        maxlength="4096"
                        autocomplete="current-password"
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

                <label for="new_password">New Password</label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="12"
                        maxlength="4096"
                        autocomplete="new-password"
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

                <p class="help">
                    Minimum 12 characters with uppercase, lowercase,
                    number and special character.
                </p>

            </div>

            <div class="form-group">

                <label for="confirm_password">Confirm New Password</label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="12"
                        maxlength="4096"
                        autocomplete="new-password"
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

            <button type="submit" class="update-button">
                Update Password
            </button>

        </form>

        <div class="links">

            <a href="my-account.php">My Account</a>

            <a href="index.php">Home</a>

        </div>

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
