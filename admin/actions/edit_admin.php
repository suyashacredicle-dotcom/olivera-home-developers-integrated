
<?php

require_once "../../includes/permissions.php";
require_once "../../config/database.php";
require_once "../../includes/activity_logger.php";

requirePermission("manage_admins");

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
// GET ACCOUNT ID
// --------------------------------------------------

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit("Invalid account ID.");
}

// --------------------------------------------------
// VALIDATE CURRENT ADMIN ID
// --------------------------------------------------

$currentAdminId = filter_var(
    $_SESSION["admin_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$currentAdminId || $currentAdminId <= 0) {
    http_response_code(403);
    exit("Invalid admin session.");
}

// --------------------------------------------------
// FIND ACCOUNT
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT id, name, email, role, status
    FROM admins
    WHERE id = ?
    AND role IN ('admin', 'editor', 'sales_employee')
    LIMIT 1
");

$stmt->execute([$id]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    http_response_code(404);
    exit("Account not found or cannot be edited.");
}

$message = "";
$messageType = "";

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
// UPDATE ACCOUNT
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

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

    $name = $_POST["name"] ?? "";
    $email = $_POST["email"] ?? "";
    $role = $_POST["role"] ?? "";
    $status = $_POST["status"] ?? "";

    if (
        !is_string($name) ||
        !is_string($email) ||
        !is_string($role) ||
        !is_string($status)
    ) {

        $message = "Invalid account details.";
        $messageType = "error";

    } else {

        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === "" || strlen($name) > 100) {

            $message = "Enter a valid name. Maximum 100 characters.";
            $messageType = "error";

        } elseif (
            $email === "" ||
            strlen($email) > 254 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {

            $message = "Please enter a valid email address.";
            $messageType = "error";

        } elseif (
            !in_array(
                $role,
                ["admin", "editor", "sales_employee"],
                true
            )
        ) {

            $message = "Please select a valid role.";
            $messageType = "error";

        } elseif (
            !in_array($status, ["active", "inactive"], true)
        ) {

            $message = "Please select a valid account status.";
            $messageType = "error";

        } elseif (
            (int) $id === (int) $currentAdminId &&
            (
                $role !== $_SESSION["admin_role"] ||
                $status !== "active"
            )
        ) {

            $message =
                "You cannot change your own role or deactivate your own account.";

            $messageType = "error";

        } else {

            try {

                $pdo->beginTransaction();

                // Lock the account before updating it.

                $lock = $pdo->prepare("
                    SELECT id, name, email, role, status
                    FROM admins
                    WHERE id = ?
                    AND role IN ('admin', 'editor', 'sales_employee')
                    LIMIT 1
                    FOR UPDATE
                ");

                $lock->execute([$id]);

                $lockedAdmin = $lock->fetch(PDO::FETCH_ASSOC);

                if (!$lockedAdmin) {

                    $pdo->rollBack();

                    $message =
                        "Account not found or cannot be edited.";

                    $messageType = "error";

                } elseif (
                    (int) $id === (int) $currentAdminId &&
                    (
                        $role !== $lockedAdmin["role"] ||
                        $status !== "active"
                    )
                ) {

                    $pdo->rollBack();

                    $message =
                        "You cannot change your own role or deactivate your own account.";

                    $messageType = "error";

                } else {

                    // Update only permitted account roles.

                    $update = $pdo->prepare("
                        UPDATE admins
                        SET name = ?, email = ?, role = ?, status = ?
                        WHERE id = ?
                        AND role IN ('admin', 'editor', 'sales_employee')
                    ");

                    $update->execute([
                        $name,
                        $email,
                        $role,
                        $status,
                        $id
                    ]);

                    if ($update->rowCount() > 0) {

                        logActivity(
                            $pdo,
                            "ADMIN_UPDATED",
                            "Updated account ID " . $id .
                            ". Name: " . $name .
                            ", Email: " . $email .
                            ", Role: " . $role .
                            ", Status: " . $status . "."
                        );

                    }

                    $pdo->commit();

                    $_SESSION["csrf_token"] =
                        bin2hex(random_bytes(32));

                    $_SESSION["admin_message"] =
                        "Account updated successfully!";

                    $_SESSION["admin_message_type"] = "success";

                    header("Location: ../manage_admins.php");
                    exit;
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($e->getMessage());

                if (
                    $e instanceof PDOException &&
                    $e->getCode() === "23000"
                ) {

                    $message =
                        "This email address is already registered.";

                } else {

                    $message =
                        "Something went wrong. Please try again.";
                }

                $messageType = "error";
            }
        }
    }

    // Preserve submitted values after validation errors.

    $admin["name"] = $name;
    $admin["email"] = $email;
    $admin["role"] = $role;
    $admin["status"] = $status;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Account</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">

</head>

<body>

<div class="main-content">

    <h1>Edit Account</h1>

    <?php if ($message !== ""): ?>

        <p style="color: <?php echo $messageType === "error" ? "red" : "green"; ?>;">
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
            value="<?php echo e($admin["name"]); ?>"
            required
        >

        <br><br>

        <label>Email Address</label>
        <br>

        <input
            type="email"
            name="email"
            maxlength="254"
            value="<?php echo e($admin["email"]); ?>"
            required
        >

        <br><br>

        <label>Role</label>
        <br>

        <select name="role" required>

            <option value="admin"
                <?php echo $admin["role"] === "admin" ? "selected" : ""; ?>>
                Admin
            </option>

            <option value="editor"
                <?php echo $admin["role"] === "editor" ? "selected" : ""; ?>>
                Editor
            </option>

            <option value="sales_employee"
                <?php echo $admin["role"] === "sales_employee" ? "selected" : ""; ?>>
                Sales Employee
            </option>

        </select>

        <br><br>

        <label>Account Status</label>
        <br>

        <select name="status" required>

            <option value="active"
                <?php echo $admin["status"] === "active" ? "selected" : ""; ?>>
                Active
            </option>

            <option value="inactive"
                <?php echo $admin["status"] === "inactive" ? "selected" : ""; ?>>
                Inactive
            </option>

        </select>

        <br><br>

        <button type="submit" class="btn">
            Save Changes
        </button>

    </form>

    <br>

    <a href="../manage_admins.php">Back to Manage Accounts</a>

</div>

</body>
</html>
