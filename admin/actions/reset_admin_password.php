
<?php

require_once "../../includes/permissions.php";
require_once "../../config/database.php";
require_once "../../includes/activity_logger.php";

// Only users with manage_admins permission can reset passwords.
requirePermission("manage_admins");

// --------------------------------------------------
// POST REQUEST ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method Not Allowed");
}

// --------------------------------------------------
// CSRF PROTECTION
// --------------------------------------------------

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

// --------------------------------------------------
// GET SUBMITTED VALUES
// --------------------------------------------------

$adminId = filter_var(
    $_POST["id"] ?? null,
    FILTER_VALIDATE_INT
);

$newPassword = $_POST["new_password"] ?? "";

// --------------------------------------------------
// VALIDATE ADMIN ID
// --------------------------------------------------

if (!$adminId || $adminId <= 0) {

    $_SESSION["admin_message"] = "Invalid admin account.";
    $_SESSION["admin_message_type"] = "error";

    header("Location: ../manage_admins.php");
    exit;
}

// --------------------------------------------------
// VALIDATE PASSWORD
// --------------------------------------------------

if (!is_string($newPassword)) {

    $_SESSION["admin_message"] = "Invalid password input.";
    $_SESSION["admin_message_type"] = "error";

} elseif (
    strlen($newPassword) < 12 ||
    strlen($newPassword) > 4096
) {

    $_SESSION["admin_message"] =
        "Password must contain between 12 and 4096 characters.";

    $_SESSION["admin_message_type"] = "error";

} elseif (!preg_match('/[A-Z]/', $newPassword)) {

    $_SESSION["admin_message"] =
        "Password must contain at least one uppercase letter.";

    $_SESSION["admin_message_type"] = "error";

} elseif (!preg_match('/[a-z]/', $newPassword)) {

    $_SESSION["admin_message"] =
        "Password must contain at least one lowercase letter.";

    $_SESSION["admin_message_type"] = "error";

} elseif (!preg_match('/[0-9]/', $newPassword)) {

    $_SESSION["admin_message"] =
        "Password must contain at least one number.";

    $_SESSION["admin_message_type"] = "error";

} elseif (!preg_match('/[^a-zA-Z0-9]/', $newPassword)) {

    $_SESSION["admin_message"] =
        "Password must contain at least one special character.";

    $_SESSION["admin_message_type"] = "error";

} else {

    try {

        // --------------------------------------------------
        // FIND TARGET ACCOUNT
        // --------------------------------------------------

        $stmt = $pdo->prepare("
            SELECT id, name, email, role
            FROM admins
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$adminId]);

        $targetAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetAdmin) {

            $_SESSION["admin_message"] =
                "Admin account not found.";

            $_SESSION["admin_message_type"] = "error";

        } elseif ($targetAdmin["role"] === "super_admin") {

            $_SESSION["admin_message"] =
                "The Super Admin account is protected.";

            $_SESSION["admin_message_type"] = "error";

        } elseif (!in_array(
            $targetAdmin["role"],
            ["admin", "editor", "sales_employee"],
            true
        )) {

            $_SESSION["admin_message"] =
                "This account cannot have its password reset.";

            $_SESSION["admin_message_type"] = "error";

        } else {

            // --------------------------------------------------
            // HASH NEW PASSWORD
            // --------------------------------------------------

            $hashedPassword = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            if ($hashedPassword === false) {
                throw new RuntimeException(
                    "Password hashing failed."
                );
            }

            // --------------------------------------------------
            // UPDATE PASSWORD
            // --------------------------------------------------

            $stmt = $pdo->prepare("
                UPDATE admins
                SET password = ?
                WHERE id = ?
                AND role IN ('admin', 'editor', 'sales_employee')
            ");

            $stmt->execute([
                $hashedPassword,
                $adminId
            ]);

            if ($stmt->rowCount() === 1) {

                // --------------------------------------------------
                // ACTIVITY LOG
                // --------------------------------------------------

                logActivity(
                    $pdo,
                    "ADMIN_PASSWORD_RESET",
                    "Reset password for " .
                    $targetAdmin["name"] .
                    " (" . $targetAdmin["email"] . ")."
                );

                // --------------------------------------------------
                // SUCCESS MESSAGE
                // --------------------------------------------------

                $_SESSION["admin_message"] =
                    "Password reset successfully for " .
                    $targetAdmin["name"] . ".";

                $_SESSION["admin_message_type"] = "success";

                // Rotate CSRF token after successful reset.
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            } else {

                $_SESSION["admin_message"] =
                    "Password could not be reset. Please try again.";

                $_SESSION["admin_message_type"] = "error";
            }
        }

    } catch (PDOException $e) {

        error_log($e->getMessage());

        $_SESSION["admin_message"] =
            "Something went wrong. Please try again.";

        $_SESSION["admin_message_type"] = "error";

    } catch (RuntimeException $e) {

        error_log($e->getMessage());

        $_SESSION["admin_message"] =
            "Something went wrong. Please try again.";
        
        $_SESSION["admin_message_type"] = "error";
    }
}

// --------------------------------------------------
// REDIRECT TO MANAGE ACCOUNTS
// --------------------------------------------------

header("Location: ../manage_admins.php");
exit;

?>
