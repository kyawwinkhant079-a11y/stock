<?php

namespace Secure\Api\V1\Models\Shopmodels;

use PDO;
use Exception;

class Shop
{
    private $conn;
    private $userdata;

    public function __construct($userdata, $conn)
    {
        $this->userdata = $userdata;
        $this->conn = $conn;
    }

    // 1. Branches ဇယားထဲသို့ အသစ်ထည့်ခြင်း
    public function createBranch($id, $name, $address, $phone)
    {
        $query = "INSERT INTO branches (id, name, address, phone, created_at) 
                  VALUES (:id, :name, :address, :phone, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            ':id'      => $id,
            ':name'    => $name,
            ':address' => $address,
            ':phone'   => $phone
        ]);
    }

    // 2. ဆိုင်ခွဲအားလုံးကို ဆွဲထုတ်ရန်
    public function getAllBranches()
    {
        $query = "SELECT * FROM branches ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. ID ဖြင့် ဆိုင်ခွဲရှာရန်
    public function getBranchById($id)
    {
        $query = "SELECT * FROM branches WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        return $branch ? $branch : false;
    }

    // 4. ဆိုင်ခွဲအချက်အလက် ပြင်ဆင်ရန်
    public function updateBranch($id, $name, $address, $phone)
    {
        $query = "UPDATE branches 
                  SET name = :name, 
                      address = :address, 
                      phone = :phone 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            ':id'      => $id,
            ':name'    => $name,
            ':address' => $address,
            ':phone'   => $phone
        ]);
    }

    // 5. ဆိုင်ခွဲဖျက်ရန်
    public function deleteBranchById($id)
    {
        $query = "DELETE FROM branches WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([':id' => $id]);
    }
}