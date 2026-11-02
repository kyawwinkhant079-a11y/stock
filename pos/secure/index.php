<?php

require_once __DIR__ . '/Vendor/autoload.php';

use Secure\Core\Response;
use Secure\Connection\Database;
use Secure\Api\V1\Controller\Usercontroller;
use Secure\Api\V1\Controller\Categorycontroller;
use Secure\Api\V1\Controller\Productcontroller;
use Secure\Api\V1\Controller\Logcontroller;
use Secure\Api\V1\Controller\Salecontroller;
use Secure\Api\V1\Controller\Reportcontroller;
use Secure\Api\V1\Controller\Suppliercontroller;
use Secure\Api\V1\Controller\Shopcontroller;
use Secure\Api\V1\Controller\Inventorycontroller;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-TOKEN, Authorization");
    http_response_code(200);
    exit();
}

$userinput = file_get_contents('php://input');

if (empty($userinput)) {
    Response::sendbody([
        "status" => 400,
        "message" => "Empty payload received."
    ]);
}

$userdata = json_decode($userinput);

if (empty($userdata) || empty($userdata->action)) {
    Response::sendbody([
        "status" => 400,
        "message" => "Action parameter missing in request payload."
    ]);
}

Database::init();

class Index 
{
    private $userdata;

    public function __construct($userdata)
    {
        $this->userdata = $userdata;
        $this->managecontrol();
    }

    public function managecontrol() 
    {
        switch ($this->userdata->action) {
            // Shop Management
            case '1000': (new Shopcontroller($this->userdata))->add_shop(); break;
            case '1001': (new Shopcontroller($this->userdata))->update_shop(); break;
            case '1002': (new Shopcontroller($this->userdata))->delete_shop(); break;
            case '1003': (new Shopcontroller($this->userdata))->getShopList(); break;
            
            // User & Auth Management
            case '1101': (new Usercontroller($this->userdata))->creation(); break;
            case '1102': (new Usercontroller($this->userdata))->login(); break;
            case '1103': (new Usercontroller($this->userdata))->auth(); break;
            case '1123': (new Usercontroller($this->userdata))->updateProfile(); break;
            case '1124': (new Usercontroller($this->userdata))->getAllUsers(); break;
            case '1125': (new Usercontroller($this->userdata))->updateUserInfo(); break;
            case '1126': (new Usercontroller($this->userdata))->deleteUser(); break;

            // Category Management
            case '1104': (new Categorycontroller($this->userdata))->addCategory(); break;
            case '1105':
            case '1109':
            case '1112': (new Categorycontroller($this->userdata))->getCategoryList(); break;
            case '1106': (new Categorycontroller($this->userdata))->updateCategory(); break;
            case '1117': (new Categorycontroller($this->userdata))->deleteCategory(); break;

            // Product Management
            case '1108': (new Productcontroller($this->userdata))->addProduct(); break;
            case '1110': (new Productcontroller($this->userdata))->getAllProducts(); break;
            case '1111': (new Productcontroller($this->userdata))->getProductDetail(); break;
            case '1114': (new Productcontroller($this->userdata))->updateProduct(); break;
            case '1115': (new Productcontroller($this->userdata))->updateStock(); break;
            case '1116': (new Productcontroller($this->userdata))->deleteProduct(); break;
            case '1119': (new Productcontroller($this->userdata))->getStockTransactions(); break;

            // Logs Management
            case '1118': (new Logcontroller($this->userdata))->getLogs(); break;

            // Sale Management
            case '1120': (new Salecontroller($this->userdata))->processCheckout(); break;
            case '1121': (new Salecontroller($this->userdata))->getSalesHistoryDetail(); break;
            case '1122': (new Salecontroller($this->userdata))->getSalesHistory(); break;

            // Report Management
            case '1127': (new Reportcontroller($this->userdata))->getDashboardSummary(); break;

            // Supplier Management
            case '1128': (new Suppliercontroller($this->userdata))->addsupplier(); break;
            case '1129': (new Suppliercontroller($this->userdata))->getSupplierList(); break;
            case '1130': (new Suppliercontroller($this->userdata))->updateSupplier(); break;
            case '1131': (new Suppliercontroller($this->userdata))->deleteSupplier(); break;

            default:
                Response::sendbody([
                    "status" => 404,
                    "message" => "Invalid Action Code!"
                ]);
                break;
        }
    }
}

new Index($userdata);