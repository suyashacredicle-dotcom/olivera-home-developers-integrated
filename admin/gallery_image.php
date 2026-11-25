
<?php

// --------------------------------------------------
// SECURE EMPLOYEE GALLERY IMAGE VIEWER
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

// --------------------------------------------------
// ALLOWED ADMIN ROLES
// --------------------------------------------------

$allowedRoles = [
    "super_admin",
    "admin",
    "editor",
    "sales_employee"
];

$currentRole = $_SESSION["admin_role"] ?? "";

if (!in_array($currentRole, $allowedRoles, true)) {
    http_response_code(403);
    exit("Access Denied!");
}

// --------------------------------------------------
// ONLY ALLOW GET REQUESTS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    http_response_code(405);
    exit("Method Not Allowed");
}

// --------------------------------------------------
// VALIDATE PHOTO ID
// --------------------------------------------------

$photoId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($photoId === false || $photoId === null) {
    http_response_code(400);
    exit("Invalid photo request.");
}

// --------------------------------------------------
// FETCH EMPLOYEE PHOTO ONLY
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT image_filename
        FROM gallery_photos
        WHERE id = ?
          AND visibility = 'employees'
        LIMIT 1
    ");

    $stmt->execute([$photoId]);

    $photo = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        "Gallery image database error: " . $e->getMessage()
    );

    http_response_code(500);
    exit("Unable to load image.");
}

// --------------------------------------------------
// CHECK PHOTO EXISTS
// --------------------------------------------------

if (!$photo) {
    http_response_code(404);
    exit("Image not found.");
}

// --------------------------------------------------
// VALIDATE STORED FILENAME
// --------------------------------------------------

$filename = $photo["image_filename"] ?? "";

if (
    !is_string($filename) ||
    $filename === "" ||
    basename($filename) !== $filename ||
    !preg_match(
        '/\A[a-f0-9]{40}\.(jpg|jpeg|png|webp)\z/i',
        $filename
    )
) {
    http_response_code(404);
    exit("Invalid image file.");
}

// --------------------------------------------------
// RESOLVE PROTECTED GALLERY DIRECTORY
// --------------------------------------------------

$galleryDirectory = realpath(
    __DIR__ . "/../uploads/gallery"
);

if (
    $galleryDirectory === false ||
    !is_dir($galleryDirectory)
) {
    error_log("Gallery image directory is unavailable.");

    http_response_code(500);
    exit("Image storage unavailable.");
}

// --------------------------------------------------
// RESOLVE IMAGE PATH
// --------------------------------------------------

$imagePath = realpath(
    $galleryDirectory . DIRECTORY_SEPARATOR . $filename
);

if (
    $imagePath === false ||
    dirname($imagePath) !== $galleryDirectory ||
    !is_file($imagePath) ||
    !is_readable($imagePath)
) {
    http_response_code(404);
    exit("Image file not found.");
}

// --------------------------------------------------
// VALIDATE IMAGE CONTENT
// --------------------------------------------------

if (!function_exists("finfo_open")) {
    http_response_code(500);
    exit("Image validation unavailable.");
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);

if ($finfo === false) {
    http_response_code(500);
    exit("Image validation unavailable.");
}

$mimeType = finfo_file($finfo, $imagePath);

finfo_close($finfo);

$allowedMimeTypes = [
    "image/jpeg" => ["jpg", "jpeg"],
    "image/png"  => ["png"],
    "image/webp" => ["webp"]
];

$extension = strtolower(
    pathinfo($filename, PATHINFO_EXTENSION)
);

if (
    !is_string($mimeType) ||
    !isset($allowedMimeTypes[$mimeType]) ||
    !in_array(
        $extension,
        $allowedMimeTypes[$mimeType],
        true
    )
) {
    http_response_code(415);
    exit("Unsupported image file.");
}

// --------------------------------------------------
// CHECK IMAGE SIZE
// --------------------------------------------------

$imageSize = filesize($imagePath);

if (
    $imageSize === false ||
    $imageSize <= 0 ||
    $imageSize > 5 * 1024 * 1024
) {
    http_response_code(404);
    exit("Invalid image size.");
}

// --------------------------------------------------
// SEND SECURE IMAGE RESPONSE
// --------------------------------------------------

header("Content-Type: " . $mimeType);
header("Content-Length: " . $imageSize);
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

header(
    "Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0"
);

header("Pragma: no-cache");
header("Expires: 0");

readfile($imagePath);

exit;

?>
