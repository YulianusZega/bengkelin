-- ============================================================
-- BENGKELIN - Revenue Sharing & Payroll System Migration
-- Run this ONCE after the main bengkelin.sql
-- ============================================================

USE `bengkelin`;

-- --------------------------------------------------------
-- Add revenue settings to settings table
-- --------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('service_mechanic_pct',  '60'),   -- % jasa untuk total mekanik
('service_owner_pct',     '40'),   -- % jasa untuk owner
('senior_share_pct',      '80'),   -- % dari bagian mekanik → Senior Teknisi
('junior_share_pct',      '20'),   -- % dari bagian mekanik → Junior Mekanik
('parts_owner_pct',       '100'),  -- % spare part → owner
('admin_bonus_pct',       '1'),    -- % dari total omset bulanan → admin bonus
('senior_min_guarantee',  '0')     -- Min retensi bulanan Senior Teknisi (Rp)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- --------------------------------------------------------
-- Table: salary_records (rekap penggajian bulanan)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `salary_records` (
  `id`               int(11) NOT NULL AUTO_INCREMENT,
  `employee_id`      int(11) NOT NULL,
  `period_year`      int(4) NOT NULL,
  `period_month`     int(2) NOT NULL,
  `base_salary`      decimal(12,2) DEFAULT 0.00 COMMENT 'Gaji pokok bulanan',
  `service_bonus`    decimal(12,2) DEFAULT 0.00 COMMENT 'Bonus dari bagi jasa',
  `omset_bonus`      decimal(12,2) DEFAULT 0.00 COMMENT 'Bonus % omset (admin)',
  `total_salary`     decimal(12,2) DEFAULT 0.00,
  `wo_count`         int(11) DEFAULT 0 COMMENT 'Jumlah WO dikerjakan bulan ini',
  `service_revenue`  decimal(12,2) DEFAULT 0.00 COMMENT 'Total jasa yang dikerjakan',
  `status`           enum('draft','approved','paid') DEFAULT 'draft',
  `notes`            text DEFAULT NULL,
  `created_by`       int(11) DEFAULT NULL,
  `approved_by`      int(11) DEFAULT NULL,
  `approved_at`      datetime DEFAULT NULL,
  `paid_at`          datetime DEFAULT NULL,
  `created_at`       timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_emp_period` (`employee_id`, `period_year`, `period_month`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Update employees: update senior to no base salary (on-call model)
-- (Adjust accordingly — uncomment if desired)
-- --------------------------------------------------------
-- UPDATE `employees` SET `salary` = 0.00 WHERE `position` = 'senior_teknisi';

-- --------------------------------------------------------
-- View: v_monthly_mechanic_revenue
-- Kalkulasi otomatis pendapatan jasa per mekanik per bulan
-- --------------------------------------------------------
CREATE OR REPLACE VIEW `v_monthly_mechanic_revenue` AS
SELECT
    e.id                          AS employee_id,
    e.name                        AS employee_name,
    e.position,
    e.salary                      AS base_salary,
    YEAR(wo.updated_at)           AS period_year,
    MONTH(wo.updated_at)          AS period_month,
    COUNT(DISTINCT wo.id)         AS wo_count,
    COALESCE(SUM(DISTINCT wo.subtotal_services), 0) AS total_service_revenue,
    COALESCE(SUM(DISTINCT wo.subtotal_parts),    0) AS total_parts_revenue
FROM employees e
LEFT JOIN (
    SELECT id, mechanic_id as emp_id, updated_at, payment_status, status, subtotal_services, subtotal_parts FROM work_orders
    UNION ALL
    SELECT w.id, wa.employee_id as emp_id, w.updated_at, w.payment_status, w.status, w.subtotal_services, w.subtotal_parts 
    FROM work_orders w JOIN wo_assistants wa ON w.id = wa.wo_id
) wo ON e.id = wo.emp_id 
    AND wo.payment_status = 'paid' 
    AND wo.status IN ('done', 'delivered')
WHERE e.position IN ('senior_teknisi', 'junior_teknisi')
GROUP BY e.id, e.name, e.position, e.salary, YEAR(wo.updated_at), MONTH(wo.updated_at);
