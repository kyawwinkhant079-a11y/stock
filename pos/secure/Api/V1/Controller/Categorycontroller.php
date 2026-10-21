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
            $categories = $categoryModel->getAllCategories();

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

        $categoryModel = new Category(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        if ($categoryModel->isCategoryExists(self::$userdata->name)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Category with this name already exists!"
            ]);
            return;
        }

        $categoryUuid = bin2hex(random_bytes(16));
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
            $categoryModel->createCategory($categoryUuid, $categoryName, $dbImagePath);

            $logModel->logActivity(
                "ADD_CATEGORY",
                "category",
                $categoryUuid,
                "Created category: {$categoryName}",
                null,
                ["uuid" => $categoryUuid, "name" => $categoryName, "image_path" => $dbImagePath],
                $username
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Category created successfully!",
                "data" => [
                    "uuid"       => $categoryUuid,
                    "name"       => $categoryName,
                    "image_path" => $dbImagePath
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

        $categoryModel = new Category(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $existingCategory = $categoryModel->getCategoryByUuid(self::$userdata->uuid);

        if (!$existingCategory) {
            Response::sendbody([
                "status" => 404,
                "message" => "Category not found!"
            ]);
            return;
        }

        $categoryUuid = self::$userdata->uuid;
        $categoryName = self::$userdata->name;
        $dbImagePath  = $existingCategory['image_path'];
        $isNewImageUploaded = false;
        $oldFilePathToDelete = null;

        $uploadDir = __DIR__ . '/../../../Storage/Category/';

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

            $fileName = "cat-" . $categoryUuid . "-" . time() . "." . $extension;
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . $fileName;

            if (file_put_contents($filePath, $imageData) === false) {
                Response::sendbody([
                    "status" => 500,
                    "message" => "Failed to save updated category image!"
                ]);
                return;
            }

            if (!empty($existingCategory['image_path'])) {
                $oldFileName = basename($existingCategory['image_path']);
                $oldFilePathToDelete = $uploadDir . $oldFileName;
            }

            $dbImagePath = "Storage/Category/" . $fileName;
            $isNewImageUploaded = true;
        }

        self::$conn->beginTransaction();

        try {
            $categoryModel->updateCategory($categoryUuid, $categoryName, $dbImagePath);

            $logModel->logActivity(
                "UPDATE_CATEGORY",
                "category",
                $categoryUuid,
                "Updated category: {$categoryName}",
                $existingCategory,
                ["uuid" => $categoryUuid, "name" => $categoryName, "image_path" => $dbImagePath],
                $username
            );

            self::$conn->commit();

            if ($isNewImageUploaded && $oldFilePathToDelete && file_exists($oldFilePathToDelete)) {
                @unlink($oldFilePathToDelete);
            }

            Response::sendbody([
                "status" => 200,
                "message" => "Category updated successfully!",
                "data" => [
                    "uuid"       => $categoryUuid,
                    "name"       => $categoryName,
                    "image_path" => $dbImagePath
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            if ($isNewImageUploaded && file_exists($filePath)) {
                @unlink($filePath);
            }

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
        $username = $decodedToken->username ?? 'Unknown User';

        $cData = self::$userdata;
        $categoryModel = new Category(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $existingCategory = $categoryModel->getCategoryByUuid($cData->uuid);

        if (!$existingCategory) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Category not found!"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $isDeleted = $categoryModel->deleteCategoryByUuid($cData->uuid);

            if (!$isDeleted) {
                throw new Exception("Failed to delete category from database.");
            }

            $logModel->logActivity(
                'DELETE_CATEGORY',
                'category',
                $cData->uuid,
                "Deleted category '{$existingCategory['name']}'",
                $existingCategory,
                null,
                $username
            );

            self::$conn->commit();

          
            if (!empty($existingCategory['image_path'])) {
                $imageName = basename($existingCategory['image_path']);
                $imageFilePath = __DIR__ . '/../../../Storage/Category/' . $imageName;
                if (file_exists($imageFilePath)) {
                    @unlink($imageFilePath);
                }
            }

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