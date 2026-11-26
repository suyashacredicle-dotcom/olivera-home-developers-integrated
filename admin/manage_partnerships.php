
<?php

// --------------------------------------------------
// MANAGE PARTNERSHIPS
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/activity_logger.php";

requirePermission("manage_partnerships");

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function partnershipPageUrl($pageNumber)
{
    $params = [];

    if (!empty($_GET["search"])) {
        $params["search"] = substr(trim((string)$_GET["search"]), 0, 100);
    }

    if (!empty($_GET["status"])) {
        $params["status"] = trim((string)$_GET["status"]);
    }

    $params["page"] = $pageNumber;

    return "manage_partnerships.php?" . http_build_query($params);
}

// --------------------------------------------------
// ADMIN SESSION
// --------------------------------------------------

$adminId = filter_var(
    $_SESSION["admin_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$adminId || $adminId < 1) {
    http_response_code(403);
    exit("Access denied.");
}

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    strlen($_SESSION["csrf_token"]) < 32
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// --------------------------------------------------
// SIDEBAR PERMISSIONS
// --------------------------------------------------

$canViewPropertyEnquiries = hasPermission("manage_users");
$canManageAdmins = hasPermission("manage_admins");
$canManageLeads = hasPermission("manage_leads") || hasPermission("view_all_leads");
$canManagePartnerships = hasPermission("manage_partnerships");
$canManageFollowups = hasPermission("manage_followups");
$canManageSiteVisits = hasPermission("manage_site_visits");
$canViewReports = hasPermission("view_crm_reports");

// --------------------------------------------------
// FLASH MESSAGES
// --------------------------------------------------

$success = $_SESSION["partnership_success"] ?? "";
$error = $_SESSION["partnership_error"] ?? "";

unset(
    $_SESSION["partnership_success"],
    $_SESSION["partnership_error"]
);

// --------------------------------------------------
// ALLOWED STATUSES
// --------------------------------------------------

$allowedStatuses = [
    "new",
    "contacted",
    "qualified",
    "rejected"
];

// --------------------------------------------------
// HANDLE STATUS AND NOTES UPDATE
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        if (
            !isset($_POST["csrf_token"]) ||
            !is_string($_POST["csrf_token"]) ||
            !hash_equals($csrfToken, $_POST["csrf_token"])
        ) {
            throw new RuntimeException(
                "Invalid security token. Please refresh the page."
            );
        }

        $inquiryId = filter_var(
            $_POST["inquiry_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        $status = trim((string)($_POST["status"] ?? ""));
        $adminNotes = trim((string)($_POST["admin_notes"] ?? ""));

        if (!$inquiryId || $inquiryId < 1) {
            throw new RuntimeException("Invalid partnership inquiry.");
        }

        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException("Invalid partnership status.");
        }

        if (strlen($adminNotes) > 20000) {
            throw new RuntimeException("Admin notes are too long.");
        }

        $checkStmt = $pdo->prepare("
            SELECT id, name
            FROM partnership_inquiries
            WHERE id = :id
            LIMIT 1
        ");

        $checkStmt->execute([
            ":id" => $inquiryId
        ]);

        $inquiry = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$inquiry) {
            throw new RuntimeException("Partnership inquiry not found.");
        }

        $updateStmt = $pdo->prepare("
            UPDATE partnership_inquiries
            SET
                status = :status,
                admin_notes = :admin_notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $updateStmt->execute([
            ":status" => $status,
            ":admin_notes" => $adminNotes,
            ":id" => $inquiryId
        ]);

        if (function_exists("logActivity")) {
            logActivity(
                $pdo,
                "partnership_updated",
                "Updated partnership inquiry #" .
                $inquiryId .
                " for " .
                $inquiry["name"] .
                ". Status: " .
                $status
            );
        }

        $_SESSION["partnership_success"] =
            "Partnership inquiry updated successfully.";

    } catch (Throwable $exception) {

        error_log(
            "Partnership update error: " .
            $exception->getMessage()
        );

        $_SESSION["partnership_error"] =
            $exception instanceof RuntimeException
                ? $exception->getMessage()
                : "Unable to update partnership inquiry.";
    }

    // Preserve filters after update
    $redirectParams = [];

    if (!empty($_POST["return_search"])) {
        $redirectParams["search"] = substr(
            trim((string)$_POST["return_search"]),
            0,
            100
        );
    }

    if (!empty($_POST["return_status"])) {
        $returnStatus = trim((string)$_POST["return_status"]);

        if (in_array($returnStatus, $allowedStatuses, true)) {
            $redirectParams["status"] = $returnStatus;
        }
    }

    if (!empty($_POST["return_page"])) {
        $redirectParams["page"] = max(
            1,
            (int)$_POST["return_page"]
        );
    }

    $redirectUrl = "manage_partnerships.php";

    if ($redirectParams) {
        $redirectUrl .= "?" . http_build_query($redirectParams);
    }

    header("Location: " . $redirectUrl);
    exit;
}

// --------------------------------------------------
// SEARCH AND FILTER
// --------------------------------------------------

$search = trim((string)($_GET["search"] ?? ""));
$search = substr($search, 0, 100);

$filterStatus = trim((string)($_GET["status"] ?? ""));

if (
    $filterStatus !== "" &&
    !in_array($filterStatus, $allowedStatuses, true)
) {
    $filterStatus = "";
}

$page = filter_var(
    $_GET["page"] ?? 1,
    FILTER_VALIDATE_INT
);

$page = max(1, $page ?: 1);

$perPage = 20;
$offset = ($page - 1) * $perPage;

// --------------------------------------------------
// FETCH PARTNERSHIP INQUIRIES
// --------------------------------------------------

$inquiries = [];
$totalRecords = 0;
$totalPages = 1;

$statistics = [
    "total" => 0,
    "new" => 0,
    "contacted" => 0,
    "qualified" => 0,
    "rejected" => 0
];

$databaseError = "";

try {

    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'new') AS new_count,
            SUM(status = 'contacted') AS contacted_count,
            SUM(status = 'qualified') AS qualified_count,
            SUM(status = 'rejected') AS rejected_count
        FROM partnership_inquiries
    ");

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        $statistics = [
            "total" => (int)$stats["total"],
            "new" => (int)$stats["new_count"],
            "contacted" => (int)$stats["contacted_count"],
            "qualified" => (int)$stats["qualified_count"],
            "rejected" => (int)$stats["rejected_count"]
        ];
    }

    $conditions = [];
    $params = [];

    if ($filterStatus !== "") {
        $conditions[] = "status = :status";
        $params[":status"] = $filterStatus;
    }

    if ($search !== "") {

        $conditions[] = "
            (
                name LIKE :search_name
                OR phone LIKE :search_phone
                OR email LIKE :search_email
                OR city LIKE :search_city
                OR partnership_type LIKE :search_type
            )
        ";

        $searchValue = "%" . $search . "%";

        $params[":search_name"] = $searchValue;
        $params[":search_phone"] = $searchValue;
        $params[":search_email"] = $searchValue;
        $params[":search_city"] = $searchValue;
        $params[":search_type"] = $searchValue;
    }

    $whereSql = $conditions
        ? "WHERE " . implode(" AND ", $conditions)
        : "";

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM partnership_inquiries
        $whereSql
    ");

    $countStmt->execute($params);

    $totalRecords = (int)$countStmt->fetchColumn();

    $totalPages = max(
        1,
        (int)ceil($totalRecords / $perPage)
    );

    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $listStmt = $pdo->prepare("
        SELECT
            id,
            name,
            phone,
            email,
            city,
            partnership_type,
            investment_range,
            message,
            status,
            admin_notes,
            created_at,
            updated_at
        FROM partnership_inquiries
        $whereSql
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $listStmt->bindValue($key, $value);
    }

    $listStmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(":offset", $offset, PDO::PARAM_INT);

    $listStmt->execute();

    $inquiries = $listStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log(
        "Partnership inquiry database error: " .
        $exception->getMessage()
    );

    $databaseError =
        "Unable to load partnership inquiries. Please check that the database table exists and has the expected columns.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Partnerships | Olivera Admin</title>

