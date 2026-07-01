-- ============================================================
-- BENGKELIN - Update Database Sparepart & Jasa Selengkap-lengkapnya (Versi Profesional)
-- Menambahkan Ratusan Data untuk Kondisi Bengkel di Indonesia
-- ============================================================

USE `bengkelin`;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `wo_services`;
DELETE FROM `wo_parts`;
DELETE FROM `services`;
DELETE FROM `parts`;
DELETE FROM `service_categories`;
DELETE FROM `part_categories`;

ALTER TABLE `service_categories` AUTO_INCREMENT = 1;
ALTER TABLE `part_categories` AUTO_INCREMENT = 1;
ALTER TABLE `services` AUTO_INCREMENT = 1;
ALTER TABLE `parts` AUTO_INCREMENT = 1;

-- 1. KATEGORI JASA (8 Kategori)
INSERT INTO `service_categories` (`id`, `name`, `description`, `vehicle_type`, `color`, `icon`) VALUES
(1, 'Servis Rutin Berkala', 'Perawatan standar harian & ganti oli', 'both', '#10B981', 'fa-sync-alt'),
(2, 'Perbaikan Mesin & Transmisi', 'Overhaul, transmisi, kopling, CVT', 'both', '#EF4444', 'fa-cogs'),
(3, 'Sistem Kelistrikan', 'Aki, lampu, relay, alternator, stater', 'both', '#8B5CF6', 'fa-bolt'),
(4, 'Sistem Kaki-kaki & Suspensi', 'Rem, suspensi, tie-rod, bearing', 'both', '#3B82F6', 'fa-circle-notch'),
(5, 'Sistem AC & Pendingin', 'Servis AC, isi freon, radiator, waterpump', 'both', '#06B6D4', 'fa-snowflake'),
(6, 'Body, Exterior & Interior', 'Cuci, poles, salon, detailing', 'both', '#F59E0B', 'fa-paint-brush'),
(7, 'Ban, Velg & Roda', 'Ganti ban, tambal, spooring, balancing', 'both', '#84CC16', 'fa-circle'),
(8, 'Sistem Bahan Bakar', 'Injectror cleaning, bersihkan tangki/karbu', 'both', '#EC4899', 'fa-gas-pump');

