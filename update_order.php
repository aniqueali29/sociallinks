
<?php
// update_order.php - Update link order via AJAX
session_start();
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if link_ids array is submitted
if (isset($_POST['link_ids']) && is_array($_POST['link_ids'])) {
    $link_ids = $_POST['link_ids'];
    
    // Update each link's display_order
    for ($i = 0; $i < count($link_ids); $i++) {
        $link_id = (int)$link_ids[$i];
        $display_order = $i + 1;
        
        $sql = "UPDATE links SET display_order = ? WHERE link_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $display_order, $link_id, $user_id);
        $stmt->execute();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No link_ids provided']);
}
?>

<?php
// profile.php - Public profile page
require_once 'database.php';

// Check if username is provided
if (!isset($_GET['user'])) {
    header("Location: index.php");
    exit;
}

$username = $_GET['user'];

// Get user data
$userSQL = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($userSQL);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found
    header("Location: index.php");
    exit;
}

$userData = $result->fetch_assoc();
$user_id = $userData['user_id'];

// Get user links
$linksSQL = "SELECT * FROM links WHERE user_id = ? ORDER BY display_order";
$stmt = $conn->prepare($linksSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$links = $stmt->get_result();

// Set theme class based on user preference
$themeClass = 'theme-' . $userData['theme'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $userData['username']; ?> - Social Links</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-bottom: 60px;
        }
        
        .profile-container {
            max-width: 600px;
            margin: 0 auto;
            padding-top: 50px;
        }
        
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        
        .social-link {
            display: block;
            padding: 12px 20px;
            margin-bottom: 12px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        /* Theme styles */
        .theme-default .social-link {
            background-color: #4361ee;
            color: white;
        }
        
        .theme-default .social-link:hover {
            background-color: #3a56d4;
            transform: translateY(-2px);
        }
        
        .theme-dark {
            background-color: #121212;
            color: #f8f9fa;
        }
        
        .theme-dark .card {
            background-color: #1e1e1e;
            color: #f8f9fa;
        }
        
        .theme-dark .social-link {
            background-color: #333;
            color: #f8f9fa;
            border: 1px solid #444;
        }
        
        .theme-dark .social-link:hover {
            background-color: #444;
            transform: translateY(-2px);
        }
        
        .theme-light {
            background-color: #f8f9fa;
        }
        
        .theme-light .social-link {
            background-color: #fff;
            color: #333;
            border: 1px solid #dee2e6;
        }
        
        .theme-light .social-link:hover {
            background-color: #f8f9fa;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transform: translateY(-2px);
        }
        
        .theme-colorful {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        
        .theme-colorful .card {
            background-color: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: none;
            color: #fff;
        }
        
        .theme-colorful .social-link {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: none;
        }
        
        .theme-colorful .social-link:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        /* Platform specific icons */
        .platform-icon {
            margin-right: 10px;
            font-size: 1.2rem;
        }
    </style>
</head>
<body class="<?php echo $themeClass; ?>">
    <div class="profile-container">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <img src="<?php echo !empty($userData['profile_image']) ? $userData['profile_image'] : 'https://via.placeholder.com/150'; ?>" 
                     class="profile-image my-4" alt="<?php echo $userData['username']; ?>">
                <h2 class="mb-2"><?php echo $userData['username']; ?></h2>
                <?php if (!empty($userData['bio'])): ?>
                    <p class="text-muted mb-4"><?php echo $userData['bio']; ?></p>
                <?php endif; ?>
                
                <div class="links-container mt-4">
                    <?php if ($links->num_rows > 0): ?>
                        <?php while ($link = $links->fetch_assoc()): ?>
                            <a href="<?php echo $link['url']; ?>" class="social-link" target="_blank">
                                <i class="fab fa-<?php echo $link['platform']; ?> platform-icon"></i>
                                <?php echo $link['display_text']; ?>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted">No links have been added yet.</p>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4">
                    <a href="generate_qr.php?user=<?php echo $userData['username']; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-qrcode me-1"></i> Show QR Code
                    </a>
                </div>
                
                <div class="mt-5 pt-3 border-top text-center">
                    <small class="text-muted">Create your own profile at <a href="index.php">Social Links</a></small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>