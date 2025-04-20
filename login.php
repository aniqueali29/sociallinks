<?php
// login.php - User login with enhanced security and Google OAuth
session_start();
// require_once 'google-debug.php'; // Include our new debugging functions
require_once 'database.php';
require_once 'google-config.php'; // Include Google OAuth configuration

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

// Google Login setup
require_once 'vendor/autoload.php';

$googleClient = new Google_Client();
$googleClient->setClientId($googleClientId);
$googleClient->setClientSecret($googleClientSecret);
$googleClient->setRedirectUri($googleLoginRedirectUrl);
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
            
            // Check if user exists with this Google ID or email
            $googleId = $userData->id;
            $userEmail = $userData->email;
            
            // Improved SQL query with proper column naming
            $sql = "SELECT user_id, username, status, google_id FROM users WHERE google_id = ? OR email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $googleId, $userEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Add debugging
            error_log("Google login attempt for ID: $googleId, Email: $userEmail");
            error_log("Query result rows: " . $result->num_rows);
            
            if ($result->num_rows === 1) {
                // User exists, log them in
                $user = $result->fetch_assoc();
                
                // Add debugging
                error_log("Found user: " . print_r($user, true));
                
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $errors[] = "Account is inactive or suspended. Please contact support.";
                } else {
                    // Log successful login
                    $user_id = $user['user_id']; // Use correct field name
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $userAgent = $_SERVER['HTTP_USER_AGENT'];
                    $logAction = 'google_login';
                    $logSQL = "INSERT INTO login_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())";
                    $logStmt = $conn->prepare($logSQL);
                    $logStmt->bind_param("isss", $user_id, $logAction, $ip, $userAgent);
                    $logStmt->execute();
                    
                    // If user was found by email but Google ID is not set, update it
                    if (empty($user['google_id'])) {
                        $updateSQL = "UPDATE users SET google_id = ? WHERE user_id = ?";
                        $updateStmt = $conn->prepare($updateSQL);
                        $updateStmt->bind_param("si", $googleId, $user_id);
                        $updateStmt->execute();
                        error_log("Updated Google ID for user $user_id");
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
                    
                    error_log("Google login successful for user: $user_id");
                    
                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit;
                }
            }
        } else {
            $errors[] = "Google authentication failed. Please try again.";
            error_log("Google auth error: " . print_r($token['error'], true));
        }
    } catch (Exception $e) {
        $errors[] = "Error during Google authentication: " . $e->getMessage();
        error_log("Google auth exception: " . $e->getMessage());
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
        // Fixed SQL query to use proper column names
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
                $logAction = 'login';
                $logSQL = "INSERT INTO login_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())";
                $logStmt = $conn->prepare($logSQL);
                $logStmt->bind_param("isss", $user_id, $logAction, $ip, $userAgent);
                $logStmt->execute();
                
                // Update password hash if needed (if using an older algorithm)
                if (password_needs_rehash($user['password'], PASSWORD_ARGON2ID)) {
                    $newHash = password_hash($password, PASSWORD_ARGON2ID);
                    $updateSQL = "UPDATE users SET password = ? WHERE id = ?";
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
                $logAction = 'failed_login';
                $logSQL = "INSERT INTO login_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())";
                $logStmt = $conn->prepare($logSQL);
                $logStmt->bind_param("isss", $user['user_id'], $logAction, $ip, $userAgent);
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
            $logAction = 'failed_login_unknown';
            $logSQL = "INSERT INTO login_logs (user_id, action, ip_address, user_agent, created_at) VALUES (NULL, ?, ?, ?, NOW())";
            $logStmt = $conn->prepare($logSQL);
            $logStmt->bind_param("sss", $logAction, $ip, $userAgent);
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
    <style>
    :root {
        --primary-color: #4e6bff;
        --primary-dark: #3a56e8;
        --primary-light: #e0e7ff;
        --secondary-color: #10b981;
        --text-color: #111827;
        --light-text: #6b7280;
        --error-color: #ef4444;
        --border-color: #e5e7eb;
        --success-color: #10b981;
        --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
        --input-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    /* Enhanced Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes slideInUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes gradientAnimation {
        0% {
            background-position: 0% 50%;
        }

        50% {
            background-position: 100% 50%;
        }

        100% {
            background-position: 0% 50%;
        }
    }

    @keyframes float {
        0% {
            transform: translate(0, 0) rotate(0deg);
        }

        25% {
            transform: translate(10px, 15px) rotate(3deg);
        }

        50% {
            transform: translate(5px, -10px) rotate(-3deg);
        }

        75% {
            transform: translate(-10px, 15px) rotate(5deg);
        }

        100% {
            transform: translate(0, 0) rotate(0deg);
        }
    }

    @keyframes pulse {
        0% {
            transform: scale(0.95);
            opacity: 0.7;
        }

        50% {
            transform: scale(1.05);
            opacity: 1;
        }

        100% {
            transform: scale(0.95);
            opacity: 0.7;
        }
    }

    @keyframes buttonGlow {
        0% {
            transform: translateX(-100%);
        }

        50%,
        100% {
            transform: translateX(100%);
        }
    }

    body {
        font-family: 'Poppins', 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(-45deg, #f0f4ff, #e0edff, #dbeafe, #e6e9ff);
        background-size: 400% 400%;
        animation: gradientAnimation 15s ease infinite;
        color: var(--text-color);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow-x: hidden;
    }

    .background-shapes {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -2;
        overflow: hidden;
    }

    .shape {
        position: absolute;
        border-radius: 50%;
        opacity: 0.3;
        filter: blur(20px);
    }

    .shape-1 {
        width: 300px;
        height: 300px;
        background: linear-gradient(45deg, #4e6bff, #818cf8);
        top: -150px;
        right: -50px;
        animation: float 15s ease-in-out infinite;
    }

    .shape-2 {
        width: 200px;
        height: 200px;
        background: linear-gradient(45deg, #10b981, #34d399);
        bottom: -100px;
        left: -50px;
        animation: float 18s ease-in-out infinite;
    }

    .shape-3 {
        width: 150px;
        height: 150px;
        background: linear-gradient(45deg, #f59e0b, #fbbf24);
        top: 30%;
        left: 10%;
        animation: float 12s ease-in-out infinite 1s;
    }

    .shape-4 {
        width: 100px;
        height: 100px;
        background: linear-gradient(45deg, #ef4444, #f87171);
        bottom: 20%;
        right: 10%;
        animation: float 10s ease-in-out infinite 0.5s;
    }

    .particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
    }

    .particle {
        position: absolute;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: rgba(79, 70, 229, 0.2);
    }

    .auth-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        width: 100%;
    }

    .card {
        border: none;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        backdrop-filter: blur(10px);
        background-color: rgba(255, 255, 255, 0.9);
        max-width: 1100px;
        width: 100%;
        margin: 0 auto;
        animation: slideInUp 0.8s ease-out forwards;
        border: 1px solid rgba(255, 255, 255, 0.3);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .auth-illustration {
        background: linear-gradient(135deg, #4e6bff 0%, #7e74f1 100%);
        height: 100%;
        border-radius: 20px 0 0 20px;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .auth-illustration::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
        animation: pulse 15s infinite;
    }

    .auth-illustration::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }

    .auth-illustration-content {
        padding: 50px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        z-index: 2;
    }

    .brand-mark {
        font-size: 32px;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: -1px;
        margin-bottom: 15px;
        display: inline-block;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        position: relative;
    }

    .brand-mark::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 0;
        width: 40px;
        height: 4px;
        background: #ffffff;
        border-radius: 2px;
    }

    .illustration-title {
        color: #ffffff;
        font-size: 36px;
        font-weight: 700;
        margin-bottom: 20px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        animation: fadeIn 1s ease forwards;
    }

    .feature-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background-color: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(5px);
        color: #ffffff;
        margin-right: 16px;
        font-size: 18px;
        transition: all 0.3s ease;
    }

    .feature-text {
        font-weight: 500;
        color: #ffffff;
        font-size: 16px;
    }

    .login-banner {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        padding: 15px 30px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(8px);
        border-radius: 100px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        animation: fadeIn 1s ease forwards 0.8s;
        opacity: 0;
    }

    .login-banner-icon {
        color: #ffffff;
        font-size: 18px;
    }

    .login-banner-text {
        font-weight: 500;
        margin: 0;
        font-size: 15px;
        color: #ffffff;
    }

    .card-body {
        padding: 3rem;
    }

    .form-control {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 15px 20px;
        transition: all 0.3s ease;
        background-color: rgba(249, 250, 251, 0.5);
        box-shadow: var(--input-shadow);
        font-size: 16px;
        height: calc(3.5rem + 2px);
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(78, 107, 255, 0.2);
        background-color: white;
        transform: translateY(-2px);
    }

    .form-floating>.form-control:focus~label,
    .form-floating>.form-control:not(:placeholder-shown)~label {
        transform: scale(0.85) translateY(-0.75rem) translateX(0.15rem);
        color: var(--primary-color);
        font-weight: 600;
    }

    .form-floating>label {
        padding: 1rem 1.25rem;
        color: var(--light-text);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        border: none;
        border-radius: 12px;
        padding: 15px 28px;
        font-weight: 700;
        letter-spacing: 0.5px;
        transition: all 0.4s ease;
        box-shadow: 0 4px 15px rgba(78, 107, 255, 0.3);
        position: relative;
        overflow: hidden;
        font-size: 16px;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(78, 107, 255, 0.4);
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
    }

    .btn-primary:active {
        transform: translateY(0);
        box-shadow: 0 4px 15px rgba(78, 107, 255, 0.3);
    }

    .btn-hover-effect {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transform: translateX(-100%);
        animation: buttonGlow 3s infinite;
    }

    .form-check-input {
        width: 20px;
        height: 20px;
        margin-top: 0;
        cursor: pointer;
        border: 2px solid var(--light-text);
        border-radius: 5px;
    }

    .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .form-check-label {
        margin-left: 8px;
        cursor: pointer;
        font-size: 15px;
    }

    .social-login {
        margin: 30px 0;
        position: relative;
        text-align: center;
    }

    .social-login:before {
        content: "";
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 1px;
        background-color: var(--border-color);
        z-index: 1;
    }

    .social-login-text {
        display: inline-block;
        padding: 0 16px;
        background-color: white;
        position: relative;
        z-index: 2;
        color: var(--light-text);
        font-size: 14px;
    }

    .social-login-container {
        display: flex;
        justify-content: center;
        margin: 30px 0;
    }

    .google-signin-button {
        display: flex;
        align-items: center;
        background-color: white;
        border: 1px solid #dadce0;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        color: #3c4043;
        cursor: pointer;
        font-family: 'Roboto', sans-serif;
        font-size: 16px;
        font-weight: 500;
        height: 64px;
        letter-spacing: 0.25px;
        max-width: 320px;
        overflow: hidden;
        padding: 0;
        position: relative;
        text-align: center;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        width: 100%;
    }

    .google-signin-button:hover {
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        transform: translateY(-2px) scale(1.02);
        background-color: #f8f9fa;
    }

    .google-signin-button:active {
        background-color: #f1f3f4;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        transform: scale(0.98);
    }

    .google-icon-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: white;
        border-radius: 16px 0 0 16px;
        height: 100%;
        padding: 0 24px;
        margin-right: 8px;
    }

    .google-icon {
        height: 24px;
        width: 24px;
    }

    .button-text {
        flex-grow: 1;
        padding: 0 16px 0 0;
        text-align: center;
        font-weight: 500;
    }

    .new-user-banner {
        background-color: rgba(248, 250, 252, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        border-radius: 16px;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .new-user-banner:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .new-user-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);
        z-index: -1;
    }

    .btn-light {
        background: white;
        border-radius: 30px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-light:hover {
        background: var(--primary-color);
        color: white !important;
        box-shadow: 0 6px 15px rgba(78, 107, 255, 0.2);
        transform: translateY(-2px);
    }

    .link-primary {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s ease;
        position: relative;
    }

    .link-primary::after {
        content: '';
        position: absolute;
        width: 0;
        height: 2px;
        bottom: -2px;
        left: 0;
        background-color: var(--primary-color);
        transition: width 0.3s ease;
    }

    .link-primary:hover::after {
        width: 100%;
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(8px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        visibility: hidden;
        opacity: 0;
        transition: all 0.3s ease;
    }

    .loading-overlay.show {
        visibility: visible;
        opacity: 1;
    }

    .spinner {
        width: 60px;
        height: 60px;
        border: 5px solid rgba(78, 107, 255, 0.2);
        border-top: 5px solid var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        box-shadow: 0 0 15px rgba(78, 107, 255, 0.3);
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Additional flourishes */
    .floating-shapes {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        pointer-events: none;
        z-index: -3;
    }

    .floating-shape {
        position: absolute;
        opacity: 0.3;
    }

    .floating-shape-1 {
        top: 15%;
        left: 5%;
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: var(--primary-color);
        transform: rotate(15deg);
        animation: float 15s ease-in-out infinite;
    }

    .floating-shape-2 {
        bottom: 10%;
        right: 10%;
        width: 70px;
        height: 70px;
        border: 3px solid var(--primary-color);
        border-radius: 50%;
        animation: float 18s ease-in-out infinite 2s;
    }

    .floating-shape-3 {
        top: 60%;
        right: 5%;
        width: 35px;
        height: 35px;
        background: var(--secondary-color);
        clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
        animation: float 12s ease-in-out infinite 1s;
    }

    /* Responsive adjustments */
    @media (max-width: 991.98px) {
        .card {
            margin: 20px;
        }

        .card-body {
            padding: 2rem;
        }

        .brand-mark,
        .illustration-title {
            font-size: 26px;
        }
    }

    @media (max-width: 767.98px) {
        .auth-illustration {
            display: none;
        }

        body {
            background: linear-gradient(-45deg, #f0f4ff, #e0edff, #dbeafe, #e6e9ff);
        }

        .card {
            background-color: white;
            backdrop-filter: none;
        }
    }
    </style>
</head>

<body>
    <div class="background-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>

    <div class="floating-shapes">
        <div class="floating-shape floating-shape-1"></div>
        <div class="floating-shape floating-shape-2"></div>
        <div class="floating-shape floating-shape-3"></div>
    </div>

    <div class="particles" id="particles"></div>

    <div class="container auth-container">
        <div class="row justify-content-center w-100">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="row g-0">
                        <div class="col-lg-5 d-none d-lg-block">
                            <div class="auth-illustration h-100">
                                <div class="auth-illustration-content">
                                    <div class="brand-mark">SocialLinks</div>
                                    <h2 class="illustration-title">Welcome Back!</h2>
                                    <p class="mb-5 text-white opacity-80">Sign in to access your account and continue
                                        your journey with us.</p>

                                    <div class="mt-5">
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
                                <div class="text-center mb-5">
                                    <div class="brand-mark d-block d-lg-none mb-3 text-primary">SocialLinks</div>
                                    <h1 class="h2 mb-3 fw-bold text-primary">Sign In to Your Account</h1>
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
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo $_SESSION['csrf_token']; ?>">

                                    <div class="form-floating mb-4">
                                        <input type="email" class="form-control" id="email" name="email"
                                            placeholder="Email address" value="<?php echo $email; ?>" required>
                                        <label for="email">Email address</label>
                                        <div class="invalid-feedback">Please enter a valid email address.</div>
                                    </div>

                                    <div class="form-floating mb-4">
                                        <input type="password" class="form-control" id="password" name="password"
                                            placeholder="Password" required>
                                        <label for="password">Password</label>
                                        <div class="invalid-feedback">Please enter your password.</div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="remember"
                                                name="remember">
                                            <label class="form-check-label" for="remember">Remember me</label>
                                        </div>
                                        <a href="forgot-password.php" class="link-primary">Forgot password?</a>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 py-3 mb-3">
                                        <span class="btn-text-inner">
                                            <i class="fas fa-sign-in-alt me-2"></i> Sign In
                                        </span>
                                        <span class="btn-hover-effect"></span>
                                    </button>

                                    <div class="social-login">
                                        <span class="social-login-text">Or continue with</span>
                                    </div>

                                    <div class="social-login-container">
                                        <a href="<?php echo $googleAuthUrl; ?>" class="google-signin-button">
                                            <div class="google-icon-wrapper">
                                                <!-- Google "G" logo as SVG directly in the code -->
                                                <svg class="google-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                                                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                                                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                                                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                                                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                                                    <path fill="none" d="M0 0h48v48H0z"/>
                                                </svg>
                                            </div>
                                            <span class="button-text">Sign in with Google</span>
                                        </a>
                                    </div>

                                    <div
                                        class="new-user-banner mt-5 p-4 rounded-4 text-center position-relative overflow-hidden">
                                        <div class="new-user-bg"></div>
                                        <div class="position-relative">
                                            <i class="fas fa-user-plus fs-4 mb-2 text-primary"></i>
                                            <h6 class="mb-2">New to SocialLinks?</h6>
                                            <p class="small mb-3">Join our community of over 100,000 users today!</p>
                                            <a href="register.php"
                                                class="btn btn-light link-primary btn-sm px-4 py-2 fw-bold">
                                                Create Account <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        </div>
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
    });









    // Add this to your existing JavaScript

    // Enhanced animations for form elements
    document.addEventListener('DOMContentLoaded', function() {
        // Staggered animation for form elements
        const formElements = document.querySelectorAll(
            '.form-floating, .btn-primary, .social-buttons, .form-check, .new-user-banner');

        formElements.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            element.style.transitionDelay = `${index * 0.1}s`;

            setTimeout(() => {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, 100);
        });

        // Form field focus animations
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(control => {
            control.addEventListener('focus', function() {
                this.parentElement.classList.add('input-focused');
            });

            control.addEventListener('blur', function() {
                if (this.value === '') {
                    this.parentElement.classList.remove('input-focused');
                }
            });
        });

        // Password strength indicator
        const passwordField = document.getElementById('password');
        if (passwordField) {
            const strengthIndicator = document.createElement('div');
            strengthIndicator.className = 'password-strength mt-1';
            strengthIndicator.innerHTML = `
            <div class="progress" style="height: 5px;">
                <div class="progress-bar bg-danger" role="progressbar" style="width: 0%"></div>
            </div>
            <small class="text-muted mt-1 d-block password-feedback"></small>
        `;

            passwordField.parentElement.appendChild(strengthIndicator);

            passwordField.addEventListener('input', function() {
                const value = this.value;
                const progressBar = strengthIndicator.querySelector('.progress-bar');
                const feedback = strengthIndicator.querySelector('.password-feedback');

                if (value.length === 0) {
                    progressBar.style.width = '0%';
                    progressBar.className = 'progress-bar';
                    feedback.textContent = '';
                    return;
                }

                let strength = 0;

                // Add strength based on length
                if (value.length > 6) strength += 20;
                if (value.length > 10) strength += 10;

                // Add strength for character types
                if (/[A-Z]/.test(value)) strength += 20;
                if (/[a-z]/.test(value)) strength += 15;
                if (/[0-9]/.test(value)) strength += 15;
                if (/[^A-Za-z0-9]/.test(value)) strength += 20;

                progressBar.style.width = `${strength}%`;

                if (strength < 30) {
                    progressBar.className = 'progress-bar bg-danger';
                    feedback.textContent = 'Weak password';
                } else if (strength < 60) {
                    progressBar.className = 'progress-bar bg-warning';
                    feedback.textContent = 'Moderate password';
                } else {
                    progressBar.className = 'progress-bar bg-success';
                    feedback.textContent = 'Strong password';
                }
            });
        }

        // Add subtle parallax effect to the background shapes
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;

            shapes.forEach(shape => {
                const speed = parseFloat(shape.getAttribute('data-speed') || 0.05);
                const moveX = (x - 0.5) * speed * 100;
                const moveY = (y - 0.5) * speed * 100;

                shape.style.transform = `translate(${moveX}px, ${moveY}px)`;
            });
        });
    });

    // Enhance the loading overlay
    document.getElementById('loginForm').addEventListener('submit', function(event) {
        if (this.checkValidity()) {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.add('show');

            // Add animated text to the loading overlay
            const loadingText = loadingOverlay.querySelector('p');
            const originalText = loadingText.textContent;
            let dots = 0;

            const textAnimation = setInterval(() => {
                dots = (dots + 1) % 4;
                loadingText.textContent = originalText.replace('...', '.'.repeat(dots));
            }, 500);

            // This is just for demo purposes to simulate loading
            // Remove this setTimeout in production
            setTimeout(() => {
                clearInterval(textAnimation);
            }, 5000);
        }
    });
    </script>

</body>

</html>