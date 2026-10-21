<?php

namespace Secure\Api\V1\Models\Reportmodels;

use PDO;

class Report 
{
    private $conn;

    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    public function getSalesSummary($filterType = 'today', $startDate = null, $endDate = null)
    {
        $inventoryQuery = "
            SELECT 
                COUNT(id) AS total_products,
                COALESCE(SUM(stock_quantity), 0) AS total_stock_count,
                COALESCE(SUM(cost_price * stock_quantity), 0) AS total_inventory_cost,
                COALESCE(SUM(sell_price * stock_quantity), 0) AS total_inventory_sell
            FROM products
        ";
        $stmtInv = $this->conn->prepare($inventoryQuery);
        $stmtInv->execute();
        $invData = $stmtInv->fetch(PDO::FETCH_ASSOC);

        $totalInvCost   = (float)$invData['total_inventory_cost'];
        $totalInvSell   = (float)$invData['total_inventory_sell'];
        $totalInvProfit = $totalInvSell - $totalInvCost;

        $whereClause = "";
        $params = [];

        if ($filterType === 'custom' && !empty($startDate) && !empty($endDate)) {
            $whereClause = "WHERE DATE(created_at) BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
        } else if ($filterType === 'custom' && !empty($startDate)) {
            $whereClause = "WHERE DATE(created_at) = ?";
            $params = [$startDate];
        } else {
            switch ($filterType) {
                case 'week':
                    $whereClause = "WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
                    break;
                case 'month':
                    $whereClause = "WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
                    break;
                case 'today':
                default:
                    $whereClause = "WHERE DATE(created_at) = CURDATE()";
                    break;
            }
        }

        $salesQuery = "
            SELECT 
                COALESCE(SUM(unit_price * quantity), 0) AS total_sales_amount,
                COALESCE(SUM(cost_price * quantity), 0) AS total_cost_amount,
                COALESCE(SUM(quantity), 0)             AS total_sold_items_count
            FROM sale_records
            {$whereClause}
        ";
        $stmtSales = $this->conn->prepare($salesQuery);
        $stmtSales->execute($params);
        $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

        $totalSalesAmount    = (float)$salesData['total_sales_amount'];
        $totalCostAmount     = (float)$salesData['total_cost_amount'];
        $totalSoldItemsCount = (int)$salesData['total_sold_items_count'];
        $totalSalesProfit    = $totalSalesAmount - $totalCostAmount;

        return [
            "filter" => $filterType,
            "custom_range" => [
                "start_date" => $startDate,
                "end_date"   => $endDate
            ],
            // Product စတော့ခ်တစ်ခုလုံး၏ ကိန်းဂဏန်းများ (Unfiltered)
            "all_products_inventory" => [
                "total_products_count" => (int)$invData['total_products'],
                "total_stock_count"    => (int)$invData['total_stock_count'],
                "total_buy_price"      => $totalInvCost,
                "total_sell_price"     => $totalInvSell,
                "total_profit"         => $totalInvProfit
            ],
            // ရောင်းရငွေဆိုင်ရာ ကိန်းဂဏန်းများ (Filtered)
            "sales_summary" => [
                "total_sales_amount"     => $totalSalesAmount,
                "total_cost_amount"      => $totalCostAmount,
                "total_profit"           => $totalSalesProfit,
                "total_sold_items_count" => $totalSoldItemsCount
            ]
        ];
    }
}