<style>

:root {
    --navy: #071b38;
    --navy-light: #102b50;
    --gold: #d6a63b;
    --gold-light: #edca76;
    --ivory: #f7f5ef;
    --white: #ffffff;
    --text: #263448;
    --muted: #718096;
    --border: #e6e3db;
    --green: #168653;
    --red: #c43d3d;
    --sidebar-width: 255px;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: var(--ivory);
    color: var(--text);
}

/* SIDEBAR */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--navy);
    color: var(--white);
    z-index: 1000;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    transition: transform 0.25s ease;
}

.sidebar-brand {
    padding: 25px 20px 22px;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    border-bottom: 3px solid var(--gold);
}

.sidebar-brand h2 {
    color: var(--gold);
    font-size: 19px;
    margin: 0;
    letter-spacing: 0.4px;
}

.sidebar-brand p {
    margin: 7px 0 0;
    color: #cbd5e1;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.sidebar-section {
    padding: 19px 13px 5px;
}

.sidebar-heading {
    padding: 0 11px 9px;
    color: #aab8ca;
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 1.3px;
    text-transform: uppercase;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 11px 12px;
    margin-bottom: 4px;
    color: #e2e8f0;
    text-decoration: none;
    font-size: 13px;
    border-radius: 6px;
    transition: 0.2s ease;
}

