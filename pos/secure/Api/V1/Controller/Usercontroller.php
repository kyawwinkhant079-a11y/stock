<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Usermodels\User;
use Secure\Api\V1\Models\Logmodels\Logs;
use Secure\Connection\Database;
use Secure\Config\Config;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Usercontroller
{
    private $user;
    private $input;
    public static $conn;
    public static $userdata;
    public static $config;
    public static $auth;
    
    public function __construct($userdata) 
    {
        Database::init();
        self::$conn = Database::getConnection();

        $this->user = new User($userdata, self::$conn);
        $this->input = new Userinputlayer($userdata);
        
        self::$userdata = $userdata;
        self::$config = Config::get();
        self::$auth = new Auth();
    } 

    public function getAllUsers()
    {
        $token = $this->input->validate("getAllUsers");
        $decodedData = self::$auth->decode($token);
        
        $currentUserId = $decodedData->id ?? null;
        $branchId      = self::$userdata->branch_id ?? $decodedData->branch_id ?? null;

        try {
            $users = $this->user->getAllUsers($branchId, $currentUserId);

            Response::sendbody([
                "status"  => 200,
                "message" => "Users retrieved successfully!",
                "data"    => $users
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve users: " . $e->getMessage()
            ]);
        }
    }

    public function updateUserInfo()
    {
        $token = $this->input->validate("updateUserInfo");
        $decodedData = self::$auth->decode($token);
        $adminUserId   = $decodedData->id ?? null;
        $adminBranchId = self::$userdata->branch_id ?? $decodedData->branch_id ?? null;

        $userId = self::$userdata->user_id ?? self::$userdata->id ?? null;
        $role   = self::$userdata->role ?? 'cashier';

        if (empty($userId)) {
            Response::sendbody([
                "status"  => 400,
                "message" => "User ID is required."
            ]);
            return;
        }

        if (!in_array($role, ['super_admin', 'branch_manager', 'cashier'])) {
            Response::sendbody([
                "status"  => 400,
                "message" => "Invalid role provided."
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $oldUser = $this->user->getUserById($userId);
            if (!$oldUser) {
                Response::sendbody([
                    "status"  => 404,
                    "message" => "User not found!"
                ]);
                return;
            }

            $success = $this->user->updateUserRole($userId, $role);

            if ($success) {
                $logModel = new Logs(self::$conn);
                $logModel->logActivity(
                    $adminBranchId,
                    $adminUserId,
                    "UPDATE_USER_ROLE",
                    "Updated role for user: {$oldUser['name']} to {$role}",
                    $_SERVER['REMOTE_ADDR'] ?? null
                );

                self::$conn->commit();

                Response::sendbody([
                    "status"  => 200,
                    "message" => "User role updated successfully!"
                ]);
            } else {
                self::$conn->rollBack();
                Response::sendbody([
                    "status"  => 500,
                    "message" => "Failed to update user role."
                ]);
            }

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Error: " . $e->getMessage()
            ]);
        }
    }

    public function deleteUser()
    {
        $token = $this->input->validate("deleteUser");
        $decodedData = self::$auth->decode($token);
        $adminUserId   = $decodedData->id ?? null;
        $adminBranchId = self::$userdata->branch_id ?? $decodedData->branch_id ?? null;

        $userId = self::$userdata->user_id ?? self::$userdata->id ?? null;

        if (empty($userId)) {
            Response::sendbody([
                "status"  => 400,
                "message" => "User ID is required."
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $oldUser = $this->user->getUserById($userId);
            if (!$oldUser) {
                Response::sendbody([
                    "status"  => 404,
                    "message" => "User not found!"
                ]);
                return;
            }

            // Protect deleting the last super_admin
            if ($oldUser['role'] === 'super_admin') {
                $adminCount = $this->user->getSuperAdminCount();
                if ($adminCount <= 1) {
                    Response::sendbody([
                        "status"  => 400,
                        "message" => "Cannot delete the only super admin in the system!"
                    ]);
                    return;
                }
            }

            $deleted = $this->user->deleteUserById($userId);

            if ($deleted) {
                $logModel = new Logs(self::$conn);
                $logModel->logActivity(
                    $adminBranchId,
                    $adminUserId,
                    "DELETE_USER",
                    "Deleted user: {$oldUser['name']}",
                    $_SERVER['REMOTE_ADDR'] ?? null
                );

                self::$conn->commit();

                Response::sendbody([
                    "status"  => 200,
                    "message" => "User deleted successfully!"
                ]);
            } else {
                self::$conn->rollBack();
                Response::sendbody([
                    "status"  => 500,
                    "message" => "Failed to delete user."
                ]);
            }

        } catch (Exception $e) {
            self::$conn->rollBack();
            Response::sendbody([
                "status"  => 500,
                "message" => "Error: " . $e->getMessage()
            ]);
        }
    }

    public function login()  
    {
        $this->input->validate("login");

        try {
            $loginUser = $this->user->logincheck();

            if (!$loginUser) {
                Response::sendbody([
                    "status" => 400,
                    "message" => "Invalid email or password!"
                ]);
                return;
            }

            $payload = [
                "id"        => $loginUser['id'],
                "branch_id" => $loginUser['branch_id'],
                "name"      => $loginUser['name'],
                "email"     => $loginUser['email'],
                "role"      => $loginUser['role'],
                "exp"       => time() + 241900 
            ];

            $jwt = self::$auth->encode($payload);

            Response::sendheader($jwt);
            Response::sendbody([
                "status" => 200,
                "message" => "Login Success!",
                "data" => [
                    "id"        => $loginUser['id'],
                    "branch_id" => $loginUser['branch_id'],
                    "name"      => $loginUser['name'],
                    "email"     => $loginUser['email'],
                    "role"      => $loginUser['role']
                ]
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status" => 500,
                "message" => "Something went wrong!"
            ]);
        }
    }

    public function auth()
    {
        $token = $this->input->validate("auth");
        $decodedData = self::$auth->decode($token);

        $userId = $decodedData->id ?? null;
        $userData = $this->user->getUserById($userId);

        if (!$userData) {
            Response::sendbody([
                "status" => 404,
                "message" => "User account not found!"
            ]);
            exit;
        }

        unset($userData['password']);

        Response::sendbody([
            "status" => 200,
            "message" => "Authentication successful!",
            "data" => $userData
        ]);
    }

    public function creation() 
    {
        $this->input->validate("register");

        if ($this->user->emailcheck()) {
            Response::sendbody([
                "status" => 400,
                "message" => "Your email is already used"
            ]);
            return;
        }

        // CHAR(36) UUID generation for User
        $userId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $hasUsers = $this->user->hasUsers();

        // ပထမဦးဆုံး အကောင့်ဖြစ်ပါက super_admin ပေးမည်၊ ကျန်သည်များ cashier
        if (!$hasUsers) {
            $assignedRole = "super_admin";
        } else {
            $assignedRole = self::$userdata->role ?? "cashier";
        }

        $branchId = self::$userdata->branch_id ?? null;

        self::$conn->beginTransaction();

        try {
            $this->user->registeruser($userId, $branchId, $assignedRole);

            self::$conn->commit();

            $payload = [
                "id"        => $userId,
                "branch_id" => $branchId,
                "name"      => self::$userdata->name,
                "email"     => self::$userdata->email,
                "role"      => $assignedRole,
                "exp"       => time() + 241900
            ];

            $jwt = self::$auth->encode($payload);

            Response::sendheader($jwt);
            Response::sendbody([
                "status"  => 200,
                "message" => ($assignedRole === 'super_admin') 
                    ? "First account created as Super Admin successfully!" 
                    : "User registration successful!"
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Something went wrong during registration: " . $e->getMessage()
            ]);
        }
    }
}