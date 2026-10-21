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

    public function deleteCategoryByUuid($uuid)
    {
        $query = "DELETE FROM categories WHERE uuid = ?";
        $stmt = $this->conn->prepare($query); 
        return $stmt->execute([$uuid]);
    }

    public function getCategoryByUuid($uuid)
    {
        $stmt = $this->conn->prepare("SELECT * FROM categories WHERE uuid = ?");
        $stmt->execute([$uuid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllCategories()
    {
        $query = "SELECT uuid, name, image_path, updated_at FROM categories ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateCategory($uuid, $name, $imagePath)
    {
        $stmt = $this->conn->prepare("UPDATE categories SET name = ?, image_path = ?, updated_at = NOW() WHERE uuid = ?");
        $success = $stmt->execute([$name, $imagePath, $uuid]);

        if (!$success) {
            throw new Exception("Failed to update category record.");
        }

        return true;
    }

    public function isCategoryExists($name)
    {
        $stmt = $this->conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->rowCount() > 0;
    }

    public function createCategory($uuid, $name, $imagePath = null)
    {
        $stmt = $this->conn->prepare("INSERT INTO categories (uuid, name, image_path) VALUES (?, ?, ?)");
        $success = $stmt->execute([$uuid, $name, $imagePath]);

        if (!$success) {
            throw new Exception("Failed to insert category record.");
        }

        return true;
    }
}