.sidebar a:hover {
    background: rgba(255,255,255,0.09);
    color: var(--gold-light);
}

.sidebar a.active {
    background: var(--gold);
    color: var(--navy);
    font-weight: bold;
}

.sidebar-icon {
    width: 20px;
    text-align: center;
    font-size: 15px;
}

.sidebar-footer {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid rgba(255,255,255,0.12);
}

.sidebar-footer a {
    margin: 0;
    color: var(--gold-light);
}

/* MOBILE NAVIGATION */

.mobile-topbar {
    display: none;
    position: sticky;
    top: 0;
    z-index: 900;
    background: var(--navy);
    color: white;
    padding: 13px 16px;
    align-items: center;
    gap: 13px;
    border-bottom: 3px solid var(--gold);
}

.mobile-topbar strong {
    color: var(--gold);
    font-size: 15px;
}

.menu-toggle {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    border-radius: 5px;
    padding: 7px 10px;
    font-size: 19px;
    cursor: pointer;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.48);
    z-index: 950;
}

.sidebar-overlay.show {
    display: block;
}

/* MAIN CONTENT */

.main-content {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
    transition: margin-left 0.25s ease;
}

.header {
    background: var(--navy);
    color: var(--white);
    padding: 22px 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    border-bottom: 4px solid var(--gold);
}

.header h1 {
    margin: 0;
    font-size: 23px;
}

.header p {
    margin: 7px 0 0;
    color: #d5dce7;
    font-size: 13px;
}

.header-label {
    color: var(--gold-light);
    font-size: 12px;
    font-weight: bold;
}

.container {
    max-width: 1500px;
    margin: 30px auto;
    padding: 0 22px;
}

/* STATISTICS */

.stats {
    display: grid;
    grid-template-columns: repeat(5, minmax(130px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-top: 3px solid var(--gold);
    border-radius: 9px;
    padding: 19px;
    box-shadow: 0 3px 12px rgba(7,27,56,0.04);
}

.stat-card span {
    display: block;
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 10px;
}

.stat-card strong {
    font-size: 27px;
    color: var(--navy);
}

/* PANELS */

.panel {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(7,27,56,0.04);
}

.panel h2 {
    margin: 0 0 18px;
    color: var(--navy);
    font-size: 19px;
}

/* FILTERS */

.filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filters input,
.filters select {
    min-height: 43px;
    padding: 10px 13px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 14px;
    background: var(--white);
    color: var(--text);
}

.filters input {
    flex: 1;
    min-width: 220px;
}

.filters input:focus,
.filters select:focus,
.update-form select:focus,
.update-form textarea:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(214,166,59,0.13);
}

/* BUTTONS */

button,
.btn {
    border: 0;
    border-radius: 6px;
    padding: 11px 17px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: 0.2s ease;
}

.btn-gold {
    background: var(--gold);
    color: var(--navy);
}

.btn-gold:hover {
    background: var(--gold-dark, #b98a25);
    color: white;
}

.btn-dark {
    background: var(--navy);
    color: white;
}

.btn-dark:hover {
    background: var(--navy-light);
}

.btn-light {
    background: #efede7;
    color: var(--navy);
}

/* MESSAGES */

.message {
    padding: 13px 16px;
    border-radius: 7px;
    margin-bottom: 18px;
    font-size: 14px;
}

.message.success {
    background: #e8f6ee;
    color: #17643b;
    border: 1px solid #b9e3c9;
}

.message.error {
    background: #fff0ef;
    color: #a52b2b;
    border: 1px solid #f0c4c1;
}

/* TABLE */

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

th {
    background: var(--navy);
    color: white;
    padding: 14px 12px;
    text-align: left;
    font-size: 12px;
    white-space: nowrap;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
    font-size: 13px;
}

tbody tr:hover {
    background: #fbfaf6;
}

.person-name {
    font-weight: bold;
    color: var(--navy);
    margin-bottom: 5px;
}

.small-text {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

/* STATUS */

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    text-transform: capitalize;
}

.status-new {
    background: #fff2d8;
    color: #92600a;
}

.status-contacted {
    background: #e5efff;
    color: #245a9b;
}

.status-qualified {
    background: #e0f5e8;
    color: #17643b;
}

.status-rejected {
    background: #ffe7e5;
    color: #a52b2b;
}

/* UPDATE FORM */

.update-form {
    min-width: 230px;
}

.update-form select,
.update-form textarea {
    width: 100%;
    padding: 9px;
    border: 1px solid var(--border);
    border-radius: 5px;
    margin-bottom: 8px;
    font-family: inherit;
    font-size: 12px;
}

.update-form textarea {
    min-height: 65px;
    resize: vertical;
}

/* PAGINATION */

.pagination {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 24px;
}

.pagination a,
.pagination span {
    padding: 9px 13px;
    border: 1px solid var(--border);
    border-radius: 5px;
    text-decoration: none;
    color: var(--navy);
    background: white;
    font-size: 13px;
}

.pagination .active {
    background: var(--navy);
    color: white;
    border-color: var(--navy);
}

/* EMPTY STATE */

.empty {
    text-align: center;
    padding: 45px 15px;
    color: var(--muted);
}

.empty strong {
    display: block;
    color: var(--navy);
    font-size: 18px;
    margin-bottom: 8px;
}

/* RESPONSIVE */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(3, minmax(120px, 1fr));
    }
}

