<?php
// login.php - User login with enhanced security and Google OAuth
session_start();
require_once 'database.php';
require_once 'google-config.php'; // Create this file for Google OAuth configuration

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Function to validate input
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialize variables
$email = "";
$errors = [];
$loginAttempts = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;

// Google Login URL creation
require_once 'vendor/autoload.php'; // Include Google Client library

$googleClient = new Google_Client();
$googleClient->setClientId($googleClientId);
$googleClient->setClientSecret($googleClientSecret);
$googleClient->setRedirectUri($googleRedirectUrl);
$googleClient->addScope("email");
$googleClient->addScope("profile");

$googleAuthUrl = $googleClient->createAuthUrl();

// If this is a Google OAuth callback
if (isset($_GET['code']) && !empty($_GET['code'])) {
    try {
        $token = $googleClient->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (!isset($token['error'])) {
            $googleClient->setAccessToken($token['access_token']);
            
            // Get user profile
            $googleService = new Google_Service_Oauth2($googleClient);
            $userData = $googleService->userinfo->get();
            
            // Check if Google user exists in database
            $googleId = $userData->id;
            $googleEmail = $userData->email;
            $googleName = $userData->name;
            
            $sql = "SELECT user_id, username FROM users WHERE google_id = ? OR email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $googleId, $googleEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // User exists, log them in
                $user = $result->fetch_assoc();
                
                // Update Google ID if needed
                if (empty($user['google_id'])) {
                    $updateSql = "UPDATE users SET google_id = ? WHERE user_id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("si", $googleId, $user['user_id']);
                    $updateStmt->execute();
                }
                
                // Log successful login
                $user_id = $user['user_id'];
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $logSQL = "INSERT INTO login_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, 'google_login', ?, ?, NOW())";
                $logStmt = $conn->prepare($logSQL);
                $logStmt->bind_param("iss", $user_id, $ip, $userAgent);
                $logStmt->execute();
                
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $user['username'];
                $_SESSION['auth_time'] = time();
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                // User doesn't exist, redirect to registration with Google data
                $_SESSION['google_data'] = [
                    'id' => $googleId,
                    'email' => $googleEmail,
                    'name' => $googleName
                ];
                header("Location: register.php?google=1");
                exit;
            }
        } else {
            $errors[] = "Google authentication failed. Please try again.";
        }
    } catch (Exception $e) {
        $errors[] = "Error during Google authentication: " . $e->getMessage();
    }
}

// If login attempts exceed threshold, check if cooldown period has passed
if ($loginAttempts >= 5) {
    if (!isset($_SESSION['lockout_time']) || time() - $_SESSION['lockout_time'] < 300) {
        // Still in cooldown period
        $_SESSION['lockout_time'] = isset($_SESSION['lockout_time']) ? $_SESSION['lockout_time'] : time();
        $remainingTime = 300 - (time() - $_SESSION['lockout_time']);
        $errors[] = "Too many failed login attempts. Please try again in " . ceil($remainingTime / 60) . " minutes.";
    } else {
        // Cooldown period has passed, reset counters
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['lockout_time']);
        $loginAttempts = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loginAttempts < 5) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    $email = validateInput($_POST['email']);
    $password = $_POST['password'];
    
    // Basic validation
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        $sql = "SELECT user_id, username, password, status FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is active
            if ($user['status'] !== 'active') {
                $errors[] = "Account is inactive or suspended. Please contact support.";
            } else if (password_verify($password, $user['password'])) {
                // Log successful login
                $user_id = $user['user_id'];
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $logSQL = "INSERT INTO login_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, 'login', ?, ?, NOW())";
                $logStmt = $conn->prepare($logSQL);
                $logStmt->bind_param("iss", $user_id, $ip, $userAgent);
                $logStmt->execute();
                
                // Update password hash if needed (if using an older algorithm)
                if (password_needs_rehash($user['password'], PASSWORD_ARGON2ID)) {
                    $newHash = password_hash($password, PASSWORD_ARGON2ID);
                    $updateSQL = "UPDATE users SET password = ? WHERE user_id = ?";
                    $updateStmt = $conn->prepare($updateSQL);
                    $updateStmt->bind_param("si", $newHash, $user_id);
                    $updateStmt->execute();
                }
                
                // Reset login attempts
                $_SESSION['login_attempts'] = 0;
                unset($_SESSION['lockout_time']);
                
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $user['username'];
                $_SESSION['auth_time'] = time();
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                // Increment login attempts on failure
                $_SESSION['login_attempts'] = $loginAttempts + 1;
                
                // Set lockout time if reached max attempts
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['lockout_time'] = time();
                    $errors[] = "Too many failed login attempts. Your account is locked for 5 minutes.";
                } else {
                    $errors[] = "Invalid email or password. Attempts remaining: " . (5 - $_SESSION['login_attempts']);
                }
                
                // Log failed login attempt
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $logSQL = "INSERT INTO login_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, 'failed_login', ?, ?, NOW())";
                $logStmt = $conn->prepare($logSQL);
                $logStmt->bind_param("iss", $user['user_id'], $ip, $userAgent);
                $logStmt->execute();
            }
        } else {
            // Increment login attempts on failure
            $_SESSION['login_attempts'] = $loginAttempts + 1;
            
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['lockout_time'] = time();
                $errors[] = "Too many failed login attempts. Please try again in 5 minutes.";
            } else {
                $errors[] = "Invalid email or password. Attempts remaining: " . (5 - $_SESSION['login_attempts']);
            }
            
            // Log failed login with unknown user
            $ip = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            $logSQL = "INSERT INTO login_logs (user_id, action, ip_address, user_agent, created_at) VALUES (NULL, 'failed_login_unknown', ?, ?, NOW())";
            $logStmt = $conn->prepare($logSQL);
            $logStmt->bind_param("ss", $ip, $userAgent);
            $logStmt->execute();
        }
    }
}

