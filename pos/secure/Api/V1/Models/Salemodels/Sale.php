<?php

namespace Secure\Api\V1\Models\Salemodels;

use Exception;
use PDO;

class Sale 
{
    private $conn;

    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    /**
     * ရောင်းချမှု မှတ်တမ်းများအားလုံးကို ဆွဲထုတ်ရန်
     */
    public function getSalesHistory($branchId = null)
    {
        if ($branchId) {
            $query = "SELECT s.*, u.name as user_name, b.name as branch_name 
                      FROM sales s
                      LEFT JOIN users u ON s.user_id = u.id
                      LEFT JOIN branches b ON s.branch_id = b.id
                      WHERE s.branch_id = :branch_id
                      ORDER BY s.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':branch_id' => $branchId]);
        } else {
            $query = "SELECT s.*, u.name as user_name, b.name as branch_name 
                      FROM sales s
                      LEFT JOIN users u ON s.user_id = u.id
                      LEFT JOIN branches b ON s.branch_id = b.id
                      ORDER BY s.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Invoice နံပါတ် သို့မဟုတ် Sale ID ဖြင့် အရောင်းအသေးစိတ်ကို ရှာရန်
     */
    public function getSaleDetailById($saleId)
    {
        $headerQuery = "SELECT s.*, u.name as user_name, b.name as branch_name 
                        FROM sales s
                        LEFT JOIN users u ON s.user_id = u.id
                        LEFT JOIN branches b ON s.branch_id = b.id
                        WHERE s.id = ? 
                        LIMIT 1";

        $stmtHeader = $this->conn->prepare($headerQuery);
        $stmtHeader->execute([$saleId]);
        $header = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            return null;
        }

        $itemsQuery = "SELECT 
                        si.*, 
                        p.name as product_name, 
                        p.barcode 
                       FROM sale_items si
                       LEFT JOIN products p ON si.product_id = p.id
                       WHERE si.sale_id = ?";

        $stmtItems = $this->conn->prepare($itemsQuery);
        $stmtItems->execute([$saleId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $header['items'] = $items;
        return $header;
    }

    /**
     * Sales နှင့် Sale Items ဇယားများထဲသို့ အချက်အလက်သွင်းရန်
     */
    public function createSale($saleId, $branchId, $userId, $totalAmount, $discount, $finalAmount, $paymentMethod, $items)
    {
        // 1. Insert into sales table
        $salesQuery = "INSERT INTO sales (
                        id, 
                        branch_id, 
                        user_id, 
                        total_amount, 
                        discount, 
                        final_amount, 
                        payment_method, 
                        created_at
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmtSale = $this->conn->prepare($salesQuery);
        $successSale = $stmtSale->execute([
            $saleId,
            $branchId,
            $userId,
            $totalAmount,
            $discount,
            $finalAmount,
            $paymentMethod
        ]);

        if (!$successSale) {
            throw new Exception("Failed to insert main sale record.");
        }

        // 2. Insert into sale_items table
        $itemQuery = "INSERT INTO sale_items (
                        id, 
                        sale_id, 
                        product_id, 
                        quantity, 
                        unit_price, 
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
            $unitPrice = (float)($item->unit_price ?? $item->price ?? 0.00);
            $subtotal  = $quantity * $unitPrice;

            $successItem = $stmtItem->execute([
                $itemId,
                $saleId,
                $productId,
                $quantity,
                $unitPrice,
                $subtotal
            ]);

            if (!$successItem) {
                throw new Exception("Failed to insert sale item record for product ID: " . $productId);
            }
        }

        return true;
    }
}