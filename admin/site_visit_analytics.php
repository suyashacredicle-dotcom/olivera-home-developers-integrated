
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
$currentRole = $_SESSION["admin_role"] ?? "";

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// --------------------------------------------------
// 1. ROLE-BASED ACCESS
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
// 2. OVERALL SITE VISIT STATISTICS
// --------------------------------------------------

$statsSql = "
    SELECT
        COUNT(*) AS total_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'scheduled' THEN 1
                ELSE 0
            END
        ) AS scheduled_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'completed' THEN 1
                ELSE 0
            END
        ) AS completed_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'cancelled' THEN 1
                ELSE 0
            END
        ) AS cancelled_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'rescheduled' THEN 1
                ELSE 0
            END
        ) AS rescheduled_visits

    FROM site_visits sv

    INNER JOIN leads l
        ON sv.lead_id = l.id

    WHERE 1 = 1

    $scopeSql
";

$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($params);

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalVisits = (int) ($stats["total_visits"] ?? 0);
$scheduledVisits = (int) ($stats["scheduled_visits"] ?? 0);
$completedVisits = (int) ($stats["completed_visits"] ?? 0);
$cancelledVisits = (int) ($stats["cancelled_visits"] ?? 0);
$rescheduledVisits = (int) ($stats["rescheduled_visits"] ?? 0);

// --------------------------------------------------
// 3. COMPLETION AND CANCELLATION RATES
// --------------------------------------------------

$completionRate = $totalVisits > 0
    ? round(($completedVisits / $totalVisits) * 100, 2)
    : 0;

$cancellationRate = $totalVisits > 0
    ? round(($cancelledVisits / $totalVisits) * 100, 2)
    : 0;

// --------------------------------------------------
// 4. EMPLOYEE-WISE SITE VISIT PERFORMANCE
// --------------------------------------------------

$employeeSql = "
    SELECT
        assigned_employee.id AS employee_id,

        COALESCE(
            assigned_employee.name,
            'Unassigned'
        ) AS employee_name,

        COUNT(sv.id) AS total_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'scheduled' THEN 1
                ELSE 0
            END
        ) AS scheduled_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'completed' THEN 1
                ELSE 0
            END
        ) AS completed_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'cancelled' THEN 1
                ELSE 0
            END
        ) AS cancelled_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'rescheduled' THEN 1
                ELSE 0
            END
        ) AS rescheduled_visits

    FROM site_visits sv

    INNER JOIN leads l
        ON sv.lead_id = l.id

    LEFT JOIN admins assigned_employee
        ON sv.assigned_to = assigned_employee.id

    WHERE 1 = 1

    $scopeSql

    GROUP BY
        assigned_employee.id,
        assigned_employee.name

    ORDER BY total_visits DESC, employee_name ASC
";

$employeeStmt = $pdo->prepare($employeeSql);
$employeeStmt->execute($params);

$employeePerformance = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 5. MONTH-WISE SITE VISIT STATISTICS
// LAST SIX MONTHS
// --------------------------------------------------

$monthlySql = "
    SELECT
        DATE_FORMAT(sv.scheduled_at, '%Y-%m') AS visit_month,

        COUNT(*) AS total_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'scheduled' THEN 1
                ELSE 0
            END
        ) AS scheduled_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'completed' THEN 1
                ELSE 0
            END
        ) AS completed_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'cancelled' THEN 1
                ELSE 0
            END
        ) AS cancelled_visits,

        SUM(
            CASE
                WHEN sv.visit_status = 'rescheduled' THEN 1
                ELSE 0
            END
        ) AS rescheduled_visits

    FROM site_visits sv

    INNER JOIN leads l
        ON sv.lead_id = l.id

    WHERE sv.scheduled_at >=
        DATE_FORMAT(
            DATE_SUB(CURDATE(), INTERVAL 5 MONTH),
            '%Y-%m-01'
        )

    $scopeSql

    GROUP BY DATE_FORMAT(sv.scheduled_at, '%Y-%m')

    ORDER BY visit_month DESC
