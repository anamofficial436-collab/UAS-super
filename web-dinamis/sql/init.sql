-- =============================================================
--  init.sql — Inisialisasi database UAS
--  File ini dijalankan otomatis saat kontainer MariaDB
--  pertama kali dibuat (docker-entrypoint-initdb.d)
-- =============================================================

-- Gunakan database yang sudah dibuat via environment variable
USE uas_db;

-- ─────────────────────────────────────────────────────────────
-- Tabel: users (untuk autentikasi admin panel)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    username   VARCHAR(50)      NOT NULL UNIQUE,
    password   VARCHAR(255)     NOT NULL,  -- bcrypt hash
    nama       VARCHAR(100)     NOT NULL,
    role       ENUM('admin','operator') NOT NULL DEFAULT 'operator',
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Tabel: mahasiswa (data utama aplikasi CRUD)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mahasiswa (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nim        VARCHAR(20)      NOT NULL UNIQUE COMMENT 'Nomor Induk Mahasiswa',
    nama       VARCHAR(100)     NOT NULL,
    jurusan    VARCHAR(100)     NOT NULL,
    angkatan   YEAR             NOT NULL,
    email      VARCHAR(150)         NULL UNIQUE,
    no_telp    VARCHAR(20)          NULL,
    ipk        DECIMAL(3,2)         NULL COMMENT 'Indeks Prestasi Kumulatif (0.00 - 4.00)',
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_nim     (nim),
    INDEX idx_jurusan (jurusan),
    INDEX idx_angkatan(angkatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Seeding: Admin user
-- password: admin123  →  bcrypt hash (cost 12)
-- Untuk verifikasi: password_verify('admin123', hash) === true
-- ─────────────────────────────────────────────────────────────
INSERT INTO users (username, password, nama, role) VALUES
(
    'admin',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: admin123
    'Administrator',
    'admin'
)
ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    nama     = VALUES(nama),
    role     = VALUES(role);

-- ─────────────────────────────────────────────────────────────
-- Seeding: Data mahasiswa contoh (10 record)
-- ─────────────────────────────────────────────────────────────
INSERT INTO mahasiswa (nim, nama, jurusan, angkatan, email, no_telp, ipk) VALUES
('2021001001', 'Budi Santoso',       'Teknik Informatika',      2021, 'budi.s@kampus.ac.id',       '081234567890', 3.75),
('2021001002', 'Siti Rahayu',        'Sistem Informasi',        2021, 'siti.r@kampus.ac.id',       '081234567891', 3.80),
('2021001003', 'Ahmad Fauzi',        'Teknik Informatika',      2021, 'ahmad.f@kampus.ac.id',      '081234567892', 3.60),
('2022001001', 'Dewi Kusuma',        'Teknik Komputer',         2022, 'dewi.k@kampus.ac.id',       '081234567893', 3.90),
('2022001002', 'Rizky Pratama',      'Teknik Informatika',      2022, 'rizky.p@kampus.ac.id',      '081234567894', 3.55),
('2022001003', 'Putri Handayani',    'Sistem Informasi',        2022, 'putri.h@kampus.ac.id',      '081234567895', 3.85),
('2023001001', 'Fajar Nugroho',      'Teknik Informatika',      2023, 'fajar.n@kampus.ac.id',      '081234567896', 3.70),
('2023001002', 'Maya Sari',          'Manajemen Informatika',   2023, 'maya.s@kampus.ac.id',       '081234567897', 3.65),
('2023001003', 'Dimas Wahyudi',      'Teknik Komputer',         2023, 'dimas.w@kampus.ac.id',      '081234567898', 3.50),
('2023001004', 'Lestari Indah',      'Sistem Informasi',        2023, 'lestari.i@kampus.ac.id',    '081234567899', 3.95)
ON DUPLICATE KEY UPDATE
    nama     = VALUES(nama),
    jurusan  = VALUES(jurusan),
    angkatan = VALUES(angkatan),
    ipk      = VALUES(ipk);

-- Konfirmasi seeding
SELECT 'Database initialized successfully' AS status;
SELECT COUNT(*) AS total_mahasiswa FROM mahasiswa;
SELECT COUNT(*) AS total_users FROM users;
