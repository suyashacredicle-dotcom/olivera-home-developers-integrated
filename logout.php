
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --------------------------------------------------
// LOG OUT CUSTOMER
// --------------------------------------------------

// Remove customer session information
unset(
    $_SESSION["customer_id"],
    $_SESSION["customer_name"],
    $_SESSION["customer_email"]
);

// Rotate CSRF token
$_SESSION["csrf_token"] = bin2hex(random_bytes(32));

// Regenerate session ID
session_regenerate_id(true);

// Redirect to customer login page
header("Location: login.php?logged_out=1");
exit;

?>
