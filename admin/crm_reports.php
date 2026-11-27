
<?php

// --------------------------------------------------
// REQUIRED FILES
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

requirePermission("view_crm_reports");

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function reportDateValid($date): bool
{
    if (!is_string($date) || $date === "") {
        return false;
    }

    $d = DateTime::createFromFormat("!Y-m-d", $date);

    return $d && $d->format("Y-m-d") === $date;
}

// --------------------------------------------------
// LEAD SUMMARY
// --------------------------------------------------

$totalLeads = (int)$pdo->query("
    SELECT COUNT(*) FROM leads
")->fetchColumn();

$leadStatuses = $pdo->query("
    SELECT lead_status, COUNT(*) AS total
    FROM leads
    GROUP BY lead_status
")->fetchAll(PDO::FETCH_ASSOC);

$conversionData = $pdo->query("
    SELECT
        COUNT(*) AS total_leads,
        SUM(CASE WHEN lead_status='converted' THEN 1 ELSE 0 END) AS converted_leads,
        SUM(CASE WHEN lead_status='lost' THEN 1 ELSE 0 END) AS lost_leads
    FROM leads
")->fetch(PDO::FETCH_ASSOC);

$conversionTotal = (int)($conversionData["total_leads"] ?? 0);
$convertedLeads = (int)($conversionData["converted_leads"] ?? 0);
$lostLeads = (int)($conversionData["lost_leads"] ?? 0);

$activeLeads = max(
    0,
    $conversionTotal - $convertedLeads - $lostLeads
);

$conversionRate = $conversionTotal
    ? ($convertedLeads / $conversionTotal) * 100
    : 0;

// --------------------------------------------------
// SITE VISITS AND FOLLOW-UPS
// --------------------------------------------------

$totalVisits = (int)$pdo->query("
    SELECT COUNT(*) FROM site_visits
")->fetchColumn();

$visitStatuses = $pdo->query("
    SELECT visit_status, COUNT(*) AS total
    FROM site_visits
    GROUP BY visit_status
")->fetchAll(PDO::FETCH_ASSOC);

$totalFollowups = (int)$pdo->query("
    SELECT COUNT(*) FROM lead_followups
")->fetchColumn();

// --------------------------------------------------
// EMPLOYEE PERFORMANCE
// --------------------------------------------------

$employees = $pdo->query("
    SELECT
        a.id,
        a.name,
        a.email,

        (
            SELECT COUNT(*)
            FROM leads l
            WHERE l.assigned_to = a.id
        ) AS total_leads,

        (
            SELECT COUNT(*)
            FROM leads l
            WHERE l.assigned_to = a.id
            AND l.lead_status = 'converted'
        ) AS converted_leads,

        (
            SELECT COUNT(*)
            FROM leads l
            WHERE l.assigned_to = a.id
            AND l.lead_status = 'lost'
        ) AS lost_leads,

        (
            SELECT COUNT(*)
            FROM leads l
            WHERE l.assigned_to = a.id
            AND l.lead_status NOT IN ('converted','lost')
        ) AS active_leads,

        (
            SELECT COUNT(*)
            FROM site_visits sv
            WHERE sv.assigned_to = a.id
        ) AS total_visits,

        (
            SELECT COUNT(*)
            FROM site_visits sv
            WHERE sv.assigned_to = a.id
            AND sv.visit_status = 'completed'
        ) AS completed_visits,

        (
            SELECT COUNT(*)
            FROM lead_followups lf
            WHERE lf.admin_id = a.id
        ) AS total_followups

    FROM admins a
    WHERE a.role = 'sales_employee'
    ORDER BY total_leads DESC, a.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// MONTHLY LEADS
// --------------------------------------------------

$monthlyLeadReports = $pdo->query("
    SELECT
        DATE_FORMAT(created_at,'%Y-%m') AS month_key,
        DATE_FORMAT(created_at,'%M %Y') AS month_name,
        COUNT(*) AS total_leads,
        SUM(CASE WHEN lead_status='converted' THEN 1 ELSE 0 END) AS converted_leads,
        SUM(CASE WHEN lead_status='lost' THEN 1 ELSE 0 END) AS lost_leads,
        SUM(CASE WHEN lead_status NOT IN ('converted','lost') THEN 1 ELSE 0 END) AS active_leads
    FROM leads
    GROUP BY
        DATE_FORMAT(created_at,'%Y-%m'),
        DATE_FORMAT(created_at,'%M %Y')
    ORDER BY month_key DESC
")->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// LEAD SOURCES
// --------------------------------------------------

$leadSources = $pdo->query("
    SELECT
        COALESCE(NULLIF(TRIM(lead_source),''),'Unknown') AS source_name,
        COUNT(*) AS total_leads,
        SUM(CASE WHEN lead_status='converted' THEN 1 ELSE 0 END) AS converted_leads,
        SUM(CASE WHEN lead_status='lost' THEN 1 ELSE 0 END) AS lost_leads,
        SUM(CASE WHEN lead_status NOT IN ('converted','lost') THEN 1 ELSE 0 END) AS active_leads
    FROM leads
    GROUP BY COALESCE(NULLIF(TRIM(lead_source),''),'Unknown')
    ORDER BY total_leads DESC, source_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// FOLLOW-UP REPORTS
// --------------------------------------------------

$followupTypes = $pdo->query("
    SELECT
        followup_type,
        COUNT(*) AS total_followups
    FROM lead_followups
    GROUP BY followup_type
    ORDER BY total_followups DESC, followup_type ASC
")->fetchAll(PDO::FETCH_ASSOC);

$followupEmployeeReports = $pdo->query("
    SELECT
        a.id,
        a.name,
        a.email,
        COUNT(lf.id) AS total_followups,

        SUM(CASE WHEN lf.followup_type='call' THEN 1 ELSE 0 END) AS call_followups,
        SUM(CASE WHEN lf.followup_type='email' THEN 1 ELSE 0 END) AS email_followups,
        SUM(CASE WHEN lf.followup_type='whatsapp' THEN 1 ELSE 0 END) AS whatsapp_followups,
        SUM(CASE WHEN lf.followup_type='meeting' THEN 1 ELSE 0 END) AS meeting_followups,
        SUM(CASE WHEN lf.followup_type='note' THEN 1 ELSE 0 END) AS note_followups

    FROM admins a
    LEFT JOIN lead_followups lf
        ON lf.admin_id = a.id

    WHERE a.role = 'sales_employee'

    GROUP BY a.id, a.name, a.email
    ORDER BY total_followups DESC, a.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// DATE RANGE
// --------------------------------------------------

$today = date("Y-m-d");
$defaultStartDate = date("Y-m-01");

$startDate = isset($_GET["start_date"])
    ? trim((string)$_GET["start_date"])
    : $defaultStartDate;

$endDate = isset($_GET["end_date"])
    ? trim((string)$_GET["end_date"])
    : $today;

$dateRangeError = "";

if (
    !reportDateValid($startDate) ||
    !reportDateValid($endDate)
) {
    $dateRangeError = "Please select valid start and end dates.";

    $startDate = $defaultStartDate;
    $endDate = $today;

} elseif (
    $startDate > $endDate ||
    $endDate > $today
) {
    $dateRangeError = "Please select a valid date range.";

    $startDate = $defaultStartDate;
    $endDate = $today;
}

$rangeStartDateTime = $startDate . " 00:00:00";

$endDateObject = new DateTime($endDate);
$endDateObject->modify("+1 day");

$rangeEndDateTime = $endDateObject->format("Y-m-d") . " 00:00:00";

// --------------------------------------------------
// DATE RANGE SUMMARY
// --------------------------------------------------

$dateRangeSummaryQuery = $pdo->prepare("
    SELECT
        COUNT(*) AS total_leads,
        SUM(CASE WHEN lead_status='converted' THEN 1 ELSE 0 END) AS converted_leads,
        SUM(CASE WHEN lead_status='lost' THEN 1 ELSE 0 END) AS lost_leads,
        SUM(CASE WHEN lead_status NOT IN ('converted','lost') THEN 1 ELSE 0 END) AS active_leads
    FROM leads
    WHERE created_at >= :start_datetime
    AND created_at < :end_datetime
");

$dateRangeSummaryQuery->execute([
    ":start_datetime" => $rangeStartDateTime,
    ":end_datetime" => $rangeEndDateTime
]);

$dateRangeSummary = $dateRangeSummaryQuery->fetch(PDO::FETCH_ASSOC);

$dateRangeTotal = (int)($dateRangeSummary["total_leads"] ?? 0);
$dateRangeConverted = (int)($dateRangeSummary["converted_leads"] ?? 0);
$dateRangeLost = (int)($dateRangeSummary["lost_leads"] ?? 0);
$dateRangeActive = (int)($dateRangeSummary["active_leads"] ?? 0);

// --------------------------------------------------
// DAILY LEAD GENERATION
// --------------------------------------------------

$dateWiseQuery = $pdo->prepare("
    SELECT
        DATE(created_at) AS report_date,
        COUNT(*) AS total_leads,
        SUM(CASE WHEN lead_status='converted' THEN 1 ELSE 0 END) AS converted_leads,
        SUM(CASE WHEN lead_status='lost' THEN 1 ELSE 0 END) AS lost_leads,
        SUM(CASE WHEN lead_status NOT IN ('converted','lost') THEN 1 ELSE 0 END) AS active_leads
    FROM leads
    WHERE created_at >= :start_datetime
    AND created_at < :end_datetime
    GROUP BY DATE(created_at)
    ORDER BY report_date DESC
");

$dateWiseQuery->execute([
    ":start_datetime" => $rangeStartDateTime,
    ":end_datetime" => $rangeEndDateTime
]);

$dateWiseLeadReports = $dateWiseQuery->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// ADVERTISEMENT CAMPAIGN REPORT
// --------------------------------------------------

$campaignQuery = $pdo->prepare("
    SELECT
        COALESCE(NULLIF(TRIM(pe.source_platform),''),'website') AS source_platform,
        COALESCE(NULLIF(TRIM(pe.source_campaign),''),'Unspecified Campaign') AS campaign_name,
        COALESCE(NULLIF(TRIM(pe.utm_medium),''),'Not specified') AS utm_medium,
        COALESCE(NULLIF(TRIM(pe.utm_content),''),'Not specified') AS utm_content,
        COALESCE(NULLIF(TRIM(pe.utm_term),''),'Not specified') AS utm_term,

        COUNT(pe.id) AS total_enquiries,

        SUM(CASE WHEN l.lead_status='new' THEN 1 ELSE 0 END) AS new_leads,
        SUM(CASE WHEN l.lead_status='converted' THEN 1 ELSE 0 END) AS converted_leads,
        SUM(CASE WHEN l.lead_status='lost' THEN 1 ELSE 0 END) AS lost_leads,

        SUM(
            CASE
                WHEN l.id IS NOT NULL
                AND l.lead_status NOT IN ('converted','lost','new')
                THEN 1
                ELSE 0
            END
        ) AS other_active_leads,

        SUM(
            CASE
                WHEN pe.fbclid IS NOT NULL AND pe.fbclid <> ''
                THEN 1 ELSE 0
            END
        ) AS facebook_clicks_tracked,

        SUM(
            CASE
                WHEN pe.gclid IS NOT NULL AND pe.gclid <> ''
                THEN 1 ELSE 0
            END
        ) AS google_clicks_tracked,

        SUM(
            CASE
                WHEN pe.ttclid IS NOT NULL AND pe.ttclid <> ''
                THEN 1 ELSE 0
            END
        ) AS tiktok_clicks_tracked

    FROM property_enquiries pe

    LEFT JOIN leads l
        ON l.property_enquiry_id = pe.id

    WHERE pe.created_at >= :start_datetime
    AND pe.created_at < :end_datetime

    GROUP BY
        COALESCE(NULLIF(TRIM(pe.source_platform),''),'website'),
        COALESCE(NULLIF(TRIM(pe.source_campaign),''),'Unspecified Campaign'),
        COALESCE(NULLIF(TRIM(pe.utm_medium),''),'Not specified'),
        COALESCE(NULLIF(TRIM(pe.utm_content),''),'Not specified'),
        COALESCE(NULLIF(TRIM(pe.utm_term),''),'Not specified')

    ORDER BY
        total_enquiries DESC,
        source_platform ASC,
        campaign_name ASC
");

$campaignQuery->execute([
    ":start_datetime" => $rangeStartDateTime,
    ":end_datetime" => $rangeEndDateTime
]);

$campaignReports = $campaignQuery->fetchAll(PDO::FETCH_ASSOC);

$totalCampaignEnquiries = 0;
$totalCampaignConverted = 0;
$totalCampaignLost = 0;

$totalFacebookClicksTracked = 0;
$totalGoogleClicksTracked = 0;
$totalTikTokClicksTracked = 0;

foreach ($campaignReports as $campaign) {

    $totalCampaignEnquiries += (int)$campaign["total_enquiries"];
    $totalCampaignConverted += (int)$campaign["converted_leads"];
    $totalCampaignLost += (int)$campaign["lost_leads"];

    $totalFacebookClicksTracked += (int)$campaign["facebook_clicks_tracked"];
    $totalGoogleClicksTracked += (int)$campaign["google_clicks_tracked"];
    $totalTikTokClicksTracked += (int)$campaign["tiktok_clicks_tracked"];
}

$campaignConversionRate = $totalCampaignEnquiries
    ? ($totalCampaignConverted / $totalCampaignEnquiries) * 100
    : 0;

// --------------------------------------------------
// RECENT LEADS
// --------------------------------------------------

$recentLeads = $pdo->query("
    SELECT
        l.id,
        l.customer_name,
        l.phone,
        l.lead_status,
        l.lead_source,
        l.created_at,
        a.name AS assigned_employee
    FROM leads l
    LEFT JOIN admins a
        ON l.assigned_to = a.id
    ORDER BY l.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<meta name="robots" content="noindex, nofollow">

<title>CRM Reports | Olivera Home Developers</title>

<link rel="stylesheet" href="assets/css/admin.css">
<link rel="stylesheet" href="assets/css/admin-sidebar.css">

<style>

:root {
    --navy:#071b38;
    --navy-light:#102b50;
    --gold:#d6a63b;
    --gold-light:#edca76;
    --ivory:#f7f5ef;
    --white:#ffffff;
    --text:#263247;
    --muted:#718096;
    --border:#e6e2d8;
    --green:#16804b;
    --red:#c43d45;
    --orange:#bd781c;
}

* {
    box-sizing:border-box;
}

body {
    margin:0;
    background:var(--ivory);
    color:var(--text);
    font-family:Arial,Helvetica,sans-serif;
    font-size:14px;
    line-height:1.6;
}

/* MAIN CONTENT */

.main {
    min-height:100vh;
    margin-left:255px;
    padding:30px;
}

.container {
    width:100%;
    max-width:1600px;
    margin:0 auto;
}

/* PAGE HEADER */

.page-heading {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    flex-wrap:wrap;
    gap:15px;
    margin-bottom:24px;
    padding-bottom:20px;
    border-bottom:2px solid var(--gold);
}

h1 {
    margin:0 0 5px;
    color:var(--navy);
    font-size:29px;
    font-weight:800;
}

.page-heading p {
    margin:0;
    color:var(--muted);
}

h2 {
    margin:0 0 16px;
    color:var(--navy);
    font-size:19px;
}

h3 {
    color:var(--navy);
    font-size:14px;
}

/* CARDS */

.card,
.summary-card {
    padding:23px;
    margin-bottom:22px;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--white);
    box-shadow:0 3px 14px rgba(7,27,56,.045);
}

.summary-grid,
.conversion-grid,
.date-range-summary,
.advertisement-summary-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
    gap:15px;
    margin:18px 0 24px;
}

.summary-card {
    position:relative;
    overflow:hidden;
    min-height:115px;
    margin:0;
}

.summary-card:before {
    position:absolute;
    inset:0 auto 0 0;
    width:4px;
    background:var(--gold);
    content:"";
}

.summary-card h3 {
    margin:0 0 10px;
    color:var(--muted);
    font-size:13px;
}

.number {
    color:var(--navy);
    font-size:30px;
    font-weight:800;
    line-height:1.2;
}

.card>p {
    color:var(--muted);
    font-size:13px;
}

/* TABLES */

.table-wrapper {
    width:100%;
    overflow-x:auto;
    border:1px solid var(--border);
    border-radius:8px;
}

table {
    width:100%;
    min-width:750px;
    border-collapse:collapse;
    background:#fff;
}

th,
td {
    padding:12px 13px;
    border-bottom:1px solid var(--border);
    text-align:left;
    vertical-align:top;
    font-size:13px;
}

th {
    background:var(--navy);
    color:#fff;
    font-size:12px;
    white-space:nowrap;
}

td {
    color:#445168;
}

td strong {
    color:var(--navy);
}

tbody tr:last-child td {
    border-bottom:0;
}

tbody tr:hover {
    background:#fbf8ef;
}

/* STATUS REPORTS */

.status-list {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
    gap:12px;
}

.status-item,
.conversion-item,
.followup-type-item,
.date-range-summary-item,
.advertisement-summary-item {
    padding:16px;
    border:1px solid var(--border);
    border-radius:9px;
    background:#fbfaf6;
}

.status-item {
    color:#536178;
    font-weight:600;
}

.status-item strong {
    display:block;
    margin-top:7px;
    color:var(--navy);
    font-size:24px;
}

/* CONVERSION REPORT */

.conversion-item h3 {
    margin:0 0 9px;
    color:var(--muted);
}

.conversion-item .number {
    font-size:26px;
}

.conversion-total .number {
    color:var(--navy);
}

.conversion-success .number {
    color:var(--green);
}

.conversion-lost .number {
    color:var(--red);
}

.conversion-active .number {
    color:var(--orange);
}

.conversion-rate-section {
    margin-top:22px;
    padding-top:18px;
    border-top:1px solid var(--border);
}

.conversion-rate-heading {
    display:flex;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:10px;
    font-weight:700;
}

.conversion-rate-heading strong {
    color:var(--green);
    font-size:23px;
}

.progress-track {
    height:11px;
    overflow:hidden;
    border-radius:20px;
    background:#e8edf4;
}

.progress-fill {
    height:100%;
    border-radius:20px;
    background:var(--gold);
}

.conversion-note,
.employee-performance-note,
.monthly-report-note,
.source-report-note,
.followup-report-note,
.date-range-note,
.advertisement-report-note {
    margin:12px 0 17px;
    color:var(--muted);
    font-size:12px;
}

.employee-rate,
.monthly-rate,
.source-rate,
.advertisement-campaign-rate,
.followup-total {
    color:var(--green);
    font-weight:700;
}

/* FOLLOW-UP REPORT */

.followup-type-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
    gap:12px;
    margin:17px 0 24px;
}

.followup-type-item span,
.date-range-summary-item span,
.advertisement-summary-item span {
    display:block;
    margin-bottom:7px;
    color:var(--muted);
    font-size:12px;
}

.followup-type-item strong,
.date-range-summary-item strong,
.advertisement-summary-item strong {
    color:var(--navy);
    font-size:23px;
}

/* DATE RANGE FILTER */

.date-range-form {
    display:flex;
    flex-wrap:wrap;
    align-items:end;
    gap:13px;
    margin:18px 0;
    padding:17px;
    border:1px solid var(--border);
    border-radius:9px;
    background:#fbfaf6;
}

.date-range-field {
    display:flex;
    flex-direction:column;
    gap:6px;
}

.date-range-field label {
    color:var(--navy);
    font-size:12px;
    font-weight:700;
}

.date-range-field input {
    min-width:180px;
    min-height:41px;
    padding:9px;
    border:1px solid #d6deea;
    border-radius:6px;
    font:inherit;
}

.date-range-form button,
.date-range-reset {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:41px;
    padding:9px 16px;
    border:1px solid var(--gold);
    border-radius:6px;
    background:var(--gold);
    color:var(--navy);
    font-weight:700;
    text-decoration:none;
    cursor:pointer;
}

.date-range-form button:hover,
.date-range-reset:hover {
    background:var(--gold-light);
}

.date-range-reset {
    border-color:var(--navy);
    background:var(--navy);
    color:#fff;
}

.date-range-summary-item.converted strong,
.advertisement-summary-item.converted strong {
    color:var(--green);
}

.date-range-summary-item.lost strong,
.advertisement-summary-item.lost strong {
    color:var(--red);
}

.date-range-summary-item.active strong {
    color:var(--orange);
}

.date-range-error {
    padding:12px;
    border:1px solid #f1c8ca;
    border-radius:7px;
    background:#fff5f5;
    color:#a52c34;
}

/* EMPTY STATES */

.empty {
    padding:12px 0;
    color:var(--muted);
}

/* RESPONSIVE */

@media(max-width:1000px) {

    .main {
        margin-left:220px;
        padding:20px;
    }
}

@media(max-width:700px) {

    .main {
        margin-left:0;
        padding:13px;
    }

    h1 {
        font-size:24px;
    }

    h2 {
        font-size:17px;
    }

    .card,
    .summary-card {
        padding:15px;
    }

    .summary-grid,
    .conversion-grid,
    .date-range-summary,
    .advertisement-summary-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:10px;
    }

    .date-range-field,
    .date-range-field input {
        width:100%;
    }

    .date-range-form button,
    .date-range-reset {
        width:100%;
    }
}

@media(max-width:380px) {

    .summary-grid,
    .conversion-grid,
    .date-range-summary,
    .advertisement-summary-grid {
        grid-template-columns:1fr;
    }
}

</style>

</head>

<body>

    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <main class="main">

        <div class="container">

            <header class="page-heading">

                <div>
                    <h1>CRM Reports</h1>

                    <p>
                        Monitor leads, employee performance, site visits and advertisement campaigns.
                    </p>
                </div>

            </header>

            <!-- SUMMARY -->

            <div class="summary-grid">

                <div class="summary-card">
                    <h3>Total Leads</h3>
                    <div class="number"><?php echo $totalLeads; ?></div>
                </div>

                <div class="summary-card">
                    <h3>Total Site Visits</h3>
                    <div class="number"><?php echo $totalVisits; ?></div>
                </div>

                <div class="summary-card">
                    <h3>Total Follow-ups</h3>
                    <div class="number"><?php echo $totalFollowups; ?></div>
                </div>

                <div class="summary-card">
                    <h3>Sales Employees</h3>
                    <div class="number"><?php echo count($employees); ?></div>
                </div>

            </div>

            <!-- CONVERSION REPORT -->

            <section class="card">

                <h2>Lead Conversion Rate Report</h2>

                <p>
                    Understand how many customer leads are successfully converted into sales.
                </p>

                <div class="conversion-grid">

                    <div class="conversion-item conversion-total">
                        <h3>Total Leads</h3>
                        <div class="number"><?php echo $conversionTotal; ?></div>
                    </div>

                    <div class="conversion-item conversion-success">
                        <h3>Converted Leads</h3>
                        <div class="number"><?php echo $convertedLeads; ?></div>
                    </div>

                    <div class="conversion-item conversion-lost">
                        <h3>Lost Leads</h3>
                        <div class="number"><?php echo $lostLeads; ?></div>
                    </div>

                    <div class="conversion-item conversion-active">
                        <h3>Active Leads</h3>
                        <div class="number"><?php echo $activeLeads; ?></div>
                    </div>

                </div>

                <div class="conversion-rate-section">

                    <div class="conversion-rate-heading">
                        <span>Overall Lead Conversion Rate</span>

                        <strong>
                            <?php echo number_format($conversionRate, 2); ?>%
                        </strong>
                    </div>

                    <div
                        class="progress-track"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="<?php echo number_format($conversionRate, 2, ".", ""); ?>"
                    >
                        <div
                            class="progress-fill"
                            style="width:<?php echo min(100, max(0, $conversionRate)); ?>%"
                        ></div>
                    </div>

                    <p class="conversion-note">
                        Conversion Rate = (Converted Leads / Total Leads) × 100.
                        <?php echo $convertedLeads; ?>
                        out of <?php echo $conversionTotal; ?>
                        leads have been converted.
                    </p>

                </div>

            </section>

            <!-- LEAD STATUS -->

            <section class="card">

                <h2>Lead Status Report</h2>

                <div class="status-list">

                    <?php if ($leadStatuses): ?>

                        <?php foreach ($leadStatuses as $status): ?>

                            <div class="status-item">

                                <?php
                                echo e(
                                    ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $status["lead_status"]
                                        )
                                    )
                                );
                                ?>

                                <strong>
                                    <?php echo (int)$status["total"]; ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="empty">No leads found.</p>

                    <?php endif; ?>

                </div>

            </section>

            <!-- SITE VISITS -->

            <section class="card">

                <h2>Site Visit Status Report</h2>

                <div class="status-list">

                    <?php if ($visitStatuses): ?>

                        <?php foreach ($visitStatuses as $status): ?>

                            <div class="status-item">

                                <?php
                                echo e(
                                    ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $status["visit_status"]
                                        )
                                    )
                                );
                                ?>

                                <strong>
                                    <?php echo (int)$status["total"]; ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="empty">No site visits found.</p>

                    <?php endif; ?>

                </div>

            </section>

            <!-- EMPLOYEE PERFORMANCE -->

            <section class="card">

                <h2>Employee-wise Performance Report</h2>

                <p class="employee-performance-note">
                    Compare assigned leads, conversions, lost leads, active leads, site visits and follow-up activity.
                </p>

                <?php if ($employees): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Email</th>
                                    <th>Total Leads</th>
                                    <th>Converted</th>
                                    <th>Lost</th>
                                    <th>Active</th>
                                    <th>Conversion Rate</th>
                                    <th>Total Visits</th>
                                    <th>Completed Visits</th>
                                    <th>Follow-ups</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($employees as $employee):

                                    $employeeTotalLeads = (int)$employee["total_leads"];
                                    $employeeConvertedLeads = (int)$employee["converted_leads"];

                                    $employeeRate = $employeeTotalLeads
                                        ? ($employeeConvertedLeads / $employeeTotalLeads) * 100
                                        : 0;

                                ?>

                                    <tr>

                                        <td><?php echo e($employee["name"]); ?></td>

                                        <td><?php echo e($employee["email"]); ?></td>

                                        <td><?php echo $employeeTotalLeads; ?></td>

                                        <td><?php echo $employeeConvertedLeads; ?></td>

                                        <td><?php echo (int)$employee["lost_leads"]; ?></td>

                                        <td><?php echo (int)$employee["active_leads"]; ?></td>

                                        <td class="employee-rate">
                                            <?php echo number_format($employeeRate, 2); ?>%
                                        </td>

                                        <td><?php echo (int)$employee["total_visits"]; ?></td>

                                        <td><?php echo (int)$employee["completed_visits"]; ?></td>

                                        <td><?php echo (int)$employee["total_followups"]; ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="empty">No Sales Employees found.</p>

                <?php endif; ?>

            </section>

            <!-- MONTHLY REPORT -->

            <section class="card">

                <h2>Monthly Lead Generation Report</h2>

                <p class="monthly-report-note">
                    View monthly leads, conversions, losses and active leads.
                </p>

                <?php if ($monthlyLeadReports): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Total Leads</th>
                                    <th>Converted</th>
                                    <th>Lost</th>
                                    <th>Active</th>
                                    <th>Conversion Rate</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($monthlyLeadReports as $report):

                                    $monthTotal = (int)$report["total_leads"];
                                    $monthConverted = (int)$report["converted_leads"];

                                    $monthRate = $monthTotal
                                        ? ($monthConverted / $monthTotal) * 100
                                        : 0;

                                ?>

                                    <tr>

                                        <td>
                                            <strong><?php echo e($report["month_name"]); ?></strong>
                                        </td>

                                        <td><?php echo $monthTotal; ?></td>

                                        <td><?php echo $monthConverted; ?></td>

                                        <td><?php echo (int)$report["lost_leads"]; ?></td>

                                        <td><?php echo (int)$report["active_leads"]; ?></td>

                                        <td class="monthly-rate">
                                            <?php echo number_format($monthRate, 2); ?>%
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="empty">
                        No monthly lead generation records found.
                    </p>

                <?php endif; ?>

            </section>

            <!-- LEAD SOURCE REPORT -->

            <section class="card">

                <h2>Lead Source Conversion Analysis</h2>

                <p class="source-report-note">
                    Compare marketing sources to understand which sources generate leads and conversions.
                </p>

                <?php if ($leadSources): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Lead Source</th>
                                    <th>Total Leads</th>
                                    <th>Converted</th>
                                    <th>Lost</th>
                                    <th>Active</th>
                                    <th>Conversion Rate</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($leadSources as $source):

                                    $sourceTotal = (int)$source["total_leads"];
                                    $sourceConverted = (int)$source["converted_leads"];

                                    $sourceRate = $sourceTotal
                                        ? ($sourceConverted / $sourceTotal) * 100
                                        : 0;

                                ?>

                                    <tr>

                                        <td>
                                            <strong><?php echo e($source["source_name"]); ?></strong>
                                        </td>

                                        <td><?php echo $sourceTotal; ?></td>

                                        <td><?php echo $sourceConverted; ?></td>

                                        <td><?php echo (int)$source["lost_leads"]; ?></td>

                                        <td><?php echo (int)$source["active_leads"]; ?></td>

                                        <td class="source-rate">
                                            <?php echo number_format($sourceRate, 2); ?>%
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="empty">No lead source records found.</p>

                <?php endif; ?>

            </section>

            <!-- ADVERTISEMENT REPORT -->

            <section class="card">

                <h2>Advertisement Campaign Performance</h2>

                <p class="advertisement-report-note">
                    Compare campaigns, UTM medium, advertisement content and keywords using the selected date range.
                </p>

                <div class="advertisement-summary-grid">

                    <div class="advertisement-summary-item">
                        <span>Total Advertisement Enquiries</span>
                        <strong><?php echo $totalCampaignEnquiries; ?></strong>
                    </div>

                    <div class="advertisement-summary-item converted">
                        <span>Converted Leads</span>
                        <strong><?php echo $totalCampaignConverted; ?></strong>
                    </div>

                    <div class="advertisement-summary-item lost">
                        <span>Lost Leads</span>
                        <strong><?php echo $totalCampaignLost; ?></strong>
                    </div>

                    <div class="advertisement-summary-item">
                        <span>Advertisement Conversion Rate</span>
                        <strong>
                            <?php echo number_format($campaignConversionRate, 2); ?>%
                        </strong>
                    </div>

                </div>

                <div class="advertisement-summary-grid">

                    <div class="advertisement-summary-item">
                        <span>Facebook Click IDs</span>
                        <strong><?php echo $totalFacebookClicksTracked; ?></strong>
                    </div>

                    <div class="advertisement-summary-item">
                        <span>Google Click IDs</span>
                        <strong><?php echo $totalGoogleClicksTracked; ?></strong>
                    </div>

                    <div class="advertisement-summary-item">
                        <span>TikTok Click IDs</span>
                        <strong><?php echo $totalTikTokClicksTracked; ?></strong>
                    </div>

                </div>

                <?php if ($campaignReports): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Platform</th>
                                    <th>Campaign</th>
                                    <th>UTM Medium</th>
                                    <th>Advertisement Content</th>
                                    <th>Keyword / UTM Term</th>
                                    <th>Enquiries</th>
                                    <th>New Leads</th>
                                    <th>Converted</th>
                                    <th>Lost</th>
                                    <th>Other Active</th>
                                    <th>Conversion Rate</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($campaignReports as $campaign):

                                    $campaignTotal = (int)$campaign["total_enquiries"];
                                    $campaignConverted = (int)$campaign["converted_leads"];

                                    $campaignRate = $campaignTotal
                                        ? ($campaignConverted / $campaignTotal) * 100
                                        : 0;

                                ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                <?php
                                                echo e(
                                                    ucwords(
                                                        str_replace(
                                                            "_",
                                                            " ",
                                                            $campaign["source_platform"]
                                                        )
                                                    )
                                                );
                                                ?>
                                            </strong>
                                        </td>

                                        <td><?php echo e($campaign["campaign_name"]); ?></td>

                                        <td><?php echo e($campaign["utm_medium"]); ?></td>

                                        <td><?php echo e($campaign["utm_content"]); ?></td>

                                        <td><?php echo e($campaign["utm_term"]); ?></td>

                                        <td><?php echo $campaignTotal; ?></td>

                                        <td><?php echo (int)$campaign["new_leads"]; ?></td>

                                        <td><?php echo $campaignConverted; ?></td>

                                        <td><?php echo (int)$campaign["lost_leads"]; ?></td>

                                        <td><?php echo (int)$campaign["other_active_leads"]; ?></td>

                                        <td class="advertisement-campaign-rate">
                                            <?php echo number_format($campaignRate, 2); ?>%
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="empty">
                        No advertisement property enquiries were found during the selected date range.
                    </p>

                <?php endif; ?>

                <p class="advertisement-report-note">
                    Conversion Rate = Converted CRM Leads divided by Total Advertisement Enquiries,
                    multiplied by 100. Lead statuses reflect their current CRM status.
                </p>

            </section>

            <!-- FOLLOW-UP PERFORMANCE -->

            <section class="card">

                <h2>Follow-up Performance Report</h2>

                <p class="followup-report-note">
                    Review follow-up activity by type and compare activity by Sales Employee.
                </p>

                <h3>Follow-ups by Type</h3>

                <?php if ($followupTypes): ?>

                    <div class="followup-type-grid">

                        <?php foreach ($followupTypes as $type): ?>

                            <div class="followup-type-item">

                                <span>
                                    <?php
                                    echo e(
                                        ucwords(
                                            str_replace(
                                                "_",
                                                " ",
                                                $type["followup_type"]
                                            )
                                        )
                                    );
                                    ?>
                                </span>

                                <strong>
                                    <?php echo (int)$type["total_followups"]; ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p class="empty">No follow-up records found.</p>

                <?php endif; ?>

                <h3>Employee-wise Follow-up Activity</h3>

                <p class="followup-report-note">
                    Counts are based on the Sales Employee recorded as the creator of each follow-up.
                </p>

                <?php if ($followupEmployeeReports): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Email</th>
                                    <th>Total</th>
                                    <th>Calls</th>
                                    <th>Emails</th>
                                    <th>WhatsApp</th>
                                    <th>Meetings</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($followupEmployeeReports as $employee): ?>

                                    <tr>

                                        <td>
                                            <strong><?php echo e($employee["name"]); ?></strong>
                                        </td>

                                        <td><?php echo e($employee["email"]); ?></td>

                                        <td class="followup-total">
                                            <?php echo (int)$employee["total_followups"]; ?>
                                        </td>

                                        <td><?php echo (int)$employee["call_followups"]; ?></td>

                                        <td><?php echo (int)$employee["email_followups"]; ?></td>

                                        <td><?php echo (int)$employee["whatsapp_followups"]; ?></td>

                                        <td><?php echo (int)$employee["meeting_followups"]; ?></td>

                                        <td><?php echo (int)$employee["note_followups"]; ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="empty">No Sales Employees found.</p>

                <?php endif; ?>

                <p class="followup-report-note">
                    The current lead_followups table does not contain a completion-status field.
                    This report measures recorded follow-up activity.
                </p>

            </section>

            <!-- DATE RANGE REPORT -->

            <section class="card">

                <h2>Date-wise and Custom-Range Analytics</h2>

                <p class="date-range-note">
                    Select a start date and end date to view leads created during that period,
                    including their current status.
                </p>

                <?php if ($dateRangeError !== ""): ?>

                    <div class="date-range-error">
                        <?php echo e($dateRangeError); ?>
                    </div>

                <?php endif; ?>

                <form
                    method="GET"
                    action="crm_reports.php"
                    class="date-range-form"
                >

                    <div class="date-range-field">

                        <label for="start_date">Start Date</label>

                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            value="<?php echo e($startDate); ?>"
                            max="<?php echo e($today); ?>"
                            required
                        >

                    </div>

                    <div class="date-range-field">

                        <label for="end_date">End Date</label>

                        <input
                            type="date"
                            id="end_date"
                            name="end_date"
                            value="<?php echo e($endDate); ?>"
                            max="<?php echo e($today); ?>"
                            required
                        >

                    </div>

                    <button type="submit">
                        Apply Date Filter
                    </button>

                    <a href="crm_reports.php" class="date-range-reset">
                        Reset
                    </a>

                </form>

                <p class="date-range-note">
                    Showing leads created from
                    <strong><?php echo e($startDate); ?></strong>
                    to
                    <strong><?php echo e($endDate); ?></strong>,
                    inclusive.
                </p>

                <div class="date-range-summary">

                    <div class="date-range-summary-item">
                        <span>Total Leads</span>
                        <strong><?php echo $dateRangeTotal; ?></strong>
                    </div>

                    <div class="date-range-summary-item converted">
                        <span>Converted Leads</span>
                        <strong><?php echo $dateRangeConverted; ?></strong>
                    </div>

                    <div class="date-range-summary-item lost">
                        <span>Lost Leads</span>
                        <strong><?php echo $dateRangeLost; ?></strong>
                    </div>

                    <div class="date-range-summary-item active">
                        <span>Active Leads</span>
                        <strong><?php echo $dateRangeActive; ?></strong>
                    </div>

                </div>

                <h3>Daily Lead Generation</h3>

                <?php if ($dateWiseLeadReports): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Total Leads</th>
                                    <th>Converted</th>
                                    <th>Lost</th>
                                    <th>Active</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($dateWiseLeadReports as $daily): ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                <?php
                                                echo e(
                                                    date(
                                                        "d M Y",
                                                        strtotime($daily["report_date"])
                                                    )
                                                );
                                                ?>
                                            </strong>
                                        </td>

                                        <td><?php echo (int)$daily["total_leads"]; ?></td>

                                        <td><?php echo (int)$daily["converted_leads"]; ?></td>

                                        <td><?php echo (int)$daily["lost_leads"]; ?></td>

                                        <td><?php echo (int)$daily["active_leads"]; ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="empty">
                        No leads were created during the selected date range.
                    </p>

                <?php endif; ?>

                <p class="date-range-note">
                    This report groups leads by their creation date.
                    Status counts reflect each lead's current status.
                </p>

            </section>

            <!-- RECENT LEADS -->

            <section class="card">

                <h2>Recent Customer Leads</h2>

                <?php if ($recentLeads): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Source</th>
                                    <th>Assigned Employee</th>
                                    <th>Created Date</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($recentLeads as $lead): ?>

                                    <tr>

                                        <td><?php echo (int)$lead["id"]; ?></td>

                                        <td><?php echo e($lead["customer_name"]); ?></td>

                                        <td><?php echo e($lead["phone"]); ?></td>

                                        <td>
                                            <?php
                                            echo e(
                                                ucwords(
                                                    str_replace(
                                                        "_",
                                                        " ",
                                                        $lead["lead_status"]
                                                    )
                                                )
                                            );
                                            ?>
                                        </td>

                                        <td><?php echo e($lead["lead_source"]); ?></td>

                                        <td>
                                            <?php
                                            echo e(
                                                $lead["assigned_employee"] ??
                                                "Not Assigned"
                                            );
                                            ?>
                                        </td>

                                        <td><?php echo e($lead["created_at"]); ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <p class="empty">No customer leads found.</p>

                <?php endif; ?>

            </section>

        </div>

    </main>

</body>
</html>
