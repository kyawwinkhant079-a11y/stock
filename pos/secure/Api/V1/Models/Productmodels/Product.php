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

    public function deleteProductById($id)
    {
        $query = "DELETE FROM products WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }

    public function updateBranchStock($branchId, $productId, $newStock)
    {
        // branch_stocks တွင် ရှိပြီးသားလား စစ်ဆေးပြီး ရှိလျှင် update, မရှိလျှင် insert
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

    public function updateProduct($data)
    {
        $query = "UPDATE products SET 
                    category_id  = :category_id,
                    name         = :name,
                    barcode      = :barcode,
                    cost_price   = :cost_price,
                    sale_price   = :sale_price,
                    updated_at   = NOW()
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':category_id' => $data['category_id'],
            ':name'        => $data['product_name'],
            ':barcode'     => $data['barcode'],
            ':cost_price'  => $data['cost_price'],
            ':sale_price'  => $data['unit_price'],
            ':id'          => $data['id']
        ]);
    }

    public function getProductById($id, $branchId = null)
    {
        if ($branchId) {
            $query = "SELECT 
                        p.id,
                        p.category_id,
                        c.name AS category_name,
                        p.name AS product_name, 
                        p.barcode,
                        p.cost_price,
                        p.sale_price AS unit_price,
                        COALESCE(bs.stock_qty, 0) AS stock_quantity,
                        p.created_at
                      FROM products p
                      LEFT JOIN categories c ON p.category_id = c.id
                      LEFT JOIN branch_stocks bs ON p.id = bs.product_id AND bs.branch_id = ?
                      WHERE p.id = ? 
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$branchId, $id]);
        } else {
            $query = "SELECT 
                        p.id,
                        p.category_id,
                        c.name AS category_name,
                        p.name AS product_name, 
                        p.barcode,
                        p.cost_price,
                        p.sale_price AS unit_price,
                        p.created_at
                      FROM products p
                      LEFT JOIN categories c ON p.category_id = c.id
                      WHERE p.id = ? 
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
        }

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

    public function getAllProducts($branchId = null)
    {
        if ($branchId) {
            $query = "SELECT 
                        p.id,
                        p.category_id,
                        c.name AS category_name,
                        p.name AS product_name, 
                        p.barcode,
                        p.cost_price,
                        p.sale_price AS unit_price,
                        COALESCE(bs.stock_qty, 0) AS stock_quantity,
                        p.created_at
                      FROM products p
                      LEFT JOIN categories c ON p.category_id = c.id
                      LEFT JOIN branch_stocks bs ON p.id = bs.product_id AND bs.branch_id = :branch_id
                      ORDER BY p.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':branch_id' => $branchId]);
        } else {
            $query = "SELECT 
                        p.id,
                        p.category_id,
                        c.name AS category_name,
                        p.name AS product_name, 
                        p.barcode,
                        p.cost_price,
                        p.sale_price AS unit_price,
                        0 AS stock_quantity,
                        p.created_at
                      FROM products p
                      LEFT JOIN categories c ON p.category_id = c.id
                      ORDER BY p.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProduct($data)
    {
        $query = "INSERT INTO products (
                    id, category_id, name, barcode, 
                    cost_price, sale_price, created_at
                  ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);
        $success = $stmt->execute([
            $data['id'],
            $data['category_id'],
            $data['name'],
            $data['barcode'],
            $data['cost_price'],
            $data['sell_price']
        ]);

        if (!$success) {
            throw new Exception("Failed to insert product record.");
        }

        return true;
    }
}