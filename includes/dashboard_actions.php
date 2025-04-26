<?php
// dashboard_actions.php - Handle AJAX requests for dashboard
session_start();
require_once '../database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false];

// Determine the action based on form data
$action = $_POST['action'] ?? '';

// If no explicit action is set, determine from other POST params
if (empty($action)) {
    if (isset($_POST['update_username'])) {
        $action = 'update_username';
    } elseif (isset($_POST['add_link'])) {
        $action = 'add_link';
    } elseif (isset($_POST['delete_link'])) {
        $action = 'delete_link';
    } elseif (isset($_POST['update_layout'])) {
        $action = 'update_layout';
    } elseif (isset($_POST['update_profile'])) {
        $action = 'update_profile';
    }
}

// Process actions
switch ($action) {
    case 'update_username':
        handleUsernameChange($conn, $user_id, $response);
        break;
        
    case 'add_link':
        handleAddLink($conn, $user_id, $response);
        break;
        
    case 'delete_link':
        handleDeleteLink($conn, $user_id, $response);
        break;
        
    case 'update_layout':
        handleUpdateLayout($conn, $user_id, $response);
        break;
        
    case 'update_profile':
        handleUpdateProfile($conn, $user_id, $response);
        break;
        
    case 'update_font':
        if (isset($_POST['font_family'])) {
            $font_family = $_POST['font_family'];
            
            $updateSQL = "UPDATE users SET font_family = ? WHERE user_id = ?";
            $stmt = $conn->prepare($updateSQL);
            $stmt->bind_param("si", $font_family, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Font updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error updating font: ' . $conn->error
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Font family not specified'
            ]);
        }
        break;
        
    default:
        $response['message'] = 'Unknown action';
        break;
}

// Return JSON response
echo json_encode($response);
exit;

// Function to handle username change
function handleUsernameChange($conn, $user_id, &$response) {
    $new_username = trim($_POST['new_username']);
    $can_change = true;
    
    // Get user data
    $userSQL = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($userSQL);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    
    // Get last username change
    $usernameChangeSQL = "SELECT * FROM username_changes WHERE user_id = ? ORDER BY change_date DESC LIMIT 1";
    $stmt = $conn->prepare($usernameChangeSQL);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $lastUsernameChange = $stmt->get_result()->fetch_assoc();
    
    // Check if username change table exists, if not create it
    $createTableSQL = "CREATE TABLE IF NOT EXISTS username_changes (
        change_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        old_username VARCHAR(255) NOT NULL,
        new_username VARCHAR(255) NOT NULL,
        change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";
    $conn->query($createTableSQL);
    
    // Check if username is already taken
    $checkUsernameSQL = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
    $stmt = $conn->prepare($checkUsernameSQL);
    $stmt->bind_param("si", $new_username, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $can_change = false;
        $response['message'] = "Username already exists. Please choose a different one.";
    } else {
        // Check if user has changed username recently
        if ($lastUsernameChange) {
            $lastChangeDate = new DateTime($lastUsernameChange['change_date']);
            $currentDate = new DateTime();
            $daysSinceChange = $currentDate->diff($lastChangeDate)->days;
            
            if ($daysSinceChange < 14) {
                $can_change = false;
                $response['message'] = "You can only change your username once every 14 days. Please wait " . (14 - $daysSinceChange) . " more days.";
            }
        }
        
        if ($can_change) {
            // Update username in the database
            $updateUsernameSQL = "UPDATE users SET username = ? WHERE user_id = ?";
            $stmt = $conn->prepare($updateUsernameSQL);
            $stmt->bind_param("si", $new_username, $user_id);
            
            if ($stmt->execute()) {
                // Record the username change
                $insertChangeSQL = "INSERT INTO username_changes (user_id, old_username, new_username) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($insertChangeSQL);
                $stmt->bind_param("iss", $user_id, $userData['username'], $new_username);
                $stmt->execute();
                
                $response['success'] = true;
                $response['message'] = "Username updated successfully.";
                $response['new_username'] = $new_username;
            } else {
                $response['message'] = "Database error while updating username.";
            }
        }
    }
}

