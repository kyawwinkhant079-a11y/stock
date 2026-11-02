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

    public function getAllUsers($branchId = null, $currentUserId = null)
    {
        if ($branchId) {
            $stmt = $this->conn->prepare("SELECT id, branch_id, name, email, role, created_at FROM users WHERE branch_id = ? AND id != ? ORDER BY created_at DESC");
            $stmt->execute([$branchId, $currentUserId]);
        } else if ($currentUserId) {
            $stmt = $this->conn->prepare("SELECT id, branch_id, name, email, role, created_at FROM users WHERE id != ? ORDER BY created_at DESC");
            $stmt->execute([$currentUserId]);
        } else {
            $stmt = $this->conn->prepare("SELECT id, branch_id, name, email, role, created_at FROM users ORDER BY created_at DESC");
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

    public function updateUserRole($userId, $role)
    {
        $stmt = $this->conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        return $stmt->execute([$role, $userId]);
    }

    public function deleteUserById($userId)
    {
        $stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public function getSuperAdminCount()
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'super_admin'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['count'] ?? 0);
    }

    public function logincheck()
    {
        $stmt = $this->conn->prepare("SELECT id, branch_id, name, email, password, role FROM users WHERE email = ?");
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

    public function registeruser($userId, $branchId = null, $role = 'cashier') 
    {
        $hashedPassword = password_hash($this->userdata->password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare("INSERT INTO users (id, branch_id, name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $success = $stmt->execute([
            $userId, 
            $branchId,
            $this->userdata->name, 
            $this->userdata->email, 
            $hashedPassword,
            $role
        ]);

        if (!$success) {
            throw new Exception("Failed to register user record.");
        }

        return true;
    }
}
