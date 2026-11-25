
<?php

// --------------------------------------------------
// LOAD SECURITY AND DATABASE FILES
// --------------------------------------------------

require_once __DIR__ . "/../../includes/permissions.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/activity_logger.php";

// Only Super Admin can update gallery photos.
requirePermission("manage_admins");

// --------------------------------------------------
// POST REQUEST ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    exit("Method not allowed.");
}

// --------------------------------------------------
// CSRF VALIDATION
// --------------------------------------------------

$csrfToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($csrfToken) ||
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    $_SESSION["csrf_token"] === "" ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    http_response_code(403);
    exit("Invalid security token. Please go back and try again.");
}

// --------------------------------------------------
// VALIDATE PHOTO ID
// --------------------------------------------------

$idValue = $_POST["id"] ?? null;
$id = filter_var($idValue, FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    http_response_code(400);
    exit("Invalid gallery photo ID.");
}

// --------------------------------------------------
// VALIDATE FORM DATA
// --------------------------------------------------

$title = $_POST["title"] ?? "";
$description = $_POST["description"] ?? "";
$visibility = $_POST["visibility"] ?? "";

if (
    !is_string($title) ||
    !is_string($description) ||
    !is_string($visibility)
) {
    http_response_code(400);
    exit("Invalid form data.");
}

$title = trim($title);
$description = trim($description);

$titleLength = function_exists("mb_strlen")
    ? mb_strlen($title, "UTF-8")
    : strlen($title);

$descriptionLength = function_exists("mb_strlen")
    ? mb_strlen($description, "UTF-8")
    : strlen($description);

if ($titleLength > 150) {
    http_response_code(400);
    exit("Photo title cannot exceed 150 characters.");
}

if ($descriptionLength > 5000) {
    http_response_code(400);
    exit("Description cannot exceed 5000 characters.");
}

if (!in_array($visibility, ["public", "employees"], true)) {
    http_response_code(400);
    exit("Invalid photo visibility.");
}

// --------------------------------------------------
// GENERATE NEW CSRF TOKEN
// --------------------------------------------------

try {
    $newCsrfToken = bin2hex(random_bytes(32));
} catch (Throwable $e) {
    error_log("Gallery CSRF generation error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to process your request. Please try again.");
}

// --------------------------------------------------
// UPDATE GALLERY PHOTO
// --------------------------------------------------

try {

    // Check whether the photo exists.
    $check = $pdo->prepare("
        SELECT id
        FROM gallery_photos
        WHERE id = :id
        LIMIT 1
    ");

    $check->execute([
        "id" => $id
    ]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        exit("Gallery photo not found.");
    }

    // Update photo information.
    $stmt = $pdo->prepare("
        UPDATE gallery_photos
        SET
            title = :title,
            description = :description,
            visibility = :visibility
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        "title" => $title !== "" ? $title : null,
        "description" => $description !== "" ? $description : null,
        "visibility" => $visibility,
        "id" => $id
    ]);

    // --------------------------------------------------
    // ACTIVITY LOG
    // --------------------------------------------------

    try {

        logActivity(
            $pdo,
            "GALLERY_PHOTO_UPDATED",
            "Updated gallery photo ID: " . $id .
            " | Visibility: " . $visibility
        );

    } catch (Throwable $logError) {

        error_log(
            "Gallery update activity log error: " .
            $logError->getMessage()
        );
    }

    // --------------------------------------------------
    // SUCCESS MESSAGE
    // --------------------------------------------------

    $_SESSION["csrf_token"] = $newCsrfToken;

    $_SESSION["gallery_message"] =
        "Gallery photo updated successfully.";

    $_SESSION["gallery_message_type"] = "success";

    header("Location: ../manage_gallery.php");
    exit;

} catch (Throwable $e) {

    error_log("Gallery update error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to update gallery photo. Please try again.");
}
