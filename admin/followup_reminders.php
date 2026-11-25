<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("manage_followups");

$adminId = (int)$_SESSION["admin_id"];
$currentRole = $_SESSION["admin_role"];
$isSalesEmployee = ($currentRole === "sales_employee");

// Fetch reminders
$sql = "
    SELECT
        lf.id,
        lf.lead_id,
        lf.followup_type,
        lf.notes,
        lf.next_followup_at,
        lf.created_at,
        l.customer_name,
        l.phone,
        l.email,
        l.interested_location,
        l.property_type,
        l.lead_status,
        l.priority,
        l.assigned_to,
        assigned_admin.name AS employee_name,
        followup_admin.name AS followup_created_by

    FROM lead_followups lf

    INNER JOIN leads l
        ON lf.lead_id = l.id

    LEFT JOIN admins assigned_admin
        ON l.assigned_to = assigned_admin.id

    LEFT JOIN admins followup_admin
        ON lf.admin_id = followup_admin.id

    WHERE lf.next_followup_at IS NOT NULL
";

$params = [];

if ($isSalesEmployee) {
    $sql .= " AND l.assigned_to = ?";
    $params[] = $adminId;
}

$sql .= " ORDER BY lf.next_followup_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$allReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorize reminders
$overdueReminders = [];
$todayReminders = [];
$upcomingReminders = [];

$today = date("Y-m-d");
$tomorrow = date("Y-m-d", strtotime("+1 day"));

foreach ($allReminders as $reminder) {

    $followupDate = date(
        "Y-m-d",
        strtotime($reminder["next_followup_at"])
    );

    if ($followupDate < $today) {
        $overdueReminders[] = $reminder;
    } elseif ($followupDate === $today) {
        $todayReminders[] = $reminder;
    } else {
        $upcomingReminders[] = $reminder;
    }
}

// Count upcoming within 24 hours
$upcomingSoonCount = 0;

foreach ($upcomingReminders as $reminder) {

    $reminderTime = strtotime($reminder["next_followup_at"]);

    if (
        $reminderTime >= time() &&
        $reminderTime <= time() + 86400
    ) {
        $upcomingSoonCount++;
    }
}

