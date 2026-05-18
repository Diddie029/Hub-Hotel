-- Smart Autonomous Restaurant & Robotics Management System
-- Database Initialization Script

CREATE DATABASE IF NOT EXISTS `database`;
USE `database`;

-- Customers Table
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `phone_number` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100),
  `email` VARCHAR(100),
  `loyalty_points` INT DEFAULT 0,
  `total_spent` DECIMAL(10, 2) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Menu Table
CREATE TABLE IF NOT EXISTS `menu` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(8, 2) NOT NULL,
  `image_url` VARCHAR(255),
  `is_available` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table Monitors
CREATE TABLE IF NOT EXISTS `table_monitors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `table_id` VARCHAR(20) NOT NULL UNIQUE,
  `location_name` VARCHAR(100),
  `status` ENUM('Active', 'Inactive', 'Maintenance') DEFAULT 'Active',
  `coordinates_x` INT,
  `coordinates_y` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT,
  `table_id` VARCHAR(20) NOT NULL,
  `order_type` ENUM('Eat-in', 'Takeaway') DEFAULT 'Eat-in',
  `status` ENUM('Pending', 'Cooking', 'Ready', 'Assigned to robot', 'Delivering', 'Delivered') DEFAULT 'Pending',
  `total_amount` DECIMAL(10, 2),
  `assigned_robot_id` INT,
  `priority` ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
  `estimated_preparation_time` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`table_id`) REFERENCES `table_monitors` (`table_id`),
  INDEX (`status`),
  INDEX (`assigned_robot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `menu_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(8, 2),
  `subtotal` DECIMAL(10, 2),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments Table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `payment_method` ENUM('M-Pesa', 'Cash', 'Bank transfer') DEFAULT 'M-Pesa',
  `transaction_id` VARCHAR(100),
  `status` ENUM('Pending', 'Completed', 'Failed') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Robots Table
CREATE TABLE IF NOT EXISTS `robots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `robot_id` VARCHAR(50) NOT NULL UNIQUE,
  `status` ENUM('Idle', 'Moving', 'Delivering', 'Charging', 'Out of Service') DEFAULT 'Idle',
  `battery_level` INT DEFAULT 100,
  `position_x` INT DEFAULT 0,
  `position_y` INT DEFAULT 0,
  `current_task` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`status`),
  INDEX (`battery_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Robot Reports Table
CREATE TABLE IF NOT EXISTS `robot_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `robot_id` INT NOT NULL,
  `order_id` INT,
  `action` VARCHAR(100),
  `status_before` VARCHAR(50),
  `status_after` VARCHAR(50),
  `battery_at_action` INT,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`robot_id`) REFERENCES `robots` (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reviews Table
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `customer_id` INT,
  `rating` INT CHECK (rating >= 1 AND rating <= 5),
  `comment` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Logs Table
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `log_type` VARCHAR(50),
  `action` VARCHAR(200),
  `details` TEXT,
  `user_type` ENUM('Customer', 'Admin', 'System') DEFAULT 'System',
  `reference_id` INT,
  `status` ENUM('Success', 'Error', 'Warning') DEFAULT 'Success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`log_type`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Health Table
CREATE TABLE IF NOT EXISTS `system_health` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `metric_name` VARCHAR(100),
  `metric_value` VARCHAR(100),
  `status` ENUM('Healthy', 'Warning', 'Critical') DEFAULT 'Healthy',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`metric_name`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Menu Data
INSERT INTO `menu` (`name`, `category`, `description`, `price`, `image_url`) VALUES
('Nyama Choma', 'Main Course', 'Grilled meat with ugali', 450.00, 'assets/menu/nyama_choma.jpg'),
('Ugali', 'Main Course', 'Maize meal staple', 150.00, 'assets/menu/ugali.jpg'),
('Sukuma Wiki', 'Vegetables', 'Collard greens with tomatoes', 100.00, 'assets/menu/sukuma.jpg'),
('Mandazi', 'Breakfast', 'Fried dough pastry', 50.00, 'assets/menu/mandazi.jpg'),
('Chai', 'Drinks', 'Traditional tea', 50.00, 'assets/menu/chai.jpg'),
('Coffee', 'Drinks', 'Black coffee', 80.00, 'assets/menu/coffee.jpg'),
('Samosa', 'Appetizer', 'Fried pastry with filling', 60.00, 'assets/menu/samosa.jpg'),
('Chapati', 'Bread', 'Indian flatbread', 80.00, 'assets/menu/chapati.jpg'),
('Rice', 'Sides', 'Steamed white rice', 100.00, 'assets/menu/rice.jpg'),
('Beans', 'Sides', 'Cooked beans with spices', 150.00, 'assets/menu/beans.jpg');

-- Sample Table Monitors
INSERT INTO `table_monitors` (`table_id`, `location_name`, `status`, `coordinates_x`, `coordinates_y`) VALUES
('T001', 'Window Seat', 'Active', 100, 50),
('T002', 'Center Left', 'Active', 200, 100),
('T003', 'Center Right', 'Active', 400, 100),
('T004', 'Back Corner', 'Active', 500, 200),
('T005', 'Bar Counter', 'Active', 300, 300),
('T006', 'VIP Section', 'Active', 150, 250);

-- Sample Robots
INSERT INTO `robots` (`robot_id`, `status`, `battery_level`, `position_x`, `position_y`) VALUES
('ROBOT-001', 'Idle', 100, 0, 0),
('ROBOT-002', 'Idle', 95, 0, 0),
('ROBOT-003', 'Idle', 87, 0, 0),
('ROBOT-004', 'Charging', 40, 0, 0);
