-- ClassicCuts Barbershop Database Schema
-- Database: barber_db

CREATE DATABASE IF NOT EXISTS `barber_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `barber_db`;

-- Table structure for `appointments`
CREATE TABLE IF NOT EXISTS `appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `services` TEXT DEFAULT NULL,
    `staff` VARCHAR(100) DEFAULT NULL,
    `appointment_date` DATE DEFAULT NULL,
    `appointment_time` VARCHAR(50) DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for `contacts`
CREATE TABLE IF NOT EXISTS `contacts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for `admins`
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for `staff`
CREATE TABLE IF NOT EXISTS `staff` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
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
    `account_status` VARCHAR(20) DEFAULT 'approved',
    `approved_at` DATETIME DEFAULT NULL,
    `last_login_at` DATETIME DEFAULT NULL,
    `registration_token` VARCHAR(64) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for `reviews`
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_name` VARCHAR(150) NOT NULL,
    `rating` INT DEFAULT 5,
    `comment` TEXT NOT NULL,
    `status` VARCHAR(20) DEFAULT 'Approved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin account (username: admin, password: admin123)
INSERT IGNORE INTO `admins` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$4nC7vK5b1q9Y/K4K4y9W8u2E3gZ.O0T8fE6zK2v7rQ5W.L2H8uK9G');

-- Default Staff Accounts
INSERT IGNORE INTO `staff` (`id`, `name`, `email`, `phone`, `role`, `experience_years`, `base_salary`, `commission_rate`, `photo`) VALUES
(1, 'Ram Bahadur',    'ram@classiccuts.com',    '9801111111', 'Master Barber',    8, 35000.00, 20.00, 'images/team/1.jpg'),
(2, 'Shyam Kumar',    'shyam@classiccuts.com',  '9802222222', 'Senior Stylist',   5, 28000.00, 18.00, 'images/team/2.jpg'),
(3, 'Ramesh Shrestha','ramesh@classiccuts.com', '9803333333', 'Barber & Stylist', 3, 22000.00, 15.00, 'images/team/3.jpg'),
(4, 'Hari Sharma',    'hari@classiccuts.com',   '9804444444', 'Junior Barber',    2, 16000.00, 12.00, 'images/team/4.jpg');

-- Default Customer Reviews
INSERT IGNORE INTO `reviews` (`id`, `customer_name`, `rating`, `comment`, `status`) VALUES
(1, 'Robert Smith', 5, 'Best haircut and beard trim in town! Steven is a master at his craft.', 'Approved'),
(2, 'Michael Johnson', 5, 'Great atmosphere and professional service. Highly recommended!', 'Approved'),
(3, 'David Brown', 5, 'Loved the royal shave experience. Will definitely come back!', 'Approved');
