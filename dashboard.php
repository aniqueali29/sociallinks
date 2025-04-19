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

// Get link click analytics
$linkClicksSQL = "SELECT 
                    l.platform, 
                    l.display_text,
                    COUNT(lc.click_id) as click_count 
                  FROM links l
                  LEFT JOIN link_clicks lc ON l.link_id = lc.link_id
                  WHERE l.user_id = ?
                  GROUP BY l.link_id
                  ORDER BY click_count DESC";
$stmt = $conn->prepare($linkClicksSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$linkClicks = $stmt->get_result();

// Get analytics data
$analyticsSQL = "SELECT 
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT visitor_ip) as unique_visitors,
                    DATE(visit_time) as visit_date,
                    COUNT(*) as daily_visits
                  FROM profile_visits 
                  WHERE user_id = ?
                  GROUP BY DATE(visit_time)
                  ORDER BY visit_date DESC
                  LIMIT 30";
$stmt = $conn->prepare($analyticsSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$analyticsResult = $stmt->get_result();

// Prepare analytics data for charts
$dates = [];
$visitCounts = [];
$totalVisits = 0;
$uniqueVisitors = 0;

while ($row = $analyticsResult->fetch_assoc()) {
    $dates[] = date('M d', strtotime($row['visit_date']));
    $visitCounts[] = $row['daily_visits'];
    $totalVisits += $row['daily_visits'];
    if (isset($row['unique_visitors'])) {
        $uniqueVisitors = $row['unique_visitors'];
    }
}

// Get analytics data - updated query
$analyticsSQL = "SELECT 
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT visitor_ip) as unique_visitors,
                    DATE(visit_time) as visit_date,
                    COUNT(*) as daily_visits
                  FROM profile_visits 
                  WHERE user_id = ?
                  GROUP BY DATE(visit_time)
                  ORDER BY visit_date DESC
                  LIMIT 30";

// New query for geographic data
$geoAnalyticsSQL = "SELECT 
                      country, 
                      COUNT(*) as visit_count 
                    FROM profile_visits 
                    WHERE user_id = ? AND country IS NOT NULL
                    GROUP BY country 
                    ORDER BY visit_count DESC 
                    LIMIT 10";
