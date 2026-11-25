
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("manage_site_visits");

$adminId = (int) ($_SESSION["admin_id"] ?? 0);
$currentRole = $_SESSION["admin_role"] ?? "";

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// --------------------------------------------------
// ROLE-BASED ACCESS
// --------------------------------------------------

$scopeSql = "";
$params = [];

if ($currentRole === "sales_employee") {
    $scopeSql = "
        AND sv.assigned_to = ?
        AND l.assigned_to = ?
    ";

    $params = [$adminId, $adminId];
}

// --------------------------------------------------
// EMPLOYEE-WISE SITE VISIT REPORT
// --------------------------------------------------

$sql = "
    SELECT
        a.id AS employee_id,
        COALESCE(a.name, 'Unassigned') AS employee_name,

        COUNT(sv.id) AS total_visits,

        SUM(CASE
            WHEN sv.visit_status = 'scheduled' THEN 1
            ELSE 0
        END) AS scheduled_visits,

        SUM(CASE
            WHEN sv.visit_status = 'completed' THEN 1
            ELSE 0
        END) AS completed_visits,

        SUM(CASE
            WHEN sv.visit_status = 'cancelled' THEN 1
            ELSE 0
        END) AS cancelled_visits,

        SUM(CASE
            WHEN sv.visit_status = 'rescheduled' THEN 1
            ELSE 0
        END) AS rescheduled_visits,

        SUM(CASE
            WHEN sv.visit_status IN ('scheduled', 'rescheduled')
             AND sv.scheduled_at >= NOW()
            THEN 1
            ELSE 0
        END) AS upcoming_visits,

        SUM(CASE
            WHEN sv.visit_status IN ('scheduled', 'rescheduled')
             AND sv.scheduled_at < NOW()
            THEN 1
            ELSE 0
        END) AS overdue_visits

    FROM site_visits sv

    INNER JOIN leads l
        ON sv.lead_id = l.id

    LEFT JOIN admins a
        ON sv.assigned_to = a.id

    WHERE 1 = 1

    $scopeSql

    GROUP BY a.id, a.name

    ORDER BY total_visits DESC, employee_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// OVERALL TOTALS
// --------------------------------------------------

$totals = [
    "total" => 0,
    "scheduled" => 0,
    "completed" => 0,
    "cancelled" => 0,
    "rescheduled" => 0,
    "upcoming" => 0,
    "overdue" => 0
];

foreach ($employees as $employee) {
    $totals["total"] += (int) $employee["total_visits"];
    $totals["scheduled"] += (int) $employee["scheduled_visits"];
    $totals["completed"] += (int) $employee["completed_visits"];
    $totals["cancelled"] += (int) $employee["cancelled_visits"];
    $totals["rescheduled"] += (int) $employee["rescheduled_visits"];
    $totals["upcoming"] += (int) $employee["upcoming_visits"];
    $totals["overdue"] += (int) $employee["overdue_visits"];
}

// --------------------------------------------------
// UPCOMING VISITS
// --------------------------------------------------

$upcomingSql = "
    SELECT
        sv.id AS visit_id,
        sv.scheduled_at,
        sv.visit_status,
        l.customer_name,
        l.phone,
        l.property_type,
        l.interested_location,
        a.name AS employee_name

    FROM site_visits sv

    INNER JOIN leads l
        ON sv.lead_id = l.id

    LEFT JOIN admins a
        ON sv.assigned_to = a.id

    WHERE sv.visit_status IN ('scheduled', 'rescheduled')
      AND sv.scheduled_at >= NOW()

    $scopeSql

    ORDER BY sv.scheduled_at ASC
";

$upcomingStmt = $pdo->prepare($upcomingSql);
$upcomingStmt->execute($params);
$upcomingVisits = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// OVERDUE VISITS
// --------------------------------------------------

$overdueSql = "
    SELECT
        sv.id AS visit_id,
        sv.scheduled_at,
        sv.visit_status,
        l.customer_name,
        l.phone,
        l.property_type,
        l.interested_location,
        a.name AS employee_name

    FROM site_visits sv

    INNER JOIN leads l
        ON sv.lead_id = l.id

    LEFT JOIN admins a
        ON sv.assigned_to = a.id

    WHERE sv.visit_status IN ('scheduled', 'rescheduled')
      AND sv.scheduled_at < NOW()

    $scopeSql

    ORDER BY sv.scheduled_at ASC
