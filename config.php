<?php
/**
 * ClassicCuts Barbershop - Database Connection & Auto Schema Upgrades
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'barber_db');

$db_connected = false;
$db_error = '';
$pdo = null;
$mysqli = null;

if (extension_loaded('pdo_mysql')) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        // Appointments table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `appointments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `appointment_no` VARCHAR(50) DEFAULT NULL,
                `name` VARCHAR(150) NOT NULL,
                `email` VARCHAR(150) NOT NULL,
                `phone` VARCHAR(50) NOT NULL,
                `services` TEXT DEFAULT NULL,
                `staff` VARCHAR(100) DEFAULT NULL,
                `appointment_date` DATE DEFAULT NULL,
                `appointment_time` VARCHAR(50) DEFAULT NULL,
                `price` DECIMAL(10,2) DEFAULT 500.00,
                `payment_method` VARCHAR(50) DEFAULT 'Cash',
                `txn_id` VARCHAR(100) DEFAULT NULL,
                `payment_status` VARCHAR(50) DEFAULT 'Pending (Unpaid)',
                `message` TEXT DEFAULT NULL,
                `status` VARCHAR(20) DEFAULT 'Pending',
                `is_read` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Individual Alters for Existing Databases
        try { $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `appointment_no` VARCHAR(50) DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `price` DECIMAL(10,2) DEFAULT 500.00"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `is_read` INT DEFAULT 0"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `status` VARCHAR(20) DEFAULT 'Pending'"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `payment_status` VARCHAR(50) DEFAULT 'Pending (Unpaid)'"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'Cash'"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `txn_id` VARCHAR(100) DEFAULT NULL"); } catch (Exception $ex) {}

        // Contacts table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `contacts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(150) NOT NULL,
                `email` VARCHAR(150) NOT NULL,
                `phone` VARCHAR(50) DEFAULT NULL,
                `message` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Admins table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `admins` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) UNIQUE NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Staff table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `staff` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(150) DEFAULT NULL,
                `phone` VARCHAR(50) DEFAULT NULL,
                `password` VARCHAR(255) NOT NULL,
                `role` VARCHAR(100) DEFAULT 'Barber',
                `experience_years` INT DEFAULT 1,
                `base_salary` DECIMAL(10,2) DEFAULT 20000.00,
                `commission_rate` DECIMAL(5,2) DEFAULT 15.00,
                `esewa_id` VARCHAR(50) DEFAULT NULL,
                `bank_name` VARCHAR(100) DEFAULT NULL,
                `bank_acc_num` VARCHAR(100) DEFAULT NULL,
                `salary_status` VARCHAR(50) DEFAULT 'Unpaid',
                `bio` TEXT DEFAULT NULL,
                `photo` VARCHAR(255) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `password` VARCHAR(255) DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `email` VARCHAR(150) DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `phone` VARCHAR(50) DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `role` VARCHAR(100) DEFAULT 'Barber'"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `experience_years` INT DEFAULT 1"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `base_salary` DECIMAL(10,2) DEFAULT 20000.00"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `commission_rate` DECIMAL(5,2) DEFAULT 15.00"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `esewa_id` VARCHAR(50) DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `bank_name` VARCHAR(100) DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `bank_acc_num` VARCHAR(100) DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `salary_status` VARCHAR(50) DEFAULT 'Unpaid'"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `bio` TEXT DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `photo` VARCHAR(255) DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `account_status` VARCHAR(20) DEFAULT 'approved'"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `approved_at` DATETIME DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `last_login_at` DATETIME DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `registration_token` VARCHAR(64) DEFAULT NULL"); } catch (Exception $ex) {}
        // Set existing pre-seeded staff as approved so they can still login
        try { $pdo->exec("UPDATE `staff` SET account_status = 'approved', approved_at = NOW() WHERE account_status IS NULL OR account_status = ''"); } catch (Exception $ex) {}

        // Reviews table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `reviews` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `customer_name` VARCHAR(150) NOT NULL,
                `rating` INT DEFAULT 5,
                `comment` TEXT NOT NULL,
                `status` VARCHAR(20) DEFAULT 'Approved',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Seed default admin account (username: admin, password: admin123)
        $stmtCheck = $pdo->query("SELECT COUNT(*) FROM admins");
        if ($stmtCheck->fetchColumn() == 0) {
            $defaultPassHash = password_hash('admin123', PASSWORD_DEFAULT);
            $stmtSeed = $pdo->prepare("INSERT INTO admins (username, password) VALUES ('admin', :pass)");
            $stmtSeed->execute([':pass' => $defaultPassHash]);
        }

        // Seed Nepali staff members if empty
        $stmtStaffCheck = $pdo->query("SELECT COUNT(*) FROM staff");
        if ($stmtStaffCheck->fetchColumn() == 0) {
            $defaultStaffPass = password_hash('staff123', PASSWORD_DEFAULT);
            $defaultStaff = [
                ['Ram Bahadur',    'ram@classiccuts.com',    '9801111111', $defaultStaffPass, 'Master Barber',    8, 35000.00, 20.00, '9801111111', 'Nabil Bank', '01201017500012', 'Paid via eSewa', 'Expert in skin fades & hair styling', 'images/team/1.jpg'],
                ['Shyam Kumar',    'shyam@classiccuts.com',  '9802222222', $defaultStaffPass, 'Senior Stylist',   5, 28000.00, 18.00, '9802222222', 'NIC Asia Bank', '10293847561023', 'Unpaid', 'Specialist in razor shave & beard shape up', 'images/team/2.jpg'],
                ['Ramesh Shrestha','ramesh@classiccuts.com', '9803333333', $defaultStaffPass, 'Barber & Stylist', 3, 22000.00, 15.00, '9803333333', 'Global IME Bank', '091827364510', 'Paid via Bank', 'Expert in facial & head massage', 'images/team/3.jpg'],
                ['Hari Sharma',    'hari@classiccuts.com',   '9804444444', $defaultStaffPass, 'Junior Barber',    2, 16000.00, 12.00, '9804444444', 'Everest Bank', '00192837465011', 'Unpaid', 'Specialist in kids haircuts', 'images/team/4.jpg']
            ];
            $stmtInsStaff = $pdo->prepare("
                INSERT INTO staff (name, email, phone, password, role, experience_years, base_salary, commission_rate, esewa_id, bank_name, bank_acc_num, salary_status, bio, photo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($defaultStaff as $st) {
                $stmtInsStaff->execute($st);
            }
        } else {
            // Update existing database staff rows if they still have old default names
            try {
                $pdo->exec("UPDATE staff SET name='Ram Bahadur', email='ram@classiccuts.com', photo='images/team/1.jpg' WHERE id=1 AND name IN ('Steven','Raj Kumari Saiba','Frank','John')");
                $pdo->exec("UPDATE staff SET name='Shyam Kumar', email='shyam@classiccuts.com', photo='images/team/2.jpg' WHERE id=2 AND name IN ('Huey','Pratik PK','Barney')");
                $pdo->exec("UPDATE staff SET name='Ramesh Shrestha', email='ramesh@classiccuts.com', photo='images/team/3.jpg' WHERE id=3 AND name IN ('Harry','Nujal Shrestha')");
                $pdo->exec("UPDATE staff SET name='Hari Sharma', email='hari@classiccuts.com', photo='images/team/4.jpg' WHERE id=4 AND name IN ('Axe','Samir Maharjan')");
                $pdo->exec("UPDATE appointments SET staff='Ram Bahadur' WHERE staff='Steven'");
                $pdo->exec("UPDATE appointments SET staff='Shyam Kumar' WHERE staff='Huey'");
                $pdo->exec("UPDATE appointments SET staff='Ramesh Shrestha' WHERE staff='Harry'");
                $pdo->exec("UPDATE appointments SET staff='Hari Sharma' WHERE staff='Axe'");
            } catch (Exception $ex) {}
        }

        $db_connected = true;
    } catch (PDOException $e) {
        $db_error = "Database Error: " . $e->getMessage();
    }
} elseif (extension_loaded('mysqli')) {
    try {
        $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS);
        if (!$mysqli->connect_error) {
            $mysqli->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $mysqli->select_db(DB_NAME);

            $mysqli->query("
                CREATE TABLE IF NOT EXISTS `appointments` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `appointment_no` VARCHAR(50) DEFAULT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `email` VARCHAR(150) NOT NULL,
                    `phone` VARCHAR(50) NOT NULL,
                    `services` TEXT DEFAULT NULL,
                    `staff` VARCHAR(100) DEFAULT NULL,
                    `appointment_date` DATE DEFAULT NULL,
                    `appointment_time` VARCHAR(50) DEFAULT NULL,
                    `price` DECIMAL(10,2) DEFAULT 500.00,
                    `payment_method` VARCHAR(50) DEFAULT 'Cash',
                    `txn_id` VARCHAR(100) DEFAULT NULL,
                    `payment_status` VARCHAR(50) DEFAULT 'Pending (Unpaid)',
                    `message` TEXT DEFAULT NULL,
                    `status` VARCHAR(20) DEFAULT 'Pending',
                    `is_read` INT DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            @$mysqli->query("ALTER TABLE `appointments` ADD COLUMN `appointment_no` VARCHAR(50) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `appointments` ADD COLUMN `price` DECIMAL(10,2) DEFAULT 500.00");
            @$mysqli->query("ALTER TABLE `appointments` ADD COLUMN `is_read` INT DEFAULT 0");
            @$mysqli->query("ALTER TABLE `appointments` ADD COLUMN `payment_status` VARCHAR(50) DEFAULT 'Pending (Unpaid)'");
            @$mysqli->query("ALTER TABLE `appointments` ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'Cash'");
            @$mysqli->query("ALTER TABLE `appointments` ADD COLUMN `txn_id` VARCHAR(100) DEFAULT NULL");

            $mysqli->query("
                CREATE TABLE IF NOT EXISTS `contacts` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(150) NOT NULL,
                    `email` VARCHAR(150) NOT NULL,
                    `phone` VARCHAR(50) DEFAULT NULL,
                    `message` TEXT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $mysqli->query("
                CREATE TABLE IF NOT EXISTS `admins` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `username` VARCHAR(50) UNIQUE NOT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $mysqli->query("
                CREATE TABLE IF NOT EXISTS `staff` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `email` VARCHAR(150) DEFAULT NULL,
                    `phone` VARCHAR(50) DEFAULT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `role` VARCHAR(100) DEFAULT 'Barber',
                    `experience_years` INT DEFAULT 1,
                    `base_salary` DECIMAL(10,2) DEFAULT 20000.00,
                    `commission_rate` DECIMAL(5,2) DEFAULT 15.00,
                    `esewa_id` VARCHAR(50) DEFAULT NULL,
                    `bank_name` VARCHAR(100) DEFAULT NULL,
                    `bank_acc_num` VARCHAR(100) DEFAULT NULL,
                    `salary_status` VARCHAR(50) DEFAULT 'Unpaid',
                    `bio` TEXT DEFAULT NULL,
                    `photo` VARCHAR(255) DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `password` VARCHAR(255) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `email` VARCHAR(150) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `phone` VARCHAR(50) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `role` VARCHAR(100) DEFAULT 'Barber'");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `experience_years` INT DEFAULT 1");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `base_salary` DECIMAL(10,2) DEFAULT 20000.00");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `commission_rate` DECIMAL(5,2) DEFAULT 15.00");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `esewa_id` VARCHAR(50) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `bank_name` VARCHAR(100) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `bank_acc_num` VARCHAR(100) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `salary_status` VARCHAR(50) DEFAULT 'Unpaid'");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `bio` TEXT DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `photo` VARCHAR(255) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `account_status` VARCHAR(20) DEFAULT 'approved'");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `approved_at` DATETIME DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `last_login_at` DATETIME DEFAULT NULL");
            @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `registration_token` VARCHAR(64) DEFAULT NULL");
            @$mysqli->query("UPDATE `staff` SET account_status = 'approved', approved_at = NOW() WHERE account_status IS NULL OR account_status = ''");


            $mysqli->query("
                CREATE TABLE IF NOT EXISTS `reviews` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `customer_name` VARCHAR(150) NOT NULL,
                    `rating` INT DEFAULT 5,
                    `comment` TEXT NOT NULL,
                    `status` VARCHAR(20) DEFAULT 'Approved',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $resCount = $mysqli->query("SELECT COUNT(*) as cnt FROM admins");
            $row = $resCount ? $resCount->fetch_assoc() : null;
            if (!$row || $row['cnt'] == 0) {
                $defaultPassHash = password_hash('admin123', PASSWORD_DEFAULT);
                $stmtSeed = $mysqli->prepare("INSERT INTO admins (username, password) VALUES ('admin', ?)");
                $stmtSeed->bind_param("s", $defaultPassHash);
                $stmtSeed->execute();
            }

            $resStaffCount = $mysqli->query("SELECT COUNT(*) as cnt FROM staff");
            $rowS = $resStaffCount ? $resStaffCount->fetch_assoc() : null;
            if (!$rowS || $rowS['cnt'] == 0) {
                $defaultStaffPass = password_hash('staff123', PASSWORD_DEFAULT);
                $defaultStaff = [
                    ['Ram Bahadur',    'ram@classiccuts.com',    '9801111111', $defaultStaffPass, 'Master Barber',    8, 35000.00, 20.00, '9801111111', 'Nabil Bank', '01201017500012', 'Paid via eSewa', 'Expert in skin fades & hair styling', 'images/team/1.jpg'],
                    ['Shyam Kumar',    'shyam@classiccuts.com',  '9802222222', $defaultStaffPass, 'Senior Stylist',   5, 28000.00, 18.00, '9802222222', 'NIC Asia Bank', '10293847561023', 'Unpaid', 'Specialist in razor shave & beard shape up', 'images/team/2.jpg'],
                    ['Ramesh Shrestha','ramesh@classiccuts.com', '9803333333', $defaultStaffPass, 'Barber & Stylist', 3, 22000.00, 15.00, '9803333333', 'Global IME Bank', '091827364510', 'Paid via Bank', 'Expert in facial & head massage', 'images/team/3.jpg'],
                    ['Hari Sharma',    'hari@classiccuts.com',   '9804444444', $defaultStaffPass, 'Junior Barber',    2, 16000.00, 12.00, '9804444444', 'Everest Bank', '00192837465011', 'Unpaid', 'Specialist in kids haircuts', 'images/team/4.jpg']
                ];
                $stmtInsStaff = $mysqli->prepare("INSERT INTO staff (name, email, phone, password, role, experience_years, base_salary, commission_rate, esewa_id, bank_name, bank_acc_num, salary_status, bio, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($defaultStaff as $st) {
                    $stmtInsStaff->bind_param("sssssiddssssss", $st[0], $st[1], $st[2], $st[3], $st[4], $st[5], $st[6], $st[7], $st[8], $st[9], $st[10], $st[11], $st[12], $st[13]);
                    $stmtInsStaff->execute();
                }
            } else {
                @$mysqli->query("UPDATE staff SET name='Ram Bahadur', email='ram@classiccuts.com', photo='images/team/1.jpg' WHERE id=1 AND name IN ('Steven','Raj Kumari Saiba','Frank','John')");
                @$mysqli->query("UPDATE staff SET name='Shyam Kumar', email='shyam@classiccuts.com', photo='images/team/2.jpg' WHERE id=2 AND name IN ('Huey','Pratik PK','Barney')");
                @$mysqli->query("UPDATE staff SET name='Ramesh Shrestha', email='ramesh@classiccuts.com', photo='images/team/3.jpg' WHERE id=3 AND name IN ('Harry','Nujal Shrestha')");
                @$mysqli->query("UPDATE staff SET name='Hari Sharma', email='hari@classiccuts.com', photo='images/team/4.jpg' WHERE id=4 AND name IN ('Axe','Samir Maharjan')");
                @$mysqli->query("UPDATE appointments SET staff='Ram Bahadur' WHERE staff='Steven'");
                @$mysqli->query("UPDATE appointments SET staff='Shyam Kumar' WHERE staff='Huey'");
                @$mysqli->query("UPDATE appointments SET staff='Ramesh Shrestha' WHERE staff='Harry'");
                @$mysqli->query("UPDATE appointments SET staff='Hari Sharma' WHERE staff='Axe'");
            }

            $db_connected = true;
        } else {
            $db_error = "MySQLi Connection Error: " . $mysqli->connect_error;
        }
    } catch (Exception $e) {
        $db_error = "MySQLi Error: " . $e->getMessage();
    }
} else {
    $db_error = "Neither pdo_mysql nor mysqli extension is enabled in php.ini. Please enable extension=pdo_mysql in your php.ini file.";
}
