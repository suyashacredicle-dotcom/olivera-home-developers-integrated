<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['public_form_csrf']) || !is_string($_SESSION['public_form_csrf'])) {
    $_SESSION['public_form_csrf'] = bin2hex(random_bytes(32));
}
$publicFormCsrf = $_SESSION['public_form_csrf'];
?>