-- ============================================================
-- Migrasi: Ganti assistant_mechanic_id dengan tabel wo_assistants
-- Jalankan setelah add_assistant_mechanic.sql
-- ============================================================
USE `bengkelin`;

-- 1. Buat tabel junction untuk banyak asisten per WO
CREATE TABLE IF NOT EXISTS `wo_assistants` (
  `id`          int(11) NOT NULL AUTO_INCREMENT,
  `wo_id`       int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wo_emp` (`wo_id`, `employee_id`),
  FOREIGN KEY (`wo_id`)       REFERENCES `work_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Migrasi data lama dari assistant_mechanic_id (jika ada isinya)
INSERT IGNORE INTO `wo_assistants` (`wo_id`, `employee_id`)
SELECT `id`, `assistant_mechanic_id`
FROM `work_orders`
WHERE `assistant_mechanic_id` IS NOT NULL;

-- 3. Hapus kolom lama (tidak diperlukan lagi)
ALTER TABLE `work_orders`
    DROP FOREIGN KEY `fk_wo_assistant`,
    DROP COLUMN `assistant_mechanic_id`;
