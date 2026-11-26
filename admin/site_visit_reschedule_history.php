
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

$adminId = (int) ($_SESSION["admin_id"] ?? 0);
$canViewAll = hasPermission("view_all_leads");

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function formatHistoryDate($value, $format = "d-m-Y h:i A") {

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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Site Visit Rescheduling History | Olivera Home Developers</title>

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
            font-family: Arial, Helvetica, sans-serif;
            background: var(--ivory);
            margin: 0;
            padding: 30px;
            color: var(--text);
        }

        .container {
            max-width: 1450px;
            margin: auto;
        }

        .page-header {
            margin-bottom: 24px;
        }

        h1, h2 {
            color: var(--navy);
        }

        h1 {
            font-size: 28px;
            margin: 0 0 9px;
        }

        .page-header p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-bottom: 22px;
        }

        .nav-links a {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--navy);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .nav-links a:hover {
            background: #f0e7d1;
            border-color: var(--gold);
        }

        .card {
            background: var(--surface);
            padding: 24px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(7, 27, 56, 0.04);
        }

        h2 {
            font-size: 19px;
            margin: 0 0 20px;
        }

        .summary {
            background: var(--navy);
            color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 5px solid var(--gold);
        }

        .summary-label {
            font-size: 13px;
            color: var(--gold-light);
            font-weight: 600;
        }

        .summary strong {
            display: block;
            font-size: 30px;
            color: #ffffff;
            margin-top: 7px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #f0ede6;
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }

        th {
            background: var(--ivory);
            color: var(--navy);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fcfaf5;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .property-details {
            line-height: 1.7;
        }

        .property-details strong {
            display: block;
            color: var(--navy);
        }

        .date-value {
            font-weight: bold;
            color: var(--text);
        }

        .new-date {
            color: #216b32;
            font-weight: bold;
        }

        .old-date {
            color: #80540b;
            font-weight: bold;
        }

        .description {
            max-width: 300px;
            line-height: 1.6;
            color: var(--muted);
        }

        .empty {
            color: var(--muted);
            text-align: center;
            padding: 30px 15px;
        }

        @media (max-width: 700px) {

            body {
                padding: 15px;
            }

            .card {
                padding: 16px;
            }

            h1 {
                font-size: 23px;
            }

            .nav-links a {
                padding: 9px 11px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <header class="page-header">

        <h1>Site Visit Rescheduling History</h1>

        <p>
            View the previous and updated schedules of rescheduled site visits.
        </p>

    </header>

    <nav class="nav-links">

        <a href="dashboard.php">Dashboard</a>

        <a href="site_visits.php">Monthly Site Visit Calendar</a>

        <a href="weekly_site_visits.php">Weekly Site Visit Schedule</a>

        <a href="reschedule_site_visits.php">Reschedule Site Visits</a>

        <a href="manage_leads.php">Manage Leads</a>

    </nav>

    <section class="card">

        <div class="summary">

            <div class="summary-label">
                Total Rescheduling Records
            </div>

            <strong>
                <?php echo (int) $totalReschedules; ?>
            </strong>

        </div>

        <h2>Rescheduling Records</h2>

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

                                <td>
                                    #<?php echo (int) $record["activity_id"]; ?>
                                </td>

                                <td>

                                    <strong>
                                        <?php echo e($record["customer_name"]); ?>
                                    </strong>

                                    <br>

                                    <small>
                                        Lead ID: <?php echo (int) $record["lead_id"]; ?>
                                    </small>

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
                                        formatHistoryDate($record["old_value"] ?? null)
                                    );
                                    ?>
                                </td>

                                <td class="new-date">
                                    <?php
                                    echo e(
                                        formatHistoryDate($record["new_value"] ?? null)
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo e(
                                        $record["changed_by"] ??
                                        "Deleted User"
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo e(
                                        formatHistoryDate($record["created_at"] ?? null)
                                    );
                                    ?>
                                </td>

                                <td class="description">
                                    <?php
                                    echo e($record["description"]);
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">
                No site visit rescheduling records found.
            </p>

        <?php endif; ?>

    </section>

</div>

</body>
</html>
