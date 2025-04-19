<?php
// register.php - User registration with enhanced security, validation and Google OAuth
session_start();
require_once 'database.php';
require_once 'google-config.php'; // Create this file for Google OAuth configuration

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Form validation function
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialize variables
$username = "";
$email = "";
$errors = [];

// Check if coming from Google OAuth
$fromGoogle = isset($_GET['google']) && $_GET['google'] == '1';
$googleData = isset($_SESSION['google_data']) ? $_SESSION['google_data'] : null;

// Google Login setup
require_once 'vendor/autoload.php';

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
            
            // Store Google data in session for registration form
            $_SESSION['google_data'] = [
                'id' => $userData->id,
                'email' => $userData->email,
                'name' => $userData->name
            ];
            
            // Redirect to the registration form with Google flag
            header("Location: register.php?google=1");
            exit;
        } else {
            $errors[] = "Google authentication failed. Please try again.";
        }
    } catch (Exception $e) {
        $errors[] = "Error during Google authentication: " . $e->getMessage();
    }
}

// Pre-fill form with Google data if available
if ($fromGoogle && $googleData) {
    $email = $googleData['email'];
    // Generate a suggested username from Google name
    $suggestedUsername = strtolower(str_replace(' ', '', $googleData['name'])) . rand(100, 999);
    $username = $suggestedUsername;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    // Validate all inputs
    $username = validateInput($_POST['username']);
    $email = validateInput($_POST['email']);
    $googleId = $fromGoogle && $googleData ? $googleData['id'] : null;
    
    // If not from Google, validate password
    if (!$fromGoogle) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Password validation
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        // Confirm passwords match
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    } else {
        // Generate a random secure password for Google users
        $password = bin2hex(random_bytes(16));
    }
    
    // Username validation
    if (strlen($username) < 3 || strlen($username) > 20) {
        $errors[] = "Username must be between 3 and 20 characters";
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    }
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // If no errors, check if username or email already exists
    if (empty($errors)) {
        $checkSQL = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($checkSQL);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if ($user['username'] === $username) {
                $errors[] = "Username already exists";
            }
            if ($user['email'] === $email) {
                $errors[] = "Email already exists";
            }
        } else {
            // Hash password with strong algorithm and options
            $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
            
            // Insert new user, including Google ID if from Google
            $insertSQL = $googleId 
                ? "INSERT INTO users (username, email, password, google_id, created_at) VALUES (?, ?, ?, ?, NOW())" 
                : "INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($insertSQL);
            
            if ($googleId) {
                $stmt->bind_param("ssss", $username, $email, $passwordHash, $googleId);
            } else {
                $stmt->bind_param("sss", $username, $email, $passwordHash);
            }
            
            if ($stmt->execute()) {
                // Log registration
                $user_id = $stmt->insert_id;
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $logAction = $googleId ? 'google_register' : 'register';
                $logSQL = "INSERT INTO login_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())";
                $logStmt = $conn->prepare($logSQL);
                $logStmt->bind_param("isss", $user_id, $logAction, $ip, $userAgent);
                $logStmt->execute();
                
                // Set session
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['auth_time'] = time();
                
                // Clean up Google data from session
                if (isset($_SESSION['google_data'])) {
                    unset($_SESSION['google_data']);
                }
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $errors[] = "Registration failed: " . $conn->error;
            }
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
    <title>Register - Social Links</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./css/register.css">
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
                                    <h2 class="illustration-title">Join Our Community!</h2>
                                    <p class="mb-5">Create an account to connect with others and build your professional
                                        network.</p>

                                    <div class="mt-4">
                                        <div class="d-flex align-items-center mb-4">
                                            <div class="feature-icon">
                                                <i class="fas fa-user-plus"></i>
                                            </div>
                                            <span class="feature-text">Easy registration</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-4">
                                            <div class="feature-icon">
                                                <i class="fas fa-shield-alt"></i>
                                            </div>
                                            <span class="feature-text">Secure account</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div class="feature-icon">
                                                <i class="fas fa-globe"></i>
                                            </div>
                                            <span class="feature-text">Global community</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="login-banner">
                                    <i class="fas fa-bolt login-banner-icon"></i>
                                    <p class="login-banner-text">Join over 100,000 people on SocialLinks</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="card-body p-4 p-lg-5">
                                <div class="text-center mb-4">
                                    <div class="brand-mark d-block d-lg-none mb-3">SocialLinks</div>
                                    <h1 class="h3 mb-3 fw-bold text-primary">Create Your Account</h1>
                                    <p class="text-muted">Fill out the form below to get started</p>
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

                                <form method="POST" action="" id="registerForm" novalidate class="needs-validation">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo $_SESSION['csrf_token']; ?>">

                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="username" name="username"
                                            placeholder="Username" value="<?php echo $username; ?>" required>
                                        <label for="username">Username</label>
                                        <div class="invalid-feedback">Please choose a username (3-20 characters,
                                            letters, numbers, underscores only).</div>
                                    </div>

                                    <div class="form-floating mb-4">
                                        <input type="email" class="form-control" id="email" name="email"
                                            placeholder="Email address" value="<?php echo $email; ?>" required>
                                        <label for="email">Email address</label>
                                        <div class="invalid-feedback">Please enter a valid email address.</div>
                                    </div>

                                    <?php if (!$fromGoogle): ?>
                                    <div class="form-floating mb-4">
                                        <input type="password" class="form-control" id="password" name="password"
                                            placeholder="Password" required>
                                        <label for="password">Password</label>
                                        <div class="invalid-feedback">Password must be at least 8 characters with
                                            uppercase, lowercase, number, and special character.</div>
                                    </div>

                                    <div class="form-floating mb-4">
                                        <input type="password" class="form-control" id="confirm_password"
                                            name="confirm_password" placeholder="Confirm Password" required>
                                        <label for="confirm_password">Confirm Password</label>
                                        <div class="invalid-feedback">Passwords must match.</div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="form-check mb-4">
                                        <input type="checkbox" class="form-check-input" id="terms" name="terms"
                                            required>
                                        <label class="form-check-label" for="terms">I agree to the <a href="#"
                                                class="link-primary">Terms of Service</a> and <a href="#"
                                                class="link-primary">Privacy Policy</a></label>
                                        <div class="invalid-feedback">You must agree to our terms to register.</div>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 py-3 mb-3">
                                        <i class="fas fa-user-plus me-2"></i> Create Account
                                    </button>

                                    <div class="social-login">
                                        <span class="social-login-text">Or sign up with</span>
                                    </div>

                                    <div class="social-buttons">
                                        <a href="<?php echo $googleAuthUrl; ?>" class="social-button">
                                            <i class="fab fa-google"></i>
                                        </a>
                                    </div>

                                    <div class="text-center mt-4">
                                        <p class="mb-0">Already have an account? <a href="login.php"
                                                class="link-primary fw-bold">Sign in</a></p>
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
        <p class="mt-3 text-primary fw-bold">Creating your account...</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Password strength meter
    function checkPasswordStrength(password) {
        let strength = 0;

        // If password is 8 characters or more, add 1 point
        if (password.length >= 8) strength += 1;

        // If password contains lowercase letters, add 1 point
        if (password.match(/[a-z]+/)) strength += 1;

        // If password contains uppercase letters, add 1 point
        if (password.match(/[A-Z]+/)) strength += 1;

        // If password contains numbers, add 1 point
        if (password.match(/[0-9]+/)) strength += 1;

        // If password contains special characters, add 1 point
        if (password.match(/[^A-Za-z0-9]+/)) strength += 1;

        return strength;
    }

    // Update password strength indicator
    document.getElementById('password') && document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const strength = checkPasswordStrength(password);

        // If we had a visual strength meter, we would update it here
        // For now we'll just change the color of the password field border
        if (strength < 3) {
            this.style.borderColor = '#ef4444'; // weak - red
        } else if (strength < 5) {
            this.style.borderColor = '#f59e0b'; // medium - orange
        } else {
            this.style.borderColor = '#10b981'; // strong - green
        }
    });

    // Password matching validation
    document.getElementById('confirm_password') && document.getElementById('confirm_password').addEventListener('input',
        function() {
            const password = document.getElementById('password').value;
            if (this.value !== password) {
                this.setCustomValidity("Passwords don't match");
                this.style.borderColor = '#ef4444';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '#10b981';
            }
        });

    // Form validation
    (function() {
        'use strict';

        // Fetch the form we want to validate
        const form = document.getElementById('registerForm');

        // Add event listener for form submission
        form.addEventListener('submit', function(event) {
            // Check if the form is valid
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                // If form is valid, show loading overlay
                document.getElementById('loadingOverlay').classList.add('show');
            }

            form.classList.add('was-validated');
        }, false);

        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            if (username.length < 3 || username.length > 20) {
                this.setCustomValidity('Username must be between 3 and 20 characters');
            } else if (!username.match(/^[a-zA-Z0-9_]+$/)) {
                this.setCustomValidity('Username can only contain letters, numbers, and underscores');
            } else {
                this.setCustomValidity('');
            }
        });

        // Email validation using HTML5 built-in validation
        document.getElementById('email').addEventListener('input', function() {
            if (this.validity.typeMismatch) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });

        // Password validation - only if not from Google
        if (document.getElementById('password')) {
            document.getElementById('password').addEventListener('input', function() {
                const password = this.value;
                let errorMsg = '';

                if (password.length < 8) {
                    errorMsg = 'Password must be at least 8 characters long';
                } else if (!password.match(/[A-Z]/)) {
                    errorMsg = 'Password must contain at least one uppercase letter';
                } else if (!password.match(/[a-z]/)) {
                    errorMsg = 'Password must contain at least one lowercase letter';
                } else if (!password.match(/[0-9]/)) {
                    errorMsg = 'Password must contain at least one number';
                } else if (!password.match(/[^A-Za-z0-9]/)) {
                    errorMsg = 'Password must contain at least one special character';
                }

                this.setCustomValidity(errorMsg);
            });
        }

        // Terms checkbox validation
        document.getElementById('terms').addEventListener('change', function() {
            if (!this.checked) {
                this.setCustomValidity('You must agree to our terms to register');
            } else {
                this.setCustomValidity('');
            }
        });

        // Make password toggleable (show/hide)
        const togglePasswordButtons = document.querySelectorAll('.password-toggle');
        togglePasswordButtons.forEach(button => {
            button.addEventListener('click', function() {
                const passwordInput = this.previousElementSibling;
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' :
                    'password';
                passwordInput.setAttribute('type', type);

                // Toggle icon
                const icon = this.querySelector('i');
                if (type === 'password') {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            });
        });
    })();

    // Initialize tooltips if they're used
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    </script>
</body>

</html>