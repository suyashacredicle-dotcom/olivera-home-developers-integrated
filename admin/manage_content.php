
<?php

// --------------------------------------------------
// MANAGE WEBSITE CONTENT
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/activity_logger.php";

requirePermission("manage_content");

// --------------------------------------------------
// ESCAPE OUTPUT
// --------------------------------------------------

function manageContentEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
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

// --------------------------------------------------
// FLASH MESSAGES
// --------------------------------------------------

$message = "";
$messageType = "";

if (isset($_SESSION["content_message"])) {

    $message = $_SESSION["content_message"];
    $messageType = $_SESSION["content_message_type"] ?? "success";

    unset($_SESSION["content_message"]);
    unset($_SESSION["content_message_type"]);
}

// --------------------------------------------------
// CREATE CONTENT
// --------------------------------------------------

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["create_content"])
) {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {
        http_response_code(403);
        exit("Invalid request. Please reload the page.");
    }

    $title = $_POST["title"] ?? "";
    $body = $_POST["body"] ?? "";
    $status = $_POST["status"] ?? "draft";

    if (
        !is_string($title) ||
        !is_string($body) ||
        !is_string($status)
    ) {

        $_SESSION["content_message"] = "Invalid input provided.";
        $_SESSION["content_message_type"] = "error";

    } else {

        $title = trim($title);
        $body = trim($body);

        if ($title === "" || $body === "") {

            $_SESSION["content_message"] =
                "Please fill in all fields.";

            $_SESSION["content_message_type"] = "error";

        } elseif (strlen($title) > 255) {

            $_SESSION["content_message"] =
                "Content title cannot exceed 255 characters.";

            $_SESSION["content_message_type"] = "error";

        } elseif (!in_array($status, ["draft", "published"], true)) {

            $_SESSION["content_message"] =
                "Invalid content status.";

            $_SESSION["content_message_type"] = "error";

        } else {

            try {

                $adminId = filter_var(
                    $_SESSION["admin_id"] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$adminId || $adminId < 1) {
                    throw new RuntimeException("Invalid admin session.");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO content
                    (title, body, status, created_by)
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->execute([
                    $title,
                    $body,
                    $status,
                    $adminId
                ]);

                logActivity(
                    $pdo,
                    "CONTENT_CREATED",
                    "Created content: " . $title
                );

                $_SESSION["content_message"] =
                    "Content created successfully!";

                $_SESSION["content_message_type"] = "success";

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            } catch (Throwable $e) {

                error_log(
                    "Content creation failed: " . $e->getMessage()
                );

                $_SESSION["content_message"] =
                    "Unable to create content. Please try again.";

                $_SESSION["content_message_type"] = "error";
            }
        }
    }

    header("Location: manage_content.php");
    exit;
}

// --------------------------------------------------
// SEARCH CONTENT
// --------------------------------------------------

$search = $_GET["search"] ?? "";

if (!is_string($search)) {
    $search = "";
}

$search = trim($search);

try {

    if ($search !== "") {

        $stmt = $pdo->prepare("
            SELECT *
            FROM content
            WHERE title LIKE ?
            ORDER BY id DESC
        ");

        $stmt->execute(["%" . $search . "%"]);

    } else {

        $stmt = $pdo->query("
            SELECT *
            FROM content
            ORDER BY id DESC
        ");
    }

    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        "Content listing failed: " . $e->getMessage()
    );

    $contents = [];

    if ($message === "") {
        $message = "Unable to load content records.";
        $messageType = "error";
    }
}

$totalContents = count($contents);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Content | Olivera Admin</title>

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
            margin: 0;
            background: var(--ivory);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        /* MAIN LAYOUT */

        .dashboard-container {
            display: block;
            min-height: 100vh;
            width: 100%;
        }

        .main-content {
            min-width: 0;
            width: calc(100% - 255px);
            margin-left: 255px;
            padding: 28px;
        }

        .content-page {
            width: 100%;
            max-width: 1450px;
            margin: 0 auto;
            padding: 0 0 35px;
            color: var(--text);
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
            background: linear-gradient(
                120deg,
                var(--navy),
                var(--navy-light)
            );
            border: 1px solid var(--navy-light);
            border-radius: 13px;
            color: white;
        }

        .content-header h1 {
            margin: 0 0 7px;
            color: white;
            font-size: 27px;
            font-weight: 750;
        }

        .content-header p {
            margin: 0;
            color: #d7dfeb;
            font-size: 13px;
            line-height: 1.6;
        }

        .content-heading {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .content-header-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border: 1px solid rgba(237,202,118,.4);
            border-radius: 12px;
            background: rgba(214,166,59,.15);
            color: var(--gold-light);
            font-size: 23px;
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
            overflow: hidden;
            padding: 22px 23px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
            box-shadow: 0 3px 12px rgba(7,27,56,.035);
        }

        .content-summary-card::before {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 4px;
            background: var(--gold);
            content: "";
        }

        .content-summary-label {
            margin-bottom: 10px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 650;
        }

        .content-summary-number {
            color: var(--navy);
            font-size: 31px;
            font-weight: 750;
            line-height: 1.2;
        }

        /* PANELS */

        .content-panel {
            overflow: hidden;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: var(--surface);
            box-shadow: 0 4px 15px rgba(7,27,56,.035);
        }

        .content-panel-header {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 21px 25px;
            border-bottom: 1px solid var(--border);
            background: #faf8f2;
        }

        .content-panel-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 39px;
            height: 39px;
            flex-shrink: 0;
            border-radius: 10px;
            background: rgba(214,166,59,.16);
            color: #94701e;
            font-size: 18px;
        }

        .content-panel-header h2 {
            margin: 0 0 5px;
            color: var(--navy);
            font-size: 16px;
            font-weight: 750;
        }

        .content-panel-header p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .content-panel-body {
            padding: 25px;
        }

        /* ALERTS */

        .content-alert {
            margin-bottom: 22px;
            padding: 15px 18px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 650;
        }

        .content-alert.success {
            border: 1px solid #c9e5cf;
            background: #edf7ee;
            color: #27653a;
        }

        .content-alert.error {
            border: 1px solid #efcfca;
            background: #fbefed;
            color: #a33128;
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
            margin-bottom: 8px;
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        .content-form-group input,
        .content-form-group select,
        .content-form-group textarea {
            display: block;
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            background: white;
            color: var(--text);
            font-family: inherit;
            font-size: 13px;
            transition: border-color .2s, box-shadow .2s;
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
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,.13);
        }

        .content-form-group input::placeholder,
        .content-form-group textarea::placeholder {
            color: #a0aaba;
        }

        .content-form-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 11px;
        }

        /* BUTTONS */

        .content-primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 21px;
            border: 1px solid var(--gold);
            border-radius: 8px;
            background: linear-gradient(
                120deg,
                var(--gold-light),
                var(--gold)
            );
            color: var(--navy);
            font-size: 13px;
            font-weight: 750;
            cursor: pointer;
            transition: .2s ease;
        }

        .content-primary-btn:hover {
            filter: brightness(.96);
            transform: translateY(-1px);
        }

        /* SEARCH */

        .content-search-area {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
        }

        .content-search-form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .content-search-form input {
            flex: 1;
            min-width: 220px;
            min-height: 43px;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            color: var(--text);
            font-size: 13px;
        }

        .content-search-form input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,.13);
        }

        .content-search-btn {
            min-height: 43px;
            padding: 10px 19px;
            border: 1px solid var(--navy);
            border-radius: 8px;
            background: var(--navy);
            color: white;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .content-search-btn:hover {
            background: var(--navy-light);
        }

        .content-clear-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 43px;
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #faf8f2;
            color: var(--text);
            font-size: 12px;
            font-weight: 650;
            text-decoration: none;
            transition: .2s;
        }

        .content-clear-btn:hover {
            border-color: var(--gold);
            background: #f5f0e4;
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
            padding: 16px 19px;
            border-bottom: 1px solid var(--border);
            background: #faf8f2;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: .55px;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .content-table td {
            padding: 16px 19px;
            border-bottom: 1px solid #f0ede6;
            color: #475569;
            font-size: 13px;
            vertical-align: middle;
        }

        .content-table tbody tr {
            transition: background .15s ease;
        }

        .content-table tbody tr:hover {
            background: #fcfaf5;
        }

        .content-table tbody tr:last-child td {
            border-bottom: none;
        }

        .content-id {
            color: var(--muted) !important;
            font-weight: 700;
            white-space: nowrap;
        }

        .content-title {
            max-width: 320px;
            color: var(--navy) !important;
            font-weight: 650;
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
            background: #edf7ee;
            color: #27653a;
        }

        .content-status.draft {
            background: #fbf3df;
            color: #896319;
        }

        .content-date {
            color: var(--muted) !important;
            font-size: 12px !important;
            white-space: nowrap;
        }

        /* ACTIONS */

        .content-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
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
            transition: .2s;
        }

        .content-edit-btn {
            border: 1px solid #e5d5a9;
            background: #faf5e8;
            color: #80601b;
        }

        .content-edit-btn:hover {
            background: #f2e8ce;
        }

        .content-delete-btn {
            border: 1px solid #efcfca;
            background: #fbefed;
            color: #a33128;
        }

        .content-delete-btn:hover {
            background: #f7e2df;
        }

        /* EMPTY STATE */

        .content-empty {
            padding: 48px 20px !important;
            color: var(--muted) !important;
            line-height: 1.7;
            text-align: center;
        }

        .content-empty strong {
            display: block;
            margin-bottom: 6px;
            color: var(--navy);
            font-size: 15px;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .main-content {
                width: calc(100% - 220px);
                margin-left: 220px;
                padding: 22px;
            }
        }

        @media (max-width: 760px) {

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

        @media (max-width: 700px) {

            .dashboard-container {
                display: block;
            }

            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 18px;
            }
        }

        @media (max-width: 480px) {

            .main-content {
                padding: 15px;
            }

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

    <!-- SHARED ADMIN SIDEBAR CSS -->
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">

</head>

<body>

<div class="dashboard-container">

    <!-- SHARED ADMIN SIDEBAR -->
    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

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
                ?>" role="alert">

                    <?php echo manageContentEscape($message); ?>

                </div>

            <?php endif; ?>

            <!-- CONTENT SUMMARY -->

            <div class="content-summary">

                <div class="content-summary-card">

                    <div class="content-summary-label">
                        Matching Content Records
                    </div>

                    <div class="content-summary-number">
                        <?php echo (int)$totalContents; ?>
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

                    <form method="POST" action="manage_content.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo manageContentEscape($_SESSION["csrf_token"]); ?>"
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

                    <form method="GET" action="manage_content.php" class="content-search-form">

                        <input
                            type="text"
                            name="search"
                            placeholder="Search content by title..."
                            value="<?php echo manageContentEscape($search); ?>"
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
                                            #<?php echo (int)$content["id"]; ?>
                                        </td>

                                        <td class="content-title">
                                            <?php echo manageContentEscape($content["title"]); ?>
                                        </td>

                                        <td>

                                            <span class="content-status <?php
                                                echo $content["status"] === "published"
                                                    ? "published"
                                                    : "draft";
                                            ?>">

                                                <?php echo manageContentEscape($content["status"]); ?>

                                            </span>

                                        </td>

                                        <td class="content-date">
                                            <?php echo manageContentEscape($content["created_at"]); ?>
                                        </td>

                                        <td>

                                            <div class="content-actions">

                                                <a
                                                    href="actions/edit_content.php?id=<?php echo (int)$content["id"]; ?>"
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
                                                        value="<?php echo manageContentEscape($_SESSION["csrf_token"]); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?php echo (int)$content["id"]; ?>"
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
