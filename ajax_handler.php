<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action']);
    exit;
}

// Include database connection
require_once 'config/database.php';

// Function to sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Handler for adding a new link
if (isset($_POST['add_link']) && $_POST['ajax'] === 'true') {
    // Get form data
    $displayText = sanitize($_POST['display_text']);
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    $iconClass = sanitize($_POST['icon_class']);
    
    // Validate input
    if (empty($displayText) || empty($url)) {
        echo json_encode(['success' => false, 'message' => 'Display text and URL are required']);
        exit;
    }
    
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid URL']);
        exit;
    }
    
    // Get user ID
    $userId = $_SESSION['user_id'];
    
    // Get next order position
    $stmt = $conn->prepare("SELECT MAX(position) AS max_position FROM user_links WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $position = ($row['max_position'] !== null) ? $row['max_position'] + 1 : 0;
    
    // Insert link into database
    $stmt = $conn->prepare("INSERT INTO user_links (user_id, display_text, url, icon_class, position) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $userId, $displayText, $url, $iconClass, $position);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding link: ' . $conn->error]);
    }
    
    exit;
}

// Handler for deleting a link
if (isset($_POST['delete_link']) && $_POST['ajax'] === 'true') {
    // Get link ID
    $linkId = filter_var($_POST['link_id'], FILTER_SANITIZE_NUMBER_INT);
    $userId = $_SESSION['user_id'];
    
    // Delete link from database
    $stmt = $conn->prepare("DELETE FROM user_links WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $linkId, $userId);
    
    if ($stmt->execute()) {
        // Reorder remaining links
        $stmt = $conn->prepare("SELECT id FROM user_links WHERE user_id = ? ORDER BY position");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $position = 0;
        while ($row = $result->fetch_assoc()) {
            $updateStmt = $conn->prepare("UPDATE user_links SET position = ? WHERE id = ?");
            $updateStmt->bind_param("ii", $position, $row['id']);
            $updateStmt->execute();
            $position++;
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting link: ' . $conn->error]);
    }
    
    exit;
}

// Handler for updating link order
if (isset($_POST['update_link_order']) && $_POST['ajax'] === 'true') {
    // Get link IDs in new order
    $linkIds = json_decode($_POST['link_ids']);
    $userId = $_SESSION['user_id'];
    
    if (!is_array($linkIds)) {
        echo json_encode(['success' => false, 'message' => 'Invalid link order data']);
        exit;
    }
    
    // Update link positions
    $position = 0;
    foreach ($linkIds as $linkId) {
        $stmt = $conn->prepare("UPDATE user_links SET position = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $position, $linkId, $userId);
        $stmt->execute();
        $position++;
    }
    
    echo json_encode(['success' => true]);
    exit;
}

// Handler for updating profile
if (isset($_POST['update_profile']) && $_POST['ajax'] === 'true') {
    // Get form data
    $bio = sanitize($_POST['bio']);
    $fontFamily = sanitize($_POST['font_family']);
    $themeColor = sanitize($_POST['theme_color']);
    $userId = $_SESSION['user_id'];
    
    // Initialize response data
    $responseData = ['success' => false];
    
    // Handle profile image upload if present
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['profile_image']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $fileName = 'profile_' . $userId . '_' . time() . '.' . pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $uploadPath = 'uploads/profile_images/' . $fileName;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
                // Update profile image path in database
                $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $stmt->bind_param("si", $uploadPath, $userId);
                $stmt->execute();
                
                $responseData['profile_image'] = $uploadPath;
            }
        }
    }
    
    // Update user profile in database
    $stmt = $conn->prepare("UPDATE users SET bio = ?, font_family = ?, theme_color = ? WHERE id = ?");
    $stmt->bind_param("sssi", $bio, $fontFamily, $themeColor, $userId);
    
    if ($stmt->execute()) {
        $responseData['success'] = true;
        $responseData['bio'] = $bio;
    } else {
        $responseData['message'] = 'Error updating profile: ' . $conn->error;
    }
    
    echo json_encode($responseData);
    exit;
}

// Handler for updating layout
if (isset($_POST['update_layout']) && $_POST['ajax'] === 'true') {
    // Get form data
    $layoutType = sanitize($_POST['layout_type']);
    $userId = $_SESSION['user_id'];
    
    // Validate layout type
    $validLayouts = ['standard', 'grid', 'minimal', 'creative'];
    if (!in_array($layoutType, $validLayouts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid layout type']);
        exit;
    }
    
    // Update layout in database
    $stmt = $conn->prepare("UPDATE user_preferences SET layout_type = ? WHERE user_id = ?");
    $stmt->bind_param("si", $layoutType, $userId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows === 0) {
            // No row exists yet, insert one
            $stmt = $conn->prepare("INSERT INTO user_preferences (user_id, layout_type) VALUES (?, ?)");
            $stmt->bind_param("is", $userId, $layoutType);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating layout: ' . $conn->error]);
    }
    
    exit;
}

// Handler for updating username
if (isset($_POST['update_username']) && $_POST['ajax'] === 'true') {
    // Get new username
    $newUsername = sanitize($_POST['new_username']);
    $userId = $_SESSION['user_id'];
    
    // Validate username
    if (empty($newUsername)) {
        echo json_encode(['success' => false, 'message' => 'Username cannot be empty']);
        exit;
    }
    
    if (strlen($newUsername) < 3 || strlen($newUsername) > 20) {
        echo json_encode(['success' => false, 'message' => 'Username must be between 3 and 20 characters']);
        exit;
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
        echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores']);
        exit;
    }
    
    // Check if username is already taken
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $newUsername, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username is already taken']);
        exit;
    }
    
    // Update username in database
    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
    $stmt->bind_param("si", $newUsername, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['username'] = $newUsername;
        echo json_encode(['success' => true, 'new_username' => $newUsername]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating username: ' . $conn->error]);
    }
    
    exit;
}

// Handler for other AJAX requests...

// If no valid handler was found
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>