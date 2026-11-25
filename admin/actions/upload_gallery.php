
<?php

// --------------------------------------------------
// SECURE GALLERY PHOTO UPLOAD HANDLER
// --------------------------------------------------

require_once __DIR__ . "/../../includes/permissions.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/activity_logger.php";

// Only Super Admin has manage_admins permission.
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
// CSRF PROTECTION
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
    exit("Invalid request. Please reload the page.");
}

// --------------------------------------------------
// VALIDATE ADMIN ID
// --------------------------------------------------

$adminId = filter_var(
    $_SESSION["admin_id"] ?? null,
    FILTER_VALIDATE_INT
);

if ($adminId === false || $adminId === null || $adminId <= 0) {
    http_response_code(403);
    exit("Invalid administrator account.");
}

// --------------------------------------------------
// VALIDATE VISIBILITY
// --------------------------------------------------

$visibility = $_POST["visibility"] ?? "";

if (
    !is_string($visibility) ||
    !in_array($visibility, ["public", "employees"], true)
) {
    http_response_code(400);
    exit("Invalid gallery visibility.");
}

// --------------------------------------------------
// VALIDATE PHOTO DETAILS
// --------------------------------------------------

$titleInput = $_POST["title"] ?? "";
$descriptionInput = $_POST["description"] ?? "";

if (
    !is_string($titleInput) ||
    !is_string($descriptionInput)
) {
    http_response_code(400);
    exit("Invalid photo details.");
}

$title = trim($titleInput);
$description = trim($descriptionInput);

$titleLength = function_exists("mb_strlen")
    ? mb_strlen($title, "UTF-8")
    : strlen($title);

$descriptionLength = function_exists("mb_strlen")
    ? mb_strlen($description, "UTF-8")
    : strlen($description);

if ($titleLength > 150) {
    http_response_code(400);
    exit("Photo title must not exceed 150 characters.");
}

if ($descriptionLength > 5000) {
    http_response_code(400);
    exit("Photo description is too long.");
}

$title = ($title === "") ? null : $title;
$description = ($description === "") ? null : $description;

// --------------------------------------------------
// CHECK UPLOADED FILE STRUCTURE
// --------------------------------------------------

if (
    !isset($_FILES["photo"]) ||
    !is_array($_FILES["photo"])
) {
    http_response_code(400);
    exit("Please select a photo.");
}

$file = $_FILES["photo"];

if (
    !isset(
        $file["error"],
        $file["tmp_name"],
        $file["size"],
        $file["name"]
    ) ||
    !is_int($file["error"]) ||
    !is_string($file["tmp_name"]) ||
    !is_int($file["size"]) ||
    !is_string($file["name"])
) {
    http_response_code(400);
    exit("Invalid upload.");
}

// --------------------------------------------------
// SAVE ORIGINAL DESKTOP FILENAME
// --------------------------------------------------

$submittedFilename = str_replace(
    "\\",
    "/",
    $file["name"]
);

$originalFilename = basename($submittedFilename);

// Remove control characters.
$originalFilename = preg_replace(
    '/[\x00-\x1F\x7F]/',
    '',
    $originalFilename
);

if (
    !is_string($originalFilename) ||
    $originalFilename === "" ||
    $originalFilename === "." ||
    $originalFilename === ".."
) {
    http_response_code(400);
    exit("Invalid original photo filename.");
}

$originalFilenameLength = function_exists("mb_strlen")
    ? mb_strlen($originalFilename, "UTF-8")
    : strlen($originalFilename);

if ($originalFilenameLength > 255) {
    http_response_code(400);
    exit("Original photo filename is too long.");
}

// --------------------------------------------------
// CHECK UPLOAD ERROR
// --------------------------------------------------

if ($file["error"] !== UPLOAD_ERR_OK) {

    http_response_code(400);

    switch ($file["error"]) {

        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            exit("Photo exceeds the allowed upload size.");

        case UPLOAD_ERR_PARTIAL:
            exit("Photo upload was incomplete.");

        case UPLOAD_ERR_NO_FILE:
            exit("Please select a photo.");

        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            error_log("Gallery upload failed due to a server storage error.");
            exit("Server could not store the uploaded photo.");

        default:
            exit("Photo upload failed. Please try again.");
    }
}

// --------------------------------------------------
// VERIFY UPLOADED TEMPORARY FILE
// --------------------------------------------------

if (
    $file["tmp_name"] === "" ||
    !is_uploaded_file($file["tmp_name"])
) {
    http_response_code(400);
    exit("Invalid uploaded file.");
}

// --------------------------------------------------
// VALIDATE FILE SIZE
// --------------------------------------------------

$maxFileSize = 5 * 1024 * 1024;

$actualFileSize = filesize($file["tmp_name"]);

if (
    $actualFileSize === false ||
    $actualFileSize <= 0 ||
    $actualFileSize > $maxFileSize ||
    $file["size"] !== $actualFileSize
) {
    http_response_code(400);
    exit("Photo size must be between 1 byte and 5 MB.");
}

// --------------------------------------------------
// VERIFY IMAGE VALIDATION EXTENSIONS
// --------------------------------------------------

if (
    !extension_loaded("fileinfo") ||
    !function_exists("finfo_open") ||
    !function_exists("getimagesize")
) {
    error_log("Required image validation functions are unavailable.");

    http_response_code(500);
    exit("Image validation is currently unavailable.");
}

// --------------------------------------------------
// DETECT ACTUAL MIME TYPE
// --------------------------------------------------

