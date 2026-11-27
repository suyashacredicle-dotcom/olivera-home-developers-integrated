
<?php

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/lead_activity_logger.php";

// --------------------------------------------------
// ACCESS CONTROL
// --------------------------------------------------

if (!hasPermission("view_assigned_leads")) {
    http_response_code(403);
    exit("Access Denied! You do not have permission to view assigned leads.");
}

$adminId = (int)($_SESSION["admin_id"] ?? 0);

if ($adminId < 1) {
    http_response_code(403);
    exit("Invalid admin session.");
}

// --------------------------------------------------
// HELPERS
// --------------------------------------------------

function assignedLeadsEscape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function assignedLeadsRedirect(): void
{
    header("Location: my_assigned_leads.php");
    exit;
}

function assignedLeadsStatusClass($status): string
{
    return "status-" . preg_replace("/[^a-z0-9_-]/i", "", (string)$status);
}

// --------------------------------------------------
// CSRF
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
// OPTIONS
// --------------------------------------------------

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
// FLASH MESSAGE
// --------------------------------------------------

$message = "";
$messageType = "success";

if (isset($_SESSION["user_message"])) {
    $message = $_SESSION["user_message"];
    $messageType = $_SESSION["user_message_type"] ?? "success";

    unset($_SESSION["user_message"], $_SESSION["user_message_type"]);
}

// --------------------------------------------------
// UPDATE ASSIGNED LEAD STATUS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !is_string($_POST["csrf_token"] ?? null) ||
        !hash_equals($csrfToken, $_POST["csrf_token"])
    ) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";

    } elseif (($_POST["action"] ?? "") === "update_status") {

        $leadId = (int)($_POST["lead_id"] ?? 0);
        $newStatus = trim($_POST["lead_status"] ?? "");

        if ($leadId < 1 || !isset($statuses[$newStatus])) {

            $message = "Invalid lead or status selected.";
            $messageType = "error";

        } else {

            try {
                $pdo->beginTransaction();

                // Lock and verify that this lead belongs to this employee.
                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        customer_name,
                        lead_status,
                        assigned_to,
                        property_enquiry_id
                    FROM leads
                    WHERE id = ?
                    AND assigned_to = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmt->execute([$leadId, $adminId]);
                $lead = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$lead) {

                    $pdo->rollBack();

                    $message = "This lead is not assigned to your account, or it no longer exists.";
                    $messageType = "error";

                } elseif ($lead["lead_status"] === $newStatus) {

                    $pdo->commit();

                    $message = "Lead status is unchanged.";

                } else {

                    $oldStatus = $lead["lead_status"];

                    $stmt = $pdo->prepare("
                        UPDATE leads
                        SET lead_status = ?
                        WHERE id = ?
                        AND assigned_to = ?
                    ");

                    $stmt->execute([
                        $newStatus,
                        $leadId,
                        $adminId
                    ]);

                    // Keep linked property enquiry status synchronized.
                    if (!empty($lead["property_enquiry_id"])) {

                        $stmt = $pdo->prepare("
                            UPDATE property_enquiries
                            SET status = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $newStatus,
                            (int)$lead["property_enquiry_id"]
                        ]);
                    }

                    logLeadActivity(
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

                    assignedLeadsRedirect();
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    "Assigned lead status update error: " . $e->getMessage()
                );

                $message = "Unable to update lead status right now.";
                $messageType = "error";
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

$where = ["l.assigned_to = ?"];
$params = [$adminId];

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

$whereSql = "WHERE " . implode(" AND ", $where);

// --------------------------------------------------
// SUMMARY COUNTS
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN lead_status = 'new' THEN 1 ELSE 0 END) AS new_count,
        SUM(CASE WHEN lead_status = 'contacted' THEN 1 ELSE 0 END) AS contacted_count,
        SUM(CASE WHEN lead_status = 'interested' THEN 1 ELSE 0 END) AS interested_count,
        SUM(CASE WHEN lead_status = 'site_visit_scheduled' THEN 1 ELSE 0 END) AS visit_count,
        SUM(CASE WHEN lead_status = 'negotiation' THEN 1 ELSE 0 END) AS negotiation_count,
        SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END) AS converted_count,
        SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END) AS lost_count
    FROM leads
    WHERE assigned_to = ?
");

