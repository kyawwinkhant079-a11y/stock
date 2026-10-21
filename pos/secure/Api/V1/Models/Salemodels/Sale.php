<?php

namespace Secure\Api\V1\Models\Salemodels;

use Exception;
use PDO;

class Sale 
{
    private $conn;

    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    public function getSalesHistory()
    {
        $query = "SELECT 
                    s.invoice_no, 
                    s.payment_method, 
                    MIN(s.created_at) as created_at,
                    SUM(s.subtotal) as total_amount,
                    SUM(s.quantity) as total_quantity
                  FROM sale_records s
                  GROUP BY s.invoice_no, s.payment_method
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $responseList = [];
        foreach ($results as $row) {
            $responseList[] = [
                'invoice_no'     => $row['invoice_no'],
                'created_at'     => $row['created_at'],
                'total_quantity' => (int)$row['total_quantity'],
                'total_amount'   => (float)$row['total_amount'],
                'payment_method' => $row['payment_method']
            ];
        }

        return $responseList;
    }

    public function getSaleDetailByInvoice($invoiceNo)
    {
        
        $headerQuery = "SELECT 
                            s.invoice_no, 
                            s.payment_method, 
                            s.created_username, 
                            MIN(s.created_at) as created_at,
                            SUM(s.subtotal) as total_amount,
                            SUM(s.quantity) as total_quantity
                        FROM sale_records s
                        WHERE s.invoice_no = ?
                        GROUP BY s.invoice_no, s.payment_method, s.created_username
                        LIMIT 1";

        $stmtHeader = $this->conn->prepare($headerQuery);
        $stmtHeader->execute([$invoiceNo]);
        $header = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            return null;
        }

        // 2. Fetch Purchased Items
        $itemsQuery = "SELECT 
                        sr.product_uuid, 
                        sr.product_name, 
                        sr.cost_price, 
                        sr.unit_price, 
                        sr.quantity, 
                        sr.subtotal
                       FROM sale_records sr
                       WHERE sr.invoice_no = ?";

        $stmtItems = $this->conn->prepare($itemsQuery);
        $stmtItems->execute([$invoiceNo]);
        $rawItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $itemList = [];
        foreach ($rawItems as $item) {
            $itemList[] = [
                'product_uuid' => $item['product_uuid'],
                'product_name' => $item['product_name'],
                'cost_price'   => (float)$item['cost_price'],
                'unit_price'   => (float)$item['unit_price'],
                'price'        => (float)$item['unit_price'],
                'quantity'     => (int)$item['quantity'],
                'subtotal'     => (float)$item['subtotal']
            ];
        }

        return [
            'invoice_no'       => $header['invoice_no'],
            'created_at'       => $header['created_at'],
            'created_username' => $header['created_username'],
            'payment_method'   => $header['payment_method'],
            'total_amount'     => (float)$header['total_amount'],
            'total_quantity'   => (int)$header['total_quantity'],
            'items'            => $itemList
        ];
    }
    



    // public function getSalesHistory()
    // {
    //     $summaryQuery = "SELECT 
    //                         s.invoice_no, 
    //                         s.payment_method, 
    //                         s.created_username, 
    //                         MIN(s.created_at) as created_at,
    //                         SUM(s.subtotal) as total_amount,
    //                         SUM(s.quantity) as total_quantity
    //                      FROM sale_records s
    //                      GROUP BY s.invoice_no, s.payment_method, s.created_username
    //                      ORDER BY created_at DESC";

    //     $stmt = $this->conn->prepare($summaryQuery);
    //     $stmt->execute();
    //     $salesSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //     if (empty($salesSummary)) {
    //         return [];
    //     }

    //     $itemsQuery = "SELECT 
    //                     sr.invoice_no, 
    //                     sr.product_uuid, 
    //                     sr.product_name, 
    //                     sr.cost_price, 
    //                     sr.unit_price as price, 
    //                     sr.quantity, 
    //                     sr.subtotal
    //                    FROM sale_records sr";

    //     $stmtItems = $this->conn->prepare($itemsQuery);
    //     $stmtItems->execute();
    //     $allItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    //     $groupedItems = [];
    //     foreach ($allItems as $item) {
    //         $groupedItems[$item['invoice_no']][] = [
    //             'product_uuid'  => $item['product_uuid'],
    //             'product_name'  => $item['product_name'],
    //             'category_name' => 'General', 
    //             'cost_price'    => (float)$item['cost_price'],
    //             'price'         => (float)$item['price'],
    //             'quantity'      => (int)$item['quantity'],
    //             'subtotal'      => (float)$item['subtotal']
    //         ];
    //     }

    //     $responseList = [];
    //     foreach ($salesSummary as $summary) {
    //         $invNo = $summary['invoice_no'];
    //         $responseList[] = [
    //             'invoice_no'       => $invNo,
    //             'created_at'       => $summary['created_at'],
    //             'created_username' => $summary['created_username'],
    //             'payment_method'   => $summary['payment_method'],
    //             'total_amount'     => (float)$summary['total_amount'],
    //             'total_quantity'   => (int)$summary['total_quantity'],
    //             'items'            => $groupedItems[$invNo] ?? []
    //         ];
    //     }
    //     return $responseList;
    // }


    
    public function createSaleRecords($invoiceNo, $paymentMethod, $createdUsername, $items)
    {
        $query = "INSERT INTO sale_records (
                    invoice_no, 
                    product_uuid, 
                    product_name, 
                    cost_price, 
                    unit_price, 
                    quantity, 
                    subtotal, 
                    payment_method, 
                    created_username, 
                    created_at
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);

        foreach ($items as $item) {
            $success = $stmt->execute([
                $invoiceNo,
                $item->product_uuid,
                $item->product_name,
                $item->cost_price,
                $item->price, 
                $item->quantity,
                $item->subtotal,
                $paymentMethod,
                $createdUsername
            ]);

            if (!$success) {
                throw new Exception("Failed to insert sale record for item: " . $item->product_name);
            }
        }

        return true;
    }
}