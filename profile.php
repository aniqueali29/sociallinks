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

// Record profile visit
$visitor_ip = $_SERVER['REMOTE_ADDR'];
$insertVisitSQL = "INSERT INTO profile_visits (user_id, visitor_ip, visit_time) VALUES (?, ?, NOW())";
$stmt = $conn->prepare($insertVisitSQL);
$stmt->bind_param("is", $user_id, $visitor_ip);
$stmt->execute();

// Get user links
$linksSQL = "SELECT * FROM links WHERE user_id = ? ORDER BY display_order";
$stmt = $conn->prepare($linksSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$links = $stmt->get_result();

// Set theme class based on user preference
$themeClass = 'theme-' . ($userData['theme'] ?? 'default');

// Set layout class based on user preference
$layoutClass = 'layout-' . ($userData['links_layout'] ?? 'list');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($userData['username']); ?> - SocialLinks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta property="og:title" content="<?php echo htmlspecialchars($userData['username']); ?> - SocialLinks" />
    <meta property="og:description" content="Check out my social media links!" />
    <meta property="og:type" content="website" />
    <link rel="stylesheet" href="./css/profile.css">
</head>

<!-- <body class="<?php echo $themeClass; ?>"> -->
<body class="<?php echo $themeClass; ?> layout-<?php echo $userData['links_layout'] ? $userData['links_layout'] : 'list'; ?>">
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
                <img src="<?php echo !empty($userData['profile_image']) ? htmlspecialchars($userData['profile_image']) : 'https://via.placeholder.com/150'; ?>"
                    class="profile-image animate__animated animate__zoomIn"
                    alt="<?php echo htmlspecialchars($userData['username']); ?>">
            </div>

            <div class="profile-content">
                <h1 class="profile-name animate__animated animate__fadeIn">
                    <?php echo htmlspecialchars($userData['username']); ?></h1>

                <?php if (!empty($userData['bio'])): ?>
                <p class="profile-bio animate__animated animate__fadeIn animate__delay-1s">
                    <?php echo nl2br(htmlspecialchars($userData['bio'])); ?></p>
                <?php endif; ?>

                <?php 
                if ($links->num_rows > 0): 
                ?>
                <div class="social-links-counter animate__animated animate__fadeIn animate__delay-1s">
                    <i class="fas fa-link me-1"></i> <?php echo $links->num_rows; ?>
                    <?php echo $links->num_rows == 1 ? 'Link' : 'Links'; ?>
                </div>

                <div
                    class="links-container animate__animated animate__fadeInUp animate__delay-1s <?php echo $layoutClass; ?>">
                    <?php 
                    $delay = 2;
                    while ($link = $links->fetch_assoc()): 
                    ?>
                    <a href="track_click.php?link_id=<?php echo $link['link_id']; ?>&user_id=<?php echo $user_id; ?>"
                        class="social-link animate__animated animate__fadeInUp animate__delay-<?php echo $delay; ?>s"
                        target="_blank">
                        <div class="platform-icon">
                            <i class="fab fa-<?php echo htmlspecialchars($link['platform']); ?>"></i>
                        </div>
                        <span class="link-text"><?php echo htmlspecialchars($link['display_text']); ?></span>
                    </a>
                    <?php 
                    $delay += 0.1;
                    endwhile; 
                    ?>
                </div>
                <?php else: ?>
                <p class="text-muted animate__animated animate__fadeIn animate__delay-2s">No links have been added yet.
                </p>
                <?php endif; ?>

                <div class="mt-4 animate__animated animate__fadeIn animate__delay-3s">
                    <a href="#" id="show-qr-btn" class="btn btn-qr">
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

    <!-- QR Code Modal -->
    <div class="modal fade qr-modal" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel"
        aria-hidden="true">
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
                    <p class="mt-3">Scan this code to visit <?php echo htmlspecialchars($userData['username']); ?>'s
                        profile</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="download-qr" href="#"
                        download="<?php echo htmlspecialchars($userData['username']); ?>-qrcode.png"
                        class="btn btn-primary">
                        <i class="fas fa-download me-2"></i> Save QR Code
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add slight parallax effect to the profile header
        document.addEventListener('mousemove', function(e) {
            const profileHeader = document.querySelector('.profile-header');
            if (profileHeader) {
                const moveX = (e.clientX - window.innerWidth / 2) * 0.01;
                const moveY = (e.clientY - window.innerHeight / 2) * 0.01;
                profileHeader.style.transform = `translate(${moveX}px, ${moveY}px)`;
            }
        });

        // Adjust container height to fit viewport
        function adjustContainerHeight() {
            const profileContainer = document.querySelector('.profile-container');
            const linksContainer = document.querySelector('.links-container');
            const viewportHeight = window.innerHeight;

            if (linksContainer) {
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
                const paddingHeight = 80; // Container padding

                // Calculate available height for links container
                const availableHeight = viewportHeight - (headerHeight + imageHeight + nameHeight +
                    bioHeight + counterHeight + buttonHeight +
                    footerHeight + paddingHeight);

                // Set max height for links container
                if (availableHeight > 100) {
                    linksContainer.style.maxHeight = `${availableHeight}px`;
                }
            }
        }

        // QR code functionality
        const showQrBtn = document.getElementById('show-qr-btn');
        const qrCodeImg = document.getElementById('qr-code-img');
        const downloadQr = document.getElementById('download-qr');
        const qrModal = new bootstrap.Modal(document.getElementById('qrCodeModal'));

        if (showQrBtn) {
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
        }

        // When modal is hidden, reset QR state
        document.getElementById('qrCodeModal').addEventListener('hidden.bs.modal', function() {
            qrCodeImg.classList.remove('loaded');
            qrCodeImg.style.display = 'none';
            document.querySelector('.qr-loading').style.display = 'block';
        });

        // Initial height adjustment
        window.addEventListener('load', adjustContainerHeight);
        window.addEventListener('resize', adjustContainerHeight);
    });
    </script>
</body>

</html>