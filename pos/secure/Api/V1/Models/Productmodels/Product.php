<?php

namespace Secure\Api\V1\Models\Productmodels;

use Exception;
use PDO;

class Product 
{
    private $conn;

    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    public function getStockTransactions()
    {
        $query = "SELECT 
                    id,
                    product_uuid,
                    product_name,
                    category_name,
                    type,
                    quantity,
                    created_username,
                    created_at
                FROM stock_transactions 
                ORDER BY id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteProductByUuid($uuid)
    {
        $query = "DELETE FROM products WHERE uuid = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$uuid]);
    }

    public function updateProductStock($uuid, $newStock)
    {
        $query = "UPDATE products SET 
                    stock_quantity = :stock_quantity,
                    updated_at     = NOW()
                WHERE uuid = :uuid";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':stock_quantity' => $newStock,
            ':uuid'           => $uuid
        ]);
    }

    public function logStockTransaction($productUuid, $productName, $categoryName, $type, $quantity, $username)
    {
        $query = "INSERT INTO stock_transactions (
                    product_uuid, 
                    product_name, 
                    category_name, 
                    type, 
                    quantity, 
                    created_username, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            $productUuid,
            $productName,
            $categoryName,
            strtolower($type), 
            $quantity,
            $username
        ]);
    }

    public function updateProduct($data)
    {
        if (!empty($data['image_path'])) {
            $query = "UPDATE products SET 
                        category_name  = :category_name,
                        name           = :name,
                        barcode        = :barcode,
                        cost_price     = :cost_price,
                        sell_price     = :sell_price,
                        stock_quantity = :stock_quantity,
                        brand          = :brand,
                        model          = :model,
                        description    = :description,
                        image_path     = :image_path,
                        updated_at     = NOW()
                      WHERE uuid = :uuid";
        } else {
            $query = "UPDATE products SET 
                        category_name  = :category_name,
                        name           = :name,
                        barcode        = :barcode,
                        cost_price     = :cost_price,
                        sell_price     = :sell_price,
                        stock_quantity = :stock_quantity,
                        brand          = :brand,
                        model          = :model,
                        description    = :description,
                        updated_at     = NOW()
                      WHERE uuid = :uuid";
        }

        $stmt = $this->conn->prepare($query);

        $params = [
            ':category_name'  => $data['category_name'],
            ':name'           => $data['product_name'],
            ':barcode'        => $data['barcode'],
            ':cost_price'     => $data['cost_price'],
            ':sell_price'     => $data['unit_price'],
            ':stock_quantity' => $data['stock_quantity'],
            ':brand'          => $data['brand'],
            ':model'          => $data['model'],
            ':description'    => $data['description'],
            ':uuid'           => $data['uuid']
        ];

        if (!empty($data['image_path'])) {
            $params[':image_path'] = $data['image_path'];
        }

        return $stmt->execute($params);
    }

    public function getProductByUuid($uuid)
    {
        $query = "SELECT 
                    id,
                    uuid,
                    category_name,
                    name AS product_name, 
                    barcode,
                    cost_price,
                    sell_price AS unit_price,
                    stock_quantity,
                    brand,
                    model,
                    description,
                    image_path,
                    created_user_name,
                    updated_at
                  FROM products 
                  WHERE uuid = ? 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$uuid]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function isBarcodeExists($barcode)
    {
        if (empty($barcode)) {
            return false;
        }
        $stmt = $this->conn->prepare("SELECT id FROM products WHERE barcode = ?");
        $stmt->execute([$barcode]);
        return $stmt->rowCount() > 0;
    }

    public function getAllProducts()
    {
        $query = "SELECT 
                    id,
                    uuid,
                    category_name,
                    name AS product_name, 
                    barcode,
                    cost_price,
                    sell_price AS unit_price,
                    stock_quantity,
                    brand,
                    model,
                    image_path,
                    created_user_name,
                    updated_at
                  FROM products 
                  ORDER BY id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProduct($data)
    {
        $query = "INSERT INTO products (
                    uuid, category_name, name, barcode, 
                    cost_price, sell_price, stock_quantity, 
                    brand, model, description, image_path, created_user_name
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $success = $stmt->execute([
            $data['uuid'],
            $data['category_name'],
            $data['name'],
            $data['barcode'],
            $data['cost_price'],
            $data['sell_price'],
            $data['stock_quantity'],
            $data['brand'],
            $data['model'],
            $data['description'],
            $data['image_path'],
            $data['created_user_name']
        ]);

        if (!$success) {
            throw new Exception("Failed to insert product record.");
        }

        return true;
    }
}