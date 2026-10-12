<?php
require_once '../includes/auth.php';
auth_require(['trainer']);

$pdo = get_db_connection();
$user = auth_user();
$error = '';
$success = '';
$trainer = null;

if (!$pdo || empty($user['id'])) {
    header('Location: programmes.php?error=' . urlencode('Database connection failed'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM trainers WHERE user_id = ? LIMIT 1");
$stmt->execute([(int) $user['id']]);
$trainer = $stmt->fetch();

if (!$trainer) {
    $name = $user['name'] !== '' ? $user['name'] : 'Trainer';
    $pdo->prepare("INSERT INTO trainers (user_id, name, title, is_active, display_order) VALUES (?, ?, '', 0, 0)")
        ->execute([(int) $user['id'], $name]);
    $stmt->execute([(int) $user['id']]);
    $trainer = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
            throw new Exception('The form expired. Please try again.');
        }

        $name = trim($_POST['name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $experience_years = (int) ($_POST['experience_years'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $specialties = array_values(array_filter(array_map('trim', explode(',', $_POST['specialties'] ?? ''))));
        $credentials = array_values(array_filter(array_map('trim', explode(',', $_POST['credentials'] ?? ''))));

        if ($name === '' || $title === '') {
            throw new Exception('Name and title are required.');
        }

        $image_path = $trainer['image_path'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/trainers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                throw new Exception('Use a JPG, PNG, GIF, or WebP image.');
            }
            $filename = 'trainer_' . time() . '_' . uniqid() . '.' . $file_extension;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                throw new Exception('Could not upload the image.');
            }
            if ($trainer['image_path'] && !preg_match('/icol-chua\.png|HRDTrainer\.png|AccreditedTrainer\.png|PowerUpMentor\.png/', $trainer['image_path'])) {
                $old = '../' . $trainer['image_path'];
                if (is_file($old)) {
                    unlink($old);
                }
            }
            $image_path = 'assets/images/trainers/' . $filename;
        }

        $pdo->prepare("UPDATE trainers SET name = ?, title = ?, experience_years = ?, image_path = ?, description = ?, specialties = ?, credentials = ? WHERE id = ? AND user_id = ?")
            ->execute([
                $name,
                $title,
                $experience_years,
                $image_path,
                $description,
                json_encode($specialties),
                json_encode($credentials),
                $trainer['id'],
                (int) $user['id'],
            ]);

        $success = 'Profile saved. It appears on the programmes page when an admin has published your card.';
        $stmt->execute([(int) $user['id']]);
        $trainer = $stmt->fetch();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$specialties = [];
$credentials = [];
if (!empty($trainer['specialties'])) {
    $specialties = json_decode($trainer['specialties'], true) ?: [];
}
if (!empty($trainer['credentials'])) {
    $credentials = json_decode($trainer['credentials'], true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My trainer profile - XpressPoint</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('My profile', 'fas fa-id-badge'); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('my_profile'); ?>
        <div class="page-header">
            <h2 class="page-title"><i class="fas fa-id-badge"></i> Trainer card</h2>
            <p>This is the profile shown in the trainer section on the programmes page.</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (empty($trainer['is_active'])): ?>
            <div class="alert alert-warning"><i class="fas fa-eye-slash"></i> Your card is hidden until an admin marks it active.</div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($trainer['name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" class="form-control" required value="<?php echo htmlspecialchars($trainer['title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="experience_years">Years of experience</label>
                        <input type="number" id="experience_years" name="experience_years" min="0" max="50" class="form-control" value="<?php echo htmlspecialchars((string) ($trainer['experience_years'] ?? 0)); ?>">
                    </div>
                    <div class="form-group">
                        <label for="image">Photo</label>
                        <?php if (!empty($trainer['image_path'])): ?>
                            <p><img src="../<?php echo htmlspecialchars($trainer['image_path']); ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:8px;"></p>
                        <?php endif; ?>
                        <input type="file" id="image" name="image" accept="image/*" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="5"><?php echo htmlspecialchars($trainer['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="specialties">Specialties</label>
                        <input type="text" id="specialties" name="specialties" class="form-control" value="<?php echo htmlspecialchars(implode(', ', $specialties)); ?>">
                        <small class="form-text text-muted">Separate items with commas.</small>
                    </div>
                    <div class="form-group">
                        <label for="credentials">Credentials</label>
                        <input type="text" id="credentials" name="credentials" class="form-control" value="<?php echo htmlspecialchars(implode(', ', $credentials)); ?>">
                        <small class="form-text text-muted">Separate items with commas.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Save profile</button>
                </form>
            </div>
        </div>
    </div>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
