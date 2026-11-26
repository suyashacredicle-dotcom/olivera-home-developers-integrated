
<?php

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

requirePermission("manage_admins");

function galleryEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

$locations = [
    "Delhi",
    "Noida",
    "Gurugram",
    "Jewar",
    "Vrindavan",
    "Uttarakhand",
    "Goa",
    "Dubai"
];

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

$message = "";
$messageType = "";

if (isset($_SESSION["gallery_message"])) {
    $message = $_SESSION["gallery_message"];
    $messageType = $_SESSION["gallery_message_type"] ?? "success";

    unset($_SESSION["gallery_message"]);
    unset($_SESSION["gallery_message_type"]);
}

// --------------------------------------------------
// SEARCH AND LOCATION FILTER
// --------------------------------------------------

$search = $_GET["search"] ?? "";
$locationFilter = $_GET["location"] ?? "";

if (!is_string($search)) {
    $search = "";
}

if (!is_string($locationFilter)) {
    $locationFilter = "";
}

$search = trim(substr($search, 0, 200));

if (
    $locationFilter !== "" &&
    $locationFilter !== "Unassigned" &&
    !in_array($locationFilter, $locations, true)
) {
    $locationFilter = "";
}

// --------------------------------------------------
// LOAD GALLERY RECORDS
// --------------------------------------------------

try {

    $where = [];
    $params = [];

    if ($search !== "") {

        $where[] = "(
            title LIKE ?
            OR original_filename LIKE ?
            OR image_filename LIKE ?
        )";

        $term = "%" . $search . "%";

        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if ($locationFilter !== "") {

        $where[] = "location = ?";
        $params[] = $locationFilter;
    }

    $sql = "
        SELECT *
        FROM gallery_photos
    ";

    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= "
        ORDER BY location ASC, sort_order ASC, id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // SUMMARY COUNTS
    // --------------------------------------------------

    $totalPhotos = count($photos);
    $publicPhotos = 0;
    $employeePhotos = 0;
    $featuredPhotos = 0;

    foreach ($photos as $photo) {

        if ($photo["visibility"] === "public") {
            $publicPhotos++;
        }

        if ($photo["visibility"] === "employees") {
            $employeePhotos++;
        }

        if ((int)($photo["is_featured"] ?? 0) === 1) {
            $featuredPhotos++;
        }
    }

    // --------------------------------------------------
    // LOCATION COUNTS
    // --------------------------------------------------

    $locationCounts = array_fill_keys($locations, 0);
    $locationCounts["Unassigned"] = 0;

    $countStmt = $pdo->query("
        SELECT location, COUNT(*) AS total
        FROM gallery_photos
        GROUP BY location
    ");

    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $name = $row["location"];

        if (isset($locationCounts[$name])) {
            $locationCounts[$name] = (int)$row["total"];
        }
    }

} catch (PDOException $e) {

    error_log("Gallery loading failed: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to load gallery records.");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Gallery | Olivera Admin</title>

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
    --surface: #ffffff;
}

* {
    box-sizing: border-box;
}

body {
    background: var(--ivory);
    color: var(--text);
    font-family: "Segoe UI", Arial, sans-serif;
}

.gallery-page {
    max-width: 1500px;
    width: 100%;
    margin: auto;
    padding: 15px 5px 40px;
}

.gallery-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 25px;
    margin-bottom: 22px;
    background: linear-gradient(120deg, var(--navy), var(--navy-light));
    border-radius: 13px;
    color: white;
}

.gallery-header h1 {
    margin: 0 0 8px;
    font-size: 26px;
    color: white;
}

.gallery-header p {
    margin: 0;
    color: #d7dfeb;
    font-size: 13px;
    line-height: 1.6;
}

.gallery-header-tag {
    padding: 9px 14px;
    border: 1px solid rgba(237,202,118,.5);
    border-radius: 7px;
    color: var(--gold-light);
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

/* SUMMARY */

.gallery-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 23px;
}

.gallery-summary-card {
    padding: 19px;
    background: white;
    border: 1px solid var(--border);
    border-top: 3px solid var(--gold);
    border-radius: 11px;
}

.gallery-summary-label {
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 9px;
}

.gallery-summary-number {
    color: var(--navy);
    font-size: 27px;
    font-weight: 750;
}

