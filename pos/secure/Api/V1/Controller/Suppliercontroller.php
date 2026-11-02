<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Suppliermodels\Supplier;
use Secure\Api\V1\Models\Logmodels\Logs;
use Secure\Connection\Database;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Suppliercontroller
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

    public function getSupplierList()
    {
        $token = $this->input->validate("getSupplierList");
        self::$auth->decode($token);

        $supplierModel = new Supplier(self::$userdata, self::$conn);

        try {
            $suppliers = $supplierModel->getAllSuppliers();

            Response::sendbody([
                "status"  => 200,
                "message" => "Suppliers retrieved successfully!",
                "data"    => $suppliers
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve suppliers: " . $e->getMessage()
            ]);
        }
    }

    public function addSupplier()
    {
        $token = $this->input->validate("addSupplier");

        $decodedData = self::$auth->decode($token);
        $userId   = $decodedData->id ?? null;
        $branchId = self::$userdata->branch_id ?? $decodedData->branch_id ?? null;

        $supplierModel = new Supplier(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);
        
        $supplierName        = self::$userdata->name ?? '';
        $supplierContactPers = self::$userdata->contact_person ?? '';
        $supplierPhone       = self::$userdata->phone ?? '';
        $supplierEmail       = self::$userdata->email ?? '';
        $supplierAddress     = self::$userdata->address ?? '';

        if (empty($supplierName)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Please enter supplier name"
            ]);
            return;
        }

        if (empty($supplierPhone)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Please enter supplier phone number"
            ]);
            return;
        }

        // CHAR(36) UUID generation
        $supplierId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        self::$conn->beginTransaction();

        try {
            $supplierModel->createSupplier($supplierId, $supplierName, $supplierContactPers, $supplierPhone, $supplierEmail, $supplierAddress);

            $logModel->logActivity(
                $branchId,
                $userId,
                "ADD_SUPPLIER",
                "Created supplier: {$supplierName}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Supplier added successfully!",
                "data" => [
                    "id"             => $supplierId,
                    "name"           => $supplierName,
                    "contact_person" => $supplierContactPers,
                    "phone"          => $supplierPhone,
                    "email"          => $supplierEmail,
                    "address"        => $supplierAddress
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Failed to add supplier: " . $e->getMessage()
            ]);
        }
    }

    public function updateSupplier()
    {
        $token = $this->input->validate("updateSupplier");
        $decodedData = self::$auth->decode($token);
        $userId   = $decodedData->id ?? null;
        $branchId = self::$userdata->branch_id ?? $decodedData->branch_id ?? null;

        $supplierModel = new Supplier(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $supplierId          = self::$userdata->id ?? self::$userdata->uuid ?? '';
        $supplierName        = self::$userdata->name ?? '';
        $supplierContactPers = self::$userdata->contact_person ?? '';
        $supplierPhone       = self::$userdata->phone ?? '';
        $supplierEmail       = self::$userdata->email ?? '';
        $supplierAddress     = self::$userdata->address ?? '';

        if (empty($supplierId)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Supplier ID is required for update"
            ]);
            return;
        }

        if (empty($supplierName)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Please enter supplier name"
            ]);
            return;
        }

        if (empty($supplierPhone)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Please enter supplier phone number"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $supplierModel->updateSupplier($supplierId, $supplierName, $supplierContactPers, $supplierPhone, $supplierEmail, $supplierAddress);

            $logModel->logActivity(
                $branchId,
                $userId,
                "UPDATE_SUPPLIER",
                "Updated supplier: {$supplierName}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Supplier updated successfully!",
                "data" => [
                    "id"             => $supplierId,
                    "name"           => $supplierName,
                    "contact_person" => $supplierContactPers,
                    "phone"          => $supplierPhone,
                    "email"          => $supplierEmail,
                    "address"        => $supplierAddress
                ]
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Failed to update supplier: " . $e->getMessage()
            ]);
        }
    }

    public function deleteSupplier()
    {
        $token = $this->input->validate("deleteSupplier");
        $decodedData = self::$auth->decode($token);
        $userId   = $decodedData->id ?? null;
        $branchId = self::$userdata->branch_id ?? $decodedData->branch_id ?? null;

        $supplierModel = new Supplier(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $supplierId = self::$userdata->id ?? self::$userdata->uuid ?? '';

        if (empty($supplierId)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Supplier identifier (id) is required"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $supplierModel->deleteSupplier($supplierId);

            $logModel->logActivity(
                $branchId,
                $userId,
                "DELETE_SUPPLIER",
                "Deleted supplier with ID: {$supplierId}",
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Supplier deleted successfully!"
            ]);

        } catch (Exception $e) {
            self::$conn->rollBack();

            Response::sendbody([
                "status" => 500,
                "message" => "Failed to delete supplier: " . $e->getMessage()
            ]);
        }
    }
}