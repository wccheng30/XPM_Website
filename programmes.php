<?php
// Include shared database connection
require_once 'includes/database.php';
require_once 'includes/programme-pricing.php';

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

        $programme_type_filter = $_GET['type'] ?? 'all';
        if (!in_array($programme_type_filter, ['all', 'webinar', 'training'], true)) {
            $programme_type_filter = 'all';
        }
        if ($programme_type_filter !== 'all') {
            $programmes = array_values(array_filter($programmes, function ($programme) use ($programme_type_filter) {
                return ($programme['programme_type'] ?? 'training') === $programme_type_filter;
            }));
        }
        
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
    <?php $xpm_page_styles = ['pages/programmes.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php $xpm_active = 'programmes'; include 'partials/site-nav.php'; ?>

    <main id="main">
        <header class="page-hero">
            <div class="container">
                <span class="eyebrow" data-reveal><i class="fas fa-graduation-cap"></i> HRD Corp Registered Training Provider</span>
                <h1 data-split>Professional training programmes.</h1>
                <p class="lead" data-reveal>Practical HR programmes aligned with national standards, designed to strengthen your team's capabilities and drive organisational success.</p>
                <div class="hero-stats" data-reveal-stagger>
                    <div>
                        <span class="stat-value" data-count="<?php echo count($programmes); ?>" data-suffix="+">0+</span>
                        <span class="stat-caption">Programmes</span>
                    </div>
                    <div>
                        <span class="stat-value" data-count="500" data-suffix="+">500+</span>
                        <span class="stat-caption">Graduates</span>
                    </div>
                    <div>
                        <span class="stat-value" data-count="98" data-suffix="%">98%</span>
                        <span class="stat-caption">Success rate</span>
                    </div>
                </div>
            </div>
        </header>

        <section id="programme" class="section">
            <div class="container">
            <?php
                $programme_type_filter = $programme_type_filter ?? 'all';
                $type_links = [
                    'all' => 'All',
                    'webinar' => 'Webinar',
                    'training' => 'Training Programme',
                ];
            ?>
            <div class="programme-filters" data-reveal="fade">
                <?php foreach ($type_links as $type_key => $type_label): ?>
                    <a class="btn btn-outline btn-sm<?php echo $programme_type_filter === $type_key ? ' is-active' : ''; ?>" href="programmes.php<?php echo $type_key === 'all' ? '' : '?type=' . $type_key; ?>#programme"><?php echo $type_label; ?></a>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($programmes_by_month)): ?>
                <?php foreach ($programmes_by_month as $month_key => $month_data): ?>
                <div class="month-block">
                    <div class="month-header" data-reveal="fade">
                        <h2><?php echo htmlspecialchars($month_data['month_name']); ?></h2>
                        <span class="month-count"><?php echo count($month_data['programmes']); ?> programme<?php echo count($month_data['programmes']) === 1 ? '' : 's'; ?></span>
                    </div>

                    <div class="listing-grid" data-reveal-stagger>
                        <?php foreach ($month_data['programmes'] as $index => $programme): ?>
                        <article class="listing-card programme-listing">
                            <?php if (!empty($programme['poster_image'])): ?>
                            <div class="listing-media">
                                <img src="<?php echo htmlspecialchars($programme['poster_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($programme['title']); ?> Poster"
                                     class="programme-poster-img"
                                     loading="lazy"
                                     data-poster-src="<?php echo htmlspecialchars($programme['poster_image']); ?>"
                                     data-poster-secondary="<?php echo !empty($programme['poster_image_2']) ? htmlspecialchars($programme['poster_image_2']) : ''; ?>"
                                     data-poster-title="<?php echo htmlspecialchars($programme['title']); ?>">
                                <span class="listing-media-hint"><i class="fas fa-magnifying-glass-plus"></i> View poster<?php echo !empty($programme['poster_image_2']) ? 's' : ''; ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="listing-top">
                                <span class="icon-box"><i class="<?php echo htmlspecialchars($programme['programme_icon'] ?? 'fas fa-graduation-cap'); ?>"></i></span>
                                <span class="badge"><?php echo htmlspecialchars((($programme['programme_type'] ?? 'training') === 'webinar') ? 'Webinar' : 'Training Programme'); ?></span>
                                <span class="badge"><?php echo htmlspecialchars($programme['programme_badge'] ?? $programme['category']); ?></span>
                            </div>

                            <div class="listing-body">
                                <h3><?php echo htmlspecialchars($programme['title']); ?></h3>
                                <p class="listing-desc"><?php echo htmlspecialchars($programme['description']); ?></p>

                                <div class="meta-list">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>
                                        <?php if (!empty($programme['start_date']) && $programme['start_date'] !== '0000-00-00'): ?>
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
                                        <?php else: ?>
                                            Dates TBA - Contact for Schedule
                                        <?php endif; ?>
                                        </span>
                                    </div>
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
                                    <div class="detail-item">
                                        <i class="fas fa-hourglass-half"></i>
                                        <span><?php echo htmlspecialchars($programme['duration']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-location-dot"></i>
                                        <span><?php echo htmlspecialchars($programme['location'] ?? 'Online'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span>Max <?php echo htmlspecialchars($programme['max_participants'] ?? 25); ?> participants</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-certificate"></i>
                                        <span><?php echo htmlspecialchars($programme['certification'] ?? 'HRD Corp Certified'); ?></span>
                                    </div>
                                </div>

                                <?php if (!empty($programme['modules'])): ?>
                                <div>
                                    <h4 class="listing-label">Key modules</h4>
                                    <ul class="check-list check-list-sm">
                                        <?php 
                                        // Handle comma-space separated modules
                                        $modules = explode(', ', $programme['modules']);
                                        foreach (array_slice($modules, 0, 4) as $module): 
                                            $module = trim($module);
                                            if (!empty($module)):
                                        ?>
                                        <li><?php echo htmlspecialchars($module); ?></li>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="listing-footer">
                                <?php $is_special = !empty($programme['original_price']) && $programme['original_price'] > $programme['price']; ?>
                                <div class="price-block">
                                    <?php if ($is_special): ?>
                                        <span class="price-label">Special offer</span>
                                        <span class="price-original"><?php echo formatPrice(getDisplayPrice($programme, 'original_price')); ?></span>
                                        <span class="price-amount"><?php echo formatPrice(getDisplayPrice($programme, 'price')); ?></span>
                                        <span class="badge badge-dark price-save">Save <?php echo formatPrice(getDisplayPrice($programme, 'original_price') - getDisplayPrice($programme, 'price')); ?></span>
                                    <?php else: ?>
                                        <span class="price-label">Programme fee</span>
                                        <span class="price-amount"><?php echo formatPrice(getDisplayPrice($programme, 'price')); ?></span>
                                    <?php endif; ?>
                                    <?php $sst_note = programme_sst_note($programme); ?>
                                    <?php if ($sst_note !== ''): ?>
                                        <span class="price-note"><?php echo htmlspecialchars($sst_note); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (programme_registration_is_open($programme)): ?>
                                    <a href="programme-registration.php?programme_id=<?php echo $programme['id']; ?>" class="btn btn-primary btn-sm">
                                        Register <i class="fas fa-arrow-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-disabled btn-sm" aria-disabled="true">
                                        <i class="fas fa-lock"></i>
                                        <?php echo isset($programme['registration_status']) && $programme['registration_status'] === 'closed' ? 'Registration Closed' : 'Registration Full'; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" data-reveal>
                    <span class="icon-box"><i class="fas fa-graduation-cap"></i></span>
                    <h3>New programmes coming soon</h3>
                    <p>Our HR training programmes are being updated. Please check back soon or contact us for an in-house programme.</p>
                    <a href="index.html#contact" class="btn btn-primary">Contact Us <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
            </div>
        </section>

        <section class="section section-alt pum-section">
            <div class="container split">
                <div data-reveal="left">
                    <img src="assets/images/PowerUpMentor.png" alt="Power Up Mentor (PUM) Logo" class="pum-logo">
                    <span class="eyebrow"><i class="fas fa-users"></i> Power Up Mentor</span>
                    <h2 class="section-title">Melaka's premier training collective.</h2>
                    <p class="lead">Power Up Mentor (PUM) is a collaborative platform that brings together passionate, experienced trainers from Melaka to elevate the visibility, credibility and professionalism of the training industry.</p>
                    <a href="trainers-page.php" target="_blank" class="btn btn-primary">
                        View all PUM trainers <i class="fas fa-arrow-up-right-from-square"></i>
                    </a>
                </div>

                <div class="pum-trainers" data-reveal-stagger="right">
                    <?php foreach ($trainers as $trainer): ?>
                    <div class="pum-trainer">
                        <img src="<?php echo htmlspecialchars($trainer['image_path']); ?>" alt="<?php echo htmlspecialchars($trainer['name']); ?>" loading="lazy">
                        <div>
                            <h4><?php echo htmlspecialchars($trainer['name']); ?></h4>
                            <p><?php echo htmlspecialchars($trainer['title']); ?></p>
                            <span class="tag"><?php echo $trainer['experience_years']; ?>+ years experience</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <?php include 'partials/site-footer.php'; ?>

    <!-- Poster Modal Lightbox -->
    <div id="posterModal" class="poster-modal" aria-hidden="true">
        <div class="poster-modal-overlay"></div>
        <div class="poster-modal-content" role="dialog" aria-modal="true" aria-labelledby="posterModalTitle">
            <div class="poster-modal-header">
                <h3 id="posterModalTitle">Programme Poster</h3>
                <div class="poster-zoom-controls">
                    <button type="button" class="zoom-btn zoom-out" id="zoomOut" title="Zoom out" aria-label="Zoom out">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="zoom-level" id="zoomLevel">100%</span>
                    <button type="button" class="zoom-btn zoom-in" id="zoomIn" title="Zoom in" aria-label="Zoom in">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="zoom-btn zoom-reset" id="zoomReset" title="Fit to screen" aria-label="Fit to screen">
                        <i class="fas fa-compress-arrows-alt"></i>
                    </button>
                    <button type="button" class="zoom-btn zoom-actual" id="zoomActual" title="Actual size" aria-label="Actual size">
                        100%
                    </button>
                </div>
                <button type="button" class="poster-modal-close" id="posterModalClose" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="poster-modal-body">
                <div class="poster-zoom-container" id="posterViewport">
                    <div class="poster-canvas" id="posterCanvas">
                        <img id="posterModalImage" src="" alt="Programme Poster" class="poster-zoom-image" draggable="false">
                    </div>
                </div>
                <div class="poster-secondary-thumbs" id="posterThumbs" hidden></div>
            </div>
        </div>
    </div>
</body>
</html>