/* PANELS */

.gallery-panel {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 23px;
    overflow: hidden;
}

.gallery-panel-header {
    padding: 20px 23px;
    border-bottom: 1px solid var(--border);
}

.gallery-panel-header h2 {
    margin: 0 0 6px;
    color: var(--navy);
    font-size: 17px;
}

.gallery-panel-header p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}

.gallery-panel-body {
    padding: 22px;
}

/* ALERTS */

.gallery-alert {
    padding: 13px 16px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}

.gallery-alert.success {
    background: #eaf7ef;
    color: #187347;
    border: 1px solid #c8e9d5;
}

.gallery-alert.error {
    background: #fff0f0;
    color: #a83232;
    border: 1px solid #f1cccc;
}

/* LOCATION COLLECTIONS */

.gallery-location-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 13px;
}

.gallery-location-card {
    display: block;
    padding: 17px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    text-decoration: none;
    transition: .2s;
}

.gallery-location-card:hover,
.gallery-location-card.active {
    border-color: var(--gold);
    background: #fcf8ed;
    transform: translateY(-2px);
}

.gallery-location-name {
    color: var(--navy);
    font-size: 14px;
    font-weight: 750;
    margin-bottom: 8px;
}

.gallery-location-count {
    color: var(--muted);
    font-size: 12px;
}

.gallery-location-count strong {
    color: #a67a20;
    font-size: 19px;
    margin-right: 4px;
}

/* FORMS */

.gallery-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.gallery-form-group {
    min-width: 0;
}

.gallery-form-group.full-width {
    grid-column: 1 / -1;
}

.gallery-form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text);
    font-size: 12px;
    font-weight: 700;
}

.gallery-form-group input,
.gallery-form-group select,
.gallery-form-group textarea,
.gallery-search-form input,
.gallery-search-form select {
    width: 100%;
    min-height: 43px;
    padding: 10px 12px;
    border: 1px solid #ddd5c5;
    border-radius: 7px;
    background: #fff;
    color: var(--text);
    font-size: 13px;
}

.gallery-form-group textarea {
    min-height: 95px;
    resize: vertical;
}

.gallery-form-group input:focus,
.gallery-form-group select:focus,
.gallery-form-group textarea:focus,
.gallery-search-form input:focus,
.gallery-search-form select:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(214,166,59,.12);
}

.gallery-form-help {
    display: block;
    margin-top: 6px;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
}

.gallery-primary-btn {
    min-height: 43px;
    padding: 0 22px;
    margin-top: 17px;
    border: 0;
    border-radius: 7px;
    background: linear-gradient(120deg, var(--gold-light), var(--gold));
    color: var(--navy);
    font-size: 13px;
    font-weight: 750;
    cursor: pointer;
}

.gallery-primary-btn:hover {
    filter: brightness(.96);
}

/* SEARCH */

.gallery-search-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.gallery-search-form input {
    flex: 1;
    min-width: 190px;
}

.gallery-search-form select {
    max-width: 220px;
}

