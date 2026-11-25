
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("manage_users");

// --------------------------------------------------
// HELPER FUNCTION
// --------------------------------------------------

function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// --------------------------------------------------
// CREATE CSRF TOKEN
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
                    error_log($e->getMessage());

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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Website Users | Admin Panel</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f6fb;
            font-family: Arial, Helvetica, sans-serif;
            color: #263247;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 250px;
            min-width: 250px;
            background: #17243a;
            color: #fff;
            padding: 25px 16px;
        }

        .sidebar-brand {
            font-size: 21px;
            font-weight: 700;
            padding: 0 12px 25px;
            border-bottom: 1px solid rgba(255,255,255,.12);
            margin-bottom: 20px;
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
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: background .2s, color .2s;
        }

        .sidebar-menu a:hover {
            background: #263954;
            color: #fff;
        }

        .sidebar-menu a.active {
            background: #315fbd;
            color: #fff;
            font-weight: 700;
        }

        .sidebar-section {
            color: #8292aa;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 19px 14px 7px;
            text-transform: uppercase;
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
        }

        .page-header h1 {
            margin: 0 0 7px;
            font-size: 27px;
            color: #1d2b43;
            font-weight: 700;
        }

        .page-header p {
            margin: 0;
            color: #718096;
            font-size: 14px;
        }

        .welcome-text {
            color: #718096;
            font-size: 13px;
            background: #fff;
            border: 1px solid #e5eaf1;
            border-radius: 8px;
            padding: 10px 14px;
        }

        .welcome-text strong {
            color: #263247;
        }

        /* CONTENT CARDS */

        .content-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 24px;
            border: 1px solid #e5eaf1;
            box-shadow: 0 3px 12px rgba(20, 35, 60, .035);
        }

        .card-heading {
            border-bottom: 1px solid #edf0f5;
            padding-bottom: 17px;
            margin-bottom: 23px;
        }

        .card-heading h2 {
            margin: 0 0 7px;
            font-size: 19px;
            color: #263247;
        }

        .card-heading p {
            margin: 0;
            color: #8290a3;
            font-size: 13px;
            line-height: 1.5;
        }

        /* MESSAGES */

        .message {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.5;
        }

        .message.success {
            background: #e8f7ee;
            color: #187444;
            border: 1px solid #c7ead5;
        }

        .message.error {
            background: #fff0f0;
            color: #b42323;
            border: 1px solid #f3cccc;
        }

        /* FORM */

        .form-group {
            margin-bottom: 20px;
            max-width: 650px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: #35445a;
            font-size: 14px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d8e0eb;
            border-radius: 8px;
            font-size: 14px;
            color: #263247;
            background: #fff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-control:focus {
            border-color: #4776d0;
            box-shadow: 0 0 0 3px rgba(71,118,208,.11);
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
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #315fbd;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            padding: 6px;
        }

        .toggle-password:hover {
            color: #244b99;
        }

        .form-help {
            display: block;
            margin-top: 7px;
            color: #8290a3;
            font-size: 12px;
        }

        /* BUTTONS */

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border: 1px solid transparent;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, border-color .2s, color .2s;
            text-align: center;
            white-space: nowrap;
        }

        .btn-primary {
            background: #315fbd;
            color: #fff;
        }

        .btn-primary:hover {
            background: #244b99;
        }

        .btn-secondary {
            background: #edf1f7;
            color: #35445a;
        }

        .btn-secondary:hover {
            background: #dfe6f0;
        }

        .btn-danger {
            background: #fff0f0;
            color: #b42323;
        }

        .btn-danger:hover {
            background: #ffe0e0;
        }

        .btn-success {
            background: #e8f7ee;
            color: #187444;
        }

        .btn-success:hover {
            background: #d5f0df;
        }

        .btn-warning {
            background: #fff5df;
            color: #936000;
        }

        .btn-warning:hover {
            background: #ffebc2;
        }

        /* SEARCH */

        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
            color: #315fbd;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .search-clear:hover {
            text-decoration: underline;
        }

        /* TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #edf0f5;
            border-radius: 9px;
        }

        .users-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .users-table th {
            background: #f5f7fb;
            color: #536176;
            padding: 14px 13px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            border-bottom: 1px solid #e6ebf2;
            white-space: nowrap;
        }

        .users-table td {
            padding: 15px 13px;
            border-bottom: 1px solid #edf0f5;
            color: #45546a;
            font-size: 13px;
            vertical-align: middle;
        }

        .users-table tbody tr:last-child td {
            border-bottom: none;
        }

        .users-table tbody tr:hover {
            background: #fafbfd;
        }

        .user-name {
            font-weight: 600;
            color: #263247;
        }

        .user-id {
            color: #8290a3;
            font-weight: 600;
        }

        /* STATUS BADGES */

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .status-active {
            background: #e8f7ee;
            color: #187444;
        }

        .status-inactive {
            background: #fff0f0;
            color: #b42323;
        }

        /* ACTIONS */

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .action-buttons form {
            margin: 0;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px !important;
            color: #8290a3 !important;
        }

        /* FOOTER */

        .account-footer {
            margin-top: 25px;
        }

        .account-footer a {
            color: #315fbd;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .account-footer a:hover {
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

            <?php
            require __DIR__ . "/includes/gallery_sidebar_link.php";
            ?>

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

            <a href="logout.php">
                ↪ Logout
            </a>

        </nav>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main-content">

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
                    <?php echo e($_SESSION["admin_name"] ?? "Admin"); ?>
                </strong>
            </div>

        </div>

        <!-- SUCCESS AND ERROR MESSAGES -->

        <?php if ($message !== ""): ?>

            <div class="message <?php echo e($messageType === "error" ? "error" : "success"); ?>">
                <?php echo e($message); ?>
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
                    value="<?php echo e($_SESSION["csrf_token"]); ?>"
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
                            data-target="newUserPassword"
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
                    value="<?php echo e($search); ?>"
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
                                        <?php echo (int) $user["id"]; ?>
                                    </td>

                                    <td class="user-name">
                                        <?php echo e($user["name"]); ?>
                                    </td>

                                    <td>
                                        <?php echo e($user["email"]); ?>
                                    </td>

                                    <td>

                                        <span class="status-badge <?php echo $user["status"] === "active" ? "status-active" : "status-inactive"; ?>">
                                            <?php echo e($user["status"]); ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?php echo e($user["created_at"]); ?>
                                    </td>

                                    <td>

                                        <div class="action-buttons">

                                            <!-- EDIT USER -->

                                            <a
                                                href="actions/edit_user.php?id=<?php echo (int) $user["id"]; ?>"
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
                                                    value="<?php echo e($_SESSION["csrf_token"]); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo (int) $user["id"]; ?>"
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
                                                    value="<?php echo e($_SESSION["csrf_token"]); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo (int) $user["id"]; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn <?php echo $user["status"] === "active" ? "btn-warning" : "btn-success"; ?>"
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

        <div class="account-footer">

            <a href="dashboard.php">
                ← Back to Dashboard
            </a>

        </div>

    </main>

</div>

<!-- SHOW / HIDE PASSWORD SCRIPT -->

<script>
document.querySelectorAll(".toggle-password").forEach(function(button) {

    button.addEventListener("click", function() {

        const targetId = this.getAttribute("data-target");
        const passwordInput = document.getElementById(targetId);

        if (!passwordInput) {
            return;
        }

        if (passwordInput.type === "password") {

            passwordInput.type = "text";
            this.textContent = "Hide";
            this.setAttribute("aria-label", "Hide password");
            this.setAttribute("aria-pressed", "true");

        } else {

            passwordInput.type = "password";
            this.textContent = "Show";
            this.setAttribute("aria-label", "Show password");
            this.setAttribute("aria-pressed", "false");

        }

    });

});
</script>

</body>
</html>
