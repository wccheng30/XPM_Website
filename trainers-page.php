<?php
// Include database connection
require_once 'includes/database.php';

// Set page title
$page_title = "Expert Trainers - XpressPoint";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Power Up Mentor (PUM) - Expert HR Trainers from Melaka. A collective of passionate, experienced trainers united to elevate the visibility and professionalism of Melaka's training industry">
    <meta name="keywords" content="Power Up Mentor, PUM, HR trainers, Melaka trainers, expert trainers, HR training, professional development, certified trainers, training collective">
    <meta name="author" content="Xpress Point Management Services">
    <title>Power Up Mentor (PUM) - Expert Trainers - XpressPoint</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=20250115-2">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="index.html">
                    <img src="assets/images/XPMLogo.png" alt="Xpress Point Management Services" class="logo-img">
                </a>
            </div>
            
            <div class="nav-menu" id="nav-menu">
                <a href="index.html#hero" class="nav-link">Home</a>
                <a href="index.html#about" class="nav-link">About</a>
                <a href="index.html#initiator" class="nav-link">Founder</a>
                <a href="index.html#services" class="nav-link">Services</a>
                <a href="index.html#facilities" class="nav-link">Facilities</a>
                <a href="index.html#testimonials" class="nav-link">Testimonials</a>
                <a href="programmes.php" class="nav-link">Programmes</a>
                <a href="jobs.php" class="nav-link">Careers</a>
                <a href="index.html#portfolio" class="nav-link">Portfolio</a>
                <a href="index.html#contact" class="nav-link">Contact</a>
            </div>
            
            <div class="nav-toggle" id="nav-toggle">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content" style="margin-top: 80px; width: 100%; overflow-x: hidden;">
        <div class="container" style="width: 100%; max-width: 100%; padding: 0 1rem;">
            <!-- Page Header -->
            <div class="page-header" style="padding: 4rem 0 2rem; text-align: center; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%); margin-bottom: 3rem; border-radius: 25px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1); border: 1px solid rgba(59, 130, 246, 0.1); position: relative; overflow: hidden;">
                <div class="breadcrumb" style="margin-bottom: 1rem; font-size: 0.9rem; color: #6b7280;">
                    <a href="index.html" style="color: #3b82f6; text-decoration: none;">Home</a>
                    <span style="margin: 0 0.5rem;">/</span>
                    <a href="programmes.php" style="color: #3b82f6; text-decoration: none;">Programmes</a>
                    <span style="margin: 0 0.5rem;">/</span>
                    <span style="color: #6b7280;">Expert Trainers</span>
                </div>
                
                <!-- Decorative Background Elements -->
                <div style="position: absolute; top: -50px; left: -50px; width: 100px; height: 100px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(99, 102, 241, 0.1)); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
                <div style="position: absolute; top: 20px; right: -30px; width: 80px; height: 80px; background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.1)); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>
                <div style="position: absolute; bottom: -40px; left: 20%; width: 60px; height: 60px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); border-radius: 50%; animation: float 7s ease-in-out infinite;"></div>
                
                <!-- PUM Logo -->
                <div class="pum-logo-container" style="margin-bottom: 2rem; position: relative; z-index: 2;">
                    <img src="assets/images/PowerUpMentor.png" alt="Power Up Mentor (PUM) Logo" style="max-width: 300px; height: auto; filter: drop-shadow(0 8px 20px rgba(0, 0, 0, 0.15));">
                </div>
                
                <h1 class="page-title" style="font-size: 3rem; font-weight: 800; color: #1f2937; margin-bottom: 1rem; background: linear-gradient(135deg, #3b82f6, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Power Up Mentor (PUM)</h1>
                <p class="page-subtitle" style="font-size: 1.25rem; color: #6b7280; max-width: 800px; margin: 0 auto; line-height: 1.6;">A collective effort to unite passionate, experienced, and like-minded trainers from Melaka with a shared purpose — to elevate the visibility, credibility, and professionalism of Melaka's training industry, both within and beyond the state.</p>
            </div>

            <!-- Expert Trainers Section -->
            <?php include 'trainers.php'; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="assets/images/XPMLogo.png" alt="Xpress Point Management Services Logo">
                    </div>
                    <p>Empowering organizations with comprehensive HR services and training solutions since 2010.</p>
                    <div class="social-links">
                        <a href="https://www.linkedin.com/in/icol-chua-18248623/" target="_blank" rel="noopener noreferrer"><i class="fab fa-linkedin"></i></a>
                        <a href="https://www.facebook.com/XpressPointManagementServices" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook"></i></a>
                        <a href="https://www.instagram.com/xpresspoint_mlk/" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i></a>
                        <a href="https://api.whatsapp.com/send?phone=601120816113&text&context=Afe5aPTzugSnNb11RVXu8gplTXAka-fQ3OSnoIHLoEqmW6cDcc2-nda4uIC8TFMPge3mZXDvEk9uqftJnydY7o4PQeFu4teMCQA9M_MxuKCiQ09tgidUXLf8Za7t5li-eJv1K9QAXOxfmXK4qCobgVak8Q&source&app=facebook" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.html#about">About Us</a></li>
                        <li><a href="index.html#services">Services</a></li>
                        <li><a href="programmes.php">Training Programmes</a></li>
                        <li><a href="jobs.php">Careers</a></li>
                        <li><a href="index.html#contact">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Why Choose Us</h4>
                    <ul>
                        <li><a href="index.html#about">20+ Years Experience</a></li>
                        <li><a href="index.html#services">Certified Professionals</a></li>
                        <li><a href="index.html#services">Custom Solutions</a></li>
                        <li><a href="index.html#testimonials">Proven Results</a></li>
                        <li><a href="index.html#contact">Ongoing Support</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> No.6, Jalan Berkat 12, Taman Malim Jaya, 75250 Melaka</li>
                        <li><i class="fas fa-phone"></i> 016 772 1113</li>
                        <li><i class="fas fa-envelope"></i> hr@xpresspointservices.com</li>
                    </ul>
                    <div class="footer-help-message" style="margin-top: 1rem; padding: 0.75rem; background: rgba(37, 99, 235, 0.1); border-radius: 8px; border-left: 3px solid #2563eb;">
                        <p style="margin: 0; font-size: 0.875rem; color: #1e40af; line-height: 1.4;">
                            <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
                            Found an error or issue? <a href="index.html#contact" style="color: #2563eb; text-decoration: underline;">Contact us</a> and we'll fix it promptly.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 Xpress Point Management Services. All rights reserved.</p>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
</body>
</html>
