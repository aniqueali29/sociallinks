<?php
// dashboard.php - User dashboard with enhanced functionality
session_start();
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Get user data
$userSQL = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

// Get user links
$linksSQL = "SELECT * FROM links WHERE user_id = ? ORDER BY display_order";
$stmt = $conn->prepare($linksSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$links = $stmt->get_result();

// Get link categories
$categoriesSQL = "SELECT DISTINCT category FROM links WHERE user_id = ? AND category IS NOT NULL AND category != ''";
$stmt = $conn->prepare($categoriesSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories_result = $stmt->get_result();
$categories = [];
while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category['category'];
}

// Process link submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_link'])) {
    $platform = $_POST['platform'];
    $url = $_POST['url'];
    $display_text = $_POST['display_text'];
    $icon = $_POST['platform'];  // Using platform name as icon identifier
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error_message = "Please enter a valid URL including http:// or https://";
    } else {
        // Get the next display order
        $orderSQL = "SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM links WHERE user_id = ?";
        $stmt = $conn->prepare($orderSQL);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $next_order = $stmt->get_result()->fetch_assoc()['next_order'];
        
        $insertSQL = "INSERT INTO links (user_id, platform, url, display_text, icon, display_order, category, is_featured) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertSQL);
        $stmt->bind_param("issssisi", $user_id, $platform, $url, $display_text, $icon, $next_order, $category, $is_featured);
        
        if ($stmt->execute()) {
            $success_message = "Link added successfully!";
            // Refresh the links
            $stmt = $conn->prepare($linksSQL);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $links = $stmt->get_result();
            
            // Refresh categories
            $stmt = $conn->prepare($categoriesSQL);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $categories_result = $stmt->get_result();
            $categories = [];
            while ($category = $categories_result->fetch_assoc()) {
                $categories[] = $category['category'];
            }
        } else {
            $error_message = "Error adding link: " . $conn->error;
        }
    }
}

// Process link deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_link'])) {
    $link_id = $_POST['link_id'];
    
    $deleteSQL = "DELETE FROM links WHERE link_id = ? AND user_id = ?";
    $stmt = $conn->prepare($deleteSQL);
    $stmt->bind_param("ii", $link_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Link deleted successfully!";
        // Refresh the links
        $stmt = $conn->prepare($linksSQL);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $links = $stmt->get_result();
    } else {
        $error_message = "Error deleting link: " . $conn->error;
    }
}

// Process link editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_link'])) {
    $link_id = $_POST['link_id'];
    $platform = $_POST['platform'];
    $url = $_POST['url'];
    $display_text = $_POST['display_text'];
    $icon = $_POST['platform']; // Using platform name as icon identifier
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error_message = "Please enter a valid URL including http:// or https://";
    } else {
        $updateSQL = "UPDATE links SET platform = ?, url = ?, display_text = ?, icon = ?, category = ?, is_featured = ? 
                    WHERE link_id = ? AND user_id = ?";
        $stmt = $conn->prepare($updateSQL);
        $stmt->bind_param("sssssiii", $platform, $url, $display_text, $icon, $category, $is_featured, $link_id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Link updated successfully!";
            // Refresh the links
            $stmt = $conn->prepare($linksSQL);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $links = $stmt->get_result();
        } else {
            $error_message = "Error updating link: " . $conn->error;
        }
    }
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $bio = $_POST['bio'];
    $theme = $_POST['theme'];
    $display_name = isset($_POST['display_name']) ? $_POST['display_name'] : $userData['username'];
    $email = isset($_POST['email']) ? $_POST['email'] : $userData['email'];
    $social_layout = isset($_POST['social_layout']) ? $_POST['social_layout'] : 'list';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    // Handle profile image upload
    $profile_image = $userData['profile_image']; // Default to current image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $upload_dir = "uploads/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $error_message = "Only JPG, PNG, and GIF images are allowed.";
        } else {
            $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . "." . $file_ext;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                $profile_image = $target_file;
            } else {
                $error_message = "Failed to upload image.";
            }
        }
    }
    
    if (empty($error_message)) {
        $updateSQL = "UPDATE users SET bio = ?, theme = ?, profile_image = ?, display_name = ?, email = ?, social_layout = ?, is_public = ? 
                     WHERE user_id = ?";
        $stmt = $conn->prepare($updateSQL);
        $stmt->bind_param("ssssssis", $bio, $theme, $profile_image, $display_name, $email, $social_layout, $is_public, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh user data
            $stmt = $conn->prepare($userSQL);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $userData = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $userData['password'])) {
        $error_message = "Current password is incorrect.";
    } else if ($new_password !== $confirm_password) {
        $error_message = "New passwords don't match.";
    } else if (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $updateSQL = "UPDATE users SET password = ? WHERE user_id = ?";
        $stmt = $conn->prepare($updateSQL);
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Error changing password: " . $conn->error;
        }
    }
}

