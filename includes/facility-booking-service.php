<?php
/**
 * Facility Booking Service
 * Handles integration between programmes and facility bookings
 */

class FacilityBookingService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check if the database schema has been updated for facility integration
     */
    private function checkDatabaseSchema() {
        try {
            // Check if the new columns exist in programmes table
            $stmt = $this->pdo->query("DESCRIBE programmes");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $required_columns = ['facility_type', 'requires_facility', 'facility_booking_id', 'auto_create_facility_booking'];
            $missing_columns = array_diff($required_columns, $columns);
            
            if (!empty($missing_columns)) {
                throw new Exception("Database schema not updated. Missing columns: " . implode(', ', $missing_columns) . 
                    ". Please run the installation script at: setup/install-facility-integration.php");
            }
        } catch (Exception $e) {
            throw new Exception("Database schema check failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create facility booking for a programme
     */
    public function createProgrammeFacilityBooking($programmeId) {
        try {
            // Check if the new columns exist
            $this->checkDatabaseSchema();
            
            // Get programme details
            $stmt = $this->pdo->prepare("
                SELECT id, title, start_date, end_date, start_time, end_time, 
                       facility_type, requires_facility, max_participants, facility_booking_id
                FROM programmes 
                WHERE id = ? AND requires_facility = TRUE
            ");
            $stmt->execute([$programmeId]);
            $programme = $stmt->fetch();
            
            if (!$programme) {
                throw new Exception("Programme not found or doesn't require facility");
            }
            
            // Check for existing facility booking
            if (!empty($programme['facility_booking_id'])) {
                throw new Exception("Facility booking already exists for this programme");
            }
            
            // Check for conflicts
            $conflicts = $this->checkFacilityConflicts(
                $programme['facility_type'],
                $programme['start_date'],
                $programme['start_time'],
                $programme['end_time']
            );
            
            if (!empty($conflicts)) {
                throw new Exception("Facility conflicts detected: " . implode(', ', $conflicts));
            }
            
            // Create facility booking
            $stmt = $this->pdo->prepare("
                INSERT INTO bookings 
                (customer_name, customer_email, customer_phone, company, facility_type, 
                 booking_date, start_time, end_time, attendees_count, purpose, 
                 special_requirements, status, programme_id, is_programme_booking) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, TRUE)
            ");
            
            $stmt->execute([
                'XpressPoint Training', // customer_name
                'admin@xpresspoint.com', // customer_email
                '+60123456789', // customer_phone
                'XpressPoint Training Center', // company
                $programme['facility_type'],
                $programme['start_date'],
                $programme['start_time'],
                $programme['end_time'],
                $programme['max_participants'] ?? 25,
                'Training Programme: ' . $programme['title'],
                'Automatically created for programme booking',
                $programmeId
            ]);
            
            $bookingId = $this->pdo->lastInsertId();
            
            // Update programme with facility booking ID
            $stmt = $this->pdo->prepare("
                UPDATE programmes 
                SET facility_booking_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$bookingId, $programmeId]);
            
            return $bookingId;
            
        } catch (Exception $e) {
            error_log("Facility booking creation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check for facility conflicts
     */
    public function checkFacilityConflicts($facilityType, $date, $startTime, $endTime) {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.customer_name, b.purpose, b.start_time, b.end_time
            FROM bookings b
            WHERE b.facility_type = ? 
            AND b.booking_date = ? 
            AND b.status IN ('pending', 'confirmed')
            AND (
                (b.start_time <= ? AND b.end_time > ?) OR
                (b.start_time < ? AND b.end_time >= ?) OR
                (b.start_time >= ? AND b.end_time <= ?)
            )
        ");
        
        $stmt->execute([
            $facilityType, $date,
            $startTime, $startTime,
            $endTime, $endTime,
            $startTime, $endTime
        ]);
        
        $conflicts = [];
        while ($row = $stmt->fetch()) {
            $conflicts[] = "Booking #{$row['id']} ({$row['customer_name']}) - {$row['start_time']} to {$row['end_time']}";
        }
        
        return $conflicts;
    }
    
    /**
     * Update facility booking for a programme
     */
    public function updateProgrammeFacilityBooking($programmeId, $newFacilityType = null, $newDate = null, $newStartTime = null, $newEndTime = null) {
        try {
            // Check if the new columns exist
            $this->checkDatabaseSchema();
            
            // Get current programme and booking details
            $stmt = $this->pdo->prepare("
                SELECT p.*, b.id as booking_id, b.facility_type as current_facility_type,
                       b.booking_date as current_date, b.start_time as current_start_time, 
                       b.end_time as current_end_time
                FROM programmes p
                LEFT JOIN bookings b ON p.facility_booking_id = b.id
                WHERE p.id = ?
            ");
            $stmt->execute([$programmeId]);
            $programme = $stmt->fetch();
            
            if (!$programme || !$programme['booking_id']) {
                throw new Exception("No facility booking found for this programme");
            }
            
            // Use current values if not provided
            $facilityType = $newFacilityType ?? $programme['facility_type'];
            $date = $newDate ?? $programme['current_date'];
            $startTime = $newStartTime ?? $programme['current_start_time'];
            $endTime = $newEndTime ?? $programme['current_end_time'];
            
            // Check for conflicts (excluding current booking)
            $stmt = $this->pdo->prepare("
                SELECT b.id, b.customer_name, b.purpose, b.start_time, b.end_time
                FROM bookings b
                WHERE b.facility_type = ? 
                AND b.booking_date = ? 
                AND b.status IN ('pending', 'confirmed')
                AND b.id != ?
                AND (
                    (b.start_time <= ? AND b.end_time > ?) OR
                    (b.start_time < ? AND b.end_time >= ?) OR
                    (b.start_time >= ? AND b.end_time <= ?)
                )
            ");
            
            $stmt->execute([
                $facilityType, $date, $programme['booking_id'],
                $startTime, $startTime,
                $endTime, $endTime,
                $startTime, $endTime
            ]);
            
            $conflicts = [];
            while ($row = $stmt->fetch()) {
                $conflicts[] = "Booking #{$row['id']} ({$row['customer_name']}) - {$row['start_time']} to {$row['end_time']}";
            }
            
            if (!empty($conflicts)) {
                throw new Exception("Facility conflicts detected: " . implode(', ', $conflicts));
            }
            
            // Update facility booking
            $stmt = $this->pdo->prepare("
                UPDATE bookings 
                SET facility_type = ?, booking_date = ?, start_time = ?, end_time = ?,
                    attendees_count = ?, purpose = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $facilityType,
                $date,
                $startTime,
                $endTime,
                $programme['max_participants'] ?? 25,
                'Training Programme: ' . $programme['title'],
                $programme['booking_id']
            ]);
            
            // Update programme details
            $stmt = $this->pdo->prepare("
                UPDATE programmes 
                SET facility_type = ?, start_date = ?, end_date = ?, 
                    start_time = ?, end_time = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $facilityType,
                $date,
                $date, // Assuming single day programmes
                $startTime,
                $endTime,
                $programmeId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Facility booking update failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete facility booking for a programme
     */
    public function deleteProgrammeFacilityBooking($programmeId) {
        try {
            // Check if the new columns exist
            $this->checkDatabaseSchema();
            
            // Get programme details
            $stmt = $this->pdo->prepare("
                SELECT facility_booking_id FROM programmes WHERE id = ?
            ");
            $stmt->execute([$programmeId]);
            $programme = $stmt->fetch();
            
            if (!$programme || !$programme['facility_booking_id']) {
                return true; // No booking to delete
            }
            
            // Delete the facility booking
            $stmt = $this->pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$programme['facility_booking_id']]);
            
            // Clear facility booking reference from programme
            $stmt = $this->pdo->prepare("
                UPDATE programmes 
                SET facility_booking_id = NULL, requires_facility = FALSE 
                WHERE id = ?
            ");
            $stmt->execute([$programmeId]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Facility booking deletion failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Cancel facility booking for a programme (mark as cancelled instead of deleting)
     */
    public function cancelProgrammeFacilityBooking($programmeId) {
        try {
            // Check if the new columns exist
            $this->checkDatabaseSchema();
            
            // Get programme details
            $stmt = $this->pdo->prepare("
                SELECT facility_booking_id FROM programmes WHERE id = ?
            ");
            $stmt->execute([$programmeId]);
            $programme = $stmt->fetch();
            
            if (!$programme || !$programme['facility_booking_id']) {
                return true; // No booking to cancel
            }
            
            // Cancel the facility booking instead of deleting
            $stmt = $this->pdo->prepare("
                UPDATE bookings 
                SET status = 'cancelled', 
                    cancellation_reason = 'Programme deactivated',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$programme['facility_booking_id']]);
            
            // Clear facility booking reference from programme but keep requires_facility
            $stmt = $this->pdo->prepare("
                UPDATE programmes 
                SET facility_booking_id = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$programmeId]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Facility booking cancellation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Restore facility booking when programme is reactivated
     */
    public function restoreProgrammeFacilityBooking($programmeId) {
        try {
            // Check if the new columns exist
            $this->checkDatabaseSchema();
            
            // Get programme details
            $stmt = $this->pdo->prepare("
                SELECT id, title, requires_facility, facility_type, start_date, start_time, end_time, max_participants
                FROM programmes 
                WHERE id = ? AND requires_facility = TRUE
            ");
            $stmt->execute([$programmeId]);
            $programme = $stmt->fetch();
            
            if (!$programme) {
                return true; // Programme doesn't require facility or not found
            }
            
            // Check if programme already has a facility booking
            if ($programme['facility_booking_id']) {
                return true; // Already has a booking
            }
            
            // Create new facility booking
            $bookingId = $this->createProgrammeFacilityBooking($programmeId);
            
            return $bookingId;
            
        } catch (Exception $e) {
            error_log("Facility booking restoration failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get programme facility booking details
     */
    public function getProgrammeFacilityBooking($programmeId) {
        try {
            // Check if the new columns exist
            $this->checkDatabaseSchema();
            
            $stmt = $this->pdo->prepare("
                SELECT p.id as programme_id, p.title, p.requires_facility, p.facility_type,
                       b.id as booking_id, b.status, b.booking_date, b.start_time, b.end_time,
                       b.customer_name, b.purpose
                FROM programmes p
                LEFT JOIN bookings b ON p.facility_booking_id = b.id
                WHERE p.id = ?
            ");
            $stmt->execute([$programmeId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            // Return empty result if schema not updated
            return false;
        }
    }
    
    /**
     * Get all programme facility bookings
     */
    public function getAllProgrammeFacilityBookings() {
        try {
            // Check if the new columns exist
            $this->checkDatabaseSchema();
            
            $stmt = $this->pdo->prepare("
                SELECT p.id as programme_id, p.title, p.requires_facility, p.facility_type,
                       b.id as booking_id, b.status, b.booking_date, b.start_time, b.end_time,
                       b.customer_name, b.purpose
                FROM programmes p
                LEFT JOIN bookings b ON p.facility_booking_id = b.id
                WHERE p.requires_facility = TRUE
                ORDER BY p.start_date DESC, p.start_time DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            // Return empty array if schema not updated
            return [];
        }
    }
}
