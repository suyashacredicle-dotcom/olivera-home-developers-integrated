
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
// INCLUDES AND PERMISSIONS
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

requirePermission("manage_site_visits");

$adminId = (int)($_SESSION["admin_id"] ?? 0);
$canViewAll = hasPermission("view_all_leads");

$canManageUsers = hasPermission("manage_users");
$canManageAdmins = hasPermission("manage_admins");
$canViewActivity = hasPermission("view_activity_log");
$canManageLeads = hasPermission("manage_leads");
$canManagePartnerships = hasPermission("manage_partnerships");
$canManageFollowups = hasPermission("manage_followups");
$canViewReports = hasPermission("view_crm_reports");

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// --------------------------------------------------
// WEEK VALIDATION
// --------------------------------------------------

$requestedWeek = $_GET["week"] ?? date("Y-m-d");

$weekDate = DateTime::createFromFormat("!Y-m-d", $requestedWeek);

if (
    !$weekDate ||
    $weekDate->format("Y-m-d") !== $requestedWeek
) {
    $weekDate = new DateTime("today");
}

$weekStart = clone $weekDate;
$weekStart->modify("monday this week");

$weekEnd = clone $weekStart;
$weekEnd->modify("+7 days");

$weekEndDisplay = clone $weekEnd;
$weekEndDisplay->modify("-1 day");

$startDate = $weekStart->format("Y-m-d");

$previousWeek = clone $weekStart;
$previousWeek->modify("-7 days");

$nextWeek = clone $weekStart;
$nextWeek->modify("+7 days");

$today = date("Y-m-d");

// --------------------------------------------------
// GET WEEKLY VISITS
// --------------------------------------------------

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

        INNER JOIN leads l
            ON sv.lead_id = l.id

        LEFT JOIN admins assigned
            ON sv.assigned_to = assigned.id

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

        INNER JOIN leads l
            ON sv.lead_id = l.id

        LEFT JOIN admins assigned
            ON sv.assigned_to = assigned.id

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

// --------------------------------------------------
// GROUP VISITS BY DATE
// --------------------------------------------------

$visitsByDate = [];

foreach ($visits as $visit) {

    $timestamp = strtotime($visit["scheduled_at"]);

    if ($timestamp === false) {
        continue;
    }

    $dateKey = date("Y-m-d", $timestamp);

    $visitsByDate[$dateKey][] = $visit;
}

// --------------------------------------------------
// WEEKLY STATUS SUMMARY
// --------------------------------------------------

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
<meta name="robots" content="noindex, nofollow">

<title>Weekly Site Visit Schedule | Olivera Home Developers</title>

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
    font-family: Arial, sans-serif;
    background: var(--ivory);
    color: var(--text);
}

a {
    text-decoration: none;
}

.admin-layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.sidebar {
    width: 255px;
    flex-shrink: 0;
    background: var(--navy);
    color: white;
    padding: 25px 15px;
    min-height: 100vh;
}

.brand {
    padding: 5px 12px 25px;
    border-bottom: 1px solid rgba(255,255,255,.15);
    margin-bottom: 22px;
}

.brand h2 {
    color: var(--gold-light);
    font-size: 21px;
    margin: 0 0 6px;
}

.brand p {
    color: #c6d0df;
    font-size: 11px;
    margin: 0;
    letter-spacing: 1px;
}

.sidebar-section {
    color: var(--gold-light);
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 1.4px;
    padding: 15px 12px 8px;
    text-transform: uppercase;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 11px;
    color: #e7edf5;
    padding: 12px;
    margin: 3px 0;
    border-radius: 5px;
    font-size: 13px;
    transition: .2s;
}

.sidebar a:hover {
    background: var(--navy-light);
    color: var(--gold-light);
}

.sidebar a.active {
    background: var(--gold);
    color: var(--navy);
    font-weight: bold;
}

.menu-icon {
    width: 19px;
    text-align: center;
    font-size: 15px;
}

/* MAIN CONTENT */

.main-content {
    flex: 1;
    min-width: 0;
    padding: 30px;
}

.container {
    max-width: 1450px;
    margin: auto;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 10px;
}

h1 {
    color: var(--navy);
    font-size: 28px;
    margin: 0;
}

h2 {
    color: var(--navy);
    font-size: 20px;
    margin: 0 0 20px;
}

