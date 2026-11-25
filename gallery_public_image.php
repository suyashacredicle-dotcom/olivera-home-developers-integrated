
<?php

// --------------------------------------------------
// SECURE PUBLIC GALLERY IMAGE VIEWER
// --------------------------------------------------

require_once __DIR__ . "/config/database.php";

// --------------------------------------------------
// ACCEPT GET REQUESTS ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");
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
    http_response_code(404);
    exit("Image not found.");
}

// --------------------------------------------------
// FETCH PUBLIC PHOTO ONLY
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT image_filename
        FROM gallery_photos
        WHERE id = ?
        AND visibility = 'public'
        LIMIT 1
    ");

    $stmt->execute([$photoId]);

    $photo = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        "Public gallery image error: " . $e->getMessage()
    );

    http_response_code(500);
    exit("Unable to load image.");

}

if (!$photo) {
    http_response_code(404);
    exit("Image not found.");
}

// --------------------------------------------------
// VALIDATE IMAGE FILENAME
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
    exit("Image not found.");

}

// --------------------------------------------------
// RESOLVE IMAGE DIRECTORY
// --------------------------------------------------

$galleryDirectory = realpath(
    __DIR__ . "/uploads/gallery"
);

if (
    $galleryDirectory === false ||
    !is_dir($galleryDirectory)
) {

    error_log("Public gallery image directory unavailable.");

    http_response_code(404);
    exit("Image not found.");

}

// --------------------------------------------------
// RESOLVE IMAGE PATH
// --------------------------------------------------

$imagePath = realpath(
    $galleryDirectory . DIRECTORY_SEPARATOR . $filename
);

// --------------------------------------------------
// VALIDATE IMAGE PATH
// --------------------------------------------------

if (
    $imagePath === false ||
    dirname($imagePath) !== $galleryDirectory ||
    !is_file($imagePath) ||
    !is_readable($imagePath)
) {

    http_response_code(404);
    exit("Image not found.");

}

// --------------------------------------------------
// VALIDATE FILE SIZE
// --------------------------------------------------

$fileSize = filesize($imagePath);

if (
    $fileSize === false ||
    $fileSize < 1 ||
    $fileSize > 5 * 1024 * 1024
) {

    http_response_code(404);
    exit("Image not found.");

}

// --------------------------------------------------
// DETECT ACTUAL MIME TYPE
// --------------------------------------------------

if (!class_exists("finfo")) {

    http_response_code(500);
    exit("Image validation unavailable.");

}

$finfo = new finfo(FILEINFO_MIME_TYPE);

$mimeType = $finfo->file($imagePath);

// --------------------------------------------------
// ALLOWED IMAGE TYPES
// --------------------------------------------------

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
    exit("Unsupported image file.");

}

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("Content-Type: " . $mimeType);
header("Content-Length: " . $fileSize);
header("X-Content-Type-Options: nosniff");

header(
    'Content-Disposition: inline; filename="gallery-image.' .
    $extension . '"'
);

header("Cache-Control: public, max-age=3600");
header("X-Frame-Options: SAMEORIGIN");

// --------------------------------------------------
// DISPLAY IMAGE
// --------------------------------------------------

readfile($imagePath);

exit;

?>
