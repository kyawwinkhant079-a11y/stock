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

    public function logActivity($action, $module, $recordId, $description, $oldValues = null, $newValues = null, $username = 'System')
    {
        $query = "INSERT INTO logs (
                    action, 
                    module, 
                    record_id, 
                    description, 
                    old_values, 
                    new_values, 
                    created_username, 
                    created_at
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);

        $oldJson = $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;

        $success = $stmt->execute([
            $action,
            $module,
            $recordId,
            $description,
            $oldJson,
            $newJson,
            $username
        ]);

        if (!$success) {
            throw new Exception("Failed to insert system log record.");
        }

        return true;
    }

    public function getLogs($limit = 100, $offset = 0)
    {
        $query = "SELECT * FROM logs ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}