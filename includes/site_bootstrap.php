<?php
// Shared public-site URL helper. Supports both the XAMPP /mywebsite folder
// and deployment at a domain root without hard-coded hostnames.
$siteBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($siteBasePath === '/') {
    $siteBasePath = '';
}
if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        global $siteBasePath;
        return $siteBasePath . '/' . ltrim($path, '/');
    }
}
?>