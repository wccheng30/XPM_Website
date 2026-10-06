<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $application_id = $_POST['application_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    
    if ($application_id && $new_status) {
        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $application_id]);
                $success_message = "Application status updated successfully!";
            } catch (PDOException $e) {
                $error_message = "Error updating application status.";
            }
        } else {
            $error_message = "Database connection failed.";
        }
    }
}

// Handle interview comment updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_interview_comment') {
    $application_id = $_POST['application_id'] ?? null;
    $interview_comment = $_POST['interview_comment'] ?? '';
    
    if ($application_id) {
        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE applications SET interview_comment = ? WHERE id = ?");
                $stmt->execute([$interview_comment, $application_id]);
                $success_message = "Interview comment updated successfully!";
            } catch (PDOException $e) {
                $error_message = "Error updating interview comment.";
            }
        } else {
            $error_message = "Database connection failed.";
        }
    }
}

// Include database connection
require_once '../includes/database.php';

$pdo = get_db_connection();
$application = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $application_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, j.title as job_title, j.company_name, j.location, j.type as job_type, j.description as job_description
            FROM applications a 
            LEFT JOIN jobs j ON a.job_id = j.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch();
    } catch (PDOException $e) {
        echo "Error loading application details.";
        exit;
    }
}

if (!$application) {
    echo "Application not found.";
    exit;
}
?>

