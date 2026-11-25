
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

// --------------------------------------------------
// PERMISSION CHECK
// --------------------------------------------------

if (!hasPermission("manage_leads")) {
    http_response_code(403);
    exit("Access Denied! Only CRM managers can edit customer details.");
}

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    $_SESSION["csrf_token"] === ""
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// --------------------------------------------------
// VARIABLES
// --------------------------------------------------

$message = "";
$messageType = "error";

// --------------------------------------------------
// ESCAPE OUTPUT
// --------------------------------------------------

function editLeadEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// --------------------------------------------------
// GET LEAD ID
// --------------------------------------------------

$rawLeadId = $_SERVER["REQUEST_METHOD"] === "POST"
    ? ($_POST["lead_id"] ?? "")
    : ($_GET["id"] ?? "");

$leadId = filter_var(
    $rawLeadId,
    FILTER_VALIDATE_INT
);

if (!$leadId || $leadId <= 0) {
    http_response_code(400);
    exit("Invalid lead ID.");
}

// --------------------------------------------------
// FETCH EXISTING LEAD
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM leads
    WHERE id = ?
");

$stmt->execute([$leadId]);

$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    http_response_code(404);
    exit("Customer lead not found.");
}

// --------------------------------------------------
// ALLOWED PROPERTY TYPES
// --------------------------------------------------

$propertyTypes = [
    "Apartment",
    "Villa",
    "Plot",
    "Commercial",
    "Office",
    "Other"
];

// --------------------------------------------------
// ALLOWED LEAD SOURCES
// --------------------------------------------------

$leadSources = [
    "Manual Entry",
    "Website",
    "Phone Call",
    "WhatsApp",
    "Facebook",
    "Instagram",
    "Google Ads",
    "YouTube",
    "Referral",
    "Other"
];

// Preserve an existing source not listed above.
$currentLeadSource = (string)($lead["lead_source"] ?? "");

if (
    $currentLeadSource !== "" &&
    !in_array($currentLeadSource, $leadSources, true)
) {
    $leadSources[] = $currentLeadSource;
}

