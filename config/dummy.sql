INSERT INTO `kantor_cabang` (`id`, `kode_cabang`, `nama_cabang`, `alamat_cabang`, `telp_cabang`) VALUES
(1, 'BTG', 'Bontang', 'Jl. Bontang', '0845454554'),
(2, 'SMD', 'Samarinda', 'Jl. Samarinda', '0822544545'),
(3, 'WHU', 'Wahau', 'Jl. Wahau', '0822544545'),
(4, 'MLK', 'Melak', 'Jl. Melak', '0822544545'),
(5, 'BPN', 'Balikpapan', 'Jl. Balikpapan', '0822544545'),
(6, 'BEA', 'Berau', 'Jl. Berau', '0822544545'),
(7, 'LAM', 'Lambung', 'Jl. Lambung', '0822544545'),
(8, 'PLR', 'Palaran', 'Jl. Palaran', '0822544545'),
(9, 'LBK', 'Loa Bakung', 'Jl. Loa Bakung', '0822544545'),
(10, 'LPK', 'Lempake', 'Jl. Lempake', '0822544545'),
(11, 'SGT', 'Sangatta', 'Jl. Sangatta', '0822544545'),
(12, 'TGR', 'Tenggarong', 'Jl. tenggarong', '0822544545');

INSERT INTO `user` (`id`, `id_cabang`, `username`, `password`, `role`) VALUES
(2, 1, 'sp_btg', '$2y$10$HsH3nYMyOQtFYkIjtOkQ1O4Ip7IW71cNASjGqY8HQfqXGU5qZTl.u', 'superAdmin'),
(4, 2, 'sp_smd', '$2y$10$8Nmz2Pq20dFTkQhdPoSF8.kRQO7ODnFzmXjruYw6Fc26yCZ3u2J4K', 'superAdmin'),
(5, 5, 'sp_bpp', '$2y$10$cIm.CzArpywoypXzSWl7ve2c7cDFiz88Tl8SnGBPfc2VLcQ/OD29q', 'superAdmin'),
(6, 11, 'sp_sgt', '$2y$10$RIYRhIwxTjGWvuQv0Bf0SOH39NKszSH/r2fLBY.g0B/m8BZL5yvh6', 'superAdmin'),
(7, 4, 'sp_mlk', '$2y$10$SjLWmAUct/oOxSG8knOB6.VOYk829Un79Rp5PAmprlQC0OXon4t9C', 'superAdmin'),
(8, 3, 'sp_whu', '$2y$10$otyYr/HPG79g7JgymSKzL.u7mcTnl2ayugtiftPDaNS7nBAxogmtC', 'superAdmin'),
(9, 6, 'sp_bea', '$2y$10$6s4T29Db5kWyOqpJs7VdHO1NVDQ7ka/VIAAAVlWQy5Pg/ilmtLKZa', 'superAdmin'),
(10, 12, 'sp_tgr', '$2y$10$p7HDXP4ZnljlhARfiXHoz.8YsIohfERy7KlDRwxIx7lDSz5hUPstu', 'superAdmin'),
(11, 7, 'sp_lam', '$2y$10$Eq9JcjasuzJEQ21yOKYkZerw8G4kNiVdWFLcH0YjlZhQa9961RNYG', 'superAdmin'),
(12, 8, 'sp_plr', '$2y$10$G.gB5NMOjsnKSat13Oz8Lu0F2tSenQkr6QdPcVvmW6ZtV6v2QGL/i', 'superAdmin'),
(13, 9, 'sp_lbk', '$2y$10$TCuKpZysF2BHmH.Gv7eDeuI//mg1ZCrNCrCtKhSfs36eEsrOzt5U2', 'superAdmin');

-- Dummy Data Tarif Pengiriman
INSERT INTO `tarif_pengiriman` (`id`, `id_cabang_asal`, `id_cabang_tujuan`, `tarif_dasar`, `batas_berat_dasar`, `tarif_tambahan_perkg`, `status`) VALUES
-- Dari Bontang (BTG)
(1, 1, 2, 40000.00, 10, 4000.00, 'aktif'),  -- Bontang → Samarinda
(2, 1, 3, 75000.00, 10, 7500.00, 'aktif'),  -- Bontang → Wahau
(3, 1, 4, 85000.00, 10, 8500.00, 'aktif'),  -- Bontang → Melak
(4, 1, 5, 50000.00, 10, 5000.00, 'aktif'),  -- Bontang → Balikpapan
(5, 1, 6, 95000.00, 10, 9500.00, 'aktif'),  -- Bontang → Berau
(6, 1, 7, 70000.00, 10, 7000.00, 'aktif'),  -- Bontang → Lambung
(7, 1, 8, 35000.00, 10, 3500.00, 'aktif'),  -- Bontang → Palaran
(8, 1, 9, 38000.00, 10, 3800.00, 'aktif'),  -- Bontang → Loa Bakung
(9, 1, 10, 42000.00, 10, 4200.00, 'aktif'), -- Bontang → Lempake
(10, 1, 11, 30000.00, 10, 3000.00, 'aktif'), -- Bontang → Sangatta
(11, 1, 12, 55000.00, 10, 5500.00, 'aktif'), -- Bontang → Tenggarong

