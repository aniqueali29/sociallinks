<?php
// generate_qr.php - Generates QR code image for profile
require_once 'database.php';

// Check if username is provided
if (!isset($_GET['user'])) {
    http_response_code(400);
    exit('Username parameter is required');
}

$username = $_GET['user'];

// Verify user exists
$userSQL = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($userSQL);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit('User not found');
}

// Generate the profile URL for QR code
$profileUrl = "http://" . $_SERVER['HTTP_HOST'] . "/profile.php?user=" . urlencode($username);

// Require the QR code library
require_once __DIR__ . '/phpqrcode/phpqrcode.php';

// Set content type to PNG image
header('Content-Type: image/png');

// Generate QR code
QRcode::png($profileUrl, false, QR_ECLEVEL_H, 10, 2);
exit;