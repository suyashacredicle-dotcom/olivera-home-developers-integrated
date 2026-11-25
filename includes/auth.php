
<?php

// --------------------------------------------------
// SECURE ADMIN SESSION CONFIGURATION
// --------------------------------------------------

$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off" &&
    $_SERVER["HTTPS"] !== ""
);

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}

// --------------------------------------------------
// PREVENT CACHING OF PROTECTED ADMIN PAGES
// --------------------------------------------------

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// --------------------------------------------------
// CHECK ADMIN AUTHENTICATION
// --------------------------------------------------

if (
    !isset($_SESSION["admin_id"]) ||
    !filter_var(
        $_SESSION["admin_id"],
        FILTER_VALIDATE_INT
    ) ||
    (int) $_SESSION["admin_id"] <= 0
) {

    header("Location: /mywebsite/admin/login.php");
    exit;

}

?>
