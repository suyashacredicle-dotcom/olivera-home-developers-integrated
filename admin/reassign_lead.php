
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";
require_once "../includes/lead_activity_logger.php";
require_once "../includes/notification_helper.php";

// Only Admin and Super Admin users with assignment permission
// can access this page.
if (!hasPermission("assign_leads")) {
    http_response_code(403);
    exit("Access Denied! You do not have permission to reassign leads.");
}

$adminId = (int)($_SESSION["admin_id"] ?? 0);

$message = "";
$messageType = "success";

// CSRF protection
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// Display messages after redirect
if (isset($_SESSION["user_message"])) {
    $message = $_SESSION["user_message"];
    $messageType = $_SESSION["user_message_type"] ?? "success";

    unset(
        $_SESSION["user_message"],
        $_SESSION["user_message_type"]
    );
}

// Get active Sales Employees
$stmt = $pdo->query("
    SELECT id, name
    FROM admins
    WHERE role = 'sales_employee'
    AND status = 'active'
    ORDER BY name ASC
");

$salesEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle reassignment
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($csrfToken, $_POST["csrf_token"])
    ) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";

    } else {

        $leadId = (int)($_POST["lead_id"] ?? 0);
        $newEmployeeId = (int)($_POST["assigned_to"] ?? 0);
        $reason = trim($_POST["reassignment_reason"] ?? "");

        if ($leadId <= 0 || $newEmployeeId <= 0) {

            $message = "Please select a valid lead and Sales Employee.";
            $messageType = "error";

        } elseif ($reason === "") {

            $message = "Please enter a reason for reassignment.";
            $messageType = "error";

        } elseif (strlen($reason) > 1000) {

            $message = "Reassignment reason cannot exceed 1000 characters.";
            $messageType = "error";

        } else {

            // Verify that the lead exists and is already assigned.
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    customer_name,
                    assigned_to
                FROM leads
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$leadId]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lead) {

                $message = "Lead not found.";
                $messageType = "error";

            } elseif ($lead["assigned_to"] === null) {

                $message = "This lead is currently unassigned. Please use Manage Leads to assign it first.";
                $messageType = "error";

            } else {

                $oldEmployeeId = (int)$lead["assigned_to"];

                // Verify that the new employee is active
                // and has the Sales Employee role.
                $stmt = $pdo->prepare("
                    SELECT id, name
                    FROM admins
                    WHERE id = ?
                    AND role = 'sales_employee'
                    AND status = 'active'
                    LIMIT 1
                ");

                $stmt->execute([$newEmployeeId]);
                $newEmployee = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$newEmployee) {

                    $message = "Please select an active Sales Employee.";
                    $messageType = "error";

                } elseif ($oldEmployeeId === $newEmployeeId) {

                    $message = "This lead is already assigned to the selected employee.";
                    $messageType = "error";

                } else {

                    // Get the previous employee's name.
                    $stmt = $pdo->prepare("
                        SELECT name
                        FROM admins
                        WHERE id = ?
                    ");

                    $stmt->execute([$oldEmployeeId]);
                    $oldEmployeeName = $stmt->fetchColumn();

                    if (!$oldEmployeeName) {
                        $oldEmployeeName = "Unknown Employee";
                    }

                    try {

                        $pdo->beginTransaction();

                        // Update lead assignment.
                        $stmt = $pdo->prepare("
                            UPDATE leads
                            SET assigned_to = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $newEmployeeId,
                            $leadId
                        ]);

                        // Record the assignment change and reason.
                        $activityDescription =
                            "Lead reassigned from "
                            . $oldEmployeeName
                            . " to "
                            . $newEmployee["name"]
                            . ". Reason: "
                            . $reason;

                        logLeadActivity(
                            $pdo,
                            $leadId,
                            $adminId,
                            "lead_assigned",
                            $activityDescription,
                            $oldEmployeeName,
                            $newEmployee["name"]
                        );

                        // Create an in-app notification for the
                        // new Sales Employee.
                        $notificationTitle = "New Lead Assigned";

                        $notificationMessage =
                            "The lead for "
                            . $lead["customer_name"]
                            . " has been reassigned to you. "
                            . "Previous employee: "
                            . $oldEmployeeName
                            . ". Reason: "
                            . $reason;

                        createNotification(
                            $pdo,
                            $newEmployeeId,
                            $leadId,
                            $notificationTitle,
                            $notificationMessage
                        );

                        $pdo->commit();

                        $_SESSION["user_message"] =
                            "Lead successfully reassigned from "
                            . $oldEmployeeName
                            . " to "
                            . $newEmployee["name"]
                            . ". A notification has been sent to the new employee.";

                        $_SESSION["user_message_type"] = "success";

                        header("Location: reassign_lead.php");
                        exit;

                    } catch (Exception $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $message = "Unable to reassign the lead or create its notification. Please try again.";
                        $messageType = "error";
                    }
                }
            }
        }
    }
}

// Get all currently assigned leads.
$stmt = $pdo->query("
    SELECT
        l.id,
        l.customer_name,
        l.phone,
        l.interested_location,
        l.lead_status,
        l.priority,
        l.assigned_to,
        a.name AS assigned_employee
    FROM leads l
    INNER JOIN admins a
        ON l.assigned_to = a.id
    WHERE l.assigned_to IS NOT NULL
    ORDER BY l.created_at DESC
");

$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<title>Reassign Leads</title>

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
    max-width: 1250px;
    margin: auto;
}

