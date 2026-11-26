
<?php

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/activity_logger.php";

requirePermission("manage_projects");

// --------------------------------------------------
// HELPERS
// --------------------------------------------------

function projectEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function projectSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim($value, '-');
}

function projectFlash(string $message, string $type = "success"): void
{
    $_SESSION["project_message"] = $message;
    $_SESSION["project_message_type"] = $type;
}

function projectDeleteImage(?string $path): void
{
    if (!$path || !is_string($path)) {
        return;
    }

    $prefix = "uploads/projects/";

    if (strpos($path, $prefix) !== 0) {
        return;
    }

    $filename = basename($path);

    if ($filename === "") {
        return;
    }

    $fullPath = dirname(__DIR__) . "/" . $prefix . $filename;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function projectUploadImage(string $field, ?string $existing = null): ?string
{
    if (
        !isset($_FILES[$field]) ||
        !is_array($_FILES[$field]) ||
        $_FILES[$field]["error"] === UPLOAD_ERR_NO_FILE
    ) {
        return $existing;
    }

    $file = $_FILES[$field];

    if ($file["error"] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Image upload failed. Please try again.");
    }

    if (
        !isset($file["tmp_name"], $file["size"]) ||
        !is_uploaded_file($file["tmp_name"])
    ) {
        throw new RuntimeException("Invalid uploaded image.");
    }

    if ((int)$file["size"] > 5 * 1024 * 1024) {
        throw new RuntimeException("Each image must be 5 MB or smaller.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file["tmp_name"]);

    $allowedTypes = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException("Only JPG, PNG and WEBP images are allowed.");
    }

    $imageInfo = @getimagesize($file["tmp_name"]);

    if (
        $imageInfo === false ||
        !isset($imageInfo["mime"]) ||
        $imageInfo["mime"] !== $mime
    ) {
        throw new RuntimeException("The uploaded file is not a valid image.");
    }

    $uploadDirectory = dirname(__DIR__) . "/uploads/projects/";

    if (
        !is_dir($uploadDirectory) &&
        !mkdir($uploadDirectory, 0755, true) &&
        !is_dir($uploadDirectory)
    ) {
        throw new RuntimeException("Unable to create the project image directory.");
    }

    // Prevent PHP and CGI execution inside the upload directory.
    $htaccessPath = $uploadDirectory . ".htaccess";

    if (!file_exists($htaccessPath)) {
        $htaccess = "Options -ExecCGI\n"
            . "<FilesMatch \"\\.(php[0-9]?|phtml|phar|cgi|pl|py|sh)$\">\n"
            . "    Require all denied\n"
            . "</FilesMatch>\n";

        @file_put_contents($htaccessPath, $htaccess, LOCK_EX);
    }

    $filename = bin2hex(random_bytes(24))
        . "."
        . $allowedTypes[$mime];

    $destination = $uploadDirectory . $filename;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        throw new RuntimeException("Unable to save the uploaded image.");
    }

    @chmod($destination, 0644);

    return "uploads/projects/" . $filename;
}

function projectUniqueSlug(PDO $pdo, string $title, int $excludeId = 0): string
{
    $base = projectSlug($title);

    if ($base === "") {
        throw new RuntimeException("Please enter a valid project name.");
    }

    $slug = $base;
    $counter = 2;

    while (true) {
        $sql = "SELECT id FROM projects WHERE slug = ?";
        $params = [$slug];

        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (!$stmt->fetchColumn()) {
            return $slug;
        }

        $slug = $base . "-" . $counter;
        $counter++;
    }
}

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// --------------------------------------------------
// FLASH MESSAGE
// --------------------------------------------------

$message = $_SESSION["project_message"] ?? "";
$messageType = $_SESSION["project_message_type"] ?? "success";

unset(
    $_SESSION["project_message"],
    $_SESSION["project_message_type"]
);

// --------------------------------------------------
// HANDLE POST ACTIONS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $uploadedImages = [];

    try {

        $submittedToken = $_POST["csrf_token"] ?? "";

        if (
            !is_string($submittedToken) ||
            !hash_equals($csrfToken, $submittedToken)
        ) {
            throw new RuntimeException("Your session expired. Please try again.");
        }

        $action = $_POST["action"] ?? "";

        if (!is_string($action)) {
            throw new RuntimeException("Invalid request.");
        }

        // --------------------------------------------------
        // SAVE PROJECT
        // --------------------------------------------------

        if ($action === "save") {

            $id = filter_var(
                $_POST["id"] ?? 0,
                FILTER_VALIDATE_INT
            );

            $id = $id === false ? 0 : (int)$id;

            $title = trim((string)($_POST["title"] ?? ""));
            $shortDescription = trim(
                (string)($_POST["short_description"] ?? "")
            );

            $heroDescription = trim(
                (string)($_POST["hero_description"] ?? "")
            );

            $introLabel = trim(
                (string)($_POST["intro_label"] ?? "")
            );

            $introHeading = trim(
                (string)($_POST["intro_heading"] ?? "")
            );

            $introContent = trim(
                (string)($_POST["intro_content"] ?? "")
            );

            $advantagesHeading = trim(
                (string)($_POST["advantages_heading"] ?? "")
            );

            $advantagesDescription = trim(
                (string)($_POST["advantages_description"] ?? "")
            );

            $advantagesInput = trim(
                (string)($_POST["advantages"] ?? "")
            );

            $ctaHeading = trim(
                (string)($_POST["cta_heading"] ?? "")
            );

            $ctaDescription = trim(
                (string)($_POST["cta_description"] ?? "")
            );

            $status = $_POST["status"] ?? "draft";

            $sortOrder = filter_var(
                $_POST["sort_order"] ?? 0,
                FILTER_VALIDATE_INT
            );

            $sortOrder = $sortOrder === false ? 0 : (int)$sortOrder;

            if ($title === "" || strlen($title) > 150) {
                throw new RuntimeException(
                    "Project name is required and must not exceed 150 characters."
                );
            }

            if (
                $shortDescription === "" ||
                strlen($shortDescription) > 500
            ) {
                throw new RuntimeException(
                    "Enter a short description of up to 500 characters."
                );
            }

            if (!in_array($status, ["published", "draft"], true)) {
                throw new RuntimeException("Invalid publication status.");
            }

            if ($sortOrder < 0 || $sortOrder > 1000000) {
                throw new RuntimeException("Invalid display order.");
            }

            if (
                strlen($heroDescription) > 10000 ||
                strlen($introContent) > 20000 ||
                strlen($advantagesInput) > 10000 ||
                strlen($ctaDescription) > 5000
            ) {
                throw new RuntimeException(
                    "One or more content fields are too long."
                );
            }

            // --------------------------------------------------
            // CHECK EXISTING PROJECT
            // --------------------------------------------------

            $existing = null;

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM projects
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([$id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    throw new RuntimeException("Project not found.");
                }
            }

            // --------------------------------------------------
            // PROCESS ADVANTAGES
            // Format: Title | Description
            // --------------------------------------------------

            $advantages = [];

            if ($advantagesInput !== "") {

                $lines = preg_split("/\r\n|\n|\r/", $advantagesInput);

                foreach ($lines as $line) {

                    $line = trim($line);

                    if ($line === "") {
                        continue;
                    }

                    $parts = explode("|", $line, 2);

                    if (count($parts) !== 2) {
                        throw new RuntimeException(
                            "Enter each advantage as: Title | Description"
                        );
                    }

                    $advantageTitle = trim($parts[0]);
                    $advantageDescription = trim($parts[1]);

                    if (
                        $advantageTitle === "" ||
                        $advantageDescription === "" ||
                        strlen($advantageTitle) > 150 ||
                        strlen($advantageDescription) > 1000
                    ) {
                        throw new RuntimeException(
                            "Check the title and description of each advantage."
                        );
                    }

                    $advantages[] = [
                        "title" => $advantageTitle,
                        "description" => $advantageDescription
                    ];
                }
            }

            $advantagesJson = json_encode(
                $advantages,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            // --------------------------------------------------
            // IMAGE UPLOADS
            // --------------------------------------------------

            $cardImage = projectUploadImage(
                "card_image",
                $existing["card_image"] ?? null
            );

            if (
                $cardImage &&
                $cardImage !== ($existing["card_image"] ?? null)
            ) {
                $uploadedImages[] = $cardImage;
            }

            if (!$cardImage) {
                throw new RuntimeException("Please upload a project card image.");
            }

            $detailImage = projectUploadImage(
                "detail_image",
                $existing["detail_image"] ?? null
            );

            if (
                $detailImage &&
                $detailImage !== ($existing["detail_image"] ?? null)
            ) {
                $uploadedImages[] = $detailImage;
            }

            // --------------------------------------------------
            // GENERATE UNIQUE SLUG
            // --------------------------------------------------

            $slug = projectUniqueSlug($pdo, $title, $id);

            // --------------------------------------------------
            // SAVE TO DATABASE
            // --------------------------------------------------

            if ($existing) {

                $stmt = $pdo->prepare("
                    UPDATE projects SET
                        title = ?,
                        slug = ?,
                        short_description = ?,
                        hero_description = ?,
                        card_image = ?,
                        detail_image = ?,
                        intro_label = ?,
                        intro_heading = ?,
                        intro_content = ?,
                        advantages_heading = ?,
                        advantages_description = ?,
                        advantages_json = ?,
                        cta_heading = ?,
                        cta_description = ?,
                        status = ?,
                        sort_order = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $title,
                    $slug,
                    $shortDescription,
                    $heroDescription,
                    $cardImage,
                    $detailImage,
                    $introLabel,
                    $introHeading,
                    $introContent,
                    $advantagesHeading,
                    $advantagesDescription,
                    $advantagesJson,
                    $ctaHeading,
                    $ctaDescription,
                    $status,
                    $sortOrder,
                    $id
                ]);

                $actionName = "project_updated";
                $description = "Updated project: " . $title;

                if ($existing["card_image"] !== $cardImage) {
                    projectDeleteImage($existing["card_image"]);
                }

                if ($existing["detail_image"] !== $detailImage) {
                    projectDeleteImage($existing["detail_image"]);
                }

                projectFlash("Project updated successfully.");

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO projects (
                        title,
                        slug,
                        short_description,
                        hero_description,
                        card_image,
                        detail_image,
                        intro_label,
                        intro_heading,
                        intro_content,
                        advantages_heading,
                        advantages_description,
                        advantages_json,
                        cta_heading,
                        cta_description,
                        status,
                        sort_order,
                        created_by
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");

                $stmt->execute([
                    $title,
                    $slug,
                    $shortDescription,
                    $heroDescription,
                    $cardImage,
                    $detailImage,
                    $introLabel,
                    $introHeading,
                    $introContent,
                    $advantagesHeading,
                    $advantagesDescription,
                    $advantagesJson,
                    $ctaHeading,
                    $ctaDescription,
                    $status,
                    $sortOrder,
                    (int)($_SESSION["admin_id"] ?? 0)
                ]);

                $actionName = "project_created";
                $description = "Created project: " . $title;

                projectFlash("New project added successfully.");
            }

            logActivity($pdo, $actionName, $description);

        // --------------------------------------------------
        // DELETE PROJECT
        // --------------------------------------------------

        } elseif ($action === "delete") {

            $id = filter_var(
                $_POST["id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id === null || $id < 1) {
                throw new RuntimeException("Invalid project ID.");
            }

            $stmt = $pdo->prepare("
                SELECT title, card_image, detail_image
                FROM projects
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                throw new RuntimeException("Project not found.");
            }

            $stmt = $pdo->prepare("
                DELETE FROM projects
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            projectDeleteImage($project["card_image"]);
            projectDeleteImage($project["detail_image"]);

            logActivity(
                $pdo,
                "project_deleted",
                "Deleted project: " . $project["title"]
            );

            projectFlash("Project deleted successfully.");

        // --------------------------------------------------
        // PUBLISH / UNPUBLISH
        // --------------------------------------------------

        } elseif ($action === "toggle_status") {

            $id = filter_var(
                $_POST["id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id === null || $id < 1) {
                throw new RuntimeException("Invalid project ID.");
            }

            $stmt = $pdo->prepare("
                SELECT title, status
                FROM projects
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                throw new RuntimeException("Project not found.");
            }

            $newStatus = $project["status"] === "published"
                ? "draft"
                : "published";

            $stmt = $pdo->prepare("
                UPDATE projects
                SET status = ?
                WHERE id = ?
            ");

            $stmt->execute([$newStatus, $id]);

            logActivity(
                $pdo,
                "project_status_changed",
                $project["title"] . " changed to " . $newStatus
            );

            projectFlash(
                $newStatus === "published"
                    ? "Project published successfully."
                    : "Project moved to draft."
            );

        } else {
            throw new RuntimeException("Invalid action.");
        }

    } catch (Throwable $e) {

        // Remove any newly uploaded files if saving failed.
        foreach ($uploadedImages as $uploadedImage) {
            projectDeleteImage($uploadedImage);
        }

        error_log("Project management error: " . $e->getMessage());

        projectFlash(
            $e instanceof RuntimeException
                ? $e->getMessage()
                : "Unable to complete the project operation. Please try again.",
            "error"
        );
    }

    header("Location: manage_projects.php");
    exit;
}

// --------------------------------------------------
// LOAD EDIT PROJECT
// --------------------------------------------------

$editProject = null;

$editId = filter_input(INPUT_GET, "edit", FILTER_VALIDATE_INT);

if ($editId && $editId > 0) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM projects
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$editId]);
    $editProject = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editProject) {
        $message = "Project not found.";
        $messageType = "error";
    }
}

// --------------------------------------------------
// LOAD PROJECTS
// --------------------------------------------------

try {

    $stmt = $pdo->query("
        SELECT *
        FROM projects
        ORDER BY sort_order ASC, id DESC
    ");

    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalProjects = count($projects);
    $publishedProjects = 0;
    $draftProjects = 0;

    foreach ($projects as $project) {

        if ($project["status"] === "published") {
            $publishedProjects++;
        } else {
            $draftProjects++;
        }
    }

} catch (PDOException $e) {

    error_log("Project list loading failed: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to load projects.");
}

// --------------------------------------------------
// FORM VALUES
// --------------------------------------------------

$form = $editProject ?: [];

$advantagesText = "";

if (!empty($form["advantages_json"])) {

    $decodedAdvantages = json_decode(
        $form["advantages_json"],
        true
    );

    if (is_array($decodedAdvantages)) {

        foreach ($decodedAdvantages as $advantage) {

            if (
                isset($advantage["title"], $advantage["description"])
            ) {
                $advantagesText .=
                    $advantage["title"] .
                    " | " .
                    $advantage["description"] .
                    "\n";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Projects | Olivera Admin</title>

<link rel="stylesheet" href="../assets/css/admin.css">

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
}

* {
    box-sizing: border-box;
}

body {
    background: var(--ivory);
    color: var(--text);
    font-family: "Segoe UI", Arial, sans-serif;
}

.dashboard-container {
    min-height: 100vh;
}

.sidebar {
    width: 255px;
    min-height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    padding: 25px 18px;
    background: var(--navy);
    z-index: 10;
}

.sidebar h2 {
    color: var(--gold-light);
    font-size: 21px;
    margin: 0 0 25px;
}

.sidebar a {
    display: block;
    padding: 12px 13px;
    margin-bottom: 5px;
    border-radius: 6px;
    color: #e5eaf1;
    text-decoration: none;
    font-size: 13px;
}

.sidebar a:hover,
.sidebar a.active {
    background: var(--navy-light);
    color: var(--gold-light);
}

.sidebar form {
    margin-top: 25px;
}

.sidebar button {
    width: 100%;
    padding: 11px;
    border: 1px solid rgba(237,202,118,.5);
    border-radius: 6px;
    background: transparent;
    color: var(--gold-light);
    cursor: pointer;
}

.main-content {
    margin-left: 255px;
    padding: 25px;
}

.project-page {
    max-width: 1450px;
    margin: auto;
}

.project-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 25px;
    margin-bottom: 22px;
    border-radius: 12px;
    background: linear-gradient(120deg, var(--navy), var(--navy-light));
    color: white;
}

.project-header h1 {
    margin: 0 0 7px;
    font-size: 25px;
    color: var(--gold-light);
}
.project-header p {
    margin: 0;
    color: #d7dfeb;
    font-size: 13px;
    line-height: 1.6;
}

.project-header-tag {
    padding: 9px 13px;
    border: 1px solid rgba(237,202,118,.5);
    border-radius: 6px;
    color: var(--gold-light);
    font-size: 11px;
    font-weight: 700;
}

.project-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.project-summary-card {
    padding: 18px;
    background: white;
    border: 1px solid var(--border);
    border-top: 3px solid var(--gold);
    border-radius: 9px;
}

.project-summary-card span {
    display: block;
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 9px;
}

.project-summary-card strong {
    color: var(--navy);
    font-size: 27px;
}

.project-panel {
    margin-bottom: 22px;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 11px;
    background: white;
}

.project-panel-header {
    padding: 19px 22px;
    border-bottom: 1px solid var(--border);
}

.project-panel-header h2 {
    margin: 0 0 6px;
    color: var(--navy);
    font-size: 17px;
}

.project-panel-header p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}

.project-panel-body {
    padding: 22px;
}

.project-alert {
    padding: 13px 16px;
    margin-bottom: 18px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
}

.project-alert.success {
    color: #187347;
    background: #eaf7ef;
    border: 1px solid #c8e9d5;
}

.project-alert.error {
    color: #a83232;
    background: #fff0f0;
    border: 1px solid #f1cccc;
}

.project-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.project-field {
    min-width: 0;
}

.project-field.full {
    grid-column: 1 / -1;
}

.project-field label {
    display: block;
    margin-bottom: 7px;
    color: var(--text);
    font-size: 12px;
    font-weight: 700;
}

.project-field input,
.project-field select,
.project-field textarea {
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid #ddd5c5;
    border-radius: 7px;
    background: #fff;
    color: var(--text);
    font: inherit;
    font-size: 13px;
}

.project-field textarea {
    min-height: 105px;
    resize: vertical;
}

.project-field input:focus,
.project-field select:focus,
.project-field textarea:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(214,166,59,.12);
}

.project-help {
    display: block;
    margin-top: 6px;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
}

.project-current-image {
    display: block;
    max-width: 180px;
    max-height: 110px;
    margin-top: 10px;
    border-radius: 7px;
    object-fit: cover;
}

.project-primary-btn,
.project-cancel-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 20px;
    margin-top: 18px;
    border: 0;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}

.project-primary-btn {
    background: linear-gradient(120deg, var(--gold-light), var(--gold));
    color: var(--navy);
}

.project-cancel-btn {
    background: #efede7;
    color: var(--text);
    margin-left: 7px;
}

.project-table-wrap {
    overflow-x: auto;
}

.project-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.project-table th {
    padding: 13px;
    background: #f8f6f0;
    border-bottom: 1px solid var(--border);
    color: var(--muted);
    font-size: 10px;
    text-align: left;
    text-transform: uppercase;
}

.project-table td {
    padding: 13px;
    border-bottom: 1px solid #eee9df;
    font-size: 12px;
    vertical-align: middle;
}

.project-table tr:hover td {
    background: #fdfbf6;
}

.project-thumb {
    width: 75px;
    height: 55px;
    border-radius: 6px;
    object-fit: cover;
    background: #eee;
}

.project-name {
    color: var(--navy);
    font-weight: 750;
}

.project-slug {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 10px;
    overflow-wrap: anywhere;
}

.project-status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
}

