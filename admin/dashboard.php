<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include shared database connection
require_once '../includes/database.php';

// Get statistics
$pdo = get_db_connection();
$stats = [
    'contacts' => 0,
    'today_contacts' => 0,
    'recent_contacts' => 0,
    'custom_requests' => 0,
    'recent_custom' => 0,
    'jobs' => 0,
    'programmes' => 0,
    'applications' => 0,
    'pending_applications' => 0,
    'recent_applications' => 0,
    'registrations' => 0,
    'pending_registrations' => 0,
    'recent_registrations' => 0,
    'bookings' => 0,
    'pending_bookings' => 0,
    'today_bookings' => 0,
    'recent_bookings' => 0,
    'trainers' => 0,
    'active_trainers' => 0
];

$recent_contacts = [];
$recent_custom_requests = [];
$interest_breakdown = [];

if ($pdo) {
    try {
        // Total contacts
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts");
        $stats['contacts'] = $stmt->fetchColumn();
        
        // Today's contacts
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts WHERE DATE(created_at) = CURDATE()");
        $stats['today_contacts'] = $stmt->fetchColumn();
        
        // Recent contacts (this week)
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_contacts'] = $stmt->fetchColumn();
        
        // Custom requests
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts WHERE interest = 'custom'");
        $stats['custom_requests'] = $stmt->fetchColumn();
        
        // Recent custom requests
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts WHERE interest = 'custom' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_custom'] = $stmt->fetchColumn();
        
        // Active jobs
        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE is_active = 1");
        $stats['jobs'] = $stmt->fetchColumn();
        
        // Training programmes
        $stmt = $pdo->query("SELECT COUNT(*) FROM programmes WHERE is_active = 1");
        $stats['programmes'] = $stmt->fetchColumn();
        
        // Job applications
        $stmt = $pdo->query("SELECT COUNT(*) FROM applications");
        $stats['applications'] = $stmt->fetchColumn();
        
        // Pending applications
        $stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'");
        $stats['pending_applications'] = $stmt->fetchColumn();
        
        // Recent applications (this week)
        $stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_applications'] = $stmt->fetchColumn();
        
        // Programme registrations
        $stmt = $pdo->query("SELECT COUNT(*) FROM programme_registrations");
        $stats['registrations'] = $stmt->fetchColumn();
        
        // Pending registrations
        $stmt = $pdo->query("SELECT COUNT(*) FROM programme_registrations WHERE status = 'pending'");
        $stats['pending_registrations'] = $stmt->fetchColumn();
        
        // Recent registrations (this week)
        $stmt = $pdo->query("SELECT COUNT(*) FROM programme_registrations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_registrations'] = $stmt->fetchColumn();
        
        // Recent contacts
        $stmt = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5");
        $recent_contacts = $stmt->fetchAll();
        
        // Recent custom requests
        $stmt = $pdo->query("SELECT * FROM contacts WHERE interest = 'custom' ORDER BY created_at DESC LIMIT 3");
        $recent_custom_requests = $stmt->fetchAll();
        
        // Interest breakdown
        $stmt = $pdo->query("SELECT interest, COUNT(*) as count FROM contacts GROUP BY interest ORDER BY count DESC");
        $interest_breakdown = $stmt->fetchAll();
        
        // Total bookings
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
        $stats['bookings'] = $stmt->fetchColumn();
        
        // Pending bookings
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
        $stats['pending_bookings'] = $stmt->fetchColumn();
        
        // Today's bookings
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE()");
        $stats['today_bookings'] = $stmt->fetchColumn();
        
        // Recent bookings (this week)
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_bookings'] = $stmt->fetchColumn();
        
        // Trainer statistics
        $stmt = $pdo->query("SELECT COUNT(*) FROM trainers");
        $stats['trainers'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM trainers WHERE is_active = 1");
        $stats['active_trainers'] = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        // Handle error silently for demo
    }
}

$interest_labels = [
    'hr-leadership' => 'HR Leadership',
    'employee-relations' => 'Employee Relations',
    'hr-analytics' => 'HR Analytics',
    'hr-compliance' => 'HR Compliance',
    'recruitment' => 'Recruitment',
    'compensation' => 'Compensation',
    'custom' => 'Custom Service'
];

$interest_total = 0;
foreach ($interest_breakdown as $interest_row) {
    $interest_total += (int) $interest_row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Dashboard', 'fas fa-tachometer-alt'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('dashboard'); ?>
        
        <!-- Welcome Section -->
        <div class="card mb-3">
            <div class="card-body">
                <h2 style="margin-bottom: 8px; color: #1e293b; font-size: 24px; font-weight: 700;">
                    <i class="fas fa-home" style="color: #111111; margin-right: 12px;"></i>
                    Welcome to XpressPoint Admin
                </h2>
                <p style="color: #64748b; margin: 0; font-size: 16px;">Manage your HR training business from one central location.</p>
            </div>
        </div>
        
        <!-- Key Metrics - Simplified -->
        <div class="key-metrics">
            <div class="metric-card primary">
                <div class="metric-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-value"><?php echo number_format($stats['contacts']); ?></div>
                    <div class="metric-label">Total Contacts</div>
                    <div class="metric-subtitle">+<?php echo $stats['recent_contacts']; ?> this week</div>
                </div>
            </div>
            
            <div class="metric-card warning">
                <div class="metric-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-value"><?php echo number_format($stats['custom_requests']); ?></div>
                    <div class="metric-label">Custom Requests</div>
                    <div class="metric-subtitle">+<?php echo $stats['recent_custom']; ?> this week</div>
                </div>
            </div>
            
            <div class="metric-card success">
                <div class="metric-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-value"><?php echo number_format($stats['jobs']); ?></div>
                    <div class="metric-label">Active Jobs</div>
                    <div class="metric-subtitle">Currently posted</div>
                </div>
            </div>
            
            <div class="metric-card info">
                <div class="metric-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-value"><?php echo number_format($stats['programmes']); ?></div>
                    <div class="metric-label">Training Programmes</div>
                    <div class="metric-subtitle">Active courses</div>
                </div>
            </div>
            
            <div class="metric-card secondary">
                <div class="metric-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-value"><?php echo number_format($stats['trainers']); ?></div>
                    <div class="metric-label">Expert Trainers</div>
                    <div class="metric-subtitle"><?php echo $stats['active_trainers']; ?> active</div>
                </div>
            </div>
        </div>
        
        <!-- Pending Items - Action Required -->
        <div class="pending-section">
            <h3 class="section-title">
                <i class="fas fa-exclamation-circle"></i>
                Action Required
            </h3>
            <div class="pending-grid">
                <div class="pending-item">
                    <div class="pending-count"><?php echo $stats['pending_applications']; ?></div>
                    <div class="pending-label">Job Applications</div>
                    <a href="applications.php" class="pending-action">Review</a>
                </div>
                <div class="pending-item">
                    <div class="pending-count"><?php echo $stats['pending_registrations']; ?></div>
                    <div class="pending-label">Programme Registrations</div>
                    <a href="registrations.php" class="pending-action">Review</a>
                </div>
                <div class="pending-item">
                    <div class="pending-count"><?php echo $stats['pending_bookings']; ?></div>
                    <div class="pending-label">Facility Bookings</div>
                    <a href="bookings.php" class="pending-action">Review</a>
                </div>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="dashboard-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            <!-- Left Column -->
            <div class="dashboard-left">
                <!-- Quick Actions - Simplified -->
                <div class="quick-actions-section">
                    <h3 class="section-title">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </h3>
                    <div class="quick-actions-grid">
                        <a href="contacts.php" class="quick-action primary">
                            <i class="fas fa-users"></i>
                            <span>All Contacts</span>
                        </a>
                        <a href="contacts.php?filter=custom" class="quick-action warning">
                            <i class="fas fa-star"></i>
                            <span>Custom Requests</span>
                        </a>
                        <a href="jobs.php" class="quick-action success">
                            <i class="fas fa-briefcase"></i>
                            <span>Manage Jobs</span>
                        </a>
                        <a href="programmes.php" class="quick-action info">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Training Programmes</span>
                        </a>
                        <a href="trainers.php" class="quick-action secondary">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Manage Trainers</span>
                        </a>
                    </div>
                </div>
                
                <!-- Recent Contacts - Simplified -->
                <div class="recent-section">
                    <h3 class="section-title">
                        <i class="fas fa-clock"></i>
                        Recent Contacts
                    </h3>
                    <?php if (!empty($recent_contacts)): ?>
                        <div class="recent-list">
                            <?php foreach ($recent_contacts as $contact): ?>
                                <div class="recent-item">
                                    <div class="recent-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="recent-info">
                                        <div class="recent-name"><?php echo htmlspecialchars($contact['name']); ?></div>
                                        <div class="recent-email"><?php echo htmlspecialchars($contact['email']); ?></div>
                                        <?php if ($contact['company']): ?>
                                            <div class="recent-company"><?php echo htmlspecialchars($contact['company']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="recent-meta">
                                        <span class="recent-badge <?php echo $contact['interest'] === 'custom' ? 'custom' : 'primary'; ?>">
                                            <?php echo htmlspecialchars($interest_labels[$contact['interest']] ?? $contact['interest']); ?>
                                        </span>
                                        <div class="recent-date"><?php echo date('M j', strtotime($contact['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="recent-footer">
                            <a href="contacts.php" class="view-all-link">View All Contacts</a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No recent contacts</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="dashboard-right">
                <!-- Custom Service Requests -->
                <?php if (!empty($recent_custom_requests)): ?>
                    <div class="custom-requests-section">
                        <h3 class="section-title">
                            <i class="fas fa-star"></i>
                            Custom Requests
                        </h3>
                        <div class="custom-list">
                            <?php foreach ($recent_custom_requests as $contact): ?>
                                <div class="custom-item">
                                    <div class="custom-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="custom-info">
                                        <div class="custom-name"><?php echo htmlspecialchars($contact['name']); ?></div>
                                        <div class="custom-email"><?php echo htmlspecialchars($contact['email']); ?></div>
                                        <div class="custom-date"><?php echo date('M j', strtotime($contact['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="custom-footer">
                            <a href="contacts.php?filter=custom" class="view-all-link">View All Custom</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Interest Breakdown -->
                <?php if (!empty($interest_breakdown)): ?>
                    <div class="interest-section">
                        <h3 class="section-title">
                            <i class="fas fa-chart-pie"></i>
                            Interest Breakdown
                        </h3>
                        <div class="interest-list">
                            <?php foreach ($interest_breakdown as $interest):
                                $interest_count = (int) $interest['count'];
                                $interest_share = $interest_total > 0 ? round(($interest_count / $interest_total) * 100, 1) : 0;
                                $interest_name = $interest_labels[$interest['interest']] ?? $interest['interest'];
                            ?>
                                <div class="interest-item">
                                    <span class="interest-label">
                                        <?php echo htmlspecialchars($interest_name); ?>
                                    </span>
                                    <span class="interest-bar" role="img" aria-label="<?php echo htmlspecialchars($interest_name); ?>: <?php echo $interest_share; ?>%">
                                        <span class="interest-bar-fill" style="width: <?php echo $interest_share; ?>%"></span>
                                    </span>
                                    <span class="interest-count"><?php echo $interest_count; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- Modern JavaScript for Enhanced UX -->
<script>
// Theme Toggle Functionality
function toggleTheme() {
    const body = document.body;
    const themeIcon = document.getElementById('theme-icon');
    const themeText = document.getElementById('theme-text');
    
    if (body.getAttribute('data-theme') === 'dark') {
        body.removeAttribute('data-theme');
        themeIcon.className = 'fas fa-moon';
        themeText.textContent = 'Dark Mode';
        localStorage.setItem('theme', 'light');
    } else {
        body.setAttribute('data-theme', 'dark');
        themeIcon.className = 'fas fa-sun';
        themeText.textContent = 'Light Mode';
        localStorage.setItem('theme', 'dark');
    }
}

// Load saved theme
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
        document.getElementById('theme-icon').className = 'fas fa-sun';
        document.getElementById('theme-text').textContent = 'Light Mode';
    }
    
    // Add smooth animations to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Add hover effects to table rows
    const tableRows = document.querySelectorAll('.table-row');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(8px) scale(1.02)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0) scale(1)';
        });
    });
    
    // Real-time updates (optional - can be enabled for live data)
    // setInterval(() => {
    //     updateStats();
    // }, 30000); // Update every 30 seconds
});

// Real-time stats updates (disabled by default to prevent artificial inflation)
function updateStats() {
    // This function can be used for actual AJAX calls to fetch real-time data
    // For now, it's disabled to prevent artificial number inflation
}

// Enhanced form interactions
document.addEventListener('DOMContentLoaded', function() {
    const formControls = document.querySelectorAll('.form-control');
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });
        
        control.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });
});

