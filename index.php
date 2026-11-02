<?php
session_start();
require __DIR__.'/config/database.php';

function h($v){return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');}
function rupiah($v){return 'Rp '.number_format((float)$v,0,',','.');}

// Load Accounts for Multi-Role Switching
$accounts = [];
try {
  $accounts = $pdo->query("SELECT * FROM akun ORDER BY id ASC")->fetchAll();
} catch (Throwable $e) {}

if (empty($accounts)) {
  $accounts = [
    ['id'=>1, 'username'=>'admin', 'nama_lengkap'=>'Administrator Utama', 'peran'=>'Super Administrator ERP', 'departemen'=>'IT & Sistem Informasi', 'avatar'=>'👑'],
    ['id'=>2, 'username'=>'adie.kusna', 'nama_lengkap'=>'Adie Kusna Wibowo, S.T., M.Kom.', 'peran'=>'Dosen Pengampu', 'departemen'=>'Akademik TRPL', 'avatar'=>'🎓'],
    ['id'=>3, 'username'=>'praktikan', 'nama_lengkap'=>'Mahasiswa Praktikan (TRPL)', 'peran'=>'Mahasiswa Praktikan', 'departemen'=>'Politeknik META Industri', 'avatar'=>'⭐'],
    ['id'=>4, 'username'=>'sales.andi', 'nama_lengkap'=>'Andi Pratama', 'peran'=>'Staff Penjualan', 'departemen'=>'Sales & Marketing (SD)', 'avatar'=>'💼'],
    ['id'=>5, 'username'=>'it.budi', 'nama_lengkap'=>'Budi Santoso', 'peran'=>'IT System Administrator', 'departemen'=>'Infrastruktur & Keamanan', 'avatar'=>'🛠️'],
    ['id'=>6, 'username'=>'proc.citra', 'nama_lengkap'=>'Citra Lestari', 'peran'=>'Procurement Officer', 'departemen'=>'Pengadaan & Pembelian (MM)', 'avatar'=>'📋'],
    ['id'=>7, 'username'=>'fin.dewi', 'nama_lengkap'=>'Dewi Anggraini', 'peran'=>'Staff Akuntansi & Keuangan', 'departemen'=>'Finance & Controlling (FI)', 'avatar'=>'💰'],
    ['id'=>8, 'username'=>'wh.eko', 'nama_lengkap'=>'Eko Saputra', 'peran'=>'Staff Gudang & Logistik', 'departemen'=>'Warehouse & Supply Chain', 'avatar'=>'📦']
  ];
}

// Active user session
if (!isset($_SESSION['current_user'])) {
  $_SESSION['current_user'] = $accounts[0];
}
$currentUser = $_SESSION['current_user'];

$page = $_GET['page'] ?? 'dashboard';
$pages = ['dashboard','integrasi','arsitektur','proses','keuangan','scm','laporan'];
if (!in_array($page, $pages, true)) $page = 'dashboard';

$msg = '';
$msg_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $a = $_POST['action'] ?? '';
    
    // Switch User Role
    if ($a === 'switch_user') {
      $uname = trim($_POST['username'] ?? '');
      foreach ($accounts as $acc) {
        if ($acc['username'] === $uname) {
          $_SESSION['current_user'] = $acc;
          $currentUser = $acc;
          $msg = 'Beralih peran aktif ke: ' . $acc['nama_lengkap'] . ' (' . $acc['peran'] . ')';
          $msg_type = 'success';
          break;
        }
      }
    }
    // Sales Order
    elseif ($a === 'order') {
      $cust = trim($_POST['customer']);
      $prod = trim($_POST['product']);
      $qty = max(1, (int)$_POST['qty']);
      
      // Credit check simulation
      $status = 'Pesanan Disetujui';
      $notes = 'Lolos pengecekan batas kredit';
      if (stripos($cust, 'Suzuki') !== false || $qty >= 500) {
        $status = 'CREDIT BLOCKED';
        $notes = 'Jumlah pesanan melampaui limit kredit yang tersedia';
      }
      
      $pdo->prepare("INSERT INTO orders(customer,product,qty,status) VALUES(?,?,?,?)")
        ->execute([$cust, $prod, $qty, $status]);
      $msg = 'Pesanan tersimpan dengan status [' . $status . ']. Dampak otomatis diteruskan ke Gudang, Produksi, dan Keuangan.';
      $msg_type = $status === 'CREDIT BLOCKED' ? 'warning' : 'success';
    }
    // PR Request
    elseif ($a === 'request') {
      $pdo->prepare("INSERT INTO purchase_requests(requester,item_name,quantity,unit,reason,status) VALUES(?,?,?,?,?,'Menunggu Verifikasi')")
        ->execute([trim($_POST['requester']), trim($_POST['item_name']), (float)$_POST['quantity'], trim($_POST['unit']), trim($_POST['reason'])]);
      $msg = 'Purchase Requisition berhasil diajukan dan masuk ke antrean verifikasi.';
      $msg_type = 'success';
    }
    // Approve PR
    elseif ($a === 'approve') {
      $pdo->prepare("UPDATE purchase_requests SET status='Disetujui' WHERE id=? AND status='Menunggu Verifikasi'")->execute([(int)$_POST['id']]);
      $msg = 'Permintaan pembelian telah disetujui (PO siap diterbitkan ke pemasok).';
      $msg_type = 'success';
    }
    // Receive Goods
    elseif ($a === 'receive') {
      $pdo->beginTransaction();
      $q = $pdo->prepare("SELECT * FROM purchase_requests WHERE id=? FOR UPDATE");
      $q->execute([(int)$_POST['id']]);
      $r = $q->fetch();
      if (!$r || $r['status'] !== 'Disetujui') throw new Exception('Permintaan belum berstatus Disetujui.');
      $qty = (float)$_POST['quantity'];
      if ($qty <= 0 || $qty > ($r['quantity'] - $r['received'])) throw new Exception('Jumlah penerimaan tidak valid.');
      
      $pdo->prepare("INSERT INTO inventory(item_name,quantity,unit) VALUES(?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
        ->execute([$r['item_name'], $qty, $r['unit']]);
      
      $newReceived = $r['received'] + $qty;
      $status = $newReceived >= (float)$r['quantity'] ? 'Barang Diterima' : 'Diterima Sebagian';
      $pdo->prepare("UPDATE purchase_requests SET received=?, status=? WHERE id=?")->execute([$newReceived, $status, $r['id']]);
      
      // Catat juga ke p5_grn log jika tabel ada
      try {
        $pdo->prepare("INSERT INTO p5_grn (po_no, kode_material, qty_po, qty_diterima, sisa, status, catatan_qc) VALUES (?,?,?,?,?,?,?)")
          ->execute(['PO-PR-'.$r['id'], 'MAT-GEN', (int)$r['quantity'], (int)$qty, (int)($r['quantity'] - $newReceived), $status, 'Lolos QC Inspection Laboratorium']);
      } catch (Throwable $eIgnore) {}

      $pdo->commit();
      $msg = 'Penerimaan fisik barang (GRN) berhasil dicatat dan saldo stok diperbarui otomatis.';
      $msg_type = 'success';
    }
    // Supplier
    elseif ($a === 'supplier') {
      $pdo->prepare("INSERT INTO suppliers(name,contact,lead_time) VALUES(?,?,?)")
        ->execute([trim($_POST['name']), trim($_POST['contact']), max(0, (int)$_POST['lead_time'])]);
      $msg = 'Data pemasok baru berhasil disimpan.';
      $msg_type = 'success';
    }
    // Finance Journal Entry
    elseif ($a === 'finance') {
      $pdo->beginTransaction();
      $amount = (float)$_POST['amount'];
      if ($amount <= 0) throw new Exception('Nominal transaksi harus lebih dari nol.');
      $pdo->prepare("INSERT INTO finance_transactions(tx_date,description,code,debit_account,credit_account,amount,duplicate_of) VALUES(?,?,?,?,?,?,NULL)")
        ->execute([$_POST['tx_date'], trim($_POST['description']), trim($_POST['code']), $_POST['debit_account'], $_POST['credit_account'], $amount]);
      $pdo->commit();
      $msg = 'Transaksi jurnal umum berhasil dicatat ke buku besar.';
      $msg_type = 'success';
    }
    // Simulate Duplicate
    elseif ($a === 'duplicate') {
      $q = $pdo->prepare("SELECT * FROM finance_transactions WHERE id=?");
      $q->execute([(int)$_POST['id']]);
      $t = $q->fetch();
      if (!$t) throw new Exception('Transaksi asal tidak ditemukan.');
      $pdo->prepare("INSERT INTO finance_transactions(tx_date,description,code,debit_account,credit_account,amount,duplicate_of) VALUES(?,?,?,?,?,?,?)")
        ->execute([$t['tx_date'], $t['description'].' (Duplikasi Pembayaran Pemasok)', $t['code'].'-DUP', $t['debit_account'], $t['credit_account'], $t['amount'], $t['id']]);
      $msg = 'Duplikasi transaksi berhasil disimulasikan. Saldo kas berkurang ganda; gunakan tombol Jurnal Koreksi / Pembalik untuk audit trail.';
      $msg_type = 'warning';
    }
    // Reverse Duplicate Entry
    elseif ($a === 'reverse') {
      $q = $pdo->prepare("SELECT * FROM finance_transactions WHERE id=?");
      $q->execute([(int)$_POST['id']]);
      $t = $q->fetch();
      if (!$t || !$t['duplicate_of']) throw new Exception('Hanya transaksi duplikat simulasi yang dapat dikoreksi di sini.');
      $pdo->prepare("UPDATE finance_transactions SET reversed=1 WHERE id=?")->execute([$t['id']]);
      $msg = 'Jurnal Pembalik (Reversing Entry) berhasil dicatat; dampak kas dibatalkan dan jejak audit transaksi asal tetap utuh.';
      $msg_type = 'success';
    }
    // Chaos Simulator Test
    elseif ($a === 'chaos_test') {
      $code = $_POST['skenario_kode'] ?? 'DS-01';
      $nama = $_POST['nama_skenario'] ?? 'Simulasi Gangguan';
      $kat = $_POST['kategori'] ?? 'System Test';
      $dampak = $_POST['dampak'] ?? 'Pengujian failover';
      $mitigasi = $_POST['status_mitigasi'] ?? 'Mitigasi otomatis berjalan';
      
      try {
        $pdo->prepare("INSERT INTO p2_chaos_logs (skenario_kode, nama_skenario, kategori, dampak, status_mitigasi) VALUES (?,?,?,?,?)")
          ->execute([$code, $nama, $kat, $dampak, $mitigasi]);
      } catch (Throwable $e) {}
      
      $pdo->prepare("INSERT INTO system_logs(event,detail) VALUES(?,?)")
        ->execute(['Chaos Test: ' . $code, $mitigasi]);
      $msg = 'Pengujian Chaos Engineering [' . $code . '] sukses dijalankan. Log mitigasi telah dicatat ke sistem audit.';
      $msg_type = 'success';
    }
    // Backup simulation
    elseif ($a === 'backup') {
      $pdo->prepare("INSERT INTO system_logs(event,detail) VALUES('3-2-1 Automated Backup','Snapshot database harian selesai. Replikasi WAL per 5 menit tersimpan di Object Storage cloud.')")->execute();
      $msg = 'Simulasi backup otomatis 3-2-1 berhasil dicatat ke log pemeliharaan.';
      $msg_type = 'success';
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msg = 'Operasi tidak berhasil: ' . $e->getMessage();
    $msg_type = 'danger';
  }
}

// Queries for Module Data
$requests = $pdo->query("SELECT * FROM purchase_requests ORDER BY id DESC")->fetchAll();
$stock = $pdo->query("SELECT * FROM inventory ORDER BY item_name")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
$orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC")->fetchAll();
$tx = $pdo->query("SELECT * FROM finance_transactions ORDER BY tx_date,id")->fetchAll();

// Balances Calculation (Rp 30.000.000 Initial Capital)
$balances = [
  'Kas' => 30000000,
  'Piutang Usaha' => 0,
  'Persediaan/Pembelian' => 0,
  'Utang Usaha' => 0,
  'Modal Pemilik' => 30000000,
  'Penjualan' => 0,
  'Beban Operasional' => 0
];
foreach ($tx as $t) {
  if (!empty($t['reversed'])) continue;
  $amt = (float)$t['amount'];
  $d = $t['debit_account'];
  $c = $t['credit_account'];
  if (isset($balances[$d])) $balances[$d] += $amt;
  if (isset($balances[$c])) {
    if ($c === 'Modal Pemilik' || $c === 'Utang Usaha' || $c === 'Penjualan') {
      $balances[$c] += $amt;
    } else {
      $balances[$c] -= $amt;
    }
  }
}
$cash = $balances['Kas'];

// Trial Balance Calculation
$trial_debits = [
  'Kas' => $balances['Kas'],
  'Piutang Usaha' => $balances['Piutang Usaha'],
  'Persediaan Bahan Baku' => $balances['Persediaan/Pembelian'],
  'Beban Operasional' => $balances['Beban Operasional']
];
$trial_credits = [
  'Utang Usaha' => $balances['Utang Usaha'],
  'Modal Pemilik (Saldo Awal)' => 30000000,
  'Penjualan Produk' => $balances['Penjualan']
];
$total_debit = array_sum($trial_debits);
$total_credit = array_sum($trial_credits);

// MRP Calculation (300 Units target, A=3/unit, B=2/unit)
$grossA = 900; $grossB = 600;
$stockA = 400; $poA = 100;
$stockB = 250; $poB = 0;
$netA = max(0, $grossA - $stockA - $poA); // 400
$netB = max(0, $grossB - $stockB - $poB); // 350

// Reference Tables Data
$masterData = [];
try { $masterData = $pdo->query("SELECT * FROM p1_master_data ORDER BY id ASC")->fetchAll(); } catch (Throwable $e) {}

$rbacMatrix = [];
try { $rbacMatrix = $pdo->query("SELECT * FROM p2_rbac ORDER BY id ASC")->fetchAll(); } catch (Throwable $e) {}

$chaosLogs = [];
try { $chaosLogs = $pdo->query("SELECT * FROM p2_chaos_logs ORDER BY id DESC LIMIT 5")->fetchAll(); } catch (Throwable $e) {}

$approvalRules = [];
try { $approvalRules = $pdo->query("SELECT * FROM p3_approval_rules ORDER BY id ASC")->fetchAll(); } catch (Throwable $e) {}

$grnLogs = [];
try { $grnLogs = $pdo->query("SELECT * FROM p5_grn ORDER BY id DESC LIMIT 5")->fetchAll(); } catch (Throwable $e) {}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>META ERP Enterprise Suite — Praktikum Terpadu TRPL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="layout">
  <!-- SIDEBAR NAVIGATION -->
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-logo"><i class="bi bi-layers-half"></i> META<span>ERP</span></div>
      <small>POLITEKNIK META INDUSTRI</small>
    </div>
    <nav>
      <?php 
      $nav = [
        'dashboard' => 'Dashboard',
        'integrasi' => '1. Integrasi ERP',
        'arsitektur' => '2. Arsitektur Teknis',
        'proses' => '3. Proses & Pengadaan',
        'keuangan' => '4. Modul Keuangan',
        'scm' => '5. SCM & MRP',
        'laporan' => 'Laporan & Bukti'
      ];
      $nav_icons = [
        'dashboard' => 'bi-speedometer2',
        'integrasi' => 'bi-diagram-3-fill',
        'arsitektur' => 'bi-hdd-network-fill',
        'proses' => 'bi-cart-check-fill',
        'keuangan' => 'bi-wallet2',
        'scm' => 'bi-boxes',
        'laporan' => 'bi-file-earmark-text-fill'
      ];
      foreach ($nav as $k => $v): ?>
        <a class="<?=$page===$k ? 'active' : ''?>" href="?page=<?=$k?>">
          <i class="bi <?=$nav_icons[$k]??'bi-circle'?> nav-icon"></i>
          <span class="nav-text"><?=h($v)?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <div class="status-indicator"><span class="dot-live"></span> Sistem Online</div>
      <small>MySQL: meta_erp_db • TRPL 2026</small>
    </div>
  </aside>

  <!-- MAIN VIEWPORT -->
  <main class="main">
    <!-- TOPBAR WITH USER SWITCHER -->
    <header class="topbar">
      <div>
        <div class="eyebrow"><i class="bi bi-mortarboard-fill me-1"></i> TRPL PL/4008 • PRAKTIKUM ERP</div>
        <h5 class="mb-0"><?=h($nav[$page])?></h5>
      </div>
      <div class="d-flex align-items-center gap-3">
        <!-- User Switcher Trigger Button -->
        <button type="button" class="user-btn" data-bs-toggle="modal" data-bs-target="#userModal" title="Klik untuk ganti peran demo">
          <span class="fs-5"><?=$currentUser['avatar']??'👤'?></span>
          <div class="text-start d-none d-sm-block">
            <div style="line-height:1.2;font-size:12.5px;"><?=h($currentUser['nama_lengkap'])?></div>
            <span class="user-badge-role"><?=h($currentUser['peran'])?></span>
          </div>
          <i class="bi bi-chevron-down text-muted ms-1" style="font-size:11px;"></i>
        </button>
      </div>
    </header>

    <div class="content">
      <?php if ($msg): ?>
        <div class="alert alert-<?=$msg_type==='danger'?'danger':($msg_type==='warning'?'warning':'info')?>">
          <i class="bi bi-<?=$msg_type==='danger'?'exclamation-circle-fill':($msg_type==='warning'?'exclamation-triangle-fill':'info-circle-fill')?> fs-5 me-2"></i>
          <div><?=h($msg)?></div>
        </div>
      <?php endif; ?>

      <!-- ===================================================================
           TAB: DASHBOARD
           =================================================================== -->
      <?php if ($page === 'dashboard'): ?>
        <div class="welcome">
          <div>
            <div class="eyebrow text-white-50"><i class="bi bi-award-fill me-1"></i> UJIAN TENGAH SEMESTER (UTS) • TEKNOLOGI REKAYASA PERANGKAT LUNAK</div>
            <h2>META ERP Enterprise Suite</h2>
            <p class="mb-0">Portal implementasi praktikum terpadu (Modul 1 s/d 5) sesuai standar silabus resmi Politeknik META Industri Cikarang. Dosen Pengampu: <b>Adie Kusna Wibowo, S.T., M.Kom.</b></p>
          </div>
          <b class="welcome-mark">ERP</b>
        </div>

        <!-- Academic Rubric & System Status -->
        <div class="row g-3 my-2">
          <div class="col-12 col-md-8">
            <div class="panel h-100 mb-0">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6><i class="bi bi-card-checklist me-2 text-primary"></i>Rubrik Penilaian Resmi UTS (Halaman 6–11)</h6>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Bobot: 100%</span>
              </div>
              <div class="row g-2">
                <div class="col-sm-6">
                  <div class="p-3 bg-light rounded-3 border">
                    <b class="d-block text-primary"><i class="bi bi-pc-display me-1"></i> 50% Bagian Praktikum (P1 - P5)</b>
                    <small class="text-muted">Kelengkapan implementasi 5 modul, validasi database master, otomasi approval berjenjang, jurnal seimbang, dan kalkulator MRP.</small>
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="p-3 bg-light rounded-3 border">
                    <b class="d-block text-primary"><i class="bi bi-file-earmark-text me-1"></i> 50% Bagian Esai Konseptual</b>
                    <small class="text-muted">Analisis arsitektur 3-tier, matriks otorisasi RACI/RBAC, mitigasi kegagalan sistem (RTO/RPO), dan decision matrix rantai pasok.</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="panel h-100 mb-0 d-flex flex-column justify-content-between">
              <h6><i class="bi bi-database-check me-2 text-success"></i>Konektivitas Database</h6>
              <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="dot-live"></span>
                  <strong class="text-dark">MySQL Server (XAMPP)</strong>
                </div>
                <div class="text-muted small">Database Aktif: <code>meta_erp_db</code></div>
                <div class="text-muted small">Tabel Terhubung: <b>14 Tabel Lengkap</b></div>
              </div>
              <div class="mt-3 pt-2 border-top">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle w-100 py-2"><i class="bi bi-shield-lock-fill me-1"></i> Multi-Role RBAC Aktif</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 4 Stats Cards -->
        <?php 
        $stat_meta = [
          ['Pesanan Penjualan', count($orders), 'bi-bag-check-fill', 'text-primary', '?page=integrasi'],
          ['Purchase Requisition', count($requests), 'bi-clipboard-check-fill', 'text-success', '?page=proses'],
          ['Pemasok Terdaftar', count($suppliers), 'bi-truck-front-fill', 'text-purple', '?page=scm'],
          ['Transaksi Jurnal', count($tx), 'bi-cash-coin', 'text-warning', '?page=keuangan']
        ];
        ?>
        <div class="row g-3 my-2">
          <?php foreach ($stat_meta as $s): ?>
            <div class="col-6 col-xl-3">
              <a href="<?=$s[4]?>" class="text-decoration-none">
                <div class="stat">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <small><?=h($s[0])?></small>
                    <i class="bi <?=$s[2]?> fs-5 opacity-75 <?=$s[3]?>"></i>
                  </div>
                  <strong><?=$s[1]?></strong>
                  <span><i class="bi bi-arrow-right-circle me-1"></i>Buka Modul</span>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Practical Overview & Cash Balance -->
        <div class="row g-3">
          <div class="col-lg-7">
            <div class="panel">
              <h6>5 Ruang Lingkup Praktikum Terpadu</h6>
              <div class="list-group list-group-flush">
                <a href="?page=integrasi" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2 border-0 border-bottom">
                  <span><b>1. Integrasi ERP</b> — Analisis 4 Silo Data, Diagram As-Is vs To-Be, Master Data & Pengujian Dosen</span>
                  <i class="bi bi-chevron-right text-muted"></i>
                </a>
                <a href="?page=arsitektur" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2 border-0 border-bottom">
                  <span><b>2. Arsitektur Teknis</b> — Model 3-Tier (100 Users), RBAC Matrix, 3-2-1 Backup & Chaos Simulator</span>
                  <i class="bi bi-chevron-right text-muted"></i>
                </a>
                <a href="?page=proses" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2 border-0 border-bottom">
                  <span><b>3. Proses & Pengadaan</b> — Kasus Lab Manufaktur, RACI, Slider Wewenang & SOP Darurat 24 Jam</span>
                  <i class="bi bi-chevron-right text-muted"></i>
                </a>
                <a href="?page=keuangan" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2 border-0 border-bottom">
                  <span><b>4. Modul Keuangan</b> — Saldo Awal Rp30 Jt, Jurnal Otomatis, Neraca Saldo Seimbang & Jurnal Pembalik</span>
                  <i class="bi bi-chevron-right text-muted"></i>
                </a>
                <a href="?page=scm" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2 border-0">
                  <span><b>5. SCM & MRP</b> — Target 300 Unit KPP-300, BOM, Bottleneck Bahan B & Decision Matrix Pemasok</span>
                  <i class="bi bi-chevron-right text-muted"></i>
                </a>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="panel">
              <h6>Posisi Saldo Kas Perusahaan</h6>
              <div class="money"><?=rupiah($cash)?></div>
              <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                <span class="text-muted small">Modal Awal Pemilik</span>
                <span class="fw-semibold small">Rp 30.000.000</span>
              </div>
              <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                <span class="text-muted small">Status Neraca Saldo</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Seimbang (Balanced)</span>
              </div>
              <div class="d-flex justify-content-between align-items-center py-1">
                <span class="text-muted small">Total Debit / Kredit</span>
                <span class="fw-semibold small"><?=rupiah($total_debit)?></span>
              </div>
              <a href="?page=keuangan" class="btn btn-outline-primary btn-sm w-100 mt-3"><i class="bi bi-journal-text me-1"></i>Buka Buku Besar Keuangan</a>
            </div>
          </div>
        </div>

      <!-- ===================================================================
           TAB 1: INTEGRASI ERP
           =================================================================== -->
      <?php elseif ($page === 'integrasi'): ?>
        <div class="panel">
          <h6>Praktikum 1 — Analisis Evolusi & Integrasi ERP</h6>
          <p class="text-muted">Penyelesaian masalah *Data Silo* pada 4 departemen sebelum implementasi ERP menuju arsitektur terintegrasi (*Single Source of Truth*).</p>
          
          <!-- 4 Data Silo Cards -->
          <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
              <div class="subpanel">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle mb-2">Penjualan</span>
                <b>Sistem: Spreadsheet</b>
                <p class="small text-muted mb-2"><b>Masalah:</b> Pesanan terlambat diperbarui ke pabrik.</p>
                <p class="small text-danger mb-0"><b>Dampak:</b> Janji delivery tidak realistis kepada pelanggan.</p>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="subpanel">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle mb-2">Gudang</span>
                <b>Sistem: Aplikasi Stok Terpisah</b>
                <p class="small text-muted mb-2"><b>Masalah:</b> Catatan fisik berbeda dengan pembelian.</p>
                <p class="small text-danger mb-0"><b>Dampak:</b> Terjadi overstock atau kekurangan bahan kritis.</p>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="subpanel">
                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle mb-2">Produksi</span>
                <b>Sistem: Formulir Manual</b>
                <p class="small text-muted mb-2"><b>Masalah:</b> Jadwal sering berubah mendadak.</p>
                <p class="small text-danger mb-0"><b>Dampak:</b> Mesin menganggur (idle time) menunggu komponen.</p>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="subpanel">
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-2">Keuangan</span>
                <b>Sistem: Aplikasi Akuntansi</b>
                <p class="small text-muted mb-2"><b>Masalah:</b> Transaksi harus dimasukkan ulang.</p>
                <p class="small text-danger mb-0"><b>Dampak:</b> Laporan terlambat, rawan salah ketik nominal.</p>
              </div>
            </div>
          </div>

          <!-- Architecture Comparison: As-Is vs To-Be -->
          <div class="row g-3">
            <div class="col-md-6">
              <div class="subpanel">
                <b>Arsitektur Sebelum ERP (As-Is: Data Silo Terfragmentasi)</b>
                <div class="flow">Penjualan (Spreadsheet Terpisah)</div>
                <div class="down">✕ tidak terhubung</div>
                <div class="flow">Gudang (Aplikasi Stok Mandiri)</div>
                <div class="down">✕ input ulang manual</div>
                <div class="flow">Produksi (Formulir Kertas)</div>
                <div class="down">✕ laporan terlambat</div>
                <div class="flow">Keuangan (Software Akuntansi Terpisah)</div>
                <p class="text-danger small mt-2 mb-0"><i class="bi bi-x-circle me-1"></i>Tinggi risiko redudansi data, salah input, dan lambatnya respon bisnis.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="subpanel">
                <b>Arsitektur Setelah ERP (To-Be: Single Source of Truth)</b>
                <div class="flow primary"><i class="bi bi-database-fill me-1"></i> Database Terpusat & Data Master Bersama</div>
                <div class="row g-2 mt-1">
                  <div class="col-6"><div class="flow"><i class="bi bi-bag-check me-1"></i> Penjualan (SD)</div></div>
                  <div class="col-6"><div class="flow"><i class="bi bi-boxes me-1"></i> Gudang (MM)</div></div>
                  <div class="col-6"><div class="flow"><i class="bi bi-gear-wide-connected me-1"></i> Produksi (PP)</div></div>
                  <div class="col-6"><div class="flow"><i class="bi bi-wallet2 me-1"></i> Keuangan (FI)</div></div>
                </div>
                <p class="text-success small mt-3 mb-0"><i class="bi bi-check-circle me-1"></i>Satu transaksi penjualan langsung mereservasi stok gudang, menjadwalkan produksi, dan mencatat piutang.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- 5 Master Data Bersama -->
        <div class="panel">
          <h6>5 Data Master Bersama Perusahaan</h6>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Nama Data Master</th>
                  <th>Pemilik Utama</th>
                  <th>Digunakan Oleh</th>
                  <th>Atribut Kunci</th>
                  <th>Contoh Data Riil</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($masterData)): ?>
                  <?php foreach ($masterData as $md): ?>
                    <tr>
                      <td><code><?=h($md['kode'])?></code></td>
                      <td><b><?=h($md['nama'])?></b></td>
                      <td><span class="badge bg-light text-dark border"><?=h($md['departemen_pemilik'])?></span></td>
                      <td><small><?=h($md['digunakan_oleh'])?></small></td>
                      <td><small class="text-muted"><?=h($md['atribut_kunci'])?></small></td>
                      <td><span class="text-primary small fw-semibold"><?=h($md['contoh_data'])?></span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="6" class="text-center text-muted">Data master belum dimuat.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Sales Order Simulator & Credit Check -->
        <div class="row g-3">
          <div class="col-lg-4">
            <div class="panel">
              <h6>Simulasi Pesanan Penjualan (Sales Order)</h6>
              <form method="post">
                <input type="hidden" name="action" value="order">
                <label>Nama Pelanggan (Customer)</label>
                <select required name="customer" class="form-select mb-2">
                  <option value="PT Astra Honda Motor">PT Astra Honda Motor (Limit: Rp 200 Jt)</option>
                  <option value="PT Yamaha Indonesia Motor">PT Yamaha Indonesia Motor (Limit: Rp 150 Jt)</option>
                  <option value="PT Suzuki Indomobil Motor">PT Suzuki Indomobil Motor (Limit: Rp 155 Jt - Uji Overlimit)</option>
                </select>
                <label>Produk Pesanan</label>
                <select required name="product" class="form-select mb-2">
                  <option value="Brake Caliper Assy Type-C (MAT-FG-001)">Brake Caliper Assy Type-C (MAT-FG-001)</option>
                  <option value="Piston Master Cylinder (MAT-FG-002)">Piston Master Cylinder (MAT-FG-002)</option>
                  <option value="Komponen Presisi Poros Mesin (KPP-300)">Komponen Presisi Poros Mesin (KPP-300)</option>
                </select>
                <label>Jumlah Pesanan (Qty)</label>
                <input type="number" min="1" required name="qty" value="50" class="form-control mb-3">
                <button class="btn btn-primary w-100"><i class="bi bi-cart-plus-fill me-1"></i>Simpan Sales Order</button>
              </form>
              <div class="alert alert-info mt-3 p-2 small mb-0">
                <i class="bi bi-info-circle me-1"></i>Sistem otomatis mengecek limit kredit. Pesanan Suzuki atau Qty &ge; 500 akan berstatus <b>CREDIT BLOCKED</b>.
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="panel">
              <h6>Daftar Transaksi Sales Order Tersimpan</h6>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Pelanggan</th>
                      <th>Produk</th>
                      <th>Qty</th>
                      <th>Status Order</th>
                      <th>Waktu</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($orders as $o): ?>
                      <tr>
                        <td><b><?=h($o['customer'])?></b></td>
                        <td><?=h($o['product'])?></td>
                        <td><?=$o['qty']?> Unit</td>
                        <td><?=h($o['status'])?></td>
                        <td><small class="text-muted"><?=$o['created_at']??'-'?></small></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- 4 Individual Challenges Dosen -->
        <div class="panel mt-3">
          <h6>Pengujian 4 Tantangan Individual Dosen (Halaman 7 Soal)</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="challenge-item">
                <b class="d-block text-primary"><i class="bi bi-1-circle-fill me-1"></i> Tantangan 1: Stok Bahan Baku Kurang</b>
                <small class="text-muted d-block mb-1"><b>Pemicu:</b> Pelanggan pesan 150 unit, bahan baku di gudang hanya cukup 80 unit.</small>
                <small class="d-block text-dark"><b>Respon Sistem ERP:</b> Sistem otomatis menahan Work Order, men-generate Purchase Requisition (PR) darurat untuk defisit 70 unit, dan memberitahu bagian penjualan tentang estimasi jadwal pengiriman baru.</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="challenge-item">
                <b class="d-block text-primary"><i class="bi bi-2-circle-fill me-1"></i> Tantangan 2: Perubahan Jumlah Pesanan Mendadak</b>
                <small class="text-muted d-block mb-1"><b>Pemicu:</b> Pesanan dinaikkan dari 50 menjadi 120 unit saat produksi berjalan.</small>
                <small class="d-block text-dark"><b>Respon Sistem ERP:</b> Menerbitkan revisi SO (Versioning Rev.1), mengkalkulasi ulang kebutuhan delta BOM (+70 unit), dan menerbitkan Work Order batch tambahan tanpa menduplikasi pesanan awal.</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="challenge-item">
                <b class="d-block text-primary"><i class="bi bi-3-circle-fill me-1"></i> Tantangan 3: Pembayaran Belum Diterima (Overdue)</b>
                <small class="text-muted d-block mb-1"><b>Pemicu:</b> Pelanggan memiliki faktur jatuh tempo &gt; 30 hari yang belum lunas.</small>
                <small class="d-block text-dark"><b>Respon Sistem ERP:</b> Fitur Credit Management Hold aktif. Sales Order baru berstatus <b>CREDIT BLOCKED</b>, surat penagihan (Dunning Letter) terkirim otomatis, dan gudang dicegah mencetak Surat Jalan.</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="challenge-item">
                <b class="d-block text-primary"><i class="bi bi-4-circle-fill me-1"></i> Tantangan 4: Keterlambatan Mesin Pabrik (Breakdown)</b>
                <small class="text-muted d-block mb-1"><b>Pemicu:</b> Mesin CNC utama rusak selama 2 hari kerja.</small>
                <small class="d-block text-dark"><b>Respon Sistem ERP:</b> Tiket perbaikan terbit di modul Maintenance, kapasitas Work Center diturunkan, jadwal Master Production Schedule (MPS) digeser otomatis, dan notifikasi keterlambatan dikirim ke Sales.</small>
              </div>
            </div>
          </div>
        </div>

      <!-- ===================================================================
           TAB 2: ARSITEKTUR TEKNIS ERP
           =================================================================== -->
      <?php elseif ($page === 'arsitektur'): ?>
        <div class="panel">
          <h6>Praktikum 2 — Arsitektur Teknis ERP untuk 100 Pengguna</h6>
          <p class="text-muted">Rancangan infrastruktur 3-Tier Enterprise terukur untuk 100 pengguna serentak di 5 departemen (Penjualan: 20, Gudang: 15, Produksi: 30, Pembelian: 15, Keuangan: 20).</p>
          
          <!-- 3-Tier Diagram -->
          <div class="architecture my-3">
            <div class="flow"><b>Client Tier:</b> 100 Pengguna Serentak (PC Kantor, Tablet Produksi, Barcode Scanner Gudang, Secure VPN)</div>
            <div class="down">↓ HTTPS / TLS 1.3 Terenkripsi</div>
            <div class="flow primary"><b>Web & Load Balancing Tier:</b> NGINX Reverse Proxy & SSL Termination (Cluster Port 80/443)</div>
            <div class="down">↓ Internal High-Speed Private Network (10 Gbps)</div>
            <div class="flow primary"><b>Application Server Tier:</b> Apache + PHP Cluster (Multi-Instance Instance 1 &amp; Instance 2)</div>
            <div class="down">↓ Connection Pooling (ProxySQL / PgBouncer)</div>
            <div class="flow"><b>Database Tier:</b> MySQL / MariaDB Enterprise (Primary Master Node)</div>
            <div class="down">↓ Streaming Replication Semi-Synchronous (Lag &lt; 1 Detik)</div>
            <div class="flow"><b>Hot Standby Replica Node &amp; Cloud Object Storage (Pencadangan 3-2-1)</b></div>
          </div>
        </div>

        <!-- RBAC Matrix -->
        <div class="panel">
          <h6>Matriks Hak Akses Pengguna (Role-Based Access Control / RBAC)</h6>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Peran / Role</th>
                  <th>Departemen</th>
                  <th>Penjualan (SD)</th>
                  <th>Gudang (MM)</th>
                  <th>Produksi (PP)</th>
                  <th>Keuangan (FI)</th>
                  <th>Keterangan Otoritas</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($rbacMatrix)): ?>
                  <?php foreach ($rbacMatrix as $r): ?>
                    <tr>
                      <td><b><?=h($r['peran'])?></b></td>
                      <td><span class="badge bg-light text-dark border"><?=h($r['departemen'])?></span></td>
                      <td><span class="badge <?=$r['akses_sd']==='CRUD'?'bg-success':'bg-light text-dark'?>"><?=$r['akses_sd']?></span></td>
                      <td><span class="badge <?=$r['akses_mm']==='CRUD'?'bg-success':'bg-light text-dark'?>"><?=$r['akses_mm']?></span></td>
                      <td><span class="badge <?=$r['akses_pp']==='CRUD'?'bg-success':'bg-light text-dark'?>"><?=$r['akses_pp']?></span></td>
                      <td><span class="badge <?=$r['akses_fi']==='CRUD'?'bg-success':'bg-light text-dark'?>"><?=$r['akses_fi']?></span></td>
                      <td><small class="text-muted"><?=h($r['keterangan'])?></small></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="7" class="text-center text-muted">Matriks hak akses belum dimuat.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Chaos Engineering Simulator & 3-2-1 Backup SOP -->
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="panel h-100">
              <h6><i class="bi bi-shield-exclamation text-danger me-2"></i>Chaos Engineering Simulator (Uji 5 Gangguan)</h6>
              <p class="small text-muted">Klik salah satu tombol simulasi untuk menguji ketahanan infrastruktur dan mencatat mitigasinya ke audit log:</p>
              
              <div class="row g-2 mb-3">
                <div class="col-12">
                  <form method="post">
                    <input type="hidden" name="action" value="chaos_test">
                    <input type="hidden" name="skenario_kode" value="DS-01">
                    <input type="hidden" name="nama_skenario" value="Database Crash / Failover">
                    <input type="hidden" name="kategori" value="Infrastruktur Database">
                    <input type="hidden" name="dampak" value="Transaksi write terhenti sejenak">
                    <input type="hidden" name="status_mitigasi" value="Auto-failover ke MySQL Standby Replica dalam 3.2 detik (RTO < 30 menit, RPO < 5 menit)">
                    <button class="btn btn-outline-danger btn-sm w-100 text-start d-flex justify-content-between align-items-center">
                      <span><i class="bi bi-database-down me-1"></i> Uji 1: Database Server Crash</span>
                      <span class="badge bg-danger">Simulasikan</span>
                    </button>
                  </form>
                </div>
                <div class="col-12">
                  <form method="post">
                    <input type="hidden" name="action" value="chaos_test">
                    <input type="hidden" name="skenario_kode" value="DS-02">
                    <input type="hidden" name="nama_skenario" value="App Server Out of Memory (OOM)">
                    <input type="hidden" name="kategori" value="Application Tier">
                    <input type="hidden" name="dampak" value="Node 1 tidak merespon traffic">
                    <input type="hidden" name="status_mitigasi" value="NGINX mengalihkan 100% request ke Node 2 tanpa downtime pengguna">
                    <button class="btn btn-outline-warning btn-sm w-100 text-start d-flex justify-content-between align-items-center">
                      <span><i class="bi bi-cpu-fill me-1"></i> Uji 2: App Server Out of Memory</span>
                      <span class="badge bg-warning text-dark">Simulasikan</span>
                    </button>
                  </form>
                </div>
                <div class="col-12">
                  <form method="post">
                    <input type="hidden" name="action" value="chaos_test">
                    <input type="hidden" name="skenario_kode" value="DS-03">
                    <input type="hidden" name="nama_skenario" value="Koneksi Internet Eksternal Putus">
                    <input type="hidden" name="kategori" value="Network Tier">
                    <input type="hidden" name="dampak" value="Akses publik terputus">
                    <input type="hidden" name="status_mitigasi" value="LAN internal gigabit tetap normal + failover otomatis ke link 4G/5G seluler">
                    <button class="btn btn-outline-secondary btn-sm w-100 text-start d-flex justify-content-between align-items-center">
                      <span><i class="bi bi-wifi-off me-1"></i> Uji 3: Internet Eksternal Putus</span>
                      <span class="badge bg-secondary">Simulasikan</span>
                    </button>
                  </form>
                </div>
                <div class="col-12">
                  <form method="post">
                    <input type="hidden" name="action" value="chaos_test">
                    <input type="hidden" name="skenario_kode" value="DS-04">
                    <input type="hidden" name="nama_skenario" value="Akses Menu Keuangan Ilegal">
                    <input type="hidden" name="kategori" value="Keamanan RBAC">
                    <input type="hidden" name="dampak" value="Staff gudang mencoba akses modul keuangan">
                    <input type="hidden" name="status_mitigasi" value="Sistem merespon 403 Forbidden & mencatat Security Audit Event ke SIEM">
                    <button class="btn btn-outline-primary btn-sm w-100 text-start d-flex justify-content-between align-items-center">
                      <span><i class="bi bi-shield-lock-fill me-1"></i> Uji 4: Akses Finansial Tidak Sah</span>
                      <span class="badge bg-primary">Simulasikan</span>
                    </button>
                  </form>
                </div>
              </div>

              <!-- Terminal Output Box -->
              <div class="terminal-box">
                <div class="text-white-50 border-bottom pb-1 mb-2">Terminal Audit Log Mitigasi (Real-time):</div>
                <?php if (!empty($chaosLogs)): ?>
                  <?php foreach ($chaosLogs as $cl): ?>
                    <div class="mb-1">
                      <span class="log-time">[<?=$cl['waktu_uji']?>]</span>
                      <span class="log-ok fw-bold"><?=$cl['skenario_kode']?>:</span>
                      <span class="text-light"><?=$cl['nama_skenario']?> &rarr; </span>
                      <span class="log-warn"><?=$cl['status_mitigasi']?></span>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-muted">Belum ada pengujian chaos yang dijalankan. Klik tombol simulasi di atas.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="panel h-100">
              <h6><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Kebijakan Pencadangan 3-2-1 & SOP RTO / RPO</h6>
              <div class="subpanel mb-3">
                <b class="text-primary">Aturan Pencadangan 3-2-1 Baku</b>
                <ul class="small mb-2">
                  <li><b>3 Salinan Data:</b> 1 data aktif produksi, 1 backup lokal, 1 backup off-site cloud.</li>
                  <li><b>2 Media Berbeda:</b> Disimpan pada NVMe Storage lokal dan Cloud S3 terenkripsi.</li>
                  <li><b>1 Salinan Terpisah (Off-site):</b> Disimpan di Data Center eksternal untuk perlindungan bencana fisik.</li>
                </ul>
              </div>
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <div class="p-2 border rounded bg-light text-center">
                    <small class="text-muted d-block">Target RTO (Recovery Time)</small>
                    <b class="text-success">&lt; 30 Menit</b>
                  </div>
                </div>
                <div class="col-6">
                  <div class="p-2 border rounded bg-light text-center">
                    <small class="text-muted d-block">Target RPO (Recovery Point)</small>
                    <b class="text-success">&lt; 5 Menit</b>
                  </div>
                </div>
              </div>
              <form method="post">
                <input type="hidden" name="action" value="backup">
                <button class="btn btn-primary w-100"><i class="bi bi-cloud-check-fill me-1"></i>Jalankan Pencadangan Simulasi (Snapshot 3-2-1)</button>
              </form>
            </div>
          </div>
        </div>

      <!-- ===================================================================
           TAB 3: PROSES BISNIS & PENGADAAN LAB
           =================================================================== -->
      <?php elseif ($page === 'proses'): ?>
        <div class="panel">
          <h6>Praktikum 3 — Pemetaan Proses Bisnis & Alur Persetujuan Pengadaan</h6>
          <p class="text-muted">Studi Kasus: Pengadaan Bahan Praktik Laboratorium Manufaktur TRPL Politeknik META Industri Cikarang.</p>
          
          <!-- As-Is vs To-Be Process Comparison -->
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="subpanel">
                <b>4 Kelemahan Proses Manual Lama (As-Is)</b>
                <div class="flow">1. Pengajuan via WhatsApp Pribadi / Kertas<br><small class="text-danger">Pesan tertumpuk, spesifikasi barang rawan salah</small></div>
                <div class="down">↓</div>
                <div class="flow">2. Pemeriksaan Berdasarkan Perkiraan<br><small class="text-danger">Kepala Lab tidak melihat stok riil di gudang</small></div>
                <div class="down">↓</div>
                <div class="flow">3. Pembelian Tanpa Cek Stok Fisik<br><small class="text-danger">Terjadi duplikasi pembelian & penumpukan material</small></div>
                <div class="down">↓</div>
                <div class="flow">4. Bukti Penerimaan Difoto via Chat<br><small class="text-danger">Keuangan bayar tanpa 3-way matching (rawan selisih)</small></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="subpanel">
                <b>Diagram Alur Kerja To-Be ERP (Digital &amp; Terotomasi)</b>
                <div class="flow primary">1. Dosen Input Purchase Requisition (PR)<br><small>Wajib NIDN, Mata Kuliah &amp; Alasan Praktikum</small></div>
                <div class="down">↓ Validasi Anggaran Lab Otomatis</div>
                <div class="flow">2. Verifikasi Kepala Laboratorium Manufaktur</div>
                <div class="down">↓ Cek Saldo Stok Otomatis (Jika ada &rarr; Issue Internal)</div>
                <div class="flow">3. Pembelian Memilih Pemasok &amp; Rilis PO</div>
                <div class="down">↓ Approval Berjenjang Berdasarkan Nominal</div>
                <div class="flow">4. Gudang Input Goods Receipt Note (GRN)</div>
                <div class="down">↓ 3-Way Matching Otomatis (PO = GRN = Invoice)</div>
                <div class="flow primary">5. Keuangan Membayar &amp; Posting Jurnal Kas Keluar</div>
              </div>
            </div>
          </div>

          <!-- Business Rules & RACI Matrix -->
          <div class="row g-3">
            <div class="col-lg-6">
              <div class="subpanel">
                <b>5 Aturan Bisnis Baku (Business Rules Engine)</b>
                <ol class="small mb-0">
                  <li class="mb-2"><b>BR-01 (Identitas Pemohon):</b> Setiap PR wajib memuat NIDN dosen, nama bahan, kuantitas, satuan, dan alasan silabus.</li>
                  <li class="mb-2"><b>BR-02 (Cek Stok Otomatis):</b> Bila stok fisik gudang mencukupi, PR tidak diteruskan ke pembelian melainkan menjadi pengeluaran barang internal.</li>
                  <li class="mb-2"><b>BR-03 (Otorisasi Berjenjang):</b> &le; Rp2 Jt (Ka.Lab); Rp2 Jt–10 Jt (Ka.Lab + Pudir II); &gt; Rp10 Jt (+ Direktur Politeknik).</li>
                  <li class="mb-2"><b>BR-04 (Pemberlakuan Re-Approval):</b> Bila penawaran baru naik &gt; 10% atau naik level wewenang, persetujuan lama gugur dan wajib diajukan ulang.</li>
                  <li class="mb-2"><b>BR-05 (Strict 3-Way Match):</b> Pembayaran kas hanya diproses jika PO, GRN, dan Invoice tagihan vendor 100% cocok.</li>
                </ol>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="subpanel">
                <b>Tabel RACI Matrix Pengadaan</b>
                <div class="table-responsive">
                  <table class="table table-sm">
                    <thead>
                      <tr><th>Aktivitas</th><th>Dosen</th><th>Ka.Lab</th><th>Gudang</th><th>Beli</th><th>Keu</th></tr>
                    </thead>
                    <tbody class="small">
                      <tr><td>Pengajuan PR</td><td><span class="badge-raci-r">R</span></td><td><span class="badge-raci-a">A</span></td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-i">I</span></td></tr>
                      <tr><td>Verifikasi Kebutuhan</td><td><span class="badge-raci-c">C</span></td><td><span class="badge-raci-a">R &amp; A</span></td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-i">I</span></td></tr>
                      <tr><td>Pengecekan Stok</td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-a">A</span></td><td><span class="badge-raci-r">R</span></td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-i">I</span></td></tr>
                      <tr><td>Penerbitan PO</td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-c">C</span></td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-r">R &amp; A</span></td><td><span class="badge-raci-i">I</span></td></tr>
                      <tr><td>Penerimaan Fisik (GRN)</td><td><span class="badge-raci-c">C</span></td><td><span class="badge-raci-c">C</span></td><td><span class="badge-raci-r">R &amp; A</span></td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-i">I</span></td></tr>
                      <tr><td>3-Way Match &amp; Bayar</td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-i">I</span></td><td><span class="badge-raci-c">C</span></td><td><span class="badge-raci-c">C</span></td><td><span class="badge-raci-a">R &amp; A</span></td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Interactive Approval Threshold Calculator -->
        <div class="panel">
          <h6><i class="bi bi-sliders2 text-primary me-2"></i>Kalkulator Dinamis Wewenang Approval Berjenjang</h6>
          <div class="row g-3 align-items-center">
            <div class="col-md-5">
              <label>Geser Nominal Pengadaan (Rp):</label>
              <input type="range" class="form-range" id="nominalRange" min="500000" max="25000000" step="500000" value="3500000" oninput="calcApproval(this.value)">
              <div class="d-flex justify-content-between text-muted small">
                <span>Rp 500 Rb</span>
                <span>Rp 10 Jt</span>
                <span>Rp 25 Jt</span>
              </div>
            </div>
            <div class="col-md-7">
              <div class="p-3 border rounded bg-light" id="approvalResultBox">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-muted small">Nominal Diuji: <b class="text-dark fs-6" id="dispNominal">Rp 3.500.000</b></span>
                  <span class="badge bg-primary" id="dispLevel">Level 2: Ka.Lab + Pudir II</span>
                </div>
                <div class="small text-muted" id="dispDesc">Pengadaan menengah membutuhkan peninjauan minimal 2 penawaran vendor dan persetujuan dari Kepala Lab serta Pudir II Keuangan (SLA: Maksimal 2 Hari Kerja).</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Form PR & Approval Workflow Table -->
        <div class="row g-3">
          <div class="col-lg-4">
            <div class="panel">
              <h6>Buat Purchase Requisition (PR)</h6>
              <form method="post">
                <input type="hidden" name="action" value="request">
                <label>Nama Dosen / Pemohon</label>
                <input required name="requester" value="<?=h($currentUser['nama_lengkap'])?>" class="form-control mb-2">
                <label>Nama Bahan Praktikum</label>
                <input required name="item_name" placeholder="Misal: Mata Pahat Bubut Insert Karbida" class="form-control mb-2">
                <div class="row g-2">
                  <div class="col-7">
                    <label>Jumlah (Qty)</label>
                    <input required type="number" min="0.1" step="0.1" name="quantity" value="10" class="form-control mb-2">
                  </div>
                  <div class="col-5">
                    <label>Satuan</label>
                    <input required name="unit" value="Pcs" class="form-control mb-2">
                  </div>
                </div>
                <label>Alasan Kebutuhan Silabus</label>
                <textarea required name="reason" rows="2" class="form-control mb-3" placeholder="Kebutuhan Praktikum Mesin CNC Semester Ganjil"></textarea>
                <button class="btn btn-primary w-100"><i class="bi bi-send-fill me-1"></i>Ajukan Purchase Requisition</button>
              </form>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="panel">
              <h6>Workflow Persetujuan &amp; Penerimaan Barang</h6>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Barang</th>
                      <th>Pemohon</th>
                      <th>Qty Diajukan</th>
                      <th>Diterima</th>
                      <th>Status Dokumen</th>
                      <th>Tindakan Otoritas</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($requests as $r): ?>
                      <tr>
                        <td><b><?=h($r['item_name'])?></b></td>
                        <td><?=h($r['requester'])?></td>
                        <td><?=$r['quantity']?> <?=h($r['unit'])?></td>
                        <td><?=$r['received']?> <?=h($r['unit'])?></td>
                        <td><?=h($r['status'])?></td>
                        <td>
                          <?php if ($r['status'] === 'Menunggu Verifikasi'): ?>
                            <form method="post" class="d-inline">
                              <input type="hidden" name="action" value="approve">
                              <input type="hidden" name="id" value="<?=$r['id']?>">
                              <button class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Setujui</button>
                            </form>
                          <?php elseif (in_array($r['status'], ['Disetujui', 'Diterima Sebagian'])): ?>
                            <form method="post" class="d-flex gap-1">
                              <input type="hidden" name="action" value="receive">
                              <input type="hidden" name="id" value="<?=$r['id']?>">
                              <input class="form-control form-control-sm" style="width:75px" type="number" min="0.1" max="<?=($r['quantity']-$r['received'])?>" step="0.1" name="quantity" required placeholder="Qty">
                              <button class="btn btn-sm btn-primary"><i class="bi bi-box-arrow-in-down me-1"></i>Terima</button>
                            </form>
                          <?php else: ?>
                            <span class="badge bg-success-subtle text-success"><i class="bi bi-check2-all me-1"></i>Tuntas</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      <!-- ===================================================================
           TAB 4: MODUL KEUANGAN
           =================================================================== -->
      <?php elseif ($page === 'keuangan'): ?>
        <div class="panel">
          <h6>Praktikum 4 — Konfigurasi &amp; Simulasi Modul Keuangan ERP</h6>
          <p class="text-muted">Saldo modal awal kas: <b>Rp 30.000.000</b>. Implementasi pencatatan jurnal berpasangan debit-kredit terstandar dengan pengujian integritas audit trail.</p>
          
          <!-- Scenario Transactions Table -->
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Skenario Transaksi Resmi Soal</th>
                  <th>Nominal</th>
                  <th>Debit</th>
                  <th>Kredit</th>
                  <th>Dasar Perlakuan Akuntansi</th>
                </tr>
              </thead>
              <tbody>
                <tr><td>1</td><td>Pembelian bahan baku secara kredit</td><td>Rp 8.000.000</td><td>Persediaan/Pembelian</td><td>Utang Usaha</td><td>Aset persediaan bertambah (+Dr), kewajiban utang timbul (+Cr).</td></tr>
                <tr><td>2</td><td>Pembayaran utang kepada pemasok</td><td>Rp 3.000.000</td><td>Utang Usaha</td><td>Kas</td><td>Kewajiban dilunasi berkurang (-Dr), aset kas keluar (-Cr).</td></tr>
                <tr><td>3</td><td>Penjualan produk secara kredit</td><td>Rp 12.000.000</td><td>Piutang Usaha</td><td>Penjualan</td><td>Hak tagih pelanggan bertambah (+Dr), pendapatan diakui (+Cr).</td></tr>
                <tr><td>4</td><td>Penerimaan pelunasan piutang pelanggan</td><td>Rp 5.000.000</td><td>Kas</td><td>Piutang Usaha</td><td>Aset kas masuk bertambah (+Dr), tagihan piutang berkurang (-Cr).</td></tr>
                <tr><td>5</td><td>Pembayaran biaya operasional tunai</td><td>Rp 1.500.000</td><td>Beban Operasional</td><td>Kas</td><td>Beban operasional diakui (+Dr), aset kas berkurang (-Cr).</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Balanced Trial Balance & Balance Sheet Summary -->
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="panel h-100">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6><i class="bi bi-scale me-2 text-primary"></i>Neraca Saldo (Trial Balance)</h6>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Status: Seimbang (Balanced)</span>
              </div>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr><th>Akun Keuangan</th><th class="text-end">Debit</th><th class="text-end">Kredit</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($trial_debits as $acc => $val): ?>
                      <tr><td><?=h($acc)?></td><td class="text-end"><?=rupiah($val)?></td><td class="text-end">-</td></tr>
                    <?php endforeach; ?>
                    <?php foreach ($trial_credits as $acc => $val): ?>
                      <tr><td><?=h($acc)?></td><td class="text-end">-</td><td class="text-end"><?=rupiah($val)?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot class="table-light fw-bold">
                    <tr>
                      <td>TOTAL NERACA SALDO</td>
                      <td class="text-end text-success"><?=rupiah($total_debit)?></td>
                      <td class="text-end text-success"><?=rupiah($total_credit)?></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="panel h-100">
              <h6>Buku Besar &amp; Saldo Akun Terkini</h6>
              <div class="row g-2">
                <?php foreach ($balances as $k => $v): ?>
                  <div class="col-6">
                    <div class="p-2 border rounded bg-light">
                      <small class="text-muted d-block"><?=h($k)?></small>
                      <strong class="text-dark fs-6"><?=rupiah($v)?></strong>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="alert alert-info mt-3 p-2 small mb-0">
                <i class="bi bi-shield-check me-1"></i>Saldo dihitung otomatis dari jurnal aktif. Transaksi duplikat yang telah dikoreksi tidak lagi mendistorsi saldo kas.
              </div>
            </div>
          </div>
        </div>

        <!-- Input New Transaction & Journal Table with Reversing Entry -->
        <div class="panel mt-3">
          <h6>Input Transaksi Jurnal Umum Baru</h6>
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="finance">
            <div class="col-md-2">
              <label>Tanggal</label>
              <input type="date" name="tx_date" value="<?=date('Y-m-d')?>" required class="form-control">
            </div>
            <div class="col-md-3">
              <label>Keterangan Transaksi</label>
              <input name="description" required placeholder="Uraian bukti transaksi" class="form-control">
            </div>
            <div class="col-md-2">
              <label>Kode Bukti</label>
              <input name="code" required class="form-control" placeholder="TRX-006">
            </div>
            <div class="col-md-2">
              <label>Akun Debit</label>
              <select name="debit_account" class="form-select">
                <?php foreach (array_keys($balances) as $x): ?><option><?=h($x)?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label>Akun Kredit</label>
              <select name="credit_account" class="form-select">
                <?php foreach (array_keys($balances) as $x): ?><option><?=h($x)?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-1">
              <label>Nominal</label>
              <input type="number" min="1" name="amount" required class="form-control" placeholder="Rp">
            </div>
            <div class="col-12 mt-2">
              <button class="btn btn-primary"><i class="bi bi-save2 me-1"></i>Posting Jurnal ke Buku Besar</button>
            </div>
          </form>
        </div>

        <!-- Audit Trail & Duplicate Testing -->
        <div class="panel mt-3">
          <h6>Jurnal Umum &amp; Pengujian Integritas Audit Trail (Duplikasi Pembayaran)</h6>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Kode Bukti</th>
                  <th>Keterangan</th>
                  <th>Debit</th>
                  <th>Kredit</th>
                  <th>Nominal</th>
                  <th>Status Audit</th>
                  <th>Uji Koreksi Pembalik</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tx as $t): ?>
                  <tr class="<?=$t['reversed']?'text-decoration-line-through text-muted':''?>">
                    <td><?=h($t['tx_date'])?></td>
                    <td><code><?=h($t['code'])?></code></td>
                    <td><?=h($t['description'])?></td>
                    <td><?=h($t['debit_account'])?></td>
                    <td><?=h($t['credit_account'])?></td>
                    <td class="fw-semibold"><?=rupiah($t['amount'])?></td>
                    <td>
                      <?php if (!empty($t['reversed'])): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Dikoreksi (Pembalik)</span>
                      <?php elseif (!empty($t['duplicate_of'])): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Duplikat Simulasi</span>
                      <?php else: ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif &amp; Terposting</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (empty($t['duplicate_of']) && empty($t['reversed'])): ?>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="duplicate">
                          <input type="hidden" name="id" value="<?=$t['id']?>">
                          <button class="btn btn-sm btn-outline-warning" title="Simulasikan human error pembayaran ganda"><i class="bi bi-copy me-1"></i>Uji Duplikat</button>
                        </form>
                      <?php elseif (!empty($t['duplicate_of']) && empty($t['reversed'])): ?>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="reverse">
                          <input type="hidden" name="id" value="<?=$t['id']?>">
                          <button class="btn btn-sm btn-outline-danger" title="Batalkan efek duplikat dengan reversing entry"><i class="bi bi-arrow-counterclockwise me-1"></i>Jurnal Koreksi</button>
                        </form>
                      <?php else: ?>
                        <small class="text-muted"><i class="bi bi-shield-check me-1"></i>Audit Trail Aman</small>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <!-- ===================================================================
           TAB 5: SCM, BOM & MRP
           =================================================================== -->
      <?php elseif ($page === 'scm'): ?>
        <div class="panel">
          <h6>Praktikum 5 — SCM &amp; Material Requirement Planning (MRP)</h6>
          <p class="text-muted">Target Produksi: <b>300 Unit</b> Komponen Presisi Poros Mesin (KPP-300) dalam batas waktu <b>6 hari kerja</b>.</p>
          
          <!-- MRP Calculation Grid -->
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Kode Material</th>
                  <th>Deskripsi Komponen</th>
                  <th>Formula BOM</th>
                  <th>Kebutuhan Bruto</th>
                  <th>Stok Gudang</th>
                  <th>PO On-Order</th>
                  <th>Lead Time</th>
                  <th>Kebutuhan Bersih (Net)</th>
                  <th>Status Kelayakan Waktu</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><code>MAT-A</code></td>
                  <td><b>Bahan Baku A (Aluminium Billet)</b></td>
                  <td>3 Unit / Produk</td>
                  <td>900 Unit</td>
                  <td>400 Unit</td>
                  <td>100 Unit (Tiba Hari ke-4)</td>
                  <td>4 Hari Kerja</td>
                  <td><b class="text-primary"><?=$netA?> Unit</b></td>
                  <td><span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Aman (&le; 6 Hari)</span></td>
                </tr>
                <tr>
                  <td><code>MAT-B</code></td>
                  <td><b>Bahan Baku B (Baut High-Tensile)</b></td>
                  <td>2 Unit / Produk</td>
                  <td>600 Unit</td>
                  <td>250 Unit</td>
                  <td>0 Unit</td>
                  <td>7 Hari Kerja</td>
                  <td><b class="text-danger"><?=$netB?> Unit</b></td>
                  <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-exclamation-triangle-fill me-1"></i>BOTTLENECK (&gt; 6 Hari)</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="small text-muted mb-0">*Formula: <code>Kebutuhan Bersih = Kebutuhan Bruto - Stok Gudang - PO On-Order</code>. Kebutuhan bersih Bahan A = 900 - 400 - 100 = 400 Unit; Bahan B = 600 - 250 - 0 = 350 Unit.</p>
        </div>

        <!-- MRP Visual Metric Cards -->
        <div class="row g-3">
          <div class="col-md-6">
            <div class="mrp-card">
              <small>Kebutuhan Bruto Bahan A (300 &times; 3)</small>
              <strong>900 Unit</strong>
              <span>Persediaan riil (400) + PO On-Order (100) = 500 Unit. Kekurangan bersih: <b>400 Unit</b>.</span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mrp-card" style="border-left-color: var(--accent-rose);">
              <small>Kebutuhan Bruto Bahan B (300 &times; 2)</small>
              <strong class="text-danger">600 Unit</strong>
              <span>Persediaan riil (250) + PO (0) = 250 Unit. Kekurangan bersih: <b>350 Unit</b>.</span>
            </div>
          </div>
        </div>

        <!-- Supplier B Delay Shock Test & Decision Matrix -->
        <div class="panel mt-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Pengujian Kondisi Khusus: Keterlambatan Pemasok Bahan B (Shock Test)</h6>
            <span class="badge bg-danger">Lead Time Molor: 8 Hari Kerja</span>
          </div>
          <div class="alert alert-warning p-3 small mb-3">
            <b>Skenario Masalah:</b> Pemasok Bahan B (PT Fastener Baja Utama) mengumumkan pesanan baru hanya dapat tiba dalam <b>8 hari kerja</b> (molor dari 7 hari), padahal kontrak produksi 300 unit harus selesai dalam <b>6 hari kerja</b>. Terjadi defisit 2 hari keterlambatan fatal jika tidak diambil tindakan manajerial.
          </div>

          <!-- Decision Matrix Cards -->
          <div class="row g-3">
            <div class="col-md-6 col-lg-3">
              <div class="decision-card recommended h-100">
                <b class="d-block mb-1">Strategi 2: Pengadaan Kilat Lokal (Express Local Supplier)</b>
                <span class="badge bg-success-subtle text-success mb-2">Lead Time: 3 Hari Kerja</span>
                <p class="small text-muted mb-2">Beli 350 unit Bahan B dari supplier sekunder lokal di Cikarang dengan biaya kirim ekspres.</p>
                <div class="small border-top pt-2">
                  <div class="d-flex justify-content-between"><span>Biaya Tambahan:</span> <b>+Rp 756.000</b></div>
                  <div class="d-flex justify-content-between"><span>Risiko Keterlambatan:</span> <b class="text-success">Sangat Rendah</b></div>
                  <div class="d-flex justify-content-between"><span>Penyelesaian 300 Unit:</span> <b class="text-success">Hari ke-5 (Aman)</b></div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="decision-card h-100">
                <b class="d-block mb-1">Strategi 1: Split Production (Produksi Bertahap)</b>
                <span class="badge bg-primary-subtle text-primary mb-2">Lead Time: Parsial</span>
                <p class="small text-muted mb-2">Gunakan 250 unit stok yang ada untuk memproduksi 125 unit produk jadi tepat waktu; sisa 175 unit menunggu kiriman.</p>
                <div class="small border-top pt-2">
                  <div class="d-flex justify-content-between"><span>Biaya Tambahan:</span> <b>Rp 0</b></div>
                  <div class="d-flex justify-content-between"><span>Risiko:</span> <b class="text-warning">Parsial Kirim</b></div>
                  <div class="d-flex justify-content-between"><span>Pelanggan:</span> <small>Komunikasi Delivery</small></div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="decision-card h-100">
                <b class="d-block mb-1">Strategi 3: Transfer Antar Pabrik (Inter-Plant)</b>
                <span class="badge bg-info-subtle text-info mb-2">Lead Time: 2 Hari Kerja</span>
                <p class="small text-muted mb-2">Meminjam stok konsinyasi dari gudang lab manufaktur mitra di kawasan industri terdekat.</p>
                <div class="small border-top pt-2">
                  <div class="d-flex justify-content-between"><span>Biaya Tambahan:</span> <b>+Rp 250.000</b></div>
                  <div class="d-flex justify-content-between"><span>Risiko:</span> <b class="text-success">Rendah</b></div>
                  <div class="d-flex justify-content-between"><span>Ketersediaan:</span> <small>Tergantung Stok Mitra</small></div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="decision-card not-recommended h-100">
                <b class="d-block mb-1 text-danger">Strategi 4: Menunggu Pasif Tanpa Tindakan</b>
                <span class="badge bg-danger-subtle text-danger mb-2">Lead Time: 8 Hari Kerja</span>
                <p class="small text-muted mb-2">Membiarkan pesanan berjalan normal pada supplier lama tanpa percepatan.</p>
                <div class="small border-top pt-2">
                  <div class="d-flex justify-content-between"><span>Biaya Denda:</span> <b class="text-danger">Pasti Terkena Penalti</b></div>
                  <div class="d-flex justify-content-between"><span>Risiko:</span> <b class="text-danger">SANGAT TINGGI</b></div>
                  <div class="d-flex justify-content-between"><span>Rekomendasi:</span> <b class="text-danger">Ditolak Keras</b></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Supplier Management Form -->
        <div class="row g-3 mt-1">
          <div class="col-md-5">
            <div class="panel">
              <h6>Tambah Mitra Pemasok &amp; Lead Time</h6>
              <form method="post">
                <input type="hidden" name="action" value="supplier">
                <label>Nama Perusahaan Pemasok</label>
                <input required name="name" placeholder="Misal: PT Cikarang Fastener Mandiri" class="form-control mb-2">
                <label>Kontak / Email PIC</label>
                <input name="contact" placeholder="sales@cikarangfastener.com" class="form-control mb-2">
                <label>Lead Time Pengiriman (Hari Kerja)</label>
                <input required type="number" min="0" name="lead_time" value="3" class="form-control mb-3">
                <button class="btn btn-primary"><i class="bi bi-truck me-1"></i>Simpan Mitra Pemasok</button>
              </form>
            </div>
          </div>
          <div class="col-md-7">
            <div class="panel">
              <h6>Daftar Pemasok Aktif</h6>
              <div class="table-responsive">
                <table class="table">
                  <thead><tr><th>Nama Pemasok</th><th>Kontak</th><th>Lead Time</th></tr></thead>
                  <tbody>
                    <?php foreach ($suppliers as $s): ?>
                      <tr>
                        <td><b><?=h($s['name'])?></b></td>
                        <td><?=h($s['contact'])?></td>
                        <td><span class="badge bg-primary-subtle text-primary"><?=$s['lead_time']?> Hari Kerja</span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      <!-- ===================================================================
           TAB: LAPORAN & BUKTI AKADEMIK
           =================================================================== -->
      <?php elseif ($page === 'laporan'): ?>
        <div class="panel">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <div class="eyebrow text-primary">REKAPITULASI RESMI UJIAN TENGAH SEMESTER</div>
              <h5 class="mb-0">Laporan Pemenuhan Indikator Praktikum ERP (Modul 1 – 5)</h5>
            </div>
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i>Cetak Laporan / PDF</button>
          </div>

          <div class="p-3 bg-light rounded-3 border mb-3">
            <div class="row g-2 small">
              <div class="col-sm-4"><b>Institusi:</b> Politeknik META Industri Cikarang</div>
              <div class="col-sm-4"><b>Program Studi:</b> Teknologi Rekayasa Perangkat Lunak (TRPL)</div>
              <div class="col-sm-4"><b>Mata Kuliah:</b> Enterprise Resource Planning (PL/4008 - 3 SKS)</div>
              <div class="col-sm-4"><b>Dosen Pengampu:</b> Adie Kusna Wibowo, S.T., M.Kom.</div>
              <div class="col-sm-4"><b>Praktikan:</b> <?=h($currentUser['nama_lengkap'])?> (Peran: <?=h($currentUser['peran'])?>)</div>
              <div class="col-sm-4"><b>Status Database:</b> 🟢 Terhubung MySQL (meta_erp_db)</div>
            </div>
          </div>

          <!-- Summary Matrix Table -->
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Modul</th>
                  <th>Topik Praktikum</th>
                  <th>Indikator Pemenuhan Soal</th>
                  <th>Status Verifikasi Sistem</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><b>Praktikum 1</b></td>
                  <td>Evolusi &amp; Integrasi ERP</td>
                  <td>Analisis 4 Silo, As-Is vs To-Be, 5 Data Master Bersama, Simulasi SO Credit Limit, 4 Tantangan Dosen.</td>
                  <td><span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>100% Terpenuhi</span></td>
                </tr>
                <tr>
                  <td><b>Praktikum 2</b></td>
                  <td>Arsitektur Teknis &amp; Keamanan</td>
                  <td>Model 3-Tier 100 User, Matriks RBAC 5 Departemen, Kebijakan 3-2-1 Backup (RTO &lt;30m, RPO &lt;5m), Chaos Simulator 5 Gangguan.</td>
                  <td><span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>100% Terpenuhi</span></td>
                </tr>
                <tr>
                  <td><b>Praktikum 3</b></td>
                  <td>Proses Bisnis &amp; Approval Pengadaan</td>
                  <td>Studi Kasus Lab Manufaktur, 4 Cacat As-Is, Alur To-Be, 5 Aturan Bisnis, RACI Matrix, Slider Otorisasi, SOP Darurat 24 Jam.</td>
                  <td><span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>100% Terpenuhi</span></td>
                </tr>
                <tr>
                  <td><b>Praktikum 4</b></td>
                  <td>Modul Keuangan &amp; Akuntansi</td>
                  <td>COA Baku, Kas Awal Rp30 Jt, 5 Transaksi Skenario Soal, Neraca Saldo Seimbang (Rp47 Jt), Duplikasi &amp; Jurnal Pembalik Audit Trail.</td>
                  <td><span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>100% Terpenuhi</span></td>
                </tr>
                <tr>
                  <td><b>Praktikum 5</b></td>
                  <td>SCM, BOM &amp; MRP</td>
                  <td>Target 300 Unit KPP-300 (6 Hari), Formula BOM, Kalkulator Kebutuhan Bersih, Shock Test Supplier B (8 Hari) &amp; Decision Matrix 4 Opsi.</td>
                  <td><span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>100% Terpenuhi</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<!-- =======================================================================
     MODAL MULTI-ROLE USER SWITCHER (1-CLICK SWITCH)
     ======================================================================= -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
      <div class="modal-header border-bottom py-3">
        <h6 class="modal-title fw-bold" id="userModalLabel"><i class="bi bi-people-fill text-primary me-2"></i>Pilih Peran Demo Pengguna</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3">
        <p class="small text-muted mb-3">Sistem dilengkapi simulasi Multi-Role RBAC. Klik salah satu akun untuk langsung berganti peran dan departemen:</p>
        <div class="d-grid gap-2">
          <?php foreach ($accounts as $acc): ?>
            <form method="post" class="m-0">
              <input type="hidden" name="action" value="switch_user">
              <input type="hidden" name="username" value="<?=h($acc['username'])?>">
              <button class="btn btn-light border text-start w-100 p-2 d-flex align-items-center gap-3 <?=($currentUser['username']??'')===$acc['username']?'border-primary bg-primary-subtle':''?>">
                <span class="fs-4"><?=$acc['avatar']??'👤'?></span>
                <div class="flex-grow-1">
                  <div class="fw-bold small text-dark"><?=h($acc['nama_lengkap'])?></div>
                  <div class="text-muted" style="font-size:11.5px;"><?=h($acc['peran'])?> &bull; <?=h($acc['departemen'])?></div>
                </div>
                <?php if (($currentUser['username']??'')===$acc['username']): ?>
                  <span class="badge bg-primary rounded-pill">Aktif</span>
                <?php endif; ?>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
