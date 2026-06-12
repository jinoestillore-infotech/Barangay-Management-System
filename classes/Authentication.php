<?php
/**
 * Authentication Class
 * Manages user logins, registration, session security, and authorization.
 */

class Authentication {
    private $db;
    private $user_table = "users";

    public function __construct($databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Securely authenticate a user
     * @param string $username
     * @param string $password
     * @return array|bool User data array if authenticated, false otherwise
     */
    public function login($username, $password) {
        try {
            $query = "SELECT u.*, r.role_name 
                      FROM " . $this->user_table . " u
                      JOIN roles r ON u.role_id = r.id
                      WHERE u.username = :username AND u.status = 'active'
                      LIMIT 1";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();

                // Verify the hashed password
                if (password_verify($password, $user['password'])) {
                    
                    // Prevent session fixation attacks by regenerating session ID
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    session_regenerate_id(true);

                    // Set secure session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['role_name'] = $user['role_name'];
                    
                    // Extra security: bind session to user-agent and IP to mitigate hijacking
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];

                    return $user;
                }
            }
            return false;
        } catch (PDOException $e) {
            error_log("Authentication Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new administrative user with a securely hashed password
     * @param array $userData
     * @return bool
     */
    public function registerUser($userData) {
        try {
            $query = "INSERT INTO " . $this->user_table . " 
                      (username, password, first_name, last_name, email, role_id, status) 
                      VALUES (:username, :password, :first_name, :last_name, :email, :role_id, :status)";

            $stmt = $this->db->prepare($query);

            // Hash password using default algorithm (currently bcrypt)
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            $status = isset($userData['status']) ? $userData['status'] : 'active';

            $stmt->bindParam(':username', $userData['username'], PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $stmt->bindParam(':first_name', $userData['first_name'], PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $userData['last_name'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $userData['email'], PDO::PARAM_STR);
            $stmt->bindParam(':role_id', $userData['role_id'], PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("User Registration Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Destroy session and log out
     */
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = array(); // Clear all session data

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Validate session sanity against Hijacking
     * @return bool
     */
    public static function checkSessionValidity() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Check if user agent or IP has suddenly changed mid-session
        if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT'] || 
            $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
            return false;
        }

        return true;
    }
}
?>