-- ============================================================
-- BENGKELIN - Update Informasi Usaha
-- Jalankan script ini jika database sudah ada (sudah pernah import bengkelin.sql)
-- ============================================================

USE `bengkelin`;

-- Update Settings
UPDATE `settings` SET `setting_value` = 'Bengkelin' WHERE `setting_key` = 'bengkel_name';
UPDATE `settings` SET `setting_value` = 'Bengkel Otomotif SMKS Pembda Nias — Teaching Factory (Tefa)' WHERE `setting_key` = 'bengkel_tagline';
UPDATE `settings` SET `setting_value` = 'Jl. Pelita No. 09 Kel. Ilir Kec. Gunungsitoli Kota Gunungsitoli (22815)' WHERE `setting_key` = 'bengkel_address';
UPDATE `settings` SET `setting_value` = 'yulzega@gmail.com' WHERE `setting_key` = 'bengkel_email';

-- Update Owner User Account
UPDATE `users` SET 
    `name` = 'Yulianus Zega, S.Kom, M.Pd.T',
    `email` = 'yulzega@gmail.com'
WHERE `role` = 'owner' AND `id` = 1;

-- Update Owner Employee Record
UPDATE `employees` SET 
    `name` = 'Yulianus Zega, S.Kom, M.Pd.T',
    `specialization` = 'Management & Teaching Factory'
WHERE `user_id` = 1;

-- Verify changes
SELECT '✅ Settings Updated' AS status;
SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('bengkel_name','bengkel_tagline','bengkel_address','bengkel_email');
SELECT '✅ Owner Updated' AS status;
SELECT id, name, email, role FROM users WHERE role = 'owner';
