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

// Process username change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_username'])) {
    $new_username = trim($_POST['new_username']);
    $can_change = true;
    $error_message = "";
    
    // Check if username is already taken
    $checkUsernameSQL = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
    $stmt = $conn->prepare($checkUsernameSQL);
    $stmt->bind_param("si", $new_username, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $can_change = false;
        $error_message = "Username already exists. Please choose a different one.";
    } else {
        // Check if user has changed username recently
        if ($lastUsernameChange) {
            $lastChangeDate = new DateTime($lastUsernameChange['change_date']);
            $currentDate = new DateTime();
            $daysSinceChange = $currentDate->diff($lastChangeDate)->days;
            
            if ($daysSinceChange < 14) {
                $can_change = false;
                $error_message = "You can only change your username once every 14 days. Please wait " . (14 - $daysSinceChange) . " more days.";
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
                
                // Refresh the page to show the updated username
                header("Location: dashboard.php?username_updated=1");
                exit;
            }
        }
    }
}

// Add this variable to use in the HTML part
$canChangeUsername = true;
$daysUntilNextChange = 0;

if ($lastUsernameChange) {
    $lastChangeDate = new DateTime($lastUsernameChange['change_date']);
    $currentDate = new DateTime();
    $daysSinceChange = $currentDate->diff($lastChangeDate)->days;
    
    if ($daysSinceChange < 14) {
        $canChangeUsername = false;
        $daysUntilNextChange = 14 - $daysSinceChange;
    }
}

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

// Generate QR code URL
$qrCodeUrl = "generate_qr.php?user=" . urlencode($userData['username']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage your social links and profile on SocialLinks">
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
    <!-- Notification styles -->
    <style>
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background-color: #4CAF50;
        color: white;
        border-radius: 12px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        z-index: 9999;
        opacity: 0;
        transform: translateY(-20px);
        transition: opacity 0.3s, transform 0.3s;
        max-width: 300px;
    }

    .notification.show {
        opacity: 1;
        transform: translateY(0);
    }

    .notification.error {
        background-color: #F44336;
    }

    .notification.warning {
        background-color: #FF9800;
    }

    .notification.info {
        background-color: #2196F3;
    }
    </style>
</head>

