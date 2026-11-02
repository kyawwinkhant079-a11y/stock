<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Salemodels\Sale;
use Secure\Api\V1\Models\Productmodels\Product;
use Secure\Api\V1\Models\Logmodels\Logs;
use Secure\Connection\Database;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Salecontroller
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

    public function getSalesHistory()
    {
        $token = $this->input->validate("getSalesHistory");
        $decodedToken = self::$auth->decode($token);
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        try {
            $saleModel = new Sale(self::$conn);
            $salesData = $saleModel->getSalesHistory($branchId);

            Response::sendbody([
                "status"  => 200,
                "message" => "Sales history retrieved successfully",
                "data"    => $salesData
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to fetch sales history: " . $e->getMessage()
            ]);
        }
    }

    public function getSalesHistoryDetail()
    {
        $token = $this->input->validate("getSalesHistoryDetail");
        self::$auth->decode($token);

        $saleId = self::$userdata->sale_id ?? self::$userdata->id ?? null;

        if (empty($saleId)) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Sale ID is required!"
            ]);
            return;
        }

        try {
            $saleModel = new Sale(self::$conn);
            $saleDetail = $saleModel->getSaleDetailById($saleId);

            if (!$saleDetail) {
                Response::sendbody([
                    "status"  => 404,
                    "message" => "Sale record not found for ID: " . $saleId
                ]);
                return;
            }

            Response::sendbody([
                "status"  => 200,
                "message" => "Sale detail retrieved successfully",
                "data"    => $saleDetail
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to fetch sale detail: " . $e->getMessage()
            ]);
        }
    }

    public function processCheckout()
    {
        $token = $this->input->validate("processCheckout");
        $decodedToken = self::$auth->decode($token);
        
        $userId   = $decodedToken->id ?? null;
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        if (!$branchId || !$userId) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Branch ID and User ID are required for checkout!"
            ]);
            return;
        }

        $totalAmount   = (float)(self::$userdata->total_amount ?? 0.00);
        $discount      = (float)(self::$userdata->discount ?? 0.00);
        $finalAmount   = (float)(self::$userdata->final_amount ?? ($totalAmount - $discount));
        $paymentMethod = self::$userdata->payment_method ?? 'Cash';
        $items         = self::$userdata->items ?? [];

        if (empty($items)) {
            Response::sendbody([
                "status"  => 400,
                "message" => "No items provided for checkout!"
            ]);
            return;
        }

        $saleModel    = new Sale(self::$conn);
        $productModel = new Product(self::$conn);
        $logModel     = new Logs(self::$conn);

        // Generate UUID for Sale ID (CHAR 36)
        $saleId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        self::$conn->beginTransaction();

        try {
            // 1. Create Sale and Sale Items records
            $saleModel->createSale($saleId, $branchId, $userId, $totalAmount, $discount, $finalAmount, $paymentMethod, $items);

            // 2. Update Branch Stocks and Log Stock Movements (OUT)
            foreach ($items as $item) {
                $productId = $item->product_id ?? $item->id ?? '';
                $quantity  = (int)($item->quantity ?? 0);

                // Fetch current stock from branch_stocks table
                $currentProduct = $productModel->getProductById($productId, $branchId);
                
                if ($currentProduct) {
                    $currentStock = (int)($currentProduct['stock_quantity'] ?? 0);
                    $newStock = $currentStock - $quantity;
                    
                    if ($newStock < 0) {
                        throw new Exception("Insufficient stock for product: " . ($currentProduct['product_name'] ?? $productId));
                    }

                    // Update branch stocks
                    $productModel->updateBranchStock($branchId, $productId, $newStock);

                    // Generate UUID for stock movement
                    $movementId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );

                    // Log stock movement (OUT)
                    $productModel->logStockMovement(
                        $movementId,
                        $branchId,
                        $productId,
                        $userId,
                        'OUT',
                        $quantity,
                        "Sale Checkout - Sale ID: {$saleId}"
                    );
                } else {
                    throw new Exception("Product not found in inventory for ID: " . $productId);
                }
            }

            // 3. Log Audit Activity
            $logModel->logActivity(
                $branchId,
                $userId,
                'CHECKOUT_SALE',
                "Processed sale checkout with Final Amount: {$finalAmount} ({payments}: {$paymentMethod})",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Checkout processed successfully!",
                "data"    => [
                    "sale_id"      => $saleId,
                    "final_amount" => $finalAmount
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status"  => 500,
                "message" => "Checkout processing failed: " . $e->getMessage()
            ]);
        }
    }
}