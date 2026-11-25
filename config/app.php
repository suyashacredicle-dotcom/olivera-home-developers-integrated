<?php
// Build the application URL from the current host and project folder.
// This works for /mywebsite, /mywebsite_integrated, and a domain-root install.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
$basePath = rtrim(dirname($scriptPath), '/');
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}
if (substr($basePath, -6) === '/admin') {
    $basePath = substr($basePath, 0, -6);
}
$basePath = rtrim($basePath, '/');
$detectedBaseUrl = $scheme . '://' . $host . $basePath;
if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', $detectedBaseUrl);
}
if (
    !filter_var(APP_BASE_URL, FILTER_VALIDATE_URL) ||
    !in_array(parse_url(APP_BASE_URL, PHP_URL_SCHEME), ['http', 'https'], true)
) {
    throw new RuntimeException('Invalid website URL configuration.');
}
?>