
<?php

// --------------------------------------------------
// GALLERY AND PROPERTY ENQUIRIES SIDEBAR LINKS
// --------------------------------------------------

$currentRole = $_SESSION["admin_role"] ?? "";

// --------------------------------------------------
// GALLERY ACCESS
// --------------------------------------------------

$galleryAllowedRoles = [
    "super_admin",
    "admin",
    "editor",
    "sales_employee"
];

$canAccessGallery = in_array(
    $currentRole,
    $galleryAllowedRoles,
    true
);

$isSuperAdmin = ($currentRole === "super_admin");

if ($canAccessGallery):

    $galleryLink = $isSuperAdmin
        ? "manage_gallery.php"
        : "employee_gallery.php";

    $galleryLabel = $isSuperAdmin
        ? "Gallery Management"
        : "Employee Gallery";

?>

<a href="<?php echo htmlspecialchars($galleryLink, ENT_QUOTES, "UTF-8"); ?>">
    <span class="menu-icon">▧</span>
    <?php echo htmlspecialchars($galleryLabel, ENT_QUOTES, "UTF-8"); ?>
</a>

<?php endif; ?>

<?php

// --------------------------------------------------
// PROPERTY ENQUIRIES ACCESS
// --------------------------------------------------

$propertyEnquiryAllowedRoles = [
    "super_admin",
    "admin"
];

$canViewPropertyEnquiries = in_array(
    $currentRole,
    $propertyEnquiryAllowedRoles,
    true
);

if ($canViewPropertyEnquiries):

?>

<a href="property_enquiries.php">
    <span class="menu-icon">▤</span>
    Property Enquiries
</a>

<?php endif; ?>
