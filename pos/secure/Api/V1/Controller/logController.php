<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Logmodels\Logs;
use Secure\Connection\Database;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Logcontroller
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

    public function getLogs()
    {
        $token = $this->input->validate("getLogs");

        self::$auth->decode($token);

        $logModel = new Logs(self::$conn);

        try {
            $logs = $logModel->getLogs();

            Response::sendbody([
                "status"  => 200,
                "message" => "Activity logs retrieved successfully!",
                "data"    => $logs
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve logs: " . $e->getMessage()
            ]);
        }
    }
}