$stmt->execute([$adminId]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalAssigned = (int)($summary["total"] ?? 0);

// --------------------------------------------------
// FETCH ASSIGNED LEADS
// --------------------------------------------------

$sql = "
    SELECT l.*
    FROM leads l
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

<title>My Assigned Leads | Olivera Home Developers</title>

<link rel="stylesheet" href="assets/css/admin.css">
<link rel="stylesheet" href="assets/css/admin-sidebar.css">

<style>
:root {
    --navy: #071b38;
    --navy-light: #102b50;
    --gold: #d6a63b;
    --ivory: #f7f5ef;
    --white: #ffffff;
    --text: #263449;
    --muted: #718096;
    --border: #e5e7eb;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--ivory);
    color: var(--text);
    font-family: Arial, Helvetica, sans-serif;
    line-height: 1.5;
}

.main-content {
    margin-left: 255px;
    min-height: 100vh;
    padding: 30px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 22px;
}

.page-header h1 {
    color: var(--navy);
    font-size: 27px;
    margin: 0 0 6px;
}

.page-subtitle {
    color: var(--muted);
    font-size: 13px;
    margin: 0;
}

.admin-user {
    padding: 11px 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--white);
    color: var(--navy);
    font-size: 13px;
    font-weight: bold;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
    gap: 13px;
    margin-bottom: 22px;
}

.summary-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 16px;
}

.summary-card span {
    display: block;
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 7px;
}

.summary-card strong {
    display: block;
    color: var(--navy);
    font-size: 23px;
}

.panel {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 11px;
    padding: 22px;
    margin-bottom: 21px;
    box-shadow: 0 4px 18px rgba(7, 27, 56, 0.04);
}

.panel h2 {
    margin: 0 0 17px;
    color: var(--navy);
    font-size: 17px;
}

.filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
    gap: 14px;
    align-items: end;
}

.filters label {
    display: block;
    color: #526176;
    font-size: 12px;
    font-weight: 600;
}

.filters input,
.filters select,
td select {
    width: 100%;
    padding: 10px;
    margin-top: 6px;
    border: 1px solid #dce3eb;
    border-radius: 7px;
    background: #fff;
    color: #344458;
    font-family: inherit;
    font-size: 13px;
}

.filters input:focus,
.filters select:focus,
td select:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(214,166,59,.12);
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 10px 15px;
    border: 0;
    border-radius: 7px;
    background: var(--navy);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}

.btn:hover {
    background: var(--navy-light);
}

.btn-secondary {
    background: #64748b;
}

.notice {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 19px;
    font-size: 13px;
    font-weight: 600;
}

.notice.success {
    background: #edf7f1;
    color: #256746;
    border: 1px solid #d6ecdf;
}

.notice.error {
    background: #fff0ef;
    color: #a83d3d;
    border: 1px solid #f2d6d3;
}

.table-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
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
    margin-left: 5px;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 8px;
}

table {
    width: 100%;
    min-width: 1000px;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
}

th, td {
    padding: 13px 12px;
    border-bottom: 1px solid #edf0f4;
    text-align: left;
    vertical-align: middle;
}

th {
    background: var(--navy);
    color: #fff;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .35px;
    white-space: nowrap;
}

td {
    color: #4d5d70;
    font-size: 12px;
}

tbody tr:last-child td {
    border-bottom: 0;
}

tbody tr:hover {
    background: #fcfaf5;
}

td strong {
    color: #34485f;
    font-size: 13px;
}

