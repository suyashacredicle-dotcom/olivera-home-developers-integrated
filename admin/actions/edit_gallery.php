
<?php

// --------------------------------------------------
// SECURE GALLERY PHOTO EDIT PAGE
// --------------------------------------------------

require_once __DIR__ . "/../../includes/permissions.php";
require_once __DIR__ . "/../../config/database.php";

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
// ALLOWED LOCATIONS
// --------------------------------------------------

$locations = [
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
// FETCH PHOTO
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            description,
            image_filename,
            visibility,
            location,
            sort_order,
            is_featured
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
    !in_array($photo["visibility"], ["public", "employees"], true) ||
    !in_array($photo["location"], $locations, true) ||
    !is_numeric($photo["sort_order"]) ||
    !in_array((string)$photo["is_featured"], ["0", "1"], true)
) {
    error_log("Invalid gallery data for photo ID: " . $id);

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

function editGalleryEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
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

    <title>Edit Gallery Photo | Olivera Admin</title>

    <style>

        :root {
            --navy: #071b38;
            --navy-light: #102b50;
            --gold: #d6a63b;
            --gold-light: #edca76;
            --ivory: #f7f5ef;
            --text: #172b46;
            --muted: #68768a;
            --border: #e9e2d4;
            --surface: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            background: var(--ivory);
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text);
        }

        .container {
            max-width: 780px;
            margin: 0 auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 13px;
            overflow: hidden;
        }

        .header {
            padding: 25px 30px;
            background: linear-gradient(120deg, var(--navy), var(--navy-light));
            color: white;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 25px;
        }

        .header p {
            margin: 0;
            color: #d7dfeb;
            font-size: 13px;
            line-height: 1.6;
        }

        .form-content {
            padding: 28px 30px;
        }

        .section-title {
            margin: 0 0 20px;
            color: var(--navy);
            font-size: 16px;
            font-weight: 750;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-size: 12px;
            font-weight: 750;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd5c5;
            border-radius: 7px;
            background: white;
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,.12);
        }

        .help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        .filename {
            padding: 13px;
            background: #f8f6f0;
            border: 1px solid var(--border);
            border-radius: 7px;
            overflow-wrap: anywhere;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .checkbox-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            background: #fcf8ed;
            border: 1px solid #ead9b4;
            border-radius: 8px;
        }

        .checkbox-card input {
            width: 18px;
            height: 18px;
            margin: 1px 0 0;
            accent-color: var(--gold);
        }

        .checkbox-card label {
            margin: 0;
            cursor: pointer;
            line-height: 1.5;
        }

        .checkbox-card small {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 400;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        button,
        .back-link {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 44px;
            padding: 0 22px;
            border: 0;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 750;
            cursor: pointer;
        }

        button {
            background: linear-gradient(120deg, var(--gold-light), var(--gold));
            color: var(--navy);
        }

        .back-link {
            background: #f0ede5;
            color: var(--text);
        }

        button:hover,
        .back-link:hover {
            filter: brightness(.96);
        }

        @media (max-width: 600px) {

            body {
                padding: 15px 10px;
            }

            .header,
            .form-content {
                padding: 22px 18px;
            }

            .header h1 {
                font-size: 21px;
            }

            .buttons {
                flex-direction: column;
            }

            button,
            .back-link {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>Edit Gallery Photo</h1>

        <p>
            Manage this photograph's location, visibility,
            display order and featured status.
        </p>

    </div>

    <div class="form-content">

        <form action="update_gallery.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo editGalleryEscape($csrfToken); ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?php echo (int)$photo["id"]; ?>"
            >

            <h2 class="section-title">Photo Information</h2>

            <div class="form-group">

                <label for="title">Photo Title</label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="150"
                    value="<?php echo editGalleryEscape($photo["title"]); ?>"
                >

            </div>

            <div class="form-group">

                <label for="description">Description</label>

                <textarea
                    id="description"
                    name="description"
                    maxlength="5000"
                ><?php echo editGalleryEscape($photo["description"]); ?></textarea>

            </div>

            <div class="form-group">

                <label>Stored Image Filename</label>

                <div class="filename">
                    <?php echo editGalleryEscape($photo["image_filename"]); ?>
                </div>

            </div>

            <h2 class="section-title">Location & Display</h2>

            <div class="form-group">

                <label for="location">Photo Location</label>

                <select id="location" name="location" required>

                    <?php foreach ($locations as $location): ?>

                        <option
                            value="<?php echo editGalleryEscape($location); ?>"
                            <?php echo $photo["location"] === $location ? "selected" : ""; ?>
                        >
                            <?php echo editGalleryEscape($location); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <span class="help">
                    Moving this photo changes the location collection where it is managed.
                </span>

            </div>

            <div class="form-group">

                <label for="sort_order">Display Order</label>

                <input
                    type="number"
                    id="sort_order"
                    name="sort_order"
                    min="0"
                    max="4294967295"
                    step="1"
                    required
                    value="<?php echo (int)$photo["sort_order"]; ?>"
                >

                <span class="help">
                    Lower numbers appear first within the location collection.
                </span>

            </div>

            <div class="form-group">

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

            </div>

            <div class="form-group">

                <div class="checkbox-card">

                    <input
                        type="checkbox"
                        id="is_featured"
                        name="is_featured"
                        value="1"
                        <?php echo (int)$photo["is_featured"] === 1 ? "checked" : ""; ?>
                    >

                    <label for="is_featured">
                        Mark as Featured Photo

                        <small>
                            Identifies this photograph as a featured image
                            in gallery management.
                        </small>
                    </label>

                </div>

            </div>

            <div class="buttons">

                <button type="submit">
                    Save Changes
                </button>

                <a href="../manage_gallery.php" class="back-link">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>
