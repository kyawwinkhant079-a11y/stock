<?php

namespace Secure\Api\V1\Models\Inventorymodels;

use Exception;
use PDO;

class Inventory 
{
    private $conn;

    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    /**
     * Stock Movements (IN, OUT, ADJUSTMENT) များကို ဆွဲထုတ်ရန်
     */
    public function getStockMovements($branchId = null)
    {
        if ($branchId) {
            $query = "SELECT sm.*, p.name as product_name, b.name as branch_name, u.name as user_name
                      FROM stock_movements sm
                      LEFT JOIN products p ON sm.product_id = p.id
                      LEFT JOIN branches b ON sm.branch_id = b.id
                      LEFT JOIN users u ON sm.user_id = u.id
                      WHERE sm.branch_id = :branch_id
                      ORDER BY sm.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':branch_id' => $branchId]);
        } else {
            $query = "SELECT sm.*, p.name as product_name, b.name as branch_name, u.name as user_name
                      FROM stock_movements sm
                      LEFT JOIN products p ON sm.product_id = p.id
                      LEFT JOIN branches b ON sm.branch_id = b.id
                      LEFT JOIN users u ON sm.user_id = u.id
                      ORDER BY sm.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Stock Movement မှတ်တမ်းအသစ်သွင်းရန်
     */
    public function logStockMovement($movementId, $branchId, $productId, $userId, $type, $quantity, $note)
    {
        $query = "INSERT INTO stock_movements (
                    id, branch_id, product_id, user_id, type, quantity, note, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $movementId,
            $branchId,
            $productId,
            $userId,
            strtoupper($type), 
            $quantity,
            $note
        ]);
    }

    /**
     * Branch Stocks များကို ချိန်ညှိရန် (Update or Insert)
     */
    public function updateBranchStock($branchId, $productId, $newStock)
    {
        $query = "INSERT INTO branch_stocks (id, branch_id, product_id, stock_qty, updated_at) 
                  VALUES (:id, :branch_id, :product_id, :stock_qty, NOW())
                  ON DUPLICATE KEY UPDATE stock_qty = :stock_qty, updated_at = NOW()";
        
        $stmt = $this->conn->prepare($query);
        $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        return $stmt->execute([
            ':id'         => $id,
            ':branch_id'  => $branchId,
            ':product_id' => $productId,
            ':stock_qty'  => $newStock
        ]);
    }

    /**
     * ဆိုင်ခွဲတစ်ခုမှ တစ်ခုသို့ ပစ္စည်းလွှဲပြောင်းမှု (Stock Transfer) ဖန်တီးရန်
     */
    public function createStockTransfer($transferId, $fromBranchId, $toBranchId, $productId, $quantity, $userId)
    {
        $query = "INSERT INTO stock_transfers (
                    id, from_branch_id, to_branch_id, product_id, quantity, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $transferId,
            $fromBranchId,
            $toBranchId,
            $productId,
            $quantity,
            $userId
        ]);
    }

    /**
     * Stock Transfers စာရင်းအားလုံးကို ဆွဲထုတ်ရန်
     */
    public function getAllStockTransfers($branchId = null)
    {
        if ($branchId) {
            $query = "SELECT st.*, 
                             fb.name as from_branch_name, 
                             tb.name as to_branch_name, 
                             p.name as product_name, 
                             u.name as creator_name
                      FROM stock_transfers st
                      LEFT JOIN branches fb ON st.from_branch_id = fb.id
                      LEFT JOIN branches tb ON st.to_branch_id = tb.id
                      LEFT JOIN products p ON st.product_id = p.id
                      LEFT JOIN users u ON st.created_by = u.id
                      WHERE st.from_branch_id = :branch_id OR st.to_branch_id = :branch_id
                      ORDER BY st.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':branch_id' => $branchId]);
        } else {
            $query = "SELECT st.*, 
                             fb.name as from_branch_name, 
                             tb.name as to_branch_name, 
                             p.name as product_name, 
                             u.name as creator_name
                      FROM stock_transfers st
                      LEFT JOIN branches fb ON st.from_branch_id = fb.id
                      LEFT JOIN branches tb ON st.to_branch_id = tb.id
                      LEFT JOIN products p ON st.product_id = p.id
                      LEFT JOIN users u ON st.created_by = u.id
                      ORDER BY st.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Transfer ID ဖြင့် အချက်အလက်ရှာရန်
     */
    public function getStockTransferById($transferId)
    {
        $query = "SELECT * FROM stock_transfers WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$transferId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Stock Transfer ၏ Status ကို ပြင်ဆင်ရန် (Pending, Approved, Completed, Cancelled)
     */
    public function updateTransferStatus($transferId, $status)
    {
        $query = "UPDATE stock_transfers SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$status, $transferId]);
    }

    /**
     * သတ်မှတ်ထားသော Branch တွင် Product ၏ လက်ရှိ Stock အရေအတွက်ကို ရှာရန်
     */
    public function getBranchStockQty($branchId, $productId)
    {
        $query = "SELECT stock_qty FROM branch_stocks WHERE branch_id = ? AND product_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$branchId, $productId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['stock_qty'] : 0;
    }
}