@media (max-width: 850px) {

    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .mobile-topbar {
        display: flex;
    }

    .main-content {
        margin-left: 0;
    }

    .header {
        padding: 20px;
    }

    .container {
        margin-top: 20px;
    }
}

@media (max-width: 550px) {

    .header {
        padding: 17px;
    }

    .header h1 {
        font-size: 20px;
    }

    .container {
        padding: 0 12px;
        margin-top: 18px;
    }

    .stats {
        grid-template-columns: 1fr 1fr;
        gap: 9px;
    }

    .stat-card {
        padding: 13px;
    }

    .stat-card strong {
        font-size: 22px;
    }

    .panel {
        padding: 14px;
    }

    .panel h2 {
        font-size: 17px;
    }

    .filters {
        flex-direction: column;
    }

    .filters input,
    .filters select {
        width: 100%;
        min-width: 0;
    }

    .filters button,
    .filters .btn {
        text-align: center;
    }
}

</style>

</head>

<body>

<!-- MOBILE TOPBAR -->

<div class="mobile-topbar">

    <button
        type="button"
        class="menu-toggle"
        id="menuToggle"
        aria-label="Open navigation menu"
        aria-expanded="false"
    >
        ☰
    </button>

    <strong>OLIVERA ADMIN</strong>

</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->

