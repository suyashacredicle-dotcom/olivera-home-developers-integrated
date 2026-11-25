
<?php

require_once "config/database.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --------------------------------------------------
// CUSTOMER LOGIN CHECK
// --------------------------------------------------

$customerId = (int) ($_SESSION["customer_id"] ?? 0);

if ($customerId <= 0) {
    header("Location: login.php");
    exit;
}

// --------------------------------------------------
// HELPER FUNCTION
// --------------------------------------------------

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// --------------------------------------------------
// FETCH CUSTOMER DETAILS
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, status, created_at
        FROM customers
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $customerId
    ]);

    $customer = $stmt->fetch();

    if (!$customer || $customer["status"] !== "active") {

        unset(
            $_SESSION["customer_id"],
            $_SESSION["customer_name"],
            $_SESSION["customer_email"]
        );

        header("Location: login.php");
        exit;
    }

    // Keep session information current
    $_SESSION["customer_name"] = $customer["name"];
    $_SESSION["customer_email"] = $customer["email"];

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit("Unable to load your account. Please try again later.");

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Account</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #333;
        }

        .navbar {
            background: #1e293b;
            color: white;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .navbar h2 {
            margin: 0;
            font-size: 21px;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 5px;
            background: #dc2626;
        }

        .container {
            max-width: 1000px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .welcome h1 {
            margin-bottom: 8px;
        }

        .welcome p {
            color: #666;
        }

        .account-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.06);
        }

        .account-card h2 {
            margin-top: 0;
            margin-bottom: 25px;
        }

        .detail {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .detail:last-child {
            border-bottom: none;
        }

        .detail strong {
            color: #555;
        }

        .status {
            color: #15803d;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .action-button {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            background: #2563eb;
        }

        .action-button:hover {
            background: #1d4ed8;
        }

        .footer {
            text-align: center;
            color: #777;
            margin: 35px 0;
            font-size: 13px;
        }

        @media (max-width: 600px) {

            .navbar {
                padding: 16px 20px;
            }

            .account-card {
                padding: 20px;
            }

            .detail {
                flex-direction: column;
                gap: 7px;
            }

        }

    </style>

</head>

<body>

    <div class="navbar">

        <h2>Customer Account</h2>

        <a href="logout.php">Logout</a>

    </div>

    <div class="container">

        <div class="welcome">

            <h1>
                Welcome, <?php echo e($customer["name"]); ?>!
            </h1>

            <p>
                Welcome to your customer dashboard.
            </p>

        </div>

        <div class="account-card">

            <h2>My Profile</h2>

            <div class="detail">

                <strong>Customer ID</strong>

                <span>
                    <?php echo (int) $customer["id"]; ?>
                </span>

            </div>

            <div class="detail">

                <strong>Full Name</strong>

                <span>
                    <?php echo e($customer["name"]); ?>
                </span>

            </div>

            <div class="detail">

                <strong>Email Address</strong>

                <span>
                    <?php echo e($customer["email"]); ?>
                </span>

            </div>

            <div class="detail">

                <strong>Phone Number</strong>

                <span>
                    <?php echo e($customer["phone"]); ?>
                </span>

            </div>

            <div class="detail">

                <strong>Account Status</strong>

                <span class="status">
                    <?php echo e(ucfirst($customer["status"])); ?>
                </span>

            </div>

            <div class="detail">

                <strong>Registration Date</strong>

                <span>
                    <?php
                    echo e(
                        date(
                            "d M Y",
                            strtotime($customer["created_at"])
                        )
                    );
                    ?>
                </span>

            </div>

            <div class="actions">

                <a href="edit-profile.php" class="action-button">
                    Edit Profile
                </a>

                <a href="change-customer-password.php" class="action-button">
                    Change Password
                </a>

            </div>

        </div>

        <div class="footer">
            Customer Dashboard
        </div>

    </div>

</body>

</html>
