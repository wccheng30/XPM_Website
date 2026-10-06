<?php
// Include shared database connection
require_once 'includes/database.php';

// Get job ID from URL parameter
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    header('Location: jobs.php?error=Job not found');
    exit;
}

// Get job details
try {
    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND is_active = 1");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
    
    if (!$job) {
        header('Location: jobs.php?error=Job not found');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: jobs.php?error=Database error');
    exit;
}

// Get related jobs (same company or similar type)
try {
    $stmt = $pdo->prepare("
        SELECT * FROM jobs 
        WHERE id != ? AND is_active = 1 
        AND (company_name = ? OR type = ?)
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$job_id, $job['company_name'], $job['type']]);
    $related_jobs = $stmt->fetchAll();
} catch (Exception $e) {
    $related_jobs = [];
}

// Format requirements for better display
$requirements_array = [];
if (!empty($job['requirements'])) {
    // Split by newlines first, then by commas if no newlines
    if (strpos($job['requirements'], "\n") !== false) {
        $lines = explode("\n", $job['requirements']);
        $requirements_array = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $requirements_array[] = $line;
            }
        }
    } else {
        $items = explode(',', $job['requirements']);
        $requirements_array = [];
        foreach ($items as $item) {
            $item = trim($item);
            if (!empty($item)) {
                $requirements_array[] = $item;
            }
        }
    }
}

