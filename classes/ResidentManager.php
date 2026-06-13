<?php
declare(strict_types=1);

/**
 * ResidentManager Class
 * Manages Barangay Resident demographic records, relationships, age calculations, 
 * vital statuses, and related security audit logs.
 */
class ResidentManager {
    private PDO $db;

    // Allowed schema values for strict validation constraints
    private const ALLOWED_GENDERS = ['Male', 'Female', 'Other'];
    private const ALLOWED_CIVIL_STATUSES = ['Single', 'Married', 'Widowed', 'Divorced', 'Separated'];
    private const ALLOWED_STATUSES = ['Active', 'Deceased', 'Moved Out'];

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Fetch residents with dynamic searching and multi-parameter filters.
     * Integrates with households to allow geographic sorting by Purok/Zone.
     * 
     * @param string $search (Name, National ID, or Contact details)
     * @param string $purok Filter by geographic sector
     * @param string $classification Filter by demographic subset (Senior, PWD, Voter)
     * @param string $status Filter by vital status (Active, Deceased, Moved Out)
     * @return array
     */
    public function getResidents(
        string $search = '', 
        string $purok = '', 
        string $classification = '', 
        string $status = ''
    ): array {
        try {
            $query = "SELECT r.*, h.household_number, h.zone_purok, h.street 
                      FROM residents r
                      LEFT JOIN households h ON r.household_id = h.id 
                      WHERE 1=1";
            $params = [];

            // Dynamic Search handling (Full name, National ID, Contact details)
            if ($search !== '') {
                $query .= " AND (r.first_name LIKE :search_name 
                             OR r.last_name LIKE :search_name 
                             OR r.middle_name LIKE :search_name 
                             OR r.national_id LIKE :search_id
                             OR r.contact_number LIKE :search_contact)";
                $params[':search_name'] = '%' . $search . '%';
                $params[':search_id'] = '%' . $search . '%';
                $params[':search_contact'] = '%' . $search . '%';
            }

            // Geographic clustering filter
            if ($purok !== '') {
                $query .= " AND h.zone_purok = :purok";
                $params[':purok'] = $purok;
            }

            // Demographic subset filter
            if ($classification !== '') {
                if ($classification === 'Senior') {
                    $query .= " AND r.is_senior = 1";
                } elseif ($classification === 'PWD') {
                    $query .= " AND r.is_pwd = 1";
                } elseif ($classification === 'Voter') {
                    $query .= " AND r.is_voter = 1";
                }
            }

            // Vital status filter (Default to showing Active residents if not specified)
            if ($status !== '') {
                if (in_array($status, self::ALLOWED_STATUSES, true)) {
                    $query .= " AND r.status = :status";
                    $params[':status'] = $status;
                }
            } else {
                $query .= " AND r.status = 'Active'";
            }

            $query .= " ORDER BY r.last_name ASC, r.first_name ASC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log("Error retrieving residents: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single resident's detailed profile including linked Household details
     * 
     * @param int $id
     * @return array|bool Resident data array if found, false otherwise
     */
    public function getResidentById(int $id) {
        try {
            $query = "SELECT r.*, h.household_number, h.zone_purok, h.street, h.income_bracket
                      FROM residents r
                      LEFT JOIN households h ON r.household_id = h.id 
                      WHERE r.id = :id 
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch() ?: false;
        } catch (PDOException $e) {
            error_log("Error retrieving resident ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Internal helper to calculate senior citizen status dynamically based on birthdate
     * 
     * @param string $birthDate MySQL formatted Date string (Y-m-d)
     * @return int 1 if age >= 60, otherwise 0
     */
    private function calculateIsSenior(string $birthDate): int {
        try {
            $birth = new DateTime($birthDate);
            $today = new DateTime();
            $age = $today->diff($birth)->y;
            return $age >= 60 ? 1 : 0;
        } catch (Exception $e) {
            return 0; // Fallback default if date format parsing errors occur
        }
    }

    /**
     * Validate if a household already has an active designated Head of Household.
     * Prevents violating the core Philippine RBI constraint (one head per household unit).
     * 
     * @param int $householdId
     * @param int|null $excludeResidentId
     * @return bool True if an active head exists, false otherwise
     */
    public function hasActiveHouseholdHead(int $householdId, ?int $excludeResidentId = null): bool {
        try {
            $query = "SELECT id FROM residents 
                      WHERE household_id = :household_id 
                        AND relationship_to_head = 'Head' 
                        AND status = 'Active'";
            if ($excludeResidentId !== null) {
                $query .= " AND id != :exclude_id";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':household_id', $householdId, PDO::PARAM_INT);
            if ($excludeResidentId !== null) {
                $stmt->bindValue(':exclude_id', $excludeResidentId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Register a new resident in the system with comprehensive profile parameters.
     * Integrates automatic senior calculation and sanitization.
     * 
     * @param array $data
     * @param int $actorId (The admin user registering the resident)
     * @return bool
     */
    public function createResident(array $data, int $actorId): bool {
        try {
            // Validate unique National ID (if provided)
            if (!empty($data['national_id']) && $this->isNationalIdTaken(trim($data['national_id']))) {
                return false;
            }

            // Enforce DILG structural constraint (Single household head limit)
            $householdId = !empty($data['household_id']) ? (int)$data['household_id'] : null;
            $relationship = !empty($data['relationship_to_head']) ? trim($data['relationship_to_head']) : null;
            if ($householdId !== null && $relationship === 'Head') {
                if ($this->hasActiveHouseholdHead($householdId)) {
                    error_log("Constraint violation: Household already has an active head.");
                    return false;
                }
            }

            // Automatic Senior Citizen Age Calculation (Philippine Law: age >= 60)
            $isSenior = $this->calculateIsSenior($data['birth_date']);

            // Enumeration strict fallbacks
            $gender = in_array($data['gender'], self::ALLOWED_GENDERS, true) ? $data['gender'] : 'Male';
            $civilStatus = in_array($data['civil_status'], self::ALLOWED_CIVIL_STATUSES, true) ? $data['civil_status'] : 'Single';
            $status = in_array($data['status'] ?? 'Active', self::ALLOWED_STATUSES, true) ? ($data['status'] ?? 'Active') : 'Active';

            $query = "INSERT INTO residents (
                        household_id, national_id, first_name, middle_name, last_name, extension_name,
                        birth_date, birth_place, gender, civil_status, citizenship, religion, occupation,
                        is_voter, is_senior, is_pwd, pwd_type, relationship_to_head, contact_number, email, status
                      ) VALUES (
                        :household_id, :national_id, :first_name, :middle_name, :last_name, :extension_name,
                        :birth_date, :birth_place, :gender, :civil_status, :citizenship, :religion, :occupation,
                        :is_voter, :is_senior, :is_pwd, :pwd_type, :relationship_to_head, :contact_number, :email, :status
                      )";

            $stmt = $this->db->prepare($query);

            // Clean, sanitize, and bind text variables
            $stmt->bindValue(':household_id', $householdId, $householdId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':national_id', !empty($data['national_id']) ? trim($data['national_id']) : null, PDO::PARAM_STR);
            $stmt->bindValue(':first_name', strip_tags(trim($data['first_name'])), PDO::PARAM_STR);
            $stmt->bindValue(':middle_name', !empty($data['middle_name']) ? strip_tags(trim($data['middle_name'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':last_name', strip_tags(trim($data['last_name'])), PDO::PARAM_STR);
            $stmt->bindValue(':extension_name', !empty($data['extension_name']) ? strip_tags(trim($data['extension_name'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':birth_date', $data['birth_date'], PDO::PARAM_STR);
            $stmt->bindValue(':birth_place', !empty($data['birth_place']) ? strip_tags(trim($data['birth_place'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
            $stmt->bindValue(':civil_status', $civilStatus, PDO::PARAM_STR);
            $stmt->bindValue(':citizenship', !empty($data['citizenship']) ? strip_tags(trim($data['citizenship'])) : 'Filipino', PDO::PARAM_STR);
            $stmt->bindValue(':religion', !empty($data['religion']) ? strip_tags(trim($data['religion'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':occupation', !empty($data['occupation']) ? strip_tags(trim($data['occupation'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':is_voter', (int)($data['is_voter'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':is_senior', $isSenior, PDO::PARAM_INT);
            $stmt->bindValue(':is_pwd', (int)($data['is_pwd'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':pwd_type', !empty($data['pwd_type']) ? strip_tags(trim($data['pwd_type'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':relationship_to_head', $relationship, $relationship === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':contact_number', !empty($data['contact_number']) ? strip_tags(trim($data['contact_number'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':email', !empty($data['email']) ? filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL) : null, PDO::PARAM_STR);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $fullname = trim($data['first_name'] . ' ' . $data['last_name']);
                $safeName = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
                $this->logActivity($actorId, "Registered a new resident: {$safeName}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating resident: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing resident's profile securely.
     * 
     * @param int $id
     * @param array $data
     * @param int $actorId
     * @return bool
     */
    public function updateResident(int $id, array $data, int $actorId): bool {
        try {
            // Check National ID unique constraint if modified
            if (!empty($data['national_id']) && $this->isNationalIdTaken(trim($data['national_id']), $id)) {
                return false;
            }

            // Enforce DILG structural constraint (Single household head limit)
            $householdId = !empty($data['household_id']) ? (int)$data['household_id'] : null;
            $relationship = !empty($data['relationship_to_head']) ? trim($data['relationship_to_head']) : null;
            if ($householdId !== null && $relationship === 'Head') {
                if ($this->hasActiveHouseholdHead($householdId, $id)) {
                    error_log("Constraint violation: Household already has an active head.");
                    return false;
                }
            }

            // Automatic Senior Citizen Age Calculation
            $isSenior = $this->calculateIsSenior($data['birth_date']);

            // Enumeration strict fallbacks
            $gender = in_array($data['gender'], self::ALLOWED_GENDERS, true) ? $data['gender'] : 'Male';
            $civilStatus = in_array($data['civil_status'], self::ALLOWED_CIVIL_STATUSES, true) ? $data['civil_status'] : 'Single';
            $status = in_array($data['status'] ?? 'Active', self::ALLOWED_STATUSES, true) ? ($data['status'] ?? 'Active') : 'Active';

            $query = "UPDATE residents SET 
                        household_id = :household_id, national_id = :national_id, first_name = :first_name, 
                        middle_name = :middle_name, last_name = :last_name, extension_name = :extension_name,
                        birth_date = :birth_date, birth_place = :birth_place, gender = :gender, 
                        civil_status = :civil_status, citizenship = :citizenship, religion = :religion, 
                        occupation = :occupation, is_voter = :is_voter, is_senior = :is_senior, 
                        is_pwd = :is_pwd, pwd_type = :pwd_type, relationship_to_head = :relationship_to_head, 
                        contact_number = :contact_number, email = :email, status = :status
                      WHERE id = :id";

            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':household_id', $householdId, $householdId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':national_id', !empty($data['national_id']) ? trim($data['national_id']) : null, PDO::PARAM_STR);
            $stmt->bindValue(':first_name', strip_tags(trim($data['first_name'])), PDO::PARAM_STR);
            $stmt->bindValue(':middle_name', !empty($data['middle_name']) ? strip_tags(trim($data['middle_name'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':last_name', strip_tags(trim($data['last_name'])), PDO::PARAM_STR);
            $stmt->bindValue(':extension_name', !empty($data['extension_name']) ? strip_tags(trim($data['extension_name'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':birth_date', $data['birth_date'], PDO::PARAM_STR);
            $stmt->bindValue(':birth_place', !empty($data['birth_place']) ? strip_tags(trim($data['birth_place'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
            $stmt->bindValue(':civil_status', $civilStatus, PDO::PARAM_STR);
            $stmt->bindValue(':citizenship', !empty($data['citizenship']) ? strip_tags(trim($data['citizenship'])) : 'Filipino', PDO::PARAM_STR);
            $stmt->bindValue(':religion', !empty($data['religion']) ? strip_tags(trim($data['religion'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':occupation', !empty($data['occupation']) ? strip_tags(trim($data['occupation'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':is_voter', (int)($data['is_voter'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':is_senior', $isSenior, PDO::PARAM_INT);
            $stmt->bindValue(':is_pwd', (int)($data['is_pwd'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':pwd_type', !empty($data['pwd_type']) ? strip_tags(trim($data['pwd_type'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':relationship_to_head', $relationship, $relationship === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':contact_number', !empty($data['contact_number']) ? strip_tags(trim($data['contact_number'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':email', !empty($data['email']) ? filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL) : null, PDO::PARAM_STR);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $fullname = trim($data['first_name'] . ' ' . $data['last_name']);
                $safeName = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
                $this->logActivity($actorId, "Updated profile details for resident: {$safeName}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error updating resident: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update only the vital status of a resident (Active, Deceased, Moved Out)
     * 
     * @param int $id
     * @param string $status
     * @param int $actorId
     * @return bool
     */
    public function updateVitalStatus(int $id, string $status, int $actorId): bool {
        try {
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                return false;
            }

            // Fetch target user fullname before update
            $target = $this->getResidentById($id);
            $targetName = $target ? ($target['first_name'] . ' ' . $target['last_name']) : "ID: " . $id;

            $query = "UPDATE residents SET status = :status WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $safeName = htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8');
                $safeStatus = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
                $this->logActivity($actorId, "Marked vital status of resident '{$safeName}' to '{$safeStatus}'");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error updating vital status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a National Identification card number is already logged in our database
     * 
     * @param string $nationalId
     * @param int|null $excludeId
     * @return bool True if registered to another resident, false otherwise
     */
    public function isNationalIdTaken(string $nationalId, ?int $excludeId = null): bool {
        try {
            $query = "SELECT id FROM residents WHERE national_id = :national_id";
            if ($excludeId !== null) {
                $query .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':national_id', trim($nationalId), PDO::PARAM_STR);
            if ($excludeId !== null) {
                $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Collect precise demographic statistics about the active population.
     * Perfect for reports, dashboards, and distribution profiles.
     * 
     * @return array
     */
    public function getDemographicStats(): array {
        try {
            $stats = [
                'total_active' => 0,
                'voters' => 0,
                'seniors' => 0,
                'pwds' => 0,
                'males' => 0,
                'females' => 0,
                'deceased' => 0,
                'moved_out' => 0
            ];

            $query = "SELECT 
                        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as total_active,
                        SUM(CASE WHEN status = 'Active' AND is_voter = 1 THEN 1 ELSE 0 END) as voters,
                        SUM(CASE WHEN status = 'Active' AND is_senior = 1 THEN 1 ELSE 0 END) as seniors,
                        SUM(CASE WHEN status = 'Active' AND is_pwd = 1 THEN 1 ELSE 0 END) as pwds,
                        SUM(CASE WHEN status = 'Active' AND gender = 'Male' THEN 1 ELSE 0 END) as males,
                        SUM(CASE WHEN status = 'Active' AND gender = 'Female' THEN 1 ELSE 0 END) as females,
                        SUM(CASE WHEN status = 'Deceased' THEN 1 ELSE 0 END) as deceased,
                        SUM(CASE WHEN status = 'Moved Out' THEN 1 ELSE 0 END) as moved_out
                      FROM residents";

            $stmt = $this->db->query($query);
            $row = $stmt->fetch();
            if ($row) {
                $stats['total_active'] = (int)($row['total_active'] ?? 0);
                $stats['voters'] = (int)($row['voters'] ?? 0);
                $stats['seniors'] = (int)($row['seniors'] ?? 0);
                $stats['pwds'] = (int)($row['pwds'] ?? 0);
                $stats['males'] = (int)($row['males'] ?? 0);
                $stats['females'] = (int)($row['females'] ?? 0);
                $stats['deceased'] = (int)($row['deceased'] ?? 0);
                $stats['moved_out'] = (int)($row['moved_out'] ?? 0);
            }
            return $stats;
        } catch (PDOException $e) {
            error_log("Error pulling demographics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Secure activity logging helper
     */
    private function logActivity(int $userId, string $action): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $cleanAction = preg_replace('/[\r\n]+/', ' ', trim($action));
            $cleanAction = strip_tags((string)$cleanAction);

            $query = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (:user_id, :action, :ip)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':action', $cleanAction, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to write audit log from ResidentManager: " . $e->getMessage());
        }
    }
}
?>