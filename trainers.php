<?php
// Include database connection
require_once 'includes/database.php';

// Get active trainers from database
$trainers = [];
$pdo = get_db_connection();

if ($pdo) {
    try {
        $sql = "SELECT * FROM trainers WHERE is_active = 1 ORDER BY display_order ASC, name ASC";
        $stmt = $pdo->query($sql);
        $trainers = $stmt->fetchAll();
    } catch (PDOException $e) {
        // If database error, fall back to static content
        $trainers = [];
    }
}

// If no trainers found in database, use static content
if (empty($trainers)) {
    // Static trainers data as fallback
    $static_trainers = [
        [
            'name' => 'iCoL Chua',
            'title' => 'PUM Founder & Senior HR Specialist',
            'experience_years' => 20,
            'image_path' => 'assets/images/icol-chua.png',
            'description' => 'Founder of Power Up Mentor and XpressPoint, iCoL brings over two decades of HR expertise to Melaka\'s training landscape. His vision of uniting trainers has created a collaborative platform that elevates the entire training ecosystem in Melaka and beyond.',
            'specialties' => ['Employee Relations', 'Training & Development', 'HR Compliance', 'PUM Leadership'],
            'credentials' => ['Certified Trainer', 'HRD Corp Registered', 'PUM Founder']
        ],
        [
            'name' => 'Sarah Johnson',
            'title' => 'PUM Senior HR Consultant',
            'experience_years' => 15,
            'image_path' => 'assets/images/HRDTrainer.png',
            'description' => 'A key member of the Power Up Mentor collective, Sarah brings extensive Melaka-based experience in performance management and leadership development. Her collaborative approach exemplifies the PUM spirit of elevating the training industry through shared expertise.',
            'specialties' => ['Performance Management', 'Leadership Development', 'Change Management', 'PUM Collaboration'],
            'credentials' => ['SPHR Certified', 'Master Trainer', 'PUM Member']
        ],
        [
            'name' => 'Michael Chen',
            'title' => 'PUM HR Operations Expert',
            'experience_years' => 12,
            'image_path' => 'assets/images/AccreditedTrainer.png',
            'description' => 'As a dedicated PUM member, Michael brings specialized HR operations expertise to Melaka\'s training collective. His focus on workplace safety and systems implementation helps strengthen the overall quality and professionalism of training services across the region.',
            'specialties' => ['Payroll Management', 'HR Systems', 'Workplace Safety', 'PUM Standards'],
            'credentials' => ['PHR Certified', 'Safety Specialist', 'PUM Member']
        ],
        [
            'name' => 'Dr. Lisa Wong',
            'title' => 'PUM Organizational Development Specialist',
            'experience_years' => 18,
            'image_path' => 'assets/images/PowerUpMentor.png',
            'description' => 'Dr. Lisa embodies the Power Up Mentor philosophy through her research-based approach to organizational development. Her academic excellence combined with practical HR experience strengthens Melaka\'s training ecosystem, providing evidence-based solutions that elevate the collective\'s professional standards.',
            'specialties' => ['Organizational Psychology', 'Team Building', 'Strategic HR', 'PUM Research'],
            'credentials' => ['PhD in Psychology', 'SHRM-SCP', 'PUM Member']
        ]
    ];
    $trainers = $static_trainers;
}
?>

<!-- Expert Trainers Section -->
<div class="expert-trainers">
    <div class="trainers-section-header">
        <!-- Section Badge at Top -->
        <div class="section-badge">
            <i class="fas fa-users"></i>
            <span>Power Up Mentor</span>
        </div>
        
        <!-- PUM Logo Below Badge -->
        <div class="pum-logo-section">
            <img src="assets/images/PowerUpMentor.png" alt="Power Up Mentor (PUM) Logo" style="max-width: 350px; height: auto; filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));">
        </div>
        
        <!-- Overview Content -->
        <h3>Melaka's Premier Training Collective</h3>
        <p>Power Up Mentor (PUM) serves as a collaborative platform that benefits individual trainers, clients, and the training ecosystem as a whole. Our collective brings together passionate, experienced trainers from Melaka to elevate the visibility, credibility, and professionalism of the training industry.</p>
    </div>
    
    <!-- Trainers List Container -->
    <div class="trainers-list-container">
        <div class="trainers-grid">
            <?php foreach ($trainers as $trainer): 
                // Parse specialties and credentials
                $specialties = [];
                $credentials = [];
                
                if (isset($trainer['specialties'])) {
                    if (is_string($trainer['specialties'])) {
                        $specialties = json_decode($trainer['specialties'], true) ?: [];
                    } else {
                        $specialties = $trainer['specialties'];
                    }
                }
                
                if (isset($trainer['credentials'])) {
                    if (is_string($trainer['credentials'])) {
                        $credentials = json_decode($trainer['credentials'], true) ?: [];
                    } else {
                        $credentials = $trainer['credentials'];
                    }
                }
            ?>
                <div class="trainer-card">
                    <div class="trainer-card-inner">
                        <div class="trainer-image">
                            <img src="<?php echo htmlspecialchars($trainer['image_path']); ?>" alt="<?php echo htmlspecialchars($trainer['name']); ?>">
                        </div>
                        <div class="trainer-info">
                            <h4><?php echo htmlspecialchars($trainer['name']); ?></h4>
                            <p class="trainer-title"><?php echo htmlspecialchars($trainer['title']); ?></p>
                            <p class="trainer-experience"><?php echo $trainer['experience_years']; ?>+ Years Experience</p>
                            
                            <?php if (!empty($specialties)): ?>
                            <div class="trainer-specialties">
                                    <?php foreach ($specialties as $specialty): ?>
                                        <span class="specialty-tag"><?php echo htmlspecialchars($specialty); ?></span>
                                    <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($trainer['description'])): ?>
                            <div class="trainer-description">
                                    <p><?php echo htmlspecialchars($trainer['description']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($credentials)): ?>
                            <div class="trainer-credentials">
                                    <?php foreach ($credentials as $credential): ?>
                                        <span class="credential"><?php echo htmlspecialchars($credential); ?></span>
                                    <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