.project-status.published {
    background: #e8f6ed;
    color: #23764c;
}

.project-status.draft {
    background: #fff3d9;
    color: #926719;
}

.project-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.project-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 31px;
    padding: 0 10px;
    border: 0;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}

.project-edit {
    background: #edf1f7;
    color: var(--navy);
}

.project-toggle {
    background: #fff4d6;
    color: #89651c;
}

.project-delete {
    background: #fff0f0;
    color: #a83232;
}

.project-inline-form {
    display: inline;
    margin: 0;
}

.project-empty {
    padding: 35px !important;
    text-align: center;
    color: var(--muted);
}

@media (max-width: 950px) {

    .sidebar {
        width: 210px;
    }

    .main-content {
        margin-left: 210px;
    }
}

@media (max-width: 700px) {

    .sidebar {
        position: relative;
        width: 100%;
        min-height: auto;
    }

    .main-content {
        margin-left: 0;
        padding: 14px;
    }

    .project-header {
        align-items: flex-start;
        padding: 19px;
    }

    .project-header-tag {
        display: none;
    }

    .project-summary {
        grid-template-columns: 1fr;
    }

    .project-form-grid {
        grid-template-columns: 1fr;
    }

    .project-field.full {
        grid-column: auto;
    }

    .project-panel-body {
        padding: 15px;
    }

    .project-primary-btn,
    .project-cancel-btn {
        width: 100%;
        margin-left: 0;
    }
}

