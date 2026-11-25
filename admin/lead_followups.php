
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";
require_once "../includes/lead_activity_logger.php";

requirePermission("manage_followups");

$message = "";
$messageType = "success";

$adminId = (int)($_SESSION["admin_id"] ?? 0);
$canViewAllLeads = hasPermission("view_all_leads");

// Escape output safely.
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Create a valid CSRF token.
if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    $_SESSION["csrf_token"] === ""
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// Display saved message.
if (isset($_SESSION["user_message"])) {
    $message = $_SESSION["user_message"];
    $messageType = $_SESSION["user_message_type"] ?? "success";

    unset($_SESSION["user_message"], $_SESSION["user_message_type"]);
}

// --------------------------------------------------
// SAVE FOLLOW-UP
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"]) ||
        !is_string($_POST["csrf_token"] ?? null) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $leadIdInput = $_POST["lead_id"] ?? null;
    $followupType = $_POST["followup_type"] ?? null;
    $notesInput = $_POST["notes"] ?? null;
    $nextFollowupInput = $_POST["next_followup_at"] ?? "";

    $allowedTypes = [
        "call",
        "email",
        "whatsapp",
        "meeting",
        "note"
    ];

    if (
        !is_scalar($leadIdInput) ||
        !is_string($followupType) ||
        !is_string($notesInput) ||
        !is_string($nextFollowupInput)
    ) {
        $message = "Invalid form information.";
        $messageType = "error";

    } else {

        $leadId = filter_var(
            $leadIdInput,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        $notes = trim($notesInput);
        $nextFollowup = trim($nextFollowupInput);

        if (
            $leadId === false ||
            $leadId === null ||
            !in_array($followupType, $allowedTypes, true)
        ) {
            $message = "Please select a valid customer and follow-up type.";
            $messageType = "error";

        } elseif ($notes === "") {
            $message = "Please enter follow-up notes.";
            $messageType = "error";

        } elseif (strlen($notes) > 10000) {
            $message = "Follow-up notes cannot exceed 10,000 characters.";
            $messageType = "error";

        } elseif (strlen($nextFollowup) > 16) {
            $message = "Invalid next follow-up date and time.";
            $messageType = "error";

        } else {

            try {

                if ($canViewAllLeads) {

                    $checkLead = $pdo->prepare("
                        SELECT id, customer_name
                        FROM leads
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $checkLead->execute([$leadId]);

                } else {

                    $checkLead = $pdo->prepare("
                        SELECT id, customer_name
                        FROM leads
                        WHERE id = ?
                        AND assigned_to = ?
                        LIMIT 1
                    ");

                    $checkLead->execute([$leadId, $adminId]);
                }

                $lead = $checkLead->fetch(PDO::FETCH_ASSOC);

                if (!$lead) {

                    $message = "You are not allowed to add a follow-up for this lead.";
                    $messageType = "error";

                } else {

                    $nextFollowupValue = null;

                    if ($nextFollowup !== "") {

                        $date = DateTime::createFromFormat(
                            "!Y-m-d\TH:i",
                            $nextFollowup
                        );

                        $dateErrors = DateTime::getLastErrors();

                        if (
                            !$date ||
                            (
                                $dateErrors !== false &&
                                (
                                    $dateErrors["warning_count"] > 0 ||
                                    $dateErrors["error_count"] > 0
                                )
                            ) ||
                            $date->format("Y-m-d\TH:i") !== $nextFollowup
                        ) {
                            $message = "Please enter a valid next follow-up date and time.";
                            $messageType = "error";

                        } else {
                            $nextFollowupValue = $date->format("Y-m-d H:i:s");
                        }
                    }

                    if ($message === "") {

                        $pdo->beginTransaction();

                        $insert = $pdo->prepare("
                            INSERT INTO lead_followups
                            (
                                lead_id,
                                admin_id,
                                followup_type,
                                notes,
                                next_followup_at
                            )
                            VALUES (?, ?, ?, ?, ?)
                        ");

                        $insert->execute([
                            $leadId,
                            $adminId,
                            $followupType,
                            $notes,
                            $nextFollowupValue
                        ]);

                        $followupDescription =
                            "Follow-up added (" .
                            ucwords($followupType) .
                            "): " .
                            $notes;

                        if ($nextFollowupValue !== null) {
                            $followupDescription .=
                                " Next follow-up: " . $nextFollowupValue;
                        }

                        logLeadActivity(
                            $pdo,
                            $leadId,
                            $adminId,
                            "followup_added",
                            $followupDescription,
                            null,
                            $followupType
                        );

                        $pdo->commit();

                        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                        $_SESSION["user_message"] = "Follow-up saved successfully!";
                        $_SESSION["user_message_type"] = "success";

                        header("Location: lead_followups.php");
                        exit;
                    }
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    "Lead follow-up save error: " . $e->getMessage()
                );

                $message = "Unable to save the follow-up. Please try again.";
                $messageType = "error";
            }
        }
    }
}

// --------------------------------------------------
// FETCH AVAILABLE LEADS AND HISTORY
// --------------------------------------------------

try {

    if ($canViewAllLeads) {

        $leadQuery = $pdo->query("
            SELECT id, customer_name, phone
            FROM leads
            ORDER BY customer_name ASC
        ");

    } else {

        $leadQuery = $pdo->prepare("
            SELECT id, customer_name, phone
            FROM leads
            WHERE assigned_to = ?
            ORDER BY customer_name ASC
        ");

        $leadQuery->execute([$adminId]);
    }

    $leads = $leadQuery->fetchAll(PDO::FETCH_ASSOC);

    if ($canViewAllLeads) {

        $historyQuery = $pdo->query("
            SELECT
                lf.id,
                lf.lead_id,
                lf.followup_type,
                lf.notes,
                lf.next_followup_at,
                lf.created_at,
                l.customer_name,
                a.name AS employee_name
            FROM lead_followups lf
            INNER JOIN leads l ON lf.lead_id = l.id
            LEFT JOIN admins a ON lf.admin_id = a.id
            ORDER BY lf.created_at DESC
        ");

    } else {

        $historyQuery = $pdo->prepare("
            SELECT
                lf.id,
                lf.lead_id,
                lf.followup_type,
                lf.notes,
                lf.next_followup_at,
                lf.created_at,
                l.customer_name,
                a.name AS employee_name
            FROM lead_followups lf
            INNER JOIN leads l ON lf.lead_id = l.id
            LEFT JOIN admins a ON lf.admin_id = a.id
            WHERE l.assigned_to = ?
            ORDER BY lf.created_at DESC
        ");

        $historyQuery->execute([$adminId]);
    }

    $followups = $historyQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        "Lead follow-up page loading error: " . $e->getMessage()
    );

    $leads = [];
    $followups = [];

    $message = "Unable to load follow-up information. Please try again.";
    $messageType = "error";
}

$totalFollowups = count($followups);
$scheduledFollowups = 0;

foreach ($followups as $followup) {
    if (!empty($followup["next_followup_at"])) {
        $scheduledFollowups++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lead Follow-ups | Admin Panel</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px;
            background: #f5f7fb;
            color: #263247;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }

        .followup-container {
            width: 100%;
            max-width: 1450px;
            margin: 0 auto;
        }

        /* PAGE HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 25px;
        }

        .page-title h1 {
            margin: 0 0 7px;
            color: #17243a;
            font-size: 28px;
            font-weight: 700;
        }

        .page-title p {
            margin: 0;
            color: #758197;
            font-size: 14px;
        }

        .header-label {
            padding: 10px 15px;
            border: 1px solid #e1e7f0;
            border-radius: 9px;
            background: #ffffff;
            color: #526078;
            font-size: 12px;
            font-weight: 600;
        }

        /* NAVIGATION */

        .navigation {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 9px;
            margin-bottom: 25px;
        }

        .navigation a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border: 1px solid #e1e7f0;
            border-radius: 8px;
            background: #ffffff;
            color: #526078;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }

        .navigation a:hover {
            background: #edf3ff;
            color: #285bb2;
            border-color: #cddcff;
        }

        .navigation a.active {
            background: #285bb2;
            border-color: #285bb2;
            color: #ffffff;
        }

        /* SUMMARY */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .summary-card {
            padding: 21px 23px;
            background: #ffffff;
            border: 1px solid #e4e9f1;
            border-radius: 11px;
            box-shadow: 0 3px 12px rgba(25, 42, 70, 0.035);
        }

        .summary-label {
            margin-bottom: 9px;
            color: #778399;
            font-size: 13px;
            font-weight: 600;
        }

        .summary-number {
            color: #1d3152;
            font-size: 29px;
            font-weight: 700;
        }

        /* ALERTS */

        .message {
            padding: 14px 17px;
            margin-bottom: 22px;
            border: 1px solid #cce9d6;
            border-radius: 9px;
            background: #effaf3;
            color: #247448;
            font-size: 13px;
        }

        .message.error {
            border-color: #f2cccc;
            background: #fff2f2;
            color: #ae3030;
        }

        /* CONTENT CARDS */

        .content-card {
            margin-bottom: 25px;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid #e3e9f1;
            border-radius: 12px;
            box-shadow: 0 3px 13px rgba(25, 42, 70, 0.04);
        }

        .card-heading {
            padding: 22px 25px;
            border-bottom: 1px solid #edf0f5;
        }

        .card-heading h2 {
            margin: 0 0 5px;
            color: #1c2d49;
            font-size: 19px;
        }

        .card-heading p {
            margin: 0;
            color: #7a8699;
            font-size: 13px;
        }

        .card-body {
            padding: 25px;
        }

        /* FORM */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #35445c;
            font-size: 13px;
            font-weight: 700;
        }

        .required-mark {
            color: #dc5555;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            display: block;
            width: 100%;
            min-height: 44px;
            padding: 11px 13px;
            border: 1px solid #dce3ed;
            border-radius: 8px;
            outline: none;
            background: #ffffff;
            color: #293a55;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group textarea {
            min-height: 125px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #5684d5;
            box-shadow: 0 0 0 3px rgba(62, 111, 197, 0.10);
        }

        .field-hint {
            display: block;
            margin-top: 6px;
            color: #8792a4;
            font-size: 11px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 23px;
            padding-top: 19px;
            border-top: 1px solid #edf0f5;
        }

        .save-button {
            min-height: 43px;
            padding: 0 22px;
            border: none;
            border-radius: 8px;
            background: #285bb2;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .save-button:hover {
            background: #1d478f;
            transform: translateY(-1px);
        }

        /* HISTORY TABLE */

        .history-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .history-count {
            padding: 7px 12px;
            border-radius: 20px;
            background: #edf3ff;
            color: #315fae;
            font-size: 11px;
            font-weight: 700;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .history-table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
            text-align: left;
        }

        .history-table th {
            padding: 15px 16px;
            background: #f8fafd;
            border-bottom: 1px solid #e5eaf1;
            color: #65738a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .history-table td {
            padding: 16px;
            border-bottom: 1px solid #edf0f5;
            color: #526078;
            font-size: 12px;
            vertical-align: top;
            line-height: 1.65;
        }

        .history-table tbody tr:hover {
            background: #fafbfd;
        }

        .history-table tbody tr:last-child td {
            border-bottom: none;
        }

        .record-id {
            color: #8994a6;
            font-weight: 700;
        }

        .customer-name {
            color: #263b5d;
            font-weight: 700;
        }

        .followup-type {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #edf3ff;
            color: #315fae;
            font-size: 11px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .followup-notes {
            max-width: 340px;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .scheduled-date {
            color: #27764b;
            font-weight: 600;
            white-space: nowrap;
        }

        .not-scheduled {
            color: #939daf;
            font-size: 11px;
        }

        .employee-name {
            color: #465873;
            font-weight: 600;
        }

        .created-date {
            color: #7c879a;
            white-space: nowrap;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 42px 20px;
            text-align: center;
        }

        .empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            margin: 0 auto 13px;
            border-radius: 50%;
            background: #edf3ff;
            color: #426bb4;
            font-size: 22px;
        }

        .empty-state h3 {
            margin: 0 0 7px;
            color: #34445d;
            font-size: 16px;
        }

        .empty-state p {
            margin: 0;
            color: #8792a4;
            font-size: 13px;
        }

        /* FOOTER */

        .page-footer {
            padding: 15px 5px;
            color: #96a0af;
            text-align: center;
            font-size: 11px;
        }

        /* RESPONSIVE */

        @media (max-width: 750px) {

            body {
                padding: 18px 14px;
            }

            .page-title h1 {
                font-size: 24px;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .summary-card {
                padding: 17px;
            }

            .summary-number {
                font-size: 25px;
            }

            .card-heading,
            .card-body {
                padding: 19px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-group.full-width {
                grid-column: auto;
            }

        }

        @media (max-width: 480px) {

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                align-items: flex-start;
            }

            .header-label {
                width: 100%;
            }

            .navigation a {
                flex: 1;
            }

            .form-actions {
                justify-content: stretch;
            }

            .save-button {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<main class="followup-container">

    <!-- PAGE HEADER -->

    <header class="page-header">

        <div class="page-title">

            <h1>Lead Follow-ups</h1>

            <p>
                Record customer interactions and manage your follow-up history.
            </p>

        </div>

        <div class="header-label">
            CRM Management
        </div>

    </header>

    <!-- NAVIGATION -->

    <nav class="navigation">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="manage_leads.php">
            Manage Leads
        </a>

        <a href="lead_followups.php" class="active">
            Lead Follow-ups
        </a>

    </nav>

    <!-- MESSAGES -->

    <?php if ($message !== ""): ?>

        <div
            class="message <?php echo $messageType === "error" ? "error" : ""; ?>"
            role="alert"
        >
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <!-- SUMMARY -->

    <section class="summary-grid">

        <div class="summary-card">

            <div class="summary-label">
                Follow-up Records
            </div>

            <div class="summary-number">
                <?php echo $totalFollowups; ?>
            </div>

        </div>

        <div class="summary-card">

            <div class="summary-label">
                Scheduled Next Follow-ups
            </div>

            <div class="summary-number">
                <?php echo $scheduledFollowups; ?>
            </div>

        </div>

    </section>

    <!-- ADD FOLLOW-UP -->

    <section class="content-card">

        <div class="card-heading">

            <h2>Add New Follow-up</h2>

            <p>
                Record a call, email, WhatsApp conversation, meeting or customer note.
            </p>

        </div>

        <div class="card-body">

            <?php if (count($leads) > 0): ?>

                <form method="POST" action="lead_followups.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e($_SESSION["csrf_token"]); ?>"
                    >

                    <div class="form-grid">

                        <div class="form-group">

                            <label for="lead_id">
                                Select Customer <span class="required-mark">*</span>
                            </label>

                            <select name="lead_id" id="lead_id" required>

                                <option value="">-- Select Customer --</option>

                                <?php foreach ($leads as $lead): ?>

                                    <option value="<?php echo (int)$lead["id"]; ?>">

                                        <?php
                                        echo e($lead["customer_name"]);
                                        echo " - ";
                                        echo e($lead["phone"]);
                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label for="followup_type">
                                Follow-up Type <span class="required-mark">*</span>
                            </label>

                            <select name="followup_type" id="followup_type" required>

                                <option value="">-- Select Type --</option>
                                <option value="call">Phone Call</option>
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="meeting">Meeting</option>
                                <option value="note">Note</option>

                            </select>

                        </div>

                        <div class="form-group full-width">

                            <label for="notes">
                                Discussion / Follow-up Notes <span class="required-mark">*</span>
                            </label>

                            <textarea
                                name="notes"
                                id="notes"
                                maxlength="10000"
                                placeholder="Write what happened during the customer interaction..."
                                required
                            ></textarea>

                            <span class="field-hint">
                                Maximum 10,000 characters.
                            </span>

                        </div>

                        <div class="form-group">

                            <label for="next_followup_at">
                                Next Follow-up Date and Time
                            </label>

                            <input
                                type="datetime-local"
                                name="next_followup_at"
                                id="next_followup_at"
                            >

                            <span class="field-hint">
                                Optional — schedule the next customer interaction.
                            </span>

                        </div>

                    </div>

                    <div class="form-actions">

                        <button type="submit" class="save-button">
                            Save Follow-up
                        </button>

                    </div>

                </form>

            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-icon">♧</div>

                    <h3>No Available Leads</h3>

                    <p>
                        No leads are currently assigned or available to you.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <!-- FOLLOW-UP HISTORY -->

    <section class="content-card">

        <div class="card-heading history-heading">

            <div>

                <h2>Customer Follow-up History</h2>

                <p>
                    Review previously recorded customer interactions.
                </p>

            </div>

            <span class="history-count">
                <?php echo $totalFollowups; ?> Records
            </span>

        </div>

        <?php if (count($followups) > 0): ?>

            <div class="table-wrapper">

                <table class="history-table">

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Follow-up Type</th>
                            <th>Notes</th>
                            <th>Next Follow-up</th>
                            <th>Added By</th>
                            <th>Created Date</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($followups as $followup): ?>

                            <tr>

                                <td class="record-id">
                                    #<?php echo (int)$followup["id"]; ?>
                                </td>

                                <td class="customer-name">
                                    <?php echo e($followup["customer_name"]); ?>
                                </td>

                                <td>

                                    <span class="followup-type">

                                        <?php
                                        echo e(
                                            ucwords(
                                                str_replace(
                                                    "_",
                                                    " ",
                                                    $followup["followup_type"]
                                                )
                                            )
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td class="followup-notes">
                                    <?php echo nl2br(e($followup["notes"])); ?>
                                </td>

                                <td>

                                    <?php if (!empty($followup["next_followup_at"])): ?>

                                        <span class="scheduled-date">
                                            <?php echo e($followup["next_followup_at"]); ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="not-scheduled">
                                            Not scheduled
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="employee-name">
                                    <?php echo e($followup["employee_name"] ?? "Deleted User"); ?>
                                </td>

                                <td class="created-date">
                                    <?php echo e($followup["created_at"]); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <div class="empty-icon">◷</div>

                <h3>No Follow-up Records Yet</h3>

                <p>
                    Saved customer interactions will appear here.
                </p>

            </div>

        <?php endif; ?>

    </section>

    <footer class="page-footer">
        Olivera Home Developers — CRM Management
    </footer>

</main>

</body>
</html>
