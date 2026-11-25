
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

// --------------------------------------------------
// CHECK LEAD VIEW PERMISSION
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

// --------------------------------------------------
// FETCH LEAD DETAILS AND CHECK EMPLOYEE ACCESS
// --------------------------------------------------

if ($currentRole === "sales_employee") {

    $stmt = $pdo->prepare("
        SELECT
            l.*,
            assigned.name AS assigned_employee,
            creator.name AS created_by_name
        FROM leads l
        LEFT JOIN admins assigned
            ON l.assigned_to = assigned.id
        LEFT JOIN admins creator
            ON l.created_by = creator.id
        WHERE l.id = ?
        AND l.assigned_to = ?
        LIMIT 1
    ");

    $stmt->execute([$leadId, $adminId]);

} else {

    $stmt = $pdo->prepare("
        SELECT
            l.*,
            assigned.name AS assigned_employee,
            creator.name AS created_by_name
        FROM leads l
        LEFT JOIN admins assigned
            ON l.assigned_to = assigned.id
        LEFT JOIN admins creator
            ON l.created_by = creator.id
        WHERE l.id = ?
        LIMIT 1
    ");

    $stmt->execute([$leadId]);
}

$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    http_response_code(404);
    exit("Lead not found or Access Denied!");
}

// --------------------------------------------------
// FETCH ORIGINAL PROPERTY ENQUIRY AND TRACKING
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
// FETCH FOLLOW-UP HISTORY
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        lf.*,
        a.name AS admin_name
    FROM lead_followups lf
    LEFT JOIN admins a
        ON lf.admin_id = a.id
    WHERE lf.lead_id = ?
    ORDER BY lf.created_at DESC
");

$stmt->execute([$leadId]);
$followups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// FETCH SITE VISIT HISTORY
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        sv.*,
        assigned.name AS assigned_employee,
        creator.name AS created_by_name
    FROM site_visits sv
    LEFT JOIN admins assigned
        ON sv.assigned_to = assigned.id
    LEFT JOIN admins creator
        ON sv.created_by = creator.id
    WHERE sv.lead_id = ?
    ORDER BY sv.scheduled_at DESC
");

$stmt->execute([$leadId]);
$siteVisits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// FETCH LEAD ACTIVITY TIMELINE
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        lal.*,
        a.name AS admin_name
    FROM lead_activity_logs lal
    LEFT JOIN admins a
        ON lal.admin_id = a.id
    WHERE lal.lead_id = ?
    ORDER BY lal.created_at DESC
");

$stmt->execute([$leadId]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// FETCH LEAD ASSIGNMENT HISTORY
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
    LEFT JOIN admins a
        ON lal.admin_id = a.id
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
// ESCAPE OUTPUT
// --------------------------------------------------

function displayValue($value)
{
    if ($value === null || $value === "") {
        return "Not available";
    }

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Lead Details</title>

<style>

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    padding: 25px;
    color: #333;
    margin: 0;
}

.container {
    max-width: 1100px;
    margin: auto;
    background: white;
    padding: 25px;
    border-radius: 8px;
}

h2 {
    margin-bottom: 20px;
}

h3 {
    margin-top: 30px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.navigation {
    margin-bottom: 25px;
}

.navigation a {
    display: inline-block;
    background: #007bff;
    color: white;
    text-decoration: none;
    padding: 9px 14px;
    border-radius: 5px;
    margin: 0 8px 8px 0;
}

.navigation a:hover {
    background: #0056b3;
}

.details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.detail-box {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 5px;
    overflow-wrap: anywhere;
}

.detail-box strong {
    display: block;
    margin-bottom: 6px;
}

.record {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 5px;
    overflow-wrap: anywhere;
}

.assignment-record {
    border-left-color: #198754;
}

.enquiry-record {
    border-left-color: #6f42c1;
}

.record-meta {
    color: #666;
    font-size: 13px;
    margin-bottom: 10px;
}

.empty {
    color: #777;
    padding: 10px 0;
}

.address-box {
    white-space: pre-wrap;
    line-height: 1.6;
}

@media (max-width: 650px) {

    body {
        padding: 10px;
    }

    .container {
        padding: 15px;
    }

    .details {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="container">

<h2>Lead Details</h2>

<div class="navigation">

    <a href="manage_leads.php">
        Back to Manage Leads
    </a>

    <a href="lead_notes.php?lead_id=<?= (int)$lead["id"] ?>">
        Internal Notes
    </a>

    <a href="dashboard.php">
        Dashboard
    </a>

</div>

<hr>

<h2><?= displayValue($lead["customer_name"]) ?></h2>

<!-- GENERAL LEAD DETAILS -->

<h3>General Lead Information</h3>

<div class="details">

    <div class="detail-box">
        <strong>Lead ID</strong>
        <?= (int)$lead["id"] ?>
    </div>

    <div class="detail-box">
        <strong>Customer Name</strong>
        <?= displayValue($lead["customer_name"]) ?>
    </div>

    <div class="detail-box">
        <strong>Phone</strong>
        <?= displayValue($lead["phone"]) ?>
    </div>

    <div class="detail-box">
        <strong>Email</strong>
        <?= displayValue($lead["email"]) ?>
    </div>

    <div class="detail-box">
        <strong>Property Type</strong>
        <?= displayValue($lead["property_type"]) ?>
    </div>

    <div class="detail-box">
        <strong>Interested Location</strong>
        <?= displayValue($lead["interested_location"]) ?>
    </div>

    <div class="detail-box">
        <strong>Minimum Budget</strong>
        <?= displayValue($lead["budget_min"]) ?>
    </div>

    <div class="detail-box">
        <strong>Maximum Budget</strong>
        <?= displayValue($lead["budget_max"]) ?>
    </div>

    <div class="detail-box">
        <strong>Lead Source</strong>
        <?= displayValue($lead["lead_source"]) ?>
    </div>

    <div class="detail-box">
        <strong>Lead Status</strong>
        <?= displayValue($lead["lead_status"]) ?>
    </div>

    <div class="detail-box">
        <strong>Assigned Employee</strong>
        <?= displayValue($lead["assigned_employee"]) ?>
    </div>

    <div class="detail-box">
        <strong>Created By</strong>
        <?= displayValue($lead["created_by_name"]) ?>
    </div>

    <div class="detail-box">
        <strong>Created At</strong>
        <?= displayValue($lead["created_at"]) ?>
    </div>

    <div class="detail-box">
        <strong>Customer Account ID</strong>
        <?= displayValue($lead["customer_id"] ?? null) ?>
    </div>

</div>

<!-- PROPERTY ENQUIRY DETAILS -->

<h3>Property Enquiry Details</h3>

<?php if ($propertyEnquiry): ?>

<div class="details">

    <div class="detail-box">
        <strong>Property Enquiry ID</strong>
        <?= (int)$propertyEnquiry["id"] ?>
    </div>

    <div class="detail-box">
        <strong>Enquiry Status</strong>
        <?= displayValue($propertyEnquiry["status"]) ?>
    </div>

    <div class="detail-box">
        <strong>Customer Budget</strong>
        <?= displayValue($propertyEnquiry["budget"]) ?>
    </div>

    <div class="detail-box">
        <strong>Preferred Location</strong>
        <?= displayValue($propertyEnquiry["preferred_location"]) ?>
    </div>

    <div class="detail-box">
        <strong>Required Plot Size</strong>
        <?= displayValue($propertyEnquiry["plot_size"]) ?>
    </div>

    <div class="detail-box">
        <strong>Advertisement Source</strong>
        <?= displayValue($propertyEnquiry["source_platform"]) ?>
    </div>

    <div class="detail-box">
        <strong>Advertisement Campaign</strong>
        <?= displayValue($propertyEnquiry["source_campaign"]) ?>
    </div>

    <div class="detail-box">
        <strong>UTM Medium</strong>
        <?= displayValue($propertyEnquiry["utm_medium"]) ?>
    </div>

    <div class="detail-box">
        <strong>UTM Content</strong>
        <?= displayValue($propertyEnquiry["utm_content"]) ?>
    </div>

    <div class="detail-box">
        <strong>UTM Term</strong>
        <?= displayValue($propertyEnquiry["utm_term"]) ?>
    </div>

    <div class="detail-box">
        <strong>Facebook Click ID (fbclid)</strong>
        <?= displayValue($propertyEnquiry["fbclid"]) ?>
    </div>

    <div class="detail-box">
        <strong>Google Click ID (gclid)</strong>
        <?= displayValue($propertyEnquiry["gclid"]) ?>
    </div>

    <div class="detail-box">
        <strong>Google Braid (gbraid)</strong>
        <?= displayValue($propertyEnquiry["gbraid"]) ?>
    </div>

    <div class="detail-box">
        <strong>Google Web Braid (wbraid)</strong>
        <?= displayValue($propertyEnquiry["wbraid"]) ?>
    </div>

    <div class="detail-box">
        <strong>TikTok Click ID (ttclid)</strong>
        <?= displayValue($propertyEnquiry["ttclid"]) ?>
    </div>

    <div class="detail-box">
        <strong>Enquiry Submitted At</strong>
        <?= displayValue($propertyEnquiry["created_at"]) ?>
    </div>

</div>

<h3>Customer Complete Address</h3>

<div class="record enquiry-record address-box">
    <?= displayValue($propertyEnquiry["customer_address"]) ?>
</div>

<?php elseif ($propertyEnquiryId > 0): ?>

<p class="empty">
    The linked property enquiry could not be found.
</p>

<?php else: ?>

<p class="empty">
    This lead was not created through the Property Enquiry Form.
</p>

<?php endif; ?>

<!-- CUSTOMER MESSAGE -->

<h3>Customer Message</h3>

<div class="record">
    <?= !empty($lead["message"])
        ? nl2br(displayValue($lead["message"]))
        : "No customer message available." ?>
</div>

<!-- LEAD ASSIGNMENT HISTORY -->

<h3>Lead Assignment History</h3>

<?php if (count($assignmentHistory) > 0): ?>

    <?php foreach ($assignmentHistory as $assignment): ?>

        <div class="record assignment-record">

            <div class="record-meta">

                <strong>Changed By:</strong>
                <?= displayValue($assignment["admin_name"]) ?>

                |

                <strong>Date & Time:</strong>
                <?= displayValue($assignment["created_at"]) ?>

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

    <p class="empty">
        No assignment history recorded yet.
    </p>

<?php endif; ?>

<!-- FOLLOW-UP HISTORY -->

<h3>Follow-up History</h3>

<?php if (count($followups) > 0): ?>

    <?php foreach ($followups as $followup): ?>

        <div class="record">

            <div class="record-meta">

                <strong>Employee:</strong>
                <?= displayValue($followup["admin_name"]) ?>

                |

                <strong>Date:</strong>
                <?= displayValue($followup["created_at"]) ?>

            </div>

            <p>
                <strong>Type:</strong>
                <?= displayValue(ucfirst($followup["followup_type"])) ?>
            </p>

            <p>
                <strong>Notes:</strong>
                <?= nl2br(displayValue($followup["notes"])) ?>
            </p>

            <p>
                <strong>Next Follow-up:</strong>
                <?= displayValue($followup["next_followup_at"]) ?>
            </p>

        </div>

    <?php endforeach; ?>

<?php else: ?>

    <p class="empty">
        No follow-ups recorded yet.
    </p>

<?php endif; ?>

<!-- SITE VISIT HISTORY -->

<h3>Site Visit History</h3>

<?php if (count($siteVisits) > 0): ?>

    <?php foreach ($siteVisits as $visit): ?>

        <div class="record">

            <div class="record-meta">

                <strong>Scheduled By:</strong>
                <?= displayValue($visit["created_by_name"]) ?>

                |

                <strong>Date:</strong>
                <?= displayValue($visit["scheduled_at"]) ?>

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
                <?= nl2br(displayValue($visit["notes"])) ?>
            </p>

        </div>

    <?php endforeach; ?>

<?php else: ?>

    <p class="empty">
        No site visits recorded yet.
    </p>

<?php endif; ?>

<!-- LEAD ACTIVITY TIMELINE -->

<h3>Lead Activity Timeline</h3>

<?php if (count($activities) > 0): ?>

    <?php foreach ($activities as $activity): ?>

        <div class="record">

            <div class="record-meta">

                <strong>
                    <?= displayValue(
                        $activityLabels[$activity["activity_type"]]
                        ?? ucwords(str_replace(
                            "_",
                            " ",
                            $activity["activity_type"]
                        ))
                    ) ?>
                </strong>

                |

                <?= displayValue($activity["created_at"]) ?>

                |

                <?= displayValue($activity["admin_name"]) ?>

            </div>

            <p>
                <?= nl2br(displayValue($activity["description"])) ?>
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

    <p class="empty">
        No activity has been recorded yet.
    </p>

<?php endif; ?>

</div>

</body>
</html>
