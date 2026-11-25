
<?php

// --------------------------------------------------
// SECURE CRM LEAD ACTIVITY LOGGER
// --------------------------------------------------

/**
 * Record an activity related to a CRM lead.
 *
 * @param PDO $pdo Database connection
 * @param int $leadId Lead ID
 * @param int|null $adminId Admin who performed the activity
 * @param string $activityType Activity category
 * @param string $description Human-readable activity description
 * @param string|null $oldValue Previous value
 * @param string|null $newValue New value
 *
 * @return bool
 */

function logLeadActivity(
    $pdo,
    $leadId,
    $adminId,
    $activityType,
    $description,
    $oldValue = null,
    $newValue = null
) {

    try {

        // --------------------------------------------------
        // VALIDATE DATABASE CONNECTION
        // --------------------------------------------------

        if (!$pdo instanceof PDO) {
            error_log("Lead activity logging failed: Invalid database connection.");
            return false;
        }

        // --------------------------------------------------
        // VALIDATE LEAD ID
        // --------------------------------------------------

        $leadId = filter_var(
            $leadId,
            FILTER_VALIDATE_INT
        );

        if ($leadId === false || $leadId <= 0) {
            error_log("Lead activity logging failed: Invalid lead ID.");
            return false;
        }

        // --------------------------------------------------
        // VALIDATE ADMIN ID
        // --------------------------------------------------

        if ($adminId !== null) {

            $adminId = filter_var(
                $adminId,
                FILTER_VALIDATE_INT
            );

            if ($adminId === false || $adminId <= 0) {
                error_log("Lead activity logging failed: Invalid admin ID.");
                return false;
            }
        }

        // --------------------------------------------------
        // VALIDATE ACTIVITY TYPE
        // --------------------------------------------------

        if (
            !is_string($activityType) ||
            trim($activityType) === ""
        ) {
            error_log("Lead activity logging failed: Invalid activity type.");
            return false;
        }

        // --------------------------------------------------
        // VALIDATE DESCRIPTION
        // --------------------------------------------------

        if (
            !is_string($description) ||
            trim($description) === ""
        ) {
            error_log("Lead activity logging failed: Invalid description.");
            return false;
        }

        // --------------------------------------------------
        // VALIDATE OLD AND NEW VALUES
        // --------------------------------------------------

        if (
            $oldValue !== null &&
            !is_string($oldValue) &&
            !is_numeric($oldValue)
        ) {
            error_log("Lead activity logging failed: Invalid old value.");
            return false;
        }

        if (
            $newValue !== null &&
            !is_string($newValue) &&
            !is_numeric($newValue)
        ) {
            error_log("Lead activity logging failed: Invalid new value.");
            return false;
        }

        // --------------------------------------------------
        // APPLY FIELD LENGTH LIMITS
        // --------------------------------------------------

        $activityType = substr(
            trim($activityType),
            0,
            100
        );

        $description = substr(
            trim($description),
            0,
            2000
        );

        if ($oldValue !== null) {
            $oldValue = substr((string) $oldValue, 0, 2000);
        }

        if ($newValue !== null) {
            $newValue = substr((string) $newValue, 0, 2000);
        }

        // --------------------------------------------------
        // INSERT LEAD ACTIVITY
        // --------------------------------------------------

        $stmt = $pdo->prepare("
            INSERT INTO lead_activity_logs (
                lead_id,
                admin_id,
                activity_type,
                description,
                old_value,
                new_value
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $leadId,
            $adminId,
            $activityType,
            $description,
            $oldValue,
            $newValue
        ]);

        return true;

    } catch (Throwable $e) {

        // Record technical details privately.
        error_log(
            "Lead Activity Log Error: " .
            $e->getMessage()
        );

        return false;
    }
}

?>
