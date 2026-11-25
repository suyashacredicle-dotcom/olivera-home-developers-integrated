
<?php

require_once "../../includes/permissions.php";
require_once "../../config/database.php";

requirePermission("manage_users");

$message = "";

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
// GET USER ID
// --------------------------------------------------

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit("Invalid user ID.");
}

// --------------------------------------------------
// CREATE CSRF TOKEN IF REQUIRED
// --------------------------------------------------

if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    $_SESSION["csrf_token"] === ""
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// --------------------------------------------------
// GET EXISTING USER
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT id, name, email, status
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    exit("User not found.");
}

// --------------------------------------------------
// UPDATE USER DETAILS
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF PROTECTION

    $submittedToken = $_POST["csrf_token"] ?? "";
    $sessionToken = $_SESSION["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        $sessionToken === "" ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(403);
        exit("Invalid request. Please reload the page.");
    }

    // GET SUBMITTED VALUES

    $name = $_POST["name"] ?? "";
    $email = $_POST["email"] ?? "";
    $status = $_POST["status"] ?? "";

    // VALIDATE INPUT TYPES

    if (
        !is_string($name) ||
        !is_string($email) ||
        !is_string($status)
    ) {

        $message = "Invalid user details.";

    } else {

        $name = trim($name);
        $email = strtolower(trim($email));

        // VALIDATE NAME

        if ($name === "") {

            $message = "Please enter the full name.";

        } elseif (strlen($name) > 100) {

            $message = "Name cannot exceed 100 characters.";

        // VALIDATE EMAIL

        } elseif (
            $email === "" ||
            strlen($email) > 254 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {

            $message = "Please enter a valid email address.";

        // VALIDATE STATUS

        } elseif (
            !in_array($status, ["active", "inactive"], true)
        ) {

            $message = "Please select a valid account status.";

        } else {

            try {

                // UPDATE USER

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET name = ?, email = ?, status = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $status,
                    $id
                ]);

                // ROTATE CSRF TOKEN

                $_SESSION["csrf_token"] =
                    bin2hex(random_bytes(32));

                // REDIRECT AFTER SUCCESS

                header("Location: ../manage_users.php");
                exit;

            } catch (PDOException $e) {

                if ($e->getCode() === "23000") {

                    $message =
                        "This email address is already registered.";

                } else {

                    error_log($e->getMessage());

                    $message =
                        "Something went wrong. Please try again.";
                }
            }
        }
    }

    // PRESERVE SUBMITTED VALUES AFTER VALIDATION ERROR

    if (
        is_string($name) &&
        is_string($email) &&
        is_string($status)
    ) {
        $user["name"] = $name;
        $user["email"] = $email;
        $user["status"] = $status;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Website User</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">

</head>

<body>

<div class="main-content">

    <h1>Edit Website User</h1>

    <?php if ($message !== ""): ?>

        <p style="color: red;">
            <?php echo e($message); ?>
        </p>

    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($_SESSION["csrf_token"]); ?>"
        >

        <label>Full Name</label>
        <br>

        <input
            type="text"
            name="name"
            maxlength="100"
            value="<?php echo e($user["name"]); ?>"
            required
        >

        <br><br>

        <label>Email Address</label>
        <br>

        <input
            type="email"
            name="email"
            maxlength="254"
            value="<?php echo e($user["email"]); ?>"
            required
        >

        <br><br>

        <label>Status</label>
        <br>

        <select name="status" required>

            <option value="active"
                <?php echo $user["status"] === "active" ? "selected" : ""; ?>>
                Active
            </option>

            <option value="inactive"
                <?php echo $user["status"] === "inactive" ? "selected" : ""; ?>>
                Inactive
            </option>

        </select>

        <br><br>

        <button type="submit">
            Update User
        </button>

    </form>

    <br>

    <a href="../manage_users.php">
        Back to Manage Users
    </a>

</div>

</body>
</html>
