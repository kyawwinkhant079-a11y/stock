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
        self::$auth->decode($token);

        $productModel = new Product(self::$conn);

        try {
            $transactions = $productModel->getStockTransactions();

            Response::sendbody([
                "status"  => 200,
                "message" => "Stock transactions retrieved successfully!",
                "data"    => $transactions
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve stock transactions: " . $e->getMessage()
            ]);
        }
    }

    public function updateStock()
    {
        $token = $this->input->validate("updateStock");
        $decodedToken = self::$auth->decode($token);
        $username = $decodedToken->username ?? 'Unknown User';

        $pData = self::$userdata;
        $productModel = new Product(self::$conn);
        $logModel = new Logs(self::$conn);

        $oldProduct = $productModel->getProductByUuid($pData->uuid);

        if (!$oldProduct) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Product not found!"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $isUpdated = $productModel->updateProductStock($pData->uuid, $pData->new_stock);

            if (!$isUpdated) {
                throw new Exception("Failed to update stock in products table.");
            }

            $productName  = $oldProduct['product_name'] ?? '';
            $categoryName = $oldProduct['category_name'] ?? '';
            
            $stockLogged = $productModel->logStockTransaction(
                $pData->uuid,
                $productName,
                $categoryName,
                $pData->type,
                $pData->adjustment,
                $username
            );

            if (!$stockLogged) {
                throw new Exception("Failed to log record in stock_transactions table.");
            }

            $logAction      = (strtoupper($pData->type) === 'IN') ? 'STOCK_IN' : 'STOCK_OUT';
            $logDescription = "Stock " . (strtoupper($pData->type) === 'IN' ? 'added' : 'reduced') . 
                              " by {$pData->adjustment} for '{$productName}' (New Stock: {$pData->new_stock})";
            
            $oldValues = ["stock_quantity" => $oldProduct['stock_quantity']];
            $newValues = [
                "stock_quantity" => $pData->new_stock, 
                "adjustment"     => $pData->adjustment, 
                "type"           => $pData->type
            ];

            $logModel->logActivity(
                $logAction,
                'stock',
                $pData->uuid,
                $logDescription,
                $oldValues,
                $newValues,
                $username
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Stock updated and recorded successfully!",
                "data"    => [
                    "uuid"      => $pData->uuid,
                    "new_stock" => $pData->new_stock
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
        $username = $decodedToken->username ?? 'Unknown User';

        $pData = self::$userdata;
        $productModel = new Product(self::$conn);
        $logModel = new Logs(self::$conn);

        $existingProduct = $productModel->getProductByUuid($pData->uuid);

        if (!$existingProduct) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Product not found!"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $isDeleted = $productModel->deleteProductByUuid($pData->uuid);

            if (!$isDeleted) {
                throw new Exception("Failed to delete product from database.");
            }

            $logModel->logActivity(
                'DELETE_PRODUCT',
                'product',
                $pData->uuid,
                "Deleted product '{$existingProduct['product_name']}'",
                $existingProduct,
                null,
                $username
            );

            self::$conn->commit();

            if (!empty($existingProduct['image_path'])) {
                $imageName = basename($existingProduct['image_path']);
                $imageFilePath = __DIR__ . '/../../../Storage/Products/' . $imageName;
                if (file_exists($imageFilePath)) {
                    @unlink($imageFilePath);
                }
            }

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
        self::$auth->decode($token);

        $productModel = new Product(self::$conn);

        try {
            $products = $productModel->getAllProducts();

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
        self::$auth->decode($token);

        $uuid = self::$userdata->uuid;
        $productModel = new Product(self::$conn);

        try {
            $product = $productModel->getProductByUuid($uuid);

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
        $username = $decodedToken->username ?? 'Unknown User';

        $pData = self::$userdata;
        $productModel = new Product(self::$conn);
        $logModel = new Logs(self::$conn);

        $oldProductData = $productModel->getProductByUuid($pData->uuid);

        if (!$oldProductData) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Product not found to update."
            ]);
            return;
        }

        $imagePath = $oldProductData['image_path'];
        $isNewImageUploaded = false;
        $oldFilePathToDelete = null;

        $uploadDir = __DIR__ . '/../../../Storage/Products/';

        if (!empty($pData->image_base64)) {
            $base64Image = $pData->image_base64;

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
                $extension = strtolower($type[1]);
                
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                    Response::sendbody([
                        "status" => 400,
                        "message" => "Invalid image format! Only JPG, PNG and WEBP allowed."
                    ]);
                    return;
                }
            } else {
                $extension = 'png';
            }

            $base64Image = str_replace(' ', '+', $base64Image);
            $imageData = base64_decode($base64Image);

            if ($imageData === false) {
                Response::sendbody([
                    "status" => 400,
                    "message" => "Invalid image data provided."
                ]);
                return;
            }

            $fileName = "prod-" . $pData->uuid . "-" . time() . "." . $extension;

            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . $fileName;

            if (file_put_contents($filePath, $imageData) === false) {
                Response::sendbody([
                    "status" => 500,
                    "message" => "Failed to save updated product image!"
                ]);
                return;
            }

            // မူလဟောင်း ပုံဖိုင်ရှိခဲ့လျှင် ဖျက်ရန် စီစဉ်ခြင်း (Categorycontroller ပုံစံအတိုင်း)
            if (!empty($oldProductData['image_path'])) {
                $oldFileName = basename($oldProductData['image_path']);
                $oldFilePathToDelete = $uploadDir . $oldFileName;
            }

            $imagePath = "Storage/Products/" . $fileName;
            $isNewImageUploaded = true;
        }

        $updateData = [
            'uuid'           => $pData->uuid,
            'category_name'  => $pData->category_name,
            'barcode'        => $pData->barcode,
            'product_name'   => $pData->product_name,
            'cost_price'     => $pData->cost_price,
            'unit_price'     => $pData->unit_price,
            'stock_quantity' => $pData->stock_quantity,
            'brand'          => $pData->brand,
            'model'          => $pData->model,
            'description'    => $pData->description,
            'image_path'     => $imagePath
        ];

        self::$conn->beginTransaction();

        try {
            $isUpdated = $productModel->updateProduct($updateData);

            if ($isUpdated) {
                $logModel->logActivity(
                    'UPDATE_PRODUCT',
                    'product',
                    $pData->uuid,
                    "Updated product info for '{$pData->product_name}'",
                    $oldProductData,
                    $updateData,
                    $username
                );

                self::$conn->commit();

                // ပုံအသစ် အောင်မြင်စွာ update ပြီးမှ မူလပုံဟောင်းကို ဖြတ်ထုတ်ခြင်း
                if ($isNewImageUploaded && $oldFilePathToDelete && file_exists($oldFilePathToDelete)) {
                    @unlink($oldFilePathToDelete);
                }

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

            if ($isNewImageUploaded && file_exists($filePath)) {
                @unlink($filePath);
            }

            Response::sendbody([
                "status"  => 500,
                "message" => "Error updating product: " . $e->getMessage()
            ]);
        }
    }

    public function addProduct()
    {
        $token = $this->input->validate("addProduct");

        $decodedData = self::$auth->decode($token);
        $username = $decodedData->username ?? 'Admin';

        $productModel = new Product(self::$conn);
        $logModel = new Logs(self::$conn);

        if (!empty(self::$userdata->barcode) && $productModel->isBarcodeExists(self::$userdata->barcode)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Product with this barcode already exists!"
            ]);
            return;
        }

        $productUuid = !empty(self::$userdata->uuid) ? self::$userdata->uuid : bin2hex(random_bytes(16));
        $dbImagePath = null;

        $uploadDir = __DIR__ . '/../../../Storage/Products/';

        if (!empty(self::$userdata->image_base64)) {
            $base64Image = self::$userdata->image_base64;

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
                $extension = strtolower($type[1]);
                
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $extension = 'jpg';
                }
            } else {
                $extension = 'jpg';
            }

            $base64Image = str_replace(' ', '+', $base64Image);
            $imageData = base64_decode($base64Image);

            if ($imageData !== false) {
                $fileName = "prod-" . $productUuid . "-" . time() . "." . $extension;

                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $filePath = $uploadDir . $fileName;

                if (file_put_contents($filePath, $imageData) !== false) {
                    $dbImagePath = "Storage/Products/" . $fileName;
                }
            }
        }

        self::$conn->beginTransaction();

        try {
            $productData = [
                "uuid"              => $productUuid,
                "category_name"     => self::$userdata->category_name,
                "name"              => self::$userdata->product_name ?? '',
                "barcode"           => self::$userdata->barcode ?? null,
                "cost_price"        => self::$userdata->cost_price ?? 0.00,
                "sell_price"        => self::$userdata->unit_price ?? 0.00,
                "stock_quantity"    => self::$userdata->stock_quantity ?? 0,
                "brand"             => self::$userdata->brand ?? null,
                "model"             => self::$userdata->model ?? null,
                "description"       => self::$userdata->description ?? null,
                "image_path"        => $dbImagePath,
                "created_user_name" => $username
            ];

            $productModel->createProduct($productData);

            $initialStock = (int) ($productData['stock_quantity']);
            if ($initialStock > 0) {
                $stockLogged = $productModel->logStockTransaction(
                    $productUuid,
                    $productData['name'],
                    $productData['category_name'],
                    'IN',           
                    $initialStock,   
                    $username
                );

                if (!$stockLogged) {
                    throw new Exception("Failed to log initial stock transaction.");
                }
            }

            $logModel->logActivity(
                "ADD_PRODUCT",
                "product",
                $productUuid,
                "Created product: {$productData['name']}",
                null,
                $productData,
                $username
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Product created successfully!",
                "data"    => $productData
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            if ($dbImagePath && file_exists(__DIR__ . '/../../../Storage/Products/' . basename($dbImagePath))) {
                @unlink(__DIR__ . '/../../../Storage/Products/' . basename($dbImagePath));
            }

            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to add product: " . $e->getMessage()
            ]);
        }
    }
}