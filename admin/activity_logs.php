
<?php

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

requirePermission("manage_admins");

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// --------------------------------------------------
// HELPER
// --------------------------------------------------

function activityLogEscape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

$adminName = $_SESSION["admin_name"] ?? "Administrator";

$message = "";

// --------------------------------------------------
// FETCH LATEST 100 ACTIVITY LOGS
// --------------------------------------------------

$activities = [];

try {

    $stmt = $pdo->query("
        SELECT
            activity_logs.id,
            activity_logs.admin_id,
            activity_logs.action,
            activity_logs.description,
            activity_logs.created_at,
            admins.name AS admin_name
        FROM activity_logs
        LEFT JOIN admins
            ON activity_logs.admin_id = admins.id
        ORDER BY activity_logs.id DESC
        LIMIT 100
    ");

    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        "Activity logs loading failed: " . $e->getMessage()
    );

    $message = "Unable to load activity records. Please try again later.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Activity Log | Olivera Home Developers</title>

    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">

    <style>

        :root {
            --navy: #071b38;
            --navy-light: #102b50;
            --gold: #d6a63b;
            --gold-light: #edca76;
            --ivory: #f7f5ef;
            --white: #ffffff;
            --text: #263449;
            --muted: #718096;
            --border: #e5e7eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--ivory);
            color: var(--text);
        }

        /* MAIN CONTENT */

        .main-content {
            margin-left: 255px;
            min-height: 100vh;
            padding: 30px;
            min-width: 0;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            color: var(--navy);
            font-size: 27px;
            margin: 0 0 7px;
        }

        .page-heading p {
            color: var(--muted);
            margin: 0;
            font-size: 13px;
        }

        .admin-user {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 16px;
            color: var(--navy);
            font-size: 13px;
            font-weight: bold;
        }

        /* CONTENT CARD */

        .content-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 18px rgba(7, 27, 56, 0.04);
        }

        .card-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .card-heading h2 {
            color: var(--navy);
            font-size: 18px;
            margin: 0;
        }

        .record-count {
            background: #fdf6e5;
            color: var(--navy);
            border: 1px solid #f0dfb6;
            border-radius: 20px;
            padding: 7px 13px;
            font-size: 12px;
            font-weight: bold;
        }

        /* MESSAGES */

        .message {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            border: 1px solid #efcfca;
            background: #fbefed;
            color: #a33128;
        }

        /* TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        thead {
            background: var(--navy);
            color: var(--white);
        }

        th {
            padding: 14px 13px;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        td {
            padding: 14px 13px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            vertical-align: top;
            line-height: 1.5;
        }

        tbody tr:hover {
            background: #fcfaf5;
        }

        .log-id {
            color: var(--muted);
            font-weight: bold;
        }

        .admin-name {
            color: var(--navy);
            font-weight: bold;
        }

        .action-name {
            display: inline-block;
            background: #fdf6e5;
            color: #795a17;
            border: 1px solid #f0dfb6;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
        }

        .description {
            max-width: 350px;
            overflow-wrap: anywhere;
        }

        .date-time {
            color: var(--muted);
            white-space: nowrap;
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 45px 15px;
            color: var(--muted);
            font-size: 14px;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .main-content {
                margin-left: 220px;
                padding: 25px;
            }
        }

        @media (max-width: 700px) {

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            .page-heading h1 {
                font-size: 23px;
            }

            .content-card {
                padding: 16px;
            }
        }

        @media (max-width: 500px) {

            .main-content {
                padding: 15px;
            }

            .page-heading {
                align-items: flex-start;
            }

            .admin-user {
                width: 100%;
            }
        }

    </style>

</head>

<body>

    <!-- SHARED ADMIN SIDEBAR -->

    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="page-heading">

            <div>
                <h1>Activity Log</h1>

                <p>
                    Monitor recent actions performed by administrators.
                </p>
            </div>

            <div class="admin-user">
                <?php echo activityLogEscape($adminName); ?>
            </div>

        </div>

        <!-- ERROR MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="message" role="alert">
                <?php echo activityLogEscape($message); ?>
            </div>

        <?php endif; ?>

        <!-- ACTIVITY RECORDS -->

        <section class="content-card">

            <div class="card-heading">

                <h2>Recent Activity Records</h2>

                <span class="record-count">
                    <?php echo count($activities); ?> Records
                </span>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Date and Time</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (!empty($activities)): ?>

                            <?php foreach ($activities as $activity): ?>

                                <tr>

                                    <td class="log-id">
                                        #<?php echo (int)$activity["id"]; ?>
                                    </td>

                                    <td class="admin-name">

                                        <?php
                                        echo activityLogEscape(
                                            $activity["admin_name"] ?: "Deleted / Unknown Admin"
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <span class="action-name">
                                            <?php
                                            echo activityLogEscape(
                                                $activity["action"]
                                            );
                                            ?>
                                        </span>

                                    </td>

                                    <td class="description">
                                        <?php
                                        echo activityLogEscape(
                                            $activity["description"]
                                        );
                                        ?>
                                    </td>

                                    <td class="date-time">
                                        <?php
                                        echo activityLogEscape(
                                            $activity["created_at"]
                                        );
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="5" class="empty-state">
                                    No activity records found.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</body>

</html>
