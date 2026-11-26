
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
// INCLUDES
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

requirePermission("manage_site_visits");

$adminId = (int) ($_SESSION["admin_id"] ?? 0);
$currentRole = $_SESSION["admin_role"] ?? "";

header("X-Robots-Tag: noindex, nofollow");

// --------------------------------------------------
// HELPERS
// --------------------------------------------------

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function formatHistoryDate($value): string
{
    if (empty($value)) {
        return "—";
    }

    try {
        $date = new DateTime((string) $value);
        return $date->format("d M Y, h:i A");
    } catch (Exception $exception) {
        return "—";
    }
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

    <meta name="robots" content="noindex, nofollow">

    <title>Site Visit Cancellation History | Olivera Home Developers</title>

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
            font-family: Arial, sans-serif;
            background: var(--ivory);
            margin: 0;
            padding: 24px;
            color: var(--text);
        }

        .container {
            max-width: 1500px;
            margin: auto;
        }

        h1 {
            color: var(--navy);
            margin-bottom: 8px;
            font-size: 28px;
        }

        h2 {
            color: var(--navy);
            margin-top: 28px;
            margin-bottom: 15px;
            font-size: 21px;
        }

        .page-description {
            color: var(--muted);
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .navigation {
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .navigation a {
            display: inline-block;
            padding: 11px 15px;
            background: var(--navy);
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            transition: 0.2s ease;
        }

        .navigation a:hover {
            background: var(--gold);
            color: var(--navy);
        }

        .summary-card {
            background: var(--surface);
            padding: 24px;
            margin-bottom: 24px;
            border-radius: 10px;
            border: 1px solid var(--border);
            border-top: 4px solid var(--gold);
            box-shadow: 0 3px 12px rgba(7, 27, 56, 0.06);
        }

        .summary-card h2 {
            margin: 0 0 12px;
        }

        .total-number {
            font-size: 36px;
            font-weight: bold;
            color: var(--navy);
        }

        .table-wrapper {
            overflow-x: auto;
            background: var(--surface);
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: 0 3px 12px rgba(7, 27, 56, 0.05);
            margin-bottom: 28px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th,
        td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
            font-size: 13px;
            line-height: 1.5;
        }

        th {
            background: var(--navy);
            color: #ffffff;
            font-weight: bold;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fbf8ef;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .cancelled-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #f8e9e5;
            color: #923b2c;
            border: 1px solid #e9c5ba;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .reason {
            max-width: 280px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .employee-count {
            font-weight: bold;
            color: var(--navy);
        }

        .empty-message {
            padding: 28px;
            text-align: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--muted);
        }

        @media (max-width: 768px) {
            body {
                padding: 14px;
            }

            h1 {
                font-size: 23px;
            }

            .navigation a {
                padding: 10px 12px;
                font-size: 12px;
            }

            .summary-card {
                padding: 18px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Site Visit Cancellation History Report</h1>

    <p class="page-description">
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

    <?php if (!empty($employeeSummary)): ?>

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

    <?php if (!empty($cancellations)): ?>

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
                        <th>Original Scheduled Date &amp; Time</th>
                        <th>Cancellation Reason</th>
                        <th>Cancelled By</th>
                        <th>Cancellation Date &amp; Time</th>
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
                                <?php echo e(formatHistoryDate($visit["scheduled_at"])); ?>
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
                                <?php echo e(formatHistoryDate($visit["cancelled_at"])); ?>
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
