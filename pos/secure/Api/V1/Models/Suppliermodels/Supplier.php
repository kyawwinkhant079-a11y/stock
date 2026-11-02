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
        $query = "SELECT id, name, contact_person, phone, email, address, created_at FROM suppliers ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSupplier($id, $name, $contactPerson, $phone, $email, $address)
    {
        $query = "INSERT INTO suppliers (id, name, contact_person, phone, email, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $success = $stmt->execute([$id, $name, $contactPerson, $phone, $email, $address]);

        if (!$success) {
            throw new Exception("Failed to insert supplier record.");
        }

        return true;
    }

    public function updateSupplier($id, $name, $contactPerson, $phone, $email, $address)
    {
        $query = "UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $success = $stmt->execute([$name, $contactPerson, $phone, $email, $address, $id]);

        if (!$success) {
            throw new Exception("Failed to update supplier record.");
        }

        return true;
    }

    public function deleteSupplier($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM suppliers WHERE id = ?");
        $success = $stmt->execute([$id]);

        if (!$success) {
            throw new Exception("Failed to delete supplier record.");
        }

        return true;
    }
}