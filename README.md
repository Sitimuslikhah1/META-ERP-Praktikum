# META ERP Praktikum — Disesuaikan dengan Soal

Aplikasi PHP Native + MySQL untuk lima praktikum ERP Politeknik META Industri.

## Instalasi XAMPP
1. Ekstrak folder `META_ERP_Sesuai_Soal` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dan MySQL.
3. Buka `http://localhost/phpmyadmin`.
4. Buat/import database dengan mengimpor `database/meta_erp_db.sql`.
5. Buka `http://localhost/META_ERP_Sesuai_Soal/`.

Konfigurasi database default: host `127.0.0.1`, user `root`, password kosong.

## Isi sesuai soal PDF
1. Integrasi ERP: sistem terpisah as-is, arsitektur terintegrasi to-be, data master, simulasi pesanan, dampak lintas departemen.
2. Arsitektur: 100 pengguna, matriks hak akses, backup/pemulihan, potensi gangguan dan penanganan.
3. Proses bisnis: as-is/to-be, aktor, aturan bisnis, PR, approval, penerimaan, perubahan penawaran.
4. Keuangan: saldo awal Rp30.000.000, lima transaksi persis sesuai soal, jurnal debit-kredit, saldo akun, simulasi duplikasi dan koreksi.
5. SCM/MRP: 300 produk; BOM A=3 dan B=2; stok A=400, PO A=100, lead time A=4 hari; stok B=250, PO B=0, lead time B=7 hari. Kebutuhan bruto A=900 dan B=600; kebutuhan bersih A=400 dan B=350. Kondisi perubahan lead time B menjadi 8 hari dibanding tenggat 6 hari dianalisis.

## Catatan akademik
Aplikasi merupakan prototipe simulasi. Matriks akses, arsitektur 100 pengguna, backup/pemulihan, dan sebagian alur lintas modul merupakan rancangan demonstrasi, bukan implementasi infrastruktur produksi atau autentikasi multi-role. Untuk praktikum 4, masukkan transaksi skenario satu per satu melalui form jurnal. Pastikan kode bukti unik dan cocokkan debit/kredit dengan tabel soal.
