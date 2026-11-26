
<?php

// --------------------------------------------------
// MANAGE WEBSITE USERS
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

requirePermission("manage_users");

// --------------------------------------------------
// ESCAPE OUTPUT
// --------------------------------------------------

function manageUsersEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
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
// DISPLAY SAVED MESSAGES
// --------------------------------------------------

$message = $_SESSION["user_message"] ?? "";
$messageType = $_SESSION["user_message_type"] ?? "success";

unset($_SESSION["user_message"]);
unset($_SESSION["user_message_type"]);

// --------------------------------------------------
// CREATE NEW WEBSITE USER
// --------------------------------------------------

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["create_user"])
) {
    $submittedToken = $_POST["csrf_token"] ?? "";
    $sessionToken = $_SESSION["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        $sessionToken === "" ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        $_SESSION["user_message"] =
            "Invalid request. Please reload the page.";

        $_SESSION["user_message_type"] = "error";

        header("Location: manage_users.php");
        exit;
    }

    $name = $_POST["name"] ?? "";
    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";

    if (
        !is_string($name) ||
        !is_string($email) ||
        !is_string($password)
    ) {
        $_SESSION["user_message"] = "Invalid user details.";
        $_SESSION["user_message_type"] = "error";

    } else {

        $name = trim($name);
        $email = strtolower(trim($email));

        if (
            $name === "" ||
            strlen($name) > 100 ||
            $email === "" ||
            strlen($email) > 254 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($password) < 12 ||
            strlen($password) > 4096
        ) {
            $_SESSION["user_message"] =
                "Enter valid details. Name must not exceed 100 characters, email must be valid, and password must be between 12 and 4096 characters.";

            $_SESSION["user_message_type"] = "error";

        } else {

            try {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {
                    throw new RuntimeException(
                        "Password hashing failed."
                    );
                }

                $stmt = $pdo->prepare("
                    INSERT INTO users
                        (name, email, password, status)
                    VALUES
                        (?, ?, ?, 'active')
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $hashedPassword
                ]);

                $_SESSION["user_message"] =
                    "New website user created successfully!";

                $_SESSION["user_message_type"] = "success";

                $_SESSION["csrf_token"] =
                    bin2hex(random_bytes(32));

            } catch (PDOException $e) {

                if ($e->getCode() === "23000") {

                    $_SESSION["user_message"] =
                        "This email address is already registered.";

                } else {

                    error_log(
                        "Website user creation failed: " . $e->getMessage()
                    );

                    $_SESSION["user_message"] =
                        "Something went wrong. Please try again.";
                }

                $_SESSION["user_message_type"] = "error";

            } catch (Throwable $e) {

                error_log($e->getMessage());

                $_SESSION["user_message"] =
                    "Something went wrong. Please try again.";

                $_SESSION["user_message_type"] = "error";
            }
        }
    }

    header("Location: manage_users.php");
    exit;
}

// --------------------------------------------------
// SEARCH USERS
// --------------------------------------------------

$search = $_GET["search"] ?? "";

if (!is_string($search)) {
    $search = "";
}

$search = trim($search);

if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

// --------------------------------------------------
// FETCH USERS
// --------------------------------------------------

