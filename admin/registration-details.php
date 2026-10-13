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
    $registration_id = $_POST['registration_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    
    if ($registration_id && $new_status) {
        if (!in_array((string) $new_status, registration_status_values(), true)) {
            $error_message = "Choose a valid status.";
        } else {
            $pdo = get_db_connection();
            if ($pdo) {
                try {
                    $previous = $pdo->prepare("SELECT status FROM programme_registrations WHERE id = ?");
                    $previous->execute([$registration_id]);
                    $previous_status = (string)$previous->fetchColumn();
                    $stmt = $pdo->prepare("UPDATE programme_registrations SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $registration_id]);
                    blacklist_sync_status($pdo, 'registration', (int)$registration_id, $previous_status, (string)$new_status);
                    $success_message = "Registration status updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating registration status.";
                }
            } else {
                $error_message = "Database connection failed.";
            }
        }
    }
}

// Include database connection
require_once '../includes/database.php';
require_once __DIR__ . '/includes/ui.php';

// Helper function to safely display prices
function formatPrice($price) {
    if (is_numeric($price)) {
        if ((float)$price == 0) {
            return 'Free';
        }
        return 'RM ' . number_format($price, 0);
    } else {
        return htmlspecialchars($price);
    }
}

$pdo = get_db_connection();
$registration = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $registration_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, p.title as programme_title, p.duration, p.price, p.description as programme_description, p.location, p.certification
            FROM programme_registrations pr 
            LEFT JOIN programmes p ON pr.programme_id = p.id 
            WHERE pr.id = ?
        ");
        $stmt->execute([$registration_id]);
        $registration = $stmt->fetch();
    } catch (PDOException $e) {
        echo "Error loading registration details.";
        exit;
    }
}

if (!$registration) {
    echo "Registration not found.";
    exit;
}

$bl_info = $pdo ? blacklist_match(load_blacklist_lookup($pdo), $registration['name'], $registration['phone'] ?? '') : null;
$is_blacklisted = ($registration['status'] === 'blacklisted');
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

