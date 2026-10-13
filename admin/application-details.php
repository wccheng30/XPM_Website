<?php
session_start();

require_once '../includes/auth.php';
require_once '../includes/blacklist-lookup.php';
auth_require(['admin', 'manager'], '403');
csrf_guard();
require_once __DIR__ . '/includes/assets.php';
admin_icons_begin();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $application_id = $_POST['application_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    
    if ($application_id && $new_status) {
        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $previous = $pdo->prepare("SELECT status FROM applications WHERE id = ?");
                $previous->execute([$application_id]);
                $previous_status = (string)$previous->fetchColumn();
                $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $application_id]);
                blacklist_sync_status($pdo, 'application', (int)$application_id, $previous_status, (string)$new_status);
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
require_once '../includes/blacklist-lookup.php';
require_once __DIR__ . '/includes/ui.php';

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

$bl_info = $pdo ? blacklist_match(load_blacklist_lookup($pdo), $application['name'], $application['phone'] ?? '') : null;
$is_blacklisted = ($application['status'] === 'blacklisted') || $bl_info !== null;
?>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle is-success" aria-hidden="true"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle is-danger" aria-hidden="true"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php
$app_steps = [
    'pending' => 'Pending',
    'reviewed' => 'Reviewed',
    'shortlisted' => 'Shortlisted',
    'waiting for interview' => 'Waiting for interview',
    'hired' => 'Hired',
];
$app_step_keys = array_keys($app_steps);
$app_step_index = array_search($application['status'], $app_step_keys, true);
$can_delete_application = (($_SESSION['admin_role'] ?? 'admin') !== 'manager');
$js_name = json_encode((string) $application['name']);
$js_phone = json_encode((string) ($application['phone'] ?? ''));
$js_job = json_encode((string) ($application['job_title'] ?? ''));
?>
<div class="detail-page application-details">
    <header class="detail-head">
        <div class="detail-head-copy">
            <a class="detail-back" href="applications.php" onclick="var m=document.getElementById('detailsModal'); if(m && m.style.display==='block'){ m.style.display='none'; document.body.style.overflow='auto'; return false; }"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to applications</a>
            <h2 class="detail-title"><?php echo htmlspecialchars($application['name']); ?></h2>
            <div class="detail-head-meta">
                <?php renderStatusBadge($application['status']); ?>
                <span class="meta"><?php echo htmlspecialchars($application['job_title'] ?? 'No position'); ?></span>
            </div>
        </div>
        <a class="btn btn-primary" href="export-candidate-pdf.php?id=<?php echo (int) $application['id']; ?>"><i class="fas fa-file-pdf" aria-hidden="true"></i> Export to PDF</a>
    </header>

    <ol class="pipeline" aria-label="Hiring pipeline">
        <?php foreach ($app_steps as $step_key => $step_label):
            $index = array_search($step_key, $app_step_keys, true);
            $step_class = '';
            if ($app_step_index !== false && $index < $app_step_index) {
                $step_class = ' is-complete';
            } elseif ($app_step_index !== false && $index === $app_step_index) {
                $step_class = ' is-current';
            }
        ?>
        <li class="<?php echo trim($step_class); ?>"<?php echo $step_class === ' is-current' ? ' aria-current="step"' : ''; ?>>
            <span class="pipeline-mark" aria-hidden="true"><?php echo $step_class === ' is-complete' ? '✓' : (int) $index + 1; ?></span>
            <?php echo htmlspecialchars($step_label); ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php if ($application['status'] === 'rejected'): ?>
        <p class="pipeline-end"><i class="fas fa-times" aria-hidden="true"></i> Rejected</p>
    <?php endif; ?>

    <?php if ($is_blacklisted): ?>
    <div class="blacklist-banner" role="status">
        <i class="fas fa-ban" aria-hidden="true"></i>
        <div>
            <strong>This candidate is blacklisted</strong>
            <?php if (!empty($bl_info['reason'])): ?>
            <p><?php echo htmlspecialchars($bl_info['reason']); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="detail-layout">
        <div class="detail-main">
            <section class="detail-card">
                <h3>Personal details</h3>
                <div class="kv-list">
                    <div class="kv-row"><span class="kv-label">Name</span><span class="kv-value"><?php echo htmlspecialchars($application['name']); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Email</span><span class="kv-value"><a href="mailto:<?php echo htmlspecialchars($application['email']); ?>"><?php echo htmlspecialchars($application['email']); ?></a></span></div>
                    <div class="kv-row"><span class="kv-label">Phone</span><span class="kv-value"><?php echo $application['phone'] ? '<a href="tel:' . htmlspecialchars($application['phone']) . '">' . htmlspecialchars($application['phone']) . '</a>' : 'Not provided'; ?></span></div>
                    <div class="kv-row"><span class="kv-label">IC number</span><span class="kv-value"><?php echo htmlspecialchars($application['ic_number'] ?: 'Not provided'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Applied</span><span class="kv-value"><?php echo date('F j, Y \a\t g:i A', strtotime($application['created_at'])); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Application type</span><span class="kv-value"><?php echo $application['application_type'] === 'with_resume' ? 'With Resume' : 'Detailed Application'; ?></span></div>
                </div>
            </section>

            <?php if ($application['application_type'] === 'without_resume'): ?>
            <section class="detail-card">
                <h3>Experience and education</h3>
                <div class="kv-list">
                    <?php if ($application['education_level']): ?>
                    <div class="kv-row"><span class="kv-label">Education</span><span class="kv-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $application['education_level']))); ?></span></div>
                    <?php endif; ?>
                    <?php if ($application['education_details']): ?>
                    <div class="kv-row"><span class="kv-label">Details</span><span class="kv-value"><?php echo nl2br(htmlspecialchars($application['education_details'])); ?></span></div>
                    <?php endif; ?>
                    <?php if ($application['technical_skills']): ?>
                    <div class="kv-row"><span class="kv-label">Technical skills</span><span class="kv-value"><?php echo nl2br(htmlspecialchars($application['technical_skills'])); ?></span></div>
                    <?php endif; ?>
                    <?php if ($application['soft_skills']): ?>
                    <div class="kv-row"><span class="kv-label">Soft skills</span><span class="kv-value"><?php echo nl2br(htmlspecialchars($application['soft_skills'])); ?></span></div>
                    <?php endif; ?>
                    <?php if ($application['certifications']): ?>
                    <div class="kv-row"><span class="kv-label">Certifications</span><span class="kv-value"><?php echo nl2br(htmlspecialchars($application['certifications'])); ?></span></div>
                    <?php endif; ?>
                    <?php if ($application['languages']): ?>
                    <div class="kv-row"><span class="kv-label">Languages</span><span class="kv-value"><?php echo nl2br(htmlspecialchars($application['languages'])); ?></span></div>
                    <?php endif; ?>
                </div>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <?php if ($application["employment{$i}_company"]): ?>
                    <h4 class="name-line">Employment <?php echo $i; ?></h4>
                    <div class="kv-list">
                        <div class="kv-row"><span class="kv-label">Company</span><span class="kv-value"><?php echo htmlspecialchars($application["employment{$i}_company"]); ?></span></div>
                        <div class="kv-row"><span class="kv-label">Position</span><span class="kv-value"><?php echo htmlspecialchars($application["employment{$i}_position"] ?? 'N/A'); ?></span></div>
                        <div class="kv-row"><span class="kv-label">Duration</span><span class="kv-value"><?php echo htmlspecialchars($application["employment{$i}_duration"] ?? 'N/A'); ?></span></div>
                        <div class="kv-row"><span class="kv-label">Description</span><span class="kv-value"><?php echo nl2br(htmlspecialchars($application["employment{$i}_description"] ?? 'N/A')); ?></span></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($application["reference{$i}_name"]): ?>
                    <h4 class="name-line">Reference <?php echo $i; ?></h4>
                    <div class="kv-list">
                        <div class="kv-row"><span class="kv-label">Name</span><span class="kv-value"><?php echo htmlspecialchars($application["reference{$i}_name"]); ?></span></div>
                        <div class="kv-row"><span class="kv-label">Position</span><span class="kv-value"><?php echo htmlspecialchars($application["reference{$i}_position"] ?? 'N/A'); ?></span></div>
                        <div class="kv-row"><span class="kv-label">Company</span><span class="kv-value"><?php echo htmlspecialchars($application["reference{$i}_company"] ?? 'N/A'); ?></span></div>
                        <div class="kv-row"><span class="kv-label">Phone</span><span class="kv-value"><?php echo $application["reference{$i}_phone"] ? '<a href="tel:' . htmlspecialchars($application["reference{$i}_phone"]) . '">' . htmlspecialchars($application["reference{$i}_phone"]) . '</a>' : 'N/A'; ?></span></div>
                        <div class="kv-row"><span class="kv-label">Email</span><span class="kv-value"><?php echo $application["reference{$i}_email"] ? '<a href="mailto:' . htmlspecialchars($application["reference{$i}_email"]) . '">' . htmlspecialchars($application["reference{$i}_email"]) . '</a>' : 'N/A'; ?></span></div>
                    </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </section>
            <?php endif; ?>

            <section class="detail-card">
                <h3>Job info</h3>
                <div class="kv-list">
                    <div class="kv-row"><span class="kv-label">Position</span><span class="kv-value"><?php echo htmlspecialchars($application['job_title'] ?? 'N/A'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Company</span><span class="kv-value"><?php echo htmlspecialchars($application['company_name'] ?? 'N/A'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Location</span><span class="kv-value"><?php echo htmlspecialchars($application['location'] ?? 'N/A'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Type</span><span class="kv-value"><?php echo htmlspecialchars($application['job_type'] ?? 'N/A'); ?></span></div>
                </div>
                <?php if ($application['job_description']): ?>
                    <p class="detail-prose"><?php echo nl2br(htmlspecialchars($application['job_description'])); ?></p>
                <?php endif; ?>
            </section>

            <?php if ($application['expected_salary'] || $application['availability_date'] || $application['additional_notes']): ?>
            <section class="detail-card">
                <h3>Answers</h3>
                <div class="kv-list">
                    <?php if ($application['expected_salary']): ?>
                    <div class="kv-row"><span class="kv-label">Expected salary</span><span class="kv-value"><?php echo htmlspecialchars($application['expected_salary']); ?></span></div>
                    <?php endif; ?>
                    <?php $available_on = strtotime((string) ($application['availability_date'] ?? '')); ?>
                    <?php if ($available_on > 0): ?>
                    <div class="kv-row"><span class="kv-label">Available</span><span class="kv-value"><?php echo date('F j, Y', $available_on); ?></span></div>
                    <?php endif; ?>
                </div>
                <?php if ($application['additional_notes']): ?>
                    <p class="detail-prose"><?php echo nl2br(htmlspecialchars($application['additional_notes'])); ?></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="detail-card">
                <h3>Documents</h3>
                <?php if ($application['resume_path'] || $application['cover_letter_path']): ?>
                <div class="doc-switch">
                    <?php if ($application['resume_path']): ?>
                    <input type="radio" name="applicationDocs" id="docResume" checked>
                    <label for="docResume">Resume</label>
                    <?php endif; ?>
                    <?php if ($application['cover_letter_path']): ?>
                    <input type="radio" name="applicationDocs" id="docCover" <?php echo $application['resume_path'] ? '' : 'checked'; ?>>
                    <label for="docCover">Cover letter</label>
                    <?php endif; ?>
                    <div class="doc-panels">
                        <?php if ($application['resume_path']):
                            $resume_ext = strtolower(pathinfo($application['resume_path'], PATHINFO_EXTENSION));
                            $resume_src = $resume_ext === 'pdf' ? '../' . $application['resume_path'] : 'resume-preview.php?path=' . rawurlencode($application['resume_path']);
                        ?>
                        <div class="doc-panel">
                            <iframe class="doc-frame" title="Resume preview" src="<?php echo htmlspecialchars($resume_src); ?>"></iframe>
                            <div class="doc-links">
                                <a class="btn btn-secondary btn-sm" href="resume-preview.php?path=<?php echo rawurlencode($application['resume_path']); ?>">Open preview page</a>
                                <a class="btn btn-secondary btn-sm" href="../<?php echo htmlspecialchars($application['resume_path']); ?>" target="_blank">Download</a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($application['cover_letter_path']):
                            $cover_ext = strtolower(pathinfo($application['cover_letter_path'], PATHINFO_EXTENSION));
                            $cover_src = $cover_ext === 'pdf' ? '../' . $application['cover_letter_path'] : 'cover-letter-preview.php?path=' . rawurlencode($application['cover_letter_path']);
                        ?>
                        <div class="doc-panel">
                            <iframe class="doc-frame" title="Cover letter preview" src="<?php echo htmlspecialchars($cover_src); ?>"></iframe>
                            <div class="doc-links">
                                <a class="btn btn-secondary btn-sm" href="cover-letter-preview.php?path=<?php echo rawurlencode($application['cover_letter_path']); ?>">Open preview page</a>
                                <a class="btn btn-secondary btn-sm" href="../<?php echo htmlspecialchars($application['cover_letter_path']); ?>" target="_blank">Download</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                    <p class="detail-prose">No resume or cover letter was uploaded.</p>
                <?php endif; ?>
            </section>
        </div>

        <aside class="detail-side">
            <section class="detail-card">
                <h3>Current status</h3>
                <?php renderStatusBadge($application['status']); ?>
                <form method="POST" class="detail-status-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                    <label for="detailStatus">Change status</label>
                    <select name="status" id="detailStatus">
                        <option value="pending" <?php echo $application['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="reviewed" <?php echo $application['status'] === 'reviewed' ? 'selected' : ''; ?>>KIV</option>
                        <option value="shortlisted" <?php echo $application['status'] === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                        <option value="rejected" <?php echo $application['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="blacklisted" <?php echo $application['status'] === 'blacklisted' ? 'selected' : ''; ?>>Blacklisted</option>
                        <option value="waiting for interview" <?php echo $application['status'] === 'waiting for interview' ? 'selected' : ''; ?>> Interview </option>
                        <option value="hired" <?php echo $application['status'] === 'hired' ? 'selected' : ''; ?>> Hired </option>
                    </select>
                    <button type="submit" class="btn btn-primary">Change status</button>
                </form>
            </section>

            <section class="detail-card">
                <h3>Quick actions</h3>
                <div class="detail-actions">
                    <a class="btn btn-secondary" href="mailto:<?php echo htmlspecialchars($application['email']); ?>"><i class="fas fa-envelope" aria-hidden="true"></i> Email</a>
                    <a class="btn btn-secondary" href="export-candidate-pdf.php?id=<?php echo (int) $application['id']; ?>"><i class="fas fa-download" aria-hidden="true"></i> Download PDF</a>
                    <a class="btn btn-secondary" href="export-applications.php?format=csv&amp;search=<?php echo rawurlencode($application['name']); ?>"><i class="fas fa-file-export" aria-hidden="true"></i> Export</a>
                    <?php if (!$is_blacklisted): ?>
                    <button type="button" class="btn btn-secondary" onclick="if (window.blacklistCandidate) { blacklistCandidate(<?php echo (int) $application['id']; ?>, <?php echo htmlspecialchars($js_name, ENT_QUOTES); ?>, <?php echo htmlspecialchars($js_phone, ENT_QUOTES); ?>, <?php echo htmlspecialchars($js_job, ENT_QUOTES); ?>); }"><i class="fas fa-user-slash" aria-hidden="true"></i> Add to blacklist</button>
                    <?php endif; ?>
                    <?php if ($can_delete_application): ?>
                    <button type="button" class="btn btn-danger" onclick="if (window.deleteApplication) { deleteApplication(<?php echo (int) $application['id']; ?>, <?php echo htmlspecialchars($js_name, ENT_QUOTES); ?>); } else if (window.deleteApplicationFromDetails) { deleteApplicationFromDetails(<?php echo (int) $application['id']; ?>, <?php echo htmlspecialchars($js_name, ENT_QUOTES); ?>); }"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>
                    <?php endif; ?>
                </div>
            </section>

            <section class="detail-card">
                <h3>Comments</h3>
                <ol class="detail-timeline">
                    <li>
                        <div>
                            <strong>Applied</strong>
                            <time datetime="<?php echo htmlspecialchars($application['created_at']); ?>"><?php echo date('M j, Y', strtotime($application['created_at'])); ?></time>
                        </div>
                    </li>
                    <?php if (!empty($application['interviewer_comment'])): ?>
                    <li>
                        <div>
                            <strong>Comment</strong>
                            <p><?php echo nl2br(htmlspecialchars($application['interviewer_comment'])); ?></p>
                        </div>
                    </li>
                    <?php endif; ?>
                </ol>
                <form method="POST" id="interviewCommentForm" class="detail-comment-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_interview_comment">
                    <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                    <label for="interviewCommentTextarea">Add a comment</label>
                    <textarea name="interviewer_comment" id="interviewCommentTextarea" class="form-control" rows="4" placeholder="Enter interview comments, notes, or feedback here..."><?php echo htmlspecialchars($application['interviewer_comment'] ?? ''); ?></textarea>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Save comment</button>
                </form>
            </section>
        </aside>
    </div>
</div>


    <!-- Status Update Modal -->
    <div id="statusModal" class="modal modal-overlay is-top">
    <div class="modal-content modal-dialog">
        <div class="modal-header modal-head">
            <h3 class="modal-title">
                <i class="fas fa-edit ink" aria-hidden="true"></i>
                Update Status
            </h3>
            <button class="modal-close" aria-label="Close" onclick="closeModal()">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <form method="POST" class="status-form modal-body">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="application_id" id="applicationId">
            <div class="form-group u-mb-5">
                <label class="field-label" for="status">Application Status</label>
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
            <div class="form-actions cluster">
                <button type="button" class="btn btn-secondary grow" onclick="closeModal()">
                    Cancel
                </button>
                <button type="submit" class="btn btn-ink grow">
                    Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resume Preview Modal -->
<div id="resumePreviewModal" class="modal modal-overlay">
    <div class="modal-content modal-dialog modal-lg">
        <div class="modal-header modal-head">
            <h3 class="modal-title">
                <i class="fas fa-file-pdf ico-muted is-danger" aria-hidden="true"></i>
                Resume Preview - <span id="resumeCandidateName"></span>
            </h3>
            <button class="modal-close" aria-label="Close" onclick="closeResumePreview()">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body-scroll" id="resumePreviewContent">
            <!-- Resume content will be loaded here -->
        </div>
    </div>
</div>

<!-- Cover Letter Preview Modal -->
<div id="coverLetterPreviewModal" class="modal modal-overlay">
    <div class="modal-content modal-dialog modal-lg">
        <div class="modal-header modal-head">
            <h3 class="modal-title">
                <i class="fas fa-file-alt ico-muted ink" aria-hidden="true"></i>
                Cover Letter Preview - <span id="coverLetterCandidateName"></span>
            </h3>
            <button class="modal-close" aria-label="Close" onclick="closeCoverLetterPreview()">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body-scroll" id="coverLetterPreviewContent">
            <!-- Cover letter content will be loaded here -->
        </div>
    </div>
</div>

<script defer src="<?php echo admin_asset('../assets/js/xpm-dialog.js'); ?>"></script>
<script>
window.XPM_CSRF = window.XPM_CSRF || <?php echo json_encode(auth_csrf_token()); ?>;
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
        <div class="empty-state is-compact">
            <div class="spinner-ring"></div>
            <p class="page-sub">Loading resume content...</p>
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
                <div class="empty-state is-compact is-error">
                    <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
                    <h4 class="mb-2">Error Loading Resume</h4>
                    <p class="page-sub">Unable to load resume content. Please try again.</p>
                    <p class="fine-note">Error: ${error.message}</p>
                    <div class="u-mt-5">
                        <a href="../${resumePath}" target="_blank" class="btn btn-danger">
                            <i class="fas fa-download ico-md" aria-hidden="true"></i>
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
        <div class="empty-state is-compact">
            <div class="spinner-ring"></div>
            <p class="page-sub">Loading cover letter content...</p>
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
                <div class="empty-state is-compact is-error">
                    <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
                    <h4 class="mb-2">Error Loading Cover Letter</h4>
                    <p class="page-sub">Unable to load cover letter content. Please try again.</p>
                    <p class="fine-note">Error: ${error.message}</p>
                    <div class="u-mt-5">
                        <a href="../${coverLetterPath}" target="_blank" class="btn btn-danger">
                            <i class="fas fa-download ico-md" aria-hidden="true"></i>
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
            if (window.XPM_CSRF) {
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = 'csrf_token';
                csrf.value = window.XPM_CSRF;
                form.appendChild(csrf);
            }
            
            document.body.appendChild(form);
            form.submit();
        });
    }
}
</script>

