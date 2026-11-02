<?php

namespace Secure\Api\V1\Models\Purchasemodels;

use Exception;
use PDO;

class Purchase 
{
    private $conn;

    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    /**
     * ဝယ်ယူမှု (Purchases) စာရင်းအားလုံးကို ဆွဲထုတ်ရန်
     */
    public function getAllPurchases($branchId = null)
    {
        if ($branchId) {
            $query = "SELECT p.*, s.name as supplier_name, b.name as branch_name, u.name as user_name
                      FROM purchases p
                      LEFT JOIN suppliers s ON p.supplier_id = s.id
                      LEFT JOIN branches b ON p.branch_id = b.id
                      LEFT JOIN users u ON p.user_id = u.id
                      WHERE p.branch_id = :branch_id
                      ORDER BY p.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':branch_id' => $branchId]);
        } else {
            $query = "SELECT p.*, s.name as supplier_name, b.name as branch_name, u.name as user_name
                      FROM purchases p
                      LEFT JOIN suppliers s ON p.supplier_id = s.id
                      LEFT JOIN branches b ON p.branch_id = b.id
                      LEFT JOIN users u ON p.user_id = u.id
                      ORDER BY p.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Purchase ID ဖြင့် ဝယ်ယူမှု အသေးစိတ်ကို ဆွဲထုတ်ရန် (Items များနှင့်တကွ)
     */
    public function getPurchaseById($purchaseId)
    {
        $query = "SELECT p.*, s.name as supplier_name, b.name as branch_name, u.name as user_name
                  FROM purchases p
                  LEFT JOIN suppliers s ON p.supplier_id = s.id
                  LEFT JOIN branches b ON p.branch_id = b.id
                  LEFT JOIN users u ON p.user_id = u.id
                  WHERE p.id = ? 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$purchaseId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$purchase) {
            return null;
        }

        $itemsQuery = "SELECT pi.*, pr.name as product_name, pr.barcode
                       FROM purchase_items pi
                       LEFT JOIN products pr ON pi.product_id = pr.id
                       WHERE pi.purchase_id = ?";

        $stmtItems = $this->conn->prepare($itemsQuery);
        $stmtItems->execute([$purchaseId]);
        $purchase['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        return $purchase;
    }

    /**
     * Purchases နှင့် Purchase Items ဇယားများထဲသို့ အချက်အလက်သွင်းရန်
     */
    public function createPurchase($purchaseId, $supplierId, $branchId, $userId, $invoiceNo, $totalAmount, $status, $purchaseDate, $items)
    {
        // 1. Insert into purchases table
        $purchaseQuery = "INSERT INTO purchases (
                            id, 
                            supplier_id, 
                            branch_id, 
                            user_id, 
                            invoice_no, 
                            total_amount, 
                            status, 
                            purchase_date, 
                            created_at
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmtPurchase = $this->conn->prepare($purchaseQuery);
        $successPurchase = $stmtPurchase->execute([
            $purchaseId,
            $supplierId,
            $branchId,
            $userId,
            $invoiceNo,
            $totalAmount,
            $status,
            $purchaseDate
        ]);

        if (!$successPurchase) {
            throw new Exception("Failed to insert main purchase record.");
        }

        // 2. Insert into purchase_items table
        $itemQuery = "INSERT INTO purchase_items (
                        id, 
                        purchase_id, 
                        product_id, 
                        quantity, 
                        unit_cost, 
                        subtotal
                      ) VALUES (?, ?, ?, ?, ?, ?)";

        $stmtItem = $this->conn->prepare($itemQuery);

        foreach ($items as $item) {
            $itemId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $productId = $item->product_id ?? $item->id ?? '';
            $quantity  = (int)($item->quantity ?? 0);
            $unitCost  = (float)($item->unit_cost ?? $item->cost_price ?? 0.00);
            $subtotal  = $quantity * $unitCost;

            $successItem = $stmtItem->execute([
                $itemId,
                $purchaseId,
                $productId,
                $quantity,
                $unitCost,
                $subtotal
            ]);

            if (!$successItem) {
                throw new Exception("Failed to insert purchase item record for product ID: " . $productId);
            }
        }

        return true;
    }

    /**
     * Purchase ၏ Status ကို ပြင်ဆင်ရန် (pending, received, cancelled)
     */
    public function updatePurchaseStatus($purchaseId, $status)
    {
        $query = "UPDATE purchases SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$status, $purchaseId]);
    }
}