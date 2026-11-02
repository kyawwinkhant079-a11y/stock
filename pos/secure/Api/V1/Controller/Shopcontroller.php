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
            $branches = $shopModel->getAllBranches();

            Response::sendbody([
                "status"  => 200,
                "message" => "Branches retrieved successfully!",
                "data"    => $branches
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve branches: " . $e->getMessage()
            ]);
        }
    }
    
    public function add_shop()
    {
        $token = $this->input->validate("addShop");

        $decodedData = self::$auth->decode($token);
        $username = $decodedData->username ?? 'Unknown';
        $userId   = $decodedData->id ?? null; // Database တွင် user_id လိုအပ်ပါက သုံးရန်

        $shopModel = new Shop(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        // Database schema တွင် id သည် CHAR(36) (UUID) ဖြစ်ပါသည်
        $branchId    = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $branchName  = self::$userdata->shop_name ?? '';
        $address     = self::$userdata->address ?? '';
        $phone       = self::$userdata->phone_no ?? '';

        self::$conn->beginTransaction();

        try {
            // branches ဇယားထဲသို့ ထည့်သွင်းခြင်း
            $shopModel->createBranch($branchId, $branchName, $address, $phone);

            // Activity log မှတ်တမ်းတင်ခြင်း
            $logModel->logActivity(
                $branchId, // branch_id
                $userId,   // user_id
                "ADD_BRANCH",
                "Created branch/shop: {$branchName}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Branch created successfully!",
                "data" => [
                    "id"       => $branchId,
                    "name"     => $branchName,
                    "address"  => $address,
                    "phone"    => $phone
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Failed to add branch: " . $e->getMessage()
            ]);
        }
    }

    public function update_shop()
    {
        $token = $this->input->validate("updateShop");

        $decodedData = self::$auth->decode($token);
        $username = $decodedData->username ?? 'Unknown';
        $userId   = $decodedData->id ?? null;

        $shopModel = new Shop(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $branchId = self::$userdata->id ?? self::$userdata->uuid ?? '';
        $existingBranch = $shopModel->getBranchById($branchId);

        if (!$existingBranch) {
            Response::sendbody([
                "status" => 404,
                "message" => "Branch not found!"
            ]);
            return;
        }

        $branchName = self::$userdata->shop_name ?? $existingBranch['name'];
        $address    = self::$userdata->address ?? $existingBranch['address'];
        $phone      = self::$userdata->phone_no ?? $existingBranch['phone'];

        self::$conn->beginTransaction();

        try {
            $shopModel->updateBranch($branchId, $branchName, $address, $phone);

            $logModel->logActivity(
                $branchId,
                $userId,
                "UPDATE_BRANCH",
                "Updated branch: {$branchName}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Branch updated successfully!",
                "data" => [
                    "id"      => $branchId,
                    "name"    => $branchName,
                    "address" => $address,
                    "phone"   => $phone
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Failed to update branch: " . $e->getMessage()
            ]);
        }
    }

    public function delete_shop()
    {
        $token = $this->input->validate("deleteShop");
        $decodedToken = self::$auth->decode($token);
        $userId = $decodedToken->id ?? null;

        $cData = self::$userdata;
        $shopModel = new Shop(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $branchId = $cData->id ?? $cData->uuid ?? '';
        $existingBranch = $shopModel->getBranchById($branchId);

        if (!$existingBranch) {
            Response::sendbody([
                "status"  => 404,
                "message" => "Branch not found!"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $isDeleted = $shopModel->deleteBranchById($branchId);

            if (!$isDeleted) {
                throw new Exception("Failed to delete branch from database.");
            }

            $logModel->logActivity(
                null,
                $userId,
                'DELETE_BRANCH',
                "Deleted branch '{$existingBranch['name']}'",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status"  => 200,
                "message" => "Branch deleted successfully!"
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Error deleting branch: " . $e->getMessage()
            ]);
        }
    }
}