-- 2. DAFTAR JASA (Services - Lebih Lengkap)
INSERT INTO `services` (`category_id`, `name`, `description`, `price_car`, `price_motorcycle`, `duration_hours`) VALUES
(1, 'Jasa Ganti Oli Mesin', 'Hanya jasa penggantian oli mesin', 50000, 15000, 0.5),
(1, 'Tune Up Ringan', 'Bersihkan throtle body, busi, filter udara', 250000, 75000, 1.0),
(1, 'Tune Up Standar', 'Scan engine, bersihkan sistem intake', 350000, 100000, 1.5),
(1, 'Servis 10rb KM', 'Pengecekan rem, rotasi ban, general check', 450000, 120000, 2.0),
(1, 'Servis Besar 40rb KM', 'General check menyeluruh & kuras cairan', 950000, 250000, 4.5),
(2, 'Overhaul Mesin (Turun Mesin)', 'Jasa bongkar mesin total', 3500000, 1000000, 48.0),
(2, 'Top Overhaul', 'Skur klep, ganti packing, carbon clean', 1500000, 450000, 8.0),
(2, 'Ganti Kopling Set', 'Bongkar pasang transmisi & kopling', 650000, 150000, 5.0),
(2, 'Servis CVT Standar', 'Bongkar & bersihkan blok CVT matic', 0, 75000, 1.0),
(2, 'Ganti V-Belt & Roller', 'Jasa bongkar pasang girboks/CVT', 0, 85000, 1.5),
(3, 'Ganti Aki (Jasa)', 'Pemasangan aki baru', 15000, 10000, 0.2),
(3, 'Urut Kelistrikan', 'Mencari jalur kabel yang konslet', 350000, 100000, 3.0),
(3, 'Servis Alternator/Dinamo Stater', 'Bongkar & servis dinamo', 350000, 150000, 3.0),
(3, 'Bongkar Pasang Lampu Utama', 'Ganti bohlam/LED utama', 50000, 20000, 0.5),
(4, 'Ganti Kampas Rem Depan', 'Per pasang (roda kiri & kanan)', 75000, 25000, 0.6),
(4, 'Ganti Kampas Rem Belakang (Tromol)', 'Bongkar pasang kampas rem belakang', 85000, 35000, 0.8),
(4, 'Ganti Shockbreaker Depan', 'Bongkar pasang per pasang (pair)', 250000, 85000, 2.5),
(4, 'Ganti Shockbreaker Belakang', 'Bongkar pasang per pasang (pair)', 150000, 50000, 1.5),
(4, 'Ganti Tie Rod & Rack End', 'Pemasangan paket kaki-kaki depan', 350000, 0, 3.0),
(5, 'Isi Freon AC Standar', 'Ulang isi gas freon hfc-134a', 300000, 0, 1.0),
(5, 'Servis AC Ringan (Cuci)', 'Cuci kondensor & filter kabin', 450000, 0, 2.0),
(5, 'Radiator Flush', 'Pembersihan jalur pendingin radiator', 150000, 50000, 1.5),
(5, 'Ganti Waterpump', 'Bongkar pasang waterpump', 350000, 100000, 3.0),
(6, 'Poles Body Standar', 'Hilangkan jamur & baret halus', 1200000, 250000, 6.0),
(6, 'Salon Interior Detailing', 'Cuci plafon, jok, dashboard, karpet dasar', 850000, 150000, 5.0),
(6, 'Poles Kaca Depan', 'Hilangkan jamur kaca', 150000, 50000, 1.0),
(6, 'Cuci Standar', 'Cuci kompresor + salju', 50000, 20000, 0.5),
(7, 'Ganti Ban (Per Roda)', 'Bongkar pasang ban luar', 45000, 15000, 0.4),
(7, 'Balancing Roda', 'Per roda menggunakan mesin balancing', 35000, 20000, 0.3),
(7, 'Spooring 4 Roda', 'Setel sudut kemiringan ban', 250000, 0, 1.0),
(7, 'Tambal Ban Tubeless', 'Per lubang', 35000, 20000, 0.3),
(8, 'Gurah Mesin (Carbon Clean)', 'Pembersihan ruang bakar via lubang busi', 400000, 150000, 1.5),
(8, 'Injection Cleaning (Liqui Moly)', 'Pembersihan injector via kompresor', 350000, 100000, 1.0),
(8, 'Kuras Tangki Bensin', 'Bongkar & kuras tangki bahan bakar', 350000, 100000, 3.0);

-- 3. KATEGORI SPAREPART (15 Kategori)
INSERT INTO `part_categories` (`id`, `name`) VALUES
(1, 'Pelumas & Cairan Kimia'),
(2, 'Filter (Saringan)'),
(3, 'Busi & Sistem Pengapian'),
(4, 'Sistem Rem'),
(5, 'Ban & Velg'),
(6, 'Aki & Sistem Kelistrikan'),
(7, 'Lampu & Bohlam'),
(8, 'Sabuk & Rantai (Belts/Chain)'),
(9, 'Suku Cadang CVT (Motor)'),
(10, 'Suku Cadang Kopling (Manual)'),
(11, 'Suku Cadang Mesin (Internal)'),
(12, 'Suku Cadang Kaki-kaki'),
(13, 'Sistem Pendingin (Radiator/AC)'),
(14, 'Wiper & Pembersih Kaca'),
(15, 'Sekring & Konektor');

