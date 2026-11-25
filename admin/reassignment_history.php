
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

// Only Admin and Super Admin can view reassignment history.
if (!hasPermission("assign_leads")) {
    http_response_code(403);
    exit("Access Denied! You do not have permission to view reassignment history.");
}

// Fetch reassignment history.
$stmt = $pdo->query("
    SELECT
        al.id,
        al.lead_id,
        al.admin_id,
        al.activity_type,
        al.description,
        al.old_value,
        al.new_value,
        al.created_at,
        l.customer_name,
        a.name AS changed_by
    FROM lead_activity_logs al

    LEFT JOIN leads l
        ON al.lead_id = l.id

    LEFT JOIN admins a
        ON al.admin_id = a.id

    WHERE al.activity_type = 'lead_assigned'
    AND al.description LIKE 'Lead reassigned from %'

    ORDER BY al.created_at DESC, al.id DESC
");

$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Lead Reassignment History</title>

<style>

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
    padding: 20px;
    color: #222;
}

main {
    max-width: 1200px;
    margin: auto;
}

.top-links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.top-links a {
    display: inline-block;
    padding: 9px 13px;
    border-radius: 5px;
    background: #1f5e9c;
    color: white;
    text-decoration: none;
    font-size: 14px;
}

.top-links a:hover {
    opacity: .88;
}

.panel {
    background: white;
    padding: 20px;
    border-radius: 9px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px #0000000c;
}

h1 {
    margin-top: 0;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e7eaf0;
    text-align: left;
    vertical-align: top;
}

th {
    background: #f1f4f8;
    font-size: 13px;
}

td {
    font-size: 13px;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 14px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 12px;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #667085;
}

.muted {
    color: #667085;
    font-size: 12px;
}

.view-link {
    color: #1f5e9c;
    text-decoration: none;
    font-weight: bold;
}

.view-link:hover {
    text-decoration: underline;
}

</style>

</head>

<body>

<main>

    <div class="top-links">

        <a href="dashboard.php">Dashboard</a>

        <a href="manage_leads.php">Manage Leads</a>

        <a href="reassign_lead.php">Reassign Leads</a>

        <a href="crm_reports.php">CRM Reports</a>

    </div>

    <section class="panel">

        <h1>Lead Reassignment History</h1>

        <p class="muted">
            This page shows leads transferred from one Sales Employee
            to another, including the Admin who performed the reassignment.
        </p>

        <p>
            <strong>Total Reassignments:</strong>
            <?php echo count($history); ?>
        </p>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>Record ID</th>
                        <th>Lead</th>
                        <th>Previous Employee</th>
                        <th>New Employee</th>
                        <th>Reassigned By</th>
                        <th>Date &amp; Time</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!$history): ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No lead reassignment records found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($history as $record): ?>

                        <tr>

                            <td>
                                <?php echo (int)$record["id"]; ?>
                            </td>

                            <td>

                                <strong>
                                    <?php echo h(
                                        $record["customer_name"]
                                        ?? "Lead no longer available"
                                    ); ?>
                                </strong>

                                <br>

                                <span class="muted">
                                    Lead ID:
                                    <?php echo (int)$record["lead_id"]; ?>
                                </span>

                                <br>

                                <a class="view-link"
                                   href="view_lead.php?id=<?php echo (int)$record["lead_id"]; ?>">
                                    View Lead
                                </a>

                            </td>

                            <td>
                                <?php echo h(
                                    $record["old_value"] ?? "-"
                                ); ?>
                            </td>

                            <td>

                                <span class="badge">
                                    <?php echo h(
                                        $record["new_value"] ?? "-"
                                    ); ?>
                                </span>

                            </td>

                            <td>
                                <?php echo h(
                                    $record["changed_by"]
                                    ?? "Unknown Admin"
                                ); ?>
                            </td>

                            <td>
                                <?php
                                echo h(
                                    date(
                                        "d-m-Y h:i A",
                                        strtotime($record["created_at"])
                                    )
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>

</html>