<div class="detail-page registration-details">
    <header class="detail-head">
        <div class="detail-head-copy">
            <a class="detail-back" href="registrations.php" onclick="var m=document.getElementById('detailsModal'); if(m && m.style.display==='block'){ m.style.display='none'; document.body.style.overflow='auto'; return false; }"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to registrations</a>
            <h2 class="detail-title"><?php echo htmlspecialchars($registration['name']); ?></h2>
            <div class="detail-head-meta">
                <?php renderStatusBadge($registration['status']); ?>
                <span class="meta"><?php echo htmlspecialchars($registration['programme_title'] ?? 'No programme'); ?></span>
            </div>
        </div>
        <?php if ($registration['programme_id']): ?>
        <a class="btn btn-primary" href="programmes.php#programme-<?php echo (int) $registration['programme_id']; ?>"><i class="fas fa-book-open" aria-hidden="true"></i> View programme</a>
        <?php endif; ?>
    </header>

    <?php if ($is_blacklisted): ?>
    <div class="blacklist-banner" role="status">
        <i class="fas fa-ban" aria-hidden="true"></i>
        <div>
            <strong>This participant is blacklisted</strong>
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
                    <div class="kv-row"><span class="kv-label">Name</span><span class="kv-value"><?php echo htmlspecialchars($registration['name']); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Email</span><span class="kv-value"><a href="mailto:<?php echo htmlspecialchars($registration['email']); ?>"><?php echo htmlspecialchars($registration['email']); ?></a></span></div>
                    <div class="kv-row"><span class="kv-label">Phone</span><span class="kv-value"><?php echo $registration['phone'] ? '<a href="tel:' . htmlspecialchars($registration['phone']) . '">' . htmlspecialchars($registration['phone']) . '</a>' : 'Not provided'; ?></span></div>
                    <div class="kv-row"><span class="kv-label">IC number</span><span class="kv-value"><?php echo htmlspecialchars($registration['ic_number'] ?: 'Not provided'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Company</span><span class="kv-value"><?php echo htmlspecialchars($registration['company'] ?? 'Not provided'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Position</span><span class="kv-value"><?php echo htmlspecialchars($registration['position'] ?? 'Not provided'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Registered</span><span class="kv-value"><?php echo date('F j, Y \a\t g:i A', strtotime($registration['created_at'])); ?></span></div>
                </div>
            </section>

            <section class="detail-card">
                <h3>Programme info</h3>
                <div class="kv-list">
                    <div class="kv-row"><span class="kv-label">Programme</span><span class="kv-value"><?php echo htmlspecialchars($registration['programme_title'] ?? 'N/A'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Duration</span><span class="kv-value"><?php echo htmlspecialchars($registration['duration'] ?? 'N/A'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Price</span><span class="kv-value"><?php echo formatPrice($registration['price'] ?? 0); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Location</span><span class="kv-value"><?php echo htmlspecialchars($registration['location'] ?? 'N/A'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Certification</span><span class="kv-value"><?php echo htmlspecialchars($registration['certification'] ?? 'N/A'); ?></span></div>
                </div>
                <?php if ($registration['programme_description']): ?>
                    <p class="detail-prose"><?php echo nl2br(htmlspecialchars($registration['programme_description'])); ?></p>
                <?php endif; ?>
            </section>

            <section class="detail-card">
                <h3>Answers</h3>
                <div class="kv-list">
                    <div class="kv-row"><span class="kv-label">Payment</span><span class="kv-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $registration['payment_method'] ?? 'Not provided'))); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Billing address</span><span class="kv-value"><?php echo htmlspecialchars($registration['billing_address'] ?? 'Not provided'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Billing contact</span><span class="kv-value"><?php echo htmlspecialchars($registration['billing_contact_no'] ?? 'Not provided'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Vehicle</span><span class="kv-value"><?php
                        if ($registration['vehicle_type']) {
                            echo htmlspecialchars($registration['vehicle_type'] === 'other' ? ($registration['vehicle_type_other'] ?? 'Other') : ucfirst($registration['vehicle_type']));
                        } else {
                            echo 'Not provided';
                        }
                    ?></span></div>
                    <div class="kv-row"><span class="kv-label">Plate</span><span class="kv-value"><?php echo htmlspecialchars($registration['plate_number'] ?? 'Not provided'); ?></span></div>
                    <div class="kv-row"><span class="kv-label">Dietary</span><span class="kv-value"><?php
                        if ($registration['dietary_preference'] && $registration['dietary_preference'] !== 'none') {
                            $dietary = ucfirst($registration['dietary_preference']);
                            if ($registration['dietary_preference'] === 'other' && $registration['dietary_restrictions']) {
                                $dietary .= ': ' . $registration['dietary_restrictions'];
                            }
                            echo htmlspecialchars($dietary);
                        } else {
                            echo 'None';
                        }
                    ?></span></div>
                    <div class="kv-row"><span class="kv-label">Allergies</span><span class="kv-value"><?php
                        if ($registration['allergies'] && $registration['allergies'] !== 'none') {
                            $allergies = ucfirst($registration['allergies']);
                            if ($registration['allergies'] === 'other' && $registration['allergies_other']) {
                                $allergies .= ': ' . $registration['allergies_other'];
                            }
                            echo htmlspecialchars($allergies);
                        } else {
                            echo 'None';
                        }
                    ?></span></div>
                    <div class="kv-row"><span class="kv-label">Spicy preference</span><span class="kv-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $registration['spicy_preference'] ?? 'Not specified'))); ?></span></div>
                    <div class="kv-row"><span class="kv-label">How they heard</span><span class="kv-value"><?php
                        if ($registration['how_did_you_hear']) {
                            $source = ucfirst(str_replace('_', ' ', $registration['how_did_you_hear']));
                            if ($registration['how_did_you_hear'] === 'other' && $registration['how_did_you_hear_other']) {
                                $source .= ': ' . $registration['how_did_you_hear_other'];
                            }
                            echo htmlspecialchars($source);
                        } else {
                            echo 'Not specified';
                        }
                    ?></span></div>
                    <div class="kv-row"><span class="kv-label">Interested programmes</span><span class="kv-value"><?php
                        if ($registration['interested_programs']) {
                            $programs = ucfirst(str_replace('_', ' ', $registration['interested_programs']));
                            if ($registration['interested_programs'] === 'other' && $registration['interested_programs_other']) {
                                $programs .= ': ' . $registration['interested_programs_other'];
                            }
                            echo htmlspecialchars($programs);
                        } else {
                            echo 'Not specified';
                        }
                    ?></span></div>
                </div>
                <?php if ($registration['additional_notes']): ?>
                    <p class="detail-prose"><?php echo nl2br(htmlspecialchars($registration['additional_notes'])); ?></p>
                <?php endif; ?>
            </section>
        </div>

        <aside class="detail-side">
            <section class="detail-card">
                <h3>Current status</h3>
                <?php renderStatusBadge($registration['status']); ?>
                <form method="POST" class="detail-status-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="registration_id" value="<?php echo (int) $registration['id']; ?>">
                    <label for="detailRegStatus">Change status</label>
                    <select name="status" id="detailRegStatus">
                        <option value="pending" <?php echo $registration['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $registration['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="waitlisted" <?php echo $registration['status'] === 'waitlisted' ? 'selected' : ''; ?>>Waitlisted</option>
                        <option value="cancelled" <?php echo $registration['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="blacklisted" <?php echo $registration['status'] === 'blacklisted' ? 'selected' : ''; ?>>Blacklisted</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Change status</button>
                </form>
            </section>
            <section class="detail-card">
                <h3>Quick actions</h3>
                <div class="detail-actions">
                    <a class="btn btn-secondary" href="mailto:<?php echo htmlspecialchars($registration['email']); ?>"><i class="fas fa-envelope" aria-hidden="true"></i> Email</a>
                    <a class="btn btn-secondary" href="export-registrations.php?format=csv&amp;search=<?php echo rawurlencode($registration['name']); ?>"><i class="fas fa-file-export" aria-hidden="true"></i> Export</a>
                    <?php if ($registration['programme_id']): ?>
                    <a class="btn btn-secondary" href="programmes.php#programme-<?php echo (int) $registration['programme_id']; ?>">View programme</a>
                    <?php endif; ?>
                </div>
            </section>
            <section class="detail-card">
                <h3>Activity</h3>
                <ol class="detail-timeline">
                    <li>
                        <div>
                            <strong>Registered</strong>
                            <time datetime="<?php echo htmlspecialchars($registration['created_at']); ?>"><?php echo date('M j, Y', strtotime($registration['created_at'])); ?></time>
                            <p>Status: <?php echo htmlspecialchars(ucfirst($registration['status'])); ?></p>
                        </div>
                    </li>
                </ol>
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
            <input type="hidden" name="registration_id" id="registrationId">
            <div class="form-group u-mb-5">
                <label class="field-label" for="status">Registration Status</label>
                <select name="status" id="statusSelect" class="form-control" required>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="waitlisted">Waitlisted</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="blacklisted">Blacklisted</option>
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

<script>
function updateStatus(registrationId, currentStatus) {
    document.getElementById('registrationId').value = registrationId;
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

// Close modal when clicking outside
window.onclick = function(event) {
    const statusModal = document.getElementById('statusModal');
    if (event.target === statusModal) {
        closeModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

