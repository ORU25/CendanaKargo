CREATE TABLE Kantor_cabang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_cabang VARCHAR(10) NOT NULL UNIQUE,
    nama_cabang VARCHAR(100) NOT NULL,
    alamat_cabang TEXT NOT NULL,
    telp_cabang VARCHAR(20)
);

CREATE TABLE User (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cabang INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'superAdmin') DEFAULT 'admin',
    nama VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_cabang) REFERENCES Kantor_cabang(id)
);

CREATE TABLE Tarif_pengiriman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cabang_asal INT NOT NULL,
    id_cabang_tujuan INT NOT NULL,
    tarif_dasar DECIMAL(10,2) NOT NULL,
    batas_berat_dasar INT NOT NULL,
    tarif_tambahan_perkg DECIMAL(10,2) NOT NULL,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    FOREIGN KEY (id_cabang_asal) REFERENCES Kantor_cabang(id),
    FOREIGN KEY (id_cabang_tujuan) REFERENCES Kantor_cabang(id)
);

CREATE TABLE Pengiriman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_cabang_pengirim INT NOT NULL,
    id_cabang_penerima INT NOT NULL,
    id_tarif INT NOT NULL,
    no_resi VARCHAR(30) UNIQUE NOT NULL,
    nama_pengirim VARCHAR(100) NOT NULL,
    telp_pengirim VARCHAR(20),
    nama_penerima VARCHAR(100) NOT NULL,
    telp_penerima VARCHAR(20),
    nama_barang VARCHAR(100) NOT NULL,
    berat DECIMAL(10,2) NOT NULL,
    jumlah INT DEFAULT 1,
    jasa_pengiriman VARCHAR(100),
    tanggal DATE NOT NULL,
    total_tarif DECIMAL(10,2) NOT NULL,
    status varchar(50) DEFAULT 'dalam proses',
    FOREIGN KEY (id_user) REFERENCES User(id),
    FOREIGN KEY (id_cabang_pengirim) REFERENCES Kantor_cabang(id),
    FOREIGN KEY (id_cabang_penerima) REFERENCES Kantor_cabang(id),
    FOREIGN KEY (id_tarif) REFERENCES Tarif_pengiriman(id)
);

CREATE TABLE Surat_jalan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cabang_pengirim INT NOT NULL,
    id_cabang_penerima INT NOT NULL,
    tanggal DATE NOT NULL,
    driver VARCHAR(100) NOT NULL,
    status ENUM('dalam perjalanan', 'sampai tujuan') DEFAULT 'dalam perjalanan',
    FOREIGN KEY (id_cabang_pengirim) REFERENCES Kantor_cabang(id),
    FOREIGN KEY (id_cabang_penerima) REFERENCES Kantor_cabang(id)
);

CREATE TABLE detail_surat_jalan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_surat_jalan INT NOT NULL,
    id_pengiriman INT NOT NULL,
    FOREIGN KEY (id_surat_jalan) REFERENCES Surat_jalan(id),
    FOREIGN KEY (id_pengiriman) REFERENCES Pengiriman(id)
);


-- akun user superadmin dengan password 'superadmin123' (hashed)
INSERT INTO users (username, password, role)
VALUES ('superadmin', 
        '$2y$10$Q1kDgk2lbhhEim5r7i6z7uL7Dz6z8C2AEeM9pEQBgeG8UmtQhT9/O', 
        'superadmin');
