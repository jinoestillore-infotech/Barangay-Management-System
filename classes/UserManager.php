<?php
/**
 * UserManager Class
 * Manages administrative accounts, status updates, edits, and security logs.
 */
class UserManager {
    private $db;

    public function __construct($databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Fetch users with dynamic searching and filtering
     */
    public function getUsers($search = '', $role = '', $status = '') {
        try {
            $query = "SELECT id, fullname, username, role, status, last_login, failed_attempts, lock_until, created_at 
                      FROM users WHERE 1=1";
            $params = [];

            if (!empty($search)) {
                $query .= " AND (fullname LIKE :search OR username LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }

            if (!empty($role)) {
                $query .= " AND role = :role";
                $params[':role'] = $role;
            }

            if (!empty($status)) {
                $query .= " AND status = :status";
                $params[':status'] = $status;
            }

            $query .= " ORDER BY created_at DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error retrieving users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single user's detailed information
     */
    public function getUserById($id) {
        try {
            $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error retrieving user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new administrative user securely
     */
    public function createUser($data, $actorId) {
        try {
            $query = "INSERT INTO users (fullname, username, password, role, status) 
                      VALUES (:fullname, :username, :password, :role, :status)";
            
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':fullname', $data['fullname'], PDO::PARAM_STR);
            $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $stmt->bindParam(':role', $data['role'], PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $newUserId = $this->db->lastInsertId();
                $this->logActivity($actorId, "Created new system user: {$data['username']} ({$data['fullname']}) as {$data['role']}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing user's information securely
     */
    public function updateUser($id, $data, $actorId) {
        try {
            // Build dynamic update query to handle optional password updates
            $query = "UPDATE users SET fullname = :fullname, role = :role, status = :status";
            
            if (!empty($data['password'])) {
                $query .= ", password = :password, failed_attempts = 0, lock_until = NULL"; // password reset also unlocks account
            }
            $query .= " WHERE id = :id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':fullname', $data['fullname'], PDO::PARAM_STR);
            $stmt->bindParam(':role', $data['role'], PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if (!empty($data['password'])) {
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            }

            if ($stmt->execute()) {
                $changeDetails = !empty($data['password']) ? "and reset password" : "";
                $this->logActivity($actorId, "Updated profile fields {$changeDetails} for User ID: {$id}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error updating user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update only the account status (Active, Inactive, Suspended)
     */
    public function updateStatus($id, $status, $actorId) {
        try {
            $query = "UPDATE users SET status = :status WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $this->logActivity($actorId, "Changed status of User ID: {$id} to '{$status}'");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error updating user status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unlock a locked account manually
     */
    public function unlockAccount($id, $actorId) {
        try {
            $query = "UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $this->logActivity($actorId, "Manually unlocked user account ID: {$id}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error unlocking user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a username is already taken
     */
    public function isUsernameTaken($username, $excludeId = null) {
        try {
            $query = "SELECT id FROM users WHERE username = :username";
            if ($excludeId) {
                $query .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            if ($excludeId) {
                $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Retrieve security audit logs with optional limit
     */
    public function getSecurityLogs($limit = 50) {
        try {
            $query = "SELECT al.*, u.fullname, u.username, u.role 
                      FROM audit_logs al
                      LEFT JOIN users u ON al.user_id = u.id
                      ORDER BY al.created_at DESC LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error retrieving logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Shared activity logging helper
     */
    private function logActivity($userId, $action) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $query = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (:user_id, :action, :ip)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action, PDO::PARAM_STR);
            $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to write audit log from UserManager: " . $e->getMessage());
        }
    }
}
?>