<?php
// update_status.php - Update user activity status
session_start();
require_once 'database.php';

// Only process if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Update last activity timestamp and set status to online
    $updateSQL = "UPDATE users SET last_activity = NOW(), online_status = 'online' WHERE user_id = ?";
    $stmt = $conn->prepare($updateSQL);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Return success response
    echo json_encode(['status' => 'success']);
} else {
    // User not logged in
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
}