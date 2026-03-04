<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title  = 'Aset IT';
$active_menu = 'aset_it';

// ── Helper: generate nomor inventaris berikutnya ─────────────────────────────
function generateNoInventaris(PDO $pdo): string {
    $tahun = date('Y');
    $last  = $pdo->query("SELECT no_inventaris FROM aset_it ORDER BY id DESC LIMIT 1")->fetchColumn();
    $seq   = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) $seq = (int)$m[1] + 1;
    return 'INV-IT-' . $tahun . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    if ($act === 'tambah' || $act === 'edit') {
        $auto_inv    = ($_POST['auto_inv'] ?? '0') === '1';
        $no_inv      = $auto_inv ? generateNoInventaris($pdo) : trim($_POST['no_inventaris'] ?? '');
        $nama        = trim($_POST['nama_aset']     ?? '');
        $kategori    = trim($_POST['kategori']      ?? '');
        $merek       = trim($_POST['merek']         ?? '');
        $model       = trim($_POST['model_aset']    ?? '');
        $serial      = trim($_POST['serial_number'] ?? '');
        $kondisi     = trim($_POST['kondisi']       ?? 'Baik');
        $status_pakai= trim($_POST['status_pakai']  ?? 'Terpakai');
        $bagian_id   = (int)($_POST['bagian_id']    ?? 0) ?: null;
        $pj_user_id  = (int)($_POST['pj_user_id']   ?? 0) ?: null;
        $tgl_beli    = $_POST['tanggal_beli']  ?: null;
        $harga       = strlen($_POST['harga_beli'] ?? '') ? (int)$_POST['harga_beli'] : null;
        $garansi     = $_POST['garansi_sampai'] ?: null;
        $keterangan  = trim($_POST['keterangan'] ?? '');

        // Simpan nama teks dari relasi (untuk display cepat tanpa JOIN)
        $lokasi_teks = '';
        if ($bagian_id) {
            $s = $pdo->prepare("SELECT nama FROM bagian WHERE id=?");
            $s->execute([$bagian_id]);
            $lokasi_teks = $s->fetchColumn() ?: '';
        }
        $pj_nama = '';
        if ($pj_user_id) {
            $s = $pdo->prepare("SELECT nama FROM users WHERE id=?");
            $s->execute([$pj_user_id]);
            $pj_nama = $s->fetchColumn() ?: '';
        }

        if ($act === 'tambah') {
            $pdo->prepare("INSERT INTO aset_it
                (no_inventaris,nama_aset,kategori,merek,model_aset,serial_number,kondisi,status_pakai,
                 bagian_id,lokasi,pj_user_id,penanggung_jawab,
                 tanggal_beli,harga_beli,garansi_sampai,keterangan,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$no_inv,$nama,$kategori,$merek,$model,$serial,$kondisi,$status_pakai,
                           $bagian_id,$lokasi_teks,$pj_user_id,$pj_nama,
                           $tgl_beli,$harga,$garansi,$keterangan,$_SESSION['user_id']]);
            setFlash('success','Aset <strong>'.htmlspecialchars($nama).'</strong> berhasil ditambahkan dengan nomor <code>'.$no_inv.'</code>.');
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE aset_it SET
                no_inventaris=?,nama_aset=?,kategori=?,merek=?,model_aset=?,serial_number=?,kondisi=?,status_pakai=?,
                bagian_id=?,lokasi=?,pj_user_id=?,penanggung_jawab=?,
                tanggal_beli=?,harga_beli=?,garansi_sampai=?,keterangan=?,updated_at=NOW()
                WHERE id=?")
                ->execute([$no_inv,$nama,$kategori,$merek,$model,$serial,$kondisi,$status_pakai,
                           $bagian_id,$lokasi_teks,$pj_user_id,$pj_nama,
                           $tgl_beli,$harga,$garansi,$keterangan,$id]);
            setFlash('success','Aset berhasil diperbarui.');
        }
        redirect(APP_URL.'/pages/aset_it.php');
    }

    if ($act === 'hapus' && hasRole('admin')) {
        $pdo->prepare("DELETE FROM aset_it WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success','Aset berhasil dihapus.');
        redirect(APP_URL.'/pages/aset_it.php');
    }
}

