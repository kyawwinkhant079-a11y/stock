<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Productmodels\Product;
use Secure\Api\V1\Models\Logmodels\Logs;
use Secure\Connection\Database;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Productcontroller
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

    public function getStockTransactions()
    {
        $token = $this->input->validate("getStockTransactions");
        $decodedToken = self::$auth->decode($token);
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        $productModel = new Product(self::$conn);

        try {
            $transactions = $productModel->getStockMovements($branchId);

            Response::sendbody([
                "status"  => 200,
                "message" => "Stock movements retrieved successfully!",
                "data"    => $transactions
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve stock movements: " . $e->getMessage()
            ]);
        }
    }

    public function updateStock()
    {
        $token = $this->input->validate("updateStock");
        $decodedToken = self::$auth->decode($token);
        $username = $decodedToken->username ?? 'Unknown User';
        $userId   = $decodedToken->id ?? null;
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        if (!$branchId) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Branch ID is required for stock updates!"
            ]);
            return;
        }

        $pData = self::$userdata;
        $productModel = new Product(self::$conn);
        $logModel = new Logs(self::$conn);

        $productId = $pData->id ?? $pData->uuid ?? '';
        $oldProduct = $productModel->getProductById($productId, $branchId);

        if (!$oldProduct) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Product not found!"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $isUpdated = $productModel->updateBranchStock($branchId, $productId, $pData->new_stock);

            if (!$isUpdated) {
                throw new Exception("Failed to update stock in branch_stocks table.");
            }

            $movementId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $stockLogged = $productModel->logStockMovement(
                $movementId,
                $branchId,
                $productId,
                $userId,
                $pData->type,
                $pData->adjustment,
                $pData->note ?? 'Stock adjustment'
            );

            if (!$stockLogged) {
                throw new Exception("Failed to log record in stock_movements table.");
            }

            $productName = $oldProduct['product_name'] ?? '';
            $logDescription = "Stock " . (strtoupper($pData->type) === 'IN' ? 'added' : 'reduced') . 
                              " by {$pData->adjustment} for '{$productName}' (New Stock: {$pData->new_stock})";
            
            $logModel->logActivity(
                $branchId,
                $userId,
                (strtoupper($pData->type) === 'IN' ? 'STOCK_IN' : 'STOCK_OUT'),
                $logDescription,
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Stock updated and recorded successfully!",
                "data"    => [
                    "product_id" => $productId,
                    "new_stock"  => $pData->new_stock
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Error updating stock: " . $e->getMessage()
            ]);
        }
    }

    public function deleteProduct()
    {
        $token = $this->input->validate("deleteProduct");
        $decodedToken = self::$auth->decode($token);
        $userId   = $decodedToken->id ?? null;
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        $pData = self::$userdata;
        $productModel = new Product(self::$conn);
        $logModel = new Logs(self::$conn);

        $productId = $pData->id ?? $pData->uuid ?? '';
        $existingProduct = $productModel->getProductById($productId, $branchId);

        if (!$existingProduct) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Product not found!"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $isDeleted = $productModel->deleteProductById($productId);

            if (!$isDeleted) {
                throw new Exception("Failed to delete product from database.");
            }

            $logModel->logActivity(
                $branchId,
                $userId,
                'DELETE_PRODUCT',
                "Deleted product '{$existingProduct['product_name']}'",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Product deleted successfully!"
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Error deleting product: " . $e->getMessage()
            ]);
        }
    }

    public function getAllProducts()
    {
        $token = $this->input->validate("getAllProducts");
        $decodedToken = self::$auth->decode($token);
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        $productModel = new Product(self::$conn);

        try {
            $products = $productModel->getAllProducts($branchId);

            Response::sendbody([
                "status"  => 200,
                "message" => "Products retrieved successfully!",
                "data"    => $products
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve products: " . $e->getMessage()
            ]);
        }
    }

    public function getProductDetail()
    {
        $token = $this->input->validate("getProductDetail");
        $decodedToken = self::$auth->decode($token);
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        $productId = self::$userdata->id ?? self::$userdata->uuid ?? '';
        $productModel = new Product(self::$conn);

        try {
            $product = $productModel->getProductById($productId, $branchId);

            if (!$product) {
                Response::sendbody([
                    "status"  => 404,
                    "message" => "Product not found!"
                ]);
                return;
            }

            Response::sendbody([
                "status"  => 200,
                "message" => "Product detail retrieved successfully!",
                "data"    => $product
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to fetch product detail: " . $e->getMessage()
            ]);
        }
    }

    public function updateProduct()
    {
        $token = $this->input->validate("updateProduct");
        $decodedToken = self::$auth->decode($token);
        $userId   = $decodedToken->id ?? null;
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        $pData = self::$userdata;
        $productModel = new Product(self::$conn);
        $logModel = new Logs(self::$conn);

        $productId = $pData->id ?? $pData->uuid ?? '';
        $oldProductData = $productModel->getProductById($productId, $branchId);

        if (!$oldProductData) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Product not found to update."
            ]);
            return;
        }

        $updateData = [
            'id'           => $productId,
            'category_id'  => $pData->category_id,
            'barcode'      => $pData->barcode,
            'product_name' => $pData->product_name,
            'cost_price'   => $pData->cost_price,
            'unit_price'   => $pData->unit_price
        ];

        self::$conn->beginTransaction();

        try {
            $isUpdated = $productModel->updateProduct($updateData);

            if ($isUpdated) {
                $logModel->logActivity(
                    $branchId,
                    $userId,
                    'UPDATE_PRODUCT',
                    "Updated product info for '{$pData->product_name}'",
                    $_SERVER['REMOTE_ADDR'] ?? null
                );

                self::$conn->commit();

                Response::sendbody([
                    "status"  => 200,
                    "message" => "Product updated successfully!"
                ]);
            } else {
                self::$conn->rollBack();
                Response::sendbody([
                    "status"  => 500,
                    "message" => "Failed to update product in database."
                ]);
            }

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status"  => 500,
                "message" => "Error updating product: " . $e->getMessage()
            ]);
        }
    }

    public function addProduct()
    {
        $token = $this->input->validate("addProduct");
        $decodedToken = self::$auth->decode($token);
        $userId   = $decodedToken->id ?? null;
        $branchId = self::$userdata->branch_id ?? $decodedToken->branch_id ?? null;

        $productModel = new Product(self::$conn);
        $logModel = new Logs(self::$conn);

        if (!empty(self::$userdata->barcode) && $productModel->isBarcodeExists(self::$userdata->barcode)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Product with this barcode already exists!"
            ]);
            return;
        }

        $productId = !empty(self::$userdata->id) ? self::$userdata->id : (!empty(self::$userdata->uuid) ? self::$userdata->uuid : sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        ));

        self::$conn->beginTransaction();

        try {
            $productData = [
                "id"             => $productId,
                "category_id"    => self::$userdata->category_id ?? null,
                "name"           => self::$userdata->product_name ?? '',
                "barcode"        => self::$userdata->barcode ?? null,
                "cost_price"     => self::$userdata->cost_price ?? 0.00,
                "sell_price"     => self::$userdata->unit_price ?? 0.00,
                "stock_quantity" => self::$userdata->stock_quantity ?? 0
            ];

            $productModel->createProduct($productData);

            $initialStock = (int) ($productData['stock_quantity']);
            if ($initialStock > 0 && $branchId) {
                $productModel->updateBranchStock($branchId, $productId, $initialStock);

                $movementId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );

                $stockLogged = $productModel->logStockMovement(
                    $movementId,
                    $branchId,
                    $productId,
                    $userId,
                    'IN',           
                    $initialStock,   
                    'Initial stock on creation'
                );

                if (!$stockLogged) {
                    throw new Exception("Failed to log initial stock movement.");
                }
            }

            $logModel->logActivity(
                $branchId,
                $userId,
                "ADD_PRODUCT",
                "Created product: {$productData['name']}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Product created successfully!",
                "data"    => $productData
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to add product: " . $e->getMessage()
            ]);
        }
    }
}