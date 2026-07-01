-- ============================================================
-- Tambah kolom assistant_mechanic_id ke work_orders
-- Jalankan sekali setelah revenue_system.sql
-- ============================================================
USE `bengkelin`;

ALTER TABLE `work_orders`
    ADD COLUMN `assistant_mechanic_id` int(11) DEFAULT NULL COMMENT 'Junior Mekanik pembantu'
        AFTER `mechanic_id`,
    ADD FOREIGN KEY `fk_wo_assistant` (`assistant_mechanic_id`)
        REFERENCES `employees`(`id`) ON DELETE SET NULL;