// Handle batch operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action'])) {
    if (isset($_POST['selected_links']) && is_array($_POST['selected_links'])) {
        $selected_links = $_POST['selected_links'];
        $action = $_POST['batch_action'];
        
        if (!empty($selected_links)) {
            if ($action === 'delete') {
                $placeholders = str_repeat('?,', count($selected_links) - 1) . '?';
                $deleteSQL = "DELETE FROM links WHERE link_id IN ($placeholders) AND user_id = ?";
                $types = str_repeat('i', count($selected_links)) . 'i';
                $stmt = $conn->prepare($deleteSQL);
                
                $params = $selected_links;
                $params[] = $user_id;
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $success_message = count($selected_links) . " links deleted successfully!";
                    // Refresh the links
                    $stmt = $conn->prepare($linksSQL);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $links = $stmt->get_result();
                } else {
                    $error_message = "Error deleting links: " . $conn->error;
                }
            } elseif ($action === 'feature') {
                $placeholders = str_repeat('?,', count($selected_links) - 1) . '?';
                $updateSQL = "UPDATE links SET is_featured = 1 WHERE link_id IN ($placeholders) AND user_id = ?";
                $types = str_repeat('i', count($selected_links)) . 'i';
                $stmt = $conn->prepare($updateSQL);
                
                $params = $selected_links;
                $params[] = $user_id;
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $success_message = count($selected_links) . " links marked as featured!";
                    // Refresh the links
                    $stmt = $conn->prepare($linksSQL);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $links = $stmt->get_result();
                } else {
                    $error_message = "Error updating links: " . $conn->error;
                }
            } elseif ($action === 'unfeature') {
                $placeholders = str_repeat('?,', count($selected_links) - 1) . '?';
                $updateSQL = "UPDATE links SET is_featured = 0 WHERE link_id IN ($placeholders) AND user_id = ?";
                $types = str_repeat('i', count($selected_links)) . 'i';
                $stmt = $conn->prepare($updateSQL);
                
                $params = $selected_links;
                $params[] = $user_id;
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $success_message = count($selected_links) . " links unmarked as featured!";
                    // Refresh the links
                    $stmt = $conn->prepare($linksSQL);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $links = $stmt->get_result();
                } else {
                    $error_message = "Error updating links: " . $conn->error;
                }
            } elseif ($action === 'category' && isset($_POST['batch_category'])) {
                $new_category = $_POST['batch_category'];
                $placeholders = str_repeat('?,', count($selected_links) - 1) . '?';
                $updateSQL = "UPDATE links SET category = ? WHERE link_id IN ($placeholders) AND user_id = ?";
                $types = 's' . str_repeat('i', count($selected_links)) . 'i';
                $stmt = $conn->prepare($updateSQL);
                
                $params = [$new_category];
                $params = array_merge($params, $selected_links);
                $params[] = $user_id;
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $success_message = count($selected_links) . " links moved to category '$new_category'!";
                    // Refresh the links and categories
                    $stmt = $conn->prepare($linksSQL);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $links = $stmt->get_result();
                    
                    $stmt = $conn->prepare($categoriesSQL);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $categories_result = $stmt->get_result();
                    $categories = [];
                    while ($category = $categories_result->fetch_assoc()) {
                        $categories[] = $category['category'];
                    }
                } else {
                    $error_message = "Error updating links: " . $conn->error;
                }
            }
        } else {
            $error_message = "No links selected.";
        }
    } else {
        $error_message = "No links selected.";
    }
}

// Generate page statistics
$statsSQL = "SELECT COUNT(*) as total_views FROM page_views WHERE user_id = ?";
$stmt = $conn->prepare($statsSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_views = $stmt->get_result()->fetch_assoc()['total_views'];

// Get referrer statistics
$referrerSQL = "SELECT referrer, COUNT(*) as count FROM page_views WHERE user_id = ? GROUP BY referrer ORDER BY count DESC LIMIT 5";
$stmt = $conn->prepare($referrerSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$referrers = $stmt->get_result();

// Get recent clicks
$clicksSQL = "SELECT l.display_text, lc.clicked_at FROM link_clicks lc 
             JOIN links l ON lc.link_id = l.link_id 
             WHERE l.user_id = ? 
             ORDER BY lc.clicked_at DESC LIMIT 10";
$stmt = $conn->prepare($clicksSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_clicks = $stmt->get_result();

// Generate QR code URL
$qrCodeUrl = "generate_qr.php?user=" . urlencode($userData['username']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SocialLinks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
:root {
    --primary-color: #6C63FF;
    --secondary-color: #FF6584;
    --accent-color: #4CD5C5;
    --dark-color: #2A2A2A;
    --light-color: #F8F9FA;
    --gradient: linear-gradient(135deg, var(--primary-color) 0%, #8E2DE2 100%);
}

body {
    font-family: 'Poppins', sans-serif;
    background-color: #f8f9fa;
    color: #444;
    overflow-x: hidden;
}

.navbar {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    padding: 15px 0;
    transition: all 0.3s ease;
}

.navbar-brand {
    font-weight: 700;
    color: var(--primary-color) !important;
    font-size: 1.6rem;
}

.navbar-nav .nav-link {
    color: var(--dark-color) !important;
    font-weight: 500;
    margin: 0 10px;
    position: relative;
    transition: all 0.3s ease;
}

.navbar-nav .nav-link:hover {
    color: var(--primary-color) !important;
}

.navbar-nav .nav-link::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    background-color: var(--primary-color);
    bottom: -2px;
    left: 0;
    transition: width 0.3s ease;
}

.navbar-nav .nav-link:hover::after,
.navbar-nav .nav-link.active::after {
    width: 100%;
}

.dashboard-header {
    background: var(--gradient);
    padding: 100px 0 30px;
    color: white;
    position: relative;
    overflow: hidden;
    margin-bottom: 40px;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    top: -100px;
    right: -100px;
}

.dashboard-header::after {
    content: '';
    position: absolute;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    bottom: -50px;
    left: -50px;
}

.dashboard-header h1 {
    font-weight: 700;
    margin-bottom: 10px;
    font-size: 2.5rem;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.dashboard-header p {
    max-width: 600px;
    margin-bottom: 0;
    opacity: 0.9;
}

.profile-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: none;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.profile-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--gradient);
    transform: scaleX(0);
    transform-origin: right;
    transition: transform 0.3s ease;
}

.profile-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(108, 99, 255, 0.1);
}

.profile-card:hover::after {
    transform: scaleX(1);
    transform-origin: left;
}

.profile-image {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border-radius: 50%;
    border: 5px solid white;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.profile-image:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(108, 99, 255, 0.2);
}

.add-link-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: none;
    margin-bottom: 30px;
    transition: all 0.3s ease;
}

.add-link-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(108, 99, 255, 0.1);
}

.my-links-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: none;
    transition: all 0.3s ease;
}

.my-links-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(108, 99, 255, 0.1);
}

.card-header {
    background: transparent;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding-left: 0;
    padding-right: 0;
}

.card-header h5 {
    font-weight: 600;
    color: var(--dark-color);
    position: relative;
    display: inline-block;
    padding-bottom: 10px;
}

.card-header h5::after {
    content: '';
    position: absolute;
    width: 50px;
    height: 3px;
    background: var(--primary-color);
    bottom: 0;
    left: 0;
    border-radius: 2px;
}

