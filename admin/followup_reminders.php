
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("manage_followups");

$adminId = (int)($_SESSION["admin_id"] ?? 0);
$currentRole = $_SESSION["admin_role"] ?? "";
$isSalesEmployee = ($currentRole === "sales_employee");

// --------------------------------------------------
// FETCH REMINDERS
// --------------------------------------------------

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
    INNER JOIN leads l ON lf.lead_id = l.id
    LEFT JOIN admins assigned_admin ON l.assigned_to = assigned_admin.id
    LEFT JOIN admins followup_admin ON lf.admin_id = followup_admin.id
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

// --------------------------------------------------
// CATEGORIZE REMINDERS
// --------------------------------------------------

$overdueReminders = [];
$todayReminders = [];
$upcomingReminders = [];

$today = date("Y-m-d");

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

// --------------------------------------------------
// COUNT UPCOMING WITHIN 24 HOURS
// --------------------------------------------------

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

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

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

// --------------------------------------------------
// DISPLAY REMINDER SECTION
// --------------------------------------------------

function displayReminderSection(
    $title,
    $reminders,
    $color,
    $sectionClass
) {
?>

<section class="reminder-section <?= reminderEscape($sectionClass) ?>">

    <div class="section-heading">
        <h2><?= reminderEscape($title) ?></h2>
        <span class="section-count">
            <?= count($reminders) ?> reminders
        </span>
    </div>

    <?php if (!empty($reminders)): ?>

        <div class="reminder-grid">

            <?php foreach ($reminders as $reminder): ?>

                <?php

                $reminderTime = strtotime($reminder["next_followup_at"]);

                $isOverdue = ($sectionClass === "overdue-section");
                $isToday = ($sectionClass === "today-section");
                $isUpcoming = ($sectionClass === "upcoming-section");

                $priority = $reminder["priority"] ?? "medium";
                $isHighPriority = ($priority === "high");

                $daysOverdue = 0;

                if ($isOverdue) {
                    $daysOverdue = (int)floor(
                        (time() - $reminderTime) / 86400
                    );
                }

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
                        $isHighPriority && $daysUntil <= 3
                    );
                }

                ?>

                <div class="reminder-card
                    <?= $isOverdue ? "overdue-card" : "" ?>
                    <?= $isToday ? "today-card" : "" ?>
                    <?= $isUrgentUpcoming ? "urgent-upcoming-card" : "" ?>
                    <?= $isHighPriority ? "high-priority-card" : "" ?>">

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
                                FOLLOW UP TODAY
                            </span>

                        <?php elseif ($isUpcoming): ?>

                            <?php if ($isWithin24Hours): ?>
                                <span class="soon-warning">
                                    UPCOMING SOON
                                </span>
                            <?php elseif ($isTomorrow): ?>
                                <span class="tomorrow-warning">
                                    DUE TOMORROW
                                </span>
                            <?php else: ?>
                                <span class="upcoming-warning">
                                    DUE IN <?= $daysUntil ?> DAYS
                                </span>
                            <?php endif; ?>

                        <?php endif; ?>

                        <span class="priority-badge <?= reminderEscape(reminderPriorityClass($priority)) ?>">
                            <?= $isHighPriority ? "🔥 " : "" ?>
                            <?= reminderEscape(ucfirst($priority)) ?> Priority
                        </span>

                        <span class="status-badge <?= reminderEscape(reminderStatusClass($reminder["lead_status"])) ?>">
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

                    <h3>
                        <?= reminderEscape($reminder["customer_name"]) ?>
                    </h3>

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

                        <div class="reminder-date
                            <?= $isOverdue ? "overdue-date" : "" ?>
                            <?= $isToday ? "today-date" : "" ?>
                            <?= $isUpcoming ? "upcoming-date" : "" ?>">

                            <strong>Reminder Date</strong>

                            <div class="date-display">
                                <?= reminderEscape(date("d-m-Y", $reminderTime)) ?>
                            </div>

                            <div class="time-display">
                                <?= reminderEscape(date("h:i A", $reminderTime)) ?>
                            </div>

                            <?php if ($isUpcoming): ?>
                                <div class="countdown-text">

                                    <?php if ($isWithin24Hours): ?>
                                        Scheduled within the next 24 hours
                                    <?php elseif ($isTomorrow): ?>
                                        Customer follow-up is tomorrow
                                    <?php else: ?>
                                        <?= $daysUntil ?> days remaining
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

                    <?php if (!empty($reminder["notes"])): ?>
                        <div class="notes-box">
                            <strong>Follow-up Notes</strong>
                            <p><?= nl2br(reminderEscape($reminder["notes"])) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="card-actions">

                        <a class="btn btn-primary"
                           href="view_lead.php?id=<?= (int)$reminder["lead_id"] ?>">
                            View Lead
                        </a>

                        <a class="btn btn-success"
                           href="lead_followups.php?lead_id=<?= (int)$reminder["lead_id"] ?>">
                            Add Follow-up
                        </a>

                        <a class="btn btn-gold"
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Follow-up Reminders | Admin Panel</title>

    <style>

        :root {
            --navy: #071b38;
            --navy-light: #102b50;
            --gold: #d6a63b;
            --gold-light: #edca76;
            --ivory: #f7f5ef;
            --white: #ffffff;
            --text: #263449;
            --muted: #718096;
            --border: #e5e1d7;
            --red: #b42318;
            --orange: #b76e00;
            --green: #067647;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--ivory);
            color: var(--text);
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 255px;
            height: 100vh;
            background: var(--navy);
            color: var(--white);
            padding: 25px 15px;
            overflow-y: auto;
            z-index: 1000;
            transition: transform .3s ease;
        }

        .brand {
            padding: 5px 12px 25px;
            border-bottom: 1px solid rgba(255,255,255,.15);
            margin-bottom: 22px;
        }

        .brand h2 {
            color: var(--gold-light);
            margin: 0;
            font-size: 21px;
            letter-spacing: .5px;
        }

        .brand p {
            color: #cbd5e1;
            font-size: 11px;
            margin: 7px 0 0;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .sidebar-label {
            color: #aab7c9;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1.5px;
            padding: 10px 12px;
            text-transform: uppercase;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #e6edf6;
            text-decoration: none;
            padding: 12px;
            margin: 4px 0;
            border-radius: 7px;
            font-size: 13px;
            transition: .2s;
        }

        .sidebar a:hover {
            background: var(--navy-light);
            color: var(--gold-light);
        }

        .sidebar a.active {
            background: var(--gold);
            color: var(--navy);
            font-weight: bold;
        }

        .menu-icon {
            width: 21px;
            text-align: center;
            font-size: 16px;
        }

        /* MAIN CONTENT */

        .main-content {
            margin-left: 255px;
            padding: 30px;
            min-height: 100vh;
        }

        .mobile-menu {
            display: none;
            background: var(--navy);
            color: var(--gold-light);
            border: 0;
            padding: 11px 15px;
            border-radius: 6px;
            font-size: 18px;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .page-header {
            background: var(--white);
            border: 1px solid var(--border);
            border-top: 4px solid var(--gold);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(7,27,56,.04);
        }

        .page-header h1 {
            margin: 0 0 10px;
            color: var(--navy);
            font-size: 27px;
        }

        .page-header p {
            color: var(--muted);
            margin: 0;
            line-height: 1.6;
            font-size: 14px;
        }

        /* SUMMARY */

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .summary-box {
            background: var(--white);
            padding: 22px;
            border-radius: 9px;
            border: 1px solid var(--border);
            border-top: 4px solid var(--gold);
            box-shadow: 0 3px 12px rgba(7,27,56,.04);
        }

        .summary-box.overdue {
            border-top-color: var(--red);
        }

        .summary-box.today {
            border-top-color: var(--orange);
        }

        .summary-box.upcoming {
            border-top-color: var(--green);
        }

        .summary-box.soon {
            border-top-color: var(--gold);
        }

        .summary-label {
            color: var(--muted);
            font-size: 13px;
        }

        .summary-number {
            display: block;
            color: var(--navy);
            font-size: 32px;
            font-weight: bold;
            margin-top: 10px;
        }

        /* REMINDER SECTIONS */

        .reminder-section {
            background: var(--white);
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
            box-shadow: 0 3px 12px rgba(7,27,56,.04);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 14px;
        }

        .section-heading h2 {
            color: var(--navy);
            margin: 0;
            font-size: 19px;
        }

        .section-count {
            background: #f1eee5;
            color: var(--navy);
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .reminder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 18px;
        }

        .reminder-card {
            background: #fffefa;
            border: 1px solid var(--border);
            border-left: 5px solid var(--gold);
            border-radius: 8px;
            padding: 18px;
            min-width: 0;
        }

        .overdue-section .reminder-card {
            border-left-color: var(--red);
        }

        .today-section .reminder-card {
            border-left-color: var(--orange);
        }

        .upcoming-section .reminder-card {
            border-left-color: var(--green);
        }

        /* OVERDUE */

        .overdue-card {
            background: #fff5f5;
            border: 2px solid #dcaca8;
            border-left: 7px solid var(--red);
        }

        .overdue-warning {
            background: var(--red);
            color: white;
            font-weight: bold;
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 11px;
        }

        .overdue-date {
            background: #ffe8e6 !important;
            color: #a40000;
            padding: 12px;
            border-radius: 5px;
        }

        /* TODAY */

        .today-card {
            background: #fffaf0;
            border: 2px solid #e9cb8d;
            border-left: 7px solid var(--orange);
        }

        .today-warning {
            background: var(--orange);
            color: white;
            font-weight: bold;
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 11px;
        }

        .today-date {
            background: #fff0d1 !important;
            border: 1px solid #e9cb8d;
            padding: 12px;
            border-radius: 6px;
        }

        /* UPCOMING */

        .upcoming-date {
            background: #eaf8ef;
            border: 1px solid #b7e4c7;
            padding: 12px;
            border-radius: 6px;
        }

        .upcoming-warning {
            background: var(--green);
            color: white;
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
        }

        .soon-warning {
            background: var(--gold-light);
            color: var(--navy);
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
        }

        .tomorrow-warning {
            background: #fff0c2;
            color: #805b00;
            padding: 7px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
        }

        .urgent-upcoming-card {
            background: #fffaf0;
            border: 2px solid var(--gold);
            border-left: 7px solid var(--gold);
            box-shadow: 0 3px 12px rgba(214,166,59,.12);
        }

        .urgent-upcoming-card h3 {
            color: var(--navy);
        }

        .countdown-text {
            margin-top: 10px;
            font-weight: bold;
            color: var(--green);
            font-size: 12px;
        }

        /* PRIORITY */

        .high-priority-card {
            box-shadow: 0 3px 12px rgba(180,35,24,.10);
        }

        .overdue-card.high-priority-card {
            border: 3px solid #b42318;
            border-left: 9px solid #b42318;
            background: #ffeded;
        }

        .today-card.high-priority-card {
            border: 3px solid var(--gold);
            border-left: 9px solid var(--gold);
            background: #fff4dd;
        }

        /* BADGES */

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
            font-size: 10px;
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

        /* CUSTOMER INFORMATION */

        .reminder-card h3 {
            margin-top: 15px;
            margin-bottom: 15px;
            font-size: 19px;
            color: var(--navy);
        }

        .card-info p {
            margin: 9px 0;
            font-size: 13px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .card-info strong {
            color: var(--navy);
        }

        .date-display {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
        }

        .time-display {
            font-size: 20px;
            font-weight: bold;
            color: var(--navy);
            margin-top: 7px;
        }

        /* NOTES */

        .notes-box {
            background: #f7f5ef;
            padding: 12px;
            border-radius: 6px;
            margin-top: 15px;
            border: 1px solid var(--border);
            overflow-wrap: anywhere;
        }

        .notes-box strong {
            color: var(--navy);
        }

        .notes-box p {
            margin-bottom: 0;
            margin-top: 8px;
            line-height: 1.6;
            font-size: 13px;
        }

        /* BUTTONS */

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
            padding: 10px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            transition: .2s;
        }

        .btn-primary {
            background: var(--navy);
        }

        .btn-success {
            background: var(--green);
        }

        .btn-gold {
            background: var(--gold);
            color: var(--navy);
        }

        .btn:hover {
            opacity: .85;
            transform: translateY(-1px);
        }

        /* EMPTY STATE */

        .empty-box {
            background: #faf9f5;
            border: 1px dashed #d0c9b8;
            border-radius: 7px;
            padding: 25px;
            text-align: center;
            color: var(--muted);
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            .mobile-menu {
                display: inline-block;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.45);
                z-index: 999;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 600px) {

            .main-content {
                padding: 12px;
            }

            .page-header,
            .reminder-section {
                padding: 16px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .reminder-grid {
                grid-template-columns: 1fr;
            }

            .card-actions .btn {
                flex: 1;
                text-align: center;
            }

            .summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .summary-box {
                padding: 15px;
            }

            .summary-number {
                font-size: 26px;
            }
        }

    </style>

</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar" id="sidebar">

    <div class="brand">
        <h2>OLIVERA</h2>
        <p>Home Developers</p>
    </div>

    <div class="sidebar-label">Main Menu</div>

    <a href="dashboard.php">
        <span class="menu-icon">⌂</span>
        Dashboard
    </a>

    <?php if (hasPermission("manage_users")): ?>
        <a href="property_enquiries.php">
            <span class="menu-icon">⌕</span>
            Property Enquiries
        </a>
    <?php endif; ?>

    <?php if (hasPermission("manage_admins")): ?>
        <a href="manage_admins.php">
            <span class="menu-icon">♙</span>
            Manage Admin
        </a>
    <?php endif; ?>

    <?php if (hasPermission("manage_admins")): ?>
        <a href="activity_log.php">
            <span class="menu-icon">◷</span>
            Activity Log
        </a>
    <?php endif; ?>

    <?php if (hasPermission("manage_leads")): ?>
        <a href="manage_leads.php">
            <span class="menu-icon">♧</span>
            Manage Leads
        </a>
    <?php endif; ?>

    <?php if (hasPermission("manage_partnerships")): ?>
        <a href="manage_partnerships.php">
            <span class="menu-icon">♢</span>
            Manage Partnerships
        </a>
    <?php endif; ?>

    <?php if (hasPermission("manage_followups")): ?>
        <a href="lead_followups.php">
            <span class="menu-icon">☷</span>
            Lead Follow-ups
        </a>

        <a href="followup_reminders.php" class="active">
            <span class="menu-icon">◷</span>
            Follow-up Reminders
        </a>
    <?php endif; ?>

    <a href="site_visits.php">
        <span class="menu-icon">⌖</span>
        Site Visits
    </a>

    <a href="crm_reports.php">
        <span class="menu-icon">▥</span>
        CRM Reports
    </a>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- MAIN CONTENT -->

<main class="main-content">

    <button class="mobile-menu" id="mobileMenu" type="button">
        ☰ Menu
    </button>

    <div class="page-header">

        <h1>Follow-up Reminders</h1>

        <p>
            Track overdue, today's and upcoming customer follow-ups.
            Prepare in advance and prioritize important customers.
        </p>

    </div>

    <!-- SUMMARY -->

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

    <!-- OVERDUE -->

    <?php
    displayReminderSection(
        "Overdue Follow-ups",
        $overdueReminders,
        "#b42318",
        "overdue-section"
    );
    ?>

    <!-- TODAY -->

    <?php
    displayReminderSection(
        "Today's Follow-ups",
        $todayReminders,
        "#b76e00",
        "today-section"
    );
    ?>

    <!-- UPCOMING -->

    <?php
    displayReminderSection(
        "Upcoming Follow-ups",
        $upcomingReminders,
        "#067647",
        "upcoming-section"
    );
    ?>

</main>

<script>

    const sidebar = document.getElementById("sidebar");
    const mobileMenu = document.getElementById("mobileMenu");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    mobileMenu.addEventListener("click", function () {
        sidebar.classList.toggle("open");
        sidebarOverlay.classList.toggle("show");
    });

    sidebarOverlay.addEventListener("click", function () {
        sidebar.classList.remove("open");
        sidebarOverlay.classList.remove("show");
    });

</script>

</body>
</html>
