
<?php

// --------------------------------------------------
// PROPERTY ENQUIRIES MANAGEMENT
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

requirePermission("manage_users");

// --------------------------------------------------
// OUTPUT ESCAPING
// --------------------------------------------------

function propertyEnquiryAdminEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// --------------------------------------------------
// SEARCH
// --------------------------------------------------

$search = trim((string)($_GET["search"] ?? ""));
$search = substr($search, 0, 200);

// --------------------------------------------------
// STATUS OPTIONS
// --------------------------------------------------

$statusOptions = [
    "new",
    "contacted",
    "interested",
    "site_visit_scheduled",
    "negotiation",
    "converted",
    "lost"
];

// --------------------------------------------------
// SUMMARY STATISTICS
// --------------------------------------------------

$totalEnquiries = (int)$pdo->query("
    SELECT COUNT(*) FROM property_enquiries
")->fetchColumn();

$newEnquiries = (int)$pdo->query("
    SELECT COUNT(*) FROM property_enquiries
    WHERE status = 'new'
")->fetchColumn();

$convertedEnquiries = (int)$pdo->query("
    SELECT COUNT(*) FROM property_enquiries
    WHERE status = 'converted'
")->fetchColumn();

$lostEnquiries = (int)$pdo->query("
    SELECT COUNT(*) FROM property_enquiries
    WHERE status = 'lost'
")->fetchColumn();

// --------------------------------------------------
// FETCH PROPERTY ENQUIRIES
// --------------------------------------------------

$sql = "
    SELECT
        p.*,
        c.name AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,
        l.id AS crm_lead_id
    FROM property_enquiries p
    LEFT JOIN customers c ON c.id = p.customer_id
    LEFT JOIN leads l ON l.property_enquiry_id = p.id
";

$params = [];

if ($search !== "") {

    $sql .= "
        WHERE
            c.name LIKE :search_name
            OR c.email LIKE :search_email
            OR c.phone LIKE :search_phone
            OR p.preferred_location LIKE :search_location
            OR p.source_platform LIKE :search_source
            OR p.source_campaign LIKE :search_campaign
            OR p.budget LIKE :search_budget
            OR p.plot_size LIKE :search_plot
            OR p.customer_address LIKE :search_address
            OR p.utm_medium LIKE :search_medium
            OR p.utm_content LIKE :search_content
            OR p.utm_term LIKE :search_term
            OR p.fbclid LIKE :search_fbclid
            OR p.gclid LIKE :search_gclid
            OR p.gbraid LIKE :search_gbraid
            OR p.wbraid LIKE :search_wbraid
            OR p.ttclid LIKE :search_ttclid
    ";

    $searchValue = "%" . $search . "%";

    $params = [
        ":search_name" => $searchValue,
        ":search_email" => $searchValue,
        ":search_phone" => $searchValue,
        ":search_location" => $searchValue,
        ":search_source" => $searchValue,
        ":search_campaign" => $searchValue,
        ":search_budget" => $searchValue,
        ":search_plot" => $searchValue,
        ":search_address" => $searchValue,
        ":search_medium" => $searchValue,
        ":search_content" => $searchValue,
        ":search_term" => $searchValue,
        ":search_fbclid" => $searchValue,
        ":search_gclid" => $searchValue,
        ":search_gbraid" => $searchValue,
        ":search_wbraid" => $searchValue,
        ":search_ttclid" => $searchValue
    ];
}

$sql .= " ORDER BY p.created_at DESC, p.id DESC";

$enquiryQuery = $pdo->prepare($sql);
$enquiryQuery->execute($params);
$enquiries = $enquiryQuery->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// SOURCE LABELS
// --------------------------------------------------

$sourceLabels = [
    "website" => "Website",
    "facebook" => "Facebook",
    "instagram" => "Instagram",
    "google" => "Google Ads",
    "youtube" => "YouTube",
    "other" => "Other"
];

// --------------------------------------------------
// TRACKING LABELS
// --------------------------------------------------

$trackingLabels = [
    "utm_medium" => "Medium",
    "utm_content" => "Ad Content",
    "utm_term" => "Keyword",
    "fbclid" => "Facebook Click ID",
    "gclid" => "Google Click ID",
    "gbraid" => "Google GBRAID",
    "wbraid" => "Google WBRAID",
    "ttclid" => "TikTok Click ID"
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Property Enquiries | Olivera Admin</title>

    <!-- SHARED ADMIN SIDEBAR CSS -->
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">

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
            margin: 0;
            background: var(--ivory);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        button,
        input,
        select {
            font: inherit;
        }

        /* MAIN AREA */

        .main-content {
            min-height: 100vh;
            margin-left: 255px;
            padding: 28px;
        }

        .page-container {
            width: 100%;
            max-width: 1700px;
            margin: 0 auto;
        }

        /* PAGE HEADER */

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding: 25px 28px;
            background: linear-gradient(
                120deg,
                var(--navy),
                var(--navy-light)
            );
            border: 1px solid var(--navy-light);
            border-radius: 14px;
            color: white;
        }

        .heading-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .heading-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(237,202,118,.4);
            border-radius: 13px;
            background: rgba(214,166,59,.15);
            color: var(--gold-light);
            font-size: 25px;
        }

        .page-heading h1 {
            margin: 0 0 7px;
            color: #fff;
            font-size: 25px;
            font-weight: 700;
        }

        .page-heading p {
            margin: 0;
            color: #d7dfeb;
            font-size: 13px;
            line-height: 1.6;
        }

        .record-count {
            padding: 10px 15px;
            border: 1px solid rgba(237,202,118,.45);
            border-radius: 8px;
            background: rgba(214,166,59,.12);
            color: var(--gold-light);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        /* STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 24px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            min-height: 125px;
            padding: 21px 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(7,27,56,.035);
            transition: transform .2s, box-shadow .2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(7,27,56,.07);
        }

        .stat-card::after {
            content: "";
            position: absolute;
            top: -28px;
            right: -20px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--card-tint);
            opacity: .8;
        }

        .stat-card.total { --card-tint: #e9edf3; }
        .stat-card.new { --card-tint: #fcf1d9; }
        .stat-card.converted { --card-tint: #e8f2e9; }
        .stat-card.lost { --card-tint: #f6e9e4; }

        .stat-label {
            position: relative;
            z-index: 1;
            display: block;
            margin-bottom: 12px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }

        .stat-value {
            position: relative;
            z-index: 1;
            display: block;
            color: var(--navy);
            font-size: 29px;
            font-weight: 750;
            line-height: 1.2;
        }

        /* MAIN CONTENT */

        .content-card {
            padding: 23px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 3px 14px rgba(7,27,56,.035);
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 19px;
        }

        .content-header h2 {
            margin: 0;
            color: var(--navy);
            font-size: 17px;
            font-weight: 750;
        }

        .content-header p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        /* SEARCH */

        .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 21px;
        }

        .search-input-wrap {
            position: relative;
            flex: 1;
            min-width: 0;
        }

        .search-input-wrap input {
            width: 100%;
            min-height: 44px;
            padding: 11px 14px 11px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            color: var(--text);
            font-size: 13px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .search-input-wrap input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,.12);
        }

        .search-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 16px;
            pointer-events: none;
        }

        .search-form button,
        .search-form a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 19px;
            border: 0;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .search-form button {
            background: var(--gold);
            color: var(--navy);
        }

        .search-form button:hover {
            background: var(--gold-light);
        }

        .search-form a {
            border: 1px solid var(--border);
            background: var(--ivory);
            color: var(--text);
        }

        .search-form a:hover {
            background: #eee9dc;
        }

        /* TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        table {
            width: 100%;
            min-width: 1550px;
            border-collapse: separate;
            border-spacing: 0;
        }

        th,
        td {
            padding: 15px 13px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
            font-size: 12px;
            line-height: 1.65;
        }

        th {
            background: #f3f0e8;
            color: var(--navy);
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: .4px;
            white-space: nowrap;
        }

        td {
            background: #fff;
            color: #526176;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover td {
            background: #fcfaf5;
        }

        .serial-number {
            color: var(--muted);
            font-weight: 600;
        }

        .customer-name {
            margin-bottom: 5px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 750;
        }

        .customer-contact {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .data-value {
            color: var(--text);
            font-weight: 600;
        }

        .address-value {
            min-width: 160px;
            max-width: 230px;
            overflow-wrap: anywhere;
            color: var(--muted);
        }

        /* ADVERTISEMENT DETAILS */

        .source-badge {
            display: inline-block;
            padding: 5px 9px;
            border: 1px solid #ead9b4;
            border-radius: 6px;
            background: #fcf6e9;
            color: #80601e;
            font-size: 11px;
            font-weight: 750;
        }

        .campaign-details {
            display: block;
            min-width: 130px;
            max-width: 200px;
            margin-top: 9px;
            overflow-wrap: anywhere;
            color: #536176;
        }

        .tracking-details {
            min-width: 220px;
            max-width: 300px;
            overflow-wrap: anywhere;
        }

        .tracking-item {
            margin-bottom: 10px;
        }

        .tracking-item:last-child {
            margin-bottom: 0;
        }

        .tracking-label {
            display: block;
            color: #8995a7;
            font-size: 10px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .tracking-value {
            display: block;
            margin-top: 3px;
            color: var(--text);
            font-size: 12px;
        }

        .tracking-empty {
            color: #9aa5b4;
            font-size: 12px;
            font-style: italic;
        }

        /* STATUS FORM */

        .status-form {
            min-width: 165px;
        }

        .status-form select {
            width: 100%;
            min-height: 38px;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: #fff;
            color: var(--text);
            font-size: 12px;
            outline: none;
        }

        .status-form select:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,.12);
        }

        .status-form button {
            width: 100%;
            min-height: 35px;
            margin-top: 8px;
            padding: 7px 10px;
            border: 0;
            border-radius: 7px;
            background: var(--navy);
            color: #fff;
            font-size: 11px;
            font-weight: 750;
            cursor: pointer;
            transition: background .2s;
        }

        .status-form button:hover {
            background: var(--navy-light);
        }

        /* CRM LINK */

        .crm-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 11px;
            border: 1px solid #ead9b4;
            border-radius: 7px;
            background: #fcf6e9;
            color: #80601e;
            text-decoration: none;
            font-size: 11px;
            font-weight: 750;
            white-space: nowrap;
            transition: .2s;
        }

        .crm-link:hover {
            border-color: var(--gold);
            background: #f8edcf;
        }

        .no-lead {
            color: #9aa5b4;
            font-size: 12px;
            font-style: italic;
        }

        .submitted-date {
            min-width: 130px;
            color: var(--muted);
            white-space: nowrap;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 55px 20px;
            text-align: center;
            color: #8491a3;
            font-size: 14px;
        }

        .empty-icon {
            display: block;
            margin-bottom: 10px;
            color: var(--gold);
            font-size: 27px;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .main-content {
                margin-left: 220px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {

            .main-content {
                margin-left: 0;
                padding: 13px;
            }

            .page-heading {
                align-items: flex-start;
                padding: 18px;
            }

            .heading-icon {
                width: 43px;
                height: 43px;
                font-size: 21px;
            }

            .page-heading h1 {
                font-size: 20px;
            }

            .page-heading p {
                font-size: 12px;
            }

            .record-count {
                display: none;
            }

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                min-height: 105px;
                padding: 16px;
            }

            .stat-value {
                font-size: 24px;
            }

            .stat-label {
                font-size: 12px;
            }

            .content-card {
                padding: 14px;
            }

            .content-header {
                align-items: flex-start;
            }

            .search-form {
                flex-wrap: wrap;
            }

            .search-input-wrap {
                flex-basis: 100%;
            }

            .search-form button,
            .search-form a {
                flex: 1;
            }

            th,
            td {
                padding: 12px 10px;
            }
        }

        @media (max-width: 380px) {

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

<?php
// --------------------------------------------------
// SHARED ADMIN SIDEBAR
// --------------------------------------------------

require __DIR__ . "/includes/admin_sidebar.php";
?>

<!-- MAIN CONTENT -->

<main class="main-content">

    <div class="page-container">

        <!-- PAGE HEADER -->

        <div class="page-heading">

            <div class="heading-content">

                <div class="heading-icon">⌂</div>

                <div>
                    <h1>Property Enquiries</h1>
                    <p>Manage customer enquiries, advertisement sources and CRM connections.</p>
                </div>

            </div>

            <div class="record-count">
                <?php echo count($enquiries); ?> Records Found
            </div>

        </div>

        <!-- SUMMARY STATISTICS -->

        <div class="stats-grid">

            <div class="stat-card total">
                <span class="stat-label">Total Enquiries</span>
                <strong class="stat-value"><?php echo $totalEnquiries; ?></strong>
            </div>

            <div class="stat-card new">
                <span class="stat-label">New Enquiries</span>
                <strong class="stat-value"><?php echo $newEnquiries; ?></strong>
            </div>

            <div class="stat-card converted">
                <span class="stat-label">Converted Enquiries</span>
                <strong class="stat-value"><?php echo $convertedEnquiries; ?></strong>
            </div>

            <div class="stat-card lost">
                <span class="stat-label">Lost Enquiries</span>
                <strong class="stat-value"><?php echo $lostEnquiries; ?></strong>
            </div>

        </div>

        <!-- ENQUIRIES TABLE -->

        <div class="content-card">

            <div class="content-header">
                <div>
                    <h2>Enquiry Records</h2>
                    <p>Customer information, property preferences and lead status.</p>
                </div>
            </div>

            <!-- SEARCH -->

            <form method="GET" class="search-form">

                <div class="search-input-wrap">

                    <span class="search-icon">⌕</span>

                    <input
                        type="text"
                        name="search"
                        placeholder="Search customer, location, source, campaign or tracking ID..."
                        value="<?php echo propertyEnquiryAdminEscape($search); ?>"
                    >

                </div>

                <button type="submit">Search</button>

                <a href="property_enquiries.php">Reset</a>

            </form>

            <!-- TABLE -->

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>S. No.</th>
                            <th>Customer</th>
                            <th>Budget</th>
                            <th>Preferred Location</th>
                            <th>Plot Size</th>
                            <th>Full Address</th>
                            <th>Advertisement Source</th>
                            <th>Advertisement Details</th>
                            <th>Status Management</th>
                            <th>CRM Lead</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!empty($enquiries)): ?>

                        <?php $serialNumber = 1; ?>

                        <?php foreach ($enquiries as $enquiry): ?>

                            <tr>

                                <td class="serial-number">
                                    <?php echo $serialNumber++; ?>
                                </td>

                                <td>

                                    <div class="customer-name">
                                        <?php echo propertyEnquiryAdminEscape(
                                            $enquiry["customer_name"] ?? "Customer unavailable"
                                        ); ?>
                                    </div>

                                    <span class="customer-contact">
                                        <?php echo propertyEnquiryAdminEscape($enquiry["customer_email"] ?? ""); ?>
                                    </span>

                                    <span class="customer-contact">
                                        <?php echo propertyEnquiryAdminEscape($enquiry["customer_phone"] ?? ""); ?>
                                    </span>

                                </td>

                                <td class="data-value">
                                    <?php echo propertyEnquiryAdminEscape($enquiry["budget"] ?? ""); ?>
                                </td>

                                <td class="data-value">
                                    <?php echo propertyEnquiryAdminEscape($enquiry["preferred_location"] ?? ""); ?>
                                </td>

                                <td>
                                    <?php echo propertyEnquiryAdminEscape($enquiry["plot_size"] ?? ""); ?>
                                </td>

                                <td>
                                    <div class="address-value">
                                        <?php echo nl2br(propertyEnquiryAdminEscape(
                                            $enquiry["customer_address"] ?? ""
                                        )); ?>
                                    </div>
                                </td>

                                <td>

                                    <?php
                                    $platform = strtolower(
                                        (string)($enquiry["source_platform"] ?? "website")
                                    );
                                    ?>

                                    <span class="source-badge">
                                        <?php echo propertyEnquiryAdminEscape(
                                            $sourceLabels[$platform] ?? "Other"
                                        ); ?>
                                    </span>

                                    <?php if (!empty($enquiry["source_campaign"])): ?>

                                        <span class="campaign-details">
                                            <span class="tracking-label">Campaign</span>

                                            <?php echo propertyEnquiryAdminEscape(
                                                $enquiry["source_campaign"]
                                            ); ?>
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- ADVERTISEMENT TRACKING -->

                                <td>

                                    <div class="tracking-details">

                                        <?php
                                        $hasTrackingDetails = false;

                                        foreach ($trackingLabels as $field => $label):

                                            $trackingValue = trim(
                                                (string)($enquiry[$field] ?? "")
                                            );

                                            if ($trackingValue === "") {
                                                continue;
                                            }

                                            $hasTrackingDetails = true;
                                        ?>

                                            <div class="tracking-item">

                                                <span class="tracking-label">
                                                    <?php echo propertyEnquiryAdminEscape($label); ?>
                                                </span>

                                                <span class="tracking-value">
                                                    <?php echo propertyEnquiryAdminEscape($trackingValue); ?>
                                                </span>

                                            </div>

                                        <?php endforeach; ?>

                                        <?php if (!$hasTrackingDetails): ?>

                                            <span class="tracking-empty">
                                                No additional tracking details
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>

                                <!-- STATUS MANAGEMENT -->

                                <td>

                                    <form
                                        method="POST"
                                        action="actions/update_property_enquiry_status.php"
                                        class="status-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo propertyEnquiryAdminEscape($csrfToken); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="enquiry_id"
                                            value="<?php echo (int)$enquiry["id"]; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="search"
                                            value="<?php echo propertyEnquiryAdminEscape($search); ?>"
                                        >

                                        <select name="status" required>

                                            <?php foreach ($statusOptions as $status): ?>

                                                <option
                                                    value="<?php echo propertyEnquiryAdminEscape($status); ?>"
                                                    <?php echo (
                                                        ($enquiry["status"] ?? "new") === $status
                                                    ) ? "selected" : ""; ?>
                                                >
                                                    <?php echo propertyEnquiryAdminEscape(
                                                        ucwords(str_replace("_", " ", $status))
                                                    ); ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <button type="submit">
                                            Update Status
                                        </button>

                                    </form>

                                </td>

                                <!-- CRM LEAD -->

                                <td>

                                    <?php if (!empty($enquiry["crm_lead_id"])): ?>

                                        <a
                                            class="crm-link"
                                            href="view_lead.php?id=<?php echo (int)$enquiry["crm_lead_id"]; ?>"
                                        >
                                            View CRM Lead
                                        </a>

                                    <?php else: ?>

                                        <span class="no-lead">
                                            No linked lead
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- SUBMISSION DATE -->

                                <td>
                                    <div class="submitted-date">
                                        <?php echo propertyEnquiryAdminEscape(
                                            $enquiry["created_at"] ?? ""
                                        ); ?>
                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="11" class="empty-state">
                                <span class="empty-icon">⌕</span>
                                No property enquiries found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</main>

</body>
</html>
