
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";
require_once "../includes/lead_activity_logger.php";

// Only managers who can manage leads can import
if (!hasPermission("manage_leads")) {
    http_response_code(403);
    die("Access Denied!");
}

$message = "";
$messageType = "success";

// Create CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Process CSV upload
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify CSRF token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        die("Invalid security token!");
    }

    if (
        !isset($_FILES["csv_file"]) ||
        $_FILES["csv_file"]["error"] !== UPLOAD_ERR_OK
    ) {

        $message = "Please select a valid CSV file.";
        $messageType = "error";

    } else {

        $fileName = $_FILES["csv_file"]["name"];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension !== "csv") {

            $message = "Only CSV files are allowed.";
            $messageType = "error";

        } else {

            $file = fopen($_FILES["csv_file"]["tmp_name"], "r");

            if (!$file) {

                $message = "Unable to read the CSV file.";
                $messageType = "error";

            } else {

                $requiredHeaders = [
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

                // Skip blank lines before reading headings
                $headers = false;

                while (($line = fgetcsv($file)) !== false) {

                    if (
                        count($line) === 1 &&
                        trim((string)$line[0]) === ""
                    ) {
                        continue;
                    }

                    $headers = $line;
                    break;
                }

                if ($headers === false) {

                    $message = "The CSV file is empty.";
                    $messageType = "error";

                } else {

                    // Clean headings and remove UTF-8 BOM
                    $headers = array_map(
                        function ($header) {

                            $header = preg_replace(
                                '/^\xEF\xBB\xBF/',
                                '',
                                (string)$header
                            );

                            return strtolower(
                                trim($header, " \t\n\r\0\x0B\"'")
                            );
                        },
                        $headers
                    );

                    if ($headers !== $requiredHeaders) {

                        $message = "CSV headings are incorrect. Please use the sample format below.";
                        $messageType = "error";

                    } else {

                        $imported = 0;
                        $skipped = 0;

                        try {

                            $pdo->beginTransaction();

                            while (($row = fgetcsv($file)) !== false) {

                                // Ignore blank rows
                                if (
                                    count($row) === 1 &&
                                    trim((string)$row[0]) === ""
                                ) {
                                    continue;
                                }

                                if (count($row) !== count($requiredHeaders)) {
                                    $skipped++;
                                    continue;
                                }

                                $data = array_combine(
                                    $requiredHeaders,
                                    $row
                                );

                                $customerName = trim($data["customer_name"]);
                                $phone = trim($data["phone"]);
                                $email = trim($data["email"]);
                                $propertyType = trim($data["property_type"]);
                                $location = trim($data["interested_location"]);
                                $budgetMin = trim($data["budget_min"]);
                                $budgetMax = trim($data["budget_max"]);
                                $leadMessage = trim($data["message"]);
                                $leadSource = trim($data["lead_source"]);

                                // Validate required fields
                                if (
                                    $customerName === "" ||
                                    $phone === "" ||
                                    $propertyType === "" ||
                                    $location === ""
                                ) {
                                    $skipped++;
                                    continue;
                                }

                                // Validate email
                                if (
                                    $email !== "" &&
                                    !filter_var($email, FILTER_VALIDATE_EMAIL)
                                ) {
                                    $skipped++;
                                    continue;
                                }

                                // Validate budget values
                                if (
                                    ($budgetMin !== "" && !is_numeric($budgetMin)) ||
                                    ($budgetMax !== "" && !is_numeric($budgetMax))
                                ) {
                                    $skipped++;
                                    continue;
                                }

                                $budgetMinValue = $budgetMin === ""
                                    ? null
                                    : (float)$budgetMin;

                                $budgetMaxValue = $budgetMax === ""
                                    ? null
                                    : (float)$budgetMax;

                                // Check budget range
                                if (
                                    $budgetMinValue !== null &&
                                    $budgetMaxValue !== null &&
                                    $budgetMinValue > $budgetMaxValue
                                ) {
                                    $skipped++;
                                    continue;
                                }

                                // Insert lead
                                $stmt = $pdo->prepare("
                                    INSERT INTO leads (
                                        customer_name,
                                        phone,
                                        email,
                                        property_type,
                                        interested_location,
                                        budget_min,
                                        budget_max,
                                        message,
                                        lead_source,
                                        lead_status,
                                        assigned_to,
                                        created_by
                                    )
                                    VALUES (
                                        ?, ?, ?, ?, ?, ?, ?, ?, ?,
                                        'new', NULL, ?
                                    )
                                ");

                                $stmt->execute([
                                    $customerName,
                                    $phone,
                                    $email !== "" ? $email : null,
                                    $propertyType,
                                    $location,
                                    $budgetMinValue,
                                    $budgetMaxValue,
                                    $leadMessage,
                                    $leadSource !== "" ? $leadSource : "CSV Import",
                                    $_SESSION["admin_id"]
                                ]);

                                $leadId = $pdo->lastInsertId();

                                // Record activity
                                logLeadActivity(
                                    $pdo,
                                    $leadId,
                                    $_SESSION["admin_id"],
                                    "lead_imported",
                                    "Lead imported from CSV file."
                                );

                                $imported++;
                            }

                            $pdo->commit();

                            $message = "Import completed! Imported: " .
                                $imported . " leads. Skipped: " .
                                $skipped . " rows.";

                            $messageType = "success";

                        } catch (PDOException $e) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            $message = "Database error occurred while importing leads.";
                            $messageType = "error";
                        }
                    }
                }

                fclose($file);
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Import Leads</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 750px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h2 {
            margin-top: 0;
        }

        input[type="file"] {
            display: block;
            margin: 20px 0;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .success {
            background: #d1fae5;
            color: #065f46;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .links {
            margin-top: 25px;
        }

        .links a {
            margin-right: 15px;
        }

        code {
            display: block;
            background: #f1f5f9;
            padding: 15px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Import Leads from CSV</h2>

    <p>
        Upload a CSV file to add multiple customer leads to your CRM.
    </p>

    <?php if ($message !== ""): ?>

        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <h3>CSV File Format</h3>

    <p>
        Your CSV file must have these headings in the exact order:
    </p>

    <code>
        customer_name,phone,email,property_type,interested_location,budget_min,budget_max,message,lead_source
    </code>

    <p>
        Required fields: Customer Name, Phone, Property Type and Interested Location.
    </p>

    <form method="POST" enctype="multipart/form-data">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
        >

        <label>
            <strong>Select CSV File:</strong>
        </label>

        <input
            type="file"
            name="csv_file"
            accept=".csv,text/csv"
            required
        >

        <button type="submit">
            Import Leads
        </button>

    </form>

    <div class="links">

        <a href="manage_leads.php">Back to Manage Leads</a>

        <a href="dashboard.php">Dashboard</a>

    </div>

</div>

</body>
</html>
