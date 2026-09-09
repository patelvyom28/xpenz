-- XPenz Master Database Schema

CREATE DATABASE IF NOT EXISTS `xpenz_db`;
USE `xpenz_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Transactions Table
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `type` ENUM('income', 'expense') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Subscriptions & Bills Table
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `billing_cycle` ENUM('monthly', 'yearly') DEFAULT 'monthly',
  `due_date` DATE NOT NULL,
  `category` VARCHAR(50) DEFAULT 'Subscription',
  `status` ENUM('active', 'cancelled') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Goal-based Savings Table
CREATE TABLE IF NOT EXISTS `goals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `goal_name` VARCHAR(100) NOT NULL,
  `target_amount` DECIMAL(10,2) NOT NULL,
  `current_amount` DECIMAL(10,2) DEFAULT 0.00,
  `target_date` DATE NOT NULL,
  `status` ENUM('in_progress', 'achieved') DEFAULT 'in_progress',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Loans, Insurance & EMI Portfolio Table
CREATE TABLE IF NOT EXISTS `loans_insurance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `type` ENUM('loan', 'insurance', 'emi') NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `monthly_installment` DECIMAL(10,2) NOT NULL,
  `due_day` INT NOT NULL,
  `lender_provider` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;