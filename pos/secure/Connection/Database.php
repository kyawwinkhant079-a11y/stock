<?php

namespace Secure\Connection;

use Secure\Config\Config;
use PDO;

class Database
{
    private static $conn = null;

    public static function init()
    {
        if (self::$conn === null) {
            $config = Config::get(); 
            
            try {
                self::$conn = new PDO(
                    "mysql:host=" . $config['host'] . ";dbname=" . $config['dbname'],
                    $config['username'],
                    $config['password']
                );
            } catch (\PDOException $e) {
                
            }
        }
    }

    public static function getConnection()
    {
        return self::$conn;
    }
}