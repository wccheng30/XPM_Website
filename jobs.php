<?php
// Include shared database connection
require_once 'includes/database.php';

$pdo = get_db_connection();
$jobs = [];

if ($pdo) {
    try {
        // Get only active jobs
        $stmt = $pdo->query("SELECT * FROM jobs WHERE is_active = 1 ORDER BY created_at DESC");
        $jobs = $stmt->fetchAll();
        
        // Sort all jobs by creation date (newest first)
        usort($jobs, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
} else {
    error_log("Database connection failed");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover exciting career opportunities with leading companies. Browse our latest job openings and find your dream position.">
    <meta name="keywords" content="job opportunities, careers, employment, HR jobs, professional positions">
    <meta name="author" content="Xpress Point Management">
    <title>Career Opportunities - Xpress Point Management</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
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
                <a href="jobs.php" class="nav-link active">Careers</a>
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

    <!-- Jobs Section -->
    <section id="jobs" class="programme">
        <!-- Background Elements -->
        <div class="programme-background">
            <!-- Animated gradient background -->
            <div class="programme-gradient-bg"></div>
            
            <!-- Animated grid pattern -->
            <div class="programme-grid-pattern"></div>
            
            <!-- Particles -->
            <div class="programme-particles">
                <div class="programme-particle programme-particle-1"></div>
                <div class="programme-particle programme-particle-2"></div>
                <div class="programme-particle programme-particle-3"></div>
            </div>
            
            <!-- Floating shapes -->
            <div class="programme-shapes">
                <div class="programme-shape programme-shape-1"></div>
                <div class="programme-shape programme-shape-2"></div>
                <div class="programme-shape programme-shape-3"></div>
                <div class="programme-shape programme-shape-4"></div>
                <div class="programme-shape programme-shape-5"></div>
            </div>
        </div>
        
        <div class="container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="fas fa-briefcase"></i>
                    <span>Career Opportunities</span>
                </div>
                <h2 class="section-title">
                    Find Your <span class="gradient-text">Dream Career</span>
                </h2>
                <p class="section-subtitle">Discover exciting opportunities with leading companies across Malaysia. We connect talented professionals with amazing career paths.</p>
            </div>
            
            <!-- Jobs Overview -->
            <div class="programme-overview">
                <div class="overview-card">
                    <div class="overview-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Professional Career Services</h3>
                    <p>Connect with top employers and find your perfect career match. Our comprehensive job placement services help you navigate the competitive job market.</p>
                    <div class="overview-stats">
                        <div class="overview-stat">
                            <span class="stat-number"><?php echo count($jobs); ?>+</span>
                            <span class="stat-label">Active Positions</span>
                        </div>
                        <div class="overview-stat">
                            <span class="stat-number">200+</span>
                            <span class="stat-label">Partner Companies</span>
                        </div>
                        <div class="overview-stat">
                            <span class="stat-number">95%</span>
                            <span class="stat-label">Success Rate</span>
                        </div>
                    </div>
                </div>
            </div>
            


            
            <?php if (!empty($jobs)): ?>
                <div class="jobs-grid">
                    <?php foreach ($jobs as $index => $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <div class="job-icon">
                                    <i class="<?php echo htmlspecialchars($job['job_icon'] ?? 'fas fa-briefcase'); ?>"></i>
                                </div>
                                <div class="job-badge">
                                    <span><?php echo htmlspecialchars($job['job_badge'] ?? $job['type']); ?></span>
                                </div>
                            </div>
                            
                            <div class="job-logo">
                                <?php if (!empty($job['poster_image'])): ?>
                                <img src="<?php echo htmlspecialchars($job['poster_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($job['title']); ?> Logo"
                                     class="job-logo-img">
                                <?php else: ?>
                                <div class="default-logo">
                                    <i class="fas fa-building"></i>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($job['company_name'])): ?>
                                <div class="company-name">
                                    <span><?php echo htmlspecialchars($job['company_name']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="job-content">
                                <h4><?php echo htmlspecialchars($job['title']); ?></h4>
                                <p class="job-description">
                                    <?php 
                                    $description = htmlspecialchars($job['description']);
                                    if (strlen($description) > 200) {
                                        echo substr($description, 0, 200) . '...';
                                    } else {
                                        echo $description;
                                    }
                                    ?>
                                </p>
                                
                                <div class="job-details">
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($job['location']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo htmlspecialchars($job['type']); ?></span>
                                    </div>
                                    
                                    <?php if (!empty($job['start_time']) && !empty($job['end_time'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-business-time"></i>
                                        <span><?php echo date('g:i A', strtotime($job['start_time'])); ?> - <?php echo date('g:i A', strtotime($job['end_time'])); ?></span>
                                    </div>
                                    <?php elseif (is_null($job['start_time']) && is_null($job['end_time'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-question-circle text-warning"></i>
                                        <span>-</span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($job['salary_range'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-dollar-sign"></i>
                                        <span><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($job['experience_level'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-user-tie"></i>
                                        <span>
                                            <?php 
                                            $level = htmlspecialchars($job['experience_level']);
                                            
                                            // Check if it's a predefined level that needs "Level" suffix
                                            $predefined_levels = ['entry', 'mid', 'senior', 'executive'];
                                            if (in_array(strtolower($level), $predefined_levels)) {
                                                echo ucfirst($level) . ' Level';
                                            } else {
                                                // For custom levels, display as is
                                                echo $level;
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>

                                </div>
                                
                                <?php if (!empty($job['requirements'])): ?>
                                <div class="job-skills-section">
                                    <h5 class="skills-title">
                                        <i class="fas fa-tools"></i>
                                        Required Skills
                                    </h5>
                                    <div class="job-skills">
                                        <?php 
                                        // Split by newlines first, then by commas if no newlines
                                        if (strpos($job['requirements'], "\n") !== false) {
                                            $requirements = array_filter(array_map('trim', explode("\n", $job['requirements'])));
                                        } else {
                                            $requirements = array_filter(array_map('trim', explode(',', $job['requirements'])));
                                        }
                                        
                                        foreach (array_slice($requirements, 0, 6) as $requirement): 
                                            if (!empty($requirement)):
                                        ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars($requirement); ?></span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        
                                        if (count($requirements) > 6):
                                        ?>
                                        <span class="skill-tag more-tag" title="Click to see all skills">
                                            <i class="fas fa-plus"></i>
                                            <?php echo count($requirements) - 6; ?> more
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="job-footer">
                                <div class="job-actions">
                                    <a href="job-detail.php?id=<?php echo $job['id']; ?>" class="btn btn-outline">
                                        <span>View Details</span>
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="job-application.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary">
                                        <span>Apply Now</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                                <div class="job-salary">
                                    <?php if (!empty($job['salary_range'])): ?>
                                        <span class="salary-label">Salary</span>
                                        <span class="salary-range"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    <?php else: ?>
                                        <span class="salary-label">Posted</span>
                                        <span class="salary-range"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="job-glow"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                    <!-- Fallback content when no jobs are available -->
                    <div class="programme-card programme-card-1">
                        <div class="programme-header">
                            <div class="programme-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <div class="programme-badge">
                                <span>Coming Soon</span>
                            </div>
                        </div>
                        
                        <div class="programme-content">
                            <h3>Career Opportunities</h3>
                            <p class="programme-description">
                                We're currently updating our job listings with exciting career opportunities. Please check back soon for the latest positions with leading companies.
                            </p>
                            
                            <div class="programme-details">
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Various Locations</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Multiple Types</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-building"></i>
                                    <span>Top Companies</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="programme-footer" style="position: relative; z-index: 10;">
                            <a href="index.html#contact" class="btn btn-primary btn-glow" style="position: relative; z-index: 100; pointer-events: auto !important; cursor: pointer !important;">
                                <span>Contact Us</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        
                        <div class="programme-glow"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

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

    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html> 