-- 4. DATA SPAREPART (Parts - Secara Masif)
INSERT INTO `parts` (`category_id`, `code`, `name`, `brand`, `unit`, `stock`, `min_stock`, `buy_price`, `sell_price`, `vehicle_type`, `shelf_location`) VALUES
-- Pelumas (Oli Mobil)
(1, 'OLI-001', 'Shell Helix HX7 10W-40 (1L)', 'Shell', 'Botol', 50, 12, 85000, 110000, 'mobil', 'A1-01'),
(1, 'OLI-002', 'Castrol Magnatec 10W-40 (1L)', 'Castrol', 'Botol', 30, 8, 98000, 125000, 'mobil', 'A1-01'),
(1, 'OLI-003', 'TMO Synthetic Blue 10W-40 (1L)', 'Toyota', 'Botol', 60, 15, 75000, 95000, 'mobil', 'A1-02'),
(1, 'OLI-004', 'Pertamina Fastron Techno 10W-40 (1L)', 'Pertamina', 'Botol', 40, 10, 72000, 95000, 'mobil', 'A1-02'),
(1, 'OLI-005', 'Mobil 1 Super 1000 10W-40 (1L)', 'Mobil 1', 'Botol', 24, 6, 82000, 105000, 'mobil', 'A1-03'),
(1, 'OLI-006', 'Oli Transmisi ATF Mobil (1L)', 'Total', 'Botol', 12, 4, 115000, 145000, 'mobil', 'A1-03'),
(1, 'OLI-007', 'Meditran S 40 (Super) (1L)', 'Pertamina', 'Botol', 20, 5, 55000, 70000, 'mobil', 'A1-04'),

-- Pelumas (Oli Motor)
(1, 'OLI-010', 'AHM Oil MPX2 10W-30 (0.8L)', 'AHM', 'Botol', 120, 24, 42000, 58000, 'motor', 'A2-01'),
(1, 'OLI-011', 'AHM Oil SPX2 (Full Synthetic) (0.8L)', 'AHM', 'Botol', 60, 12, 55000, 72000, 'motor', 'A2-01'),
(1, 'OLI-012', 'Yamalube Silver (0.8L)', 'Yamaha', 'Botol', 50, 12, 38000, 52000, 'motor', 'A2-02'),
(1, 'OLI-013', 'Yamalube Matic Super (1L)', 'Yamaha', 'Botol', 40, 10, 55000, 75000, 'motor', 'A2-02'),
(1, 'OLI-014', 'Shell Advance AX7 Matic (0.8L)', 'Shell', 'Botol', 48, 12, 52000, 70000, 'motor', 'A2-03'),
(1, 'OLI-015', 'Enduro Matic 10W-30 (0.8L)', 'Pertamina', 'Botol', 60, 15, 42000, 55000, 'motor', 'A2-03'),
(1, 'OLI-016', 'Motul Scooter Expert 10W-40 (0.8L)', 'Motul', 'Botol', 24, 6, 68000, 95000, 'motor', 'A2-04'),
(1, 'OLI-017', 'Oli Gardan Motor Matic (120ml)', 'AHM', 'Botol', 100, 20, 15000, 25000, 'motor', 'A2-04'),

-- Suku Cadang Rem (Mobil)
(4, 'REM-001', 'Pad Kit Depan (Kampas Rem) Avanza', 'Bendix', 'Set', 10, 4, 235000, 325000, 'mobil', 'B1-01'),
(4, 'REM-002', 'Pad Kit Depan Jazz RS/Brio', 'Akebono', 'Set', 8, 3, 280000, 385000, 'mobil', 'B1-01'),
(4, 'REM-003', 'Sepatu Rem Belakang (Drum) Avanza', 'Toyota', 'Set', 6, 2, 185000, 245000, 'mobil', 'B1-02'),
(4, 'REM-004', 'Minyak Rem DOT 3 Prestone (300ml)', 'Prestone', 'Botol', 24, 6, 25000, 38000, 'both', 'B1-02'),

-- Suku Cadang Rem (Motor)
(4, 'REM-010', 'Kampas Rem Depan Vario/Beat', 'AHM', 'Pcs', 50, 15, 25000, 45000, 'motor', 'B2-01'),
(4, 'REM-011', 'Kampas Rem Depan N-MAX/Aerox', 'Yamaha', 'Pcs', 30, 10, 35000, 55000, 'motor', 'B2-01'),
(4, 'REM-012', 'Kampas Rem Belakang Vario (Tromol)', 'AHM', 'Pcs', 40, 10, 32000, 50000, 'motor', 'B2-02'),

