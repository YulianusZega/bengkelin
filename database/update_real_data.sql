-- ============================================================
-- BENGKELIN - Update Data Jasa & Sparepart Realistis (Indonesia) - FIX
-- Menggunakan DELETE sebagai ganti TRUNCATE untuk menghindari error Foreign Key
-- ============================================================

USE `bengkelin`;

-- Matikan cek foreign key
SET FOREIGN_KEY_CHECKS = 0;

-- Hapus data lama (DELETE mengizinkan penghapusan meski ada relasi)
DELETE FROM `wo_services`;
DELETE FROM `wo_parts`;
DELETE FROM `services`;
DELETE FROM `parts`;
DELETE FROM `service_categories`;
DELETE FROM `part_categories`;

-- Reset AUTO_INCREMENT ke 1 agar ID mulai dari awal
ALTER TABLE `service_categories` AUTO_INCREMENT = 1;
ALTER TABLE `part_categories` AUTO_INCREMENT = 1;
ALTER TABLE `services` AUTO_INCREMENT = 1;
ALTER TABLE `parts` AUTO_INCREMENT = 1;
ALTER TABLE `wo_services` AUTO_INCREMENT = 1;
ALTER TABLE `wo_parts` AUTO_INCREMENT = 1;

-- 1. INSERT KATEGORI JASA
INSERT INTO `service_categories` (`id`, `name`, `description`, `vehicle_type`, `color`, `icon`) VALUES
(1, 'Servis Rutin/Berkala', 'Ganti oli, filter, tune-up ringan', 'both', '#10B981', 'fa-sync-alt'),
(2, 'Perbaikan Mesin', 'Overhaul, transmisi, radiator', 'both', '#EF4444', 'fa-cogs'),
(3, 'Kelistrikan & AC', 'Aki, lampu, isi freon, servis AC', 'both', '#8B5CF6', 'fa-bolt'),
(4, 'Kaki-kaki & Ban', 'Rem, suspensi, ganti ban, bearing', 'both', '#3B82F6', 'fa-circle-notch'),
(5, 'Body & Interior', 'Cuci, poles, perbaikan body', 'both', '#F59E0B', 'fa-paint-brush');

-- 2. INSERT DAFTAR JASA (Services)
INSERT INTO `services` (`category_id`, `name`, `description`, `price_car`, `price_motorcycle`, `duration_hours`) VALUES
(1, 'Jasa Ganti Oli', 'Hanya jasa penggantian oli mesin', 50000, 15000, 0.5),
(1, 'Tune Up Ringan', 'Bersihkan throtle body, busi, filter udara', 250000, 75000, 1.0),
(1, 'Servis Berkala 10rb KM', 'General check & pembersihan rem', 450000, 100000, 2.0),
(1, 'Servis Berkala 40rb KM', 'Pengecekan menyeluruh & kuras cairan', 950000, 250000, 4.0),
(2, 'Jasa Turun Mesin (Overhaul)', 'Biaya jasa saja, belum termasuk part', 3500000, 1000000, 24.0),
(2, 'Gurah Mesin (Carbon Clean)', 'Pembersihan ruang bakar', 400000, 150000, 1.5),
(2, 'Service Radiator', 'Kuras dan bersihkan radiator', 250000, 75000, 2.0),
(3, 'Isi Freon AC & Cek Bocor', 'Hanya isi freon standard', 300000, 0, 1.0),
(3, 'Service AC (Evaporator)', 'Bongkar dashboard & cuci evaporator', 1200000, 0, 5.0),
(3, 'Urut Kabel/Kelistrikan', 'Pengecekan jalur kabel bermasalah', 350000, 100000, 3.0),
(3, 'Ganti Aki (Jasa Pasang)', 'Hanya jasa pasang aki baru', 15000, 5000, 0.2),
(4, 'Jasa Ganti Kampas Rem', 'Per pasang (roda depan/belakang)', 75000, 25000, 0.5),
(4, 'Jasa Ganti Ban', 'Bongkar pasang per roda', 45000, 15000, 0.5),
(4, 'Spooring & Balancing', 'Setel sudut roda & balancing 4 roda', 250000, 0, 1.5),
(4, 'Ganti Shockbreaker', 'Jasa pasang per pair (2 unit)', 250000, 50000, 2.0),
(5, 'Salon Interior / Detailing', 'Bersihkan plafon, jok, dashboard', 850000, 150000, 5.0),
(5, 'Poles Body & Wax', 'Poles cat agar kembali mengkilap', 1500000, 250000, 6.0),
(5, 'Cuci Standar', 'Cuci kompresor + sabun salju', 50000, 20000, 0.5);

