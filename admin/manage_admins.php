
<?php

// --------------------------------------------------
// MANAGE ADMIN ACCOUNTS
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/activity_logger.php";

requirePermission("manage_admins");

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
}

// --------------------------------------------------
// OUTPUT ESCAPING
// --------------------------------------------------

function manageAdminsEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// --------------------------------------------------
// PAGE MESSAGES
// --------------------------------------------------

$message = "";
$messageType = "success";

// --------------------------------------------------
// CREATE CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// --------------------------------------------------
// DISPLAY SAVED MESSAGE
// --------------------------------------------------

$message = $_SESSION["admin_message"] ?? "";
$messageType = $_SESSION["admin_message_type"] ?? "success";

unset($_SESSION["admin_message"]);
unset($_SESSION["admin_message_type"]);

// --------------------------------------------------
// CREATE NEW ADMIN ACCOUNT
// --------------------------------------------------

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["create_admin"])
) {
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !is_string($_SESSION["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {
        http_response_code(403);
        exit("Invalid request. Please reload the page.");
    }

    $name = $_POST["name"] ?? "";
    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "";

    if (
        !is_string($name) ||
        !is_string($email) ||
        !is_string($password) ||
        !is_string($role)
    ) {
        $_SESSION["admin_message"] = "Invalid input provided.";
        $_SESSION["admin_message_type"] = "error";

    } else {

        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === "") {

            $_SESSION["admin_message"] = "Please enter the full name.";
            $_SESSION["admin_message_type"] = "error";

        } elseif (strlen($name) > 100) {

            $_SESSION["admin_message"] = "Name cannot exceed 100 characters.";
            $_SESSION["admin_message_type"] = "error";

        } elseif (
            $email === "" ||
            strlen($email) > 254 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {

            $_SESSION["admin_message"] = "Please enter a valid email address.";
            $_SESSION["admin_message_type"] = "error";

        } elseif (
            strlen($password) < 12 ||
            strlen($password) > 4096
        ) {

            $_SESSION["admin_message"] =
                "Password must contain between 12 and 4096 characters.";

            $_SESSION["admin_message_type"] = "error";

        } elseif (!preg_match('/[A-Z]/', $password)) {

            $_SESSION["admin_message"] =
                "Password must contain at least one uppercase letter.";

            $_SESSION["admin_message_type"] = "error";

        } elseif (!preg_match('/[a-z]/', $password)) {

            $_SESSION["admin_message"] =
                "Password must contain at least one lowercase letter.";

            $_SESSION["admin_message_type"] = "error";

        } elseif (!preg_match('/[0-9]/', $password)) {

            $_SESSION["admin_message"] =
                "Password must contain at least one number.";

            $_SESSION["admin_message_type"] = "error";

        } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {

            $_SESSION["admin_message"] =
                "Password must contain at least one special character.";

            $_SESSION["admin_message_type"] = "error";

        } elseif (
            !in_array(
                $role,
                ["admin", "editor", "sales_employee"],
                true
            )
        ) {

            $_SESSION["admin_message"] = "Please select a valid role.";
            $_SESSION["admin_message_type"] = "error";

        } else {

            try {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {
                    throw new RuntimeException("Password hashing failed.");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO admins
                    (name, email, password, role, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $hashedPassword,
                    $role
                ]);

                logActivity(
                    $pdo,
                    "ADMIN_CREATED",
                    "Created a new " . $role .
                    " account for " . $name .
                    " (" . $email . ")."
                );

                $_SESSION["admin_message"] =
                    "New account created successfully!";

                $_SESSION["admin_message_type"] = "success";

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            } catch (PDOException $e) {

                if ($e->getCode() === "23000") {

                    $_SESSION["admin_message"] =
                        "This email address is already registered.";

                } else {

                    error_log($e->getMessage());

                    $_SESSION["admin_message"] =
                        "Something went wrong. Please try again.";
                }

                $_SESSION["admin_message_type"] = "error";

            } catch (Throwable $e) {

                error_log($e->getMessage());

                $_SESSION["admin_message"] =
                    "Something went wrong. Please try again.";

                $_SESSION["admin_message_type"] = "error";
            }
        }
    }

    header("Location: manage_admins.php");
    exit;
}

// --------------------------------------------------
// SIDEBAR PERMISSIONS
// --------------------------------------------------

$canManageUsers = hasPermission("manage_users");
$canManageAdmins = hasPermission("manage_admins");
$canViewActivityLog = hasPermission("view_activity_log");

$canManageLeads =
    hasPermission("manage_leads") ||
    hasPermission("view_all_leads");

$canManagePartnerships = hasPermission("manage_partnerships");
$canManageFollowups = hasPermission("manage_followups");
$canManageVisits = hasPermission("manage_site_visits");
$canViewCRMReports = hasPermission("view_crm_reports");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Admin Accounts | Olivera Admin</title>

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
            --sidebar-width: 265px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--ivory);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        button,
        input,
        select {
            font: inherit;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
            width: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            background: var(--navy);
            color: #fff;
            box-shadow: 4px 0 18px rgba(7, 27, 56, .12);
            transition: transform .25s ease;
        }

        .sidebar-brand {
            padding: 25px 22px 23px;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }

        .brand-name {
            color: #fff;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: .5px;
        }

        .brand-name span {
            color: var(--gold-light);
        }

        .brand-caption {
            margin-top: 7px;
            color: #b9c5d5;
            font-size: 10px;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .sidebar-section {
            padding: 20px 14px 8px;
            color: #9eacc0;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .sidebar-nav {
            padding: 7px 12px 20px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 45px;
            margin-bottom: 5px;
            padding: 10px 13px;
            border: 1px solid transparent;
            border-radius: 8px;
            color: #dce4ef;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: .2s ease;
        }

        .sidebar-nav a:hover {
            border-color: rgba(214,166,59,.22);
            background: rgba(255,255,255,.07);
            color: var(--gold-light);
        }

        .sidebar-nav a.active {
            border-color: rgba(237,202,118,.3);
            background: linear-gradient(
                100deg,
                rgba(214,166,59,.22),
                rgba(214,166,59,.08)
            );
            color: var(--gold-light);
        }

        .nav-icon {
            width: 23px;
            flex-shrink: 0;
            color: var(--gold-light);
            text-align: center;
            font-size: 17px;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 17px 20px;
            border-top: 1px solid rgba(255,255,255,.12);
            color: #aebbd0;
            font-size: 11px;
            line-height: 1.7;
        }

        .sidebar-footer strong {
            color: var(--gold-light);
        }

        .sidebar-overlay {
            display: none;
        }

        /* MAIN CONTENT */

        .main-content {
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            margin-left: var(--sidebar-width);
            padding: 28px;
        }

        .page-container {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
        }

        .mobile-topbar {
            display: none;
        }

        /* PAGE HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 25px 28px;
            background: linear-gradient(
                120deg,
                var(--navy),
                var(--navy-light)
            );
            border: 1px solid var(--navy-light);
            border-radius: 13px;
            color: white;
        }

        .page-header h1 {
            margin: 0 0 8px;
            color: white;
            font-size: 26px;
            font-weight: 750;
        }

        .page-header p {
            margin: 0;
            color: #d7dfeb;
            font-size: 13px;
            line-height: 1.6;
        }

        .welcome-text {
            padding: 11px 16px;
            border: 1px solid rgba(237, 202, 118, .4);
            border-radius: 8px;
            background: rgba(214, 166, 59, .12);
            color: #e8e9eb;
            font-size: 13px;
        }

        .welcome-text strong {
            color: var(--gold-light);
        }

        /* CONTENT CARD */

        .content-card {
            margin-bottom: 24px;
            padding: 27px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(7, 27, 56, .035);
        }

        .section-heading {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 20px;
            font-weight: 750;
        }

        .section-description {
            margin: 0 0 24px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        /* FORM GRID */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 21px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214, 166, 59, .12);
        }

        .form-group input::placeholder {
            color: #a0aaba;
        }

        /* PASSWORD */

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            padding-right: 75px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            padding: 7px;
            border: 0;
            background: transparent;
            color: #94701e;
            font-size: 12px;
            font-weight: 750;
            cursor: pointer;
        }

        .toggle-password:hover {
            color: var(--navy);
        }

        .password-help {
            margin-top: 11px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #faf8f2;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .password-help strong {
            color: var(--navy);
        }

        .password-help ul {
            margin: 6px 0 0;
            padding-left: 19px;
        }

        .password-help li {
            padding-left: 2px;
        }

        /* BUTTONS */

        .primary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 21px;
            border: 1px solid var(--gold);
            border-radius: 8px;
            background: linear-gradient(
                120deg,
                var(--gold-light),
                var(--gold)
            );
            color: var(--navy);
            font-size: 13px;
            font-weight: 750;
            text-decoration: none;
            cursor: pointer;
            transition: filter .2s, transform .2s;
        }

        .primary-button:hover {
            filter: brightness(.96);
            transform: translateY(-1px);
        }

        /* MESSAGES */

        .message {
            margin-bottom: 22px;
            padding: 14px 17px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }

        .message-success {
            border: 1px solid #c9e5cf;
            background: #edf7ee;
            color: #27653a;
        }

        .message-error {
            border: 1px solid #efcfca;
            background: #fbefed;
            color: #a33128;
        }

        /* ALL ACCOUNTS */

        .all-accounts-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            padding: 23px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #faf8f2;
        }

        .all-accounts-card h2 {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 18px;
        }

        .all-accounts-card p {
            max-width: 650px;
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        /* FOOTER */

        .bottom-link {
            display: inline-block;
            margin-top: 4px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .bottom-link:hover {
            color: #94701e;
            text-decoration: underline;
        }

        /* MOBILE */

        @media (max-width: 1050px) {
            :root {
                --sidebar-width: 235px;
            }

            .main-content {
                padding: 20px;
            }
        }

        @media (max-width: 760px) {

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay.show {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 999;
                background: rgba(3,13,29,.55);
            }

            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 13px;
            }

            .mobile-topbar {
                display: flex;
                align-items: center;
                gap: 13px;
                margin-bottom: 16px;
                padding: 13px 15px;
                border: 1px solid var(--border);
                border-radius: 10px;
                background: #fff;
            }

            .menu-toggle {
                width: 39px;
                height: 39px;
                border: 0;
                border-radius: 7px;
                background: var(--navy);
                color: var(--gold-light);
                font-size: 21px;
                cursor: pointer;
            }

            .mobile-brand {
                color: var(--navy);
                font-size: 15px;
                font-weight: 800;
            }

            .mobile-brand span {
                color: #a77a1d;
            }
        }

        @media (max-width: 700px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: auto;
            }
        }

        @media (max-width: 500px) {

            .main-content {
                padding: 15px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .content-card {
                padding: 18px;
            }

            .all-accounts-card {
                align-items: flex-start;
                flex-direction: column;
            }

            .all-accounts-card .primary-button {
                width: 100%;
            }

            .welcome-text {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<!-- SIDEBAR OVERLAY -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
    onclick="closeSidebar()"
></div>

<!-- SIDEBAR -->

<aside class="sidebar" id="adminSidebar">

    <div class="sidebar-brand">

        <div class="brand-name">
            OLIVERA <span>HOME</span>
        </div>

        <div class="brand-caption">
            Developers Admin Panel
        </div>

    </div>

    <div class="sidebar-section">
        Main Navigation
    </div>

    <nav class="sidebar-nav">

        <a href="dashboard.php">
            <span class="nav-icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <?php if ($canManageUsers): ?>
            <a href="property_enquiries.php">
                <span class="nav-icon">⌕</span>
                <span>Property Enquiries</span>
            </a>
        <?php endif; ?>

        <?php if ($canManageAdmins): ?>
            <a href="manage_admins.php" class="active">
                <span class="nav-icon">♙</span>
                <span>Manage Admin</span>
            </a>
        <?php endif; ?>

        <?php if ($canViewActivityLog): ?>
            <a href="activity_logs.php">
                <span class="nav-icon">◷</span>
                <span>Activity Log</span>
            </a>
        <?php endif; ?>

        <?php if ($canManageLeads): ?>
            <a href="manage_leads.php">
                <span class="nav-icon">▤</span>
                <span>Manage Leads</span>
            </a>
        <?php endif; ?>

        <?php if ($canManagePartnerships): ?>
            <a href="manage_partnerships.php">
                <span class="nav-icon">♧</span>
                <span>Manage Partnerships</span>
            </a>
        <?php endif; ?>

        <?php if ($canManageFollowups): ?>
            <a href="lead_followups.php">
                <span class="nav-icon">☷</span>
                <span>Lead Follow-ups</span>
            </a>

            <a href="followup_reminders.php">
                <span class="nav-icon">♧</span>
                <span>Follow-up Reminders</span>
            </a>
        <?php endif; ?>

        <?php if ($canManageVisits): ?>
            <a href="site_visits.php">
                <span class="nav-icon">⌖</span>
                <span>Site Visits</span>
            </a>

            <a href="weekly_site_visits.php">
                <span class="nav-icon">▦</span>
                <span>Weekly Schedule</span>
            </a>

            <a href="reschedule_site_visits.php">
                <span class="nav-icon">◷</span>
                <span>Reschedule Visits</span>
            </a>

            <a href="cancel_site_visit.php">
                <span class="nav-icon">⊗</span>
                <span>Cancel Site Visits</span>
            </a>

            <a href="site_visit_reschedule_history.php">
                <span class="nav-icon">▤</span>
                <span>Rescheduling History</span>
            </a>
        <?php endif; ?>

        <?php if ($canViewCRMReports): ?>
            <a href="crm_reports.php">
                <span class="nav-icon">▥</span>
                <span>CRM Reports</span>
            </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <strong>OLIVERA HOME DEVELOPERS</strong><br>
        Property Management System
    </div>

</aside>

<!-- MAIN CONTENT -->

<main class="main-content">

    <!-- MOBILE TOPBAR -->

    <div class="mobile-topbar">

        <button
            type="button"
            class="menu-toggle"
            onclick="toggleSidebar()"
            aria-label="Open navigation"
            aria-expanded="false"
            id="menuToggle"
        >
            ☰
        </button>

        <div class="mobile-brand">
            OLIVERA <span>HOME</span>
        </div>

    </div>

    <div class="page-container">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <h1>Manage Admin Accounts</h1>

                <p>
                    Create administrator, editor and sales employee accounts.
                </p>

            </div>

            <div class="welcome-text">

                Welcome,
                <strong>
                    <?php echo manageAdminsEscape($_SESSION["admin_name"] ?? "Admin"); ?>
                </strong>

            </div>

        </div>

        <!-- SUCCESS AND ERROR MESSAGES -->

        <?php if ($message !== ""): ?>

            <div
                class="message <?php echo $messageType === "error" ? "message-error" : "message-success"; ?>"
                role="alert"
            >
                <?php echo manageAdminsEscape($message); ?>
            </div>

        <?php endif; ?>

        <!-- CREATE ADMIN ACCOUNT -->

        <section class="content-card">

            <h2 class="section-heading">
                Create New Account
            </h2>

            <p class="section-description">
                Add a new administrator, editor or sales employee to your website.
            </p>

            <form method="POST" action="manage_admins.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo manageAdminsEscape($_SESSION["csrf_token"]); ?>"
                >

                <div class="form-grid">

                    <div class="form-group">

                        <label for="admin_name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="admin_name"
                            name="name"
                            placeholder="Enter full name"
                            maxlength="100"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="admin_email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="admin_email"
                            name="email"
                            placeholder="Enter email address"
                            maxlength="254"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="create_admin_password">
                            Password
                        </label>

                        <div class="password-wrapper">

                            <input
                                type="password"
                                id="create_admin_password"
                                name="password"
                                placeholder="Create a strong password"
                                minlength="12"
                                maxlength="4096"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="toggle-password"
                                data-target="create_admin_password"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                Show
                            </button>

                        </div>

                        <div class="password-help">

                            <strong>Password requirements:</strong>

                            <ul>
                                <li>At least 12 characters</li>
                                <li>One uppercase letter (A-Z)</li>
                                <li>One lowercase letter (a-z)</li>
                                <li>One number (0-9)</li>
                                <li>One special character</li>
                            </ul>

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="admin_role">
                            Select Role
                        </label>

                        <select id="admin_role" name="role" required>

                            <option value="">
                                Choose a role
                            </option>

                            <option value="admin">
                                Admin
                            </option>

                            <option value="editor">
                                Editor
                            </option>

                            <option value="sales_employee">
                                Sales Employee
                            </option>

                        </select>

                    </div>

                    <div class="form-group full-width">

                        <button
                            type="submit"
                            name="create_admin"
                            class="primary-button"
                        >
                            + Create Account
                        </button>

                    </div>

                </div>

            </form>

        </section>

        <!-- ALL ACCOUNTS -->

        <section class="content-card">

            <div class="all-accounts-card">

                <div>

                    <h2>All Accounts</h2>

                    <p>
                        View, search, edit, delete and manage existing
                        administration accounts on a separate page.
                    </p>

                </div>

                <a href="all_accounts.php" class="primary-button">
                    Open All Accounts
                </a>

            </div>

        </section>

        <!-- BACK TO DASHBOARD -->

        <a href="dashboard.php" class="bottom-link">
            &larr; Back to Dashboard
        </a>

    </div>

</main>

<!-- SHOW / HIDE PASSWORD AND SIDEBAR -->

<script>

function toggleSidebar() {

    const sidebar = document.getElementById("adminSidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const button = document.getElementById("menuToggle");

    sidebar.classList.toggle("open");
    overlay.classList.toggle("show");

    button.setAttribute(
        "aria-expanded",
        sidebar.classList.contains("open") ? "true" : "false"
    );
}

function closeSidebar() {

    const sidebar = document.getElementById("adminSidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const button = document.getElementById("menuToggle");

    sidebar.classList.remove("open");
    overlay.classList.remove("show");

    button.setAttribute("aria-expanded", "false");
}

document.querySelectorAll(".toggle-password").forEach(function(button) {

    button.addEventListener("click", function() {

        const targetId = this.getAttribute("data-target");
        const passwordInput = document.getElementById(targetId);

        if (!passwordInput) {
            return;
        }

        const isHidden = passwordInput.type === "password";

        passwordInput.type = isHidden ? "text" : "password";

        this.textContent = isHidden ? "Hide" : "Show";

        this.setAttribute(
            "aria-label",
            isHidden ? "Hide password" : "Show password"
        );

        this.setAttribute(
            "aria-pressed",
            isHidden ? "true" : "false"
        );

    });

});

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closeSidebar();
    }

});

</script>

</body>
</html>
