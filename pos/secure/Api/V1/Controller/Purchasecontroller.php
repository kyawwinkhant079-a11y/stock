<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Purchasemodels\Purchase;
use Secure\Api\V1\Models\Productmodels\Product;
use Secure\Api\V1\Models\Inventorymodels\Inventory;
use Secure\Api\V1\Models\Logmodels\Logs;
use Secure\Connection\Database;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Purchasecontroller
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
     * ဝယ်ယူမှု (Purchases) စာရင်းအားလုံးကို ဆွဲထုတ်ရန်
     */
    public function getPurchases()
    {
        $token = $this->input->validate("getPurchases");
        $decodedToken = self::$auth->decode($token);
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        try {
            $purchaseModel = new Purchase(self::$conn);
            $purchases = $purchaseModel->getAllPurchases($branchId);

            Response::sendbody([
                "status"  => 200,
                "message" => "Purchases retrieved successfully!",
                "data"    => $purchases
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve purchases: " . $e->getMessage()
            ]);
        }
    }

    /**
     * ဝယ်ယူမှု အသေးစိတ်ကို ID ဖြင့် ကြည့်ရန်
     */
    public function getPurchaseDetail()
    {
        $token = $this->input->validate("getPurchaseDetail");
        self::$auth->decode($token);

        $purchaseId = self::$userdata->purchase_id ?? self::$userdata->id ?? null;

        if (empty($purchaseId)) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Purchase ID is required!"
            ]);
            return;
        }

        try {
            $purchaseModel = new Purchase(self::$conn);
            $purchase = $purchaseModel->getPurchaseById($purchaseId);

            if (!$purchase) {
                Response::sendbody([
                    "status"  => 404,
                    "message" => "Purchase record not found!"
                ]);
                return;
            }

            Response::sendbody([
                "status"  => 200,
                "message" => "Purchase detail retrieved successfully!",
                "data"    => $purchase
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve purchase detail: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Supplier ဆီမှ ကုန်ပစ္စည်း အသစ်ဝယ်ယူခြင်း (Create Purchase Order / Received)
     */
    public function createPurchase()
    {
        $token = $this->input->validate("createPurchase");
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

        $supplierId   = self::$userdata->supplier_id ?? null;
        $invoiceNo    = self::$userdata->invoice_no ?? 'INV-' . time();
        $totalAmount  = (float)(self::$userdata->total_amount ?? 0.00);
        $status       = self::$userdata->status ?? 'received'; // pending, received, cancelled
        $purchaseDate = self::$userdata->purchase_date ?? date('Y-m-d');
        $items        = self::$userdata->items ?? [];

        if (empty($supplierId) || empty($items)) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Supplier ID and purchase items are required!"
            ]);
            return;
        }

        $purchaseModel  = new Purchase(self::$conn);
        $inventoryModel = new Inventory(self::$conn);
        $logModel       = new Logs(self::$conn);

        // Generate UUID for Purchase ID (CHAR 36)
        $purchaseId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        self::$conn->beginTransaction();

        try {
            // 1. Create Purchase and Purchase Items records
            $purchaseModel->createPurchase(
                $purchaseId, 
                $supplierId, 
                $branchId, 
                $userId, 
                $invoiceNo, 
                $totalAmount, 
                $status, 
                $purchaseDate, 
                $items
            );

            // 2. If status is 'received', automatically update branch stocks and log stock movements (IN)
            if ($status === 'received') {
                foreach ($items as $item) {
                    $productId = $item->product_id ?? $item->id ?? '';
                    $quantity  = (int)($item->quantity ?? 0);

                    // Get current stock in branch
                    $currentStock = $inventoryModel->getBranchStockQty($branchId, $productId);
                    $newStock = $currentStock + $quantity;

                    // Update branch stocks
                    $inventoryModel->updateBranchStock($branchId, $productId, $newStock);

                    // Log stock movement (IN)
                    $movementId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );

                    $inventoryModel->logStockMovement(
                        $movementId,
                        $branchId,
                        $productId,
                        $userId,
                        'IN',
                        $quantity,
                        "Purchase Order Received - Invoice: {$invoiceNo}"
                    );
                }
            }

            // 3. Log Audit Activity
            $logModel->logActivity(
                $branchId,
                $userId,
                'CREATE_PURCHASE',
                "Created purchase order (Invoice: {$invoiceNo}) with total amount: {$totalAmount} [Status: {$status}]",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Purchase order created successfully!",
                "data"    => [
                    "purchase_id" => $purchaseId,
                    "invoice_no"  => $invoiceNo
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to create purchase order: " . $e->getMessage()
            ]);
        }
    }
}

