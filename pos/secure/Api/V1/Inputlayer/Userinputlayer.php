<?php

namespace Secure\Api\V1\Inputlayer;

use Secure\Core\Response;
use Secure\Connection\Database;
use Secure\Api\V1\JWT\Auth;
use PDO;

class Userinputlayer 
{
    private $userdata;

    public function __construct($data) 
    {
        $this->userdata = $data;
    }

    public function validate($method) 
    {
        switch($method) {
            case 'register':
                return $this->register();

            case 'login':
                return $this->login();

            case 'auth':
                return $this->auth();

            case 'updateProfile':
                return $this->updateProfile();

            case 'getAllUsers':
            case 'updateUserInfo':
            case 'deleteUser':
                return $this->checkRoleAndStatus(['admin']);

            case 'addCategory':
            case 'updateCategory':
            case 'deleteCategory':
            case 'addProduct':
            case 'updateProduct':
            case 'deleteProduct':
            case 'updateStock':
                return $this->checkRoleAndStatus(['admin', 'product']);

            case 'addSupplier':
            case 'updateSupplier':
            case 'deleteSupplier':
            case 'getStockTransactions':
            case 'getSupplierList':
                return $this->checkRoleAndStatus(['admin', 'product']);

            case 'getCategoryList':
            case 'getAllProducts':
            case 'getProductDetail':
            case 'getCategory':
                return $this->checkRoleAndStatus(['admin', 'product', 'sale']);

            case 'processCheckout':
            case 'getSalesHistory':
            case 'getSalesHistoryDetail':
                return $this->checkRoleAndStatus(['admin', 'sale']);

            case 'getLogs':
            case 'updateUserPermission':
            case 'getDashboardSummary':
                return $this->checkRoleAndStatus(['admin']);

            case 'addShop':
            case 'updateShop':
            case 'deleteShop':
            case 'getShopList':
                return $this->checkRoleAndStatus(['admin']);

            default:
                Response::sendbody([
                    "status" => 400,
                    "message" => "Invalid validation mode"
                ]);
                exit;
        }
    }

    private function addShop()
    {
        $token = $this->auth();

        if (empty($this->userdata->shop_name) || empty($this->userdata->owner_name) || empty($this->userdata->phone_no) || empty($this->userdata->address)) {
            Response::sendbody([
                "status" => 400,
                "message" => "All fields (shop_name, owner_name, phone_no, address) are required."
            ]);
            exit;
        }

        $this->userdata->shop_name  = htmlspecialchars(trim($this->userdata->shop_name), ENT_QUOTES, 'UTF-8');
        $this->userdata->owner_name = htmlspecialchars(trim($this->userdata->owner_name), ENT_QUOTES, 'UTF-8');
        $this->userdata->phone_no   = trim($this->userdata->phone_no);
        $this->userdata->address    = htmlspecialchars(trim($this->userdata->address), ENT_QUOTES, 'UTF-8');

        return $token;
    }

    private function updateShop()
    {
        $token = $this->auth();

        if (empty($this->userdata->uuid)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Shop UUID is required for update."
            ]);
            exit;
        }

        if (isset($this->userdata->shop_name)) {
            $this->userdata->shop_name = htmlspecialchars(trim($this->userdata->shop_name), ENT_QUOTES, 'UTF-8');
        }
        if (isset($this->userdata->owner_name)) {
            $this->userdata->owner_name = htmlspecialchars(trim($this->userdata->owner_name), ENT_QUOTES, 'UTF-8');
        }
        if (isset($this->userdata->phone_no)) {
            $this->userdata->phone_no = trim($this->userdata->phone_no);
        }
        if (isset($this->userdata->address)) {
            $this->userdata->address = htmlspecialchars(trim($this->userdata->address), ENT_QUOTES, 'UTF-8');
        }

        return $token;
    }

    private function updateProfile()
    {
        $token = $this->auth();
        
        if (empty($this->userdata->username)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Username is required."
            ]);
            exit;
        }

        $this->userdata->username = htmlspecialchars(trim($this->userdata->username), ENT_QUOTES, 'UTF-8');
        $this->userdata->phone = !empty($this->userdata->phone) ? trim($this->userdata->phone) : '';

        return $token;
    }

    private function checkRoleAndStatus(array $allowedRoles)
    {
        $token = $this->auth();
        
        $auth = new Auth();
        $decoded = $auth->decode($token);

        Database::init();
        $conn = Database::getConnection();

        $stmt = $conn->prepare("SELECT role, status FROM users WHERE usertoken = ? LIMIT 1");
        $stmt->execute([$decoded->usertoken ?? '']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::sendbody([
                "status" => 401,
                "message" => "Unauthorized access. User record not found!"
            ]);
            exit;
        }

        if ($user['status'] !== 'active') {
            Response::sendbody([
                "status" => 403,
                "message" => "Access denied! Your account status is currently " . $user['status'] . ". Please wait for admin approval."
            ]);
            exit;
        }

        if (!in_array($user['role'], $allowedRoles)) {
            Response::sendbody([
                "status" => 403,
                "message" => "Permission denied! Your role ('" . $user['role'] . "') is not authorized to perform this action."
            ]);
            exit;
        }

        return $token;
    }

    private function auth()
    {
        $headers = getallheaders();
        $token = null;

        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            } else {
                $token = $authHeader;
            }
        } elseif (isset($headers['X-TOKEN'])) {
            $token = $headers['X-TOKEN'];
        }

        if (empty($token)) {
            Response::sendbody([
                "status" => 401,
                "message" => "Authorization token is missing."
            ]);
            exit;
        }

        return $token;
    }

    private function login()
    {
        if (empty($this->userdata->email) || empty($this->userdata->password)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Email and Password are required."
            ]);
            exit;
        }

        $email = filter_var(trim($this->userdata->email), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Invalid email format"
            ]);
            exit;
        }

        $this->userdata->email = $email;
        return true;
    }

    private function register() 
    {
        if (empty($this->userdata->username) || 
            empty($this->userdata->email) || 
            empty($this->userdata->phone) || 
            empty($this->userdata->password)) {

            Response::sendbody([
                "status" => 400,
                "message" => "All fields (username, email, phone, password) are required."
            ]);
            exit; 
        }

        $username = htmlspecialchars(trim($this->userdata->username), ENT_QUOTES, 'UTF-8');
        $email    = filter_var(trim($this->userdata->email), FILTER_SANITIZE_EMAIL);
        $phone    = trim($this->userdata->phone);
        $password = $this->userdata->password;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Invalid email format"
            ]);
            exit;
        }

        if (!preg_match('/^[0-9\+\-\s]{7,15}$/', $phone)) {
            Response::sendbody([
                "status" => 400,
                "message" => "Invalid phone number format"
            ]);
            exit;
        }

        if (strlen($password) < 6) {
            Response::sendbody([
                "status" => 400,
                "message" => "Password must be at least 6 characters long"
            ]);
            exit;
        }

        $this->userdata->username = $username;
        $this->userdata->email    = $email;
        $this->userdata->phone    = $phone;

        return true;
    }
}