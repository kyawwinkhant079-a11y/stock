<?php

namespace Secure\Config;

class Config
{
    public static function get(): array
    {
        return [
            'host'     => 'localhost',
            'username' => 'root',
            'password' => '',
            'dbname'   => 'selling_pos',
            'jwt'      => 'selling_pos_jwttokenkey_sec_random_string_2026'
        ];
    }
}