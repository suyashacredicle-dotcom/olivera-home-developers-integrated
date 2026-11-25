
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";
require_once "../includes/lead_activity_logger.php";

$message = "";
$messageType = "success";

$adminId = (int)($_SESSION["admin_id"] ?? 0);
$currentRole = $_SESSION["admin_role"] ?? "";

$canManageLeads = hasPermission("manage_leads");
$canAssignLeads = ($currentRole === "super_admin");
$canViewAllLeads = hasPermission("view_all_leads");

$isSalesEmployee = ($currentRole === "sales_employee");

if (!$canManageLeads && !$canViewAllLeads && !hasPermission("view_assigned_leads")) {
    http_response_code(403);
    exit("Access Denied! You do not have permission to view leads.");
}

// Create CSRF token
if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    $_SESSION["csrf_token"] === ""
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// Display flash message
if (isset($_SESSION["user_message"])) {
    $message = $_SESSION["user_message"];
    $messageType = $_SESSION["user_message_type"] ?? "success";

    unset($_SESSION["user_message"], $_SESSION["user_message_type"]);
}

// Lead priority options
$priorities = [
    "high" => "High",
    "medium" => "Medium",
    "low" => "Low"
];

// Lead status options
$statuses = [
    "new" => "New",
    "contacted" => "Contacted",
    "interested" => "Interested",
    "site_visit_scheduled" => "Site Visit Scheduled",
    "negotiation" => "Negotiation",
    "converted" => "Converted",
    "lost" => "Lost"
];

// Property types
$propertyTypes = [
    "Plot",
    "Apartment",
    "Villa",
    "Independent House",
    "Commercial",
    "Other"
];

// Lead sources
$leadSources = [
    "Manual Entry",
    "Website",
    "Google Ads",
    "Google Search",
    "Facebook",
    "Instagram",
    "WhatsApp",
    "Phone Call",
    "99acres",
    "Magicbricks",
    "Housing.com",
    "Property Portal",
    "Referral",
    "Walk-in",
    "Broker",
    "YouTube",
    "LinkedIn",
    "Other"
];

