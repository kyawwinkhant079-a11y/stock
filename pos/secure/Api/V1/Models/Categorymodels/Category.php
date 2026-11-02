<?php

namespace Secure\Api\V1\Models\Categorymodels;

use Exception;
use PDO;

class Category 
{
    private $conn;
    private $userdata;

    public function __construct($userdata, $conn) 
    {
        $this->userdata = $userdata;
        $this->conn = $conn;
    }

    public function createCategory($id, $branchId, $name)
    {
        $query = "INSERT INTO categories (id, branch_id, name, created_at) VALUES (:id, :branch_id, :name, NOW())";
        $stmt = $this->conn->prepare($query);
        $success = $stmt->execute([
            ':id'       => $id,
            ':branch_id'=> $branchId,
            ':name'     => $name
        ]);

        if (!$success) {
            throw new Exception("Failed to insert category record.");
        }

        return true;
    }

    public function getAllCategories($branchId = null)
    {
        if ($branchId) {
            $query = "SELECT * FROM categories WHERE branch_id = :branch_id ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':branch_id' => $branchId]);
        } else {
            $query = "SELECT * FROM categories ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategoryById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateCategory($id, $name)
    {
        $stmt = $this->conn->prepare("UPDATE categories SET name = :name WHERE id = :id");
        $success = $stmt->execute([
            ':name' => $name,
            ':id'   => $id
        ]);

        if (!$success) {
            throw new Exception("Failed to update category record.");
        }

        return true;
    }

    public function deleteCategoryById($id)
    {
        $query = "DELETE FROM categories WHERE id = :id";
        $stmt = $this->conn->prepare($query); 
        return $stmt->execute([':id' => $id]);
    }

    public function isCategoryExists($name, $branchId = null)
    {
        if ($branchId) {
            $stmt = $this->conn->prepare("SELECT id FROM categories WHERE name = :name AND branch_id = :branch_id");
            $stmt->execute([':name' => $name, ':branch_id' => $branchId]);
        } else {
            $stmt = $this->conn->prepare("SELECT id FROM categories WHERE name = :name");
            $stmt->execute([':name' => $name]);
        }
        return $stmt->rowCount() > 0;
    }
}