<body>
    <!-- Main navigation -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-light fixed-top">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="index.php">
                    <i class="fas fa-link me-2"></i>SocialLinks
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php" aria-current="page">
                                <i class="fas fa-gauge-high me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="./<?php echo $userData['username']; ?>" target="_blank">
                                <i class="fas fa-eye me-1"></i> View My Page
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
                                <i class="fas fa-qrcode me-1"></i> My QR Code
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-right-from-bracket me-1"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Welcome banner -->
    <section class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="animate__animated animate__fadeIn">Welcome back,
                        <?php echo htmlspecialchars($userData['username']); ?>!</h1>
                    <p class="animate__animated animate__fadeIn animate__delay-1s">Manage your social links and
                        customize your profile from your personal dashboard.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="./<?php echo $userData['username']; ?>" class="btn btn-light" target="_blank">
                        <i class="fas fa-eye me-2"></i> Preview My Page
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content area -->
    <main class="container">
        <!-- Analytics overview cards -->
        <section class="row mb-4">
            <div class="col-12">
                <div class="analytics-card">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Analytics Overview</h2>
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
                            <canvas id="visitsChart" aria-label="Profile visits chart" role="img"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main dashboard grid -->
        <div class="row">
            <!-- Left sidebar - Profile management -->
            <aside class="col-lg-4">
                <!-- Profile card -->
                <section class="profile-card mb-4">
                    <div class="text-center">
                        <img src="<?php echo !empty($userData['profile_image']) ? $userData['profile_image'] : 'https://via.placeholder.com/150'; ?>"
                            class="profile-image mb-4 floating" alt="Profile Image">
                        <h3><?php echo htmlspecialchars($userData['username']); ?></h3>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($userData['bio'])); ?></p>

                        <hr>

                        <div class="d-flex justify-content-center mb-3">
                            <a href="./<?php echo $userData['username']; ?>" class="btn btn-primary me-2"
                                target="_blank">
                                <i class="fas fa-external-link-alt me-1"></i> View Profile
                            </a>
                            <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#qrCodeModal">
                                <i class="fas fa-qrcode"></i> QR Code
                            </a>
                        </div>
                    </div>
                </section>

                <!-- Username section -->
                <section class="profile-card mt-4">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">Username</h3>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                            data-bs-target="#usernameChangeModal" <?php echo !$canChangeUsername ? 'disabled' : ''; ?>>
                            <i class="fas fa-edit me-1"></i> Change
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h4 class="h6 mb-0"><?php echo htmlspecialchars($userData['username']); ?></h4>
                                <small class="text-muted">Your current username</small>

                                <?php if (!$canChangeUsername): ?>
                                <div class="mt-2 small text-warning">
                                    <i class="fas fa-clock me-1"></i> You can change your username again in
                                    <?php echo $daysUntilNextChange; ?> days
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form id="usernameChangeForm" method="POST" action="">
                            <input type="hidden" id="new_username_hidden" name="new_username" value="">
                            <input type="hidden" name="update_username" value="1">
                        </form>
                    </div>
                </section>

                <!-- Profile edit section -->
                <section class="profile-card">
                    <div class="card-header bg-transparent">
                        <h3 class="h5 mb-0">Edit Profile</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Profile Image</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image"
                                    accept="image/*">
                                <small class="text-muted">Recommended size: 300x300px</small>
                            </div>

                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3"
                                    placeholder="Tell visitors about yourself..."><?php echo htmlspecialchars($userData['bio']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="background_image" class="form-label">Background Image</label>
                                <input type="file" class="form-control" id="background_image" name="background_image"
                                    accept="image/*">
                                <small class="text-muted">Recommended size: 1920x1080px</small>

                                <?php if (!empty($userData['background_image'])): ?>
                                <div class="current-bg-preview mt-2">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($userData['background_image']); ?>"
                                            class="img-thumbnail me-2"
                                            style="width: 80px; height: 45px; object-fit: cover;"
                                            alt="Current Background">
                                        <div>
                                            <span class="d-block text-muted">Current background</span>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="remove_background"
                                                    name="remove_background">
                                                <label class="form-check-label" for="remove_background">Remove
                                                    background image</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="theme" class="form-label">Theme</label>
                                    <select class="form-select" id="theme" name="theme">
                                        <option value="default"
                                            <?php echo $userData['theme'] === 'default' ? 'selected' : ''; ?>>Default
                                        </option>
                                        <option value="dark"
                                            <?php echo $userData['theme'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                        <option value="light"
                                            <?php echo $userData['theme'] === 'light' ? 'selected' : ''; ?>>Light
                                        </option>
                                        <option value="colorful"
                                            <?php echo $userData['theme'] === 'colorful' ? 'selected' : ''; ?>>Colorful
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="font_family" class="form-label">Font Family</label>
                                    <select class="form-select" id="font_family" name="font_family">
                                        <option value="Poppins"
                                            <?php echo ($userData['font_family'] === 'Poppins' || !$userData['font_family']) ? 'selected' : ''; ?>>
                                            Poppins (Default)</option>
                                        <option value="Roboto"
                                            <?php echo $userData['font_family'] === 'Roboto' ? 'selected' : ''; ?>>
                                            Roboto</option>
                                        <option value="Open Sans"
                                            <?php echo $userData['font_family'] === 'Open Sans' ? 'selected' : ''; ?>>
                                            Open Sans</option>
                                        <option value="Montserrat"
                                            <?php echo $userData['font_family'] === 'Montserrat' ? 'selected' : ''; ?>>
                                            Montserrat</option>
                                        <option value="Lato"
                                            <?php echo $userData['font_family'] === 'Lato' ? 'selected' : ''; ?>>Lato
                                        </option>
                                        <option value="Raleway"
                                            <?php echo $userData['font_family'] === 'Raleway' ? 'selected' : ''; ?>>
                                            Raleway</option>
                                        <option value="Nunito"
                                            <?php echo $userData['font_family'] === 'Nunito' ? 'selected' : ''; ?>>
                                            Nunito</option>
                                        <option value="Playfair Display"
                                            <?php echo $userData['font_family'] === 'Playfair Display' ? 'selected' : ''; ?>>
                                            Playfair Display</option>
                                        <option value="Merriweather"
                                            <?php echo $userData['font_family'] === 'Merriweather' ? 'selected' : ''; ?>>
                                            Merriweather</option>
                                        <option value="Ubuntu"
                                            <?php echo $userData['font_family'] === 'Ubuntu' ? 'selected' : ''; ?>>
                                            Ubuntu</option>

                                        <?php if (!in_array($userData['font_family'], ['', 'Poppins', 'Roboto', 'Open Sans', 'Montserrat', 'Lato', 'Raleway', 'Nunito', 'Playfair Display', 'Merriweather', 'Ubuntu'])): ?>
                                        <option value="<?php echo htmlspecialchars($userData['font_family']); ?>"
                                            selected><?php echo htmlspecialchars($userData['font_family']); ?></option>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted mt-1">
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#fontSelectorModal">
                                            <i class="fas fa-palette me-1"></i> Browse all fonts
                                        </a>
                                    </small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </section>
            </aside>

            <!-- Main content - Link management -->
            <div class="col-lg-8">
                <!-- Add link section -->
                <section class="add-link-card">
                    <div class="card-header bg-transparent">
                        <h3 class="h5 mb-0">Add New Link</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="addLinkForm">
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
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i> Add Link
                            </button>
                        </form>
                    </div>
                </section>

                <!-- Links management section -->
                <section class="my-links-card">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">My Links</h3>
                        <span class="badge bg-primary rounded-pill"><?php echo $links->num_rows; ?> Links</span>
                    </div>
                    <div class="card-body">
                        <?php if ($links->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
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
                                                <span class="handle" title="Drag to reorder">
                                                    <i class="fas fa-grip-vertical text-muted me-2"></i>
                                                </span>
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
                                            <div class="btn-group btn-group-sm" role="group" aria-label="Link actions">
                                                <button type="button" class="btn btn-outline-primary edit-link"
                                                    data-id="<?php echo $link['link_id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger delete-link"
                                                    onclick="deleteLink(<?php echo $link['link_id']; ?>)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state text-center p-4">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-link-slash fa-3x text-muted"></i>
                            </div>
                            <h4>No links added yet</h4>
                            <p class="text-muted">Start adding your social links using the form above!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Link layout options -->
                <section class="analytics-card">
                    <div class="card-header bg-transparent">
                        <h3 class="h5 mb-0">Social Links Layout</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row layout-options">
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
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </main>
    <div class="modal fade font-selector-modal" id="fontSelectorModal" tabindex="-1"
        aria-labelledby="fontSelectorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fontSelectorModalLabel">Select Font Family</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" id="fontSearch" class="form-control" placeholder="Search fonts...">
                    </div>
                    <div class="font-categories mb-3">
                        <button class="btn btn-sm btn-outline-primary active" data-category="all">All Fonts</button>
                        <button class="btn btn-sm btn-outline-primary" data-category="serif">Serif</button>
                        <button class="btn btn-sm btn-outline-primary" data-category="sans-serif">Sans Serif</button>
                        <button class="btn btn-sm btn-outline-primary" data-category="display">Display</button>
                        <button class="btn btn-sm btn-outline-primary" data-category="handwriting">Handwriting</button>
                        <button class="btn btn-sm btn-outline-primary" data-category="monospace">Monospace</button>
                    </div>
                    <div class="font-list" id="fontList">
                        <!-- Fonts will be loaded dynamically via JS -->
                        <div class="d-flex justify-content-center my-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading fonts...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="selectFontBtn">Select Font</button>
                </div>
            </div>
        </div>
    </div>

    <?php require './includes/analytics.php'; ?>




    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>
    <script>
    // dashboard.js - JavaScript functionality for SocialLinks dashboard

    // Function to handle all AJAX form submissions
    function submitFormAjax(formElement, successCallback) {
        const form = $(formElement);
        const formData = new FormData(form[0]);

        $.ajax({
            url: './includes/dashboard_actions.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        if (successCallback) {
                            successCallback(result);
                        } else {
                            showNotification(result.message || 'Action completed successfully', 'success');
                        }
                    } else {
                        showNotification(result.message || 'An error occurred', 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showNotification('An unexpected error occurred', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showNotification('Failed to complete request', 'error');
            }
        });
    }

    // Show notification function
    function showNotification(message, type = 'success') {
        const notifElement = $('<div>')
            .addClass('notification ' + type)
            .text(message)
            .appendTo('body');

        setTimeout(function() {
            notifElement.addClass('show');

            setTimeout(function() {
                notifElement.removeClass('show');
                setTimeout(function() {
                    notifElement.remove();
                }, 300);
            }, 3000);
        }, 100);
    }

    // Handle username change
    function handleUsernameChange() {
        const form = $('#usernameChangeForm');
        const newUsername = $('#modal_new_username').val();
        $('#new_username_hidden').val(newUsername);

        submitFormAjax(form, function(result) {
            if (result.success) {
                // Update displayed username throughout the page
                $('.username-display').text(newUsername);
                $('#usernameChangeModal').modal('hide');
                showNotification('Username updated successfully');

                // Update the profile page link
                const profileLink = $('.nav-link[target="_blank"]');
                profileLink.attr('href', './' + newUsername);

                // Refresh the page after a short delay
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            }
        });
    }

    // Handle add link form
    function handleAddLink() {
        const form = $('#addLinkForm');
        const formData = new FormData(form[0]);
        formData.append('action', 'add_link');

        $.ajax({
            url: './includes/dashboard_actions.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showNotification('Link added successfully');
                        // Refresh the links table
                        refreshLinksList();
                        // Clear the form
                        form[0].reset();
                    } else {
                        showNotification(result.message || 'Failed to add link', 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showNotification('An unexpected error occurred', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showNotification('Failed to add link', 'error');
            }
        });
    }

    // Delete link
    function deleteLink(linkId) {
        if (!confirm('Are you sure you want to delete this link?')) {
            return;
        }

        $.ajax({
            url: './includes/dashboard_actions.php',
            type: 'POST',
            data: {
                action: 'delete_link',
                link_id: linkId
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showNotification('Link deleted successfully');
                        refreshLinksList();
                    } else {
                        showNotification(result.message || 'Failed to delete link', 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showNotification('An unexpected error occurred', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showNotification('Failed to delete link', 'error');
            }
        });
    }

    // Refresh links list
    function refreshLinksList() {
        $.ajax({
            url: './includes/get_links.php',
            type: 'GET',
            success: function(response) {
                $('#sortable-links').html(response);
                initSortable(); // Re-initialize sortable
            },
            error: function(xhr, status, error) {
                console.error('Error refreshing links:', error);
            }
        });
    }

    // Update profile
    function updateProfile() {
        const form = $('#profileForm');
        const formData = new FormData(form[0]);
        formData.append('action', 'update_profile');

        $.ajax({
            url: './includes/dashboard_actions.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        // Update profile image if it was changed
                        if (result.profile_image) {
                            $('.profile-image').attr('src', result.profile_image);
                        }
                        showNotification('Profile updated successfully');
                    } else {
                        showNotification(result.message || 'Failed to update profile', 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showNotification('An unexpected error occurred', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showNotification('Failed to update profile', 'error');
            }
        });
    }

    // Modify the layout option click handler
    $('.layout-option').on('click', function() {
        // Remove active class from all options
        $('.layout-option').removeClass('active');
        // Add active class to the clicked option
        $(this).addClass('active');
        // Check the corresponding radio button
        $(this).find('input[type="radio"]').prop('checked', true);
        // Update layout immediately
        updateLayout();
    });

    // Add preventDefault to the form submission
    $('form').on('submit', function(e) {
        if ($(this).find('input[name="update_layout"]').length) {
            e.preventDefault();
            updateLayout();
        }
    });

    // Modify the updateLayout function
    function updateLayout() {
        const layoutStyle = $('input[name="layout_style"]:checked').val();

        $.ajax({
            url: './includes/dashboard_actions.php',
            type: 'POST',
            data: {
                action: 'update_layout',
                layout_style: layoutStyle
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showNotification('Layout updated successfully');
                    } else {
                        showNotification(result.message || 'Failed to update layout', 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showNotification('An unexpected error occurred', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showNotification('Failed to update layout', 'error');
            }
        });
    }

    // Initialize sortable
    function initSortable() {
        $("#sortable-links").sortable({
            handle: ".handle",
            update: function(event, ui) {
                // Get the new order of link IDs
                const linkIds = $(this).sortable("toArray", {
                    attribute: "data-id"
                });

                // Send the new order to the server via AJAX
                $.ajax({
                    url: "./includes/update_link_order.php",
                    method: "POST",
                    data: {
                        link_order: linkIds
                    },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                showNotification("Link order updated successfully");
                            } else {
                                showNotification(result.message ||
                                    "Failed to update link order", "error");
                            }
                        } catch (e) {
                            console.error("Error parsing response:", e);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error updating link order", error);
                        showNotification("Failed to update link order", "error");
                    }
                });
            }
        });
        $("#sortable-links").disableSelection();
    }

    // Replace the initFontSelector function in your dashboard.js script

    function initFontSelector() {
        const fontList = $('#fontList');
        let allFonts = [];
        let filteredFonts = [];
        let selectedCategory = 'all';

        // Load fonts from Google Fonts API
        function loadGoogleFonts() {
            fontList.html(`
            <div class="d-flex justify-content-center my-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading fonts...</span>
                </div>
            </div>
        `);

            // Google Fonts API key - you should replace this with your own API key
            const apiKey = 'AIzaSyAzR-puCAp38gaJcx4xrr0nNPP9wtL1Co8';
            const apiUrl = `https://www.googleapis.com/webfonts/v1/webfonts?key=${apiKey}&sort=popularity`;

            $.ajax({
                url: apiUrl,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    allFonts = response.items;

                    // Create font category mapping
                    allFonts.forEach(font => {
                        // Load the actual font for preview
                        $('head').append(
                            `<link href="https://fonts.googleapis.com/css?family=${font.family.replace(' ', '+')}&display=swap" rel="stylesheet">`
                        );
                    });

                    // Display fonts based on current category
                    filterFontsByCategory(selectedCategory);
                },
                error: function(xhr, status, error) {
                    console.error('Error loading fonts:', error);
                    fontList.html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading fonts. Please try again later.
                    </div>
                `);
                }
            });
        }

        // Filter and display fonts by category
        function filterFontsByCategory(category) {
            selectedCategory = category;

            if (category === 'all') {
                filteredFonts = allFonts.slice(0, 100); // Limit to first 100 fonts for performance
            } else {
                filteredFonts = allFonts.filter(font => {
                    if (category === 'serif' && font.category === 'serif') return true;
                    if (category === 'sans-serif' && font.category === 'sans-serif') return true;
                    if (category === 'display' && font.category === 'display') return true;
                    if (category === 'handwriting' && (font.category === 'handwriting' || font.category ===
                            'handwritten')) return true;
                    if (category === 'monospace' && font.category === 'monospace') return true;
                    return false;
                }).slice(0, 100); // Limit to first 100 fonts for performance
            }

            displayFonts(filteredFonts);
        }

        // Display fonts in the list
        function displayFonts(fonts) {
            fontList.empty();

            if (fonts.length === 0) {
                fontList.html(`
                <div class="alert alert-info">
                    No fonts found for this category.
                </div>
            `);
                return;
            }

            fonts.forEach(font => {
                const fontItem = $(`
                <div class="font-item" data-font="${font.family}">
                    <div class="font-preview" style="font-family: '${font.family}';">
                        The quick brown fox jumps over the lazy dog
                    </div>
                    <div class="font-name">${font.family}</div>
                </div>
            `);
                fontList.append(fontItem);
            });

            // Add click event for font selection
            $('.font-item').on('click', function() {
                $('.font-item').removeClass('selected');
                $(this).addClass('selected');
            });
        }

        // Category buttons click event
        $('.font-categories button').on('click', function() {
            $('.font-categories button').removeClass('active');
            $(this).addClass('active');
            const category = $(this).data('category');
            filterFontsByCategory(category);
        });

        // Search functionality
        $('#fontSearch').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();

            if (searchTerm.length === 0) {
                filterFontsByCategory(selectedCategory);
                return;
            }

            const searchResults = allFonts.filter(font =>
                font.family.toLowerCase().includes(searchTerm)
            ).slice(0, 100); // Limit results for performance

            displayFonts(searchResults);
        });

        $('#selectFontBtn').on('click', function() {
            const selectedFont = $('.font-item.selected').data('font');
            if (selectedFont) {
                // Update the select dropdown
                $('#font_family').val(selectedFont);

                // If the font isn't in the dropdown options, add it
                if ($('#font_family option[value="' + selectedFont + '"]').length === 0) {
                    $('#font_family').append(new Option(selectedFont, selectedFont, true, true));
                }

                // Close the modal
                var fontModal = bootstrap.Modal.getInstance(document.getElementById('fontSelectorModal'));
                fontModal.hide();

                // Remove backdrop
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');

                // Save the font change immediately
                $.ajax({
                    url: './includes/dashboard_actions.php',
                    type: 'POST',
                    data: {
                        action: 'update_font',
                        font_family: selectedFont
                    },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                showNotification(`Font changed to ${selectedFont}`);
                            } else {
                                showNotification(result.message || 'Failed to update font',
                                    'error');
                            }
                        } catch (e) {
                            // console.error('Error parsing response:', e);
                            showNotification(`Font changed to ${selectedFont}`);
                            // showNotification('An unexpected error occurred', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        showNotification('Failed to update font', 'error');
                    }
                });
            } else {
                showNotification('Please select a font first', 'warning');
            }
        });
        // Initialize by loading fonts
        loadGoogleFonts();
    }

    // Make sure modal backdrop is removed when any modal is hidden
    $('.modal').on('hidden.bs.modal', function() {
        if ($('.modal:visible').length) {
            $('body').addClass('modal-open');
        } else {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
        }
    });

    // Initialize the visits chart
    function initVisitsChart(dates, visits) {
        const ctx = document.getElementById('visitsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Profile Views',
                    data: visits,
                    backgroundColor: 'rgba(108, 99, 255, 0.2)',
                    borderColor: 'rgba(108, 99, 255, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(108, 99, 255, 1)',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                return context.raw + ' views';
                            }
                        }
                    }
                }
            }
        });
    }

    // Document ready
    $(document).ready(function() {
        // Initialize sortable links
        initSortable();

        // Initialize font selector
        initFontSelector();

        // Username change
        $('#usernameChangeButton').on('click', handleUsernameChange);

        // Add link form
        $('#addLinkForm').on('submit', function(e) {
            e.preventDefault();
            handleAddLink();
        });

        // Profile form
        $('#profileForm').on('submit', function(e) {
            e.preventDefault();
            updateProfile();
        });

        // Layout options
        $('.layout-option').on('click', function() {
            // Remove active class from all options
            $('.layout-option').removeClass('active');
            // Add active class to the clicked option
            $(this).addClass('active');
            // Check the corresponding radio button
            $(this).find('input[type="radio"]').prop('checked', true);
            // Update layout immediately
            updateLayout();
        });

        // Preview profile image upon selection
        $('#profile_image').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('.profile-image').attr('src', e.target.result);
                }
                reader.readAsDataURL(file);
            }
        });

        // Preview background image upon selection
        $('#background_image').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // If there's already a preview, update it
                    if ($('.current-bg-preview').length) {
                        $('.current-bg-preview img').attr('src', e.target.result);
                    } else {
                        // Create a new preview
                        const previewDiv = $(`
                        <div class="current-bg-preview mt-2">
                            <div class="d-flex align-items-center">
                                <img src="${e.target.result}" class="img-thumbnail me-2" 
                                     style="width: 80px; height: 45px; object-fit: cover;" alt="Background Preview">
                                <span class="text-muted">New background preview</span>
                            </div>
                        </div>
                    `);
                        $(this).after(previewDiv);
                    }
                }
                reader.readAsDataURL(file);
            }
        });

        // Handle "remove background" checkbox
        $('#remove_background').on('change', function() {
            if ($(this).is(':checked')) {
                $('#background_image').prop('disabled', true);
            } else {
                $('#background_image').prop('disabled', false);
            }
        });

        // Initialize chart if data exists
        if (typeof chartDates !== 'undefined' && typeof chartVisits !== 'undefined') {
            initVisitsChart(chartDates, chartVisits);
        }
    });

    // Make deleteLink function globally available
    window.deleteLink = deleteLink;
    </script>

</body>

</html>