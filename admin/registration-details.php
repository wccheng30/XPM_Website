<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $registration_id = $_POST['registration_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    
    if ($registration_id && $new_status) {
        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE programme_registrations SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $registration_id]);
                $success_message = "Registration status updated successfully!";
            } catch (PDOException $e) {
                $error_message = "Error updating registration status.";
            }
        } else {
            $error_message = "Database connection failed.";
        }
    }
}

// Include database connection
require_once '../includes/database.php';

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

<div class="registration-details" style="padding: 0;">
    <div class="details-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- Participant Information -->
        <div class="detail-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-user-graduate" style="color: #3b82f6;"></i> Participant Information</h4>
            <div class="detail-content">
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Name:</span>
                    <span class="value" style="color: #1f2937; font-weight: 500;"><?php echo htmlspecialchars($registration['name']); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Email:</span>
                    <span class="value" style="color: #1f2937;">
                        <a href="mailto:<?php echo htmlspecialchars($registration['email']); ?>" style="color: #3b82f6; text-decoration: none;"><?php echo htmlspecialchars($registration['email']); ?></a>
                    </span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Phone:</span>
                    <span class="value" style="color: #1f2937;">
                        <?php if ($registration['phone']): ?>
                            <a href="tel:<?php echo htmlspecialchars($registration['phone']); ?>" style="color: #3b82f6; text-decoration: none;"><?php echo htmlspecialchars($registration['phone']); ?></a>
                        <?php else: ?>
                            <span style="color: #9ca3af;">Not provided</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">IC Number:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($registration['ic_number'] ?? ''); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Company:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($registration['company'] ?? 'Not provided'); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Position:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($registration['position'] ?? 'Not provided'); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Registered:</span>
                    <span class="value" style="color: #1f2937;"><?php echo date('F j, Y \a\t g:i A', strtotime($registration['created_at'])); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Status:</span>
                    <span class="value">
                        <span class="status-badge status-<?php echo $registration['status']; ?>" style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?php echo ucfirst($registration['status']); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Billing Information -->
        <div class="detail-section" style="background: #fefce8; border: 1px solid #f59e0b; border-radius: 12px; padding: 20px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-credit-card" style="color: #f59e0b;"></i> Billing Information</h4>
            <div class="detail-content" style="display: flex; flex-direction: column; gap: 12px;">
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Payment Method:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $registration['payment_method'] ?? 'Not provided'))); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: flex-start; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Billing Address:</span>
                    <span class="value" style="color: #1f2937; text-align: right; max-width: 300px;"><?php echo htmlspecialchars($registration['billing_address'] ?? 'Not provided'); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Billing Contact:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($registration['billing_contact_no'] ?? 'Not provided'); ?></span>
                </div>
            </div>
        </div>

        <!-- Vehicle Information -->
        <div class="detail-section" style="background: #f0fdf4; border: 1px solid #22c55e; border-radius: 12px; padding: 20px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-car" style="color: #22c55e;"></i> Vehicle Information</h4>
            <div class="detail-content" style="display: flex; flex-direction: column; gap: 12px;">
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Vehicle Type:</span>
                    <span class="value" style="color: #1f2937;">
                        <?php 
                        if ($registration['vehicle_type']) {
                            if ($registration['vehicle_type'] === 'other') {
                                echo htmlspecialchars($registration['vehicle_type_other'] ?? 'Other');
                            } else {
                                echo htmlspecialchars(ucfirst($registration['vehicle_type']));
                            }
                        } else {
                            echo 'Not provided';
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Plate Number:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($registration['plate_number'] ?? 'Not provided'); ?></span>
                </div>
            </div>
        </div>

        <!-- Dietary & Preferences -->
        <div class="detail-section" style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 12px; padding: 20px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-utensils" style="color: #f59e0b;"></i> Dietary & Preferences</h4>
            <div class="detail-content" style="display: flex; flex-direction: column; gap: 12px;">
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Dietary Preference:</span>
                    <span class="value" style="color: #1f2937;">
                        <?php 
                        if ($registration['dietary_preference'] && $registration['dietary_preference'] !== 'none') {
                            $dietary = ucfirst($registration['dietary_preference']);
                            if ($registration['dietary_preference'] === 'other' && $registration['dietary_restrictions']) {
                                $dietary .= ': ' . $registration['dietary_restrictions'];
                            }
                            echo htmlspecialchars($dietary);
                        } else {
                            echo 'None';
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Allergies:</span>
                    <span class="value" style="color: #1f2937;">
                        <?php 
                        if ($registration['allergies'] && $registration['allergies'] !== 'none') {
                            $allergies = ucfirst($registration['allergies']);
                            if ($registration['allergies'] === 'other' && $registration['allergies_other']) {
                                $allergies .= ': ' . $registration['allergies_other'];
                            }
                            echo htmlspecialchars($allergies);
                        } else {
                            echo 'None';
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Spicy Preference:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $registration['spicy_preference'] ?? 'Not specified'))); ?></span>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="detail-section" style="background: #f3e8ff; border: 1px solid #a855f7; border-radius: 12px; padding: 20px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-info-circle" style="color: #a855f7;"></i> Additional Information</h4>
            <div class="detail-content" style="display: flex; flex-direction: column; gap: 12px;">
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">How did you hear:</span>
                    <span class="value" style="color: #1f2937;">
                        <?php 
                        if ($registration['how_did_you_hear']) {
                            $source = ucfirst(str_replace('_', ' ', $registration['how_did_you_hear']));
                            if ($registration['how_did_you_hear'] === 'other' && $registration['how_did_you_hear_other']) {
                                $source .= ': ' . $registration['how_did_you_hear_other'];
                            }
                            echo htmlspecialchars($source);
                        } else {
                            echo 'Not specified';
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 120px;">Interested Programs:</span>
                    <span class="value" style="color: #1f2937;">
                        <?php 
                        if ($registration['interested_programs']) {
                            $programs = ucfirst(str_replace('_', ' ', $registration['interested_programs']));
                            if ($registration['interested_programs'] === 'other' && $registration['interested_programs_other']) {
                                $programs .= ': ' . $registration['interested_programs_other'];
                            }
                            echo htmlspecialchars($programs);
                        } else {
                            echo 'Not specified';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Programme Information -->
        <div class="detail-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-graduation-cap" style="color: #3b82f6;"></i> Programme Details</h4>
            <div class="detail-content" style="display: flex; flex-direction: column; gap: 12px;">
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Programme:</span>
                    <span class="value" style="color: #1f2937; font-weight: 500;"><?php echo htmlspecialchars($registration['programme_title'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Duration:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($registration['duration'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Price:</span>
                    <span class="value" style="color: #1f2937;"><?php echo formatPrice($registration['price'] ?? 0); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Location:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($registration['location'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                    <span class="label" style="font-weight: 500; color: #374151; min-width: 80px;">Certification:</span>
                    <span class="value" style="color: #1f2937;"><?php echo htmlspecialchars($registration['certification'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Notes -->
    <?php if ($registration['additional_notes']): ?>
        <div class="detail-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-file-alt" style="color: #3b82f6;"></i> Additional Notes</h4>
            <div class="notes-content" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; line-height: 1.6; color: #374151;">
                <?php echo nl2br(htmlspecialchars($registration['additional_notes'])); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Programme Description -->
    <?php if ($registration['programme_description']): ?>
        <div class="detail-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <h4 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-info-circle" style="color: #3b82f6;"></i> Programme Description</h4>
            <div class="description-content" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; line-height: 1.6; color: #374151;">
                <?php echo nl2br(htmlspecialchars($registration['programme_description'])); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="detail-actions" style="display: flex; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
        <button class="btn btn-primary" onclick="updateStatus(<?php echo $registration['id']; ?>, '<?php echo $registration['status']; ?>')" style="background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#2563eb'" onmouseout="this.style.backgroundColor='#3b82f6'">
            <i class="fas fa-edit"></i>
            Update Status
        </button>
        <?php if ($registration['programme_id']): ?>
            <a href="programmes.php#programme-<?php echo $registration['programme_id']; ?>" target="_blank" class="btn btn-info" style="background: #06b6d4; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#0891b2'" onmouseout="this.style.backgroundColor='#06b6d4'">
                <i class="fas fa-external-link-alt"></i>
                View Programme
            </a>
        <?php endif; ?>

    </div>
</div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal" style="display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-edit" style="color: #3b82f6;"></i>
                Update Status
            </h3>
            <button class="modal-close" onclick="closeModal()" style="background: none; border: none; font-size: 18px; color: #6b7280; cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="status-form" style="padding: 24px;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="registration_id" id="registrationId">
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="status" style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-size: 14px;">Registration Status</label>
                <select name="status" id="statusSelect" required style="width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #1f2937; background: white; cursor: pointer; transition: border-color 0.2s;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#d1d5db'">
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="waitlisted">Waitlisted</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="blacklisted">Blacklisted</option>
                </select>
            </div>
            <div class="form-actions" style="display: flex; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex: 1; background: #f3f4f6; color: #374151; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="flex: 1; background: #3b82f6; color: white; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#2563eb'" onmouseout="this.style.backgroundColor='#3b82f6'">
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

<style>
    .registration-details {
        padding: 1rem;
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
        color: #2563eb;
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
        color: #2563eb;
        text-decoration: none;
    }
    
    .value a:hover {
        text-decoration: underline;
    }
    
    .notes-content,
    .description-content {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 1rem;
        max-height: 200px;
        overflow-y: auto;
        line-height: 1.6;
        color: #374151;
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
    
    .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }
    
    .status-confirmed {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }
    
    .status-waitlisted {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }
    
    .status-cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }
    
    .status-blacklisted {
        background: rgba(31, 41, 55, 0.1);
        color: #111827;
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
</style>
