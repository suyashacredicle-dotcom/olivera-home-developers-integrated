
<?php

require_once "../../includes/permissions.php";
require_once "../../config/database.php";

requirePermission("manage_users");

// --------------------------------------------------
// ONLY ALLOW POST REQUESTS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../manage_users.php");
    exit;
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
    exit("Invalid request. Please reload the page.");
}

// --------------------------------------------------
// VALIDATE USER ID
// --------------------------------------------------

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit("Invalid user ID.");
}

// --------------------------------------------------
// DELETE USER
// --------------------------------------------------

try {

    $pdo->beginTransaction();

    // Lock and confirm user exists.
    $check = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $check->execute([$id]);

    $user = $check->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        $pdo->rollBack();

        $_SESSION["user_message"] = "User not found.";

        $_SESSION["user_message_type"] = "error";

    } else {

        $stmt = $pdo->prepare("
            DELETE FROM users
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        if ($stmt->rowCount() === 1) {

            $pdo->commit();

            // Rotate CSRF token after successful deletion.
            $_SESSION["csrf_token"] =
                bin2hex(random_bytes(32));

            $_SESSION["user_message"] =
                "User deleted successfully!";

            $_SESSION["user_message_type"] = "success";

        } else {

            $pdo->rollBack();

            $_SESSION["user_message"] =
                "User could not be deleted.";

            $_SESSION["user_message_type"] = "error";
        }
    }

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $_SESSION["user_message"] =
        "Something went wrong. Please try again.";

    $_SESSION["user_message_type"] = "error";
}

// --------------------------------------------------
// RETURN TO MANAGE USERS
// --------------------------------------------------

header("Location: ../manage_users.php");
exit;

?>
