
<?php

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// --------------------------------------------------
// REQUIRED FILES
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

// --------------------------------------------------
// ACCESS CONTROL
// --------------------------------------------------

if (($_SESSION["admin_role"] ?? "") !== "sales_employee") {
    http_response_code(403);
    exit("Access Denied! This page is for Sales Employees only.");
}

$adminId = (int)($_SESSION["admin_id"] ?? 0);

if ($adminId <= 0) {
    header("Location: login.php");
    exit;
}

// --------------------------------------------------
// INITIALIZE
// --------------------------------------------------

$message = "";
$messageType = "";

// --------------------------------------------------
// CSRF PROTECTION
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// --------------------------------------------------
// MARK NOTIFICATION AS READ
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        http_response_code(403);
        exit("Invalid security token. Please reload the page.");
    }

    $notificationId = filter_var(
        $_POST["notification_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$notificationId || $notificationId <= 0) {

        $message = "Invalid notification.";
        $messageType = "error";

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = ?
                AND admin_id = ?
                AND is_read = 0
            ");

            $stmt->execute([
                $notificationId,
                $adminId
            ]);

            if ($stmt->rowCount() > 0) {
                $message = "Notification marked as read.";
                $messageType = "success";
            } else {
                $message = "Notification was already read or could not be found.";
                $messageType = "error";
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $message = "Unable to update the notification. Please try again.";
            $messageType = "error";
        }
    }

    // Rotate CSRF token after processing the request.
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    $csrfToken = $_SESSION["csrf_token"];
}

// --------------------------------------------------
// FETCH EMPLOYEE NOTIFICATIONS
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT
            n.*,
            l.customer_name
        FROM notifications n
        LEFT JOIN leads l
            ON n.lead_id = l.id
        WHERE n.admin_id = ?
        ORDER BY n.created_at DESC, n.id DESC
    ");

    $stmt->execute([$adminId]);

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // COUNT UNREAD NOTIFICATIONS
    // --------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE admin_id = ?
        AND is_read = 0
    ");

    $stmt->execute([$adminId]);

    $unreadCount = (int)$stmt->fetchColumn();

} catch (PDOException $e) {

    error_log($e->getMessage());

    $notifications = [];
    $unreadCount = 0;

    $message = "Unable to load notifications. Please try again.";
    $messageType = "error";
}

// --------------------------------------------------
// OUTPUT ESCAPING
// --------------------------------------------------

function notificationEscape($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>My Notifications | Admin Panel</title>

    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">

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
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
        }

        .main-content {
            margin-left: 255px;
            padding: 35px;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 27px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 27px;
            color: var(--navy);
        }

        .page-header p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .notification-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 22px;
        }

        .summary-label {
            color: var(--muted);
            font-size: 14px;
        }

        .summary-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 20px;
            background: #f3e8ca;
            color: var(--navy);
            font-size: 15px;
            font-weight: bold;
        }

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 5px solid #aab3c0;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(20, 35, 60, 0.035);
        }

        .notification-card.unread {
            border-left-color: var(--gold);
            background: #fffdf8;
        }

        .notification-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .notification-heading h3 {
            margin: 0;
            color: var(--navy);
            font-size: 16px;
            line-height: 1.5;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-unread {
            background: #f3e8ca;
            color: #77550d;
        }

        .status-read {
            background: #e9edf2;
            color: #526174;
        }

        .notification-message {
            margin: 0 0 15px;
            color: #46566b;
            font-size: 14px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .notification-meta {
            margin: 0 0 16px;
            color: #8490a1;
            font-size: 12px;
        }

        .notification-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .notification-actions form {
            margin: 0;
        }

        .notification-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 14px;
            border: none;
            border-radius: 6px;
            background: var(--navy);
            color: #ffffff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-btn:hover {
            background: var(--navy-light);
        }

        .notification-btn.mark-read {
            background: var(--gold);
            color: var(--navy);
        }

        .notification-btn.mark-read:hover {
            background: var(--gold-light);
        }

        .page-message {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.5;
        }

        .page-message.success {
            background: #eaf7ef;
            color: #187444;
            border: 1px solid #c7ead5;
        }

        .page-message.error {
            background: #fff0f0;
            color: #b42323;
            border: 1px solid #f3cccc;
        }

        .empty-state {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 45px 20px;
            text-align: center;
            color: var(--muted);
        }

        .empty-state h3 {
            margin: 0 0 8px;
            color: var(--navy);
            font-size: 17px;
        }

        .empty-state p {
            margin: 0;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 1000px) {

            .main-content {
                margin-left: 220px;
                padding: 25px;
            }
        }

        @media (max-width: 700px) {

            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .notification-card {
                padding: 16px;
            }

        }

    </style>

</head>

<body>

<div class="admin-layout">

    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <main class="main-content">

        <div class="page-header">

            <h1>My Notifications</h1>

            <p>
                View and manage notifications assigned to your sales account.
            </p>

        </div>

        <?php if ($message !== ""): ?>

            <div class="page-message <?php echo notificationEscape($messageType); ?>">
                <?php echo notificationEscape($message); ?>
            </div>

        <?php endif; ?>

        <div class="notification-summary">

            <span class="summary-label">
                Total Unread Notifications
            </span>

            <span class="summary-count">
                <?php echo $unreadCount; ?>
            </span>

        </div>

        <?php if (empty($notifications)): ?>

            <div class="empty-state">

                <h3>No Notifications Yet</h3>

                <p>
                    You do not have any notifications at the moment.
                </p>

            </div>

        <?php else: ?>

            <div class="notification-list">

                <?php foreach ($notifications as $notification): ?>

                    <?php
                    $isUnread = ((int)$notification["is_read"] === 0);
                    ?>

                    <div class="notification-card <?php echo $isUnread ? "unread" : ""; ?>">

                        <div class="notification-heading">

                            <h3>
                                <?php echo notificationEscape($notification["title"] ?? "Notification"); ?>
                            </h3>

                            <?php if ($isUnread): ?>

                                <span class="status-badge status-unread">
                                    Unread
                                </span>

                            <?php else: ?>

                                <span class="status-badge status-read">
                                    Read
                                </span>

                            <?php endif; ?>

                        </div>

                        <p class="notification-message">
                            <?php echo nl2br(notificationEscape($notification["message"] ?? "")); ?>
                        </p>

                        <p class="notification-meta">

                            Received:
                            <?php echo notificationEscape($notification["created_at"] ?? ""); ?>

                        </p>

                        <div class="notification-actions">

                            <?php if (!empty($notification["lead_id"])): ?>

                                <a
                                    class="notification-btn"
                                    href="view_lead.php?id=<?php echo (int)$notification["lead_id"]; ?>"
                                >
                                    Open Lead
                                </a>

                            <?php endif; ?>

                            <?php if ($isUnread): ?>

                                <form method="POST" action="notifications.php">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo notificationEscape($csrfToken); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?php echo (int)$notification["id"]; ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="notification-btn mark-read"
                                    >
                                        Mark as Read
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </main>

</div>

</body>
</html>