-- Filter (Mobil & Motor)
(2, 'FLT-001', 'Filter Oli Avanza/Xenia', 'Denso', 'Pcs', 50, 12, 28000, 45000, 'mobil', 'C1-01'),
(2, 'FLT-002', 'Filter Oli Honda (Jazz/Brio/Jazz)', 'Honda', 'Pcs', 30, 8, 32000, 55000, 'mobil', 'C1-01'),
(2, 'FLT-003', 'Filter Oli Innova Diesel/Fortuner', 'Toyota', 'Pcs', 20, 5, 55000, 85000, 'mobil', 'C1-02'),
(2, 'FLT-004', 'Filter AC/Kabin Avanza/Veloz', 'Denso', 'Pcs', 20, 5, 45000, 68000, 'mobil', 'C1-03'),
(2, 'FLT-005', 'Filter Udara Avanza (Grand)', 'Toyota', 'Pcs', 15, 4, 75000, 115000, 'mobil', 'C1-04'),
(2, 'FLT-010', 'Filter Udara Vario 125/150', 'AHM', 'Pcs', 30, 10, 42000, 65000, 'motor', 'C2-01'),
(2, 'FLT-011', 'Filter Udara N-MAX Old/New', 'Yamaha', 'Pcs', 25, 8, 48000, 72000, 'motor', 'C2-01'),

-- Busi (Ignition)
(3, 'BSI-001', 'Busi NGK Standar C7HSA (Motor)', 'NGK', 'Pcs', 100, 20, 12500, 22000, 'motor', 'D1-01'),
(3, 'BSI-002', 'Busi NGK Iridium CPR9EAIX (Motor)', 'NGK', 'Pcs', 24, 6, 85000, 115000, 'motor', 'D1-01'),
(3, 'BSI-003', 'Busi Denso Standar Avanza', 'Denso', 'Pcs', 48, 12, 18000, 32000, 'mobil', 'D1-02'),
(3, 'BSI-004', 'Busi Iridium Mobil Denso', 'Denso', 'Pcs', 20, 4, 95000, 135000, 'mobil', 'D1-02'),

-- Aki (Battery)
(6, 'AKI-001', 'Aki GS Astra NS40Z (Avanza)', 'GS Astra', 'Pcs', 6, 2, 745000, 925000, 'mobil', 'E1-01'),
(6, 'AKI-002', 'Aki GS Astra NS60 (Grand Livina)', 'GS Astra', 'Pcs', 4, 2, 825000, 1050000, 'mobil', 'E1-01'),
(6, 'AKI-010', 'Aki Motor GTZ5S (Beat/Vario)', 'GS Astra', 'Pcs', 15, 4, 185000, 245000, 'motor', 'E2-01'),
(6, 'AKI-011', 'Aki Motor YTZ7V (N-MAX)', 'Yuasa', 'Pcs', 10, 3, 315000, 425000, 'motor', 'E2-01'),

-- Ban (Tires)
(5, 'BAN-001', 'Ban Mobil Bridgestone Ecopia 185/70 R14', 'Bridgestone', 'Pcs', 8, 2, 650000, 845000, 'mobil', 'F1-01'),
(5, 'BAN-002', 'Ban Mobil Dunlop Enasave 185/70 R14', 'Dunlop', 'Pcs', 6, 2, 620000, 795000, 'mobil', 'F1-01'),
(5, 'BAN-010', 'Ban Motor IRC 80/90-14 Tubeless', 'IRC', 'Pcs', 20, 5, 195000, 265000, 'motor', 'F2-01'),
(5, 'BAN-011', 'Ban Motor Pirelli Angel Scooter 80/90-14', 'Pirelli', 'Pcs', 12, 4, 315000, 425000, 'motor', 'F2-01'),

