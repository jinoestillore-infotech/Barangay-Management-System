<?php
declare(strict_types=1);

/**
 * ResidentManager Class
 * Manages Barangay Resident demographic profiles, dynamic filters, stats, and audit logs.
 */
class ResidentManager {
    private PDO $db;

    // Schema validations
    private const ALLOWED_GENDERS = ['Male', 'Female', 'Other'];
    private const ALLOWED_CIVIL_STATUSES = ['Single', 'Married', 'Widowed', 'Divorced', 'Separated'];
    private const ALLOWED_STATUSES = ['Active', 'Deceased', 'Moved Out'];

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Get paginated and filtered list of residents
     * FIXED: Avoids reusing named placeholders to prevent errors when PDO emulation is disabled.
     */
    public function getResidents(
        string $search = '', 
        string $purok = '', 
        string $classification = '', 
        string $status = 'Active', 
        int $limit = 10, 
        int $offset = 0
    ): array {
        try {
            $query = "SELECT r.*, h.household_number, h.zone_purok 
                      FROM residents r
                      LEFT JOIN households h ON r.household_id = h.id
                      WHERE 1=1";
            $params = [];

            if ($search !== '') {
                // FIXED: Using unique parameter bindings for each field to satisfy strict PDO prepared statements
                $query .= " AND (r.first_name LIKE :search_first 
                             OR r.last_name LIKE :search_last 
                             OR r.national_id LIKE :search_national 
                             OR r.contact_number LIKE :search_contact)";
                $params[':search_first'] = '%' . $search . '%';
                $params[':search_last'] = '%' . $search . '%';
                $params[':search_national'] = '%' . $search . '%';
                $params[':search_contact'] = '%' . $search . '%';
            }

            if ($purok !== '') {
                $query .= " AND h.zone_purok = :purok";
                $params[':purok'] = $purok;
            }

            // Classification filter logic
            if ($classification === 'Senior') {
                $query .= " AND r.is_senior = 1";
            } elseif ($classification === 'PWD') {
                $query .= " AND r.is_pwd = 1";
            } elseif ($classification === 'Voter') {
                $query .= " AND r.is_voter = 1";
            }

            // Default to Active if status is invalid or blank
            $statusVal = in_array($status, self::ALLOWED_STATUSES, true) ? $status : 'Active';
            $query .= " AND r.status = :status";
            $params[':status'] = $statusVal;

            $query .= " ORDER BY r.last_name ASC, r.first_name ASC LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);
            
            // Bind search, status, and filter params
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            // Explicitly bind integer limits to avoid syntax evaluation errors in PDO
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log("Error retrieving residents: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get count of filtered records for server-side pagination limits
     * FIXED: Avoids reusing named placeholders.
     */
    public function getResidentsCount(
        string $search = '', 
        string $purok = '', 
        string $classification = '', 
        string $status = 'Active'
    ): int {
        try {
            $query = "SELECT COUNT(r.id) as total 
                      FROM residents r
                      LEFT JOIN households h ON r.household_id = h.id
                      WHERE 1=1";
            $params = [];

            if ($search !== '') {
                // FIXED: Split named parameters
                $query .= " AND (r.first_name LIKE :search_first 
                             OR r.last_name LIKE :search_last 
                             OR r.national_id LIKE :search_national 
                             OR r.contact_number LIKE :search_contact)";
                $params[':search_first'] = '%' . $search . '%';
                $params[':search_last'] = '%' . $search . '%';
                $params[':search_national'] = '%' . $search . '%';
                $params[':search_contact'] = '%' . $search . '%';
            }

            if ($purok !== '') {
                $query .= " AND h.zone_purok = :purok";
                $params[':purok'] = $purok;
            }

            if ($classification === 'Senior') {
                $query .= " AND r.is_senior = 1";
            } elseif ($classification === 'PWD') {
                $query .= " AND r.is_pwd = 1";
            } elseif ($classification === 'Voter') {
                $query .= " AND r.is_voter = 1";
            }

            $statusVal = in_array($status, self::ALLOWED_STATUSES, true) ? $status : 'Active';
            $query .= " AND r.status = :status";
            $params[':status'] = $statusVal;

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return $result ? (int)$result['total'] : 0;
        } catch (PDOException $e) {
            error_log("Error counting filtered residents: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch a single resident record with complete household reference
     */
    public function getResidentById(int $id) {
        try {
            $query = "SELECT r.*, h.household_number, h.zone_purok, h.street 
                      FROM residents r
                      LEFT JOIN households h ON r.household_id = h.id
                      WHERE r.id = :id LIMIT 1";
            
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
     * Verify if household already contains an active designated "Head of Household"
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
     * Check if a National ID / PhilSys Card is already linked to another profile
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
     * Register a new resident profile securely
     */
    public function createResident(array $data, int $actorId): bool {
        try {
            $query = "INSERT INTO residents (
                        household_id, national_id, first_name, middle_name, last_name, extension_name,
                        birth_date, birth_place, gender, civil_status, citizenship, religion, occupation,
                        is_voter, is_senior, is_pwd, pwd_type, relationship_to_head, contact_number, email
                      ) VALUES (
                        :household_id, :national_id, :first_name, :middle_name, :last_name, :extension_name,
                        :birth_date, :birth_place, :gender, :civil_status, :citizenship, :religion, :occupation,
                        :is_voter, :is_senior, :is_pwd, :pwd_type, :relationship_to_head, :contact_number, :email
                      )";
            
            $stmt = $this->db->prepare($query);

            // Automatic law-defined Senior citizen age determination (Age >= 60)
            $isSenior = 0;
            if (!empty($data['birth_date'])) {
                $birthDate = new DateTime($data['birth_date']);
                $today = new DateTime('today');
                $age = $today->diff($birthDate)->y;
                if ($age >= 60) {
                    $isSenior = 1;
                }
            }

            // Bind values securely
            $stmt->bindValue(':household_id', !empty($data['household_id']) ? (int)$data['household_id'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':national_id', !empty($data['national_id']) ? trim($data['national_id']) : null, PDO::PARAM_STR);
            $stmt->bindValue(':first_name', strip_tags(trim($data['first_name'])), PDO::PARAM_STR);
            $stmt->bindValue(':middle_name', !empty($data['middle_name']) ? strip_tags(trim($data['middle_name'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':last_name', strip_tags(trim($data['last_name'])), PDO::PARAM_STR);
            $stmt->bindValue(':extension_name', !empty($data['extension_name']) ? strip_tags(trim($data['extension_name'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':birth_date', $data['birth_date'], PDO::PARAM_STR);
            $stmt->bindValue(':birth_place', !empty($data['birth_place']) ? strip_tags(trim($data['birth_place'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':gender', in_array($data['gender'], self::ALLOWED_GENDERS, true) ? $data['gender'] : 'Male', PDO::PARAM_STR);
            $stmt->bindValue(':civil_status', in_array($data['civil_status'], self::ALLOWED_CIVIL_STATUSES, true) ? $data['civil_status'] : 'Single', PDO::PARAM_STR);
            $stmt->bindValue(':citizenship', strip_tags(trim($data['citizenship'] ?? 'Filipino')), PDO::PARAM_STR);
            $stmt->bindValue(':religion', !empty($data['religion']) ? strip_tags(trim($data['religion'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':occupation', !empty($data['occupation']) ? strip_tags(trim($data['occupation'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':is_voter', (int)($data['is_voter'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':is_senior', $isSenior, PDO::PARAM_INT);
            $stmt->bindValue(':is_pwd', (int)($data['is_pwd'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':pwd_type', !empty($data['pwd_type']) && ($data['is_pwd'] ?? 0) == 1 ? strip_tags(trim($data['pwd_type'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':relationship_to_head', !empty($data['relationship_to_head']) ? $data['relationship_to_head'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':contact_number', !empty($data['contact_number']) ? preg_replace('/[^0-9+]/', '', $data['contact_number']) : null, PDO::PARAM_STR);
            $stmt->bindValue(':email', !empty($data['email']) ? filter_var($data['email'], FILTER_SANITIZE_EMAIL) : null, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $safeName = htmlspecialchars($data['first_name'] . ' ' . $data['last_name'], ENT_QUOTES, 'UTF-8');
                $this->logActivity($actorId, "Registered resident profile: {$safeName}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating resident: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing resident profile record securely
     */
    public function updateResident(int $id, array $data, int $actorId): bool {
        try {
            $query = "UPDATE residents SET 
                        household_id = :household_id, national_id = :national_id, first_name = :first_name, 
                        middle_name = :middle_name, last_name = :last_name, extension_name = :extension_name,
                        birth_date = :birth_date, birth_place = :birth_place, gender = :gender, 
                        civil_status = :civil_status, citizenship = :citizenship, religion = :religion, 
                        occupation = :occupation, is_voter = :is_voter, is_senior = :is_senior, 
                        is_pwd = :is_pwd, pwd_type = :pwd_type, relationship_to_head = :relationship_to_head, 
                        contact_number = :contact_number, email = :email
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);

            $isSenior = 0;
            if (!empty($data['birth_date'])) {
                $birthDate = new DateTime($data['birth_date']);
                $today = new DateTime('today');
                $age = $today->diff($birthDate)->y;
                if ($age >= 60) {
                    $isSenior = 1;
                }
            }

            $stmt->bindValue(':household_id', !empty($data['household_id']) ? (int)$data['household_id'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':national_id', !empty($data['national_id']) ? trim($data['national_id']) : null, PDO::PARAM_STR);
            $stmt->bindValue(':first_name', strip_tags(trim($data['first_name'])), PDO::PARAM_STR);
            $stmt->bindValue(':middle_name', !empty($data['middle_name']) ? strip_tags(trim($data['middle_name'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':last_name', strip_tags(trim($data['last_name'])), PDO::PARAM_STR);
            $stmt->bindValue(':extension_name', !empty($data['extension_name']) ? strip_tags(trim($data['extension_name'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':birth_date', $data['birth_date'], PDO::PARAM_STR);
            $stmt->bindValue(':birth_place', !empty($data['birth_place']) ? strip_tags(trim($data['birth_place'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':gender', in_array($data['gender'], self::ALLOWED_GENDERS, true) ? $data['gender'] : 'Male', PDO::PARAM_STR);
            $stmt->bindValue(':civil_status', in_array($data['civil_status'], self::ALLOWED_CIVIL_STATUSES, true) ? $data['civil_status'] : 'Single', PDO::PARAM_STR);
            $stmt->bindValue(':citizenship', strip_tags(trim($data['citizenship'] ?? 'Filipino')), PDO::PARAM_STR);
            $stmt->bindValue(':religion', !empty($data['religion']) ? strip_tags(trim($data['religion'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':occupation', !empty($data['occupation']) ? strip_tags(trim($data['occupation'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':is_voter', (int)($data['is_voter'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':is_senior', $isSenior, PDO::PARAM_INT);
            $stmt->bindValue(':is_pwd', (int)($data['is_pwd'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':pwd_type', !empty($data['pwd_type']) && ($data['is_pwd'] ?? 0) == 1 ? strip_tags(trim($data['pwd_type'])) : null, PDO::PARAM_STR);
            $stmt->bindValue(':relationship_to_head', !empty($data['relationship_to_head']) ? $data['relationship_to_head'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':contact_number', !empty($data['contact_number']) ? preg_replace('/[^0-9+]/', '', $data['contact_number']) : null, PDO::PARAM_STR);
            $stmt->bindValue(':email', !empty($data['email']) ? filter_var($data['email'], FILTER_SANITIZE_EMAIL) : null, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $safeName = htmlspecialchars($data['first_name'] . ' ' . $data['last_name'], ENT_QUOTES, 'UTF-8');
                $this->logActivity($actorId, "Updated resident profile: {$safeName}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error updating resident ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Change resident vital and demographic registration status
     */
    public function updateVitalStatus(int $id, string $status, int $actorId): bool {
        try {
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                return false;
            }

            $resident = $this->getResidentById($id);
            if (!$resident) {
                return false;
            }

            $query = "UPDATE residents SET status = :status WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $safeName = htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name'], ENT_QUOTES, 'UTF-8');
                $safeStatus = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
                $this->logActivity($actorId, "Marked vital status of resident {$safeName} as '{$safeStatus}'");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error updating vital status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve aggregate summary parameters for administrative dashboard overview cards
     */
    public function getDemographicStats(): array {
        try {
            $stats = [
                'total_active' => 0,
                'seniors'      => 0,
                'pwds'         => 0,
                'voters'       => 0
            ];

            $query = "SELECT 
                        COUNT(CASE WHEN status = 'Active' THEN 1 END) as total_active,
                        COUNT(CASE WHEN status = 'Active' AND is_senior = 1 THEN 1 END) as seniors,
                        COUNT(CASE WHEN status = 'Active' AND is_pwd = 1 THEN 1 END) as pwds,
                        COUNT(CASE WHEN status = 'Active' AND is_voter = 1 THEN 1 END) as voters
                      FROM residents";

            $stmt = $this->db->query($query);
            $res = $stmt->fetch();
            if ($res) {
                $stats['total_active'] = (int)$res['total_active'];
                $stats['seniors'] = (int)$res['seniors'];
                $stats['pwds'] = (int)$res['pwds'];
                $stats['voters'] = (int)$res['voters'];
            }
            return $stats;
        } catch (PDOException $e) {
            error_log("Error retrieving demographics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Shared activity logging helper (Mitigates Log Injection and XSS)
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