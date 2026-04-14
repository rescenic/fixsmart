<?php
// pages/berkas_karyawan.php
ob_start();
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'hrd'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Berkas Karyawan';
$active_menu = 'berkas_karyawan';

// ── Auto-create tabel ─────────────────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `berkas_karyawan` (
      `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `user_id`         INT UNSIGNED NOT NULL,
      `jenis_berkas_id` INT UNSIGNED DEFAULT NULL,
      `kategori_id`     INT UNSIGNED DEFAULT NULL,
      `nama_file`       VARCHAR(255) NOT NULL,
      `nama_asli`       VARCHAR(255) NOT NULL,
      `ukuran`          INT UNSIGNED DEFAULT 0,
      `mime_type`       VARCHAR(100) DEFAULT NULL,
      `keterangan`      TEXT         DEFAULT NULL,
      `tgl_dokumen`     DATE         DEFAULT NULL,
      `tgl_exp`         DATE         DEFAULT NULL,
      `status_verif`    ENUM('pending','terverifikasi','ditolak') DEFAULT 'pending',
      `catatan_verif`   TEXT         DEFAULT NULL,
      `verified_by`     INT UNSIGNED DEFAULT NULL,
      `verified_at`     DATETIME     DEFAULT NULL,
      `uploaded_by`     INT UNSIGNED DEFAULT NULL,
      `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
      `updated_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY `idx_user_id` (`user_id`),
      KEY `idx_jenis`   (`jenis_berkas_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/berkas_karyawan/');
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'upload_berkas') {
        $uid      = (int)($_POST['user_id']         ?? 0);
        $jenis_id = (int)($_POST['jenis_berkas_id'] ?? 0);
        $ket      = trim($_POST['keterangan']  ?? '');
        $tgl_dok  = trim($_POST['tgl_dokumen'] ?? '') ?: null;
        $tgl_exp  = trim($_POST['tgl_exp']     ?? '') ?: null;

        if (!$uid || !$jenis_id) {
            setFlash('danger', 'Data tidak lengkap.');
            redirect(APP_URL . '/pages/berkas_karyawan.php');
        }

        $jenis = $pdo->prepare("SELECT j.*,k.id AS kid FROM master_jenis_berkas j LEFT JOIN master_kategori_berkas k ON k.id=j.kategori_id WHERE j.id=? AND j.status='aktif'");
        $jenis->execute([$jenis_id]);
        $jenis = $jenis->fetch(PDO::FETCH_ASSOC);
        if (!$jenis) {
            setFlash('danger', 'Jenis berkas tidak ditemukan.');
            redirect(APP_URL . '/pages/berkas_karyawan.php');
        }

        if (empty($_FILES['file_berkas']['name'])) {
            setFlash('danger', 'Tidak ada file.');
            redirect(APP_URL . '/pages/berkas_karyawan.php');
        }

        $file    = $_FILES['file_berkas'];
        $allowed = array_map('trim', explode(',', $jenis['format_file'] ?: 'pdf,jpg,jpeg,png'));
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            setFlash('danger', 'Format tidak diizinkan. Gunakan: ' . strtoupper(implode(', ', $allowed)));
            redirect(APP_URL . '/pages/berkas_karyawan.php');
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            setFlash('danger', 'File melebihi 10 MB.');
            redirect(APP_URL . '/pages/berkas_karyawan.php');
        }

        $user_dir  = UPLOAD_DIR . $uid . '/';
        if (!is_dir($user_dir)) @mkdir($user_dir, 0755, true);
        $nama_file = uniqid('bk_', true) . '.' . $ext;

        if (move_uploaded_file($file['tmp_name'], $user_dir . $nama_file)) {
            try {
                $old = $pdo->prepare("SELECT nama_file FROM berkas_karyawan WHERE user_id=? AND jenis_berkas_id=? LIMIT 1");
                $old->execute([$uid, $jenis_id]);
                if ($r = $old->fetch()) {
                    @unlink($user_dir . $r['nama_file']);
                    $pdo->prepare("DELETE FROM berkas_karyawan WHERE user_id=? AND jenis_berkas_id=?")->execute([$uid, $jenis_id]);
                }
                $pdo->prepare("INSERT INTO berkas_karyawan (user_id,jenis_berkas_id,kategori_id,nama_file,nama_asli,ukuran,mime_type,keterangan,tgl_dokumen,tgl_exp,status_verif,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,'pending',?)")
                    ->execute([$uid, $jenis_id, $jenis['kid'], $nama_file, $file['name'], $file['size'], $file['type'] ?: null, $ket ?: null, $tgl_dok, $tgl_exp, (int)$_SESSION['user_id']]);
                setFlash('success', 'Berkas <strong>' . htmlspecialchars($jenis['nama']) . '</strong> berhasil diunggah.');
            } catch (Exception $e) {
                @unlink($user_dir . $nama_file);
                setFlash('danger', 'Gagal: ' . htmlspecialchars($e->getMessage()));
            }
        } else {
            setFlash('danger', 'Gagal memindahkan file.');
        }
        redirect(APP_URL . '/pages/berkas_karyawan.php');
    }

    if ($act === 'hapus_berkas') {
        $bid = (int)($_POST['berkas_id'] ?? 0);
        if ($bid) {
            $r = $pdo->prepare("SELECT user_id,nama_file FROM berkas_karyawan WHERE id=?");
            $r->execute([$bid]);
            $b = $r->fetch();
            if ($b) {
                @unlink(UPLOAD_DIR . $b['user_id'] . '/' . $b['nama_file']);
                $pdo->prepare("DELETE FROM berkas_karyawan WHERE id=?")->execute([$bid]);
                setFlash('success', 'Berkas dihapus.');
            }
        }
        redirect(APP_URL . '/pages/berkas_karyawan.php');
    }

    if ($act === 'verif_berkas') {
        // PERBAIKAN: hapus typo $_ yang salah
        $bid = (int)($_POST['berkas_id']    ?? 0);
        $sv  = $_POST['status_verif']       ?? '';
        $cat = trim($_POST['catatan_verif'] ?? '');
        if ($bid && in_array($sv, ['terverifikasi', 'ditolak'])) {
            $pdo->prepare("UPDATE berkas_karyawan SET status_verif=?,catatan_verif=?,verified_by=?,verified_at=NOW() WHERE id=?")
                ->execute([$sv, $cat ?: null, (int)$_SESSION['user_id'], $bid]);
            setFlash('success', 'Status berkas diperbarui.');
        }
        redirect(APP_URL . '/pages/berkas_karyawan.php');
    }

    redirect(APP_URL . '/pages/berkas_karyawan.php');
}

