
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

    <title>Site Visit Performance Analytics</title>

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
            display: inline-block;
            padding: 10px 14px;
            background: #34495e;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .navigation a:hover {
            background: #22313f;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
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
            margin: 0 0 12px;
            font-size: 14px;
            color: #666;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
        }

        .total {
            color: #34495e;
        }

        .scheduled {
            color: #2980b9;
        }

        .completed {
            color: #27ae60;
        }

        .cancelled {
            color: #c0392b;
        }

        .rescheduled {
            color: #8e44ad;
        }

        .rate {
            color: #16a085;
        }

        .section {
            background: white;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            font-size: 14px;
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
            color: #666;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .rate-bar {
            width: 100%;
            max-width: 150px;
            height: 10px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 5px;
        }

        .rate-fill {
            height: 100%;
            background: #27ae60;
        }

        @media (max-width: 600px) {

            body {
                padding: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-number {
                font-size: 25px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <h1>Site Visit Performance Analytics</h1>

    <p>
        Overall site visit statistics, employee performance
        and monthly visit analysis.
    </p>

    <!-- NAVIGATION -->

    <div class="navigation">

        <a href="dashboard.php">Dashboard</a>

        <a href="site_visits.php">Monthly Calendar</a>

        <a href="weekly_site_visits.php">Weekly Schedule</a>

        <a href="reschedule_site_visits.php">Reschedule Visits</a>

        <a href="site_visit_reschedule_history.php">Rescheduling History</a>

        <a href="cancel_site_visit.php">Cancel Site Visits</a>

        <a href="site_visit_cancellation_history.php">Cancellation History</a>

        <a href="manage_leads.php">Manage Leads</a>

    </div>

    <!-- OVERALL STATISTICS -->

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

    <!-- PERFORMANCE RATES -->

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
                    style="width: <?php echo $completionRate; ?>%;"
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

    <!-- EMPLOYEE PERFORMANCE -->

    <div class="section">

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

                                <td>
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

    </div>

    <!-- MONTHLY PERFORMANCE -->

    <div class="section">

        <h2>Month-wise Site Visit Statistics</h2>

        <p>
            Statistics are based on the original scheduled date
            of each site visit and cover the last six months.
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

                                <td>
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

    </div>

</div>

</body>
</html>