p {
    color: var(--muted);
    line-height: 1.6;
}

.card {
    background: var(--surface);
    padding: 25px;
    margin-bottom: 25px;
    border-radius: 9px;
    border: 1px solid var(--border);
    box-shadow: 0 3px 12px rgba(7, 27, 56, .05);
}

.navigation {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin: 20px 0;
}

.navigation a {
    display: inline-block;
    padding: 11px 15px;
    background: var(--navy);
    color: white;
    border-radius: 5px;
    font-size: 13px;
    font-weight: bold;
    transition: .2s;
}

.navigation a:hover {
    background: var(--gold);
    color: var(--navy);
}

.navigation .current {
    background: var(--gold);
    color: var(--navy);
}

.date-form {
    display: flex;
    align-items: end;
    gap: 12px;
    flex-wrap: wrap;
    margin: 22px 0;
}

.date-form label {
    display: block;
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 8px;
    color: var(--navy);
}

.date-form input {
    padding: 11px;
    border: 1px solid var(--border);
    border-radius: 5px;
    color: var(--text);
    background: white;
    font-size: 14px;
}

.date-form input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(214, 166, 59, .15);
}

button {
    padding: 11px 19px;
    background: var(--navy);
    color: white;
    border: 1px solid var(--navy);
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: .2s;
}

button:hover {
    background: var(--gold);
    color: var(--navy);
    border-color: var(--gold);
}

.summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
    gap: 13px;
    margin: 22px 0 30px;
}

.summary-item {
    padding: 17px;
    background: var(--ivory);
    border-radius: 7px;
    border: 1px solid var(--border);
    border-top: 3px solid var(--gold);
    color: var(--muted);
    font-size: 13px;
}

.summary-item strong {
    display: block;
    font-size: 25px;
    margin-top: 9px;
    color: var(--navy);
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

th,
td {
    padding: 13px;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}

th {
    background: var(--navy);
    color: white;
    font-weight: 600;
    white-space: nowrap;
}

td {
    color: var(--text);
}

tbody tr:not(.day-heading):hover {
    background: #fbf8f0;
}

.day-heading td {
    background: #f4edda;
    font-weight: bold;
    color: var(--navy);
    font-size: 14px;
    padding: 14px;
}

.today-heading td {
    background: var(--navy);
    color: white;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    background: #e5edf5;
    color: var(--navy);
    white-space: nowrap;
}

.status.completed {
    background: #e7f2e8;
    color: #286139;
}

.status.cancelled {
    background: #f9e8e5;
    color: #963b2f;
}

.status.rescheduled {
    background: #f8edcf;
    color: #76540b;
}

.property-details strong {
    display: block;
    margin-bottom: 5px;
    color: var(--navy);
}

.empty {
    color: var(--muted);
    padding: 18px;
    text-align: center;
}

.today-label {
    color: var(--gold-light);
    font-weight: bold;
    margin-left: 5px;
}

/* MOBILE SIDEBAR */

.mobile-header {
    display: none;
    align-items: center;
    justify-content: space-between;
    background: var(--navy);
    color: white;
    padding: 14px 18px;
}

.mobile-header strong {
    color: var(--gold-light);
    font-size: 16px;
}

.menu-toggle {
    background: var(--gold);
    color: var(--navy);
    border: 0;
    border-radius: 5px;
    padding: 9px 13px;
    font-size: 18px;
}

.sidebar-overlay {
    display: none;
}

@media (max-width: 900px) {

    .sidebar {
        position: fixed;
        top: 0;
        left: -270px;
        width: 255px;
        height: 100vh;
        overflow-y: auto;
        z-index: 1001;
        transition: left .25s ease;
    }

    .sidebar.open {
        left: 0;
    }

    .sidebar-overlay.show {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.5);
        z-index: 1000;
    }

    .mobile-header {
        display: flex;
    }

    .admin-layout {
        display: block;
    }

    .main-content {
        padding: 20px;
    }
}

@media (max-width: 600px) {

    .main-content {
        padding: 12px;
    }

    h1 {
        font-size: 23px;
    }

    .card {
        padding: 15px;
    }

    .summary-item {
        padding: 13px;
    }

}

</style>

</head>

<body>

