<?php

namespace Secure\Core;

class Response
{
    public static function sendbody($data) 
    {
        if (ob_get_length()) {
            ob_clean();
        }

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, X-TOKEN, Authorization");
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        $statusCode = isset($data['status']) ? (int)$data['status'] : 200;
        http_response_code($statusCode);

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit(); 
    }

    public static function sendheader($token)
    {
        header('Access-Control-Expose-Headers: X-TOKEN');
        header('X-TOKEN: ' . $token);
    }

    public static function generatetoken() 
    {
        return strtoupper(bin2hex(random_bytes(32)));
    }
}