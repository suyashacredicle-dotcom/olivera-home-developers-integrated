
<?php

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/lead_activity_logger.php";

$message = "";
$messageType = "success";

$adminId = (int)($_SESSION["admin_id"] ?? 0);
$currentRole = $_SESSION["admin_role"] ?? "";

$canManageLeads = hasPermission("manage_leads");
$canAssignLeads = ($currentRole === "super_admin");
$canViewAllLeads = hasPermission("view_all_leads");
$canViewAssignedLeads = hasPermission("view_assigned_leads");

// --------------------------------------------------
// ACCESS CONTROL
// --------------------------------------------------

if (!$canManageLeads && !$canViewAllLeads && !$canViewAssignedLeads) {
    http_response_code(403);
    exit("Access Denied! You do not have permission to view leads.");
}

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    $_SESSION["csrf_token"] === ""
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// --------------------------------------------------
// FLASH MESSAGE
// --------------------------------------------------

if (isset($_SESSION["user_message"])) {
    $message = $_SESSION["user_message"];
    $messageType = $_SESSION["user_message_type"] ?? "success";

    unset($_SESSION["user_message"], $_SESSION["user_message_type"]);
}

// --------------------------------------------------
// OPTIONS
// --------------------------------------------------

$priorities = [
    "high" => "High",
    "medium" => "Medium",
    "low" => "Low"
];

$statuses = [
    "new" => "New",
    "contacted" => "Contacted",
    "interested" => "Interested",
    "site_visit_scheduled" => "Site Visit Scheduled",
    "negotiation" => "Negotiation",
    "converted" => "Converted",
    "lost" => "Lost"
];

$propertyTypes = [
    "Plot",
    "Apartment",
    "Villa",
    "Independent House",
    "Commercial",
    "Other"
];

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

// --------------------------------------------------
// ACTIVE SALES EMPLOYEES
// --------------------------------------------------