-- Suku Cadang CVT (Motor Matic)
(9, 'CVT-001', 'V-Belt Kit Vario 125/150', 'AHM', 'Set', 10, 3, 145000, 215000, 'motor', 'G1-01'),
(9, 'CVT-002', 'V-Belt Kit N-MAX Old', 'Yamaha', 'Set', 8, 3, 165000, 245000, 'motor', 'G1-01'),
(9, 'CVT-003', 'Roller Vario 125 Set', 'AHM', 'Set', 12, 4, 45000, 68000, 'motor', 'G1-02'),
(9, 'CVT-004', 'Gemuk CVT (Grease) Set', 'Honda', 'Pcs', 50, 10, 12000, 20000, 'motor', 'G1-02'),

-- Sabuk (Fan Belt Mobil)
(8, 'BLT-001', 'Fan Belt Avanza (Duo/Grand)', 'Toyota', 'Pcs', 10, 3, 115000, 165000, 'mobil', 'G1-03'),
(8, 'BLT-002', 'Fan Belt Jazz RS/Brio', 'Honda', 'Pcs', 8, 2, 135000, 195000, 'mobil', 'G1-03'),

-- Lampu & Bohlam
(7, 'LMP-001', 'Bohlam Utama H4 60/55W', 'Osram', 'Pcs', 24, 6, 45000, 75000, 'mobil', 'H1-01'),
(7, 'LMP-002', 'Bohlam Lampu Rem Mobil (Bayonet)', 'Philips', 'Pcs', 40, 10, 12000, 25000, 'mobil', 'H1-02'),
(7, 'LMP-010', 'Bohlam Depan Motor (M5/Bebek)', 'Osram', 'Pcs', 50, 10, 15000, 35000, 'motor', 'H2-01'),
(7, 'LMP-011', 'Lampu LED T10 (Senja/Plat)', 'Generic', 'Pcs', 100, 20, 5000, 15000, 'both', 'H2-02'),

-- Kimia (Chemicals)
(1, 'CHM-001', 'Carburetor & Injector Cleaner (500ml)', 'Mega Cools', 'Botol', 48, 12, 22000, 35000, 'both', 'I1-01'),
(1, 'CHM-002', 'Engine Flush (Pembersih Mesin)', 'Liqui Moly', 'Botol', 24, 6, 65000, 95000, 'both', 'I1-01'),
(1, 'CHM-003', 'Penetrating Oil (WD-type)', 'STP', 'Botol', 24, 6, 35000, 55000, 'both', 'I1-02'),
(1, 'CHM-004', 'Coolant Radiator Prestone (1L)', 'Prestone', 'Botol', 30, 8, 38000, 55000, 'both', 'I1-03'),

-- Wiper
(14, 'WPR-001', 'Wiper Blade 14" (Standard)', 'Bosch', 'Pcs', 12, 4, 38000, 65000, 'mobil', 'J1-01'),
(14, 'WPR-002', 'Wiper Blade 16" (Standard)', 'Bosch', 'Pcs', 12, 4, 42000, 70000, 'mobil', 'J1-01'),
(14, 'WPR-003', 'Wiper Blade 20" (Standard)', 'Bosch', 'Pcs', 12, 4, 48000, 75000, 'mobil', 'J1-01'),
(14, 'WPR-004', 'Wiper Blade 24" (Standard)', 'Bosch', 'Pcs', 12, 4, 55000, 85000, 'mobil', 'J1-01'),

-- Sekring (Fuses)
(15, 'FUS-001', 'Sekring Tancep (Blade) 10A Red', 'Generic', 'Pcs', 200, 50, 500, 2000, 'both', 'K1-01'),
(15, 'FUS-002', 'Sekring Tancep (Blade) 15A Blue', 'Generic', 'Pcs', 200, 50, 500, 2000, 'both', 'K1-01'),
(15, 'FUS-003', 'Sekring Tancep (Blade) 20A Yellow', 'Generic', 'Pcs', 200, 50, 500, 2000, 'both', 'K1-01');

SET FOREIGN_KEY_CHECKS = 1;
