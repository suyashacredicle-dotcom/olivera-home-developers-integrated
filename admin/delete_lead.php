
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

// Only users with manage_leads permission can delete leads
requirePermission("manage_leads");

// Allow POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage_leads.php");
    exit;
}

// Strict CSRF validation
if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    !is_string($_POST["csrf_token"] ?? null) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"]
    )
) {
    $_SESSION["user_message"] = "Invalid security token. Please try again.";
    $_SESSION["user_message_type"] = "error";

    header("Location: manage_leads.php");
    exit;
}

// Validate lead ID
$leadId = filter_var(
    $_POST["lead_id"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$leadId || $leadId <= 0) {
    $_SESSION["user_message"] = "Invalid lead ID.";
    $_SESSION["user_message_type"] = "error";

    header("Location: manage_leads.php");
    exit;
}

try {

    // Check whether the lead exists
    $check = $pdo->prepare("
        SELECT customer_name
        FROM leads
        WHERE id = ?
        LIMIT 1
    ");

    $check->execute([$leadId]);

    $lead = $check->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {

        $_SESSION["user_message"] = "Lead not found.";
        $_SESSION["user_message_type"] = "error";

    } else {

        // Delete the lead using a prepared statement
        $delete = $pdo->prepare("
            DELETE FROM leads
            WHERE id = ?
        ");

        $delete->execute([$leadId]);

        if ($delete->rowCount() === 1) {

            // Rotate CSRF token after successful deletion
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            $_SESSION["user_message"] =
                "Lead for " . $lead["customer_name"] . " deleted successfully.";

            $_SESSION["user_message_type"] = "success";

        } else {

            $_SESSION["user_message"] = "Lead could not be deleted.";
            $_SESSION["user_message_type"] = "error";
        }
    }

} catch (Throwable $e) {

    error_log("Lead deletion error: " . $e->getMessage());

    $_SESSION["user_message"] = "Unable to delete the lead. Please try again.";
    $_SESSION["user_message_type"] = "error";
}

header("Location: manage_leads.php");
exit;