.form-label {
    font-weight: 500;
    color: #555;
    margin-bottom: 8px;
}

.form-control {
    padding: 12px 15px;
    border-radius: 10px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.form-control:focus {
    box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.2);
    border-color: var(--primary-color);
}

.form-select {
    padding: 12px 15px;
    border-radius: 10px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.form-select:focus {
    box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.2);
    border-color: var(--primary-color);
}

.btn {
    padding: 12px 25px;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--gradient);
    border: none;
    box-shadow: 0 5px 15px rgba(108, 99, 255, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5d56d8 0%, #7a28c0 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(108, 99, 255, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, #28c76f 0%, #00c6b4 100%);
    border: none;
    box-shadow: 0 5px 15px rgba(40, 199, 111, 0.3);
}

.btn-success:hover {
    background: linear-gradient(135deg, #24b565 0%, #00b3a2 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(40, 199, 111, 0.4);
}

.btn-outline-primary {
    border: 2px solid var(--primary-color);
    color: var(--primary-color);
}

.btn-outline-primary:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(108, 99, 255, 0.2);
}

.btn-danger {
    background: linear-gradient(135deg, #FF6584 0%, #FF4C71 100%);
    border: none;
    box-shadow: 0 5px 15px rgba(255, 101, 132, 0.3);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #ff5172 0%, #ff395f 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(255, 101, 132, 0.4);
}

.table {
    border-collapse: separate;
    border-spacing: 0 10px;
}

.table thead th {
    border-bottom: none;
    color: #777;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 1px;
    padding: 15px;
}

.table tbody tr {
    background: white;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.02);
    border-radius: 15px;
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(108, 99, 255, 0.1);
}

.table tbody td {
    padding: 15px;
    border-top: none;
    vertical-align: middle;
}

.table tbody tr td:first-child {
    border-top-left-radius: 10px;
    border-bottom-left-radius: 10px;
}

.table tbody tr td:last-child {
    border-top-right-radius: 10px;
    border-bottom-right-radius: 10px;
}