$finfo = finfo_open(FILEINFO_MIME_TYPE);

if ($finfo === false) {
    http_response_code(500);
    exit("Unable to validate the uploaded image.");
}

$mimeType = finfo_file(
    $finfo,
    $file["tmp_name"]
);

finfo_close($finfo);

// --------------------------------------------------
// INSPECT IMAGE CONTENT AND DIMENSIONS
// --------------------------------------------------

$imageInfo = getimagesize($file["tmp_name"]);

if ($imageInfo === false) {
    http_response_code(400);
    exit("The uploaded file is not a valid image.");
}

$allowedTypes = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp"
];

if (
    !is_string($mimeType) ||
    !isset($allowedTypes[$mimeType]) ||
    !isset($imageInfo["mime"]) ||
    $imageInfo["mime"] !== $mimeType
) {
    http_response_code(415);
    exit("Only JPG, PNG and WEBP images are allowed.");
}

// --------------------------------------------------
// VALIDATE IMAGE DIMENSIONS
// --------------------------------------------------

$imageWidth = $imageInfo[0] ?? 0;
$imageHeight = $imageInfo[1] ?? 0;

$maxDimension = 10000;
$maxPixels = 40000000;

if (
    !is_int($imageWidth) ||
    !is_int($imageHeight) ||
    $imageWidth <= 0 ||
    $imageHeight <= 0 ||
    $imageWidth > $maxDimension ||
    $imageHeight > $maxDimension ||
    ($imageWidth * $imageHeight) > $maxPixels
) {
    http_response_code(400);
    exit("Image dimensions are too large.");
}

// --------------------------------------------------
// VERIFY IMAGE CAN BE DECODED
// --------------------------------------------------

if (!function_exists("imagecreatefromstring")) {
    http_response_code(500);
    exit("Image processing is currently unavailable.");
}

$imageContents = file_get_contents($file["tmp_name"]);

if ($imageContents === false) {
    http_response_code(400);
    exit("Unable to read the uploaded image.");
}

$decodedImage = @imagecreatefromstring($imageContents);

unset($imageContents);

if ($decodedImage === false) {
    http_response_code(400);
    exit("The uploaded image could not be decoded.");
}

imagedestroy($decodedImage);

// --------------------------------------------------
// PREPARE SECURE STORAGE LOCATION
// --------------------------------------------------

$uploadDirectory = __DIR__ . "/../../uploads/gallery";

$realUploadDirectory = realpath($uploadDirectory);

if (
    $realUploadDirectory === false ||
    !is_dir($realUploadDirectory) ||
    !is_writable($realUploadDirectory)
) {
    error_log("Gallery directory is unavailable or not writable.");

    http_response_code(500);
    exit("Gallery storage is currently unavailable.");
}

// --------------------------------------------------
// GENERATE RANDOM STORAGE FILENAME AND CSRF TOKEN
// --------------------------------------------------

try {

    $randomName = bin2hex(random_bytes(20));
    $newCsrfToken = bin2hex(random_bytes(32));

} catch (Throwable $e) {

    error_log("Gallery security token generation failed: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to prepare the photo upload.");
}

$extension = $allowedTypes[$mimeType];

$filename = $randomName . "." . $extension;

$destination = $realUploadDirectory .
    DIRECTORY_SEPARATOR .
    $filename;

// --------------------------------------------------
// PREVENT DESTINATION OVERWRITE
// --------------------------------------------------

if (file_exists($destination)) {
    http_response_code(500);
    exit("Unable to prepare the photo upload.");
}

// --------------------------------------------------
// MOVE PHOTO INTO PROTECTED GALLERY FOLDER
// --------------------------------------------------

if (!move_uploaded_file($file["tmp_name"], $destination)) {

    error_log("Gallery upload failed: Unable to move uploaded file.");

    http_response_code(500);
    exit("Unable to save the uploaded photo.");
}

// --------------------------------------------------
// SAVE PHOTO DETAILS IN DATABASE
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        INSERT INTO gallery_photos
            (
                title,
                description,
                image_filename,
                original_filename,
                visibility,
                uploaded_by
            )
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $title,
        $description,
        $filename,
        $originalFilename,
        $visibility,
        $adminId
    ]);

    $photoId = $pdo->lastInsertId();

} catch (Throwable $e) {

    if (is_file($destination)) {
        if (!unlink($destination)) {
            error_log("Gallery cleanup failed for file: " . $filename);
        }
    }

    error_log("Gallery database insertion failed: " . $e->getMessage());

    http_response_code(500);
    exit("The photo could not be saved. Please try again.");
}

// --------------------------------------------------
// RECORD ADMIN ACTIVITY
// --------------------------------------------------

try {

    logActivity(
        $pdo,
        "GALLERY_PHOTO_UPLOADED",
        "Gallery photo uploaded. Photo ID: " .
        $photoId .
        ". Original filename: " .
        $originalFilename .
        ". Visibility: " .
        $visibility
    );

} catch (Throwable $e) {

    error_log("Gallery activity logging failed: " . $e->getMessage());
}

// --------------------------------------------------
// ROTATE CSRF TOKEN
// --------------------------------------------------

$_SESSION["csrf_token"] = $newCsrfToken;

// --------------------------------------------------
// SUCCESS MESSAGE AND REDIRECT
// --------------------------------------------------

$_SESSION["gallery_message"] = "Photo uploaded successfully.";
$_SESSION["gallery_message_type"] = "success";

header("Location: ../manage_gallery.php");
exit;

?>