";

$overdueStmt = $pdo->prepare($overdueSql);
$overdueStmt->execute($params);
$overdueVisits = $overdueStmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// COMPLETION RATE
// --------------------------------------------------

$completionRate = $totals["total"] > 0
    ? round(
        ($totals["completed"] / $totals["total"]) * 100,
        2
    )
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Employee-wise Site Visit Reports</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
    padding: 20px;
    color: #333;
}

.container {
    max-width: 1400px;
    margin: auto;
}

h1, h2 {
    color: #222;
}

.navigation {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 25px;
}

.navigation a {
    background: #34495e;
    color: white;
    padding: 10px 14px;
    text-decoration: none;
    border-radius: 5px;
    font-size: 14px;
}

.navigation a:hover {
    background: #22313f;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.stat-card h3 {
    color: #666;
    font-size: 14px;
    margin-top: 0;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
}

.blue { color: #2980b9; }
.green { color: #27ae60; }
.red { color: #c0392b; }
.purple { color: #8e44ad; }
.orange { color: #e67e22; }

.section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: left;
    font-size: 14px;
    vertical-align: top;
}

th {
    background: #34495e;
    color: white;
}

tr:hover {
    background: #f8f9fa;
}

.empty-message {
    padding: 20px;
    text-align: center;
    background: #f8f9fa;
    border-radius: 5px;
    color: #666;
}

.status {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.status-scheduled {
    background: #d6eaf8;
    color: #21618c;
}

.status-rescheduled {
    background: #e8daef;
    color: #6c3483;
}

@media(max-width: 600px) {
    body {
        padding: 10px;
    }

    .stat-card {
        padding: 15px;
    }
}

</style>

</head>

<body>

<div class="container">

<h1>Employee-wise Site Visit Reports</h1>

<p>
View employee performance, visit completion,
upcoming visits and overdue visits.
</p>

<!-- NAVIGATION -->

<div class="navigation">

<a href="dashboard.php">Dashboard</a>

<a href="site_visits.php">Monthly Calendar</a>

<a href="weekly_site_visits.php">Weekly Schedule</a>

<a href="reschedule_site_visits.php">Reschedule Visits</a>

<a href="site_visit_analytics.php">Performance Analytics</a>

<a href="site_visit_cancellation_history.php">Cancellation History</a>

<a href="manage_leads.php">Manage Leads</a>

</div>

<!-- OVERALL SUMMARY -->

<h2>Overall Employee Visit Summary</h2>

<div class="stats-grid">

<div class="stat-card">
<h3>Total Visits</h3>
<div class="stat-number blue">
<?php echo $totals["total"]; ?>
</div>
</div>

<div class="stat-card">
<h3>Scheduled</h3>
<div class="stat-number blue">
<?php echo $totals["scheduled"]; ?>
</div>
</div>

<div class="stat-card">
<h3>Completed</h3>
<div class="stat-number green">
<?php echo $totals["completed"]; ?>
</div>
</div>

<div class="stat-card">
<h3>Cancelled</h3>
<div class="stat-number red">
<?php echo $totals["cancelled"]; ?>
</div>
</div>

<div class="stat-card">
<h3>Rescheduled</h3>
<div class="stat-number purple">
<?php echo $totals["rescheduled"]; ?>
</div>
</div>

<div class="stat-card">
<h3>Upcoming Visits</h3>
<div class="stat-number blue">
<?php echo $totals["upcoming"]; ?>
</div>
</div>

<div class="stat-card">
<h3>Overdue Visits</h3>
<div class="stat-number red">
<?php echo $totals["overdue"]; ?>
</div>
</div>

<div class="stat-card">
<h3>Completion Rate</h3>
<div class="stat-number green">
<?php echo $completionRate; ?>%
</div>
</div>

</div>

<!-- EMPLOYEE PERFORMANCE -->

<div class="section">

<h2>Employee-wise Performance</h2>

<?php if (count($employees) > 0): ?>

<div class="table-wrapper">

<table>

<thead>

<tr>
<th>Sales Employee</th>
<th>Total Visits</th>
<th>Scheduled</th>
<th>Completed</th>
<th>Cancelled</th>
<th>Rescheduled</th>
<th>Upcoming</th>
<th>Overdue</th>
<th>Completion Rate</th>
</tr>

</thead>

<tbody>

<?php foreach ($employees as $employee): ?>

<?php

$total = (int) $employee["total_visits"];
$completed = (int) $employee["completed_visits"];

$rate = $total > 0
    ? round(($completed / $total) * 100, 2)
    : 0;

?>

<tr>

<td><?php echo e($employee["employee_name"]); ?></td>

<td><?php echo $total; ?></td>

<td><?php echo (int) $employee["scheduled_visits"]; ?></td>

<td><?php echo $completed; ?></td>

<td><?php echo (int) $employee["cancelled_visits"]; ?></td>

<td><?php echo (int) $employee["rescheduled_visits"]; ?></td>

<td><?php echo (int) $employee["upcoming_visits"]; ?></td>

<td><?php echo (int) $employee["overdue_visits"]; ?></td>

<td><?php echo $rate; ?>%</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="empty-message">
No employee site visit records found.
</div>

<?php endif; ?>

</div>

<!-- UPCOMING VISITS -->

<div class="section">

<h2>Upcoming Site Visits</h2>

<?php if (count($upcomingVisits) > 0): ?>

<div class="table-wrapper">

<table>

<thead>

<tr>
<th>Visit ID</th>
<th>Customer</th>
<th>Phone</th>
<th>Property</th>
<th>Location</th>
<th>Employee</th>
<th>Scheduled Date & Time</th>
<th>Status</th>
</tr>

</thead>

<tbody>

<?php foreach ($upcomingVisits as $visit): ?>

<tr>

<td><?php echo (int) $visit["visit_id"]; ?></td>

<td><?php echo e($visit["customer_name"]); ?></td>

<td><?php echo e($visit["phone"]); ?></td>

<td><?php echo e($visit["property_type"]); ?></td>

<td><?php echo e($visit["interested_location"]); ?></td>

<td><?php echo e($visit["employee_name"] ?: "Unassigned"); ?></td>

<td>
<?php
echo e(date(
    "d M Y, h:i A",
    strtotime($visit["scheduled_at"])
));
?>
</td>

<td>
<span class="status status-<?php echo e($visit["visit_status"]); ?>">
<?php echo e(ucfirst($visit["visit_status"])); ?>
</span>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="empty-message">
No upcoming site visits found.
</div>

<?php endif; ?>

</div>

<!-- OVERDUE VISITS -->

<div class="section">

<h2>Overdue Site Visits</h2>

<p>
These visits have a scheduled date and time in the past,
but their status is still Scheduled or Rescheduled.
</p>

<?php if (count($overdueVisits) > 0): ?>

<div class="table-wrapper">

<table>

<thead>

<tr>
<th>Visit ID</th>
<th>Customer</th>
<th>Phone</th>
<th>Property</th>
<th>Location</th>
<th>Employee</th>
<th>Scheduled Date & Time</th>
<th>Status</th>
</tr>

</thead>

<tbody>

<?php foreach ($overdueVisits as $visit): ?>

<tr>

<td><?php echo (int) $visit["visit_id"]; ?></td>

<td><?php echo e($visit["customer_name"]); ?></td>

<td><?php echo e($visit["phone"]); ?></td>

<td><?php echo e($visit["property_type"]); ?></td>

<td><?php echo e($visit["interested_location"]); ?></td>

<td><?php echo e($visit["employee_name"] ?: "Unassigned"); ?></td>

<td>
<?php
echo e(date(
    "d M Y, h:i A",
    strtotime($visit["scheduled_at"])
));
?>
</td>

<td>
<span class="status status-<?php echo e($visit["visit_status"]); ?>">
<?php echo e(ucfirst($visit["visit_status"])); ?>
</span>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="empty-message">
No overdue site visits found.
</div>

<?php endif; ?>

</div>

</div>

</body>
</html>
