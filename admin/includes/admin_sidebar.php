
<?php

// --------------------------------------------------
// SHARED ADMIN SIDEBAR
// --------------------------------------------------

require_once __DIR__ . "/../../includes/permissions.php";
require_once __DIR__ . "/../../config/database.php";

// --------------------------------------------------
// WEBSITE SETTINGS
// --------------------------------------------------

$sidebarWebsiteName = "Olivera Home Developers";
$sidebarWebsiteLogo = "";

try {
    $stmt = $pdo->query("
        SELECT setting_key, setting_value
        FROM settings
        WHERE setting_key IN ('website_name', 'website_logo')
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (
            $row["setting_key"] === "website_name" &&
            $row["setting_value"] !== ""
        ) {
            $sidebarWebsiteName = $row["setting_value"];
        }

        if ($row["setting_key"] === "website_logo") {
            $sidebarWebsiteLogo = $row["setting_value"];
        }
    }
} catch (Throwable $e) {
    error_log("Admin Sidebar Settings Error: " . $e->getMessage());
}

// --------------------------------------------------
// HELPER
// --------------------------------------------------

if (!function_exists("adminSidebarEscape")) {
    function adminSidebarEscape($value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8"
        );
    }
}

// --------------------------------------------------
// CURRENT PAGE
// --------------------------------------------------

$currentAdminPage = basename($_SERVER["PHP_SELF"] ?? "");

$adminSidebarActive = function (array $pages) use ($currentAdminPage): string {
    return in_array($currentAdminPage, $pages, true) ? "active" : "";
};

// --------------------------------------------------
// PERMISSIONS
// --------------------------------------------------

$sidebarCanManageAdmins = hasPermission("manage_admins");
$sidebarCanManageUsers = hasPermission("manage_users");
$sidebarCanManageContent = hasPermission("manage_content");
$sidebarCanManageProjects = hasPermission("manage_projects");
$sidebarCanManageFollowups = hasPermission("manage_followups");
$sidebarCanManageVisits = hasPermission("manage_site_visits");
$sidebarIsSalesEmployee = hasPermission("view_assigned_leads");

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// --------------------------------------------------
// EMPLOYEE NOTIFICATIONS
// --------------------------------------------------

$sidebarUnreadNotifications = 0;

if ($sidebarIsSalesEmployee) {
    try {
        $sidebarAdminId = (int)($_SESSION["admin_id"] ?? 0);

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE admin_id = ?
            AND is_read = 0
        ");

        $stmt->execute([$sidebarAdminId]);
        $sidebarUnreadNotifications = (int)$stmt->fetchColumn();

    } catch (Throwable $e) {
        error_log("Admin Sidebar Notification Error: " . $e->getMessage());
    }
}

// --------------------------------------------------
// LOGO VALIDATION
// --------------------------------------------------

$sidebarLogoPath = "";

if (
    is_string($sidebarWebsiteLogo) &&
    preg_match(
        '/^uploads\/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i',
        $sidebarWebsiteLogo
    )
) {
    $physicalLogoPath = dirname(__DIR__, 2) . "/" . $sidebarWebsiteLogo;

    if (is_file($physicalLogoPath)) {
        $sidebarLogoPath = "../" . $sidebarWebsiteLogo;
    }
}

?>

