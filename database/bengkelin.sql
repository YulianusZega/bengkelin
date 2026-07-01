-- ============================================================
-- BENGKELIN - Aplikasi Manajemen Bengkel Otomotif
-- Database Schema - Phase 1
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+07:00";

CREATE DATABASE IF NOT EXISTS `bengkelin` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bengkelin`;

-- --------------------------------------------------------
-- Table: users (untuk login sistem)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','admin','kabeng','mekanik') DEFAULT 'mekanik',
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: employees (data karyawan detail)
-- --------------------------------------------------------
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `employee_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `position` enum('owner','admin','kabeng','mekanik') NOT NULL,
  `specialization` text DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT 0.00,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: customers
-- --------------------------------------------------------
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `segment` enum('new','regular','vip') DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_code` (`customer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: vehicles
-- --------------------------------------------------------
CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` int(4) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `type` enum('mobil','motor') NOT NULL,
  `vehicle_subtype` varchar(50) DEFAULT NULL,
  `km_current` int(11) DEFAULT 0,
  `km_last_service` int(11) DEFAULT 0,
  `fuel_type` enum('bensin','diesel','listrik') DEFAULT 'bensin',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: service_categories
-- --------------------------------------------------------
CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `vehicle_type` enum('mobil','motor','both') DEFAULT 'both',
  `color` varchar(7) DEFAULT '#FF6B2B',
  `icon` varchar(50) DEFAULT 'fa-wrench',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: services
-- --------------------------------------------------------
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price_car` decimal(12,2) DEFAULT 0.00,
  `price_motorcycle` decimal(12,2) DEFAULT 0.00,
  `duration_hours` decimal(4,1) DEFAULT 1.0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `service_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: part_categories
-- --------------------------------------------------------
CREATE TABLE `part_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: parts (sparepart & produk)
-- --------------------------------------------------------
CREATE TABLE `parts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'pcs',
  `stock` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 5,
  `buy_price` decimal(12,2) DEFAULT 0.00,
  `sell_price` decimal(12,2) DEFAULT 0.00,
  `vehicle_type` enum('mobil','motor','both') DEFAULT 'both',
  `shelf_location` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  FOREIGN KEY (`category_id`) REFERENCES `part_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: work_orders