<aside class="sidebar" id="adminSidebar">

    <div class="sidebar-brand">
        <h2>OLIVERA</h2>
        <p>Home Developers</p>
    </div>

    <div class="sidebar-section">

        <div class="sidebar-heading">Main Menu</div>

        <a href="dashboard.php">
            <span class="sidebar-icon">⌂</span>
            Dashboard
        </a>

        <?php if ($canViewPropertyEnquiries): ?>
            <a href="property_enquiries.php">
                <span class="sidebar-icon">▤</span>
                Property Enquiries
            </a>
        <?php endif; ?>

    </div>

    <div class="sidebar-section">

        <div class="sidebar-heading">Administration</div>

        <?php if ($canManageAdmins): ?>

            <a href="manage_admins.php">
                <span class="sidebar-icon">♙</span>
                Manage Admins
            </a>

            <a href="activity_logs.php">
                <span class="sidebar-icon">◷</span>
                Activity Log
            </a>

        <?php endif; ?>

    </div>

    <div class="sidebar-section">

        <div class="sidebar-heading">CRM Management</div>

        <?php if ($canManageLeads): ?>
            <a href="manage_leads.php">
                <span class="sidebar-icon">♧</span>
                Manage Leads
            </a>
        <?php endif; ?>

        <?php if ($canManagePartnerships): ?>
            <a href="manage_partnerships.php" class="active">
                <span class="sidebar-icon">◇</span>
                Manage Partnerships
            </a>
        <?php endif; ?>

        <?php if ($canManageFollowups): ?>

            <a href="lead_followups.php">
                <span class="sidebar-icon">◷</span>
                Lead Follow-ups
            </a>

            <a href="followup_reminders.php">
                <span class="sidebar-icon">♧</span>
                Follow-up Reminders
            </a>

        <?php endif; ?>

        <?php if ($canManageSiteVisits): ?>

            <a href="site_visits.php">
                <span class="sidebar-icon">⌖</span>
                Site Visits
            </a>

            <a href="weekly_site_visits.php">
                <span class="sidebar-icon">▦</span>
                Weekly Site Visits
            </a>

            <a href="reschedule_site_visits.php">
                <span class="sidebar-icon">↻</span>
                Reschedule Visits
            </a>

            <a href="cancel_site_visit.php">
                <span class="sidebar-icon">⊗</span>
                Cancel Site Visits
            </a>

            <a href="site_visit_reschedule_history.php">
                <span class="sidebar-icon">▤</span>
                Rescheduling History
            </a>

        <?php endif; ?>

        <?php if ($canViewReports): ?>
            <a href="crm_reports.php">
                <span class="sidebar-icon">▥</span>
                CRM Reports
            </a>
        <?php endif; ?>

    </div>

    <div class="sidebar-footer">
        <a href="dashboard.php">
            <span class="sidebar-icon">←</span>
            Back to Dashboard
        </a>
    </div>

</aside>

<!-- MAIN CONTENT -->