<aside class="sidebar">

    <a
        href="dashboard.php"
        class="sidebar-header"
        aria-label="Go to Admin Dashboard"
    >

        <?php if ($sidebarLogoPath !== ""): ?>
            <img
                src="<?php echo adminSidebarEscape($sidebarLogoPath); ?>"
                alt="<?php echo adminSidebarEscape($sidebarWebsiteName); ?> Logo"
            >
        <?php endif; ?>

        <div class="sidebar-header-text">

            <h2>
                <?php echo adminSidebarEscape($sidebarWebsiteName); ?>
            </h2>

            <p>Admin Control Panel</p>

        </div>

    </a>

    <nav class="sidebar-menu">

        <a
            href="dashboard.php"
            class="<?php echo $adminSidebarActive(["dashboard.php"]); ?>"
        >
            <span class="menu-icon">▦</span>
            Dashboard
        </a>

        <?php
        $gallerySidebarFile = __DIR__ . "/gallery_sidebar_link.php";

        if (is_file($gallerySidebarFile)) {
            require $gallerySidebarFile;
        }
        ?>

        <?php if ($sidebarCanManageAdmins): ?>

            <a
                href="manage_admins.php"
                class="<?php echo $adminSidebarActive(["manage_admins.php"]); ?>"
            >
                <span class="menu-icon">♙</span>
                Manage Admins
            </a>

            <a
                href="all_accounts.php"
                class="<?php echo $adminSidebarActive(["all_accounts.php"]); ?>"
            >
                <span class="menu-icon">☷</span>
                All Accounts
            </a>

            <a
                href="activity_logs.php"
                class="<?php echo $adminSidebarActive(["activity_logs.php"]); ?>"
            >
                <span class="menu-icon">◷</span>
                Activity Logs
            </a>

        <?php endif; ?>

        <?php if ($sidebarCanManageContent): ?>
            <a
                href="manage_content.php"
                class="<?php echo $adminSidebarActive(["manage_content.php", "edit_content.php"]); ?>"
            >
                <span class="menu-icon">▤</span>
                Manage Content
            </a>
        <?php endif; ?>

        <?php if ($sidebarCanManageProjects): ?>
            <a
                href="manage_projects.php"
                class="<?php echo $adminSidebarActive(["manage_projects.php"]); ?>"
            >
                <span class="menu-icon">⌂</span>
                Manage Projects
            </a>
        <?php endif; ?>

        <?php if ($sidebarCanManageUsers): ?>

            <a
                href="manage_users.php"
                class="<?php echo $adminSidebarActive(["manage_users.php"]); ?>"
            >
                <span class="menu-icon">♧</span>
                Manage Users
            </a>

            <a
                href="manage_customers.php"
                class="<?php echo $adminSidebarActive(["manage_customers.php"]); ?>"
            >
                <span class="menu-icon">♧</span>
                Manage Customers
            </a>

        <?php endif; ?>

        <?php if (hasPermission("manage_settings")): ?>
            <a
                href="manage_settings.php"
                class="<?php echo $adminSidebarActive(["manage_settings.php"]); ?>"
            >
                <span class="menu-icon">⚙</span>
                Website Settings
            </a>
        <?php endif; ?>

        <?php if (hasPermission("manage_leads")): ?>
            <a
                href="manage_leads.php"
                class="<?php echo $adminSidebarActive(["manage_leads.php"]); ?>"
            >
                <span class="menu-icon">♧</span>
                Manage Leads
            </a>
        <?php endif; ?>

        <?php if (hasPermission("manage_partnerships")): ?>
            <a
                href="manage_partnerships.php"
                class="<?php echo $adminSidebarActive(["manage_partnerships.php"]); ?>"
            >
                <span class="menu-icon">♙</span>
                Manage Partnerships
            </a>
        <?php endif; ?>

        <?php if ($sidebarCanManageFollowups): ?>

            <a
                href="lead_followups.php"
                class="<?php echo $adminSidebarActive(["lead_followups.php"]); ?>"
            >
                <span class="menu-icon">◷</span>
                Lead Follow-ups
            </a>

            <a
                href="followup_reminders.php"
                class="<?php echo $adminSidebarActive(["followup_reminders.php"]); ?>"
            >
                <span class="menu-icon">♢</span>
                Follow-up Reminders
            </a>

        <?php endif; ?>

        <?php if ($sidebarCanManageVisits): ?>
            <a
                href="site_visits.php"
                class="<?php echo $adminSidebarActive(["site_visits.php"]); ?>"
            >
                <span class="menu-icon">⌂</span>
                Site Visits
            </a>
        <?php endif; ?>

        <?php if (hasPermission("view_crm_reports")): ?>
            <a
                href="crm_reports.php"
                class="<?php echo $adminSidebarActive(["crm_reports.php"]); ?>"
            >
                <span class="menu-icon">▥</span>
                CRM Reports
            </a>
        <?php endif; ?>

        <?php if ($sidebarIsSalesEmployee): ?>

            <a
                href="my_assigned_leads.php"
                class="<?php echo $adminSidebarActive(["my_assigned_leads.php"]); ?>"
            >
                <span class="menu-icon">♙</span>
                My Assigned Leads
            </a>

            <a
                href="notifications.php"
                class="<?php echo $adminSidebarActive(["notifications.php"]); ?>"
            >
                <span class="menu-icon">♧</span>
                Notifications

                <?php if ($sidebarUnreadNotifications > 0): ?>
                    <span class="notification-badge">
                        <?php echo $sidebarUnreadNotifications; ?>
                    </span>
                <?php endif; ?>
            </a>

        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">

        <form action="logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo adminSidebarEscape($_SESSION["csrf_token"]); ?>"
            >

            <button type="submit">↪ &nbsp; Logout</button>

        </form>

    </div>

</aside>
