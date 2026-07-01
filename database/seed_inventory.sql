-- Seed Categories
INSERT INTO `part_categories` (`id`, `name`) VALUES
(1, 'Filter & Saringan'),
(2, 'Kampas Rem'),
(3, 'Oli & Pelumas'),
(4, 'Cairan & Chemical'),
(5, 'Kelistrikan');

-- Seed Parts
INSERT INTO `parts` (`category_id`, `code`, `name`, `brand`, `unit`, `stock`, `min_stock`, `buy_price`, `sell_price`, `vehicle_type`) VALUES
(1, 'FLT-AVXEN', 'FILTER OLI AVANZA / XENIA (ASLI)', 'DAIHATSU', 'PCS', 45, 5, 25000, 30000, 'mobil'),
(1, 'FLT-INV', 'FILTER OLI INNOVA (SDK) 90015-YZZZ2', 'SDK', 'PCS', 10, 2, 31000, 37200, 'mobil'),
(1, 'AIR-AVVVT', 'SARINGAN HAWA AVANZA VVTI (ASLI) 17801-YZZT1', 'TOYOTA', 'PCS', 5, 2, 186000, 223200, 'mobil'),
(1, 'AIR-AVX15', 'SARINGAN HAWA AVXEN 15-21/TERIOS 19 (ASLI)', 'DAIHATSU', 'PCS', 5, 2, 112000, 134400, 'mobil'),
(1, 'AC-AVRSH', 'FILTER AC AVANZA / RUSH (TAG) DCF-002', 'TAG', 'PCS', 6, 2, 31000, 37200, 'mobil'),
(1, 'AC-AV22', 'FILTER AC AVANZA 22 (TAG)', 'TAG', 'PCS', 5, 2, 45000, 54000, 'mobil'),
(2, 'BRK-F-AV', 'KAMPAS REM DEPAN AV/APV/RUSH (TDW)', 'TDW', 'SET', 6, 2, 150000, 180000, 'mobil'),
(2, 'BRK-F-RA', 'KAMPAS REM DEPAN RAIZE/AV22 (BIRKENS)', 'BIRKENS', 'SET', 5, 2, 192000, 230400, 'mobil'),
(2, 'BRK-F-GM', 'KAMPAS REM DEPAN GRAN MAX (BIRKENS)', 'BIRKENS', 'SET', 5, 2, 162000, 194400, 'mobil'),
(2, 'BRK-R-AV', 'KAMPAS REM BLKG AV/XEN (TDW)', 'TDW', 'SET', 6, 2, 135000, 162000, 'mobil'),
(2, 'BRK-R-VL', 'KAMPAS REM BLKG VELOZ 22/ZENIX (BIRKENS)', 'BIRKENS', 'SET', 5, 2, 242000, 290400, 'mobil'),
(2, 'BRK-R-RK', 'KAMPAS REM BLKG ROCKY/RAIZE 21 (BIRKENS)', 'BIRKENS', 'SET', 5, 2, 229000, 274800, 'mobil'),
(3, 'OLI-HX6-4L', 'SHELL HELIX HX6 4L (4X4)', 'SHELL', 'BOTOL', 4, 1, 448000, 537600, 'mobil'),
(3, 'OLI-ECO-3L', 'SHELL ECO PLUS 0W-20 3L (4X3)', 'SHELL', 'BOTOL', 4, 1, 290750, 348900, 'mobil'),
(3, 'OLI-HX7-1L', 'SHELL HELIX HX7 1L (12X1)', 'SHELL', 'BOTOL', 12, 3, 132083, 158500, 'both'),
(4, 'OLI-PS-BSR', 'OLI POWER STERING BESAR XTP (12X1)', 'XTP', 'BOTOL', 12, 3, 36000, 43200, 'mobil'),
(4, 'BRK-FL-BSR', 'MINYAK REM BESAR XTP (12x1)', 'XTP', 'BOTOL', 12, 3, 35000, 42000, 'mobil'),
(5, 'REL-12V4K', 'RELAY KACA / TRANSPARANT 12V-4 KAKI 80A', '-', 'PCS', 20, 5, 26000, 31200, 'both');
