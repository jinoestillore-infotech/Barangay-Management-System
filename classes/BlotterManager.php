<?php
declare(strict_types=1);

/**
 * BlotterManager Class
 * Manages Barangay Blotter Cases, Mediation Hearings, Settlements, and related audit logs.
 * Rebuilt to perfectly match the `blotter_records` table columns and constraints.
 */
class BlotterManager {
    private PDO $db;

    // Allowed ENUM statuses matching database.sql
    private const ALLOWED_STATUSES = ['Active', 'Settled', 'Scheduled for Mediation', 'Referred to Court'];
    
    // Whitelist of common incident types for drop-downs
    private const ALLOWED_INCIDENTS = [
        'Theft', 
        'Physical Injuries', 
        'Slander/Defamation', 
        'Boundary Dispute', 
        'Noise Complaint', 
        'Trespassing', 
        'Breach of Peace', 
        'Threats', 
        'Others'
    ];

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Fetch cases with dynamic searching, filtering, and pagination
     */
    public function getBlotters(
        string $search = '',
        string $status = '',
        string $incidentType = '',
        int $limit = 10,
        int $offset = 0
    ): array {
        try {
            $query = "SELECT b.*, 
                        CONCAT(rc.first_name, ' ', rc.last_name) as resident_complainant,
                        CONCAT(rr.first_name, ' ', rr.last_name) as resident_respondent,
                        u.fullname as recorded_by_name
                      FROM blotter_records b
                      LEFT JOIN residents rc ON b.complainant_id = rc.id
                      LEFT JOIN residents rr ON b.respondent_id = rr.id
                      LEFT JOIN users u ON b.recorded_by = u.id
                      WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $query .= " AND (b.case_number LIKE :search_case 
                             OR b.complainant_non_resident LIKE :search_comp_nr 
                             OR b.respondent_non_resident LIKE :search_resp_nr 
                             OR rc.first_name LIKE :search_rc_first 
                             OR rc.last_name LIKE :search_rc_last
                             OR rr.first_name LIKE :search_rr_first
                             OR rr.last_name LIKE :search_rr_last)";
                $params[':search_case'] = '%' . $search . '%';
                $params[':search_comp_nr'] = '%' . $search . '%';
                $params[':search_resp_nr'] = '%' . $search . '%';
                $params[':search_rc_first'] = '%' . $search . '%';
                $params[':search_rc_last'] = '%' . $search . '%';
                $params[':search_rr_first'] = '%' . $search . '%';
                $params[':search_rr_last'] = '%' . $search . '%';
            }

            if ($status !== '' && in_array($status, self::ALLOWED_STATUSES, true)) {
                $query .= " AND b.status = :status";
                $params[':status'] = $status;
            }

            if ($incidentType !== '') {
                $query .= " AND b.incident_type = :incident_type";
                $params[':incident_type'] = $incidentType;
            }

            $query .= " ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log("Error retrieving blotters: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get count of filtered blotter records for pagination
     */
    public function getBlottersCount(string $search = '', string $status = '', string $incidentType = ''): int {
        try {
            $query = "SELECT COUNT(b.id) as total 
                      FROM blotter_records b
                      LEFT JOIN residents rc ON b.complainant_id = rc.id
                      LEFT JOIN residents rr ON b.respondent_id = rr.id
                      WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $query .= " AND (b.case_number LIKE :search_case 
                             OR b.complainant_non_resident LIKE :search_comp_nr 
                             OR b.respondent_non_resident LIKE :search_resp_nr 
                             OR rc.first_name LIKE :search_rc_first 
                             OR rc.last_name LIKE :search_rc_last
                             OR rr.first_name LIKE :search_rr_first
                             OR rr.last_name LIKE :search_rr_last)";
                $params[':search_case'] = '%' . $search . '%';
                $params[':search_comp_nr'] = '%' . $search . '%';
                $params[':search_resp_nr'] = '%' . $search . '%';
                $params[':search_rc_first'] = '%' . $search . '%';
                $params[':search_rc_last'] = '%' . $search . '%';
                $params[':search_rr_first'] = '%' . $search . '%';
                $params[':search_rr_last'] = '%' . $search . '%';
            }

            if ($status !== '' && in_array($status, self::ALLOWED_STATUSES, true)) {
                $query .= " AND b.status = :status";
                $params[':status'] = $status;
            }

