
<?php

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

requirePermission("manage_admins");

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// --------------------------------------------------
// HELPER
// --------------------------------------------------

function activityLogEscape($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$adminName = $_SESSION["admin_name"] ?? "Administrator";

// --------------------------------------------------
// FETCH LATEST 100 ACTIVITY LOGS
// --------------------------------------------------

$stmt = $pdo->query("
    SELECT
        activity_logs.id,
        activity_logs.admin_id,
        activity_logs.action,
        activity_logs.description,
        activity_logs.created_at,
        admins.name AS admin_name
    FROM activity_logs
    LEFT JOIN admins
        ON activity_logs.admin_id = admins.id
    ORDER BY activity_logs.id DESC
    LIMIT 100
");

$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Activity Log | Olivera Home Developers</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

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
            --border: #e5e7eb;
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

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 260px;
            min-width: 260px;
            background: var(--navy);
            color: var(--white);
            min-height: 100vh;
            padding: 25px 16px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 5px 12px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 22px;
        }

        .sidebar-brand h2 {
            margin: 0;
            color: var(--gold-light);
            font-size: 21px;
            letter-spacing: 0.5px;
        }

        .sidebar-brand p {
            color: #cbd5e1;
            font-size: 11px;
            margin: 7px 0 0;
            letter-spacing: 1.2px;
        }

        .sidebar-section {
            color: var(--gold-light);
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1.5px;
            padding: 15px 12px 8px;
            text-transform: uppercase;
        }

        .sidebar a {
            display: block;
            color: #e2e8f0;
            text-decoration: none;
            padding: 12px 13px;
            margin: 4px 0;
            border-radius: 7px;
            font-size: 13px;
            transition: 0.2s ease;
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

        .sidebar-footer {
            margin-top: 30px;
            padding: 15px 12px;
            border-top: 1px solid rgba(255,255,255,0.15);
            color: #cbd5e1;
            font-size: 12px;
            line-height: 1.6;
        }

        /* MAIN CONTENT */

        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 30px;
        }

        .mobile-header {
            display: none;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            color: var(--navy);
            font-size: 27px;
            margin: 0 0 7px;
        }

        .page-heading p {
            color: var(--muted);
            margin: 0;
            font-size: 13px;
        }

        .admin-user {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 16px;
            color: var(--navy);
            font-size: 13px;
            font-weight: bold;
        }

        /* CONTENT CARD */

        .content-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 18px rgba(7, 27, 56, 0.04);
        }

        .card-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .card-heading h2 {
            color: var(--navy);
            font-size: 18px;
            margin: 0;
        }

        .record-count {
            background: #fdf6e5;
            color: var(--navy);
            border: 1px solid #f0dfb6;
            border-radius: 20px;
            padding: 7px 13px;
            font-size: 12px;
            font-weight: bold;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        thead {
            background: var(--navy);
            color: var(--white);
        }

        th {
            padding: 14px 13px;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        td {
            padding: 14px 13px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            vertical-align: top;
            line-height: 1.5;
        }

        tbody tr:hover {
            background: #fcfaf5;
        }

        .log-id {
            color: var(--muted);
            font-weight: bold;
        }

        .admin-name {
            color: var(--navy);
            font-weight: bold;
        }

        .action-name {
            display: inline-block;
            background: #fdf6e5;
            color: #795a17;
            border: 1px solid #f0dfb6;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
        }

        .description {
            max-width: 350px;
            overflow-wrap: anywhere;
        }

        .date-time {
            color: var(--muted);
            white-space: nowrap;
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 45px 15px;
            color: var(--muted);
            font-size: 14px;
        }

        /* MOBILE NAVIGATION */

        .sidebar-overlay {
            display: none;
        }

        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.25s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay.show {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.45);
                z-index: 999;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 18px;
            }

            .mobile-header {
                display: flex;
                align-items: center;
                gap: 14px;
                background: var(--navy);
                color: var(--white);
                padding: 14px 18px;
                margin: -18px -18px 22px;
            }

            .menu-button {
                border: none;
                background: var(--gold);
                color: var(--navy);
                padding: 9px 12px;
                border-radius: 5px;
                font-size: 19px;
                cursor: pointer;
            }

            .mobile-header strong {
                color: var(--gold-light);
                font-size: 15px;
            }

            .page-heading h1 {
                font-size: 23px;
            }

            .content-card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->

    <aside class="sidebar" id="adminSidebar">

        <div class="sidebar-brand">
            <h2>OLIVERA</h2>
            <p>HOME DEVELOPERS</p>
        </div>

        <div class="sidebar-section">Main Menu</div>

        <a href="dashboard.php">Dashboard</a>

        <?php if (hasPermission("manage_users")): ?>
            <a href="property_enquiries.php">Property Enquiries</a>
        <?php endif; ?>

        <?php if (hasPermission("manage_admins")): ?>
            <a href="manage_admins.php">Manage Admin</a>
            <a href="activity_logs.php" class="active">Activity Log</a>
        <?php endif; ?>

        <?php if (
            hasPermission("manage_leads") ||
            hasPermission("view_all_leads")
        ): ?>
            <a href="manage_leads.php">Manage Leads</a>
        <?php endif; ?>

        <?php if (hasPermission("manage_partnerships")): ?>
            <a href="manage_partnerships.php">Manage Partnerships</a>
        <?php endif; ?>

        <?php if (hasPermission("manage_followups")): ?>
            <a href="lead_followups.php">Lead Follow-ups</a>
            <a href="followup_reminders.php">Follow-up Reminders</a>
        <?php endif; ?>

        <?php if (hasPermission("manage_site_visits")): ?>
            <div class="sidebar-section">Site Visits</div>
            <a href="site_visits.php">Site Visits</a>
            <a href="weekly_site_visits.php">Weekly Site Visits</a>
            <a href="reschedule_site_visits.php">Reschedule Visits</a>
            <a href="cancel_site_visit.php">Cancel Site Visits</a>
            <a href="site_visit_reschedule_history.php">Rescheduling History</a>
        <?php endif; ?>

        <?php if (hasPermission("view_crm_reports")): ?>
            <div class="sidebar-section">Analytics</div>
            <a href="crm_reports.php">CRM Reports</a>
        <?php endif; ?>

        <div class="sidebar-footer">
            Logged in as<br>
            <strong><?php echo activityLogEscape($adminName); ?></strong>
        </div>

    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="mobile-header">
            <button class="menu-button" type="button" onclick="toggleSidebar()">☰</button>
            <strong>OLIVERA HOME DEVELOPERS</strong>
        </div>

        <div class="page-heading">

            <div>
                <h1>Activity Log</h1>
                <p>Monitor recent actions performed by administrators.</p>
            </div>

            <div class="admin-user">
                <?php echo activityLogEscape($adminName); ?>
            </div>

        </div>

        <section class="content-card">

            <div class="card-heading">
                <h2>Recent Activity Records</h2>

                <span class="record-count">
                    <?php echo count($activities); ?> Records
                </span>
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Date and Time</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!empty($activities)): ?>

                            <?php foreach ($activities as $activity): ?>

                                <tr>

                                    <td class="log-id">
                                        #<?php echo (int)$activity["id"]; ?>
                                    </td>

                                    <td class="admin-name">
                                        <?php
                                        echo activityLogEscape(
                                            $activity["admin_name"] ?: "Deleted / Unknown Admin"
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <span class="action-name">
                                            <?php echo activityLogEscape($activity["action"]); ?>
                                        </span>
                                    </td>

                                    <td class="description">
                                        <?php echo activityLogEscape($activity["description"]); ?>
                                    </td>

                                    <td class="date-time">
                                        <?php echo activityLogEscape($activity["created_at"]); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="empty-state">
                                    No activity records found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

<script>
    function toggleSidebar() {
        document.getElementById("adminSidebar").classList.toggle("open");
        document.getElementById("sidebarOverlay").classList.toggle("show");
    }

    function closeSidebar() {
        document.getElementById("adminSidebar").classList.remove("open");
        document.getElementById("sidebarOverlay").classList.remove("show");
    }
</script>

</body>
</html>
