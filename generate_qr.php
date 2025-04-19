<?php
require_once __DIR__ . '/phpqrcode/phpqrcode.php';

if (!isset($_GET['user'])) {
    http_response_code(400);
    exit('Username parameter is required');
}

$username = htmlspecialchars($_GET['user']);
$url = "http://" . $_SERVER['HTTP_HOST'] . "/profile.php?user=" . urlencode($username);

// Send proper headers
header('Content-Type: image/png');

// Generate QR code (output directly to browser)
QRcode::png($url, false, QR_ECLEVEL_H, 6, 2);
exit;
