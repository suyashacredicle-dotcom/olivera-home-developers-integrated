
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";
require_once "../includes/activity_logger.php";

requirePermission("manage_admins");

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

            } catch (RuntimeException $e) {
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
// ESCAPE OUTPUT
// --------------------------------------------------

function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Admin Accounts | Admin Panel</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f6fb;
            color: #263247;
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-content {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 32px;
            min-height: 100vh;
        }

        /* PAGE HEADER */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 27px;
            font-weight: 700;
            color: #1d2b43;
        }

        .page-header p {
            margin: 0;
            color: #718096;
            font-size: 14px;
            line-height: 1.5;
        }

        .welcome-text {
            padding: 11px 16px;
            background: #ffffff;
            color: #64748b;
            border: 1px solid #e5eaf1;
            border-radius: 9px;
            font-size: 13px;
        }

        .welcome-text strong {
            color: #263247;
        }

        /* CONTENT CARD */

        .content-card {
            background: #ffffff;
            border: 1px solid #e5eaf1;
            border-radius: 12px;
            padding: 27px;
            margin-bottom: 24px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .section-heading {
            margin: 0 0 7px;
            color: #1d2b43;
            font-size: 20px;
            font-weight: 700;
        }

        .section-description {
            margin: 0 0 24px;
            color: #8290a3;
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
            color: #35445a;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            border: 1px solid #dce3ed;
            border-radius: 8px;
            background: #ffffff;
            color: #26364a;
            font-family: inherit;
            font-size: 14px;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3976df;
            box-shadow: 0 0 0 3px rgba(57, 118, 223, 0.10);
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
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #315fbd;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            padding: 7px;
        }

        .toggle-password:hover {
            color: #244b99;
        }

        .password-help {
            margin-top: 11px;
            padding: 14px 16px;
            background: #f7f9fc;
            border: 1px solid #e8edf4;
            border-radius: 8px;
            color: #718096;
            font-size: 12px;
            line-height: 1.8;
        }

        .password-help strong {
            color: #34445b;
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
            border: 1px solid #315fbd;
            border-radius: 8px;
            background: #315fbd;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, border-color .2s;
        }

        .primary-button:hover {
            background: #244b99;
            border-color: #244b99;
        }

        /* MESSAGES */

        .message {
            padding: 14px 17px;
            margin-bottom: 22px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
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

        /* ALL ACCOUNTS */

        .all-accounts-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            padding: 23px;
            background: #f8fafd;
            border: 1px solid #e6ebf2;
            border-radius: 10px;
        }

        .all-accounts-card h2 {
            margin: 0 0 7px;
            color: #1d2b43;
            font-size: 18px;
        }

        .all-accounts-card p {
            margin: 0;
            max-width: 650px;
            color: #718096;
            font-size: 13px;
            line-height: 1.6;
        }

        /* FOOTER */

        .bottom-link {
            display: inline-block;
            margin-top: 4px;
            color: #315fbd;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .bottom-link:hover {
            text-decoration: underline;
        }

        /* RESPONSIVE */

        @media (max-width: 700px) {

            .main-content {
                padding: 22px;
            }

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

            .page-header h1 {
                font-size: 23px;
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

<div class="main-content">

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
                <?php echo e($_SESSION["admin_name"] ?? "Admin"); ?>
            </strong>

        </div>

    </div>

    <!-- SUCCESS AND ERROR MESSAGES -->

    <?php if ($message !== ""): ?>

        <div
            class="message <?php echo $messageType === "error" ? "message-error" : "message-success"; ?>"
            role="alert"
        >
            <?php echo e($message); ?>
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
                value="<?php echo e($_SESSION["csrf_token"]); ?>"
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
