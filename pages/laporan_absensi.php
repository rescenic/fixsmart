<?php
// pages/laporan_absensi.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config.php';
requireLogin();

// ── Hak akses ─────────────────────────────────────────────────────────────────
// Admin & HRD: lihat semua karyawan
// Role lain (user, teknisi, teknisi_ipsrs): hanya bisa lihat data diri sendiri
$_cur_role   = $_SESSION['user_role'] ?? 'user';
$_uid_session = (int)($_SESSION['user_id'] ?? 0);
$is_manager  = in_array($_cur_role, ['admin', 'hrd']);

// Kalau bukan admin/hrd, paksa redirect ke rekap diri sendiri
// (tetap izinkan akses, tapi kunci ke user_id sendiri)
if (!$is_manager) {
    // Tidak perlu redirect — kita filter di query
    $force_user_id = $_uid_session;
} else {
    // Admin/HRD: boleh lihat user_id tertentu atau semua
    $force_user_id = 0; // 0 = tidak dipaksa
}

$page_title  = 'Laporan Absensi';
$active_menu = $is_manager ? 'laporan_absen' : 'laporan_absen_saya';

// ── Parameter ─────────────────────────────────────────────────────────────────
$bulan     = (int)($_GET['bulan']  ?? date('n'));
$tahun     = (int)($_GET['tahun']  ?? date('Y'));
$bagian_id = $is_manager ? (int)($_GET['bagian'] ?? 0) : 0; // non-manager tidak bisa filter bagian
$f_nama    = $is_manager ? trim($_GET['nama'] ?? '') : '';   // non-manager tidak bisa cari nama

// Filter user_id:
// - non-manager: selalu diri sendiri
// - admin/hrd: bisa ?user_id=X untuk lihat 1 orang, atau 0 untuk semua
$filter_user_id = $force_user_id > 0
    ? $force_user_id
    : (int)($_GET['user_id'] ?? 0);

$view_mode = in_array($_GET['view'] ?? '', ['rekap','kalender','detail','jam'])
    ? $_GET['view'] : 'rekap';

if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
if ($tahun < 2020 || $tahun > 2040) $tahun = (int)date('Y');

$tgl_awal  = sprintf('%04d-%02d-01', $tahun, $bulan);
$tgl_akhir = date('Y-m-t', strtotime($tgl_awal));
$jml_hari  = (int)date('t', strtotime($tgl_awal));

$prev_b = $bulan - 1; $prev_t = $tahun; if ($prev_b < 1)  { $prev_b = 12; $prev_t--; }
$next_b = $bulan + 1; $next_t = $tahun; if ($next_b > 12) { $next_b = 1;  $next_t++; }

$nama_bulan       = ['','Januari','Februari','Maret','April','Mei','Juni',
                     'Juli','Agustus','September','Oktober','November','Desember'];
$nama_hari_pendek = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'];

// ── Pastikan kolom absensi lengkap ───────────────────────────────────────────
$_la_alters = [
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `terlambat_menit`   SMALLINT NOT NULL DEFAULT 0",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `pulang_awal_menit` SMALLINT NOT NULL DEFAULT 0",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `durasi_kerja`      SMALLINT DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `foto_masuk`        VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `absensi` ADD COLUMN IF NOT EXISTS `foto_keluar`       VARCHAR(255) DEFAULT NULL",
];
foreach ($_la_alters as $_s) { try { $pdo->exec($_s); } catch(Exception $e){} }

// ── Fetch bagian (hanya untuk manager) ───────────────────────────────────────
$bagian_list = [];
if ($is_manager) {
    try {
        $bagian_list = $pdo->query("SELECT id,nama FROM bagian WHERE status='aktif' ORDER BY urutan,nama")->fetchAll();
    } catch(Exception $e){}
}

// ── Fetch karyawan ────────────────────────────────────────────────────────────
// Non-manager: hanya ambil data diri sendiri
// Manager dengan filter user_id: ambil 1 orang
// Manager tanpa filter: ambil semua (dengan filter bagian/nama jika ada)