// --------------------------------------------------
// HANDLE UPDATE
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // --------------------------------------------------
    // CSRF VALIDATION
    // --------------------------------------------------

    if (
        !isset($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"]) ||
        !is_string($_POST["csrf_token"] ?? null) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    // --------------------------------------------------
    // VALIDATE FIELD TYPES
    // --------------------------------------------------

    $fields = [
        "customer_name",
        "phone",
        "email",
        "property_type",
        "interested_location",
        "budget_min",
        "budget_max",
        "message",
        "lead_source"
    ];

    foreach ($fields as $field) {

        if (
            isset($_POST[$field]) &&
            !is_string($_POST[$field])
        ) {
            http_response_code(400);
            exit("Invalid form data.");
        }
    }

    // --------------------------------------------------
    // READ FORM VALUES
    // --------------------------------------------------

    $customerName = trim($_POST["customer_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $propertyType = trim($_POST["property_type"] ?? "");
    $location = trim($_POST["interested_location"] ?? "");
    $budgetMin = trim($_POST["budget_min"] ?? "");
    $budgetMax = trim($_POST["budget_max"] ?? "");
    $leadMessage = trim($_POST["message"] ?? "");
    $leadSource = trim($_POST["lead_source"] ?? "");

    // --------------------------------------------------
    // VALIDATION
    // --------------------------------------------------

    if ($customerName === "" || $phone === "") {

        $message = "Customer name and phone number are required.";

    } elseif (
        strlen($customerName) > 150 ||
        strlen($phone) > 30
    ) {

        $message = "Customer name or phone number is too long.";

    } elseif (
        !empty($lead["customer_id"]) &&
        (
            strlen($customerName) > 100 ||
            strlen($phone) > 20
        )
    ) {

        $message = "For a linked customer, name must not exceed 100 characters and phone must not exceed 20 characters.";

    } elseif (
        strlen($email) > 150 ||
        (
            $email !== "" &&
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        )
    ) {

        $message = "Please enter a valid email address.";

    } elseif (
        ($budgetMin !== "" &&
            (
                !is_numeric($budgetMin) ||
                !is_finite((float)$budgetMin) ||
                (float)$budgetMin < 0
            )
        ) ||
        ($budgetMax !== "" &&
            (
                !is_numeric($budgetMax) ||
                !is_finite((float)$budgetMax) ||
                (float)$budgetMax < 0
            )
        )
    ) {

        $message = "Please enter valid budget amounts.";

    } elseif (
        $budgetMin !== "" &&
        $budgetMax !== "" &&
        (float)$budgetMin > (float)$budgetMax
    ) {

        $message = "Minimum budget cannot exceed maximum budget.";

    } elseif (
        $propertyType !== "" &&
        !in_array($propertyType, $propertyTypes, true)
    ) {

        $message = "Please select a valid property type.";

    } elseif (
        !in_array($leadSource, $leadSources, true)
    ) {

        $message = "Please select a valid lead source.";

    } elseif (
        strlen($location) > 255 ||
        strlen($leadMessage) > 5000
    ) {

        $message = "Location or customer message is too long.";

    } else {

        // --------------------------------------------------
        // UPDATE LEAD AND LINKED CUSTOMER
        // --------------------------------------------------

        try {

            $pdo->beginTransaction();

            // Lock the lead to protect its customer connection.
            $lockLead = $pdo->prepare("
                SELECT
                    id,
                    customer_id,
                    property_enquiry_id
                FROM leads
                WHERE id = ?
                FOR UPDATE
            ");

            $lockLead->execute([$leadId]);

            $lockedLead = $lockLead->fetch(PDO::FETCH_ASSOC);

            if (!$lockedLead) {
                throw new RuntimeException("Lead no longer exists.");
            }

            $linkedCustomerId = $lockedLead["customer_id"];

            // --------------------------------------------------
            // UPDATE LINKED CUSTOMER
            // --------------------------------------------------

            if (!empty($linkedCustomerId)) {

                $customerStmt = $pdo->prepare("
                    SELECT
                        id,
                        name,
                        email,
                        phone
                    FROM customers
                    WHERE id = ?
                    FOR UPDATE
                ");

                $customerStmt->execute([
                    $linkedCustomerId
                ]);

                $linkedCustomer = $customerStmt->fetch(PDO::FETCH_ASSOC);

                if (!$linkedCustomer) {
                    throw new RuntimeException(
                        "The customer linked to this lead could not be found."
                    );
                }

                // Customers require an email address.
                // If the email field is left blank, preserve the existing one.
                $customerEmail = $email !== ""
                    ? $email
                    : $linkedCustomer["email"];

                if (
                    $customerEmail === "" ||
                    !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)
                ) {
                    throw new RuntimeException(
                        "A valid email address is required for a linked customer."
                    );
                }

                if (strlen($customerEmail) > 150) {
                    throw new RuntimeException(
                        "Customer email must not exceed 150 characters."
                    );
                }

                // Check whether another customer already uses this email.
                $duplicateCheck = $pdo->prepare("
                    SELECT id
                    FROM customers
                    WHERE email = ?
                    AND id != ?
                    LIMIT 1
                ");

                $duplicateCheck->execute([
                    $customerEmail,
                    $linkedCustomerId
                ]);

                if ($duplicateCheck->fetchColumn()) {
                    throw new RuntimeException(
                        "Another customer already uses this email address."
                    );
                }

                // Update customer details.
                // IMPORTANT: Password is deliberately not modified.
                $updateCustomer = $pdo->prepare("
                    UPDATE customers
                    SET
                        name = ?,
                        email = ?,
                        phone = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $updateCustomer->execute([
                    $customerName,
                    $customerEmail,
                    $phone,
                    $linkedCustomerId
                ]);

                // Keep the lead email consistent with the customer.
                $email = $customerEmail;
            }

            // --------------------------------------------------
            // UPDATE CRM LEAD
            // --------------------------------------------------

            $updateLead = $pdo->prepare("
                UPDATE leads SET
                    customer_name = ?,
                    phone = ?,
                    email = ?,
                    property_type = ?,
                    interested_location = ?,
                    budget_min = ?,
                    budget_max = ?,
                    message = ?,
                    lead_source = ?
                WHERE id = ?
            ");

            $updateLead->execute([
                $customerName,
                $phone,
                $email !== "" ? $email : null,
                $propertyType !== "" ? $propertyType : null,
                $location !== "" ? $location : null,
                $budgetMin !== "" ? $budgetMin : null,
                $budgetMax !== "" ? $budgetMax : null,
                $leadMessage !== "" ? $leadMessage : null,
                $leadSource,
                $leadId
            ]);

            // --------------------------------------------------
            // COMMIT BOTH UPDATES
            // --------------------------------------------------

            $pdo->commit();

            // Rotate CSRF token.
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            $_SESSION["user_message"] =
                "Customer and CRM lead details updated successfully.";

            $_SESSION["user_message_type"] = "success";

            header("Location: manage_leads.php");
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                "Lead and customer update error: " .
                $e->getMessage()
            );

            if ($e instanceof RuntimeException) {
                $message = $e->getMessage();
            } else {
                $message = "Unable to update the customer lead. Please try again.";
            }
        }
    }

    // --------------------------------------------------
    // PRESERVE ENTERED VALUES
    // --------------------------------------------------

    $lead["customer_name"] = $customerName;
    $lead["phone"] = $phone;
    $lead["email"] = $email;
    $lead["property_type"] = $propertyType;
    $lead["interested_location"] = $location;
    $lead["budget_min"] = $budgetMin;
    $lead["budget_max"] = $budgetMax;
    $lead["message"] = $leadMessage;
    $lead["lead_source"] = $leadSource;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Customer Lead</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>

        .edit-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 25px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .edit-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .form-group textarea {
            min-height: 120px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .save-button {
            padding: 12px 22px;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
        }

        .message {
            padding: 12px;
            margin-bottom: 20px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 5px;
        }

        @media (max-width: 650px) {

            .edit-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }

        }

    </style>

</head>

<body>

<div class="edit-container">

    <a href="manage_leads.php" class="back-link">
        &larr; Back to Manage Leads
    </a>

    <h1>Edit Customer Lead #<?php echo (int)$leadId; ?></h1>

    <p>Update the customer's contact and property requirements.</p>

    <?php if ($message !== ""): ?>

        <div class="message">
            <?php echo editLeadEscape($message); ?>
        </div>

    <?php endif; ?>

    <form
        method="POST"
        action="edit_lead.php?id=<?php echo (int)$leadId; ?>"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo editLeadEscape($_SESSION["csrf_token"]); ?>"
        >

        <input
            type="hidden"
            name="lead_id"
            value="<?php echo (int)$leadId; ?>"
        >

        <div class="edit-grid">

            <div class="form-group">

                <label>Customer Name *</label>

                <input
                    type="text"
                    name="customer_name"
                    maxlength="150"
                    required
                    value="<?php echo editLeadEscape($lead["customer_name"] ?? ""); ?>"
                >

            </div>

            <div class="form-group">

                <label>Phone Number *</label>

                <input
                    type="tel"
                    name="phone"
                    maxlength="30"
                    required
                    value="<?php echo editLeadEscape($lead["phone"] ?? ""); ?>"
                >

            </div>

            <div class="form-group">

                <label>Email Address</label>

                <input
                    type="email"
                    name="email"
                    maxlength="150"
                    value="<?php echo editLeadEscape($lead["email"] ?? ""); ?>"
                >

            </div>

            <div class="form-group">

                <label>Property Type</label>

                <select name="property_type">

                    <option value="">Select Property Type</option>

                    <?php foreach ($propertyTypes as $type): ?>

                        <option
                            value="<?php echo editLeadEscape($type); ?>"
                            <?php echo (($lead["property_type"] ?? "") === $type) ? "selected" : ""; ?>
                        >
                            <?php echo editLeadEscape($type); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Interested Location</label>

                <input
                    type="text"
                    name="interested_location"
                    maxlength="255"
                    value="<?php echo editLeadEscape($lead["interested_location"] ?? ""); ?>"
                >

            </div>

            <div class="form-group">

                <label>Lead Source</label>

                <select name="lead_source">

                    <?php foreach ($leadSources as $source): ?>

                        <option
                            value="<?php echo editLeadEscape($source); ?>"
                            <?php echo (($lead["lead_source"] ?? "") === $source) ? "selected" : ""; ?>
                        >
                            <?php echo editLeadEscape($source); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Minimum Budget</label>

                <input
                    type="number"
                    name="budget_min"
                    min="0"
                    step="0.01"
                    value="<?php echo editLeadEscape((string)($lead["budget_min"] ?? "")); ?>"
                >

            </div>

            <div class="form-group">

                <label>Maximum Budget</label>

                <input
                    type="number"
                    name="budget_max"
                    min="0"
                    step="0.01"
                    value="<?php echo editLeadEscape((string)($lead["budget_max"] ?? "")); ?>"
                >

            </div>

            <div class="form-group full-width">

                <label>Customer Message / Requirements</label>

                <textarea
                    name="message"
                    maxlength="5000"
                ><?php echo editLeadEscape($lead["message"] ?? ""); ?></textarea>

            </div>

            <div class="form-group full-width">

                <button type="submit" class="save-button">
                    Save Updated Lead
                </button>

            </div>

        </div>

    </form>

</div>

</body>

</html>
