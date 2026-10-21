<?php

namespace Secure\Api\V1\Controller;

use Secure\Core\Response;
use Secure\Api\V1\Inputlayer\Userinputlayer;
use Secure\Api\V1\Models\Reportmodels\Report;
use Secure\Connection\Database;
use Secure\Config\Config;
use Secure\Api\V1\JWT\Auth;
use Exception;

date_default_timezone_set('Asia/Yangon');

class Reportcontroller
{
    private $report;
    private $input;
    public static $conn;
    public static $userdata;
    public static $config;
    public static $auth;

    public function __construct($userdata) 
    {
        Database::init();
        self::$conn = Database::getConnection();
        
        $this->report = new Report(self::$conn);
        $this->input = new Userinputlayer($userdata);
        
        self::$userdata = $userdata;
        self::$config = Config::get();
        self::$auth = new Auth();
    }

    public function getDashboardSummary()
    {
        $token = $this->input->validate("getDashboardSummary");
        self::$auth->decode($token);

        $filter    = self::$userdata->filter ?? 'today';
        $startDate = self::$userdata->start_date ?? null;
        $endDate   = self::$userdata->end_date ?? null;

        if (!in_array($filter, ['today', 'week', 'month', 'custom'])) {
            $filter = 'today';
        }

        try {
            $summaryData = $this->report->getSalesSummary($filter, $startDate, $endDate);

            Response::sendbody([
                "status"  => 200,
                "message" => "Dashboard summary retrieved successfully!",
                "data"    => $summaryData
            ]);

        } catch (Exception $e) {
            Response::sendbody([
                "status"  => 500,
                "message" => "Failed to retrieve summary: " . $e->getMessage()
            ]);
        }
    }
}