-- Dari Samarinda (SMD)
(12, 2, 1, 40000.00, 10, 4000.00, 'aktif'), -- Samarinda → Bontang
(13, 2, 3, 65000.00, 10, 6500.00, 'aktif'), -- Samarinda → Wahau
(14, 2, 4, 75000.00, 10, 7500.00, 'aktif'), -- Samarinda → Melak
(15, 2, 5, 35000.00, 10, 3500.00, 'aktif'), -- Samarinda → Balikpapan
(16, 2, 6, 85000.00, 10, 8500.00, 'aktif'), -- Samarinda → Berau
(17, 2, 7, 60000.00, 10, 6000.00, 'aktif'), -- Samarinda → Lambung
(18, 2, 8, 25000.00, 10, 2500.00, 'aktif'), -- Samarinda → Palaran
(19, 2, 9, 28000.00, 10, 2800.00, 'aktif'), -- Samarinda → Loa Bakung
(20, 2, 10, 30000.00, 10, 3000.00, 'aktif'), -- Samarinda → Lempake
(21, 2, 11, 55000.00, 10, 5500.00, 'aktif'), -- Samarinda → Sangatta
(22, 2, 12, 32000.00, 10, 3200.00, 'aktif'), -- Samarinda → Tenggarong

-- Dari Wahau (WHU)
(23, 3, 1, 75000.00, 10, 7500.00, 'aktif'), -- Wahau → Bontang
(24, 3, 2, 65000.00, 10, 6500.00, 'aktif'), -- Wahau → Samarinda
(25, 3, 4, 45000.00, 10, 4500.00, 'aktif'), -- Wahau → Melak
(26, 3, 5, 80000.00, 10, 8000.00, 'aktif'), -- Wahau → Balikpapan
(27, 3, 6, 120000.00, 10, 12000.00, 'aktif'), -- Wahau → Berau
(28, 3, 7, 50000.00, 10, 5000.00, 'aktif'), -- Wahau → Lambung
(29, 3, 8, 68000.00, 10, 6800.00, 'aktif'), -- Wahau → Palaran
(30, 3, 9, 70000.00, 10, 7000.00, 'aktif'), -- Wahau → Loa Bakung
(31, 3, 10, 72000.00, 10, 7200.00, 'aktif'), -- Wahau → Lempake
(32, 3, 11, 90000.00, 10, 9000.00, 'aktif'), -- Wahau → Sangatta
(33, 3, 12, 78000.00, 10, 7800.00, 'aktif'), -- Wahau → Tenggarong

-- Dari Melak (MLK)
(34, 4, 1, 85000.00, 10, 8500.00, 'aktif'), -- Melak → Bontang
(35, 4, 2, 75000.00, 10, 7500.00, 'aktif'), -- Melak → Samarinda
(36, 4, 3, 45000.00, 10, 4500.00, 'aktif'), -- Melak → Wahau
(37, 4, 5, 90000.00, 10, 9000.00, 'aktif'), -- Melak → Balikpapan
(38, 4, 6, 130000.00, 10, 13000.00, 'aktif'), -- Melak → Berau
(39, 4, 7, 55000.00, 10, 5500.00, 'aktif'), -- Melak → Lambung
(40, 4, 8, 78000.00, 10, 7800.00, 'aktif'), -- Melak → Palaran
(41, 4, 9, 80000.00, 10, 8000.00, 'aktif'), -- Melak → Loa Bakung
(42, 4, 10, 82000.00, 10, 8200.00, 'aktif'), -- Melak → Lempake
(43, 4, 11, 100000.00, 10, 10000.00, 'aktif'), -- Melak → Sangatta
(44, 4, 12, 88000.00, 10, 8800.00, 'aktif'), -- Melak → Tenggarong

