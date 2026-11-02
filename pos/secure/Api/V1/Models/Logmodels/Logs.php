<?php

namespace Secure\Api\V1\Models\Logmodels;

use Exception;
use PDO;

class Logs 
{
    private $conn;

    public function __construct($conn) 
    {
        $this->conn = $conn;
    }

    /**
     * Activity Log အသစ်ထည့်သွင်းရန်
     */
    public function logActivity($branchId, $userId, $action, $description, $ipAddress = null)
    {
        // UUID (CHAR(36)) ဖန်တီးရန်
        $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $query = "INSERT INTO activity_logs (
                    id, 
                    branch_id, 
                    user_id, 
                    action, 
                    description, 
                    ip_address, 
                    created_at
                  ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);

        $success = $stmt->execute([
            $id,
            $branchId,
            $userId,
            $action,
            $description,
            $ipAddress
        ]);

        if (!$success) {
            throw new Exception("Failed to insert activity log record.");
        }

        return true;
    }

    /**
     * Logs များကို ဆွဲထုတ်ရန် (Branch အလိုက် သို့မဟုတ် အားလုံး)
     */
    public function getLogs($branchId = null, $limit = 100, $offset = 0)
    {
        if ($branchId) {
            $query = "SELECT al.*, u.name as user_name, b.name as branch_name 
                      FROM activity_logs al
                      LEFT JOIN users u ON al.user_id = u.id
                      LEFT JOIN branches b ON al.branch_id = b.id
                      WHERE al.branch_id = :branch_id 
                      ORDER BY al.created_at DESC 
                      LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':branch_id', $branchId, PDO::PARAM_STR);
        } else {
            $query = "SELECT al.*, u.name as user_name, b.name as branch_name 
                      FROM activity_logs al
                      LEFT JOIN users u ON al.user_id = u.id
                      LEFT JOIN branches b ON al.branch_id = b.id
                      ORDER BY al.created_at DESC 
                      LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($query);
        }

        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}