// ── AJAX: single row for edit ─────────────────────────────────────────────────
if (isset($_GET['get_aset'])) {
    $s = $pdo->prepare("SELECT * FROM aset_it WHERE id=?");
    $s->execute([(int)$_GET['get_aset']]);
    header('Content-Type: application/json');
    echo json_encode($s->fetch(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: preview auto no_inventaris ─────────────────────────────────────────
if (isset($_GET['preview_no_inv'])) {
    header('Content-Type: application/json');
    echo json_encode(['no' => generateNoInventaris($pdo)]);
    exit;
}

// ── Pagination & Filter ───────────────────────────────────────────────────────
$page        = max(1,(int)($_GET['page']         ?? 1));
$per_page    = 15;
$fk          = $_GET['kategori']     ?? '';
$fkondisi    = $_GET['kondisi']      ?? '';
$fstatus     = $_GET['status_pakai'] ?? '';
$search      = $_GET['q']            ?? '';

$where  = ['1=1']; $params = [];
if ($fk)       { $where[] = 'a.kategori=?';     $params[] = $fk; }
if ($fkondisi) { $where[] = 'a.kondisi=?';       $params[] = $fkondisi; }
if ($fstatus)  { $where[] = 'a.status_pakai=?';  $params[] = $fstatus; }
if ($search) {
    $where[]  = '(a.no_inventaris LIKE ? OR a.nama_aset LIKE ? OR a.merek LIKE ? OR b.nama LIKE ? OR u.nama LIKE ?)';
    $params   = array_merge($params, array_fill(0, 5, "%$search%"));
}
$wsql = implode(' AND ', $where);

$st_cnt = $pdo->prepare("SELECT COUNT(*) FROM aset_it a LEFT JOIN bagian b ON b.id=a.bagian_id LEFT JOIN users u ON u.id=a.pj_user_id WHERE $wsql");
$st_cnt->execute($params);
$total  = (int)$st_cnt->fetchColumn();
$pages  = max(1,ceil($total/$per_page));
$page   = min($page,$pages);
$offset = ($page-1)*$per_page;

$st_data = $pdo->prepare("
    SELECT a.*,
           b.nama AS bagian_nama, b.kode AS bagian_kode, b.lokasi AS bagian_lokasi,
           u.nama AS pj_nama_db,  u.divisi AS pj_divisi
    FROM aset_it a
    LEFT JOIN bagian b ON b.id  = a.bagian_id
    LEFT JOIN users  u ON u.id  = a.pj_user_id
    WHERE $wsql
    ORDER BY a.id DESC
    LIMIT $per_page OFFSET $offset");
$st_data->execute($params);
$asets = $st_data->fetchAll();

// Stats kondisi
$stats_kondisi = [];
foreach ($pdo->query("SELECT kondisi, COUNT(*) n FROM aset_it GROUP BY kondisi")->fetchAll() as $r)
    $stats_kondisi[$r['kondisi']] = $r['n'];
$total_all = array_sum($stats_kondisi);

// Stats status_pakai
$stats_pakai = [];
foreach ($pdo->query("SELECT status_pakai, COUNT(*) n FROM aset_it GROUP BY status_pakai")->fetchAll() as $r)
    $stats_pakai[$r['status_pakai']] = $r['n'];

// Dropdown data
$bagian_list = $pdo->query("SELECT id,nama,kode,lokasi FROM bagian WHERE status='aktif' ORDER BY urutan,nama")->fetchAll();
$users_list  = $pdo->query("SELECT id,nama,divisi,role FROM users WHERE status='aktif' ORDER BY nama")->fetchAll();
$kat_opts    = $pdo->query("SELECT DISTINCT kategori FROM aset_it WHERE kategori IS NOT NULL AND kategori!='' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>

<style>
/* ── Aset IT ────────────────────────────────────── */
.kondisi-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
.kb-baik     {background:#dcfce7;color:#166534;}
.kb-rusak    {background:#fee2e2;color:#991b1b;}
.kb-perbaikan{background:#fef9c3;color:#854d0e;}
.kb-tidak    {background:#f1f5f9;color:#64748b;}

/* ── Status Pakai badges ── */
.sp-terpakai  {background:#dbeafe;color:#1e40af;}
.sp-tidak     {background:#d1fae5;color:#065f46;}
.sp-dipinjam  {background:#fef3c7;color:#92400e;}

/* ── Row highlight untuk "Tidak Terpakai" ── */
tr.row-tidak-terpakai {
    background: linear-gradient(90deg, #f0fdf4 0%, #f7fffe 100%) !important;
    border-left: 4px solid #22c55e !important;
    position: relative;
}
tr.row-tidak-terpakai td {
    background: transparent !important;
    opacity: 0.82;
}
tr.row-tidak-terpakai td:first-child {
    border-left: 4px solid #22c55e;
}
tr.row-tidak-terpakai .nama-aset-txt {
    color: #6b7280 !important;
    text-decoration: line-through;
    text-decoration-color: #86efac;
}
tr.row-tidak-terpakai .inv-badge {
    background: linear-gradient(135deg,#f0fdf4,#dcfce7) !important;
    color: #15803d !important;
    border-color: #86efac !important;
    opacity: 0.8;
}

/* ── Row highlight untuk "Dipinjam" ── */
tr.row-dipinjam {
    background: linear-gradient(90deg, #fffbeb 0%, #fefce8 100%) !important;
    border-left: 4px solid #f59e0b !important;
}
tr.row-dipinjam td {
    background: transparent !important;
}
tr.row-dipinjam td:first-child {
    border-left: 4px solid #f59e0b;
}

.inv-badge{font-family:'Courier New',monospace;font-size:11px;font-weight:700;background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#1e40af;border:1px solid #bfdbfe;padding:2px 8px;border-radius:5px;white-space:nowrap;}

.stat-card-aset{background:#fff;border-radius:8px;border:1px solid #e8ecf0;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .2s;}
.stat-card-aset:hover{box-shadow:0 4px 12px rgba(0,0,0,.07);}
.stat-card-aset.active-filter{border-color:#26B99A;box-shadow:0 0 0 2px rgba(38,185,154,.2);}
.stat-icon-aset{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.grs-modal{height:1px;background:#f0f0f0;margin:12px 0;}

/* ── Status Pakai Info Banner ── */
.sp-legend-bar {
    display:flex;align-items:center;gap:16px;padding:9px 14px;
    background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;
    margin-bottom:14px;flex-wrap:wrap;
}
.sp-legend-item {display:flex;align-items:center;gap:6px;font-size:11.5px;color:#374151;}
.sp-legend-dot  {width:12px;height:12px;border-radius:3px;flex-shrink:0;}

/* ── Toggle Auto/Manual ── */
.inv-toggle-wrap{display:flex;align-items:center;gap:8px;padding:7px 11px;border-radius:6px;background:#f8fafc;border:1px solid #e2e8f0;cursor:pointer;user-select:none;transition:all .18s;flex-shrink:0;}
.inv-toggle-wrap:hover{border-color:#26B99A;background:#f0fdf9;}
.inv-sw{position:relative;width:34px;height:18px;flex-shrink:0;}
.inv-sw input{opacity:0;width:0;height:0;}
.inv-sl{position:absolute;inset:0;background:#cbd5e1;border-radius:34px;transition:.2s;cursor:pointer;}
.inv-sl:before{content:'';position:absolute;width:12px;height:12px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
.inv-sw input:checked~.inv-sl{background:#26B99A;}
.inv-sw input:checked~.inv-sl:before{transform:translateX(16px);}

/* ── Form inputs ── */
.f-label{font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px;}
.f-inp{width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:6px;font-size:12.5px;box-sizing:border-box;font-family:inherit;transition:border .18s;}
.f-inp:focus{outline:none;border-color:#26B99A;box-shadow:0 0 0 3px rgba(38,185,154,.12);}
.f-inp:disabled{background:#f8fafc;color:#94a3b8;cursor:not-allowed;}

/* ── Status pakai select highlight ── */
#f-status_pakai option[value="Tidak Terpakai"] { color: #065f46; background: #d1fae5; }
#f-status_pakai option[value="Dipinjam"]       { color: #92400e; background: #fef3c7; }
#f-status_pakai option[value="Terpakai"]       { color: #1e40af; background: #dbeafe; }
#f-status_pakai.val-tidak { border-color: #22c55e; background: #f0fdf4; color: #065f46; }
#f-status_pakai.val-dipinjam { border-color: #f59e0b; background: #fffbeb; color: #92400e; }
#f-status_pakai.val-terpakai { border-color: #3b82f6; background: #eff6ff; color: #1e40af; }

/* ── Dropdown Cetak ── */
.cetak-drop-wrap{position:relative;display:inline-block;}
.cetak-drop-menu{display:none;position:absolute;right:0;top:36px;z-index:9999;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 8px 28px rgba(0,0,0,.13);min-width:238px;overflow:hidden;}
.cetak-drop-menu.open{display:block;}
.cdm-head{padding:8px 13px;background:#f8fafc;border-bottom:1px solid #e2e8f0;}
.cdm-head-txt{font-size:11px;font-weight:700;color:#374151;}
.cdm-section{padding:6px 13px 4px;font-size:9.5px;font-weight:700;color:#94a3b8;letter-spacing:1.2px;text-transform:uppercase;border-bottom:1px solid #f1f5f9;}
.cdm-item{display:flex;align-items:center;gap:10px;padding:9px 13px;text-decoration:none;color:#1e293b;border-bottom:1px solid #f1f5f9;transition:background .14s;cursor:pointer;}
.cdm-item:hover{background:#f0fdf9;}
.cdm-item:last-of-type{border-bottom:none;}
.cdm-icon{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;}
.cdm-item-sm{display:flex;align-items:center;gap:9px;padding:7px 13px;text-decoration:none;color:#1e293b;border-bottom:1px solid #f1f5f9;font-size:12px;transition:background .14s;}
.cdm-item-sm:hover{background:#f0fdf9;}
.cdm-foot{padding:6px 13px;background:#f8fafc;border-top:1px solid #e2e8f0;}
.cdm-foot-txt{font-size:10px;color:#94a3b8;}

/* ── Filter tab status pakai ── */
.sp-filter-tabs{display:flex;gap:5px;margin-bottom:10px;flex-wrap:wrap;}
.sp-tab{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:11.5px;font-weight:600;text-decoration:none;border:1.5px solid transparent;transition:all .16s;cursor:pointer;}
.sp-tab-all   {background:#f1f5f9;color:#475569;border-color:#e2e8f0;}
.sp-tab-all.active   {background:#1e293b;color:#fff;border-color:#1e293b;}
.sp-tab-terpakai{background:#eff6ff;color:#1e40af;border-color:#bfdbfe;}
.sp-tab-terpakai.active{background:#1e40af;color:#fff;}
.sp-tab-tidak {background:#f0fdf4;color:#065f46;border-color:#86efac;}
.sp-tab-tidak.active {background:#16a34a;color:#fff;}
.sp-tab-dipinjam{background:#fffbeb;color:#92400e;border-color:#fde68a;}
.sp-tab-dipinjam.active{background:#d97706;color:#fff;}
</style>

<div class="page-header">
  <h4><i class="fa fa-server text-primary"></i> &nbsp;Aset IT</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span><span class="cur">Aset IT</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Stats Kondisi -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:10px;">
    <div class="stat-card-aset">
      <div class="stat-icon-aset" style="background:#eff6ff;"><i class="fa fa-boxes-stacked" style="color:#3b82f6;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $total_all ?></div><div style="font-size:11px;color:#94a3b8;">Total Aset</div></div>
    </div>
    <?php foreach([['Baik','#dcfce7','#22c55e','fa-circle-check'],['Rusak','#fee2e2','#ef4444','fa-circle-xmark'],['Dalam Perbaikan','#fef9c3','#f59e0b','fa-wrench'],['Tidak Aktif','#f1f5f9','#94a3b8','fa-ban']] as [$k,$bg,$ic,$ico]): ?>
    <div class="stat-card-aset">
      <div class="stat-icon-aset" style="background:<?= $bg ?>;"><i class="fa <?= $ico ?>" style="color:<?= $ic ?>;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stats_kondisi[$k]??0 ?></div><div style="font-size:11px;color:#94a3b8;"><?= $k ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Stats Status Pemakaian -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;">
    <?php foreach([
      ['Terpakai',      '#dbeafe','#1d4ed8','fa-circle-dot',     'Sedang digunakan'],
      ['Tidak Terpakai','#d1fae5','#065f46','fa-circle',         'Belum/tidak digunakan'],
      ['Dipinjam',      '#fef3c7','#92400e','fa-hand-holding',   'Sedang dipinjam'],
    ] as [$k,$bg,$ic,$ico,$sub]):
      $isActive = ($fstatus === $k);
    ?>
    <a href="?status_pakai=<?= urlencode($isActive ? '' : $k) ?>&kondisi=<?= urlencode($fkondisi) ?>&q=<?= urlencode($search) ?>"
       class="stat-card-aset <?= $isActive?'active-filter':'' ?>" style="text-decoration:none;cursor:pointer;">
      <div class="stat-icon-aset" style="background:<?= $bg ?>;"><i class="fa <?= $ico ?>" style="color:<?= $ic ?>;"></i></div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stats_pakai[$k]??0 ?></div>
        <div style="font-size:11px;color:#94a3b8;"><?= $k ?></div>
        <div style="font-size:10px;color:#cbd5e1;margin-top:1px;"><?= $sub ?></div>
      </div>
      <?php if($isActive): ?><i class="fa fa-check-circle" style="margin-left:auto;color:#26B99A;font-size:14px;"></i><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Legend kode warna baris -->
  <div class="sp-legend-bar">
    <span style="font-size:11px;font-weight:700;color:#374151;margin-right:4px;"><i class="fa fa-palette" style="color:#26B99A;"></i> Keterangan warna baris:</span>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#dbeafe;border:1.5px solid #93c5fd;"></div> Baris normal = <strong>Terpakai</strong></div>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#d1fae5;border:1.5px solid #22c55e;"></div> Baris hijau + <s>coret</s> = <strong>Tidak Terpakai</strong></div>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#fef3c7;border:1.5px solid #f59e0b;"></div> Baris kuning = <strong>Dipinjam</strong></div>
  </div>

  <!-- Quick filter kondisi -->
  <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
    <?php foreach([''=> 'Semua','Baik'=>'Baik','Rusak'=>'Rusak','Dalam Perbaikan'=>'Dalam Perbaikan','Tidak Aktif'=>'Tidak Aktif'] as $v=>$l):
      $cnt=$v===''?$total_all:($stats_kondisi[$v]??0); ?>
    <a href="?kondisi=<?= urlencode($v) ?>&status_pakai=<?= urlencode($fstatus) ?>" class="btn <?= $fkondisi===$v?'btn-primary':'btn-default' ?>" style="font-size:12px;">
      <?= $l ?> <span style="background:<?= $fkondisi===$v?'rgba(255,255,255,.3)':'#ddd' ?>;border-radius:9px;padding:0 6px;font-size:10px;"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <!-- Toolbar -->
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf-aset" style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">
          <?php if ($fkondisi): ?><input type="hidden" name="kondisi" value="<?= clean($fkondisi) ?>"><?php endif; ?>
          <?php if ($fstatus):  ?><input type="hidden" name="status_pakai" value="<?= clean($fstatus) ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search" placeholder="Cari nama, no. inv, bagian…" onchange="document.getElementById('sf-aset').submit()">
          <select name="kategori" class="sel-filter" onchange="document.getElementById('sf-aset').submit()">
            <option value="">Semua Kategori</option>
            <?php foreach($kat_opts as $k): ?>
            <option value="<?= clean($k) ?>" <?= $fk===$k?'selected':'' ?>><?= clean($k) ?></option>
            <?php endforeach; ?>
          </select>
          <!-- Filter Status Pakai inline -->
          <select name="status_pakai" class="sel-filter" onchange="document.getElementById('sf-aset').submit()" style="border-color:<?= $fstatus?'#26B99A':'#d1d5db' ?>;">
            <option value="">Semua Status</option>
            <option value="Terpakai"       <?= $fstatus==='Terpakai'       ?'selected':'' ?>>🔵 Terpakai</option>
            <option value="Tidak Terpakai" <?= $fstatus==='Tidak Terpakai' ?'selected':'' ?>>🟢 Tidak Terpakai</option>
            <option value="Dipinjam"       <?= $fstatus==='Dipinjam'       ?'selected':'' ?>>🟡 Dipinjam</option>
          </select>
          <?php if($search||$fk||$fstatus): ?><a href="?kondisi=<?= urlencode($fkondisi) ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Reset</a><?php endif; ?>
        </form>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="tbl-info"><?= $total ?> aset<?php if($fstatus): ?> <span style="background:#d1fae5;color:#065f46;padding:1px 7px;border-radius:9px;font-size:10px;font-weight:700;"><?= clean($fstatus) ?></span><?php endif; ?></span>

        <!-- ══ DROPDOWN CETAK LAPORAN ══ -->
        <div class="cetak-drop-wrap" id="wrap-cetak">
          <button type="button" id="btn-cetak" onclick="toggleCetakDrop(event)"
            class="btn btn-default btn-sm"
            style="border-color:#26B99A;color:#0f766e;font-weight:600;">
            <i class="fa fa-print"></i> Cetak
            <i class="fa fa-chevron-down" style="font-size:9px;margin-left:3px;"></i>
          </button>
          <div class="cetak-drop-menu" id="cetak-drop">
            <div class="cdm-head">
              <div class="cdm-head-txt"><i class="fa fa-file-pdf" style="color:#ef4444;margin-right:5px;"></i> Pilih Jenis Laporan PDF</div>
            </div>
            <a href="<?= APP_URL ?>/pages/cetak_aset_it.php?mode=semua" target="_blank" class="cdm-item">
              <div class="cdm-icon" style="background:#eff6ff;"><i class="fa fa-boxes-stacked" style="color:#1d4ed8;"></i></div>
              <div><div style="font-size:12.5px;font-weight:700;color:#1e293b;">Semua Aset IT</div><div style="font-size:10.5px;color:#94a3b8;">Laporan lengkap seluruh kategori</div></div>
              <i class="fa fa-arrow-up-right-from-square" style="margin-left:auto;color:#cbd5e1;font-size:10px;"></i>
            </a>
            <!-- Tidak Terpakai -->
            <a href="<?= APP_URL ?>/pages/cetak_aset_it.php?mode=semua&status_pakai=Tidak+Terpakai" target="_blank" class="cdm-item">
              <div class="cdm-icon" style="background:#d1fae5;"><i class="fa fa-circle" style="color:#16a34a;"></i></div>
              <div><div style="font-size:12.5px;font-weight:700;color:#1e293b;">Aset Tidak Terpakai</div><div style="font-size:10.5px;color:#94a3b8;">Daftar aset yang belum digunakan</div></div>
              <i class="fa fa-arrow-up-right-from-square" style="margin-left:auto;color:#cbd5e1;font-size:10px;"></i>
            </a>
            <?php if (!empty($kat_opts)): ?>
            <div class="cdm-section">Per Kategori</div>
            <?php
            $kat_icons = [
                'Laptop'=>['fa-laptop','#6366f1'],'Desktop'=>['fa-desktop','#0891b2'],
                'Printer'=>['fa-print','#d97706'],'Scanner'=>['fa-scanner','#059669'],
                'Server'=>['fa-server','#7c3aed'],'Switch'=>['fa-network-wired','#0f766e'],
                'Router'=>['fa-router','#b45309'],'Access Point'=>['fa-wifi','#16a34a'],
                'Monitor'=>['fa-display','#2563eb'],'Keyboard'=>['fa-keyboard','#475569'],
                'Mouse'=>['fa-computer-mouse','#475569'],'UPS'=>['fa-battery-full','#ca8a04'],
                'Proyektor'=>['fa-projector','#9333ea'],'Kamera IP'=>['fa-camera','#dc2626'],
                'Telepon'=>['fa-phone','#0284c7'],'Tablet'=>['fa-tablet','#7c3aed'],
            ];
            foreach ($kat_opts as $k):
                [$ico, $col] = $kat_icons[$k] ?? ['fa-tag', '#26B99A'];
            ?>
            <a href="<?= APP_URL ?>/pages/cetak_aset_it.php?mode=kategori&kategori=<?= urlencode($k) ?>" target="_blank" class="cdm-item-sm">
              <div class="cdm-icon" style="background:<?= $col ?>18;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa <?= $ico ?>" style="color:<?= $col ?>;font-size:11px;"></i>
              </div>
              <span style="font-weight:600;"><?= clean($k) ?></span>
              <i class="fa fa-arrow-up-right-from-square" style="color:#e2e8f0;font-size:9px;margin-left:auto;"></i>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
            <div class="cdm-section" style="margin-top:2px;">Filter Kondisi</div>
            <?php
            $kond_conf = [
                'Baik'=>['fa-circle-check','#16a34a','#dcfce7'],
                'Rusak'=>['fa-circle-xmark','#dc2626','#fee2e2'],
                'Dalam Perbaikan'=>['fa-wrench','#d97706','#fef9c3'],
                'Tidak Aktif'=>['fa-ban','#64748b','#f1f5f9'],
            ];
            foreach ($kond_conf as $kond => [$kico, $kcol, $kbg]): ?>
            <a href="<?= APP_URL ?>/pages/cetak_aset_it.php?mode=semua&kondisi=<?= urlencode($kond) ?>" target="_blank" class="cdm-item-sm">
              <div style="width:22px;height:22px;border-radius:50%;background:<?= $kbg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa <?= $kico ?>" style="color:<?= $kcol ?>;font-size:10px;"></i>
              </div>
              <span>Kondisi: <strong><?= $kond ?></strong></span>
              <span style="margin-left:auto;font-size:10.5px;background:<?= $kbg ?>;color:<?= $kcol ?>;padding:1px 7px;border-radius:9px;font-weight:700;"><?= $stats_kondisi[$kond] ?? 0 ?></span>
            </a>
            <?php endforeach; ?>
            <div class="cdm-foot"><div class="cdm-foot-txt"><i class="fa fa-circle-info" style="color:#26B99A;"></i> Laporan terbuka di tab baru sebagai PDF</div></div>
          </div>
        </div>
        <!-- ══ END DROPDOWN CETAK ══ -->

        <?php if(hasRole(['admin','teknisi'])): ?>
        <button onclick="bukaModalTambah()" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Tambah Aset</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Table -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:35px;">#</th>
            <th>No. Inventaris</th>
            <th>Nama Aset</th>
            <th>Kategori</th>
            <th>Merek / Model</th>
            <th>Serial Number</th>
            <th>Lokasi / Bagian</th>
            <th>Penanggung Jawab</th>
            <th>Status Pakai</th>
            <th>Kondisi</th>
            <th>Tgl Beli</th>
            <th>Garansi s/d</th>
            <th style="width:90px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($asets)): ?>
          <tr><td colspan="13" class="td-empty"><i class="fa fa-server"></i> Tidak ada data aset</td></tr>
          <?php else: $no=$offset+1; foreach($asets as $a):
            $g_exp  = $a['garansi_sampai'] && strtotime($a['garansi_sampai'])<time();
            $g_soon = $a['garansi_sampai'] && !$g_exp && strtotime($a['garansi_sampai'])<strtotime('+30 days');
            $kmap   = ['Baik'=>'kb-baik','Rusak'=>'kb-rusak','Dalam Perbaikan'=>'kb-perbaikan','Tidak Aktif'=>'kb-tidak'];
            $kico   = ['Baik'=>'fa-circle-check','Rusak'=>'fa-circle-xmark','Dalam Perbaikan'=>'fa-wrench','Tidak Aktif'=>'fa-ban'];
            $kc     = $kmap[$a['kondisi']] ?? 'kb-tidak';
            $ki     = $kico[$a['kondisi']] ?? 'fa-circle';

            // Status Pakai
            $sp     = $a['status_pakai'] ?? 'Terpakai';
            $spmap  = ['Terpakai'=>'sp-terpakai','Tidak Terpakai'=>'sp-tidak','Dipinjam'=>'sp-dipinjam'];
            $spico  = ['Terpakai'=>'fa-circle-dot','Tidak Terpakai'=>'fa-circle','Dipinjam'=>'fa-hand-holding'];
            $spc    = $spmap[$sp] ?? 'sp-terpakai';
            $spi    = $spico[$sp] ?? 'fa-circle-dot';

            // Row CSS class
            $row_class = '';
            if ($sp === 'Tidak Terpakai') $row_class = 'row-tidak-terpakai';
            elseif ($sp === 'Dipinjam')   $row_class = 'row-dipinjam';

            $lokasi_disp = $a['bagian_nama']
                ? ($a['bagian_kode']?'['.clean($a['bagian_kode']).'] ':'').clean($a['bagian_nama'])
                : clean($a['lokasi']?:'—');
            $pj_disp = $a['pj_nama_db'] ?: clean($a['penanggung_jawab']?:'—');
            $pj_init = $a['pj_nama_db'] ? getInitials($a['pj_nama_db']) : ($a['penanggung_jawab']?getInitials($a['penanggung_jawab']):'?');
          ?>
          <tr class="<?= $row_class ?>">
            <td style="color:#bbb;"><?= $no++ ?></td>
            <td><span class="inv-badge"><?= clean($a['no_inventaris']) ?></span></td>
            <td>
              <div class="nama-aset-txt" style="font-weight:600;color:#1e293b;font-size:13px;"><?= clean($a['nama_aset']) ?></div>
              <?php if($a['keterangan']): ?><small style="color:#aaa;font-size:10.5px;"><?= mb_strimwidth(clean($a['keterangan']),0,40,'…') ?></small><?php endif; ?>
              <?php if($sp === 'Tidak Terpakai'): ?>
              <div style="margin-top:2px;"><span style="font-size:9.5px;background:#d1fae5;color:#065f46;padding:1px 6px;border-radius:3px;font-weight:700;letter-spacing:.3px;"><i class="fa fa-circle" style="font-size:7px;"></i> TIDAK TERPAKAI</span></div>
              <?php elseif($sp === 'Dipinjam'): ?>
              <div style="margin-top:2px;"><span style="font-size:9.5px;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:3px;font-weight:700;letter-spacing:.3px;"><i class="fa fa-hand-holding" style="font-size:7px;"></i> DIPINJAM</span></div>
              <?php endif; ?>
            </td>
            <td style="font-size:11px;"><?= clean($a['kategori']?:'—') ?></td>
            <td style="font-size:12px;"><span style="font-weight:600;"><?= clean($a['merek']?:'—') ?></span><?php if($a['model_aset']): ?><br><small style="color:#aaa;"><?= clean($a['model_aset']) ?></small><?php endif; ?></td>
            <td style="font-family:monospace;font-size:11px;color:#64748b;"><?= clean($a['serial_number']?:'—') ?></td>
            <td style="font-size:12px;">
              <div><i class="fa fa-building" style="color:#94a3b8;font-size:10px;"></i> <?= $lokasi_disp ?></div>
              <?php if($a['bagian_lokasi']): ?><small style="color:#aaa;font-size:10px;"><i class="fa fa-location-dot" style="font-size:9px;"></i> <?= clean($a['bagian_lokasi']) ?></small><?php endif; ?>
            </td>
            <td>
              <?php if($pj_disp!=='—'): ?>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs"><?= $pj_init ?></div>
                <div>
                  <div style="font-size:12px;"><?= $pj_disp ?></div>
                  <?php if($a['pj_divisi']): ?><small style="color:#aaa;font-size:10px;"><?= clean($a['pj_divisi']) ?></small><?php endif; ?>
                </div>
              </div>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <!-- STATUS PAKAI COLUMN -->
            <td>
              <span class="kondisi-badge <?= $spc ?>">
                <i class="fa <?= $spi ?>"></i> <?= clean($sp) ?>
              </span>
            </td>
            <td><span class="kondisi-badge <?= $kc ?>"><i class="fa <?= $ki ?>"></i> <?= clean($a['kondisi']) ?></span></td>
            <td style="font-size:11px;color:#94a3b8;white-space:nowrap;">
              <?= $a['tanggal_beli']?date('d M Y',strtotime($a['tanggal_beli'])):'—' ?>
              <?php if($a['harga_beli']): ?><br><small style="color:#10b981;font-weight:600;">Rp <?= number_format($a['harga_beli'],0,',','.') ?></small><?php endif; ?>
            </td>
            <td style="font-size:11px;white-space:nowrap;">
              <?php if(!$a['garansi_sampai']): ?><span style="color:#cbd5e1;">—</span>
              <?php elseif($g_exp): ?><span style="color:#ef4444;font-weight:700;"><i class="fa fa-triangle-exclamation"></i> Expired</span><br><small style="color:#f87171;"><?= date('d M Y',strtotime($a['garansi_sampai'])) ?></small>
              <?php elseif($g_soon): ?><span style="color:#f59e0b;font-weight:700;"><i class="fa fa-clock"></i> Segera</span><br><small style="color:#fbbf24;"><?= date('d M Y',strtotime($a['garansi_sampai'])) ?></small>
              <?php else: ?><span style="color:#22c55e;"><?= date('d M Y',strtotime($a['garansi_sampai'])) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:4px;">
                <button onclick="editAset(<?= $a['id'] ?>)" class="btn btn-warning btn-sm" title="Edit"><i class="fa fa-pen"></i></button>
                <?php if(hasRole('admin')): ?>
                <button onclick="hapusAset(<?= $a['id'] ?>,'<?= addslashes(clean($a['nama_aset'])) ?>')" class="btn btn-danger btn-sm" title="Hapus"><i class="fa fa-trash"></i></button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="tbl-footer">
      <span class="tbl-info">Menampilkan <?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?></span>
      <?php if($pages>1): ?>
      <div class="pagination">
        <?php if($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pag-btn"><i class="fa fa-chevron-left"></i></a><?php endif; ?>
        <?php for($i=1;$i<=$pages;$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if($page<$pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pag-btn"><i class="fa fa-chevron-right"></i></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>


<!-- ════════════════════════════════════════════════════
     MODAL TAMBAH / EDIT
════════════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-tambah-aset" style="align-items:flex-start;justify-content:center;padding-top:30px;">
  <div style="background:#fff;width:100%;max-width:720px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:15px 20px;background:linear-gradient(135deg,#1a2e3f,#2a3f54);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:32px;height:32px;background:rgba(38,185,154,.25);border:1px solid rgba(38,185,154,.5);border-radius:7px;display:flex;align-items:center;justify-content:center;">
          <i id="modal-icon" class="fa fa-plus" style="color:#26B99A;font-size:13px;"></i>
        </div>
        <div>
          <div id="modal-title" style="color:#fff;font-size:14px;font-weight:700;">Tambah Aset IT Baru</div>
          <div style="color:rgba(255,255,255,.4);font-size:10.5px;">Isi data aset dengan lengkap</div>
        </div>
      </div>
      <button onclick="tutupModal()" style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:13px;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='#ef4444';this.style.color='#fff';" onmouseout="this.style.background='rgba(255,255,255,.1)';this.style.color='#ccc';"><i class="fa fa-times"></i></button>
    </div>

    <!-- Form Body -->
    <form method="POST" action="<?= APP_URL ?>/pages/aset_it.php" id="form-aset">
      <input type="hidden" name="_action"  id="f-action"   value="tambah">
      <input type="hidden" name="id"       id="f-id"       value="">
      <input type="hidden" name="auto_inv" id="f-auto-inv" value="1">

      <div style="padding:18px 20px;max-height:72vh;overflow-y:auto;">

        <!-- ① No. Inventaris + Toggle Auto/Manual -->
        <div style="margin-bottom:14px;">
          <label class="f-label">No. Inventaris <span style="color:#ef4444;">*</span></label>
          <div style="display:flex;align-items:center;gap:9px;">
            <div style="flex:1;position:relative;">
              <input type="text" name="no_inventaris" id="f-no_inventaris" class="f-inp"
                placeholder="Akan digenerate otomatis…"
                style="font-family:'Courier New',monospace;padding-right:36px;" disabled>
              <i id="inv-icon" class="fa fa-lock" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;pointer-events:none;"></i>
            </div>
            <label class="inv-toggle-wrap" title="Aktifkan untuk input manual">
              <label class="inv-sw" style="margin:0;">
                <input type="checkbox" id="toggle-manual-inv" onchange="toggleInv(this)" style="position:absolute;opacity:0;width:0;height:0;">
                <span class="inv-sl"></span>
              </label>
              <div>
                <div style="font-size:11.5px;font-weight:700;color:#374151;" id="inv-toggle-label">Auto</div>
                <div style="font-size:10px;color:#94a3b8;margin-top:1px;" id="inv-toggle-sub">Klik untuk manual</div>
              </div>
            </label>
          </div>
          <div id="inv-preview" style="margin-top:5px;font-size:10.5px;color:#64748b;">
            <i class="fa fa-circle-info" style="color:#26B99A;"></i>
            Nomor akan digenerate: <span id="inv-preview-val" style="font-family:monospace;color:#1e40af;font-weight:700;">memuat…</span>
          </div>
        </div>

        <!-- ② Nama Aset -->
        <div style="margin-bottom:12px;">
          <label class="f-label">Nama Aset <span style="color:#ef4444;">*</span></label>
          <input type="text" name="nama_aset" id="f-nama_aset" required class="f-inp" placeholder="Contoh: Laptop Dell Latitude">
        </div>

        <!-- ③ Kategori + Kondisi -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Kategori</label>
            <input type="text" name="kategori" id="f-kategori" class="f-inp" placeholder="Laptop, Printer, Switch…" list="kat-dl">
            <datalist id="kat-dl">
              <?php foreach(['Laptop','Desktop','Printer','Scanner','Server','Switch','Router','Access Point','Monitor','Keyboard','Mouse','UPS','Proyektor','Kamera IP','Telepon','Tablet','Hardisk Eksternal','Flash Drive','Kabel Network','Lainnya'] as $k): ?>
              <option value="<?= $k ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="f-label">Kondisi</label>
            <select name="kondisi" id="f-kondisi" class="f-inp">
              <option value="Baik">✅ Baik</option>
              <option value="Dalam Perbaikan">🔧 Dalam Perbaikan</option>
              <option value="Rusak">❌ Rusak</option>
              <option value="Tidak Aktif">⛔ Tidak Aktif</option>
            </select>
          </div>
        </div>

        <!-- ③b STATUS PAKAI — full width dengan visual indicator -->
        <div style="margin-bottom:12px;">
          <label class="f-label"><i class="fa fa-circle-dot" style="color:#26B99A;"></i> Status Pemakaian <span style="color:#ef4444;">*</span></label>
          <select name="status_pakai" id="f-status_pakai" class="f-inp val-terpakai" onchange="updateStatusPakaiStyle(this)">
            <option value="Terpakai">🔵 Terpakai — Aset sedang aktif digunakan</option>
            <option value="Tidak Terpakai">🟢 Tidak Terpakai — Aset belum/tidak digunakan</option>
            <option value="Dipinjam">🟡 Dipinjam — Aset sedang dipinjam</option>
          </select>
          <!-- Preview indikator -->
          <div id="sp-preview-bar" style="margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;">
            <i id="sp-preview-icon" class="fa fa-circle-dot"></i>
            <span id="sp-preview-txt">Baris tabel akan tampil normal (putih)</span>
          </div>
        </div>

        <!-- ④ Merek + Model -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Merek</label>
            <input type="text" name="merek" id="f-merek" class="f-inp" placeholder="Dell, HP, Canon, Cisco…">
          </div>
          <div>
            <label class="f-label">Model / Tipe</label>
            <input type="text" name="model_aset" id="f-model_aset" class="f-inp" placeholder="Latitude 5520, LaserJet Pro…">
          </div>
        </div>

        <!-- ⑤ Serial Number -->
        <div style="margin-bottom:12px;">
          <label class="f-label">Serial Number</label>
          <input type="text" name="serial_number" id="f-serial_number" class="f-inp" placeholder="Nomor seri perangkat" style="font-family:'Courier New',monospace;">
        </div>

        <div class="grs-modal"></div>

        <!-- ⑥ Lokasi / Bagian -->
        <div style="margin-bottom:12px;">
          <label class="f-label"><i class="fa fa-building" style="color:#26B99A;"></i> Lokasi / Bagian</label>
          <select name="bagian_id" id="f-bagian_id" class="f-inp" onchange="updateLokasiHint(this)">
            <option value="">— Pilih Bagian / Ruangan —</option>
            <?php foreach($bagian_list as $b): ?>
            <option value="<?= $b['id'] ?>" data-lokasi="<?= clean($b['lokasi']??'') ?>" data-kode="<?= clean($b['kode']??'') ?>">
              <?= ($b['kode']?'['.$b['kode'].'] ':'').clean($b['nama']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div id="lokasi-hint" style="margin-top:4px;font-size:10.5px;color:#64748b;display:none;">
            <i class="fa fa-location-dot" style="color:#26B99A;"></i> <span id="lokasi-hint-val"></span>
          </div>
        </div>

        <!-- ⑦ Penanggung Jawab -->
        <div style="margin-bottom:12px;">
          <label class="f-label"><i class="fa fa-user" style="color:#26B99A;"></i> Penanggung Jawab</label>
          <select name="pj_user_id" id="f-pj_user_id" class="f-inp" onchange="updatePjHint(this)">
            <option value="">— Pilih Pengguna / PIC —</option>
            <?php foreach($users_list as $u): ?>
            <option value="<?= $u['id'] ?>" data-divisi="<?= clean($u['divisi']??'') ?>" data-role="<?= clean($u['role']) ?>">
              <?= clean($u['nama']) ?><?php if($u['divisi']): ?> — <?= clean($u['divisi']) ?><?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div id="pj-hint" style="margin-top:4px;font-size:10.5px;color:#64748b;display:none;">
            <i class="fa fa-id-badge" style="color:#26B99A;"></i> <span id="pj-hint-val"></span>
          </div>
        </div>

        <div class="grs-modal"></div>

        <!-- ⑧ Tanggal Beli + Harga + Garansi -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Tanggal Beli</label>
            <input type="date" name="tanggal_beli" id="f-tanggal_beli" class="f-inp">
          </div>
          <div>
            <label class="f-label">Harga Beli (Rp)</label>
            <input type="number" name="harga_beli" id="f-harga_beli" class="f-inp" placeholder="0" min="0">
          </div>
          <div>
            <label class="f-label">Garansi Sampai</label>
            <input type="date" name="garansi_sampai" id="f-garansi_sampai" class="f-inp">
          </div>
        </div>

        <!-- ⑨ Keterangan -->
        <div>
          <label class="f-label">Keterangan</label>
          <textarea name="keterangan" id="f-keterangan" rows="3" class="f-inp" placeholder="Catatan tambahan…" style="resize:vertical;"></textarea>
        </div>

      </div><!-- /scroll -->

      <!-- Modal Footer -->
      <div style="padding:12px 20px;border-top:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
        <span style="font-size:11px;color:#94a3b8;"><i class="fa fa-asterisk" style="color:#ef4444;font-size:8px;"></i> Wajib diisi</span>
        <div style="display:flex;gap:8px;">
          <button type="button" onclick="tutupModal()" style="padding:7px 16px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f1f5f9';">Batal</button>
          <button type="submit" style="padding:7px 18px;background:linear-gradient(135deg,#26B99A,#1a7a5e);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;" onmouseover="this.style.opacity='.85';" onmouseout="this.style.opacity='1';"><i class="fa fa-save"></i> <span id="btn-submit-label">Simpan Aset</span></button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- ════════════════════════════════════════════════════
     MODAL HAPUS
════════════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-hapus-aset" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:380px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:20px 22px 14px;text-align:center;">
      <div style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;"><i class="fa fa-trash" style="color:#ef4444;font-size:22px;"></i></div>
      <div style="font-size:15px;font-weight:700;color:#1e293b;">Hapus Aset?</div>
      <div style="font-size:12.5px;color:#64748b;margin-top:6px;">Aset <strong id="hapus-nama"></strong> akan dihapus permanen.</div>
    </div>
    <form method="POST" action="<?= APP_URL ?>/pages/aset_it.php">
      <input type="hidden" name="_action" value="hapus">
      <input type="hidden" name="id" id="hapus-id">
      <div style="padding:12px 20px;display:flex;gap:8px;justify-content:center;border-top:1px solid #f0f0f0;">
        <button type="button" onclick="closeModal('m-hapus-aset')" style="padding:8px 20px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12.5px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
        <button type="submit" style="padding:8px 20px;background:#ef4444;border:none;border-radius:5px;font-size:12.5px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"><i class="fa fa-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>


<!-- ════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════ -->
<script>
const APP_URL = '<?= APP_URL ?>';

/* ── Dropdown Cetak ──────────────────────────────────────────────── */
function toggleCetakDrop(e) {
    e.stopPropagation();
    document.getElementById('cetak-drop').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('wrap-cetak');
    if (wrap && !wrap.contains(e.target))
        document.getElementById('cetak-drop').classList.remove('open');
});

/* ── Status Pakai: style select + preview bar ────────────────────── */
function updateStatusPakaiStyle(sel) {
    const val     = sel.value;
    const bar     = document.getElementById('sp-preview-bar');
    const icon    = document.getElementById('sp-preview-icon');
    const txt     = document.getElementById('sp-preview-txt');

    // Reset classes
    sel.className = 'f-inp';

    if (val === 'Tidak Terpakai') {
        sel.classList.add('val-tidak');
        bar.style.cssText = 'margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;background:#d1fae5;color:#065f46;border:1px solid #86efac;';
        icon.className = 'fa fa-circle';
        txt.textContent = 'Baris tabel akan berwarna HIJAU + nama aset dicoret';
    } else if (val === 'Dipinjam') {
        sel.classList.add('val-dipinjam');
        bar.style.cssText = 'margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;';
        icon.className = 'fa fa-hand-holding';
        txt.textContent = 'Baris tabel akan berwarna KUNING';
    } else {
        sel.classList.add('val-terpakai');
        bar.style.cssText = 'margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;';
        icon.className = 'fa fa-circle-dot';
        txt.textContent = 'Baris tabel akan tampil normal (putih)';
    }
}

/* ── Toggle Auto / Manual No. Inventaris ─────────────────────────── */
function toggleInv(chk) {
    const inp     = document.getElementById('f-no_inventaris');
    const icon    = document.getElementById('inv-icon');
    const lbl     = document.getElementById('inv-toggle-label');
    const sub     = document.getElementById('inv-toggle-sub');
    const preview = document.getElementById('inv-preview');
    const autoInp = document.getElementById('f-auto-inv');

    if (chk.checked) {
        inp.disabled = false; inp.placeholder = 'Ketik nomor inventaris…'; inp.value = ''; inp.focus();
        icon.className = 'fa fa-pen'; icon.style.color = '#26B99A';
        lbl.textContent = 'Manual'; sub.textContent = 'Klik untuk kembali auto';
        preview.style.display = 'none'; autoInp.value = '0';
    } else {
        inp.disabled = true; inp.placeholder = 'Akan digenerate otomatis…'; inp.value = '';
        icon.className = 'fa fa-lock'; icon.style.color = '#94a3b8';
        lbl.textContent = 'Auto'; sub.textContent = 'Klik untuk manual';
        preview.style.display = ''; autoInp.value = '1';
        previewNoInv();
    }
}
function previewNoInv() {
    fetch(APP_URL + '/pages/aset_it.php?preview_no_inv=1')
        .then(r => r.json())
        .then(d => { document.getElementById('inv-preview-val').textContent = d.no; });
}

/* ── Hint lokasi ─────────────────────────────────────────────────── */
function updateLokasiHint(sel) {
    const lok  = sel.options[sel.selectedIndex].dataset.lokasi || '';
    const hint = document.getElementById('lokasi-hint');
    if (lok && sel.value) { document.getElementById('lokasi-hint-val').textContent = lok; hint.style.display = ''; }
    else { hint.style.display = 'none'; }
}

/* ── Hint PJ ─────────────────────────────────────────────────────── */
function updatePjHint(sel) {
    const opt = sel.options[sel.selectedIndex];
    const divisi = opt.dataset.divisi || '', role = opt.dataset.role || '';
    const hint = document.getElementById('pj-hint');
    if (sel.value && (divisi || role)) {
        let txt = [];
        if (role)   txt.push('Role: ' + role.charAt(0).toUpperCase() + role.slice(1));
        if (divisi) txt.push('Divisi: ' + divisi);
        document.getElementById('pj-hint-val').textContent = txt.join('  •  ');
        hint.style.display = '';
    } else { hint.style.display = 'none'; }
}

/* ── Buka modal Tambah ───────────────────────────────────────────── */
function bukaModalTambah() {
    resetFormAset();
    openModal('m-tambah-aset');
    previewNoInv();
}

/* ── Edit Aset ───────────────────────────────────────────────────── */
function editAset(id) {
    fetch(APP_URL + '/pages/aset_it.php?get_aset=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('modal-title').textContent      = 'Edit Aset IT';
            document.getElementById('modal-icon').className         = 'fa fa-pen';
            document.getElementById('btn-submit-label').textContent = 'Perbarui Aset';
            document.getElementById('f-action').value = 'edit';
            document.getElementById('f-id').value     = d.id;

            // Mode manual saat edit
            const chk = document.getElementById('toggle-manual-inv');
            chk.checked = true; toggleInv(chk);
            document.getElementById('f-no_inventaris').value = d.no_inventaris || '';

            document.getElementById('f-nama_aset').value      = d.nama_aset     || '';
            document.getElementById('f-kategori').value       = d.kategori      || '';
            document.getElementById('f-kondisi').value        = d.kondisi       || 'Baik';
            document.getElementById('f-merek').value          = d.merek         || '';
            document.getElementById('f-model_aset').value     = d.model_aset    || '';
            document.getElementById('f-serial_number').value  = d.serial_number || '';
            document.getElementById('f-tanggal_beli').value   = d.tanggal_beli  || '';
            document.getElementById('f-harga_beli').value     = d.harga_beli    || '';
            document.getElementById('f-garansi_sampai').value = d.garansi_sampai|| '';
            document.getElementById('f-keterangan').value     = d.keterangan    || '';

            // Status Pakai
            const spSel = document.getElementById('f-status_pakai');
            spSel.value = d.status_pakai || 'Terpakai';
            updateStatusPakaiStyle(spSel);

            // Bagian
            const sBagian = document.getElementById('f-bagian_id');
            sBagian.value = d.bagian_id || ''; updateLokasiHint(sBagian);

            // PJ
            const sPj = document.getElementById('f-pj_user_id');
            sPj.value = d.pj_user_id || ''; updatePjHint(sPj);

            openModal('m-tambah-aset');
        });
}

/* ── Hapus ───────────────────────────────────────────────────────── */
function hapusAset(id, nama) {
    document.getElementById('hapus-id').value         = id;
    document.getElementById('hapus-nama').textContent = nama;
    openModal('m-hapus-aset');
}

/* ── Tutup & reset ───────────────────────────────────────────────── */
function tutupModal() {
    closeModal('m-tambah-aset');
    resetFormAset();
}
function resetFormAset() {
    document.getElementById('form-aset').reset();
    document.getElementById('f-action').value               = 'tambah';
    document.getElementById('f-id').value                   = '';
    document.getElementById('modal-title').textContent      = 'Tambah Aset IT Baru';
    document.getElementById('modal-icon').className         = 'fa fa-plus';
    document.getElementById('btn-submit-label').textContent = 'Simpan Aset';
    // Reset ke Auto
    const chk = document.getElementById('toggle-manual-inv');
    chk.checked = false; toggleInv(chk);
    document.getElementById('lokasi-hint').style.display = 'none';
    document.getElementById('pj-hint').style.display     = 'none';
    // Reset status pakai
    const spSel = document.getElementById('f-status_pakai');
    spSel.value = 'Terpakai'; updateStatusPakaiStyle(spSel);
}

document.getElementById('m-tambah-aset').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
</script>

<?php include '../includes/footer.php'; ?>