<?php
// index.php - Landing page
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Links - Share Your Social Media Profiles</title>
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
            --gradient-primary: linear-gradient(135deg, var(--primary-color) 0%, #8E2DE2 100%);
            --gradient-accent: linear-gradient(135deg, var(--accent-color) 0%, #00BFA6 100%);
            --gradient-text: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            --shadow-sm: 0 2px 15px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 15px 50px rgba(0, 0, 0, 0.1);
            --shadow-primary: 0 10px 20px rgba(108, 99, 255, 0.3);
            --transition: all 0.3s ease;
            --border-radius-sm: 10px;
            --border-radius-md: 15px;
            --border-radius-lg: 20px;
            --border-radius-full: 50px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            color: var(--dark-color);
            background-color: #fcfcfc;
        }
        
        /* Glass morphism effect for navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
            padding: 15px 0;
            transition: var(--transition);
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar-brand i {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .navbar-nav .nav-link {
            color: var(--dark-color) !important;
            font-weight: 500;
            margin: 0 10px;
            position: relative;
            transition: var(--transition);
            padding: 8px 15px;
            border-radius: var(--border-radius-full);
        }
        
        .navbar-nav .nav-link:hover {
            color: var(--primary-color) !important;
            background-color: rgba(108, 99, 255, 0.08);
        }
        
        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            background-color: var(--primary-color);
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        
        .navbar-nav .nav-link:hover::after {
            width: 70%;
        }
        
        /* Hero section with particles */
        .hero {
            background: var(--gradient-primary);
            padding: 180px 0 160px;
            position: relative;
            overflow: hidden;
        }
        
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 15s infinite ease-in-out;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(100px, 50px) rotate(90deg); }
            50% { transform: translate(50px, 100px) rotate(180deg); }
            75% { transform: translate(-50px, 50px) rotate(270deg); }
        }
        
        .hero .container {
            position: relative;
            z-index: 10;
        }
        
        .hero h1 {
            font-weight: 700;
            margin-bottom: 30px;
            font-size: 3.8rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 1.35rem;
            font-weight: 300;
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0.95;
            line-height: 1.6;
        }
        
        .hero-image {
            max-width: 100%;
            animation: floatImage 6s ease-in-out infinite;
            filter: drop-shadow(0 20px 30px rgba(0, 0, 0, 0.2));
            margin-top: 30px;
            border-radius: var(--border-radius-md);
        }
        
        @keyframes floatImage {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .btn-hero {
            padding: 14px 32px;
            border-radius: var(--border-radius-full);
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            z-index: 1;
            border: none;
        }
        
        .btn-hero.primary {
            background: white;
            color: var(--primary-color);
        }
        
        .btn-hero.primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: var(--gradient-accent);
            transition: 0.5s ease;
            z-index: -1;
        }
        
        .btn-hero.primary:hover {
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .btn-hero.primary:hover::before {
            width: 100%;
        }
        
        .btn-hero.secondary {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-hero.secondary:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .btn-icon {
            margin-left: 8px;
            transition: transform 0.3s ease;
        }
        
        .btn-hero:hover .btn-icon {
            transform: translateX(5px);
        }
        
        .wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            filter: drop-shadow(0 -5px 5px rgba(0, 0, 0, 0.05));
        }
        
        /* Stats counter section */
        .stats-section {
            padding: 60px 0;
            background-color: white;
            position: relative;
            box-shadow: var(--shadow-sm);
        }
        
        .stats-card {
            text-align: center;
            padding: 20px;
            transition: var(--transition);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            background: var(--gradient-text);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stats-label {
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85rem;
        }
        
        /* Features section */
        .features-section {
            padding: 120px 0;
            background-color: white;
            position: relative;
        }
        
        .section-title {
            font-weight: 700;
            margin-bottom: 60px;
            position: relative;
            display: inline-block;
            font-size: 2.5rem;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            width: 70px;
            height: 4px;
            background: var(--gradient-primary);
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        .feature-card {
            background: white;
            border-radius: var(--border-radius-md);
            padding: 40px 30px;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
            height: 100%;
            border-bottom: 4px solid transparent;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 0;
            background: linear-gradient(to top, rgba(108, 99, 255, 0.05), transparent);
            transition: var(--transition);
            z-index: -1;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
            border-bottom: 4px solid var(--primary-color);
        }
        
        .feature-card:hover::before {
            height: 100%;
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 25px;
            color: var(--primary-color);
            background: rgba(108, 99, 255, 0.1);
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            transition: var(--transition);
        }
        
        .feature-card:hover .feature-icon {
            background: var(--primary-color);
            color: white;
            transform: rotateY(180deg);
            border-radius: 50%;
        }
        
        .feature-card h3 {
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.5rem;
            transition: var(--transition);
        }
        
        .feature-card:hover h3 {
            color: var(--primary-color);
        }
        
        .feature-card p {
            color: #666;
            line-height: 1.7;
        }
        
        /* Testimonials section */
        .testimonials-section {
            padding: 120px 0;
            background: #f9f9ff;
            position: relative;
            overflow: hidden;
        }
        
        .testimonial-card {
            background: white;
            border-radius: var(--border-radius-md);
            padding: 40px;
            box-shadow: var(--shadow-md);
            margin: 20px 10px;
            position: relative;
            transition: var(--transition);
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .testimonial-quote {
            color: var(--primary-color);
            font-size: 4rem;
            position: absolute;
            top: 20px;
            right: 30px;
            opacity: 0.1;
        }
        
        .testimonial-text {
            font-style: italic;
            color: #555;
            line-height: 1.8;
            margin-bottom: 25px;
            font-size: 1.1rem;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .testimonial-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .testimonial-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .testimonial-info h4 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .testimonial-info p {
            margin: 0;
            color: #777;
            font-size: 0.9rem;
        }
        
        .testimonial-rating {
            color: #FFD700;
            margin-top: 5px;
        }
        
        /* CTA section */
        .cta-section {
            background-color: #F8F9FA;
            padding: 120px 0;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('/api/placeholder/1400/400') center center/cover;
            opacity: 0.05;
        }
        
        .cta-content {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 60px;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 10;
            border: 1px solid rgba(108, 99, 255, 0.1);
        }
        
        .cta-content h2 {
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 2.5rem;
            background: var(--gradient-text);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .cta-content p {
            font-size: 1.25rem;
            color: #555;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-cta {
            padding: 15px 40px;
            border-radius: var(--border-radius-full);
            font-weight: 600;
            font-size: 1.1rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            transition: var(--transition);
            box-shadow: var(--shadow-primary);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
            z-index: -1;
        }
        
        .btn-cta:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(108, 99, 255, 0.4);
        }
        
        .btn-cta:hover::before {
            left: 100%;
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(5deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
        
        /* Footer */
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 80px 0 40px;
            position: relative;
            overflow: hidden;
        }
        
        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 70% 10%, rgba(108, 99, 255, 0.1), transparent 50%);
        }
        
        .footer-heading {
            color: white;
            font-weight: 600;
            margin-bottom: 25px;
            font-size: 1.3rem;
            position: relative;
            padding-bottom: 15px;
        }
        
        .footer-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 2px;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 40px;
        }
        
        .footer-about {
            flex: 1 1 300px;
        }
        
        .footer-links-container {
            flex: 1 1 150px;
        }
        
        .footer-logo {
            font-weight: 700;
            font-size: 1.8rem;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .footer-logo span {
            color: var(--primary-color);
        }
        
        .footer-about p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
            line-height: 1.7;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
            display: inline-block;
            position: relative;
            padding-left: 20px;
        }
        
        .footer-links a::before {
            content: '→';
            position: absolute;
            left: 0;
            color: var(--primary-color);
            opacity: 0;
            transform: translateX(-10px);
            transition: var(--transition);
        }
        
        .footer-links a:hover {
            color: white;
            padding-left: 25px;
        }
        
        .footer-links a:hover::before {
            opacity: 1;
            transform: translateX(0);
        }
        
        .footer-newsletter p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
            line-height: 1.7;
        }
        
        .newsletter-form {
            display: flex;
            margin-bottom: 20px;
        }
        
        .newsletter-input {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius-full) 0 0 var(--border-radius-full);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .newsletter-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0 25px;
            border-radius: 0 var(--border-radius-full) var(--border-radius-full) 0;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .newsletter-button:hover {
            background: var(--secondary-color);
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
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            z-index: 1;
            text-decoration: none;
        }
        
        .social-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-primary);
            opacity: 0;
            z-index: -1;
            transition: var(--transition);
            transform: scale(0.5);
            border-radius: 50%;
        }
        
        .social-icon:hover {
            transform: translateY(-3px);
            color: white;
        }
        
        .social-icon:hover::before {
            opacity: 1;
            transform: scale(1);
        }
        
        .copyright {
            margin-top: 60px;
            color: rgba(255, 255, 255, 0.5);
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .hero h1 {
                font-size: 2.8rem;
            }
            
            .navbar-collapse {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                padding: 20px;
                border-radius: var(--border-radius-sm);
                box-shadow: var(--shadow-sm);
                margin-top: 10px;
            }
            
            .navbar-nav .nav-link {
                padding: 10px 15px;
            }
            
            .cta-content {
                padding: 40px 20px;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 40px;
            }
            
            .footer-links-container {
                width: 100%;
            }
        }
        
        @media (max-width: 767px) {
            .hero {
                padding: 140px 0 120px;
            }
            
            .hero h1 {
                font-size: 2.4rem;
            }
            
            .stats-section {
                padding: 40px 0;
            }
            
            .stats-card {
                margin-bottom: 30px;
            }
            
            .feature-card {
                margin-bottom: 30px;
            }
        }
        
        /* Preloader */
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        
        .preloader.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .loader {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(108, 99, 255, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Back to top button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 999;
            box-shadow: 0 5px 15px rgba(108, 99, 255, 0.3);
        }
        
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background: var(--secondary-color);
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader">
        <div class="loader"></div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-link me-2"></i>SocialLinks
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Testimonials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#cta">Get Started</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-hero primary" href="register.php">Sign Up Free</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero text-center text-white">
        <!-- Particles background -->
        <div class="particles">
            <?php for ($i = 0; $i < 10; $i++): ?>
                <div class="particle" style="
                    width: <?php echo rand(50, 300); ?>px;
                    height: <?php echo rand(50, 300); ?>px;
                    left: <?php echo rand(0, 100); ?>%;
                    top: <?php echo rand(0, 100); ?>%;
                    opacity: <?php echo rand(1, 10) / 20; ?>;
                    animation-delay: <?php echo rand(0, 5); ?>s;
                    animation-duration: <?php echo rand(10, 25); ?>s;
                "></div>
            <?php endfor; ?>
        </div>
        
<div class="container">
            <h1 class="animate__animated animate__fadeIn">One Link to Connect<br>Your Entire Digital World</h1>
            <p class="hero-subtitle animate__animated animate__fadeIn animate__delay-1s">Create your personalized landing page that beautifully showcases all your social profiles, websites, and online content in one simple link.</p>
            <div class="animate__animated animate__fadeIn animate__delay-2s">
                <a href="register.php" class="btn btn-hero primary me-3 mb-3">
                    Create Your Page <i class="fas fa-arrow-right btn-icon"></i>
                </a>
                <a href="#features" class="btn btn-hero secondary mb-3">
                    Discover Features <i class="fas fa-chevron-down btn-icon"></i>
                </a>
            </div>
            <div class="mt-5 animate__animated animate__fadeIn animate__delay-3s">
                <img src="./uploads/image3.png" alt="SocialLinks Preview" class="hero-image img-fluid">
            </div>
        </div>
        <svg class="wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
            <path fill="#ffffff" fill-opacity="1" d="M0,128L48,138.7C96,149,192,171,288,176C384,181,480,171,576,144C672,117,768,75,864,80C960,85,1056,139,1152,154.7C1248,171,1344,149,1392,138.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>
    </section>

    <!-- Stats Counter Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number" data-counter="50000">50,000+</div>
                        <div class="stats-label">Active Users</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number" data-counter="5">5 Million+</div>
                        <div class="stats-label">Profile Views</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number" data-counter="100">100+</div>
                        <div class="stats-label">Supported Platforms</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number" data-counter="98">98%</div>
                        <div class="stats-label">Satisfaction Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 class="text-center section-title">Why Choose SocialLinks</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon floating">
                            <i class="fas fa-link"></i>
                        </div>
                        <h3>All-in-One Profile</h3>
                        <p>Consolidate all your social media profiles, websites, and digital platforms in one beautiful, easy-to-share location.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon floating">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <h3>Custom QR Codes</h3>
                        <p>Generate branded QR codes for your profile page to make in-person sharing and networking effortless.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon floating">
                            <i class="fas fa-paint-brush"></i>
                        </div>
                        <h3>Beautiful Themes</h3>
                        <p>Express your personal brand with customizable themes, colors, and layouts that make your profile stand out.</p>
                    </div>
                </div>
                <div class="col-md-4 mt-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon floating">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Engagement Analytics</h3>
                        <p>Track views, clicks, and engagement with detailed analytics to understand which platforms perform best.</p>
                    </div>
                </div>
                <div class="col-md-4 mt-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon floating">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3>Mobile Optimized</h3>
                        <p>Your profile page looks perfect on any device, ensuring a seamless experience for everyone who visits.</p>
                    </div>
                </div>
                <div class="col-md-4 mt-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon floating">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Secure & Private</h3>
                        <p>Control your online presence with privacy settings and secure link management features.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="cta" class="cta-section">
        <div class="container">
            <div class="cta-content text-center">
                <h2 class="animate__animated animate__fadeIn">Ready to Simplify Your Online Presence?</h2>
                <p class="animate__animated animate__fadeIn animate__delay-1s">Join thousands of influencers, creators, professionals and businesses who use SocialLinks to connect with their audience.</p>
                <a href="register.php" class="btn btn-cta animate__animated animate__fadeIn animate__delay-2s">Create Your SocialLinks <i class="fas fa-chevron-right ms-2"></i></a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <a href="#" class="footer-logo">Social<span>Links</span></a>
                    <p>We help you build a stronger online presence by bringing all your digital profiles together in one beautiful, easy-to-share page.</p>
                    <div class="social-links">
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-links-container">
                    <h3 class="footer-heading">Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#features">Features</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                        <li><a href="#cta">Get Started</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Sign Up</a></li>
                    </ul>
                </div>
                
                <div class="footer-links-container">
                    <h3 class="footer-heading">Company</h3>
                    <ul class="footer-links">
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Press</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-links-container">
                    <h3 class="footer-heading">Support</h3>
                    <ul class="footer-links">
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Cookies</a></li>
                    </ul>
                </div>
                
            </div>
            
            <div class="copyright">
                <p>© 2025 SocialLinks. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Back to top button -->
    <div class="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wow/1.1.2/wow.min.js"></script>
    <script>
        // Preloader
        window.addEventListener('load', function() {
            const preloader = document.querySelector('.preloader');
            setTimeout(function() {
                preloader.classList.add('hidden');
            }, 500);
        });
        
        // Initialize WOW.js for scroll animations
        new WOW().init();
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            const backToTop = document.querySelector('.back-to-top');
            
            if (window.scrollY > 50) {
                navbar.style.padding = '10px 0';
                navbar.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
            } else {
                navbar.style.padding = '15px 0';
                navbar.style.boxShadow = '0 2px 15px rgba(0, 0, 0, 0.1)';
            }
            
            // Back to top button visibility
            if (window.scrollY > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Back to top functionality
        document.querySelector('.back-to-top').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Stats counter animation
        function animateCounters() {
            const counters = document.querySelectorAll('.stats-number');
            const speed = 200;
            
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-counter').replace(/,/g, '').replace(/\+/g, '');
                const text = counter.textContent.replace(/,/g, '').replace(/\+/g, '');
                const count = parseInt(text, 10) || 0;
                const increment = Math.trunc(target / speed);
                
                if (count < target) {
                    counter.textContent = (count + increment) + (counter.textContent.includes('+') ? '+' : '');
                    setTimeout(() => animateCounters(), 1);
                } else {
                    counter.textContent = counter.textContent.includes('+') ? 
                        target.toLocaleString() + '+' : 
                        target.toLocaleString();
                }
            });
        }
        
        // Initialize counters when they come into view
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            observer.observe(statsSection);
        }
    </script>
</body>
</html>