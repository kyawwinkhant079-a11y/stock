<?php
// Database Connection Configuration (Complete POS System with Audit Logs)
$host = 'localhost';
$db   = 'selling_pos';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 1. Branches Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS branches (
        id CHAR(36) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        address TEXT,
        phone VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id CHAR(36) PRIMARY KEY,
        branch_id CHAR(36) NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('super_admin', 'branch_manager', 'cashier') DEFAULT 'cashier',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Categories Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id CHAR(36) PRIMARY KEY,
        branch_id CHAR(36) NULL,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Products Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id CHAR(36) PRIMARY KEY,
        category_id CHAR(36) NULL,
        name VARCHAR(255) NOT NULL,
        barcode VARCHAR(100) UNIQUE NOT NULL,
        cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Branch Stocks Table (Multi-branch inventory)
    $pdo->exec("CREATE TABLE IF NOT EXISTS branch_stocks (
        id CHAR(36) PRIMARY KEY,
        branch_id CHAR(36) NOT NULL,
        product_id CHAR(36) NOT NULL,
        stock_qty INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_branch_product (branch_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Sales Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id CHAR(36) PRIMARY KEY,
        branch_id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        discount DECIMAL(10,2) DEFAULT 0.00,
        final_amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 7. Sale Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS sale_items (
        id CHAR(36) PRIMARY KEY,
        sale_id CHAR(36) NOT NULL,
        product_id CHAR(36) NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 8. Stock Movements Table (Stock In/Out)
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
        id CHAR(36) PRIMARY KEY,
        branch_id CHAR(36) NOT NULL,
        product_id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        type ENUM('IN', 'OUT', 'ADJUSTMENT') NOT NULL,
        quantity INT NOT NULL,
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id),
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 9. Stock Transfers Table (Between branches)
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_transfers (
        id CHAR(36) PRIMARY KEY,
        from_branch_id CHAR(36) NOT NULL,
        to_branch_id CHAR(36) NOT NULL,
        product_id CHAR(36) NOT NULL,
        quantity INT NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_by CHAR(36) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (from_branch_id) REFERENCES branches(id),
        FOREIGN KEY (to_branch_id) REFERENCES branches(id),
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 10. Suppliers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id CHAR(36) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        contact_person VARCHAR(255),
        phone VARCHAR(50),
        email VARCHAR(255),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 11. Purchases Table (Purchase Orders from Supplier)
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
        id CHAR(36) PRIMARY KEY,
        supplier_id CHAR(36) NOT NULL,
        branch_id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        invoice_no VARCHAR(100),
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'received', 'cancelled') DEFAULT 'received',
        purchase_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 12. Purchase Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_items (
        id CHAR(36) PRIMARY KEY,
        purchase_id CHAR(36) NOT NULL,
        product_id CHAR(36) NOT NULL,
        quantity INT NOT NULL,
        unit_cost DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 13. Activity Logs Table (Admin Tracking & Audit Log)
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id CHAR(36) PRIMARY KEY,
        branch_id CHAR(36) NULL,
        user_id CHAR(36) NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Successfully created all secure POS database tables including Activity Logs!";

} catch (\PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>