-- 3. INSERT KATEGORI SPAREPART
INSERT INTO `part_categories` (`id`, `name`) VALUES
(1, 'Pelumas & Cairan'),
(2, 'Suku Cadang Mesin'),
(3, 'Suku Cadang Rem'),
(4, 'Kelistrikan & Busi'),
(5, 'Filter & Gasket'),
(6, 'Ban & Velg'),
(7, 'Aki & Battery');

-- 4. INSERT DATA SPAREPART (Parts)
INSERT INTO `parts` (`category_id`, `code`, `name`, `brand`, `unit`, `stock`, `min_stock`, `buy_price`, `sell_price`, `vehicle_type`, `shelf_location`) VALUES
(1, 'OLI-MBL-01', 'Shell Helix HX7 10W-40 (1L)', 'Shell', 'Botol', 50, 10, 85000, 105000, 'mobil', 'R-01'),
(1, 'OLI-MBL-02', 'Castrol Magnatec 10W-40 (1L)', 'Castrol', 'Botol', 30, 5, 95000, 120000, 'mobil', 'R-01'),
(1, 'OLI-MBL-03', 'TMO Synthetic 10W-40 (1L)', 'Toyota', 'Botol', 40, 10, 75000, 95000, 'mobil', 'R-01'),
(1, 'OLI-MTR-01', 'AHM Oil MPX2 10W-30 (0.8L)', 'AHM', 'Botol', 100, 20, 42000, 55000, 'motor', 'R-02'),
(1, 'OLI-MTR-02', 'Yamalube Super Matic (1L)', 'Yamaha', 'Botol', 60, 15, 55000, 70000, 'motor', 'R-02'),
(1, 'OLI-MTR-03', 'Shell Advance AX7 (1L)', 'Shell', 'Botol', 40, 10, 62000, 80000, 'motor', 'R-02'),
(1, 'CRD-01', 'Air Radiator (Coolant) Prestone', 'Prestone', 'Galon', 20, 5, 85000, 110000, 'both', 'R-03'),
(5, 'FLT-OLI-MBL', 'Filter Oli Avanza/Xenia', 'Denso', 'Pcs', 50, 10, 25000, 45000, 'mobil', 'B-01'),
(5, 'FLT-OLI-MBH', 'Filter Oli Honda (Jazz/CRV/Brio)', 'Honda', 'Pcs', 30, 10, 35000, 55000, 'mobil', 'B-01'),
(5, 'FLT-UDR-MTR', 'Filter Udara Vario 125/150', 'AHM', 'Pcs', 25, 5, 45000, 65000, 'motor', 'B-02'),
(4, 'BSI-NGK-01', 'Busi NGK Standar C7HSA', 'NGK', 'Pcs', 100, 20, 12000, 20000, 'motor', 'E-01'),
(4, 'BSI-DEN-01', 'Busi Denso Iridium (Mobil)', 'Denso', 'Pcs', 40, 8, 85000, 115000, 'mobil', 'E-01'),
(3, 'RMS-MBL-01', 'Kampas Rem Depan Avanza', 'Bendix', 'Set', 15, 5, 235000, 325000, 'mobil', 'K-01'),
(3, 'RMS-MTR-01', 'Kampas Rem Depan Vario/Beat', 'Fideli', 'Set', 50, 10, 25000, 45000, 'motor', 'K-02'),
(3, 'OIL-REM', 'Minyak Rem DOT 3 (300ml)', 'Prestone', 'Botol', 20, 5, 25000, 35000, 'both', 'K-03'),
(7, 'AKI-MBL-GSA', 'Aki Kering NS40Z (Avanza)', 'GS Astra', 'Pcs', 5, 2, 750000, 950000, 'mobil', 'A-01'),
(7, 'AKI-MTR-GSA', 'Aki Kering GTZ5S (Beat/Vario)', 'GS Astra', 'Pcs', 10, 3, 185000, 245000, 'motor', 'A-02'),
(6, 'BAN-MTR-01', 'Ban IRC NF66 80/90-14 (Tubeless)', 'IRC', 'Pcs', 10, 3, 195000, 265000, 'motor', 'G-01'),
(6, 'BAN-MBL-01', 'Ban Bridgestone Ecopia 185/70 R14', 'Bridgestone', 'Pcs', 8, 2, 650000, 850000, 'mobil', 'G-02');

-- Nyalakan kembali cek foreign key
SET FOREIGN_KEY_CHECKS = 1;
