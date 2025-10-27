USE cendanalintaskargo;

CREATE TABLE Kantor_cabang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_cabang VARCHAR(10) NOT NULL UNIQUE,
    nama_cabang VARCHAR(100) NOT NULL,
    alamat_cabang TEXT NOT NULL,
    telp_cabang VARCHAR(20) NOT NULL
);

CREATE TABLE User (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cabang INT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'superAdmin', 'superSuperAdmin') DEFAULT 'admin',
    FOREIGN KEY (id_cabang) REFERENCES Kantor_cabang(id) ON DELETE CASCADE
);

CREATE TABLE Tarif_pengiriman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cabang_asal INT NOT NULL,
    id_cabang_tujuan INT NOT NULL,
    tarif_dasar DECIMAL(10,2) NOT NULL,
    batas_berat_dasar INT NOT NULL,
    tarif_tambahan_perkg DECIMAL(10,2) NOT NULL,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    FOREIGN KEY (id_cabang_asal) REFERENCES Kantor_cabang(id) ON DELETE CASCADE,
    FOREIGN KEY (id_cabang_tujuan) REFERENCES Kantor_cabang(id) ON DELETE CASCADE
);

CREATE TABLE Pengiriman (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_user INT,
    id_cabang_pengirim INT,
    id_cabang_penerima INT,
    id_tarif INT,
    no_resi VARCHAR(50) UNIQUE NOT NULL,
    user VARCHAR(50) NOT NULL,
    cabang_pengirim VARCHAR(100) NOT NULL,
    cabang_penerima VARCHAR(100) NOT NULL,
    nama_pengirim VARCHAR(100) NOT NULL,
    telp_pengirim VARCHAR(20) NOT NULL,
    nama_penerima VARCHAR(100) NOT NULL,
    telp_penerima VARCHAR(20) NOT NULL,
    nama_barang VARCHAR(100) NOT NULL,
    berat DECIMAL(10,2) NOT NULL,
    jumlah INT DEFAULT 1,
    pembayaran ENUM('cash', 'transfer', 'bayar di tempat') NOT NULL,
    tanggal DATETIME NOT NULL,
    diskon DECIMAL(10,2) DEFAULT 0,
    total_tarif DECIMAL(10,2) NOT NULL,
    status ENUM('bkd', 'dalam pengiriman', 'sampai tujuan', 'pod', 'dibatalkan') DEFAULT 'bkd',
    FOREIGN KEY (id_user) REFERENCES User(id) ON DELETE SET NULL,
    FOREIGN KEY (id_cabang_pengirim) REFERENCES Kantor_cabang(id) ON DELETE SET NULL,
    FOREIGN KEY (id_cabang_penerima) REFERENCES Kantor_cabang(id) ON DELETE SET NULL,
    FOREIGN KEY (id_tarif) REFERENCES Tarif_pengiriman(id) ON DELETE SET NULL
);

CREATE TABLE Surat_jalan (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_user INT,
    id_cabang_pengirim INT,
    id_cabang_penerima INT,
    no_surat_jalan VARCHAR(30) UNIQUE NOT NULL,
    user VARCHAR(50) NOT NULL,
    cabang_pengirim VARCHAR(100) NOT NULL,
    cabang_penerima VARCHAR(100) NOT NULL,
    tanggal DATETIME NOT NULL,
    driver VARCHAR(100) NOT NULL,
    status ENUM('draft','dalam perjalanan', 'sampai tujuan', 'dibatalkan') DEFAULT 'draft',
    FOREIGN KEY (id_user) REFERENCES User(id) ON DELETE SET NULL,
    FOREIGN KEY (id_cabang_pengirim) REFERENCES Kantor_cabang(id) ON DELETE SET NULL,
    FOREIGN KEY (id_cabang_penerima) REFERENCES Kantor_cabang(id) ON DELETE SET NULL
);

CREATE TABLE detail_surat_jalan (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_surat_jalan BIGINT,
    id_pengiriman BIGINT,
    FOREIGN KEY (id_surat_jalan) REFERENCES Surat_jalan(id) ON DELETE SET NULL,
    FOREIGN KEY (id_pengiriman) REFERENCES Pengiriman(id) ON DELETE SET NULL
);

CREATE TABLE pengambilan (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_user INT,
    no_resi VARCHAR(50) NOT NULL,
    nama_pengambil VARCHAR(100) NOT NULL,
    telp_pengambil VARCHAR(20) NOT NULL,
    tanggal DATETIME NOT NULL,
    FOREIGN KEY (id_user) REFERENCES User(id) ON DELETE SET NULL
);

CREATE TABLE log_status_pengiriman (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_pengiriman BIGINT NOT NULL,
    status_lama ENUM('bkd', 'dalam pengiriman', 'sampai tujuan', 'pod', 'dibatalkan'),
    status_baru ENUM('bkd', 'dalam pengiriman', 'sampai tujuan', 'pod', 'dibatalkan') NOT NULL,
    keterangan VARCHAR(255) DEFAULT NULL,
    diubah_oleh INT DEFAULT NULL,
    waktu_perubahan DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pengiriman) REFERENCES Pengiriman(id) ON DELETE CASCADE,
    FOREIGN KEY (diubah_oleh) REFERENCES User(id) ON DELETE SET NULL
);

CREATE TABLE log_surat_jalan (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_surat_jalan BIGINT NOT NULL,
    id_user INT,
    status_lama ENUM('draft', 'dalam perjalanan', 'sampai tujuan', 'dibatalkan') NULL,
    status_baru ENUM('draft', 'dalam perjalanan', 'sampai tujuan', 'dibatalkan') NOT NULL,
    keterangan TEXT,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    username VARCHAR(50) NOT NULL,
    FOREIGN KEY (id_surat_jalan) REFERENCES Surat_jalan(id) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES User(id) ON DELETE SET NULL
);

-- akun user superadmin dengan password 'supersuperadmin' (hashed)
INSERT INTO User (username, password, role)
VALUES (
    'supersuperadmin',
    '$2y$10$jmjSlyLplXCYwg0lGeUQReYm5ZG29DJSAz79PZIpessBjNx8sZfaa',
    'supersuperadmin'
);
