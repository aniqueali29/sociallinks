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
        }
        
        body {
            font-family: 'Poppins', sans-serif;
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
        
        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }
        
        .hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, #8E2DE2 100%);
            padding: 160px 0 140px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            top: -100px;
            right: -100px;
        }
        
        .hero::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            bottom: -50px;
            left: -50px;
        }
        
        .hero .container {
            position: relative;
            z-index: 10;
        }
        
        .hero h1 {
            font-weight: 700;
            margin-bottom: 30px;
            font-size: 3.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .hero p {
            font-size: 1.25rem;
            font-weight: 300;
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-hero {
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-hero.primary {
            background: white;
            color: var(--primary-color);
        }
        
        .btn-hero.primary:hover {
            background: var(--secondary-color);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .btn-hero.secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-hero.secondary:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
        }
        
        .features-section {
            padding: 120px 0;
            background-color: white;
        }
        
        .section-title {
            font-weight: 700;
            margin-bottom: 60px;
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            width: 70px;
            height: 4px;
            background: var(--primary-color);
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            height: 100%;
            border-bottom: 4px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(108, 99, 255, 0.1);
            border-bottom: 4px solid var(--primary-color);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 25px;
            color: var(--primary-color);
            background: rgba(108, 99, 255, 0.1);
            width: 90px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .feature-card h3 {
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .feature-card p {
            color: #666;
            line-height: 1.7;
        }
        
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
            border-radius: 20px;
            padding: 60px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 10;
        }
        
        .cta-content h2 {
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
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
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(108, 99, 255, 0.3);
        }
        
        .btn-cta:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(108, 99, 255, 0.4);
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 40px 0;
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
        
        @media (max-width: 991px) {
            .hero h1 {
                font-size: 2.8rem;
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
            
            .cta-content {
                padding: 40px 20px;
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

    <section class="hero text-center text-white">
        <div class="container">
            <h1 class="animate__animated animate__fadeIn">One Link to Connect Everything</h1>
            <p class="animate__animated animate__fadeIn animate__delay-1s">Create your personalized landing page that beautifully showcases all your social profiles, websites, and online content in one simple link.</p>
            <div class="animate__animated animate__fadeIn animate__delay-2s">
                <a href="register.php" class="btn btn-hero primary me-3 mb-3">Create Your Page <i class="fas fa-arrow-right ms-1"></i></a>
                <a href="#features" class="btn btn-hero secondary mb-3">Discover Features</a>
            </div>
        </div>
        <svg class="wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
            <path fill="#ffffff" fill-opacity="1" d="M0,128L48,138.7C96,149,192,171,288,176C384,181,480,171,576,144C672,117,768,75,864,80C960,85,1056,139,1152,154.7C1248,171,1344,149,1392,138.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>
    </section>

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

    <section id="cta" class="cta-section">
        <div class="container">
            <div class="cta-content text-center">
                <h2 class="animate__animated animate__fadeIn">Ready to Simplify Your Online Presence?</h2>
                <p class="animate__animated animate__fadeIn animate__delay-1s">Join thousands of influencers, creators, professionals and businesses who use SocialLinks to connect with their audience.</p>
                <a href="register.php" class="btn btn-cta animate__animated animate__fadeIn animate__delay-2s">Create Your SocialLinks <i class="fas fa-chevron-right ms-2"></i></a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <a href="#" class="footer-logo">Social<span>Links</span></a>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wow/1.1.2/wow.min.js"></script>
    <script>
        // Initialize WOW.js for scroll animations
        new WOW().init();
        
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
    </script>
</body>
</html>