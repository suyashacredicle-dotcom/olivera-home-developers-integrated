
<?php

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

// --------------------------------------------------
// ACCESS CONTROL
// --------------------------------------------------

requirePermission("manage_admins");

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
// CSRF TOKEN
// --------------------------------------------------

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// --------------------------------------------------
// HELPER FUNCTION
// --------------------------------------------------

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// --------------------------------------------------
// PAGE MESSAGES
// --------------------------------------------------

$message = $_SESSION["admin_message"] ?? "";
$messageType = $_SESSION["admin_message_type"] ?? "success";

unset(
    $_SESSION["admin_message"],
    $_SESSION["admin_message_type"]
);

if (!is_string($message)) {
    $message = "";
}

if (!in_array($messageType, ["success", "error"], true)) {
    $messageType = "success";
}

// --------------------------------------------------
// SEARCH ADMIN ACCOUNTS
// --------------------------------------------------

$search = $_GET["search"] ?? "";

if (!is_string($search)) {
    $search = "";
}

$search = trim($search);

// Limit search input safely.
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

if ($search !== "") {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            email,
            role,
            status,
            created_at
        FROM admins
        WHERE name LIKE ? OR email LIKE ?
        ORDER BY id ASC
    ");

    $searchTerm = "%" . $search . "%";

    $stmt->execute([
        $searchTerm,
        $searchTerm
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            email,
            role,
            status,
            created_at
        FROM admins
        ORDER BY id ASC
    ");
}

$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// ACCOUNT SUMMARY
// --------------------------------------------------

$totalAccounts = count($admins);
$activeAccounts = 0;
$inactiveAccounts = 0;

foreach ($admins as $account) {

    if (($account["status"] ?? "") === "active") {
        $activeAccounts++;
    } else {
        $inactiveAccounts++;
    }
}

// --------------------------------------------------
// WEBSITE SETTINGS
// --------------------------------------------------

$stmt = $pdo->query("
    SELECT setting_key, setting_value
    FROM settings
");

$settingsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$settings = [];

foreach ($settingsRows as $row) {
    $settings[$row["setting_key"]] = $row["setting_value"];
}

$websiteName = $settings["website_name"] ?? "Olivera Home Developers";

if (!is_string($websiteName) || trim($websiteName) === "") {
    $websiteName = "Olivera Home Developers";
}

$websiteLogo = $settings["website_logo"] ?? "";

$logoPath = "";

if (
    is_string($websiteLogo) &&
    preg_match(
        '/\Auploads\/[a-f0-9]{32}\.(jpg|jpeg|png|webp)\z/i',
        $websiteLogo
    )
) {

    $physicalLogoPath = dirname(__DIR__) . "/" . $websiteLogo;

    if (is_file($physicalLogoPath)) {
        $logoPath = "../" . $websiteLogo;
    }
}

$adminName = $_SESSION["admin_name"] ?? "Admin";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>All Accounts | <?php echo e($websiteName); ?></title>

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
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--ivory);
            color: var(--text);
        }

        .sidebar {
            width: 255px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--navy);
            color: white;
            padding: 22px 15px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-header {
            text-align: center;
            padding: 10px 10px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 20px;
        }

        .sidebar-header img {
            max-width: 150px;
            max-height: 65px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .sidebar-header h2 {
            margin: 0 0 7px;
            font-size: 19px;
            color: #ffffff;
            overflow-wrap: anywhere;
        }

        .sidebar-header p {
            margin: 0;
            color: var(--gold-light);
            font-size: 12px;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            border-radius: 7px;
            color: #dbe4f0;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: var(--navy-light);
            color: var(--gold-light);
        }

        .sidebar-menu a.active {
            background: var(--gold);
            color: var(--navy);
            font-weight: bold;
        }

        .menu-icon {
            width: 21px;
            text-align: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 20px;
        }

        .sidebar-footer form {
            margin: 0;
        }

        .sidebar-footer button {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 7px;
            background: #9f302c;
            color: white;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .sidebar-footer button:hover {
            background: #7f2522;
        }

        .main-content {
            margin-left: 255px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 27px;
            color: var(--navy);
        }

        .page-header p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .welcome-label {
            padding: 12px 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 9px;
            font-size: 13px;
        }

        .welcome-label strong {
            color: var(--navy);
        }

        .message {
            padding: 14px 17px;
            margin-bottom: 22px;
            border-radius: 8px;
            font-size: 13px;
        }

        .message-success {
            background: #eaf8ef;
            border: 1px solid #c9ecd5;
            color: #187444;
        }

        .message-error {
            background: #fff0ef;
            border: 1px solid #f4cfcb;
            color: #b42318;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 4px solid var(--gold);
            border-radius: 11px;
            padding: 21px;
            box-shadow: 0 3px 12px rgba(7,27,56,0.04);
        }

        .summary-label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 12px;
        }

        .summary-number {
            color: var(--navy);
            font-size: 29px;
            font-weight: 700;
        }

        .content-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 11px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(7,27,56,0.04);
        }

        .section-heading {
            margin: 0 0 7px;
            font-size: 19px;
            color: var(--navy);
        }

        .section-description {
            margin: 0 0 20px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .search-area {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-area input {
            width: 100%;
            max-width: 360px;
            height: 42px;
            padding: 0 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
        }

        .search-area input:focus,
        .password-wrapper input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,0.15);
        }

        .primary-button,
        .secondary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 16px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .primary-button {
            border: 1px solid var(--navy);
            background: var(--navy);
            color: white;
        }

        .primary-button:hover {
            background: var(--navy-light);
        }

        .secondary-button {
            border: 1px solid var(--border);
            background: white;
            color: var(--text);
        }

        .secondary-button:hover {
            background: var(--ivory);
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        .accounts-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            text-align: left;
        }

        .accounts-table th {
            padding: 15px 13px;
            background: var(--ivory);
            color: var(--navy);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .accounts-table td {
            padding: 15px 13px;
            border-bottom: 1px solid #f1eee7;
            color: #42516a;
            font-size: 12px;
            vertical-align: middle;
        }

        .accounts-table tbody tr:hover {
            background: #fcfaf5;
        }

        .account-name {
            color: var(--navy);
            font-weight: 600;
        }

        .status-badge,
        .role-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .role-badge {
            background: #f8f1df;
            color: #765719;
            border: 1px solid #eddfbd;
            border-radius: 6px;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
        }

        .action-buttons form {
            margin: 0;
        }

        .action-button {
            min-height: 32px;
            padding: 0 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: white;
            color: var(--text);
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .edit-button {
            color: var(--navy);
            border-color: #c7d3e2;
        }

        .delete-button {
            color: #dc3545;
            border-color: #f3c8cd;
        }

        .reset-button {
            color: #765719;
            border-color: #eddfbd;
        }

        .action-button:hover {
            background: var(--ivory);
        }

        .reset-form {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 165px;
            margin-top: 8px;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            width: 100%;
            height: 35px;
            padding: 0 58px 0 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 12px;
        }

        .toggle-password {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--navy);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .protected-label {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 6px;
            background: #fff5db;
            color: #8a6500;
            font-size: 11px;
            font-weight: 700;
        }

        .empty-state {
            padding: 35px 15px !important;
            text-align: center;
            color: var(--muted) !important;
        }

        .bottom-link {
            display: inline-block;
            margin-top: 5px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .bottom-link:hover {
            color: #946d19;
            text-decoration: underline;
        }

        .page-footer {
            text-align: center;
            padding: 20px 10px;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 1000px) {

            .sidebar {
                width: 220px;
            }

            .main-content {
                margin-left: 220px;
                padding: 23px;
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 17px;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }

            .sidebar-menu a {
                padding: 10px;
                font-size: 12px;
            }

            .sidebar-footer {
                margin-top: 15px;
                padding-top: 10px;
            }

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .content-card {
                padding: 17px;
            }

            .search-area {
                align-items: stretch;
                flex-direction: column;
            }

            .search-area input {
                max-width: 100%;
            }

        }

    </style>

</head>

<body>

<aside class="sidebar">

    <div class="sidebar-header">

        <?php if ($logoPath !== ""): ?>
            <img src="<?php echo e($logoPath); ?>" alt="Website Logo">
        <?php endif; ?>

        <h2><?php echo e($websiteName); ?></h2>
        <p>Admin Control Panel</p>

    </div>

    <nav class="sidebar-menu">

        <a href="dashboard.php">
            <span class="menu-icon">▦</span>
            Dashboard
        </a>

        <?php
        require __DIR__ . "/includes/gallery_sidebar_link.php";
        ?>

        <a href="manage_admins.php">
            <span class="menu-icon">♙</span>
            Manage Admins
        </a>

        <a href="all_accounts.php" class="active" aria-current="page">
            <span class="menu-icon">☷</span>
            All Accounts
        </a>

        <a href="activity_logs.php">
            <span class="menu-icon">◷</span>
            Activity Logs
        </a>

        <?php if (hasPermission("manage_content")): ?>
            <a href="manage_content.php">
                <span class="menu-icon">▤</span>
                Manage Content
            </a>
        <?php endif; ?>

        <?php if (hasPermission("manage_users")): ?>

            <a href="manage_users.php">
                <span class="menu-icon">♧</span>
                Manage Users
            </a>

            <a href="manage_customers.php">
                <span class="menu-icon">♧</span>
                Manage Customers
            </a>

        <?php endif; ?>

        <?php if (hasPermission("manage_settings")): ?>
            <a href="manage_settings.php">
                <span class="menu-icon">⚙</span>
                Website Settings
            </a>
        <?php endif; ?>

        <a href="profile.php">
            <span class="menu-icon">♙</span>
            My Profile
        </a>

        <a href="change_password.php">
            <span class="menu-icon">🔒</span>
            Change Password
        </a>

    </nav>

    <div class="sidebar-footer">

        <form action="logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION["csrf_token"]); ?>"
            >

            <button type="submit">↪ &nbsp; Logout</button>

        </form>

    </div>

</aside>

<main class="main-content">

    <header class="page-header">

        <div>
            <h1>All Accounts</h1>
            <p>Search, view and manage all administrator accounts.</p>
        </div>

        <div class="welcome-label">
            Welcome, <strong><?php echo e($adminName); ?></strong>
        </div>

    </header>

    <?php if ($message !== ""): ?>

        <div
            class="message message-<?php echo e($messageType); ?>"
            role="alert"
        >
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <div class="summary-grid">

        <div class="summary-card">
            <div class="summary-label">Accounts Listed</div>
            <div class="summary-number"><?php echo (int)$totalAccounts; ?></div>
        </div>

        <div class="summary-card">
            <div class="summary-label">Active Accounts</div>
            <div class="summary-number"><?php echo (int)$activeAccounts; ?></div>
        </div>

        <div class="summary-card">
            <div class="summary-label">Inactive Accounts</div>
            <div class="summary-number"><?php echo (int)$inactiveAccounts; ?></div>
        </div>

    </div>

    <section class="content-card">

        <h2 class="section-heading">Administrator Account Directory</h2>

        <p class="section-description">
            Find accounts by name or email address. You can edit accounts,
            delete accounts or reset passwords from this page.
        </p>

        <form method="GET" action="all_accounts.php" class="search-area">

            <input
                type="text"
                name="search"
                maxlength="100"
                placeholder="Search by name or email..."
                value="<?php echo e($search); ?>"
            >

            <button type="submit" class="primary-button">
                Search Accounts
            </button>

            <a href="all_accounts.php" class="secondary-button">
                Clear Search
            </a>

        </form>

        <div class="table-container">

            <table class="accounts-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($admins) > 0): ?>

                        <?php foreach ($admins as $admin): ?>

                            <tr>

                                <td>
                                    #<?php echo (int)$admin["id"]; ?>
                                </td>

                                <td class="account-name">
                                    <?php echo e($admin["name"]); ?>
                                </td>

                                <td>
                                    <?php echo e($admin["email"]); ?>
                                </td>

                                <td>
                                    <span class="role-badge">
                                        <?php
                                        echo e(
                                            str_replace(
                                                "_",
                                                " ",
                                                (string)$admin["role"]
                                            )
                                        );
                                        ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-badge <?php echo ($admin["status"] ?? "") === "active" ? "status-active" : "status-inactive"; ?>">
                                        <?php echo e($admin["status"]); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php echo e($admin["created_at"]); ?>
                                </td>

                                <td>

                                    <?php if (
                                        in_array(
                                            $admin["role"],
                                            ["admin", "editor", "sales_employee"],
                                            true
                                        )
                                    ): ?>

                                        <div class="action-buttons">

                                            <a
                                                href="actions/edit_admin.php?id=<?php echo (int)$admin["id"]; ?>"
                                                class="action-button edit-button"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                action="actions/delete_admin.php"
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this account? This action cannot be undone.');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo e($_SESSION["csrf_token"]); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo (int)$admin["id"]; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-button delete-button"
                                                >
                                                    Delete
                                                </button>

                                            </form>

                                        </div>

                                        <form
                                            action="actions/reset_admin_password.php"
                                            method="POST"
                                            class="reset-form"
                                            onsubmit="return confirm('Are you sure you want to reset this account password?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo e($_SESSION["csrf_token"]); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int)$admin["id"]; ?>"
                                            >

                                            <div class="password-wrapper">

                                                <input
                                                    type="password"
                                                    id="reset_password_<?php echo (int)$admin["id"]; ?>"
                                                    name="new_password"
                                                    placeholder="Enter new password"
                                                    minlength="12"
                                                    maxlength="4096"
                                                    autocomplete="new-password"
                                                    required
                                                >

                                                <button
                                                    type="button"
                                                    class="toggle-password"
                                                    data-target="reset_password_<?php echo (int)$admin["id"]; ?>"
                                                    aria-label="Show new password"
                                                    aria-pressed="false"
                                                >
                                                    Show
                                                </button>

                                            </div>

                                            <button
                                                type="submit"
                                                class="action-button reset-button"
                                            >
                                                Reset Password
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="protected-label">
                                            Protected Super Admin
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty-state">
                                No accounts found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <a href="manage_admins.php" class="bottom-link">
        &larr; Back to Manage Admins
    </a>

    <footer class="page-footer">
        <?php echo e($websiteName); ?> — Admin Control Panel
    </footer>

</main>

<script>

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

</script>

</body>
</html>
