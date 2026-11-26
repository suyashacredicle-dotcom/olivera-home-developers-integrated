
<?php

// --------------------------------------------------
// MANAGE CUSTOMERS
// --------------------------------------------------

require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/../config/database.php";

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
// ESCAPE OUTPUT
// --------------------------------------------------

function manageCustomersEscape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
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

    $messageType =
        $_SESSION["customer_admin_message_type"] ?? "success";

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

                $customer = $findCustomer->fetch(PDO::FETCH_ASSOC);

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

                error_log(
                    "Customer status update failed: " .
                    $exception->getMessage()
                );

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

// --------------------------------------------------
// FETCH CUSTOMERS
// --------------------------------------------------

$customers = [];

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

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $exception) {

    error_log(
        "Customer listing failed: " .
        $exception->getMessage()
    );

    $customers = [];

    $message = "Unable to load customer records.";
    $messageType = "error";
}

$totalCustomers = count($customers);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Customers | Olivera Admin</title>

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

        /* MAIN LAYOUT */

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* SIDEBAR */

        .sidebar {
            width: 245px;
            min-width: 245px;
            min-height: 100vh;
            padding: 25px 16px;
            background: var(--navy);
            color: white;
        }

        .sidebar h2 {
            margin: 0 0 28px;
            padding: 0 10px 19px;
            border-bottom: 1px solid rgba(255,255,255,.13);
            color: var(--gold-light);
            font-size: 20px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 6px;
        }

        .sidebar-menu a,
        .sidebar-logout-button {
            display: flex;
            align-items: center;
            gap: 11px;
            width: 100%;
            padding: 12px 13px;
            border: none;
            border-radius: 7px;
            background: transparent;
            color: #e2e8f0;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, color .2s;
        }

        .sidebar-menu a:hover,
        .sidebar-logout-button:hover {
            background: rgba(214,166,59,.16);
            color: var(--gold-light);
        }

        .sidebar-menu a.active {
            background: rgba(214,166,59,.16);
            color: var(--gold-light);
            border-left: 3px solid var(--gold);
            padding-left: 10px;
        }

        .menu-icon {
            width: 20px;
            flex-shrink: 0;
            text-align: center;
            font-size: 15px;
        }

        .sidebar-logout-form {
            margin-top: 22px;
            padding: 0 5px;
        }

        .sidebar-logout-button {
            justify-content: flex-start;
            border: 1px solid rgba(237,202,118,.5);
            color: var(--gold-light);
        }

        /* MAIN CONTENT */

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 28px;
        }

        .content-page {
            width: 100%;
            max-width: 1450px;
            margin: 0 auto;
            padding-bottom: 35px;
        }

        /* PAGE HEADER */

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 25px;
            padding: 26px 28px;
            border: 1px solid var(--navy-light);
            border-radius: 13px;
            background: linear-gradient(
                120deg,
                var(--navy),
                var(--navy-light)
            );
            color: white;
        }

        .content-heading {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .content-header-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border: 1px solid rgba(237,202,118,.4);
            border-radius: 12px;
            background: rgba(214,166,59,.15);
            color: var(--gold-light);
            font-size: 23px;
        }

        .content-header h1 {
            margin: 0 0 7px;
            color: white;
            font-size: 27px;
            font-weight: 750;
        }

        .content-header p {
            margin: 0;
            color: #d7dfeb;
            font-size: 13px;
            line-height: 1.6;
        }

        /* SUMMARY */

        .customer-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 17px;
            margin-bottom: 24px;
        }

        .summary-card {
            position: relative;
            overflow: hidden;
            padding: 22px 23px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
            box-shadow: 0 3px 12px rgba(7,27,56,.035);
        }

        .summary-card::before {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 4px;
            background: var(--gold);
            content: "";
        }

        .summary-label {
            margin-bottom: 10px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 650;
        }

        .summary-number {
            color: var(--navy);
            font-size: 31px;
            font-weight: 750;
            line-height: 1.2;
        }

        /* ALERTS */

        .customer-alert {
            margin-bottom: 22px;
            padding: 15px 18px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 650;
        }

        .customer-alert.success {
            border: 1px solid #c9e5cf;
            background: #edf7ee;
            color: #27653a;
        }

        .customer-alert.error {
            border: 1px solid #efcfca;
            background: #fbefed;
            color: #a33128;
        }

        /* PANELS */

        .customer-panel {
            overflow: hidden;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: var(--surface);
            box-shadow: 0 4px 15px rgba(7,27,56,.035);
        }

        .customer-panel-header {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 21px 25px;
            border-bottom: 1px solid var(--border);
            background: #faf8f2;
        }

        .customer-panel-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 39px;
            height: 39px;
            flex-shrink: 0;
            border-radius: 10px;
            background: rgba(214,166,59,.16);
            color: #94701e;
            font-size: 18px;
        }

        .customer-panel-header h2 {
            margin: 0 0 5px;
            color: var(--navy);
            font-size: 16px;
            font-weight: 750;
        }

        .customer-panel-header p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .customer-panel-body {
            padding: 25px;
        }

        /* SEARCH */

        .customer-search-form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .customer-search-form input {
            flex: 1;
            min-width: 220px;
            min-height: 43px;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            background: white;
            color: var(--text);
            font-family: inherit;
            font-size: 13px;
            transition: border-color .2s, box-shadow .2s;
        }

        .customer-search-form input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(214,166,59,.13);
        }

        .customer-search-form input::placeholder {
            color: #a0aaba;
        }

        .customer-search-button {
            min-height: 43px;
            padding: 10px 19px;
            border: 1px solid var(--navy);
            border-radius: 8px;
            background: var(--navy);
            color: white;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .customer-search-button:hover {
            background: var(--navy-light);
        }

        .customer-clear-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 43px;
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #faf8f2;
            color: var(--text);
            font-size: 12px;
            font-weight: 650;
            text-decoration: none;
            transition: .2s;
        }

        .customer-clear-button:hover {
            border-color: var(--gold);
            background: #f5f0e4;
        }

        /* CUSTOMER COUNT */

        .customer-count {
            margin: 0 0 15px;
            color: var(--muted);
            font-size: 12px;
        }

        .customer-count strong {
            color: var(--navy);
            font-size: 13px;
        }

        /* TABLE */

        .customer-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .customer-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        .customer-table th {
            padding: 16px 19px;
            border-bottom: 1px solid var(--border);
            background: #faf8f2;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: .55px;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .customer-table td {
            padding: 16px 19px;
            border-bottom: 1px solid #f0ede6;
            color: #475569;
            font-size: 13px;
            vertical-align: middle;
        }

        .customer-table tbody tr {
            transition: background .15s ease;
        }

        .customer-table tbody tr:hover {
            background: #fcfaf5;
        }

        .customer-table tbody tr:last-child td {
            border-bottom: none;
        }

        .customer-id {
            color: var(--muted) !important;
            font-weight: 700;
            white-space: nowrap;
        }

        .customer-name {
            color: var(--navy) !important;
            font-weight: 650;
        }

        .customer-email,
        .customer-phone {
            color: #526176;
        }

        .customer-date {
            color: var(--muted) !important;
            font-size: 12px !important;
            white-space: nowrap;
        }

        /* STATUS */

        .customer-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 750;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .customer-status.active {
            background: #edf7ee;
            color: #27653a;
        }

        .customer-status.inactive {
            background: #fbf3df;
            color: #896319;
        }

        /* ACTIONS */

        .customer-action-form {
            margin: 0;
        }

        .customer-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 33px;
            padding: 7px 13px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .customer-activate-button {
            border: 1px solid var(--gold);
            background: linear-gradient(
                120deg,
                var(--gold-light),
                var(--gold)
            );
            color: var(--navy);
        }

        .customer-activate-button:hover {
            filter: brightness(.96);
        }

        .customer-deactivate-button {
            border: 1px solid #e2b6ad;
            background: #fbefed;
            color: #a33128;
        }

        .customer-deactivate-button:hover {
            background: #f7e2df;
        }

        /* EMPTY STATE */

        .customer-empty {
            padding: 48px 20px !important;
            color: var(--muted) !important;
            line-height: 1.7;
            text-align: center;
        }

        .customer-empty strong {
            display: block;
            margin-bottom: 6px;
            color: var(--navy);
            font-size: 15px;
        }

        /* FOOTER */

        .page-footer {
            padding: 15px;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {

            .dashboard-container {
                display: block;
            }

            .sidebar {
                width: 100%;
                min-width: 0;
                min-height: auto;
            }

            .sidebar h2 {
                margin-bottom: 15px;
            }

            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .sidebar-menu li {
                margin: 0;
            }

            .sidebar-menu a {
                width: auto;
            }

            .sidebar-logout-form {
                max-width: 180px;
                margin-top: 12px;
            }

            .main-content {
                padding: 22px;
            }
        }

        @media (max-width: 760px) {

            .content-header {
                padding: 20px;
            }

            .content-header h1 {
                font-size: 23px;
            }

            .customer-panel-body {
                padding: 19px;
            }

            .customer-panel-header {
                padding: 18px;
            }
        }

        @media (max-width: 480px) {

            .main-content {
                padding: 15px;
            }

            .content-heading {
                gap: 10px;
            }

            .content-header-icon {
                width: 40px;
                height: 40px;
                font-size: 19px;
            }

            .customer-search-form {
                align-items: stretch;
                flex-direction: column;
            }

            .customer-search-form input,
            .customer-search-button,
            .customer-clear-button {
                width: 100%;
            }

            .customer-action-button {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="dashboard-container">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <h2>Olivera Admin</h2>

        <ul class="sidebar-menu">

            <li>
                <a href="dashboard.php">
                    <span class="menu-icon">▦</span>
                    Dashboard
                </a>
            </li>

            <li>
                <a href="manage_gallery.php">
                    <span class="menu-icon">▧</span>
                    Manage Gallery
                </a>
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

        </ul>

        <form
            action="logout.php"
            method="POST"
            class="sidebar-logout-form"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo manageCustomersEscape($csrfToken); ?>"
            >

            <button type="submit" class="sidebar-logout-button">
                <span class="menu-icon">↪</span>
                Logout
            </button>

        </form>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="content-page">

            <!-- PAGE HEADER -->

            <div class="content-header">

                <div class="content-heading">

                    <div class="content-header-icon">♧</div>

                    <div>

                        <h1>Manage Customers</h1>

                        <p>
                            View customer registrations and manage customer account status.
                        </p>

                    </div>

                </div>

            </div>

            <!-- ALERT -->

            <?php if ($message !== ""): ?>

                <div
                    class="customer-alert <?php
                        echo $messageType === "success"
                            ? "success"
                            : "error";
                    ?>"
                    role="alert"
                >
                    <?php echo manageCustomersEscape($message); ?>
                </div>

            <?php endif; ?>

            <!-- SUMMARY -->

            <div class="customer-summary">

                <div class="summary-card">

                    <div class="summary-label">
                        Matching Customer Records
                    </div>

                    <div class="summary-number">
                        <?php echo (int) $totalCustomers; ?>
                    </div>

                </div>

            </div>

            <!-- SEARCH CUSTOMERS -->

            <section class="customer-panel">

                <div class="customer-panel-header">

                    <div class="customer-panel-icon">⌕</div>

                    <div>

                        <h2>Search Customers</h2>

                        <p>
                            Search customers using their name, email address or phone number.
                        </p>

                    </div>

                </div>

                <div class="customer-panel-body">

                    <form
                        method="GET"
                        action="manage_customers.php"
                        class="customer-search-form"
                    >

                        <input
                            type="text"
                            name="q"
                            maxlength="100"
                            placeholder="Search by name, email or phone..."
                            value="<?php echo manageCustomersEscape($search); ?>"
                        >

                        <button
                            type="submit"
                            class="customer-search-button"
                        >
                            Search
                        </button>

                        <a
                            href="manage_customers.php"
                            class="customer-clear-button"
                        >
                            Clear Search
                        </a>

                    </form>

                </div>

            </section>

            <!-- CUSTOMER LIST -->

            <section class="customer-panel">

                <div class="customer-panel-header">

                    <div class="customer-panel-icon">▦</div>

                    <div>

                        <h2>Customer List</h2>

                        <p>
                            View registered customers and manage their account status.
                        </p>

                    </div>

                </div>

                <div class="customer-panel-body">

                    <p class="customer-count">

                        Showing
                        <strong><?php echo (int) $totalCustomers; ?></strong>
                        customer record(s).

                        <?php if ($totalCustomers === 200): ?>
                            The list is limited to the latest 200 records.
                        <?php endif; ?>

                    </p>

                    <div class="customer-table-wrapper">

                        <table class="customer-table">

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

                                        <td colspan="7" class="customer-empty">

                                            <strong>No Customers Found</strong>

                                            <?php if ($search !== ""): ?>

                                                No customers match your search.

                                            <?php else: ?>

                                                No customer records are available.

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($customers as $customer): ?>

                                        <tr>

                                            <td class="customer-id">
                                                #<?php echo (int) $customer["id"]; ?>
                                            </td>

                                            <td class="customer-name">
                                                <?php echo manageCustomersEscape($customer["name"]); ?>
                                            </td>

                                            <td class="customer-email">
                                                <?php echo manageCustomersEscape($customer["email"]); ?>
                                            </td>

                                            <td class="customer-phone">
                                                <?php echo manageCustomersEscape($customer["phone"]); ?>
                                            </td>

                                            <td>

                                                <span class="customer-status <?php
                                                    echo $customer["status"] === "active"
                                                        ? "active"
                                                        : "inactive";
                                                ?>">

                                                    <?php
                                                    echo manageCustomersEscape(
                                                        ucfirst((string) $customer["status"])
                                                    );
                                                    ?>

                                                </span>

                                            </td>

                                            <td class="customer-date">

                                                <?php

                                                $registrationDate = strtotime(
                                                    (string) $customer["created_at"]
                                                );

                                                echo $registrationDate
                                                    ? manageCustomersEscape(
                                                        date("d M Y", $registrationDate)
                                                    )
                                                    : "N/A";

                                                ?>

                                            </td>

                                            <td>

                                                <form
                                                    method="POST"
                                                    action="manage_customers.php"
                                                    class="customer-action-form"
                                                    onsubmit="return confirm('Are you sure you want to change this customer account status?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?php echo manageCustomersEscape($csrfToken); ?>"
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
                                                            class="customer-action-button customer-deactivate-button"
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
                                                            class="customer-action-button customer-activate-button"
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

                </div>

            </section>

            <!-- FOOTER -->

            <div class="page-footer">
                Olivera Home Developers | Admin Control Panel
            </div>

        </div>

    </main>

</div>

</body>
</html>
