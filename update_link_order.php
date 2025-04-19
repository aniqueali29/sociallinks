<?php
// update_link_order.php - Handles AJAX requests to update link display order
session_start();
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if link_order data was sent
if (isset($_POST['link_order']) && is_array($_POST['link_order'])) {
    $link_ids = $_POST['link_order'];
    
    // Update the display_order for each link
    $success = true;
    $conn->begin_transaction();
    
    try {
        foreach ($link_ids as $index => $link_id) {
            // Make sure the link belongs to the current user before updating
            $updateSQL = "UPDATE links SET display_order = ? WHERE link_id = ? AND user_id = ?";
            $stmt = $conn->prepare($updateSQL);
            $display_order = $index + 1; // Start from 1
            $stmt->bind_param("iii", $display_order, $link_id, $user_id);
            
            if (!$stmt->execute()) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            $conn->commit();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Link order updated successfully']);
        } else {
            $conn->rollback();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error updating link order']);
        }
    } catch (Exception $e) {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>