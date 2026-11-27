
<?php

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// --------------------------------------------------
// REQUIRED FILES
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/lead_activity_logger.php";

requirePermission("manage_site_visits");

$adminId = (int)($_SESSION["admin_id"] ?? 0);
$canViewAll = hasPermission("view_all_leads");

$message = "";
$messageType = "success";

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function formatVisitDate($value, $format = "d-m-Y h:i A"): string
{
    if (!is_string($value) || trim($value) === "") {
        return "Not available";
    }

    try {
        return (new DateTime($value))->format($format);
    } catch (Throwable $exception) {
        return "Not available";
    }
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
    $message = is_string($_SESSION["user_message"])
        ? $_SESSION["user_message"]
        : "";

    unset($_SESSION["user_message"]);
}

// --------------------------------------------------
// HANDLE RESCHEDULING
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
    ) ? (int)$visitIdInput : 0;

    $newScheduledAt = $_POST["new_scheduled_at"] ?? "";

    if (!is_string($newScheduledAt)) {
        $newScheduledAt = "";
    }

    $newScheduledAt = trim($newScheduledAt);

    if ($visitId <= 0 || $newScheduledAt === "") {

        $message = "Please select a valid visit and enter the new date and time.";
        $messageType = "error";

    } else {

        $newDate = DateTime::createFromFormat(
            "!Y-m-d\TH:i",
            $newScheduledAt
        );

        $dateErrors = DateTime::getLastErrors();

        $validDate = (
            $newDate !== false &&
            (
                $dateErrors === false ||
                (
                    $dateErrors["warning_count"] === 0 &&
                    $dateErrors["error_count"] === 0
                )
            ) &&
            $newDate->format("Y-m-d\TH:i") === $newScheduledAt
        );

        if (!$validDate) {

            $message = "Please enter a valid new visit date and time.";
            $messageType = "error";

        } elseif ($newDate <= new DateTime()) {

            $message = "The new site visit date and time must be in the future.";
            $messageType = "error";

        } else {

            $newScheduledAtForDatabase = $newDate->format("Y-m-d H:i:s");

            try {

                $pdo->beginTransaction();

                // FETCH AND LOCK VISIT

                if ($canViewAll) {

                    $visitCheck = $pdo->prepare("
                        SELECT
                            sv.id,
                            sv.lead_id,
                            sv.scheduled_at,
                            sv.visit_status,
                            sv.assigned_to,
                            l.customer_name
                        FROM site_visits sv
                        INNER JOIN leads l ON sv.lead_id = l.id
                        WHERE sv.id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $visitCheck->execute([$visitId]);

                } else {

                    $visitCheck = $pdo->prepare("
                        SELECT
                            sv.id,
                            sv.lead_id,
                            sv.scheduled_at,
                            sv.visit_status,
                            sv.assigned_to,
                            l.customer_name
                        FROM site_visits sv
                        INNER JOIN leads l ON sv.lead_id = l.id
                        WHERE sv.id = ?
                        AND sv.assigned_to = ?
                        AND l.assigned_to = ?
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $visitCheck->execute([
                        $visitId,
                        $adminId,
                        $adminId
                    ]);
                }

                $visit = $visitCheck->fetch(PDO::FETCH_ASSOC);

                if (!$visit) {
                    throw new RuntimeException("VISIT_NOT_FOUND");
                }

                // VALIDATE VISIT STATUS

                if (
                    !in_array(
                        $visit["visit_status"],
                        ["scheduled", "rescheduled"],
                        true
                    )
                ) {
                    throw new RuntimeException("VISIT_STATUS_INVALID");
                }

                $oldScheduledAt = $visit["scheduled_at"];

                if (
                    !is_string($oldScheduledAt) ||
                    $oldScheduledAt === ""
                ) {
                    throw new RuntimeException("INVALID_EXISTING_DATE");
                }

                if ($oldScheduledAt === $newScheduledAtForDatabase) {
                    throw new RuntimeException("SAME_SCHEDULE");
                }

                // UPDATE VISIT

                if ($canViewAll) {

                    $update = $pdo->prepare("
                        UPDATE site_visits
                        SET
                            scheduled_at = ?,
                            visit_status = 'rescheduled'
                        WHERE id = ?
                        AND visit_status IN ('scheduled', 'rescheduled')
                    ");

                    $update->execute([
                        $newScheduledAtForDatabase,
                        $visitId
                    ]);

                } else {

                    $update = $pdo->prepare("
                        UPDATE site_visits sv
                        INNER JOIN leads l ON sv.lead_id = l.id
                        SET
                            sv.scheduled_at = ?,
                            sv.visit_status = 'rescheduled'
                        WHERE sv.id = ?
                        AND sv.assigned_to = ?
                        AND l.assigned_to = ?
                        AND sv.visit_status IN ('scheduled', 'rescheduled')
                    ");

                    $update->execute([
                        $newScheduledAtForDatabase,
                        $visitId,
                        $adminId,
                        $adminId
                    ]);
                }

                if ($update->rowCount() !== 1) {
                    throw new RuntimeException("VISIT_UPDATE_FAILED");
                }

                // RECORD ACTIVITY

                $oldDisplay = formatVisitDate($oldScheduledAt);
                $newDisplay = $newDate->format("d-m-Y h:i A");

                $activityDescription =
                    "Site visit rescheduled for " .
                    $visit["customer_name"] .
                    ". Previous schedule: " .
                    $oldDisplay .
                    ". New schedule: " .
                    $newDisplay;

                logLeadActivity(
                    $pdo,
                    (int)$visit["lead_id"],
                    $adminId,
                    "site_visit_rescheduled",
                    $activityDescription,
                    $oldScheduledAt,
                    $newScheduledAtForDatabase
                );

                // COMMIT TRANSACTION

                $pdo->commit();

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                $_SESSION["user_message"] =
                    "Site visit successfully rescheduled to " .
                    $newDisplay .
                    ". The activity history has been updated.";

                header(
                    "Location: site_visits.php?month=" .
                    $newDate->format("Y-m") .
                    "&day=" .
                    $newDate->format("Y-m-d") .
                    "#daily-schedule"
                );

                exit;

            } catch (RuntimeException $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($exception->getMessage() === "VISIT_NOT_FOUND") {

                    $message = "Visit not found or you are not allowed to reschedule it.";

                } elseif ($exception->getMessage() === "VISIT_STATUS_INVALID") {

                    $message = "Only scheduled or previously rescheduled visits can be rescheduled. Completed and cancelled visits cannot be rescheduled.";

                } elseif ($exception->getMessage() === "SAME_SCHEDULE") {

                    $message = "The new date and time are the same as the current schedule.";

                } elseif ($exception->getMessage() === "INVALID_EXISTING_DATE") {

                    $message = "The existing visit date is invalid. Please contact the administrator.";

                } else {

                    error_log(
                        "Site visit rescheduling error: " .
                        $exception->getMessage()
                    );

                    $message = "Unable to reschedule the visit. Please try again.";
                }

                $messageType = "error";

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    "Site visit rescheduling error: " .
                    $exception->getMessage()
                );

                $message = "Unable to reschedule the visit due to a system error. Please try again.";
                $messageType = "error";
            }
        }
    }
}

// --------------------------------------------------
// FETCH VISITS AVAILABLE FOR RESCHEDULING
// --------------------------------------------------

if ($canViewAll) {

    $visitQuery = $pdo->query("
        SELECT
            sv.id,
            sv.lead_id,
            sv.scheduled_at,
            sv.visit_status,
            sv.notes,
            sv.assigned_to,
            l.customer_name,
            l.phone,
            l.property_type,
            l.interested_location,
            a.name AS assigned_employee
        FROM site_visits sv
        INNER JOIN leads l ON sv.lead_id = l.id
        LEFT JOIN admins a ON sv.assigned_to = a.id
        WHERE sv.visit_status IN ('scheduled', 'rescheduled')
        ORDER BY sv.scheduled_at ASC
    ");

} else {

    $visitQuery = $pdo->prepare("
        SELECT
            sv.id,
            sv.lead_id,
            sv.scheduled_at,
            sv.visit_status,
            sv.notes,
            sv.assigned_to,
            l.customer_name,
            l.phone,
            l.property_type,
            l.interested_location,
            a.name AS assigned_employee
        FROM site_visits sv
        INNER JOIN leads l ON sv.lead_id = l.id
        LEFT JOIN admins a ON sv.assigned_to = a.id
        WHERE sv.assigned_to = ?
        AND l.assigned_to = ?
        AND sv.visit_status IN ('scheduled', 'rescheduled')
        ORDER BY sv.scheduled_at ASC
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

    <title>Reschedule Site Visits | Olivera Home Developers</title>

    <link rel="stylesheet" href="assets/css/admin.css">
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
            font-family: Arial, Helvetica, sans-serif;
            background: var(--ivory);
            margin: 0;
            color: var(--text);
        }

        .main-content {
            margin-left: 255px;
            min-height: 100vh;
            padding: 30px;
        }

        .container {
            max-width: 1500px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 23px;
        }

        h1, h2 {
            color: var(--navy);
        }

        h1 {
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
            transition: 0.2s ease;
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
            box-shadow: 0 3px 12px rgba(7, 27, 56, 0.04);
        }

        h2 {
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

        .information {
            background: var(--ivory);
            border: 1px solid var(--border);
            border-left: 4px solid var(--gold);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: var(--text);
            font-size: 13px;
            line-height: 1.7;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
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

        .property-details {
            line-height: 1.7;
        }

        .property-details strong {
            display: block;
            color: var(--navy);
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

        .status.rescheduled {
            background: #fff3dc;
            color: #80540b;
        }

        .reschedule-form {
            min-width: 220px;
        }

        .reschedule-form label {
            display: block;
            margin-bottom: 7px;
            color: var(--navy);
            font-weight: 700;
            font-size: 12px;
        }

        .reschedule-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: 9px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: var(--text);
        }

        .reschedule-form input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214, 166, 59, 0.15);
        }

        button {
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

        .empty-state {
            padding: 35px 15px;
            text-align: center;
            color: var(--muted);
            background: #fcfaf5;
            border: 1px dashed var(--border);
            border-radius: 7px;
        }

        @media (max-width: 1000px) {
            .main-content {
                margin-left: 220px;
            }
        }

        @media (max-width: 700px) {
            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            .card {
                padding: 16px;
            }

            h1 {
                font-size: 23px;
            }

            .nav-links a {
                padding: 9px 11px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 11px;
            }

            .card {
                padding: 14px;
            }

            h1 {
                font-size: 21px;
            }
        }

    </style>

</head>

<body>

<?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

<main class="main-content">

    <div class="container">

        <header class="page-header">

            <h1>Reschedule Site Visits</h1>

            <p>
                Change the date and time of an upcoming property visit.
            </p>

        </header>

        <nav class="nav-links">

            <a href="dashboard.php">Dashboard</a>

            <a href="site_visits.php">
                Monthly Site Visit Calendar
            </a>

            <a href="weekly_site_visits.php">
                Weekly Site Visit Schedule
            </a>

            <a href="cancel_site_visit.php">
                Cancel Site Visits
            </a>

            <a href="site_visit_reschedule_history.php">
                Rescheduling History
            </a>

            <?php if (
                hasPermission("manage_leads") ||
                hasPermission("view_all_leads")
            ): ?>
                <a href="manage_leads.php">Manage Leads</a>
            <?php endif; ?>

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

            <h2>Upcoming Visits Available for Rescheduling</h2>

            <div class="information">
                You can reschedule visits that are currently Scheduled or
                Rescheduled. The new date and time must be in the future.
                Completed and Cancelled visits cannot be rescheduled.
                Previous and new date/time details are recorded in the
                lead activity history.
            </div>

            <?php if (count($visits) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Visit ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Property Details</th>
                                <th>Current Date and Time</th>
                                <th>Assigned Employee</th>
                                <th>Status</th>
                                <th>Reschedule Visit</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($visits as $visit): ?>

                                <tr>

                                    <td>
                                        #<?php echo (int)$visit["id"]; ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?php echo e($visit["customer_name"]); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php echo e($visit["phone"]); ?>
                                    </td>

                                    <td class="property-details">

                                        <strong>
                                            <?php
                                            echo e(
                                                $visit["property_type"] ??
                                                "Not specified"
                                            );
                                            ?>
                                        </strong>

                                        <?php
                                        echo e(
                                            $visit["interested_location"] ??
                                            "Location not specified"
                                        );
                                        ?>

                                    </td>

                                    <td>
                                        <?php
                                        echo e(
                                            formatVisitDate($visit["scheduled_at"])
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo e(
                                            $visit["assigned_employee"] ??
                                            "Not Assigned"
                                        );
                                        ?>
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

                                    <td>

                                        <form
                                            method="POST"
                                            action="reschedule_site_visits.php"
                                            class="reschedule-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo e($_SESSION["csrf_token"]); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="visit_id"
                                                value="<?php echo (int)$visit["id"]; ?>"
                                            >

                                            <label>
                                                New Date and Time
                                            </label>

                                            <input
                                                type="datetime-local"
                                                name="new_scheduled_at"
                                                min="<?php echo e(date("Y-m-d\TH:i", time() + 60)); ?>"
                                                required
                                            >

                                            <button type="submit">
                                                Reschedule Visit
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    There are no scheduled or rescheduled visits available.
                </div>

            <?php endif; ?>

        </section>

    </div>

</main>

</body>
</html>