// ── Data master dari DB ───────────────────────────────────────────────────────
$master_berkas = $pdo->query("
    SELECT j.id, j.nama, j.icon, j.keterangan, j.wajib, j.has_exp, j.has_tgl_terbit,
           j.format_file, j.urutan,
           k.id AS kategori_id, k.nama AS nama_kategori,
           k.icon AS kat_icon, k.warna AS kat_warna
    FROM master_jenis_berkas j
    LEFT JOIN master_kategori_berkas k ON k.id=j.kategori_id
    WHERE j.status='aktif' AND (k.status='aktif' OR k.id IS NULL)
    ORDER BY k.urutan, k.nama, j.urutan, j.nama
")->fetchAll(PDO::FETCH_ASSOC);

$berkas_by_kat = [];
foreach ($master_berkas as $b) {
    $key = ($b['kategori_id'] ?? 0) . '|' . ($b['nama_kategori'] ?? 'Umum');
    if (!isset($berkas_by_kat[$key])) {
        $berkas_by_kat[$key] = [
            'id'    => $b['kategori_id'],
            'nama'  => $b['nama_kategori'] ?? 'Umum',
            'icon'  => $b['kat_icon']      ?? 'fa-folder',
            'warna' => $b['kat_warna']     ?? '#6366f1',
            'items' => [],
        ];
    }
    $berkas_by_kat[$key]['items'][] = $b;
}
$total_required = array_sum(array_column($master_berkas, 'wajib'));

// ── Fetch karyawan ────────────────────────────────────────────────────────────
$f_nama   = trim($_GET['nama']   ?? '');
$f_status = trim($_GET['status'] ?? '');
$f_divisi = trim($_GET['divisi'] ?? '');
$page_cur = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;

$where = []; $params = [];
if ($f_nama   !== '') { $where[] = '(u.nama LIKE ? OR u.email LIKE ?)'; $params[] = "%$f_nama%"; $params[] = "%$f_nama%"; }
if ($f_status !== '') { $where[] = 'u.status=?';  $params[] = $f_status; }
if ($f_divisi !== '') { $where[] = 'u.divisi=?';  $params[] = $f_divisi; }
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM users u $wsql");
$cnt->execute($params);
$total_rows  = (int)$cnt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page_cur    = min($page_cur, $total_pages);
$offset      = ($page_cur - 1) * $per_page;

$stm = $pdo->prepare("
    SELECT u.id, u.nama, u.email, u.divisi, u.status, u.role, u.no_hp,
           s.jenis_karyawan, s.nik_rs, s.gelar_depan, s.gelar_belakang
    FROM users u
    LEFT JOIN sdm_karyawan s ON s.user_id = u.id
    $wsql ORDER BY u.nama ASC
    LIMIT $per_page OFFSET $offset
");
$stm->execute($params);
$list = $stm->fetchAll(PDO::FETCH_ASSOC);

$berkas_stats = [];
if ($list) {
    $uids = array_column($list, 'id');
    $ph   = implode(',', array_fill(0, count($uids), '?'));
    $rows = $pdo->prepare("
        SELECT user_id,
               COUNT(*) total,
               SUM(status_verif='terverifikasi') verified,
               SUM(status_verif='pending') pending,
               SUM(status_verif='ditolak') ditolak,
               SUM(tgl_exp IS NOT NULL AND tgl_exp < DATE_ADD(NOW(),INTERVAL 30 DAY) AND tgl_exp >= NOW()) exp_soon,
               SUM(tgl_exp IS NOT NULL AND tgl_exp < NOW()) expired
        FROM berkas_karyawan WHERE user_id IN ($ph)
        GROUP BY user_id
    ");
    $rows->execute($uids);
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) $berkas_stats[$r['user_id']] = $r;
}

$gstats = ['total_berkas' => 0, 'terverifikasi' => 0, 'pending' => 0, 'ditolak' => 0, 'exp_soon' => 0];
try {
    $g = $pdo->query("
        SELECT COUNT(*) total_berkas,
               SUM(status_verif='terverifikasi') terverifikasi,
               SUM(status_verif='pending') pending,
               SUM(status_verif='ditolak') ditolak,
               SUM(tgl_exp IS NOT NULL AND tgl_exp < DATE_ADD(NOW(),INTERVAL 30 DAY) AND tgl_exp >= NOW()) exp_soon
        FROM berkas_karyawan
    ")->fetch(PDO::FETCH_ASSOC);
    if ($g) $gstats = array_merge($gstats, $g);
} catch (Exception $e) {}

$bagian_list = [];
try {
    $bagian_list = $pdo->query("SELECT nama FROM bagian WHERE status='aktif' ORDER BY urutan,nama")
                       ->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

include '../includes/header.php';
?>
<style>
:root { --bk-green: #00c896; --bk-danger: #dc3545; }
.bk-ov { display:none; position:fixed; inset:0; background:rgba(15,23,42,.65); z-index:99999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px); }
.bk-ov.open { display:flex; }
.bk-modal { background:#fff; border-radius:16px; box-shadow:0 32px 100px rgba(0,0,0,.3); width:100%; max-width:920px; height:88vh; min-height:560px; max-height:94vh; display:flex; flex-direction:column; animation:bkIn .22s ease; overflow:hidden; }
@keyframes bkIn { from{opacity:0;transform:translateY(18px) scale(.97);} to{opacity:1;transform:none;} }
.bk-mhead { background:linear-gradient(135deg,#1e293b,#1e3a5f); padding:16px 20px; display:flex; align-items:center; gap:14px; color:#fff; flex-shrink:0; border-bottom:2px solid rgba(255,255,255,.08); }
.bk-mav { width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,var(--bk-green),#00a077); color:#fff; font-size:14px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.bk-mhead-info { flex:1; min-width:0; }
.bk-mhead-nama { font-size:15px; font-weight:700; }
.bk-mhead-sub  { font-size:11.5px; color:#94a3b8; margin-top:2px; }
.bk-mclose { width:32px; height:32px; border-radius:8px; flex-shrink:0; border:none; background:rgba(255,255,255,.12); color:#fff; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; transition:background .15s; }
.bk-mclose:hover { background:var(--bk-danger); }
.bk-prog-wrap { padding:12px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.bk-prog-label { display:flex; justify-content:space-between; font-size:11px; font-weight:600; color:#64748b; margin-bottom:5px; }
.bk-prog-bar  { height:6px; border-radius:99px; background:#e2e8f0; overflow:hidden; }
.bk-prog-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,var(--bk-green),#0ea5e9); transition:width .4s; }
.bk-mbody { flex:1; overflow-y:auto; min-height:0; overscroll-behavior:contain; }
.bk-cat { border-bottom:1px solid #f1f5f9; }
.bk-cat-hd { display:flex; align-items:center; gap:10px; padding:11px 20px; cursor:pointer; background:#f8fafc; user-select:none; transition:background .15s; }
.bk-cat-hd:hover { background:#f1f5f9; }
.bk-cat-icon  { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:11px; flex-shrink:0; }
.bk-cat-title { font-size:12.5px; font-weight:700; color:#1e293b; flex:1; }
.bk-cat-badge { font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; }
.bk-cat-arrow { font-size:10px; color:#94a3b8; transition:transform .2s; }
.bk-cat.open .bk-cat-arrow { transform:rotate(180deg); }
.bk-cat-body { display:none; }
.bk-cat.open .bk-cat-body { display:block; }
.bk-item { display:flex; align-items:center; gap:12px; padding:10px 20px 10px 28px; border-bottom:1px solid #f8fafc; transition:background .12s; }
.bk-item:hover { background:#fafbff; }
.bk-item-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
.bk-item-info { flex:1; min-width:0; }
.bk-item-name { font-size:12.5px; font-weight:600; color:#1e293b; display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
.bk-item-meta { font-size:10.5px; color:#94a3b8; margin-top:2px; }
.bk-req-badge  { font-size:9px; font-weight:700; padding:1px 5px; border-radius:4px; background:#fee2e2; color:#dc2626; }
.bk-status-chip { font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; }
.chip-ok      { background:#dcfce7; color:#16a34a; }
.chip-pending { background:#fef3c7; color:#d97706; }
.chip-ditolak { background:#fee2e2; color:#dc2626; }
.chip-kosong  { background:#f1f5f9; color:#94a3b8; }
.bk-exp-tag   { font-size:9.5px; font-weight:700; padding:1px 6px; border-radius:4px; }
.exp-soon { background:#fff7ed; color:#ea580c; }
.exp-gone { background:#fee2e2; color:#dc2626; }
.exp-ok   { background:#f0fdf4; color:#16a34a; }
.bk-btn-up    { font-size:11px; font-weight:600; padding:5px 12px; border-radius:8px; border:1.5px dashed #94a3b8; background:#f8fafc; color:#64748b; cursor:pointer; white-space:nowrap; transition:all .15s; display:inline-flex; align-items:center; gap:5px; }
.bk-btn-up:hover { border-color:#0d6efd; color:#0d6efd; background:#eff6ff; }
.bk-btn-view  { font-size:11px; font-weight:600; padding:5px 10px; border-radius:8px; border:1px solid #e2e8f0; background:#fff; color:#374151; cursor:pointer; display:inline-flex; align-items:center; gap:4px; text-decoration:none; transition:all .15s; }
.bk-btn-view:hover { background:#f1f5f9; }
.bk-btn-del   { font-size:11px; width:28px; height:28px; border-radius:8px; border:1px solid #fca5a5; background:#fff5f5; color:var(--bk-danger); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all .15s; }
.bk-btn-del:hover { background:var(--bk-danger); color:#fff; }
.bk-btn-verif { font-size:10px; font-weight:600; padding:4px 8px; border-radius:8px; border:1px solid #bbf7d0; background:#f0fdf4; color:#16a34a; cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:all .15s; }
.bk-btn-verif:hover { background:#dcfce7; }
.up-modal { background:#fff; border-radius:14px; box-shadow:0 24px 80px rgba(0,0,0,.35); width:100%; max-width:480px; animation:bkIn .2s ease; overflow:hidden; }
.up-mhead { background:linear-gradient(135deg,#4f46e5,#6366f1); padding:14px 18px; display:flex; align-items:center; gap:10px; color:#fff; }
.up-mhead-title { font-size:13px; font-weight:700; flex:1; }
.up-mbody { padding:18px; }
.up-drop { border:2px dashed #c7d2fe; border-radius:12px; padding:24px; text-align:center; background:#f5f3ff; cursor:pointer; transition:all .15s; margin-bottom:14px; }
.up-drop:hover, .up-drop.drag { border-color:#6366f1; background:#ede9fe; }
.up-drop i { font-size:26px; color:#6366f1; margin-bottom:8px; display:block; }
.up-drop p  { font-size:12.5px; color:#6b7280; margin:0; }
.up-drop small { font-size:10.5px; color:#9ca3af; }
.up-fg { margin-bottom:10px; }
.up-fg label { font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
.up-fg input, .up-fg select, .up-fg textarea { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:7px 11px; font-size:12.5px; font-family:inherit; background:#f9fafb; color:#111827; box-sizing:border-box; transition:border-color .15s; }
.up-fg input:focus, .up-fg select:focus, .up-fg textarea:focus { outline:none; border-color:#6366f1; background:#fff; box-shadow:0 0 0 3px rgba(99,102,241,.1); }
.up-mft { padding:12px 18px; border-top:1px solid #e5e7eb; display:flex; gap:8px; justify-content:flex-end; background:#f8fafc; }
.vf-modal { background:#fff; border-radius:14px; box-shadow:0 24px 80px rgba(0,0,0,.35); width:100%; max-width:400px; animation:bkIn .2s ease; }
</style>

<div class="page-header">
  <h4><i class="fa fa-folder-open text-primary"></i> &nbsp;Berkas Karyawan</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Berkas Karyawan</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <?php if (empty($master_berkas)): ?>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-start;">
    <i class="fa fa-triangle-exclamation" style="color:#f59e0b;margin-top:2px;flex-shrink:0;"></i>
    <div>
      <strong style="color:#92400e;">Master Berkas Kosong!</strong>
      <div style="font-size:12.5px;color:#a16207;margin-top:3px;">
        Belum ada jenis berkas aktif. Tambahkan di
        <a href="master_berkas.php" style="color:#d97706;font-weight:600;">Master Berkas</a> terlebih dahulu.
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:18px;">
    <?php foreach ([
      ['Total Berkas',    $gstats['total_berkas'],  '#1e293b', '#6366f1'],
      ['Terverifikasi',   $gstats['terverifikasi'], '#065f46', '#00c896'],
      ['Menunggu Verif',  $gstats['pending'],       '#b45309', '#f59e0b'],
      ['Ditolak',         $gstats['ditolak'],       '#991b1b', '#dc3545'],
      ['Exp &lt;30 Hari', $gstats['exp_soon'],      '#7c2d12', '#f97316'],
      ['Jenis Berkas',    count($master_berkas),    '#1e3a5f', '#0ea5e9'],
    ] as [$lbl, $val, $tc, $bc]): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:13px 15px;">
      <div style="font-size:22px;font-weight:800;color:<?= $tc ?>;line-height:1;"><?= (int)$val ?></div>
      <div style="font-size:11px;color:#6b7280;font-weight:500;margin-top:3px;"><?= $lbl ?></div>
      <div style="height:3px;border-radius:99px;background:<?= $bc ?>;margin-top:5px;opacity:.5;"></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-bottom:12px;display:flex;justify-content:flex-end;">
    <a href="master_berkas.php" class="btn btn-default btn-sm">
      <i class="fa fa-folder-tree"></i> &nbsp;Kelola Master Berkas
    </a>
  </div>

  <!-- Filter -->
  <form method="GET">
    <div class="panel" style="margin-bottom:14px;">
      <div class="panel-bd">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
          <div>
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:3px;">Cari Nama</label>
            <input type="text" name="nama" class="form-control" style="width:180px;height:34px;"
                   placeholder="Ketik…" value="<?= htmlspecialchars($f_nama) ?>">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:3px;">Divisi</label>
            <select name="divisi" class="form-control" style="min-width:130px;height:34px;">
              <option value="">Semua</option>
              <?php foreach ($bagian_list as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>" <?= $f_divisi === $b ? 'selected' : '' ?>>
                <?= htmlspecialchars($b) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:3px;">Status</label>
            <select name="status" class="form-control" style="min-width:100px;height:34px;">
              <option value="">Semua</option>
              <option value="aktif"    <?= $f_status === 'aktif'    ? 'selected' : '' ?>>Aktif</option>
              <option value="nonaktif" <?= $f_status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
          <div style="display:flex;gap:6px;">
            <button type="submit" class="btn btn-primary" style="height:34px;">
              <i class="fa fa-search"></i> Filter
            </button>
            <a href="berkas_karyawan.php" class="btn btn-default"
               style="height:34px;display:inline-flex;align-items:center;gap:5px;">
              <i class="fa fa-rotate-left"></i> Reset
            </a>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- Tabel -->
  <div class="panel">
    <div class="panel-hd">
      <h5>
        <i class="fa fa-folder-open text-primary"></i> &nbsp;Daftar Berkas Karyawan
        <span style="color:#aaa;font-weight:400;">(<?= number_format($total_rows) ?>)</span>
      </h5>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th>Nama Karyawan</th>
            <th>Divisi</th>
            <th style="width:120px;">Kelengkapan</th>
            <th style="width:100px;">Verifikasi</th>
            <th style="width:90px;">Expiry</th>
            <th style="width:120px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($list)): ?>
          <tr>
            <td colspan="7" class="td-empty">
              <i class="fa fa-folder-open"></i> Tidak ada data
            </td>
          </tr>
          <?php else: foreach ($list as $i => $k):
            $words   = array_filter(explode(' ', trim($k['nama'])));
            $inisial = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(array_values($words), 0, 2))));
            $bs      = $berkas_stats[$k['id']] ?? ['total' => 0, 'verified' => 0, 'pending' => 0, 'ditolak' => 0, 'exp_soon' => 0, 'expired' => 0];
            $pct     = $total_required > 0 ? min(100, round($bs['total'] / $total_required * 100)) : 0;
            $nama_tampil = ($k['gelar_depan'] ? $k['gelar_depan'] . ' ' : '')
                         . htmlspecialchars($k['nama'])
                         . ($k['gelar_belakang'] ? ', ' . $k['gelar_belakang'] : '');
          ?>
          <tr>
            <td style="color:#bbb;font-size:12px;"><?= $offset + $i + 1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:34px;height:34px;border-radius:9px;flex-shrink:0;
                            background:linear-gradient(135deg,#6366f1,#4f46e5);
                            color:#fff;font-size:11px;font-weight:800;
                            display:inline-flex;align-items:center;justify-content:center;">
                  <?= htmlspecialchars($inisial ?: '?') ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:13px;"><?= $nama_tampil ?></div>
                  <div style="font-size:10.5px;color:#9ca3af;">
                    <?= $k['nik_rs'] ? 'NIK: ' . htmlspecialchars($k['nik_rs']) : htmlspecialchars($k['email'] ?? '') ?>
                  </div>
                </div>
              </div>
            </td>
            <td style="font-size:12px;">
              <?= $k['divisi'] ? htmlspecialchars($k['divisi']) : '<span style="color:#bbb;">—</span>' ?>
              <?php if ($k['jenis_karyawan']): ?>
              <br><span style="font-size:10px;color:#888;"><?= htmlspecialchars($k['jenis_karyawan']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-size:10.5px;font-weight:600;color:#374151;margin-bottom:4px;">
                <?= (int)$bs['total'] ?> / <?= $total_required ?>
              </div>
              <div style="height:5px;border-radius:99px;background:#e5e7eb;overflow:hidden;">
                <div style="height:100%;border-radius:99px;width:<?= $pct ?>%;
                     background:<?= $pct >= 80 ? '#00c896' : ($pct >= 40 ? '#f59e0b' : '#ef4444') ?>;
                     transition:width .4s;"></div>
              </div>
              <div style="font-size:9.5px;color:#9ca3af;margin-top:2px;"><?= $pct ?>%</div>
            </td>
            <td>
              <?php if ($bs['verified'] > 0): ?>
              <span class="bk-status-chip chip-ok"><i class="fa fa-check"></i> <?= (int)$bs['verified'] ?></span>
              <?php endif; ?>
              <?php if ($bs['pending'] > 0): ?>
              <br><span class="bk-status-chip chip-pending" style="margin-top:2px;">
                <i class="fa fa-clock"></i> <?= (int)$bs['pending'] ?>
              </span>
              <?php endif; ?>
              <?php if ($bs['ditolak'] > 0): ?>
              <br><span class="bk-status-chip chip-ditolak" style="margin-top:2px;">
                <i class="fa fa-times"></i> <?= (int)$bs['ditolak'] ?>
              </span>
              <?php endif; ?>
              <?php if (!$bs['total']): ?>
              <span class="bk-status-chip chip-kosong"><i class="fa fa-minus"></i> Kosong</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($bs['expired'] > 0): ?>
              <span style="font-size:10px;font-weight:700;color:#dc2626;">
                <i class="fa fa-triangle-exclamation"></i> <?= (int)$bs['expired'] ?> Exp
              </span>
              <?php elseif ($bs['exp_soon'] > 0): ?>
              <span style="font-size:10px;font-weight:700;color:#ea580c;">
                <i class="fa fa-clock"></i> <?= (int)$bs['exp_soon'] ?> &lt;30h
              </span>
              <?php else: ?>
              <span style="font-size:10px;color:#9ca3af;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <button type="button"
                      class="btn btn-primary btn-sm btn-open-berkas"
                      data-uid="<?= (int)$k['id'] ?>"
                      data-nama="<?= htmlspecialchars($k['nama'], ENT_QUOTES) ?>"
                      data-divisi="<?= htmlspecialchars($k['divisi'] ?? '', ENT_QUOTES) ?>">
                <i class="fa fa-folder-open"></i> Kelola
              </button>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;
                border-top:1px solid #e5e7eb;flex-wrap:wrap;gap:8px;">
      <div style="font-size:11.5px;color:#6b7280;">Hal <?= $page_cur ?> / <?= $total_pages ?></div>
      <div style="display:flex;gap:4px;">
        <?php
          $qs    = http_build_query(array_filter(['nama' => $f_nama, 'divisi' => $f_divisi, 'status' => $f_status]));
          $qs    = $qs ? '&' . $qs : '';
          $s     = max(1, $page_cur - 2);
          $e     = min($total_pages, $s + 4);
          $s     = max(1, $e - 4);
        ?>
        <a href="?p=1<?= $qs ?>" class="btn btn-sm btn-default"><i class="fa fa-angles-left"></i></a>
        <a href="?p=<?= max(1, $page_cur - 1) ?><?= $qs ?>" class="btn btn-sm btn-default"><i class="fa fa-angle-left"></i></a>
        <?php for ($pg = $s; $pg <= $e; $pg++): ?>
        <a href="?p=<?= $pg ?><?= $qs ?>"
           class="btn btn-sm <?= $pg === $page_cur ? 'btn-primary' : 'btn-default' ?>">
          <?= $pg ?>
        </a>
        <?php endfor; ?>
        <a href="?p=<?= min($total_pages, $page_cur + 1) ?><?= $qs ?>" class="btn btn-sm btn-default"><i class="fa fa-angle-right"></i></a>
        <a href="?p=<?= $total_pages ?><?= $qs ?>" class="btn btn-sm btn-default"><i class="fa fa-angles-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div><!-- /content -->

<!-- ══════════ MODAL KELOLA BERKAS ══════════ -->
<div id="bk-modal" class="bk-ov">
  <div class="bk-modal">
    <div class="bk-mhead">
      <div class="bk-mav" id="bk-av">?</div>
      <div class="bk-mhead-info">
        <div class="bk-mhead-nama" id="bk-nama-display">—</div>
        <div class="bk-mhead-sub"  id="bk-sub-display">Berkas Karyawan</div>
      </div>
      <button type="button" class="bk-mclose" id="bk-close-btn"><i class="fa fa-times"></i></button>
    </div>
    <div class="bk-prog-wrap">
      <div class="bk-prog-label">
        <span>Kelengkapan Berkas Wajib</span>
        <span id="bk-prog-text">0 / <?= $total_required ?> berkas wajib</span>
      </div>
      <div class="bk-prog-bar">
        <div class="bk-prog-fill" id="bk-prog-fill" style="width:0%;"></div>
      </div>
    </div>
    <div class="bk-mbody" id="bk-mbody">
      <div style="display:flex;align-items:center;justify-content:center;height:200px;color:#9ca3af;gap:8px;">
        <i class="fa fa-spinner fa-spin"></i> Memuat…
      </div>
    </div>
  </div>
</div>

<!-- ══════════ MODAL UPLOAD ══════════ -->
<div id="up-modal" class="bk-ov" style="z-index:100000;">
  <div class="up-modal">
    <div class="up-mhead">
      <i class="fa fa-cloud-arrow-up" style="font-size:18px;"></i>
      <span class="up-mhead-title" id="up-modal-title">Upload Berkas</span>
      <button type="button" class="bk-mclose" id="up-close-btn"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="up-form">
      <input type="hidden" name="action"        value="upload_berkas">
      <input type="hidden" name="user_id"        id="up-user-id"  value="">
      <input type="hidden" name="jenis_berkas_id" id="up-jenis-id" value="">
      <div class="up-mbody">
        <div class="up-drop" id="up-drop" onclick="document.getElementById('up-file-input').click()">
          <i class="fa fa-file-arrow-up"></i>
          <p>Klik atau seret file ke sini</p>
          <small id="up-format-hint">Format: PDF, JPG, PNG — Maks. 10 MB</small>
        </div>
        <input type="file" name="file_berkas" id="up-file-input" style="display:none;">
        <div id="up-file-prev" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;
             border-radius:8px;padding:9px 12px;margin-bottom:12px;font-size:12px;
             align-items:center;gap:8px;">
          <i class="fa fa-file-check" style="color:#16a34a;"></i>
          <span id="up-file-name" style="flex:1;font-weight:600;"></span>
          <span id="up-file-size" style="color:#9ca3af;"></span>
        </div>
        <div class="up-fg" id="up-terbit-wrap" style="display:none;">
          <label>Tanggal Terbit Dokumen</label>
          <input type="date" name="tgl_dokumen">
        </div>
        <div class="up-fg" id="up-exp-wrap" style="display:none;">
          <label>Tanggal Kadaluarsa</label>
          <input type="date" name="tgl_exp">
        </div>
        <div class="up-fg">
          <label>Keterangan <span style="font-size:10px;color:#9ca3af;">(opsional)</span></label>
          <textarea name="keterangan" rows="2" placeholder="No. dokumen, instansi penerbit, catatan…"></textarea>
        </div>
      </div>
      <div class="up-mft">
        <button type="button" class="btn btn-default" id="up-cancel-btn">
          <i class="fa fa-times"></i> Batal
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-cloud-arrow-up"></i> Upload
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════ MODAL VERIF ══════════ -->
<div id="vf-modal" class="bk-ov" style="z-index:100000;">
  <div class="vf-modal">
    <div class="up-mhead" style="background:linear-gradient(135deg,#065f46,#047857);">
      <i class="fa fa-shield-check" style="font-size:18px;"></i>
      <span class="up-mhead-title">Verifikasi Berkas</span>
      <button type="button" class="bk-mclose" id="vf-close-btn"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action"    value="verif_berkas">
      <input type="hidden" name="berkas_id" id="vf-berkas-id" value="">
      <div style="padding:18px;">
        <div style="font-size:13px;font-weight:600;color:#1e293b;margin-bottom:12px;"
             id="vf-berkas-name">—</div>
        <div class="up-fg">
          <label>Status</label>
          <select name="status_verif">
            <option value="terverifikasi">✅ Terverifikasi</option>
            <option value="ditolak">❌ Ditolak</option>
          </select>
        </div>
        <div class="up-fg">
          <label>Catatan <span style="font-size:10px;color:#9ca3af;">(opsional)</span></label>
          <textarea name="catatan_verif" rows="3" placeholder="Catatan jika ditolak…"></textarea>
        </div>
      </div>
      <div class="up-mft">
        <button type="button" class="btn btn-default" id="vf-cancel-btn">
          <i class="fa fa-times"></i> Batal
        </button>
        <button type="submit" class="btn btn-success">
          <i class="fa fa-check"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>

<script>
var MASTER_KAT = <?= json_encode(array_values($berkas_by_kat), JSON_UNESCAPED_UNICODE) ?>;
var TOTAL_REQ  = <?= $total_required ?>;
var APP_URL    = '<?= APP_URL ?>';
</script>
<script>
(function () {
'use strict';
var uid = 0;

function fmtSize(b) { return b < 1024 ? b + ' B' : b < 1048576 ? (b/1024).toFixed(1) + ' KB' : (b/1048576).toFixed(1) + ' MB'; }
function fmtDate(s) { if (!s) return '—'; return new Date(s).toLocaleDateString('id-ID', {day:'2-digit',month:'short',year:'numeric'}); }
function expCls(s)  { if (!s) return ''; var d = (new Date(s) - new Date()) / 86400000; return d < 0 ? 'exp-gone' : d < 30 ? 'exp-soon' : 'exp-ok'; }
function expLbl(s)  { if (!s) return ''; var d = Math.ceil((new Date(s) - new Date()) / 86400000); return d < 0 ? 'Expired' : d === 0 ? 'Hari ini!' : d < 30 ? d + ' hari lagi' : fmtDate(s); }
function esc(s)     { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

var bkMod  = document.getElementById('bk-modal');
var bkBody = document.getElementById('bk-mbody');

function openBk(u, nama, divisi) {
  uid = u;
  var words = nama.trim().split(/\s+/).filter(Boolean);
  document.getElementById('bk-av').textContent          = words.slice(0, 2).map(function (w) { return w[0].toUpperCase(); }).join('') || '?';
  document.getElementById('bk-nama-display').textContent = nama;
  document.getElementById('bk-sub-display').textContent  = divisi ? divisi + ' — Berkas Karyawan' : 'Berkas Karyawan';
  bkMod.classList.add('open');
  document.body.style.overflow = 'hidden';
  loadBerkas(u);
}
function closeBk() { bkMod.classList.remove('open'); document.body.style.overflow = ''; }
bkMod.addEventListener('click', function (e) { if (e.target === bkMod) closeBk(); });
document.getElementById('bk-close-btn').addEventListener('click', closeBk);
document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && bkMod.classList.contains('open')) closeBk(); });

function loadBerkas(u) {
  bkBody.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:200px;color:#9ca3af;gap:8px;"><i class="fa fa-spinner fa-spin"></i> Memuat data berkas…</div>';
  document.getElementById('bk-prog-fill').style.width = '0%';
  fetch('ajax_berkas.php?user_id=' + u, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(renderBerkas)
    .catch(function () { renderBerkas({}); });
}

function renderBerkas(data) {
  var reqUp = 0, html = '';
  MASTER_KAT.forEach(function (kat, ki) {
    var filled = 0, itemsHtml = '';
    kat.items.forEach(function (j) {
      var b  = data[j.id] || null;
      var ic = kat.warna || '#6366f1';
      if (b) {
        filled++;
        if (j.wajib) reqUp++;
        var cc = 'chip-pending', ci = 'fa-clock', cl = 'Menunggu';
        if (b.status_verif === 'terverifikasi') { cc = 'chip-ok';      ci = 'fa-check'; cl = 'Terverifikasi'; }
        if (b.status_verif === 'ditolak')       { cc = 'chip-ditolak'; ci = 'fa-times'; cl = 'Ditolak'; }
        var expH = b.tgl_exp ? ' <span class="bk-exp-tag ' + expCls(b.tgl_exp) + '"><i class="fa fa-clock"></i> ' + expLbl(b.tgl_exp) + '</span>' : '';
        var fUrl = APP_URL + '/uploads/berkas_karyawan/' + uid + '/' + b.nama_file;
        itemsHtml += '<div class="bk-item">' +
          '<div class="bk-item-icon" style="background:' + ic + '18;"><i class="fa ' + esc(j.icon) + '" style="color:' + ic + ';"></i></div>' +
          '<div class="bk-item-info">' +
            '<div class="bk-item-name">' + esc(j.nama) + (j.wajib ? ' <span class="bk-req-badge">WAJIB</span>' : '') +
              ' <span class="bk-status-chip ' + cc + '"><i class="fa ' + ci + '"></i> ' + cl + '</span>' + expH + '</div>' +
            '<div class="bk-item-meta"><i class="fa fa-file" style="font-size:9px;"></i> ' + esc(b.nama_asli) +
              ' &nbsp;·&nbsp; ' + fmtSize(b.ukuran) +
              (b.tgl_dokumen ? ' &nbsp;·&nbsp; Terbit: ' + fmtDate(b.tgl_dokumen) : '') +
              (b.keterangan  ? ' &nbsp;·&nbsp; ' + esc(b.keterangan) : '') + '</div>' +
          '</div>' +
          '<div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">' +
            '<a href="' + fUrl + '" target="_blank" class="bk-btn-view"><i class="fa fa-eye"></i> Lihat</a>' +
            '<button type="button" class="bk-btn-up btn-do-up"' +
              ' data-jid="' + j.id + '" data-jnm="' + esc(j.nama) + '"' +
              ' data-exp="' + j.has_exp + '" data-terbit="' + j.has_tgl_terbit + '"' +
              ' data-fmt="' + esc(j.format_file || 'pdf,jpg,jpeg,png') + '">' +
              '<i class="fa fa-arrow-up-from-bracket"></i> Ganti</button>' +
            (b.status_verif !== 'terverifikasi'
              ? '<button type="button" class="bk-btn-verif btn-do-verif" data-id="' + b.id + '" data-nm="' + esc(j.nama) + '"><i class="fa fa-shield-check"></i> Verif</button>'
              : '') +
            '<form method="POST" style="display:inline;" onsubmit="return confirm(\'Hapus berkas ini?\');">' +
              '<input type="hidden" name="action" value="hapus_berkas">' +
              '<input type="hidden" name="berkas_id" value="' + b.id + '">' +
              '<button type="submit" class="bk-btn-del"><i class="fa fa-trash"></i></button>' +
            '</form>' +
          '</div></div>';
      } else {
        itemsHtml += '<div class="bk-item">' +
          '<div class="bk-item-icon" style="background:#f1f5f9;"><i class="fa ' + esc(j.icon) + '" style="color:#9ca3af;"></i></div>' +
          '<div class="bk-item-info">' +
            '<div class="bk-item-name">' + esc(j.nama) + (j.wajib ? ' <span class="bk-req-badge">WAJIB</span>' : '') +
              ' <span class="bk-status-chip chip-kosong"><i class="fa fa-minus"></i> Belum ada</span></div>' +
            '<div class="bk-item-meta" style="color:#d1d5db;">' + (j.keterangan ? esc(j.keterangan) : 'Belum ada berkas') + '</div>' +
          '</div>' +
          '<div style="flex-shrink:0;">' +
            '<button type="button" class="bk-btn-up btn-do-up"' +
              ' data-jid="' + j.id + '" data-jnm="' + esc(j.nama) + '"' +
              ' data-exp="' + j.has_exp + '" data-terbit="' + j.has_tgl_terbit + '"' +
              ' data-fmt="' + esc(j.format_file || 'pdf,jpg,jpeg,png') + '">' +
              '<i class="fa fa-plus"></i> Upload</button>' +
          '</div></div>';
      }
    });
    var badgeS = filled === kat.items.length
      ? 'background:#dcfce7;color:#16a34a;'
      : filled > 0 ? 'background:#fef3c7;color:#d97706;' : 'background:#f1f5f9;color:#9ca3af;';
    html += '<div class="bk-cat' + (ki === 0 ? ' open' : '') + '">' +
      '<div class="bk-cat-hd">' +
        '<div class="bk-cat-icon" style="background:' + kat.warna + '18;">' +
          '<i class="fa ' + esc(kat.icon) + '" style="color:' + kat.warna + ';"></i>' +
        '</div>' +
        '<span class="bk-cat-title">' + esc(kat.nama) + '</span>' +
        '<span class="bk-cat-badge" style="' + badgeS + '">' + filled + '/' + kat.items.length + '</span>' +
        '<i class="fa fa-chevron-down bk-cat-arrow"></i>' +
      '</div>' +
      '<div class="bk-cat-body">' + itemsHtml + '</div>' +
    '</div>';
  });

  bkBody.innerHTML = html;
  var pct = TOTAL_REQ > 0 ? Math.min(100, Math.round(reqUp / TOTAL_REQ * 100)) : 0;
  document.getElementById('bk-prog-fill').style.width  = pct + '%';
  document.getElementById('bk-prog-text').textContent  = reqUp + ' / ' + TOTAL_REQ + ' berkas wajib (' + pct + '%)';

  bkBody.querySelectorAll('.bk-cat-hd').forEach(function (hd) {
    hd.addEventListener('click', function () { hd.closest('.bk-cat').classList.toggle('open'); });
  });
  bkBody.querySelectorAll('.btn-do-up').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openUp(uid, btn.dataset.jid, btn.dataset.jnm,
             btn.dataset.exp === '1', btn.dataset.terbit === '1', btn.dataset.fmt);
    });
  });
  bkBody.querySelectorAll('.btn-do-verif').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('vf-berkas-id').value   = btn.dataset.id;
      document.getElementById('vf-berkas-name').textContent = btn.dataset.nm;
      openVf();
    });
  });
}

// ── Upload modal ──────────────────────────────────────────────────────────────
var upMod = document.getElementById('up-modal');
var upForm = document.getElementById('up-form');
var upDrop = document.getElementById('up-drop');
var upFIn  = document.getElementById('up-file-input');

function openUp(u, jid, jnm, hasExp, hasTerbit, fmt) {
  upForm.reset();
  document.getElementById('up-file-prev').style.display  = 'none';
  upDrop.style.display = 'block';
  document.getElementById('up-user-id').value  = u;
  document.getElementById('up-jenis-id').value = jid;
  document.getElementById('up-modal-title').textContent   = jnm;
  document.getElementById('up-exp-wrap').style.display    = hasExp    ? 'block' : 'none';
  document.getElementById('up-terbit-wrap').style.display = hasTerbit ? 'block' : 'none';
  var fmts = (fmt || 'pdf,jpg,jpeg,png').split(',').map(function (f) { return '.' + f.trim(); }).join(',');
  upFIn.setAttribute('accept', fmts);
  document.getElementById('up-format-hint').textContent = 'Format: ' + fmt.toUpperCase() + ' — Maks. 10 MB';
  upMod.classList.add('open');
}
function closeUp() { upMod.classList.remove('open'); }
upMod.addEventListener('click',  function (e) { if (e.target === upMod) closeUp(); });
document.getElementById('up-close-btn').addEventListener('click',  closeUp);
document.getElementById('up-cancel-btn').addEventListener('click', closeUp);

upFIn.addEventListener('change', function () {
  if (upFIn.files[0]) {
    document.getElementById('up-file-name').textContent = upFIn.files[0].name;
    document.getElementById('up-file-size').textContent = fmtSize(upFIn.files[0].size);
    document.getElementById('up-file-prev').style.display = 'flex';
  }
});
upDrop.addEventListener('dragover',  function (e) { e.preventDefault(); upDrop.classList.add('drag'); });
upDrop.addEventListener('dragleave', function ()  { upDrop.classList.remove('drag'); });
upDrop.addEventListener('drop', function (e) {
  e.preventDefault(); upDrop.classList.remove('drag');
  if (e.dataTransfer.files[0]) {
    var dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]); upFIn.files = dt.files;
    document.getElementById('up-file-name').textContent    = e.dataTransfer.files[0].name;
    document.getElementById('up-file-size').textContent    = fmtSize(e.dataTransfer.files[0].size);
    document.getElementById('up-file-prev').style.display  = 'flex';
  }
});
upForm.addEventListener('submit', function (e) { if (!upFIn.files[0]) { e.preventDefault(); alert('Pilih file!'); } });

// ── Verif modal ───────────────────────────────────────────────────────────────
var vfMod = document.getElementById('vf-modal');
function openVf()  { vfMod.classList.add('open'); }
function closeVf() { vfMod.classList.remove('open'); }
vfMod.addEventListener('click', function (e) { if (e.target === vfMod) closeVf(); });
document.getElementById('vf-close-btn').addEventListener('click',  closeVf);
document.getElementById('vf-cancel-btn').addEventListener('click', closeVf);

// ── Delegation tombol kelola ───────────────────────────────────────────────────
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.btn-open-berkas');
  if (!btn) return;
  openBk(btn.dataset.uid, btn.dataset.nama, btn.dataset.divisi || '');
});

})();
</script>

<?php include '../includes/footer.php'; ?>