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

-- Verify indexes
SHOW INDEX FROM pengiriman WHERE Key_name LIKE 'idx_%';