// Format description with better structure
$description = $job['description'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['company_name']); ?> | XpressPoint</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr($job['description'], 0, 160)); ?>">
    <?php $xpm_page_styles = ['pages/job-detail.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php $xpm_active = 'careers'; include 'partials/site-nav.php'; ?>

    <main id="main">
        <header class="page-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Breadcrumb" data-reveal="fade">
                    <a href="index.html">Home</a><i class="fas fa-chevron-right"></i>
                    <a href="jobs.php">Careers</a><i class="fas fa-chevron-right"></i>
                    <span><?php echo htmlspecialchars($job['title']); ?></span>
                </nav>
                <div class="job-hero">
                    <div>
                        <?php if (!empty($job['company_name'])): ?>
                        <span class="eyebrow" data-reveal><i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?></span>
                        <?php endif; ?>
                        <h1 data-split><?php echo htmlspecialchars($job['title']); ?></h1>
                        <div class="job-hero-meta" data-reveal-stagger="fade">
                            <span class="tag"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                            <span class="tag"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($job['type']); ?></span>
                            <?php if (!empty($job['salary_range'])): ?>
                            <span class="tag"><i class="fas fa-wallet"></i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($job['experience_level'])): ?>
                            <span class="tag"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($job['experience_level']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="job-hero-actions" data-reveal="right">
                        <a href="job-application.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary btn-lg">
                            Apply now <i class="fas fa-arrow-right"></i>
                        </a>
                        <a href="jobs.php" class="btn btn-ghost">
                            <i class="fas fa-arrow-left"></i> All jobs
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="container job-layout">
                <div class="job-main">
                    <div class="job-facts" data-reveal-stagger>
                        <div class="job-fact">
                            <span class="icon-box icon-box-sm"><i class="fas fa-calendar"></i></span>
                            <div>
                                <span class="job-fact-label">Posted</span>
                                <span class="job-fact-value"><?php echo date('F j, Y', strtotime($job['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="job-fact">
                            <span class="icon-box icon-box-sm"><i class="fas fa-signal"></i></span>
                            <div>
                                <span class="job-fact-label">Status</span>
                                <span class="job-fact-value"><span class="pulse-dot"></span> Actively hiring</span>
                            </div>
                        </div>
                        <?php if (!empty($job['start_time']) && !empty($job['end_time'])): ?>
                        <div class="job-fact">
                            <span class="icon-box icon-box-sm"><i class="fas fa-business-time"></i></span>
                            <div>
                                <span class="job-fact-label">Work hours</span>
                                <span class="job-fact-value"><?php echo date('g:i A', strtotime($job['start_time'])); ?> - <?php echo date('g:i A', strtotime($job['end_time'])); ?></span>
                            </div>
                        </div>
                        <?php elseif (is_null($job['start_time']) && is_null($job['end_time'])): ?>
                        <div class="job-fact">
                            <span class="icon-box icon-box-sm"><i class="fas fa-business-time"></i></span>
                            <div>
                                <span class="job-fact-label">Work hours</span>
                                <span class="job-fact-value text-muted">To be confirmed</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($job['job_badge'])): ?>
                        <div class="job-fact">
                            <span class="icon-box icon-box-sm"><i class="fas fa-tag"></i></span>
                            <div>
                                <span class="job-fact-label">Category</span>
                                <span class="job-fact-value"><?php echo htmlspecialchars($job['job_badge']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <article class="job-block" data-reveal>
                        <h2>About the role</h2>
                        <div class="job-description">
                            <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                        </div>
                    </article>

                    <?php if (!empty($requirements_array)): ?>
                    <article class="job-block" data-reveal>
                        <h2>Requirements &amp; qualifications</h2>
                        <ol class="requirements" data-reveal-stagger="fade">
                            <?php foreach ($requirements_array as $index => $requirement): ?>
                            <li>
                                <span class="requirement-number"><?php echo str_pad($index + 1, 2, '0', STR_PAD_LEFT); ?></span>
                                <span><?php echo htmlspecialchars($requirement); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    </article>
                    <?php endif; ?>
                </div>

                <aside class="job-sidebar">
                    <div class="card job-summary" data-reveal="right">
                        <?php if (!empty($job['poster_image'])): ?>
                        <div class="job-summary-logo">
                            <img src="<?php echo htmlspecialchars($job['poster_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($job['company_name']); ?> Logo">
                        </div>
                        <?php endif; ?>
                        <h3>Job highlights</h3>
                        <dl class="summary-list">
                            <div><dt>Job type</dt><dd><?php echo htmlspecialchars($job['type']); ?></dd></div>
                            <div><dt>Location</dt><dd><?php echo htmlspecialchars($job['location']); ?></dd></div>
                            <?php if (!empty($job['salary_range'])): ?>
                            <div><dt>Salary</dt><dd><?php echo htmlspecialchars($job['salary_range']); ?></dd></div>
                            <?php endif; ?>
                            <?php if (!empty($job['experience_level'])): ?>
                            <div><dt>Experience</dt><dd><?php echo htmlspecialchars($job['experience_level']); ?></dd></div>
                            <?php endif; ?>
                        </dl>
                        <a href="job-application.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary btn-block">
                            Apply for this position <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="card card-flat" data-reveal="right">
                        <h3>Questions?</h3>
                        <ul class="contact-lines">
                            <li><i class="fas fa-envelope"></i><a href="mailto:hr@xpresspointservices.com">hr@xpresspointservices.com</a></li>
                            <li><i class="fas fa-phone"></i><a href="tel:+60167721113">+60 16-772 1113</a></li>
                            <li><i class="fas fa-clock"></i><span>We reply within 24 hours</span></li>
                        </ul>
                    </div>
                </aside>
            </div>
        </section>

        <?php if (!empty($related_jobs)): ?>
        <section class="section section-alt">
            <div class="container">
                <div class="section-header align-left" data-reveal>
                    <span class="eyebrow"><i class="fas fa-briefcase"></i> Keep exploring</span>
                    <h2 class="section-title">Related jobs</h2>
                </div>
                <div class="grid grid-3" data-reveal-stagger>
                    <?php foreach ($related_jobs as $related_job): ?>
                    <a href="job-detail.php?id=<?php echo $related_job['id']; ?>" class="card related-job">
                        <span class="listing-sub"><?php echo htmlspecialchars($related_job['company_name']); ?></span>
                        <h4><?php echo htmlspecialchars($related_job['title']); ?></h4>
                        <div class="related-job-foot">
                            <span class="tag"><?php echo htmlspecialchars($related_job['type']); ?></span>
                            <span class="link-arrow">View <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php include 'partials/site-footer.php'; ?>

    <script>
        // Toast Notification Function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
