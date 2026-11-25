
<?php

require_once "../../includes/permissions.php";
require_once "../../config/database.php";

requirePermission("manage_content");

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../manage_content.php");
    exit;
}

// Verify CSRF security token
if (
    !isset($_SESSION["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )
) {
    die("Invalid request. Please try again.");
}

// Get content ID
$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die("Invalid content ID.");
}

// Delete content
$stmt = $pdo->prepare(
    "DELETE FROM content WHERE id = ?"
);

$stmt->execute([$id]);

if ($stmt->rowCount() === 1) {
    $_SESSION["content_message"] = "Content deleted successfully!";
} else {
    $_SESSION["content_message"] = "Content not found.";
}

// Return to Manage Content page
header("Location: ../manage_content.php");
exit;

?>
