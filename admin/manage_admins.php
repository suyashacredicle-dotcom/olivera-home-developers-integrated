
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Admin Accounts | Olivera Admin</title>

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

        /* MAIN CONTENT */

        .main-content {
            width: calc(100% - 255px);
            min-height: 100vh;
            margin-left: 255px;
            padding: 28px;
        }

        .page-container {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
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

        /* FOOTER LINK */

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

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .main-content {
                width: calc(100% - 220px);
                margin-left: 220px;
                padding: 20px;
            }
        }

        @media (max-width: 700px) {

            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: auto;
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

<?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

<!-- MAIN CONTENT -->

<main class="main-content">

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

<!-- SHOW / HIDE PASSWORD -->

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
