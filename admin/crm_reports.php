<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("view_crm_reports");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// --------------------------------------------------
// 1. LEAD SUMMARY
// --------------------------------------------------

$totalLeads = (int)$pdo->query("
    SELECT COUNT(*) FROM leads
")->fetchColumn();

$leadStatuses = $pdo->query("
    SELECT lead_status, COUNT(*) AS total
    FROM leads
    GROUP BY lead_status
")->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 2. LEAD CONVERSION REPORT
// --------------------------------------------------

$conversionData = $pdo->query("
    SELECT
        COUNT(*) AS total_leads,
        SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END) AS converted_leads,
        SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END) AS lost_leads
    FROM leads
")->fetch(PDO::FETCH_ASSOC);

$conversionTotal = (int)($conversionData["total_leads"] ?? 0);
$convertedLeads = (int)($conversionData["converted_leads"] ?? 0);
$lostLeads = (int)($conversionData["lost_leads"] ?? 0);
$activeLeads = $conversionTotal - $convertedLeads - $lostLeads;

$conversionRate = $conversionTotal > 0
    ? ($convertedLeads / $conversionTotal) * 100
    : 0;

// --------------------------------------------------
// 3. SITE VISIT SUMMARY
// --------------------------------------------------

$totalVisits = (int)$pdo->query("
    SELECT COUNT(*) FROM site_visits
")->fetchColumn();

$visitStatuses = $pdo->query("
    SELECT visit_status, COUNT(*) AS total
    FROM site_visits
    GROUP BY visit_status
")->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 4. FOLLOW-UP SUMMARY
// --------------------------------------------------

$totalFollowups = (int)$pdo->query("
    SELECT COUNT(*) FROM lead_followups
")->fetchColumn();

// --------------------------------------------------
// 5. EMPLOYEE PERFORMANCE
// --------------------------------------------------

$employeeQuery = $pdo->query("
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
            AND l.lead_status NOT IN ('converted', 'lost')
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
");

$employees = $employeeQuery->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 6. MONTHLY LEAD GENERATION
// --------------------------------------------------

$monthlyLeadQuery = $pdo->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        DATE_FORMAT(created_at, '%M %Y') AS month_name,
        COUNT(*) AS total_leads,

        SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END)
            AS converted_leads,

        SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END)
            AS lost_leads,

        SUM(
            CASE
                WHEN lead_status NOT IN ('converted', 'lost')
                THEN 1 ELSE 0
            END
        ) AS active_leads

    FROM leads

    GROUP BY
        DATE_FORMAT(created_at, '%Y-%m'),
        DATE_FORMAT(created_at, '%M %Y')

    ORDER BY month_key DESC
");

$monthlyLeadReports = $monthlyLeadQuery->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 7. LEAD SOURCE CONVERSION ANALYSIS
// --------------------------------------------------

$sourceQuery = $pdo->query("
    SELECT
        COALESCE(NULLIF(TRIM(lead_source), ''), 'Unknown') AS source_name,
        COUNT(*) AS total_leads,

        SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END)
            AS converted_leads,

        SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END)
            AS lost_leads,

        SUM(
            CASE
                WHEN lead_status NOT IN ('converted', 'lost')
                THEN 1 ELSE 0
            END
        ) AS active_leads

    FROM leads

    GROUP BY COALESCE(NULLIF(TRIM(lead_source), ''), 'Unknown')

    ORDER BY total_leads DESC, source_name ASC
");

$leadSources = $sourceQuery->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 8. FOLLOW-UP PERFORMANCE
// --------------------------------------------------

$followupTypeQuery = $pdo->query("
    SELECT
        followup_type,
        COUNT(*) AS total_followups
    FROM lead_followups
    GROUP BY followup_type
    ORDER BY total_followups DESC, followup_type ASC
");

$followupTypes = $followupTypeQuery->fetchAll(PDO::FETCH_ASSOC);

$followupEmployeeQuery = $pdo->query("
    SELECT
        a.id,
        a.name,
        a.email,

        COUNT(lf.id) AS total_followups,

        SUM(CASE WHEN lf.followup_type = 'call' THEN 1 ELSE 0 END)
            AS call_followups,

        SUM(CASE WHEN lf.followup_type = 'email' THEN 1 ELSE 0 END)
            AS email_followups,

        SUM(CASE WHEN lf.followup_type = 'whatsapp' THEN 1 ELSE 0 END)
            AS whatsapp_followups,

        SUM(CASE WHEN lf.followup_type = 'meeting' THEN 1 ELSE 0 END)
            AS meeting_followups,

        SUM(CASE WHEN lf.followup_type = 'note' THEN 1 ELSE 0 END)
            AS note_followups

    FROM admins a

    LEFT JOIN lead_followups lf
        ON lf.admin_id = a.id

    WHERE a.role = 'sales_employee'

    GROUP BY a.id, a.name, a.email

    ORDER BY total_followups DESC, a.name ASC
");

$followupEmployeeReports = $followupEmployeeQuery->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 9. DATE-WISE AND CUSTOM-RANGE ANALYTICS
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

function isValidReportDate($date) {

    if (!is_string($date) || $date === "") {
        return false;
    }

    $dateObject = DateTime::createFromFormat("!Y-m-d", $date);

    return $dateObject &&
        $dateObject->format("Y-m-d") === $date;
}

if (
    !isValidReportDate($startDate) ||
    !isValidReportDate($endDate)
) {
    $dateRangeError = "Please select valid start and end dates.";
    $startDate = $defaultStartDate;
    $endDate = $today;

} elseif ($startDate > $endDate) {

    $dateRangeError = "Start Date cannot be later than End Date.";
    $startDate = $defaultStartDate;
    $endDate = $today;
}

$rangeStartDateTime = $startDate . " 00:00:00";

$endDateObject = new DateTime($endDate);
$endDateObject->modify("+1 day");

$rangeEndDateTime = $endDateObject->format("Y-m-d") . " 00:00:00";

// --------------------------------------------------
// 9A. DATE RANGE LEAD SUMMARY
// --------------------------------------------------

$dateRangeSummaryQuery = $pdo->prepare("
    SELECT
        COUNT(*) AS total_leads,

        SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END)
            AS converted_leads,

        SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END)
            AS lost_leads,

        SUM(
            CASE
                WHEN lead_status NOT IN ('converted', 'lost')
                THEN 1 ELSE 0
            END
        ) AS active_leads

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
// 9B. DAILY LEAD GENERATION
// --------------------------------------------------

$dateWiseLeadQuery = $pdo->prepare("
    SELECT
        DATE(created_at) AS report_date,
        COUNT(*) AS total_leads,

        SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END)
            AS converted_leads,

        SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END)
            AS lost_leads,

        SUM(
            CASE
                WHEN lead_status NOT IN ('converted', 'lost')
                THEN 1 ELSE 0
            END
        ) AS active_leads

    FROM leads

    WHERE created_at >= :start_datetime
    AND created_at < :end_datetime

    GROUP BY DATE(created_at)

    ORDER BY report_date DESC
");

$dateWiseLeadQuery->execute([
    ":start_datetime" => $rangeStartDateTime,
    ":end_datetime" => $rangeEndDateTime
]);

$dateWiseLeadReports = $dateWiseLeadQuery->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 9C. ADVERTISEMENT CAMPAIGN AND UTM PERFORMANCE
// --------------------------------------------------

$campaignQuery = $pdo->prepare("
    SELECT
        COALESCE(
            NULLIF(TRIM(pe.source_platform), ''),
            'website'
        ) AS source_platform,

        COALESCE(
            NULLIF(TRIM(pe.source_campaign), ''),
            'Unspecified Campaign'
        ) AS campaign_name,

        COALESCE(
            NULLIF(TRIM(pe.utm_medium), ''),
            'Not specified'
        ) AS utm_medium,

        COALESCE(
            NULLIF(TRIM(pe.utm_content), ''),
            'Not specified'
        ) AS utm_content,

        COALESCE(
            NULLIF(TRIM(pe.utm_term), ''),
            'Not specified'
        ) AS utm_term,

        COUNT(pe.id) AS total_enquiries,

        SUM(
            CASE WHEN l.lead_status = 'new'
            THEN 1 ELSE 0 END
        ) AS new_leads,

        SUM(
            CASE WHEN l.lead_status = 'converted'
            THEN 1 ELSE 0 END
        ) AS converted_leads,

        SUM(
            CASE WHEN l.lead_status = 'lost'
            THEN 1 ELSE 0 END
        ) AS lost_leads,

        SUM(
            CASE
                WHEN l.id IS NOT NULL
                AND l.lead_status NOT IN ('converted', 'lost', 'new')
                THEN 1 ELSE 0
            END
        ) AS other_active_leads,

        SUM(
            CASE WHEN pe.fbclid IS NOT NULL AND pe.fbclid <> ''
            THEN 1 ELSE 0 END
        ) AS facebook_clicks_tracked,

        SUM(
            CASE WHEN pe.gclid IS NOT NULL AND pe.gclid <> ''
            THEN 1 ELSE 0 END
        ) AS google_clicks_tracked,

        SUM(
            CASE WHEN pe.ttclid IS NOT NULL AND pe.ttclid <> ''
            THEN 1 ELSE 0 END
        ) AS tiktok_clicks_tracked

    FROM property_enquiries pe

    LEFT JOIN leads l
        ON l.property_enquiry_id = pe.id

    WHERE pe.created_at >= :start_datetime
    AND pe.created_at < :end_datetime

    GROUP BY
        COALESCE(NULLIF(TRIM(pe.source_platform), ''), 'website'),
        COALESCE(NULLIF(TRIM(pe.source_campaign), ''), 'Unspecified Campaign'),
        COALESCE(NULLIF(TRIM(pe.utm_medium), ''), 'Not specified'),
        COALESCE(NULLIF(TRIM(pe.utm_content), ''), 'Not specified'),
        COALESCE(NULLIF(TRIM(pe.utm_term), ''), 'Not specified')

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

    $totalFacebookClicksTracked +=
        (int)$campaign["facebook_clicks_tracked"];

    $totalGoogleClicksTracked +=
        (int)$campaign["google_clicks_tracked"];

    $totalTikTokClicksTracked +=
        (int)$campaign["tiktok_clicks_tracked"];
}

$campaignConversionRate = $totalCampaignEnquiries > 0
    ? ($totalCampaignConverted / $totalCampaignEnquiries) * 100
    : 0;

// --------------------------------------------------
// 10. RECENT LEADS
// --------------------------------------------------

$recentLeadsQuery = $pdo->query("
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
");

$recentLeads = $recentLeadsQuery->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CRM Reports</title>

    
<style>
    * {
        box-sizing: border-box;
    }

    :root {
        --report-primary: #315fbd;
        --report-primary-dark: #244b9b;
        --report-navy: #17243a;
        --report-background: #f4f7fb;
        --report-surface: #ffffff;
        --report-border: #e5eaf2;
        --report-text: #263247;
        --report-muted: #718096;
        --report-green: #16804b;
        --report-red: #c43d45;
        --report-orange: #bd781c;
    }

    body {
        margin: 0;
        padding: 30px;
        background: var(--report-background);
        color: var(--report-text);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 14px;
        line-height: 1.6;
    }

    .container {
        width: 100%;
        max-width: 1500px;
        margin: 0 auto;
    }

    h1 {
        margin: 0 0 8px;
        color: var(--report-navy);
        font-size: 29px;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    h2 {
        margin: 0 0 16px;
        color: var(--report-navy);
        font-size: 19px;
        font-weight: 700;
    }

    h3 {
        color: var(--report-text);
        font-size: 15px;
        font-weight: 700;
    }

    .container > p {
        margin: 0 0 22px;
        color: var(--report-muted);
    }

    .nav-links {
        display: flex;
        flex-wrap: wrap;
        gap: 9px;
        margin: 22px 0 27px;
    }

    .nav-links a {
        display: inline-flex;
        align-items: center;
        padding: 9px 15px;
        margin: 0;
        border: 1px solid var(--report-border);
        border-radius: 8px;
        background: #fff;
        color: var(--report-text);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.2s ease, color 0.2s ease,
                    border-color 0.2s ease;
    }

    .nav-links a:hover {
        border-color: var(--report-primary);
        background: var(--report-primary);
        color: #fff;
    }

    .summary-grid,
    .conversion-grid,
    .date-range-summary,
    .advertisement-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin: 18px 0 24px;
    }

    .summary-card,
    .card {
        margin-bottom: 23px;
        padding: 24px;
        border: 1px solid var(--report-border);
        border-radius: 13px;
        background: var(--report-surface);
        box-shadow: 0 3px 14px rgba(27, 43, 68, 0.045);
    }

    .summary-card {
        position: relative;
        overflow: hidden;
        min-height: 115px;
    }

    .summary-card::before {
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--report-primary);
        content: "";
    }

    .summary-card h3 {
        margin: 0 0 12px;
        color: var(--report-muted);
        font-size: 13px;
        font-weight: 600;
    }

    .summary-card .number {
        color: var(--report-navy);
        font-size: 31px;
        font-weight: 700;
        line-height: 1.2;
    }

    .card > p {
        color: var(--report-muted);
        font-size: 13px;
        line-height: 1.7;
    }

    .table-wrapper {
        width: 100%;
        overflow-x: auto;
        border: 1px solid var(--report-border);
        border-radius: 9px;
    }

    table {
        width: 100%;
        min-width: 750px;
        border-collapse: collapse;
        background: #fff;
    }

    th,
    td {
        padding: 13px 14px;
        border-bottom: 1px solid var(--report-border);
        text-align: left;
        vertical-align: top;
        font-size: 13px;
    }

    th {
        background: #f3f6fb;
        color: #42516a;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    td {
        color: #445168;
    }

    tbody tr:last-child td {
        border-bottom: 0;
    }

    tbody tr:hover {
        background: #f8faff;
    }

    td strong {
        color: var(--report-navy);
    }

    .status-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 13px;
    }

    .status-item {
        padding: 16px;
        border: 1px solid var(--report-border);
        border-radius: 9px;
        background: #f8faff;
        color: #536178;
        font-size: 13px;
        font-weight: 600;
    }

    .status-item strong {
        display: block;
        margin-top: 7px;
        color: var(--report-primary);
        font-size: 24px;
    }

    .conversion-item {
        padding: 19px;
        border: 1px solid var(--report-border);
        border-radius: 10px;
        background: #f8faff;
    }

    .conversion-item h3 {
        margin: 0 0 10px;
        color: var(--report-muted);
        font-size: 13px;
    }

    .conversion-item .number {
        font-size: 27px;
        font-weight: 700;
    }

    .conversion-total .number {
        color: var(--report-primary);
    }

    .conversion-success .number {
        color: var(--report-green);
    }

    .conversion-lost .number {
        color: var(--report-red);
    }

    .conversion-active .number {
        color: var(--report-orange);
    }

    .conversion-rate-section {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--report-border);
    }

    .conversion-rate-heading {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
        color: var(--report-text);
        font-weight: 600;
    }

    .conversion-rate-heading strong {
        color: var(--report-green);
        font-size: 24px;
    }

    .progress-track {
        width: 100%;
        height: 12px;
        overflow: hidden;
        border-radius: 20px;
        background: #e8edf4;
    }

    .progress-fill {
        height: 100%;
        border-radius: 20px;
        background: var(--report-green);
        transition: width 0.3s ease;
    }

    .conversion-note,
    .employee-performance-note,
    .monthly-report-note,
    .source-report-note,
    .followup-report-note,
    .date-range-note,
    .advertisement-report-note {
        margin: 12px 0 17px;
        color: var(--report-muted);
        font-size: 12px;
        line-height: 1.7;
    }

    .employee-rate,
    .monthly-rate,
    .source-rate,
    .advertisement-campaign-rate {
        color: var(--report-green);
        font-weight: 700;
    }

    .followup-type-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 13px;
        margin: 18px 0 25px;
    }

    .followup-type-item {
        padding: 17px;
        border: 1px solid var(--report-border);
        border-radius: 9px;
        background: #f8faff;
    }

    .followup-type-item span {
        display: block;
        margin-bottom: 8px;
        color: var(--report-muted);
        font-size: 13px;
    }

    .followup-type-item strong {
        display: block;
        color: var(--report-primary);
        font-size: 24px;
    }

    .followup-total {
        color: var(--report-primary);
        font-weight: 700;
    }

    .date-range-form {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: 14px;
        margin: 18px 0 22px;
        padding: 19px;
        border: 1px solid var(--report-border);
        border-radius: 10px;
        background: #f8faff;
    }

    .date-range-field {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .date-range-field label {
        color: var(--report-text);
        font-size: 12px;
        font-weight: 700;
    }

    .date-range-field input {
        min-width: 185px;
        min-height: 42px;
        padding: 9px 11px;
        border: 1px solid #d6deea;
        border-radius: 7px;
        background: #fff;
        color: var(--report-text);
        font-family: inherit;
        font-size: 13px;
    }

    .date-range-field input:focus {
        outline: none;
        border-color: var(--report-primary);
        box-shadow: 0 0 0 3px rgba(49, 95, 189, 0.11);
    }

    .date-range-form button,
    .date-range-reset {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 17px;
        border: 1px solid var(--report-primary);
        border-radius: 7px;
        background: var(--report-primary);
        color: #fff;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .date-range-form button:hover {
        background: var(--report-primary-dark);
    }

    .date-range-reset {
        border-color: #d6deea;
        background: #fff;
        color: var(--report-text);
    }

    .date-range-reset:hover {
        background: #edf2f8;
    }

    .date-range-error {
        margin: 12px 0;
        padding: 13px 15px;
        border: 1px solid #f1c8ca;
        border-radius: 8px;
        background: #fff5f5;
        color: #a52c34;
        font-size: 13px;
    }

    .date-range-summary-item,
    .advertisement-summary-item {
        padding: 17px;
        border: 1px solid var(--report-border);
        border-radius: 9px;
        background: #f8faff;
    }

    .date-range-summary-item span,
    .advertisement-summary-item span {
        display: block;
        margin-bottom: 8px;
        color: var(--report-muted);
        font-size: 12px;
    }

    .date-range-summary-item strong,
    .advertisement-summary-item strong {
        display: block;
        color: var(--report-primary);
        font-size: 24px;
        font-weight: 700;
    }

    .date-range-summary-item.converted strong,
    .advertisement-summary-item.converted strong {
        color: var(--report-green);
    }

    .date-range-summary-item.lost strong,
    .advertisement-summary-item.lost strong {
        color: var(--report-red);
    }

    .date-range-summary-item.active strong {
        color: var(--report-orange);
    }

    .empty {
        padding: 12px 0;
        color: var(--report-muted);
        font-size: 13px;
    }

    .tracking-cell {
        min-width: 180px;
        max-width: 260px;
        overflow-wrap: anywhere;
        font-size: 13px;
    }

    .tracking-cell div {
        margin-bottom: 5px;
    }

    .tracking-cell strong {
        color: var(--report-text);
    }

    @media (max-width: 900px) {
        body {
            padding: 20px;
        }

        .card,
        .summary-card {
            padding: 19px;
        }
    }

    @media (max-width: 600px) {
        body {
            padding: 13px;
        }

        h1 {
            font-size: 24px;
        }

        h2 {
            font-size: 17px;
        }

        .summary-grid,
        .conversion-grid,
        .date-range-summary,
        .advertisement-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .card,
        .summary-card {
            padding: 15px;
            border-radius: 10px;
        }

        .summary-card .number {
            font-size: 25px;
        }

        .conversion-item {
            padding: 14px;
        }

        .conversion-item .number {
            font-size: 23px;
        }

        .conversion-rate-heading strong {
            font-size: 21px;
        }

        .date-range-form {
            align-items: stretch;
        }

        .date-range-field,
        .date-range-field input {
            width: 100%;
        }

        .date-range-form button,
        .date-range-reset {
            width: 100%;
        }

        .nav-links a {
            padding: 8px 11px;
        }
    }

    @media (max-width: 380px) {
        .summary-grid,
        .conversion-grid,
        .date-range-summary,
        .advertisement-summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>



</head>

<body>

<div class="container">

    <h1>CRM Reports</h1>

    <p>
        Monitor customer leads, sales employee performance,
        property visits and advertisement campaigns.
    </p>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_leads.php">Manage Leads</a>
        <a href="lead_followups.php">Lead Follow-ups</a>
        <a href="site_visits.php">Site Visits</a>
    </div>

    <!-- SUMMARY CARDS -->

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

    <!-- LEAD CONVERSION REPORT -->

    <div class="card">

        <h2>Lead Conversion Rate Report</h2>

        <p>
            Understand how many customer leads are successfully
            converted into sales.
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
                aria-label="Lead conversion rate"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="<?php echo number_format($conversionRate, 2, ".", ""); ?>"
            >
                <div
                    class="progress-fill"
                    style="width: <?php echo number_format($conversionRate, 2, ".", ""); ?>%;"
                ></div>
            </div>

            <p class="conversion-note">
                Conversion Rate = (Converted Leads / Total Leads) × 100.

                <?php if ($conversionTotal === 0): ?>
                    No leads are available to calculate the conversion rate.
                <?php else: ?>
                    <?php echo $convertedLeads; ?> out of
                    <?php echo $conversionTotal; ?> leads have been converted.
                <?php endif; ?>
            </p>

        </div>

    </div>

    <!-- LEAD STATUS REPORT -->

    <div class="card">

        <h2>Lead Status Report</h2>

        <div class="status-list">

            <?php if (count($leadStatuses) > 0): ?>

                <?php foreach ($leadStatuses as $status): ?>

                    <div class="status-item">

                        <?php
                        echo e(
                            ucwords(
                                str_replace("_", " ", $status["lead_status"])
                            )
                        );
                        ?>

                        <strong><?php echo (int)$status["total"]; ?></strong>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty">No leads found.</p>

            <?php endif; ?>

        </div>

    </div>

    <!-- SITE VISIT REPORT -->

    <div class="card">

        <h2>Site Visit Status Report</h2>

        <div class="status-list">

            <?php if (count($visitStatuses) > 0): ?>

                <?php foreach ($visitStatuses as $status): ?>

                    <div class="status-item">

                        <?php
                        echo e(
                            ucwords(
                                str_replace("_", " ", $status["visit_status"])
                            )
                        );
                        ?>

                        <strong><?php echo (int)$status["total"]; ?></strong>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty">No site visits found.</p>

            <?php endif; ?>

        </div>

    </div>

    <!-- EMPLOYEE-WISE PERFORMANCE -->

    <div class="card">

        <h2>Employee-wise Performance Report</h2>

        <p class="employee-performance-note">
            Compare each Sales Employee's assigned leads, conversions,
            lost leads, active leads, site visits and follow-up activity.
        </p>

        <?php if (count($employees) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Email</th>
                            <th>Total Leads</th>
                            <th>Converted Leads</th>
                            <th>Lost Leads</th>
                            <th>Active Leads</th>
                            <th>Conversion Rate</th>
                            <th>Total Visits</th>
                            <th>Completed Visits</th>
                            <th>Total Follow-ups</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($employees as $employee): ?>

                            <?php

                            $employeeTotalLeads = (int)$employee["total_leads"];
                            $employeeConvertedLeads = (int)$employee["converted_leads"];

                            $employeeConversionRate = $employeeTotalLeads > 0
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
                                    <?php echo number_format($employeeConversionRate, 2); ?>%
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

    </div>

    <!-- MONTHLY LEAD GENERATION -->

    <div class="card">

        <h2>Monthly Lead Generation Report</h2>

        <p class="monthly-report-note">
            View how many leads were generated each month and how many
            are converted, lost or still active.
        </p>

        <?php if (count($monthlyLeadReports) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Leads</th>
                            <th>Converted Leads</th>
                            <th>Lost Leads</th>
                            <th>Active Leads</th>
                            <th>Conversion Rate</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($monthlyLeadReports as $monthlyReport): ?>

                            <?php

                            $monthTotal = (int)$monthlyReport["total_leads"];
                            $monthConverted = (int)$monthlyReport["converted_leads"];

                            $monthConversionRate = $monthTotal > 0
                                ? ($monthConverted / $monthTotal) * 100
                                : 0;

                            ?>

                            <tr>
                                <td><strong><?php echo e($monthlyReport["month_name"]); ?></strong></td>
                                <td><?php echo $monthTotal; ?></td>
                                <td><?php echo $monthConverted; ?></td>
                                <td><?php echo (int)$monthlyReport["lost_leads"]; ?></td>
                                <td><?php echo (int)$monthlyReport["active_leads"]; ?></td>

                                <td class="monthly-rate">
                                    <?php echo number_format($monthConversionRate, 2); ?>%
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">No monthly lead generation records found.</p>

        <?php endif; ?>

    </div>

    <!-- LEAD SOURCE CONVERSION ANALYSIS -->

    <div class="card">

        <h2>Lead Source Conversion Analysis</h2>

        <p class="source-report-note">
            Compare marketing sources to understand which sources
            generate the most leads and successful conversions.
        </p>

        <?php if (count($leadSources) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Lead Source</th>
                            <th>Total Leads</th>
                            <th>Converted Leads</th>
                            <th>Lost Leads</th>
                            <th>Active Leads</th>
                            <th>Conversion Rate</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($leadSources as $source): ?>

                            <?php

                            $sourceTotal = (int)$source["total_leads"];
                            $sourceConverted = (int)$source["converted_leads"];

                            $sourceConversionRate = $sourceTotal > 0
                                ? ($sourceConverted / $sourceTotal) * 100
                                : 0;

                            ?>

                            <tr>
                                <td><strong><?php echo e($source["source_name"]); ?></strong></td>
                                <td><?php echo $sourceTotal; ?></td>
                                <td><?php echo $sourceConverted; ?></td>
                                <td><?php echo (int)$source["lost_leads"]; ?></td>
                                <td><?php echo (int)$source["active_leads"]; ?></td>

                                <td class="source-rate">
                                    <?php echo number_format($sourceConversionRate, 2); ?>%
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">No lead source records found.</p>

        <?php endif; ?>

    </div>

    <!-- ADVERTISEMENT CAMPAIGN PERFORMANCE -->

    <div class="card">

        <h2>Advertisement Campaign Performance</h2>

        <p class="advertisement-report-note">
            Compare campaigns, UTM medium, advertisement content and
            keywords. The report uses the selected date range below.
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
                <strong><?php echo number_format($campaignConversionRate, 2); ?>%</strong>
            </div>

        </div>

        <div class="advertisement-summary-grid">

            <div class="advertisement-summary-item">
                <span>Enquiries with Facebook Click ID</span>
                <strong><?php echo $totalFacebookClicksTracked; ?></strong>
            </div>

            <div class="advertisement-summary-item">
                <span>Enquiries with Google Click ID</span>
                <strong><?php echo $totalGoogleClicksTracked; ?></strong>
            </div>

            <div class="advertisement-summary-item">
                <span>Enquiries with TikTok Click ID</span>
                <strong><?php echo $totalTikTokClicksTracked; ?></strong>
            </div>

        </div>

        <?php if (count($campaignReports) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Campaign Name</th>
                            <th>UTM Medium</th>
                            <th>Advertisement Content</th>
                            <th>Keyword / UTM Term</th>
                            <th>Total Enquiries</th>
                            <th>New Leads</th>
                            <th>Converted</th>
                            <th>Lost</th>
                            <th>Other Active Leads</th>
                            <th>Conversion Rate</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($campaignReports as $campaign): ?>

                            <?php

                            $campaignTotal = (int)$campaign["total_enquiries"];
                            $campaignConverted = (int)$campaign["converted_leads"];

                            $campaignRate = $campaignTotal > 0
                                ? ($campaignConverted / $campaignTotal) * 100
                                : 0;

                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?php
                                        echo e(
                                            ucwords(
                                                str_replace("_", " ", $campaign["source_platform"])
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
                No advertisement property enquiries were found
                during the selected date range.
            </p>

        <?php endif; ?>

        <p class="advertisement-report-note">
            Conversion Rate = Converted CRM Leads divided by Total
            Advertisement Enquiries, multiplied by 100. Lead statuses
            reflect their current CRM status.
        </p>

    </div>

    <!-- FOLLOW-UP PERFORMANCE REPORT -->

    <div class="card">

        <h2>Follow-up Performance Report</h2>

        <p class="followup-report-note">
            Review follow-up activity by type and compare the number
            of follow-ups recorded by each Sales Employee.
        </p>

        <h3>Follow-ups by Type</h3>

        <?php if (count($followupTypes) > 0): ?>

            <div class="followup-type-grid">

                <?php foreach ($followupTypes as $followupType): ?>

                    <div class="followup-type-item">

                        <span>
                            <?php
                            echo e(
                                ucwords(
                                    str_replace("_", " ", $followupType["followup_type"])
                                )
                            );
                            ?>
                        </span>

                        <strong><?php echo (int)$followupType["total_followups"]; ?></strong>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p class="empty">No follow-up records found.</p>

        <?php endif; ?>

        <h3>Employee-wise Follow-up Activity</h3>

        <p class="followup-report-note">
            Counts below are based on the Sales Employee recorded
            as the creator of each follow-up.
        </p>

        <?php if (count($followupEmployeeReports) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Email</th>
                            <th>Total Follow-ups</th>
                            <th>Calls</th>
                            <th>Emails</th>
                            <th>WhatsApp</th>
                            <th>Meetings</th>
                            <th>Notes</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($followupEmployeeReports as $followupEmployee): ?>

                            <tr>
                                <td><strong><?php echo e($followupEmployee["name"]); ?></strong></td>
                                <td><?php echo e($followupEmployee["email"]); ?></td>

                                <td class="followup-total">
                                    <?php echo (int)$followupEmployee["total_followups"]; ?>
                                </td>

                                <td><?php echo (int)$followupEmployee["call_followups"]; ?></td>
                                <td><?php echo (int)$followupEmployee["email_followups"]; ?></td>
                                <td><?php echo (int)$followupEmployee["whatsapp_followups"]; ?></td>
                                <td><?php echo (int)$followupEmployee["meeting_followups"]; ?></td>
                                <td><?php echo (int)$followupEmployee["note_followups"]; ?></td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="empty">No Sales Employees found.</p>

        <?php endif; ?>

        <p class="followup-report-note">
            Note: The current lead_followups table does not contain
            a completion-status field. This report measures recorded
            follow-up activity.
        </p>

    </div>

    <!-- DATE-WISE AND CUSTOM-RANGE ANALYTICS -->

    <div class="card">

        <h2>Date-wise and Custom-Range Analytics</h2>

        <p class="date-range-note">
            Select a start date and end date to view leads created
            during that period, including their current status.
        </p>

        <?php if ($dateRangeError !== ""): ?>

            <div class="date-range-error">
                <?php echo e($dateRangeError); ?>
            </div>

        <?php endif; ?>

        <form method="GET" action="crm_reports.php" class="date-range-form">

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

            <button type="submit">Apply Date Filter</button>

            <a href="crm_reports.php" class="date-range-reset">Reset</a>

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

        <?php if (count($dateWiseLeadReports) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total Leads</th>
                            <th>Converted Leads</th>
                            <th>Lost Leads</th>
                            <th>Active Leads</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($dateWiseLeadReports as $dailyReport): ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?php
                                        echo e(
                                            date(
                                                "d M Y",
                                                strtotime($dailyReport["report_date"])
                                            )
                                        );
                                        ?>
                                    </strong>
                                </td>

                                <td><?php echo (int)$dailyReport["total_leads"]; ?></td>
                                <td><?php echo (int)$dailyReport["converted_leads"]; ?></td>
                                <td><?php echo (int)$dailyReport["lost_leads"]; ?></td>
                                <td><?php echo (int)$dailyReport["active_leads"]; ?></td>
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
            Note: This report groups leads by their creation date.
            Status counts reflect each lead's current status.
        </p>

    </div>

    <!-- RECENT LEADS -->

    <div class="card">

        <h2>Recent Customer Leads</h2>

        <?php if (count($recentLeads) > 0): ?>

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
                                            str_replace("_", " ", $lead["lead_status"])
                                        )
                                    );
                                    ?>
                                </td>

                                <td><?php echo e($lead["lead_source"]); ?></td>

                                <td>
                                    <?php
                                    echo e(
                                        $lead["assigned_employee"] ?? "Not Assigned"
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

    </div>

</div>

</body>
</html>