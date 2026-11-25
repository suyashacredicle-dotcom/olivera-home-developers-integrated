
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("manage_site_visits");

$adminId = (int) $_SESSION["admin_id"];
$canViewAll = hasPermission("view_all_leads");

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Validate selected week date.
$requestedWeek = $_GET["week"] ?? date("Y-m-d");

$weekDate = DateTime::createFromFormat("!Y-m-d", $requestedWeek);

if (
    !$weekDate ||
    $weekDate->format("Y-m-d") !== $requestedWeek
) {
    $weekDate = new DateTime("today");
}

// Set week start to Monday.
$weekStart = clone $weekDate;
$weekStart->modify("monday this week");

$weekEnd = clone $weekStart;
$weekEnd->modify("+7 days");

$weekEndDisplay = clone $weekEnd;
$weekEndDisplay->modify("-1 day");

$startDate = $weekStart->format("Y-m-d");
$endDate = $weekEnd->format("Y-m-d");

$previousWeek = clone $weekStart;
$previousWeek->modify("-7 days");

$nextWeek = clone $weekStart;
$nextWeek->modify("+7 days");

$today = date("Y-m-d");

// Get visits for the selected week.
if ($canViewAll) {

    $visitQuery = $pdo->prepare("
        SELECT
            sv.id,
            sv.scheduled_at,
            sv.visit_status,
            sv.notes,
            l.customer_name,
            l.phone,
            l.property_type,
            l.interested_location,
            assigned.name AS assigned_employee
        FROM site_visits sv
        INNER JOIN leads l ON sv.lead_id = l.id
        LEFT JOIN admins assigned ON sv.assigned_to = assigned.id
        WHERE sv.scheduled_at >= ?
        AND sv.scheduled_at < ?
        ORDER BY sv.scheduled_at ASC
    ");

    $visitQuery->execute([
        $weekStart->format("Y-m-d 00:00:00"),
        $weekEnd->format("Y-m-d 00:00:00")
    ]);

} else {

    $visitQuery = $pdo->prepare("
        SELECT
            sv.id,
            sv.scheduled_at,
            sv.visit_status,
            sv.notes,
            l.customer_name,
            l.phone,
            l.property_type,
            l.interested_location,
            assigned.name AS assigned_employee
        FROM site_visits sv
        INNER JOIN leads l ON sv.lead_id = l.id
        LEFT JOIN admins assigned ON sv.assigned_to = assigned.id
        WHERE sv.scheduled_at >= ?
        AND sv.scheduled_at < ?
        AND sv.assigned_to = ?
        AND l.assigned_to = ?
        ORDER BY sv.scheduled_at ASC
    ");

    $visitQuery->execute([
        $weekStart->format("Y-m-d 00:00:00"),
        $weekEnd->format("Y-m-d 00:00:00"),
        $adminId,
        $adminId
    ]);
}

$visits = $visitQuery->fetchAll(PDO::FETCH_ASSOC);

// Group visits by date.
$visitsByDate = [];

foreach ($visits as $visit) {

    $dateKey = date(
        "Y-m-d",
        strtotime($visit["scheduled_at"])
    );

    $visitsByDate[$dateKey][] = $visit;
}

// Weekly status summary.
$statusCounts = [
    "scheduled" => 0,
    "completed" => 0,
    "cancelled" => 0,
    "rescheduled" => 0
];

foreach ($visits as $visit) {

    $status = $visit["visit_status"];

    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

$totalVisits = count($visits);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Weekly Site Visit Schedule</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #333;
        }

        .container {
            max-width: 1350px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 22px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }

        h1, h2, h3 {
            color: #222;
        }

        .navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .navigation a {
            display: inline-block;
            padding: 10px 14px;
            background: #1769aa;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .navigation a:hover {
            background: #10548b;
        }

        .navigation .current {
            background: #555;
        }

        .date-form {
            display: flex;
            align-items: end;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .date-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .date-form input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            padding: 10px 18px;
            background: #1769aa;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #10548b;
        }

        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0;
        }

        .summary-item {
            flex: 1;
            min-width: 130px;
            padding: 15px;
            background: #f4f6f9;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .summary-item strong {
            display: block;
            font-size: 23px;
            margin-top: 7px;
            color: #1769aa;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
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

        .day-heading td {
            background: #eaf2fb;
            font-weight: bold;
            color: #174b7a;
            font-size: 15px;
        }

        .today-heading td {
            background: #dcecff;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            background: #e5f1ff;
            color: #174b7a;
        }

        .status.completed {
            background: #e5f5e9;
            color: #216b32;
        }

        .status.cancelled {
            background: #fde8e8;
            color: #a32222;
        }

        .status.rescheduled {
            background: #fff3dc;
            color: #80540b;
        }

        .property-details strong {
            display: block;
            margin-bottom: 4px;
        }

        .empty {
            color: #777;
            padding: 15px 0;
        }

        .today-label {
            color: #1769aa;
            font-weight: bold;
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

    <h1>Weekly Site Visit Schedule</h1>

    <p>View all site visits scheduled from Monday to Sunday.</p>

    <div class="navigation">

        <a href="site_visits.php">
            Monthly Calendar
        </a>

        <a href="site_visits.php?day=<?php echo e($today); ?>#daily-schedule">
            Daily Schedule
        </a>

        <a href="dashboard.php">
            Dashboard
        </a>

    </div>

    <div class="card">

        <h2>Select Week</h2>

        <div class="navigation">

            <a href="weekly_site_visits.php?week=<?php echo e($previousWeek->format("Y-m-d")); ?>">
                &laquo; Previous Week
            </a>

            <a href="weekly_site_visits.php" class="current">
                Current Week
            </a>

            <a href="weekly_site_visits.php?week=<?php echo e($nextWeek->format("Y-m-d")); ?>">
                Next Week &raquo;
            </a>

        </div>

        <form method="GET" action="weekly_site_visits.php" class="date-form">

            <div>

                <label for="week">Choose a date in the week</label>

                <input
                    type="date"
                    id="week"
                    name="week"
                    value="<?php echo e($startDate); ?>"
                    required
                >

            </div>

            <button type="submit">
                View Week
            </button>

        </form>

        <h2>
            <?php echo e($weekStart->format("d M Y")); ?>
            -
            <?php echo e($weekEndDisplay->format("d M Y")); ?>
        </h2>

        <div class="summary">

            <div class="summary-item">
                Total Visits
                <strong><?php echo $totalVisits; ?></strong>
            </div>

            <div class="summary-item">
                Scheduled
                <strong><?php echo $statusCounts["scheduled"]; ?></strong>
            </div>

            <div class="summary-item">
                Completed
                <strong><?php echo $statusCounts["completed"]; ?></strong>
            </div>

            <div class="summary-item">
                Cancelled
                <strong><?php echo $statusCounts["cancelled"]; ?></strong>
            </div>

            <div class="summary-item">
                Rescheduled
                <strong><?php echo $statusCounts["rescheduled"]; ?></strong>
            </div>

        </div>

        <h2>Weekly Visit Details</h2>

        <?php if ($totalVisits > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Visit ID</th>
                            <th>Visit Time</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Property Details</th>
                            <th>Assigned Employee</th>
                            <th>Status</th>
                            <th>Visit Notes</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php

                    $currentDay = clone $weekStart;

                    while ($currentDay < $weekEnd):

                        $dateKey = $currentDay->format("Y-m-d");

                        $isToday = $dateKey === $today;

                        $dayVisits = $visitsByDate[$dateKey] ?? [];

                    ?>

                        <tr class="day-heading <?php echo $isToday ? 'today-heading' : ''; ?>">

                            <td colspan="8">

                                <?php echo e($currentDay->format("l, d F Y")); ?>

                                <?php if ($isToday): ?>
                                    <span class="today-label"> - TODAY</span>
                                <?php endif; ?>

                                (<?php echo count($dayVisits); ?> visit(s))

                            </td>

                        </tr>

                        <?php if (count($dayVisits) > 0): ?>

                            <?php foreach ($dayVisits as $visit): ?>

                                <tr>

                                    <td>
                                        <?php echo (int) $visit["id"]; ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?php
                                            echo e(
                                                date(
                                                    "h:i A",
                                                    strtotime($visit["scheduled_at"])
                                                )
                                            );
                                            ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php echo e($visit["customer_name"]); ?>
                                    </td>

                                    <td>
                                        <?php echo e($visit["phone"]); ?>
                                    </td>

                                    <td class="property-details">

                                        <strong>
                                            <?php echo e($visit["property_type"] ?? "Not specified"); ?>
                                        </strong>

                                        <?php echo e($visit["interested_location"] ?? "Location not specified"); ?>

                                    </td>

                                    <td>
                                        <?php echo e($visit["assigned_employee"] ?? "Not Assigned"); ?>
                                    </td>

                                    <td>

                                        <span class="status <?php echo e($visit["visit_status"]); ?>">

                                            <?php
                                            echo e(
                                                ucwords(
                                                    str_replace(
                                                        "_",
                                                        " ",
                                                        $visit["visit_status"]
                                                    )
                                                )
                                            );
                                            ?>

                                        </span>

                                    </td>

                                    <td>
                                        <?php
                                        echo !empty($visit["notes"])
                                            ? nl2br(e($visit["notes"]))
                                            : "No notes";
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8" class="empty">
                                    No site visits scheduled for this day.
                                </td>
                            </tr>

                        <?php endif; ?>

                    <?php

                        $currentDay->modify("+1 day");

                    endwhile;

                    ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">
                No site visits are scheduled for this week.
            </p>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

