
<?php

// --------------------------------------------------
// UPDATE PROPERTY ENQUIRY AND LINKED CRM LEAD STATUS
// --------------------------------------------------

require_once "../../includes/permissions.php";
require_once "../../config/database.php";

// --------------------------------------------------
// ALLOW ONLY AUTHORIZED ADMIN USERS
// --------------------------------------------------

requirePermission("manage_users");

// --------------------------------------------------
// START SESSION
// --------------------------------------------------

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --------------------------------------------------
// POST REQUEST ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

// --------------------------------------------------
// CSRF TOKEN VALIDATION
// --------------------------------------------------

$sessionToken = $_SESSION["csrf_token"] ?? "";
$submittedToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($sessionToken) ||
    !is_string($submittedToken) ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit("Invalid security token. Please return and try again.");
}

// --------------------------------------------------
// VALIDATE ENQUIRY ID
// --------------------------------------------------

$enquiryId = filter_input(
    INPUT_POST,
    "enquiry_id",
    FILTER_VALIDATE_INT
);

if (!$enquiryId || $enquiryId < 1) {
    http_response_code(400);
    exit("Invalid property enquiry.");
}

// --------------------------------------------------
// VALIDATE STATUS
// Keep these statuses aligned with the CRM.
// --------------------------------------------------

$allowedStatuses = [
    "new",
    "contacted",
    "interested",
    "site_visit_scheduled",
    "negotiation",
    "converted",
    "lost"
];

$newStatus = trim((string)($_POST["status"] ?? ""));

if (!in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    exit("Invalid enquiry status.");
}

// --------------------------------------------------
// PRESERVE SEARCH AFTER UPDATE
// --------------------------------------------------

$search = trim((string)($_POST["search"] ?? ""));

if (strlen($search) > 200) {
    $search = substr($search, 0, 200);
}

// --------------------------------------------------
// UPDATE ENQUIRY AND LINKED CRM LEAD
// --------------------------------------------------

try {

    $pdo->beginTransaction();

    // --------------------------------------------------
    // CHECK THAT PROPERTY ENQUIRY EXISTS
    // --------------------------------------------------

    $checkStmt = $pdo->prepare("
        SELECT id
        FROM property_enquiries
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ");

    $checkStmt->execute([
        ":id" => $enquiryId
    ]);

    $existingEnquiry = $checkStmt->fetchColumn();

    if (!$existingEnquiry) {
        throw new RuntimeException("Property enquiry not found.");
    }

    // --------------------------------------------------
    // UPDATE PROPERTY ENQUIRY STATUS
    // --------------------------------------------------

    $enquiryStmt = $pdo->prepare("
        UPDATE property_enquiries
        SET status = :status
        WHERE id = :id
    ");

    $enquiryStmt->execute([
        ":status" => $newStatus,
        ":id" => $enquiryId
    ]);

    // --------------------------------------------------
    // UPDATE LINKED CRM LEAD STATUS
    // --------------------------------------------------

    $leadStmt = $pdo->prepare("
        UPDATE leads
        SET lead_status = :lead_status
        WHERE property_enquiry_id = :enquiry_id
    ");

    $leadStmt->execute([
        ":lead_status" => $newStatus,
        ":enquiry_id" => $enquiryId
    ]);

    // --------------------------------------------------
    // COMMIT BOTH UPDATES
    // --------------------------------------------------

    $pdo->commit();

    // --------------------------------------------------
    // ROTATE CSRF TOKEN
    // --------------------------------------------------

    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

    // --------------------------------------------------
    // REDIRECT BACK TO PROPERTY ENQUIRIES
    // --------------------------------------------------

    $redirectUrl = "../property_enquiries.php";

    if ($search !== "") {
        $redirectUrl .= "?search=" . urlencode($search);
    }

    header("Location: " . $redirectUrl);
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        "Property enquiry and CRM status update error: " .
        $e->getMessage()
    );

    http_response_code(500);
    exit("Unable to update property enquiry status right now.");
}
