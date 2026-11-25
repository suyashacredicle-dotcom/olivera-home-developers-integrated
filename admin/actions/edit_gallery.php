
<?php

// --------------------------------------------------
// SECURE GALLERY PHOTO EDIT PAGE
// --------------------------------------------------

require_once __DIR__ . "/../../includes/permissions.php";
require_once __DIR__ . "/../../config/database.php";

// Only Super Admin can manage gallery photos.
requirePermission("manage_admins");

// --------------------------------------------------
// GET REQUEST ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    http_response_code(405);
    exit("Method not allowed.");
}

// --------------------------------------------------
// VALIDATE PHOTO ID
// --------------------------------------------------

$idValue = $_GET["id"] ?? null;
$id = filter_var($idValue, FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    http_response_code(400);
    exit("Invalid gallery photo ID.");
}

// --------------------------------------------------
// FETCH PHOTO
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            description,
            image_filename,
            visibility
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

} catch (Throwable $e) {

    error_log("Gallery edit fetch error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to load gallery photo.");
}

// --------------------------------------------------
// VALIDATE PHOTO DATA
// --------------------------------------------------

if (
    !in_array(
        $photo["visibility"],
        ["public", "employees"],
        true
    )
) {
    error_log("Invalid gallery visibility for photo ID: " . $id);

    http_response_code(500);
    exit("Invalid gallery photo information.");
}

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

try {

    if (
        empty($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"])
    ) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    $csrfToken = $_SESSION["csrf_token"];

} catch (Throwable $e) {

    error_log("Gallery edit CSRF generation failed: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to prepare the edit form.");
}

// --------------------------------------------------
// OUTPUT ESCAPING
// --------------------------------------------------

function editGalleryEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Gallery Photo</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #263238;
        }

        .container {
            max-width: 750px;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 25px;
        }

        .subtitle {
            color: #687782;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccd4dc;
            border-radius: 7px;
            font-size: 15px;
            font-family: inherit;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: 2px solid #1769aa;
            outline-offset: 1px;
        }

        .filename {
            padding: 12px;
            background: #f1f4f7;
            border-radius: 7px;
            overflow-wrap: anywhere;
            line-height: 1.5;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        button,
        .back-link {
            display: inline-block;
            padding: 12px 20px;
            border: 0;
            border-radius: 7px;
            text-decoration: none;
            font-size: 15px;
            cursor: pointer;
        }

        button {
            background: #1769aa;
            color: #ffffff;
        }

        .back-link {
            background: #e8edf2;
            color: #263238;
        }

        button:hover {
            background: #10558d;
        }

        .back-link:hover {
            background: #d9e0e7;
        }

        @media (max-width: 600px) {

            .container {
                padding: 22px 18px;
            }

            .buttons {
                flex-direction: column;
            }

            button,
            .back-link {
                width: 100%;
                text-align: center;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <h1>Edit Gallery Photo</h1>

    <p class="subtitle">
        Update the photo title, description and visibility.
    </p>

    <form action="update_gallery.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo editGalleryEscape($csrfToken); ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?php echo (int) $photo["id"]; ?>"
        >

        <label for="title">Photo Title</label>

        <input
            type="text"
            id="title"
            name="title"
            maxlength="150"
            value="<?php echo editGalleryEscape($photo["title"]); ?>"
        >

        <label for="description">Description</label>

        <textarea
            id="description"
            name="description"
            maxlength="5000"
        ><?php echo editGalleryEscape($photo["description"]); ?></textarea>

        <label>Image Filename</label>

        <div class="filename">
            <?php echo editGalleryEscape($photo["image_filename"]); ?>
        </div>

        <label for="visibility">Photo Visibility</label>

        <select id="visibility" name="visibility" required>

            <option
                value="public"
                <?php echo $photo["visibility"] === "public" ? "selected" : ""; ?>
            >
                Public Website
            </option>

            <option
                value="employees"
                <?php echo $photo["visibility"] === "employees" ? "selected" : ""; ?>
            >
                Employees Only
            </option>

        </select>

        <div class="buttons">

            <button type="submit">
                Save Changes
            </button>

            <a
                href="../manage_gallery.php"
                class="back-link"
            >
                Cancel
            </a>

        </div>

    </form>

</div>

</body>
</html>
