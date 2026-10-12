<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);

require_once '../includes/database.php';

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261008-3">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Contact', 'fas fa-user', ['url' => 'contacts.php', 'label' => 'Contacts']); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('contacts'); ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($contact): ?>
            <div class="card">
                <div class="card-body">
                    <h2 style="margin-top:0;"><?php echo htmlspecialchars($contact['name']); ?></h2>
                    <dl class="account-facts" style="display:grid; gap:12px; margin:0 0 20px;">
                        <div><strong>Email</strong><br><?php echo htmlspecialchars($contact['email']); ?></div>
                        <div><strong>Phone</strong><br><?php echo htmlspecialchars($contact['phone'] ?: '—'); ?></div>
                        <div><strong>Company</strong><br><?php echo htmlspecialchars($contact['company'] ?: '—'); ?></div>
                        <div><strong>Interest</strong><br><?php echo htmlspecialchars($interest_labels[$contact['interest']] ?? $contact['interest']); ?></div>
                        <div><strong>Status</strong><br><?php echo htmlspecialchars($status_labels[$contact['status'] ?? 'new'] ?? $contact['status']); ?></div>
                        <div><strong>Submitted</strong><br><?php echo date('F j, Y \a\t g:i A', strtotime($contact['created_at'])); ?></div>
                    </dl>
                    <h3>Message</h3>
                    <div style="white-space:pre-wrap; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;"><?php echo htmlspecialchars($contact['message']); ?></div>
                    <form method="POST" style="margin-top:20px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
                        <button type="submit" class="btn btn-danger" data-confirm-title="Remove this contact?" data-confirm="This inquiry will be deleted." data-confirm-ok="Remove" data-confirm-icon="fas fa-trash">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
