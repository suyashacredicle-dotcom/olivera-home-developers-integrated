
<?php

// --------------------------------------------------
// SECURE GALLERY PHOTO UPLOAD HANDLER
// --------------------------------------------------

require_once __DIR__ . "/../../includes/permissions.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/activity_logger.php";

// --------------------------------------------------
// VALIDATION EXCEPTION
// --------------------------------------------------

class GalleryUploadValidationException extends RuntimeException
{
}

// --------------------------------------------------
// SUPER ADMIN PERMISSION
// --------------------------------------------------

requirePermission("manage_admins");

// --------------------------------------------------
// POST REQUEST ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("Method Not Allowed");
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
// ALLOWED GALLERY LOCATIONS
// --------------------------------------------------

$allowedLocations = [
    "Delhi",
    "Noida",
    "Gurugram",
    "Jewar",
    "Vrindavan",
    "Uttarakhand",
    "Goa",
    "Dubai"
];

$location = $_POST["location"] ?? "";

if (
    !is_string($location) ||
    !in_array($location, $allowedLocations, true)
) {
    http_response_code(400);
    exit("Please select a valid gallery location.");
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
// VALIDATE TITLE AND DESCRIPTION
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
    exit("Photo description must not exceed 5000 characters.");
}

$title = ($title === "") ? null : $title;
$description = ($description === "") ? null : $description;

// --------------------------------------------------
// SUPPORT MULTIPLE AND SINGLE PHOTO UPLOADS
// --------------------------------------------------

$uploadedFiles = [];

// Multiple photo upload: photos[]

