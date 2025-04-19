<?php
// dashboard.php - User dashboard
session_start();
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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

// Process link submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_link'])) {
    $platform = $_POST['platform'];
    $url = $_POST['url'];
    $display_text = $_POST['display_text'];
    $icon = $_POST['platform'];  // Using platform name as icon identifier
    
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
        // Refresh the page to show the updated links
        header("Location: dashboard.php");
        exit;
    }
}

// Process link deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_link'])) {
    $link_id = $_POST['link_id'];
    
    $deleteSQL = "DELETE FROM links WHERE link_id = ? AND user_id = ?";
    $stmt = $conn->prepare($deleteSQL);
    $stmt->bind_param("ii", $link_id, $user_id);
    
    if ($stmt->execute()) {
        // Refresh the page to show the updated links
        header("Location: dashboard.php");
        exit;
    }
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $bio = $_POST['bio'];
    $theme = $_POST['theme'];
    
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
    
    $updateSQL = "UPDATE users SET bio = ?, theme = ?, profile_image = ? WHERE user_id = ?";
    $stmt = $conn->prepare($updateSQL);
    $stmt->bind_param("sssi", $bio, $theme, $profile_image, $user_id);
    
    if ($stmt->execute()) {
        // Refresh the page to show the updated profile
        header("Location: dashboard.php");
        exit;
    }
}

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
            /* height: 100%; */
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
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
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
        <div class="row">
            <div class="col-lg-4">
                <div class="profile-card mb-4">
                    <div class="text-center">
                        <img src="<?php echo !empty($userData['profile_image']) ? $userData['profile_image'] : 'https://via.placeholder.com/150'; ?>" 
                             class="profile-image mb-4 floating" alt="Profile Image">
                        <h3><?php echo htmlspecialchars($userData['username']); ?></h3>
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
                    </div>
                </div>
                
                <div class="profile-card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
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
                                </select>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i> Update Profile
                            </button>
                        </form>
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
                                <div class="col-md-4 mb-3">
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
                                        <option value="website">Website</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="display_text" class="form-label">Display Text</label>
                                    <input type="text" class="form-control" id="display_text" name="display_text" placeholder="Follow me on Instagram" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="url" class="form-label">URL</label>
                                    <input type="url" class="form-control" id="url" name="url" placeholder="https://" required>
                                </div>
                            </div>
                            <button type="submit" name="add_link" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i> Add Link
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="my-links-card">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">My Links</h5>
                        <span class="badge bg-primary"><?php echo $links->num_rows; ?> Links</span>
                    </div>
                    <div class="card-body">
                        <?php if ($links->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Platform</th>
                                            <th>Display Text</th>
                                            <th>URL</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sortable-links">
                                        <?php while ($link = $links->fetch_assoc()): ?>
                                            <tr data-id="<?php echo $link['link_id']; ?>">
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
                                <td>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="link_id" value="<?php echo $link['link_id']; ?>">
                                        <button type="submit" name="delete_link" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this link?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
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

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <a href="index.php" class="footer-logo">Social<span>Links</span></a>
                <div class="footer-links">
                    <a href="#">About Us</a>
                    <a href="#">Features</a>
                    <a href="#">Pricing</a>
                    <a href="#">Support</a>
                    <a href="#">Terms</a>
                    <a href="#">Privacy</a>
                </div>
                <div class="social-links">
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="copyright">
                <p>© 2025 SocialLinks. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>
    <script>
        // Initialize drag-and-drop functionality for reordering links
        $(function() {
            $("#sortable-links").sortable({
                update: function(event, ui) {
                    // Get the new order of link IDs
                    const linkIds = $(this).sortable("toArray", { attribute: "data-id" });
                    
                    // Send the new order to the server via AJAX
                    $.ajax({
                        url: "update_link_order.php",
                        method: "POST",
                        data: { link_order: linkIds },
                        success: function(response) {
                            console.log("Link order updated successfully");
                        },
                        error: function(error) {
                            console.error("Error updating link order", error);
                        }
                    });
                }
            });
            $("#sortable-links").disableSelection();
        });
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.padding = '10px 0';
                navbar.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
            } else {
                navbar.style.padding = '15px 0';
                navbar.style.boxShadow = '0 2px 15px rgba(0, 0, 0, 0.1)';
            }
        });
        
        // Preview profile image upon selection
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-image').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>