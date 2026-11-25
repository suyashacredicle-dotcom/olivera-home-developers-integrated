
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";
require_once "../includes/lead_activity_logger.php";

requirePermission("manage_site_visits");

$adminId = (int) $_SESSION["admin_id"];
$canViewAll = hasPermission("view_all_leads");

$message = "";
$messageType = "success";

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
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
// FLASH MESSAGE
// --------------------------------------------------

if (isset($_SESSION["user_message"])) {
    $message = (string) $_SESSION["user_message"];
    unset($_SESSION["user_message"]);
}

// --------------------------------------------------
// HANDLE CANCELLATION
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $sessionToken = $_SESSION["csrf_token"] ?? null;
    $postedToken = $_POST["csrf_token"] ?? null;

    if (
        !is_string($sessionToken) ||
        $sessionToken === "" ||
        !is_string($postedToken) ||
        $postedToken === "" ||
        !hash_equals($sessionToken, $postedToken)
    ) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $visitIdInput = $_POST["visit_id"] ?? null;

    $visitId = (
        is_string($visitIdInput) &&
        ctype_digit($visitIdInput)
    ) ? (int) $visitIdInput : 0;

    $reasonInput = $_POST["cancellation_reason"] ?? "";

    if (!is_string($reasonInput)) {
        $reasonInput = "";
    }

    $reason = trim($reasonInput);

    $reasonLength = function_exists("mb_strlen")
        ? mb_strlen($reason, "UTF-8")
        : strlen($reason);

    if ($visitId <= 0) {

        $message = "Invalid site visit selected.";
        $messageType = "error";

    } elseif ($reason === "") {

        $message = "Please enter a cancellation reason.";
        $messageType = "error";

    } elseif ($reasonLength > 1000) {

        $message = "Cancellation reason cannot exceed 1000 characters.";
        $messageType = "error";

    } else {

        try {

            $pdo->beginTransaction();

            if ($canViewAll) {

                $check = $pdo->prepare("
                    SELECT
                        sv.id,
                        sv.lead_id,
                        sv.visit_status,
                        sv.scheduled_at,
                        l.customer_name
                    FROM site_visits sv
                    INNER JOIN leads l ON sv.lead_id = l.id
                    WHERE sv.id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $check->execute([$visitId]);

            } else {

                $check = $pdo->prepare("
                    SELECT
                        sv.id,
                        sv.lead_id,
                        sv.visit_status,
                        sv.scheduled_at,
                        l.customer_name
                    FROM site_visits sv
                    INNER JOIN leads l ON sv.lead_id = l.id
                    WHERE sv.id = ?
                    AND sv.assigned_to = ?
                    AND l.assigned_to = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $check->execute([
                    $visitId,
                    $adminId,
                    $adminId
                ]);
            }

            $visit = $check->fetch(PDO::FETCH_ASSOC);

            if (!$visit) {
                throw new RuntimeException("VISIT_NOT_FOUND");
            }

            if (
                !in_array(
                    $visit["visit_status"],
                    ["scheduled", "rescheduled"],
                    true
                )
            ) {
                throw new RuntimeException("VISIT_STATUS_INVALID");
            }

            $oldStatus = $visit["visit_status"];

            $update = $pdo->prepare("
                UPDATE site_visits
                SET
                    visit_status = 'cancelled',
                    cancellation_reason = ?,
                    cancelled_by = ?,
                    cancelled_at = NOW()
                WHERE id = ?
                AND visit_status IN ('scheduled', 'rescheduled')
            ");

            $update->execute([
                $reason,
                $adminId,
                $visitId
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException("VISIT_UPDATE_FAILED");
            }

            $activityDescription =
                "Site visit cancelled. Previous status: " .
                ucwords(str_replace("_", " ", $oldStatus)) .
                ". Scheduled date: " .
                date("d-m-Y h:i A", strtotime($visit["scheduled_at"])) .
                ". Cancellation reason: " .
                $reason;

            logLeadActivity(
                $pdo,
                (int) $visit["lead_id"],
                $adminId,
                "site_visit_cancelled",
                $activityDescription,
                $oldStatus,
                "cancelled"
            );

            $pdo->commit();

            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            $_SESSION["user_message"] =
                "Site visit for " .
                $visit["customer_name"] .
                " was cancelled successfully. The reason has been recorded.";

            header("Location: cancel_site_visit.php");
            exit;

        } catch (RuntimeException $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception->getMessage() === "VISIT_NOT_FOUND") {

                $message = "Visit not found or you are not allowed to cancel it.";

            } elseif ($exception->getMessage() === "VISIT_STATUS_INVALID") {

                $message = "Only scheduled or rescheduled visits can be cancelled. Completed or already cancelled visits cannot be cancelled again.";

            } else {

                error_log(
                    "Site visit cancellation error: " .
                    $exception->getMessage()
                );

                $message = "Unable to cancel this visit. Please try again.";
            }

            $messageType = "error";

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                "Site visit cancellation error: " .
                $exception->getMessage()
            );

            $message = "Unable to cancel this visit due to a system error. Please try again.";
            $messageType = "error";
        }
    }
}

// --------------------------------------------------
// FETCH SITE VISITS
// --------------------------------------------------

if ($canViewAll) {

    $visitQuery = $pdo->query("
        SELECT
            sv.id,
            sv.lead_id,
            sv.scheduled_at,
            sv.visit_status,
            sv.notes,
            sv.cancellation_reason,
            sv.cancelled_at,
            sv.cancelled_by,
            l.customer_name,
            l.phone,
            l.property_type,
            l.interested_location,
            assigned.name AS assigned_employee,
            canceller.name AS cancelled_by_name
        FROM site_visits sv
        INNER JOIN leads l ON sv.lead_id = l.id
        LEFT JOIN admins assigned
            ON sv.assigned_to = assigned.id
        LEFT JOIN admins canceller
            ON sv.cancelled_by = canceller.id
        ORDER BY sv.scheduled_at DESC
    ");

} else {

    $visitQuery = $pdo->prepare("
        SELECT
            sv.id,
            sv.lead_id,
            sv.scheduled_at,
            sv.visit_status,
            sv.notes,
            sv.cancellation_reason,
            sv.cancelled_at,
            sv.cancelled_by,
            l.customer_name,
            l.phone,
            l.property_type,
            l.interested_location,
            assigned.name AS assigned_employee,
            canceller.name AS cancelled_by_name
        FROM site_visits sv
        INNER JOIN leads l ON sv.lead_id = l.id
        LEFT JOIN admins assigned
            ON sv.assigned_to = assigned.id
        LEFT JOIN admins canceller
            ON sv.cancelled_by = canceller.id
        WHERE sv.assigned_to = ?
        AND l.assigned_to = ?
        ORDER BY sv.scheduled_at DESC
    ");

    $visitQuery->execute([
        $adminId,
        $adminId
    ]);
}

$visits = $visitQuery->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Cancel Site Visits | Olivera Home Developers</title>

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
            font-family: Arial, Helvetica, sans-serif;
            background: var(--ivory);
            margin: 0;
            padding: 30px;
            color: var(--text);
        }

        .container {
            max-width: 1450px;
            margin: auto;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: var(--navy);
            font-size: 28px;
            margin: 0 0 9px;
        }

        .page-header p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-bottom: 22px;
        }

        .nav-links a {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--navy);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s ease;
        }

        .nav-links a:hover {
            background: #f0e7d1;
            border-color: var(--gold);
        }

        .card {
            background: var(--surface);
            padding: 24px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(7,27,56,0.04);
        }

        h2 {
            color: var(--navy);
            font-size: 19px;
            margin: 0 0 20px;
        }

        .message {
            padding: 14px 17px;
            border-radius: 7px;
            margin-bottom: 20px;
            background: #e6f5e9;
            border: 1px solid #c9ecd5;
            color: #216b32;
            font-size: 13px;
            line-height: 1.6;
        }

        .message.error {
            background: #fde8e8;
            border-color: #f4cfcb;
            color: #a32222;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #f0ede6;
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }

        th {
            background: var(--ivory);
            color: var(--navy);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fcfaf5;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        label {
            display: block;
            margin: 12px 0 7px;
            font-weight: 700;
            color: var(--navy);
            font-size: 12px;
        }

        textarea {
            width: 100%;
            min-height: 75px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            font-size: 12px;
            resize: vertical;
        }

        textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,0.15);
        }

        button {
            margin-top: 9px;
            padding: 10px 14px;
            background: var(--navy);
            color: white;
            border: 1px solid var(--navy);
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s ease;
        }

        button:hover {
            background: var(--navy-light);
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
            background: #e5f1ff;
            color: #174b7a;
            white-space: nowrap;
        }

        .status.completed {
            background: #e5f5e9;
            color: #216b32;
        }

        .status.cancelled {
            background: #fde8e8;
            color: #a32222;
        }

        .status.rescheduled {
            background: #fff3dc;
            color: #80540b;
        }

        .status.scheduled {
            background: #e5f1ff;
            color: #174b7a;
        }

        .reason {
            color: #a32222;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .empty {
            color: var(--muted);
        }

        .property {
            line-height: 1.7;
        }

        .property strong {
            display: block;
            color: var(--navy);
        }

        .cancel-form {
            min-width: 230px;
        }

        .cancelled-details {
            min-width: 200px;
            line-height: 1.6;
        }

        .cancelled-label {
            display: inline-block;
            padding: 7px 10px;
            background: #fde8e8;
            color: #a32222;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
        }

        .empty-state {
            padding: 35px 15px;
            text-align: center;
            color: var(--muted);
        }

        @media (max-width: 700px) {

            body {
                padding: 15px;
            }

            .card {
                padding: 16px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .nav-links a {
                padding: 9px 11px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <header class="page-header">

        <h1>Site Visit Cancellation Management</h1>

        <p>
            Cancel scheduled site visits and maintain a record
            of the cancellation reason.
        </p>

    </header>

    <nav class="nav-links">

        <a href="dashboard.php">Dashboard</a>
        <a href="site_visits.php">Monthly Calendar</a>
        <a href="weekly_site_visits.php">Weekly Schedule</a>
        <a href="reschedule_site_visits.php">Reschedule Visits</a>
        <a href="site_visit_reschedule_history.php">Rescheduling History</a>
        <a href="manage_leads.php">Manage Leads</a>

    </nav>

    <?php if ($message !== ""): ?>

        <div
            class="message <?php echo $messageType === "error" ? "error" : ""; ?>"
            role="alert"
        >
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <section class="card">

        <h2>Site Visit Cancellation Records</h2>

        <?php if (count($visits) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Visit ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Property Details</th>
                            <th>Visit Date and Time</th>
                            <th>Assigned Employee</th>
                            <th>Status</th>
                            <th>Cancellation Reason</th>
                            <th>Cancelled By</th>
                            <th>Cancelled At</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($visits as $visit): ?>

                            <tr>

                                <td>
                                    #<?php echo (int) $visit["id"]; ?>
                                </td>

                                <td>
                                    <?php echo e($visit["customer_name"]); ?>
                                </td>

                                <td>
                                    <?php echo e($visit["phone"]); ?>
                                </td>

                                <td class="property">

                                    <strong>
                                        <?php echo e($visit["property_type"] ?? "Not specified"); ?>
                                    </strong>

                                    <?php echo e($visit["interested_location"] ?? "Location not specified"); ?>

                                </td>

                                <td>
                                    <?php
                                    echo e(
                                        date(
                                            "d-m-Y h:i A",
                                            strtotime($visit["scheduled_at"])
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php echo e($visit["assigned_employee"] ?? "Not Assigned"); ?>
                                </td>

                                <td>

                                    <span class="status <?php echo e($visit["visit_status"]); ?>">
                                        <?php
                                        echo e(
                                            ucwords(
                                                str_replace(
                                                    "_",
                                                    " ",
                                                    $visit["visit_status"]
                                                )
                                            )
                                        );
                                        ?>
                                    </span>

                                </td>

                                <td class="cancelled-details">

                                    <?php if ($visit["visit_status"] === "cancelled"): ?>

                                        <div class="reason">
                                            <?php
                                            echo nl2br(
                                                e(
                                                    $visit["cancellation_reason"] ??
                                                    "Reason not recorded"
                                                )
                                            );
                                            ?>
                                        </div>

                                    <?php else: ?>

                                        Not cancelled

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?php
                                    echo $visit["visit_status"] === "cancelled"
                                        ? e($visit["cancelled_by_name"] ?? "Deleted User")
                                        : "-";
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo $visit["cancelled_at"]
                                        ? e(
                                            date(
                                                "d-m-Y h:i A",
                                                strtotime($visit["cancelled_at"])
                                            )
                                        )
                                        : "-";
                                    ?>
                                </td>

                                <td class="cancel-form">

                                    <?php if (
                                        in_array(
                                            $visit["visit_status"],
                                            ["scheduled", "rescheduled"],
                                            true
                                        )
                                    ): ?>

                                        <form
                                            method="POST"
                                            action="cancel_site_visit.php"
                                            onsubmit="return confirm('Are you sure you want to cancel this site visit?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo e($_SESSION["csrf_token"]); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="visit_id"
                                                value="<?php echo (int) $visit["id"]; ?>"
                                            >

                                            <label>
                                                Cancellation Reason *
                                            </label>

                                            <textarea
                                                name="cancellation_reason"
                                                maxlength="1000"
                                                required
                                                placeholder="Explain why this site visit is being cancelled..."
                                            ></textarea>

                                            <button type="submit">
                                                Cancel Visit
                                            </button>

                                        </form>

                                    <?php elseif ($visit["visit_status"] === "cancelled"): ?>

                                        <span class="cancelled-label">
                                            Already Cancelled
                                        </span>

                                    <?php else: ?>

                                        <span class="empty">
                                            Completed visits cannot be cancelled.
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">
                No site visits found.
            </div>

        <?php endif; ?>

    </section>

</div>

</body>
</html>
