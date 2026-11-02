<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Inventorymodels\Inventory;
use Secure\Api\V1\Models\Logmodels\Logs;
use Secure\Connection\Database;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Inventorycontroller
{
    private $input;
    public static $conn;
    public static $userdata;
    public static $auth;

    public function __construct($userdata)
    {
        Database::init();
        self::$conn = Database::getConnection();

        $this->input = new Userinputlayer($userdata);
        self::$userdata = $userdata;
        self::$auth = new Auth();
    }

    /**
     * Stock Movements (IN, OUT, ADJUSTMENT) စာရင်းများကို ကြည့်ရန်
     */
    public function getStockMovements()
    {
        $token = $this->input->validate("getStockMovements");
        $decodedToken = self::$auth->decode($token);
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        try {
            $inventoryModel = new Inventory(self::$conn);
            $movements = $inventoryModel->getStockMovements($branchId);

            Response::sendbody([
                "status"  => 200,
                "message" => "Stock movements retrieved successfully!",
                "data"    => $movements
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve stock movements: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Stock In သို့မဟုတ် Stock Out (Adjustments) ပြုလုပ်ရန်
     */
    public function adjustStock()
    {
        $token = $this->input->validate("adjustStock");
        $decodedToken = self::$auth->decode($token);
        
        $userId   = $decodedToken->id ?? null;
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        if (!$branchId || !$userId) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Branch ID and User ID are required!"
            ]);
            return;
        }

        $productId = self::$userdata->product_id ?? self::$userdata->id ?? '';
        $type      = strtoupper(self::$userdata->type ?? 'IN'); // IN, OUT, ADJUSTMENT
        $quantity  = (int)(self::$userdata->quantity ?? 0);
        $note      = self::$userdata->note ?? 'Stock adjustment';

        if (empty($productId) || $quantity <= 0) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Product ID and valid quantity are required!"
            ]);
            return;
        }

        $inventoryModel = new Inventory(self::$conn);
        $logModel       = new Logs(self::$conn);

        self::$conn->beginTransaction();

        try {
            $currentStock = $inventoryModel->getBranchStockQty($branchId, $productId);
            
            if ($type === 'IN') {
                $newStock = $currentStock + $quantity;
            } else if ($type === 'OUT') {
                $newStock = $currentStock - $quantity;
                if ($newStock < 0) {
                    throw new Exception("Insufficient stock available for reduction.");
                }
            } else { // ADJUSTMENT (Direct set or +/-)
                $newStock = $quantity; 
            }

            // Update branch_stocks
            $inventoryModel->updateBranchStock($branchId, $productId, $newStock);

            // Log stock movement
            $movementId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $inventoryModel->logStockMovement($movementId, $branchId, $productId, $userId, $type, $quantity, $note);

            // Log Audit Activity
            $logModel->logActivity(
                $branchId,
                $userId,
                "STOCK_" . $type,
                "Stock {$type} of quantity {$quantity} for product ID: {$productId}. Note: {$note}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Stock adjusted successfully!",
                "data"    => [
                    "product_id" => $productId,
                    "new_stock"  => $newStock
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Stock adjustment failed: " . $e->getMessage()
            ]);
        }
    }

    /**
     * ဆိုင်ခွဲများကြား ပစ္စည်းလွှဲပြောင်းရန် (Create Stock Transfer Request)
     */
    public function createTransfer()
    {
        $token = $this->input->validate("createTransfer");
        $decodedToken = self::$auth->decode($token);
        
        $userId       = $decodedToken->id ?? null;
        $fromBranchId = self::$userdata->from_branch_id ?? $decodedToken->branch_id ?? null;
        $toBranchId   = self::$userdata->to_branch_id ?? null;
        $productId    = self::$userdata->product_id ?? null;
        $quantity     = (int)(self::$userdata->quantity ?? 0);

        if (!$fromBranchId || !$toBranchId || !$productId || $quantity <= 0) {
            Response::sendbody([
                "status"  => 400,
                "message" => "From branch, To branch, Product, and valid quantity are required!"
            ]);
            return;
        }

        if ($fromBranchId === $toBranchId) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Cannot transfer stock to the same branch!"
            ]);
            return;
        }

        $inventoryModel = new Inventory(self::$conn);
        $logModel       = new Logs(self::$conn);

        self::$conn->beginTransaction();

        try {
            // Check if source branch has enough stock
            $currentStock = $inventoryModel->getBranchStockQty($fromBranchId, $productId);
            if ($currentStock < $quantity) {
                throw new Exception("Insufficient stock in source branch to transfer.");
            }

            $transferId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            // Create Transfer Record (Status: Pending)
            $inventoryModel->createStockTransfer($transferId, $fromBranchId, $toBranchId, $productId, $quantity, $userId);

            // Log Audit Activity
            $logModel->logActivity(
                $fromBranchId,
                $userId,
                "STOCK_TRANSFER_REQUEST",
                "Requested stock transfer of quantity {$quantity} to branch ID: {$toBranchId}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Stock transfer request created successfully!",
                "data"    => ["transfer_id" => $transferId]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Stock transfer request failed: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Stock Transfer အခြေအနေကို ပြင်ဆင်ရန် (Approved / Completed / Cancelled)
     */
    public function updateTransferStatus()
    {
        $token = $this->input->validate("updateTransferStatus");
        $decodedToken = self::$auth->decode($token);
        
        $userId     = $decodedToken->id ?? null;
        $transferId = self::$userdata->transfer_id ?? self::$userdata->id ?? null;
        $newStatus  = self::$userdata->status ?? null; // Approved, Completed, Cancelled

        if (!in_array($newStatus, ['Pending', 'Approved', 'Completed', 'Cancelled'])) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Invalid transfer status provided!"
            ]);
            return;
        }

        $inventoryModel = new Inventory(self::$conn);
        $logModel       = new Logs(self::$conn);

        self::$conn->beginTransaction();

        try {
            $transfer = $inventoryModel->getStockTransferById($transferId);
            if (!$transfer) {
                throw new Exception("Stock transfer record not found.");
            }

            $currentStatus = $transfer['status'];
            if ($currentStatus === 'Completed' || $currentStatus === 'Cancelled') {
                throw new Exception("Cannot modify a transfer that is already {$currentStatus}.");
            }

            // If status is changing to 'Completed', deduct from source and add to destination branch stocks
            if ($newStatus === 'Completed') {
                $fromBranch = $transfer['from_branch_id'];
                $toBranch   = $transfer['to_branch_id'];
                $productId  = $transfer['product_id'];
                $qty        = (int)$transfer['quantity'];

                // Deduct from source branch
                $fromStock = $inventoryModel->getBranchStockQty($fromBranch, $productId);
                if ($fromStock < $qty) {
                    throw new Exception("Source branch has insufficient stock to complete this transfer.");
                }
                $inventoryModel->updateBranchStock($fromBranch, $productId, $fromStock - $qty);

                // Add to destination branch
                $toStock = $inventoryModel->getBranchStockQty($toBranch, $productId);
                $inventoryModel->updateBranchStock($toBranch, $productId, $toStock + $qty);

                // Log movements for both branches
                $mId1 = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
                $mId2 = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

                $inventoryModel->logStockMovement($mId1, $fromBranch, $productId, $userId, 'OUT', $qty, "Stock Transfer Out to Branch: {$toBranch}");
                $inventoryModel->logStockMovement($mId2, $toBranch, $productId, $userId, 'IN', $qty, "Stock Transfer In from Branch: {$fromBranch}");
            }

            // Update status
            $inventoryModel->updateTransferStatus($transferId, $newStatus);

            // Log Audit Activity
            $logModel->logActivity(
                $transfer['from_branch_id'],
                $userId,
                "STOCK_TRANSFER_" . strtoupper($newStatus),
                "Updated stock transfer ID {$transferId} status to {$newStatus}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Stock transfer status updated to {$newStatus} successfully!"
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to update transfer status: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Stock Transfers စာရင်းကို ဆွဲထုတ်ရန်
     */
    public function getTransfers()
    {
        $token = $this->input->validate("getTransfers");
        $decodedToken = self::$auth->decode($token);
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        try {
            $inventoryModel = new Inventory(self::$conn);
            $transfers = $inventoryModel->getAllStockTransfers($branchId);

            Response::sendbody([
                "status"  => 200,
                "message" => "Stock transfers retrieved successfully!",
                "data"    => $transfers
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve stock transfers: " . $e->getMessage()
            ]);
        }
    }
}