";

$monthlyStmt = $pdo->prepare($monthlySql);
$monthlyStmt->execute($params);

$monthlyPerformance = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Site Visit Performance Analytics | Olivera Home Developers</title>

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

        h1, h2 {
            color: var(--navy);
        }

        h1 {
            font-size: 28px;
            margin: 0 0 9px;
        }

        h2 {
            font-size: 19px;
            margin: 0 0 20px;
        }

        .page-description {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 23px;
        }

        .navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-bottom: 25px;
        }

        .navigation a {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            background: var(--surface);
            color: var(--navy);
            text-decoration: none;
            border: 1px solid var(--border);
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .navigation a:hover {
            background: #f0e7d1;
            border-color: var(--gold);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--surface);
            padding: 22px;
            border-radius: 9px;
            border: 1px solid var(--border);
            border-top: 4px solid var(--gold);
            box-shadow: 0 3px 12px rgba(7, 27, 56, 0.04);
        }

        .stat-card h3 {
            margin: 0 0 13px;
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            color: var(--navy);
        }

        .total {
            color: var(--navy);
        }

        .scheduled {
            color: #24649a;
        }

        .completed {
            color: #27804a;
        }

        .cancelled {
            color: #bd4545;
        }

        .rescheduled {
            color: #8a681b;
        }

        .rate {
            color: var(--navy);
        }

        .section {
            background: var(--surface);
            padding: 24px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(7, 27, 56, 0.04);
        }

        .section-description {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
            margin-top: -8px;
            margin-bottom: 18px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #f0ede6;
            text-align: left;
            vertical-align: middle;
            font-size: 13px;
        }

        th {
            background: var(--navy);
            color: #ffffff;
            font-size: 11px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fcfaf5;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .employee-name {
            color: var(--navy);
            font-weight: 700;
        }

        .empty-message {
            padding: 25px;
            text-align: center;
            color: var(--muted);
            background: var(--ivory);
            border: 1px solid var(--border);
            border-radius: 7px;
        }

        .rate-bar {
            width: 100%;
            max-width: 150px;
            height: 10px;
            background: #eee9dd;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 7px;
        }

        .rate-fill {
            height: 100%;
            background: var(--gold);
            border-radius: 10px;
        }

        .stat-card p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
            margin: 12px 0 0;
        }

        @media (max-width: 700px) {

            body {
                padding: 15px;
            }

            .section {
                padding: 16px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-number {
                font-size: 25px;
            }

            h1 {
                font-size: 23px;
            }

            .navigation a {
                padding: 9px 11px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <header>

        <h1>Site Visit Performance Analytics</h1>

        <p class="page-description">
            Overall site visit statistics, employee performance
            and monthly visit analysis.
        </p>

    </header>

    <nav class="navigation">

        <a href="dashboard.php">Dashboard</a>

        <a href="site_visits.php">Monthly Calendar</a>

        <a href="weekly_site_visits.php">Weekly Schedule</a>

        <a href="reschedule_site_visits.php">Reschedule Visits</a>

        <a href="site_visit_reschedule_history.php">Rescheduling History</a>

        <a href="cancel_site_visit.php">Cancel Site Visits</a>

        <a href="site_visit_cancellation_history.php">Cancellation History</a>

        <a href="manage_leads.php">Manage Leads</a>

    </nav>

    <h2>Overall Site Visit Summary</h2>

    <div class="stats-grid">

        <div class="stat-card">

            <h3>Total Site Visits</h3>

            <div class="stat-number total">
                <?php echo $totalVisits; ?>
            </div>

        </div>

        <div class="stat-card">

            <h3>Scheduled Visits</h3>

            <div class="stat-number scheduled">
                <?php echo $scheduledVisits; ?>
            </div>

        </div>

        <div class="stat-card">

            <h3>Completed Visits</h3>

            <div class="stat-number completed">
                <?php echo $completedVisits; ?>
            </div>

        </div>

        <div class="stat-card">

            <h3>Cancelled Visits</h3>

            <div class="stat-number cancelled">
                <?php echo $cancelledVisits; ?>
            </div>

        </div>

        <div class="stat-card">

            <h3>Rescheduled Visits</h3>

            <div class="stat-number rescheduled">
                <?php echo $rescheduledVisits; ?>
            </div>

        </div>

    </div>

    <h2>Performance Rates</h2>

    <div class="stats-grid">

        <div class="stat-card">

            <h3>Visit Completion Rate</h3>

            <div class="stat-number rate">
                <?php echo $completionRate; ?>%
            </div>

            <div class="rate-bar">

                <div
                    class="rate-fill"
                    style="width: <?php echo min(100, max(0, $completionRate)); ?>%;"
                ></div>

            </div>

            <p>
                <?php echo $completedVisits; ?>
                completed out of
                <?php echo $totalVisits; ?>
                visits.
            </p>

        </div>

        <div class="stat-card">

            <h3>Visit Cancellation Rate</h3>

            <div class="stat-number cancelled">
                <?php echo $cancellationRate; ?>%
            </div>

            <p>
                <?php echo $cancelledVisits; ?>
                cancelled out of
                <?php echo $totalVisits; ?>
                visits.
            </p>

        </div>

    </div>

    <section class="section">

        <h2>Employee-wise Site Visit Performance</h2>

        <?php if (count($employeePerformance) > 0): ?>

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
                            <th>Completion Rate</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($employeePerformance as $employee): ?>

                            <?php

                            $employeeTotal = (int) $employee["total_visits"];

                            $employeeCompleted = (int) $employee["completed_visits"];

                            $employeeRate = $employeeTotal > 0
                                ? round(
                                    ($employeeCompleted / $employeeTotal) * 100,
                                    2
                                )
                                : 0;

                            ?>

                            <tr>

                                <td class="employee-name">
                                    <?php echo e($employee["employee_name"]); ?>
                                </td>

                                <td>
                                    <?php echo $employeeTotal; ?>
                                </td>

                                <td>
                                    <?php echo (int) $employee["scheduled_visits"]; ?>
                                </td>

                                <td>
                                    <?php echo $employeeCompleted; ?>
                                </td>

                                <td>
                                    <?php echo (int) $employee["cancelled_visits"]; ?>
                                </td>

                                <td>
                                    <?php echo (int) $employee["rescheduled_visits"]; ?>
                                </td>

                                <td>
                                    <?php echo $employeeRate; ?>%
                                </td>

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

    </section>

    <section class="section">

        <h2>Month-wise Site Visit Statistics</h2>

        <p class="section-description">
            Statistics are based on the scheduled date of each site visit
            and cover the last six months.
        </p>

        <?php if (count($monthlyPerformance) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Month</th>
                            <th>Total Visits</th>
                            <th>Scheduled</th>
                            <th>Completed</th>
                            <th>Cancelled</th>
                            <th>Rescheduled</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($monthlyPerformance as $month): ?>

                            <tr>

                                <td class="employee-name">
                                    <?php

                                    $monthDate = DateTime::createFromFormat(
                                        "!Y-m",
                                        $month["visit_month"]
                                    );

                                    echo e(
                                        $monthDate
                                            ? $monthDate->format("F Y")
                                            : $month["visit_month"]
                                    );

                                    ?>
                                </td>

                                <td>
                                    <?php echo (int) $month["total_visits"]; ?>
                                </td>

                                <td>
                                    <?php echo (int) $month["scheduled_visits"]; ?>
                                </td>

                                <td>
                                    <?php echo (int) $month["completed_visits"]; ?>
                                </td>

                                <td>
                                    <?php echo (int) $month["cancelled_visits"]; ?>
                                </td>

                                <td>
                                    <?php echo (int) $month["rescheduled_visits"]; ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-message">
                No site visit records found for the last six months.
            </div>

        <?php endif; ?>

    </section>

</div>

</body>
</html>
