<?php
/**
 * Authentication Class
 * Aligned with the database schema's security features, brute-force tracking, and Citizen profile linkages.
 */
class Authentication {
    private $db;
    private $max_attempts = 5;
    private $lockout_time = 900; // 15 Minutes (in seconds)

    public function __construct($databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Authenticate a system user or citizen with brute-force protection
     * @param string $username
     * @param string $password
     * @return string Status code: 'SUCCESS', 'INVALID', 'LOCKED', 'SUSPENDED', 'INACTIVE'
     */
    public function login($username, $password) {
        try {
            $query = "SELECT * FROM users WHERE username = :username LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return 'INVALID'; // Account doesn't exist
            }

            $user = $stmt->fetch();
            $currentTime = date('Y-m-d H:i:s');

            // 1. Check Lockout State
            if ($user['lock_until'] && strtotime($user['lock_until']) > strtotime($currentTime)) {
                return 'LOCKED';
            }

            // 2. Check Account Status State
            if ($user['status'] === 'Suspended') {
                return 'SUSPENDED';
            }
            if ($user['status'] === 'Inactive') {
                return 'INACTIVE';
            }

            // 3. Verify Password
            if (password_verify($password, $user['password'])) {
                // Login Success - Reset tracking counters
                $this->resetFailedAttempts($user['id']);
                
                // Initialize modern secure session variables
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['resident_id'] = !empty($user['resident_id']) ? (int)$user['resident_id'] : null; // Linkage to demographic profile
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; // e.g., 'Citizen', 'Administrator', etc.
                $_SESSION['profile_picture'] = $user['profile_picture'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];

                // Write to Audit Log
                $this->logActivity($user['id'], 'User logged in successfully.');

                return 'SUCCESS';
            } else {
                // Handle Login Failure
                $this->handleFailedAttempt($user);
                return 'INVALID';
            }

        } catch (PDOException $e) {
            error_log("Authentication Error: " . $e->getMessage());
            return 'ERROR';
        }
    }

    /**
     * Handles incrementing failure counter and locking accounts
     */
    private function handleFailedAttempt($user) {
        $userId = $user['id'];
        $newAttempts = $user['failed_attempts'] + 1;
        $lockUntil = null;

        if ($newAttempts >= $this->max_attempts) {
            $lockUntil = date('Y-m-d H:i:s', time() + $this->lockout_time);
            $newAttempts = 0; // Reset counter for the next window once unlocked
            $this->logActivity($userId, "Account locked automatically due to too many failed login attempts.");
        } else {
            $this->logActivity($userId, "Failed login attempt detected. Run count: {$newAttempts}.");
        }

        $query = "UPDATE users SET failed_attempts = :attempts, lock_until = :lock_until WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':attempts', $newAttempts, PDO::PARAM_INT);
        $stmt->bindParam(':lock_until', $lockUntil, PDO::PARAM_STR);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function resetFailedAttempts($userId) {
        $query = "UPDATE users SET failed_attempts = 0, lock_until = NULL, last_login = :last_login WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $now = date('Y-m-d H:i:s');
        $stmt->bindParam(':last_login', $now, PDO::PARAM_STR);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Helper system to write Audit Logs securely
     */
    public function logActivity($userId, $action) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $query = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (:user_id, :action, :ip)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action, PDO::PARAM_STR);
            $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to write audit log: " . $e->getMessage());
        }
    }

    /**
     * Validate session sanity against Hijacking
     * Prevents access if session variables are missing or if agent/IP changed mid-session
     * @return bool
     */
    public static function checkSessionValidity() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Basic verification checks to prevent session hijacking
        if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            return false;
        }

        if (!isset($_SESSION['user_ip']) || $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
            return false;
        }

        return true;
    }

    /**
     * Destroys current session elements securely
     */
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'User logged out securely.');
        }
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
?>