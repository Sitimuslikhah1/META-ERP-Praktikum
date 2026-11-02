# META ERP Praktikum — Disesuaikan dengan Soal

Aplikasi PHP Native + MySQL untuk lima praktikum ERP Politeknik META Industri.

## Instalasi XAMPP
1. Ekstrak folder `META_ERP_Sesuai_Soal` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dan MySQL.
3. Buka `http://localhost/phpmyadmin`.
4. Buat/import database dengan mengimpor `database/meta_erp_db.sql`.
5. Buka `http://localhost/META_ERP_Sesuai_Soal/`.

Konfigurasi database default: host `127.0.0.1`, user `root`, password kosong.

Ringkasan Aplikasi
META ERP Praktikum adalah aplikasi web simulasi Enterprise Resource Planning (ERP) yang dibuat menggunakan PHP Native, MySQL, HTML, CSS, dan JavaScript. Aplikasi ini disusun untuk membantu demonstrasi lima materi praktikum ERP Politeknik META Industri: integrasi proses bisnis, arsitektur teknis, pengadaan, keuangan, dan perencanaan kebutuhan material.

1. Dashboard
- Menampilkan jumlah pesanan, Purchase Requisition (PR), pemasok, dan transaksi keuangan.
- Menampilkan saldo kas berjalan dengan saldo awal simulasi Rp30.000.000.
- Menyediakan navigasi menuju seluruh modul praktikum.
  
2. Praktikum 1 — Analisis Evolusi dan Integrasi ERP
- Menjelaskan permasalahan sistem terpisah pada bagian penjualan, gudang, produksi, dan keuangan.
- Menampilkan gambaran proses sebelum ERP (as-is) dan setelah ERP (to-be).
- Menyajikan data master bersama: pelanggan, produk/BOM, bahan baku, pemasok, gudang, dan akun keuangan.
- Menyediakan form simulasi pesanan pelanggan.
- Menjelaskan dampak pesanan terhadap penjualan, stok, produksi, dan keuangan.
- Menjelaskan penanganan kondisi stok kurang, perubahan jumlah pesanan, pembayaran belum diterima, dan keterlambatan produksi.
  
3. Praktikum 2 — Rancangan dan Pengujian Arsitektur Teknis ERP
- Menampilkan rancangan arsitektur untuk 100 pengguna: pengguna, antarmuka aplikasi, server aplikasi, database, dan penyimpanan backup.
- Menyajikan matriks hak akses untuk bagian penjualan, gudang, produksi, pembelian, dan keuangan.
- Menjelaskan rencana pencadangan database dan prosedur pemulihan data.
- Menjelaskan potensi gangguan: database tidak dapat diakses, server aplikasi terganggu, koneksi internet terputus, akses tidak sah, dan kegagalan backup.
- Menyediakan tombol pencatatan log simulasi backup.
  
4. Praktikum 3 — Pemetaan Proses Bisnis dan Alur Persetujuan
- Menampilkan alur pembelian bahan praktik secara manual (as-is) dan alur usulan berbasis ERP (to-be).
- Menjelaskan peran dosen, kepala laboratorium, gudang, bagian pembelian, pimpinan, dan keuangan.
- Menyajikan aturan bisnis untuk identitas pemohon, pemeriksaan stok, persetujuan PO, dan kelengkapan dokumen pembayaran.
- Menyediakan form pengajuan Purchase Requisition (PR).
- Menyediakan simulasi persetujuan permintaan dan penerimaan barang, termasuk penerimaan sebagian.
- Menjelaskan penanganan perubahan nilai penawaran pemasok dan kebutuhan persetujuan ulang.
  
5. Praktikum 4 — Konfigurasi dan Simulasi Modul Keuangan
- Menggunakan saldo kas awal Rp30.000.000.
- Menyediakan skenario lima transaksi sesuai soal:
  1. Pembelian bahan baku secara kredit: Rp8.000.000.
  2. Pembayaran utang pemasok: Rp3.000.000.
  3. Penjualan produk secara kredit: Rp12.000.000.
  4. Penerimaan pembayaran pelanggan: Rp5.000.000.
  5. Pembayaran biaya operasional tunai: Rp1.500.000.
- Menyediakan form pencatatan jurnal dengan akun debit dan kredit.
- Menampilkan daftar jurnal serta ringkasan saldo akun.
- Menyediakan simulasi pencatatan transaksi duplikat dan penandaan koreksi.
- Catatan: aplikasi menghitung ringkasan saldo akun sederhana; laporan akuntansi lengkap dan neraca saldo formal belum diimplementasikan.
  
6. Praktikum 5 — Konfigurasi SCM dan Material Requirement Planning (MRP)
- Target produksi 300 unit.
- Bill of Materials (BOM): setiap produk membutuhkan 3 unit bahan A dan 2 unit bahan B.
- Data persediaan sesuai soal:
  - Bahan A: stok 400 unit, pesanan belum diterima 100 unit, lead time 4 hari kerja.
  - Bahan B: stok 250 unit, pesanan belum diterima 0 unit, lead time 7 hari kerja.
- Kebutuhan bruto: bahan A 900 unit dan bahan B 600 unit.
- Kebutuhan bersih: bahan A 400 unit dan bahan B 350 unit.
- Menjelaskan asumsi bahwa pesanan bahan A yang belum diterima dapat dipakai jika tiba sebelum material dibutuhkan.
- Menjelaskan risiko keterlambatan bahan B karena lead time 7 hari lebih lama daripada tenggat produksi 6 hari; juga skenario perubahan menjadi 8 hari.
- Menyediakan data pemasok dan lead time untuk simulasi.

## Catatan akademik
Aplikasi merupakan prototipe simulasi. Matriks akses, arsitektur 100 pengguna, backup/pemulihan, dan sebagian alur lintas modul merupakan rancangan demonstrasi, bukan implementasi infrastruktur produksi atau autentikasi multi-role. Untuk praktikum 4, masukkan transaksi skenario satu per satu melalui form jurnal. Pastikan kode bukti unik dan cocokkan debit/kredit dengan tabel soal.
