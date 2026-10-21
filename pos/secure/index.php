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
$conn = Database::getConnection();

class Index 
{
    private $usercontroller;
    private $categorycontroller;
    private $productcontroller;
    private $logcontroller;
    private $salecontroller;
    private $reportcontroller;
    private $suppliercontroller;
    private $shopcontroller;

    public function __construct($userdata)
    {
        $this->usercontroller = new Usercontroller($userdata);
        $this->categorycontroller = new Categorycontroller($userdata);
        $this->productcontroller = new Productcontroller($userdata);
        $this->logcontroller = new Logcontroller($userdata);
        $this->salecontroller = new Salecontroller($userdata);
        $this->reportcontroller = new Reportcontroller($userdata);
        $this->suppliercontroller = new Suppliercontroller($userdata);
        $this->shopcontroller = new Shopcontroller($userdata);
        $this->managecontrol($userdata);
    }

    public function managecontrol($userdata) 
    {
        switch ($userdata->action) {

            case '1000':
                $this->shopcontroller->add_shop();
                break;

            case '1001':
                $this->shopcontroller->update_shop();
                break;

            case '1003':
                $this->shopcontroller->getShopList();
                break;

            case '1002':
                $this->shopcontroller->delete_shop();
                break;
            
            case '1101':
                $this->usercontroller->creation();
                break;
                
            case '1102':
                $this->usercontroller->login();
                break;

            case '1103':
                $this->usercontroller->auth();
                break;

            case '1104':
                $this->categorycontroller->addCategory();
                break;

            case '1105':
            case '1109':
            case '1112':
                $this->categorycontroller->getCategoryList();
                break;
            case '1106':
                $this->categorycontroller->updateCategory();
                break;

            case '1117':
                $this->categorycontroller->deleteCategory();
                break;

            case '1108':
                $this->productcontroller->addProduct();
                break;

            case '1110':
                $this->productcontroller->getAllProducts();
                break;

            case '1111':
                $this->productcontroller->getProductDetail();
                break;

            case '1114':
                $this->productcontroller->updateProduct();
                break;

            case '1115':
                $this->productcontroller->updateStock();
                break;

            case '1116':
                $this->productcontroller->deleteProduct();
                break;

            case '1118':
                $this->logcontroller->getLogs();
                break;

            case '1119':
                $this->productcontroller->getStockTransactions(); 
                break;

            case '1120':
                $this->salecontroller->processCheckout(); 
                break;

            case '1121':
                $this->salecontroller->getSalesHistoryDetail(); 
                break;

            case '1122':
                $this->salecontroller->getSalesHistory(); 
                break;

            case '1123':
                $this->usercontroller->updateProfile();
                break;

            case '1124':
                $this->usercontroller->getAllUsers();
                break;   

            case '1125':
                $this->usercontroller->updateUserInfo();
                break;

            case '1126':
                $this->usercontroller->deleteUser();
                break;

            case '1127':
                $this->reportcontroller->getDashboardSummary();
                break;

            case '1128':
                $this->suppliercontroller->addsupplier();
                break;

            case '1129':
                $this->suppliercontroller->getSupplierList();
                break;
        
           case '1130':
                $this->suppliercontroller->updateSupplier();
                break;

            case '1131':
                $this->suppliercontroller->deleteSupplier();
                break;

            default:
                Response::sendbody([
                    "status" => 404,
                    "message" => "Invalid Action Code!"
                ]);
                break;
        }
    }
}

$obj = new Index($userdata);