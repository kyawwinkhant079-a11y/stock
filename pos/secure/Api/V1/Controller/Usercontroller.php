<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Usermodels\User;
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
        
        $currentUserToken = $decodedData->usertoken ?? null;

        try {
            $users = $this->user->getAllUsers($currentUserToken);

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
        $adminUsername = $decodedData->username ?? 'Admin';

        $userId = self::$userdata->user_id ?? null;
        $role   = self::$userdata->role ?? 'user';

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

            $stmt = self::$conn->prepare("UPDATE users SET role = ?, status = 'active' WHERE id = ?");
            $success = $stmt->execute([$role, $userId]);

            if ($success) {
                $logModel = new \Secure\Api\Models\Logmodels\Logs(self::$conn);
                $logModel->logActivity(
                    "UPDATE_USER_ROLE",
                    "user",
                    (string)$userId,
                    "Updated role for user: {$oldUser['username']} to {$role} and activated account",
                    [
                        "role"   => $oldUser['role'],
                        "status" => $oldUser['status']
                    ],
                    [
                        "role"   => $role,
                        "status" => "active"
                    ],
                    $adminUsername
                );

                self::$conn->commit();

                Response::sendbody([
                    "status"  => 200,
                    "message" => "User role updated and activated successfully!"
                ]);
            } else {
                self::$conn->rollBack();
                Response::sendbody([
                    "status"  => 500,
                    "message" => "Failed to update user."
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
        $adminUsername = $decodedData->username ?? 'Admin';

        $userId = self::$userdata->user_id ?? null;

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

            // Protect deleting last admin
            if ($oldUser['role'] === 'admin') {
                $stmt = self::$conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
                $stmt->execute();
                $adminCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                if ($adminCount <= 1) {
                    Response::sendbody([
                        "status"  => 400,
                        "message" => "Cannot delete the only admin in system!"
                    ]);
                    return;
                }
            }

            $deleted = $this->user->deleteUserById($userId);

            if ($deleted) {
                $logModel = new \Secure\Api\Models\Logmodels\Logs(self::$conn);
                $logModel->logActivity(
                    "DELETE_USER",
                    "user",
                    (string)$userId,
                    "Deleted user: {$oldUser['username']}",
                    $oldUser,
                    null,
                    $adminUsername
                );

                self::$conn->commit();

                if (!empty($oldUser['image_path'])) {
                    $imageFilePath = __DIR__ . '/../../' . $oldUser['image_path'];
                    if (file_exists($imageFilePath)) {
                        @unlink($imageFilePath);
                    }
                }

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

    public function updateProfile()
    {
        $token = $this->input->validate("updateProfile");
        $decodedData = self::$auth->decode($token);

        $usertoken = $decodedData->usertoken ?? null;
        $username  = $decodedData->username ?? 'Unknown';

        if (empty($usertoken)) {
            Response::sendbody([
                "status" => 401,
                "message" => "Invalid authorization token."
            ]);
            return;
        }

        $existingUser = $this->user->getUserByToken($usertoken);
        if (!$existingUser) {
            Response::sendbody([
                "status" => 404,
                "message" => "User account not found!"
            ]);
            return;
        }

        $newUsername = self::$userdata->username;
        $newPhone    = self::$userdata->phone ?? '';
        $dbImagePath = null;
        $isNewImageUploaded = false;

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
                $fileName = "user-" . $usertoken . "-" . time() . "." . $extension;
                $uploadDir = __DIR__ . '/../../Storage/Users/';

                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $filePath = $uploadDir . $fileName;

                if (file_put_contents($filePath, $imageData) !== false) {
                    $dbImagePath = "Storage/Users/" . $fileName;
                    $isNewImageUploaded = true;
                }
            }
        }

        self::$conn->beginTransaction();

        try {
            $finalImagePath = $isNewImageUploaded ? $dbImagePath : null;
            $this->user->updateProfile($usertoken, $newUsername, $newPhone, $finalImagePath);

            $logModel = new \Secure\Api\Models\Logmodels\Logs(self::$conn);
            $logModel->logActivity(
                "UPDATE_PROFILE",
                "user",
                $usertoken,
                "User profile updated for username: {$newUsername}",
                [
                    "username" => $existingUser['username'],
                    "phone"    => $existingUser['phone'],
                    "image"    => $existingUser['image_path']
                ],
                [
                    "username" => $newUsername,
                    "phone"    => $newPhone,
                    "image"    => $isNewImageUploaded ? $dbImagePath : $existingUser['image_path']
                ],
                $username
            );

            self::$conn->commit();

            if ($isNewImageUploaded && !empty($existingUser['image_path'])) {
                $oldImageFile = __DIR__ . '/../../' . $existingUser['image_path'];
                if (file_exists($oldImageFile)) {
                    @unlink($oldImageFile);
                }
            }

            Response::sendbody([
                "status"  => 200,
                "message" => "Profile updated successfully!",
                "data"    => [
                    "username"   => $newUsername,
                    "phone"      => $newPhone,
                    "image_path" => $isNewImageUploaded ? $dbImagePath : $existingUser['image_path']
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            if ($isNewImageUploaded && file_exists(__DIR__ . '/../../' . $dbImagePath)) {
                @unlink(__DIR__ . '/../../' . $dbImagePath);
            }

            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to update profile: " . $e->getMessage()
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
                    "message" => "Invalid email or password !"
                ]);
                return;
            }

            if ($loginUser['status'] === 'pending') {
                Response::sendbody([
                    "status" => 403,
                    "message" => "Your account is pending admin approval. Please contact the administrator."
                ]);
                return;
            }

            if ($loginUser['status'] === 'suspended') {
                Response::sendbody([
                    "status" => 403,
                    "message" => "Your account has been suspended."
                ]);
                return;
            }

            $payload = [
                "usertoken" => $loginUser['usertoken'],
                "username"  => $loginUser['username'],
                "useremail" => $loginUser['email'],
                "role"      => $loginUser['role'],
                "status"    => $loginUser['status'],
                "exp"       => time() + 241900 
            ];

            $jwt = self::$auth->encode($payload);

            Response::sendheader($jwt);
            Response::sendbody([
                "status" => 200,
                "message" => "Login Success !",
                "data" => [
                    "username" => $loginUser['username'],
                    "role"     => $loginUser['role'],
                    "status"   => $loginUser['status']
                ]
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status" => 500,
                "message" => "Something went wrong !"
            ]);
        }
    }

    public function auth()
    {
        $token = $this->input->validate("auth");
        $decodedData = self::$auth->decode($token);

        $userData = $this->user->getUserByToken($decodedData->usertoken ?? null);

        if (!$userData) {
            Response::sendbody([
                "status" => 404,
                "message" => "User account not found!"
            ]);
            exit;
        }

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

        $token = Response::generatetoken();

        $hasUsers = $this->user->hasUsers();

        if (!$hasUsers) {
            $assignedRole   = "admin";
            $assignedStatus = "active";
        } else {
            $assignedRole   = "user";
            $assignedStatus = "pending";
        }

        self::$conn->beginTransaction();

        try {
            $this->user->registeruser($token, $assignedRole, $assignedStatus);

            self::$conn->commit();

            $payload = [
                "usertoken" => $token,
                "username"  => self::$userdata->username,
                "useremail" => self::$userdata->email,
                "role"      => $assignedRole,
                "status"    => $assignedStatus,
                "exp"       => time() + 241900
            ];

            $jwt = self::$auth->encode($payload);

            Response::sendheader($jwt);
            Response::sendbody([
                "status"  => 200,
                "message" => ($assignedRole === 'admin') 
                    ? "First account created as Admin successfully!" 
                    : "Registration successful! Please wait for Admin permission."
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Something went wrong during registration!"
            ]);
        }
    }

    public function updateUserPermission()
    {
        $this->input->validate("updateUserPermission");

        $targetToken = self::$userdata->target_usertoken ?? null;
        $newRole     = self::$userdata->new_role ?? 'user';
        $newStatus   = self::$userdata->new_status ?? 'active';

        if (empty($targetToken)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Target user token is required."
            ]);
            return;
        }

        if (!in_array($newRole, ['admin', 'product', 'sale', 'user'])) {
            Response::sendbody([
                "status" => 400,
                "message" => "Invalid role provided."
            ]);
            return;
        }

        try {
            $updated = $this->user->updateUserRoleAndStatus($targetToken, $newRole, $newStatus);

            if ($updated) {
                Response::sendbody([
                    "status" => 200,
                    "message" => "User permission updated successfully!"
                ]);
            } else {
                Response::sendbody([
                    "status" => 500,
                    "message" => "Failed to update user permission."
                ]);
            }
        } catch (Exception $e) {
            Response::sendbody([
                "status" => 500,
                "message" => "Error updating permission: " . $e->getMessage()
            ]);
        }
    }
}