<?php
declare(strict_types=1);

/**
 * PaymentManager Class
 * Manages Barangay financial collection logs, Official Receipt (O.R.) ledger, and cash summaries.
 */
class PaymentManager {
    private PDO $db;

    // Common standard payment categories whitelists matching database limits
    private const ALLOWED_PURPOSES = [
        'Barangay Clearance Fee',
        'Business Clearance Fee',
        'Certificate of Residency Fee',
        'Certificate of Indigency Fee',
        'Barangay Facility Rental',
        'Logistical Asset Rental',
        'Blotter Filing Fee',
        'Miscellaneous Fee'
    ];

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Fetch payments ledger with dynamic search, category filter, and server-side pagination
     */
    public function getPayments(
        string $search = '',
        string $purpose = '',
        string $startDate = '',
        string $endDate = '',
        int $limit = 10,
        int $offset = 0
    ): array {
        try {
            $query = "SELECT p.*, 
                        CONCAT(r.first_name, ' ', r.last_name) as resident_payer,
                        u.fullname as cashier_name
                      FROM payments p
                      LEFT JOIN residents r ON p.resident_id = r.id
                      LEFT JOIN users u ON p.received_by = u.id
                      WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $query .= " AND (p.or_number LIKE :search_or 
                             OR p.payer_name LIKE :search_payer 
                             OR r.first_name LIKE :search_res_first 
                             OR r.last_name LIKE :search_res_last)";
                $params[':search_or'] = '%' . $search . '%';
                $params[':search_payer'] = '%' . $search . '%';
                $params[':search_res_first'] = '%' . $search . '%';
                $params[':search_res_last'] = '%' . $search . '%';
            }

            if ($purpose !== '' && in_array($purpose, self::ALLOWED_PURPOSES, true)) {
                $query .= " AND p.payment_for = :purpose";
                $params[':purpose'] = $purpose;
            }

            if ($startDate !== '') {
                $query .= " AND DATE(p.payment_date) >= :start_date";
                $params[':start_date'] = $startDate;
            }

            if ($endDate !== '') {
                $query .= " AND DATE(p.payment_date) <= :end_date";
                $params[':end_date'] = $endDate;
            }

            $query .= " ORDER BY p.payment_date DESC, p.id DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log("Error retrieving payments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count total filtered payment records for pagination calculations
     */
    public function getPaymentsCount(
        string $search = '',
        string $purpose = '',
        string $startDate = '',
        string $endDate = ''
    ): int {
        try {
            $query = "SELECT COUNT(p.id) as total 
                      FROM payments p
                      LEFT JOIN residents r ON p.resident_id = r.id
                      WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $query .= " AND (p.or_number LIKE :search_or 
                             OR p.payer_name LIKE :search_payer 
                             OR r.first_name LIKE :search_res_first 
                             OR r.last_name LIKE :search_res_last)";
                $params[':search_or'] = '%' . $search . '%';
                $params[':search_payer'] = '%' . $search . '%';
                $params[':search_res_first'] = '%' . $search . '%';
                $params[':search_res_last'] = '%' . $search . '%';
            }

            if ($purpose !== '' && in_array($purpose, self::ALLOWED_PURPOSES, true)) {
                $query .= " AND p.payment_for = :purpose";
                $params[':purpose'] = $purpose;
            }

            if ($startDate !== '') {
                $query .= " AND DATE(p.payment_date) >= :start_date";
                $params[':start_date'] = $startDate;
            }

