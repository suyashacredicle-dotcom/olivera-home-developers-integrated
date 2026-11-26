
<?php

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header("X-Robots-Tag: noindex, nofollow");

// --------------------------------------------------
// PERMISSIONS
// --------------------------------------------------

$canManageLeads = hasPermission("manage_leads");
$canViewAllLeads = hasPermission("view_all_leads");
$canViewAssignedLeads = hasPermission("view_assigned_leads");

if (!$canManageLeads && !$canViewAllLeads && !$canViewAssignedLeads) {
    http_response_code(403);
    exit("Access Denied!");
}

$adminId = (int)($_SESSION["admin_id"] ?? 0);
$currentRole = $_SESSION["admin_role"] ?? "";

$leadId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($leadId <= 0) {
    http_response_code(400);
    exit("Invalid Lead ID!");
}

$isRestrictedEmployee = ($currentRole === "sales_employee");

// --------------------------------------------------
// FETCH LEAD AND VERIFY EMPLOYEE ACCESS
// --------------------------------------------------

$sql = "
    SELECT
        l.*,
        assigned.name AS assigned_employee,
        creator.name AS created_by_name
    FROM leads l
    LEFT JOIN admins assigned ON l.assigned_to = assigned.id
    LEFT JOIN admins creator ON l.created_by = creator.id
    WHERE l.id = ?
";

$params = [$leadId];

if ($isRestrictedEmployee) {
    $sql .= " AND l.assigned_to = ?";
    $params[] = $adminId;
}

$sql .= " LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    http_response_code(404);
    exit("Lead not found or Access Denied!");
}

// --------------------------------------------------
// REUSABLE LEAD-SCOPED QUERY CONDITION
// --------------------------------------------------

$leadScopeSql = " WHERE lead_id = ?";
$leadScopeParams = [$leadId];

// --------------------------------------------------
// PROPERTY ENQUIRY
// --------------------------------------------------

$propertyEnquiry = null;
$propertyEnquiryId = (int)($lead["property_enquiry_id"] ?? 0);

