
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
// PERMISSIONS
// --------------------------------------------------

requirePermission("manage_site_visits");

$adminId = (int)($_SESSION["admin_id"] ?? 0);
$canViewAll = hasPermission("view_all_leads");

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function formatHistoryDate($value, $format = "d-m-Y h:i A"): string
{
    if (!is_string($value) || trim($value) === "") {
        return "Not available";
    }

    try {
        $date = new DateTime($value);
        return $date->format($format);
    } catch (Throwable $exception) {
        return "Not available";
    }
}

// --------------------------------------------------
// FETCH RESCHEDULING HISTORY
// --------------------------------------------------

$history = [];
$totalReschedules = 0;
$loadError = false;

try {

    if ($canViewAll) {

        $historyQuery = $pdo->query("
            SELECT
                lal.id AS activity_id,
                lal.lead_id,
                lal.activity_type,
                lal.description,
                lal.old_value,
                lal.new_value,
                lal.created_at,
                l.customer_name,
                l.phone,
                l.property_type,
                l.interested_location,
                a.name AS changed_by
            FROM lead_activity_logs lal
            INNER JOIN leads l
                ON lal.lead_id = l.id
            LEFT JOIN admins a
                ON lal.admin_id = a.id
            WHERE lal.activity_type = 'site_visit_rescheduled'
            ORDER BY lal.created_at DESC
        ");

    } else {

        $historyQuery = $pdo->prepare("
            SELECT
                lal.id AS activity_id,
                lal.lead_id,
                lal.activity_type,
                lal.description,
                lal.old_value,
                lal.new_value,
                lal.created_at,
                l.customer_name,
                l.phone,
                l.property_type,
                l.interested_location,
                a.name AS changed_by
            FROM lead_activity_logs lal
            INNER JOIN leads l
                ON lal.lead_id = l.id
            LEFT JOIN admins a
                ON lal.admin_id = a.id
            WHERE lal.activity_type = 'site_visit_rescheduled'
            AND l.assigned_to = ?
            ORDER BY lal.created_at DESC
        ");

        $historyQuery->execute([$adminId]);
    }

    $history = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
    $totalReschedules = count($history);

} catch (Throwable $exception) {

    error_log(
        "Site visit rescheduling history error: " .
        $exception->getMessage()
    );

    $loadError = true;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Site Visit Rescheduling History | Olivera Admin</title>

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
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        .main-content {
            min-height: 100vh;
            margin-left: 255px;
            padding: 28px;
        }

        .container {
            width: 100%;
            max-width: 1450px;
            margin: 0 auto;
        }

        /* PAGE HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 25px;
            padding: 25px 28px;
            border: 1px solid var(--navy-light);
            border-radius: 14px;
            background: linear-gradient(
                120deg,
                var(--navy),
                var(--navy-light)
            );
        }

        .page-header h1 {
            margin: 0 0 7px;
            color: #fff;
            font-size: 26px;
            font-weight: 750;
        }

        .page-header p {
            margin: 0;
            color: #d7dfeb;
            font-size: 13px;
        }

        .header-label {
            padding: 10px 15px;
            border: 1px solid rgba(237,202,118,.45);
            border-radius: 8px;
            background: rgba(214,166,59,.12);
            color: var(--gold-light);
            font-size: 12px;
            font-weight: 700;
        }

        /* CONTENT CARD */

        .card {
            margin-bottom: 25px;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 3px 13px rgba(7,27,56,.04);
        }

        .card-heading {
            padding: 22px 25px;
            border-bottom: 1px solid var(--border);
        }

        .card-heading h2 {
            margin: 0 0 5px;
            color: var(--navy);
            font-size: 19px;
        }

        .card-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .card-body {
            padding: 25px;
        }

        /* SUMMARY */

        .summary {
            padding: 21px 23px;
            margin-bottom: 25px;
            border-left: 5px solid var(--gold);
            border-radius: 9px;
            background: var(--navy);
            color: #fff;
        }

        .summary-label {
            color: var(--gold-light);
            font-size: 13px;
            font-weight: 600;
        }

        .summary strong {
            display: block;
            margin-top: 7px;
            color: #fff;
            font-size: 30px;
            font-weight: 750;
        }

        /* TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
            text-align: left;
        }

        th,
        td {
            padding: 14px 13px;
            border-bottom: 1px solid #f0ede6;
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }

        th {
            background: #f3f0e8;
            color: var(--navy);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .4px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fcfaf5;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .record-id {
            color: var(--muted);
            font-weight: 700;
        }

        .customer-name {
            color: var(--navy);
            font-weight: 700;
        }

        .lead-id {
            color: var(--muted);
            font-size: 11px;
        }

        .property-details {
            line-height: 1.7;
        }

        .property-details strong {
            display: block;
            color: var(--navy);
        }

        .old-date {
            color: #80540b;
            font-weight: 700;
            white-space: nowrap;
        }

        .new-date {
            color: #216b32;
            font-weight: 700;
            white-space: nowrap;
        }

        .changed-by {
            color: #465873;
            font-weight: 600;
        }

        .created-date {
            color: var(--muted);
            white-space: nowrap;
        }

        .description {
            max-width: 300px;
            overflow-wrap: anywhere;
            color: var(--muted);
            line-height: 1.6;
        }

        /* EMPTY AND ERROR STATES */

        .empty-state {
            padding: 42px 20px;
            text-align: center;
        }

        .empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            margin: 0 auto 13px;
            border: 1px solid #ead9b4;
            border-radius: 50%;
            background: #fcf6e9;
            color: #80601e;
            font-size: 22px;
        }

        .empty-state h3 {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 16px;
        }

        .empty-state p {
            margin: 0;
            color: #8792a4;
            font-size: 13px;
        }

        .error-message {
            padding: 15px 18px;
            margin-bottom: 20px;
            border: 1px solid #f2cccc;
            border-radius: 9px;
            background: #fff2f2;
            color: #ae3030;
            font-size: 13px;
        }

        /* FOOTER */

        .page-footer {
            padding: 15px 5px;
            color: #96a0af;
            text-align: center;
            font-size: 11px;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .main-content {
                margin-left: 220px;
                padding: 20px;
            }
        }

        @media (max-width: 700px) {

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .page-header {
                align-items: flex-start;
                padding: 19px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .header-label {
                width: 100%;
            }

            .card-heading,
            .card-body {
                padding: 18px;
            }

            .summary {
                padding: 18px;
            }
        }

    </style>

</head>

<body>

    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <main class="main-content">

        <div class="container">

            <!-- PAGE HEADER -->

            <header class="page-header">

                <div>

                    <h1>Site Visit Rescheduling History</h1>

                    <p>
                        View the previous and updated schedules of rescheduled site visits.
                    </p>

                </div>

                <div class="header-label">
                    Site Visit Management
                </div>

            </header>

            <?php if ($loadError): ?>

                <div class="error-message" role="alert">
                    Unable to load rescheduling history. Please try again later.
                </div>

            <?php endif; ?>

            <!-- HISTORY CARD -->

            <section class="card">

                <div class="card-heading">

                    <h2>Rescheduling Records</h2>

                    <p>
                        Review previous dates, updated schedules and the administrator who made each change.
                    </p>

                </div>

                <div class="card-body">

                    <!-- SUMMARY -->

                    <div class="summary">

                        <div class="summary-label">
                            Total Rescheduling Records
                        </div>

                        <strong>
                            <?php echo (int)$totalReschedules; ?>
                        </strong>

                    </div>

                    <?php if ($totalReschedules > 0): ?>

                        <div class="table-wrapper">

                            <table>

                                <thead>

                                    <tr>
                                        <th>Record ID</th>
                                        <th>Customer</th>
                                        <th>Phone</th>
                                        <th>Property Details</th>
                                        <th>Previous Date and Time</th>
                                        <th>New Date and Time</th>
                                        <th>Rescheduled By</th>
                                        <th>Recorded On</th>
                                        <th>Activity Details</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($history as $record): ?>

                                        <tr>

                                            <td class="record-id">
                                                #<?php echo (int)$record["activity_id"]; ?>
                                            </td>

                                            <td>

                                                <strong class="customer-name">
                                                    <?php echo e($record["customer_name"]); ?>
                                                </strong>

                                                <br>

                                                <span class="lead-id">
                                                    Lead ID:
                                                    <?php echo (int)$record["lead_id"]; ?>
                                                </span>

                                            </td>

                                            <td>
                                                <?php echo e($record["phone"]); ?>
                                            </td>

                                            <td class="property-details">

                                                <strong>
                                                    <?php
                                                    echo e(
                                                        $record["property_type"] ??
                                                        "Not specified"
                                                    );
                                                    ?>
                                                </strong>

                                                <?php
                                                echo e(
                                                    $record["interested_location"] ??
                                                    "Location not specified"
                                                );
                                                ?>

                                            </td>

                                            <td class="old-date">
                                                <?php
                                                echo e(
                                                    formatHistoryDate(
                                                        $record["old_value"] ?? null
                                                    )
                                                );
                                                ?>
                                            </td>

                                            <td class="new-date">
                                                <?php
                                                echo e(
                                                    formatHistoryDate(
                                                        $record["new_value"] ?? null
                                                    )
                                                );
                                                ?>
                                            </td>

                                            <td class="changed-by">
                                                <?php
                                                echo e(
                                                    $record["changed_by"] ??
                                                    "Deleted User"
                                                );
                                                ?>
                                            </td>

                                            <td class="created-date">
                                                <?php
                                                echo e(
                                                    formatHistoryDate(
                                                        $record["created_at"] ?? null
                                                    )
                                                );
                                                ?>
                                            </td>

                                            <td class="description">
                                                <?php
                                                echo e(
                                                    $record["description"] ?? ""
                                                );
                                                ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php elseif (!$loadError): ?>

                        <div class="empty-state">

                            <div class="empty-icon">◷</div>

                            <h3>No Rescheduling Records Found</h3>

                            <p>
                                Site visit rescheduling records will appear here when a visit is rescheduled.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <footer class="page-footer">
                Olivera Home Developers — Site Visit Management
            </footer>

        </div>

    </main>

</body>
</html>