$karyawan = [];
try {
    if ($filter_user_id > 0) {
        // Mode 1 orang spesifik (berlaku untuk semua role)
        $stm = $pdo->prepare("
            SELECT u.id, u.nama, u.divisi,
                   COALESCE(s.gelar_depan,'')    gelar_depan,
                   COALESCE(s.gelar_belakang,'') gelar_belakang,
                   COALESCE(s.nik_rs,'')         nik_rs,
                   COALESCE(s.jenis_karyawan,'') jenis_karyawan
            FROM users u
            LEFT JOIN sdm_karyawan s ON s.user_id = u.id
            WHERE u.id = ? AND u.status = 'aktif'
            LIMIT 1
        ");
        $stm->execute([$filter_user_id]);
        $karyawan = $stm->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Mode semua karyawan (hanya manager)
        $w_bag  = $bagian_id ? "AND u.bagian_id=".(int)$bagian_id : '';
        $w_nama = '';
        $p_nama = [];
        if ($f_nama !== '') {
            $w_nama = "AND u.nama LIKE ?";
            $p_nama = ["%$f_nama%"];
        }
        $stm = $pdo->prepare("
            SELECT u.id, u.nama, u.divisi,
                   COALESCE(s.gelar_depan,'')    gelar_depan,
                   COALESCE(s.gelar_belakang,'') gelar_belakang,
                   COALESCE(s.nik_rs,'')         nik_rs,
                   COALESCE(s.jenis_karyawan,'') jenis_karyawan
            FROM users u
            LEFT JOIN sdm_karyawan s ON s.user_id = u.id
            WHERE u.status = 'aktif' $w_bag $w_nama
            ORDER BY u.divisi, u.nama
        ");
        $stm->execute($p_nama);
        $karyawan = $stm->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Exception $e){}

// Jika non-manager dan data karyawan kosong (user tidak aktif / tidak ditemukan),
// tampilkan data diri sendiri sebagai fallback
if (!$is_manager && empty($karyawan)) {
    try {
        $stm = $pdo->prepare("
            SELECT u.id, u.nama, u.divisi,
                   COALESCE(s.gelar_depan,'')    gelar_depan,
                   COALESCE(s.gelar_belakang,'') gelar_belakang,
                   COALESCE(s.nik_rs,'')         nik_rs,
                   COALESCE(s.jenis_karyawan,'') jenis_karyawan
            FROM users u
            LEFT JOIN sdm_karyawan s ON s.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stm->execute([$_uid_session]);
        $karyawan = $stm->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e){}
}

// ── Fetch absensi bulan ini ───────────────────────────────────────────────────
$absen_map = [];
try {
    if ($filter_user_id > 0) {
        // Hanya ambil absensi 1 orang
        $stm = $pdo->prepare("
            SELECT user_id, tanggal, status,
                   COALESCE(terlambat_menit,0)   terlambat_menit,
                   COALESCE(pulang_awal_menit,0) pulang_awal_menit,
                   COALESCE(durasi_kerja,0)      durasi_kerja,
                   jam_masuk, jam_keluar,
                   foto_masuk, foto_keluar
            FROM absensi
            WHERE user_id = ? AND tanggal BETWEEN ? AND ?
            ORDER BY tanggal
        ");
        $stm->execute([$filter_user_id, $tgl_awal, $tgl_akhir]);
    } else {
        // Semua karyawan
        $stm = $pdo->prepare("
            SELECT user_id, tanggal, status,
                   COALESCE(terlambat_menit,0)   terlambat_menit,
                   COALESCE(pulang_awal_menit,0) pulang_awal_menit,
                   COALESCE(durasi_kerja,0)      durasi_kerja,
                   jam_masuk, jam_keluar,
                   foto_masuk, foto_keluar
            FROM absensi
            WHERE tanggal BETWEEN ? AND ?
            ORDER BY tanggal
        ");
        $stm->execute([$tgl_awal, $tgl_akhir]);
    }
    foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $a)
        $absen_map[$a['user_id']][$a['tanggal']] = $a;
} catch(Exception $e){}

// ── Fetch jadwal ──────────────────────────────────────────────────────────────
$jadwal_map = [];
try {
    if ($filter_user_id > 0) {
        $stm = $pdo->prepare("SELECT user_id, tanggal, tipe FROM jadwal_karyawan WHERE user_id = ? AND tanggal BETWEEN ? AND ?");
        $stm->execute([$filter_user_id, $tgl_awal, $tgl_akhir]);
    } else {
        $stm = $pdo->prepare("SELECT user_id, tanggal, tipe FROM jadwal_karyawan WHERE tanggal BETWEEN ? AND ?");
        $stm->execute([$tgl_awal, $tgl_akhir]);
    }
    foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $j)
        $jadwal_map[$j['user_id']][$j['tanggal']] = $j['tipe'];
} catch(Exception $e){}

// ── Daftar tanggal ────────────────────────────────────────────────────────────
$tgl_list = [];
for ($d = 1; $d <= $jml_hari; $d++) {
    $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
    $dow = (int)date('N', strtotime($tgl));
    $tgl_list[] = ['tgl'=>$tgl,'dow'=>$dow,'weekend'=>($dow>=6),'hari'=>$d];
}

// ── Hitung rekap ──────────────────────────────────────────────────────────────
$rekap = [];
foreach ($karyawan as $k) {
    $r = [
        'hadir'=>0,'terlambat'=>0,'alpha'=>0,'izin'=>0,
        'cuti'=>0,'dinas'=>0,'libur'=>0,'setengah_hari'=>0,
        'hari_kerja'=>0,'total_terlambat'=>0,'total_durasi'=>0,
        'total_kerja_menit'=>0,
    ];
    foreach ($tgl_list as $td) {
        $jdw = $jadwal_map[$k['id']][$td['tgl']] ?? null;
        $ab  = $absen_map[$k['id']][$td['tgl']]  ?? null;
        if ($jdw === 'libur') { $r['libur']++; continue; }
        $r['hari_kerja']++;
        if ($ab) {
            $st = $ab['status'] ?? 'hadir';
            if (isset($r[$st])) $r[$st]++; else $r['hadir']++;
            $r['total_terlambat']   += max(0,(int)($ab['terlambat_menit']??0));
            $r['total_durasi']      += max(0,(int)($ab['durasi_kerja']??0));
            $r['total_kerja_menit'] += max(0,(int)($ab['durasi_kerja']??0));
        } elseif (in_array($jdw, ['cuti','izin','dinas'])) {
            $r[$jdw]++;
        }
    }
    $r['pct_hadir'] = $r['hari_kerja'] > 0
        ? round(($r['hadir']+$r['terlambat']+$r['setengah_hari'])/$r['hari_kerja']*100) : 0;
    $rekap[$k['id']] = $r;
}

// ── Agregat total ─────────────────────────────────────────────────────────────
$tot = ['hadir'=>0,'terlambat'=>0,'alpha'=>0,'izin'=>0,'cuti'=>0,'dinas'=>0,'total_terlambat'=>0];
foreach ($rekap as $r) foreach (array_keys($tot) as $key) $tot[$key] += ($r[$key]??0);

$qs_base = http_build_query(array_filter([
    'bulan'   => $bulan,
    'tahun'   => $tahun,
    'bagian'  => $bagian_id,
    'nama'    => $f_nama,
    'user_id' => $filter_user_id ?: null,
]));

// Judul halaman — kalau lihat 1 orang, tampilkan nama
$page_title_extra = '';
if ($filter_user_id > 0 && !empty($karyawan)) {
    $page_title_extra = ' — ' . $karyawan[0]['nama'];
    $page_title = $is_manager ? 'Laporan Absensi' : 'Rekap Absensi Saya';
}

include '../includes/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

<style>
* { box-sizing: border-box; }
.la { font-family: 'Inter', sans-serif; color: #1e293b; }

/* ── NAV BAR ── */
.la-nav {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 12px 16px; margin-bottom: 14px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.la-nav-btn {
    width: 30px; height: 30px; border-radius: 7px;
    border: 1px solid #e2e8f0; background: #f8fafc;
    display: flex; align-items: center; justify-content: center;
    color: #64748b; text-decoration: none; font-size: 12px; transition: all .12s;
}
.la-nav-btn:hover { background: #e2e8f0; color: #1e293b; }
.la-month-lbl {
    font-size: 14px; font-weight: 800; color: #0f172a;
    padding: 5px 16px; background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 7px; min-width: 160px; text-align: center;
}

/* Banner mode personal */
.la-personal-banner {
    display: flex; align-items: center; gap: 10px;
    background: linear-gradient(135deg, rgba(0,229,176,0.08), rgba(0,229,176,0.04));
    border: 1px solid rgba(0,229,176,0.25);
    border-radius: 9px; padding: 10px 16px; margin-bottom: 14px;
}
.la-personal-av {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg,#00e5b0,#00c896);
    color: #0a0f14; font-size: 11px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.la-personal-name { font-size: 13px; font-weight: 700; color: #0f172a; }
.la-personal-sub  { font-size: 11px; color: #64748b; margin-top: 1px; }

.la-view-tabs { display: flex; border: 1px solid #e2e8f0; border-radius: 7px; overflow: hidden; }
.la-view-tab {
    padding: 5px 13px; font-size: 11px; font-weight: 600;
    background: #fff; color: #64748b; border: none; cursor: pointer;
    transition: all .12s; text-decoration: none; display: flex; align-items: center; gap: 5px;
}
.la-view-tab.active { background: #0d1b2e; color: #fff; }
.la-view-tab:not(.active):hover { background: #f1f5f9; }

/* ── STAT STRIP ── */
.la-stats {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(110px,1fr));
    gap: 10px; margin-bottom: 14px;
}
.la-sc { background: #fff; border: 1px solid #e2e8f0; border-radius: 9px; padding: 11px 14px; }
.la-sc-num { font-size: 22px; font-weight: 800; font-family: 'JetBrains Mono',monospace; line-height: 1; }
.la-sc-lbl { font-size: 10.5px; font-weight: 600; color: #94a3b8; margin-top: 3px; }
.la-sc-bar { height: 3px; border-radius: 9px; margin-top: 6px; opacity: .55; }

/* ── FILTER BAR ── */
.la-filter { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 12px; }
.la-filter select, .la-filter input {
    height: 32px; padding: 0 10px; border: 1.5px solid #e2e8f0; border-radius: 7px;
    background: #fff; font-size: 12px; font-family: inherit;
    outline: none; transition: border-color .15s; color: #1e293b;
}
.la-filter select:focus, .la-filter input:focus { border-color: #00c896; }
.la-filter input { min-width: 160px; }

/* ── REKAP TABLE ── */
.la-tbl-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: auto; margin-bottom: 16px; }
.la-tbl { width: 100%; border-collapse: collapse; min-width: 760px; }
.la-tbl th {
    padding: 9px 12px; background: #fafbfc;
    border-bottom: 2px solid #e2e8f0;
    font-size: 9.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; color: #64748b; white-space: nowrap; text-align: center;
}
.la-tbl th.left { text-align: left; }
.la-tbl td {
    padding: 9px 12px; border-bottom: 1px solid #f0f4f8;
    font-size: 12px; text-align: center; vertical-align: middle;
}
.la-tbl td.left { text-align: left; }
.la-tbl tr:last-child td { border-bottom: none; }
.la-tbl tr:hover td { background: #fafbfc; }
.la-tbl tfoot td { font-weight: 700; background: #f8fafc; border-top: 2px solid #e2e8f0; }
.la-tbl .divisi-row td {
    padding: 5px 12px; background: #f8fafc;
    border-bottom: 1px solid #e8ecf2;
    font-size: 9.5px; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: .6px; text-align: left;
}

/* Angka berwarna */
.n-hadir     { font-weight: 700; color: #15803d; }
.n-terlambat { font-weight: 700; color: #a16207; }
.n-alpha     { font-weight: 700; color: #b91c1c; }
.n-izin      { font-weight: 700; color: #c2410c; }
.n-cuti      { font-weight: 700; color: #6d28d9; }
.n-dinas     { font-weight: 700; color: #0369a1; }
.n-zero      { color: #e2e8f0; font-weight: 400; }

/* Progress bar */
.la-bar { height: 5px; background: #f1f5f9; border-radius: 3px; overflow: hidden; margin-top: 3px; }
.la-bar-fill { height: 5px; border-radius: 3px; }

/* ── KALENDER GRID ── */
.la-cal-wrap { overflow-x: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 16px; }
.la-cal { border-collapse: collapse; min-width: max-content; }
.la-cal th {
    padding: 6px 5px; background: #fafbfc;
    border: 1px solid #e8ecf2;
    font-size: 9px; font-weight: 700; color: #64748b;
    text-align: center; white-space: nowrap;
}
.la-cal th.sticky-col { text-align: left; padding: 6px 12px; min-width: 170px; position: sticky; left: 0; z-index: 3; background: #fafbfc; }
.la-cal td { padding: 3px 4px; border: 1px solid #f0f4f8; text-align: center; }
.la-cal td.sticky-col {
    text-align: left; padding: 6px 10px;
    background: #fafbfc; border-right: 2px solid #e2e8f0;
    font-size: 11px; font-weight: 600; white-space: nowrap;
    position: sticky; left: 0; z-index: 1;
}
.la-cal td.weekend { background: #fafaf8; }
.la-cal td.today   { background: #f0fdf9; }

/* Dot kalender */
.la-dot {
    width: 26px; height: 26px; border-radius: 6px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 9px; font-weight: 800; color: #fff; line-height: 1;
}
.la-dot.H  { background: #10b981; }
.la-dot.T  { background: #f59e0b; }
.la-dot.A  { background: #ef4444; }
.la-dot.I  { background: #f97316; }
.la-dot.C  { background: #8b5cf6; }
.la-dot.D  { background: #0ea5e9; }
.la-dot.HL { background: #e2e8f0; color: #94a3b8; }
.la-dot.E  { background: #f1f5f9; color: #cbd5e1; }
.la-dot.S  { background: #fbbf24; color: #1e293b; }

/* ── DETAIL per orang ── */
.la-detail-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 10px; overflow: hidden; margin-bottom: 12px;
}
.la-detail-head {
    padding: 11px 16px; background: #fafbfc;
    border-bottom: 1px solid #e2e8f0;
    display: flex; align-items: center; gap: 10px;
}
.la-detail-av {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, #00e5b0, #00c896);
    color: #0a0f14; font-size: 10px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.la-detail-nama { font-size: 13px; font-weight: 700; color: #0f172a; }
.la-detail-sub  { font-size: 10.5px; color: #94a3b8; margin-top: 1px; }
.la-detail-stats { display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto; }
.la-detail-stat { text-align: center; padding: 0 8px; }
.la-detail-stat-num { font-size: 15px; font-weight: 800; font-family: 'JetBrains Mono',monospace; line-height: 1; }
.la-detail-stat-lbl { font-size: 9px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }

/* Export & Print button */
.la-export {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 7px; background: #10b981;
    color: #fff; font-size: 11.5px; font-weight: 700;
    border: none; cursor: pointer; font-family: 'Inter',sans-serif;
    text-decoration: none; transition: background .15s;
}
.la-export:hover { background: #059669; color: #fff; text-decoration: none; }
.la-print-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 7px; background: #0d1b2e;
    color: #fff; font-size: 11.5px; font-weight: 700;
    border: none; cursor: pointer; font-family: 'Inter',sans-serif; transition: background .15s;
    text-decoration: none;
}
.la-print-btn:hover { background: #1a3a5c; color: #fff; text-decoration: none; }

/* Legenda */
.la-legend { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
.la-legend-item { display: flex; align-items: center; gap: 5px; font-size: 10.5px; color: #64748b; }

/* Empty */
.la-empty { padding: 48px; text-align: center; color: #94a3b8; }
.la-empty i { font-size: 24px; display: block; margin-bottom: 10px; }

/* ── JAM TABLE ── */
.la-jam-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 14px; }
.la-jam-head { padding: 11px 16px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; }
.la-jam-av { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg,#00e5b0,#00c896); color: #0a0f14; font-size: 9px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.la-jam-tbl { width: 100%; border-collapse: collapse; }
.la-jam-tbl th { padding: 8px 12px; background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; white-space: nowrap; text-align: center; }
.la-jam-tbl th.left { text-align: left; }
.la-jam-tbl td { padding: 8px 12px; border-bottom: 1px solid #f0f4f8; font-size: 12px; text-align: center; vertical-align: middle; }
.la-jam-tbl td.left { text-align: left; }
.la-jam-tbl tr:last-child td { border-bottom: none; }
.la-jam-tbl tr:hover td { background: #fafbfc; }
.la-jam-mono { font-family: 'JetBrains Mono',monospace; font-size: 12px; font-weight: 700; }
.la-jam-badge { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; }
.la-jam-foto { width: 28px; height: 28px; border-radius: 5px; object-fit: cover; cursor: pointer; border: 1px solid #e2e8f0; vertical-align: middle; transition: transform .12s; }
.la-jam-foto:hover { transform: scale(1.15); }

/* Kembali link */
.la-back-link {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11.5px; color: #64748b; text-decoration: none;
    padding: 5px 10px; border-radius: 6px; border: 1px solid #e2e8f0;
    background: #f8fafc; transition: all .12s;
}
.la-back-link:hover { background: #e2e8f0; color: #1e293b; }

@media print {
    .la-nav, .la-filter, .la-view-tabs, .la-export, .la-print-btn,
    .la-legend, .no-print { display: none !important; }
    body { background: #fff !important; }
    .la-tbl-wrap, .la-cal-wrap { box-shadow: none !important; border: 1px solid #ccc !important; }
}
@media (max-width: 768px) {
    .la-stats { grid-template-columns: repeat(4,1fr); }
}
</style>

<div class="page-header">
    <h4>
        <i class="fa fa-chart-bar text-primary"></i>
        &nbsp;<?= $is_manager ? 'Laporan Absensi' : 'Rekap Absensi Saya' ?>
        <?php if ($filter_user_id > 0 && !empty($karyawan) && $is_manager): ?>
        <span style="font-size:13px;font-weight:500;color:#64748b;margin-left:8px;">— <?= htmlspecialchars($karyawan[0]['nama']) ?></span>
        <?php endif; ?>
    </h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
        <?php if ($is_manager): ?>
        <a href="<?= APP_URL ?>/pages/laporan_absensi.php">Laporan Absensi</a><span class="sep">/</span>
        <?php endif; ?>
        <span class="cur"><?= $is_manager && $filter_user_id > 0 ? htmlspecialchars($karyawan[0]['nama'] ?? 'Detail') : ($is_manager ? 'Laporan' : 'Rekap Saya') ?></span>
    </div>
</div>

<div class="content la">
    <?= showFlash() ?>

    <?php /* ── Banner identitas diri (untuk non-manager) ── */ ?>
    <?php if (!$is_manager && !empty($karyawan)): ?>
    <?php
    $me = $karyawan[0];
    $ws = array_filter(explode(' ', $me['nama']));
    $me_ini = strtoupper(implode('',array_map(fn($w)=>mb_substr($w,0,1),array_slice(array_values($ws),0,2))));
    ?>
    <div class="la-personal-banner">
        <div class="la-personal-av"><?= $me_ini ?></div>
        <div>
            <div class="la-personal-name">
                <?= ($me['gelar_depan']?htmlspecialchars($me['gelar_depan']).' ':'').htmlspecialchars($me['nama']) ?>
                <?= ($me['gelar_belakang']?'<span style="font-weight:400;color:#94a3b8;">, '.htmlspecialchars($me['gelar_belakang']).'</span>':'') ?>
            </div>
            <div class="la-personal-sub">
                <?= $me['nik_rs']?htmlspecialchars($me['nik_rs']).' &middot; ':'' ?>
                <?= htmlspecialchars($me['divisi']?:'—') ?>
                <?= $me['jenis_karyawan']?' &middot; '.htmlspecialchars($me['jenis_karyawan']):'' ?>
            </div>
        </div>
        <div style="margin-left:auto;font-size:11px;color:#64748b;">
            <i class="fa fa-lock" style="color:#00c896;margin-right:4px;"></i> Menampilkan data absensi Anda sendiri
        </div>
    </div>
    <?php endif; ?>

    <?php /* ── Tombol kembali jika manager sedang lihat 1 orang ── */ ?>
    <?php if ($is_manager && $filter_user_id > 0): ?>
    <div style="margin-bottom:12px;">
        <a href="<?= APP_URL ?>/pages/laporan_absensi.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&bagian=<?= $bagian_id ?>" class="la-back-link">
            <i class="fa fa-arrow-left"></i> Kembali ke semua karyawan
        </a>
    </div>
    <?php endif; ?>

    <!-- NAV BULAN -->
    <div class="la-nav">
        <a href="?bulan=<?= $prev_b ?>&tahun=<?= $prev_t ?><?= $filter_user_id?'&user_id='.$filter_user_id:'' ?>&view=<?= $view_mode ?>" class="la-nav-btn"><i class="fa fa-chevron-left"></i></a>
        <span class="la-month-lbl"><?= $nama_bulan[$bulan] ?> <?= $tahun ?></span>
        <a href="?bulan=<?= $next_b ?>&tahun=<?= $next_t ?><?= $filter_user_id?'&user_id='.$filter_user_id:'' ?>&view=<?= $view_mode ?>" class="la-nav-btn"><i class="fa fa-chevron-right"></i></a>

        <input type="month"
               value="<?= sprintf('%04d-%02d', $tahun, $bulan) ?>"
               onchange="var p=this.value.split('-');location.href='?bulan='+parseInt(p[1])+'&tahun='+p[0]+'<?= $filter_user_id?'&user_id='.$filter_user_id:'' ?>&view=<?= $view_mode ?>'"
               style="height:30px;padding:0 10px;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;font-family:inherit;background:#f8fafc;outline:none;cursor:pointer;">

        <!-- View tabs -->
        <div class="la-view-tabs no-print">
            <?php foreach (['rekap'=>['fa-table-list','Rekap'], 'kalender'=>['fa-calendar','Kalender'], 'jam'=>['fa-clock','Jam Absen'], 'detail'=>['fa-id-card','Per Orang']] as $vm=>[$ico,$lbl]): ?>
            <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?><?= $filter_user_id?'&user_id='.$filter_user_id:($bagian_id?'&bagian='.$bagian_id:'') ?><?= $f_nama?'&nama='.urlencode($f_nama):'' ?>&view=<?= $vm ?>"
               class="la-view-tab <?= $view_mode===$vm?'active':'' ?>">
                <i class="fa <?= $ico ?>"></i> <?= $lbl ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div style="margin-left:auto;display:flex;gap:6px;" class="no-print">
            <a href="<?= APP_URL ?>/pages/cetak_laporan_absensi.php?mode=bulanan&bulan=<?= $bulan ?>&tahun=<?= $tahun ?><?= $filter_user_id?'&user_id='.$filter_user_id:('&bagian='.$bagian_id) ?>" target="_blank" class="la-print-btn">
                <i class="fa fa-print"></i> Cetak
            </a>
            <?php if ($is_manager && $filter_user_id === 0): ?>
            <button onclick="exportCSV()" class="la-export"><i class="fa fa-file-csv"></i> Export CSV</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- STAT STRIP -->
    <div class="la-stats">
        <?php foreach ([
            ['Hari Kerja', $jml_hari,               '#0369a1','#0ea5e9'],
            ['Hadir',      $tot['hadir'],            '#15803d','#10b981'],
            ['Terlambat',  $tot['terlambat'],        '#a16207','#f59e0b'],
            ['Alpha',      $tot['alpha'],            '#b91c1c','#ef4444'],
            ['Izin',       $tot['izin'],             '#c2410c','#f97316'],
            ['Cuti',       $tot['cuti'],             '#6d28d9','#8b5cf6'],
            ['Dinas',      $tot['dinas'],            '#0369a1','#0ea5e9'],
        ] as [$lbl,$num,$tc,$bc]): ?>
        <div class="la-sc">
            <div class="la-sc-num" style="color:<?= $tc ?>;"><?= $num ?></div>
            <div class="la-sc-lbl"><?= $lbl ?></div>
            <div class="la-sc-bar" style="background:<?= $bc ?>;"></div>
        </div>
        <?php endforeach; ?>
        <?php if ($is_manager && $filter_user_id === 0): ?>
        <div class="la-sc">
            <div class="la-sc-num" style="color:#0f172a;"><?= count($karyawan) ?></div>
            <div class="la-sc-lbl">Karyawan</div>
            <div class="la-sc-bar" style="background:#e2e8f0;"></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- FILTER — hanya tampil untuk manager dan mode semua karyawan -->
    <?php if ($is_manager && $filter_user_id === 0): ?>
    <div class="la-filter no-print">
        <select id="fil-bag" onchange="goFilter()">
            <option value="0">Semua Unit</option>
            <?php foreach ($bagian_list as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $bagian_id==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['nama']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="fil-nama" value="<?= htmlspecialchars($f_nama) ?>" placeholder="Cari nama karyawan…" onkeydown="if(event.key==='Enter')goFilter()">
        <button onclick="goFilter()" class="btn btn-sm btn-primary" style="height:32px;"><i class="fa fa-search"></i> Cari</button>
        <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&view=<?= $view_mode ?>" class="btn btn-sm btn-default" style="height:32px;display:inline-flex;align-items:center;gap:4px;"><i class="fa fa-rotate-left"></i> Reset</a>
        <a href="<?= APP_URL ?>/pages/absensi_foto.php?tgl=<?= $tgl_awal ?>&bagian=<?= $bagian_id ?>" class="btn btn-sm btn-default" style="height:32px;display:inline-flex;align-items:center;gap:4px;"><i class="fa fa-images"></i> Foto Bukti</a>
    </div>
    <?php endif; ?>

    <?php if (empty($karyawan)): ?>
    <div class="panel"><div class="la-empty">
        <i class="fa fa-user-slash"></i>
        <div style="font-weight:700;color:#475569;margin-bottom:4px;">Data tidak ditemukan</div>
        <div style="font-size:12px;">Tidak ada data absensi untuk periode ini</div>
    </div></div>

    <?php elseif ($view_mode === 'rekap'): ?>
    <!-- ════════════ VIEW: REKAP ════════════ -->
    <div class="la-tbl-wrap">
        <table class="la-tbl" id="la-tbl">
            <thead>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <?php if ($is_manager && $filter_user_id === 0): ?>
                    <th class="left" rowspan="2" style="width:30px;">#</th>
                    <th class="left" rowspan="2" style="min-width:180px;">Karyawan</th>
                    <th class="left" rowspan="2" style="min-width:100px;">Unit / Divisi</th>
                    <?php else: ?>
                    <th class="left" rowspan="2" style="min-width:180px;">Karyawan</th>
                    <th class="left" rowspan="2" style="min-width:100px;">Unit / Divisi</th>
                    <?php endif; ?>
                    <th rowspan="2">Hari<br>Kerja</th>
                    <th colspan="6" style="border-bottom:1px solid #e8ecf2;background:#f0fdf9;color:#059669;">Kehadiran</th>
                    <th colspan="2" style="border-bottom:1px solid #e8ecf2;background:#fef9c3;color:#a16207;">Waktu</th>
                    <th rowspan="2">Tingkat<br>Hadir</th>
                </tr>
                <tr>
                    <th style="background:#f0fdf9;">Hadir</th>
                    <th style="background:#fef3c7;">Terlambat</th>
                    <th style="background:#fee2e2;">Alpha</th>
                    <th style="background:#fff7ed;">Izin</th>
                    <th style="background:#faf5ff;">Cuti</th>
                    <th style="background:#e0f2fe;">Dinas</th>
                    <th style="background:#fef9c3;">Total Terlambat</th>
                    <th style="background:#fef9c3;">Total Jam Kerja</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $cur_div = null; $no = 0;
            foreach ($karyawan as $k):
                $r  = $rekap[$k['id']];
                $no++;
                if ($is_manager && $filter_user_id === 0 && $k['divisi'] !== $cur_div):
                    $cur_div = $k['divisi'];
            ?>
            <tr class="divisi-row">
                <td colspan="13"><i class="fa fa-building" style="font-size:9px;margin-right:5px;"></i><?= htmlspecialchars($k['divisi'] ?: 'Tanpa Divisi') ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <?php if ($is_manager && $filter_user_id === 0): ?>
                <td class="left" style="color:#94a3b8;font-size:11px;"><?= $no ?></td>
                <?php endif; ?>
                <td class="left">
                    <div style="font-weight:700;font-size:12.5px;">
                        <?= ($k['gelar_depan']?htmlspecialchars($k['gelar_depan']).' ':'').htmlspecialchars($k['nama']) ?>
                        <?= ($k['gelar_belakang']?'<span style="font-weight:400;color:#94a3b8;">, '.htmlspecialchars($k['gelar_belakang']).'</span>':'') ?>
                    </div>
                    <?php if ($k['nik_rs']): ?>
                    <div style="font-size:10px;color:#94a3b8;font-family:'JetBrains Mono',monospace;"><?= htmlspecialchars($k['nik_rs']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="left" style="font-size:11.5px;color:#64748b;"><?= htmlspecialchars($k['divisi']?:'—') ?></td>
                <td style="font-weight:600;"><?= $r['hari_kerja'] ?></td>
                <td class="n-hadir"><?= $r['hadir'] ?: '<span class="n-zero">0</span>' ?></td>
                <td class="n-terlambat"><?= $r['terlambat'] ?: '<span class="n-zero">0</span>' ?></td>
                <td class="n-alpha">
                    <?php if ($r['alpha'] > 0): ?>
                    <span style="background:#fee2e2;color:#b91c1c;padding:1px 7px;border-radius:4px;font-weight:700;"><?= $r['alpha'] ?></span>
                    <?php else: ?><span class="n-zero">0</span><?php endif; ?>
                </td>
                <td class="n-izin"><?= $r['izin'] ?: '<span class="n-zero">0</span>' ?></td>
                <td class="n-cuti"><?= $r['cuti'] ?: '<span class="n-zero">0</span>' ?></td>
                <td class="n-dinas"><?= $r['dinas'] ?: '<span class="n-zero">0</span>' ?></td>
                <td style="font-size:11px;<?= $r['total_terlambat']>60?'color:#b91c1c;font-weight:700;':($r['total_terlambat']>0?'color:#a16207;':'color:#e2e8f0;') ?>">
                    <?= $r['total_terlambat'] > 0 ? $r['total_terlambat'].'m' : '—' ?>
                </td>
                <td style="font-size:11px;color:#0369a1;">
                    <?php if ($r['total_durasi'] > 0): ?>
                    <?= floor($r['total_durasi']/60) ?>j <?= $r['total_durasi']%60 ?>m
                    <?php else: ?><span style="color:#e2e8f0;">—</span><?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:52px;height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                            <div class="la-bar-fill" style="width:<?= $r['pct_hadir'] ?>%;height:5px;background:<?= $r['pct_hadir']>=90?'#10b981':($r['pct_hadir']>=75?'#f59e0b':'#ef4444') ?>;"></div>
                        </div>
                        <span style="font-size:11px;font-weight:700;color:<?= $r['pct_hadir']>=90?'#15803d':($r['pct_hadir']>=75?'#a16207':'#b91c1c') ?>">
                            <?= $r['pct_hadir'] ?>%
                        </span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($is_manager && $filter_user_id === 0): ?>
            <tfoot>
                <tr>
                    <td colspan="3" class="left" style="font-weight:700;">TOTAL</td>
                    <td>—</td>
                    <td class="n-hadir"><?= $tot['hadir'] ?></td>
                    <td class="n-terlambat"><?= $tot['terlambat'] ?></td>
                    <td class="n-alpha"><?= $tot['alpha'] ?></td>
                    <td class="n-izin"><?= $tot['izin'] ?></td>
                    <td class="n-cuti"><?= $tot['cuti'] ?></td>
                    <td class="n-dinas"><?= $tot['dinas'] ?></td>
                    <td style="font-size:11px;color:#a16207;font-weight:700;"><?= $tot['total_terlambat'] ?>m</td>
                    <td>—</td>
                    <td>—</td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <?php elseif ($view_mode === 'kalender'): ?>
    <!-- ════════════ VIEW: KALENDER ════════════ -->
    <div class="la-legend no-print">
        <span style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Legenda:</span>
        <?php foreach ([
            ['H','#10b981','Hadir'],['T','#f59e0b','Terlambat'],['A','#ef4444','Alpha'],
            ['I','#f97316','Izin'],['C','#8b5cf6','Cuti'],['D','#0ea5e9','Dinas'],
            ['HL','#e2e8f0','Libur/Weekend'],
        ] as [$kd,$bg,$lbl]): ?>
        <div class="la-legend-item">
            <span style="width:20px;height:20px;border-radius:5px;background:<?= $bg ?>;color:<?= $bg==='#e2e8f0'?'#94a3b8':'#fff' ?>;font-size:8px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;"><?= $kd ?></span>
            <?= $lbl ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="la-cal-wrap">
        <table class="la-cal">
            <thead>
                <tr>
                    <th class="sticky-col">Karyawan</th>
                    <?php foreach ($tgl_list as $td): ?>
                    <th title="<?= $nama_hari_pendek[$td['dow']-1] ?>, <?= date('d M',strtotime($td['tgl'])) ?>">
                        <div style="font-size:8px;color:#94a3b8;"><?= $nama_hari_pendek[$td['dow']-1] ?></div>
                        <div style="font-size:11px;font-weight:800;color:#1e293b;font-family:'JetBrains Mono',monospace;"><?= $td['hari'] ?></div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($karyawan as $k): ?>
            <tr>
                <td class="sticky-col">
                    <div style="font-weight:700;"><?= htmlspecialchars(($k['gelar_depan']?$k['gelar_depan'].' ':'').mb_strimwidth($k['nama'],0,22,'…')) ?></div>
                    <?php if ($k['nik_rs']): ?><div style="font-size:9px;color:#94a3b8;font-family:monospace;"><?= htmlspecialchars($k['nik_rs']) ?></div><?php endif; ?>
                </td>
                <?php foreach ($tgl_list as $td):
                    $jdw = $jadwal_map[$k['id']][$td['tgl']] ?? null;
                    $ab  = $absen_map[$k['id']][$td['tgl']]  ?? null;
                    $is_today = ($td['tgl'] === date('Y-m-d'));

                    if ($jdw === 'libur' && !$ab) { $kode='HL'; $ttip='Libur'; }
                    elseif ($ab) {
                        $st = $ab['status'] ?? 'hadir';
                        $km=['hadir'=>'H','terlambat'=>'T','alpha'=>'A','izin'=>'I','cuti'=>'C','dinas'=>'D','setengah_hari'=>'S','libur'=>'HL'];
                        $kode=$km[$st]??'H'; $ttip=ucfirst($st);
                        if ($ab['jam_masuk']) $ttip.=' ('.substr($ab['jam_masuk'],0,5).')';
                        if ($ab['terlambat_menit']>0) $ttip.=' +'.($ab['terlambat_menit']).'m';
                    } elseif (in_array($jdw,['cuti','izin','dinas'])) {
                        $km2=['cuti'=>'C','izin'=>'I','dinas'=>'D']; $kode=$km2[$jdw]; $ttip=ucfirst($jdw).' (jadwal)';
                    } else { $kode='E'; $ttip='Belum ada data'; }
                    $cls_td=$is_today?'today':'';
                ?>
                <td class="<?= $cls_td ?>" title="<?= $ttip ?>">
                    <span class="la-dot <?= $kode ?>"><?= $kode==='E'?'':($kode==='HL'?'—':$kode) ?></span>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($view_mode === 'detail'): ?>
    <!-- ════════════ VIEW: DETAIL PER ORANG ════════════ -->
    <?php foreach ($karyawan as $k):
        $r   = $rekap[$k['id']];
        $ws  = array_filter(explode(' ',$k['nama']));
        $ini = strtoupper(implode('',array_map(fn($w)=>mb_substr($w,0,1),array_slice(array_values($ws),0,2))));
    ?>
    <div class="la-detail-card">
        <div class="la-detail-head">
            <div class="la-detail-av"><?= $ini ?></div>
            <div style="flex:1;min-width:0;">
                <div class="la-detail-nama">
                    <?= ($k['gelar_depan']?htmlspecialchars($k['gelar_depan']).' ':'').htmlspecialchars($k['nama']) ?>
                </div>
                <div class="la-detail-sub">
                    <?= $k['nik_rs']?htmlspecialchars($k['nik_rs']).' &middot; ':'' ?>
                    <?= htmlspecialchars($k['divisi']?:'—') ?>
                </div>
            </div>
            <div class="la-detail-stats">
                <?php foreach ([
                    [$r['hadir']+$r['terlambat'],'#10b981','Hadir'],
                    [$r['alpha'],'#ef4444','Alpha'],
                    [$r['izin']+$r['cuti'],'#8b5cf6','Izin/Cuti'],
                ] as [$num,$tc,$lbl]): ?>
                <div class="la-detail-stat">
                    <div class="la-detail-stat-num" style="color:<?= $tc ?>;"><?= $num ?></div>
                    <div class="la-detail-stat-lbl"><?= $lbl ?></div>
                </div>
                <?php endforeach; ?>
                <div class="la-detail-stat" style="padding-left:12px;border-left:1px solid #e2e8f0;">
                    <div class="la-detail-stat-num" style="color:<?= $r['pct_hadir']>=90?'#15803d':($r['pct_hadir']>=75?'#a16207':'#b91c1c') ?>;"><?= $r['pct_hadir'] ?>%</div>
                    <div class="la-detail-stat-lbl">Kehadiran</div>
                </div>
            </div>
        </div>
        <!-- Mini timeline -->
        <div style="padding:10px 14px;overflow-x:auto;">
            <div style="display:flex;gap:3px;min-width:max-content;">
            <?php foreach ($tgl_list as $td):
                $jdw=$jadwal_map[$k['id']][$td['tgl']]??null;
                $ab =$absen_map[$k['id']][$td['tgl']] ??null;
                $bg='#f1f5f9'; $tc2='#cbd5e1'; $ttip=$td['hari'].' '.substr($nama_hari_pendek[$td['dow']-1],0,1); $lbl_d='';
                if ($jdw==='libur'&&!$ab) { $bg='#f1f5f9'; $tc2='#94a3b8'; $lbl_d='—'; }
                elseif ($ab) {
                    $st=$ab['status']??'hadir';
                    $sm=['hadir'=>['#dcfce7','#15803d','H'],'terlambat'=>['#fef3c7','#a16207','T'],
                         'alpha'=>['#fee2e2','#b91c1c','A'],'izin'=>['#fff7ed','#c2410c','I'],
                         'cuti'=>['#faf5ff','#6d28d9','C'],'dinas'=>['#e0f2fe','#0369a1','D']];
                    $sv=$sm[$st]??['#f0fdf4','#15803d','H'];
                    $bg=$sv[0]; $tc2=$sv[1]; $lbl_d=$sv[2];
                    if ($ab['jam_masuk']) $ttip.=': '.substr($ab['jam_masuk'],0,5);
                    if ($ab['terlambat_menit']>0) $ttip.=' +'.($ab['terlambat_menit']).'m';
                } elseif (in_array($jdw,['cuti','izin','dinas'])) {
                    $sm2=['cuti'=>['#faf5ff','#6d28d9','C'],'izin'=>['#fff7ed','#c2410c','I'],'dinas'=>['#e0f2fe','#0369a1','D']];
                    $sv2=$sm2[$jdw]??['#f1f5f9','#94a3b8',''];
                    $bg=$sv2[0]; $tc2=$sv2[1]; $lbl_d=$sv2[2];
                }
            ?>
            <div title="<?= $ttip ?>" style="width:28px;height:36px;border-radius:5px;background:<?= $bg ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;cursor:default;">
                <span style="font-size:8px;font-weight:700;color:<?= $tc2 ?>;"><?= $td['hari'] ?></span>
                <span style="font-size:9px;font-weight:800;color:<?= $tc2 ?>;"><?= $lbl_d ?></span>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php if ($r['total_terlambat'] > 0): ?>
        <div style="padding:6px 14px 10px;border-top:1px solid #f0f2f7;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:10.5px;font-weight:600;color:#94a3b8;">Terlambat:</span>
            <span style="font-size:11px;font-weight:700;color:#a16207;background:#fef3c7;padding:2px 8px;border-radius:4px;">
                <i class="fa fa-clock" style="font-size:9px;margin-right:3px;"></i><?= $r['terlambat'] ?>x, total <?= $r['total_terlambat'] ?> menit
            </span>
            <?php if ($r['total_durasi'] > 0): ?>
            <span style="font-size:11px;font-weight:700;color:#0369a1;background:#e0f2fe;padding:2px 8px;border-radius:4px;">
                <i class="fa fa-business-time" style="font-size:9px;margin-right:3px;"></i><?= floor($r['total_durasi']/60) ?>j <?= $r['total_durasi']%60 ?>m kerja
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php elseif ($view_mode === 'jam'): ?>
    <!-- ════════════ VIEW: JAM ABSEN ════════════ -->
    <?php foreach ($karyawan as $k):
        $r   = $rekap[$k['id']];
        $ws  = array_filter(explode(' ',$k['nama']));
        $ini = strtoupper(implode('',array_map(fn($w)=>mb_substr($w,0,1),array_slice(array_values($ws),0,2))));
        $nama_tampil=($k['gelar_depan']?htmlspecialchars($k['gelar_depan']).' ':'').htmlspecialchars($k['nama']);
        $st_badge=['hadir'=>['#dcfce7','#15803d','Hadir'],'terlambat'=>['#fef3c7','#a16207','Terlambat'],
                   'alpha'=>['#fee2e2','#b91c1c','Alpha'],'izin'=>['#fff7ed','#c2410c','Izin'],
                   'cuti'=>['#faf5ff','#6d28d9','Cuti'],'dinas'=>['#e0f2fe','#0369a1','Dinas'],
                   'libur'=>['#f1f5f9','#475569','Libur'],'setengah_hari'=>['#fef9c3','#a16207','½ Hari'],
                   'kosong'=>['#f8fafc','#cbd5e1','—']];
        $nama_hari_full=['Sen','Sel','Rab','Kam','Jum','Sab','Min'];
    ?>
    <div class="la-jam-wrap" style="margin-bottom:16px;">
        <div class="la-jam-head">
            <div class="la-jam-av"><?= $ini ?></div>
            <div style="flex:1;">
                <div style="font-size:13px;font-weight:700;color:#0f172a;"><?= $nama_tampil ?></div>
                <div style="font-size:10.5px;color:#94a3b8;">
                    <?= $k['nik_rs']?htmlspecialchars($k['nik_rs']).' &middot; ':'' ?>
                    <?= htmlspecialchars($k['divisi']?:'—') ?>
                    <?= $k['jenis_karyawan']?' &middot; '.htmlspecialchars($k['jenis_karyawan']):'' ?>
                </div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <?php foreach ([
                    [$r['hadir']+$r['terlambat'],'#10b981','Hadir'],
                    [$r['alpha'],'#ef4444','Alpha'],
                    [$r['total_terlambat'].'m','#a16207','Total Terlambat'],
                ] as [$val,$tc,$lbl]): ?>
                <div style="text-align:center;">
                    <div style="font-size:15px;font-weight:800;color:<?=$tc?>;font-family:'JetBrains Mono',monospace;line-height:1;"><?=$val?></div>
                    <div style="font-size:9px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.3px;margin-top:2px;"><?=$lbl?></div>
                </div>
                <?php endforeach; ?>
                <div style="text-align:center;padding-left:12px;border-left:1px solid #e2e8f0;">
                    <div style="font-size:15px;font-weight:800;color:<?=$r['pct_hadir']>=90?'#15803d':($r['pct_hadir']>=75?'#a16207':'#b91c1c')?>;font-family:'JetBrains Mono',monospace;line-height:1;"><?=$r['pct_hadir']?>%</div>
                    <div style="font-size:9px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.3px;margin-top:2px;">Kehadiran</div>
                </div>
            </div>
        </div>
        <div style="overflow-x:auto;">
        <table class="la-jam-tbl">
            <thead>
                <tr>
                    <th class="left" style="width:36px;">#</th>
                    <th class="left" style="min-width:110px;">Tanggal</th>
                    <th class="left" style="min-width:70px;">Hari</th>
                    <th style="min-width:80px;">Jam Masuk</th>
                    <th style="min-width:80px;">Jam Keluar</th>
                    <th style="min-width:70px;">Durasi</th>
                    <th style="min-width:80px;">Terlambat</th>
                    <th style="min-width:100px;">Status</th>
                    <th style="min-width:60px;">Foto</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $no_baris=0;
            foreach ($tgl_list as $td):
                $jdw2=$jadwal_map[$k['id']][$td['tgl']]??null;
                $ab2 =$absen_map[$k['id']][$td['tgl']] ??null;
                if ($jdw2==='libur'&&!$ab2) continue;
                $rs='kosong';
                if ($ab2) $rs=$ab2['status']??'hadir';
                elseif (in_array($jdw2,['cuti','izin','dinas'])) $rs=$jdw2;
                $sb=$st_badge[$rs]??['#f1f5f9','#64748b',ucfirst($rs)];
                $no_baris++;
                $tgl_fmt=date('d',strtotime($td['tgl'])).' '.$nama_bulan[(int)date('n',strtotime($td['tgl']))];
            ?>
            <tr>
                <td class="left" style="color:#94a3b8;font-size:11px;"><?= $no_baris ?></td>
                <td class="left">
                    <span class="la-jam-mono" style="font-size:11px;color:#0f172a;"><?= $tgl_fmt ?></span>
                    <?php if ($td['tgl']===date('Y-m-d')): ?><span style="font-size:9px;background:#dcfce7;color:#15803d;padding:1px 5px;border-radius:3px;margin-left:4px;font-weight:700;">Hari ini</span><?php endif; ?>
                </td>
                <td class="left" style="color:#64748b;font-size:11.5px;font-weight:600;"><?= $nama_hari_full[$td['dow']-1] ?></td>
                <td><?= ($ab2&&$ab2['jam_masuk'])?'<span class="la-jam-mono" style="color:#10b981;">'.substr($ab2['jam_masuk'],0,5).'</span>':'<span style="color:#e2e8f0;">—</span>' ?></td>
                <td><?= ($ab2&&$ab2['jam_keluar'])?'<span class="la-jam-mono" style="color:#f59e0b;">'.substr($ab2['jam_keluar'],0,5).'</span>':'<span style="color:#e2e8f0;">—</span>' ?></td>
                <td>
                    <?php if ($ab2&&$ab2['durasi_kerja']>0): ?>
                    <span style="font-size:11.5px;font-weight:600;color:#0369a1;"><?= floor($ab2['durasi_kerja']/60) ?>j <?= $ab2['durasi_kerja']%60 ?>m</span>
                    <?php else: ?><span style="color:#e2e8f0;">—</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($ab2&&$ab2['terlambat_menit']>0): ?>
                    <span style="font-size:11px;font-weight:700;color:#a16207;background:#fef3c7;padding:2px 7px;border-radius:4px;">+<?= $ab2['terlambat_menit'] ?> menit</span>
                    <?php elseif (in_array($rs,['hadir','terlambat'])): ?>
                    <span style="font-size:10.5px;color:#15803d;font-weight:600;">Tepat waktu</span>
                    <?php else: ?><span style="color:#e2e8f0;">—</span><?php endif; ?>
                </td>
                <td><span class="la-jam-badge" style="background:<?=$sb[0]?>;color:<?=$sb[1]?>;"><?=$sb[2]?></span></td>
                <td>
                    <?php $foto=$ab2['foto_masuk']??null; ?>
                    <?php if ($foto): ?>
                    <img src="<?= APP_URL ?>/<?= htmlspecialchars($foto) ?>"
                         class="la-jam-foto"
                         onclick="openLb(this.src,'<?= htmlspecialchars($nama_tampil) ?> — <?= $tgl_fmt ?>')"
                         alt="Foto">
                    <?php else: ?><span style="color:#e2e8f0;font-size:11px;">—</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

</div><!-- /.content -->

<script>
function goFilter() {
    var url = new URL(location.href);
    url.searchParams.set('bagian', document.getElementById('fil-bag')?.value || 0);
    url.searchParams.set('nama',   document.getElementById('fil-nama')?.value || '');
    url.searchParams.set('bulan',  '<?= $bulan ?>');
    url.searchParams.set('tahun',  '<?= $tahun ?>');
    url.searchParams.set('view',   '<?= $view_mode ?>');
    url.searchParams.delete('user_id');
    location.href = url.toString();
}

function openLb(src, info) {
    var lb = document.getElementById('la-lb');
    if (!lb) {
        lb = document.createElement('div');
        lb.id = 'la-lb';
        lb.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:99998;align-items:center;justify-content:center;flex-direction:column;gap:12px;';
        lb.innerHTML = '<button onclick="document.getElementById(\'la-lb\').style.display=\'none\'" style="position:absolute;top:16px;right:16px;background:rgba(255,255,255,.15);border:none;color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;"><i class=\"fa fa-times\"></i></button><img id="la-lb-img" style="max-width:90vw;max-height:82vh;border-radius:10px;object-fit:contain;" src="" alt=""><div id="la-lb-info" style="color:#fff;font-size:12px;opacity:.7;font-weight:600;"></div>';
        lb.onclick = function(e){ if(e.target===lb) lb.style.display='none'; };
        document.body.appendChild(lb);
        document.addEventListener('keydown', function(e){ if(e.key==='Escape') lb.style.display='none'; });
    }
    document.getElementById('la-lb-img').src = src;
    document.getElementById('la-lb-info').textContent = info || '';
    lb.style.display = 'flex';
}

function exportCSV() {
    var rows = [['No','Nama','NIK RS','Divisi','Hari Kerja','Hadir','Terlambat','Alpha','Izin','Cuti','Dinas','Total Terlambat (mnt)','Total Jam Kerja','% Hadir']];
    document.querySelectorAll('#la-tbl tbody tr:not(.divisi-row)').forEach(function(tr) {
        var tds = tr.querySelectorAll('td');
        if (tds.length < 5) return;
        var row = [];
        for (var j = 0; j < tds.length; j++) {
            row.push('"' + (tds[j].innerText||'').replace(/\n/g,' ').trim().replace(/"/g,'""') + '"');
        }
        rows.push(row);
    });
    var csv = rows.map(function(r){ return r.join(','); }).join('\n');
    var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'laporan_absensi_<?= $nama_bulan[$bulan] ?>_<?= $tahun ?>.csv';
    a.click();
}
</script>

<?php include '../includes/footer.php'; ?>