if ($propertyEnquiryId > 0) {
    $enquiryStmt = $pdo->prepare("
        SELECT
            id,
            customer_id,
            budget,
            preferred_location,
            plot_size,
            customer_address,
            source_platform,
            source_campaign,
            utm_medium,
            utm_content,
            utm_term,
            fbclid,
            gclid,
            gbraid,
            wbraid,
            ttclid,
            status,
            created_at,
            updated_at
        FROM property_enquiries
        WHERE id = ?
        AND customer_id = ?
        LIMIT 1
    ");

    $enquiryStmt->execute([
        $propertyEnquiryId,
        (int)($lead["customer_id"] ?? 0)
    ]);

    $propertyEnquiry = $enquiryStmt->fetch(PDO::FETCH_ASSOC);
}

// --------------------------------------------------
// FOLLOW-UP HISTORY
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        lf.*,
        a.name AS admin_name
    FROM lead_followups lf
    LEFT JOIN admins a ON lf.admin_id = a.id
    WHERE lf.lead_id = ?
    ORDER BY lf.created_at DESC
");

$stmt->execute([$leadId]);
$followups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// SITE VISIT HISTORY
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        sv.*,
        assigned.name AS assigned_employee,
        creator.name AS created_by_name
    FROM site_visits sv
    LEFT JOIN admins assigned ON sv.assigned_to = assigned.id
    LEFT JOIN admins creator ON sv.created_by = creator.id
    WHERE sv.lead_id = ?
    ORDER BY sv.scheduled_at DESC
");

$stmt->execute([$leadId]);
$siteVisits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// LEAD ACTIVITY TIMELINE
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        lal.*,
        a.name AS admin_name
    FROM lead_activity_logs lal
    LEFT JOIN admins a ON lal.admin_id = a.id
    WHERE lal.lead_id = ?
    ORDER BY lal.created_at DESC
");

$stmt->execute([$leadId]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// ASSIGNMENT HISTORY
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        lal.id,
        lal.description,
        lal.old_value,
        lal.new_value,
        lal.created_at,
        a.name AS admin_name
    FROM lead_activity_logs lal
    LEFT JOIN admins a ON lal.admin_id = a.id
    WHERE lal.lead_id = ?
    AND lal.activity_type = 'lead_assigned'
    ORDER BY lal.created_at DESC, lal.id DESC
");

$stmt->execute([$leadId]);
$assignmentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// ACTIVITY LABELS
// --------------------------------------------------

$activityLabels = [
    "lead_created" => "Lead Created",
    "lead_assigned" => "Lead Assigned",
    "lead_status_changed" => "Lead Status Changed",
    "followup_added" => "Follow-up Added",
    "site_visit_scheduled" => "Site Visit Scheduled",
    "site_visit_status_changed" => "Site Visit Status Changed",
    "internal_note_added" => "Internal Note Added",
    "lead_imported" => "Lead Imported"
];

// --------------------------------------------------
// OUTPUT HELPERS
// --------------------------------------------------

function displayValue($value)
{
    if ($value === null || $value === "") {
        return "Not available";
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function displayMultiline($value)
{
    if ($value === null || $value === "") {
        return "Not available";
    }

    return nl2br(displayValue($value));
}

function displayDate($value)
{
    if (!$value) {
        return "Not available";
    }

    try {
        return (new DateTime((string)$value))->format("d M Y, h:i A");
    } catch (Throwable $e) {
        return displayValue($value);
    }
}

function detailBox($label, $value)
{
    echo '<div class="detail-box">';
    echo '<strong>' . displayValue($label) . '</strong>';
    echo '<span>' . displayValue($value) . '</span>';
    echo '</div>';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Lead Details | Olivera Home Developers</title>

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
    padding: 25px;
    color: var(--text);
    margin: 0;
}

.container {
    max-width: 1150px;
    margin: auto;
    background: var(--surface);
    padding: 28px;
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 8px 28px rgba(7, 27, 56, 0.07);
}

h2 {
    color: var(--navy);
    margin: 0 0 20px;
}

h3 {
    color: var(--navy);
    margin-top: 32px;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gold);
    font-size: 18px;
}

.navigation {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 24px;
}

.navigation a {
    display: inline-block;
    background: var(--navy);
    color: white;
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
    transition: 0.2s;
}

.navigation a:hover {
    background: var(--navy-light);
    color: var(--gold-light);
}

hr {
    border: 0;
    border-top: 1px solid var(--border);
    margin: 20px 0;
}

.lead-name {
    font-size: 25px;
    color: var(--navy);
    margin: 20px 0;
}

.details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.detail-box {
    background: #fbfaf7;
    border: 1px solid var(--border);
    border-left: 3px solid var(--gold);
    padding: 14px;
    border-radius: 7px;
    overflow-wrap: anywhere;
}

.detail-box strong {
    display: block;
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 7px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.detail-box span {
    color: var(--navy);
    font-size: 14px;
    font-weight: 600;
}

.record {
    background: #fbfaf7;
    border: 1px solid var(--border);
    border-left: 4px solid var(--gold);
    padding: 16px;
    margin-bottom: 14px;
    border-radius: 7px;
    overflow-wrap: anywhere;
    line-height: 1.6;
}

.assignment-record {
    border-left-color: var(--navy);
}

.enquiry-record {
    border-left-color: #9473bd;
}

.record-meta {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 12px;
    line-height: 1.7;
}

.record p {
    margin: 9px 0;
}

.record strong {
    color: var(--navy);
}

.empty {
    color: var(--muted);
    background: #fbfaf7;
    border: 1px dashed var(--border);
    padding: 15px;
    border-radius: 7px;
}

.address-box {
    white-space: pre-wrap;
}

@media (max-width: 650px) {

    body {
        padding: 10px;
    }

    .container {
        padding: 17px;
    }

    .details {
        grid-template-columns: 1fr;
    }

    .lead-name {
        font-size: 21px;
    }

}

</style>

</head>

<body>

<div class="container">

<h2>Lead Details</h2>

<div class="navigation">

    <a href="manage_leads.php">Back to Manage Leads</a>

    <a href="lead_notes.php?lead_id=<?= (int)$lead["id"] ?>">
        Internal Notes
    </a>

    <a href="dashboard.php">Dashboard</a>

</div>

<hr>

<h2 class="lead-name">
    <?= displayValue($lead["customer_name"]) ?>
</h2>

<h3>General Lead Information</h3>

<div class="details">

<?php

detailBox("Lead ID", $lead["id"]);
detailBox("Customer Name", $lead["customer_name"]);
detailBox("Phone", $lead["phone"]);
detailBox("Email", $lead["email"]);
detailBox("Property Type", $lead["property_type"]);
detailBox("Interested Location", $lead["interested_location"]);
detailBox("Minimum Budget", $lead["budget_min"]);
detailBox("Maximum Budget", $lead["budget_max"]);
detailBox("Lead Source", $lead["lead_source"]);
detailBox("Lead Status", $lead["lead_status"]);
detailBox("Assigned Employee", $lead["assigned_employee"]);
detailBox("Created By", $lead["created_by_name"]);
detailBox("Created At", displayDate($lead["created_at"]));
detailBox("Customer Account ID", $lead["customer_id"] ?? null);

?>

</div>

<h3>Property Enquiry Details</h3>

<?php if ($propertyEnquiry): ?>

<div class="details">

<?php

detailBox("Property Enquiry ID", $propertyEnquiry["id"]);
detailBox("Enquiry Status", $propertyEnquiry["status"]);
detailBox("Customer Budget", $propertyEnquiry["budget"]);
detailBox("Preferred Location", $propertyEnquiry["preferred_location"]);
detailBox("Required Plot Size", $propertyEnquiry["plot_size"]);
detailBox("Advertisement Source", $propertyEnquiry["source_platform"]);
detailBox("Advertisement Campaign", $propertyEnquiry["source_campaign"]);
detailBox("UTM Medium", $propertyEnquiry["utm_medium"]);
detailBox("UTM Content", $propertyEnquiry["utm_content"]);
detailBox("UTM Term", $propertyEnquiry["utm_term"]);
detailBox("Facebook Click ID (fbclid)", $propertyEnquiry["fbclid"]);
detailBox("Google Click ID (gclid)", $propertyEnquiry["gclid"]);
detailBox("Google Braid (gbraid)", $propertyEnquiry["gbraid"]);
detailBox("Google Web Braid (wbraid)", $propertyEnquiry["wbraid"]);
detailBox("TikTok Click ID (ttclid)", $propertyEnquiry["ttclid"]);
detailBox("Enquiry Submitted At", displayDate($propertyEnquiry["created_at"]));

?>

</div>

<h3>Customer Complete Address</h3>

<div class="record enquiry-record address-box">
    <?= displayMultiline($propertyEnquiry["customer_address"]) ?>
</div>

<?php elseif ($propertyEnquiryId > 0): ?>

<p class="empty">The linked property enquiry could not be found.</p>

<?php else: ?>

<p class="empty">
    This lead was not created through the Property Enquiry Form.
</p>

<?php endif; ?>

<h3>Customer Message</h3>

<div class="record">
    <?= !empty($lead["message"])
        ? displayMultiline($lead["message"])
        : "No customer message available." ?>
</div>

<h3>Lead Assignment History</h3>

<?php if ($assignmentHistory): ?>

<?php foreach ($assignmentHistory as $assignment): ?>

<div class="record assignment-record">

    <div class="record-meta">
        <strong>Changed By:</strong>
        <?= displayValue($assignment["admin_name"]) ?>

        &nbsp; | &nbsp;

        <strong>Date & Time:</strong>
        <?= displayDate($assignment["created_at"]) ?>
    </div>

    <p>
        <strong>Previous Employee:</strong>
        <?= displayValue($assignment["old_value"]) ?>
    </p>

    <p>
        <strong>New Employee:</strong>
        <?= displayValue($assignment["new_value"]) ?>
    </p>

    <p>
        <strong>Description:</strong>
        <?= displayValue($assignment["description"]) ?>
    </p>

</div>

<?php endforeach; ?>

<?php else: ?>

<p class="empty">No assignment history recorded yet.</p>

<?php endif; ?>

<h3>Follow-up History</h3>

<?php if ($followups): ?>

<?php foreach ($followups as $followup): ?>

<div class="record">

    <div class="record-meta">
        <strong>Employee:</strong>
        <?= displayValue($followup["admin_name"]) ?>

        &nbsp; | &nbsp;

        <strong>Date:</strong>
        <?= displayDate($followup["created_at"]) ?>
    </div>

    <p>
        <strong>Type:</strong>
        <?= displayValue(ucfirst((string)$followup["followup_type"])) ?>
    </p>

    <p>
        <strong>Notes:</strong>
        <?= displayMultiline($followup["notes"]) ?>
    </p>

    <p>
        <strong>Next Follow-up:</strong>
        <?= displayDate($followup["next_followup_at"]) ?>
    </p>

</div>

<?php endforeach; ?>

<?php else: ?>

<p class="empty">No follow-ups recorded yet.</p>

<?php endif; ?>

<h3>Site Visit History</h3>

<?php if ($siteVisits): ?>

<?php foreach ($siteVisits as $visit): ?>

<div class="record">

    <div class="record-meta">
        <strong>Scheduled By:</strong>
        <?= displayValue($visit["created_by_name"]) ?>

        &nbsp; | &nbsp;

        <strong>Date:</strong>
        <?= displayDate($visit["scheduled_at"]) ?>
    </div>

    <p>
        <strong>Assigned Employee:</strong>
        <?= displayValue($visit["assigned_employee"]) ?>
    </p>

    <p>
        <strong>Status:</strong>
        <?= displayValue($visit["visit_status"]) ?>
    </p>

    <p>
        <strong>Notes:</strong>
        <?= displayMultiline($visit["notes"]) ?>
    </p>

</div>

<?php endforeach; ?>

<?php else: ?>

<p class="empty">No site visits recorded yet.</p>

<?php endif; ?>

<h3>Lead Activity Timeline</h3>

<?php if ($activities): ?>

<?php foreach ($activities as $activity): ?>

<div class="record">

    <div class="record-meta">

        <strong>
            <?= displayValue(
                $activityLabels[$activity["activity_type"]]
                ?? ucwords(str_replace("_", " ", (string)$activity["activity_type"]))
            ) ?>
        </strong>

        &nbsp; | &nbsp;

        <?= displayDate($activity["created_at"]) ?>

        &nbsp; | &nbsp;

        <?= displayValue($activity["admin_name"]) ?>

    </div>

    <p>
        <?= displayMultiline($activity["description"]) ?>
    </p>

    <?php if (
        $activity["old_value"] !== null ||
        $activity["new_value"] !== null
    ): ?>

    <p>
        <strong>Previous Value:</strong>
        <?= displayValue($activity["old_value"]) ?>
    </p>

    <p>
        <strong>New Value:</strong>
        <?= displayValue($activity["new_value"]) ?>
    </p>

    <?php endif; ?>

</div>

<?php endforeach; ?>

<?php else: ?>

<p class="empty">No activity has been recorded yet.</p>

<?php endif; ?>

</div>

</body>
</html>
