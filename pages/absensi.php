<?php
// pages/absensi.php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'hrd'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Absensi Karyawan';
$active_menu = 'absensi';

// ── Auto-create tabel absensi ─────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `absensi` (
      `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `user_id`         INT UNSIGNED NOT NULL,
      `jadwal_id`       INT UNSIGNED DEFAULT NULL,
      `shift_id`        INT UNSIGNED DEFAULT NULL,
      `tanggal`         DATE         NOT NULL,
      `jam_masuk`       TIME         DEFAULT NULL,
      `jam_keluar`      TIME         DEFAULT NULL,
      `lat_masuk`       DECIMAL(10,7) DEFAULT NULL,
      `lon_masuk`       DECIMAL(10,7) DEFAULT NULL,
      `lat_keluar`      DECIMAL(10,7) DEFAULT NULL,
      `lon_keluar`      DECIMAL(10,7) DEFAULT NULL,
      `status`          ENUM('hadir','terlambat','alpha','izin','cuti','dinas','libur','setengah_hari') NOT NULL DEFAULT 'hadir',
      `terlambat_menit` SMALLINT      DEFAULT 0,
      `pulang_awal_menit` SMALLINT    DEFAULT 0,
      `durasi_kerja`    SMALLINT      DEFAULT NULL COMMENT 'total menit kerja',
      `keterangan`      VARCHAR(255)  DEFAULT NULL,
      `input_oleh`      ENUM('self','admin','system') DEFAULT 'admin',
      `created_by`      INT UNSIGNED  DEFAULT NULL,
      `updated_by`      INT UNSIGNED  DEFAULT NULL,
      `created_at`      DATETIME      DEFAULT CURRENT_TIMESTAMP,
      `updated_at`      DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `uq_user_tgl` (`user_id`,`tanggal`),
      KEY `idx_tanggal` (`tanggal`),
      KEY `idx_user`    (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── Pastikan semua kolom ada untuk tabel yang sudah lama dibuat ──
$_absensi_alters = [
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `terlambat_menit`   SMALLINT     NOT NULL DEFAULT 0",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `pulang_awal_menit` SMALLINT     NOT NULL DEFAULT 0",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `durasi_kerja`      SMALLINT     DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `keterangan`        VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `foto_masuk`        VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `foto_keluar`       VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `device_info`       VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `lat_masuk`         DECIMAL(10,7) DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `lon_masuk`         DECIMAL(10,7) DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `lat_keluar`        DECIMAL(10,7) DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `lon_keluar`        DECIMAL(10,7) DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `input_oleh`        VARCHAR(10)  DEFAULT 'admin'",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `shift_id`          INT UNSIGNED DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `created_by`        INT UNSIGNED DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `updated_by`        INT UNSIGNED DEFAULT NULL",
];
foreach ($_absensi_alters as $_sql) {
    try { $pdo->exec($_sql); } catch(Exception $e) {}
}

// ── AJAX: Simpan satu record absensi ──────────────────────
if (isset($_POST['ajax_absen'])) {
    header('Content-Type: application/json');
    $uid     = (int)($_POST['user_id']  ?? 0);
    $tgl     = $_POST['tanggal'] ?? date('Y-m-d');
    $jam_in  = $_POST['jam_masuk']  !== '' ? ($_POST['jam_masuk']  ?: null) : null;
    $jam_out = $_POST['jam_keluar'] !== '' ? ($_POST['jam_keluar'] ?: null) : null;
    $status  = $_POST['status']   ?? 'hadir';
    $ket     = trim($_POST['keterangan'] ?? '') ?: null;

    if (!$uid) { echo json_encode(['ok'=>false,'msg'=>'User tidak valid']); exit; }

    // Cari jadwal & shift untuk hitung keterlambatan
    $terlambat = 0; $pulang_awal = 0; $durasi = null; $shift_id = null;
    try {
        $jd = $pdo->prepare("
            SELECT j.shift_id, ms.jam_masuk sh_masuk, ms.jam_keluar sh_keluar,
                   COALESCE(ms.toleransi_masuk,15) toleransi_masuk,
                   COALESCE(ms.toleransi_pulang,0) toleransi_pulang,
                   COALESCE(ms.lintas_hari,0) lintas_hari
            FROM jadwal_karyawan j
            LEFT JOIN master_shift ms ON ms.id = j.shift_id
            WHERE j.user_id = ? AND j.tanggal = ?
        ");
        $jd->execute([$uid, $tgl]);
        $jdw = $jd->fetch(PDO::FETCH_ASSOC);
        $shift_id = $jdw['shift_id'] ?? null;

        if ($jdw && $jam_in && !empty($jdw['sh_masuk'])) {
            $sch_in = strtotime($tgl . ' ' . $jdw['sh_masuk']);
            $act_in = strtotime($tgl . ' ' . $jam_in);
            $tol    = (int)$jdw['toleransi_masuk'];
            $diff   = ($act_in - $sch_in) / 60;
            if ($diff > $tol) { $terlambat = (int)$diff; $status = 'terlambat'; }
        }
        if ($jdw && $jam_out && !empty($jdw['sh_keluar'])) {
            $sch_out = strtotime($tgl . ' ' . $jdw['sh_keluar']);
            if ($jdw['lintas_hari']) $sch_out += 86400;
            $act_out = strtotime($tgl . ' ' . $jam_out);
            if (!empty($jdw['sh_masuk']) && $jam_out < $jdw['sh_masuk'] && $jdw['lintas_hari']) $act_out += 86400;
            $diff_out = ($sch_out - $act_out) / 60;
            if ($diff_out > $jdw['toleransi_pulang']) $pulang_awal = (int)$diff_out;
        }
        if ($jam_in && $jam_out) {
            $ti = strtotime($tgl . ' ' . $jam_in);
            $to = strtotime($tgl . ' ' . $jam_out);
            if ($to < $ti) $to += 86400;
            $durasi = (int)(($to - $ti) / 60);
        }
    } catch (Exception $e) { $shift_id = null; }

    try {
        $ex = $pdo->prepare("SELECT id FROM absensi WHERE user_id=? AND tanggal=?");
        $ex->execute([$uid,$tgl]);
        $eid = $ex->fetchColumn();

        if ($eid) {
            $pdo->prepare("UPDATE absensi SET jam_masuk=?,jam_keluar=?,status=?,terlambat_menit=?,pulang_awal_menit=?,durasi_kerja=?,keterangan=?,updated_by=?,shift_id=? WHERE id=?")
                ->execute([$jam_in,$jam_out,$status,$terlambat,$pulang_awal,$durasi,$ket,$_SESSION['user_id'],$shift_id,$eid]);
        } else {
            $pdo->prepare("INSERT INTO absensi (user_id,tanggal,jam_masuk,jam_keluar,status,terlambat_menit,pulang_awal_menit,durasi_kerja,keterangan,input_oleh,created_by,shift_id) VALUES (?,?,?,?,?,?,?,?,?,'admin',?,?)")
                ->execute([$uid,$tgl,$jam_in,$jam_out,$status,$terlambat,$pulang_awal,$durasi,$ket,$_SESSION['user_id'],$shift_id]);
        }
        echo json_encode(['ok'=>true,'msg'=>'Absensi disimpan','status'=>$status,'terlambat'=>$terlambat]);
    } catch(Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Hapus absensi ───────────────────────────────────
if (isset($_POST['ajax_hapus_absen'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if ($id) { $pdo->prepare("DELETE FROM absensi WHERE id=?")->execute([$id]); }
    echo json_encode(['ok'=>true]);
    exit;
}

// ── Parameter tanggal ─────────────────────────────────────
$tgl_view   = $_GET['tgl']    ?? date('Y-m-d');
$bagian_id  = (int)($_GET['bagian'] ?? 0);
$f_status   = $_GET['status'] ?? '';

// Validasi tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_view)) $tgl_view = date('Y-m-d');

$hari_ini = date('Y-m-d');
$prev_tgl = date('Y-m-d', strtotime($tgl_view.' -1 day'));
$next_tgl = date('Y-m-d', strtotime($tgl_view.' +1 day'));

// ── Fetch data ────────────────────────────────────────────
$bagian_list = [];
try { $bagian_list = $pdo->query("SELECT id,nama FROM bagian WHERE status='aktif' ORDER BY urutan,nama")->fetchAll(); } catch(Exception $e){}

// Whitelist status agar aman
$allowed_status = ['hadir','terlambat','alpha','izin','cuti','dinas','libur','setengah_hari','belum'];
if ($f_status && !in_array($f_status, $allowed_status)) $f_status = '';

$where_bag  = $bagian_id ? "AND u.bagian_id=" . (int)$bagian_id : '';
$where_st   = ($f_status && $f_status !== 'belum') ? "AND a.status=" . $pdo->quote($f_status) : '';
$where_belum= ($f_status === 'belum') ? "AND a.id IS NULL" : '';

// Auto-create tabel pendukung jika belum ada agar tidak fatal error
try { $pdo->exec("CREATE TABLE IF NOT EXISTS `jadwal_karyawan` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `user_id` INT UNSIGNED NOT NULL, `shift_id` INT UNSIGNED DEFAULT NULL, `tanggal` DATE NOT NULL, `tipe` ENUM('shift','libur','cuti','dinas','izin','kosong') NOT NULL DEFAULT 'shift', `keterangan` VARCHAR(100) DEFAULT NULL, `created_by` INT UNSIGNED DEFAULT NULL, `updated_by` INT UNSIGNED DEFAULT NULL, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY `uq_user_tanggal` (`user_id`,`tanggal`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
try { $pdo->exec("CREATE TABLE IF NOT EXISTS `master_shift` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `kode` VARCHAR(10) NOT NULL, `nama` VARCHAR(80) NOT NULL, `jam_masuk` TIME NOT NULL DEFAULT '08:00:00', `jam_keluar` TIME NOT NULL DEFAULT '16:00:00', `lintas_hari` TINYINT(1) NOT NULL DEFAULT 0, `toleransi_masuk` SMALLINT NOT NULL DEFAULT 15, `toleransi_pulang` SMALLINT NOT NULL DEFAULT 0, `durasi_istirahat` SMALLINT NOT NULL DEFAULT 60, `warna` VARCHAR(7) NOT NULL DEFAULT '#6366f1', `jenis` VARCHAR(20) NOT NULL DEFAULT 'reguler', `berlaku_untuk` VARCHAR(30) NOT NULL DEFAULT 'semua', `deskripsi` VARCHAR(255) DEFAULT NULL, `status` VARCHAR(10) NOT NULL DEFAULT 'aktif', `urutan` SMALLINT NOT NULL DEFAULT 0, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}

$karyawan_data = [];
try {
    $sql = "
        SELECT u.id, u.nama, u.divisi, u.role,
               COALESCE(s.gelar_depan,'')    gelar_depan,
               COALESCE(s.nik_rs,'')         nik_rs,
               COALESCE(s.jenis_karyawan,'') jenis_karyawan,
               j.shift_id,
               j.tipe                        jdw_tipe,
               ms.kode                       shift_kode,
               ms.warna                      shift_warna,
               ms.nama                       shift_nama,
               ms.jam_masuk                  sch_masuk,
               ms.jam_keluar                 sch_keluar,
               ms.toleransi_masuk,
               a.id                          absen_id,
               a.jam_masuk,
               a.jam_keluar,
               a.status                      absen_status,
               a.terlambat_menit,
               a.pulang_awal_menit,
               a.durasi_kerja,
               a.keterangan
        FROM users u
        LEFT JOIN sdm_karyawan s    ON s.user_id  = u.id
        LEFT JOIN jadwal_karyawan j ON j.user_id  = u.id  AND j.tanggal = :tgl1
        LEFT JOIN master_shift ms   ON ms.id      = j.shift_id
        LEFT JOIN absensi a         ON a.user_id  = u.id  AND a.tanggal = :tgl2
        WHERE u.status = 'aktif'
        $where_bag $where_st $where_belum
        ORDER BY u.divisi, u.nama
    ";
    $stm = $pdo->prepare($sql);
    $stm->execute([':tgl1' => $tgl_view, ':tgl2' => $tgl_view]);
    $karyawan_data = $stm->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Jika query gagal (misal tabel belum lengkap), fallback ke query minimal
    try {
        $stm2 = $pdo->prepare("SELECT u.id, u.nama, u.divisi, u.role, '' gelar_depan, '' nik_rs, '' jenis_karyawan, NULL shift_id, NULL jdw_tipe, NULL shift_kode, NULL shift_warna, NULL shift_nama, NULL sch_masuk, NULL sch_keluar, NULL toleransi_masuk, NULL absen_id, NULL jam_masuk, NULL jam_keluar, NULL absen_status, 0 terlambat_menit, 0 pulang_awal_menit, NULL durasi_kerja, NULL keterangan FROM users u WHERE u.status='aktif' $where_bag ORDER BY u.divisi, u.nama");
        $stm2->execute();
        $karyawan_data = $stm2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) { $karyawan_data = []; }
}

// ── Summary ───────────────────────────────────────────────
$sm = ['total'=>count($karyawan_data),'hadir'=>0,'terlambat'=>0,'alpha'=>0,'izin'=>0,'cuti'=>0,'libur'=>0,'belum'=>0];
foreach ($karyawan_data as $k) {
    $st = $k['absen_status'] ?? null;
    if (!$st && $k['jdw_tipe'] === 'libur') $st = 'libur';
    if (!$st) { $sm['belum']++; }
    else if (isset($sm[$st])) $sm[$st]++;
    else $sm['hadir']++;
}

$nama_hari_id = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$nama_bulan_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$hari_label = $nama_hari_id[date('l',strtotime($tgl_view))] . ', ' . (int)date('j',strtotime($tgl_view)) . ' ' . $nama_bulan_id[(int)date('n',strtotime($tgl_view))] . ' ' . date('Y',strtotime($tgl_view));

include '../includes/header.php';
?>

<style>
* { box-sizing: border-box; }
.ab { font-family: 'Inter','Segoe UI',sans-serif; color: #1e293b; }

/* DATE NAV */
.ab-date-nav {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 12px 16px; margin-bottom: 14px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.ab-nav-btn {
    width: 32px; height: 32px; border-radius: 7px;
    border: 1px solid #e2e8f0; background: #f8fafc;
    display: flex; align-items: center; justify-content: center;
    color: #64748b; text-decoration: none; font-size: 12px; transition: all .12s;
}
.ab-nav-btn:hover { background: #e2e8f0; color: #1e293b; }
.ab-date-label {
    font-size: 14px; font-weight: 800; color: #0f172a;
    display: flex; align-items: center; gap: 8px;
}
.ab-date-label .today-dot { width: 8px; height: 8px; border-radius: 50%; background: #00c896; }
.ab-date-inp {
    height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 7px;
    font-size: 12px; font-family: inherit; color: #1e293b;
    background: #f8fafc; outline: none; cursor: pointer;
    transition: border-color .15s;
}
.ab-date-inp:focus { border-color: #00c896; background: #fff; }

/* SUMMARY STRIP */
.ab-summary {
    display: grid; grid-template-columns: repeat(auto-fill,minmax(110px,1fr));
    gap: 10px; margin-bottom: 14px;
}
.ab-sm-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 9px;
    padding: 11px 14px; cursor: pointer; transition: box-shadow .12s, transform .12s;
}
.ab-sm-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.07); transform: translateY(-1px); }
.ab-sm-card.active { box-shadow: 0 0 0 2px #00c896; }
.ab-sm-num { font-size: 22px; font-weight: 800; line-height: 1; font-family: 'JetBrains Mono',monospace; }
.ab-sm-lbl { font-size: 10.5px; font-weight: 600; color: #94a3b8; margin-top: 3px; }
.ab-sm-bar { height: 3px; border-radius: 9px; margin-top: 6px; opacity: .6; }

/* FILTER */
.ab-filter { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 12px; }
.ab-filter select {
    height: 32px; padding: 0 10px; border: 1.5px solid #e2e8f0; border-radius: 7px;
    background: #fff; font-size: 12px; font-family: inherit; outline: none;
    transition: border-color .15s; min-width: 150px;
}
.ab-filter select:focus { border-color: #00c896; }
.ab-filter label { font-size: 11px; font-weight: 600; color: #64748b; }

/* TABLE */
.ab-table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.ab-table { width: 100%; border-collapse: collapse; }
.ab-table th {
    padding: 10px 13px; background: #fafbfc; border-bottom: 2px solid #e2e8f0;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b;
    white-space: nowrap; text-align: left;
}
.ab-table td {
    padding: 9px 13px; border-bottom: 1px solid #f0f4f8;
    font-size: 12.5px; vertical-align: middle;
}
.ab-table tr:last-child td { border-bottom: none; }
.ab-table tr:hover td { background: #fafbfc; }
.ab-table tr.divisi-sep td {
    padding: 5px 13px; background: #f8fafc;
    border-bottom: 1px solid #e8ecf2;
    font-size: 10px; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: .6px;
}

/* Avatar */
.ab-av {
    width: 30px; height: 30px; border-radius: 50%;
    background: linear-gradient(135deg,#00e5b0,#00c896);
    color: #0a0f14; font-size: 9px; font-weight: 800;
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* Status badge */
.ab-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 5px;
    font-size: 10.5px; font-weight: 700;
}
.st-hadir       { background: #dcfce7; color: #15803d; }
.st-terlambat   { background: #fef3c7; color: #a16207; }
.st-alpha       { background: #fee2e2; color: #b91c1c; }
.st-izin        { background: #fff7ed; color: #c2410c; }
.st-cuti        { background: #faf5ff; color: #6d28d9; }
.st-dinas       { background: #e0f2fe; color: #0369a1; }
.st-libur       { background: #f1f5f9; color: #475569; }
.st-belum       { background: #f8fafc; color: #94a3b8; border: 1px dashed #e2e8f0; }
.st-setengah_hari { background: #fef9c3; color: #a16207; }

/* Jam input inline */
.ab-jam-row { display: flex; align-items: center; gap: 5px; }
.ab-jam-inp {
    width: 72px; height: 28px; padding: 0 6px; border: 1.5px solid #e2e8f0;
    border-radius: 6px; font-size: 11.5px; font-family: 'JetBrains Mono',monospace;
    background: #f8fafc; color: #1e293b; outline: none; transition: border-color .15s;
}
.ab-jam-inp:focus { border-color: #00c896; background: #fff; }
.ab-jam-sep { color: #94a3b8; font-size: 11px; }

/* Shift pill kecil */
.ab-shift-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 5px; font-size: 10.5px; font-weight: 700;
    color: #fff;
}
.ab-shift-none { color: #94a3b8; font-size: 11px; font-style: italic; }

/* Terlambat badge */
.ab-late { font-size: 10px; font-weight: 700; color: #a16207; background: #fef3c7; padding: 1px 6px; border-radius: 4px; margin-left: 4px; }
.ab-early { font-size: 10px; font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 1px 6px; border-radius: 4px; margin-left: 4px; }
.ab-durasi { font-size: 10px; color: #64748b; font-family: monospace; }

/* Action buttons */
.ab-btn-save {
    padding: 4px 10px; border-radius: 6px; background: #0d1b2e; color: #fff;
    border: none; font-size: 11px; font-weight: 600; cursor: pointer;
    font-family: inherit; transition: background .12s; display: inline-flex; align-items: center; gap: 4px;
}
.ab-btn-save:hover { background: #1a3a5c; }
.ab-btn-del {
    padding: 4px 8px; border-radius: 6px; background: #fee2e2; color: #b91c1c;
    border: none; font-size: 11px; cursor: pointer; font-family: inherit; transition: background .12s;
}
.ab-btn-del:hover { background: #fecaca; }

/* Status select */
.ab-st-sel {
    height: 28px; padding: 0 6px; border: 1.5px solid #e2e8f0; border-radius: 6px;
    font-size: 11px; font-family: inherit; background: #f8fafc; outline: none;
    transition: border-color .15s; min-width: 90px; color: #1e293b;
}
.ab-st-sel:focus { border-color: #00c896; background: #fff; }

/* Toast */
.ab-toast {
    position: fixed; bottom: 20px; right: 20px;
    background: #0d1b2e; color: #fff; padding: 10px 16px; border-radius: 8px;
    font-size: 12px; font-weight: 600; z-index: 999999;
    display: none; align-items: center; gap: 7px;
    box-shadow: 0 4px 20px rgba(0,0,0,.2); max-width: 280px;
    animation: abToastIn .2s ease;
}
@keyframes abToastIn { from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:none;} }
.ab-toast.ok  i { color: #00c896; }
.ab-toast.err i { color: #ef4444; }

/* Bulk toolbar */
.ab-bulk {
    background: #0d1b2e; border-radius: 8px; padding: 8px 14px;
    display: none; align-items: center; gap: 10px;
    margin-bottom: 10px; flex-wrap: wrap;
}
.ab-bulk.show { display: flex; }
.ab-bulk-label { font-size: 12px; font-weight: 700; color: #fff; }
.ab-bulk-btn {
    padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 700;
    border: none; cursor: pointer; font-family: inherit; transition: opacity .12s;
}
.ab-bulk-btn:hover { opacity: .85; }

/* Print area hidden */
@media print {
    .ab-date-nav,.ab-filter,.ab-bulk,.ab-btn-save,.ab-btn-del,.ab-jam-inp,.ab-st-sel,.no-print { display:none!important; }
    body { background: #fff!important; }
    .ab-table-wrap { box-shadow: none!important; border: 1px solid #ccc!important; }
}
</style>

<div class="page-header">
    <h4><i class="fa fa-fingerprint text-primary"></i> &nbsp;Absensi Karyawan</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
        <span class="cur">Absensi</span>
    </div>
</div>

<div class="content ab">
    <?= showFlash() ?>

    <!-- DATE NAV -->
    <div class="ab-date-nav">
        <a href="?tgl=<?= $prev_tgl ?>&bagian=<?= $bagian_id ?>" class="ab-nav-btn"><i class="fa fa-chevron-left"></i></a>
        <div class="ab-date-label">
            <?php if ($tgl_view === $hari_ini): ?><span class="today-dot"></span><?php endif; ?>
            <?= $hari_label ?>
        </div>
        <a href="?tgl=<?= $next_tgl ?>&bagian=<?= $bagian_id ?>" class="ab-nav-btn"><i class="fa fa-chevron-right"></i></a>
        <input type="date" class="ab-date-inp" id="ab-date-picker" value="<?= $tgl_view ?>" max="<?= date('Y-m-d') ?>">
        <a href="?tgl=<?= $hari_ini ?>&bagian=<?= $bagian_id ?>" class="btn btn-sm btn-default no-print" style="height:32px;display:inline-flex;align-items:center;gap:4px;">
            <i class="fa fa-crosshairs"></i> Hari Ini
        </a>
        <div style="margin-left:auto;display:flex;gap:6px;" class="no-print">
            <a href="laporan_absensi.php?bulan=<?= date('n',strtotime($tgl_view)) ?>&tahun=<?= date('Y',strtotime($tgl_view)) ?>" class="btn btn-sm btn-default" style="height:32px;display:inline-flex;align-items:center;gap:4px;">
                <i class="fa fa-chart-bar"></i> Laporan Bulanan
            </a>
            <button onclick="window.print()" class="btn btn-sm btn-default" style="height:32px;display:inline-flex;align-items:center;gap:4px;">
                <i class="fa fa-print"></i> Cetak
            </button>
        </div>
    </div>

    <!-- SUMMARY -->
    <div class="ab-summary">
        <?php foreach ([
            ['Total',    $sm['total'],    '#0f172a','#e2e8f0', ''],
            ['Hadir',    $sm['hadir'],    '#15803d','#00c896', 'hadir'],
            ['Terlambat',$sm['terlambat'],'#a16207','#f59e0b', 'terlambat'],
            ['Alpha',    $sm['alpha'],    '#b91c1c','#ef4444', 'alpha'],
            ['Izin',     $sm['izin'],     '#c2410c','#f97316', 'izin'],
            ['Cuti',     $sm['cuti'],     '#6d28d9','#8b5cf6', 'cuti'],
            ['Libur',    $sm['libur'],    '#475569','#94a3b8', 'libur'],
            ['Belum',    $sm['belum'],    '#64748b','#cbd5e1', 'belum'],
        ] as [$lbl,$num,$tc,$bc,$stkey]): ?>
        <div class="ab-sm-card <?= $f_status===$stkey&&$stkey?'active':'' ?>"
             onclick="filterStatus('<?= $stkey ?>')" style="cursor:<?= $stkey?'pointer':'default' ?>;">
            <div class="ab-sm-num" style="color:<?= $tc ?>;"><?= $num ?></div>
            <div class="ab-sm-lbl"><?= $lbl ?></div>
            <div class="ab-sm-bar" style="background:<?= $bc ?>;"></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FILTER -->
    <div class="ab-filter no-print">
        <label><i class="fa fa-building" style="font-size:10px;"></i> Unit:</label>
        <select id="fil-bag" onchange="goFilter()">
            <option value="0">Semua Unit</option>
            <?php foreach($bagian_list as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $bagian_id==$b['id']?'selected':''?>><?= htmlspecialchars($b['nama']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" id="fil-status" value="<?= htmlspecialchars($f_status) ?>">
    </div>

    <!-- BULK TOOLBAR -->
    <div class="ab-bulk" id="ab-bulk">
        <span class="ab-bulk-label" id="bulk-count">0 dipilih</span>
        <?php foreach([
            ['hadir','#10b981','Hadir'],['terlambat','#f59e0b','Terlambat'],
            ['alpha','#ef4444','Alpha'],['izin','#f97316','Izin'],
            ['cuti','#8b5cf6','Cuti'],['libur','#94a3b8','Libur'],
        ] as [$sv,$sc,$sl]): ?>
        <button class="ab-bulk-btn" style="background:<?=$sc?>;color:#fff;" onclick="bulkStatus('<?=$sv?>')">
            <?=$sl?>
        </button>
        <?php endforeach; ?>
        <button class="ab-bulk-btn" style="background:#475569;color:#fff;" onclick="clearSelection()">Batal</button>
    </div>

    <!-- TABLE -->
    <div class="ab-table-wrap">
        <table class="ab-table" id="ab-table">
            <thead>
                <tr>
                    <th class="no-print" style="width:36px;">
                        <input type="checkbox" id="cb-all" onclick="toggleAll(this)" title="Pilih semua">
                    </th>
                    <th>Karyawan</th>
                    <th>Shift</th>
                    <th>Jam Masuk</th>
                    <th>Jam Keluar</th>
                    <th>Durasi</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                    <th class="no-print" style="width:100px;">Aksi</th>
                </tr>
            </thead>
            <tbody id="ab-tbody">
            <?php
            $cur_div = null;
            foreach ($karyawan_data as $k):
                if ($k['divisi'] !== $cur_div):
                    $cur_div = $k['divisi'];
            ?>
            <tr class="divisi-sep">
                <td class="no-print"></td>
                <td colspan="8"><i class="fa fa-building" style="font-size:9px;margin-right:4px;"></i><?= htmlspecialchars($k['divisi'] ?: 'Tanpa Divisi') ?></td>
            </tr>
            <?php endif;
                $ws = array_filter(explode(' ',$k['nama']));
                $init = strtoupper(implode('',array_map(fn($w)=>mb_substr($w,0,1),array_slice(array_values($ws),0,2))));
                $absen_st = $k['absen_status'] ?? null;
                if (!$absen_st && $k['jdw_tipe'] === 'libur') $absen_st = 'libur';
                $display_st = $absen_st ?: 'belum';
                $st_labels = ['hadir'=>'Hadir','terlambat'=>'Terlambat','alpha'=>'Alpha','izin'=>'Izin','cuti'=>'Cuti','dinas'=>'Dinas','libur'=>'Libur','setengah_hari'=>'½ Hari','belum'=>'Belum'];
            ?>
            <tr data-uid="<?= $k['id'] ?>" data-absen-id="<?= $k['absen_id'] ?: '' ?>">
                <td class="no-print">
                    <input type="checkbox" class="cb-row" data-uid="<?= $k['id'] ?>" onchange="updateBulk()">
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="ab-av"><?= $init ?></div>
                        <div>
                            <div style="font-weight:700;font-size:12.5px;">
                                <?= ($k['gelar_depan']?htmlspecialchars($k['gelar_depan']).' ':'').htmlspecialchars($k['nama']) ?>
                            </div>
                            <div style="font-size:10px;color:#94a3b8;">
                                <?= $k['nik_rs']?htmlspecialchars($k['nik_rs']):'—' ?>
                                <?php if ($k['jenis_karyawan']): ?>· <?= htmlspecialchars($k['jenis_karyawan']) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if ($k['shift_id']): ?>
                    <span class="ab-shift-pill" style="background:<?= htmlspecialchars($k['shift_warna']??'#64748b') ?>;">
                        <?= htmlspecialchars($k['shift_kode']??'') ?>
                        <span style="font-weight:400;font-size:9px;"><?= substr($k['sch_masuk']??'',0,5) ?>–<?= substr($k['sch_keluar']??'',0,5) ?></span>
                    </span>
                    <?php elseif ($k['jdw_tipe']): ?>
                    <span style="font-size:10.5px;color:#64748b;font-weight:600;background:#f1f5f9;padding:2px 7px;border-radius:4px;"><?= ucfirst($k['jdw_tipe']) ?></span>
                    <?php else: ?>
                    <span class="ab-shift-none">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="ab-jam-row">
                        <input type="time" class="ab-jam-inp jam-masuk"
                               value="<?= $k['jam_masuk'] ? substr($k['jam_masuk'],0,5) : '' ?>"
                               data-uid="<?= $k['id'] ?>" placeholder="--:--">
                        <?php if ($k['terlambat_menit'] > 0): ?>
                        <span class="ab-late">+<?= $k['terlambat_menit'] ?>m</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="ab-jam-row">
                        <input type="time" class="ab-jam-inp jam-keluar"
                               value="<?= $k['jam_keluar'] ? substr($k['jam_keluar'],0,5) : '' ?>"
                               data-uid="<?= $k['id'] ?>" placeholder="--:--">
                        <?php if ($k['pulang_awal_menit'] > 0): ?>
                        <span class="ab-early">-<?= $k['pulang_awal_menit'] ?>m</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php if ($k['durasi_kerja']): ?>
                    <span class="ab-durasi"><?= floor($k['durasi_kerja']/60) ?>j <?= $k['durasi_kerja']%60 ?>m</span>
                    <?php else: ?>
                    <span style="color:#e2e8f0;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <select class="ab-st-sel st-select" data-uid="<?= $k['id'] ?>">
                        <?php foreach(['hadir','terlambat','alpha','izin','cuti','dinas','libur','setengah_hari'] as $sv): ?>
                        <option value="<?=$sv?>" <?=$display_st===$sv?'selected':''?>><?=$st_labels[$sv]?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="text" class="ab-jam-inp ket-inp" style="width:110px;"
                           value="<?= htmlspecialchars($k['keterangan']??'') ?>"
                           data-uid="<?= $k['id'] ?>" placeholder="Keterangan…">
                </td>
                <td class="no-print" style="white-space:nowrap;">
                    <button class="ab-btn-save" onclick="saveRow(<?= $k['id'] ?>, this)">
                        <i class="fa fa-save"></i> Simpan
                    </button>
                    <?php if ($k['absen_id']): ?>
                    <button class="ab-btn-del" onclick="delRow(<?= $k['absen_id'] ?>, this)" title="Hapus"><i class="fa fa-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($karyawan_data)): ?>
            <tr><td colspan="9" style="padding:40px;text-align:center;color:#94a3b8;font-size:12px;">
                <i class="fa fa-users" style="font-size:22px;display:block;margin-bottom:8px;"></i>
                Tidak ada karyawan ditemukan
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<div class="ab-toast" id="ab-toast"><i></i><span></span></div>

<script>
const APP_URL = '<?= APP_URL ?>';
const TGL_VIEW = '<?= $tgl_view ?>';

/* ── Date picker ── */
document.getElementById('ab-date-picker').onchange = function(){
    var url = new URL(location.href);
    url.searchParams.set('tgl', this.value);
    location.href = url.toString();
};

/* ── Filter ── */
function filterStatus(st) {
    document.getElementById('fil-status').value = st;
    goFilter();
}
function goFilter() {
    var bag = document.getElementById('fil-bag').value;
    var st  = document.getElementById('fil-status').value;
    var url = new URL(location.href);
    url.searchParams.set('bagian', bag);
    url.searchParams.set('status', st);
    url.searchParams.set('tgl', TGL_VIEW);
    location.href = url.toString();
}

/* ── Simpan baris ── */
function saveRow(uid, btn) {
    var row = btn.closest('tr');
    var jm  = row.querySelector('.jam-masuk').value;
    var jk  = row.querySelector('.jam-keluar').value;
    var st  = row.querySelector('.st-select').value;
    var ket = row.querySelector('.ket-inp').value;

    var fd = new FormData();
    fd.append('ajax_absen','1');
    fd.append('user_id',    uid);
    fd.append('tanggal',    TGL_VIEW);
    fd.append('jam_masuk',  jm);
    fd.append('jam_keluar', jk);
    fd.append('status',     st);
    fd.append('keterangan', ket);

    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    fetch(location.pathname + location.search, { method:'POST', body:fd, credentials:'same-origin' })
        .then(r=>r.json()).then(function(d){
            btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> Simpan';
            if (d.ok) {
                showToast(d.msg + (d.terlambat > 0 ? ' · Terlambat '+d.terlambat+'m':''), true);
                row.style.background = '#f0fdf9';
                setTimeout(function(){ row.style.background=''; }, 1200);
            } else showToast(d.msg||'Gagal', false);
        }).catch(function(){ btn.disabled=false; btn.innerHTML='<i class="fa fa-save"></i> Simpan'; showToast('Kesalahan jaringan',false); });
}

/* ── Hapus baris ── */
function delRow(absenId, btn) {
    if (!confirm('Hapus data absensi ini?')) return;
    var fd = new FormData();
    fd.append('ajax_hapus_absen','1'); fd.append('id', absenId);
    fetch(location.pathname + location.search, {method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(function(d){
            if (d.ok) { showToast('Absensi dihapus',true); setTimeout(function(){ location.reload(); },600); }
        });
}

/* ── Checkbox & bulk ── */
function toggleAll(cb) {
    document.querySelectorAll('.cb-row').forEach(function(c){ c.checked = cb.checked; });
    updateBulk();
}
function updateBulk() {
    var sel = document.querySelectorAll('.cb-row:checked');
    var bulk = document.getElementById('ab-bulk');
    bulk.classList.toggle('show', sel.length > 0);
    document.getElementById('bulk-count').textContent = sel.length + ' dipilih';
}
function clearSelection() {
    document.querySelectorAll('.cb-row').forEach(function(c){ c.checked=false; });
    document.getElementById('cb-all').checked = false;
    updateBulk();
}
function bulkStatus(st) {
    var sel = document.querySelectorAll('.cb-row:checked');
    if (!sel.length) return;
    var promises = [];
    sel.forEach(function(cb){
        var uid = cb.dataset.uid;
        var row = cb.closest('tr');
        var jm  = row ? row.querySelector('.jam-masuk').value : '';
        var jk  = row ? row.querySelector('.jam-keluar').value : '';
        var ket = row ? row.querySelector('.ket-inp').value : '';
        var fd  = new FormData();
        fd.append('ajax_absen','1');
        fd.append('user_id',uid); fd.append('tanggal',TGL_VIEW);
        fd.append('jam_masuk',jm); fd.append('jam_keluar',jk);
        fd.append('status',st); fd.append('keterangan',ket);
        promises.push(fetch(location.pathname+location.search,{method:'POST',body:fd,credentials:'same-origin'}));
    });
    Promise.all(promises).then(function(){
        showToast(sel.length+' absensi diperbarui ke: '+st, true);
        clearSelection();
        setTimeout(function(){ location.reload(); }, 800);
    });
}

/* ── Toast ── */
function showToast(msg, ok) {
    var t = document.getElementById('ab-toast');
    t.className = 'ab-toast ' + (ok?'ok':'err');
    t.querySelector('i').className = ok ? 'fa fa-circle-check' : 'fa fa-circle-xmark';
    t.querySelector('span').textContent = msg;
    t.style.display = 'flex';
    clearTimeout(t._tmr);
    t._tmr = setTimeout(function(){ t.style.display='none'; }, 2800);
}
</script>

<?php include '../includes/footer.php'; ?>