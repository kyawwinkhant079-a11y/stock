<?php

namespace Secure\Api\V1\JWT;

use Secure\Core\Response;
use Secure\Config\Config;

class Auth 
{
    private $secretKey;

    public function __construct() 
    {
        $config = Config::get();
        $this->secretKey = $config['jwt'];
    }

    private function base64UrlEncode($data) 
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function base64UrlDecode($data) 
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }

    public function encode($payload) 
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->secretKey, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Decode and validate token signature & expiration
     */
    public function decode($token) 
    {
        if (!$token) {
            Response::sendbody([
                "status" => 401,
                "message" => "Unauthorized access. Token missing!"
            ]);
            exit;
        }

        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            Response::sendbody([
                "status" => 401,
                "message" => "Invalid token format!"
            ]);
            exit;
        }

        list($header, $payload, $signature) = $tokenParts;

        $validSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . "." . $payload, $this->secretKey, true)
        );

        if (!hash_equals($validSignature, $signature)) {
            Response::sendbody([
                "status" => 401,
                "message" => "Invalid token signature!"
            ]);
            exit;
        }

        $decodedPayload = json_decode($this->base64UrlDecode($payload));

        if (isset($decodedPayload->exp) && $decodedPayload->exp < time()) {
            Response::sendbody([
                "status" => 401,
                "message" => "Token has expired!"
            ]);
            exit;
        }

        return $decodedPayload;
    }
}