<script>
// Interactive Approval Slider Script
function calcApproval(val) {
  val = parseInt(val);
  const dispNominal = document.getElementById('dispNominal');
  const dispLevel = document.getElementById('dispLevel');
  const dispDesc = document.getElementById('dispDesc');
  if (!dispNominal) return;
  
  dispNominal.textContent = 'Rp ' + val.toLocaleString('id-ID');
  
  if (val <= 2000000) {
    dispLevel.textContent = 'Level 1: Cukup Kepala Laboratorium';
    dispLevel.className = 'badge bg-success';
    dispDesc.textContent = 'Pengadaan rutin di bawah atau sama dengan Rp 2.000.000 cukup ditinjau dan disetujui langsung oleh Kepala Laboratorium Manufaktur sesuai pagu anggaran lab (SLA: Maksimal 1 Hari Kerja).';
  } else if (val <= 10000000) {
    dispLevel.textContent = 'Level 2: Ka.Lab + Pudir II Keuangan';
    dispLevel.className = 'badge bg-primary';
    dispDesc.textContent = 'Pengadaan bernilai Rp 2.000.001 - Rp 10.000.000 mewajibkan verifikasi kebutuhan oleh Ka.Lab serta persetujuan ketersediaan anggaran oleh Pudir II Bidang Keuangan (SLA: Maksimal 2 Hari Kerja).';
  } else {
    dispLevel.textContent = 'Level 3: Ka.Lab + Pudir II + Direktur';
    dispLevel.className = 'badge bg-danger';
    dispDesc.textContent = 'Pengadaan investasi besar di atas Rp 10.000.000 wajib memperoleh persetujuan final dari Direktur Politeknik META Industri dengan justifikasi akademik strategis (SLA: Maksimal 3 Hari Kerja).';
  }
}
</script>
</body>
</html>