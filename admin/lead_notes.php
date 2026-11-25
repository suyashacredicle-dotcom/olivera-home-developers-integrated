
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";
require_once "../includes/lead_activity_logger.php";

// Check permissions
if (
    !hasPermission("manage_leads") &&
    !hasPermission("view_assigned_leads")
) {
    http_response_code(403);
    exit("Access Denied!");
}

// Create CSRF token if required
if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    $_SESSION["csrf_token"] === ""
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Escape output
function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$adminId = (int) $_SESSION["admin_id"];
$currentRole = $_SESSION["admin_role"];

$message = "";
$error = "";

// Get lead ID
$leadId = 0;

if (isset($_GET["lead_id"])) {
    $leadId = filter_var(
        $_GET["lead_id"],
        FILTER_VALIDATE_INT
    );
} elseif (
    isset($_POST["lead_id"]) &&
    is_string($_POST["lead_id"])
) {
    $leadId = filter_var(
        $_POST["lead_id"],
        FILTER_VALIDATE_INT
    );
}

if (!$leadId || $leadId <= 0) {
    http_response_code(400);
    exit("Invalid Lead ID!");
}

// Fetch lead and check employee access
if ($currentRole === "sales_employee") {

    $stmt = $pdo->prepare("
        SELECT *
        FROM leads
        WHERE id = ?
        AND assigned_to = ?
    ");

    $stmt->execute([
        $leadId,
        $adminId
    ]);

} else {

    $stmt = $pdo->prepare("
        SELECT *
        FROM leads
        WHERE id = ?
    ");

    $stmt->execute([$leadId]);
}

$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    http_response_code(403);
    exit("Lead not found or Access Denied!");
}

// Add internal note
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate CSRF token
    if (
        !isset($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"]) ||
        !isset($_POST["csrf_token"]) ||
        !is_string($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {
        http_response_code(403);
        exit("Invalid security token!");
    }

    // Validate note input
    if (
        !isset($_POST["note"]) ||
        !is_string($_POST["note"])
    ) {

        $error = "Invalid note input.";

    } else {

        $note = trim($_POST["note"]);

        if ($note === "") {

            $error = "Please enter a note.";

        } elseif (strlen($note) > 10000) {

            $error = "Note cannot exceed 10,000 bytes.";

        } else {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO lead_notes (
                        lead_id,
                        admin_id,
                        note
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $leadId,
                    $adminId,
                    $note
                ]);

                logLeadActivity(
                    $pdo,
                    $leadId,
                    $adminId,
                    "internal_note_added",
                    "Internal note added",
                    null,
                    null
                );

                $pdo->commit();

                // Rotate CSRF token after successful submission
                $_SESSION["csrf_token"] = bin2hex(
                    random_bytes(32)
                );

                $_SESSION["note_message"] =
                    "Internal note added successfully!";

                header(
                    "Location: lead_notes.php?lead_id=" .
                    $leadId
                );

                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    "Lead Note Error: " . $e->getMessage()
                );

                $error = "Unable to save the note. Please try again.";

            }
        }
    }
}

// Display success message
if (isset($_SESSION["note_message"])) {

    $message = $_SESSION["note_message"];

    unset($_SESSION["note_message"]);
}

// Fetch previous notes
try {

    $stmt = $pdo->prepare("
        SELECT
            ln.*,
            a.name AS admin_name
        FROM lead_notes ln
        LEFT JOIN admins a
            ON ln.admin_id = a.id
        WHERE ln.lead_id = ?
        ORDER BY ln.created_at DESC
    ");

    $stmt->execute([$leadId]);

    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        "Lead Notes Fetch Error: " . $e->getMessage()
    );

    $notes = [];

    $error = "Unable to load previous notes.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Internal Lead Notes</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 25px;
        }

        .container {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h2, h3 {
            color: #333;
        }

        textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background: #007bff;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }

        .note {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .success {
            color: green;
            margin-bottom: 15px;
        }

        .error {
            color: red;
            margin-bottom: 15px;
        }

        .note-meta {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
        }

        a {
            margin-right: 15px;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Internal Notes</h2>

    <h3>
        Customer: <?= e($lead["customer_name"]) ?>
    </h3>

    <p>
        <strong>Lead ID:</strong>
        <?= (int) $lead["id"] ?>
    </p>

    <p>

        <a href="view_lead.php?id=<?= (int) $leadId ?>">
            Back to Lead Details
        </a>

        <a href="manage_leads.php">
            Manage Leads
        </a>

        <a href="dashboard.php">
            Dashboard
        </a>

    </p>

    <hr>

    <h3>Add Internal Note</h3>

    <?php if ($message): ?>

        <p class="success">
            <?= e($message) ?>
        </p>

    <?php endif; ?>

    <?php if ($error): ?>

        <p class="error">
            <?= e($error) ?>
        </p>

    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION["csrf_token"]) ?>"
        >

        <input
            type="hidden"
            name="lead_id"
            value="<?= (int) $leadId ?>"
        >

        <textarea
            name="note"
            maxlength="10000"
            required
            placeholder="Write an internal note about this customer..."
        ></textarea>

        <br>

        <button type="submit">
            Save Note
        </button>

    </form>

    <hr>

    <h3>Previous Notes</h3>

    <?php if (count($notes) > 0): ?>

        <?php foreach ($notes as $note): ?>

            <div class="note">

                <div class="note-meta">

                    <strong>
                        <?= e(
                            $note["admin_name"] ?? "Deleted Admin"
                        ) ?>
                    </strong>

                    |

                    <?= e($note["created_at"]) ?>

                </div>

                <div>
                    <?= nl2br(e($note["note"])) ?>
                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p>No internal notes have been added yet.</p>

    <?php endif; ?>

</div>

</body>
</html>
