<?php
declare(strict_types=1);

// Secure route guarding (Allows Administrators, Captains, Secretaries, Treasurers, and Staff to interact)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary', 'Treasurer', 'Staff']);

require_once '../../classes/Database.php';
require_once '../../classes/InventoryManager.php';

$database = new Database();
$conn = $database->connect();
$inventoryManager = new InventoryManager($conn);

$actorId = (int)$_SESSION['user_id'];

// --------------------------------------------------------
// AJAX API ENDPOINTS FOR MODALS
// --------------------------------------------------------
if (isset($_GET['fetch_item'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['fetch_item'];
    $itemData = $inventoryManager->getItemById($id);
    echo json_encode($itemData ? $itemData : ['error' => 'Asset specifications not found']);
    exit;
}

// --------------------------------------------------------
// FORM POST REQUEST PROCESSORS
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD NEW INVENTORY LOGISTIC
    if (isset($_POST['action']) && $_POST['action'] === 'add_item') {
        $code = trim($_POST['asset_code']);

        if ($inventoryManager->isAssetCodeTaken($code)) {
            $_SESSION['error_flash'] = "Registration failed! Asset code '{$code}' is already registered to another physical inventory item.";
        } else {
            // FIXED: Added 'available_quantity' to record input values correctly upon creation
            $data = [
                'asset_code' => $code,
                'item_name' => $_POST['item_name'],
                'quantity' => $_POST['quantity'],
                'available_quantity' => $_POST['available_quantity'] ?? $_POST['quantity'],
                'condition' => $_POST['condition'],
                'location' => $_POST['location'],
                'notes' => $_POST['notes']
            ];

            if ($inventoryManager->createItem($data, $actorId)) {
                $_SESSION['success_flash'] = "Asset '{$_POST['item_name']}' successfully cataloged in the logistics database!";
            } else {
                $_SESSION['error_flash'] = "An error occurred while saving the inventory asset.";
            }
        }
    }

    // 2. MODIFY SPECIFICATIONS
    if (isset($_POST['action']) && $_POST['action'] === 'edit_item') {
        // FIXED: Corrected parameter key from 'id' to 'edit_id' to align with the frontend HTML form inputs
        $id = (int)$_POST['edit_id'];
        $code = trim($_POST['asset_code']);

        if ($inventoryManager->isAssetCodeTaken($code, $id)) {
            $_SESSION['error_flash'] = "Update failed! The asset code '{$code}' is already assigned to a different logistical asset.";
        } else {
            $data = [
                'asset_code' => $code,
                'item_name' => $_POST['item_name'],
                'quantity' => $_POST['quantity'],
                'available_quantity' => $_POST['available_quantity'],
                'condition' => $_POST['condition'],
                'location' => $_POST['location'],
                'notes' => $_POST['notes']
            ];

            if ($inventoryManager->updateItem($id, $data, $actorId)) {
                $_SESSION['success_flash'] = "Logistical asset specifications updated successfully.";
            } else {
                $_SESSION['error_flash'] = "An error occurred inside the system while saving updates.";
            }
        }
    }

    // 3. DELETE ITEM
    if (isset($_POST['action']) && $_POST['action'] === 'delete_item') {
        // FIXED: Corrected parameter key from 'id' to 'delete_id' to align with the delete modal inputs
        $id = (int)$_POST['delete_id'];

        if ($inventoryManager->deleteItem($id, $actorId)) {
            $_SESSION['success_flash'] = "Asset has been successfully erased from the logistical inventory ledgers.";
        } else {
            $_SESSION['error_flash'] = "Failed to delete the asset from database.";
        }
    }

    // Redirect back cleanly
    header('Location: index.php');
    exit;
}

// Redirect home on direct GET attempts
header("Location: index.php");
exit;