$stmt = $pdo->query("
    SELECT id, name
    FROM admins
    WHERE role = 'sales_employee'
    AND status = 'active'
    ORDER BY name ASC
");

$salesEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function manageLeadsEscape($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function manageLeadsRedirect(): void
{
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
): void {
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

function manageLeadsPriorityClass($priority): string
{
    return match ($priority) {
        "high" => "priority-high",
        "low" => "priority-low",
        default => "priority-medium"
    };
}

function manageLeadsStatusClass($status): string
{
    return "status-" . preg_replace("/[^a-z0-9_-]/i", "", (string)$status);
}

// --------------------------------------------------
// POST ACTIONS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

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

            } elseif (!in_array($leadSource, $leadSources, true)) {

                $message = "Invalid lead source selected.";
                $messageType = "error";

            } elseif (
                $propertyType !== "" &&
                !in_array($propertyType, $propertyTypes, true)
            ) {

                $message = "Invalid property type selected.";
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
                LIMIT 1
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

                    $canUpdateThisLead = $lead && (
                        $canManageLeads ||
                        $canViewAllLeads ||
                        (int)($lead["assigned_to"] ?? 0) === $adminId
                    );

                    if (!$canUpdateThisLead) {

                        $pdo->rollBack();

                        $message = "Access denied or lead not found.";
                        $messageType = "error";

                    } else {

                        $oldStatus = $lead["lead_status"];

                        if ($oldStatus !== $newStatus) {

                            $updateLead = $pdo->prepare("
                                UPDATE leads
                                SET lead_status = ?
                                WHERE id = ?
                            ");

                            $updateLead->execute([
                                $newStatus,
                                $leadId
                            ]);

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

                            $pdo->commit();

                            $_SESSION["user_message"] =
                                "Lead and linked property enquiry status updated successfully.";

                            $_SESSION["user_message_type"] = "success";

                            manageLeadsRedirect();

                        } else {

                            $pdo->commit();

                            $message = "Lead status is unchanged.";
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

                } else {
                    $message = "Lead priority is unchanged.";
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

// Restrict assigned-only users to their own leads.
if (!$canManageLeads && !$canViewAllLeads) {
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

if (
    $propertyFilter !== "" &&
    in_array($propertyFilter, $propertyTypes, true)
) {
    $where[] = "l.property_type = ?";
    $params[] = $propertyFilter;
}

if (
    $sourceFilter !== "" &&
    in_array($sourceFilter, $leadSources, true)
) {
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

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Leads | Olivera Home Developers</title>

<link rel="stylesheet" href="assets/css/admin-sidebar.css">

<style>
:root {
    --navy: #071b38;
    --navy-light: #102b50;
    --gold: #d6a63b;
    --gold-light: #edca76;
    --ivory: #f7f5ef;
    --white: #ffffff;
    --text: #263449;
    --muted: #718096;
    --border: #e5e7eb;
    --success: #25845b;
    --danger: #c45151;
    --warning: #b77a28;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: var(--ivory);
    color: var(--text);
    line-height: 1.5;
}

.admin-layout {
    display: flex;
    min-height: 100vh;
}

/* MAIN CONTENT */

.main-content {
    margin-left: 255px;
    width: calc(100% - 255px);
    min-width: 0;
    padding: 30px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 24px;
}

.page-header h1 {
    color: var(--navy);
    font-size: 27px;
    margin: 0 0 7px;
}

.page-subtitle {
    color: var(--muted);
    font-size: 13px;
    margin: 0;
}

.admin-user {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 11px 16px;
    color: var(--navy);
    font-size: 13px;
    font-weight: bold;
}

.top-links {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-bottom: 22px;
}

.top-links a,
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
    border-radius: 7px;
    background: var(--navy);
    color: var(--white);
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: 0.2s ease;
}

.top-links a:hover,
.btn:hover {
    background: var(--navy-light);
    transform: translateY(-1px);
}

.panel {
    background: var(--white);
    padding: 23px;
    border: 1px solid var(--border);
    border-radius: 11px;
    margin-bottom: 21px;
    box-shadow: 0 4px 18px rgba(7, 27, 56, 0.04);
}

.panel h2 {
    font-size: 17px;
    color: var(--navy);
    margin: 0 0 19px;
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
}

.filters input:focus,
.filters select:focus,
.form-grid input:focus,
.form-grid select:focus,
.form-grid textarea:focus,
td select:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(214,166,59,0.12);
}

.form-grid textarea {
    resize: vertical;
    min-height: 43px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
    gap: 16px;
    align-items: end;
}

.btn-secondary {
    background: #64748b;
}

.btn-secondary:hover {
    background: #475569;
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

.btn-orange {
    background: #b77a28;
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
    background: #fcfaf5;
}

.work-card h3 {
    margin: 0 0 11px;
    font-size: 15px;
    color: var(--navy);
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
    background: var(--navy);
    color: var(--white);
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
    background: #fcfaf5;
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

.lead-actions a,
.lead-actions button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 9px;
    border-radius: 6px;
    text-decoration: none;
    color: #fff;
    background: var(--navy);
    border: none;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}

.lead-actions a:hover,
.lead-actions button:hover {
    background: var(--navy-light);
}

.lead-actions a.edit {
    background: #68788b;
}

.lead-actions button.delete-button {
    background: #bd5555;
}

.lead-actions button.delete-button:hover {
    background: #a44040;
}

.empty {
    padding: 35px 20px;
    text-align: center;
    color: #8491a1;
    font-size: 13px;
}

.table-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 27px;
    height: 25px;
    padding: 0 8px;
    border-radius: 20px;
    background: #fdf6e5;
    color: #795a17;
    font-size: 12px;
    font-weight: 700;
    margin-left: 5px;
}

.export-form {
    margin: 0;
}

@media (max-width: 1000px) {
    .main-content {
        margin-left: 220px;
        width: calc(100% - 220px);
        padding: 22px;
    }
}

@media (max-width: 700px) {
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 15px;
    }

    .page-header h1 {
        font-size: 23px;
    }

    .panel {
        padding: 15px;
    }

    .filters,
    .form-grid,
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

    .top-links a {
        padding: 9px 11px;
        font-size: 12px;
    }
}
</style>

</head>

<body>

<div class="admin-layout">

    <!-- SHARED ADMIN SIDEBAR -->

    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="page-header">

            <div>
                <h1>Manage Leads</h1>
                <p class="page-subtitle">
                    Manage customer enquiries, sales assignments and CRM activities.
                </p>
            </div>

            <div class="admin-user">
                <?php echo manageLeadsEscape($_SESSION["admin_name"] ?? "Administrator"); ?>
            </div>

        </div>

        <div class="top-links">
            <a href="lead_followups.php">Lead Follow-ups</a>
            <a href="site_visits.php">Site Visits</a>
            <a href="crm_reports.php">CRM Reports</a>
            <a href="followup_reminders.php">Follow-up Reminders</a>
        </div>

        <?php if ($message !== ""): ?>
            <div class="notice <?php echo manageLeadsEscape($messageType); ?>">
                <?php echo manageLeadsEscape($message); ?>
            </div>
        <?php endif; ?>

        <!-- ADD NEW LEAD -->

        <?php if ($canManageLeads): ?>

        <section class="panel">

            <h2>Add New Lead</h2>

            <form method="post" class="form-grid">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo manageLeadsEscape($csrfToken); ?>"
                >

                <input type="hidden" name="action" value="add_lead">

                <label>
                    Customer Name *

                    <input name="customer_name" required maxlength="190">
                </label>

                <label>
                    Phone *

                    <input name="phone" required maxlength="30">
                </label>

                <label>
                    Email

                    <input type="email" name="email" maxlength="190">
                </label>

                <label>
                    Property Type

                    <select name="property_type">
                        <option value="">Select</option>

                        <?php foreach ($propertyTypes as $type): ?>
                            <option value="<?php echo manageLeadsEscape($type); ?>">
                                <?php echo manageLeadsEscape($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Interested Location

                    <input name="interested_location" maxlength="190">
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
                                value="<?php echo manageLeadsEscape($source); ?>"
                                <?php echo $source === "Manual Entry" ? "selected" : ""; ?>
                            >
                                <?php echo manageLeadsEscape($source); ?>
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

        <!-- SALES EMPLOYEE WORKLOAD -->

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

                        <h3><?php echo manageLeadsEscape($employee["name"]); ?></h3>

                        <p>
                            <strong>Total Assigned Leads:</strong>
                            <?php echo $total; ?>

                            <span class="badge">
                                <?php echo manageLeadsEscape($level); ?> Workload
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

        <!-- SEARCH AND FILTERS -->

        <section class="panel">

            <h2>Search &amp; Filter Leads</h2>

            <form method="get" class="filters">

                <label>
                    Search

                    <input
                        type="text"
                        name="search"
                        value="<?php echo manageLeadsEscape($search); ?>"
                        placeholder="Name, phone, email, location"
                    >
                </label>

                <label>
                    Lead Status

                    <select name="status">
                        <option value="">All Statuses</option>

                        <?php foreach ($statuses as $key => $label): ?>
                            <option
                                value="<?php echo manageLeadsEscape($key); ?>"
                                <?php echo $statusFilter === $key ? "selected" : ""; ?>
                            >
                                <?php echo manageLeadsEscape($label); ?>
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
                                value="<?php echo manageLeadsEscape($type); ?>"
                                <?php echo $propertyFilter === $type ? "selected" : ""; ?>
                            >
                                <?php echo manageLeadsEscape($type); ?>
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
                                value="<?php echo manageLeadsEscape($source); ?>"
                                <?php echo $sourceFilter === $source ? "selected" : ""; ?>
                            >
                                <?php echo manageLeadsEscape($source); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Interested Location

                    <input
                        name="location"
                        value="<?php echo manageLeadsEscape($locationFilter); ?>"
                        placeholder="Filter location"
                    >
                </label>

                <label>
                    Priority

                    <select name="priority">
                        <option value="">All Priorities</option>

                        <?php foreach ($priorities as $key => $label): ?>
                            <option
                                value="<?php echo manageLeadsEscape($key); ?>"
                                <?php echo $priorityFilter === $key ? "selected" : ""; ?>
                            >
                                <?php echo manageLeadsEscape($label); ?>
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
                                    <?php echo manageLeadsEscape($employee["name"]); ?>
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

                    <a class="btn btn-secondary" href="manage_leads.php">
                        Reset
                    </a>
                </div>

            </form>

        </section>

        <!-- LEAD LIST -->

        <section class="panel">

            <div class="panel-heading">

                <h2>
                    Lead List
                    <span class="table-count"><?php echo count($leads); ?></span>
                </h2>

                <form method="get" action="export_leads.php" class="export-form">

                    <input type="hidden" name="search" value="<?php echo manageLeadsEscape($search); ?>">
                    <input type="hidden" name="status" value="<?php echo manageLeadsEscape($statusFilter); ?>">
                    <input type="hidden" name="property_type" value="<?php echo manageLeadsEscape($propertyFilter); ?>">
                    <input type="hidden" name="lead_source" value="<?php echo manageLeadsEscape($sourceFilter); ?>">
                    <input type="hidden" name="location" value="<?php echo manageLeadsEscape($locationFilter); ?>">
                    <input type="hidden" name="priority" value="<?php echo manageLeadsEscape($priorityFilter); ?>">
                    <input type="hidden" name="assigned_employee" value="<?php echo manageLeadsEscape($employeeFilter); ?>">
                    <input type="hidden" name="assigned_filter" value="<?php echo manageLeadsEscape($assignedFilter); ?>">

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

                                    <td><?php echo (int)$lead["id"]; ?></td>

                                    <td>
                                        <strong>
                                            <?php echo manageLeadsEscape($lead["customer_name"]); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php echo manageLeadsEscape($lead["phone"]); ?>
                                        <br>
                                        <?php echo manageLeadsEscape($lead["email"]); ?>
                                    </td>

                                    <td>
                                        <?php echo manageLeadsEscape($lead["property_type"]); ?>
                                    </td>

                                    <td>
                                        <?php echo manageLeadsEscape($lead["interested_location"]); ?>
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
                                        <?php echo manageLeadsEscape($lead["lead_source"]); ?>
                                    </td>

                                    <!-- PRIORITY -->

                                    <td>

                                        <?php if ($canManageLeads): ?>

                                            <form method="post" class="inline-form priority-form">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo manageLeadsEscape($csrfToken); ?>"
                                                >

                                                <input type="hidden" name="action" value="update_priority">

                                                <input
                                                    type="hidden"
                                                    name="lead_id"
                                                    value="<?php echo (int)$lead["id"]; ?>"
                                                >

                                                <select
                                                    name="priority"
                                                    class="<?php echo manageLeadsPriorityClass($lead["priority"] ?? "medium"); ?>"
                                                    onchange="this.form.submit()"
                                                >

                                                    <?php foreach ($priorities as $key => $label): ?>
                                                        <option
                                                            value="<?php echo manageLeadsEscape($key); ?>"
                                                            <?php echo ($lead["priority"] ?? "medium") === $key ? "selected" : ""; ?>
                                                        >
                                                            <?php echo manageLeadsEscape($label); ?>
                                                        </option>
                                                    <?php endforeach; ?>

                                                </select>

                                            </form>

                                        <?php else: ?>

                                            <span class="badge <?php echo manageLeadsPriorityClass($lead["priority"] ?? "medium"); ?>">
                                                <?php echo manageLeadsEscape(ucfirst($lead["priority"] ?? "medium")); ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <!-- STATUS -->

                                    <td>

                                        <form method="post" class="inline-form">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo manageLeadsEscape($csrfToken); ?>"
                                            >

                                            <input type="hidden" name="action" value="update_status">

                                            <input
                                                type="hidden"
                                                name="lead_id"
                                                value="<?php echo (int)$lead["id"]; ?>"
                                            >

                                            <select
                                                name="lead_status"
                                                onchange="this.form.submit()"
                                                class="<?php echo manageLeadsStatusClass($lead["lead_status"]); ?>"
                                            >

                                                <?php foreach ($statuses as $key => $label): ?>
                                                    <option
                                                        value="<?php echo manageLeadsEscape($key); ?>"
                                                        <?php echo $lead["lead_status"] === $key ? "selected" : ""; ?>
                                                    >
                                                        <?php echo manageLeadsEscape($label); ?>
                                                    </option>
                                                <?php endforeach; ?>

                                            </select>

                                        </form>

                                    </td>

                                    <!-- ASSIGNMENT -->

                                    <?php if ($canAssignLeads): ?>

                                        <td>

                                            <form method="post" class="inline-form">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo manageLeadsEscape($csrfToken); ?>"
                                                >

                                                <input type="hidden" name="action" value="assign_lead">

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
                                                            <?php echo manageLeadsEscape($employee["name"]); ?>
                                                        </option>
                                                    <?php endforeach; ?>

                                                </select>

                                            </form>

                                        </td>

                                    <?php endif; ?>

                                    <td>
                                        <?php
                                        echo !empty($lead["created_at"])
                                            ? manageLeadsEscape(date("d-m-Y", strtotime($lead["created_at"])))
                                            : "-";
                                        ?>
                                    </td>

                                    <!-- ACTIONS -->

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
                                                        value="<?php echo manageLeadsEscape($csrfToken); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="lead_id"
                                                        value="<?php echo (int)$lead["id"]; ?>"
                                                    >

                                                    <button type="submit" class="delete-button">
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

</div>

</body>
</html>