// Smooth scrolling for navigation
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K for search (placeholder)
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        // Add search functionality here
        // Search shortcut pressed
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        // Close any open modals
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });
    }
});

// Enhanced loading states
function showLoading(element) {
    element.classList.add('loading');
    element.style.pointerEvents = 'none';
}

function hideLoading(element) {
    element.classList.remove('loading');
    element.style.pointerEvents = 'auto';
}

// Toast notifications
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
        toast.style.opacity = '1';
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        toast.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Add toast styles
const toastStyles = document.createElement('style');
toastStyles.textContent = `
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--bg-primary);
        border: 1px solid var(--border-primary);
        border-radius: 12px;
        padding: 16px 20px;
        box-shadow: var(--shadow-lg);
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 1000;
        transform: translateX(100%);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        max-width: 300px;
    }
    
    .toast-success {
        border-left: 4px solid var(--accent-success);
    }
    
    .toast-error {
        border-left: 4px solid var(--accent-danger);
    }
    
    .toast-info {
        border-left: 4px solid var(--accent-primary);
    }
    
    .toast i {
        font-size: 18px;
    }
    
    .toast-success i {
        color: var(--accent-success);
    }
    
    .toast-error i {
        color: var(--accent-danger);
    }
    
    .toast-info i {
        color: var(--accent-primary);
    }
`;
document.head.appendChild(toastStyles);

// Enhanced file upload preview
function handleFileUpload(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.createElement('div');
            preview.className = 'file-preview';
            preview.innerHTML = `
                <i class="fas fa-file"></i>
                <span>${file.name}</span>
                <button onclick="this.parentElement.remove()" class="remove-file">
                    <i class="fas fa-times"></i>
                </button>
            `;
            input.parentElement.appendChild(preview);
        };
        reader.readAsDataURL(file);
    }
}

// Add file preview styles
const filePreviewStyles = document.createElement('style');
filePreviewStyles.textContent = `
    .file-preview {
        display: flex;
        align-items: center;
        gap: 12px;
        background: var(--bg-secondary);
        padding: 12px 16px;
        border-radius: 8px;
        margin-top: 8px;
        border: 1px solid var(--border-primary);
    }
    
    .file-preview i {
        color: var(--accent-primary);
    }
    
    .remove-file {
        background: var(--accent-danger);
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: auto;
    }
    
    .remove-file:hover {
        background: #dc2626;
        transform: scale(1.1);
    }
`;
document.head.appendChild(filePreviewStyles);
</script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html> 