

<?php

// --------------------------------------------------
// PROPERTY ENQUIRY DASHBOARD CARD
// --------------------------------------------------

if (empty($canManageUsers)) {
    return;
}

// --------------------------------------------------
// INITIALIZE STATISTICS
// --------------------------------------------------

$totalPropertyEnquiries = 0;
$newPropertyEnquiries = 0;

// --------------------------------------------------
// FETCH PROPERTY ENQUIRY STATISTICS
// --------------------------------------------------

try {

    $propertyEnquiryStatsQuery = $pdo->query("
        SELECT
            COUNT(*) AS total_enquiries,
            SUM(
                CASE
                    WHEN status = 'new' THEN 1
                    ELSE 0
                END
            ) AS new_enquiries
        FROM property_enquiries
    ");

    $propertyEnquiryStats = $propertyEnquiryStatsQuery->fetch(
        PDO::FETCH_ASSOC
    );

    $totalPropertyEnquiries = (int)(
        $propertyEnquiryStats["total_enquiries"] ?? 0
    );

    $newPropertyEnquiries = (int)(
        $propertyEnquiryStats["new_enquiries"] ?? 0
    );

} catch (Throwable $e) {

    error_log(
        "Property Enquiry Dashboard Card Error: " . $e->getMessage()
    );

}

?>

<!-- PROPERTY ENQUIRIES DASHBOARD CARD -->

<div class="dashboard-card stat-card green">

    <h3>Property Enquiries</h3>

    <h1><?php echo (int)$totalPropertyEnquiries; ?></h1>

    <p>
        <?php echo (int)$newPropertyEnquiries; ?> new enquiries
    </p>

    <a href="property_enquiries.php">
        View Enquiries →
    </a>

</div>
