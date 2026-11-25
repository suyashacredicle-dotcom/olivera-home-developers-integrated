
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

$message = "";
$messageType = "error";

// --------------------------------------------------
// HELPER FUNCTION
// --------------------------------------------------

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// --------------------------------------------------
// FETCH CUSTOMER
// --------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, status
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

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit("Unable to load your profile. Please try again later.");

}

// --------------------------------------------------
// UPDATE CUSTOMER PROFILE
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"]) ||
        !isset($_POST["csrf_token"]) ||
        !is_string($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {

        $message = "Invalid request. Please refresh the page and try again.";

    } else {

        $name = $_POST["name"] ?? null;
        $email = $_POST["email"] ?? null;
        $phone = $_POST["phone"] ?? null;

        if (
            !is_string($name) ||
            !is_string($email) ||
            !is_string($phone)
        ) {

            $message = "Invalid form information.";

        } else {

            $name = trim($name);
            $email = trim($email);
            $phone = trim($phone);

            if ($name === "" || $email === "" || $phone === "") {

                $message = "Please fill in all fields.";

            } elseif (strlen($name) > 100) {

                $message = "Name must not exceed 100 characters.";

            } elseif (
                strlen($email) > 254 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {

                $message = "Please enter a valid email address.";

            } elseif (
                strlen($phone) > 20 ||
                !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)
            ) {

                $message = "Please enter a valid phone number.";

            } else {

                try {

                    // Check whether another customer uses this email
                    $checkEmail = $pdo->prepare("
                        SELECT id
                        FROM customers
                        WHERE email = :email
                        AND id != :id
                        LIMIT 1
                    ");

                    $checkEmail->execute([
                        ":email" => $email,
                        ":id" => $customerId
                    ]);

                    if ($checkEmail->fetch()) {

                        $message = "This email address is already registered.";

                    } else {

                        // Update customer information
                        $update = $pdo->prepare("
                            UPDATE customers
                            SET
                                name = :name,
                                email = :email,
                                phone = :phone
                            WHERE id = :id
                            AND status = 'active'
                        ");

                        $update->execute([
                            ":name" => $name,
                            ":email" => $email,
                            ":phone" => $phone,
                            ":id" => $customerId
                        ]);

                        // Update session information
                        $_SESSION["customer_name"] = $name;
                        $_SESSION["customer_email"] = $email;

                        // Rotate CSRF token
                        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                        // Redirect after successful update
                        $_SESSION["customer_profile_message"] =
                            "Your profile has been updated successfully.";

                        header("Location: edit-profile.php?updated=1");
                        exit;
                    }

                } catch (PDOException $e) {

                    error_log($e->getMessage());

                    if ($e->getCode() === "23000") {
                        $message = "This email address is already registered.";
                    } else {
                        $message = "Unable to update your profile. Please try again.";
                    }

                } catch (Throwable $e) {

                    error_log($e->getMessage());

                    $message = "Unable to update your profile. Please try again.";

                }

            }
        }

        // Rotate token after a valid-CSRF submission
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        $csrfToken = $_SESSION["csrf_token"];
    }
}

// --------------------------------------------------
// SUCCESS MESSAGE
// --------------------------------------------------

if (
    isset($_GET["updated"]) &&
    $_GET["updated"] === "1" &&
    isset($_SESSION["customer_profile_message"])
) {

    $message = $_SESSION["customer_profile_message"];
    $messageType = "success";

    unset($_SESSION["customer_profile_message"]);
}

// Use submitted values when validation fails
$displayName = $_POST["name"] ?? $customer["name"];
$displayEmail = $_POST["email"] ?? $customer["email"];
$displayPhone = $_POST["phone"] ?? $customer["phone"];

if (!is_string($displayName)) {
    $displayName = $customer["name"];
}

if (!is_string($displayEmail)) {
    $displayEmail = $customer["email"];
}

if (!is_string($displayPhone)) {
    $displayPhone = $customer["phone"];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Customer Profile</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
        }

        .container {
            width: 100%;
            max-width: 480px;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        }

        h1 {
            text-align: center;
            margin-top: 0;
            color: #222;
        }

        .subtitle {
            text-align: center;
            color: #777;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .links {
            text-align: center;
            margin-top: 22px;
            font-size: 14px;
        }

        .links a {
            color: #2563eb;
            text-decoration: none;
            margin: 0 8px;
        }

    </style>

</head>

<body>

    <div class="container">

        <h1>Edit Profile</h1>

        <p class="subtitle">
            Update your customer account information
        </p>

        <?php if ($message !== ""): ?>

            <div class="message <?php echo e($messageType); ?>">
                <?php echo e($message); ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="edit-profile.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($csrfToken); ?>"
            >

            <div class="form-group">

                <label for="name">Full Name</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    autocomplete="name"
                    required
                    value="<?php echo e($displayName); ?>"
                >

            </div>

            <div class="form-group">

                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="254"
                    autocomplete="email"
                    required
                    value="<?php echo e($displayEmail); ?>"
                >

            </div>

            <div class="form-group">

                <label for="phone">Phone Number</label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    maxlength="20"
                    autocomplete="tel"
                    required
                    value="<?php echo e($displayPhone); ?>"
                >

            </div>

            <button type="submit">
                Save Changes
            </button>

        </form>

        <div class="links">

            <a href="my-account.php">My Account</a>

            <a href="index.php">Home</a>

        </div>

    </div>

</body>

</html>

