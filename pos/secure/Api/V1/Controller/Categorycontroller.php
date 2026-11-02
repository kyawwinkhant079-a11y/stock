<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Categorymodels\Category;
use Secure\Api\V1\Models\Logmodels\Logs;
use Secure\Connection\Database;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Categorycontroller
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

    public function getCategoryList()
    {
        $token = $this->input->validate("getCategoryList");
        self::$auth->decode($token);

        $categoryModel = new Category(self::$userdata, self::$conn);

        try {
            $branchId = self::$userdata->branch_id ?? null;
            $categories = $categoryModel->getAllCategories($branchId);

            Response::sendbody([
                "status"  => 200,
                "message" => "Categories retrieved successfully!",
                "data"    => $categories
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve categories: " . $e->getMessage()
            ]);
        }
    }

    public function addCategory()
    {
        $token = $this->input->validate("addCategory");

        $decodedData = self::$auth->decode($token);
        $username = $decodedData->username ?? 'Unknown';
        $userId   = $decodedData->id ?? null;
        $branchId = self::$userdata->branch_id ?? null;

        $categoryModel = new Category(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        if ($categoryModel->isCategoryExists(self::$userdata->name, $branchId)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Category with this name already exists in this branch!"
            ]);
            return;
        }

        // CHAR(36) UUID generation
        $categoryUuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $categoryName = self::$userdata->name;
        $dbImagePath  = null;

        if (!empty(self::$userdata->image_base64)) {
            $base64Image = self::$userdata->image_base64;

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

            $fileName = "cat-" . $categoryUuid . "." . $extension;
            $uploadDir = __DIR__ . '/../../../Storage/Category/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . $fileName;

            if (file_put_contents($filePath, $imageData) === false) {
                Response::sendbody([
                    "status" => 500,
                    "message" => "Failed to save category image!"
                ]);
                return;
            }

            $dbImagePath = "Storage/Category/" . $fileName;
        }

        self::$conn->beginTransaction();

        try {
            $categoryModel->createCategory($categoryUuid, $branchId, $categoryName);

            $logModel->logActivity(
                $branchId,
                $userId,
                "ADD_CATEGORY",
                "Created category: {$categoryName}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Category created successfully!",
                "data" => [
                    "id"   => $categoryUuid,
                    "name" => $categoryName
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Failed to add category: " . $e->getMessage()
            ]);
        }
    }

    public function updateCategory()
    {
        $token = $this->input->validate("updateCategory");

        $decodedData = self::$auth->decode($token);
        $username = $decodedData->username ?? 'Unknown';
        $userId   = $decodedData->id ?? null;
        $branchId = self::$userdata->branch_id ?? null;

        $categoryModel = new Category(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $categoryId = self::$userdata->id ?? self::$userdata->uuid ?? '';
        $existingCategory = $categoryModel->getCategoryById($categoryId);

        if (!$existingCategory) {
            Response::sendbody([
                "status" => 404,
                "message" => "Category not found!"
            ]);
            return;
        }

        $categoryName = self::$userdata->name ?? $existingCategory['name'];

        self::$conn->beginTransaction();

        try {
            $categoryModel->updateCategory($categoryId, $categoryName);

            $logModel->logActivity(
                $branchId,
                $userId,
                "UPDATE_CATEGORY",
                "Updated category: {$categoryName}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Category updated successfully!",
                "data" => [
                    "id"   => $categoryId,
                    "name" => $categoryName
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to update category: " . $e->getMessage()
            ]);
        }
    }

    public function deleteCategory()
    {
        $token = $this->input->validate("deleteCategory");
        $decodedToken = self::$auth->decode($token);
        $userId = $decodedToken->id ?? null;
        $branchId = self::$userdata->branch_id ?? null;

        $cData = self::$userdata;
        $categoryModel = new Category(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $categoryId = $cData->id ?? $cData->uuid ?? '';
        $existingCategory = $categoryModel->getCategoryById($categoryId);

        if (!$existingCategory) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Category not found!"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $isDeleted = $categoryModel->deleteCategoryById($categoryId);

            if (!$isDeleted) {
                throw new Exception("Failed to delete category from database.");
            }

            $logModel->logActivity(
                $branchId,
                $userId,
                'DELETE_CATEGORY',
                "Deleted category '{$existingCategory['name']}'",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Category deleted successfully!"
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Error deleting category: " . $e->getMessage()
            ]);
        }
    }
}