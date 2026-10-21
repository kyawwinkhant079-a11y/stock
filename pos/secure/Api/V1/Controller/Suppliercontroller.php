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
        $username = $decodedData->username ?? 'Unknown';

        $supplierModel = new Supplier(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);
        
        $supplierName    = self::$userdata->name ?? '';
        $supplierPhone   = self::$userdata->phone ?? '';
        $supplierEmail   = self::$userdata->email ?? '';
        $supplierFact    = self::$userdata->fact   ?? '';
        $supplierAddress = self::$userdata->address ?? '';

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

        $supplierUuid = bin2hex(random_bytes(16));

        self::$conn->beginTransaction();

        try {
            $supplierModel->createSupplier($supplierUuid, $supplierName, $supplierPhone, $supplierEmail, $supplierFact, $supplierAddress);

            $logModel->logActivity(
                "ADD_SUPPLIER",
                "supplier",
                $supplierUuid,
                "Created supplier: {$supplierName}",
                null,
                [
                    "uuid"    => $supplierUuid, 
                    "name"    => $supplierName, 
                    "phone"   => $supplierPhone, 
                    "email"   => $supplierEmail, 
                    "fact"    => $supplierFact,
                    "address" => $supplierAddress
                ],
                $username
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Supplier added successfully!",
                "data" => [
                    "uuid"    => $supplierUuid,
                    "name"    => $supplierName,
                    "phone"   => $supplierPhone,
                    "email"   => $supplierEmail,
                    "fact"    => $supplierFact,
                    "address" => $supplierAddress
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
        $username = $decodedData->username ?? 'Unknown';

        $supplierModel = new Supplier(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $uuid            = self::$userdata->uuid ?? '';
        $supplierName    = self::$userdata->name ?? '';
        $supplierPhone   = self::$userdata->phone ?? '';
        $supplierEmail   = self::$userdata->email ?? '';
        $supplierFact    = self::$userdata->fact   ?? '';
        $supplierAddress = self::$userdata->address ?? '';

        if (empty($uuid)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Supplier UUID is required for update"
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
            $supplierModel->updateSupplier($uuid, $supplierName, $supplierPhone, $supplierEmail, $supplierFact, $supplierAddress);

            $logModel->logActivity(
                "UPDATE_SUPPLIER",
                "supplier",
                $uuid,
                "Updated supplier: {$supplierName}",
                null,
                [
                    "uuid"    => $uuid, 
                    "name"    => $supplierName, 
                    "phone"   => $supplierPhone, 
                    "email"   => $supplierEmail, 
                    "fact"    => $supplierFact,
                    "address" => $supplierAddress
                ],
                $username
            );

            self::$conn->commit();

            Response::sendbody([
                "status" => 200,
                "message" => "Supplier updated successfully!",
                "data" => [
                    "uuid"    => $uuid,
                    "name"    => $supplierName,
                    "phone"   => $supplierPhone,
                    "email"   => $supplierEmail,
                    "fact"    => $supplierFact,
                    "address" => $supplierAddress
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
        $username = $decodedData->username ?? 'Unknown';

        $supplierModel = new Supplier(self::$userdata, self::$conn);
        $logModel = new Logs(self::$conn);

        $uuid = self::$userdata->uuid ?? (self::$userdata->id ?? '');

        if (empty($uuid)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Supplier identifier (uuid/id) is required"
            ]);
            return;
        }

        self::$conn->beginTransaction();

        try {
            $supplierModel->deleteSupplier($uuid);

            $logModel->logActivity(
                "DELETE_SUPPLIER",
                "supplier",
                $uuid,
                "Deleted supplier with ID/UUID: {$uuid}",
                null,
                ["uuid" => $uuid],
                $username
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