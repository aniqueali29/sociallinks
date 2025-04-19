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

// Links per page and pagination calculation
$links_per_page = 8; // Show 8 links initially
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1
$offset = ($current_page - 1) * $links_per_page;

// Get total number of links for pagination
$countSQL = "SELECT COUNT(*) as total FROM links WHERE user_id = ?";
$stmt = $conn->prepare($countSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_links = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_links / $links_per_page);

// Get paginated user links
$linksSQL = "SELECT * FROM links WHERE user_id = ? ORDER BY display_order LIMIT ? OFFSET ?";
$stmt = $conn->prepare($linksSQL);
$stmt->bind_param("iii", $user_id, $links_per_page, $offset);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
    :root {
        --primary-color: #6C63FF;
        --secondary-color: #FF6584;
        --accent-color: #4CD5C5;
        --dark-color: #2A2A2A;
        --light-color: #F8F9FA;
    }

    html,
    body {
        height: 100%;
        font-family: 'Poppins', sans-serif;
        overflow-x: hidden;
        transition: all 0.3s ease;
    }

    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    .profile-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 40px 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .profile-card {
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        position: relative;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .profile-header {
        position: relative;
        height: 120px;
        overflow: hidden;
    }

    .profile-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(108, 99, 255, 0.8) 0%, rgba(255, 101, 132, 0.8) 100%);
        z-index: 2;
    }

    .profile-pattern {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0.1;
        z-index: 3;
        background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }

    .profile-image-wrapper {
        display: flex;
        justify-content: center;
        margin-top: -50px;
        position: relative;
        z-index: 10;
    }

    .profile-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid #fff;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .profile-image:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .profile-content {
        padding: 10px 20px 20px;
        text-align: center;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .profile-name {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 10px 0 5px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: inline-block;
    }

    .profile-bio {
        font-size: 1rem;
        color: #666;
        margin-bottom: 15px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.5;
    }

    .links-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
        overflow-y: auto;
        max-height: calc(100vh - 350px);
        scrollbar-width: thin;
        scrollbar-color: var(--primary-color) #f0f0f0;
        padding: 0 5px;
    }

    /* Scrollbar styling for webkit browsers */
    .links-container::-webkit-scrollbar {
        width: 6px;
    }

    .links-container::-webkit-scrollbar-track {
        background: #f0f0f0;
        border-radius: 10px;
    }

    .links-container::-webkit-scrollbar-thumb {
        background-color: var(--primary-color);
        border-radius: 10px;
    }

    .social-link {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        z-index: 1;
        height: 100%;
    }

    .social-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(108, 99, 255, 0.1) 0%, rgba(255, 101, 132, 0.1) 100%);
        z-index: -1;
        transform: translateY(100%);
        transition: all 0.3s ease;
    }

    .social-link:hover::before {
        transform: translateY(0);
    }

    .social-link:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    }

    .platform-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        border-radius: 10px;
        margin-right: 12px;
        font-size: 1.1rem;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(108, 99, 255, 0.3);
    }

    .link-text {
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.9rem;
    }

    .btn-qr {
        padding: 10px 20px;
        border-radius: 50px;
        font-weight: 600;
        background: transparent;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        margin-top: 5px;
        font-size: 0.9rem;
    }

    .btn-qr:hover {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        border-color: transparent;
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(108, 99, 255, 0.2);
    }

    .btn-load-more {
        padding: 10px 20px;
        border-radius: 50px;
        font-weight: 600;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        border: none;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-top: 10px;
        width: 100%;
        font-size: 0.9rem;
    }

    .btn-load-more:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(108, 99, 255, 0.2);
        opacity: 0.9;
    }

    .footer {
        text-align: center;
        padding: 15px 0;
        margin-top: 15px;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        font-size: 0.85rem;
    }

    .footer-link {
        color: var(--primary-color);
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .footer-link:hover {
        color: var(--secondary-color);
    }

    .footer-brand {
        font-weight: 700;
        margin-left: 5px;
    }

    .wave-shape {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        pointer-events: none;
        z-index: -1;
    }

    .social-links-counter {
        display: inline-block;
        padding: 4px 12px;
        background: rgba(108, 99, 255, 0.1);
        border-radius: 30px;
        color: var(--primary-color);
        font-weight: 600;
        font-size: 0.85rem;
        margin-bottom: 15px;
    }

    /* Animated badge */
    .profile-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        z-index: 10;
        background: white;
        color: var(--primary-color);
        padding: 5px 12px;
        border-radius: 30px;
        font-weight: 600;
        font-size: 0.8rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .badge-dot {
        width: 8px;
        height: 8px;
        background: #4CD5C5;
        border-radius: 50%;
        display: inline-block;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.3);
            opacity: 0.7;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* Theme styles enhanced */
    /* Default theme */
    .theme-default {
        background-color: #f8f9fa;
    }

    .theme-default .profile-card {
        background-color: white;
    }

    .theme-default .social-link {
        background-color: white;
        color: var(--dark-color);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    /* Dark theme */
    .theme-dark {
        background-color: #121212;
        color: #f8f9fa;
    }

    .theme-dark .profile-card {
        background-color: #1e1e1e;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
    }

    .theme-dark .profile-overlay {
        background: linear-gradient(135deg, rgba(108, 99, 255, 0.9) 0%, rgba(66, 63, 87, 0.9) 100%);
    }

    .theme-dark .profile-bio {
        color: #aaa;
    }

    .theme-dark .social-link {
        background-color: #282828;
        color: #f8f9fa;
        border: 1px solid #333;
    }

    .theme-dark .social-link::before {
        background: linear-gradient(135deg, rgba(108, 99, 255, 0.2) 0%, rgba(66, 63, 87, 0.2) 100%);
    }

    .theme-dark .social-links-counter {
        background: rgba(108, 99, 255, 0.2);
        color: #aaa;
    }

    .theme-dark .footer {
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .theme-dark .profile-badge {
        background: #1e1e1e;
        color: #f8f9fa;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }

    .theme-dark .links-container::-webkit-scrollbar-track {
        background: #282828;
    }

    /* Light theme */
    .theme-light {
        background-color: #ffffff;
    }

    .theme-light .profile-card {
        background-color: #ffffff;
    }

    .theme-light .profile-overlay {
        background: linear-gradient(135deg, rgba(108, 99, 255, 0.7) 0%, rgba(76, 213, 197, 0.7) 100%);
    }

    .theme-light .social-link {
        background-color: #f8f9fa;
        color: #333;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .theme-light .social-link::before {
        background: linear-gradient(135deg, rgba(108, 99, 255, 0.05) 0%, rgba(76, 213, 197, 0.05) 100%);
    }

    /* Colorful theme */
    .theme-colorful {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }

    .theme-colorful .profile-card {
        background-color: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
    }

    .theme-colorful .profile-overlay {
        background: linear-gradient(135deg, rgba(108, 99, 255, 0.8) 0%, rgba(255, 101, 132, 0.8) 100%);
    }

    .theme-colorful .platform-icon {
        background: linear-gradient(135deg, #8E2DE2 0%, #FF6584 100%);
    }

    .theme-colorful .social-link {
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(5px);
        border: none;
        color: var(--dark-color);
    }

    .theme-colorful .social-link::before {
        background: linear-gradient(135deg, rgba(142, 45, 226, 0.1) 0%, rgba(255, 101, 132, 0.1) 100%);
    }

    .theme-colorful .btn-qr:hover {
        background: linear-gradient(135deg, #8E2DE2 0%, #FF6584 100%);
    }

    /* Responsive styles */
    @media (max-width: 767px) {
        .links-container {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 576px) {
        .profile-container {
            padding-top: 30px;
            padding-bottom: 20px;
        }

        .profile-name {
            font-size: 1.5rem;
        }

        .profile-bio {
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .social-link {
            padding: 10px 15px;
        }

        .platform-icon {
            width: 32px;
            height: 32px;
            font-size: 1rem;
        }

        .profile-badge {
            top: 10px;
            right: 10px;
            padding: 4px 10px;
            font-size: 0.75rem;
        }

        .links-container {
            max-height: calc(100vh - 300px);
        }
    }

    /* Floating animation for QR button */
    .float-animation {
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0% {
            transform: translateY(0px);
        }

        50% {
            transform: translateY(-5px);
        }

        100% {
            transform: translateY(0px);
        }
    }

    /* Hidden for Load More functionality */
    .hidden-link {
        display: none;
    }


    .modal-content {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
}

.theme-dark .modal-content {
    background-color: #1e1e1e;
    color: #f8f9fa;
}

.theme-dark .modal-header, 
.theme-dark .modal-footer {
    border-color: rgba(255, 255, 255, 0.1);
}

.qr-container {
    position: relative;
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-image {
    max-width: 100%;
    height: auto;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    transform: scale(0.8);
    opacity: 0;
    transition: all 0.5s ease;
}

.qr-image.loaded {
    transform: scale(1);
    opacity: 1;
}

.qr-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.theme-dark .btn-close {
    filter: invert(1);
}

    </style>
</head>

<body class="<?php echo $themeClass; ?>">
    <svg class="wave-shape" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
        <path fill="rgba(108, 99, 255, 0.05)" fill-opacity="1"
            d="M0,224L48,213.3C96,203,192,181,288,154.7C384,128,480,96,576,96C672,96,768,128,864,149.3C960,171,1056,181,1152,170.7C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z">
        </path>
    </svg>

    <div class="profile-container">
        <div class="profile-card animate__animated animate__fadeIn">
            <div class="profile-header">
                <div class="profile-overlay"></div>
                <div class="profile-pattern"></div>
                <div class="profile-badge">
                    <span class="badge-dot"></span> Online
                </div>
            </div>

            <div class="profile-image-wrapper">
                <img src="<?php echo !empty($userData['profile_image']) ? $userData['profile_image'] : 'https://via.placeholder.com/150'; ?>"
                    class="profile-image animate__animated animate__zoomIn" alt="<?php echo $userData['username']; ?>">
            </div>

            <div class="profile-content">
                <h1 class="profile-name animate__animated animate__fadeIn"><?php echo $userData['username']; ?></h1>

                <?php if (!empty($userData['bio'])): ?>
                <p class="profile-bio animate__animated animate__fadeIn animate__delay-1s">
                    <?php echo $userData['bio']; ?></p>
                <?php endif; ?>

                <?php 
                if ($total_links > 0): 
                ?>
                <div class="social-links-counter animate__animated animate__fadeIn animate__delay-1s">
                    <i class="fas fa-link me-1"></i> <?php echo $total_links; ?>
                    <?php echo $total_links == 1 ? 'Link' : 'Links'; ?>
                </div>
                <?php endif; ?>

                <div class="links-container animate__animated animate__fadeInUp animate__delay-1s">
                    <?php if ($links->num_rows > 0): ?>
                    <?php 
                        $delay = 2;
                        $link_count = 0;
                        while ($link = $links->fetch_assoc()): 
                            $link_count++;
                            $hidden_class = $link_count > 8 ? 'hidden-link' : '';
                        ?>
                    <a href="<?php echo $link['url']; ?>"
                        class="social-link <?php echo $hidden_class; ?> animate__animated animate__fadeInUp animate__delay-<?php echo $delay; ?>s"
                        target="_blank" data-link-id="<?php echo $link_count; ?>">
                        <div class="platform-icon">
                            <i class="fab fa-<?php echo $link['platform']; ?>"></i>
                        </div>
                        <span class="link-text"><?php echo $link['display_text']; ?></span>
                    </a>
                    <?php 
                        $delay += 0.1;
                        endwhile; 
                        ?>
                    <?php else: ?>
                    <p class="text-muted animate__animated animate__fadeIn animate__delay-2s">No links have been added
                        yet.</p>
                    <?php endif; ?>
                </div>

                <?php if ($total_links > 8): ?>
                <div class="mt-3 text-center animate__animated animate__fadeIn animate__delay-2s">
                    <button id="load-more-btn" class="btn-load-more">
                        <i class="fas fa-chevron-down me-2"></i> Load More
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($total_pages > 1 && $total_links <= 8): ?>
                <div class="pagination-container animate__animated animate__fadeIn animate__delay-2s">
                    <ul class="pagination">
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link"
                                href="?user=<?php echo $username; ?>&page=<?php echo $current_page - 1; ?>"
                                aria-label="Previous">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        // Show max 5 page numbers with current page in the middle if possible
                        $start_page = max(1, min($current_page - 2, $total_pages - 4));
                        $end_page = min($total_pages, max(5, $current_page + 2));
                        
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?user=' . $username . '&page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><a class="page-link">...</a></li>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = $i == $current_page ? 'active' : '';
                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?user=' . $username . '&page=' . $i . '">' . $i . '</a></li>';
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link">...</a></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?user=' . $username . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link"
                                href="?user=<?php echo $username; ?>&page=<?php echo $current_page + 1; ?>"
                                aria-label="Next">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="mt-3 animate__animated animate__fadeIn animate__delay-3s">
                    <a href="#" id="show-qr-btn" class="btn btn-qr float-animation">
                        <i class="fas fa-qrcode me-2"></i> Show QR Code
                    </a>
                </div>
            </div>
        </div>

        <div class="footer animate__animated animate__fadeIn animate__delay-3s">
            <p>Create your own profile at <a href="index.php" class="footer-link"><i class="fas fa-link"></i> <span
                        class="footer-brand">SocialLinks</span></a></p>
        </div>
    </div>
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">Scan QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="qr-container">
                        <div class="qr-loading">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <img id="qr-code-img" class="img-fluid qr-image" style="display: none;" alt="QR Code">
                    </div>
                    <p class="mt-3">Scan this code to visit <?php echo $userData['username']; ?>'s profile</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="download-qr" href="#" download="<?php echo $userData['username']; ?>-qrcode.png"
                        class="btn btn-primary">
                        <i class="fas fa-download me-2"></i> Save QR Code
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wow/1.1.2/wow.min.js"></script>
    <script>
    // Initialize wow.js for scroll animations
    new WOW().init();

    // Add slight parallax effect to the profile header
    document.addEventListener('mousemove', function(e) {
        const profileHeader = document.querySelector('.profile-header');
        if (profileHeader) {
            const moveX = (e.clientX - window.innerWidth / 2) * 0.01;
            const moveY = (e.clientY - window.innerHeight / 2) * 0.01;
            profileHeader.style.transform = `translate(${moveX}px, ${moveY}px)`;
        }
    });

    // Load More functionality
    document.addEventListener('DOMContentLoaded', function() {
        const loadMoreBtn = document.getElementById('load-more-btn');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                const hiddenLinks = document.querySelectorAll('.hidden-link');
                hiddenLinks.forEach(link => {
                    link.classList.remove('hidden-link');
                    link.classList.add('animate__animated', 'animate__fadeIn');
                });
                loadMoreBtn.style.display = 'none';
            });
        }

        // Track profile views (example - would need backend implementation)
        console.log('Profile view recorded');
    });

    // Smooth scrolling for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Adjust container height to fit in viewport
    function adjustContainerHeight() {
        const profileContainer = document.querySelector('.profile-container');
        const linksContainer = document.querySelector('.links-container');
        const viewportHeight = window.innerHeight;

        // Calculate approximate heights of other elements
        const headerHeight = 120; // profile header
        const imageHeight = 50; // Half of profile image that goes above the content
        const nameHeight = 60; // profile name
        const bioHeight = document.querySelector('.profile-bio') ?
            document.querySelector('.profile-bio').offsetHeight : 0;
        const counterHeight = document.querySelector('.social-links-counter') ?
            document.querySelector('.social-links-counter').offsetHeight : 0;
        const buttonHeight = 60; // QR button + margin
        const footerHeight = document.querySelector('.footer').offsetHeight;
        const loadMoreHeight = document.getElementById('load-more-btn') ? 50 : 0;
        const paddingHeight = 80; // Container padding

        // Calculate available height for links container
        const availableHeight = viewportHeight - (headerHeight + imageHeight + nameHeight +
            bioHeight + counterHeight + buttonHeight +
            footerHeight + loadMoreHeight + paddingHeight);

        // Set max height for links container
        if (linksContainer && availableHeight > 100) {
            linksContainer.style.maxHeight = `${availableHeight}px`;
        }
    }

    // Adjust height on load and resize
    window.addEventListener('load', adjustContainerHeight);
    window.addEventListener('resize', adjustContainerHeight);
    </script>
    <script>
// Your existing scripts remain unchanged

// QR code functionality
document.addEventListener('DOMContentLoaded', function() {
    const showQrBtn = document.getElementById('show-qr-btn');
    const qrCodeImg = document.getElementById('qr-code-img');
    const downloadQr = document.getElementById('download-qr');
    const qrModal = new bootstrap.Modal(document.getElementById('qrCodeModal'));
    
    showQrBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Show modal with loading state
        qrModal.show();
        
        // Generate QR code
        const username = '<?php echo $userData["username"]; ?>';
        const qrUrl = `generate_qr.php?user=${encodeURIComponent(username)}`;
        
        // Set random parameter to prevent caching
        const timestamp = new Date().getTime();
        const noCacheUrl = `${qrUrl}&_=${timestamp}`;
        
        // Load the QR code
        qrCodeImg.onload = function() {
            // Hide loading spinner
            document.querySelector('.qr-loading').style.display = 'none';
            
            // Show QR with animation
            qrCodeImg.style.display = 'block';
            setTimeout(() => {
                qrCodeImg.classList.add('loaded');
            }, 100);
            
            // Set download link
            downloadQr.href = qrCodeImg.src;
        };
        
        qrCodeImg.src = noCacheUrl;
    });
    
    // When modal is hidden, reset QR state
    document.getElementById('qrCodeModal').addEventListener('hidden.bs.modal', function () {
        qrCodeImg.classList.remove('loaded');
        qrCodeImg.style.display = 'none';
        document.querySelector('.qr-loading').style.display = 'block';
    });
});
</script>
</body>

</html>