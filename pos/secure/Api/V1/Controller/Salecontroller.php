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
        self::$auth->decode($token);

        try {
            $saleModel = new Sale(self::$conn);
            $salesData = $saleModel->getSalesHistory();

            Response::sendbody([
                "status"  => 200,
                "message" => "Sales history summary retrieved successfully",
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

        $invoiceNo = self::$userdata->invoice_no ?? null;

        if (empty($invoiceNo)) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Invoice number is required!"
            ]);
            return;
        }

        try {
            $saleModel = new Sale(self::$conn);
            $saleDetail = $saleModel->getSaleDetailByInvoice($invoiceNo);

            if (!$saleDetail) {
                Response::sendbody([
                    "status"  => 404,
                    "message" => "Sale record not found for invoice: " . $invoiceNo
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
        
        $username = $decodedToken->username ?? 'Unknown';
        $invoiceNo = self::$userdata->invoice_no;
        $paymentMethod = self::$userdata->payment_method;
        $items = self::$userdata->items;

        $saleModel = new Sale(self::$conn);
        $productModel = new Product(self::$conn);
        $logModel = new Logs(self::$conn);

        self::$conn->beginTransaction();

        try {

            $saleModel->createSaleRecords($invoiceNo, $paymentMethod, $username, $items);

            foreach ($items as $item) {
                $currentProduct = $productModel->getProductByUuid($item->product_uuid);
                
                if ($currentProduct) {
                    $newStock = $currentProduct['stock_quantity'] - $item->quantity;
                    if ($newStock < 0) {
                        $newStock = 0; 
                    }

                    $productModel->updateProductStock($item->product_uuid, $newStock);

                    $productModel->logStockTransaction(
                        $item->product_uuid,
                        $item->product_name,
                        $currentProduct['category_name'] ?? 'General',
                        'OUT',
                        $item->quantity,
                        $username
                    );
                }
            }

            $logModel->logActivity(
                'CHECKOUT_SALE',
                'sales',
                $invoiceNo,
                "Processed sale checkout for Invoice: {$invoiceNo} (Items count: " . count($items) . ")",
                null,
                [
                    "invoice_no" => $invoiceNo,
                    "total_amount" => self::$userdata->total_amount,
                    "payment_method" => $paymentMethod
                ],
                $username
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Checkout processed and sale records created successfully!"
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Checkout processing failed: " . $e->getMessage()
            ]);
        }
    }
}