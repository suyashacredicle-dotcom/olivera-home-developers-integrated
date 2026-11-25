
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";
require_once "../includes/activity_logger.php";

requirePermission("manage_content");

// Escape output safely
function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Create CSRF token if it does not exist
if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

// Display messages from previous actions
if (isset($_SESSION["content_message"])) {

    $message = $_SESSION["content_message"];

    $messageType =
        $_SESSION["content_message_type"] ?? "success";

    unset($_SESSION["content_message"]);
    unset($_SESSION["content_message_type"]);
}

// Create new content
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["create_content"])
) {

    if (
        !isset($_POST["csrf_token"]) ||
        !is_string($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }

    $title = trim($_POST["title"] ?? "");
    $body = trim($_POST["body"] ?? "");
    $status = $_POST["status"] ?? "draft";

    if ($title === "" || $body === "") {

        $_SESSION["content_message"] =
            "Please fill in all fields.";

        $_SESSION["content_message_type"] = "error";

    } elseif (
        !in_array($status, ["draft", "published"], true)
    ) {

        $_SESSION["content_message"] =
            "Invalid content status.";

        $_SESSION["content_message_type"] = "error";

    } else {

        try {

            $stmt = $pdo->prepare(
                "INSERT INTO content
                    (title, body, status, created_by)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $title,
                $body,
                $status,
                $_SESSION["admin_id"]
            ]);

            logActivity(
                $pdo,
                "CONTENT_CREATED",
                "Created content: " . $title
            );

            $_SESSION["content_message"] =
                "Content created successfully!";

            $_SESSION["content_message_type"] = "success";

        } catch (PDOException $e) {

            error_log(
                "Content creation failed: " . $e->getMessage()
            );

            $_SESSION["content_message"] =
                "Unable to create content. Please try again.";

            $_SESSION["content_message_type"] = "error";
        }
    }

    header("Location: manage_content.php");
    exit;
}

// Search content
$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $pdo->prepare(
        "SELECT * FROM content
         WHERE title LIKE ?
         ORDER BY id DESC"
    );

    $stmt->execute(["%" . $search . "%"]);

} else {

    $stmt = $pdo->query(
        "SELECT * FROM content ORDER BY id DESC"
    );
}

$contents = $stmt->fetchAll();

