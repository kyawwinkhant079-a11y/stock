<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Shopmodels\Shop; 
use Secure\Api\V1\Models\Logmodels\Logs;
use Secure\Connection\Database;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Shopcontroller
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

    public function getShopList()
    {
        $token = $this->input->validate("getShopList");

        $shopModel = new Shop(self::$userdata, self::$conn);

        try {
            $shops = $shopModel->getAllShops();

            Response::sendbody([
                "status"  => 200,
                "message" => "Shops retrieved successfully!",
                "data"    => $shops
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve shops: " . $e->getMessage()
            ]);
        }
    }
    
    public function add_shop()
    {
        $token = $this->input->validate("addShop");

        $decodedData = self::$auth->decode($token);
        $username = $decodedData->username ?? 'Unknown';

        $shopModel = new Shop(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $shopUuid    = bin2hex(random_bytes(16));
        $shopName    = self::$userdata->shop_name ?? '';
        $ownerName   = self::$userdata->owner_name ?? '';
        $phoneNo     = self::$userdata->phone_no ?? '';
        $address     = self::$userdata->address ?? '';
        $dbImagePath = null;

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

            $fileName = "shop-" . $shopUuid . "." . $extension;
            
            $uploadDir = __DIR__ . '/../../../Storage/Shop/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . $fileName;

            if (file_put_contents($filePath, $imageData) === false) {
                Response::sendbody([
                    "status" => 500,
                    "message" => "Failed to save shop image!"
                ]);
                return;
            }

            $dbImagePath = "Storage/Shop/" . $fileName;
        }

        self::$conn->beginTransaction();

        try {
            $shopModel->createShop($shopUuid, $shopName, $ownerName, $phoneNo, $address, $dbImagePath);

            $logModel->logActivity(
                "ADD_SHOP",
                "shop",
                $shopUuid,
                "Created shop: {$shopName}",
                null,
                [
                    "uuid"       => $shopUuid,
                    "shop_name"  => $shopName,
                    "owner_name" => $ownerName,
                    "phone_no"   => $phoneNo,
                    "address"    => $address,
                    "image_path" => $dbImagePath
                ],
                $username
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Shop created successfully!",
                "data" => [
                    "uuid"       => $shopUuid,
                    "shop_name"  => $shopName,
                    "owner_name" => $ownerName,
                    "phone_no"   => $phoneNo,
                    "address"    => $address,
                    "image_path" => $dbImagePath
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Failed to add shop: " . $e->getMessage()
            ]);
        }
    }

    public function update_shop()
    {
        $token = $this->input->validate("updateShop");

        $decodedData = self::$auth->decode($token);
        $username = $decodedData->username ?? 'Unknown';

        $shopModel = new Shop(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $existingShop = $shopModel->getShopByUuid(self::$userdata->uuid);

        if (!$existingShop) {
            Response::sendbody([
                "status" => 404,
                "message" => "Shop not found!"
            ]);
            return;
        }

        $shopUuid    = self::$userdata->uuid;
        $shopName    = self::$userdata->shop_name ?? $existingShop['shop_name'];
        $ownerName   = self::$userdata->owner_name ?? $existingShop['owner_name'];
        $phoneNo     = self::$userdata->phone_no ?? $existingShop['phone_no'];
        $address     = self::$userdata->address ?? $existingShop['address'];
        $dbImagePath = $existingShop['image_path'];
        
        $isNewImageUploaded = false;
        $oldFilePathToDelete = null;

        $uploadDir = __DIR__ . '/../../../Storage/Shop/';

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

            $fileName = "shop-" . $shopUuid . "-" . time() . "." . $extension;
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . $fileName;

            if (file_put_contents($filePath, $imageData) === false) {
                Response::sendbody([
                    "status" => 500,
                    "message" => "Failed to save updated shop image!"
                ]);
                return;
            }

            if (!empty($existingShop['image_path'])) {
                $oldFileName = basename($existingShop['image_path']);
                $oldFilePathToDelete = $uploadDir . $oldFileName;
            }

            $dbImagePath = "Storage/Shop/" . $fileName;
            $isNewImageUploaded = true;
        }

        self::$conn->beginTransaction();

        try {
            $shopModel->updateShop($shopUuid, $shopName, $ownerName, $phoneNo, $address, $dbImagePath);

            $logModel->logActivity(
                "UPDATE_SHOP",
                "shop",
                $shopUuid,
                "Updated shop: {$shopName}",
                $existingShop,
                [
                    "uuid"       => $shopUuid,
                    "shop_name"  => $shopName,
                    "owner_name" => $ownerName,
                    "phone_no"   => $phoneNo,
                    "address"    => $address,
                    "image_path" => $dbImagePath
                ],
                $username
            );

            self::$conn->commit();

            if ($isNewImageUploaded && $oldFilePathToDelete && file_exists($oldFilePathToDelete)) {
                @unlink($oldFilePathToDelete);
            }

            Response::sendbody([
                "status" => 200,
                "message" => "Shop updated successfully!",
                "data" => [
                    "uuid"       => $shopUuid,
                    "shop_name"  => $shopName,
                    "owner_name" => $ownerName,
                    "phone_no"   => $phoneNo,
                    "address"    => $address,
                    "image_path" => $dbImagePath
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            if ($isNewImageUploaded && file_exists($filePath)) {
                @unlink($filePath);
            }

            Response::sendbody([
                "status" => 500,
                "message" => "Failed to update shop: " . $e->getMessage()
            ]);
        }
    }

    public function delete_shop()
    {
        $token = $this->input->validate("deleteShop");
        $decodedToken = self::$auth->decode($token);
        $username = $decodedToken->username ?? 'Unknown User';

        $cData = self::$userdata;
        $shopModel = new Shop(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $existingShop = $shopModel->getShopByUuid($cData->uuid);

        if (!$existingShop) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Shop not found!"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $isDeleted = $shopModel->deleteShopByUuid($cData->uuid);

            if (!$isDeleted) {
                throw new Exception("Failed to delete shop from database.");
            }

            $logModel->logActivity(
                'DELETE_SHOP',
                'shop',
                $cData->uuid,
                "Deleted shop '{$existingShop['shop_name']}'",
                $existingShop,
                null,
                $username
            );

            self::$conn->commit();

            if (!empty($existingShop['image_path'])) {
                $imageName = basename($existingShop['image_path']);
                $imageFilePath = __DIR__ . '/../../../Storage/Shop/' . $imageName;
                if (file_exists($imageFilePath)) {
                    @unlink($imageFilePath);
                }
            }

            Response::sendbody([
                "status"  => 200,
                "message" => "Shop deleted successfully!"
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Error deleting shop: " . $e->getMessage()
            ]);
        }
    }
}