
<?php

// --------------------------------------------------
// REDIRECT OLD CUSTOMER LOGIN TO PROPERTY ENQUIRY
// --------------------------------------------------

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Existing customer sessions are preserved.
// Existing customer records and passwords are not changed.

header("Location: property-enquiry-start.php", true, 302);
exit;