function handleAddLink($conn, $user_id, &$response) {
    // Check if required fields are present
    if (!isset($_POST['platform']) || !isset($_POST['url']) || !isset($_POST['display_text'])) {
        $response['message'] = "Missing required fields";
        return;
    }
    
    $platform = $_POST['platform'];
    $url = $_POST['url'];
    $display_text = $_POST['display_text'];
    
    // Basic validation
    if (empty($platform) || empty($url) || empty($display_text)) {
        $response['message'] = "All fields are required";
        return;
    }
    
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $response['message'] = "Invalid URL format";
        return;
    }
    
    // Use platform name as icon identifier
    $icon = $platform;
    
    // Get the next display order
    $orderSQL = "SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM links WHERE user_id = ?";
    $stmt = $conn->prepare($orderSQL);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $next_order = $stmt->get_result()->fetch_assoc()['next_order'];
    
    $insertSQL = "INSERT INTO links (user_id, platform, url, display_text, icon, display_order) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSQL);
    $stmt->bind_param("issssi", $user_id, $platform, $url, $display_text, $icon, $next_order);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Link added successfully.";
        $response['link_id'] = $conn->insert_id;
    } else {
        $response['message'] = "Error adding link: " . $conn->error;
    }
}

// Function to handle deleting a link
function handleDeleteLink($conn, $user_id, &$response) {
    $link_id = $_POST['link_id'];
    
    $deleteSQL = "DELETE FROM links WHERE link_id = ? AND user_id = ?";
    $stmt = $conn->prepare($deleteSQL);
    $stmt->bind_param("ii", $link_id, $user_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Link deleted successfully.";
    } else {
        $response['message'] = "Error deleting link: " . $conn->error;
    }
}

// Function to handle layout update
function handleUpdateLayout($conn, $user_id, &$response) {
    $layout_style = $_POST['layout_style'];
    
    $updateSQL = "UPDATE users SET links_layout = ? WHERE user_id = ?";
    $stmt = $conn->prepare($updateSQL);
    $stmt->bind_param("si", $layout_style, $user_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Layout updated successfully.";
    } else {
        $response['message'] = "Error updating layout: " . $conn->error;
    }
}

// Function to handle profile update
function handleUpdateProfile($conn, $user_id, &$response) {
    // Get current user data
    $userSQL = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($userSQL);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    
    $bio = $_POST['bio'];
    $theme = $_POST['theme'];
    $font_family = $_POST['font_family'];
    
    // Handle profile image upload
    $profile_image = $userData['profile_image']; // Default to current image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $upload_dir = "uploads/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . "." . $file_ext;
        $target_file = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $profile_image = $target_file;
        }
    }
    
    // Handle background image upload
    $background_image = $userData['background_image']; // Default to current background
    
    // Check if user wants to remove the background image
    if (isset($_POST['remove_background'])) {
        $background_image = NULL;
    }
    // Check if a new background image is uploaded
    elseif (isset($_FILES['background_image']) && $_FILES['background_image']['error'] === 0) {
        $upload_dir = "uploads/backgrounds/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['background_image']['name'], PATHINFO_EXTENSION);
        $new_filename = "bg_" . $user_id . "_" . uniqid() . "." . $file_ext;
        $target_file = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['background_image']['tmp_name'], $target_file)) {
            $background_image = $target_file;
        }
    }
    
    $updateSQL = "UPDATE users SET bio = ?, theme = ?, profile_image = ?, font_family = ?, background_image = ? WHERE user_id = ?";
    $stmt = $conn->prepare($updateSQL);
    $stmt->bind_param("sssssi", $bio, $theme, $profile_image, $font_family, $background_image, $user_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Profile updated successfully.";
        $response['profile_image'] = $profile_image;
    } else {
        $response['message'] = "Error updating profile: " . $conn->error;
    }
}
?>