if (
    isset($_FILES["photos"]) &&
    is_array($_FILES["photos"]) &&
    isset($_FILES["photos"]["name"]) &&
    is_array($_FILES["photos"]["name"])
) {

    $multiple = $_FILES["photos"];
    $count = count($multiple["name"]);

    if (
        !isset(
            $multiple["error"],
            $multiple["tmp_name"],
            $multiple["size"]
        ) ||
        !is_array($multiple["error"]) ||
        !is_array($multiple["tmp_name"]) ||
        !is_array($multiple["size"]) ||
        count($multiple["error"]) !== $count ||
        count($multiple["tmp_name"]) !== $count ||
        count($multiple["size"]) !== $count
    ) {
        http_response_code(400);
        exit("Invalid multiple-photo upload.");
    }

    for ($i = 0; $i < $count; $i++) {

        if (
            !is_string($multiple["name"][$i]) ||
            !is_string($multiple["tmp_name"][$i]) ||
            !is_int($multiple["error"][$i]) ||
            !is_int($multiple["size"][$i])
        ) {
            http_response_code(400);
            exit("Invalid uploaded photo information.");
        }

        if ($multiple["error"][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $uploadedFiles[] = [
            "name" => $multiple["name"][$i],
            "tmp_name" => $multiple["tmp_name"][$i],
            "error" => $multiple["error"][$i],
            "size" => $multiple["size"][$i]
        ];
    }

// Legacy single photo upload: photo

} elseif (
    isset($_FILES["photo"]) &&
    is_array($_FILES["photo"])
) {

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

    if ($file["error"] !== UPLOAD_ERR_NO_FILE) {
        $uploadedFiles[] = $file;
    }
}

// --------------------------------------------------
// CHECK UPLOAD COUNT
// --------------------------------------------------

if (count($uploadedFiles) === 0) {
    http_response_code(400);
    exit("Please select at least one photo.");
}

if (count($uploadedFiles) > 30) {
    http_response_code(400);
    exit("You can upload a maximum of 30 photos at a time.");
}

// --------------------------------------------------
// CHECK IMAGE VALIDATION SUPPORT
// --------------------------------------------------

if (
    !extension_loaded("fileinfo") ||
    !function_exists("finfo_open") ||
    !function_exists("getimagesize") ||
    !function_exists("imagecreatefromstring")
) {
    error_log("Required image validation functions are unavailable.");

    http_response_code(500);
    exit("Image validation is currently unavailable.");
}

// --------------------------------------------------
// PREPARE GALLERY STORAGE DIRECTORY
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
// IMAGE VALIDATION SETTINGS
// --------------------------------------------------

$maxFileSize = 5 * 1024 * 1024;
$maxDimension = 10000;
$maxPixels = 40000000;

$allowedTypes = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp"
];

// --------------------------------------------------
// INITIALIZE UPLOAD DATA
// --------------------------------------------------

$savedFiles = [];
$photoRecords = [];
$photoIds = [];

// --------------------------------------------------
// VALIDATE, SAVE AND INSERT PHOTOS
// --------------------------------------------------

try {

    // --------------------------------------------------
    // VALIDATE AND SAVE EACH PHOTO
    // --------------------------------------------------

    foreach ($uploadedFiles as $index => $file) {

        $photoNumber = $index + 1;

        // Check PHP upload error.

        if ($file["error"] !== UPLOAD_ERR_OK) {

            switch ($file["error"]) {

                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:

                    throw new GalleryUploadValidationException(
                        "Photo " . $photoNumber . " exceeds the allowed upload size."
                    );

                case UPLOAD_ERR_PARTIAL:

                    throw new GalleryUploadValidationException(
                        "Photo " . $photoNumber . " upload was incomplete."
                    );

                case UPLOAD_ERR_NO_TMP_DIR:
                case UPLOAD_ERR_CANT_WRITE:

                    throw new RuntimeException(
                        "Server could not store photo " . $photoNumber . "."
                    );

                default:

                    throw new GalleryUploadValidationException(
                        "Photo " . $photoNumber . " upload failed."
                    );
            }
        }

        // Verify genuine uploaded file.

        if (
            $file["tmp_name"] === "" ||
            !is_uploaded_file($file["tmp_name"])
        ) {
            throw new GalleryUploadValidationException(
                "Invalid uploaded file at position " . $photoNumber . "."
            );
        }

        // Validate actual file size.

        $actualFileSize = filesize($file["tmp_name"]);

        if (
            $actualFileSize === false ||
            $actualFileSize <= 0 ||
            $actualFileSize > $maxFileSize ||
            $file["size"] !== $actualFileSize
        ) {
            throw new GalleryUploadValidationException(
                "Each photo must be between 1 byte and 5 MB."
            );
        }

        // Clean original filename.

        $submittedFilename = str_replace(
            "\\",
            "/",
            $file["name"]
        );

        $originalFilename = basename($submittedFilename);

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
            throw new GalleryUploadValidationException(
                "Invalid original filename for photo " . $photoNumber . "."
            );
        }

        $originalFilenameLength = function_exists("mb_strlen")
            ? mb_strlen($originalFilename, "UTF-8")
            : strlen($originalFilename);

        if ($originalFilenameLength > 255) {
            throw new GalleryUploadValidationException(
                "Original filename is too long."
            );
        }

        // Detect actual MIME type.

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            throw new RuntimeException(
                "Unable to validate uploaded images."
            );
        }

        $mimeType = finfo_file(
            $finfo,
            $file["tmp_name"]
        );

        finfo_close($finfo);

        // Inspect actual image.

        $imageInfo = @getimagesize($file["tmp_name"]);

        if ($imageInfo === false) {
            throw new GalleryUploadValidationException(
                "Photo " . $photoNumber . " is not a valid image."
            );
        }

        if (
            !is_string($mimeType) ||
            !isset($allowedTypes[$mimeType]) ||
            !isset($imageInfo["mime"]) ||
            $imageInfo["mime"] !== $mimeType
        ) {
            throw new GalleryUploadValidationException(
                "Only JPG, PNG and WEBP images are allowed."
            );
        }

        // Validate image dimensions.

        $imageWidth = $imageInfo[0] ?? 0;
        $imageHeight = $imageInfo[1] ?? 0;

        if (
            !is_int($imageWidth) ||
            !is_int($imageHeight) ||
            $imageWidth <= 0 ||
            $imageHeight <= 0 ||
            $imageWidth > $maxDimension ||
            $imageHeight > $maxDimension ||
            ($imageWidth * $imageHeight) > $maxPixels
        ) {
            throw new GalleryUploadValidationException(
                "Photo " . $photoNumber . " has excessive dimensions."
            );
        }

        // Verify image can actually be decoded.

        $imageContents = file_get_contents($file["tmp_name"]);

        if ($imageContents === false) {
            throw new RuntimeException(
                "Unable to read photo " . $photoNumber . "."
            );
        }

        $decodedImage = @imagecreatefromstring($imageContents);

        unset($imageContents);

        if ($decodedImage === false) {
            throw new GalleryUploadValidationException(
                "Photo " . $photoNumber . " could not be decoded."
            );
        }

        imagedestroy($decodedImage);

        // Generate unpredictable filename.

        $randomName = bin2hex(random_bytes(20));

        $extension = $allowedTypes[$mimeType];

        $filename = $randomName . "." . $extension;

        $destination = $realUploadDirectory .
            DIRECTORY_SEPARATOR .
            $filename;

        if (file_exists($destination)) {
            throw new RuntimeException(
                "Unable to prepare photo storage."
            );
        }

        // Move uploaded image into gallery directory.

        if (!move_uploaded_file($file["tmp_name"], $destination)) {
            throw new RuntimeException(
                "Unable to save photo " . $photoNumber . "."
            );
        }

        $savedFiles[] = $destination;

        // Store validated photo information for database insertion.

        $photoRecords[] = [
            "description" => $description,
            "location" => $location,
            "image_filename" => $filename,
            "original_filename" => $originalFilename,
            "visibility" => $visibility
        ];
    }

    // --------------------------------------------------
    // START DATABASE TRANSACTION
    // --------------------------------------------------

    $pdo->beginTransaction();

    // Lock existing photos in this location.

    $existingStmt = $pdo->prepare("
        SELECT title, sort_order
        FROM gallery_photos
        WHERE location = ?
        ORDER BY id
        FOR UPDATE
    ");

    $existingStmt->execute([$location]);

    $existingPhotos = $existingStmt->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // DETERMINE TITLE PREFIX AND NEXT NUMBER
    // --------------------------------------------------

    $titlePrefix = ($title !== null) ? $title : $location;

    $nextPhotoNumber = 1;

    $sortOrder = 0;

    foreach ($existingPhotos as $existingPhoto) {

        // Find the next available number for this title prefix.

        $existingTitle = trim(
            (string)($existingPhoto["title"] ?? "")
        );

        if (
            preg_match(
                '/^' . preg_quote($titlePrefix, '/') . '\s+(\d+)$/i',
                $existingTitle,
                $matches
            )
        ) {
            $nextPhotoNumber = max(
                $nextPhotoNumber,
                ((int)$matches[1]) + 1
            );
        }

        // Find the highest existing display order.

        $sortOrder = max(
            $sortOrder,
            (int)($existingPhoto["sort_order"] ?? 0)
        );
    }

    // --------------------------------------------------
    // PREPARE INSERT STATEMENT
    // --------------------------------------------------

    $insertStmt = $pdo->prepare("
        INSERT INTO gallery_photos
        (
            title,
            description,
            location,
            image_filename,
            original_filename,
            visibility,
            sort_order,
            is_featured,
            uploaded_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)
    ");

    // --------------------------------------------------
    // INSERT EACH PHOTO ONCE
    // --------------------------------------------------

    foreach ($photoRecords as $record) {

        $photoTitle = $titlePrefix . " " . $nextPhotoNumber;

        $generatedTitleLength = function_exists("mb_strlen")
            ? mb_strlen($photoTitle, "UTF-8")
            : strlen($photoTitle);

        if ($generatedTitleLength > 150) {
            throw new GalleryUploadValidationException(
                "The generated photo title exceeds 150 characters. Please use a shorter title."
            );
        }

        $sortOrder++;
        $nextPhotoNumber++;

        $insertStmt->execute([
            $photoTitle,
            $record["description"],
            $record["location"],
            $record["image_filename"],
            $record["original_filename"],
            $record["visibility"],
            $sortOrder,
            $adminId
        ]);

        $photoIds[] = (int)$pdo->lastInsertId();
    }

    // --------------------------------------------------
    // COMMIT ONCE
    // --------------------------------------------------

    $pdo->commit();

} catch (Throwable $e) {

    // Roll back database changes.

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Remove uploaded files if the operation failed.

    foreach ($savedFiles as $savedFile) {

        if (is_file($savedFile) && !unlink($savedFile)) {
            error_log(
                "Gallery cleanup failed for: " . basename($savedFile)
            );
        }
    }

    error_log("Gallery upload failed: " . $e->getMessage());

    if ($e instanceof GalleryUploadValidationException) {

        http_response_code(400);

        exit(
            "Upload failed: " .
            htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8")
        );
    }

    http_response_code(500);

    exit("Gallery upload failed due to a server error. Please try again.");
}

// --------------------------------------------------
// RECORD ADMIN ACTIVITY
// --------------------------------------------------

try {

    logActivity(
        $pdo,
        "GALLERY_PHOTO_UPLOADED",
        count($photoIds) .
        " gallery photo(s) uploaded to " .
        $location .
        ". Photo IDs: " .
        implode(", ", $photoIds) .
        ". Visibility: " .
        $visibility
    );

} catch (Throwable $e) {

    error_log(
        "Gallery activity logging failed: " . $e->getMessage()
    );
}

// --------------------------------------------------
// ROTATE CSRF TOKEN
// --------------------------------------------------

try {

    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

} catch (Throwable $e) {

    error_log(
        "Gallery CSRF token rotation failed: " . $e->getMessage()
    );
}

// --------------------------------------------------
// SUCCESS MESSAGE
// --------------------------------------------------

$_SESSION["gallery_message"] =
    count($photoIds) .
    " photo(s) uploaded successfully to " .
    $location .
    ".";

$_SESSION["gallery_message_type"] = "success";

// --------------------------------------------------
// REDIRECT TO GALLERY MANAGEMENT
// --------------------------------------------------

header(
    "Location: ../manage_gallery.php?location=" .
    urlencode($location)
);

exit;

?>