try {

    if ($search !== "") {

        $stmt = $pdo->prepare("
            SELECT id, name, email, status, created_at
            FROM users
            WHERE name LIKE ? OR email LIKE ?
            ORDER BY id DESC
        ");

        $searchTerm = "%" . $search . "%";

        $stmt->execute([
            $searchTerm,
            $searchTerm
        ]);

    } else {

        $stmt = $pdo->query("
            SELECT id, name, email, status, created_at
            FROM users
            ORDER BY id DESC
        ");
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        "Website users listing failed: " . $e->getMessage()
    );

    $users = [];

    if ($message === "") {
        $message = "Unable to load website users.";
        $messageType = "error";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Website Users | Olivera Admin</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

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
            background: var(--ivory);
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text);
        }

        /* LAYOUT */

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 250px;
            min-width: 250px;
            min-height: 100vh;
            padding: 25px 16px;
            background: var(--navy);
            color: white;
        }

        .sidebar-brand {
            margin-bottom: 20px;
            padding: 0 12px 22px;
            border-bottom: 1px solid rgba(255,255,255,.13);
            color: var(--gold-light);
            font-size: 21px;
            font-weight: 750;
            letter-spacing: .2px;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 14px;
            border-radius: 8px;
            color: #d5deea;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s, color .2s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(214,166,59,.16);
            color: var(--gold-light);
        }

        .sidebar-section {
            padding: 19px 14px 7px;
            color: #9baac0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .sidebar-logout {
            margin: 20px 5px 0;
        }

        .sidebar-logout button {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(237,202,118,.5);
            border-radius: 8px;
            background: transparent;
            color: var(--gold-light);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }

        .sidebar-logout button:hover {
            background: rgba(214,166,59,.15);
        }

        /* MAIN CONTENT */

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 32px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 27px;
            padding: 25px 28px;
            border: 1px solid var(--navy-light);
            border-radius: 13px;
            background: linear-gradient(
                120deg,
                var(--navy),
                var(--navy-light)
            );
        }

        .page-header h1 {
            margin: 0 0 7px;
            color: white;
            font-size: 27px;
            font-weight: 750;
        }

        .page-header p {
            margin: 0;
            color: #d7dfeb;
            font-size: 13px;
            line-height: 1.6;
        }

        .welcome-text {
            padding: 11px 15px;
            border: 1px solid rgba(237,202,118,.4);
            border-radius: 8px;
            background: rgba(214,166,59,.12);
            color: #e2e8f0;
            font-size: 13px;
        }

        .welcome-text strong {
            color: var(--gold-light);
        }

        /* CONTENT CARDS */

        .content-card {
            margin-bottom: 24px;
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
            box-shadow: 0 3px 12px rgba(7,27,56,.035);
        }

        .card-heading {
            margin-bottom: 23px;
            padding-bottom: 17px;
            border-bottom: 1px solid var(--border);
        }

        .card-heading h2 {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 19px;
        }

        .card-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        /* MESSAGES */

        .message {
            margin-bottom: 22px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }

        .message.success {
            border: 1px solid #c9e5cf;
            background: #edf7ee;
            color: #27653a;
        }

        .message.error {
            border: 1px solid #efcfca;
            background: #fbefed;
            color: #a33128;
        }

        /* FORM */

        .form-group {
            max-width: 650px;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            background: white;
            color: var(--text);
            font-size: 14px;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-control:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,.13);
        }

        .password-wrapper {
            position: relative;
            width: 100%;
            max-width: 500px;
        }

        .password-wrapper input {
            padding-right: 75px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            padding: 6px;
            border: none;
            background: transparent;
            color: #94701e;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .toggle-password:hover {
            color: var(--navy);
        }

        .form-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
        }

        /* BUTTONS */

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border: 1px solid transparent;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: background .2s, border-color .2s, color .2s;
        }

        .btn-primary {
            border-color: var(--gold);
            background: linear-gradient(
                120deg,
                var(--gold-light),
                var(--gold)
            );
            color: var(--navy);
        }

        .btn-primary:hover {
            filter: brightness(.96);
        }

        .btn-secondary {
            border-color: #e5d5a9;
            background: #faf5e8;
            color: #80601b;
        }

        .btn-secondary:hover {
            background: #f2e8ce;
        }

        .btn-danger {
            border-color: #efcfca;
            background: #fbefed;
            color: #a33128;
        }

        .btn-danger:hover {
            background: #f7e2df;
        }

        .btn-success {
            border-color: #c9e5cf;
            background: #edf7ee;
            color: #27653a;
        }

        .btn-success:hover {
            background: #dcefe0;
        }

        .btn-warning {
            border-color: #ead7a9;
            background: #fbf3df;
            color: #896319;
        }

        .btn-warning:hover {
            background: #f4e9c9;
        }

        /* SEARCH */

        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }

        .search-form .form-control {
            flex: 1;
            min-width: 200px;
        }

        .search-clear {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            color: #94701e;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .search-clear:hover {
            color: var(--navy);
            text-decoration: underline;
        }

        /* TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .users-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .users-table th {
            padding: 14px 13px;
            border-bottom: 1px solid var(--border);
            background: #faf8f2;
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .5px;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .users-table td {
            padding: 15px 13px;
            border-bottom: 1px solid #f0ede6;
            color: #475569;
            font-size: 13px;
            vertical-align: middle;
        }

        .users-table tbody tr:last-child td {
            border-bottom: none;
        }

        .users-table tbody tr:hover {
            background: #fcfaf5;
        }

        .user-name {
            color: var(--navy) !important;
            font-weight: 650;
        }

        .user-id {
            color: var(--muted) !important;
            font-weight: 700;
        }

        /* STATUS BADGES */

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 750;
            text-transform: capitalize;
        }

        .status-active {
            background: #edf7ee;
            color: #27653a;
        }

        .status-inactive {
            background: #fbefed;
            color: #a33128;
        }

        /* ACTIONS */

        .action-buttons {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
        }

        .action-buttons form {
            margin: 0;
        }

        .empty-state {
            padding: 35px 15px !important;
            color: var(--muted) !important;
            text-align: center;
        }

        /* FOOTER */

        .account-footer {
            margin-top: 25px;
        }

        .account-footer a {
            color: var(--navy);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .account-footer a:hover {
            color: #94701e;
            text-decoration: underline;
        }

        /* RESPONSIVE */

        @media (max-width: 850px) {

            .admin-layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-width: 0;
                min-height: auto;
                padding: 18px;
            }

            .sidebar-brand {
                padding-bottom: 15px;
                margin-bottom: 12px;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sidebar-section {
                grid-column: 1 / -1;
            }

            .main-content {
                padding: 22px;
            }
        }

        @media (max-width: 500px) {

            .main-content {
                padding: 15px;
            }

            .content-card {
                padding: 18px;
            }

            .sidebar-menu {
                grid-template-columns: 1fr;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form .form-control {
                min-width: 0;
            }

            .search-form .btn {
                width: 100%;
            }

            .welcome-text {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-brand">
            Admin Panel
        </div>

        <nav class="sidebar-menu">

            <a href="dashboard.php">
                ◈ Dashboard
            </a>

            <a href="manage_gallery.php">
                ◈ Manage Gallery
            </a>

            <div class="sidebar-section">
                Administration
            </div>

            <a href="manage_admins.php">
                ◉ Manage Admins
            </a>

            <a href="manage_users.php" class="active">
                ◉ Manage Users
            </a>

            <a href="manage_customers.php">
                ◉ Manage Customers
            </a>

            <a href="manage_content.php">
                ◉ Manage Content
            </a>

            <a href="manage_settings.php">
                ⚙ Website Settings
            </a>

            <a href="activity_logs.php">
                ◷ Activity Logs
            </a>

            <div class="sidebar-section">
                My Account
            </div>

            <a href="profile.php">
                ◉ My Profile
            </a>

            <a href="change_password.php">
                🔒 Change Password
            </a>

        </nav>

        <form
            action="logout.php"
            method="POST"
            class="sidebar-logout"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo manageUsersEscape($_SESSION["csrf_token"]); ?>"
            >

            <button type="submit">
                Logout
            </button>

        </form>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main-content">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <h1>Manage Website Users</h1>

                <p>
                    Create, search and manage registered website users.
                </p>

            </div>

            <div class="welcome-text">

                Welcome,
                <strong>
                    <?php echo manageUsersEscape($_SESSION["admin_name"] ?? "Admin"); ?>
                </strong>

            </div>

        </div>

        <!-- SUCCESS AND ERROR MESSAGES -->

        <?php if ($message !== ""): ?>

            <div class="message <?php
                echo $messageType === "error" ? "error" : "success";
            ?>" role="alert">

                <?php echo manageUsersEscape($message); ?>

            </div>

        <?php endif; ?>

        <!-- CREATE NEW USER -->

        <section class="content-card">

            <div class="card-heading">

                <h2>Create New Website User</h2>

                <p>
                    Add a new user account to your website.
                </p>

            </div>

            <form method="POST" action="manage_users.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo manageUsersEscape($_SESSION["csrf_token"]); ?>"
                >

                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control"
                        maxlength="100"
                        placeholder="Enter full name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        maxlength="254"
                        placeholder="Enter email address"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="newUserPassword">
                        Password
                    </label>

                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="newUserPassword"
                            name="password"
                            class="form-control"
                            minlength="12"
                            maxlength="4096"
                            autocomplete="new-password"
                            placeholder="Enter password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            Show
                        </button>

                    </div>

                    <small class="form-help">
                        Password must contain at least 12 characters.
                    </small>

                </div>

                <button
                    type="submit"
                    name="create_user"
                    class="btn btn-primary"
                >
                    + Create User
                </button>

            </form>

        </section>

        <!-- ALL WEBSITE USERS -->

        <section class="content-card">

            <div class="card-heading">

                <h2>All Website Users</h2>

                <p>
                    View and manage all registered website user accounts.
                </p>

            </div>

            <!-- SEARCH FORM -->

            <form
                method="GET"
                action="manage_users.php"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    maxlength="100"
                    placeholder="Search by name or email"
                    value="<?php echo manageUsersEscape($search); ?>"
                >

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Search
                </button>

                <?php if ($search !== ""): ?>

                    <a
                        href="manage_users.php"
                        class="search-clear"
                    >
                        Clear Search
                    </a>

                <?php endif; ?>

            </form>

            <!-- USERS TABLE -->

            <div class="table-wrapper">

                <table class="users-table">

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($users) > 0): ?>

                            <?php foreach ($users as $user): ?>

                                <tr>

                                    <td class="user-id">
                                        <?php echo (int)$user["id"]; ?>
                                    </td>

                                    <td class="user-name">
                                        <?php echo manageUsersEscape($user["name"]); ?>
                                    </td>

                                    <td>
                                        <?php echo manageUsersEscape($user["email"]); ?>
                                    </td>

                                    <td>

                                        <span class="status-badge <?php
                                            echo $user["status"] === "active"
                                                ? "status-active"
                                                : "status-inactive";
                                        ?>">

                                            <?php echo manageUsersEscape($user["status"]); ?>

                                        </span>

                                    </td>

                                    <td>
                                        <?php echo manageUsersEscape($user["created_at"]); ?>
                                    </td>

                                    <td>

                                        <div class="action-buttons">

                                            <!-- EDIT USER -->

                                            <a
                                                href="actions/edit_user.php?id=<?php echo (int)$user["id"]; ?>"
                                                class="btn btn-secondary"
                                            >
                                                Edit
                                            </a>

                                            <!-- DELETE USER -->

                                            <form
                                                action="actions/delete_user.php"
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to permanently delete this website user? This action cannot be undone.');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo manageUsersEscape($_SESSION["csrf_token"]); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo (int)$user["id"]; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-danger"
                                                >
                                                    Delete
                                                </button>

                                            </form>

                                            <!-- ACTIVATE / DEACTIVATE USER -->

                                            <form
                                                action="actions/toggle_user_status.php"
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to change this user status?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo manageUsersEscape($_SESSION["csrf_token"]); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo (int)$user["id"]; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn <?php
                                                        echo $user["status"] === "active"
                                                            ? "btn-warning"
                                                            : "btn-success";
                                                    ?>"
                                                >

                                                    <?php
                                                    echo $user["status"] === "active"
                                                        ? "Deactivate"
                                                        : "Activate";
                                                    ?>

                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="6" class="empty-state">
                                    No website users found.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <!-- BACK TO DASHBOARD -->

        <div class="account-footer">

            <a href="dashboard.php">
                ← Back to Dashboard
            </a>

        </div>

    </main>

</div>

<!-- SHOW / HIDE PASSWORD -->

<script>

document.querySelectorAll(".toggle-password").forEach(function(button) {

    button.addEventListener("click", function() {

        const passwordInput = document.getElementById("newUserPassword");

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
