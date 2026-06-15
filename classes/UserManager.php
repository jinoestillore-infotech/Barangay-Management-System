<?php
declare(strict_types=1);

/**
 * UserManager Class
 * Manages administrative accounts, status updates, edits, and security logs with enterprise-grade protection.
 */
class UserManager {
    private PDO $db;

    // Allowed schema values for strict validation
    private const ALLOWED_ROLES = ['Administrator', 'Barangay Captain', 'Secretary', 'Treasurer', 'Staff', 'Citizen'];
    private const ALLOWED_STATUSES = ['Active', 'Inactive', 'Suspended'];

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Fetch users with dynamic searching and filtering
     * Fixes the PDO named parameter reuse bug by splitting into unique placeholders.
     * * @param string $search
     * @param string $role
     * @param string $status
     * @return array
     */
    public function getUsers(string $search = '', string $role = '', string $status = ''): array {
        try {
            $query = "SELECT id, fullname, username, role, status, last_login, failed_attempts, lock_until, created_at 
                      FROM users WHERE 1=1";
            $params = [];

            if ($search !== '') {
                // FIXED: Using unique named placeholders (:search_fullname and :search_username) to comply with real prepared statements
                $query .= " AND (fullname LIKE :search_fullname OR username LIKE :search_username)";
                $params[':search_fullname'] = '%' . $search . '%';
                $params[':search_username'] = '%' . $search . '%';
            }

            if ($role !== '' && in_array($role, self::ALLOWED_ROLES, true)) {
                $query .= " AND role = :role";
                $params[':role'] = $role;
            }

            if ($status !== '' && in_array($status, self::ALLOWED_STATUSES, true)) {
                $query .= " AND status = :status";
                $params[':status'] = $status;
            }

            $query .= " ORDER BY created_at DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log("Error retrieving users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single user's detailed information
     * * @param int $id
     * @return array|bool User data array if found, false otherwise
     */
    public function getUserById(int $id) {
        try {
            $query = "SELECT id, fullname, username, role, status, resident_id, last_login, failed_attempts, lock_until, created_at 
                      FROM users WHERE id = :id LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch() ?: false;
        } catch (PDOException $e) {
            error_log("Error retrieving user ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new administrative user securely
     * * @param array $data
     * @param int $actorId
     * @return bool
     */
    public function createUser(array $data, int $actorId): bool {
        try {
            // Strict verification on roles and statuses
            $role = $data['role'] ?? 'Staff';
            $status = $data['status'] ?? 'Inactive';
            $residentId = !empty($data['resident_id']) ? (int)$data['resident_id'] : null;

            if (!in_array($role, self::ALLOWED_ROLES, true)) {
                $role = 'Staff';
            }
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                $status = 'Inactive';
            }

            $query = "INSERT INTO users (fullname, username, password, role, status, resident_id) 
                      VALUES (:fullname, :username, :password, :role, :status, :resident_id)";
            
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare($query);
            
            // Clean up inputs to prevent markup/log injection issues
            $cleanFullname = strip_tags(trim($data['fullname']));
            $cleanUsername = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($data['username']));

            $stmt->bindValue(':fullname', $cleanFullname, PDO::PARAM_STR);
            $stmt->bindValue(':username', $cleanUsername, PDO::PARAM_STR);
            $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
            $stmt->bindValue(':role', $role, PDO::PARAM_STR);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':resident_id', $residentId, $residentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Sanitize parameters securely before sending to logger
                $safeLogUsername = htmlspecialchars($cleanUsername, ENT_QUOTES, 'UTF-8');
                $safeLogFullname = htmlspecialchars($cleanFullname, ENT_QUOTES, 'UTF-8');
                $safeLogRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');

                $this->logActivity($actorId, "Created new system user: {$safeLogUsername} ({$safeLogFullname}) as {$safeLogRole}");
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
     * * @param int $id
     * @param array $data
     * @param int $actorId
     * @return bool
     */
    public function updateUser(int $id, array $data, int $actorId): bool {
        try {
            $role = $data['role'] ?? 'Staff';
            $status = $data['status'] ?? 'Active';
            $residentId = !empty($data['resident_id']) ? (int)$data['resident_id'] : null;

            if (!in_array($role, self::ALLOWED_ROLES, true)) {
                return false;
            }
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                return false;
            }

            // Build dynamic update query to handle optional password updates
            $query = "UPDATE users SET fullname = :fullname, role = :role, status = :status, resident_id = :resident_id";
            
            if (!empty($data['password'])) {
                $query .= ", password = :password, failed_attempts = 0, lock_until = NULL";
            }
            $query .= " WHERE id = :id";

            $stmt = $this->db->prepare($query);
            $cleanFullname = strip_tags(trim($data['fullname']));

            $stmt->bindValue(':fullname', $cleanFullname, PDO::PARAM_STR);
            $stmt->bindValue(':role', $role, PDO::PARAM_STR);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':resident_id', $residentId, $residentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if (!empty($data['password'])) {
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
            }

            if ($stmt->execute()) {
                $changeDetails = !empty($data['password']) ? " and reset password" : "";
                // Securely handle output variables to prevent log-traversal exploits
                $safeDetails = htmlspecialchars($changeDetails, ENT_QUOTES, 'UTF-8');
                $safeFullname = htmlspecialchars($cleanFullname, ENT_QUOTES, 'UTF-8');
                
                // FIXED: Log fullname instead of ID
                $this->logActivity($actorId, "Updated profile fields{$safeDetails} for: {$safeFullname}");
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
     * * @param int $id
     * @param string $status
     * @param int $actorId
     * @return bool
     */
    public function updateStatus(int $id, string $status, int $actorId): bool {
        try {
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                return false;
            }

            // Fetch target user fullname before executing update
            $targetUser = $this->getUserById($id);
            $targetName = $targetUser ? $targetUser['fullname'] : "User ID: " . $id;

            $query = "UPDATE users SET status = :status WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $safeStatus = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
                $safeTargetName = htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8');
                // FIXED: Log fullname instead of ID
                $this->logActivity($actorId, "Changed status of {$safeTargetName} to '{$safeStatus}'");
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
     * * @param int $id
     * @param int $actorId
     * @return bool
     */
    public function unlockAccount(int $id, int $actorId): bool {
        try {
            // Fetch target user fullname before executing unlock
            $targetUser = $this->getUserById($id);
            $targetName = $targetUser ? $targetUser['fullname'] : "User ID: " . $id;

            $query = "UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $safeTargetName = htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8');
                // FIXED: Log fullname instead of ID
                $this->logActivity($actorId, "Manually unlocked user account: {$safeTargetName}");
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
     * * @param string $username
     * @param int|null $excludeId
     * @return bool
     */
    public function isUsernameTaken(string $username, ?int $excludeId = null): bool {
        try {
            $query = "SELECT id FROM users WHERE username = :username";
            if ($excludeId !== null) {
                $query .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($query);
            
            $cleanUsername = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($username));
            $stmt->bindValue(':username', $cleanUsername, PDO::PARAM_STR);
            
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
     * Retrieve security audit logs with optional limit
     * * @param int $limit
     * @return array
     */
    public function getSecurityLogs(int $limit = 50): array {
        try {
            $query = "SELECT al.*, u.fullname, u.username, u.role 
                      FROM audit_logs al
                      LEFT JOIN users u ON al.user_id = u.id
                      ORDER BY al.created_at DESC LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log("Error retrieving logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Shared activity logging helper (Mitigates Log Injection and XSS)
     * * @param int $userId
     * @param string $action
     * @return void
     */
    private function logActivity(int $userId, string $action): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            
            // Mitigate CRLF / Log Injection by stripping carriage returns and newlines
            $cleanAction = preg_replace('/[\r\n]+/', ' ', trim($action));
            // Prevent HTML tags from ever executing in log outputs
            $cleanAction = strip_tags((string)$cleanAction);

            $query = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (:user_id, :action, :ip)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':action', $cleanAction, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to write audit log from UserManager: " . $e->getMessage());
        }
    }
}
?>