-- Dari Balikpapan (BPN)
(45, 5, 1, 50000.00, 10, 5000.00, 'aktif'), -- Balikpapan → Bontang
(46, 5, 2, 35000.00, 10, 3500.00, 'aktif'), -- Balikpapan → Samarinda
(47, 5, 3, 80000.00, 10, 8000.00, 'aktif'), -- Balikpapan → Wahau
(48, 5, 4, 90000.00, 10, 9000.00, 'aktif'), -- Balikpapan → Melak
(49, 5, 6, 75000.00, 10, 7500.00, 'aktif'), -- Balikpapan → Berau
(50, 5, 7, 70000.00, 10, 7000.00, 'aktif'), -- Balikpapan → Lambung
(51, 5, 8, 38000.00, 10, 3800.00, 'aktif'), -- Balikpapan → Palaran
(52, 5, 9, 40000.00, 10, 4000.00, 'aktif'), -- Balikpapan → Loa Bakung
(53, 5, 10, 42000.00, 10, 4200.00, 'aktif'), -- Balikpapan → Lempake
(54, 5, 11, 65000.00, 10, 6500.00, 'aktif'), -- Balikpapan → Sangatta
(55, 5, 12, 45000.00, 10, 4500.00, 'aktif'), -- Balikpapan → Tenggarong

-- Dari Berau (BEA)
(56, 6, 1, 95000.00, 10, 9500.00, 'aktif'), -- Berau → Bontang
(57, 6, 2, 85000.00, 10, 8500.00, 'aktif'), -- Berau → Samarinda
(58, 6, 3, 120000.00, 10, 12000.00, 'aktif'), -- Berau → Wahau
(59, 6, 4, 130000.00, 10, 13000.00, 'aktif'), -- Berau → Melak
(60, 6, 5, 75000.00, 10, 7500.00, 'aktif'), -- Berau → Balikpapan
(61, 6, 7, 110000.00, 10, 11000.00, 'aktif'), -- Berau → Lambung
(62, 6, 8, 88000.00, 10, 8800.00, 'aktif'), -- Berau → Palaran
(63, 6, 9, 90000.00, 10, 9000.00, 'aktif'), -- Berau → Loa Bakung
(64, 6, 10, 92000.00, 10, 9200.00, 'aktif'), -- Berau → Lempake
(65, 6, 11, 70000.00, 10, 7000.00, 'aktif'), -- Berau → Sangatta
(66, 6, 12, 98000.00, 10, 9800.00, 'aktif'), -- Berau → Tenggarong

-- Dari Lambung (LAM)
(67, 7, 1, 70000.00, 10, 7000.00, 'aktif'), -- Lambung → Bontang
(68, 7, 2, 60000.00, 10, 6000.00, 'aktif'), -- Lambung → Samarinda
(69, 7, 3, 50000.00, 10, 5000.00, 'aktif'), -- Lambung → Wahau
(70, 7, 4, 55000.00, 10, 5500.00, 'aktif'), -- Lambung → Melak
(71, 7, 5, 70000.00, 10, 7000.00, 'aktif'), -- Lambung → Balikpapan
(72, 7, 6, 110000.00, 10, 11000.00, 'aktif'), -- Lambung → Berau
(73, 7, 8, 63000.00, 10, 6300.00, 'aktif'), -- Lambung → Palaran
(74, 7, 9, 65000.00, 10, 6500.00, 'aktif'), -- Lambung → Loa Bakung
(75, 7, 10, 67000.00, 10, 6700.00, 'aktif'), -- Lambung → Lempake
(76, 7, 11, 85000.00, 10, 8500.00, 'aktif'), -- Lambung → Sangatta
(77, 7, 12, 73000.00, 10, 7300.00, 'aktif'), -- Lambung → Tenggarong

-- Dari Palaran (PLR)
(78, 8, 1, 35000.00, 10, 3500.00, 'aktif'), -- Palaran → Bontang
(79, 8, 2, 25000.00, 10, 2500.00, 'aktif'), -- Palaran → Samarinda
(80, 8, 3, 68000.00, 10, 6800.00, 'aktif'), -- Palaran → Wahau
(81, 8, 4, 78000.00, 10, 7800.00, 'aktif'), -- Palaran → Melak
(82, 8, 5, 38000.00, 10, 3800.00, 'aktif'), -- Palaran → Balikpapan
(83, 8, 6, 88000.00, 10, 8800.00, 'aktif'), -- Palaran → Berau
(84, 8, 7, 63000.00, 10, 6300.00, 'aktif'), -- Palaran → Lambung
(85, 8, 9, 20000.00, 10, 2000.00, 'aktif'), -- Palaran → Loa Bakung
(86, 8, 10, 22000.00, 10, 2200.00, 'aktif'), -- Palaran → Lempake
(87, 8, 11, 48000.00, 10, 4800.00, 'aktif'), -- Palaran → Sangatta
(88, 8, 12, 28000.00, 10, 2800.00, 'aktif'), -- Palaran → Tenggarong

