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

    public function createShop($shopUuid, $shopName, $ownerName, $phoneNo, $address, $dbImagePath)
    {
        $query = "INSERT INTO shops (uuid, shop_name, owner_name, phone_no, address, image_path, created_at) 
                  VALUES (:uuid, :shop_name, :owner_name, :phone_no, :address, :image_path, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            ':uuid'       => $shopUuid,
            ':shop_name'  => $shopName,
            ':owner_name' => $ownerName,
            ':phone_no'   => $phoneNo,
            ':address'    => $address,
            ':image_path' => $dbImagePath
        ]);
    }

    public function getAllShops()
    {
        $query = "SELECT * FROM shops ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getShopByUuid($uuid)
    {
        $query = "SELECT * FROM shops WHERE uuid = :uuid LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':uuid' => $uuid]);
        
        $shop = $stmt->fetch(PDO::FETCH_ASSOC);
        return $shop ? $shop : false;
    }

    public function updateShop($shopUuid, $shopName, $ownerName, $phoneNo, $address, $dbImagePath)
    {
        $query = "UPDATE shops 
                  SET shop_name = :shop_name, 
                      owner_name = :owner_name, 
                      phone_no = :phone_no, 
                      address = :address, 
                      image_path = :image_path, 
                      updated_at = NOW() 
                  WHERE uuid = :uuid";
        
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            ':uuid'       => $shopUuid,
            ':shop_name'  => $shopName,
            ':owner_name' => $ownerName,
            ':phone_no'   => $phoneNo,
            ':address'    => $address,
            ':image_path' => $dbImagePath
        ]);
    }

    public function deleteShopByUuid($uuid)
    {
        $query = "DELETE FROM shops WHERE uuid = :uuid";
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([':uuid' => $uuid]);
    }
}
