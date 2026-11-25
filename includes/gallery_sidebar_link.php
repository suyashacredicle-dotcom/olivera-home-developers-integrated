
<?php

// --------------------------------------------------
// GALLERY SIDEBAR LINK
// --------------------------------------------------

$galleryAllowedRoles = [
    "super_admin",
    "admin",
    "editor",
    "sales_employee"
];

$currentRole = $_SESSION["admin_role"] ?? "";

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
