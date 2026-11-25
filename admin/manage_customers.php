
<?php

require_once "../includes/permissions.php";
require_once "../config/database.php";

requirePermission("manage_users");

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

$csrfToken = $_SESSION["csrf_token"];

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
// FLASH MESSAGE
// --------------------------------------------------

$message = "";
$messageType = "success";

if (isset($_SESSION["customer_admin_message"])) {

    $message = $_SESSION["customer_admin_message"];
    $messageType = $_SESSION["customer_admin_message_type"] ?? "success";

    unset(
        $_SESSION["customer_admin_message"],
        $_SESSION["customer_admin_message_type"]
    );
}

// --------------------------------------------------
// ACTIVATE / DEACTIVATE CUSTOMER
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"]) ||
        !isset($_POST["csrf_token"]) ||
        !is_string($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {

        $_SESSION["customer_admin_message"] =
            "Invalid request. Please refresh the page and try again.";

        $_SESSION["customer_admin_message_type"] = "error";

    } else {

        $customerId = $_POST["customer_id"] ?? null;
        $action = $_POST["action"] ?? null;

        if (
            !is_string($customerId) ||
            !ctype_digit($customerId) ||
            (int) $customerId <= 0
        ) {

            $_SESSION["customer_admin_message"] =
                "Invalid customer ID.";

            $_SESSION["customer_admin_message_type"] = "error";

        } elseif (
            !is_string($action) ||
            !in_array($action, ["activate", "deactivate"], true)
        ) {

            $_SESSION["customer_admin_message"] =
                "Invalid customer action.";

            $_SESSION["customer_admin_message_type"] = "error";

        } else {

            try {

                $pdo->beginTransaction();

                $findCustomer = $pdo->prepare("
                    SELECT id, name, status
                    FROM customers
                    WHERE id = :id
                    LIMIT 1
                    FOR UPDATE
                ");

                $findCustomer->execute([
                    ":id" => (int) $customerId
                ]);

                $customer = $findCustomer->fetch();

                if (!$customer) {
                    throw new RuntimeException("Customer not found.");
                }

                $newStatus = ($action === "activate")
                    ? "active"
                    : "inactive";

                $updateCustomer = $pdo->prepare("
                    UPDATE customers
                    SET status = :status
                    WHERE id = :id
                ");

                $updateCustomer->execute([
                    ":status" => $newStatus,
                    ":id" => (int) $customerId
                ]);

                $pdo->commit();

                $_SESSION["customer_admin_message"] =
                    "Customer account " . $newStatus . " successfully.";

                $_SESSION["customer_admin_message_type"] = "success";

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($exception->getMessage());

                $_SESSION["customer_admin_message"] =
                    "Unable to update customer account. Please try again.";

                $_SESSION["customer_admin_message_type"] = "error";
            }
        }

        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    header("Location: manage_customers.php");
    exit;
}

// --------------------------------------------------
// SEARCH CUSTOMERS
// --------------------------------------------------

$search = $_GET["q"] ?? "";

if (!is_string($search)) {
    $search = "";
}

$search = trim($search);

if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

try {

    if ($search !== "") {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                email,
                phone,
                status,
                created_at
            FROM customers
            WHERE
                name LIKE :name_search
                OR email LIKE :email_search
                OR phone LIKE :phone_search
            ORDER BY id DESC
            LIMIT 200
        ");

        $searchValue = "%" . $search . "%";

        $stmt->execute([
            ":name_search" => $searchValue,
            ":email_search" => $searchValue,
            ":phone_search" => $searchValue
        ]);

    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                email,
                phone,
                status,
                created_at
            FROM customers
            ORDER BY id DESC
            LIMIT 200
        ");

        $stmt->execute();
    }

    $customers = $stmt->fetchAll();

} catch (Throwable $exception) {

    error_log($exception->getMessage());

    $customers = [];

    $message = "Unable to load customer records.";
    $messageType = "error";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Customers | Olivera Home Developers</title>

    <style>

        :root {
            --navy: #172b46;
            --navy-light: #223c5d;
            --primary: #315f91;
            --primary-light: #eaf1f8;
            --background: #f5f7fa;
            --surface: #ffffff;
            --text: #263445;
            --muted: #78879a;
            --border: #e4eaf1;
            --success: #25845b;
            --danger: #bd5555;
            --shadow: 0 3px 14px rgba(30, 48, 70, 0.055);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.5;
        }

        /* SIDEBAR */

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--navy);
            color: white;
            padding: 25px 15px;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-brand {
            padding: 0 12px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.13);
            margin-bottom: 23px;
        }

        .sidebar-brand h2 {
            font-size: 20px;
            margin: 0 0 6px;
            color: white;
            letter-spacing: 0.1px;
        }

        .sidebar-brand p {
            margin: 0;
            font-size: 11px;
            color: #bdcde0;
            letter-spacing: 0.3px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 6px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 7px;
            color: #dbe5f0;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: var(--navy-light);
            color: white;
        }

        .sidebar-menu a.active {
            background: #315f91;
            color: white;
            font-weight: 600;
        }

        .menu-icon {
            width: 20px;
            text-align: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        /* MAIN CONTENT */

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .content-container {
            max-width: 1500px;
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .topbar h1 {
            margin: 0 0 6px;
            font-size: 26px;
            color: #25384e;
            letter-spacing: -0.4px;
        }

        .topbar p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .admin-label {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            color: #53657a;
            font-size: 12px;
            border: 1px solid var(--border);
        }

        /* CARDS */

        .card {
            background: var(--surface);
            border-radius: 10px;
            padding: 23px;
            margin-bottom: 21px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 19px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 17px;
            color: #2d4056;
        }

        .card-description {
            color: var(--muted);
            font-size: 12px;
            margin: 6px 0 0;
        }

        /* MESSAGES */

        .message {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .message.success {
            background: #edf7f1;
            color: #256746;
            border: 1px solid #d6ecdf;
        }

        .message.error {
            background: #fff0ef;
            color: #a83d3d;
            border: 1px solid #f2d6d3;
        }

        /* SEARCH */

        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-form input {
            flex: 1;
            min-width: 220px;
            padding: 11px 13px;
            border: 1px solid #dce3eb;
            border-radius: 7px;
            font-size: 13px;
            color: var(--text);
            background: #fff;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .search-form input:focus {
            border-color: #7399bf;
            box-shadow: 0 0 0 3px rgba(49, 95, 145, 0.09);
        }

        .search-form input::placeholder {
            color: #a0acba;
        }

        /* BUTTONS */

        button,
        .button {
            border: none;
            border-radius: 7px;
            padding: 10px 15px;
            cursor: pointer;
            color: white;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        button:hover,
        .button:hover {
            transform: translateY(-1px);
        }

        .search-button {
            background: var(--primary);
        }

        .search-button:hover {
            background: #244a73;
        }

        .clear-button {
            background: #68788b;
        }

        .clear-button:hover {
            background: #526174;
        }

        .activate-button {
            background: #32815f;
        }

        .activate-button:hover {
            background: #256849;
        }

        .deactivate-button {
            background: #bd5555;
        }

        .deactivate-button:hover {
            background: #a44040;
        }

        /* CUSTOMER COUNT */

        .count {
            color: var(--muted);
            font-size: 12px;
            margin: 0 0 15px;
        }

        .count strong {
            color: #34485f;
            font-size: 13px;
        }

        /* TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 900px;
            background: #fff;
        }

        th {
            background: #f5f7fa;
            color: #56677b;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            font-weight: 700;
            padding: 14px 13px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        td {
            padding: 14px 13px;
            font-size: 12px;
            color: #526176;
            border-bottom: 1px solid #edf0f4;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fafbfd;
        }

        .customer-name {
            font-weight: 600;
            color: #34485f;
        }

        /* STATUS */

        .status {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status.active {
            background: #eaf6ef;
            color: #28764d;
        }

        .status.inactive {
            background: #fff0ef;
            color: #b44747;
        }

        .action-form {
            display: inline-block;
            margin: 0;
        }

        .empty {
            text-align: center;
            padding: 35px 20px;
            color: #8491a1;
            font-size: 13px;
        }

        /* FOOTER */

        .page-footer {
            text-align: center;
            color: #98a4b2;
            font-size: 11px;
            padding: 12px;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .main-content {
                padding: 23px;
            }

            .card {
                padding: 19px;
            }
        }

        @media (max-width: 650px) {

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 17px;
            }

            .sidebar-brand {
                margin-bottom: 14px;
                padding-bottom: 14px;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }

            .sidebar-menu li {
                margin: 0;
            }

            .sidebar-menu a {
                padding: 10px 8px;
                font-size: 11px;
                gap: 7px;
            }

            .main-content {
                margin-left: 0;
                padding: 17px;
            }

            .topbar {
                align-items: flex-start;
            }

            .topbar h1 {
                font-size: 22px;
            }

            .card {
                padding: 16px;
                border-radius: 9px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form input,
            .search-form button,
            .search-form a {
                width: 100%;
            }

            .admin-label {
                font-size: 11px;
            }
        }

    </style>

</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="sidebar-brand">

        <h2>My Website</h2>

        <p>Admin Control Panel</p>

    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="dashboard.php">
                <span class="menu-icon">▦</span>
                Dashboard
            </a>
        </li>

        <!-- ROLE-BASED GALLERY LINK -->

        <li>
            <?php
            require __DIR__ . "/includes/gallery_sidebar_link.php";
            ?>
        </li>

        <li>
            <a href="manage_admins.php">
                <span class="menu-icon">♙</span>
                Manage Admins
            </a>
        </li>

        <li>
            <a href="manage_users.php">
                <span class="menu-icon">♧</span>
                Manage Users
            </a>
        </li>

        <li>
            <a href="manage_customers.php" class="active">
                <span class="menu-icon">♧</span>
                Manage Customers
            </a>
        </li>

        <li>
            <a href="manage_content.php">
                <span class="menu-icon">▤</span>
                Manage Content
            </a>
        </li>

        <li>
            <a href="manage_settings.php">
                <span class="menu-icon">⚙</span>
                Website Settings
            </a>
        </li>

        <li>
            <a href="activity_logs.php">
                <span class="menu-icon">◷</span>
                Activity Logs
            </a>
        </li>

        <li>
            <a href="profile.php">
                <span class="menu-icon">♙</span>
                My Profile
            </a>
        </li>

        <li>
            <a href="change_password.php">
                <span class="menu-icon">🔒</span>
                Change Password
            </a>
        </li>

        <li>
            <a href="logout.php">
                <span class="menu-icon">↪</span>
                Logout
            </a>
        </li>

    </ul>

</aside>

<!-- MAIN CONTENT -->

<main class="main-content">

    <div class="content-container">

        <div class="topbar">

            <div>

                <h1>Manage Customers</h1>

                <p>
                    View customer registrations and manage customer account status.
                </p>

            </div>

            <div class="admin-label">
                Admin Panel
            </div>

        </div>

        <?php if ($message !== ""): ?>

            <div class="message <?php echo e($messageType === "error" ? "error" : "success"); ?>">
                <?php echo e($message); ?>
            </div>

        <?php endif; ?>

        <!-- SEARCH CUSTOMERS -->

        <section class="card">

            <div class="card-header">

                <div>

                    <h2>Search Customers</h2>

                    <p class="card-description">
                        Search customers using their name, email address or phone number.
                    </p>

                </div>

            </div>

            <form
                method="GET"
                action="manage_customers.php"
                class="search-form"
            >

                <input
                    type="text"
                    name="q"
                    maxlength="100"
                    placeholder="Search by name, email or phone..."
                    value="<?php echo e($search); ?>"
                >

                <button type="submit" class="search-button">
                    Search
                </button>

                <a
                    href="manage_customers.php"
                    class="button clear-button"
                >
                    Clear
                </a>

            </form>

        </section>

        <!-- CUSTOMER LIST -->

        <section class="card">

            <div class="card-header">

                <div>

                    <h2>Customer List</h2>

                    <p class="card-description">
                        View registered customers and manage their account status.
                    </p>

                </div>

            </div>

            <p class="count">

                Showing <strong><?php echo count($customers); ?></strong>
                customer record(s).

                <?php if (count($customers) === 200): ?>

                    The list is limited to the latest 200 records.

                <?php endif; ?>

            </p>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Email Address</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Registration Date</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($customers)): ?>

                            <tr>

                                <td colspan="7" class="empty">
                                    No customers found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($customers as $customer): ?>

                                <tr>

                                    <td>
                                        <?php echo (int) $customer["id"]; ?>
                                    </td>

                                    <td class="customer-name">
                                        <?php echo e($customer["name"]); ?>
                                    </td>

                                    <td>
                                        <?php echo e($customer["email"]); ?>
                                    </td>

                                    <td>
                                        <?php echo e($customer["phone"]); ?>
                                    </td>

                                    <td>

                                        <span class="status <?php echo e($customer["status"] === "active" ? "active" : "inactive"); ?>">

                                            <?php echo e(ucfirst($customer["status"])); ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?php

                                        $registrationDate = strtotime(
                                            $customer["created_at"]
                                        );

                                        echo $registrationDate
                                            ? e(date("d M Y", $registrationDate))
                                            : "N/A";

                                        ?>

                                    </td>

                                    <td>

                                        <form
                                            method="POST"
                                            action="manage_customers.php"
                                            class="action-form"
                                            onsubmit="return confirm('Are you sure you want to change this customer account status?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo e($csrfToken); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="customer_id"
                                                value="<?php echo (int) $customer["id"]; ?>"
                                            >

                                            <?php if ($customer["status"] === "active"): ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="deactivate"
                                                >

                                                <button
                                                    type="submit"
                                                    class="deactivate-button"
                                                >
                                                    Deactivate
                                                </button>

                                            <?php else: ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="activate"
                                                >

                                                <button
                                                    type="submit"
                                                    class="activate-button"
                                                >
                                                    Activate
                                                </button>

                                            <?php endif; ?>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <div class="page-footer">
            Olivera Home Developers | Admin Control Panel
        </div>

    </div>

</main>

</body>
</html>
