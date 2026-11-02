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

    public function getSalesSummary($branchId = null, $filterType = 'today', $startDate = null, $endDate = null)
    {
        // 1. Inventory Summary (Branch Stocks သို့မဟုတ် Products အလိုက် တွက်ချက်ခြင်း)
        if ($branchId) {
            $inventoryQuery = "
                SELECT 
                    COUNT(p.id) AS total_products,
                    COALESCE(SUM(bs.stock_qty), 0) AS total_stock_count,
                    COALESCE(SUM(p.cost_price * bs.stock_qty), 0) AS total_inventory_cost,
                    COALESCE(SUM(p.sale_price * bs.stock_qty), 0) AS total_inventory_sell
                FROM products p
                JOIN branch_stocks bs ON p.id = bs.product_id
                WHERE bs.branch_id = ?
            ";
            $stmtInv = $this->conn->prepare($inventoryQuery);
            $stmtInv->execute([$branchId]);
        } else {
            $inventoryQuery = "
                SELECT 
                    COUNT(p.id) AS total_products,
                    COALESCE(SUM(bs.stock_qty), 0) AS total_stock_count,
                    COALESCE(SUM(p.cost_price * bs.stock_qty), 0) AS total_inventory_cost,
                    COALESCE(SUM(p.sale_price * bs.stock_qty), 0) AS total_inventory_sell
                FROM products p
                LEFT JOIN branch_stocks bs ON p.id = bs.product_id
            ";
            $stmtInv = $this->conn->prepare($inventoryQuery);
            $stmtInv->execute();
        }
        
        $invData = $stmtInv->fetch(PDO::FETCH_ASSOC);

        $totalInvCost   = (float)($invData['total_inventory_cost'] ?? 0);
        $totalInvSell   = (float)($invData['total_inventory_sell'] ?? 0);
        $totalInvProfit = $totalInvSell - $totalInvCost;

        // 2. Sales Filter Clause (sales ဇယားပါ created_at ကို အခြေခံသည်)
        $whereClause = "";
        $params = [];

        if ($branchId) {
            $whereClause = "WHERE s.branch_id = ?";
            $params[] = $branchId;
        }

        if ($filterType === 'custom' && !empty($startDate) && !empty($endDate)) {
            $whereClause .= empty($whereClause) ? "WHERE " : " AND ";
            $whereClause .= "DATE(s.created_at) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        } else if ($filterType === 'custom' && !empty($startDate)) {
            $whereClause .= empty($whereClause) ? "WHERE " : " AND ";
            $whereClause .= "DATE(s.created_at) = ?";
            $params[] = $startDate;
        } else {
            $timeCondition = "";
            switch ($filterType) {
                case 'week':
                    $timeCondition = "YEARWEEK(s.created_at, 1) = YEARWEEK(CURDATE(), 1)";
                    break;
                case 'month':
                    $timeCondition = "MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
                    break;
                case 'today':
                default:
                    $timeCondition = "DATE(s.created_at) = CURDATE()";
                    break;
            }
            $whereClause .= empty($whereClause) ? "WHERE " . $timeCondition : " AND " . $timeCondition;
        }

        // 3. Sales & Profit Query (sales နှင့် sale_items ဇယားများကို ပေါင်းစပ်တွက်ချက်ခြင်း)
        $salesQuery = "
            SELECT 
                COALESCE(SUM(si.subtotal), 0)           AS total_sales_amount,
                COALESCE(SUM(p.cost_price * si.quantity), 0) AS total_cost_amount,
                COALESCE(SUM(si.quantity), 0)           AS total_sold_items_count
            FROM sales s
            JOIN sale_items si ON s.id = si.sale_id
            JOIN products p ON si.product_id = p.id
            {$whereClause}
        ";
        
        $stmtSales = $this->conn->prepare($salesQuery);
        $stmtSales->execute($params);
        $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

        $totalSalesAmount    = (float)($salesData['total_sales_amount'] ?? 0);
        $totalCostAmount     = (float)($salesData['total_cost_amount'] ?? 0);
        $totalSoldItemsCount = (int)($salesData['total_sold_items_count'] ?? 0);
        $totalSalesProfit    = $totalSalesAmount - $totalCostAmount;

        return [
            "filter"       => $filterType,
            "branch_id"    => $branchId,
            "custom_range" => [
                "start_date" => $startDate,
                "end_date"   => $endDate
            ],
            // ပစ္စည်းစတော့ခ် စုစုပေါင်း အချက်အလက်များ
            "all_products_inventory" => [
                "total_products_count" => (int)($invData['total_products'] ?? 0),
                "total_stock_count"    => (int)($invData['total_stock_count'] ?? 0),
                "total_buy_price"      => $totalInvCost,
                "total_sell_price"     => $totalInvSell,
                "total_profit"         => $totalInvProfit
            ],
            // ရောင်းရငွေဆိုင်ရာ အချက်အလက်များ ( Filter ဖြင့် )
            "sales_summary" => [
                "total_sales_amount"     => $totalSalesAmount,
                "total_cost_amount"      => $totalCostAmount,
                "total_profit"           => $totalSalesProfit,
                "total_sold_items_count" => $totalSoldItemsCount
            ]
        ];
    }
}