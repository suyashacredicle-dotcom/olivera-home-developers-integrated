
<?php

// --------------------------------------------------
// SECURE IN-APP NOTIFICATION HELPER
// --------------------------------------------------

/**
 * Create an in-app notification for an admin.
 *
 * @param PDO $pdo Database connection
 * @param int $adminId Admin receiving the notification
 * @param int|null $leadId Related CRM lead ID
 * @param string $title Notification title
 * @param string $message Notification message
 *
 * @return bool
 */

function createNotification(
    PDO $pdo,
    int $adminId,
    ?int $leadId,
    string $title,
    string $message
): bool {

    try {

        // --------------------------------------------------
        // VALIDATE ADMIN ID
        // --------------------------------------------------

        if ($adminId <= 0) {
            return false;
        }

        // --------------------------------------------------
        // VALIDATE LEAD ID
        // --------------------------------------------------

        if ($leadId !== null && $leadId <= 0) {
            return false;
        }

        // --------------------------------------------------
        // VALIDATE NOTIFICATION TITLE
        // --------------------------------------------------

        $title = trim($title);

        if ($title === "") {
            return false;
        }

        // --------------------------------------------------
        // VALIDATE NOTIFICATION MESSAGE
        // --------------------------------------------------

        $message = trim($message);

        if ($message === "") {
            return false;
        }

        // --------------------------------------------------
        // APPLY INPUT LENGTH LIMITS
        // --------------------------------------------------

        $title = mb_substr($title, 0, 255, "UTF-8");

        $message = mb_substr($message, 0, 2000, "UTF-8");

        // --------------------------------------------------
        // INSERT NOTIFICATION
        // --------------------------------------------------

        $stmt = $pdo->prepare("
            INSERT INTO notifications
                (admin_id, lead_id, title, message)
            VALUES
                (?, ?, ?, ?)
        ");

        $stmt->execute([
            $adminId,
            $leadId,
            $title,
            $message
        ]);

        return true;

    } catch (Throwable $e) {

        // Record technical details privately.
        error_log(
            "Notification creation failed: " .
            $e->getMessage()
        );

        return false;
    }
}

?>
