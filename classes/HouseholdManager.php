<?php
declare(strict_types=1);

/**
 * HouseholdManager Class
 * Manages Barangay Household records, filters, member listings, and related security audit logs.
 */
class HouseholdManager {
    private PDO $db;

    // Allowed Purok/Zone and Income Bracket whitelists for strict validation
    private const ALLOWED_ZONES = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7', 'Zone A', 'Zone B', 'Zone C', 'Sitio Centro', 'Sitio Pag-asa'];
    private const ALLOWED_INCOMES = ['Low Income', 'Lower Middle Income', 'Middle Income', 'Upper Middle Income', 'High Income', 'Indigent / N/A'];

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Fetch households with dynamic searching, filtering, and total member count
     * @param string $search (household number or street)
     * @param string $purok
     * @param string $income
     * @return array
     */
    public function getHouseholds(string $search = '', string $purok = '', string $income = ''): array {
        try {
            // This query fetches households, calculates the total resident count, and dynamically finds the name of the Household Head
            $query = "SELECT h.*, 
                        COUNT(r.id) as total_members,
                        MAX(CASE WHEN r.relationship_to_head = 'Head' AND r.status = 'Active' THEN CONCAT(r.first_name, ' ', r.last_name) END) as household_head
                      FROM households h
                      LEFT JOIN residents r ON h.id = r.household_id
                      WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $query .= " AND (h.household_number LIKE :search_num OR h.street LIKE :search_street)";
                $params[':search_num'] = '%' . $search . '%';
                $params[':search_street'] = '%' . $search . '%';
            }

            if ($purok !== '') {
                $query .= " AND h.zone_purok = :purok";
                $params[':purok'] = $purok;
            }

            if ($income !== '') {
                $query .= " AND h.income_bracket = :income";
                $params[':income'] = $income;
            }

            $query .= " GROUP BY h.id ORDER BY h.household_number ASC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log("Error retrieving households: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single household's detailed information
     * @param int $id
     * @return array|bool
     */
    public function getHouseholdById(int $id) {
        try {
            $query = "SELECT h.*, 
                        COUNT(r.id) as total_members,
                        MAX(CASE WHEN r.relationship_to_head = 'Head' AND r.status = 'Active' THEN CONCAT(r.first_name, ' ', r.last_name) END) as household_head
                      FROM households h
                      LEFT JOIN residents r ON h.id = r.household_id
                      WHERE h.id = :id
                      GROUP BY h.id
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch() ?: false;
        } catch (PDOException $e) {
            error_log("Error retrieving household ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch all residents belonging to a specific household
     * @param int $householdId
     * @return array
     */
    public function getHouseholdMembers(int $householdId): array {
        try {
            $query = "SELECT id, first_name, middle_name, last_name, birth_date, gender, relationship_to_head, status, is_senior, is_pwd 
                      FROM residents 
                      WHERE household_id = :household_id 
                      ORDER BY CASE WHEN relationship_to_head = 'Head' THEN 0 ELSE 1 END, last_name ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':household_id', $householdId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log("Error retrieving members for household {$householdId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new household securely
     * @param array $data
     * @param int $actorId
     * @return bool
     */
    public function createHousehold(array $data, int $actorId): bool {
        try {
            $query = "INSERT INTO households (household_number, street, zone_purok, income_bracket) 
                      VALUES (:household_number, :street, :zone_purok, :income_bracket)";
            
            $stmt = $this->db->prepare($query);
            
            // Clean inputs to prevent XSS and malicious characters
            $cleanNumber = preg_replace('/[^a-zA-Z0-9-]/', '', trim($data['household_number']));
            $cleanStreet = strip_tags(trim($data['street']));
            $purok = trim($data['zone_purok']);
            $income = trim($data['income_bracket']);

            $stmt->bindValue(':household_number', $cleanNumber, PDO::PARAM_STR);
            $stmt->bindValue(':street', $cleanStreet, PDO::PARAM_STR);
            $stmt->bindValue(':zone_purok', $purok, PDO::PARAM_STR);
            $stmt->bindValue(':income_bracket', $income, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $safeLogNumber = htmlspecialchars($cleanNumber, ENT_QUOTES, 'UTF-8');
                $safeLogPurok = htmlspecialchars($purok, ENT_QUOTES, 'UTF-8');
                $this->logActivity($actorId, "Registered a new household: {$safeLogNumber} at {$safeLogPurok}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating household: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing household record securely
     * @param int $id
     * @param array $data
     * @param int $actorId
     * @return bool
     */
    public function updateHousehold(int $id, array $data, int $actorId): bool {
        try {
            $query = "UPDATE households 
                      SET household_number = :household_number, street = :street, zone_purok = :zone_purok, income_bracket = :income_bracket 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            
            $cleanNumber = preg_replace('/[^a-zA-Z0-9-]/', '', trim($data['household_number']));
            $cleanStreet = strip_tags(trim($data['street']));
            $purok = trim($data['zone_purok']);
            $income = trim($data['income_bracket']);

            $stmt->bindValue(':household_number', $cleanNumber, PDO::PARAM_STR);
            $stmt->bindValue(':street', $cleanStreet, PDO::PARAM_STR);
            $stmt->bindValue(':zone_purok', $purok, PDO::PARAM_STR);
            $stmt->bindValue(':income_bracket', $income, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $safeLogNumber = htmlspecialchars($cleanNumber, ENT_QUOTES, 'UTF-8');
                $this->logActivity($actorId, "Updated details for household: {$safeLogNumber}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error updating household ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a household number is already taken
     * @param string $number
     * @param int|null $excludeId
     * @return bool
     */
    public function isHouseholdNumberTaken(string $number, ?int $excludeId = null): bool {
        try {
            $query = "SELECT id FROM households WHERE household_number = :number";
            if ($excludeId !== null) {
                $query .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($query);
            
            $cleanNumber = preg_replace('/[^a-zA-Z0-9-]/', '', trim($number));
            $stmt->bindValue(':number', $cleanNumber, PDO::PARAM_STR);
            
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
     * Shared activity logging helper
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
            error_log("Failed to write audit log from HouseholdManager: " . $e->getMessage());
        }
    }
}
?>