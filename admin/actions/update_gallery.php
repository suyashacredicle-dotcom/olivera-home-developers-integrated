
<?php

// --------------------------------------------------
// SECURE GALLERY PHOTO UPDATE HANDLER
// --------------------------------------------------

require_once __DIR__ . "/../../includes/permissions.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/activity_logger.php";

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
// ALLOWED LOCATIONS
// --------------------------------------------------

$allowedLocations = [
    "Delhi",
    "Noida",
    "Gurugram",
    "Jewar",
    "Vrindavan",
    "Uttarakhand",
    "Goa",
    "Dubai",
    "Unassigned"
];

// --------------------------------------------------
// VALIDATE FORM DATA
// --------------------------------------------------

$title = $_POST["title"] ?? "";
$description = $_POST["description"] ?? "";
$visibility = $_POST["visibility"] ?? "";
$location = $_POST["location"] ?? "";
$sortOrderInput = $_POST["sort_order"] ?? null;
$featuredInput = $_POST["is_featured"] ?? "0";

if (
    !is_string($title) ||
    !is_string($description) ||
    !is_string($visibility) ||
    !is_string($location) ||
    !is_string($featuredInput) ||
    !is_string($sortOrderInput)
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

if (!in_array($location, $allowedLocations, true)) {
    http_response_code(400);
    exit("Invalid gallery location.");
}

// --------------------------------------------------
// VALIDATE DISPLAY ORDER
// --------------------------------------------------

if (
    $sortOrderInput === "" ||
    !preg_match('/^(0|[1-9][0-9]*)$/', $sortOrderInput) ||
    (float)$sortOrderInput > 4294967295
) {
    http_response_code(400);
    exit("Invalid display order.");
}

$sortOrder = (int)$sortOrderInput;

// --------------------------------------------------
// VALIDATE FEATURED STATUS
// --------------------------------------------------

if (!in_array($featuredInput, ["0", "1"], true)) {
    http_response_code(400);
    exit("Invalid featured photo setting.");
}

$isFeatured = (int)$featuredInput;

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

    $pdo->beginTransaction();

    // --------------------------------------------------
    // FETCH AND LOCK EXISTING PHOTO
    // --------------------------------------------------

    $check = $pdo->prepare("
        SELECT
            id,
            title,
            location,
            visibility
        FROM gallery_photos
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ");

    $check->execute([
        "id" => $id
    ]);

    $existingPhoto = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existingPhoto) {
        throw new RuntimeException("Gallery photo not found.");
    }

    // --------------------------------------------------
    // VALIDATE EXISTING DATABASE VALUES
    // --------------------------------------------------

    $oldLocation = $existingPhoto["location"];
    $oldVisibility = $existingPhoto["visibility"];
    $oldTitle = (string)($existingPhoto["title"] ?? "");

    if (
        !is_string($oldLocation) ||
        !in_array($oldLocation, $allowedLocations, true)
    ) {
        throw new RuntimeException(
            "Existing gallery photo has an invalid location."
        );
    }

    if (
        !is_string($oldVisibility) ||
        !in_array($oldVisibility, ["public", "employees"], true)
    ) {
        throw new RuntimeException(
            "Existing gallery photo has invalid visibility."
        );
    }

    // --------------------------------------------------
    // AUTOMATIC TITLE UPDATE WHEN MOVING LOCATIONS
    // --------------------------------------------------

    // Automatically named examples:
    // Delhi 1, Delhi 2, Noida 1, etc.
    // Custom titles are preserved.

    $oldLocationPattern = '~^' .
        preg_quote($oldLocation, '~') .
        ' ([1-9][0-9]*)$~u';

    $isAutomaticallyNamed = preg_match(
        $oldLocationPattern,
        $oldTitle
    ) === 1;

    if ($oldLocation !== $location && $isAutomaticallyNamed) {

        // Retrieve destination collection photos.

        $destinationStmt = $pdo->prepare("
            SELECT id, title
            FROM gallery_photos
            WHERE location = :location
            FOR UPDATE
        ");

        $destinationStmt->execute([
            "location" => $location
        ]);

        $destinationPhotos = $destinationStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $highestNumber = 0;

        $destinationPattern = '~^' .
            preg_quote($location, '~') .
            ' ([1-9][0-9]*)$~u';

        foreach ($destinationPhotos as $destinationPhoto) {

            $destinationTitle = (string)(
                $destinationPhoto["title"] ?? ""
            );

            if (
                preg_match(
                    $destinationPattern,
                    $destinationTitle,
                    $matches
                ) === 1
            ) {
                $number = (int)$matches[1];

                if ($number > $highestNumber) {
                    $highestNumber = $number;
                }
            }
        }

        if ($highestNumber >= 2147483647) {
            throw new RuntimeException(
                "Destination location has reached its photo numbering limit."
            );
        }

        $title = $location . " " . ($highestNumber + 1);

        $generatedTitleLength = function_exists("mb_strlen")
            ? mb_strlen($title, "UTF-8")
            : strlen($title);

        if ($generatedTitleLength > 150) {
            throw new RuntimeException(
                "Generated photo title is too long."
            );
        }
    }

    // --------------------------------------------------
    // ENSURE ONLY ONE FEATURED PHOTO PER LOCATION
    // --------------------------------------------------

    if ($isFeatured === 1) {

        $clearFeatured = $pdo->prepare("
            UPDATE gallery_photos
            SET is_featured = 0
            WHERE location = :location
              AND id <> :id
        ");

        $clearFeatured->execute([
            "location" => $location,
            "id" => $id
        ]);
    }

    // --------------------------------------------------
    // UPDATE PHOTO INFORMATION
    // --------------------------------------------------

    $stmt = $pdo->prepare("
        UPDATE gallery_photos
        SET
            title = :title,
            description = :description,
            location = :location,
            sort_order = :sort_order,
            visibility = :visibility,
            is_featured = :is_featured
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        "title" => $title !== "" ? $title : null,
        "description" => $description !== "" ? $description : null,
        "location" => $location,
        "sort_order" => $sortOrder,
        "visibility" => $visibility,
        "is_featured" => $isFeatured,
        "id" => $id
    ]);

    // --------------------------------------------------
    // COMMIT TRANSACTION
    // --------------------------------------------------

    $pdo->commit();

    // --------------------------------------------------
    // ACTIVITY LOG
    // --------------------------------------------------

    try {

        logActivity(
            $pdo,
            "GALLERY_PHOTO_UPDATED",
            "Updated gallery photo ID: " . $id .
            " | Title: " . $oldTitle . " -> " . $title .
            " | Location: " . $oldLocation . " -> " . $location .
            " | Visibility: " . $oldVisibility . " -> " . $visibility .
            " | Display order: " . $sortOrder .
            " | Featured: " . ($isFeatured ? "Yes" : "No")
        );

    } catch (Throwable $logError) {

        error_log(
            "Gallery update activity log error: " .
            $logError->getMessage()
        );
    }

    // --------------------------------------------------
    // SUCCESS MESSAGE AND CSRF ROTATION
    // --------------------------------------------------

    $_SESSION["csrf_token"] = $newCsrfToken;

    $_SESSION["gallery_message"] =
        "Gallery photo updated successfully.";

    $_SESSION["gallery_message_type"] = "success";

    header(
        "Location: ../manage_gallery.php?location=" .
        urlencode($location)
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Gallery update error: " . $e->getMessage());

    if ($e->getMessage() === "Gallery photo not found.") {
        http_response_code(404);
        exit("Gallery photo not found.");
    }

    http_response_code(500);
    exit("Unable to update gallery photo. Please try again.");
}

?>