-- Dari Loa Bakung (LBK)
(89, 9, 1, 38000.00, 10, 3800.00, 'aktif'), -- Loa Bakung → Bontang
(90, 9, 2, 28000.00, 10, 2800.00, 'aktif'), -- Loa Bakung → Samarinda
(91, 9, 3, 70000.00, 10, 7000.00, 'aktif'), -- Loa Bakung → Wahau
(92, 9, 4, 80000.00, 10, 8000.00, 'aktif'), -- Loa Bakung → Melak
(93, 9, 5, 40000.00, 10, 4000.00, 'aktif'), -- Loa Bakung → Balikpapan
(94, 9, 6, 90000.00, 10, 9000.00, 'aktif'), -- Loa Bakung → Berau
(95, 9, 7, 65000.00, 10, 6500.00, 'aktif'), -- Loa Bakung → Lambung
(96, 9, 8, 20000.00, 10, 2000.00, 'aktif'), -- Loa Bakung → Palaran
(97, 9, 10, 15000.00, 10, 1500.00, 'aktif'), -- Loa Bakung → Lempake
(98, 9, 11, 50000.00, 10, 5000.00, 'aktif'), -- Loa Bakung → Sangatta
(99, 9, 12, 30000.00, 10, 3000.00, 'aktif'), -- Loa Bakung → Tenggarong

-- Dari Lempake (LPK)
(100, 10, 1, 42000.00, 10, 4200.00, 'aktif'), -- Lempake → Bontang
(101, 10, 2, 30000.00, 10, 3000.00, 'aktif'), -- Lempake → Samarinda
(102, 10, 3, 72000.00, 10, 7200.00, 'aktif'), -- Lempake → Wahau
(103, 10, 4, 82000.00, 10, 8200.00, 'aktif'), -- Lempake → Melak
(104, 10, 5, 42000.00, 10, 4200.00, 'aktif'), -- Lempake → Balikpapan
(105, 10, 6, 92000.00, 10, 9200.00, 'aktif'), -- Lempake → Berau
(106, 10, 7, 67000.00, 10, 6700.00, 'aktif'), -- Lempake → Lambung
(107, 10, 8, 22000.00, 10, 2200.00, 'aktif'), -- Lempake → Palaran
(108, 10, 9, 15000.00, 10, 1500.00, 'aktif'), -- Lempake → Loa Bakung
(109, 10, 11, 52000.00, 10, 5200.00, 'aktif'), -- Lempake → Sangatta
(110, 10, 12, 32000.00, 10, 3200.00, 'aktif'), -- Lempake → Tenggarong

-- Dari Sangatta (SGT)
(111, 11, 1, 30000.00, 10, 3000.00, 'aktif'), -- Sangatta → Bontang
(112, 11, 2, 55000.00, 10, 5500.00, 'aktif'), -- Sangatta → Samarinda
(113, 11, 3, 90000.00, 10, 9000.00, 'aktif'), -- Sangatta → Wahau
(114, 11, 4, 100000.00, 10, 10000.00, 'aktif'), -- Sangatta → Melak
(115, 11, 5, 65000.00, 10, 6500.00, 'aktif'), -- Sangatta → Balikpapan
(116, 11, 6, 70000.00, 10, 7000.00, 'aktif'), -- Sangatta → Berau
(117, 11, 7, 85000.00, 10, 8500.00, 'aktif'), -- Sangatta → Lambung
(118, 11, 8, 48000.00, 10, 4800.00, 'aktif'), -- Sangatta → Palaran
(119, 11, 9, 50000.00, 10, 5000.00, 'aktif'), -- Sangatta → Loa Bakung
(120, 11, 10, 52000.00, 10, 5200.00, 'aktif'), -- Sangatta → Lempake
(121, 11, 12, 60000.00, 10, 6000.00, 'aktif'), -- Sangatta → Tenggarong

-- Dari Tenggarong (TGR)
(122, 12, 1, 55000.00, 10, 5500.00, 'aktif'), -- Tenggarong → Bontang
(123, 12, 2, 32000.00, 10, 3200.00, 'aktif'), -- Tenggarong → Samarinda
(124, 12, 3, 78000.00, 10, 7800.00, 'aktif'), -- Tenggarong → Wahau
(125, 12, 4, 88000.00, 10, 8800.00, 'aktif'), -- Tenggarong → Melak
(126, 12, 5, 45000.00, 10, 4500.00, 'aktif'), -- Tenggarong → Balikpapan
(127, 12, 6, 98000.00, 10, 9800.00, 'aktif'), -- Tenggarong → Berau
(128, 12, 7, 73000.00, 10, 7300.00, 'aktif'), -- Tenggarong → Lambung
(129, 12, 8, 28000.00, 10, 2800.00, 'aktif'), -- Tenggarong → Palaran
(130, 12, 9, 30000.00, 10, 3000.00, 'aktif'), -- Tenggarong → Loa Bakung
(131, 12, 10, 32000.00, 10, 3200.00, 'aktif'), -- Tenggarong → Lempake
(132, 12, 11, 60000.00, 10, 6000.00, 'aktif'); -- Tenggarong → Sangatta