// Active Sales Employees
$stmt = $pdo->query("
    SELECT id, name
    FROM admins
    WHERE role = 'sales_employee'
    AND status = 'active'
    ORDER BY name ASC
");

$salesEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

function manageLeadsRedirect() {
    header("Location: manage_leads.php");
    exit;
}

function manageLeadsLog(
    $pdo,
    $leadId,
    $adminId,
    $type,
    $description,
    $oldValue = null,
    $newValue = null
) {
    logLeadActivity(
        $pdo,
        $leadId,
        $adminId,
        $type,
        $description,
        $oldValue,
        $newValue
    );
}

// --------------------------------------------------
// POST ACTIONS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Strict CSRF validation
    if (
        !is_string($csrfToken) ||
        !is_string($_POST["csrf_token"] ?? null) ||
        !hash_equals($csrfToken, $_POST["csrf_token"])
    ) {

        $message = "Invalid security token. Please try again.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";

        // --------------------------------------------------
        // ADD NEW LEAD
        // --------------------------------------------------

        if ($action === "add_lead" && $canManageLeads) {

            $customerName = trim($_POST["customer_name"] ?? "");
            $phone = trim($_POST["phone"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $propertyType = trim($_POST["property_type"] ?? "");
            $location = trim($_POST["interested_location"] ?? "");

            $budgetMin = ($_POST["budget_min"] ?? "") !== ""
                ? (float)$_POST["budget_min"]
                : null;

            $budgetMax = ($_POST["budget_max"] ?? "") !== ""
                ? (float)$_POST["budget_max"]
                : null;

            $leadMessage = trim($_POST["message"] ?? "");
            $leadSource = trim($_POST["lead_source"] ?? "Manual Entry");

            if ($customerName === "" || $phone === "") {

                $message = "Customer name and phone are required.";
                $messageType = "error";

            } elseif (
                $email !== "" &&
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {

                $message = "Please enter a valid email address.";
                $messageType = "error";

            } elseif (
                $budgetMin !== null &&
                $budgetMax !== null &&
                $budgetMin > $budgetMax
            ) {

                $message = "Minimum budget cannot be greater than maximum budget.";
                $messageType = "error";

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO leads (
                        customer_name,
                        phone,
                        email,
                        property_type,
                        interested_location,
                        budget_min,
                        budget_max,
                        message,
                        lead_source,
                        lead_status,
                        priority,
                        assigned_to,
                        created_by
                    )
                    VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        'new', 'medium', NULL, ?
                    )
                ");

                $stmt->execute([
                    $customerName,
                    $phone,
                    $email ?: null,
                    $propertyType ?: null,
                    $location ?: null,
                    $budgetMin,
                    $budgetMax,
                    $leadMessage ?: null,
                    $leadSource,
                    $adminId
                ]);

                $newLeadId = (int)$pdo->lastInsertId();

                manageLeadsLog(
                    $pdo,
                    $newLeadId,
                    $adminId,
                    "lead_created",
                    "Lead created for " . $customerName
                );

                $_SESSION["user_message"] = "Lead added successfully.";
                $_SESSION["user_message_type"] = "success";

                manageLeadsRedirect();
            }
        }

        // --------------------------------------------------
        // ASSIGN / UNASSIGN LEAD
        // --------------------------------------------------

        elseif ($action === "assign_lead" && $canAssignLeads) {

            $leadId = (int)($_POST["lead_id"] ?? 0);

            $assignedTo = ($_POST["assigned_to"] ?? "") !== ""
                ? (int)$_POST["assigned_to"]
                : null;

            $stmt = $pdo->prepare("
                SELECT id, assigned_to, customer_name
                FROM leads
                WHERE id = ?
            ");

            $stmt->execute([$leadId]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lead) {

                $message = "Lead not found.";
                $messageType = "error";

            } else {

                if ($assignedTo !== null) {

                    $check = $pdo->prepare("
                        SELECT id
                        FROM admins
                        WHERE id = ?
                        AND role = 'sales_employee'
                        AND status = 'active'
                    ");

                    $check->execute([$assignedTo]);

                    if (!$check->fetch()) {
                        $message = "Please select an active Sales Employee.";
                        $messageType = "error";
                    }
                }

                if ($messageType !== "error") {

                    $oldAssigned = $lead["assigned_to"] !== null
                        ? (int)$lead["assigned_to"]
                        : null;

                    if ($oldAssigned !== $assignedTo) {

                        $pdo->prepare("
                            UPDATE leads
                            SET assigned_to = ?
                            WHERE id = ?
                        ")->execute([$assignedTo, $leadId]);

                        $oldName = "Unassigned";
                        $newName = "Unassigned";

                        if ($oldAssigned !== null) {

                            $n = $pdo->prepare("
                                SELECT name FROM admins WHERE id = ?
                            ");

                            $n->execute([$oldAssigned]);
                            $oldName = $n->fetchColumn() ?: "Unknown";
                        }

                        if ($assignedTo !== null) {

                            $n = $pdo->prepare("
                                SELECT name FROM admins WHERE id = ?
                            ");

                            $n->execute([$assignedTo]);
                            $newName = $n->fetchColumn() ?: "Unknown";
                        }

                        manageLeadsLog(
                            $pdo,
                            $leadId,
                            $adminId,
                            "lead_assigned",
                            "Lead assignment changed from "
                                . $oldName . " to " . $newName,
                            $oldName,
                            $newName
                        );

                        $_SESSION["user_message"] = "Lead assignment updated.";
                        $_SESSION["user_message_type"] = "success";

                        manageLeadsRedirect();

                    } else {

                        $message = "Assignment is unchanged.";
                    }
                }
            }
        }

        // --------------------------------------------------
        // UPDATE LEAD STATUS AND LINKED PROPERTY ENQUIRY
        // --------------------------------------------------

        elseif ($action === "update_status") {

            $leadId = (int)($_POST["lead_id"] ?? 0);
            $newStatus = trim($_POST["lead_status"] ?? "");

            if (!isset($statuses[$newStatus])) {

                $message = "Invalid lead status.";
                $messageType = "error";

            } elseif ($leadId < 1) {

                $message = "Invalid lead.";
                $messageType = "error";

            } else {

                try {

                    $pdo->beginTransaction();

                    // Lock the lead row before changing its status.
                    $stmt = $pdo->prepare("
                        SELECT
                            id,
                            customer_name,
                            lead_status,
                            assigned_to,
                            property_enquiry_id
                        FROM leads
                        WHERE id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $stmt->execute([$leadId]);
                    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (
                        !$lead ||
                        (
                            $isSalesEmployee &&
                            (int)$lead["assigned_to"] !== $adminId
                        )
                    ) {

                        $pdo->rollBack();

                        $message = "Access denied or lead not found.";
                        $messageType = "error";

                    } else {

                        $oldStatus = $lead["lead_status"];

                        if ($oldStatus !== $newStatus) {

                            // Update CRM lead status.
                            $updateLead = $pdo->prepare("
                                UPDATE leads
                                SET lead_status = ?
                                WHERE id = ?
                            ");

                            $updateLead->execute([
                                $newStatus,
                                $leadId
                            ]);

                            // Update linked property enquiry, if one exists.
                            if (!empty($lead["property_enquiry_id"])) {

                                $updateEnquiry = $pdo->prepare("
                                    UPDATE property_enquiries
                                    SET status = ?
                                    WHERE id = ?
                                ");

                                $updateEnquiry->execute([
                                    $newStatus,
                                    (int)$lead["property_enquiry_id"]
                                ]);
                            }

                            // Record the status change in CRM activity.
                            manageLeadsLog(
                                $pdo,
                                $leadId,
                                $adminId,
                                "status_updated",
                                "Lead status changed from "
                                    . ($statuses[$oldStatus] ?? $oldStatus)
                                    . " to " . $statuses[$newStatus],
                                $oldStatus,
                                $newStatus
                            );

                            // Commit both status updates together.
                            $pdo->commit();

                            $_SESSION["user_message"] =
                                "Lead and linked property enquiry status updated successfully.";

                            $_SESSION["user_message_type"] = "success";

                            manageLeadsRedirect();

                        } else {

                            $pdo->commit();

                            $message = "Lead status is unchanged.";
                            $messageType = "success";
                        }
                    }

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log(
                        "CRM lead and property enquiry status update error: "
                        . $e->getMessage()
                    );

                    $message = "Unable to update lead status right now.";
                    $messageType = "error";
                }
            }
        }

        // --------------------------------------------------
        // UPDATE LEAD PRIORITY
        // --------------------------------------------------

        elseif ($action === "update_priority" && $canManageLeads) {

            $leadId = (int)($_POST["lead_id"] ?? 0);
            $newPriority = trim($_POST["priority"] ?? "");

            if (!isset($priorities[$newPriority])) {

                $message = "Invalid priority selected.";
                $messageType = "error";

            } else {

                $stmt = $pdo->prepare("
                    SELECT id, customer_name, priority
                    FROM leads
                    WHERE id = ?
                ");

                $stmt->execute([$leadId]);
                $lead = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$lead) {

                    $message = "Lead not found.";
                    $messageType = "error";

                } elseif ($lead["priority"] !== $newPriority) {

                    $oldPriority = $lead["priority"];

                    $pdo->prepare("
                        UPDATE leads
                        SET priority = ?
                        WHERE id = ?
                    ")->execute([$newPriority, $leadId]);

                    manageLeadsLog(
                        $pdo,
                        $leadId,
                        $adminId,
                        "priority_updated",
                        "Lead priority changed from "
                            . ucfirst($oldPriority)
                            . " to " . ucfirst($newPriority),
                        $oldPriority,
                        $newPriority
                    );

                    $_SESSION["user_message"] = "Lead priority updated.";
                    $_SESSION["user_message_type"] = "success";

                    manageLeadsRedirect();
                }
            }
        }
    }
}

// --------------------------------------------------
// FILTERS
// --------------------------------------------------

$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");
$propertyFilter = trim($_GET["property_type"] ?? "");
$sourceFilter = trim($_GET["lead_source"] ?? "");
$locationFilter = trim($_GET["location"] ?? "");
$employeeFilter = trim($_GET["assigned_employee"] ?? "");
$assignedFilter = trim($_GET["assigned_filter"] ?? "");
$priorityFilter = trim($_GET["priority"] ?? "");

$where = [];
$params = [];

if ($isSalesEmployee) {

    $where[] = "l.assigned_to = ?";
    $params[] = $adminId;

} elseif (!$canViewAllLeads && !$canManageLeads) {

    $where[] = "l.assigned_to = ?";
    $params[] = $adminId;
}

if ($search !== "") {

    $where[] = "
        (
            l.customer_name LIKE ?
            OR l.phone LIKE ?
            OR l.email LIKE ?
            OR l.interested_location LIKE ?
        )
    ";

    $like = "%" . $search . "%";

    array_push($params, $like, $like, $like, $like);
}

if ($statusFilter !== "" && isset($statuses[$statusFilter])) {

    $where[] = "l.lead_status = ?";
    $params[] = $statusFilter;
}

if ($propertyFilter !== "") {

    $where[] = "l.property_type = ?";
    $params[] = $propertyFilter;
}

if ($sourceFilter !== "") {

    $where[] = "l.lead_source = ?";
    $params[] = $sourceFilter;
}

if ($locationFilter !== "") {

    $where[] = "l.interested_location LIKE ?";
    $params[] = "%" . $locationFilter . "%";
}

if ($priorityFilter !== "" && isset($priorities[$priorityFilter])) {

    $where[] = "l.priority = ?";
    $params[] = $priorityFilter;
}

if ($canAssignLeads && $employeeFilter !== "") {

    $where[] = "l.assigned_to = ?";
    $params[] = (int)$employeeFilter;
}

if ($canAssignLeads && $assignedFilter === "unassigned") {

    $where[] = "l.assigned_to IS NULL";
}

if ($canAssignLeads && $assignedFilter === "assigned") {

    $where[] = "l.assigned_to IS NOT NULL";
}

$whereSql = $where
    ? "WHERE " . implode(" AND ", $where)
    : "";

// --------------------------------------------------
// LEAD WORKLOAD COUNTS
// --------------------------------------------------

$workload = [];
$unassignedCount = 0;

if ($canAssignLeads) {

    $stmt = $pdo->query("
        SELECT
            a.id,
            a.name,
            COUNT(l.id) AS total_leads,

            SUM(CASE WHEN l.lead_status = 'new'
                THEN 1 ELSE 0 END) AS new_count,

            SUM(CASE WHEN l.lead_status = 'contacted'
                THEN 1 ELSE 0 END) AS contacted_count,

            SUM(CASE WHEN l.lead_status = 'interested'
                THEN 1 ELSE 0 END) AS interested_count,

            SUM(CASE WHEN l.lead_status = 'converted'
                THEN 1 ELSE 0 END) AS converted_count

        FROM admins a

        LEFT JOIN leads l ON l.assigned_to = a.id

        WHERE a.role = 'sales_employee'
        AND a.status = 'active'

        GROUP BY a.id, a.name
        ORDER BY a.name
    ");

    $workload = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unassignedCount = (int)$pdo->query("
        SELECT COUNT(*)
        FROM leads
        WHERE assigned_to IS NULL
    ")->fetchColumn();
}

// --------------------------------------------------
// FETCH FILTERED LEADS
// --------------------------------------------------

$sql = "
    SELECT l.*, a.name AS assigned_employee

    FROM leads l

    LEFT JOIN admins a ON l.assigned_to = a.id

    $whereSql

    ORDER BY l.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function priorityBadgeClass($priority) {

    return match ($priority) {
        "high" => "priority-high",
        "low" => "priority-low",
        default => "priority-medium"
    };
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Leads | Olivera Home Developers</title>

<style>

:root {
    --primary: #315f91;
    --primary-dark: #244a73;
    --primary-light: #eaf1f8;
    --background: #f5f7fa;
    --surface: #ffffff;
    --text: #263445;
    --muted: #758397;
    --border: #e4eaf1;
    --success: #25845b;
    --danger: #c45151;
    --warning: #b77a28;
    --shadow: 0 3px 14px rgba(30, 48, 70, 0.055);
}

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: var(--background);
    margin: 0;
    color: var(--text);
    padding: 28px;
    line-height: 1.5;
}

main {
    max-width: 1550px;
    margin: 0 auto;
}

a {
    transition: 0.2s ease;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.page-header h1 {
    font-size: 27px;
    margin: 0 0 5px;
    font-weight: 700;
    letter-spacing: -0.5px;
    color: #25384e;
}

.page-subtitle {
    color: var(--muted);
    font-size: 13px;
    margin: 0;
}

.top-links,
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}

.top-links {
    margin-bottom: 23px;
}

.top-links a,
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 15px;
    border-radius: 7px;
    background: var(--primary);
    color: #fff;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: background 0.2s ease, transform 0.2s ease;
}

.top-links a:hover,
.btn:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.panel {
    background: var(--surface);
    padding: 23px;
    border: 1px solid var(--border);
    border-radius: 11px;
    margin-bottom: 21px;
    box-shadow: var(--shadow);
}

.panel h2 {
    font-size: 17px;
    color: #2d4056;
    margin: 0 0 19px;
    font-weight: 700;
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 17px;
}

.panel-heading h2 {
    margin: 0;
}

.filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
    gap: 15px;
    align-items: end;
}

.filters label,
.form-grid label {
    display: block;
    font-weight: 600;
    font-size: 12px;
    color: #526176;
}

.filters input,
.filters select,
.form-grid input,
.form-grid select,
.form-grid textarea,
td select {
    width: 100%;
    padding: 10px 11px;
    margin-top: 6px;
    border: 1px solid #dce3eb;
    border-radius: 7px;
    background: #fff;
    color: #344458;
    font-family: inherit;
    font-size: 13px;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.filters input:focus,
.filters select:focus,
.form-grid input:focus,
.form-grid select:focus,
.form-grid textarea:focus,
td select:focus {
    border-color: #7399bf;
    box-shadow: 0 0 0 3px rgba(49, 95, 145, 0.09);
}

.form-grid textarea {
    resize: vertical;
    min-height: 43px;
}

.btn-secondary {
    background: #68788b;
}

.btn-secondary:hover {
    background: #526174;
}

.btn-green {
    background: #32815f;
}

.btn-green:hover {
    background: #256849;
}

.btn-red {
    background: #bd5555;
}

.btn-red:hover {
    background: #a44040;
}

.btn-orange {
    background: #b77a28;
}

.btn-orange:hover {
    background: #98621d;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
    gap: 16px;
    align-items: end;
}

.notice {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 19px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid transparent;
}

.notice.success {
    background: #edf7f1;
    color: #256746;
    border-color: #d6ecdf;
}

.notice.error {
    background: #fff0ef;
    color: #a83d3d;
    border-color: #f2d6d3;
}

.workload-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(235px, 1fr));
    gap: 14px;
}

.work-card {
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 17px;
    background: #fcfdff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.work-card:hover {
    border-color: #cbd8e6;
    box-shadow: 0 4px 12px rgba(30, 48, 70, 0.05);
}

.work-card h3 {
    margin: 0 0 11px;
    font-size: 15px;
    color: #344c67;
}

.work-card p {
    color: #68788b;
    font-size: 12px;
    line-height: 1.8;
    margin: 8px 0;
}

.work-card strong {
    color: #33465c;
}

.muted {
    color: var(--muted);
}

.badge {
    display: inline-flex;
    align-items: center;
    border-radius: 20px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 600;
    background: #edf1f5;
    color: #586779;
    white-space: nowrap;
}

.priority-high {
    background: #fff0ef;
    color: #b44747;
}

.priority-medium {
    background: #fff6e5;
    color: #93651f;
}

.priority-low {
    background: #eaf6ef;
    color: #28764d;
}

.status-new {
    background: #eef2f7;
    color: #596c82;
}

.status-contacted {
    background: #eaf2fb;
    color: #356b9e;
}

.status-interested {
    background: #fff5df;
    color: #93651f;
}

.status-site_visit_scheduled {
    background: #f2ecfb;
    color: #7652a2;
}

.status-negotiation {
    background: #fff0e4;
    color: #a36531;
}

.status-converted {
    background: #eaf6ef;
    color: #28764d;
}

.status-lost {
    background: #fff0ef;
    color: #b44747;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 8px;
}

table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    min-width: 1050px;
    background: #fff;
}

th,
td {
    padding: 13px 12px;
    border-bottom: 1px solid #edf0f4;
    text-align: left;
    vertical-align: middle;
}

th {
    background: #f5f7fa;
    color: #56677b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.35px;
    white-space: nowrap;
}

td {
    font-size: 12px;
    color: #4d5d70;
}

tbody tr:last-child td {
    border-bottom: none;
}

tbody tr:hover {
    background: #fafbfd;
}

td strong {
    color: #34485f;
    font-size: 13px;
}

td select {
    min-width: 115px;
    padding: 8px 9px;
    margin: 0;
    font-size: 12px;
    border-radius: 6px;
}

.inline-form {
    margin: 0;
}

.priority-form select {
    min-width: 95px;
    font-weight: 600;
}

.lead-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.lead-actions a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 9px;
    border-radius: 6px;
    text-decoration: none;
    color: #fff;
    background: var(--primary);
    font-size: 11px;
    font-weight: 600;
}

.lead-actions a:hover {
    background: var(--primary-dark);
}

.lead-actions a.edit {
    background: #68788b;
}

.lead-actions a.edit:hover {
    background: #526174;
}

.lead-actions a.delete,
.lead-actions .delete-button {
    background: #bd5555;
    color: #fff;
    border: none;
    padding: 7px 9px;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
}

.lead-actions a.delete:hover,
.lead-actions .delete-button:hover {
    background: #a44040;
}

.empty {
    padding: 35px 20px;
    text-align: center;
    color: #8491a1;
    font-size: 13px;
}

.small {
    font-size: 12px;
}

.table-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 27px;
    height: 25px;
    padding: 0 8px;
    border-radius: 20px;
    background: var(--primary-light);
    color: var(--primary);
    font-size: 12px;
    font-weight: 700;
    margin-left: 5px;
}

.export-form {
    margin: 0;
}

@media (max-width: 900px) {

    body {
        padding: 20px;
    }

    .panel {
        padding: 18px;
    }

    .page-header h1 {
        font-size: 24px;
    }
}

@media (max-width: 600px) {

    body {
        padding: 13px;
    }

    .page-header {
        align-items: flex-start;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .top-links {
        gap: 7px;
    }

    .top-links a,
    .btn {
        padding: 9px 11px;
        font-size: 12px;
    }

    .panel {
        padding: 15px;
        border-radius: 9px;
    }

    .panel h2 {
        font-size: 16px;
    }

    .filters {
        grid-template-columns: 1fr;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .workload-grid {
        grid-template-columns: 1fr;
    }

    .actions {
        width: 100%;
    }

    .actions .btn {
        flex: 1;
    }

    .panel-heading {
        align-items: flex-start;
    }

    .export-form,
    .export-form button {
        width: 100%;
    }
}

</style>

</head>

<body>

<main>

<div class="page-header">

    <div>
        <h1>Manage Leads</h1>
        <p class="page-subtitle">
            Manage customer enquiries, sales assignments and CRM activities.
        </p>
    </div>

</div>

<div class="top-links">

    <a href="dashboard.php">Dashboard</a>
    <a href="lead_followups.php">Lead Follow-ups</a>
    <a href="site_visits.php">Site Visits</a>
    <a href="crm_reports.php">CRM Reports</a>

    <?php if ($canAssignLeads): ?>

        <a href="followup_reminders.php">Follow-up Reminders</a>

    <?php endif; ?>

</div>

<?php if ($message !== ""): ?>

    <div class="notice <?php echo h($messageType); ?>">
        <?php echo h($message); ?>
    </div>

<?php endif; ?>

<?php if ($canManageLeads): ?>

<section class="panel">

<h2>Add New Lead</h2>

<form method="post" class="form-grid">

    <input
        type="hidden"
        name="csrf_token"
        value="<?php echo h($csrfToken); ?>"
    >

    <input
        type="hidden"
        name="action"
        value="add_lead"
    >

    <label>
        Customer Name *

        <input name="customer_name" required>
    </label>

    <label>
        Phone *

        <input name="phone" required>
    </label>

    <label>
        Email

        <input type="email" name="email">
    </label>

    <label>
        Property Type

        <select name="property_type">

            <option value="">Select</option>

            <?php foreach ($propertyTypes as $type): ?>

                <option value="<?php echo h($type); ?>">
                    <?php echo h($type); ?>
                </option>

            <?php endforeach; ?>

        </select>
    </label>

    <label>
        Interested Location

        <input name="interested_location">
    </label>

    <label>
        Minimum Budget

        <input
            type="number"
            name="budget_min"
            min="0"
            step="0.01"
        >
    </label>

    <label>
        Maximum Budget

        <input
            type="number"
            name="budget_max"
            min="0"
            step="0.01"
        >
    </label>

    <label>
        Lead Source

        <select name="lead_source">

            <?php foreach ($leadSources as $source): ?>

                <option
                    value="<?php echo h($source); ?>"
                    <?php echo $source === "Manual Entry" ? "selected" : ""; ?>
                >
                    <?php echo h($source); ?>
                </option>

            <?php endforeach; ?>

        </select>
    </label>

    <label>
        Message

        <textarea name="message" rows="2"></textarea>
    </label>

    <div>

        <button class="btn btn-green" type="submit">
            Add Lead
        </button>

    </div>

</form>

</section>

<?php endif; ?>

<?php if ($canAssignLeads): ?>

<section class="panel">

<h2>Sales Employee Workload Summary</h2>

<div class="workload-grid">

<?php foreach ($workload as $employee): ?>

    <?php

    $total = (int)$employee["total_leads"];

    $level = $total <= 5
        ? "Low"
        : ($total <= 10 ? "Medium" : "High");

    ?>

    <div class="work-card">

        <h3><?php echo h($employee["name"]); ?></h3>

        <p>

            <strong>Total Assigned Leads:</strong>

            <?php echo $total; ?>

            <span class="badge">
                <?php echo h($level); ?> Workload
            </span>

        </p>

        <p>

            New: <?php echo (int)$employee["new_count"]; ?> |

            Contacted: <?php echo (int)$employee["contacted_count"]; ?> |

            Interested: <?php echo (int)$employee["interested_count"]; ?> |

            Converted: <?php echo (int)$employee["converted_count"]; ?>

        </p>

        <a
            href="manage_leads.php?assigned_employee=<?php echo (int)$employee["id"]; ?>"
            class="btn btn-secondary"
        >
            View Leads
        </a>

    </div>

<?php endforeach; ?>

<div class="work-card">

    <h3>Unassigned Leads</h3>

    <p>

        <strong><?php echo $unassignedCount; ?></strong>

        leads need assignment.

    </p>

    <a
        class="btn btn-orange"
        href="manage_leads.php?assigned_filter=unassigned"
    >
        View Unassigned
    </a>

</div>

</div>

</section>

<?php endif; ?>

<section class="panel">

<h2>Search &amp; Filter Leads</h2>

<form method="get" class="filters">

    <label>
        Search

        <input
            type="text"
            name="search"
            value="<?php echo h($search); ?>"
            placeholder="Name, phone, email, location"
        >
    </label>

    <label>
        Lead Status

        <select name="status">

            <option value="">All Statuses</option>

            <?php foreach ($statuses as $key => $label): ?>

                <option
                    value="<?php echo h($key); ?>"
                    <?php echo $statusFilter === $key ? "selected" : ""; ?>
                >
                    <?php echo h($label); ?>
                </option>

            <?php endforeach; ?>

        </select>
    </label>

    <label>
        Property Type

        <select name="property_type">

            <option value="">All Property Types</option>

            <?php foreach ($propertyTypes as $type): ?>

                <option
                    value="<?php echo h($type); ?>"
                    <?php echo $propertyFilter === $type ? "selected" : ""; ?>
                >
                    <?php echo h($type); ?>
                </option>

            <?php endforeach; ?>

        </select>
    </label>

    <label>
        Lead Source

        <select name="lead_source">

            <option value="">All Sources</option>

            <?php foreach ($leadSources as $source): ?>

                <option
                    value="<?php echo h($source); ?>"
                    <?php echo $sourceFilter === $source ? "selected" : ""; ?>
                >
                    <?php echo h($source); ?>
                </option>

            <?php endforeach; ?>

        </select>
    </label>

    <label>
        Interested Location

        <input
            name="location"
            value="<?php echo h($locationFilter); ?>"
            placeholder="Filter location"
        >
    </label>

    <label>
        Priority

        <select name="priority">

            <option value="">All Priorities</option>

            <?php foreach ($priorities as $key => $label): ?>

                <option
                    value="<?php echo h($key); ?>"
                    <?php echo $priorityFilter === $key ? "selected" : ""; ?>
                >
                    <?php echo h($label); ?>
                </option>

            <?php endforeach; ?>

        </select>
    </label>

    <?php if ($canAssignLeads): ?>

    <label>
        Sales Employee

        <select name="assigned_employee">

            <option value="">All Employees</option>

            <?php foreach ($salesEmployees as $employee): ?>

                <option
                    value="<?php echo (int)$employee["id"]; ?>"
                    <?php echo $employeeFilter === (string)$employee["id"] ? "selected" : ""; ?>
                >
                    <?php echo h($employee["name"]); ?>
                </option>

            <?php endforeach; ?>

        </select>
    </label>

    <label>
        Assignment

        <select name="assigned_filter">

            <option value="">All Leads</option>

            <option
                value="assigned"
                <?php echo $assignedFilter === "assigned" ? "selected" : ""; ?>
            >
                Assigned
            </option>

            <option
                value="unassigned"
                <?php echo $assignedFilter === "unassigned" ? "selected" : ""; ?>
            >
                Unassigned
            </option>

        </select>
    </label>

    <?php endif; ?>

    <div class="actions">

        <button class="btn" type="submit">
            Apply Filters
        </button>

        <a
            class="btn btn-secondary"
            href="manage_leads.php"
        >
            Reset
        </a>

    </div>

</form>

</section>

<section class="panel">

<div class="panel-heading">

    <h2>
        Lead List
        <span class="table-count"><?php echo count($leads); ?></span>
    </h2>

    <!-- CSV Export -->

    <form method="get" action="export_leads.php" class="export-form">

        <input type="hidden" name="search" value="<?php echo h($search); ?>">

        <input type="hidden" name="status" value="<?php echo h($statusFilter); ?>">

        <input type="hidden" name="property_type" value="<?php echo h($propertyFilter); ?>">

        <input type="hidden" name="lead_source" value="<?php echo h($sourceFilter); ?>">

        <input type="hidden" name="location" value="<?php echo h($locationFilter); ?>">

        <input type="hidden" name="priority" value="<?php echo h($priorityFilter); ?>">

        <input type="hidden" name="assigned_employee" value="<?php echo h($employeeFilter); ?>">

        <input type="hidden" name="assigned_filter" value="<?php echo h($assignedFilter); ?>">

        <button class="btn btn-green" type="submit">
            Export CSV
        </button>

    </form>

</div>

<div class="table-wrap">

<table>

<thead>

<tr>

    <th>ID</th>
    <th>Customer</th>
    <th>Phone / Email</th>
    <th>Property</th>
    <th>Location</th>
    <th>Budget</th>
    <th>Source</th>
    <th>Priority</th>
    <th>Status</th>

    <?php if ($canAssignLeads): ?>

        <th>Assigned Employee</th>

    <?php endif; ?>

    <th>Created</th>
    <th>Actions</th>

</tr>

</thead>

<tbody>

<?php if (!$leads): ?>

<tr>

    <td
        colspan="<?php echo 11 + ($canAssignLeads ? 1 : 0); ?>"
        class="empty"
    >
        No leads found for these filters.
    </td>

</tr>

<?php else: ?>

<?php foreach ($leads as $lead): ?>

<tr>

    <td>
        <?php echo (int)$lead["id"]; ?>
    </td>

    <td>
        <strong><?php echo h($lead["customer_name"]); ?></strong>
    </td>

    <td>
        <?php echo h($lead["phone"]); ?>
        <br>
        <?php echo h($lead["email"]); ?>
    </td>

    <td>
        <?php echo h($lead["property_type"]); ?>
    </td>

    <td>
        <?php echo h($lead["interested_location"]); ?>
    </td>

    <td>

        <?php if ($lead["budget_min"] !== null || $lead["budget_max"] !== null): ?>

            ₹<?php echo number_format((float)$lead["budget_min"]); ?>

            –

            ₹<?php echo number_format((float)$lead["budget_max"]); ?>

        <?php else: ?>

            -

        <?php endif; ?>

    </td>

    <td>
        <?php echo h($lead["lead_source"]); ?>
    </td>

    <!-- Priority Management -->

    <td>

        <?php if ($canManageLeads): ?>

        <form method="post" class="inline-form priority-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo h($csrfToken); ?>"
            >

            <input
                type="hidden"
                name="action"
                value="update_priority"
            >

            <input
                type="hidden"
                name="lead_id"
                value="<?php echo (int)$lead["id"]; ?>"
            >

            <select
                name="priority"
                class="<?php echo h(priorityBadgeClass($lead["priority"] ?? "medium")); ?>"
                onchange="this.form.submit()"
            >

                <?php foreach ($priorities as $key => $label): ?>

                    <option
                        value="<?php echo h($key); ?>"
                        <?php echo ($lead["priority"] ?? "medium") === $key ? "selected" : ""; ?>
                    >
                        <?php echo h($label); ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </form>

        <?php else: ?>

        <span class="badge <?php echo h(priorityBadgeClass($lead["priority"] ?? "medium")); ?>">

            <?php echo h(ucfirst($lead["priority"] ?? "medium")); ?>

        </span>

        <?php endif; ?>

    </td>

    <!-- Lead Status -->

    <td>

        <form method="post" class="inline-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo h($csrfToken); ?>"
            >

            <input
                type="hidden"
                name="action"
                value="update_status"
            >

            <input
                type="hidden"
                name="lead_id"
                value="<?php echo (int)$lead["id"]; ?>"
            >

            <select
                name="lead_status"
                onchange="this.form.submit()"
                class="status-<?php echo h($lead["lead_status"]); ?>"
            >

                <?php foreach ($statuses as $key => $label): ?>

                    <option
                        value="<?php echo h($key); ?>"
                        <?php echo $lead["lead_status"] === $key ? "selected" : ""; ?>
                    >
                        <?php echo h($label); ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </td>

    <!-- Sales Employee Assignment -->

    <?php if ($canAssignLeads): ?>

    <td>

        <form method="post" class="inline-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo h($csrfToken); ?>"
            >

            <input
                type="hidden"
                name="action"
                value="assign_lead"
            >

            <input
                type="hidden"
                name="lead_id"
                value="<?php echo (int)$lead["id"]; ?>"
            >

            <select
                name="assigned_to"
                onchange="this.form.submit()"
            >

                <option value="">Unassigned</option>

                <?php foreach ($salesEmployees as $employee): ?>

                    <option
                        value="<?php echo (int)$employee["id"]; ?>"
                        <?php echo (string)$lead["assigned_to"] === (string)$employee["id"] ? "selected" : ""; ?>
                    >
                        <?php echo h($employee["name"]); ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </form>

        <?php echo h($lead["assigned_employee"] ?? "Unassigned"); ?>

    </td>

    <?php endif; ?>

    <td>
        <?php echo h(date("d-m-Y", strtotime($lead["created_at"]))); ?>
    </td>

    <!-- Lead Actions -->

    <td>

        <div class="lead-actions">

            <a href="view_lead.php?id=<?php echo (int)$lead["id"]; ?>">
                View
            </a>

            <?php if ($canManageLeads): ?>

                <a
                    class="edit"
                    href="edit_lead.php?id=<?php echo (int)$lead["id"]; ?>"
                >
                    Edit
                </a>

                <form
                    method="POST"
                    action="delete_lead.php"
                    class="inline-form"
                    onsubmit="return confirm('Are you sure you want to permanently delete this lead?');"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo h($csrfToken); ?>"
                    >

                    <input
                        type="hidden"
                        name="lead_id"
                        value="<?php echo (int)$lead["id"]; ?>"
                    >

                    <button
                        type="submit"
                        class="delete-button"
                    >
                        Delete
                    </button>

                </form>

            <?php endif; ?>

        </div>

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
