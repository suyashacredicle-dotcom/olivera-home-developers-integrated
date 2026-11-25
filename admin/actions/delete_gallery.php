
<?php

// --------------------------------------------------
// SECURE GALLERY PHOTO DELETE HANDLER
// --------------------------------------------------

require_once __DIR__ . "/../../includes/permissions.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/activity_logger.php";

// Only Super Admin can delete gallery photos.
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

$sessionToken = $_SESSION["csrf_token"] ?? "";
$submittedToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($sessionToken) ||
    !is_string($submittedToken) ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit("Invalid security token.");
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
// GENERATE NEW CSRF TOKEN BEFORE DELETION
// --------------------------------------------------

try {
    $newCsrfToken = bin2hex(random_bytes(32));
} catch (Throwable $e) {
    error_log("Gallery deletion CSRF generation failed: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to process the deletion request.");
}

// --------------------------------------------------
// FETCH PHOTO RECORD
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT id, image_filename, title
        FROM gallery_photos
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        "id" => $id
    ]);

    $photo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$photo) {
        http_response_code(404);
        exit("Gallery photo not found.");
    }

    // --------------------------------------------------
    // VALIDATE STORED FILENAME
    // --------------------------------------------------

    $filename = $photo["image_filename"] ?? "";

    if (
        !is_string($filename) ||
        !preg_match(
            '/\A[a-f0-9]{40}\.(jpg|jpeg|png|webp)\z/i',
            $filename
        )
    ) {
        error_log("Unsafe gallery filename for photo ID: " . $id);

        http_response_code(500);
        exit("Unable to safely delete this gallery photo.");
    }

    // --------------------------------------------------
    // RESOLVE GALLERY DIRECTORY
    // --------------------------------------------------

    $galleryDirectory = realpath(
        __DIR__ . "/../../uploads/gallery"
    );

    if (
        $galleryDirectory === false ||
        !is_dir($galleryDirectory)
    ) {
        error_log("Gallery upload directory not found.");

        http_response_code(500);
        exit("Gallery upload directory is unavailable.");
    }

    // --------------------------------------------------
    // VALIDATE PHYSICAL IMAGE PATH
    // --------------------------------------------------

    $imagePath = $galleryDirectory .
        DIRECTORY_SEPARATOR .
        $filename;

    $realImagePath = null;

    if (file_exists($imagePath) || is_link($imagePath)) {

        $realImagePath = realpath($imagePath);

        if (
            $realImagePath === false ||
            dirname($realImagePath) !== $galleryDirectory ||
            !is_file($realImagePath) ||
            !is_writable($realImagePath)
        ) {
            error_log("Gallery image path validation failed for photo ID: " . $id);

            http_response_code(500);
            exit("Unable to safely delete the gallery image.");
        }
    }

    // --------------------------------------------------
    // DELETE DATABASE RECORD
    // --------------------------------------------------

    $pdo->beginTransaction();

    $delete = $pdo->prepare("
        DELETE FROM gallery_photos
        WHERE id = :id
        LIMIT 1
    ");

    $delete->execute([
        "id" => $id
    ]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException("Gallery photo could not be deleted.");
    }

    $pdo->commit();

    // --------------------------------------------------
    // DELETE PHYSICAL IMAGE FILE
    // --------------------------------------------------

    $fileDeleted = true;

    if ($realImagePath !== null) {

        if (!unlink($realImagePath)) {

            $fileDeleted = false;

            error_log(
                "Could not delete gallery image file for photo ID: " . $id
            );
        }
    }

    // --------------------------------------------------
    // RECORD ADMIN ACTIVITY
    // --------------------------------------------------

    try {

        logActivity(
            $pdo,
            "GALLERY_PHOTO_DELETED",
            "Deleted gallery photo ID: " .
            $id .
            " | Filename: " .
            $filename
        );

    } catch (Throwable $e) {

        error_log(
            "Gallery deletion activity logging failed: " .
            $e->getMessage()
        );
    }

    // --------------------------------------------------
    // ROTATE CSRF TOKEN
    // --------------------------------------------------

    $_SESSION["csrf_token"] = $newCsrfToken;

    // --------------------------------------------------
    // SUCCESS MESSAGE
    // --------------------------------------------------

    $_SESSION["gallery_message"] = $fileDeleted
        ? "Gallery photo deleted successfully."
        : "Gallery photo record deleted, but its image file could not be removed. Please contact the administrator.";

    $_SESSION["gallery_message_type"] = $fileDeleted
        ? "success"
        : "warning";

    // --------------------------------------------------
    // REDIRECT
    // --------------------------------------------------

    header("Location: ../manage_gallery.php");
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Gallery deletion error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to delete gallery photo. Please try again.");
}

?>
