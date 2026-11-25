
<?php

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

// --------------------------------------------------
// ACCESS CONTROL
// --------------------------------------------------

requirePermission("manage_admins");

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
// OUTPUT ESCAPING
// --------------------------------------------------

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// --------------------------------------------------
// FETCH LATEST 100 ACTIVITY RECORDS
// --------------------------------------------------

$stmt = $pdo->prepare("
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

$stmt->execute();

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalLogs = count($logs);
$adminName = $_SESSION["admin_name"] ?? "Administrator";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Activity Logs | Admin Control Panel</title>

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
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-content {
            min-height: 100vh;
            padding: 30px;
        }

        .activity-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 10px 40px;
        }

        /* HEADER */

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .activity-header h1 {
            font-size: 28px;
            color: var(--navy);
            margin: 0 0 8px;
            font-weight: 700;
        }

        .activity-header p {
            color: var(--muted);
            margin: 0;
            font-size: 14px;
            line-height: 1.7;
        }

        .activity-back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 19px;
            background: var(--navy);
            color: #ffffff;
            text-decoration: none;
            border: 1px solid var(--navy);
            border-radius: 7px;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .activity-back-btn:hover {
            background: var(--navy-light);
            border-color: var(--navy-light);
            color: #ffffff;
        }

        /* SUMMARY */

        .activity-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .activity-summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 4px solid var(--gold);
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(7, 27, 56, 0.04);
        }

        .activity-summary-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .activity-summary-number {
            color: var(--navy);
            font-size: 30px;
            font-weight: 700;
        }

        /* ACTIVITY CARD */

        .activity-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(7, 27, 56, 0.04);
            overflow: hidden;
        }

        .activity-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding: 22px 24px;
            border-bottom: 1px solid var(--border);
        }

        .activity-card-header h2 {
            color: var(--navy);
            font-size: 19px;
            margin: 0 0 5px;
        }

        .activity-card-header p {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .activity-count {
            display: inline-block;
            padding: 7px 12px;
            background: var(--ivory);
            color: var(--navy);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        /* TABLE */

        .activity-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .activity-table th {
            background: var(--ivory);
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
            text-align: left;
            padding: 15px 18px;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .activity-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #f1eee7;
            color: var(--text);
            font-size: 13px;
            vertical-align: top;
            line-height: 1.6;
        }

        .activity-table tbody tr:hover {
            background: #fcfaf5;
        }

        .activity-table tbody tr:last-child td {
            border-bottom: none;
        }

        .activity-id {
            color: var(--muted);
            font-weight: 700;
        }

        .activity-admin {
            color: var(--navy);
            font-weight: 600;
        }

        .activity-action {
            display: inline-block;
            padding: 5px 10px;
            background: #f8f1df;
            color: #765719;
            border: 1px solid #eddfbd;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .activity-description {
            max-width: 400px;
            overflow-wrap: anywhere;
            color: #475569;
        }

        .activity-date {
            white-space: nowrap;
            color: var(--muted);
            font-size: 12px;
        }

        .activity-empty {
            text-align: center;
            padding: 55px 20px !important;
            color: var(--muted) !important;
        }

        .activity-empty strong {
            display: block;
            color: var(--navy);
            font-size: 16px;
            margin-bottom: 7px;
        }

        /* RESPONSIVE */

        @media (max-width: 850px) {

            .main-content {
                padding: 22px;
            }

        }

        @media (max-width: 600px) {

            .main-content {
                padding: 15px;
            }

            .activity-page {
                padding: 10px 5px 30px;
            }

            .activity-header h1 {
                font-size: 23px;
            }

            .activity-card-header {
                padding: 18px;
            }

            .activity-summary-card {
                padding: 18px;
            }

            .activity-back-btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

    <main class="main-content">

        <div class="activity-page">

            <div class="activity-header">

                <div>

                    <h1>Admin Activity Logs</h1>

                    <p>
                        Welcome back, <?php echo e($adminName); ?>.
                        Monitor administrative activities across your website.
                    </p>

                </div>

                <a href="dashboard.php" class="activity-back-btn">
                    Back to Dashboard
                </a>

            </div>

            <div class="activity-summary">

                <div class="activity-summary-card">

                    <div class="activity-summary-label">
                        Activity Records Displayed
                    </div>

                    <div class="activity-summary-number">
                        <?php echo (int)$totalLogs; ?>
                    </div>

                </div>

            </div>

            <div class="activity-card">

                <div class="activity-card-header">

                    <div>

                        <h2>Activity History</h2>

                        <p>
                            Review the latest administrative actions.
                        </p>

                    </div>

                    <span class="activity-count">
                        Latest 100 Records
                    </span>

                </div>

                <div class="activity-table-wrapper">

                    <table class="activity-table">

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

                            <?php if ($totalLogs > 0): ?>

                                <?php foreach ($logs as $log): ?>

                                    <tr>

                                        <td class="activity-id">
                                            #<?php echo (int)$log["id"]; ?>
                                        </td>

                                        <td class="activity-admin">

                                            <?php
                                            echo $log["admin_name"] !== null
                                                ? e($log["admin_name"])
                                                : "Deleted / Unknown Admin";
                                            ?>

                                        </td>

                                        <td>
                                            <span class="activity-action">
                                                <?php echo e($log["action"]); ?>
                                            </span>
                                        </td>

                                        <td class="activity-description">
                                            <?php echo e($log["description"] ?? ""); ?>
                                        </td>

                                        <td class="activity-date">
                                            <?php echo e($log["created_at"]); ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="5" class="activity-empty">

                                        <strong>No Activity Records Found</strong>

                                        Administrative activities will appear here.

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

</body>
</html>
