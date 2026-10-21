<?php

namespace Secure\Api\V1\Models\Usermodels;

use Exception;
use PDO;

class User 
{
    private $conn;
    private $userdata;

    public function __construct($userdata, $conn) 
    {
        $this->userdata = $userdata;
        $this->conn = $conn;
    }

    public function getAllUsers($currentUserToken = null)
    {
        if ($currentUserToken) {
            $stmt = $this->conn->prepare("SELECT id, usertoken, username, email, phone, role, image_path, status, created_at FROM users WHERE usertoken != ? ORDER BY id DESC");
            $stmt->execute([$currentUserToken]);
        } else {
            $stmt = $this->conn->prepare("SELECT id, usertoken, username, email, phone, role, image_path, status, created_at FROM users ORDER BY id DESC");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserById($userId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateUserInfo($userId, $role)
    {
        $stmt = $this->conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        return $stmt->execute([$role, $userId]);
    }

    public function deleteUserById($userId)
    {
        $stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public function updateProfile($usertoken, $username, $phone, $imagePath = null)
    {
        if ($imagePath !== null) {
            $query = "UPDATE users SET username = ?, phone = ?, image_path = ? WHERE usertoken = ?";
            $stmt = $this->conn->prepare($query);
            $success = $stmt->execute([$username, $phone, $imagePath, $usertoken]);
        } else {
            $query = "UPDATE users SET username = ?, phone = ? WHERE usertoken = ?";
            $stmt = $this->conn->prepare($query);
            $success = $stmt->execute([$username, $phone, $usertoken]);
        }

        if (!$success) {
            throw new Exception("Failed to update user profile record.");
        }

        return true;
    }

    public function getUserByToken($usertoken)
    {
        $stmt = $this->conn->prepare("SELECT id, usertoken, username, email, image_path, phone, role, status, created_at FROM users WHERE usertoken = ?");
        $stmt->execute([$usertoken]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function logincheck()
    {
        $stmt = $this->conn->prepare("SELECT id, usertoken, username, email, password, role, status FROM users WHERE email = ?");
        $stmt->execute([$this->userdata->email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($this->userdata->password, $user['password'])) {
            unset($user['password']); 
            return $user;
        }

        return false;
    }

    public function emailcheck() 
    {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$this->userdata->email]);
        
        return $stmt->rowCount() > 0;
    }

    public function hasUsers()
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM users");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int) $row['total']) > 0;
    }

    public function registeruser($token, $role = 'user', $status = 'pending') 
    {
        $hashedPassword = password_hash($this->userdata->password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare("INSERT INTO users (usertoken, username, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([
            $token, 
            $this->userdata->username, 
            $this->userdata->email, 
            $this->userdata->phone, 
            $hashedPassword,
            $role,
            $status
        ]);

        if (!$success) {
            throw new Exception("Failed to register user record.");
        }

        return true;
    }

    public function updateUserRoleAndStatus($targetUserToken, $role, $status)
    {
        $stmt = $this->conn->prepare("UPDATE users SET role = ?, status = ? WHERE usertoken = ?");
        return $stmt->execute([$role, $status, $targetUserToken]);
    }
}