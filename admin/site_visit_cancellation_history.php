
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
// 2. CANCELLATION HISTORY
// --------------------------------------------------

$historySql = "
    SELECT
        sv.id AS visit_id,
        sv.lead_id,
        sv.scheduled_at,
        sv.cancellation_reason,
        sv.cancelled_at,

        l.customer_name,
        l.phone,
        l.property_type,
        l.interested_location,

        assigned_employee.name AS assigned_employee_name,
        canceller.name AS cancelled_by_name

    FROM site_visits sv

    INNER JOIN leads l
        ON sv.lead_id = l.id

    LEFT JOIN admins assigned_employee
        ON sv.assigned_to = assigned_employee.id

    LEFT JOIN admins canceller
        ON sv.cancelled_by = canceller.id

    WHERE sv.visit_status = 'cancelled'
      AND sv.cancelled_at IS NOT NULL

    $scopeSql

    ORDER BY sv.cancelled_at DESC
";

$historyStmt = $pdo->prepare($historySql);
$historyStmt->execute($params);

$cancellations = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$totalCancellations = count($cancellations);

// --------------------------------------------------
// 3. EMPLOYEE-WISE CANCELLATION SUMMARY
// --------------------------------------------------

$summarySql = "
    SELECT
        assigned_employee.id AS employee_id,
        COALESCE(assigned_employee.name, 'Unassigned') AS employee_name,
        COUNT(sv.id) AS cancellation_count

    FROM site_visits sv

    INNER JOIN leads l
        ON sv.lead_id = l.id

    LEFT JOIN admins assigned_employee
        ON sv.assigned_to = assigned_employee.id

    WHERE sv.visit_status = 'cancelled'
      AND sv.cancelled_at IS NOT NULL

    $scopeSql

    GROUP BY assigned_employee.id, assigned_employee.name

    ORDER BY cancellation_count DESC, employee_name ASC
";

$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);

$employeeSummary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Site Visit Cancellation History</title>

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
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
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

        .summary-card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .total-number {
            font-size: 32px;
            font-weight: bold;
            color: #c0392b;
        }

        .table-wrapper {
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #34495e;
            color: white;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .cancelled-badge {
            display: inline-block;
            padding: 5px 9px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .reason {
            max-width: 250px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .empty-message {
            padding: 25px;
            text-align: center;
            background: white;
            border-radius: 8px;
            color: #666;
        }

        .employee-count {
            font-weight: bold;
            color: #c0392b;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Site Visit Cancellation History Report</h1>

    <p>
        View cancelled site visits, cancellation reasons and
        employee-wise cancellation totals.
    </p>

    <div class="navigation">

        <a href="dashboard.php">Dashboard</a>

        <a href="site_visits.php">Monthly Site Visit Calendar</a>

        <a href="weekly_site_visits.php">Weekly Schedule</a>

        <a href="reschedule_site_visits.php">Reschedule Site Visits</a>

        <a href="site_visit_reschedule_history.php">Rescheduling History</a>

        <a href="cancel_site_visit.php">Cancel Site Visits</a>

        <a href="manage_leads.php">Manage Leads</a>

    </div>

    <!-- TOTAL CANCELLATIONS -->

    <div class="summary-card">

        <h2>Total Cancelled Site Visits</h2>

        <div class="total-number">
            <?php echo $totalCancellations; ?>
        </div>

    </div>

    <!-- EMPLOYEE-WISE SUMMARY -->

    <h2>Employee-wise Cancellation Summary</h2>

    <?php if (count($employeeSummary) > 0): ?>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Sales Employee</th>
                        <th>Total Cancelled Visits</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($employeeSummary as $employee): ?>

                        <tr>

                            <td>
                                <?php echo e($employee["employee_name"]); ?>
                            </td>

                            <td class="employee-count">
                                <?php echo (int) $employee["cancellation_count"]; ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <div class="empty-message">
            No employee-wise cancellation records found.
        </div>

    <?php endif; ?>

    <!-- CANCELLATION HISTORY -->

    <h2>Cancellation History</h2>

    <?php if (count($cancellations) > 0): ?>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Visit ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Property Type</th>
                        <th>Interested Location</th>
                        <th>Assigned Employee</th>
                        <th>Original Scheduled Date & Time</th>
                        <th>Cancellation Reason</th>
                        <th>Cancelled By</th>
                        <th>Cancellation Date & Time</th>
                        <th>Status</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($cancellations as $visit): ?>

                        <tr>

                            <td>
                                <?php echo (int) $visit["visit_id"]; ?>
                            </td>

                            <td>
                                <?php echo e($visit["customer_name"]); ?>
                            </td>

                            <td>
                                <?php echo e($visit["phone"]); ?>
                            </td>

                            <td>
                                <?php echo e($visit["property_type"]); ?>
                            </td>

                            <td>
                                <?php echo e($visit["interested_location"]); ?>
                            </td>

                            <td>
                                <?php
                                echo e(
                                    $visit["assigned_employee_name"]
                                    ?: "Unassigned"
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo e(
                                    date(
                                        "d M Y, h:i A",
                                        strtotime($visit["scheduled_at"])
                                    )
                                );
                                ?>
                            </td>

                            <td class="reason">
                                <?php
                                echo nl2br(
                                    e(
                                        $visit["cancellation_reason"]
                                        ?: "No reason recorded"
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo e(
                                    $visit["cancelled_by_name"]
                                    ?: "Admin no longer available"
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo e(
                                    date(
                                        "d M Y, h:i A",
                                        strtotime($visit["cancelled_at"])
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <span class="cancelled-badge">
                                    Cancelled
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <div class="empty-message">
            No cancelled site visits found.
        </div>

    <?php endif; ?>

</div>

</body>
</html>
