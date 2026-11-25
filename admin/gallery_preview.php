
<?php

// --------------------------------------------------
// SECURE SUPER ADMIN GALLERY IMAGE PREVIEW
// --------------------------------------------------

ob_start();

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

// Only Super Admin can preview gallery photos.
requirePermission("manage_admins");

// --------------------------------------------------
// ACCEPT GET REQUESTS ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    exit("Method not allowed.");
}

// --------------------------------------------------
// VALIDATE PHOTO ID
// --------------------------------------------------

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit("Invalid gallery photo ID.");
}

// --------------------------------------------------
// FETCH PHOTO RECORD
// --------------------------------------------------

try {
    $stmt = $pdo->prepare("
        SELECT image_filename
        FROM gallery_photos
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log("Gallery preview database error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to load image.");
}

if (!$photo) {
    http_response_code(404);
    exit("Photo not found.");
}

// --------------------------------------------------
// VALIDATE IMAGE FILENAME
// --------------------------------------------------

$filename = $photo["image_filename"] ?? "";

if (
    !is_string($filename) ||
    !preg_match('/\A[a-f0-9]{40}\.(jpg|jpeg|png|webp)\z/i', $filename)
) {
    http_response_code(400);
    exit("Invalid image filename.");
}

// --------------------------------------------------
// VERIFY IMAGE STORAGE DIRECTORY
// --------------------------------------------------

$galleryDirectory = realpath(
    __DIR__ . "/../uploads/gallery"
);

if (
    $galleryDirectory === false ||
    !is_dir($galleryDirectory)
) {
    http_response_code(404);
    exit("Image directory not found.");
}

// --------------------------------------------------
// VERIFY IMAGE FILE PATH
// --------------------------------------------------

$imagePath = realpath(
    $galleryDirectory . DIRECTORY_SEPARATOR . $filename
);

if (
    $imagePath === false ||
    !is_file($imagePath) ||
    dirname($imagePath) !== $galleryDirectory
) {
    http_response_code(404);
    exit("Image file not found.");
}

// --------------------------------------------------
// VALIDATE FILE SIZE
// --------------------------------------------------

$fileSize = filesize($imagePath);

if (
    $fileSize === false ||
    $fileSize <= 0 ||
    $fileSize > 5 * 1024 * 1024
) {
    http_response_code(400);
    exit("Invalid image size.");
}

// --------------------------------------------------
// VALIDATE ACTUAL IMAGE CONTENT
// --------------------------------------------------

if (!class_exists("finfo")) {
    http_response_code(500);
    exit("File information service is unavailable.");
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($imagePath);

$allowedTypes = [
    "jpg"  => "image/jpeg",
    "jpeg" => "image/jpeg",
    "png"  => "image/png",
    "webp" => "image/webp"
];

$extension = strtolower(
    pathinfo($filename, PATHINFO_EXTENSION)
);

if (
    !isset($allowedTypes[$extension]) ||
    $mimeType !== $allowedTypes[$extension]
) {
    http_response_code(415);
    exit("Unsupported image format.");
}

// --------------------------------------------------
// CLEAR OUTPUT BEFORE SENDING IMAGE
// --------------------------------------------------

while (ob_get_level() > 0) {
    ob_end_clean();
}

// --------------------------------------------------
// SEND IMAGE TO BROWSER
// --------------------------------------------------

header("Content-Type: " . $mimeType);
header("Content-Length: " . $fileSize);
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Cache-Control: private, no-store, max-age=0");
header("Pragma: no-cache");

header(
    'Content-Disposition: inline; filename="gallery-image.' .
    $extension . '"'
);

readfile($imagePath);
exit;