<div class="mobile-header">
    <strong>OLIVERA ADMIN</strong>
    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open navigation">☰</button>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="admin-layout">

<aside class="sidebar" id="adminSidebar">

    <div class="brand">
        <h2>OLIVERA</h2>
        <p>HOME DEVELOPERS</p>
    </div>

    <div class="sidebar-section">Main Menu</div>

    <a href="dashboard.php">
        <span class="menu-icon">⌂</span>
        Dashboard
    </a>

    <?php if ($canManageUsers): ?>
    <a href="property_enquiries.php">
        <span class="menu-icon">▤</span>
        Property Enquiries
    </a>
    <?php endif; ?>

    <?php if ($canManageAdmins): ?>
    <a href="manage_admins.php">
        <span class="menu-icon">♙</span>
        Manage Admin
    </a>
    <?php endif; ?>

    <?php if ($canViewActivity): ?>
    <a href="activity_log.php">
        <span class="menu-icon">◷</span>
        Activity Log
    </a>
    <?php endif; ?>

    <?php if ($canManageLeads): ?>
    <a href="manage_leads.php">
        <span class="menu-icon">☷</span>
        Manage Leads
    </a>
    <?php endif; ?>

    <?php if ($canManagePartnerships): ?>
    <a href="manage_partnerships.php">
        <span class="menu-icon">♧</span>
        Manage Partnerships
    </a>
    <?php endif; ?>

    <?php if ($canManageFollowups): ?>
    <a href="lead_followups.php">
        <span class="menu-icon">✉</span>
        Lead Follow-ups
    </a>

    <a href="followup_reminders.php">
        <span class="menu-icon">◷</span>
        Follow-up Reminders
    </a>
    <?php endif; ?>

    <a href="site_visits.php" class="active">
        <span class="menu-icon">⌖</span>
        Site Visits
    </a>

    <a href="weekly_site_visits.php" class="active">
        <span class="menu-icon">▦</span>
        Weekly Schedule
    </a>

    <?php if ($canViewReports): ?>
    <a href="crm_reports.php">
        <span class="menu-icon">▥</span>
        CRM Reports
    </a>
    <?php endif; ?>

    <div class="sidebar-section">Site Visit Tools</div>

    <a href="site_visits.php">
        <span class="menu-icon">▦</span>
        Monthly Calendar
    </a>

    <a href="site_visits.php?day=<?php echo e($today); ?>#daily-schedule">
        <span class="menu-icon">◷</span>
        Daily Schedule
    </a>

</aside>

<main class="main-content">

<div class="container">

<div class="page-heading">
    <h1>Weekly Site Visit Schedule</h1>
</div>

<p>View all site visits scheduled from Monday to Sunday.</p>

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

        <button type="submit">View Week</button>

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
                            <span class="today-label">- TODAY</span>
                        <?php endif; ?>

                        (<?php echo count($dayVisits); ?> visit(s))

                    </td>

                </tr>

                <?php if (count($dayVisits) > 0): ?>

                    <?php foreach ($dayVisits as $visit): ?>

                        <?php

                        $visitTimestamp = strtotime($visit["scheduled_at"]);

                        $visitTime = $visitTimestamp !== false
                            ? date("h:i A", $visitTimestamp)
                            : "Not available";

                        $statusClass = strtolower(
                            preg_replace(
                                '/[^a-z0-9_-]/i',
                                '',
                                (string)$visit["visit_status"]
                            )
                        );

                        ?>

                        <tr>

                            <td><?php echo (int)$visit["id"]; ?></td>

                            <td>
                                <strong><?php echo e($visitTime); ?></strong>
                            </td>

                            <td><?php echo e($visit["customer_name"]); ?></td>

                            <td><?php echo e($visit["phone"]); ?></td>

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
                                <span class="status <?php echo e($statusClass); ?>">
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

</main>

</div>

<script>

const sidebar = document.getElementById("adminSidebar");
const overlay = document.getElementById("sidebarOverlay");
const menuToggle = document.getElementById("menuToggle");

function openSidebar() {
    sidebar.classList.add("open");
    overlay.classList.add("show");
}

function closeSidebar() {
    sidebar.classList.remove("open");
    overlay.classList.remove("show");
}

menuToggle.addEventListener("click", openSidebar);
overlay.addEventListener("click", closeSidebar);

</script>

</body>
</html>
