<?php

$config = [
    'host'     => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname'   => 'selling_pos',
    'jwt'      => 'selling_pos_jwttokenkey_sec_random_string_2026'
];

$conn = @new mysqli($config['host'], $config['username'], $config['password']);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$dbname = $config['dbname'];
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Database '$dbname' created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

$conn->select_db($dbname);

$queries = [
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `usertoken` VARCHAR(64) NOT NULL UNIQUE,
        `username` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `image_path` VARCHAR(255) DEFAULT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'product', 'sale', 'user') NOT NULL DEFAULT 'user',
        `status` ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `otps` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL,
        `otp` VARCHAR(6) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `expires_at` DATETIME NOT NULL,
        INDEX (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `uuid` VARCHAR(64) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `image_path` VARCHAR(255) DEFAULT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `uuid` VARCHAR(64) NOT NULL UNIQUE,
        `category_name` VARCHAR(100) NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `barcode` VARCHAR(100) DEFAULT NULL,
        `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `sell_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `stock_quantity` INT NOT NULL DEFAULT 0,
        `brand` VARCHAR(100) DEFAULT NULL,
        `model` VARCHAR(100) DEFAULT NULL,
        `image_path` VARCHAR(255) DEFAULT NULL,
        `description` VARCHAR(500) DEFAULT NULL,
        `created_user_name` VARCHAR(100) NOT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `sale_records` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_no` VARCHAR(64) NOT NULL,
        `product_uuid` VARCHAR(64) NOT NULL,
        `product_name` VARCHAR(150) NOT NULL,
        `cost_price` DECIMAL(10,2) NOT NULL,
        `unit_price` DECIMAL(10,2) NOT NULL,
        `quantity` INT NOT NULL,
        `subtotal` DECIMAL(10,2) NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `created_username` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `stock_transactions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_uuid` VARCHAR(64) NOT NULL,
        `product_name` VARCHAR(150) NOT NULL,
        `category_name` VARCHAR(100) NOT NULL,
        `type` VARCHAR(20) NOT NULL,
        `quantity` INT NOT NULL,
        `created_username` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `action` VARCHAR(50) NOT NULL,
        `module` VARCHAR(50) NOT NULL,
        `record_id` VARCHAR(64) DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `old_values` JSON DEFAULT NULL,
        `new_values` JSON DEFAULT NULL,
        `created_username` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_action` (`action`),
        INDEX `idx_module` (`module`),
        INDEX `idx_record_id` (`record_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `suppliers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `uuid` VARCHAR(64) NOT NULL UNIQUE,
        `name` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(50) NOT NULL,
        `email` VARCHAR(150) DEFAULT NULL,
        `fact` TEXT DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_uuid` (`uuid`),
        INDEX `idx_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `shops` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `uuid` VARCHAR(64) NOT NULL UNIQUE,
        `shop_name` VARCHAR(150) NOT NULL,
        `owner_name` VARCHAR(100) NOT NULL,
        `phone_no` VARCHAR(50) NOT NULL,
        `address` TEXT NOT NULL,
        `image_path` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_uuid` (`uuid`),
        INDEX `idx_shop_name` (`shop_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($queries as $index => $query) {
    if ($conn->query($query) === TRUE) {
        echo "Table " . ($index + 1) . " created successfully.<br>";
    } else {
        echo "Error creating table " . ($index + 1) . ": " . $conn->error . "<br>";
    }
}

$conn->close();
echo "<br><strong>Database Setup Completed Successfully!</strong>";