            if ($endDate !== '') {
                $query .= " AND DATE(p.payment_date) <= :end_date";
                $params[':end_date'] = $endDate;
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ? (int)$result['total'] : 0;
        } catch (PDOException $e) {
            error_log("Error counting payments: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if an Official Receipt (O.R.) Number has already been recorded
     */
    public function isOrNumberTaken(string $orNumber, ?int $excludeId = null): bool {
        try {
            $query = "SELECT id FROM payments WHERE or_number = :or_number";
            if ($excludeId !== null) {
                $query .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':or_number', strtoupper(trim($orNumber)), PDO::PARAM_STR);
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
     * Record a new financial collection transaction securely
     * RESOLVED: Dynamically retrieves the resident's full name to satisfy NOT NULL table constraints on `payer_name`.
     */
    public function createPayment(array $data, int $actorId): bool {
        try {
            $query = "INSERT INTO payments (or_number, resident_id, payer_name, payment_for, amount, payment_date, received_by) 
                      VALUES (:or_number, :resident_id, :payer_name, :payment_for, :amount, :payment_date, :received_by)";
            
            $stmt = $this->db->prepare($query);

            $cleanOR = strtoupper(preg_replace('/[^a-zA-Z0-9-]/', '', trim($data['or_number'])));
            $residentId = !empty($data['resident_id']) ? (int)$data['resident_id'] : null;
            $payerName = !empty($data['payer_name']) ? strip_tags(trim($data['payer_name'])) : null;
            
            // Support both 'payment_for' and 'purpose' payload keys seamlessly
            $paymentForVal = $data['payment_for'] ?? $data['purpose'] ?? 'Miscellaneous Fee';
            $purpose = in_array($paymentForVal, self::ALLOWED_PURPOSES, true) ? $paymentForVal : 'Miscellaneous Fee';
            
            // DYNAMIC RESOLVER: Fetch resident name if resident_id is linked but payer_name is blank
            if ($residentId !== null && empty($payerName)) {
                $resQuery = "SELECT first_name, last_name FROM residents WHERE id = :res_id LIMIT 1";
                $resStmt = $this->db->prepare($resQuery);
                $resStmt->bindValue(':res_id', $residentId, PDO::PARAM_INT);
                $resStmt->execute();
                $res = $resStmt->fetch();
                if ($res) {
                    $payerName = trim($res['first_name'] . ' ' . $res['last_name']);
                } else {
                    $payerName = "Registered Resident #" . $residentId;
                }
            }

            // Fallback default name to protect NOT NULL constraint database limits
            if (empty($payerName)) {
                $payerName = "Anonymous Payer";
            }

            // Enforce safe cash currency floats
            $amount = round((float)$data['amount'], 2);
            if ($amount < 0.00) {
                $amount = 0.00;
            }

            // Fallback to current system timestamp if none provided
            $paymentDate = !empty($data['payment_date']) ? $data['payment_date'] : date('Y-m-d H:i:s');

            $stmt->bindValue(':or_number', $cleanOR, PDO::PARAM_STR);
            $stmt->bindValue(':resident_id', $residentId, PDO::PARAM_INT);
            $stmt->bindValue(':payer_name', $payerName, PDO::PARAM_STR);
            $stmt->bindValue(':payment_for', $purpose, PDO::PARAM_STR);
            $stmt->bindValue(':amount', $amount, PDO::PARAM_STR);
            $stmt->bindValue(':payment_date', $paymentDate, PDO::PARAM_STR);
            $stmt->bindValue(':received_by', $actorId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logActivity($actorId, "Issued Receipt [OR #{$cleanOR}]: PHP " . number_format($amount, 2) . " from " . $payerName);
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating payment record: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve aggregate financial summaries for the Treasurer dashboard metrics
     */
    public function getFinancialSummaries(): array {
        try {
            $stats = [
                'total_revenue' => 0.00,
                'today_collections' => 0.00,
                'total_receipts_issued' => 0,
                'clearance_shares' => 0.00
            ];

            // Single query compiling total cash flow metrics
            $query = "SELECT 
                        SUM(amount) as total_revenue,
                        SUM(CASE WHEN DATE(payment_date) = CURRENT_DATE THEN amount ELSE 0 END) as today_collections,
                        COUNT(id) as total_receipts,
                        SUM(CASE WHEN payment_for LIKE 'Barangay Clearance%' OR payment_for LIKE 'Business Clearance%' THEN amount ELSE 0 END) as clearances_total
                      FROM payments";

            $stmt = $this->db->query($query);
            $res = $stmt->fetch();
            if ($res) {
                $stats['total_revenue'] = (float)($res['total_revenue'] ?? 0.00);
                $stats['today_collections'] = (float)($res['today_collections'] ?? 0.00);
                $stats['total_receipts_issued'] = (int)($res['total_receipts'] ?? 0);
                $stats['clearance_shares'] = (float)($res['clearances_total'] ?? 0.00);
            }
            return $stats;
        } catch (PDOException $e) {
            error_log("Error generating payment summaries: " . $e->getMessage());
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
            error_log("Failed to write audit log from PaymentManager: " . $e->getMessage());
        }
    }
}
?>