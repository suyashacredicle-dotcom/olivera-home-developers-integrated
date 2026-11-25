
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
// TOGGLE USER STATUS
// --------------------------------------------------

try {

    $pdo->beginTransaction();

    // Get current status and lock the record.
    $stmt = $pdo->prepare("
        SELECT status
        FROM users
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        $pdo->rollBack();

        $_SESSION["user_message"] = "User not found.";

    } elseif (
        !in_array($user["status"], ["active", "inactive"], true)
    ) {

        $pdo->rollBack();

        $_SESSION["user_message"] = "Invalid user status.";

    } else {

        // Change active to inactive or inactive to active.
        $newStatus = ($user["status"] === "active")
            ? "inactive"
            : "active";

        $update = $pdo->prepare("
            UPDATE users
            SET status = ?
            WHERE id = ?
        ");

        $update->execute([
            $newStatus,
            $id
        ]);

        $pdo->commit();

        // Rotate CSRF token after successful update.
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

        $_SESSION["user_message"] =
            "User status changed to " . $newStatus . " successfully!";
    }

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $_SESSION["user_message"] =
        "Something went wrong. Please try again.";
}

// --------------------------------------------------
// RETURN TO MANAGE USERS
// --------------------------------------------------

header("Location: ../manage_users.php");
exit;

?>