// Helper functions
function reminderEscape($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function reminderPriorityClass($priority) {
    return match ($priority) {
        "high" => "priority-high",
        "low" => "priority-low",
        default => "priority-medium"
    };
}

function reminderStatusClass($status) {
    return "status-" . preg_replace(
        "/[^a-z0-9_-]/",
        "",
        strtolower((string)$status)
    );
}

// Display reminder section
function displayReminderSection(
    $title,
    $reminders,
    $color,
    $sectionClass
) {
?>

<section class="reminder-section <?= reminderEscape($sectionClass) ?>">

    <div class="section-heading">

        <h2 style="color:<?= reminderEscape($color) ?>">
            <?= reminderEscape($title) ?>
        </h2>

        <span class="section-count">
            <?= count($reminders) ?> reminders
        </span>

    </div>

    <?php if (!empty($reminders)): ?>

        <div class="reminder-grid">

            <?php foreach ($reminders as $reminder): ?>

                <?php

                $reminderTime = strtotime(
                    $reminder["next_followup_at"]
                );

                $isOverdue = (
                    $sectionClass === "overdue-section"
                );

                $isToday = (
                    $sectionClass === "today-section"
                );

                $isUpcoming = (
                    $sectionClass === "upcoming-section"
                );

                $priority = $reminder["priority"] ?? "medium";

                $isHighPriority = ($priority === "high");

                $daysOverdue = 0;

                if ($isOverdue) {
                    $daysOverdue = (int)floor(
                        (time() - $reminderTime) / 86400
                    );
                }

                // Upcoming countdown
                $daysUntil = 0;
                $isTomorrow = false;
                $isWithin24Hours = false;
                $isUrgentUpcoming = false;

                if ($isUpcoming) {

                    $todayDate = new DateTime(date("Y-m-d"));

                    $reminderDate = new DateTime(
                        date("Y-m-d", $reminderTime)
                    );

                    $daysUntil = (int)$todayDate->diff(
                        $reminderDate
                    )->format("%a");

                    $isTomorrow = (
                        date("Y-m-d", $reminderTime) ===
                        date("Y-m-d", strtotime("+1 day"))
                    );

                    $isWithin24Hours = (
                        $reminderTime >= time() &&
                        $reminderTime <= time() + 86400
                    );

                    $isUrgentUpcoming = (
                        $isHighPriority &&
                        $daysUntil <= 3
                    );
                }

                ?>

                <div class="reminder-card
                    <?= $isOverdue ? "overdue-card" : "" ?>
                    <?= $isToday ? "today-card" : "" ?>
                    <?= $isUrgentUpcoming ? "urgent-upcoming-card" : "" ?>
                    <?= $isHighPriority ? "high-priority-card" : "" ?>">

                    <!-- Badges -->

                    <div class="card-top">

                        <?php if ($isOverdue): ?>

                            <span class="overdue-warning">

                                ⚠ OVERDUE

                                <?php if ($daysOverdue > 0): ?>
                                    <?= $daysOverdue ?> day(s) late
                                <?php else: ?>
                                    Due earlier today
                                <?php endif; ?>

                            </span>

                        <?php elseif ($isToday): ?>

                            <span class="today-warning">
                                🟠 FOLLOW UP TODAY
                            </span>

                        <?php elseif ($isUpcoming): ?>

                            <?php if ($isWithin24Hours): ?>

                                <span class="soon-warning">
                                    🔔 UPCOMING SOON
                                </span>

                            <?php elseif ($isTomorrow): ?>

                                <span class="tomorrow-warning">
                                    🟡 DUE TOMORROW
                                </span>

                            <?php else: ?>

                                <span class="upcoming-warning">
                                    📅 DUE IN <?= $daysUntil ?> DAYS
                                </span>

                            <?php endif; ?>

                        <?php endif; ?>

                        <span class="priority-badge
                            <?= reminderEscape(reminderPriorityClass($priority)) ?>">

                            <?= $isHighPriority ? "🔥 " : "" ?>
                            <?= reminderEscape(ucfirst($priority)) ?> Priority

                        </span>

                        <span class="status-badge
                            <?= reminderEscape(reminderStatusClass($reminder["lead_status"])) ?>">

                            <?= reminderEscape(
                                ucwords(
                                    str_replace(
                                        "_",
                                        " ",
                                        $reminder["lead_status"]
                                    )
                                )
                            ) ?>

                        </span>

                    </div>

                    <!-- Customer -->

                    <h3>
                        <?= reminderEscape($reminder["customer_name"]) ?>
                    </h3>

                    <!-- Customer Information -->

                    <div class="card-info">

                        <p>
                            <strong>Phone:</strong>
                            <?= reminderEscape($reminder["phone"]) ?>
                        </p>

                        <?php if (!empty($reminder["email"])): ?>
                            <p>
                                <strong>Email:</strong>
                                <?= reminderEscape($reminder["email"]) ?>
                            </p>
                        <?php endif; ?>

                        <p>
                            <strong>Location:</strong>
                            <?= reminderEscape(
                                $reminder["interested_location"] ?: "Not specified"
                            ) ?>
                        </p>

                        <p>
                            <strong>Property:</strong>
                            <?= reminderEscape(
                                $reminder["property_type"] ?: "Not specified"
                            ) ?>
                        </p>

                        <p>
                            <strong>Follow-up Type:</strong>
                            <?= reminderEscape(
                                ucfirst($reminder["followup_type"])
                            ) ?>
                        </p>

                        <!-- Reminder Date -->

                        <div class="reminder-date
                            <?= $isOverdue ? "overdue-date" : "" ?>
                            <?= $isToday ? "today-date" : "" ?>
                            <?= $isUpcoming ? "upcoming-date" : "" ?>">

                            <strong>Reminder Date:</strong>

                            <div class="date-display">

                                <?= reminderEscape(
                                    date("d-m-Y", $reminderTime)
                                ) ?>

                            </div>

                            <div class="time-display">
                                ⏰
                                <?= reminderEscape(
                                    date("h:i A", $reminderTime)
                                ) ?>
                            </div>

                            <?php if ($isUpcoming): ?>

                                <div class="countdown-text">

                                    <?php if ($isWithin24Hours): ?>

                                        🔔 Scheduled within the next 24 hours

                                    <?php elseif ($isTomorrow): ?>

                                        🟡 Customer follow-up is tomorrow

                                    <?php else: ?>

                                        📅 <?= $daysUntil ?> days remaining

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </div>

                        <p>
                            <strong>Sales Employee:</strong>
                            <?= reminderEscape(
                                $reminder["employee_name"] ?? "Unassigned"
                            ) ?>
                        </p>

                        <p>
                            <strong>Follow-up Created By:</strong>
                            <?= reminderEscape(
                                $reminder["followup_created_by"] ?? "Unknown"
                            ) ?>
                        </p>

                    </div>

                    <!-- Notes -->

                    <?php if (!empty($reminder["notes"])): ?>

                        <div class="notes-box">

                            <strong>Follow-up Notes</strong>

                            <p>
                                <?= nl2br(
                                    reminderEscape($reminder["notes"])
                                ) ?>
                            </p>

                        </div>

                    <?php endif; ?>

                    <!-- Buttons -->

                    <div class="card-actions">

                        <a class="btn btn-blue"
                           href="view_lead.php?id=<?= (int)$reminder["lead_id"] ?>">
                            View Lead
                        </a>

                        <a class="btn btn-green"
                           href="lead_followups.php?lead_id=<?= (int)$reminder["lead_id"] ?>">
                            Add Follow-up
                        </a>

                        <a class="btn btn-orange"
                           href="site_visits.php">
                            Site Visits
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-box">
            No reminders in this category.
        </div>

    <?php endif; ?>

</section>

<?php
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Follow-up Reminders</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 20px;
            margin: 0;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: auto;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        h1 {
            margin-top: 0;
        }

        .header-description {
            color: #667085;
        }

        .navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .navigation a {
            display: inline-block;
            background: #1f5e9c;
            color: white;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 5px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-box {
            background: white;
            padding: 22px;
            border-radius: 9px;
            border-top: 5px solid #1f5e9c;
        }

        .summary-box.overdue {
            border-top-color: #dc3545;
        }

        .summary-box.today {
            border-top-color: #e69500;
        }

        .summary-box.upcoming {
            border-top-color: #198754;
        }

        .summary-box.soon {
            border-top-color: #f0ad4e;
        }

        .summary-label {
            color: #667085;
            font-size: 14px;
        }

        .summary-number {
            display: block;
            font-size: 32px;
            font-weight: bold;
            margin-top: 10px;
        }

        .reminder-section {
            background: white;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
        }

        .section-heading h2 {
            margin: 0;
        }

        .section-count {
            background: #f1f4f8;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .reminder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 18px;
        }

        .reminder-card {
            background: #f8fafc;
            border: 1px solid #e4e7ec;
            border-left: 5px solid #1f5e9c;
            border-radius: 8px;
            padding: 18px;
        }

        .overdue-section .reminder-card {
            border-left-color: #dc3545;
        }

        .today-section .reminder-card {
            border-left-color: #e69500;
        }

        .upcoming-section .reminder-card {
            border-left-color: #198754;
        }

        /* Overdue */

        .overdue-card {
            background: #fff5f5;
            border: 2px solid #dc3545;
            border-left: 8px solid #dc3545;
        }

        .overdue-warning {
            background: #dc3545;
            color: white;
            font-weight: bold;
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 12px;
        }

        .overdue-date {
            background: #ffe0e0 !important;
            color: #a40000;
            padding: 10px;
            border-radius: 5px;
        }

        /* Today */

        .today-card {
            background: #fffaf0;
            border: 2px solid #f0ad4e;
            border-left: 8px solid #e69500;
        }

        .today-warning {
            background: #e69500;
            color: white;
            font-weight: bold;
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 12px;
        }

        .today-date {
            background: #fff0d1 !important;
            border: 1px solid #f0ad4e;
            padding: 12px;
            border-radius: 6px;
        }

        /* Upcoming */

        .upcoming-date {
            background: #eaf8ef;
            border: 1px solid #b7e4c7;
            padding: 12px;
            border-radius: 6px;
        }

        .upcoming-warning {
            background: #198754;
            color: white;
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .soon-warning {
            background: #f0ad4e;
            color: #533600;
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .tomorrow-warning {
            background: #fff0c2;
            color: #805b00;
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .urgent-upcoming-card {
            background: #fffaf0;
            border: 2px solid #f0ad4e;
            border-left: 8px solid #e69500;
            box-shadow: 0 3px 12px rgba(230, 149, 0, 0.12);
        }

        .urgent-upcoming-card h3 {
            color: #b76e00;
        }

        .countdown-text {
            margin-top: 10px;
            font-weight: bold;
            color: #216e39;
            font-size: 13px;
        }

        /* Priority */

        .high-priority-card {
            box-shadow: 0 3px 12px rgba(220, 53, 69, 0.12);
        }

        .overdue-card.high-priority-card {
            border: 3px solid #b42318;
            border-left: 10px solid #b42318;
            background: #ffeded;
        }

        .today-card.high-priority-card {
            border: 3px solid #e69500;
            border-left: 10px solid #e69500;
            background: #fff4dd;
        }

        /* Badges */

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
        }

        .priority-badge,
        .status-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .priority-high {
            background: #fee4e2;
            color: #b42318;
        }

        .priority-medium {
            background: #fff0c2;
            color: #805b00;
        }

        .priority-low {
            background: #d1fadf;
            color: #067647;
        }

        /* Status */

        .status-new {
            background: #e9eef5;
            color: #344054;
        }

        .status-contacted {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-interested {
            background: #fef0c7;
            color: #b54708;
        }

        .status-site_visit_scheduled {
            background: #e9d7fe;
            color: #6941c6;
        }

        .status-negotiation {
            background: #fce7d4;
            color: #93370d;
        }

        .status-converted {
            background: #d1fadf;
            color: #067647;
        }

        .status-lost {
            background: #fee4e2;
            color: #b42318;
        }

        /* Customer Information */

        .reminder-card h3 {
            margin-top: 12px;
            margin-bottom: 15px;
            font-size: 19px;
        }

        .card-info p {
            margin: 9px 0;
            font-size: 14px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .date-display {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
        }

        .time-display {
            font-size: 20px;
            font-weight: bold;
            color: #b76e00;
            margin-top: 7px;
        }

        /* Notes */

        .notes-box {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-top: 15px;
            border: 1px solid #e4e7ec;
        }

        .notes-box p {
            margin-bottom: 0;
            margin-top: 8px;
            line-height: 1.5;
        }

        /* Buttons */

        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 18px;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            color: white;
            padding: 9px 11px;
            border-radius: 5px;
            font-size: 13px;
        }

        .btn-blue {
            background: #1f5e9c;
        }

        .btn-green {
            background: #218653;
        }

        .btn-orange {
            background: #b76e00;
        }

        .btn:hover {
            opacity: 0.85;
        }

        /* Empty */

        .empty-box {
            background: #f8fafc;
            border: 1px dashed #d0d5dd;
            border-radius: 7px;
            padding: 25px;
            text-align: center;
            color: #667085;
        }

        /* Mobile */

        @media (max-width: 600px) {

            body {
                padding: 10px;
            }

            .header,
            .reminder-section {
                padding: 15px;
            }

            .card-actions .btn {
                flex: 1;
                text-align: center;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- Header -->

    <div class="header">

        <h1>Follow-up Reminders</h1>

        <p class="header-description">
            Track overdue, today's and upcoming customer follow-ups.
            Prepare in advance and prioritize important customers.
        </p>

        <div class="navigation">

            <a href="dashboard.php">Dashboard</a>
            <a href="manage_leads.php">Manage Leads</a>
            <a href="lead_followups.php">Lead Follow-ups</a>
            <a href="site_visits.php">Site Visits</a>
            <a href="crm_reports.php">CRM Reports</a>

        </div>

    </div>

    <!-- Summary -->

    <div class="summary">

        <div class="summary-box overdue">
            <span class="summary-label">Overdue Follow-ups</span>
            <strong class="summary-number">
                <?= count($overdueReminders) ?>
            </strong>
        </div>

        <div class="summary-box today">
            <span class="summary-label">Today's Follow-ups</span>
            <strong class="summary-number">
                <?= count($todayReminders) ?>
            </strong>
        </div>

        <div class="summary-box upcoming">
            <span class="summary-label">Upcoming Follow-ups</span>
            <strong class="summary-number">
                <?= count($upcomingReminders) ?>
            </strong>
        </div>

        <div class="summary-box soon">
            <span class="summary-label">Due Within 24 Hours</span>
            <strong class="summary-number">
                <?= $upcomingSoonCount ?>
            </strong>
        </div>

    </div>

    <!-- Overdue -->

    <?php
    displayReminderSection(
        "Overdue Follow-ups",
        $overdueReminders,
        "#dc3545",
        "overdue-section"
    );
    ?>

    <!-- Today -->

    <?php
    displayReminderSection(
        "Today's Follow-ups",
        $todayReminders,
        "#e69500",
        "today-section"
    );
    ?>

    <!-- Upcoming -->

    <?php
    displayReminderSection(
        "Upcoming Follow-ups",
        $upcomingReminders,
        "#198754",
        "upcoming-section"
    );
    ?>

</div>

</body>
</html>