.top-links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.top-links a,
.btn {
    display: inline-block;
    padding: 9px 13px;
    border-radius: 5px;
    background: #1f5e9c;
    color: white;
    text-decoration: none;
    border: 0;
    cursor: pointer;
    font-size: 14px;
}

.top-links a:hover,
.btn:hover {
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
    min-width: 950px;
}

th,
td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e7eaf0;
    vertical-align: middle;
}

th {
    background: #f1f4f8;
    font-size: 13px;
}

td {
    font-size: 13px;
}

select,
textarea {
    padding: 9px;
    border: 1px solid #ccd2da;
    border-radius: 5px;
    width: 100%;
    background: white;
    font-family: Arial, sans-serif;
}

select {
    max-width: 190px;
}

textarea {
    min-width: 190px;
    min-height: 65px;
    resize: vertical;
}

.btn-green {
    background: #218653;
}

.notice {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.notice.success {
    background: #d1fadf;
    color: #05603a;
}

.notice.error {
    background: #fee4e2;
    color: #b42318;
}

.badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 14px;
    font-size: 12px;
    background: #e9eef5;
}

.priority-high {
    background: #fee4e2;
    color: #b42318;
}

.priority-medium {
    background: #fff0c2;
    color: #805b00;
}

.priority-low {
    background: #d1fadf;
    color: #067647;
}

.empty {
    padding: 25px;
    text-align: center;
    color: #667085;
}

.small {
    color: #667085;
    font-size: 12px;
}

.reassign-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 200px;
}

.reassign-form button {
    align-self: flex-start;
}

.reason-label {
    font-weight: bold;
    font-size: 12px;
}

.required {
    color: #b42318;
}

</style>

</head>

<body>

<main>

    <div class="top-links">

        <a href="dashboard.php">Dashboard</a>

        <a href="manage_leads.php">Manage Leads</a>

        <a href="crm_reports.php">CRM Reports</a>

    </div>

    <h1>Lead Reassignment</h1>

    <section class="panel">

        <h2>Transfer Leads Between Sales Employees</h2>

        <p class="small">
            Select a lead, choose another active Sales Employee,
            and enter a reason for transferring responsibility.
        </p>

        <?php if ($message !== ""): ?>

            <div class="notice <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>

        <?php endif; ?>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>Lead ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Currently Assigned To</th>
                        <th>Reassign To</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!$leads): ?>

                    <tr>
                        <td colspan="8" class="empty">
                            No assigned leads found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($leads as $lead): ?>

                        <tr>

                            <td>
                                <?php echo (int)$lead["id"]; ?>
                            </td>

                            <td>
                                <strong>
                                    <?php echo h($lead["customer_name"]); ?>
                                </strong>

                                <br>

                                <a href="view_lead.php?id=<?php echo (int)$lead["id"]; ?>">
                                    View Lead
                                </a>
                            </td>

                            <td>
                                <?php echo h($lead["phone"]); ?>
                            </td>

                            <td>
                                <?php echo h($lead["interested_location"]); ?>
                            </td>

                            <td>
                                <?php echo h(
                                    ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $lead["lead_status"]
                                        )
                                    )
                                ); ?>
                            </td>

                            <td>

                                <span class="badge priority-<?php echo h($lead["priority"] ?? "medium"); ?>">
                                    <?php echo h(
                                        ucfirst($lead["priority"] ?? "medium")
                                    ); ?>
                                </span>

                            </td>

                            <td>
                                <strong>
                                    <?php echo h($lead["assigned_employee"]); ?>
                                </strong>
                            </td>

                            <td>

                                <?php if (!$salesEmployees): ?>

                                    No active Sales Employees.

                                <?php else: ?>

                                    <form method="post"
                                          class="reassign-form"
                                          onsubmit="return confirm('Are you sure you want to reassign this lead?');">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?php echo h($csrfToken); ?>">

                                        <input type="hidden"
                                               name="lead_id"
                                               value="<?php echo (int)$lead["id"]; ?>">

                                        <label class="reason-label">
                                            New Sales Employee
                                        </label>

                                        <select name="assigned_to" required>

                                            <option value="">
                                                Select Employee
                                            </option>

                                            <?php foreach ($salesEmployees as $employee): ?>

                                                <?php if ((int)$employee["id"] !== (int)$lead["assigned_to"]): ?>

                                                    <option value="<?php echo (int)$employee["id"]; ?>">
                                                        <?php echo h($employee["name"]); ?>
                                                    </option>

                                                <?php endif; ?>

                                            <?php endforeach; ?>

                                        </select>

                                        <label class="reason-label">
                                            Reassignment Reason
                                            <span class="required">*</span>
                                        </label>

                                        <textarea
                                            name="reassignment_reason"
                                            placeholder="Enter why this lead is being reassigned..."
                                            maxlength="1000"
                                            required></textarea>

                                        <button type="submit"
                                                class="btn btn-green">
                                            Reassign
                                        </button>

                                    </form>

                                <?php endif; ?>

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
