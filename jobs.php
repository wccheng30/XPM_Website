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

function job_level_label($level) {
    $level = (string) $level;
    $predefined_levels = ['entry', 'mid', 'senior', 'executive'];
    if (in_array(strtolower($level), $predefined_levels, true)) {
        return ucfirst($level) . ' Level';
    }
    return $level;
}

function job_badge_class($label) {
    $key = strtolower(trim((string) $label));
    if ($key === 'new') {
        return 'tag-new';
    }
    $level_labels = ['entry level', 'mid-level', 'mid level', 'fresh graduate', 'senior level', 'executive level'];
    if (in_array($key, $level_labels, true)) {
        return 'tag-level';
    }
    return 'tag-meta';
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
    <?php $xpm_page_styles = ['pages/jobs.css']; include 'partials/site-head.php'; ?>
</head>
<body class="page-jobs">
    <?php $xpm_active = 'careers'; include 'partials/site-nav.php'; ?>

    <main id="main">
        <header class="page-hero jobs-hero">
            <span class="jobs-orb jobs-orb-lg" aria-hidden="true"></span>
            <span class="jobs-orb jobs-orb-sm" aria-hidden="true"></span>
            <div class="container">
                <span class="eyebrow" data-reveal><i class="fas fa-briefcase"></i> Career Opportunities</span>
                <h1 data-split>Find your next career move.</h1>
                <p class="lead" data-reveal>Discover opportunities with leading companies across Malaysia. We connect talented professionals with meaningful career paths.</p>
                <div class="hero-stats" data-reveal-stagger>
                    <div>
                        <span class="stat-value" data-count="<?php echo count($jobs); ?>" data-suffix="+">0+</span>
                        <span class="stat-caption">Active positions</span>
                    </div>
                    <div>
                        <span class="stat-value" data-count="200" data-suffix="+">200+</span>
                        <span class="stat-caption">Partner companies</span>
                    </div>
                    <div>
                        <span class="stat-value" data-count="95" data-suffix="%">95%</span>
                        <span class="stat-caption">Placement success</span>
                    </div>
                </div>
            </div>
        </header>

        <section id="jobs" class="section">
            <div class="container">
            <?php if (!empty($jobs)): ?>
                <div class="listing-grid" data-reveal-stagger>
                    <?php foreach ($jobs as $index => $job): ?>
                    <article class="listing-card job-card">
                        <div class="listing-top">
                            <?php if (!empty($job['poster_image'])): ?>
                            <span class="job-mark">
                                <img src="<?php echo htmlspecialchars($job['poster_image']); ?>"
                                     alt="<?php echo htmlspecialchars($job['title']); ?> Logo"
                                     class="job-logo-img" loading="lazy">
                            </span>
                            <?php else:
                                $job_mark_source = trim((string) ($job['company_name'] ?? ''));
                                if ($job_mark_source === '') {
                                    $job_mark_source = trim((string) ($job['title'] ?? ''));
                                }
                                if ($job_mark_source !== '') {
                                    $job_initial = function_exists('mb_substr')
                                        ? mb_substr($job_mark_source, 0, 1, 'UTF-8')
                                        : substr($job_mark_source, 0, 1);
                                    $job_initial = function_exists('mb_strtoupper')
                                        ? mb_strtoupper($job_initial)
                                        : strtoupper($job_initial);
                                } else {
                                    $job_initial = '';
                                }
                            ?>
                            <span class="job-initial" aria-hidden="true"><?php echo htmlspecialchars($job_initial); ?></span>
                            <?php endif; ?>
                            <div class="job-tags">
                                <?php $job_badge_label = (string) ($job['job_badge'] ?? $job['type']); ?>
                                <span class="badge <?php echo job_badge_class($job_badge_label); ?>"><?php echo htmlspecialchars($job_badge_label); ?></span>
                                <?php if (!empty($job['experience_level'])): ?>
                                <span class="badge tag-level"><?php echo htmlspecialchars(job_level_label($job['experience_level'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="listing-body">
                            <div>
                                <?php if (!empty($job['company_name'])): ?>
                                <span class="listing-sub"><?php echo htmlspecialchars($job['company_name']); ?></span>
                                <?php endif; ?>
                                <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                            </div>
                            <p class="listing-desc">
                                <?php 
                                $description = htmlspecialchars($job['description']);
                                if (strlen($description) > 200) {
                                    echo substr($description, 0, 200) . '...';
                                } else {
                                    echo $description;
                                }
                                ?>
                            </p>

                            <div class="meta-list">
                                <div class="detail-item">
                                    <i class="fas fa-location-dot"></i>
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
                                    <i class="fas fa-business-time text-warning"></i>
                                    <span>Hours to be confirmed</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($job['requirements'])): ?>
                            <div>
                                <h4 class="listing-label">Required skills</h4>
                                <div class="tags">
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
                                    <span class="skill-tag more-tag">+<?php echo count($requirements) - 6; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="listing-footer">
                            <div class="price-block">
                                <?php if (!empty($job['salary_range'])): ?>
                                    <span class="price-label">Salary</span>
                                    <span class="price-amount price-amount-sm is-salary"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                <?php else: ?>
                                    <span class="price-label">Posted</span>
                                    <span class="price-amount price-amount-sm"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="listing-actions">
                                <a href="job-detail.php?id=<?php echo $job['id']; ?>" class="btn btn-outline btn-sm">Details</a>
                                <a href="job-application.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary btn-sm">
                                    Apply <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" data-reveal>
                    <span class="icon-box"><i class="fas fa-briefcase"></i></span>
                    <h3>New roles are on the way</h3>
                    <p>We're currently updating our job listings. Please check back soon for the latest positions with leading companies.</p>
                    <a href="index.html#contact" class="btn btn-primary">Contact Us <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
            </div>
        </section>

        <section class="section jobs-band">
            <div class="container">
                <div class="cta-band" data-reveal>
                    <div>
                        <h2>Hiring? Let us find your next hire.</h2>
                        <p>Our recruitment team handles job posting, candidate screening and placement end to end.</p>
                    </div>
                    <a href="index.html#contact" class="btn btn-primary btn-lg">Talk to our team <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </section>
    </main>

    <?php include 'partials/site-footer.php'; ?>
</body>
</html>
