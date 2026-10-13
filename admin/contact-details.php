<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

require_once '../includes/database.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = get_db_connection();
$contact_id = (int) ($_GET['id'] ?? 0);
$error_message = '';
$contact = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delete_id = (int) ($_POST['contact_id'] ?? 0);
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_message = 'The form expired. Please try again.';
    } elseif ($pdo && $delete_id > 0) {
        $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$delete_id]);
        header('Location: contacts.php?removed=1');
        exit;
    }
}

if ($pdo && $contact_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->execute([$contact_id]);
    $contact = $stmt->fetch();
}
if (!$contact && $error_message === '') {
    $error_message = 'Contact not found.';
}

$interest_labels = [
    'hr-leadership' => 'HR Leadership',
    'employee-relations' => 'Employee Relations',
    'hr-analytics' => 'HR Analytics',
    'hr-compliance' => 'HR Compliance',
    'recruitment' => 'Recruitment',
    'compensation' => 'Compensation',
    'hiring-recruitment' => 'Hiring & Recruitment',
    'payroll-compensation' => 'Payroll & Compensation',
    'legal-authorities' => 'Legal & Authorities',
    'training-coaching' => 'Training & Coaching',
    'hr-mentoring' => 'HR Mentoring & Consultation',
    'custom' => 'Custom Service',
];
$status_labels = [
    'new' => 'New',
    'contacted' => 'Contacted',
    'qualified' => 'Qualified',
    'blacklisted' => 'Blacklisted',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/detail.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/contact-details.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Contact', 'fas fa-user', ['url' => 'contacts.php', 'label' => 'Contacts']); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('contacts'); ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($contact): ?>
            <div class="detail-page">
                <header class="detail-head">
                    <div class="detail-head-copy">
                        <a class="detail-back" href="contacts.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to contacts</a>
                        <h2 class="detail-title"><?php echo htmlspecialchars($contact['name']); ?></h2>
                        <div class="detail-head-meta"><?php renderStatusBadge($contact['status'] ?? 'new', $status_labels[$contact['status'] ?? 'new'] ?? null); ?></div>
                    </div>
                    <a class="btn btn-primary" href="mailto:<?php echo htmlspecialchars($contact['email']); ?>"><i class="fas fa-envelope" aria-hidden="true"></i> Email</a>
                </header>
                <div class="detail-layout">
                    <div class="detail-main">
                        <section class="detail-card">
                            <h3>Personal details</h3>
                            <div class="kv-list">
                                <div class="kv-row"><span class="kv-label">Email</span><span class="kv-value"><a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>"><?php echo htmlspecialchars($contact['email']); ?></a></span></div>
                                <div class="kv-row"><span class="kv-label">Phone</span><span class="kv-value"><?php echo htmlspecialchars($contact['phone'] ?: 'Not provided'); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Company</span><span class="kv-value"><?php echo htmlspecialchars($contact['company'] ?: 'Not provided'); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Interest</span><span class="kv-value"><?php echo htmlspecialchars($interest_labels[$contact['interest']] ?? $contact['interest']); ?></span></div>
                                <div class="kv-row"><span class="kv-label">Submitted</span><span class="kv-value"><?php echo date('F j, Y \a\t g:i A', strtotime($contact['created_at'])); ?></span></div>
                            </div>
                        </section>
                        <section class="detail-card">
                            <h3>Message</h3>
                            <p class="detail-prose contact-message"><?php echo htmlspecialchars($contact['message']); ?></p>
                        </section>
                    </div>
                    <aside class="detail-side">
                        <section class="detail-card">
                            <h3>Current status</h3>
                            <?php renderStatusBadge($contact['status'] ?? 'new', $status_labels[$contact['status'] ?? 'new'] ?? null); ?>
                            <form method="post" action="contacts.php" class="detail-status-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
                                <label for="detailContactStatus">Change status</label>
                                <select name="status" id="detailContactStatus">
                                    <?php foreach ($status_labels as $status_key => $status_label): ?>
                                    <option value="<?php echo htmlspecialchars($status_key); ?>" <?php echo ($contact['status'] ?? 'new') === $status_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($status_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary">Change status</button>
                            </form>
                        </section>
                        <section class="detail-card">
                            <h3>Quick actions</h3>
                            <div class="detail-actions">
                                <a class="btn btn-secondary" href="mailto:<?php echo htmlspecialchars($contact['email']); ?>"><i class="fas fa-envelope" aria-hidden="true"></i> Email</a>
                                <a class="btn btn-secondary" href="export-contacts.php?format=csv&amp;search=<?php echo rawurlencode($contact['name']); ?>"><i class="fas fa-file-export" aria-hidden="true"></i> Export</a>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
                                    <button type="submit" class="btn btn-danger" data-confirm-title="Remove this contact?" data-confirm="This inquiry will be deleted." data-confirm-ok="Remove" data-confirm-icon="fas fa-trash"><i class="fas fa-trash" aria-hidden="true"></i> Remove</button>
                                </form>
                            </div>
                        </section>
                        <section class="detail-card">
                            <h3>Activity</h3>
                            <ol class="detail-timeline">
                                <li>
                                    <div>
                                        <strong>Submitted</strong>
                                        <time datetime="<?php echo htmlspecialchars($contact['created_at']); ?>"><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></time>
                                    </div>
                                </li>
                            </ol>
                        </section>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
