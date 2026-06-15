<?php
/**
 * Dashboard Class
 * Handles database operations for dashboard metrics and operational overview data.
 */
class Dashboard {
    private $db;

    /**
     * @param PDO $databaseConnection Pass the active PDO database connection
     */
    public function __construct($databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Retrieves counts for residents, blotters, pending certificates, and active staff.
     * @return array Associative array of statistics
     */
    public function getQuickStats() {
        $stats = [
            'total_residents' => 0,
            'active_blotters'  => 0,
            'pending_certs'    => 0,
            'active_staff'     => 0
        ];

        try {
            // 1. Count Residents
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM residents WHERE status = 'Active'");
            $stats['total_residents'] = $stmt->fetch()['total'] ?? 0;

            // 2. Count Active Blotters
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM blotter_records WHERE status = 'Active'");
            $stats['active_blotters'] = $stmt->fetch()['total'] ?? 0;

            // 3. Count Pending Certificates
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM certificates WHERE status = 'Pending'");
            $stats['pending_certs'] = $stmt->fetch()['total'] ?? 0;

            // 4. Count Active Staff Users
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM users WHERE status = 'Active' AND role != 'Citizen'");
            $stats['active_staff'] = $stmt->fetch()['total'] ?? 0;

        } catch (PDOException $e) {
            // Log error silently, return default array metrics (zeros) to prevent crashes
            error_log("Dashboard Stats Error: " . $e->getMessage());
        }

        return $stats;
    }
}
?>