-- --------------------------------------------------------
CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wo_number` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `mechanic_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `status` enum('waiting','inspection','approved','in_progress','qc','done','delivered','cancelled') DEFAULT 'waiting',
  `priority` enum('normal','urgent') DEFAULT 'normal',
  `check_in_at` datetime NOT NULL,
  `estimated_finish` datetime DEFAULT NULL,
  `actual_finish` datetime DEFAULT NULL,
  `km_in` int(11) DEFAULT 0,
  `complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT 0.00,
  `customer_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `subtotal_services` decimal(12,2) DEFAULT 0.00,
  `subtotal_parts` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `tax` decimal(12,2) DEFAULT 0.00,
  `total` decimal(12,2) DEFAULT 0.00,
  `payment_method` enum('cash','transfer','qris','debit') DEFAULT 'cash',
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `wa_sent_start` tinyint(1) DEFAULT 0,
  `wa_sent_done` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wo_number` (`wo_number`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`),
  FOREIGN KEY (`mechanic_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: wo_services (jasa yang dikerjakan per WO)
-- --------------------------------------------------------
CREATE TABLE `wo_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wo_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_name` varchar(100) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`wo_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: wo_parts (sparepart yang dipakai per WO)
-- --------------------------------------------------------
CREATE TABLE `wo_parts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wo_id` int(11) NOT NULL,
  `part_id` int(11) DEFAULT NULL,
  `part_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `buy_price` decimal(12,2) DEFAULT 0.00,
  `sell_price` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`wo_id`) REFERENCES `work_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: bookings (booking online publik)
-- --------------------------------------------------------
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_number` varchar(30) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `vehicle_brand` varchar(50) NOT NULL,
  `vehicle_model` varchar(50) NOT NULL,
  `vehicle_year` int(4) DEFAULT NULL,
  `plate_number` varchar(20) DEFAULT NULL,
  `vehicle_type` enum('mobil','motor') NOT NULL,
  `service_type` varchar(255) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` time NOT NULL,
  `complaint` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','converted') DEFAULT 'pending',
  `converted_to_wo` int(11) DEFAULT NULL,
  `wa_sent` tinyint(1) DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  FOREIGN KEY (`converted_to_wo`) REFERENCES `work_orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: stock_movements
-- --------------------------------------------------------
CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `part_id` int(11) NOT NULL,
  `type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` enum('purchase','wo','adjustment','return') DEFAULT 'purchase',
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: wa_logs
-- --------------------------------------------------------
CREATE TABLE `wa_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `response` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: settings
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Default Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('bengkel_name', 'Bengkelin'),
('bengkel_tagline', 'Bengkel Otomotif SMKS Pembda Nias — Teaching Factory (Tefa)'),
('bengkel_address', 'Jl. Pelita No. 09 Kel. Ilir Kec. Gunungsitoli Kota Gunungsitoli (22815)'),
('bengkel_phone', '08123456789'),
('bengkel_email', 'yulzega@gmail.com'),
('bengkel_hours', 'Senin - Sabtu: 08.00 - 17.00 WIB'),
('wa_token', ''),
('wa_sender', ''),
('fonnte_device', ''),
('max_booking_per_day', '10'),
('tax_rate', '0'),
('currency', 'IDR'),
('logo', '');

-- Default Admin User (password: password)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`, `status`) VALUES
('Yulianus Zega, S.Kom, M.Pd.T', 'yulzega@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner', '08123456789', 'active'),
('Admin Bengkelin', 'admin@bengkelin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '08111222333', 'active'),
('Budi Santoso', 'kabeng@bengkelin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kabeng', '08222333444', 'active'),
('Andi Wijaya', 'andi@bengkelin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mekanik', '08333444555', 'active'),
('Deni Pratama', 'deni@bengkelin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mekanik', '08444555666', 'active'),
('Rio Firmansyah', 'rio@bengkelin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mekanik', '08555666777', 'active');

-- Employees
INSERT INTO `employees` (`user_id`, `employee_id`, `name`, `phone`, `position`, `specialization`, `join_date`, `salary`, `commission_rate`, `status`) VALUES
(1, 'EMP-001', 'Yulianus Zega, S.Kom, M.Pd.T', '08123456789', 'owner', 'Management & Teaching Factory', '2024-01-01', 0.00, 0.00, 'active'),
(2, 'EMP-002', 'Admin Bengkelin', '08111222333', 'admin', 'Administrasi & Kasir', '2024-01-01', 3500000.00, 0.00, 'active'),
(3, 'EMP-003', 'Budi Santoso', '08222333444', 'kabeng', 'Mesin, Kelistrikan, AC', '2024-01-01', 5000000.00, 5.00, 'active'),
(4, 'EMP-004', 'Andi Wijaya', '08333444555', 'mekanik', 'Mesin & Kaki-kaki', '2024-02-01', 3000000.00, 3.00, 'active'),
(5, 'EMP-005', 'Deni Pratama', '08444555666', 'mekanik', 'Bodi & Cat', '2024-02-01', 3000000.00, 3.00, 'active'),
(6, 'EMP-006', 'Rio Firmansyah', '08555666777', 'mekanik', 'Kelistrikan & Audio', '2024-03-01', 3000000.00, 3.00, 'active');

-- Service Categories
INSERT INTO `service_categories` (`name`, `description`, `vehicle_type`, `color`, `icon`) VALUES
('Servis Rutin', 'Ganti oli, filter, busi, tune-up ringan', 'both', '#10B981', 'fa-sync-alt'),
('Servis Berkala', 'Servis 10K, 20K, 40K km', 'both', '#3B82F6', 'fa-calendar-check'),
('Perbaikan Mesin', 'Overhaul, tune-up besar, karburator', 'both', '#EF4444', 'fa-cogs'),
('Bodi & Cat', 'Dempul, cat, polish, ketok magic', 'both', '#F59E0B', 'fa-paint-brush'),
('Kelistrikan', 'Aki, alternator, klakson, lampu', 'both', '#8B5CF6', 'fa-bolt'),
('Kaki-kaki', 'Rem, spooring, balancing, shockbreaker', 'both', '#EC4899', 'fa-circle-notch'),
('AC & Interior', 'Freon, servis AC, karpet, jok', 'mobil', '#06B6D4', 'fa-snowflake'),
('Ban & Velg', 'Ganti ban, tambal, nitrogen', 'both', '#84CC16', 'fa-circle');

-- Services
INSERT INTO `services` (`category_id`, `name`, `description`, `price_car`, `price_motorcycle`, `duration_hours`) VALUES
(1, 'Ganti Oli Mesin', 'Penggantian oli mesin + filter oli', 75000, 45000, 0.5),
(1, 'Tune-up Ringan', 'Cek busi, filter udara, karburator/injektor', 150000, 85000, 1.0),
(1, 'Ganti Filter Udara', 'Penggantian filter udara', 50000, 35000, 0.3),
(2, 'Servis 10.000 km', 'Ganti oli + filter + cek keseluruhan', 200000, 120000, 1.5),
(2, 'Servis 20.000 km', 'Tune-up + ganti oli + filter + kampas rem', 400000, 220000, 2.5),
(2, 'Servis 40.000 km', 'Servis besar komprehensif', 700000, 380000, 4.0),
(3, 'Tune-up Besar', 'Overhaul kepala silinder', 1500000, 800000, 8.0),
(3, 'Servis Karburator', 'Bongkar pasang & setel karburator', 200000, 100000, 2.0),
(4, 'Polish Body', 'Poles cat bodi kendaraan', 350000, 150000, 3.0),
(4, 'Ketok Magic', 'Perbaikan penyok ringan', 200000, 100000, 2.0),
(5, 'Ganti Aki', 'Pemasangan aki baru', 75000, 50000, 0.5),
(5, 'Servis Kelistrikan', 'Cek & perbaikan sistem kelistrikan', 200000, 100000, 2.0),
(6, 'Spooring & Balancing', 'Spooring + balancing 4 roda', 150000, 0, 1.0),
(6, 'Ganti Kampas Rem', 'Penggantian kampas rem depan & belakang', 150000, 75000, 1.0),
(6, 'Servis Shockbreaker', 'Pengecekan & perbaikan suspensi', 250000, 150000, 2.0),
(7, 'Isi Freon AC', 'Pengisian freon AC mobil', 200000, 0, 0.5),
(7, 'Servis AC Lengkap', 'Bersih evaporator + ganti freon', 450000, 0, 3.0),
(8, 'Tambal Ban Tubeless', 'Tambal ban tubeless', 35000, 25000, 0.3),
(8, 'Isi Angin Nitrogen', 'Isi nitrogen per roda', 15000, 10000, 0.2);

-- Part Categories
INSERT INTO `part_categories` (`name`) VALUES
('Oli & Pelumas'),
('Filter'),
('Busi & Pengapian'),
('Rem & Kopling'),
('Aki & Kelistrikan'),
('Kaki-kaki & Suspensi'),
('Transmisi'),
('Bodi & Aksesoris');

-- Sample Parts
INSERT INTO `parts` (`category_id`, `code`, `name`, `brand`, `unit`, `stock`, `min_stock`, `buy_price`, `sell_price`, `vehicle_type`, `shelf_location`) VALUES
(1, 'OLI-001', 'Oli Mesin 10W-30 1L', 'Shell Helix', 'liter', 25, 10, 45000, 65000, 'mobil', 'A1'),
(1, 'OLI-002', 'Oli Mesin 20W-50 1L', 'Castrol GTX', 'liter', 20, 10, 42000, 60000, 'mobil', 'A1'),
(1, 'OLI-003', 'Oli Mesin Motor 10W-40', 'AHM Oil', 'liter', 30, 15, 38000, 52000, 'motor', 'A2'),
(1, 'OLI-004', 'Oli Mesin Motor 20W-40', 'Yamalube Sport', 'liter', 25, 15, 36000, 50000, 'motor', 'A2'),
(1, 'OLI-005', 'Oli Gardan Motor Matic', 'AHM Oil', 'botol', 20, 10, 18000, 28000, 'motor', 'A3'),
(2, 'FLT-001', 'Filter Oli Mobil', 'Ryco', 'pcs', 15, 5, 35000, 55000, 'mobil', 'B1'),
(2, 'FLT-002', 'Filter Udara Mobil', 'K&N', 'pcs', 10, 5, 45000, 70000, 'mobil', 'B1'),
(2, 'FLT-003', 'Filter Oli Motor', 'HI-Q', 'pcs', 20, 8, 18000, 30000, 'motor', 'B2'),
(3, 'BUS-001', 'Busi NGK Iridium', 'NGK', 'pcs', 40, 10, 35000, 55000, 'both', 'C1'),
(3, 'BUS-002', 'Busi Denso Iriway', 'Denso', 'pcs', 30, 10, 32000, 50000, 'both', 'C1'),
(4, 'REM-001', 'Kampas Rem Depan Mobil', 'Bendix', 'set', 8, 3, 120000, 180000, 'mobil', 'D1'),
(4, 'REM-002', 'Kampas Rem Belakang Mobil', 'Bendix', 'set', 8, 3, 100000, 150000, 'mobil', 'D1'),
(4, 'REM-003', 'Kampas Rem Motor', 'FCC', 'set', 15, 5, 45000, 70000, 'motor', 'D2'),
(5, 'AKI-001', 'Aki Kering 45Ah', 'GS Astra', 'pcs', 5, 2, 450000, 620000, 'mobil', 'E1'),
(5, 'AKI-002', 'Aki Kering 60Ah', 'Yuasa', 'pcs', 4, 2, 580000, 790000, 'mobil', 'E1'),
(5, 'AKI-003', 'Aki Motor 5Ah', 'GS Astra', 'pcs', 10, 3, 145000, 210000, 'motor', 'E2');

-- Sample Customers
INSERT INTO `customers` (`customer_code`, `name`, `phone`, `email`, `address`, `segment`) VALUES
('CST-0001', 'Hendra Kusuma', '08123456789', 'hendra@email.com', 'Jl. Merdeka No. 10', 'regular'),
('CST-0002', 'Siti Rahayu', '08234567890', 'siti@email.com', 'Jl. Mawar No. 5', 'new'),
('CST-0003', 'Bambang Irawan', '08345678901', 'bambang@email.com', 'Jl. Dahlia No. 23', 'vip');

-- Sample Vehicles
INSERT INTO `vehicles` (`customer_id`, `plate_number`, `brand`, `model`, `year`, `color`, `type`, `vehicle_subtype`, `km_current`, `fuel_type`) VALUES
(1, 'B 1234 ABC', 'Toyota', 'Avanza', 2019, 'Putih', 'mobil', 'MPV', 45000, 'bensin'),
(1, 'B 5678 DEF', 'Honda', 'Vario 150', 2021, 'Hitam', 'motor', 'matic', 15000, 'bensin'),
(2, 'D 9012 GHI', 'Yamaha', 'NMAX', 2022, 'Abu-abu', 'motor', 'matic', 8000, 'bensin'),
(3, 'E 3456 JKL', 'Honda', 'CRV', 2020, 'Silver', 'mobil', 'SUV', 30000, 'bensin');

COMMIT;
