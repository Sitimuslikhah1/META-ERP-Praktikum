-- =============================================================================
-- META ERP Enterprise Suite - Skema Database Lengkap Praktikum (Modul 1 - 5)
-- Politeknik META Industri Cikarang - TRPL PL/4008
-- Mata Kuliah: Enterprise Resource Planning (ERP)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS meta_erp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meta_erp_db;

-- -----------------------------------------------------------------------------
-- 0. TABEL AKUN / PENGGUNA MULTI-ROLE
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS akun (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  peran VARCHAR(60) NOT NULL,
  departemen VARCHAR(60) NOT NULL,
  avatar VARCHAR(10) DEFAULT '👤',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO akun (id, username, password, nama_lengkap, peran, departemen, avatar) VALUES
(1, 'admin', 'admin123', 'Administrator Utama', 'Super Administrator ERP', 'IT & Sistem Informasi', '👑'),
(2, 'adie.kusna', 'dosen123', 'Adie Kusna Wibowo, S.T., M.Kom.', 'Dosen Pengampu', 'Akademik TRPL', '🎓'),
(3, 'praktikan', 'meta2026', 'Mahasiswa Praktikan (TRPL)', 'Mahasiswa Praktikan', 'Politeknik META Industri', '⭐'),
(4, 'sales.andi', 'sales123', 'Andi Pratama', 'Staff Penjualan', 'Sales & Marketing (SD)', '💼'),
(5, 'it.budi', 'it123', 'Budi Santoso', 'IT System Administrator', 'Infrastruktur & Keamanan', '🛠️'),
(6, 'proc.citra', 'proc123', 'Citra Lestari', 'Procurement Officer', 'Pengadaan & Pembelian (MM)', '📋'),
(7, 'fin.dewi', 'fin123', 'Dewi Anggraini', 'Staff Akuntansi & Keuangan', 'Finance & Controlling (FI)', '💰'),
(8, 'wh.eko', 'wh123', 'Eko Saputra', 'Staff Gudang & Logistik', 'Warehouse & Supply Chain', '📦')
ON DUPLICATE KEY UPDATE 
  nama_lengkap=VALUES(nama_lengkap),
  peran=VALUES(peran),
  departemen=VALUES(departemen),
  avatar=VALUES(avatar);


-- -----------------------------------------------------------------------------
-- 1. TABEL DATA MASTER BERSAMA (PRAKTIKUM 1)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS p1_master_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kategori ENUM('Customer','Material','Vendor','BOM','COA') NOT NULL,
  kode VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(120) NOT NULL,
  departemen_pemilik VARCHAR(60) NOT NULL,
  digunakan_oleh VARCHAR(150) NOT NULL,
  atribut_kunci TEXT NOT NULL,
  contoh_data VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO p1_master_data (kategori, kode, nama, departemen_pemilik, digunakan_oleh, atribut_kunci, contoh_data) VALUES
('Customer', 'MD-CUST', 'Master Pelanggan (Customer Master)', 'Marketing / Sales', 'Penjualan, Gudang, Keuangan (AR)', 'ID Pelanggan, Nama PT, Alamat Kirim, TOP, Credit Limit', 'CUST-001 | PT Astra Honda Motor | Limit Rp200.000.000'),
('Material', 'MD-MAT', 'Master Material & Produk (Material Master)', 'Eng. / PPIC / Gudang', 'Penjualan, Gudang, Produksi, Pembelian, Keuangan', 'Part Number, Deskripsi, Satuan (UoM), Tipe, Lead Time', 'MAT-FG-001 | Brake Caliper Assy Type-C | Min Stock 50'),
('Vendor', 'MD-VEND', 'Master Pemasok (Vendor Master)', 'Procurement / Purchasing', 'Pembelian, Gudang (Receiving), Keuangan (AP)', 'ID Pemasok, Nama Rekanan, Rekening Bank, Lead Time', 'VEND-001 | PT Alumindo Jaya Perkasa | TOP 45 Hari'),
('BOM', 'MD-BOM', 'Master Formula Produk (Bill of Materials)', 'R&D / Engineering / PPIC', 'Produksi, Pembelian, Keuangan (HPP Standard)', 'Level Hierarki, Part Number Komponen, Rasio per Unit', 'BOM-KPP300 | 1 Unit KPP-300 = 3 Unit Bahan A + 2 Unit Bahan B'),
('COA', 'MD-COA', 'Bagan Akun Standar (Chart of Accounts)', 'Finance & Accounting', 'Seluruh Departemen Operasional Perusahaan', 'Nomor Akun, Nama Akun, Klasifikasi, Saldo Normal', '1101 Kas (Aset Lancar / Debit)')
ON DUPLICATE KEY UPDATE nama=VALUES(nama), contoh_data=VALUES(contoh_data);

-- -----------------------------------------------------------------------------
-- 2. TABEL SALES ORDERS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer VARCHAR(120) NOT NULL,
  product VARCHAR(150) NOT NULL,
  qty INT NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'Pesanan Baru',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 3. TABEL HAK AKSES RBAC & CHAOS LOGS (PRAKTIKUM 2)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS p2_rbac (
  id INT AUTO_INCREMENT PRIMARY KEY,
  peran VARCHAR(60) NOT NULL UNIQUE,
  departemen VARCHAR(60) NOT NULL,
  akses_sd VARCHAR(20) NOT NULL,
  akses_mm VARCHAR(20) NOT NULL,
  akses_pp VARCHAR(20) NOT NULL,
  akses_fi VARCHAR(20) NOT NULL,
  keterangan VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO p2_rbac (peran, departemen, akses_sd, akses_mm, akses_pp, akses_fi, keterangan) VALUES
('Staff Penjualan', 'Marketing & Sales', 'CRUD', 'R', '-', '-', 'Hanya mengelola Sales Order dan melihat ketersediaan stok'),
('Staff Gudang', 'Warehouse / Inventory', 'R', 'CRUD', 'R', '-', 'Mengelola mutasi stok fisik dan penerimaan barang (GRN)'),
('Supervisor Produksi', 'Shop Floor / PPIC', '-', 'R', 'CRUD', '-', 'Merilis Work Order dan mencatat konsumsi material BOM'),
('Staff Pembelian', 'Procurement', '-', 'CRUD', 'R', 'R', 'Membuat PR/PO dan memantau status tagihan supplier'),
('Staff Keuangan', 'Finance & Accounting', 'R', 'R', 'R', 'CRUD', 'Menjurnal transaksi, rekonsiliasi kas, dan laporan keuangan')
ON DUPLICATE KEY UPDATE keterangan=VALUES(keterangan);

CREATE TABLE IF NOT EXISTS p2_chaos_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  skenario_kode VARCHAR(20) NOT NULL,
  nama_skenario VARCHAR(100) NOT NULL,
  kategori VARCHAR(60) NOT NULL,
  dampak VARCHAR(255) NOT NULL,
  status_mitigasi VARCHAR(60) NOT NULL,
  waktu_uji TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 4. TABEL PENGADAAN & APPROVAL WORKFLOW (PRAKTIKUM 3)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS p3_approval_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_aturan VARCHAR(20) NOT NULL UNIQUE,
  rentang_nominal VARCHAR(60) NOT NULL,
  nominal_min BIGINT NOT NULL,
  nominal_max BIGINT NOT NULL,
  otoritas VARCHAR(100) NOT NULL,
  sla_waktu VARCHAR(50) NOT NULL,
  syarat_dokumen VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO p3_approval_rules (kode_aturan, rentang_nominal, nominal_min, nominal_max, otoritas, sla_waktu, syarat_dokumen) VALUES
('BR-01', '<= Rp 2.000.000 (Rutin)', 0, 2000000, 'Kepala Laboratorium Manufaktur', 'Max 1 Hari Kerja', 'Formulir PR Digital + Cek Kuota Anggaran Lab'),
('BR-02', 'Rp 2.000.001 - Rp 10.000.000 (Menengah)', 2000001, 10000000, 'Ka.Lab + Pudir II Bidang Keuangan', 'Max 2 Hari Kerja', 'Minimal 2 Penawaran Vendor + Verifikasi Keuangan'),
('BR-03', '> Rp 10.000.000 (Investasi Besar)', 10000001, 999999999, 'Ka.Lab + Pudir II + Direktur Politeknik', 'Max 3 Hari Kerja', 'Minimal 3 Penawaran Resmi + Justifikasi Kebutuhan Strategis'),
('BR-04', 'Pengadaan Darurat (Emergency 24 Jam)', 0, 5000000, 'Ka.Lab + Notifikasi Cepat Pudir II', 'Maksimal 24 Jam', 'Pernyataan Urgensi Praktikum Mahasiswa Besok Pagi')
ON DUPLICATE KEY UPDATE otoritas=VALUES(otoritas);

CREATE TABLE IF NOT EXISTS purchase_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requester VARCHAR(120) NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  quantity DECIMAL(12,2) NOT NULL,
  unit VARCHAR(30) NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('Menunggu Verifikasi','Disetujui','Ditolak','Barang Diterima','Diterima Sebagian') NOT NULL DEFAULT 'Menunggu Verifikasi',
  received DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_name VARCHAR(150) NOT NULL UNIQUE,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit VARCHAR(30) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO inventory(item_name, quantity, unit) VALUES
('Bahan A (Aluminium Billet)', 400, 'Unit'),
('Bahan B (Baut High-Tensile)', 250, 'Unit')
ON DUPLICATE KEY UPDATE quantity=VALUES(quantity);

CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact VARCHAR(150),
  lead_time INT NOT NULL DEFAULT 4,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO suppliers(name, contact, lead_time) VALUES
('PT Alumindo Jaya Perkasa', 'sales@alumindo.co.id', 4),
('PT Fastener Baja Utama', 'order@fastenerbaja.com', 7)
ON DUPLICATE KEY UPDATE lead_time=VALUES(lead_time);

-- -----------------------------------------------------------------------------
-- 5. TABEL BAGAN AKUN & TRANSAKSI KEUANGAN (PRAKTIKUM 4)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coa (
  kode VARCHAR(10) PRIMARY KEY,
  nama VARCHAR(80) NOT NULL,
  klasifikasi VARCHAR(40) NOT NULL,
  saldo_normal ENUM('Debit','Kredit') NOT NULL,
  saldo_awal BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO coa (kode, nama, klasifikasi, saldo_normal, saldo_awal) VALUES
('1101', 'Kas', 'Aset Lancar', 'Debit', 30000000),
('1102', 'Piutang Usaha', 'Aset Lancar', 'Debit', 0),
('1103', 'Persediaan Bahan Baku', 'Aset Lancar', 'Debit', 0),
('2101', 'Utang Usaha', 'Kewajiban', 'Kredit', 0),
('3101', 'Modal Pemilik', 'Ekuitas', 'Kredit', 30000000),
('4101', 'Penjualan Produk', 'Pendapatan', 'Kredit', 0),
('5101', 'Beban Operasional', 'Beban', 'Debit', 0)
ON DUPLICATE KEY UPDATE nama=VALUES(nama), saldo_awal=VALUES(saldo_awal);

CREATE TABLE IF NOT EXISTS finance_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tx_date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  code VARCHAR(50) NOT NULL,
  debit_account VARCHAR(80) NOT NULL,
  credit_account VARCHAR(80) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  duplicate_of INT DEFAULT NULL,
  reversed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO finance_transactions (tx_date, description, code, debit_account, credit_account, amount) VALUES
('2026-11-01', '1. Pembelian bahan baku secara kredit', 'TRX-01', 'Persediaan/Pembelian', 'Utang Usaha', 8000000),
('2026-11-02', '2. Pembayaran utang kepada pemasok', 'TRX-02', 'Utang Usaha', 'Kas', 3000000),
('2026-11-03', '3. Penjualan produk secara kredit', 'TRX-03', 'Piutang Usaha', 'Penjualan', 12000000),
('2026-11-04', '4. Penerimaan pelunasan piutang pelanggan', 'TRX-04', 'Kas', 'Piutang Usaha', 5000000),
('2026-11-05', '5. Pembayaran biaya operasional tunai', 'TRX-05', 'Beban Operasional', 'Kas', 1500000)
ON DUPLICATE KEY UPDATE amount=VALUES(amount);

-- -----------------------------------------------------------------------------
-- 6. TABEL SCM, BOM & MRP (PRAKTIKUM 5)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS p5_material (
  kode VARCHAR(10) PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  rasio_bom INT NOT NULL,
  satuan VARCHAR(10) NOT NULL,
  stok INT NOT NULL,
  on_order INT NOT NULL,
  lead_time_hari INT NOT NULL,
  pemasok VARCHAR(80) NOT NULL,
  harga INT NOT NULL,
  gudang VARCHAR(40) NOT NULL,
  aturan_pesan VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO p5_material (kode, nama, rasio_bom, satuan, stok, on_order, lead_time_hari, pemasok, harga, gudang, aturan_pesan) VALUES
('MAT-A', 'Bahan Baku A (Aluminium Billet AL-6061)', 3, 'Unit', 400, 100, 4, 'PT Alumindo Jaya Perkasa', 45000, 'GD-01 Gudang Logistik', 'Lot-for-Lot, MOQ 50'),
('MAT-B', 'Bahan Baku B (Baut High-Tensile HT-88)', 2, 'Unit', 250, 0, 7, 'PT Fastener Baja Utama', 18000, 'GD-01 Gudang Logistik', 'Lot-for-Lot, MOQ 50')
ON DUPLICATE KEY UPDATE stok=VALUES(stok), lead_time_hari=VALUES(lead_time_hari);

CREATE TABLE IF NOT EXISTS p5_grn (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_no VARCHAR(30) NOT NULL,
  kode_material VARCHAR(10) NOT NULL,
  qty_po INT NOT NULL,
  qty_diterima INT NOT NULL,
  sisa INT NOT NULL,
  status VARCHAR(40) NOT NULL,
  catatan_qc VARCHAR(255) DEFAULT 'Lolos QC Inspection Laboratorium',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event VARCHAR(120) NOT NULL,
  detail TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
