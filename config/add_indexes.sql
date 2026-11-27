-- =====================================================
-- MIGRATION: Optimasi Index untuk Pengiriman
-- Tanggal: 2026-01-11
-- Deskripsi: Menambah indexes untuk performa query pengiriman
-- =====================================================

USE cendanalintaskargo;

-- Index untuk kolom id_cabang_pengirim (INT) - lebih cepat dari VARCHAR
-- Query di index.php filter berdasarkan id_cabang_pengirim
ALTER TABLE pengiriman ADD INDEX idx_cabang_pengirim (id_cabang_pengirim);

-- Index untuk no_resi (untuk search)
ALTER TABLE pengiriman ADD INDEX idx_no_resi (no_resi);

-- Composite index untuk query list pengiriman (id_cabang + sorting by id DESC)
ALTER TABLE pengiriman ADD INDEX idx_cabang_id_desc (id_cabang_pengirim, id DESC);

-- Index untuk status (sering di-filter)
ALTER TABLE pengiriman ADD INDEX idx_status (status);

-- Index untuk tanggal (untuk sorting dan filter)
ALTER TABLE pengiriman ADD INDEX idx_tanggal (tanggal);

-- =====================================================
-- TAMBAHAN INDEX - 2026-02-04
-- Untuk optimasi dashboard dan laporan
-- =====================================================

-- Index untuk cabang penerima (pengiriman masuk)
ALTER TABLE pengiriman ADD INDEX idx_cabang_penerima (id_cabang_penerima);

-- Index untuk status_pembayaran (filter revenue)
ALTER TABLE pengiriman ADD INDEX idx_status_pembayaran (status_pembayaran);

-- Composite index untuk query dashboard statistik harian
-- (cabang + tanggal) - sangat membantu query COUNT dengan filter tanggal
ALTER TABLE pengiriman ADD INDEX idx_cabang_tanggal (id_cabang_pengirim, tanggal);

-- Composite index untuk revenue query (pembayaran + tanggal)
ALTER TABLE pengiriman ADD INDEX idx_pembayaran_tanggal (status_pembayaran, tanggal);

-- =====================================================
-- TAMBAHAN INDEX - 2026-02-05
-- Untuk optimasi halaman Barang Masuk (admin cabang)
-- Query: WHERE status = ? AND cabang_penerima = ? ORDER BY id DESC
-- =====================================================

-- Composite index untuk barang masuk (status + cabang_penerima + id DESC)
-- Ini sangat penting untuk data puluhan ribu dengan 10 cabang akses bersamaan
ALTER TABLE pengiriman ADD INDEX idx_status_cabang_penerima (status, cabang_penerima, id DESC);

-- Verify indexes
SHOW INDEX FROM pengiriman WHERE Key_name LIKE 'idx_%';

-- =====================================================
-- TAMBAHAN INDEX - 2026-11-27
-- Dashboard superadmin/systemOwner: subquery pengambilan filter tanggal
-- (total_pendapatan 1.2 s -> 90 ms di data lokal 88rb pengambilan)
-- =====================================================
ALTER TABLE pengambilan ADD INDEX idx_tanggal (tanggal);
ALTER TABLE pengambilan ADD INDEX idx_user_tanggal (id_user, tanggal);

-- Dashboard admin & pendapatan per admin (filter id_user + tanggal)
ALTER TABLE pengiriman ADD INDEX idx_user_tanggal (id_user, tanggal);