<div class="main-content">

    <header class="header">

        <div>
            <h1>Manage Partnerships</h1>
            <p>Olivera Home Developers | Partnership Inquiries</p>
        </div>

        <div class="header-label">
            PARTNERSHIP MANAGEMENT
        </div>

    </header>

    <main class="container">

        <?php if ($success): ?>

            <div class="message success">
                <?= e($success) ?>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="message error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($databaseError): ?>

            <div class="message error">
                <?= e($databaseError) ?>
            </div>

        <?php else: ?>

            <!-- STATISTICS -->

            <section class="stats">

                <div class="stat-card">
                    <span>Total Inquiries</span>
                    <strong><?= $statistics["total"] ?></strong>
                </div>

                <div class="stat-card">
                    <span>New</span>
                    <strong><?= $statistics["new"] ?></strong>
                </div>

                <div class="stat-card">
                    <span>Contacted</span>
                    <strong><?= $statistics["contacted"] ?></strong>
                </div>

                <div class="stat-card">
                    <span>Qualified</span>
                    <strong><?= $statistics["qualified"] ?></strong>
                </div>

                <div class="stat-card">
                    <span>Rejected</span>
                    <strong><?= $statistics["rejected"] ?></strong>
                </div>

            </section>

            <!-- SEARCH AND FILTER -->

            <section class="panel">

                <h2>Search Partnership Inquiries</h2>

                <form method="GET" class="filters">

                    <input
                        type="search"
                        name="search"
                        placeholder="Search name, phone, email, city..."
                        value="<?= e($search) ?>"
                    >

                    <select name="status">

                        <option value="">All Statuses</option>

                        <option value="new"
                            <?= $filterStatus === "new" ? "selected" : "" ?>>
                            New
                        </option>

                        <option value="contacted"
                            <?= $filterStatus === "contacted" ? "selected" : "" ?>>
                            Contacted
                        </option>

                        <option value="qualified"
                            <?= $filterStatus === "qualified" ? "selected" : "" ?>>
                            Qualified
                        </option>

                        <option value="rejected"
                            <?= $filterStatus === "rejected" ? "selected" : "" ?>>
                            Rejected
                        </option>

                    </select>

                    <button type="submit" class="btn-gold">
                        Search
                    </button>

                    <a href="manage_partnerships.php" class="btn btn-light">
                        Reset
                    </a>

                </form>

            </section>

            <!-- INQUIRIES TABLE -->

            <section class="panel">

                <h2>
                    Partnership Applications
                    <span class="small-text">
                        (<?= $totalRecords ?> records)
                    </span>
                </h2>

                <?php if (empty($inquiries)): ?>

                    <div class="empty">
                        <strong>No partnership inquiries found.</strong>
                        Try changing your search or filter.
                    </div>

                <?php else: ?>

                    <div class="table-wrap">

                        <table>

                            <thead>

                                <tr>
                                    <th>Applicant</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <th>Partnership Type</th>
                                    <th>Investment</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Update Inquiry</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($inquiries as $inquiry): ?>

                                <tr>

                                    <td>
                                        <div class="person-name">
                                            <?= e($inquiry["name"]) ?>
                                        </div>

                                        <div class="small-text">
                                            ID: #<?= (int)$inquiry["id"] ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div>
                                            <?= e($inquiry["phone"]) ?>
                                        </div>

                                        <div class="small-text">
                                            <?= e($inquiry["email"]) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= e($inquiry["city"] ?: "Not provided") ?>
                                    </td>

                                    <td>
                                        <?= e($inquiry["partnership_type"]) ?>
                                    </td>

                                    <td>
                                        <?= e($inquiry["investment_range"] ?: "Not provided") ?>
                                    </td>

                                    <td>
                                        <div class="small-text">
                                            <?= nl2br(e($inquiry["message"] ?: "No message")) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="status status-<?= e($inquiry["status"]) ?>">
                                            <?= e($inquiry["status"]) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div>
                                            <?= e(date(
                                                "d M Y",
                                                strtotime($inquiry["created_at"])
                                            )) ?>
                                        </div>

                                        <div class="small-text">
                                            <?= e(date(
                                                "h:i A",
                                                strtotime($inquiry["created_at"])
                                            )) ?>
                                        </div>
                                    </td>

                                    <td>

                                        <form method="POST" class="update-form">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="inquiry_id"
                                                value="<?= (int)$inquiry["id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="return_search"
                                                value="<?= e($search) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="return_status"
                                                value="<?= e($filterStatus) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="return_page"
                                                value="<?= (int)$page ?>"
                                            >

                                            <select name="status" required>

                                                <option value="new"
                                                    <?= $inquiry["status"] === "new" ? "selected" : "" ?>>
                                                    New
                                                </option>

                                                <option value="contacted"
                                                    <?= $inquiry["status"] === "contacted" ? "selected" : "" ?>>
                                                    Contacted
                                                </option>

                                                <option value="qualified"
                                                    <?= $inquiry["status"] === "qualified" ? "selected" : "" ?>>
                                                    Qualified
                                                </option>

                                                <option value="rejected"
                                                    <?= $inquiry["status"] === "rejected" ? "selected" : "" ?>>
                                                    Rejected
                                                </option>

                                            </select>

                                            <textarea
                                                name="admin_notes"
                                                maxlength="20000"
                                                placeholder="Add internal admin notes..."
                                            ><?= e($inquiry["admin_notes"]) ?></textarea>

                                            <button type="submit" class="btn-gold">
                                                Save Changes
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <!-- PAGINATION -->

                    <?php if ($totalPages > 1): ?>

                        <div class="pagination">

                            <?php if ($page > 1): ?>

                                <a href="<?= e(partnershipPageUrl($page - 1)) ?>">
                                    Previous
                                </a>

                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>

                                <?php if ($i === $page): ?>

                                    <span class="active">
                                        <?= $i ?>
                                    </span>

                                <?php else: ?>

                                    <a href="<?= e(partnershipPageUrl($i)) ?>">
                                        <?= $i ?>
                                    </a>

                                <?php endif; ?>

                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>

                                <a href="<?= e(partnershipPageUrl($page + 1)) ?>">
                                    Next
                                </a>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </section>

        <?php endif; ?>

    </main>

</div>

<script>

const menuToggle = document.getElementById("menuToggle");
const sidebar = document.getElementById("adminSidebar");
const overlay = document.getElementById("sidebarOverlay");

function openSidebar() {
    sidebar.classList.add("open");
    overlay.classList.add("show");
    menuToggle.setAttribute("aria-expanded", "true");
}

function closeSidebar() {
    sidebar.classList.remove("open");
    overlay.classList.remove("show");
    menuToggle.setAttribute("aria-expanded", "false");
}

menuToggle.addEventListener("click", function () {
    if (sidebar.classList.contains("open")) {
        closeSidebar();
    } else {
        openSidebar();
    }
});

overlay.addEventListener("click", closeSidebar);

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeSidebar();
    }
});

</script>

</body>
</html>
