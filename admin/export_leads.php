
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

// Check access permissions
$currentRole = $_SESSION["admin_role"];
$currentAdminId = (int)$_SESSION["admin_id"];

$isSalesEmployee = ($currentRole === "sales_employee");
$canManageLeads = hasPermission("manage_leads");

if (!$canManageLeads && !$isSalesEmployee) {
    http_response_code(403);
    die("Access Denied!");
}

// Available statuses
$statuses = [
    "new",
    "contacted",
    "interested",
    "site_visit_scheduled",
    "negotiation",
    "converted",
    "lost"
];

// Available property types
$propertyTypes = [
    "Apartment",
    "Villa",
    "Plot",
    "Commercial",
    "Office",
    "Other"
];

// Available lead sources
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

// Read filters
$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");
$propertyFilter = trim($_GET["property_type"] ?? "");
$employeeFilter = trim($_GET["assigned_to"] ?? "");
$locationFilter = trim($_GET["location"] ?? "");
$sourceFilter = trim($_GET["lead_source"] ?? "");

// Validate filters
if (!in_array($statusFilter, $statuses, true)) {
    $statusFilter = "";
}

if (!in_array($propertyFilter, $propertyTypes, true)) {
    $propertyFilter = "";
}

if (!in_array($sourceFilter, $leadSources, true)) {
    $sourceFilter = "";
}

if (!ctype_digit($employeeFilter)) {
    $employeeFilter = "";
}

// Build export query
$sql = "
    SELECT
        leads.id,
        leads.customer_name,
        leads.phone,
        leads.email,
        leads.property_type,
        leads.interested_location,
        leads.budget_min,
        leads.budget_max,
        leads.lead_source,
        leads.lead_status,
        leads.assigned_to,
        admins.name AS assigned_employee,
        leads.created_at
    FROM leads
    LEFT JOIN admins ON leads.assigned_to = admins.id
";

$whereConditions = [];
$params = [];

// Sales employees can export only their own assigned leads
if ($isSalesEmployee) {
    $whereConditions[] = "leads.assigned_to = ?";
    $params[] = $currentAdminId;
}

// Search filter
if ($search !== "") {

    $whereConditions[] = "
        (
            leads.customer_name LIKE ?
            OR leads.phone LIKE ?
            OR leads.email LIKE ?
            OR leads.interested_location LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

// Status filter
if ($statusFilter !== "") {
    $whereConditions[] = "leads.lead_status = ?";
    $params[] = $statusFilter;
}

// Property type filter
if ($propertyFilter !== "") {
    $whereConditions[] = "leads.property_type = ?";
    $params[] = $propertyFilter;
}

// Location filter
if ($locationFilter !== "") {
    $whereConditions[] = "leads.interested_location LIKE ?";
    $params[] = "%" . $locationFilter . "%";
}

// Lead source filter
if ($sourceFilter !== "") {
    $whereConditions[] = "leads.lead_source = ?";
    $params[] = $sourceFilter;
}

// Assigned employee filter (managers only)
if ($canManageLeads && $employeeFilter !== "") {

    if ($employeeFilter === "0") {

        $whereConditions[] = "leads.assigned_to IS NULL";

    } else {

        $whereConditions[] = "leads.assigned_to = ?";
        $params[] = (int)$employeeFilter;
    }
}

// Add conditions
if (count($whereConditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

$sql .= " ORDER BY leads.created_at DESC";

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare CSV download
$fileName = "leads_export_" . date("Y-m-d") . ".csv";

// Clear existing output
if (ob_get_length()) {
    ob_end_clean();
}

header("Content-Type: text/csv; charset=UTF-8");
header(
    'Content-Disposition: attachment; filename="' .
    $fileName . '"'
);
header("Pragma: no-cache");
header("Expires: 0");

// Open CSV output
$output = fopen("php://output", "w");

// Add UTF-8 BOM for Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// CSV headings
fputcsv($output, [
    "Lead ID",
    "Customer Name",
    "Phone",
    "Email",
    "Property Type",
    "Interested Location",
    "Minimum Budget",
    "Maximum Budget",
    "Lead Source",
    "Lead Status",
    "Assigned Employee",
    "Created Date"
]);

// Protect spreadsheet cells against formula injection
function safeCsvValue($value) {

    $value = (string)($value ?? "");

    if (
        preg_match('/^[\s]*[=+\-@]/', $value) ||
        preg_match('/^[\t\r]/', $value)
    ) {
        return "'" . $value;
    }

    return $value;
}

// Export lead records
foreach ($leads as $lead) {

    fputcsv($output, [
        safeCsvValue($lead["id"]),
        safeCsvValue($lead["customer_name"]),
        safeCsvValue($lead["phone"]),
        safeCsvValue($lead["email"]),
        safeCsvValue($lead["property_type"]),
        safeCsvValue($lead["interested_location"]),
        safeCsvValue($lead["budget_min"]),
        safeCsvValue($lead["budget_max"]),
        safeCsvValue($lead["lead_source"]),
        safeCsvValue(
            ucwords(str_replace("_", " ", $lead["lead_status"]))
        ),
        safeCsvValue($lead["assigned_employee"]),
        safeCsvValue($lead["created_at"])
    ]);
}

fclose($output);
exit;

?>
