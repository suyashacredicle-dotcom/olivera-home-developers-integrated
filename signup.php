
<?php

// --------------------------------------------------
// REDIRECT OLD CUSTOMER SIGNUP TO PROPERTY ENQUIRY
// --------------------------------------------------

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Preserve existing customer records and passwords.
// New customers will use the enquiry form instead.

header("Location: property-enquiry-start.php", true, 302);
exit;