</style>

</head>

<body>

<div class="dashboard-container">

    <aside class="sidebar">

        <h2>Admin Panel</h2>

        <a href="dashboard.php">Dashboard</a>

        <?php if (hasPermission("manage_admins")): ?>
            <a href="manage_admins.php">Manage Admins</a>
        <?php endif; ?>

        <?php if (hasPermission("manage_users")): ?>
            <a href="manage_users.php">Manage Users</a>
            <a href="manage_customers.php">Manage Customers</a>
        <?php endif; ?>

        <?php if (hasPermission("manage_content")): ?>
            <a href="manage_content.php">Manage Content</a>
        <?php endif; ?>

        <a href="manage_projects.php" class="active">Manage Projects</a>

        <?php if (hasPermission("manage_admins")): ?>
            <a href="manage_gallery.php">Manage Gallery</a>
            <a href="activity_logs.php">Activity Logs</a>
        <?php endif; ?>

        <?php if (hasPermission("manage_settings")): ?>
            <a href="manage_settings.php">Website Settings</a>
        <?php endif; ?>

        <a href="profile.php">My Profile</a>
        <a href="change_password.php">Change Password</a>

        <form action="logout.php" method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo projectEscape($csrfToken); ?>"
            >
            <button type="submit">Logout</button>
        </form>

    </aside>

    <main class="main-content">

        <div class="project-page">

            <div class="project-header">

                <div>
                    <h1>Project Management</h1>
                    <p>Add and manage the projects displayed on your website.</p>
                </div>

                <div class="project-header-tag">WEBSITE CONTENT</div>

            </div>

            <?php if ($message !== ""): ?>

                <div class="project-alert <?php echo $messageType === "error" ? "error" : "success"; ?>">
                    <?php echo projectEscape($message); ?>
                </div>

            <?php endif; ?>

            <div class="project-summary">

                <div class="project-summary-card">
                    <span>Total Projects</span>
                    <strong><?php echo $totalProjects; ?></strong>
                </div>

                <div class="project-summary-card">
                    <span>Published Projects</span>
                    <strong><?php echo $publishedProjects; ?></strong>
                </div>

                <div class="project-summary-card">
                    <span>Draft Projects</span>
                    <strong><?php echo $draftProjects; ?></strong>
                </div>

            </div>

            <!-- ADD / EDIT PROJECT -->

            <section class="project-panel" id="project-form">

                <div class="project-panel-header">

                    <h2>
                        <?php echo $editProject ? "Edit Project" : "Add New Project"; ?>
                    </h2>

                    <p>
                        Enter project information and upload its card and detail images.
                    </p>

                </div>

                <div class="project-panel-body">

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <input type="hidden" name="csrf_token"
                            value="<?php echo projectEscape($csrfToken); ?>">

                        <input type="hidden" name="action" value="save">

                        <input type="hidden" name="id"
                            value="<?php echo (int)($form["id"] ?? 0); ?>">

                        <div class="project-form-grid">

                            <div class="project-field">
                                <label for="title">Project / City Name *</label>
                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    maxlength="150"
                                    required
                                    value="<?php echo projectEscape($form["title"] ?? ""); ?>"
                                    placeholder="Example: Jaipur"
                                >
                            </div>

                            <div class="project-field">
                                <label for="sort_order">Display Order</label>
                                <input
                                    type="number"
                                    id="sort_order"
                                    name="sort_order"
                                    min="0"
                                    max="1000000"
                                    value="<?php echo projectEscape($form["sort_order"] ?? "0"); ?>"
                                >
                            </div>

                            <div class="project-field full">
                                <label for="short_description">Card Description *</label>
                                <textarea
                                    id="short_description"
                                    name="short_description"
                                    maxlength="500"
                                    required
                                    placeholder="Short description shown on the Our Projects card."
                                ><?php echo projectEscape($form["short_description"] ?? ""); ?></textarea>
                            </div>

                            <div class="project-field full">
                                <label for="hero_description">Hero Description</label>
                                <textarea
                                    id="hero_description"
                                    name="hero_description"
                                    maxlength="10000"
                                    placeholder="Description displayed in the project detail page hero section."
                                ><?php echo projectEscape($form["hero_description"] ?? ""); ?></textarea>
                            </div>

                            <div class="project-field">
                                <label for="card_image">
                                    Project Card Image <?php echo $editProject ? "" : "*"; ?>
                                </label>

                                <input
                                    type="file"
                                    id="card_image"
                                    name="card_image"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    <?php echo $editProject ? "" : "required"; ?>
                                >

                                <span class="project-help">
                                    JPG, PNG or WEBP. Maximum 5 MB.
                                </span>

                                <?php if (!empty($form["card_image"])): ?>
                                    <img
                                        class="project-current-image"
                                        src="../<?php echo projectEscape($form["card_image"]); ?>"
                                        alt="Current project card image"
                                    >
                                <?php endif; ?>
                            </div>

                            <div class="project-field">
                                <label for="detail_image">Detail Page Image</label>

                                <input
                                    type="file"
                                    id="detail_image"
                                    name="detail_image"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                >

                                <span class="project-help">
                                    Optional. If omitted, the card image will be used.
                                </span>

                                <?php if (!empty($form["detail_image"])): ?>
                                    <img
                                        class="project-current-image"
                                        src="../<?php echo projectEscape($form["detail_image"]); ?>"
                                        alt="Current project detail image"
                                    >
                                <?php endif; ?>
                            </div>

                            <div class="project-field">
                                <label for="intro_label">Introduction Label</label>
                                <input
                                    type="text"
                                    id="intro_label"
                                    name="intro_label"
                                    maxlength="150"
                                    value="<?php echo projectEscape($form["intro_label"] ?? ""); ?>"
                                    placeholder="Example: THE CITY OF OPPORTUNITIES"
                                >
                            </div>

                            <div class="project-field">
                                <label for="intro_heading">Introduction Heading</label>
                                <input
                                    type="text"
                                    id="intro_heading"
                                    name="intro_heading"
                                    maxlength="255"
                                    value="<?php echo projectEscape($form["intro_heading"] ?? ""); ?>"
                                    placeholder="Example: Discover the Growing City"
                                >
                            </div>

                            <div class="project-field full">
                                <label for="intro_content">Introduction Content</label>
                                <textarea
                                    id="intro_content"
                                    name="intro_content"
                                    maxlength="20000"
                                    placeholder="Write the project or city introduction. Separate paragraphs with blank lines."
                                ><?php echo projectEscape($form["intro_content"] ?? ""); ?></textarea>
                            </div>

                            <div class="project-field">
                                <label for="advantages_heading">Advantages Heading</label>
                                <input
                                    type="text"
                                    id="advantages_heading"
                                    name="advantages_heading"
                                    maxlength="255"
                                    value="<?php echo projectEscape($form["advantages_heading"] ?? ""); ?>"
                                >
                            </div>

                            <div class="project-field">
                                <label for="advantages_description">Advantages Description</label>
                                <textarea
                                    id="advantages_description"
                                    name="advantages_description"
                                    maxlength="5000"
                                ><?php echo projectEscape($form["advantages_description"] ?? ""); ?></textarea>
                            </div>

                            <div class="project-field full">
                                <label for="advantages">Location Advantages</label>
                                <textarea
                                    id="advantages"
                                    name="advantages"
                                    placeholder="Metro Connectivity | Metro connectivity across the region.&#10;Healthcare | Access to hospitals and clinics.&#10;Education | Schools and educational institutions."
                                ><?php echo projectEscape($advantagesText); ?></textarea>

                                <span class="project-help">
                                    Enter one advantage per line using: Title | Description
                                </span>
                            </div>

                            <div class="project-field">
                                <label for="cta_heading">Site Visit Section Heading</label>
                                <input
                                    type="text"
                                    id="cta_heading"
                                    name="cta_heading"
                                    maxlength="255"
                                    value="<?php echo projectEscape($form["cta_heading"] ?? ""); ?>"
                                >
                            </div>

                            <div class="project-field">
                                <label for="cta_description">Site Visit Section Description</label>
                                <textarea
                                    id="cta_description"
                                    name="cta_description"
                                    maxlength="5000"
                                ><?php echo projectEscape($form["cta_description"] ?? ""); ?></textarea>
                            </div>

                            <div class="project-field">
                                <label for="status">Publication Status</label>
                                <select id="status" name="status">
                                    <option
                                        value="draft"
                                        <?php echo ($form["status"] ?? "draft") === "draft" ? "selected" : ""; ?>
                                    >
                                        Draft
                                    </option>

                                    <option
                                        value="published"
                                        <?php echo ($form["status"] ?? "") === "published" ? "selected" : ""; ?>
                                    >
                                        Published
                                    </option>
                                </select>
                            </div>

                        </div>

                        <button type="submit" class="project-primary-btn">
                            <?php echo $editProject ? "Update Project" : "Add Project"; ?>
                        </button>

                        <?php if ($editProject): ?>
                            <a href="manage_projects.php" class="project-cancel-btn">
                                Cancel Edit
                            </a>
                        <?php endif; ?>

                    </form>

                </div>

            </section>

            <!-- PROJECT LIST -->

            <section class="project-panel">

                <div class="project-panel-header">
                    <h2>All Managed Projects</h2>
                    <p>Manage the projects stored in your projects database table.</p>
                </div>

                <div class="project-table-wrap">

                    <table class="project-table">

                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if ($projects): ?>

                            <?php foreach ($projects as $project): ?>

                                <tr>

                                    <td>
                                        <img
                                            class="project-thumb"
                                            src="../<?php echo projectEscape($project["card_image"]); ?>"
                                            alt="<?php echo projectEscape($project["title"]); ?>"
                                            loading="lazy"
                                        >
                                    </td>

                                    <td>
                                        <span class="project-name">
                                            <?php echo projectEscape($project["title"]); ?>
                                        </span>

                                        <span class="project-slug">
                                            <?php echo projectEscape($project["slug"]); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="project-status <?php echo projectEscape($project["status"]); ?>">
                                            <?php echo ucfirst(projectEscape($project["status"])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo (int)$project["sort_order"]; ?>
                                    </td>

                                    <td>

                                        <div class="project-actions">

                                            <a
                                                href="manage_projects.php?edit=<?php echo (int)$project["id"]; ?>#project-form"
                                                class="project-action-btn project-edit"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                class="project-inline-form"
                                            >
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo projectEscape($csrfToken); ?>">

                                                <input type="hidden" name="action"
                                                    value="toggle_status">

                                                <input type="hidden" name="id"
                                                    value="<?php echo (int)$project["id"]; ?>">

                                                <button
                                                    type="submit"
                                                    class="project-action-btn project-toggle"
                                                >
                                                    <?php echo $project["status"] === "published" ? "Unpublish" : "Publish"; ?>
                                                </button>
                                            </form>

                                            <form
                                                method="POST"
                                                class="project-inline-form"
                                                onsubmit="return confirm('Permanently delete this project?');"
                                            >
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo projectEscape($csrfToken); ?>">

                                                <input type="hidden" name="action"
                                                    value="delete">

                                                <input type="hidden" name="id"
                                                    value="<?php echo (int)$project["id"]; ?>">

                                                <button
                                                    type="submit"
                                                    class="project-action-btn project-delete"
                                                >
                                                    Delete
                                                </button>
                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="project-empty">
                                    No projects have been added yet.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </div>

    </main>

</div>

</body>
</html>
