
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("manage_site_visits");

$adminId = (int) $_SESSION["admin_id"];
$canViewAll = hasPermission("view_all_leads");

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Get rescheduling history.
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

    <title>Site Visit Rescheduling History</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: auto;
        }

        h1, h2 {
            color: #222;
        }

        .card {
            background: white;
            padding: 22px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }

        .nav-links {
            margin-bottom: 20px;
        }

        .nav-links a {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 8px;
            color: #1769aa;
            text-decoration: none;
            font-weight: bold;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        .summary {
            background: #eef6ff;
            padding: 18px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .summary strong {
            display: block;
            font-size: 28px;
            color: #1769aa;
            margin-top: 5px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f0f2f5;
        }

        .property-details {
            line-height: 1.6;
        }

        .property-details strong {
            display: block;
        }

        .date-value {
            font-weight: bold;
            color: #333;
        }

        .new-date {
            color: #216b32;
            font-weight: bold;
        }

        .old-date {
            color: #80540b;
        }

        .description {
            max-width: 300px;
            line-height: 1.5;
        }

        .empty {
            color: #777;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .card {
                padding: 14px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Site Visit Rescheduling History</h1>

    <p>
        View the previous and updated schedules of rescheduled site visits.
    </p>

    <div class="nav-links">

        <a href="dashboard.php">Dashboard</a>

        <a href="site_visits.php">Monthly Site Visit Calendar</a>

        <a href="weekly_site_visits.php">Weekly Site Visit Schedule</a>

        <a href="reschedule_site_visits.php">Reschedule Site Visits</a>

        <a href="manage_leads.php">Manage Leads</a>

    </div>

    <div class="card">

        <div class="summary">

            Total Rescheduling Records

            <strong><?php echo $totalReschedules; ?></strong>

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
                                    <?php echo (int) $record["activity_id"]; ?>
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

                                    if (!empty($record["old_value"])) {

                                        echo e(
                                            date(
                                                "d-m-Y h:i A",
                                                strtotime($record["old_value"])
                                            )
                                        );

                                    } else {

                                        echo "Not available";

                                    }

                                    ?>

                                </td>

                                <td class="new-date">

                                    <?php

                                    if (!empty($record["new_value"])) {

                                        echo e(
                                            date(
                                                "d-m-Y h:i A",
                                                strtotime($record["new_value"])
                                            )
                                        );

                                    } else {

                                        echo "Not available";

                                    }

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
                                        date(
                                            "d-m-Y h:i A",
                                            strtotime($record["created_at"])
                                        )
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

    </div>

</div>

</body>

</html>
