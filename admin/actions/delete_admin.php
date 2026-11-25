
<?php

require_once "../../includes/permissions.php";
require_once "../../config/database.php";
require_once "../../includes/activity_logger.php";

// --------------------------------------------------
// REQUIRE ADMIN MANAGEMENT PERMISSION
// --------------------------------------------------

requirePermission("manage_admins");

// --------------------------------------------------
// ALLOW POST REQUESTS ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method Not Allowed");
}

// --------------------------------------------------
// VERIFY CSRF TOKEN
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
    exit("Invalid request. Please try again.");
}

// --------------------------------------------------
// VALIDATE ACCOUNT ID
// --------------------------------------------------

$id = filter_var(
    $_POST["id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    $_SESSION["admin_message"] = "Invalid account ID.";
    $_SESSION["admin_message_type"] = "error";

    header("Location: ../manage_admins.php");
    exit;
}

// --------------------------------------------------
// PREVENT SELF-DELETION
// --------------------------------------------------

$currentAdminId = filter_var(
    $_SESSION["admin_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (
    !$currentAdminId ||
    $id === (int) $currentAdminId
) {

    $_SESSION["admin_message"] =
        "You cannot delete your own account.";

    $_SESSION["admin_message_type"] = "error";

    header("Location: ../manage_admins.php");
    exit;
}

// --------------------------------------------------
// DELETE ADMIN ACCOUNT
// --------------------------------------------------

try {

    $pdo->beginTransaction();

    // Lock and retrieve the target account.
    // Super Admin accounts cannot be deleted.

    $find = $pdo->prepare("
        SELECT id, name, email, role
        FROM admins
        WHERE id = ?
        AND role IN ('admin', 'editor', 'sales_employee')
        LIMIT 1
        FOR UPDATE
    ");

    $find->execute([$id]);

    $admin = $find->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {

        $pdo->rollBack();

        $_SESSION["admin_message"] =
            "Account not found or cannot be deleted.";

        $_SESSION["admin_message_type"] = "error";

    } else {

        // Delete only permitted account roles.

        $stmt = $pdo->prepare("
            DELETE FROM admins
            WHERE id = ?
            AND role IN ('admin', 'editor', 'sales_employee')
        ");

        $stmt->execute([$id]);

        if ($stmt->rowCount() === 1) {

            // Record deletion in the activity log.

            logActivity(
                $pdo,
                "ADMIN_DELETED",
                "Deleted " . $admin["role"] .
                " account: " . $admin["name"] .
                " (" . $admin["email"] . ")."
            );

            $pdo->commit();

            // Rotate CSRF token after successful deletion.

            $_SESSION["csrf_token"] =
                bin2hex(random_bytes(32));

            $_SESSION["admin_message"] =
                "Account deleted successfully!";

            $_SESSION["admin_message_type"] = "success";

        } else {

            $pdo->rollBack();

            $_SESSION["admin_message"] =
                "Account could not be deleted.";

            $_SESSION["admin_message_type"] = "error";
        }
    }

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $_SESSION["admin_message"] =
        "Unable to delete this account. Please try again.";

    $_SESSION["admin_message_type"] = "error";
}

// --------------------------------------------------
// RETURN TO MANAGE ACCOUNTS
// --------------------------------------------------

header("Location: ../manage_admins.php");
exit;

?>
