
<?php

// --------------------------------------------------
// SUPER ADMIN GALLERY MANAGEMENT
// --------------------------------------------------

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("manage_admins");

// --------------------------------------------------
// ESCAPE OUTPUT
// --------------------------------------------------

function galleryEscape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// --------------------------------------------------
// CREATE CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// --------------------------------------------------
// FLASH MESSAGES
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
// SEARCH GALLERY PHOTOS
// --------------------------------------------------

$search = $_GET["search"] ?? "";

if (!is_string($search)) {
    $search = "";
}

$search = trim(substr($search, 0, 200));

// --------------------------------------------------
// FETCH GALLERY PHOTOS
// --------------------------------------------------

try {

    if ($search !== "") {

        $stmt = $pdo->prepare("
            SELECT *
            FROM gallery_photos
            WHERE title LIKE ?
               OR original_filename LIKE ?
               OR image_filename LIKE ?
            ORDER BY id DESC
        ");

        $searchTerm = "%" . $search . "%";

        $stmt->execute([
            $searchTerm,
            $searchTerm,
            $searchTerm
        ]);

    } else {

        $stmt = $pdo->query("
            SELECT *
            FROM gallery_photos
            ORDER BY id DESC
        ");
    }

    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPhotos = count($photos);
    $publicPhotos = 0;
    $employeePhotos = 0;

    foreach ($photos as $photo) {

        if ($photo["visibility"] === "public") {
            $publicPhotos++;
        }

        if ($photo["visibility"] === "employees") {
            $employeePhotos++;
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

    <title>Manage Gallery | Admin Panel</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            background: #f5f7fb;
            color: #263449;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .gallery-page {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 12px 5px 35px;
        }

        /* PAGE HEADER */

        .gallery-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 25px;
            padding: 23px 25px;
            background: linear-gradient(120deg, #ffffff, #f0f5ff);
            border: 1px solid #e3e9f2;
            border-radius: 13px;
        }

        .gallery-header h1 {
            color: #17243a;
            font-size: 25px;
            font-weight: 700;
            margin: 0 0 7px;
        }

        .gallery-header p {
            color: #718096;
            font-size: 13px;
            margin: 0;
            line-height: 1.6;
        }

        .gallery-header-tag {
            padding: 9px 13px;
            background: #edf3ff;
            border: 1px solid #dce7fb;
            border-radius: 8px;
            color: #315fbd;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        /* SUMMARY CARDS */

        .gallery-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 24px;
        }

        .gallery-summary-card {
            position: relative;
            overflow: hidden;
            padding: 21px 22px;
            background: #ffffff;
            border: 1px solid #e3e9f2;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(26, 45, 75, 0.035);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .gallery-summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 17px rgba(26, 45, 75, 0.07);
        }

        .gallery-summary-label {
            color: #718096;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 11px;
        }

        .gallery-summary-number {
            color: #17243a;
            font-size: 29px;
            font-weight: 700;
        }

        .gallery-summary-card.total {
            border-top: 3px solid #315fbd;
        }

        .gallery-summary-card.public {
            border-top: 3px solid #26966a;
        }

        .gallery-summary-card.employees {
            border-top: 3px solid #6684c5;
        }

        /* PANELS */

        .gallery-panel {
            background: #ffffff;
            border: 1px solid #e3e9f2;
            border-radius: 13px;
            margin-bottom: 24px;
            box-shadow: 0 3px 12px rgba(26, 45, 75, 0.035);
            overflow: hidden;
        }

        .gallery-panel-header {
            padding: 21px 24px;
            border-bottom: 1px solid #e8edf4;
            background: #ffffff;
        }

        .gallery-panel-header h2 {
            color: #17243a;
            font-size: 17px;
            font-weight: 700;
            margin: 0 0 6px;
        }

        .gallery-panel-header p {
            color: #718096;
            font-size: 12px;
            margin: 0;
            line-height: 1.6;
        }

        .gallery-panel-body {
            padding: 24px;
        }

        /* ALERTS */

        .gallery-alert {
            padding: 14px 17px;
            margin-bottom: 21px;
            border-radius: 9px;
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

        /* UPLOAD FORM */

        .gallery-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 21px;
            row-gap: 2px;
        }

        .gallery-form-group {
            margin-bottom: 19px;
            min-width: 0;
        }

        .gallery-form-group.full-width {
            grid-column: 1 / -1;
        }

        .gallery-form-group label {
            display: block;
            color: #34445c;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .gallery-form-group input,
        .gallery-form-group select,
        .gallery-form-group textarea {
            width: 100%;
            min-height: 43px;
            padding: 11px 13px;
            border: 1px solid #dce3ed;
            border-radius: 8px;
            font-size: 13px;
            color: #34445c;
            background: #fbfcfe;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .gallery-form-group textarea {
            min-height: 105px;
            resize: vertical;
            line-height: 1.6;
        }

        .gallery-form-group input:focus,
        .gallery-form-group select:focus,
        .gallery-form-group textarea:focus {
            border-color: #315fbd;
            box-shadow: 0 0 0 3px rgba(49, 95, 189, 0.09);
            background: #ffffff;
        }

        .gallery-form-help {
            display: block;
            color: #8995a7;
            font-size: 11px;
            margin-top: 7px;
            line-height: 1.6;
        }

        .gallery-primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 21px;
            background: #315fbd;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .gallery-primary-btn:hover {
            background: #244b9b;
            transform: translateY(-1px);
        }

        /* SEARCH */

        .gallery-search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .gallery-search-form input {
            flex: 1;
            min-width: 200px;
            min-height: 42px;
            padding: 10px 13px;
            border: 1px solid #dce3ed;
            border-radius: 8px;
            background: #fbfcfe;
            color: #34445c;
            font-size: 13px;
            outline: none;
        }

        .gallery-search-form input:focus {
            border-color: #315fbd;
            box-shadow: 0 0 0 3px rgba(49, 95, 189, 0.09);
        }

        .gallery-search-btn,
        .gallery-clear-btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 17px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .gallery-search-btn {
            background: #315fbd;
            color: #ffffff;
            border: none;
        }

        .gallery-search-btn:hover {
            background: #244b9b;
        }

        .gallery-clear-btn {
            background: #eef1f6;
            color: #526176;
        }

        .gallery-clear-btn:hover {
            background: #e2e7ef;
        }

        /* TABLE */

        .gallery-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .gallery-table {
            width: 100%;
            min-width: 780px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .gallery-table th {
            background: #f6f8fc;
            color: #536176;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            text-align: left;
            font-weight: 700;
            padding: 15px 17px;
            border-bottom: 1px solid #e5eaf1;
            white-space: nowrap;
        }

        .gallery-table td {
            padding: 15px 17px;
            border-bottom: 1px solid #edf0f5;
            color: #526176;
            font-size: 12px;
            vertical-align: middle;
        }

        .gallery-table tbody tr:last-child td {
            border-bottom: none;
        }

        .gallery-table tbody tr:hover td {
            background: #fafcff;
        }

        .gallery-serial {
            color: #8995a7 !important;
            font-weight: 700;
            width: 80px;
        }

        .gallery-photo-name {
            color: #26364d !important;
            font-weight: 700;
            overflow-wrap: anywhere;
            min-width: 180px;
        }

        /* VISIBILITY */

        .gallery-visibility {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 7px;
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
            color: #718096 !important;
            font-size: 11px !important;
            white-space: nowrap;
        }

        /* ACTION BUTTONS */

        .gallery-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            align-items: center;
        }

        .gallery-view-btn {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #edf3ff;
            color: #315fbd;
            border: 1px solid #dce7fb;
            border-radius: 7px;
            text-decoration: none;
            transition: background 0.2s;
        }

        .gallery-view-btn:hover {
            background: #dce8ff;
        }

        .gallery-view-btn svg {
            width: 17px;
            height: 17px;
        }

        .gallery-edit-btn,
        .gallery-delete-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 11px;
            border-radius: 7px;
            text-decoration: none;
            border: none;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .gallery-edit-btn {
            background: #edf3ff;
            color: #315fbd;
        }

        .gallery-edit-btn:hover {
            background: #dce8ff;
        }

        .gallery-delete-btn {
            background: #fff0f0;
            color: #a83232;
        }

        .gallery-delete-btn:hover {
            background: #ffe0e0;
        }

        .gallery-delete-form {
            display: inline;
            margin: 0;
        }

        /* INFORMATION NOTICE */

        .gallery-notice {
            padding: 13px 15px;
            background: #f0f5ff;
            color: #41629c;
            border: 1px solid #dce7fb;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.7;
            margin-bottom: 18px;
        }

        /* EMPTY STATE */

        .gallery-empty {
            text-align: center;
            padding: 45px 20px !important;
            color: #8995a7 !important;
        }

        .gallery-empty strong {
            display: block;
            color: #34445c;
            font-size: 14px;
            margin-bottom: 7px;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {

            .gallery-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 650px) {

            .gallery-page {
                padding: 8px 0 25px;
            }

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

            .gallery-summary {
                gap: 10px;
            }

            .gallery-summary-card {
                padding: 16px;
            }

            .gallery-summary-number {
                font-size: 25px;
            }

            .gallery-summary-label {
                font-size: 11px;
            }

            .gallery-panel-header {
                padding: 17px;
            }

            .gallery-panel-body {
                padding: 17px;
            }

            .gallery-form-grid {
                grid-template-columns: 1fr;
            }

            .gallery-form-group.full-width {
                grid-column: auto;
            }

            .gallery-search-form input {
                flex-basis: 100%;
                min-width: 100%;
            }

            .gallery-search-btn,
            .gallery-clear-btn {
                flex: 1;
            }

            .gallery-primary-btn {
                width: 100%;
            }

        }

        @media (max-width: 380px) {

            .gallery-summary {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="dashboard-container">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <h2>Admin Panel</h2>

        <a href="dashboard.php">Dashboard</a>
        <a href="manage_admins.php">Manage Admins</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_customers.php">Manage Customers</a>
        <a href="manage_content.php">Manage Content</a>

        <?php
        require __DIR__ . "/includes/gallery_sidebar_link.php";
        ?>

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

    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="gallery-page">

            <!-- PAGE HEADER -->

            <div class="gallery-header">

                <div>
                    <h1>Gallery Management</h1>

                    <p>
                        Manage property photos and control their visibility across your website and employee panel.
                    </p>
                </div>

                <div class="gallery-header-tag">
                    SUPER ADMIN
                </div>

            </div>

            <!-- FLASH MESSAGE -->

            <?php if ($message !== ""): ?>

                <div class="gallery-alert <?php
                    echo $messageType === "error" ? "error" : "success";
                ?>">

                    <?php echo galleryEscape($message); ?>

                </div>

            <?php endif; ?>

            <!-- SUMMARY -->

            <div class="gallery-summary">

                <div class="gallery-summary-card total">

                    <div class="gallery-summary-label">
                        Matching Photos
                    </div>

                    <div class="gallery-summary-number">
                        <?php echo $totalPhotos; ?>
                    </div>

                </div>

                <div class="gallery-summary-card public">

                    <div class="gallery-summary-label">
                        Public Website Photos
                    </div>

                    <div class="gallery-summary-number">
                        <?php echo $publicPhotos; ?>
                    </div>

                </div>

                <div class="gallery-summary-card employees">

                    <div class="gallery-summary-label">
                        Employees Only Photos
                    </div>

                    <div class="gallery-summary-number">
                        <?php echo $employeePhotos; ?>
                    </div>

                </div>

            </div>

            <!-- UPLOAD PHOTO -->

            <section class="gallery-panel">

                <div class="gallery-panel-header">

                    <h2>Upload New Photo</h2>

                    <p>
                        Add a photo to your gallery and select its visibility.
                    </p>

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

                                <label for="photo">Select Photo</label>

                                <input
                                    type="file"
                                    id="photo"
                                    name="photo"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    required
                                >

                                <span class="gallery-form-help">
                                    Allowed formats: JPG, JPEG, PNG and WEBP. Maximum file size: 5 MB.
                                </span>

                            </div>

                            <div class="gallery-form-group">

                                <label for="title">
                                    Photo Name (Optional)
                                </label>

                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    maxlength="150"
                                    placeholder="Enter photo name"
                                >

                                <span class="gallery-form-help">
                                    If left blank, the original desktop filename will be used automatically.
                                </span>

                            </div>

                            <div class="gallery-form-group">

                                <label for="visibility">
                                    Who Can View This Photo?
                                </label>

                                <select id="visibility" name="visibility" required>

                                    <option value="employees" selected>
                                        Employees Only
                                    </option>

                                    <option value="public">
                                        Public Website
                                    </option>

                                </select>

                                <span class="gallery-form-help">
                                    Employees Only is selected by default.
                                </span>

                            </div>

                            <div class="gallery-form-group full-width">

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
                            Upload Photo
                        </button>

                    </form>

                </div>

            </section>

            <!-- UPLOADED PHOTOS -->

            <section class="gallery-panel">

                <div class="gallery-panel-header">

                    <h2>Uploaded Photos</h2>

                    <p>
                        Search, preview, edit and delete gallery photos.
                    </p>

                </div>

                <div class="gallery-panel-body">

                    <div class="gallery-notice">
                        Use the eye icon to preview a photo. Public Website photos are intended for public display, while Employees Only photos are restricted to authorized admin-panel users.
                    </div>

                    <!-- SEARCH -->

                    <form method="GET" class="gallery-search-form">

                        <input
                            type="text"
                            name="search"
                            placeholder="Search by photo name..."
                            value="<?php echo galleryEscape($search); ?>"
                        >

                        <button type="submit" class="gallery-search-btn">
                            Search
                        </button>

                        <a href="manage_gallery.php" class="gallery-clear-btn">
                            Clear Search
                        </a>

                    </form>

                </div>

                <!-- TABLE -->

                <div class="gallery-table-wrapper">

                    <table class="gallery-table">

                        <thead>

                            <tr>
                                <th>S. No.</th>
                                <th>Photo Name</th>
                                <th>Visibility</th>
                                <th>Uploaded At</th>
                                <th>View</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($photos)): ?>

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

                                    ?>

                                    <tr>

                                        <td class="gallery-serial">
                                            <?php echo $serialNumber++; ?>
                                        </td>

                                        <td class="gallery-photo-name">
                                            <?php echo galleryEscape($displayPhotoName); ?>
                                        </td>

                                        <td>

                                            <span class="gallery-visibility <?php
                                                echo $photo["visibility"] === "public"
                                                    ? "public"
                                                    : "employees";
                                            ?>">

                                                <?php
                                                echo $photo["visibility"] === "public"
                                                    ? "Public Website"
                                                    : "Employees Only";
                                                ?>

                                            </span>

                                        </td>

                                        <td class="gallery-date">
                                            <?php echo galleryEscape($photo["created_at"] ?? ""); ?>
                                        </td>

                                        <td>

                                            <a
                                                href="gallery_preview.php?id=<?php echo (int)$photo["id"]; ?>"
                                                class="gallery-view-btn"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                title="View Photo"
                                                aria-label="View photo"
                                            >

                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                                                    <circle cx="12" cy="12" r="3"/>
                                                </svg>

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
                                                    onsubmit="return confirm('Are you sure you want to permanently delete this photo? This action cannot be undone.');"
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

                                    <td colspan="6" class="gallery-empty">

                                        <strong>No Photos Found</strong>

                                        <?php if ($search !== ""): ?>

                                            No gallery photos match your search.

                                        <?php else: ?>

                                            You have not uploaded any gallery photos yet.

                                        <?php endif; ?>

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
