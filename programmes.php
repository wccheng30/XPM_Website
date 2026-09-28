<?php
// Include shared database connection
require_once 'includes/database.php';

// Helper function to safely display prices
function formatPrice($price) {
    if (is_numeric($price)) {
        $numeric_price = (float) $price;
        if ($numeric_price == 0) {
            return 'Free';
        }
        $has_decimal_value = abs($numeric_price - floor($numeric_price)) > 0;
        return 'RM ' . number_format($numeric_price, $has_decimal_value ? 2 : 0);
    } else {
        return htmlspecialchars($price);
    }
}

// Helper function to get price for display
function getDisplayPrice($programme, $priceKey = 'price') {
    if (empty($programme[$priceKey])) return null;
    return $programme[$priceKey];
}

$pdo = get_db_connection();
$programmes = [];
$programmes_by_month = [];
$trainers = [];

if ($pdo) {
    try {
        // Get active programmes (including those with closed registration)
        $stmt = $pdo->query("SELECT * FROM programmes WHERE is_active = 1 ORDER BY start_date ASC, created_at DESC");
        $programmes = $stmt->fetchAll();
        
        // Get active trainers for the preview section
        $trainers_stmt = $pdo->query("SELECT * FROM trainers WHERE is_active = 1 ORDER BY display_order ASC, name ASC LIMIT 3");
        $trainers = $trainers_stmt->fetchAll();
        
        // Sort all programmes by start date (closest to today first)
        usort($programmes, function($a, $b) {
            // Handle programmes without start dates - put them at the end
            $a_date = !empty($a['start_date']) && $a['start_date'] !== '0000-00-00' ? $a['start_date'] : '9999-12-31';
            $b_date = !empty($b['start_date']) && $b['start_date'] !== '0000-00-00' ? $b['start_date'] : '9999-12-31';
            
            // If dates are the same, sort by creation date (newest first)
            if ($a_date === $b_date) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            }
            
            return strtotime($a_date) - strtotime($b_date);
        });
        
        // Group programmes by month using start_date
        foreach ($programmes as $programme) {
            // Use start_date for grouping, fallback to created_at if no start_date
            $date_to_use = !empty($programme['start_date']) && $programme['start_date'] !== '0000-00-00' 
                ? $programme['start_date'] 
                : $programme['created_at'];
            
            $programme_date = new DateTime($date_to_use);
            $month_key = $programme_date->format('Y-m');
            $month_name = $programme_date->format('F Y');
            
            if (!isset($programmes_by_month[$month_key])) {
                $programmes_by_month[$month_key] = [
                    'month_name' => $month_name,
                    'programmes' => []
                ];
            }
            
            $programmes_by_month[$month_key]['programmes'][] = $programme;
        }
        
        // Sort months by start date (earliest first)
        uasort($programmes_by_month, function($a, $b) {
            // Get the earliest start_date from each month's programmes
            $earliest_a = null;
            $earliest_b = null;
            
            foreach ($a['programmes'] as $programme) {
                $date_to_use = !empty($programme['start_date']) && $programme['start_date'] !== '0000-00-00' 
                    ? $programme['start_date'] 
                    : $programme['created_at'];
                
                if ($earliest_a === null || $date_to_use < $earliest_a) {
                    $earliest_a = $date_to_use;
                }
            }
            
            foreach ($b['programmes'] as $programme) {
                $date_to_use = !empty($programme['start_date']) && $programme['start_date'] !== '0000-00-00' 
                    ? $programme['start_date'] 
                    : $programme['created_at'];
                
                if ($earliest_b === null || $date_to_use < $earliest_b) {
                    $earliest_b = $date_to_use;
                }
            }
            
            // Sort by earliest first
            return strtotime($earliest_a) - strtotime($earliest_b);
        });
        
    } catch (PDOException $e) {
        // Handle error silently
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Professional HR Training Services - Empowering organizations with comprehensive human resources training programs and development solutions">
    <meta name="keywords" content="HR training, human resources, professional development, corporate training, HR consulting">
    <meta name="author" content="Xpress Point Management Services">
    <title>Training Programmes - Xpress Point Management Services</title>
    
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
                <a href="programmes.php" class="nav-link active">Programmes</a>
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

    <!-- Programme Section -->
    <section id="programme" class="programme">
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
                    <i class="fas fa-graduation-cap"></i>
                    <span>Our Programmes</span>
                </div>
                <h2 class="section-title">
                    Professional <span class="gradient-text">Training Programmes</span>
                </h2>
                <p class="section-subtitle">Comprehensive HR training programmes designed to enhance your team's capabilities and drive organizational success</p>
            </div>
            
            <!-- Programme Overview -->
            <div class="programme-overview">
                <div class="overview-card">
                    <div class="overview-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Certified Training Excellence</h3>
                    <p>HRD Corp registered training provider offering comprehensive programmes that align with national standards and industry best practices</p>
                    <div class="overview-stats">
                        <div class="overview-stat">
                            <span class="stat-number"><?php echo count($programmes); ?>+</span>
                            <span class="stat-label">Programmes</span>
                        </div>
                        <div class="overview-stat">
                            <span class="stat-number">500+</span>
                            <span class="stat-label">Graduates</span>
                        </div>
                        <div class="overview-stat">
                            <span class="stat-number">98%</span>
                            <span class="stat-label">Success Rate</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Power Up Mentor Section -->
            <div class="expert-trainers">
                <div class="trainers-section-header">
                    <div class="section-badge">
                        <i class="fas fa-users"></i>
                        <span>Power Up Mentor</span>
                    </div>
                    
                    <!-- PUM Logo -->
                    <div class="pum-logo-section">
                        <img src="assets/images/PowerUpMentor.png" alt="Power Up Mentor (PUM) Logo" style="max-width: 300px; height: auto; filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));">
                    </div>
                    
                    <h3>Melaka's Premier Training Collective</h3>
                    <p>Power Up Mentor (PUM) serves as a collaborative platform that benefits individual trainers, clients, and the training ecosystem as a whole. Our collective brings together passionate, experienced trainers from Melaka to elevate the visibility, credibility, and professionalism of the training industry.</p>
                    <div class="trainers-cta">
                        <a href="trainers-page.php" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i>
                            View All PUM Trainers
                        </a>
                    </div>
                </div>
                
                <!-- Preview of Trainers -->
                <div class="trainers-preview">
                    <?php foreach ($trainers as $trainer): ?>
                    <div class="trainer-preview-card">
                        <div class="trainer-preview-image">
                            <img src="<?php echo htmlspecialchars($trainer['image_path']); ?>" alt="<?php echo htmlspecialchars($trainer['name']); ?>">
                        </div>
                        <div class="trainer-preview-info">
                            <h4><?php echo htmlspecialchars($trainer['name']); ?></h4>
                            <p class="trainer-preview-title"><?php echo htmlspecialchars($trainer['title']); ?></p>
                            <p class="trainer-preview-experience"><?php echo $trainer['experience_years']; ?>+ Years Experience</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (!empty($programmes_by_month)): ?>
                <?php foreach ($programmes_by_month as $month_key => $month_data): ?>
                    <div class="programme-month-section">
                        <div class="month-header">
                            <h2 class="month-title"><?php echo htmlspecialchars($month_data['month_name']); ?></h2>
                            <div class="month-divider"></div>
                        </div>
                        
                        <div class="programme-grid">
                            <?php foreach ($month_data['programmes'] as $index => $programme): ?>
                        <div class="programme-card programme-card-<?php echo ($index % 3) + 1; ?>">
                             <?php if (!empty($programme['poster_image'])): ?>
                             <!-- Programme Poster Container with Footer -->
                             <div class="programme-poster-container">
                                 <div class="programme-poster">
                                     <img src="<?php echo htmlspecialchars($programme['poster_image']); ?>" 
                                          alt="<?php echo htmlspecialchars($programme['title']); ?> Poster"
                                          class="programme-poster-img"
                                          onclick="openPosterModal(this, '<?php echo htmlspecialchars($programme['poster_image']); ?>', '<?php echo !empty($programme['poster_image_2']) ? htmlspecialchars($programme['poster_image_2']) : ''; ?>')">
                                     
                                     <?php if (!empty($programme['poster_image_2'])): ?>
                                     <!-- Hidden second poster for modal -->
                                     <img src="<?php echo htmlspecialchars($programme['poster_image_2']); ?>" 
                                          alt="<?php echo htmlspecialchars($programme['title']); ?> Secondary Poster"
                                          class="programme-poster-img-secondary hidden-poster"
                                          style="display: none;">
                                     <?php endif; ?>
                                 </div>
                                <div class="programme-poster-footer">
                                    <div class="programme-icon-standalone">
                                        <i class="<?php echo htmlspecialchars($programme['programme_icon'] ?? 'fas fa-graduation-cap'); ?>"></i>
                                    </div>
                                    <div class="programme-badge-standalone">
                                        <span><?php echo htmlspecialchars($programme['programme_badge'] ?? $programme['category']); ?></span>
                                </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="programme-header">
                                <div class="programme-icon">
                                    <i class="<?php echo htmlspecialchars($programme['programme_icon'] ?? 'fas fa-graduation-cap'); ?>"></i>
                                </div>
                                <div class="programme-badge">
                                    <span><?php echo htmlspecialchars($programme['programme_badge'] ?? $programme['category']); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="programme-content">
                                <h3><?php echo htmlspecialchars($programme['title']); ?></h3>
                                <p class="programme-description">
                                    <?php echo htmlspecialchars($programme['description']); ?>
                                </p>
                                
                                <div class="programme-details">
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo htmlspecialchars($programme['duration']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span>Max <?php echo htmlspecialchars($programme['max_participants'] ?? 25); ?> Participants</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-certificate"></i>
                                        <span><?php echo htmlspecialchars($programme['certification'] ?? 'HRD Corp Certified'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($programme['location'] ?? 'Online'); ?></span>
                                    </div>

                                    <?php if (!empty($programme['start_date']) && $programme['start_date'] !== '0000-00-00'): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>
                                            <?php 
                                            try {
                                                $start_date = new DateTime($programme['start_date']);
                                                if (!empty($programme['end_date']) && $programme['end_date'] !== '0000-00-00' && $programme['end_date'] !== $programme['start_date']) {
                                                    $end_date = new DateTime($programme['end_date']);
                                                    echo $start_date->format('M j, Y') . ' - ' . $end_date->format('M j, Y');
                                                } else {
                                                    echo $start_date->format('M j, Y');
                                                }
                                            } catch (Exception $e) {
                                                // Handle invalid date format
                                                echo 'Date TBA';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php else: ?>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Dates TBA - Contact for Schedule</span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($programme['start_time']) && !empty($programme['end_time'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span>
                                            <?php 
                                            $start_time = new DateTime($programme['start_time']);
                                            $end_time = new DateTime($programme['end_time']);
                                            echo $start_time->format('g:i A') . ' - ' . $end_time->format('g:i A');
                                            ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($programme['modules'])): ?>
                                <div class="programme-modules">
                                    <h4>Key Modules:</h4>
                                    <div class="module-list">
                                        <?php 
                                        // Handle comma-space separated modules
                                        $modules = explode(', ', $programme['modules']);
                                        foreach (array_slice($modules, 0, 4) as $module): 
                                            $module = trim($module);
                                            if (!empty($module)):
                                        ?>
                                        <div class="module-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span><?php echo htmlspecialchars($module); ?></span>
                                        </div>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="programme-footer">
                                <div class="programme-pricing-section">
                                    <?php if (!empty($programme['original_price']) && $programme['original_price'] > $programme['price']): ?>
                                        <!-- Special Offer with Enhanced Button -->
                                        <div class="special-offer-container">
                                            <div class="pricing-display">
                                                <span class="price-label">SPECIAL OFFER</span>
                                                <div class="price-amounts">
                                                    <span class="original-price"><?php echo formatPrice(getDisplayPrice($programme, 'original_price')); ?></span>
                                                    <span class="discounted-price"><?php echo formatPrice(getDisplayPrice($programme, 'price')); ?></span>
                                                    <?php if (isset($programme['tax_amount']) && $programme['tax_amount'] > 0): ?>
                                                        <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                                                            (<?php echo ($programme['tax_inclusive'] == 1) ? 'Includes' : 'Excludes'; ?> RM <?php echo number_format($programme['tax_amount'], 2); ?> Tax)
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="savings-badge">SAVE <?php echo formatPrice(getDisplayPrice($programme, 'original_price') - getDisplayPrice($programme, 'price')); ?></span>
                                            </div>
                                            <?php if (isset($programme['registration_status']) && $programme['registration_status'] === 'open'): ?>
                                                <a href="programme-registration.php?programme_id=<?php echo $programme['id']; ?>" 
                                                   class="btn btn-special-offer">
                                                    <span>Register Now</span>
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
                                            <?php else: ?>
                                                <div class="btn btn-disabled" style="background: #6b7280; color: white; cursor: not-allowed; opacity: 0.7;">
                                                    <span><?php echo isset($programme['registration_status']) && $programme['registration_status'] === 'closed' ? 'Registration Closed' : 'Registration Full'; ?></span>
                                                    <i class="fas fa-lock"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Standard Pricing -->
                                        <div class="standard-pricing">
                                            <div class="pricing-display">
                                                <span class="price-label">Programme Fee</span>
                                                <span class="price-amount"><?php echo formatPrice(getDisplayPrice($programme, 'price')); ?></span>
                                                <?php if (isset($programme['tax_amount']) && $programme['tax_amount'] > 0): ?>
                                                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                                                        (<?php echo ($programme['tax_inclusive'] == 1) ? 'Includes' : 'Excludes'; ?> RM <?php echo number_format($programme['tax_amount'], 2); ?> Tax)
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (isset($programme['registration_status']) && $programme['registration_status'] === 'open'): ?>
                                                <a href="programme-registration.php?programme_id=<?php echo $programme['id']; ?>" 
                                                   class="btn btn-primary">
                                                    <span>Register Now</span>
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
                                            <?php else: ?>
                                                <div class="btn btn-disabled" style="background: #6b7280; color: white; cursor: not-allowed; opacity: 0.7;">
                                                    <span><?php echo isset($programme['registration_status']) && $programme['registration_status'] === 'closed' ? 'Registration Closed' : 'Registration Full'; ?></span>
                                                    <i class="fas fa-lock"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="programme-glow"></div>
                        </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                    <!-- Fallback content when no programmes are available -->
                    <div class="programme-card programme-card-1">
                        <div class="programme-header">
                            <div class="programme-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="programme-badge">
                                <span>Coming Soon</span>
                            </div>
                        </div>
                        
                        <div class="programme-content">
                            <h3>Training Programmes</h3>
                            <p class="programme-description">
                                Our comprehensive HR training programmes are being updated. Please check back soon for our latest offerings.
                            </p>
                            
                            <div class="programme-details">
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Various Durations</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span>Flexible Group Sizes</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-certificate"></i>
                                    <span>HRD Corp Certified</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="programme-footer" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-top: auto; padding: 1rem 0; position: relative; z-index: 10;">
                            <a href="index.html#contact" class="btn btn-primary btn-glow" style="display: inline-flex !important; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; font-weight: 600; padding: 12px 24px; border-radius: 8px; background: linear-gradient(135deg, #2563eb 0%, #764ba2 100%); color: white; transition: all 0.3s ease; position: relative; z-index: 100; pointer-events: auto !important; cursor: pointer !important;">
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

    <!-- Poster Modal Lightbox -->
    <div id="posterModal" class="poster-modal">
        <div class="poster-modal-overlay"></div>
        <div class="poster-modal-content">
            <div class="poster-modal-header">
                <h3 id="posterModalTitle">Programme Poster</h3>
                <button class="poster-modal-close" id="posterModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="poster-modal-body">
                <div class="poster-zoom-container">
                    <img id="posterModalImage" src="" alt="Programme Poster" class="poster-zoom-image">
                </div>
                <div class="poster-zoom-controls">
                    <button class="zoom-btn zoom-out" id="zoomOut">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <span class="zoom-level" id="zoomLevel">100%</span>
                    <button class="zoom-btn zoom-in" id="zoomIn">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button class="zoom-btn zoom-reset" id="zoomReset">
                        <i class="fas fa-expand-arrows-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html> 