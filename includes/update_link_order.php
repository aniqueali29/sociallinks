<?php
// update_link_order.php - Update the order of links
session_start();
require_once '../database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false];

// Check if link_order array exists in the POST data
if (isset($_POST['link_order']) && is_array($_POST['link_order'])) {
    $link_order = $_POST['link_order'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update the display_order for each link
        foreach ($link_order as $position => $link_id) {
            $display_order = $position + 1; // Start from 1
            
            $updateSQL = "UPDATE links SET display_order = ? WHERE link_id = ? AND user_id = ?";
            $stmt = $conn->prepare($updateSQL);
            $stmt->bind_param("iii", $display_order, $link_id, $user_id);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = 'Link order updated successfully';
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $response['message'] = 'Error updating link order: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid link order data';
}

// Return JSON response
echo json_encode($response);
exit;
?>