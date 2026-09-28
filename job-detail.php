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
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Job Detail Specific Styles */
        .job-detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .job-detail-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .job-detail-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .job-detail-header-content {
            position: relative;
            z-index: 2;
        }
        
        .job-detail-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .job-detail-company {
            font-size: 1.25rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }
        
        .job-detail-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .job-meta-item i {
            font-size: 1.1rem;
        }
        
        .job-detail-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-apply-large {
            background: #10b981;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
            border: 2px solid #10b981;
        }
        
        .btn-apply-large:hover {
            background: #059669;
            border-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }
        
        .job-detail-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .job-main-content {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .job-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .job-sidebar-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: #3b82f6;
        }
        
        .job-description-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #374151;
            margin-bottom: 2rem;
        }

        /* Job Overview Section */
        .job-overview-section {
            margin-bottom: 3rem;
        }

        .job-overview-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .overview-item {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .overview-item:nth-child(3) {
            grid-column: 1 / 2;
            grid-row: 2 / 3;
        }

        .overview-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }

        .overview-icon {
            width: 48px;
            height: 48px;
            background: #3b82f6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 1.5rem;
            margin-left: 1.5rem;
        }

        .overview-content h4 {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .overview-content p {
            font-size: 1.125rem;
            color: #1f2937;
            font-weight: 600;
            margin: 0;
            line-height: 1.4;
        }

        /* Center text for first two cards only */
        .overview-item:nth-child(1) .overview-content,
        .overview-item:nth-child(2) .overview-content {
            text-align: center;
            flex: 1;
        }

        .overview-item:nth-child(1) .overview-content h4,
        .overview-item:nth-child(2) .overview-content h4 {
            text-align: center;
        }

        .overview-item:nth-child(1) .overview-content p,
        .overview-item:nth-child(2) .overview-content p {
            text-align: center;
        }

        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .badge-custom {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            border: 1px solid #bfdbfe;
        }

        .badge-custom i {
            font-size: 0.625rem;
            opacity: 0.8;
        }

        /* Job Description Section */
        .job-description-section {
            margin-bottom: 3rem;
        }

        /* Requirements Section */
        .requirements-section {
            margin-bottom: 3rem;
        }

        .requirements-grid {
            display: grid;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .requirement-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .requirement-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-color: #10b981;
        }

        .requirement-number {
            width: 30px;
            height: 30px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .requirement-content {
            font-size: 1rem;
            line-height: 1.6;
            color: #374151;
        }

        /* Additional Information Section */
        .additional-info-section {
            margin-bottom: 3rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .info-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }

        .info-item h4 {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-item h4 i {
            color: #3b82f6;
        }

        .info-item p {
            font-size: 1rem;
            color: #374151;
            margin: 0;
            line-height: 1.5;
        }

        /* Sidebar Action Buttons */
        .action-item {
            margin-bottom: 1rem;
        }

        .action-item:last-child {
            margin-bottom: 0;
        }



        /* Contact Information */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .contact-item i {
            color: #3b82f6;
            font-size: 1.1rem;
            margin-top: 0.1rem;
        }

        .contact-item h4 {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            margin: 0 0 0.25rem 0;
        }

        .contact-item p {
            font-size: 0.875rem;
            color: #1f2937;
            font-weight: 600;
            margin: 0;
        }
        
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .requirements-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 1rem;
            line-height: 1.6;
            color: #374151;
        }
        
        .requirements-list li:last-child {
            border-bottom: none;
        }
        
        .requirements-list li::before {
            content: '✓';
            color: #10b981;
            font-weight: bold;
            font-size: 1.1rem;
            margin-top: 0.1rem;
        }
        
        .job-highlights {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        /* Company Logo Section */
        .company-logo-section {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .company-logo {
            max-width: 120px;
            max-height: 120px;
            width: auto;
            height: auto;
            transition: transform 0.3s ease;
        }
        
        .company-logo:hover {
            transform: scale(1.05);
        }
        
        .highlight-item {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 12px;
            border-left: 4px solid #3b82f6;
        }
        
        .highlight-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .highlight-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 600;
        }
        
        .related-jobs {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .related-jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .related-job-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .related-job-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
        }
        
        .related-job-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .related-job-company {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.75rem;
        }
        
        .related-job-type {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .related-job-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.75rem;
        }
        
        .related-job-link:hover {
            color: #2563eb;
        }
        
        @media (max-width: 768px) {
            .job-detail-content {
                grid-template-columns: 1fr;
            }
            
            .job-detail-title {
                font-size: 2rem;
            }
            
            .job-detail-meta {
                flex-direction: column;
                gap: 1rem;
            }
            
            .job-detail-actions {
                flex-direction: column;
            }
            
            .job-highlights {
                grid-template-columns: 1fr;
            }

            .job-overview-grid {
                grid-template-columns: 1fr;
            }

            .overview-item:nth-child(3) {
                grid-column: 1 / 2;
                grid-row: auto;
            }
        }
    </style>
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

    <!-- Job Detail Header -->
    <div class="job-detail-container">
        <div class="job-detail-header">
            <div class="job-detail-header-content">
                <h1 class="job-detail-title"><?php echo htmlspecialchars($job['title']); ?></h1>
                <div class="job-detail-company">
                    <i class="fas fa-building"></i>
                    <?php echo htmlspecialchars($job['company_name']); ?>
                </div>
                
                <div class="job-detail-meta">
                    <div class="job-meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($job['location']); ?></span>
                    </div>
                    <div class="job-meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo htmlspecialchars($job['type']); ?></span>
                    </div>
                    <?php if (!empty($job['salary_range'])): ?>
                    <div class="job-meta-item">
                        <i class="fas fa-dollar-sign"></i>
                        <span><?php echo htmlspecialchars($job['salary_range']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($job['experience_level'])): ?>
                    <div class="job-meta-item">
                        <i class="fas fa-user-tie"></i>
                        <span><?php echo htmlspecialchars($job['experience_level']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="job-detail-actions">
                    <a href="job-application.php?job_id=<?php echo $job['id']; ?>" class="btn-apply-large">
                        <i class="fas fa-paper-plane"></i>
                        Apply Now
                    </a>
                    <a href="jobs.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Back to Jobs
                    </a>
                </div>
            </div>
        </div>

        <!-- Job Content -->
        <div class="job-detail-content">
            <!-- Main Content -->
            <div class="job-main-content">
                <!-- Job Overview -->
                <div class="job-overview-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Job Overview
                    </h2>
                    <div class="job-overview-grid">
                        <div class="overview-item">
                            <div class="overview-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="overview-content">
                                <h4>Posted Date</h4>
                                <p><?php echo date('F j, Y', strtotime($job['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="overview-item">
                            <div class="overview-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="overview-content">
                                <h4>Job Status</h4>
                                <p><span class="status-badge active">Active</span></p>
                            </div>
                        </div>
                        <?php if (!empty($job['start_time']) && !empty($job['end_time'])): ?>
                        <div class="overview-item">
                            <div class="overview-icon">
                                <i class="fas fa-business-time"></i>
                            </div>
                            <div class="overview-content">
                                <h4>Work Hours</h4>
                                <p><?php echo date('g:i A', strtotime($job['start_time'])); ?> - <?php echo date('g:i A', strtotime($job['end_time'])); ?></p>
                            </div>
                        </div>
                        <?php elseif (is_null($job['start_time']) && is_null($job['end_time'])): ?>
                        <div class="overview-item">
                            <div class="overview-icon">
                                <i class="fas fa-question-circle" style="color: #f59e0b;"></i>
                            </div>
                            <div class="overview-content">
                                <h4>Work Hours</h4>
                                <p style="color: #f59e0b; font-weight: 500;">-</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($job['job_badge'])): ?>
                        <div class="overview-item">
                            <div class="overview-icon">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="overview-content">
                                <h4>Job Badge</h4>
                                <p><span class="badge-custom"><i class="fas fa-chart-bar"></i><?php echo htmlspecialchars($job['job_badge']); ?></span></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Job Description -->
                <div class="job-description-section">
                    <h2 class="section-title">
                        <i class="fas fa-file-alt"></i>
                        Job Description
                    </h2>
                    <div class="job-description-content">
                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($requirements_array)): ?>
                <!-- Requirements & Qualifications -->
                <div class="requirements-section">
                    <h2 class="section-title">
                        <i class="fas fa-list-check"></i>
                        Requirements & Qualifications
                    </h2>
                    <div class="requirements-grid">
                        <?php foreach ($requirements_array as $index => $requirement): ?>
                        <div class="requirement-item">
                            <div class="requirement-number"><?php echo $index + 1; ?></div>
                            <div class="requirement-content">
                                <?php echo htmlspecialchars($requirement); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Additional Information -->
                <div class="additional-info-section">
                    <h2 class="section-title">
                        <i class="fas fa-plus-circle"></i>
                        Additional Information
                    </h2>
                    <div class="info-grid">

                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="job-sidebar">
                <!-- Job Highlights -->
                <div class="job-sidebar-card">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Job Highlights
                    </h3>
                    
                    <?php if (!empty($job['poster_image'])): ?>
                    <!-- Company Logo -->
                    <div class="company-logo-section">
                        <img src="<?php echo htmlspecialchars($job['poster_image']); ?>" 
                             alt="<?php echo htmlspecialchars($job['company_name']); ?> Logo" 
                             class="company-logo">
                    </div>
                    <?php endif; ?>
                    
                    <div class="job-highlights">
                        <div class="highlight-item">
                            <div class="highlight-label">Job Type</div>
                            <div class="highlight-value"><?php echo htmlspecialchars($job['type']); ?></div>
                        </div>
                        <div class="highlight-item">
                            <div class="highlight-label">Location</div>
                            <div class="highlight-value"><?php echo htmlspecialchars($job['location']); ?></div>
                        </div>
                        <?php if (!empty($job['salary_range'])): ?>
                        <div class="highlight-item">
                            <div class="highlight-label">Salary</div>
                            <div class="highlight-value"><?php echo htmlspecialchars($job['salary_range']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($job['experience_level'])): ?>
                        <div class="highlight-item">
                            <div class="highlight-label">Experience</div>
                            <div class="highlight-value"><?php echo htmlspecialchars($job['experience_level']); ?></div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- Apply Button -->
                <div class="job-sidebar-card">
                    <h3 class="section-title">
                        <i class="fas fa-paper-plane"></i>
                        Apply for this Position
                    </h3>
                    
                    <div class="action-item">
                        <a href="job-application.php?job_id=<?php echo $job['id']; ?>" class="btn-apply-large" style="width: 100%; justify-content: center; padding: 0.75rem 1rem;">
                            <i class="fas fa-paper-plane"></i>
                            Apply Now
                        </a>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="job-sidebar-card">
                    <h3 class="section-title">
                        <i class="fas fa-phone"></i>
                        Contact Information
                    </h3>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email</h4>
                                <p>hr@xpresspointservices.com</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <h4>Phone</h4>
                                <p>+60 16-772 1113</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>Response Time</h4>
                                <p>Within 24 hours</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Jobs -->
        <?php if (!empty($related_jobs)): ?>
        <div class="related-jobs">
            <h2 class="section-title">
                <i class="fas fa-briefcase"></i>
                Related Jobs
            </h2>
            <div class="related-jobs-grid">
                <?php foreach ($related_jobs as $related_job): ?>
                <div class="related-job-card">
                    <h4 class="related-job-title"><?php echo htmlspecialchars($related_job['title']); ?></h4>
                    <div class="related-job-company"><?php echo htmlspecialchars($related_job['company_name']); ?></div>
                    <span class="related-job-type"><?php echo htmlspecialchars($related_job['type']); ?></span>
                    <a href="job-detail.php?id=<?php echo $related_job['id']; ?>" class="related-job-link">
                        View Details
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

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
                        <li><a href="index.html#about">15+ Years Experience</a></li>
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

    <script src="assets/js/main.js"></script>
    
    <script>
        // Job Detail Page Interactive Features
        
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
            
            // Add toast styles if not already present
            if (!document.getElementById('toast-styles')) {
                const style = document.createElement('style');
                style.id = 'toast-styles';
                style.textContent = `
                    .toast {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: white;
                        border-radius: 8px;
                        padding: 1rem 1.5rem;
                        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                        z-index: 10000;
                        transform: translateX(100%);
                        transition: transform 0.3s ease;
                        border-left: 4px solid #3b82f6;
                    }
                    .toast.show { transform: translateX(0); }
                    .toast-success { border-left-color: #10b981; }
                    .toast-error { border-left-color: #ef4444; }
                    .toast-info { border-left-color: #3b82f6; }
                    .toast-content {
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    }
                    .toast-content i {
                        font-size: 1.1rem;
                    }
                    .toast-success .toast-content i { color: #10b981; }
                    .toast-error .toast-content i { color: #ef4444; }
                    .toast-info .toast-content i { color: #3b82f6; }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Hide toast after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 3000);
        }
        

    </script>
</body>
</html>