// Generate a new CSRF token for the form
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Social Links</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./css/login.css">
</head>
<body>
    <div class="container auth-container">
        <div class="row justify-content-center w-100">
            <div class="col-lg-10">
                <div class="card shadow glassmorphism">
                    <div class="row g-0">
                        <div class="col-lg-5 d-none d-lg-block">
                            <div class="auth-illustration h-100">
                                <div class="auth-illustration-content">
                                    <div class="brand-mark">SocialLinks</div>
                                    <h2 class="illustration-title">Welcome Back!</h2>
                                    <p class="mb-5">Sign in to access your account and continue your journey with us.</p>
                                    
                                    <div class="mt-4">
                                        <div class="d-flex align-items-center mb-4">
                                            <div class="feature-icon">
                                                <i class="fas fa-shield-alt"></i>
                                            </div>
                                            <span class="feature-text">Advanced security</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-4">
                                            <div class="feature-icon">
                                                <i class="fas fa-lock"></i>
                                            </div>
                                            <span class="feature-text">Protected data</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div class="feature-icon">
                                                <i class="fas fa-user-shield"></i>
                                            </div>
                                            <span class="feature-text">Privacy first</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="login-banner">
                                    <i class="fas fa-bolt login-banner-icon"></i>
                                    <p class="login-banner-text">Over 100,000 people trust SocialLinks</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="card-body p-4 p-lg-5">
                                <div class="text-center mb-4">
                                    <div class="brand-mark d-block d-lg-none mb-3">SocialLinks</div>
                                    <h1 class="h3 mb-3 fw-bold text-primary">Sign In to Your Account</h1>
                                    <p class="text-muted">Enter your credentials to access your dashboard</p>
                                </div>
                                
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger mb-4">
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo $error; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="" id="loginForm" novalidate class="needs-validation">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    
                                    <div class="form-floating mb-4">
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Email address" value="<?php echo $email; ?>" required>
                                        <label for="email">Email address</label>
                                        <div class="invalid-feedback">Please enter a valid email address.</div>
                                    </div>
                                    
                                    <div class="form-floating mb-4">
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                        <label for="password">Password</label>
                                        <div class="invalid-feedback">Please enter your password.</div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                            <label class="form-check-label" for="remember">Remember me</label>
                                        </div>
                                        <a href="forgot-password.php" class="link-primary">Forgot password?</a>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100 py-3 mb-3">
                                        <i class="fas fa-sign-in-alt me-2"></i> Sign In
                                    </button>
                                    
                                    <div class="social-login">
                                        <span class="social-login-text">Or continue with</span>
                                    </div>
                                    
                                    <div class="social-buttons">
                                        <a href="<?php echo $googleAuthUrl; ?>" class="btn btn-light border w-100 mt-2 py-2 d-flex align-items-center justify-content-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48" class="me-2">
                                                <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4C12.955 4 4 12.955 4 24s8.955 20 20 20s20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                                                <path fill="#FF3D00" d="m6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4C16.318 4 9.656 8.337 6.306 14.691z"/>
                                                <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                                                <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002l6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                                            </svg>
                                            Sign in with Google
                                        </a>
                                    </div>
                                    
                                    <div class="text-center mt-4">
                                        <p class="mb-0">Don't have an account yet? <a href="register.php" class="link-primary fw-bold">Create an account</a></p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4 text-muted">
                    <small>&copy; <?php echo date('Y'); ?> SocialLinks. All rights reserved.</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <p class="mt-3 text-primary fw-bold">Signing you in...</p>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Add password toggle button
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const floatingDiv = passwordField.parentElement;
            
            // Create toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted pe-3';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.style.zIndex = '5';
            toggleBtn.onclick = function() {
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    passwordField.type = 'password';
                    toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
                }
            };
            
            // Add toggle button to the password field container
            floatingDiv.style.position = 'relative';
            floatingDiv.appendChild(toggleBtn);
        });
        
        // Form validation
        const form = document.getElementById('loginForm');
        
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                // Show loading overlay
                document.getElementById('loadingOverlay').classList.add('show');
            }
            
            form.classList.add('was-validated');
        });
        
        // Prevent multiple form submissions
        let formSubmitted = false;
        form.addEventListener('submit', function(event) {
            if (formSubmitted) {
                event.preventDefault();
                return;
            }
            formSubmitted = true;
        });
        
        // Add some animation to the form elements
        document.addEventListener('DOMContentLoaded', function() {
            const formElements = document.querySelectorAll('.form-floating, .btn-primary, .social-buttons');
            
            formElements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                element.style.transitionDelay = `${index * 0.1}s`;
                
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, 100);
            });
            
            // Add loading overlay for Google login
            const googleButton = document.querySelector('.social-buttons a');
            googleButton.addEventListener('click', function() {
                // Show loading overlay when clicking Google login
                document.getElementById('loadingOverlay').classList.add('show');
            });
        });
    </script>
</body>
</html>