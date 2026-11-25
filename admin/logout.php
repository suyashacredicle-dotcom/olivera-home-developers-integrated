
<?php

// --------------------------------------------------
// SECURITY HEADERS
// --------------------------------------------------

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// --------------------------------------------------
// SECURE SESSION CONFIGURATION
// --------------------------------------------------

$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off" &&
    $_SERVER["HTTPS"] !== ""
);

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.cookie_httponly", "1");
ini_set("session.cookie_secure", $isHttps ? "1" : "0");
ini_set("session.cookie_samesite", "Lax");

session_start();

// --------------------------------------------------
// DATABASE AND ACTIVITY LOGGER
// --------------------------------------------------

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/activity_logger.php";

// --------------------------------------------------
// ALLOW POST REQUESTS ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

// --------------------------------------------------
// CSRF VALIDATION
// --------------------------------------------------

if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )
) {
    http_response_code(403);
    exit("Invalid logout request.");
}

// --------------------------------------------------
// RECORD LOGOUT ACTIVITY
// --------------------------------------------------

if (isset($_SESSION["admin_id"])) {

    logActivity(
        $pdo,
        "ADMIN_LOGOUT",
        "Admin " . ($_SESSION["admin_name"] ?? "Unknown") .
        " logged out successfully."
    );

}

// --------------------------------------------------
// CLEAR SESSION DATA
// --------------------------------------------------

$_SESSION = [];

// --------------------------------------------------
// DELETE SESSION COOKIE
// --------------------------------------------------

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 42000,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => $params["samesite"] ?? "Lax"
        ]
    );

}

// --------------------------------------------------
// DESTROY SESSION
// --------------------------------------------------

session_destroy();

// --------------------------------------------------
// REDIRECT TO LOGIN
// --------------------------------------------------

header("Location: login.php");
exit;