.platform-icon {
    width: 40px;
    height: 40px;
    background: rgba(108, 99, 255, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 1.2rem;
    color: var(--primary-color);
}

.badge {
    padding: 8px 15px;
    border-radius: 50px;
    font-weight: 500;
    font-size: 0.85rem;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: #dde;
}

.footer {
    background: var(--dark-color);
    color: white;
    padding: 40px 0;
    margin-top: 80px;
}

.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.footer-logo {
    font-weight: 700;
    font-size: 1.5rem;
    color: white;
    text-decoration: none;
}

.footer-logo span {
    color: var(--primary-color);
}

.footer-links {
    display: flex;
    gap: 20px;
}

.footer-links a {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: all 0.3s ease;
}

.footer-links a:hover {
    color: white;
}

.social-links {
    display: flex;
    gap: 15px;
}

.social-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.social-icon:hover {
    background: var(--primary-color);
    transform: translateY(-3px);
}

.copyright {
    margin-top: 30px;
    color: rgba(255, 255, 255, 0.5);
    text-align: center;
}

.qr-modal .modal-content {
    border-radius: 20px;
    overflow: hidden;
    border: none;
}

.qr-modal .modal-header {
    background: var(--gradient);
    color: white;
    border-bottom: none;
}

.qr-modal .modal-title {
    font-weight: 600;
}

.qr-modal .modal-body {
    padding: 30px;
    text-align: center;
}

.qr-code-container {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    margin: 0 auto;
    width: fit-content;
}

.qr-code-container img {
    max-width: 100%;
    height: auto;
}

.floating {
    animation: floating 3s ease-in-out infinite;
}

@keyframes floating {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
    100% { transform: translateY(0px); }
}

@media (max-width: 991px) {
    .dashboard-header h1 {
        font-size: 2rem;
    }
    
    .navbar-collapse {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-top: 10px;
    }
    
    .navbar-nav .nav-link {
        padding: 10px 0;
    }
    
    .footer-content {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-link me-2"></i>SocialLinks
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php?user=<?php echo $userData['username']; ?>" target="_blank">View My Page</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
                            <i class="fas fa-qrcode me-1"></i> My QR Code
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($userData['username']); ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#accountSettingsModal"><i class="fas fa-cog me-2"></i>Account Settings</a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal"><i class="fas fa-key me-2"></i>Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="animate__animated animate__fadeIn">Welcome back, <?php echo htmlspecialchars($userData['username']); ?>!</h1>
                    <p class="animate__animated animate__fadeIn animate__delay-1s">Manage your social links and customize your profile from your personal dashboard.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="profile.php?user=<?php echo $userData['username']; ?>" class="btn btn-light mt-3" target="_blank">
                        <i class="fas fa-eye me-2"></i> Preview My Page
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Total Page Views</h6>
                                <h3><?php echo $total_views; ?></h3>
                            </div>
                            <div class="icon-bg">
                                <i class="fas fa-eye"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Total Links</h6>
                                <h3><?php echo $links->num_rows; ?></h3>
                            </div>
                            <div class="icon-bg">
                                <i class="fas fa-link"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Link Clicks</h6>
                                <h3>
                                    <?php 
                                    $clickCountSQL = "SELECT COUNT(*) as count FROM link_clicks lc 
                                                     JOIN links l ON lc.link_id = l.link_id 
                                                     WHERE l.user_id = ?";
                                    $stmt = $conn->prepare($clickCountSQL);
                                    $stmt->bind_param("i", $user_id);
                                    $stmt->execute();
                                    echo $stmt->get_result()->fetch_assoc()['count']; 
                                    ?>
                                </h3>
                            </div>
                            <div class="icon-bg">
                                <i class="fas fa-mouse-pointer"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="profile-card mb-4">
                    <div class="text-center">
                        <img src="<?php echo !empty($userData['profile_image']) ? $userData['profile_image'] : 'https://via.placeholder.com/150'; ?>" 
                             class="profile-image mb-4 floating" alt="Profile Image">
                        <h3><?php echo htmlspecialchars(isset($userData['display_name']) && !empty($userData['display_name']) ? $userData['display_name'] : $userData['username']); ?></h3>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($userData['bio'])); ?></p>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-center mb-3">
                            <a href="profile.php?user=<?php echo $userData['username']; ?>" class="btn btn-primary me-2" target="_blank">
                                <i class="fas fa-external-link-alt me-1"></i> View Profile
                            </a>
                            <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
                                <i class="fas fa-qrcode"></i> QR Code
                            </a>
                        </div>
                        
                        <!-- Social Link Toggle -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="toggleProfileVisibility" 
                                   <?php echo isset($userData['is_public']) && $userData['is_public'] == 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="toggleProfileVisibility">Profile is <?php echo isset($userData['is_public']) && $userData['is_public'] == 1 ? 'Public' : 'Private'; ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="display_name" class="form-label">Display Name</label>
                                <input type="text" class="form-control" id="display_name" name="display_name" 
                                       value="<?php echo htmlspecialchars(isset($userData['display_name']) ? $userData['display_name'] : $userData['username']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars(isset($userData['email']) ? $userData['email'] : ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Profile Image</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image">
                                <small class="text-muted">Recommended size: 300x300px</small>
                            </div>
                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Tell visitors about yourself..."><?php echo htmlspecialchars($userData['bio']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="theme" class="form-label">Theme</label>
                                <select class="form-select" id="theme" name="theme">
                                    <option value="default" <?php echo $userData['theme'] === 'default' ? 'selected' : ''; ?>>Default</option>
                                    <option value="dark" <?php echo $userData['theme'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                    <option value="light" <?php echo $userData['theme'] === 'light' ? 'selected' : ''; ?>>Light</option>
                                    <option value="colorful" <?php echo $userData['theme'] === 'colorful' ? 'selected' : ''; ?>>Colorful</option>
                                    <option value="minimal" <?php echo $userData['theme'] === 'minimal' ? 'selected' : ''; ?>>Minimal</option>
                                    <option value="gradient" <?php echo $userData['theme'] === 'gradient' ? 'selected' : ''; ?>>Gradient</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="social_layout" class="form-label">Social Links Layout</label>
                                <select class="form-select" id="social_layout" name="social_layout">
                                    <option value="list" <?php echo (isset($userData['social_layout']) && $userData['social_layout'] === 'list') ? 'selected' : ''; ?>>List View</option>
                                    <option value="grid" <?php echo (isset($userData['social_layout']) && $userData['social_layout'] === 'grid') ? 'selected' : ''; ?>>Grid View</option>
                                    <option value="buttons" <?php echo (isset($userData['social_layout']) && $userData['social_layout'] === 'buttons') ? 'selected' : ''; ?>>Button Style</option>
                                    <option value="minimal" <?php echo (isset($userData['social_layout']) && $userData['social_layout'] === 'minimal') ? 'selected' : ''; ?>>Minimal View</option>
                                </select>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_public" name="is_public" 
                                      <?php echo isset($userData['is_public']) && $userData['is_public'] == 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_public">Make profile public</label>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Analytics Section -->
                <div class="profile-card mt-4">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Analytics Overview</h5>
                    </div>
                    <div class="card-body">
                    <h6>Top Referrers</h6>
                        <ul class="list-group list-group-flush">
                            <?php if ($referrers->num_rows > 0): ?>
                                <?php while ($referrer = $referrers->fetch_assoc()): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo !empty($referrer['referrer']) ? htmlspecialchars($referrer['referrer']) : 'Direct'; ?>
                                        <span class="badge bg-primary rounded-pill"><?php echo $referrer['count']; ?></span>
                                    </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li class="list-group-item">No referrer data available yet</li>
                            <?php endif; ?>
                        </ul>
                        
                        <h6 class="mt-4">Recent Link Clicks</h6>
                        <ul class="list-group list-group-flush">
                            <?php if ($recent_clicks->num_rows > 0): ?>
                                <?php while ($click = $recent_clicks->fetch_assoc()): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($click['display_text']); ?>
                                        <small class="text-muted">
                                            <?php 
                                            $clicked_date = new DateTime($click['clicked_at']);
                                            echo $clicked_date->format('M j, Y g:i A'); 
                                            ?>
                                        </small>
                                    </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li class="list-group-item">No click data available yet</li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="text-center mt-3">
                            <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#analyticsModal">
                                <i class="fas fa-chart-bar me-2"></i> View Detailed Analytics
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="add-link-card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Add New Link</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="platform" class="form-label">Platform</label>
                                    <select class="form-select" id="platform" name="platform" required>
                                        <option value="">Select a platform</option>
                                        <option value="instagram">Instagram</option>
                                        <option value="facebook">Facebook</option>
                                        <option value="twitter">Twitter</option>
                                        <option value="linkedin">LinkedIn</option>
                                        <option value="github">GitHub</option>
                                        <option value="youtube">YouTube</option>
                                        <option value="tiktok">TikTok</option>
                                        <option value="twitch">Twitch</option>
                                        <option value="pinterest">Pinterest</option>
                                        <option value="snapchat">Snapchat</option>
                                        <option value="reddit">Reddit</option>
                                        <option value="discord">Discord</option>
                                        <option value="telegram">Telegram</option>
                                        <option value="spotify">Spotify</option>
                                        <option value="medium">Medium</option>
                                        <option value="email">Email</option>
                                        <option value="phone">Phone</option>
                                        <option value="website">Website</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="display_text" class="form-label">Display Text</label>
                                    <input type="text" class="form-control" id="display_text" name="display_text" placeholder="Follow me on Instagram" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="url" class="form-label">URL</label>
                                    <input type="url" class="form-control" id="url" name="url" placeholder="https://" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" list="existing-categories" placeholder="e.g., Social Media">
                                    <datalist id="existing-categories">
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured">
                                <label class="form-check-label" for="is_featured">
                                    Mark as featured (display prominently)
                                </label>
                            </div>
                            <button type="submit" name="add_link" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i> Add Link
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="my-links-card mt-4">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">My Links</h5>
                        <div class="d-flex">
                            <div class="input-group me-2" style="max-width: 200px;">
                                <input type="text" class="form-control form-control-sm" id="searchLinks" placeholder="Search links...">
                                <button class="btn btn-outline-secondary btn-sm" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <select class="form-select form-select-sm me-2" id="categoryFilter" style="max-width: 150px;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="badge bg-primary"><?php echo $links->num_rows; ?> Links</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($links->num_rows > 0): ?>
                            <form method="POST" action="" id="batchActionsForm">
                                <div class="mb-3 batch-actions" style="display: none;">
                                    <div class="btn-group">
                                        <button type="submit" name="batch_action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete the selected links?')">
                                            <i class="fas fa-trash me-1"></i> Delete Selected
                                        </button>
                                        <button type="submit" name="batch_action" value="feature" class="btn btn-sm btn-success">
                                            <i class="fas fa-star me-1"></i> Feature Selected
                                        </button>
                                        <button type="submit" name="batch_action" value="unfeature" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-star-half-alt me-1"></i> Unfeature Selected
                                        </button>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="fas fa-folder me-1"></i> Move to Category
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <div class="px-3 py-2">
                                                        <input type="text" class="form-control form-control-sm" name="batch_category" placeholder="Category name">
                                                    </div>
                                                </li>
                                                <?php foreach ($categories as $category): ?>
                                                    <li><button type="submit" name="batch_action" value="category" class="dropdown-item" 
                                                              onclick="document.getElementsByName('batch_category')[0].value='<?php echo htmlspecialchars($category); ?>'"><?php echo htmlspecialchars($category); ?></button></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="selectAllLinks">
                                                    </div>
                                                </th>
                                                <th>Platform</th>
                                                <th>Display Text</th>
                                                <th>URL</th>
                                                <th>Category</th>
                                                <th>Featured</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sortable-links">
                                            <?php while ($link = $links->fetch_assoc()): ?>
                                                <tr data-id="<?php echo $link['link_id']; ?>" class="link-row <?php echo isset($link['category']) ? 'category-'.$link['category'] : ''; ?>">
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input link-checkbox" type="checkbox" name="selected_links[]" value="<?php echo $link['link_id']; ?>">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="platform-icon">
                                                                <i class="fab fa-<?php echo $link['platform']; ?>"></i>
                                                            </div>
                                                            <?php echo ucfirst($link['platform']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($link['display_text']); ?></td>
                                                    <td>
                                                        <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 150px;">
                                                            <?php echo htmlspecialchars($link['url']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($link['category'] ?? 'Uncategorized'); ?></td>
                                                    <td>
                                                        <?php if (isset($link['is_featured']) && $link['is_featured'] == 1): ?>
                                                            <span class="badge bg-warning"><i class="fas fa-star me-1"></i>Featured</span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-primary edit-link-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#editLinkModal"
                                                                data-id="<?php echo $link['link_id']; ?>"
                                                                data-platform="<?php echo $link['platform']; ?>"
                                                                data-text="<?php echo htmlspecialchars($link['display_text']); ?>"
                                                                data-url="<?php echo htmlspecialchars($link['url']); ?>"
                                                                data-category="<?php echo htmlspecialchars($link['category'] ?? ''); ?>"
                                                                data-featured="<?php echo isset($link['is_featured']) && $link['is_featured'] == 1 ? '1' : '0'; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" action="" class="d-inline">
                                                                <input type="hidden" name="link_id" value="<?php echo $link['link_id']; ?>">
                                                                <button type="submit" name="delete_link" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this link?')">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-link-slash"></i>
                                </div>
                                <h5>No links added yet</h5>
                                <p class="text-muted">Start adding your social links using the form above!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade qr-modal" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">My QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center mb-4">Scan this QR code to access your profile directly.</p>
                    <div class="qr-code-container">
                        <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" class="img-fluid">
                    </div>
                    <p class="text-center mt-4 text-muted">Share your profile with anyone by showing them this QR code.</p>
                    
                    <!-- New QR code sharing options -->
                    <div class="mt-4">
                        <h6>Share your profile:</h6>
                        <div class="d-flex justify-content-center mt-3">
                            <a href="https://wa.me/?text=Check out my profile: <?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . '/profile.php?user=' . $userData['username']); ?>" 
                               class="btn btn-sm btn-outline-success me-2" target="_blank">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . '/profile.php?user=' . $userData['username']); ?>" 
                               class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                <i class="fab fa-facebook"></i> Facebook
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . '/profile.php?user=' . $userData['username']); ?>&text=Check out my profile" 
                               class="btn btn-sm btn-outline-info me-2" target="_blank">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                            <button class="btn btn-sm btn-outline-secondary copy-link-btn" 
                                    data-clipboard-text="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/profile.php?user=' . $userData['username']; ?>">
                                <i class="fas fa-copy"></i> Copy Link
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="<?php echo $qrCodeUrl; ?>" class="btn btn-primary" download="sociallinks-qr.png">
                        <i class="fas fa-download me-2"></i> Download QR Code
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Link Modal -->
    <div class="modal fade" id="editLinkModal" tabindex="-1" aria-labelledby="editLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLinkModalLabel">Edit Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="link_id" id="edit_link_id">
                        <div class="mb-3">
                            <label for="edit_platform" class="form-label">Platform</label>
                            <select class="form-select" id="edit_platform" name="platform" required>
                                <option value="">Select a platform</option>
                                <option value="instagram">Instagram</option>
                                <option value="facebook">Facebook</option>
                                <option value="twitter">Twitter</option>
                                <option value="linkedin">LinkedIn</option>
                                <option value="github">GitHub</option>
                                <option value="youtube">YouTube</option>
                                <option value="tiktok">TikTok</option>
                                <option value="twitch">Twitch</option>
                                <option value="pinterest">Pinterest</option>
                                <option value="snapchat">Snapchat</option>
                                <option value="reddit">Reddit</option>
                                <option value="discord">Discord</option>
                                <option value="telegram">Telegram</option>
                                <option value="spotify">Spotify</option>
                                <option value="medium">Medium</option>
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                                <option value="website">Website</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_display_text" class="form-label">Display Text</label>
                            <input type="text" class="form-control" id="edit_display_text" name="display_text" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_url" class="form-label">URL</label>
                            <input type="url" class="form-control" id="edit_url" name="url" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="edit_category" name="category" list="edit-existing-categories">
                            <datalist id="edit-existing-categories">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_featured" name="is_featured">
                            <label class="form-check-label" for="edit_is_featured">
                                Mark as featured (display prominently)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_link" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="text-muted">Password must be at least 8 characters long</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Account Settings Modal -->
    <div class="modal fade" id="accountSettingsModal" tabindex="-1" aria-labelledby="accountSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="accountSettingsModalLabel">Account Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" id="accountSettingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">Notifications</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">Privacy</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="data-tab" data-bs-toggle="tab" data-bs-target="#data" type="button" role="tab">Data & Export</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="danger-tab" data-bs-toggle="tab" data-bs-target="#danger" type="button" role="tab">Danger Zone</button>
                        </li>
                    </ul>
                    
                    <!-- Tab content -->
                    <div class="tab-content p-3" id="accountSettingsContent">
                        <!-- Notifications Tab -->
                        <div class="tab-pane fade show active" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                            <div class="mb-4">
                                <h6>Email Notifications</h6>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="emailNotifNewVisitor" checked>
                                    <label class="form-check-label" for="emailNotifNewVisitor">New profile visitors</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="emailNotifLinkClicks" checked>
                                    <label class="form-check-label" for="emailNotifLinkClicks">Link clicks</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="emailNotifWeeklyReport" checked>
                                    <label class="form-check-label" for="emailNotifWeeklyReport">Weekly stats report</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="emailNotifSecurity">
                                    <label class="form-check-label" for="emailNotifSecurity">Security alerts</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="emailNotifNewFeatures" checked>
                                    <label class="form-check-label" for="emailNotifNewFeatures">New features and updates</label>
                                </div>
                            </div>
                            <button class="btn btn-primary">Save Notification Settings</button>
                        </div>
                        
                        <!-- Privacy Tab -->
                        <div class="tab-pane fade" id="privacy" role="tabpanel" aria-labelledby="privacy-tab">
                            <div class="mb-3">
                                <h6>Profile Visibility</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="profileVisibility" id="profilePublic" checked>
                                    <label class="form-check-label" for="profilePublic">
                                        Public - Anyone can view your profile
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="profileVisibility" id="profileUnlisted">
                                    <label class="form-check-label" for="profileUnlisted">
                                        Unlisted - Only people with the link can view your profile
                                    </label>
                                </div>
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="radio" name="profileVisibility" id="profilePrivate">
                                    <label class="form-check-label" for="profilePrivate">
                                        Private - Your profile is currently hidden
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6>Analytics & Tracking</h6>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="trackVisitors" checked>
                                    <label class="form-check-label" for="trackVisitors">Track profile visitors</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="trackLinkClicks" checked>
                                    <label class="form-check-label" for="trackLinkClicks">Track link clicks</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="collectReferrers" checked>
                                    <label class="form-check-label" for="collectReferrers">Collect referrer information</label>
                                </div>
                            </div>
                            
                            <button class="btn btn-primary">Save Privacy Settings</button>
                        </div>
                        
                        <!-- Data & Export Tab -->
                        <div class="tab-pane fade" id="data" role="tabpanel" aria-labelledby="data-tab">
                            <div class="mb-4">
                                <h6>Export Your Data</h6>
                                <p>Download all your SocialLinks data including profile information, links, and analytics.</p>
                                <div class="d-flex">
                                    <button class="btn btn-outline-primary me-2">
                                        <i class="fas fa-download me-2"></i> Export Profile Data
                                    </button>
                                    <button class="btn btn-outline-primary me-2">
                                        <i class="fas fa-chart-bar me-2"></i> Export Analytics Data
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6>Import Links</h6>
                                <p>Import links from CSV file or other platforms</p>
                                <div class="input-group mb-3">
                                    <input type="file" class="form-control" id="importLinks">
                                    <button class="btn btn-outline-primary" type="button">Import</button>
                                </div>
                                <small class="text-muted">Supported formats: CSV, JSON</small>
                            </div>
                        </div>
                        
                        <!-- Completing the Danger Zone Tab -->
<div class="tab-pane fade" id="danger" role="tabpanel" aria-labelledby="danger-tab">
    <div class="alert alert-warning">
        <h6 class="alert-heading">Warning: Danger Zone</h6>
        <p>Actions in this section can result in permanent data loss. Please proceed with caution.</p>
    </div>
    
    <div class="mb-4">
        <h6>Delete All Links</h6>
        <p>This will remove all your social links from your profile.</p>
        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteLinksModal">
            <i class="fas fa-trash-alt me-2"></i> Delete All Links
        </button>
    </div>
    
    <div class="mb-4">
        <h6>Reset Analytics Data</h6>
        <p>This will clear all your analytics data including page views and link clicks.</p>
        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmResetAnalyticsModal">
            <i class="fas fa-eraser me-2"></i> Reset Analytics
        </button>
    </div>
    
    <div class="mb-4">
        <h6>Delete Account</h6>
        <p>This will permanently delete your account and all associated data.</p>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteAccountModal">
            <i class="fas fa-user-slash me-2"></i> Delete Account
        </button>
    </div>
</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modals -->
    <!-- Delete All Links Confirmation Modal -->
    <div class="modal fade" id="confirmDeleteLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete All Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete all your links? This action cannot be undone.</p>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="confirmDeleteLinks" class="form-label">Type "DELETE" to confirm</label>
                            <input type="text" class="form-control" id="confirmDeleteLinks" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="deleteAllLinksBtn" disabled>Delete All Links</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Analytics Confirmation Modal -->
    <div class="modal fade" id="confirmResetAnalyticsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Reset Analytics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reset all your analytics data? This will clear all page views and link click statistics.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="">
                        <input type="hidden" name="reset_analytics" value="1">
                        <button type="submit" class="btn btn-danger">Reset Analytics</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Confirmation Modal -->
    <div class="modal fade" id="confirmDeleteAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Account Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> Warning: This action is permanent and cannot be undone.
                    </div>
                    <p>Deleting your account will:</p>
                    <ul>
                        <li>Remove all your profile information</li>
                        <li>Delete all your social links</li>
                        <li>Erase all analytics data</li>
                        <li>Make your username available for others to use</li>
                    </ul>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="password" class="form-label">Enter your password to confirm</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirmDeleteAccount" class="form-label">Type "DELETE MY ACCOUNT" to confirm</label>
                            <input type="text" class="form-control" id="confirmDeleteAccount" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="deleteAccountBtn" disabled>Delete My Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Modal -->
    <div class="modal fade" id="analyticsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detailed Analytics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="analyticsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Overview</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="traffic-tab" data-bs-toggle="tab" data-bs-target="#traffic" type="button" role="tab">Traffic Sources</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="link-performance-tab" data-bs-toggle="tab" data-bs-target="#link-performance" type="button" role="tab">Link Performance</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="geography-tab" data-bs-toggle="tab" data-bs-target="#geography" type="button" role="tab">Geography</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3" id="analyticsTabContent">
                        <!-- Overview Tab -->
                        <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Profile Views</h6>
                                    <div class="chart-container" style="position: relative; height:250px;">
                                        <canvas id="viewsChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Link Clicks</h6>
                                    <div class="chart-container" style="position: relative; height:250px;">
                                        <canvas id="clicksChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <h6>Quick Stats</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h3 class="mb-0"><?php echo $total_views; ?></h3>
                                            <small class="text-muted">Total Views</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <?php 
                                            $clickCountSQL = "SELECT COUNT(*) as count FROM link_clicks lc 
                                                          JOIN links l ON lc.link_id = l.link_id 
                                                          WHERE l.user_id = ?";
                                            $stmt = $conn->prepare($clickCountSQL);
                                            $stmt->bind_param("i", $user_id);
                                            $stmt->execute();
                                            $click_count = $stmt->get_result()->fetch_assoc()['count'];
                                            ?>
                                            <h3 class="mb-0"><?php echo $click_count; ?></h3>
                                            <small class="text-muted">Total Clicks</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <?php 
                                            $ctr = ($total_views > 0) ? round(($click_count / $total_views) * 100, 1) : 0;
                                            ?>
                                            <h3 class="mb-0"><?php echo $ctr; ?>%</h3>
                                            <small class="text-muted">CTR</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <?php 
                                            $uniqueVisitorsSQL = "SELECT COUNT(DISTINCT visitor_id) as count FROM page_views WHERE user_id = ?";
                                            $stmt = $conn->prepare($uniqueVisitorsSQL);
                                            $stmt->bind_param("i", $user_id);
                                            $stmt->execute();
                                            $unique_visitors = $stmt->get_result()->fetch_assoc()['count'];
                                            ?>
                                            <h3 class="mb-0"><?php echo $unique_visitors; ?></h3>
                                            <small class="text-muted">Unique Visitors</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Traffic Sources Tab -->
                        <div class="tab-pane fade" id="traffic" role="tabpanel" aria-labelledby="traffic-tab">
                            <div class="chart-container mb-4" style="position: relative; height:300px;">
                                <canvas id="referrersChart"></canvas>
                            </div>
                            
                            <h6>Top Referrers</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Source</th>
                                            <th>Views</th>
                                            <th>% of Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $referrerSQL = "SELECT referrer, COUNT(*) as count FROM page_views WHERE user_id = ? GROUP BY referrer ORDER BY count DESC LIMIT 10";
                                        $stmt = $conn->prepare($referrerSQL);
                                        $stmt->bind_param("i", $user_id);
                                        $stmt->execute();
                                        $referrers_result = $stmt->get_result();
                                        
                                        while ($referrer = $referrers_result->fetch_assoc()): 
                                            $percentage = ($total_views > 0) ? round(($referrer['count'] / $total_views) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo !empty($referrer['referrer']) ? htmlspecialchars($referrer['referrer']) : 'Direct'; ?></td>
                                                <td><?php echo $referrer['count']; ?></td>
                                                <td><?php echo $percentage; ?>%</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Link Performance Tab -->
                        <div class="tab-pane fade" id="link-performance" role="tabpanel" aria-labelledby="link-performance-tab">
                            <div class="chart-container mb-4" style="position: relative; height:300px;">
                                <canvas id="linkPerformanceChart"></canvas>
                            </div>
                            
                            <h6>Link Click Statistics</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Link</th>
                                            <th>Clicks</th>
                                            <th>% of Total</th>
                                            <th>Last Clicked</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $linkStatsSQL = "SELECT l.link_id, l.platform, l.display_text, COUNT(lc.click_id) as clicks, 
                                                      MAX(lc.clicked_at) as last_clicked 
                                                      FROM links l 
                                                      LEFT JOIN link_clicks lc ON l.link_id = lc.link_id 
                                                      WHERE l.user_id = ? 
                                                      GROUP BY l.link_id, l.platform, l.display_text 
                                                      ORDER BY clicks DESC";
                                        $stmt = $conn->prepare($linkStatsSQL);
                                        $stmt->bind_param("i", $user_id);
                                        $stmt->execute();
                                        $link_stats = $stmt->get_result();
                                        
                                        while ($link = $link_stats->fetch_assoc()): 
                                            $percentage = ($click_count > 0) ? round(($link['clicks'] / $click_count) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="platform-icon me-2">
                                                            <i class="fab fa-<?php echo $link['platform']; ?>"></i>
                                                        </div>
                                                        <?php echo htmlspecialchars($link['display_text']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo $link['clicks']; ?></td>
                                                <td><?php echo $percentage; ?>%</td>
                                                <td>
                                                    <?php 
                                                    if (!empty($link['last_clicked'])) {
                                                        $last_clicked = new DateTime($link['last_clicked']);
                                                        echo $last_clicked->format('M j, Y g:i A');
                                                    } else {
                                                        echo 'Never';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Geography Tab -->
                        <div class="tab-pane fade" id="geography" role="tabpanel" aria-labelledby="geography-tab">
                            <div class="world-map-container mb-4" style="position: relative; height:300px;">
                                <div id="worldMap"></div>
                            </div>
                            
                            <h6>Visitor Locations</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Country</th>
                                            <th>Visitors</th>
                                            <th>% of Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $geoSQL = "SELECT country, COUNT(*) as count FROM page_views WHERE user_id = ? AND country IS NOT NULL GROUP BY country ORDER BY count DESC LIMIT 10";
                                        $stmt = $conn->prepare($geoSQL);
                                        $stmt->bind_param("i", $user_id);
                                        $stmt->execute();
                                        $geo_stats = $stmt->get_result();
                                        
                                        while ($geo = $geo_stats->fetch_assoc()): 
                                            $percentage = ($total_views > 0) ? round(($geo['count'] / $total_views) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($geo['country']); ?></td>
                                                <td><?php echo $geo['count']; ?></td>
                                                <td><?php echo $percentage; ?>%</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="exportAnalyticsBtn">
                        <i class="fas fa-download me-2"></i> Export Analytics
                    </button>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-4 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2023 SocialLinks. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-muted me-3">Privacy Policy</a>
                    <a href="#" class="text-muted me-3">Terms of Service</a>
                    <a href="#" class="text-muted">Contact Us</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest"></script>
    <script>
        // Initialize clipboard.js
        new ClipboardJS('.copy-link-btn').on('success', function(e) {
            alert('Link copied to clipboard!');
        });
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Handle edit link modal
        document.querySelectorAll('.edit-link-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Get data attributes
                const id = this.dataset.id;
                const platform = this.dataset.platform;
                const text = this.dataset.text;
                const url = this.dataset.url;
                const category = this.dataset.category;
                const featured = this.dataset.featured;
                
                // Set modal form values
                document.getElementById('edit_link_id').value = id;
                document.getElementById('edit_platform').value = platform;
                document.getElementById('edit_display_text').value = text;
                document.getElementById('edit_url').value = url;
                document.getElementById('edit_category').value = category;
                document.getElementById('edit_is_featured').checked = (featured === '1');
            });
        });
        
        // Handle select all checkboxes
        document.getElementById('selectAllLinks').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.link-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            toggleBatchActions();
        });
        
        // Show/hide batch actions
        document.querySelectorAll('.link-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', toggleBatchActions);
        });
        
        function toggleBatchActions() {
            const checkboxes = document.querySelectorAll('.link-checkbox:checked');
            const batchActions = document.querySelector('.batch-actions');
            
            if (checkboxes.length > 0) {
                batchActions.style.display = 'block';
            } else {
                batchActions.style.display = 'none';
            }
        }
        
        // Toggle profile visibility
        document.getElementById('toggleProfileVisibility').addEventListener('change', function() {
            const isPublic = this.checked;
            document.getElementById('is_public').checked = isPublic;
            this.nextElementSibling.textContent = `Profile is ${isPublic ? 'Public' : 'Private'}`;
            
            // Update via AJAX
            fetch('update_profile_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `is_public=${isPublic ? 1 : 0}`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Profile visibility updated successfully');
                } else {
                    console.error('Failed to update profile visibility');
                }
            });
        });
        
        // Filter links by category
        document.getElementById('categoryFilter').addEventListener('change', function() {
            const selectedCategory = this.value;
            const links = document.querySelectorAll('.link-row');
            
            links.forEach(link => {
                if (!selectedCategory || link.classList.contains(`category-${selectedCategory}`)) {
                    link.style.display = '';
                } else {
                    link.style.display = 'none';
                }
            });
        });
        
        // Search links
        document.getElementById('searchLinks').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const links = document.querySelectorAll('.link-row');
            
            links.forEach(link => {
                const text = link.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    link.style.display = '';
                } else {
                    link.style.display = 'none';
                }
            });
        });
        
        // Make links sortable
        $(function() {
            $("#sortable-links").sortable({
                handle: ".platform-icon",
                update: function(event, ui) {
                    const linkIds = [];
                    $("#sortable-links tr").each(function() {
                        linkIds.push($(this).data("id"));
                    });
                    
                    // Update order via AJAX
                    $.post("update_link_order.php", {
                        link_ids: linkIds
                    }, function(response) {
                        console.log("Order updated");
                    });
                }
            });
        });
        
        // Handle delete confirmations
        document.getElementById('confirmDeleteLinks').addEventListener('input', function() {
            document.getElementById('deleteAllLinksBtn').disabled = (this.value !== 'DELETE');
        });
        
        document.getElementById('confirmDeleteAccount').addEventListener('input', function() {
            document.getElementById('deleteAccountBtn').disabled = (this.value !== 'DELETE MY ACCOUNT');
        });
        
        // Initialize charts for analytics
        document.addEventListener('DOMContentLoaded', function() {
            // Views Chart
            const viewsCtx = document.getElementById('viewsChart').getContext('2d');
            const viewsChart = new Chart(viewsCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                    datasets: [{
                        label: 'Profile Views',
                        data: [65, 59, 80, 81, 56, 55, 40],
                        fill: false,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Clicks Chart
            const clicksCtx = document.getElementById('clicksChart').getContext('2d');
            const clicksChart = new Chart(clicksCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                    datasets: [{
                        label: 'Link Clicks',
                        data: [28, 48, 40, 19, 86, 27, 90],
                        fill: false,
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Referrers Chart
            const referrersCtx = document.getElementById('referrersChart').getContext('2d');
            const referrersChart = new Chart(referrersCtx, {
                type: 'pie',
                data: {
                    labels: ['Direct', 'Facebook', 'Instagram', 'Twitter', 'Others'],
                    datasets: [{
                        label: 'Traffic Sources',
                        data: [12, 19, 3, 5, 2],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Link Performance Chart
            const linkPerformanceCtx = document.getElementById('linkPerformanceChart').getContext('2d');
            const linkPerformanceChart = new Chart(linkPerformanceCtx, {
                type: 'bar',
                data: {
                    labels: ['Instagram', 'Twitter', 'Facebook', 'LinkedIn', 'GitHub', 'Other'],
                    datasets: [{
                        label: 'Click Count',
                        data: [45, 29, 18, 15, 12, 8],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 159, 64, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>