$stmt = $conn->prepare($geoAnalyticsSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$countryData = $stmt->get_result();

// City analytics
$cityAnalyticsSQL = "SELECT 
                      city, 
                      country,
                      COUNT(*) as visit_count 
                    FROM profile_visits 
                    WHERE user_id = ? AND city IS NOT NULL
                    GROUP BY city, country
                    ORDER BY visit_count DESC 
                    LIMIT 10";
$stmt = $conn->prepare($cityAnalyticsSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cityData = $stmt->get_result();

// Device analytics
$deviceAnalyticsSQL = "SELECT 
                        device_type, 
                        COUNT(*) as visit_count 
                      FROM profile_visits 
                      WHERE user_id = ? AND device_type IS NOT NULL
                      GROUP BY device_type
                      ORDER BY visit_count DESC";
$stmt = $conn->prepare($deviceAnalyticsSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deviceData = $stmt->get_result();

// Browser analytics
$browserAnalyticsSQL = "SELECT 
                         browser, 
                         COUNT(*) as visit_count 
                       FROM profile_visits 
                       WHERE user_id = ? AND browser IS NOT NULL
                       GROUP BY browser
                       ORDER BY visit_count DESC";
$stmt = $conn->prepare($browserAnalyticsSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$browserData = $stmt->get_result();

// Referrer analytics
$referrerAnalyticsSQL = "SELECT 
                          referrer,
                          COUNT(*) as visit_count 
                        FROM profile_visits 
                        WHERE user_id = ? AND referrer IS NOT NULL
                        GROUP BY referrer
                        ORDER BY visit_count DESC
                        LIMIT 5";
$stmt = $conn->prepare($referrerAnalyticsSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$referrerData = $stmt->get_result();
 
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

// Process link layout update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_layout'])) {
    $layout_style = $_POST['layout_style'];
    
    $updateSQL = "UPDATE users SET links_layout = ? WHERE user_id = ?";
    $stmt = $conn->prepare($updateSQL);
    $stmt->bind_param("si", $layout_style, $user_id);
    
    if ($stmt->execute()) {
        // Refresh the page to show the updated layout
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="./css/dashboard.css">
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
                        <a class="nav-link" href="profile.php?user=<?php echo $userData['username']; ?>"
                            target="_blank">View My Page</a>
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
                    <h1 class="animate__animated animate__fadeIn">Welcome back,
                        <?php echo htmlspecialchars($userData['username']); ?>!</h1>
                    <p class="animate__animated animate__fadeIn animate__delay-1s">Manage your social links and
                        customize your profile from your personal dashboard.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="profile.php?user=<?php echo $userData['username']; ?>" class="btn btn-light mt-3"
                        target="_blank">
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
                            <a href="profile.php?user=<?php echo $userData['username']; ?>" class="btn btn-primary me-2"
                                target="_blank">
                                <i class="fas fa-external-link-alt me-1"></i> View Profile
                            </a>
                            <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#qrCodeModal">
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
                                <textarea class="form-control" id="bio" name="bio" rows="3"
                                    placeholder="Tell visitors about yourself..."><?php echo htmlspecialchars($userData['bio']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="theme" class="form-label">Theme</label>
                                <select class="form-select" id="theme" name="theme">
                                    <option value="default"
                                        <?php echo $userData['theme'] === 'default' ? 'selected' : ''; ?>>Default
                                    </option>
                                    <option value="dark" <?php echo $userData['theme'] === 'dark' ? 'selected' : ''; ?>>
                                        Dark</option>
                                    <option value="light"
                                        <?php echo $userData['theme'] === 'light' ? 'selected' : ''; ?>>Light</option>
                                    <option value="colorful"
                                        <?php echo $userData['theme'] === 'colorful' ? 'selected' : ''; ?>>Colorful
                                    </option>
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
                                    <input type="text" class="form-control" id="display_text" name="display_text"
                                        placeholder="Follow me on Instagram" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="url" class="form-label">URL</label>
                                    <input type="url" class="form-control" id="url" name="url" placeholder="https://"
                                        required>
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
                                            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank"
                                                class="text-truncate d-inline-block" style="max-width: 150px;">
                                                <?php echo htmlspecialchars($link['url']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="link_id"
                                                    value="<?php echo $link['link_id']; ?>">
                                                <button type="submit" name="delete_link" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to delete this link?')">
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

        <!-- Analytics Overview Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="analytics-card">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Analytics Overview</h5>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                            data-bs-target="#analyticsDetailsModal">
                            <i class="fas fa-chart-line me-1"></i> View Detailed Analytics
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="stat-card">
                                    <div class="stat-icon"
                                        style="background: rgba(108, 99, 255, 0.1); color: var(--primary-color);">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $totalVisits; ?></div>
                                    <div class="stat-label">Total Profile Views</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: rgba(40, 199, 111, 0.1); color: #28c76f;">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $uniqueVisitors; ?></div>
                                    <div class="stat-label">Unique Visitors</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stat-card">
                                    <div class="stat-icon"
                                        style="background: rgba(255, 101, 132, 0.1); color: var(--secondary-color);">
                                        <i class="fas fa-mouse-pointer"></i>
                                    </div>
                                    <?php
                                    // Get total link clicks
                                    $totalClicksSQL = "SELECT COUNT(*) as total_clicks FROM link_clicks WHERE user_id = ?";
                                    $stmt = $conn->prepare($totalClicksSQL);
                                    $stmt->bind_param("i", $user_id);
                                    $stmt->execute();
                                    $totalClicks = $stmt->get_result()->fetch_assoc()['total_clicks'];
                                    ?>
                                    <div class="stat-value"><?php echo $totalClicks; ?></div>
                                    <div class="stat-label">Total Link Clicks</div>
                                </div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="visitsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Links Layout Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="analytics-card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Social Links Layout</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="layout-option <?php echo ($userData['links_layout'] == 'list' || $userData['links_layout'] == '') ? 'active' : ''; ?>"
                                        data-layout="list">
                                        <div class="layout-preview">
                                            <div class="layout-list">
                                                <div class="layout-item"></div>
                                                <div class="layout-item"></div>
                                                <div class="layout-item"></div>
                                            </div>
                                        </div>
                                        <h6 class="mt-2 mb-1">List Layout</h6>
                                        <p class="small text-muted">Links are displayed in a vertical list</p>
                                        <input type="radio" name="layout_style" value="list" class="d-none"
                                            <?php echo ($userData['links_layout'] == 'list' || $userData['links_layout'] == '') ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="layout-option <?php echo $userData['links_layout'] == 'grid' ? 'active' : ''; ?>"
                                        data-layout="grid">
                                        <div class="layout-preview">
                                            <div class="layout-grid">
                                                <div class="layout-item"></div>
                                                <div class="layout-item"></div>
                                                <div class="layout-item"></div>
                                                <div class="layout-item"></div>
                                            </div>
                                        </div>
                                        <h6 class="mt-2 mb-1">Grid Layout</h6>
                                        <p class="small text-muted">Links are displayed in a 2x2 grid</p>
                                        <input type="radio" name="layout_style" value="grid" class="d-none"
                                            <?php echo $userData['links_layout'] == 'grid' ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="layout-option <?php echo $userData['links_layout'] == 'buttons' ? 'active' : ''; ?>"
                                        data-layout="buttons">
                                        <div class="layout-preview">
                                            <div class="layout-buttons">
                                                <div class="layout-item"></div>
                                                <div class="layout-item"></div>
                                                <div class="layout-item"></div>
                                            </div>
                                        </div>
                                        <h6 class="mt-2 mb-1">Button Layout</h6>
                                        <p class="small text-muted">Links are displayed as rounded buttons</p>
                                        <input type="radio" name="layout_style" value="buttons" class="d-none"
                                            <?php echo $userData['links_layout'] == 'buttons' ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="update_layout" class="btn btn-primary mt-3">
                                <i class="fas fa-save me-2"></i> Save Layout Preference
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>


    </div>

    <!-- QR Code Modal -->
    <div class="modal fade qr-modal" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel"
        aria-hidden="true">
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
                    <p class="text-center mt-4 text-muted">Share your profile with anyone by showing them this QR code.
                    </p>
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

    <!-- Analytics Details Modal -->
    <!-- Analytics Details Modal -->
    <div class="modal fade analytics-details-modal" id="analyticsDetailsModal" tabindex="-1"
        aria-labelledby="analyticsDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="analyticsDetailsModalLabel">Detailed Analytics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-4" id="analyticsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="visits-tab" data-bs-toggle="tab"
                                data-bs-target="#visits" type="button" role="tab" aria-controls="visits"
                                aria-selected="true">Profile Visits</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="geo-tab" data-bs-toggle="tab" data-bs-target="#geo"
                                type="button" role="tab" aria-controls="geo" aria-selected="false">Geography</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tech-tab" data-bs-toggle="tab" data-bs-target="#tech"
                                type="button" role="tab" aria-controls="tech" aria-selected="false">Devices &
                                Browsers</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="clicks-tab" data-bs-toggle="tab" data-bs-target="#clicks"
                                type="button" role="tab" aria-controls="clicks" aria-selected="false">Link
                                Clicks</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="analyticsTabContent">
                        <!-- Profile Visits Tab -->
                        <div class="tab-pane fade show active" id="visits" role="tabpanel" aria-labelledby="visits-tab">
                            <h6 class="mb-3">Daily Profile Visits (Last 30 Days)</h6>
                            <div class="chart-container">
                                <canvas id="detailedVisitsChart"></canvas>
                            </div>

                            <h6 class="mt-4 mb-3">Visit Statistics</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="stat-card">
                                        <h5 class="text-primary">Total Views</h5>
                                        <h3><?php echo $totalVisits; ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="stat-card">
                                        <h5 class="text-success">Unique Visitors</h5>
                                        <h3><?php echo $uniqueVisitors; ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="stat-card">
                                        <h5 class="text-info">Average Daily Views</h5>
                                        <h3><?php echo count($dates) > 0 ? round($totalVisits / count($dates), 1) : 0; ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>

                            <h6 class="mt-4 mb-3">Traffic Sources</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Referrer</th>
                                            <th>Visits</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                    if ($referrerData && $referrerData->num_rows > 0) {
                                        while ($row = $referrerData->fetch_assoc()): 
                                            $percentage = ($row['visit_count'] / $totalVisits) * 100;
                                    ?>
                                        <tr>
                                            <td>
                                                <?php echo $row['referrer'] ? htmlspecialchars($row['referrer']) : 'Direct'; ?>
                                            </td>
                                            <td><?php echo $row['visit_count']; ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar" role="progressbar"
                                                        style="width: <?php echo $percentage; ?>%;"
                                                        aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0"
                                                        aria-valuemax="100">
                                                        <?php echo round($percentage, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php 
                                        endwhile;
                                    } else {
                                    ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No referrer data available</td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Geographic Data Tab -->
                        <div class="tab-pane fade" id="geo" role="tabpanel" aria-labelledby="geo-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Top Countries</h6>
                                    <div class="chart-container">
                                        <canvas id="countryChart"></canvas>
                                    </div>

                                    <div class="table-responsive mt-4">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Country</th>
                                                    <th>Visits</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                            if ($countryData && $countryData->num_rows > 0) {
                                                mysqli_data_seek($countryData, 0);
                                                while ($row = $countryData->fetch_assoc()): 
                                                    $percentage = ($row['visit_count'] / $totalVisits) * 100;
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['country']); ?></td>
                                                    <td><?php echo $row['visit_count']; ?></td>
                                                    <td>
                                                        <div class="progress">
                                                            <div class="progress-bar bg-primary" role="progressbar"
                                                                style="width: <?php echo $percentage; ?>%;"
                                                                aria-valuenow="<?php echo $percentage; ?>"
                                                                aria-valuemin="0" aria-valuemax="100">
                                                                <?php echo round($percentage, 1); ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php 
                                                endwhile;
                                            } else {
                                            ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No country data available</td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="mb-3">Top Cities</h6>
                                    <div class="chart-container">
                                        <canvas id="cityChart"></canvas>
                                    </div>

                                    <div class="table-responsive mt-4">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>City</th>
                                                    <th>Country</th>
                                                    <th>Visits</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                            if ($cityData && $cityData->num_rows > 0) {
                                                mysqli_data_seek($cityData, 0);
                                                while ($row = $cityData->fetch_assoc()): 
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['city']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['country']); ?></td>
                                                    <td>
                                                        <span
                                                            class="badge bg-primary"><?php echo $row['visit_count']; ?></span>
                                                    </td>
                                                </tr>
                                                <?php 
                                                endwhile;
                                            } else {
                                            ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No city data available</td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Technology Tab -->
                        <div class="tab-pane fade" id="tech" role="tabpanel" aria-labelledby="tech-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Device Types</h6>
                                    <div class="chart-container">
                                        <canvas id="deviceChart"></canvas>
                                    </div>

                                    <div class="table-responsive mt-4">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Device Type</th>
                                                    <th>Visits</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                            if ($deviceData && $deviceData->num_rows > 0) {
                                                mysqli_data_seek($deviceData, 0);
                                                while ($row = $deviceData->fetch_assoc()): 
                                                    $percentage = ($row['visit_count'] / $totalVisits) * 100;
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['device_type']); ?></td>
                                                    <td><?php echo $row['visit_count']; ?></td>
                                                    <td>
                                                        <div class="progress">
                                                            <div class="progress-bar bg-success" role="progressbar"
                                                                style="width: <?php echo $percentage; ?>%;"
                                                                aria-valuenow="<?php echo $percentage; ?>"
                                                                aria-valuemin="0" aria-valuemax="100">
                                                                <?php echo round($percentage, 1); ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php 
                                                endwhile;
                                            } else {
                                            ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No device data available</td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="mb-3">Browsers</h6>
                                    <div class="chart-container">
                                        <canvas id="browserChart"></canvas>
                                    </div>

                                    <div class="table-responsive mt-4">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Browser</th>
                                                    <th>Visits</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                            if ($browserData && $browserData->num_rows > 0) {
                                                mysqli_data_seek($browserData, 0);
                                                while ($row = $browserData->fetch_assoc()): 
                                                    $percentage = ($row['visit_count'] / $totalVisits) * 100;
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['browser']); ?></td>
                                                    <td><?php echo $row['visit_count']; ?></td>
                                                    <td>
                                                        <div class="progress">
                                                            <div class="progress-bar bg-info" role="progressbar"
                                                                style="width: <?php echo $percentage; ?>%;"
                                                                aria-valuenow="<?php echo $percentage; ?>"
                                                                aria-valuemin="0" aria-valuemax="100">
                                                                <?php echo round($percentage, 1); ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php 
                                                endwhile;
                                            } else {
                                            ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No browser data available</td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Link Clicks Tab (keep your existing one) -->
                        <div class="tab-pane fade" id="clicks" role="tabpanel" aria-labelledby="clicks-tab">
                            <h6 class="mb-3">Link Click Performance</h6>
                            <div class="chart-container">
                                <canvas id="linkClicksChart"></canvas>
                            </div>

                            <h6 class="mt-4 mb-3">Most Clicked Links</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Platform</th>
                                            <th>Link</th>
                                            <th>Clicks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                    // Reset the link clicks result pointer
                                    if ($linkClicks) {
                                        mysqli_data_seek($linkClicks, 0);
                                        while ($linkClick = $linkClicks->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="platform-icon">
                                                        <i class="fab fa-<?php echo $linkClick['platform']; ?>"></i>
                                                    </div>
                                                    <?php echo ucfirst($linkClick['platform']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($linkClick['display_text']); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $linkClick['click_count']; ?>
                                                    clicks</span>
                                            </td>
                                        </tr>
                                        <?php 
                                        endwhile;
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-outline-primary me-2">
                        <i class="fas fa-file-export me-1"></i> Export Data
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                const linkIds = $(this).sortable("toArray", {
                    attribute: "data-id"
                });

                // Send the new order to the server via AJAX
                $.ajax({
                    url: "update_link_order.php",
                    method: "POST",
                    data: {
                        link_order: linkIds
                    },
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

    // Layout option selection
    document.querySelectorAll('.layout-option').forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            document.querySelectorAll('.layout-option').forEach(opt => {
                opt.classList.remove('active');
            });

            // Add active class to the clicked option
            this.classList.add('active');

            // Check the corresponding radio button
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
        });
    });

    // Initialize analytics charts
    document.addEventListener('DOMContentLoaded', function() {
        // Data for charts
        const dates = <?php echo json_encode($dates); ?>;
        const visitCounts = <?php echo json_encode($visitCounts); ?>;

        // Main dashboard visits chart
        const visitsCtx = document.getElementById('visitsChart').getContext('2d');
        new Chart(visitsCtx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Profile Visits',
                    data: visitCounts,
                    backgroundColor: 'rgba(108, 99, 255, 0.2)',
                    borderColor: 'rgba(108, 99, 255, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    pointBackgroundColor: 'white',
                    pointBorderColor: 'rgba(108, 99, 255, 1)',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Detailed visits chart in modal
        const detailedVisitsCtx = document.getElementById('detailedVisitsChart').getContext('2d');
        new Chart(detailedVisitsCtx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Daily Visits',
                    data: visitCounts,
                    backgroundColor: 'rgba(108, 99, 255, 0.7)',
                    borderRadius: 5
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

        // Link clicks chart
        const linkClicksCtx = document.getElementById('linkClicksChart').getContext('2d');

        <?php
            // Prepare data for link clicks chart
            $platforms = [];
            $clickCounts = [];
            
            if ($linkClicks) {
                mysqli_data_seek($linkClicks, 0);
                while ($row = $linkClicks->fetch_assoc()) {
                    $platforms[] = ucfirst($row['platform']);
                    $clickCounts[] = $row['click_count'];
                }
            }
            ?>

        const platforms = <?php echo json_encode($platforms); ?>;
        const clickCounts = <?php echo json_encode($clickCounts); ?>;

        new Chart(linkClicksCtx, {
            type: 'doughnut',
            data: {
                labels: platforms,
                datasets: [{
                    data: clickCounts,
                    backgroundColor: [
                        'rgba(108, 99, 255, 0.7)',
                        'rgba(255, 101, 132, 0.7)',
                        'rgba(40, 199, 111, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Country chart data preparation
        <?php
        $countries = [];
        $countryVisits = [];

        if ($countryData) {
            mysqli_data_seek($countryData, 0);
            while ($row = $countryData->fetch_assoc()) {
                $countries[] = $row['country'];
                $countryVisits[] = $row['visit_count'];
            }
        }
        ?>

        const countries = <?php echo json_encode($countries); ?>;
        const countryVisits = <?php echo json_encode($countryVisits); ?>;

        // City chart data preparation
        <?php
        $cities = [];
        $cityVisits = [];

        if ($cityData) {
            mysqli_data_seek($cityData, 0);
            while ($row = $cityData->fetch_assoc()) {
                $cities[] = $row['city'] . ', ' . $row['country'];
                $cityVisits[] = $row['visit_count'];
            }
        }
        ?>

        const cities = <?php echo json_encode($cities); ?>;
        const cityVisits = <?php echo json_encode($cityVisits); ?>;

        // Device chart data preparation
        <?php
        $devices = [];
        $deviceVisits = [];

        if ($deviceData) {
            mysqli_data_seek($deviceData, 0);
            while ($row = $deviceData->fetch_assoc()) {
                $devices[] = $row['device_type'];
                $deviceVisits[] = $row['visit_count'];
            }
        }
        ?>

        const devices = <?php echo json_encode($devices); ?>;
        const deviceVisits = <?php echo json_encode($deviceVisits); ?>;

        // Browser chart data preparation
        <?php
        $browsers = [];
        $browserVisits = [];

        if ($browserData) {
            mysqli_data_seek($browserData, 0);
            while ($row = $browserData->fetch_assoc()) {
                $browsers[] = $row['browser'];
                $browserVisits[] = $row['visit_count'];
            }
        }
        ?>

        const browsers = <?php echo json_encode($browsers); ?>;
        const browserVisits = <?php echo json_encode($browserVisits); ?>;

        // Initialize country chart
        if (document.getElementById('countryChart')) {
            const countryCtx = document.getElementById('countryChart').getContext('2d');
            new Chart(countryCtx, {
                type: 'bar',
                data: {
                    labels: countries,
                    datasets: [{
                        label: 'Visits by Country',
                        data: countryVisits,
                        backgroundColor: 'rgba(108, 99, 255, 0.7)',
                        borderRadius: 5
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Initialize city chart
        if (document.getElementById('cityChart')) {
            const cityCtx = document.getElementById('cityChart').getContext('2d');
            new Chart(cityCtx, {
                type: 'bar',
                data: {
                    labels: cities,
                    datasets: [{
                        label: 'Visits by City',
                        data: cityVisits,
                        backgroundColor: 'rgba(255, 101, 132, 0.7)',
                        borderRadius: 5
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Initialize device chart
        if (document.getElementById('deviceChart')) {
            const deviceCtx = document.getElementById('deviceChart').getContext('2d');
            new Chart(deviceCtx, {
                type: 'doughnut',
                data: {
                    labels: devices,
                    datasets: [{
                        data: deviceVisits,
                        backgroundColor: [
                            'rgba(108, 99, 255, 0.7)',
                            'rgba(40, 199, 111, 0.7)',
                            'rgba(255, 159, 64, 0.7)',
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Initialize browser chart
        if (document.getElementById('browserChart')) {
            const browserCtx = document.getElementById('browserChart').getContext('2d');
            new Chart(browserCtx, {
                type: 'pie',
                data: {
                    labels: browsers,
                    datasets: [{
                        data: browserVisits,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 101, 132, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
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