            if ($incidentType !== '') {
                $query .= " AND b.incident_type = :incident_type";
                $params[':incident_type'] = $incidentType;
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ? (int)$result['total'] : 0;
        } catch (PDOException $e) {
            error_log("Error counting blotters: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch a single blotter record details
     */
    public function getBlotterById(int $id) {
        try {
            $query = "SELECT b.*, 
                        CONCAT(rc.first_name, ' ', rc.last_name) as resident_complainant,
                        CONCAT(rr.first_name, ' ', rr.last_name) as resident_respondent,
                        u.fullname as recorded_by_name
                      FROM blotter_records b
                      LEFT JOIN residents rc ON b.complainant_id = rc.id
                      LEFT JOIN residents rr ON b.respondent_id = rr.id
                      LEFT JOIN users u ON b.recorded_by = u.id
                      WHERE b.id = :id LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch() ?: false;
        } catch (PDOException $e) {
            error_log("Error retrieving blotter ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create/File a new blotter case securely matching your database columns
     */
    public function createBlotter(array $data, int $actorId): bool {
        try {
            // Auto-generate unique case number: KP-YYYY-XXXX (sequential based on year)
            $year = date('Y');
            $countQuery = "SELECT COUNT(id) as total FROM blotter_records WHERE YEAR(created_at) = :year";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->bindValue(':year', $year, PDO::PARAM_STR);
            $countStmt->execute();
            $sequentialCount = ($countStmt->fetch()['total'] ?? 0) + 1;
            $caseNumber = sprintf("KP-%s-%04d", $year, $sequentialCount);

            $query = "INSERT INTO blotter_records (
                        case_number, complainant_id, complainant_non_resident, respondent_id, respondent_non_resident,
                        incident_type, incident_date, incident_location, details, status, recorded_by
                      ) VALUES (
                        :case_number, :complainant_id, :complainant_non_resident, :respondent_id, :respondent_non_resident,
                        :incident_type, :incident_date, :incident_location, :details, :status, :recorded_by
                      )";
            
            $stmt = $this->db->prepare($query);

            $complainantId = !empty($data['complainant_id']) ? (int)$data['complainant_id'] : null;
            $complainantNonResident = !empty($data['complainant_non_resident']) ? strip_tags(trim($data['complainant_non_resident'])) : null;
            $respondentId = !empty($data['respondent_id']) ? (int)$data['respondent_id'] : null;
            $respondentNonResident = !empty($data['respondent_non_resident']) ? strip_tags(trim($data['respondent_non_resident'])) : null;

            $stmt->bindValue(':case_number', $caseNumber, PDO::PARAM_STR);
            $stmt->bindValue(':complainant_id', $complainantId, PDO::PARAM_INT);
            $stmt->bindValue(':complainant_non_resident', $complainantNonResident, PDO::PARAM_STR);
            $stmt->bindValue(':respondent_id', $respondentId, PDO::PARAM_INT);
            $stmt->bindValue(':respondent_non_resident', $respondentNonResident, PDO::PARAM_STR);
            $stmt->bindValue(':incident_type', in_array($data['incident_type'], self::ALLOWED_INCIDENTS, true) ? $data['incident_type'] : 'Others', PDO::PARAM_STR);
            $stmt->bindValue(':incident_date', $data['incident_date'], PDO::PARAM_STR);
            $stmt->bindValue(':incident_location', strip_tags(trim($data['incident_location'])), PDO::PARAM_STR);
            $stmt->bindValue(':details', strip_tags(trim($data['details'])), PDO::PARAM_STR);
            $stmt->bindValue(':status', 'Active', PDO::PARAM_STR); // Starts as 'Active'
            $stmt->bindValue(':recorded_by', $actorId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $compLabel = $complainantNonResident ?? "Resident ID {$complainantId}";
                $respLabel = $respondentNonResident ?? "Resident ID {$respondentId}";
                $this->logActivity($actorId, "Filed blotter case {$caseNumber}: Complainant ({$compLabel}) vs Respondent ({$respLabel})");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating blotter: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Document a status update, schedule mediation, or log resolution details
     */
    public function updateStatus(int $id, string $status, string $settlementDetails, int $actorId): bool {
        try {
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                return false;
            }

            $query = "UPDATE blotter_records 
                      SET status = :status, settlement_details = :details 
                      WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':details', strip_tags(trim($settlementDetails)), PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logActivity($actorId, "Disputed Case ID #{$id} status updated to '{$status}'");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error resolving/updating case: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch active cases to display metrics on administrative charts
     */
    public function getBlotterStats(): array {
        try {
            $query = "SELECT 
                        COUNT(id) as total_cases,
                        COUNT(CASE WHEN status = 'Active' THEN 1 END) as active,
                        COUNT(CASE WHEN status = 'Scheduled for Mediation' THEN 1 END) as scheduled,
                        COUNT(CASE WHEN status = 'Settled' THEN 1 END) as settled,
                        COUNT(CASE WHEN status = 'Referred to Court' THEN 1 END) as referred
                      FROM blotter_records";
            $stmt = $this->db->query($query);
            return $stmt->fetch() ?: [];
        } catch (PDOException $e) {
            error_log("Error getting blotter metrics: " . $e->getMessage());
            return [];
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
            error_log("Failed to write audit log from BlotterManager: " . $e->getMessage());
        }
    }
}
?>