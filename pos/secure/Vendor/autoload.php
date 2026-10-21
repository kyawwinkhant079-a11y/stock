<?php

spl_autoload_register(function ($class) {
    $mappings = [
        'Secure\\Core\\'                       => __DIR__ . '/../Core/',
        'Secure\\Helper\\'                     => __DIR__ . '/../Helper/',
        'Secure\\Connection\\'                 => __DIR__ . '/../Connection/',
        'Secure\\Config\\'                     => __DIR__ . '/../Config/',
        'Secure\\Api\\V1\\Inputlayer\\'        => __DIR__ . '/../Api/V1/Inputlayer/',
        'Secure\\Api\\V1\\Controller\\'        => __DIR__ . '/../Api/V1/Controller/',
        'Secure\\Api\\V1\\Models\\'            => __DIR__ . '/../Api/V1/Models/',
        'Secure\\Api\\V1\\JWT\\'               => __DIR__ . '/../Api/V1/JWT/',
        'PHPMailer\\PHPMailer\\'               => __DIR__ . '/../PHPMailer/src/'
    ];

    foreach ($mappings as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) === 0) {
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require_once $file;
                return;
            } else {
                error_log("Autoload failed: $file not found");
            }
        }
    }
});