$totalContents = count($contents);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Content | Admin Panel</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>

        * {
            box-sizing: border-box;
        }

        .content-page {
            width: 100%;
            max-width: 1450px;
            margin: 0 auto;
            padding: 12px 8px 40px;
            color: #1e293b;
        }

        /* PAGE HEADER */

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 25px;
            padding: 26px 28px;
            background: linear-gradient(120deg, #ffffff, #f1f6ff);
            border: 1px solid #e2e8f0;
            border-radius: 15px;
        }

        .content-header h1 {
            color: #172554;
            font-size: 27px;
            font-weight: 750;
            letter-spacing: -0.5px;
            margin: 0 0 7px;
        }

        .content-header p {
            color: #64748b;
            font-size: 13px;
            margin: 0;
            line-height: 1.6;
        }

        .content-header-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #e8efff;
            color: #2454b8;
            border-radius: 12px;
            font-size: 23px;
        }

        .content-heading {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* SUMMARY */

        .content-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 17px;
            margin-bottom: 24px;
        }

        .content-summary-card {
            position: relative;
            background: #ffffff;
            padding: 22px 23px;
            border: 1px solid #e2e8f0;
            border-radius: 13px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.035);
            overflow: hidden;
        }

        .content-summary-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #4776d0;
        }

        .content-summary-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 650;
            margin-bottom: 10px;
        }

        .content-summary-number {
            color: #172554;
            font-size: 31px;
            font-weight: 750;
            line-height: 1.2;
        }

        /* PANELS */

        .content-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 24px;
            box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
            overflow: hidden;
        }

        .content-panel-header {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 21px 25px;
            background: #fbfcfe;
            border-bottom: 1px solid #edf0f5;
        }

        .content-panel-icon {
            width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #2454b8;
            background: #eaf0ff;
            border-radius: 10px;
            font-size: 18px;
        }

        .content-panel-header h2 {
            color: #172554;
            font-size: 16px;
            font-weight: 750;
            margin: 0 0 5px;
        }

        .content-panel-header p {
            color: #8290a3;
            font-size: 12px;
            margin: 0;
            line-height: 1.5;
        }

        .content-panel-body {
            padding: 25px;
        }

        /* ALERTS */

        .content-alert {
            padding: 15px 18px;
            margin-bottom: 22px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 650;
        }

        .content-alert.success {
            background: #ecfdf3;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .content-alert.error {
            background: #fff1f2;
            color: #9f1239;
            border: 1px solid #fecdd3;
        }

        /* FORM */

        .content-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 3px 23px;
        }

        .content-form-group {
            min-width: 0;
            margin-bottom: 20px;
        }

        .content-form-group.full-width {
            grid-column: 1 / -1;
        }

        .content-form-group label {
            display: block;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .content-form-group input,
        .content-form-group select,
        .content-form-group textarea {
            display: block;
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d8e0eb;
            border-radius: 9px;
            font-family: inherit;
            font-size: 13px;
            color: #334155;
            background: #ffffff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .content-form-group input,
        .content-form-group select {
            min-height: 44px;
        }

        .content-form-group textarea {
            min-height: 170px;
            resize: vertical;
            line-height: 1.7;
        }

        .content-form-group input:focus,
        .content-form-group select:focus,
        .content-form-group textarea:focus {
            border-color: #4776d0;
            box-shadow: 0 0 0 3px rgba(59, 111, 205, 0.10);
        }

        .content-form-group input::placeholder,
        .content-form-group textarea::placeholder {
            color: #a0acbc;
        }

        .content-form-help {
            display: block;
            color: #8794a7;
            font-size: 11px;
            margin-top: 7px;
        }

        /* BUTTONS */

        .content-primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 21px;
            background: #2454b8;
            color: #ffffff;
            border: 1px solid #2454b8;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .content-primary-btn:hover {
            background: #1d4295;
            border-color: #1d4295;
            box-shadow: 0 4px 12px rgba(36, 84, 184, 0.18);
            transform: translateY(-1px);
        }

        /* SEARCH */

        .content-search-area {
            padding: 20px 25px;
            border-bottom: 1px solid #edf0f5;
        }

        .content-search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .content-search-form input {
            flex: 1;
            min-width: 220px;
            min-height: 43px;
            padding: 11px 14px;
            border: 1px solid #d8e0eb;
            border-radius: 9px;
            font-size: 13px;
            color: #334155;
            outline: none;
            transition: 0.2s;
        }

        .content-search-form input:focus {
            border-color: #4776d0;
            box-shadow: 0 0 0 3px rgba(59, 111, 205, 0.10);
        }

        .content-search-btn {
            min-height: 43px;
            padding: 10px 19px;
            background: #2454b8;
            color: #ffffff;
            border: 1px solid #2454b8;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .content-search-btn:hover {
            background: #1d4295;
        }

        .content-clear-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 43px;
            padding: 10px 16px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 650;
            transition: 0.2s;
        }

        .content-clear-btn:hover {
            background: #e8edf4;
            color: #1e293b;
        }

        /* TABLE */

        .content-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .content-table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }

        .content-table th {
            background: #f8faff;
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.55px;
            text-align: left;
            padding: 16px 19px;
            border-bottom: 1px solid #e7ecf3;
            white-space: nowrap;
        }

        .content-table td {
            padding: 16px 19px;
            border-bottom: 1px solid #f0f2f6;
            color: #475569;
            font-size: 13px;
            vertical-align: middle;
        }

        .content-table tbody tr {
            transition: background 0.15s ease;
        }

        .content-table tbody tr:hover {
            background: #f8faff;
        }

        .content-table tbody tr:last-child td {
            border-bottom: none;
        }

        .content-id {
            color: #8290a3 !important;
            font-weight: 700;
            white-space: nowrap;
        }

        .content-title {
            color: #172554 !important;
            font-weight: 650;
            max-width: 320px;
        }

        .content-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 750;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .content-status.published {
            background: #ecfdf3;
            color: #167044;
        }

        .content-status.draft {
            background: #fff7e6;
            color: #a16207;
        }

        .content-date {
            color: #8290a3 !important;
            font-size: 12px !important;
            white-space: nowrap;
        }

        /* ACTIONS */

        .content-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .content-actions form {
            margin: 0;
        }

        .content-edit-btn,
        .content-delete-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 31px;
            padding: 7px 12px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .content-edit-btn {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }

        .content-edit-btn:hover {
            background: #dbeafe;
        }

        .content-delete-btn {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #ffe4e6;
        }

        .content-delete-btn:hover {
            background: #ffe4e6;
        }

        /* EMPTY STATE */

        .content-empty {
            text-align: center;
            padding: 48px 20px !important;
            color: #8290a3 !important;
            line-height: 1.7;
        }

        .content-empty strong {
            display: block;
            color: #334155;
            font-size: 15px;
            margin-bottom: 6px;
        }

        /* RESPONSIVE */

        @media (max-width: 760px) {

            .content-page {
                padding: 8px 2px 25px;
            }

            .content-header {
                padding: 20px;
            }

            .content-header h1 {
                font-size: 23px;
            }

            .content-form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .content-form-group.full-width {
                grid-column: auto;
            }

            .content-panel-body {
                padding: 19px;
            }

            .content-panel-header {
                padding: 18px;
            }

            .content-search-area {
                padding: 17px;
            }

        }

        @media (max-width: 480px) {

            .content-heading {
                gap: 10px;
            }

            .content-header-icon {
                width: 40px;
                height: 40px;
                font-size: 19px;
            }

            .content-search-form {
                align-items: stretch;
                flex-direction: column;
            }

            .content-search-form input,
            .content-search-btn,
            .content-clear-btn {
                width: 100%;
            }

            .content-primary-btn {
                width: 100%;
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

        <?php
        require __DIR__ . "/includes/gallery_sidebar_link.php";
        ?>

        <a href="manage_admins.php">Manage Admins</a>

        <a href="manage_users.php">Manage Users</a>

        <a href="manage_customers.php">Manage Customers</a>

        <a href="manage_content.php">Manage Content</a>

        <a href="manage_settings.php">Website Settings</a>

        <a href="activity_logs.php">Activity Logs</a>

        <a href="profile.php">My Profile</a>

        <a href="change_password.php">Change Password</a>

        <form action="logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION["csrf_token"]); ?>"
            >

            <button type="submit">Logout</button>

        </form>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="content-page">

            <!-- PAGE HEADER -->

            <div class="content-header">

                <div class="content-heading">

                    <div class="content-header-icon">▤</div>

                    <div>

                        <h1>Manage Content</h1>

                        <p>
                            Create, manage, edit and publish your website content.
                        </p>

                    </div>

                </div>

            </div>

            <!-- SUCCESS / ERROR MESSAGE -->

            <?php if ($message !== ""): ?>

                <div class="content-alert <?php
                    echo $messageType === "success"
                        ? "success"
                        : "error";
                ?>">

                    <?php echo e($message); ?>

                </div>

            <?php endif; ?>

            <!-- CONTENT SUMMARY -->

            <div class="content-summary">

                <div class="content-summary-card">

                    <div class="content-summary-label">
                        Matching Content Records
                    </div>

                    <div class="content-summary-number">
                        <?php echo $totalContents; ?>
                    </div>

                </div>

            </div>

            <!-- CREATE CONTENT -->

            <section class="content-panel">

                <div class="content-panel-header">

                    <div class="content-panel-icon">＋</div>

                    <div>

                        <h2>Create New Content</h2>

                        <p>
                            Add a new content entry to your website.
                        </p>

                    </div>

                </div>

                <div class="content-panel-body">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo e($_SESSION["csrf_token"]); ?>"
                        >

                        <div class="content-form-grid">

                            <div class="content-form-group full-width">

                                <label for="title">
                                    Content Title
                                </label>

                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    placeholder="Enter content title"
                                    maxlength="255"
                                    required
                                >

                            </div>

                            <div class="content-form-group full-width">

                                <label for="body">
                                    Content Body
                                </label>

                                <textarea
                                    id="body"
                                    name="body"
                                    placeholder="Write your website content here..."
                                    required
                                ></textarea>

                                <span class="content-form-help">
                                    Enter the content you want to add to your website.
                                </span>

                            </div>

                            <div class="content-form-group">

                                <label for="status">
                                    Publication Status
                                </label>

                                <select id="status" name="status">

                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>

                                </select>

                            </div>

                        </div>

                        <button
                            type="submit"
                            name="create_content"
                            class="content-primary-btn"
                        >
                            + Add Content
                        </button>

                    </form>

                </div>

            </section>

            <!-- EXISTING CONTENT -->

            <section class="content-panel">

                <div class="content-panel-header">

                    <div class="content-panel-icon">▦</div>

                    <div>

                        <h2>Existing Content</h2>

                        <p>
                            View and manage your existing website content.
                        </p>

                    </div>

                </div>

                <!-- SEARCH -->

                <div class="content-search-area">

                    <form method="GET" class="content-search-form">

                        <input
                            type="text"
                            name="search"
                            placeholder="Search content by title..."
                            value="<?php echo e($search); ?>"
                        >

                        <button
                            type="submit"
                            class="content-search-btn"
                        >
                            Search
                        </button>

                        <a
                            href="manage_content.php"
                            class="content-clear-btn"
                        >
                            Clear Search
                        </a>

                    </form>

                </div>

                <!-- CONTENT TABLE -->

                <div class="content-table-wrapper">

                    <table class="content-table">

                        <thead>

                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if ($totalContents > 0): ?>

                                <?php foreach ($contents as $content): ?>

                                    <tr>

                                        <td class="content-id">
                                            #<?php echo (int) $content["id"]; ?>
                                        </td>

                                        <td class="content-title">
                                            <?php echo e($content["title"]); ?>
                                        </td>

                                        <td>

                                            <span class="content-status <?php
                                                echo $content["status"] === "published"
                                                    ? "published"
                                                    : "draft";
                                            ?>">

                                                <?php echo e($content["status"]); ?>

                                            </span>

                                        </td>

                                        <td class="content-date">
                                            <?php echo e($content["created_at"]); ?>
                                        </td>

                                        <td>

                                            <div class="content-actions">

                                                <a
                                                    href="actions/edit_content.php?id=<?php
                                                        echo (int) $content["id"];
                                                    ?>"
                                                    class="content-edit-btn"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    action="actions/delete_content.php"
                                                    method="POST"
                                                    onsubmit="return confirm('Are you sure you want to permanently delete this content? This action cannot be undone.');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?php
                                                            echo e($_SESSION["csrf_token"]);
                                                        ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?php
                                                            echo (int) $content["id"];
                                                        ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="content-delete-btn"
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

                                    <td colspan="5" class="content-empty">

                                        <strong>No Content Found</strong>

                                        <?php if ($search !== ""): ?>

                                            No content matches your search.

                                        <?php else: ?>

                                            You have not created any content yet.

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
