
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

// Only Sales Employees can access their notification inbox.
if ($_SESSION["admin_role"] !== "sales_employee") {
    http_response_code(403);
    exit("Access Denied! This page is for Sales Employees only.");
}

$adminId = (int)$_SESSION["admin_id"];

$message = "";

// CSRF protection
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// Mark notification as read
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $postedToken)) {

        $message = "Invalid security token. Please try again.";

    } else {

        $notificationId = (int)($_POST["notification_id"] ?? 0);

        if ($notificationId > 0) {

            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = ?
                AND admin_id = ?
            ");

            $stmt->execute([
                $notificationId,
                $adminId
            ]);

            $message = "Notification marked as read.";
        }
    }
}

// Fetch this employee's notifications only.
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

// Count unread notifications.
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE admin_id = ?
    AND is_read = 0
");

$stmt->execute([$adminId]);
$unreadCount = (int)$stmt->fetchColumn();

function h($value) {
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

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>My Notifications</title>

<style>

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
    padding: 20px;
    color: #222;
}

.container {
    max-width: 950px;
    margin: auto;
}

.navigation {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.navigation a {
    display: inline-block;
    background: #1f5e9c;
    color: white;
    text-decoration: none;
    padding: 9px 14px;
    border-radius: 5px;
}

.panel {
    background: white;
    padding: 22px;
    border-radius: 9px;
    box-shadow: 0 2px 8px #0000000c;
}

.notification {
    background: #f8f9fa;
    border: 1px solid #e1e5eb;
    border-left: 5px solid #218653;
    padding: 16px;
    border-radius: 6px;
    margin-bottom: 14px;
}

.notification.unread {
    background: #eef6ff;
    border-left-color: #1f5e9c;
}

.notification h3 {
    margin: 0 0 10px;
    font-size: 17px;
}

.notification p {
    line-height: 1.6;
}

.meta {
    color: #667085;
    font-size: 12px;
}

.badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 12px;
    font-size: 11px;
    background: #dbeafe;
    color: #1e40af;
}

.read-badge {
    background: #e5e7eb;
    color: #475467;
}

.btn {
    display: inline-block;
    padding: 8px 12px;
    border: 0;
    border-radius: 5px;
    background: #218653;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

.btn-blue {
    background: #1f5e9c;
}

.notice {
    padding: 12px;
    background: #d1fadf;
    color: #05603a;
    border-radius: 5px;
    margin-bottom: 15px;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #667085;
}

.summary {
    background: #f1f4f8;
    padding: 14px;
    border-radius: 6px;
    margin-bottom: 20px;
}

form {
    display: inline-block;
}

</style>

</head>

<body>

<div class="container">

    <div class="navigation">

        <a href="dashboard.php">Dashboard</a>

        <a href="manage_leads.php">Manage Leads</a>

    </div>

    <div class="panel">

        <h1>My Notifications</h1>

        <div class="summary">
            Total Unread Notifications:
            <strong><?php echo $unreadCount; ?></strong>
        </div>

        <?php if ($message !== ""): ?>

            <div class="notice">
                <?php echo h($message); ?>
            </div>

        <?php endif; ?>

        <?php if (!$notifications): ?>

            <div class="empty">
                You do not have any notifications yet.
            </div>

        <?php else: ?>

            <?php foreach ($notifications as $notification): ?>

                <div class="notification <?php echo ((int)$notification["is_read"] === 0) ? "unread" : ""; ?>">

                    <h3>
                        <?php echo h($notification["title"]); ?>

                        <?php if ((int)$notification["is_read"] === 0): ?>

                            <span class="badge">Unread</span>

                        <?php else: ?>

                            <span class="badge read-badge">Read</span>

                        <?php endif; ?>

                    </h3>

                    <p>
                        <?php echo nl2br(h($notification["message"])); ?>
                    </p>

                    <p class="meta">
                        Received:
                        <?php echo h($notification["created_at"]); ?>
                    </p>

                    <?php if (!empty($notification["lead_id"])): ?>

                        <a class="btn btn-blue"
                           href="view_lead.php?id=<?php echo (int)$notification["lead_id"]; ?>">
                            Open Lead
                        </a>

                    <?php endif; ?>

                    <?php if ((int)$notification["is_read"] === 0): ?>

                        <form method="post">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?php echo h($csrfToken); ?>">

                            <input type="hidden"
                                   name="notification_id"
                                   value="<?php echo (int)$notification["id"]; ?>">

                            <button type="submit" class="btn">
                                Mark as Read
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
