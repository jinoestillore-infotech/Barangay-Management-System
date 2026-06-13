<?php
declare(strict_types=1);

/**
 * InventoryManager Class
 * Manages Barangay-owned Physical Assets, equipment tracking, safety stocks, and audits.
 */
class InventoryManager {
    private PDO $db;

    // FIXED: Synchronized perfectly with database.sql ENUM('Excellent', 'Good', 'Fair', 'Damaged', 'Unusable')
    private const ALLOWED_CONDITIONS = ['Excellent', 'Good', 'Fair', 'Damaged', 'Unusable'];

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Fetch asset inventory with dynamic searching, condition filtering, and pagination
     * NOTE: `condition` is a reserved MySQL keyword and must always be escaped with backticks.
     */
    public function getInventory(
        string $search = '',
        string $condition = '',
        int $limit = 10,
        int $offset = 0
    ): array {
        try {
            $query = "SELECT * FROM inventory WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $query .= " AND (asset_code LIKE :search_code OR item_name LIKE :search_name OR location LIKE :search_loc)";
                $params[':search_code'] = '%' . $search . '%';
                $params[':search_name'] = '%' . $search . '%';
                $params[':search_loc'] = '%' . $search . '%';
            }

            if ($condition !== '' && in_array($condition, self::ALLOWED_CONDITIONS, true)) {
                $query .= " AND `condition` = :condition";
                $params[':condition'] = $condition;
            }

            $query .= " ORDER BY item_name ASC LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            
            // Explicitly bind integer limits to avoid syntax evaluation errors in strict PDO modes
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log("Error retrieving inventory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get count of filtered inventory records for server-side pagination limits
     */
    public function getInventoryCount(string $search = '', string $condition = ''): int {
        try {
            $query = "SELECT COUNT(id) as total FROM inventory WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $query .= " AND (asset_code LIKE :search_code OR item_name LIKE :search_name OR location LIKE :search_loc)";
                $params[':search_code'] = '%' . $search . '%';
                $params[':search_name'] = '%' . $search . '%';
                $params[':search_loc'] = '%' . $search . '%';
            }

            if ($condition !== '' && in_array($condition, self::ALLOWED_CONDITIONS, true)) {
                $query .= " AND `condition` = :condition";
                $params[':condition'] = $condition;
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return $result ? (int)$result['total'] : 0;
        } catch (PDOException $e) {
            error_log("Error counting filtered inventory: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch a single inventory item by ID
     */
    public function getItemById(int $id) {
        try {
            $query = "SELECT * FROM inventory WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch() ?: false;
        } catch (PDOException $e) {
            error_log("Error retrieving inventory item ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if an Asset Code is already registered (excluding a specific ID for updates)
     */
    public function isAssetCodeTaken(string $code, ?int $excludeId = null): bool {
        try {
            $query = "SELECT id FROM inventory WHERE asset_code = :code";
            if ($excludeId !== null) {
                $query .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':code', strtoupper(trim($code)), PDO::PARAM_STR);
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
     * Register a new logistical asset securely
     */
    public function createItem(array $data, int $actorId): bool {
        try {
            $query = "INSERT INTO inventory (asset_code, item_name, quantity, available_quantity, `condition`, location, notes) 
                      VALUES (:asset_code, :item_name, :quantity, :available_quantity, :condition, :location, :notes)";
            
            $stmt = $this->db->prepare($query);

            $cleanCode = strtoupper(preg_replace('/[^a-zA-Z0-9-]/', '', trim($data['asset_code'])));
            $cleanName = strip_tags(trim($data['item_name']));
            
            // Business Rule: Total stocks cannot be negative or 0
            $quantity = max(1, (int)$data['quantity']);
            // Available stocks can never be lower than 0 or higher than total stocks
            $available = max(0, min($quantity, (int)$data['available_quantity']));
            
            $condition = in_array($data['condition'], self::ALLOWED_CONDITIONS, true) ? $data['condition'] : 'Good';
            $location = !empty($data['location']) ? strip_tags(trim($data['location'])) : null;
            $notes = !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null;

            $stmt->bindValue(':asset_code', $cleanCode, PDO::PARAM_STR);
            $stmt->bindValue(':item_name', $cleanName, PDO::PARAM_STR);
            $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindValue(':available_quantity', $available, PDO::PARAM_INT);
            $stmt->bindValue(':condition', $condition, PDO::PARAM_STR);
            $stmt->bindValue(':location', $location, PDO::PARAM_STR);
            $stmt->bindValue(':notes', $notes, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $this->logActivity($actorId, "Added new logistical asset: [{$cleanCode}] {$cleanName}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating inventory item: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing logistical asset record securely
     */
    public function updateItem(int $id, array $data, int $actorId): bool {
        try {
            $query = "UPDATE inventory 
                      SET asset_code = :asset_code, item_name = :item_name, quantity = :quantity, 
                          available_quantity = :available_quantity, `condition` = :condition, 
                          location = :location, notes = :notes 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);

            $cleanCode = strtoupper(preg_replace('/[^a-zA-Z0-9-]/', '', trim($data['asset_code'])));
            $cleanName = strip_tags(trim($data['item_name']));
            
            $quantity = max(1, (int)$data['quantity']);
            $available = max(0, min($quantity, (int)$data['available_quantity']));
            
            $condition = in_array($data['condition'], self::ALLOWED_CONDITIONS, true) ? $data['condition'] : 'Good';
            $location = !empty($data['location']) ? strip_tags(trim($data['location'])) : null;
            $notes = !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null;

            $stmt->bindValue(':asset_code', $cleanCode, PDO::PARAM_STR);
            $stmt->bindValue(':item_name', $cleanName, PDO::PARAM_STR);
            $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindValue(':available_quantity', $available, PDO::PARAM_INT);
            $stmt->bindValue(':condition', $condition, PDO::PARAM_STR);
            $stmt->bindValue(':location', $location, PDO::PARAM_STR);
            $stmt->bindValue(':notes', $notes, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logActivity($actorId, "Updated details of asset: [{$cleanCode}] {$cleanName}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error updating inventory item ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an inventory item (Requires Administrative Privilege)
     */
    public function deleteItem(int $id, int $actorId): bool {
        try {
            $item = $this->getItemById($id);
            if (!$item) {
                return false;
            }

            $query = "DELETE FROM inventory WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logActivity($actorId, "Permanently deleted asset record: [{$item['asset_code']}] {$item['item_name']}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error deleting inventory item ID {$id}: " . $e->getMessage());
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
            error_log("Failed to write audit log from InventoryManager: " . $e->getMessage());
        }
    }
}
?>