td select {
    min-width: 145px;
    margin: 0;
    padding: 8px;
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

.badge {
    display: inline-flex;
    padding: 4px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
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

.empty {
    padding: 35px 20px;
    text-align: center;
    color: #8491a1;
}

@media (max-width: 1000px) {
    .main-content {
        margin-left: 220px;
        padding: 22px;
    }
}

@media (max-width: 700px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
    }

    .page-header h1 {
        font-size: 23px;
    }

    .panel {
        padding: 15px;
    }

    .filters {
        grid-template-columns: 1fr;
    }
}
</style>

</head>

<body>

<?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

<main class="main-content">

    <div class="page-header">

        <div>
            <h1>My Assigned Leads</h1>
            <p class="page-subtitle">
                View and manage customer enquiries assigned to your account.
            </p>
        </div>

        <div class="admin-user">
            <?php echo assignedLeadsEscape($_SESSION["admin_name"] ?? "Sales Employee"); ?>
        </div>

    </div>

    <?php if ($message !== ""): ?>
        <div class="notice <?php echo assignedLeadsEscape($messageType); ?>">
            <?php echo assignedLeadsEscape($message); ?>
        </div>
    <?php endif; ?>

    <div class="summary-grid">

        <div class="summary-card">
            <span>Total Assigned Leads</span>
            <strong><?php echo $totalAssigned; ?></strong>
        </div>

        <div class="summary-card">
            <span>New</span>
            <strong><?php echo (int)($summary["new_count"] ?? 0); ?></strong>
        </div>

        <div class="summary-card">
            <span>Contacted</span>
            <strong><?php echo (int)($summary["contacted_count"] ?? 0); ?></strong>
        </div>

        <div class="summary-card">
            <span>Interested</span>
            <strong><?php echo (int)($summary["interested_count"] ?? 0); ?></strong>
        </div>

        <div class="summary-card">
            <span>Site Visit Scheduled</span>
            <strong><?php echo (int)($summary["visit_count"] ?? 0); ?></strong>
        </div>

        <div class="summary-card">
            <span>Negotiation</span>
            <strong><?php echo (int)($summary["negotiation_count"] ?? 0); ?></strong>
        </div>

        <div class="summary-card">
            <span>Converted</span>
            <strong><?php echo (int)($summary["converted_count"] ?? 0); ?></strong>
        </div>

        <div class="summary-card">
            <span>Lost</span>
            <strong><?php echo (int)($summary["lost_count"] ?? 0); ?></strong>
        </div>

    </div>

    <section class="panel">

        <h2>Search &amp; Filter My Leads</h2>

        <form method="get" class="filters">

            <label>
                Search

                <input
                    type="text"
                    name="search"
                    value="<?php echo assignedLeadsEscape($search); ?>"
                    placeholder="Name, phone, email, location"
                >
            </label>

            <label>
                Lead Status

                <select name="status">
                    <option value="">All Statuses</option>

                    <?php foreach ($statuses as $key => $label): ?>
                        <option
                            value="<?php echo assignedLeadsEscape($key); ?>"
                            <?php echo $statusFilter === $key ? "selected" : ""; ?>
                        >
                            <?php echo assignedLeadsEscape($label); ?>
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
                            value="<?php echo assignedLeadsEscape($type); ?>"
                            <?php echo $propertyFilter === $type ? "selected" : ""; ?>
                        >
                            <?php echo assignedLeadsEscape($type); ?>
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
                            value="<?php echo assignedLeadsEscape($source); ?>"
                            <?php echo $sourceFilter === $source ? "selected" : ""; ?>
                        >
                            <?php echo assignedLeadsEscape($source); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Interested Location

                <input
                    type="text"
                    name="location"
                    value="<?php echo assignedLeadsEscape($locationFilter); ?>"
                    placeholder="Filter by location"
                >
            </label>

            <div>
                <button type="submit" class="btn">Apply Filters</button>
                <a href="my_assigned_leads.php" class="btn btn-secondary">Reset</a>
            </div>

        </form>

    </section>

    <section class="panel">

        <div class="table-heading">
            <h2>
                My Lead List
                <span class="table-count"><?php echo count($leads); ?></span>
            </h2>
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
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!$leads): ?>

                        <tr>
                            <td colspan="11" class="empty">
                                No leads are currently assigned to you matching these filters.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($leads as $lead): ?>

                            <tr>

                                <td><?php echo (int)$lead["id"]; ?></td>

                                <td>
                                    <strong>
                                        <?php echo assignedLeadsEscape($lead["customer_name"]); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php echo assignedLeadsEscape($lead["phone"]); ?>
                                    <br>
                                    <?php echo assignedLeadsEscape($lead["email"]); ?>
                                </td>

                                <td>
                                    <?php echo assignedLeadsEscape($lead["property_type"]); ?>
                                </td>

                                <td>
                                    <?php echo assignedLeadsEscape($lead["interested_location"]); ?>
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
                                    <?php echo assignedLeadsEscape($lead["lead_source"]); ?>
                                </td>

                                <td>
                                    <span class="badge priority-<?php echo assignedLeadsEscape($lead["priority"] ?? "medium"); ?>">
                                        <?php echo assignedLeadsEscape(ucfirst($lead["priority"] ?? "medium")); ?>
                                    </span>
                                </td>

                                <td>

                                    <form method="post">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo assignedLeadsEscape($csrfToken); ?>"
                                        >

                                        <input type="hidden" name="action" value="update_status">

                                        <input
                                            type="hidden"
                                            name="lead_id"
                                            value="<?php echo (int)$lead["id"]; ?>"
                                        >

                                        <select
                                            name="lead_status"
                                            class="<?php echo assignedLeadsStatusClass($lead["lead_status"]); ?>"
                                            onchange="this.form.submit()"
                                        >

                                            <?php foreach ($statuses as $key => $label): ?>
                                                <option
                                                    value="<?php echo assignedLeadsEscape($key); ?>"
                                                    <?php echo $lead["lead_status"] === $key ? "selected" : ""; ?>
                                                >
                                                    <?php echo assignedLeadsEscape($label); ?>
                                                </option>
                                            <?php endforeach; ?>

                                        </select>

                                    </form>

                                </td>

                                <td>
                                    <?php
                                    echo !empty($lead["created_at"])
                                        ? assignedLeadsEscape(date("d-m-Y", strtotime($lead["created_at"])))
                                        : "-";
                                    ?>
                                </td>

                                <td>
                                    <a
                                        href="view_lead.php?id=<?php echo (int)$lead["id"]; ?>"
                                        class="btn"
                                    >
                                        View
                                    </a>
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
