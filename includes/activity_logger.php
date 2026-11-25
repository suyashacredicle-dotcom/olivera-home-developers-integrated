
<?php

// --------------------------------------------------
// SECURE ADMIN ACTIVITY LOGGER
// --------------------------------------------------

function logActivity($pdo, $action, $description = "")
{
    try {

        // --------------------------------------------------
        // VALIDATE DATABASE CONNECTION
        // --------------------------------------------------

        if (!$pdo instanceof PDO) {
            error_log("Activity logging failed: Invalid database connection.");
            return false;
        }

        // --------------------------------------------------
        // VALIDATE ACTIVITY ACTION
        // --------------------------------------------------

        if (
            !is_string($action) ||
            trim($action) === ""
        ) {
            error_log("Activity logging failed: Invalid action.");
            return false;
        }

        // --------------------------------------------------
        // VALIDATE DESCRIPTION
        // --------------------------------------------------

        if (!is_string($description)) {
            error_log("Activity logging failed: Invalid description.");
            return false;
        }

        $action = trim($action);

        // Keep values within reasonable limits.
        $action = substr($action, 0, 100);
        $description = substr($description, 0, 2000);

        // --------------------------------------------------
        // GET LOGGED-IN ADMIN ID
        // --------------------------------------------------

        $adminId = null;

        if (
            session_status() === PHP_SESSION_ACTIVE &&
            isset($_SESSION["admin_id"]) &&
            filter_var(
                $_SESSION["admin_id"],
                FILTER_VALIDATE_INT
            ) !== false &&
            (int) $_SESSION["admin_id"] > 0
        ) {
            $adminId = (int) $_SESSION["admin_id"];
        }

        // --------------------------------------------------
        // INSERT ACTIVITY LOG
        // --------------------------------------------------

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs
                (admin_id, action, description)
            VALUES
                (?, ?, ?)
        ");

        $stmt->execute([
            $adminId,
            $action,
            $description
        ]);

        return true;

    } catch (Throwable $e) {

        // Record technical details privately.
        error_log(
            "Activity logging failed: " .
            $e->getMessage()
        );

        return false;
    }
}

?>
