<?php

namespace Secure\Api\V1\Models\Suppliermodels;

use Exception;
use PDO;

class Supplier 
{
    private $conn;
    private $userdata;

    public function __construct($userdata, $conn) 
    {
        $this->userdata = $userdata;
        $this->conn = $conn;
    }

    public function getAllSuppliers()
    {
        $query = "SELECT uuid, name, phone, email, fact, address, created_at FROM suppliers ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSupplier($uuid, $name, $phone, $email, $fact, $address)
    {
        $stmt = $this->conn->prepare("INSERT INTO suppliers (uuid, name, phone, email, fact, address) VALUES (?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$uuid, $name, $phone, $email, $fact, $address]);

        if (!$success) {
            throw new Exception("Failed to insert supplier record.");
        }

        return true;
    }

    public function updateSupplier($uuid, $name, $phone, $email, $fact, $address)
    {
        $stmt = $this->conn->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ?, fact = ?, address = ? WHERE uuid = ?");
        $success = $stmt->execute([$name, $phone, $email, $fact, $address, $uuid]);

        if (!$success) {
            throw new Exception("Failed to update supplier record.");
        }

        return true;
    }

    public function deleteSupplier($uuid)
    {
        $stmt = $this->conn->prepare("DELETE FROM suppliers WHERE uuid = ? OR id = ?");
        $success = $stmt->execute([$uuid, $uuid]);

        if (!$success) {
            throw new Exception("Failed to delete supplier record.");
        }

        return true;
    }
}