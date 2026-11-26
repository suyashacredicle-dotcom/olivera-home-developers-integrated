
<?php

// --------------------------------------------------
// LOAD ADMIN AUTHENTICATION
// --------------------------------------------------

require_once __DIR__ . "/auth.php";

// --------------------------------------------------
// ADMIN ROLE PERMISSIONS
// --------------------------------------------------

$permissions = [

    // SUPER ADMIN
    "super_admin" => [
        "manage_admins",
        "manage_content",
        "manage_projects",
        "manage_users",
        "manage_settings",
        "manage_leads",
        "manage_partnerships",
        "assign_leads",
        "view_all_leads",
        "manage_followups",
        "manage_site_visits",
        "view_crm_reports"
    ],

    // ADMIN
    "admin" => [
        "manage_content",
        "manage_projects",
        "manage_users",
        "manage_leads",
        "assign_leads",
        "view_all_leads",
        "manage_followups",
        "manage_site_visits",
        "view_crm_reports"
    ],

    // EDITOR
    "editor" => [
        "manage_content"
    ],

    // SALES EMPLOYEE
    "sales_employee" => [
        "view_assigned_leads",
        "update_assigned_leads",
        "manage_followups",
        "manage_site_visits"
    ]

];

// --------------------------------------------------
// VALIDATE CURRENT ADMIN ROLE
// --------------------------------------------------

$currentRole = $_SESSION["admin_role"] ?? null;

if (
    !is_string($currentRole) ||
    !isset($permissions[$currentRole])
) {
    http_response_code(403);
    exit("Access Denied! Invalid admin role.");
}

// --------------------------------------------------
// CHECK ADMIN PERMISSION
// --------------------------------------------------

function hasPermission($permission)
{
    global $permissions, $currentRole;

    if (
        !is_string($permission) ||
        $permission === "" ||
        !isset($permissions[$currentRole])
    ) {
        return false;
    }

    return in_array(
        $permission,
        $permissions[$currentRole],
        true
    );
}

// --------------------------------------------------
// REQUIRE ADMIN PERMISSION
// --------------------------------------------------

function requirePermission($permission)
{
    if (!hasPermission($permission)) {
        http_response_code(403);

        exit(
            "Access Denied! You do not have permission to access this page."
        );
    }
}

?>