<?php if (isset($success_message)): ?>
    <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-check-circle" style="color: #10b981;"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="application-details" style="padding: 0;">
    <div class="details-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- Candidate Information -->
        <div class="detail-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-user" style="color: #111111;"></i> Candidate Information</h4>
            <div class="detail-content">
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Name:</span>
                    <span class="value" style="color: #1f2937; font-weight: 500;"><?php echo htmlspecialchars($application['name']); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Email:</span>
                    <span class="value" style="color: #1f2937;">
                        <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>" style="color: #111111; text-decoration: none;"><?php echo htmlspecialchars($application['email']); ?></a>
                    </span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Phone:</span>
                    <span class="value" style="color: #1f2937;">
                        <?php if ($application['phone']): ?>
                            <a href="tel:<?php echo htmlspecialchars($application['phone']); ?>" style="color: #111111; text-decoration: none;"><?php echo htmlspecialchars($application['phone']); ?></a>
                        <?php else: ?>
                            <span style="color: #9ca3af;">Not provided</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">IC Number:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($application['ic_number'] ?? ''); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Applied:</span>
                    <span class="value" style="color: #1f2937;"><?php echo date('F j, Y \a\t g:i A', strtotime($application['created_at'])); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Application Type:</span>
                    <span class="value">
                        <span class="application-type-badge" style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; background: <?php echo $application['application_type'] === 'with_resume' ? 'rgba(17, 17, 17, 0.1); color: #111111;' : 'rgba(16, 185, 129, 0.1); color: #10b981;'; ?>">
                            <?php echo $application['application_type'] === 'with_resume' ? 'With Resume' : 'Detailed Application'; ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Status:</span>
                    <span class="value">
                        <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($application['status'])); ?>" style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?php echo ucfirst($application['status']); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Job Information -->
        <div class="detail-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-briefcase" style="color: #111111;"></i> Position Details</h4>
            <div class="detail-content" style="display: flex; flex-direction: column; gap: 12px;">
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Position:</span>
                    <span class="value" style="color: #1f2937; font-weight: 500;"><?php echo htmlspecialchars($application['job_title'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Company:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($application['company_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Location:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($application['location'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Type:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($application['job_type'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Cover Letter -->
    <?php if ($application['cover_letter_path']): ?>
        <div class="detail-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-file-alt" style="color: #111111;"></i> Cover Letter</h4>
            <div class="cover-letter-actions" style="display: flex; gap: 12px; flex-wrap: wrap;">
                <button class="btn btn-primary" onclick="previewCoverLetter('<?php echo htmlspecialchars($application['cover_letter_path']); ?>', '<?php echo htmlspecialchars($application['name']); ?>')" style="background: #111111; color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
                    <i class="fas fa-eye"></i>
                    Preview Cover Letter
                </button>
                <a href="../<?php echo htmlspecialchars($application['cover_letter_path']); ?>" 
                   class="btn btn-secondary" target="_blank" style="background: #6b7280; color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                    <i class="fas fa-download"></i>
                    Download
                </a>
                <span class="file-info" style="color: #6b7280; font-size: 14px; display: flex; align-items: center;">
                    <?php 
                    $file_extension = pathinfo($application['cover_letter_path'], PATHINFO_EXTENSION);
                    echo strtoupper($file_extension) . ' File';
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Resume -->
    <?php if ($application['resume_path']): ?>
        <div class="detail-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-file-pdf" style="color: #111111;"></i> Resume/CV</h4>
            <div class="resume-actions" style="display: flex; gap: 12px; flex-wrap: wrap;">
                <button class="btn btn-primary" onclick="previewResume('<?php echo htmlspecialchars($application['resume_path']); ?>', '<?php echo htmlspecialchars($application['name']); ?>')" style="background: #111111; color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
                    <i class="fas fa-eye"></i>
                    Preview Resume
                </button>
                <a href="../<?php echo htmlspecialchars($application['resume_path']); ?>" 
                   class="btn btn-secondary" target="_blank">
                    <i class="fas fa-download"></i>
                    Download
                </a>
                <span class="file-info">
                    <?php 
                    $file_extension = pathinfo($application['resume_path'], PATHINFO_EXTENSION);
                    echo strtoupper($file_extension) . ' File';
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Application Type & Education -->
    <?php if ($application['application_type'] === 'without_resume'): ?>
        <div class="detail-section" style="background: #f0fdf4; border: 1px solid #16a34a; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-graduation-cap" style="color: #16a34a;"></i> Education & Qualifications</h4>
            <div class="detail-content" style="display: flex; flex-direction: column; gap: 12px;">
                <?php if ($application['education_level']): ?>
                    <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                        <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Education Level:</span>
                        <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $application['education_level']))); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($application['education_details']): ?>
                    <div class="detail-row" style="display: flex; justify-content: space-between; align-items: flex-start; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                        <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Education Details:</span>
                        <span class="value" style="color: #1f2937; text-align: right; max-width: 300px;"><?php echo nl2br(htmlspecialchars($application['education_details'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- References -->
        <?php if ($application['reference1_name'] || $application['reference2_name'] || $application['reference3_name']): ?>
            <div class="detail-section" style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-users" style="color: #f59e0b;"></i> References</h4>
                <div class="detail-content" style="display: flex; flex-direction: column; gap: 16px;">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <?php if ($application["reference{$i}_name"]): ?>
                            <div style="background: white; border-radius: 8px; padding: 16px; border: 1px solid #f3f4f6;">
                                <h5 style="margin: 0 0 12px 0; color: #1f2937; font-size: 14px; font-weight: 600;">Reference <?php echo $i; ?></h5>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div>
                                        <span style="font-weight: 500; color: #374151; font-size: 13px;">Name:</span>
                                        <div style="color: #1f2937; margin-top: 4px;"><?php echo htmlspecialchars($application["reference{$i}_name"]); ?></div>
                                    </div>
                                    <div>
                                        <span style="font-weight: 500; color: #374151; font-size: 13px;">Position:</span>
                                        <div style="color: #1f2937; margin-top: 4px;"><?php echo htmlspecialchars($application["reference{$i}_position"] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span style="font-weight: 500; color: #374151; font-size: 13px;">Company:</span>
                                        <div style="color: #1f2937; margin-top: 4px;"><?php echo htmlspecialchars($application["reference{$i}_company"] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span style="font-weight: 500; color: #374151; font-size: 13px;">Phone:</span>
                                        <div style="color: #1f2937; margin-top: 4px;">
                                            <?php if ($application["reference{$i}_phone"]): ?>
                                                <a href="tel:<?php echo htmlspecialchars($application["reference{$i}_phone"]); ?>" style="color: #111111; text-decoration: none;"><?php echo htmlspecialchars($application["reference{$i}_phone"]); ?></a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="grid-column: 1 / -1;">
                                        <span style="font-weight: 500; color: #374151; font-size: 13px;">Email:</span>
                                        <div style="color: #1f2937; margin-top: 4px;">
                                            <?php if ($application["reference{$i}_email"]): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($application["reference{$i}_email"]); ?>" style="color: #111111; text-decoration: none;"><?php echo htmlspecialchars($application["reference{$i}_email"]); ?></a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Employment History -->
        <?php if ($application['employment1_company'] || $application['employment2_company'] || $application['employment3_company']): ?>
            <div class="detail-section" style="background: #f4f4f5; border: 1px solid #111111; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-briefcase" style="color: #111111;"></i> Employment History</h4>
                <div class="detail-content" style="display: flex; flex-direction: column; gap: 16px;">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <?php if ($application["employment{$i}_company"]): ?>
                            <div style="background: white; border-radius: 8px; padding: 16px; border: 1px solid #f3f4f6;">
                                <h5 style="margin: 0 0 12px 0; color: #1f2937; font-size: 14px; font-weight: 600;">Position <?php echo $i; ?></h5>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div>
                                        <span style="font-weight: 500; color: #374151; font-size: 13px;">Company:</span>
                                        <div style="color: #1f2937; margin-top: 4px;"><?php echo htmlspecialchars($application["employment{$i}_company"]); ?></div>
                                    </div>
                                    <div>
                                        <span style="font-weight: 500; color: #374151; font-size: 13px;">Position:</span>
                                        <div style="color: #1f2937; margin-top: 4px;"><?php echo htmlspecialchars($application["employment{$i}_position"] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span style="font-weight: 500; color: #374151; font-size: 13px;">Duration:</span>
                                        <div style="color: #1f2937; margin-top: 4px;"><?php echo htmlspecialchars($application["employment{$i}_duration"] ?? 'N/A'); ?></div>
                                    </div>
                                    <div style="grid-column: 1 / -1;">
                                        <span style="font-weight: 500; color: #374151; font-size: 13px;">Description:</span>
                                        <div style="color: #1f2937; margin-top: 4px; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($application["employment{$i}_description"] ?? 'N/A')); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Skills & Qualifications -->
        <?php if ($application['technical_skills'] || $application['soft_skills'] || $application['certifications'] || $application['languages']): ?>
            <div class="detail-section" style="background: #fdf2f8; border: 1px solid #ec4899; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-star" style="color: #ec4899;"></i> Skills & Qualifications</h4>
                <div class="detail-content" style="display: flex; flex-direction: column; gap: 16px;">
                    <?php if ($application['technical_skills']): ?>
                        <div style="background: white; border-radius: 8px; padding: 16px; border: 1px solid #f3f4f6;">
                            <h5 style="margin: 0 0 8px 0; color: #1f2937; font-size: 14px; font-weight: 600;">Technical Skills</h5>
                            <div style="color: #1f2937; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($application['technical_skills'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($application['soft_skills']): ?>
                        <div style="background: white; border-radius: 8px; padding: 16px; border: 1px solid #f3f4f6;">
                            <h5 style="margin: 0 0 8px 0; color: #1f2937; font-size: 14px; font-weight: 600;">Soft Skills</h5>
                            <div style="color: #1f2937; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($application['soft_skills'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($application['certifications']): ?>
                        <div style="background: white; border-radius: 8px; padding: 16px; border: 1px solid #f3f4f6;">
                            <h5 style="margin: 0 0 8px 0; color: #1f2937; font-size: 14px; font-weight: 600;">Certifications</h5>
                            <div style="color: #1f2937; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($application['certifications'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($application['languages']): ?>
                        <div style="background: white; border-radius: 8px; padding: 16px; border: 1px solid #f3f4f6;">
                            <h5 style="margin: 0 0 8px 0; color: #1f2937; font-size: 14px; font-weight: 600;">Languages</h5>
                            <div style="color: #1f2937; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($application['languages'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Additional Information -->
        <?php if ($application['expected_salary'] || $application['availability_date'] || $application['additional_notes']): ?>
            <div class="detail-section" style="background: #f4f4f5; border: 1px solid #a855f7; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-info-circle" style="color: #a855f7;"></i> Additional Information</h4>
                <div class="detail-content" style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if ($application['expected_salary']): ?>
                        <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                            <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Expected Salary:</span>
                            <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($application['expected_salary']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($application['availability_date']): ?>
                        <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                            <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Availability Date:</span>
                            <span class="value" style="color: #1f2937;"><?php echo date('F j, Y', strtotime($application['availability_date'])); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($application['additional_notes']): ?>
                        <div class="detail-row" style="display: flex; justify-content: space-between; align-items: flex-start; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                            <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Additional Notes:</span>
                            <span class="value" style="color: #1f2937; text-align: right; max-width: 300px; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($application['additional_notes'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Job Description -->
    <?php if ($application['job_description']): ?>
        <div class="detail-section">
            <h4><i class="fas fa-info-circle"></i> Job Description</h4>
            <div class="job-description-content">
                <?php echo nl2br(htmlspecialchars($application['job_description'])); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Interview Comment -->
    <div class="detail-section interview-comment-section">
        <h4><i class="fas fa-comments"></i> Interview Comment</h4>
        <form method="POST" id="interviewCommentForm">
            <input type="hidden" name="action" value="update_interview_comment">
            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
            <textarea 
                name="interviewer_comment" 
                id="interviewCommentTextarea"
                class="form-control"
                rows="4" 
                placeholder="Enter interview comments, notes, or feedback here..."
            ><?php echo htmlspecialchars($application['interviewer_comment'] ?? ''); ?></textarea>
            <div style="display: flex; justify-content: flex-end; margin-top: 12px;">
                <button type="submit" class="btn btn-save-comment" style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#059669'" onmouseout="this.style.backgroundColor='#10b981'">
                    <i class="fas fa-save"></i>
                    Save Comment
                </button>
            </div>
        </form>
    </div>

    <!-- Actions -->
    <div class="detail-actions" style="display: flex; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
        <button class="btn btn-primary" onclick="updateStatus(<?php echo $application['id']; ?>, '<?php echo $application['status']; ?>')" style="background: #111111; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
            <i class="fas fa-edit"></i>
            Update Status
        </button>
        <a href="export-candidate-pdf.php?id=<?php echo $application['id']; ?>" class="btn btn-success" style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#059669'" onmouseout="this.style.backgroundColor='#10b981'">
            <i class="fas fa-file-pdf"></i>
            Export to PDF
        </a>
        <?php if ($application['job_id']): ?>
            <a href="jobs.php#job-<?php echo $application['job_id']; ?>" target="_blank" class="btn btn-info" style="background: #111111; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
                <i class="fas fa-external-link-alt"></i>
                View Job
            </a>
        <?php endif; ?>
        <?php if (($_SESSION['admin_role'] ?? 'admin') !== 'manager'): ?>
        <button class="btn btn-danger" onclick="deleteApplicationFromDetails(<?php echo $application['id']; ?>, '<?php echo htmlspecialchars(addslashes($application['name'])); ?>')" style="background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background-color 0.2s; margin-left: auto;" onmouseover="this.style.backgroundColor='#dc2626'" onmouseout="this.style.backgroundColor='#ef4444'">
            <i class="fas fa-trash-alt"></i>
            Delete Application
        </button>
        <?php endif; ?>
    </div>
</div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal" style="display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-edit" style="color: #111111;"></i>
                Update Status
            </h3>
            <button class="modal-close" onclick="closeModal()" style="background: none; border: none; font-size: 18px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="status-form" style="padding: 24px;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="application_id" id="applicationId">
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="status" style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-size: 14px;">Application Status</label>
                <select name="status" id="statusSelect" class="form-control" required>
                    <option value="pending">Pending</option>
                    <option value="reviewed">KIV</option>
                    <option value="shortlisted">Shortlisted</option>
                    <option value="rejected">Rejected</option>
                    <option value="blacklisted">Blacklisted</option>
                    <option value="waiting for interview"> Interview </option>
                    <option value="hired"> Hired </option>
                </select>
            </div>
            <div class="form-actions" style="display: flex; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex: 1; background: #f3f4f6; color: #374151; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="flex: 1; background: #111111; color: white; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#111111'" onmouseout="this.style.backgroundColor='#111111'">
                    Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resume Preview Modal -->
<div id="resumePreviewModal" class="modal" style="display: none; position: fixed; z-index: 1060; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
    <div class="modal-content" style="background-color: white; margin: 2% auto; padding: 0; border-radius: 12px; width: 95%; max-width: 900px; height: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600;">
                <i class="fas fa-file-pdf" style="color: #ef4444; margin-right: 8px;"></i>
                Resume Preview - <span id="resumeCandidateName"></span>
            </h3>
            <button class="modal-close" onclick="closeResumePreview()" style="background: none; border: none; font-size: 20px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="resumePreviewContent" style="padding: 20px; height: calc(100% - 80px); overflow-y: auto;">
            <!-- Resume content will be loaded here -->
        </div>
    </div>
</div>

<!-- Cover Letter Preview Modal -->
<div id="coverLetterPreviewModal" class="modal" style="display: none; position: fixed; z-index: 1060; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
    <div class="modal-content" style="background-color: white; margin: 2% auto; padding: 0; border-radius: 12px; width: 95%; max-width: 900px; height: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600;">
                <i class="fas fa-file-alt" style="color: #111111; margin-right: 8px;"></i>
                Cover Letter Preview - <span id="coverLetterCandidateName"></span>
            </h3>
            <button class="modal-close" onclick="closeCoverLetterPreview()" style="background: none; border: none; font-size: 20px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="coverLetterPreviewContent" style="padding: 20px; height: calc(100% - 80px); overflow-y: auto;">
            <!-- Cover letter content will be loaded here -->
        </div>
    </div>
</div>

<script src="../assets/js/xpm-dialog.js?v=20261006-5"></script>
<script>
function updateStatus(applicationId, currentStatus) {
    document.getElementById('applicationId').value = applicationId;
    document.getElementById('statusSelect').value = currentStatus;
    
    const modal = document.getElementById('statusModal');
    modal.style.opacity = '0';
    modal.style.display = 'block';
    setTimeout(() => {
        modal.style.opacity = '1';
    }, 10);
}

function closeModal() {
    const statusModal = document.getElementById('statusModal');
    if (statusModal) {
        statusModal.style.opacity = '0';
        setTimeout(() => {
            statusModal.style.display = 'none';
        }, 200);
    }
}

function previewResume(resumePath, candidateName) {
    const resumeModal = document.getElementById('resumePreviewModal');
    const resumeContent = document.getElementById('resumePreviewContent');
    const candidateNameSpan = document.getElementById('resumeCandidateName');
    
    if (!resumeModal || !resumeContent) {
        console.error('Resume modal elements not found!');
        xpmAlert('Resume preview could not be opened. Please refresh the page.');
        return;
    }
    
    // Set candidate name
    candidateNameSpan.textContent = candidateName;
    
    // Show loading state
    resumeContent.innerHTML = `
        <div style="text-align: center; padding: 40px 20px;">
            <div style="width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top: 3px solid #ef4444; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
            <p style="color: #6b7280; margin: 0;">Loading resume content...</p>
        </div>
    `;
    
    resumeModal.style.display = 'block';
    
    // Load resume content via AJAX
    fetch(`resume-preview.php?path=${encodeURIComponent(resumePath)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            resumeContent.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading resume content:', error);
            resumeContent.innerHTML = `
                <div style="text-align: center; padding: 40px 20px; color: #ef4444;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
                    <h4 style="margin-bottom: 8px; color: #dc2626;">Error Loading Resume</h4>
                    <p style="color: #6b7280; margin: 0;">Unable to load resume content. Please try again.</p>
                    <p style="color: #6b7280; margin: 8px 0 0 0; font-size: 12px;">Error: ${error.message}</p>
                    <div style="margin-top: 20px;">
                        <a href="../${resumePath}" target="_blank" class="btn btn-primary" style="background: #ef4444; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
                            <i class="fas fa-download" style="margin-right: 6px;"></i>
                            Download Resume Instead
                        </a>
                    </div>
                </div>
            `;
        });
}

function closeResumePreview() {
    const resumeModal = document.getElementById('resumePreviewModal');
    resumeModal.style.opacity = '0';
    setTimeout(() => {
        resumeModal.style.display = 'none';
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }, 200);
}

function previewCoverLetter(coverLetterPath, candidateName) {
    const coverLetterModal = document.getElementById('coverLetterPreviewModal');
    const coverLetterContent = document.getElementById('coverLetterPreviewContent');
    const candidateNameSpan = document.getElementById('coverLetterCandidateName');
    
    if (!coverLetterModal || !coverLetterContent) {
        console.error('Cover letter modal elements not found!');
        xpmAlert('Cover letter preview could not be opened. Please refresh the page.');
        return;
    }
    
    // Set candidate name
    candidateNameSpan.textContent = candidateName;
    
    // Show loading state
    coverLetterContent.innerHTML = `
        <div style="text-align: center; padding: 40px 20px;">
            <div style="width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top: 3px solid #111111; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
            <p style="color: #6b7280; margin: 0;">Loading cover letter content...</p>
        </div>
    `;
    
    coverLetterModal.style.display = 'block';
    
    // Load cover letter content via AJAX
    fetch(`cover-letter-preview.php?path=${encodeURIComponent(coverLetterPath)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            coverLetterContent.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading cover letter content:', error);
            coverLetterContent.innerHTML = `
                <div style="text-align: center; padding: 40px 20px; color: #ef4444;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
                    <h4 style="margin-bottom: 8px; color: #dc2626;">Error Loading Cover Letter</h4>
                    <p style="color: #6b7280; margin: 0;">Unable to load cover letter content. Please try again.</p>
                    <p style="color: #6b7280; margin: 8px 0 0 0; font-size: 12px;">Error: ${error.message}</p>
                    <div style="margin-top: 20px;">
                        <a href="../${coverLetterPath}" target="_blank" class="btn btn-primary" style="background: #ef4444; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
                            <i class="fas fa-download" style="margin-right: 6px;"></i>
                            Download Cover Letter Instead
                        </a>
                    </div>
                </div>
            `;
        });
}

function closeCoverLetterPreview() {
    const coverLetterModal = document.getElementById('coverLetterPreviewModal');
    coverLetterModal.style.opacity = '0';
    setTimeout(() => {
        coverLetterModal.style.display = 'none';
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }, 200);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const statusModal = document.getElementById('statusModal');
    const resumeModal = document.getElementById('resumePreviewModal');
    const coverLetterModal = document.getElementById('coverLetterPreviewModal');
    
    if (event.target === statusModal) {
        closeModal();
    } else if (event.target === resumeModal) {
        closeResumePreview();
    } else if (event.target === coverLetterModal) {
        closeCoverLetterPreview();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeResumePreview();
        closeCoverLetterPreview();
    }
});

function deleteApplicationFromDetails(applicationId, applicantName) {
    // Use the parent page's delete modal if available
    if (window.parent && window.parent.document.getElementById('deleteModal')) {
        window.parent.deleteApplication(applicationId, applicantName);
    } else if (window.parent && typeof window.parent.deleteApplication === 'function') {
        window.parent.deleteApplication(applicationId, applicantName);
    } else {
        // Fallback: confirm dialog and form submit
        xpmConfirm({
            title: 'Delete this application?',
            message: 'Delete the application from ' + applicantName + '? This action cannot be undone.',
            confirmText: 'Delete',
            icon: 'fas fa-trash'
        }).then(function (ok) {
            if (!ok) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'applications.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_application';
            form.appendChild(actionInput);
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'application_id';
            idInput.value = applicationId;
            form.appendChild(idInput);
            
            document.body.appendChild(form);
            form.submit();
        });
    }
}
</script>

<style>
    .application-details {
        padding: 1rem;
    }

    .interview-comment-section {
        margin-top: 1.5rem;
    }

    .interview-comment-section h4 i {
        color: #10b981 !important;
    }

    .interview-comment-section textarea:hover {
        border-color: #9ca3af;
    }
    
    .details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .detail-section {
        background: #f8fafc;
        border-radius: 8px;
        padding: 1.5rem;
    }
    
    .detail-section h4 {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        color: #1f2937;
        font-size: 1.1rem;
    }
    
    .detail-section h4 i {
        color: #111111;
    }
    
    .detail-content {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .label {
        font-weight: 600;
        color: #374151;
        min-width: 100px;
    }
    
    .value {
        color: #6b7280;
        text-align: right;
    }
    
    .value a {
        color: #111111;
        text-decoration: none;
    }
    
    .value a:hover {
        text-decoration: underline;
    }
    
    .cover-letter-content,
    .job-description-content {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 1rem;
        max-height: 200px;
        overflow-y: auto;
        line-height: 1.6;
        color: #374151;
    }
    
    .resume-actions,
    .cover-letter-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .file-info {
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .detail-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }
    
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    /* Pending - Orange */
    .status-pending {
        background: rgba(249, 115, 22, 0.1);
        color: #c2410c;
    }
    
    /* KIV - Yellow */
    .status-reviewed {
        background: rgba(234, 179, 8, 0.1);
        color: #a16207;
    }
    
    /* Shortlisted - Purple */
    .status-shortlisted {
        background: rgba(17, 17, 17, 0.1);
        color: #111111;
    }
    
    /* Rejected - Red */
    .status-rejected {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }
    
    /* Blacklisted - Black */
    .status-blacklisted {
        background: rgba(31, 41, 55, 0.1);
        color: #111827;
    }

    /* Hired - Green */
    .status-hired {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #15803d;
        border: 1px solid #22c55e;
    }

    /* Interview - Blue */
    .status-interview,
    .status-interviewed,
    .status-waiting-for-interview,
    .status-waiting_for_interview {
        background: rgba(17, 17, 17, 0.1);
        color: #111111;
    }
    
    .text-muted {
        color: #9ca3af;
    }
    
    @media (max-width: 768px) {
        .details-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .detail-actions {
            flex-direction: column;
        }
    }
    
    /* Modal animations */
    .modal {
        transition: opacity 0.3s ease;
    }
    
    .modal-content {
        transition: transform 0.3s ease;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