.gallery-search-btn,
.gallery-clear-btn {
    min-height: 43px;
    padding: 0 17px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 7px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.gallery-search-btn {
    background: var(--navy);
    color: white;
}

.gallery-clear-btn {
    background: #f0ede5;
    color: var(--text);
}

/* TABLE */

.gallery-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.gallery-table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

.gallery-table th {
    padding: 14px;
    text-align: left;
    white-space: nowrap;
    background: #f8f6f0;
    border-bottom: 1px solid var(--border);
    color: var(--muted);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .4px;
}

.gallery-table td {
    padding: 14px;
    border-bottom: 1px solid #eee9df;
    color: var(--text);
    font-size: 12px;
    vertical-align: middle;
}

.gallery-table tr:hover td {
    background: #fdfbf6;
}

.gallery-photo-name {
    min-width: 170px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.gallery-location-badge,
.gallery-featured-badge {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.gallery-location-badge {
    background: #f5edda;
    color: #86621a;
}

.gallery-featured-badge {
    background: #fff4d6;
    color: #946b12;
    margin-top: 5px;
}

.gallery-visibility {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.gallery-visibility.public {
    background: #e8f6ed;
    color: #23764c;
}

.gallery-visibility.employees {
    background: #edf3ff;
    color: #315fbd;
}

.gallery-date {
    color: var(--muted) !important;
    white-space: nowrap;
    font-size: 11px !important;
}

.gallery-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
}

.gallery-view-btn,
.gallery-edit-btn,
.gallery-delete-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 32px;
    padding: 0 11px;
    border: 0;
    border-radius: 6px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.gallery-view-btn,
.gallery-edit-btn {
    background: #edf1f7;
    color: var(--navy);
}

.gallery-delete-btn {
    background: #fff0f0;
    color: #a83232;
}

.gallery-delete-form {
    display: inline;
    margin: 0;
}

.gallery-notice {
    padding: 13px 15px;
    margin-bottom: 18px;
    border: 1px solid #e8d9b7;
    border-radius: 7px;
    background: #fcf8ed;
    color: #735a25;
    font-size: 12px;
    line-height: 1.7;
}

.gallery-empty {
    padding: 40px !important;
    text-align: center;
    color: var(--muted) !important;
}

/* RESPONSIVE */

@media (max-width: 1000px) {

    .gallery-location-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .gallery-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {

    .gallery-header {
        align-items: flex-start;
        padding: 18px;
    }

    .gallery-header h1 {
        font-size: 21px;
    }

    .gallery-header-tag {
        display: none;
    }

    .gallery-panel-body {
        padding: 16px;
    }

    .gallery-form-grid {
        grid-template-columns: 1fr;
    }

    .gallery-form-group.full-width {
        grid-column: auto;
    }

    .gallery-search-form input,
    .gallery-search-form select {
        max-width: none;
        flex-basis: 100%;
    }

    .gallery-primary-btn {
        width: 100%;
    }
}

@media (max-width: 380px) {

    .gallery-location-grid,
    .gallery-summary {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="dashboard-container">

    <aside class="sidebar">

        <h2>Admin Panel</h2>

        <a href="dashboard.php">Dashboard</a>
        <a href="manage_admins.php">Manage Admins</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_customers.php">Manage Customers</a>
        <a href="manage_content.php">Manage Content</a>
        <a href="manage_gallery.php">Manage Gallery</a>
        <a href="manage_settings.php">Website Settings</a>
        <a href="activity_logs.php">Activity Logs</a>
        <a href="profile.php">My Profile</a>
        <a href="change_password.php">Change Password</a>

        <form action="logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo galleryEscape($csrfToken); ?>"
            >

            <button type="submit">Logout</button>

        </form>

    </aside>

    <main class="main-content">

        <div class="gallery-page">

            <!-- HEADER -->

            <div class="gallery-header">

                <div>
                    <h1>Gallery Management</h1>
                    <p>Organise, upload and manage project photographs by location.</p>
                </div>

                <div class="gallery-header-tag">SUPER ADMIN</div>

            </div>

            <!-- FLASH MESSAGE -->

            <?php if ($message !== ""): ?>

                <div class="gallery-alert <?php echo $messageType === "error" ? "error" : "success"; ?>">
                    <?php echo galleryEscape($message); ?>
                </div>

            <?php endif; ?>

            <!-- SUMMARY -->

            <div class="gallery-summary">

                <div class="gallery-summary-card">
                    <div class="gallery-summary-label">Matching Photos</div>
                    <div class="gallery-summary-number">
                        <?php echo $totalPhotos; ?>
                    </div>
                </div>

                <div class="gallery-summary-card">
                    <div class="gallery-summary-label">Public Website Photos</div>
                    <div class="gallery-summary-number">
                        <?php echo $publicPhotos; ?>
                    </div>
                </div>

                <div class="gallery-summary-card">
                    <div class="gallery-summary-label">Employees Only Photos</div>
                    <div class="gallery-summary-number">
                        <?php echo $employeePhotos; ?>
                    </div>
                </div>

                <div class="gallery-summary-card">
                    <div class="gallery-summary-label">Featured Photos</div>
                    <div class="gallery-summary-number">
                        <?php echo $featuredPhotos; ?>
                    </div>
                </div>

            </div>

            <!-- LOCATION COLLECTIONS -->

            <section class="gallery-panel">

                <div class="gallery-panel-header">
                    <h2>Location Collections</h2>
                    <p>Select a location to see its photographs.</p>
                </div>

                <div class="gallery-panel-body">

                    <div class="gallery-location-grid">

                        <!-- ALL LOCATIONS -->

                        <a
                            class="gallery-location-card <?php echo $locationFilter === "" ? "active" : ""; ?>"
                            href="manage_gallery.php"
                        >

                            <div class="gallery-location-name">
                                All Locations
                            </div>

                            <div class="gallery-location-count">
                                <strong><?php echo array_sum($locationCounts); ?></strong>
                                Photos
                            </div>

                        </a>

                        <!-- EIGHT LOCATIONS -->

                        <?php foreach ($locations as $location): ?>

                            <a
                                class="gallery-location-card <?php echo $locationFilter === $location ? "active" : ""; ?>"
                                href="manage_gallery.php?location=<?php echo urlencode($location); ?>"
                            >

                                <div class="gallery-location-name">
                                    <?php echo galleryEscape($location); ?>
                                </div>

                                <div class="gallery-location-count">
                                    <strong><?php echo $locationCounts[$location]; ?></strong>
                                    Photos
                                </div>

                            </a>

                        <?php endforeach; ?>

                        <!-- UNASSIGNED -->

                        <a
                            class="gallery-location-card <?php echo $locationFilter === "Unassigned" ? "active" : ""; ?>"
                            href="manage_gallery.php?location=Unassigned"
                        >

                            <div class="gallery-location-name">
                                Unassigned
                            </div>

                            <div class="gallery-location-count">
                                <strong><?php echo $locationCounts["Unassigned"]; ?></strong>
                                Photos
                            </div>

                        </a>

                    </div>

                </div>

            </section>

            <!-- UPLOAD PHOTOS -->

            <section class="gallery-panel">

                <div class="gallery-panel-header">
                    <h2>Upload New Photos</h2>
                    <p>Select one or multiple photographs and assign them to a location.</p>
                </div>

                <div class="gallery-panel-body">

                    <form
                        action="actions/upload_gallery.php"
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo galleryEscape($csrfToken); ?>"
                        >

                        <div class="gallery-form-grid">

                            <div class="gallery-form-group full-width">

                                <label for="photos">Select Photos</label>

                                <input
                                    type="file"
                                    id="photos"
                                    name="photos[]"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    multiple
                                    required
                                >

                                <span class="gallery-form-help">
                                    You can select multiple photos together.
                                    JPG, JPEG, PNG or WEBP. Maximum 5 MB per photo.
                                </span>

                            </div>

                            <div class="gallery-form-group">

                                <label for="location">Select Location</label>

                                <select id="location" name="location" required>

                                    <option value="">Choose a location</option>

                                    <?php foreach ($locations as $location): ?>

                                        <option
                                            value="<?php echo galleryEscape($location); ?>"
                                            <?php echo $locationFilter === $location ? "selected" : ""; ?>
                                        >
                                            <?php echo galleryEscape($location); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <span class="gallery-form-help">
                                    Every selected photo will be organised under this location.
                                </span>

                            </div>

                            <div class="gallery-form-group">

                                <label for="title">Photo Name (Optional)</label>

                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    maxlength="150"
                                    placeholder="Example: Delhi"
                                >

                                <span class="gallery-form-help">
                                    If you provide a name, uploaded photos will receive sequential numbers.
                                </span>

                            </div>

                            <div class="gallery-form-group">

                                <label for="visibility">Visibility</label>

                                <select id="visibility" name="visibility" required>

                                    <option value="employees" selected>
                                        Employees Only
                                    </option>

                                    <option value="public">
                                        Public Website
                                    </option>

                                </select>

                            </div>

                            <div class="gallery-form-group">

                                <label for="description">
                                    Photo Description (Optional)
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="5000"
                                    placeholder="Enter a short description..."
                                ></textarea>

                            </div>

                        </div>

                        <button type="submit" class="gallery-primary-btn">
                            Upload Photos
                        </button>

                    </form>

                </div>

            </section>

            <!-- UPLOADED PHOTOS -->

            <section class="gallery-panel">

                <div class="gallery-panel-header">

                    <h2>
                        <?php
                        echo $locationFilter !== ""
                            ? galleryEscape($locationFilter) . " Photos"
                            : "All Uploaded Photos";
                        ?>
                    </h2>

                    <p>
                        Search, preview, edit and delete your photographs.
                    </p>

                </div>

                <div class="gallery-panel-body">

                    <div class="gallery-notice">

                        Public Website photos are available for public display.
                        Employees Only photos remain restricted. Featured status
                        and custom ordering are managed separately.

                    </div>

                    <!-- SEARCH AND FILTER -->

                    <form method="GET" class="gallery-search-form">

                        <input
                            type="text"
                            name="search"
                            placeholder="Search photo name or filename..."
                            value="<?php echo galleryEscape($search); ?>"
                        >

                        <select name="location">

                            <option value="">All Locations</option>

                            <?php foreach ($locations as $location): ?>

                                <option
                                    value="<?php echo galleryEscape($location); ?>"
                                    <?php echo $locationFilter === $location ? "selected" : ""; ?>
                                >
                                    <?php echo galleryEscape($location); ?>
                                </option>

                            <?php endforeach; ?>

                            <option
                                value="Unassigned"
                                <?php echo $locationFilter === "Unassigned" ? "selected" : ""; ?>
                            >
                                Unassigned
                            </option>

                        </select>

                        <button type="submit" class="gallery-search-btn">
                            Search
                        </button>

                        <a href="manage_gallery.php" class="gallery-clear-btn">
                            Clear
                        </a>

                    </form>

                </div>

                <!-- PHOTO TABLE -->

                <div class="gallery-table-wrapper">

                    <table class="gallery-table">

                        <thead>

                            <tr>
                                <th>S. No.</th>
                                <th>Photo Name</th>
                                <th>Location</th>
                                <th>Visibility</th>
                                <th>Uploaded At</th>
                                <th>Preview</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($photos): ?>

                            <?php $serialNumber = 1; ?>

                            <?php foreach ($photos as $photo): ?>

                                <?php

                                $displayPhotoName = trim(
                                    (string)($photo["title"] ?? "")
                                );

                                if ($displayPhotoName === "") {

                                    $displayPhotoName = trim(
                                        (string)($photo["original_filename"] ?? "")
                                    );
                                }

                                if ($displayPhotoName === "") {
                                    $displayPhotoName = "Untitled Photo";
                                }

                                $photoLocation = $photo["location"] ?? "Unassigned";

                                $photoVisibility = $photo["visibility"] ?? "employees";

                                ?>

                                <tr>

                                    <td>
                                        <?php echo $serialNumber++; ?>
                                    </td>

                                    <td class="gallery-photo-name">

                                        <?php echo galleryEscape($displayPhotoName); ?>

                                        <?php if ((int)($photo["is_featured"] ?? 0) === 1): ?>

                                            <br>

                                            <span class="gallery-featured-badge">
                                                ★ Featured
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <span class="gallery-location-badge">
                                            <?php echo galleryEscape($photoLocation); ?>
                                        </span>

                                    </td>

                                    <td>

                                        <span class="gallery-visibility <?php echo $photoVisibility === "public" ? "public" : "employees"; ?>">

                                            <?php
                                            echo $photoVisibility === "public"
                                                ? "Public Website"
                                                : "Employees Only";
                                            ?>

                                        </span>

                                    </td>

                                    <td class="gallery-date">

                                        <?php
                                        echo galleryEscape($photo["created_at"] ?? "");
                                        ?>

                                    </td>

                                    <td>

                                        <a
                                            href="gallery_preview.php?id=<?php echo (int)$photo["id"]; ?>"
                                            class="gallery-view-btn"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            View
                                        </a>

                                    </td>

                                    <td>

                                        <div class="gallery-actions">

                                            <a
                                                href="actions/edit_gallery.php?id=<?php echo (int)$photo["id"]; ?>"
                                                class="gallery-edit-btn"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                action="actions/delete_gallery.php"
                                                method="POST"
                                                class="gallery-delete-form"
                                                onsubmit="return confirm('Permanently delete this photo?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo galleryEscape($csrfToken); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo (int)$photo["id"]; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="gallery-delete-btn"
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

                                <td colspan="7" class="gallery-empty">
                                    No photos found in this selection.
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
