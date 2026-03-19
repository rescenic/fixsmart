<?php
// pages/master_jadwal.php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'hrd'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Master Jadwal';
$active_menu = 'master_jadwal';

// ── AJAX: Lokasi Absen CRUD ───────────────────────────────
if (isset($_POST['ajax_lokasi'])) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_POST['act'] ?? '';

    if ($act === 'list') {
        $rows = $pdo->query("SELECT * FROM lokasi_absen ORDER BY status ASC, nama ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok'=>true,'data'=>$rows]);
        exit;
    }

    if (in_array($act, ['simpan','update'])) {
        $id    = (int)($_POST['id'] ?? 0);
        $nama  = trim($_POST['nama'] ?? '');
        $lat   = trim($_POST['lat'] ?? '');
        $lon   = trim($_POST['lon'] ?? '');
        $err   = [];
        if (!$nama)            $err[] = 'Nama lokasi wajib diisi.';
        if (!is_numeric($lat)) $err[] = 'Latitude tidak valid.';
        if (!is_numeric($lon)) $err[] = 'Longitude tidak valid.';
        if ($err) { echo json_encode(['ok'=>false,'msg'=>implode(' ', $err)]); exit; }

        $data = [
            'nama'       => $nama,
            'alamat'     => trim($_POST['alamat'] ?? '') ?: null,
            'lat'        => (float)$lat,
            'lon'        => (float)$lon,
            'radius'     => max(10,(int)($_POST['radius'] ?? 100)),
            'status'     => $_POST['status'] ?? 'aktif',
            'keterangan' => trim($_POST['keterangan'] ?? '') ?: null,
            'updated_by' => (int)$_SESSION['user_id'],
        ];
        try {
            if ($act === 'update' && $id) {
                $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
                $vals = array_values($data); $vals[] = $id;
                $pdo->prepare("UPDATE lokasi_absen SET $sets WHERE id=?")->execute($vals);
                echo json_encode(['ok'=>true,'msg'=>'Lokasi diperbarui.']);
            } else {
                $data['created_by'] = (int)$_SESSION['user_id'];
                $cols = implode(',', array_map(fn($k)=>"`$k`", array_keys($data)));
                $phs  = implode(',', array_fill(0,count($data),'?'));
                $pdo->prepare("INSERT INTO lokasi_absen ($cols) VALUES ($phs)")->execute(array_values($data));
                echo json_encode(['ok'=>true,'msg'=>'Lokasi berhasil ditambahkan.']);
            }
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    if ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $pdo->prepare("DELETE FROM lokasi_absen WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true,'msg'=>'Lokasi dihapus.']);
        exit;
    }

    if ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $pdo->prepare("UPDATE lokasi_absen SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true,'msg'=>'Status diperbarui.']);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Aksi tidak dikenal.']); exit;
}

// ── AJAX: Simpan satu slot jadwal ─────────────────────────
if (isset($_POST['ajax_simpan_slot'])) {
    header('Content-Type: application/json');
    $uid      = (int)($_POST['user_id']  ?? 0);
    $tgl      = $_POST['tanggal'] ?? '';
    $shift_id = $_POST['shift_id'] !== '' ? (int)$_POST['shift_id'] : null;
    $lokasi_id= $_POST['lokasi_id'] !== '' ? (int)$_POST['lokasi_id'] : null;
    $tipe     = $_POST['tipe'] ?? 'shift';
    $ket      = trim($_POST['keterangan'] ?? '') ?: null;

    if (!$uid || !$tgl) { echo json_encode(['ok'=>false,'msg'=>'Data tidak lengkap']); exit; }

    try {
        $ex = $pdo->prepare("SELECT id FROM jadwal_karyawan WHERE user_id=? AND tanggal=?");
        $ex->execute([$uid, $tgl]);
        $eid = $ex->fetchColumn();

        if ($tipe === 'kosong') {
            if ($eid) $pdo->prepare("DELETE FROM jadwal_karyawan WHERE id=?")->execute([$eid]);
            echo json_encode(['ok'=>true,'msg'=>'Jadwal dihapus']);
        } elseif ($eid) {
            $pdo->prepare("UPDATE jadwal_karyawan SET shift_id=?,lokasi_id=?,tipe=?,keterangan=?,updated_by=? WHERE id=?")
                ->execute([$shift_id, $lokasi_id, $tipe, $ket, $_SESSION['user_id'], $eid]);
            echo json_encode(['ok'=>true,'msg'=>'Jadwal diperbarui']);
        } else {
            $pdo->prepare("INSERT INTO jadwal_karyawan (user_id,shift_id,lokasi_id,tanggal,tipe,keterangan,created_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$uid, $shift_id, $lokasi_id, $tgl, $tipe, $ket, $_SESSION['user_id']]);
            echo json_encode(['ok'=>true,'msg'=>'Jadwal disimpan']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Copy jadwal minggu sebelumnya ───────────────────
if (isset($_POST['ajax_copy_minggu'])) {
    header('Content-Type: application/json');
    $bagian_id  = (int)($_POST['bagian_id'] ?? 0);
    $tgl_mulai  = $_POST['tgl_mulai'] ?? '';
    if (!$tgl_mulai) { echo json_encode(['ok'=>false,'msg'=>'Tanggal tidak valid']); exit; }

    $minggu_lalu_mulai = date('Y-m-d', strtotime($tgl_mulai . ' -7 days'));
    $minggu_lalu_akhir = date('Y-m-d', strtotime($tgl_mulai . ' +6 days -7 days'));

    try {
        $where_bagian = $bagian_id ? "AND u.bagian_id = $bagian_id" : '';
        $jadwal_lalu = $pdo->query("
            SELECT j.*, DATEDIFF(j.tanggal, '$minggu_lalu_mulai') as hari_ke
            FROM jadwal_karyawan j
            JOIN users u ON u.id = j.user_id
            WHERE j.tanggal BETWEEN '$minggu_lalu_mulai' AND '$minggu_lalu_akhir'
            $where_bagian
        ")->fetchAll(PDO::FETCH_ASSOC);

        $copied = 0;
        foreach ($jadwal_lalu as $j) {
            $tgl_baru = date('Y-m-d', strtotime($tgl_mulai . ' +' . $j['hari_ke'] . ' days'));
            $ex = $pdo->prepare("SELECT id FROM jadwal_karyawan WHERE user_id=? AND tanggal=?");
            $ex->execute([$j['user_id'], $tgl_baru]);
            if (!$ex->fetchColumn()) {
                $pdo->prepare("INSERT INTO jadwal_karyawan (user_id,shift_id,tanggal,tipe,keterangan,created_by) VALUES (?,?,?,?,?,?)")
                    ->execute([$j['user_id'], $j['shift_id'], $tgl_baru, $j['tipe'], $j['keterangan'], $_SESSION['user_id']]);
                $copied++;
            }
        }
        echo json_encode(['ok'=>true,'msg'=>"$copied jadwal berhasil disalin",'copied'=>$copied]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── PARAMETER TAMPILAN ────────────────────────────────────
$bulan      = (int)($_GET['bulan'] ?? date('n'));
$tahun      = (int)($_GET['tahun'] ?? date('Y'));
$bagian_id  = (int)($_GET['bagian'] ?? 0);
$view_mode  = $_GET['view'] ?? 'minggu';

if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
if ($tahun < 2020 || $tahun > 2040) $tahun = (int)date('Y');

if ($view_mode === 'bulan') {
    $tgl_mulai = "$tahun-" . str_pad($bulan,2,'0',STR_PAD_LEFT) . "-01";
    $tgl_akhir = date('Y-m-t', strtotime($tgl_mulai));
    $minggu_param = null;
} else {
    $minggu_param = isset($_GET['minggu']) ? (int)$_GET['minggu'] : null;
    if ($minggu_param === null) {
        $today = date('Y-m-d');
        $firstDay = "$tahun-" . str_pad($bulan,2,'0',STR_PAD_LEFT) . "-01";
        if ($today >= $firstDay && $today <= date('Y-m-t', strtotime($firstDay))) {
            $dow = (int)date('N', strtotime($today));
            $tgl_mulai = date('Y-m-d', strtotime($today . ' -' . ($dow-1) . ' days'));
        } else {
            $dow = (int)date('N', strtotime($firstDay));
            $tgl_mulai = date('Y-m-d', strtotime($firstDay . ' -' . ($dow-1) . ' days'));
        }
    } else {
        $firstDay = "$tahun-" . str_pad($bulan,2,'0',STR_PAD_LEFT) . "-01";
        $dow = (int)date('N', strtotime($firstDay));
        $first_monday = date('Y-m-d', strtotime($firstDay . ' -' . ($dow-1) . ' days'));
        $tgl_mulai = date('Y-m-d', strtotime($first_monday . ' +' . ($minggu_param * 7) . ' days'));
    }
    $tgl_akhir = date('Y-m-d', strtotime($tgl_mulai . ' +6 days'));
}

$first_of_month = "$tahun-" . str_pad($bulan,2,'0',STR_PAD_LEFT) . "-01";
$dow_first = (int)date('N', strtotime($first_of_month));
$minggu_pertama = date('Y-m-d', strtotime($first_of_month . ' -' . ($dow_first-1) . ' days'));
$weeks_in_month = [];
$ptr = $minggu_pertama;
for ($wi = 0; $wi < 6; $wi++) {
    $w_end = date('Y-m-d', strtotime($ptr . ' +6 days'));
    if ($ptr <= date('Y-m-t', strtotime($first_of_month)) || $w_end >= $first_of_month) {
        $weeks_in_month[] = ['mulai'=>$ptr,'akhir'=>$w_end,'idx'=>$wi];
    }
    $ptr = date('Y-m-d', strtotime($ptr . ' +7 days'));
    if ($ptr > date('Y-m-t', strtotime($first_of_month)) && count($weeks_in_month) > 0) {
        if (date('Y-m-d', strtotime(end($weeks_in_month)['akhir'])) < date('Y-m-t', strtotime($first_of_month))) continue;
        break;
    }
}

$tanggal_list = [];
$ptr = $tgl_mulai;
while ($ptr <= $tgl_akhir) {
    $tanggal_list[] = $ptr;
    $ptr = date('Y-m-d', strtotime($ptr . ' +1 day'));
}

$bagian_list = [];
try { $bagian_list = $pdo->query("SELECT id, nama, kode FROM bagian WHERE status='aktif' ORDER BY urutan, nama")->fetchAll(); } catch(Exception $e){}

$karyawan_where = $bagian_id ? "AND u.bagian_id = $bagian_id" : '';
$karyawan_list = $pdo->query("
    SELECT u.id, u.nama, u.divisi, u.role,
           COALESCE(s.nik_rs,'') nik_rs,
           COALESCE(s.jenis_karyawan,'') jenis_karyawan,
           COALESCE(s.gelar_depan,'') gelar_depan,
           COALESCE(s.gelar_belakang,'') gelar_belakang
    FROM users u
    LEFT JOIN sdm_karyawan s ON s.user_id = u.id
    WHERE u.status = 'aktif' $karyawan_where
    ORDER BY u.divisi, u.nama
")->fetchAll(PDO::FETCH_ASSOC);

$shifts = $pdo->query("SELECT * FROM master_shift WHERE status='aktif' ORDER BY urutan, jenis, jam_masuk")->fetchAll(PDO::FETCH_ASSOC);
$shift_map = [];
foreach ($shifts as $sh) $shift_map[$sh['id']] = $sh;

$jadwal_raw = $pdo->prepare("
    SELECT j.*, ms.kode shift_kode, ms.warna shift_warna, ms.nama shift_nama,
           ms.jam_masuk, ms.jam_keluar, ms.lintas_hari,
           la.nama lokasi_nama, la.radius lokasi_radius
    FROM jadwal_karyawan j
    LEFT JOIN master_shift ms ON ms.id = j.shift_id
    LEFT JOIN lokasi_absen la ON la.id = j.lokasi_id
    WHERE j.tanggal BETWEEN ? AND ?
    ORDER BY j.tanggal
");
$jadwal_raw->execute([$tgl_mulai, $tgl_akhir]);
$jadwal_all = $jadwal_raw->fetchAll(PDO::FETCH_ASSOC);

$jadwal_map = [];
foreach ($jadwal_all as $j) $jadwal_map[$j['user_id']][$j['tanggal']] = $j;

$lokasi_aktif_count = 0;
try { $lokasi_aktif_count = (int)$pdo->query("SELECT COUNT(*) FROM lokasi_absen WHERE status='aktif'")->fetchColumn(); } catch(Exception $e){}

$lokasi_list_aktif = [];
try { $lokasi_list_aktif = $pdo->query("SELECT id,nama,alamat,radius FROM lokasi_absen WHERE status='aktif' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$nama_hari  = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'];

include '../includes/header.php';
?>

<style>
/* ── Master Jadwal ── */
* { box-sizing: border-box; }
.mj { font-family: 'Inter', 'Segoe UI', sans-serif; color: #1e293b; }

/* NAV BAR */
.mj-nav {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 12px 16px; margin-bottom: 14px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.mj-nav-title { font-size: 15px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.mj-nav-title i { color: #00c896; }
.mj-month-nav { display: flex; align-items: center; gap: 6px; }
.mj-month-btn { width:30px;height:30px;border-radius:7px;background:#f1f5f9;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#64748b;font-size:12px;text-decoration:none;transition:all .12s; }
.mj-month-btn:hover { background:#e2e8f0;color:#1e293b; }
.mj-month-label { font-size:13px;font-weight:700;color:#0f172a;padding:4px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;white-space:nowrap;min-width:140px;text-align:center; }
.mj-view-toggle { display:flex;border:1px solid #e2e8f0;border-radius:7px;overflow:hidden; }
.mj-view-btn { padding:5px 12px;font-size:11px;font-weight:600;border:none;background:#fff;color:#64748b;cursor:pointer;transition:all .12s;font-family:inherit; }
.mj-view-btn.active { background:#0d1b2e;color:#fff; }
.mj-view-btn:not(.active):hover { background:#f1f5f9; }

/* Tombol Lokasi di nav */
.mj-lokasi-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 7px;
    background: #f0fdf9; border: 1.5px solid #a7f3d0;
    color: #059669; font-size: 11px; font-weight: 700;
    cursor: pointer; font-family: inherit; transition: all .15s;
    position: relative;
}
.mj-lokasi-btn:hover { background: #00c896; border-color: #00c896; color: #fff; }
.mj-lokasi-badge {
    position: absolute; top: -6px; right: -6px;
    width: 16px; height: 16px; border-radius: 50%;
    background: #00c896; color: #fff; font-size: 9px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid #fff;
}

/* WEEK TABS */
.mj-week-tabs { display:flex;gap:4px;margin-bottom:12px;flex-wrap:wrap; }
.mj-week-tab { padding:5px 13px;border:1.5px solid #e2e8f0;border-radius:7px;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .12s; }
.mj-week-tab.active { background:#0d1b2e;border-color:#0d1b2e;color:#fff; }
.mj-week-tab:hover:not(.active) { border-color:#00c896;color:#00c896;background:#f0fdf9; }

/* GRID */
.mj-table-wrap { overflow-x:auto;border-radius:10px;border:1px solid #e2e8f0;background:#fff; }
.mj-table { width:100%;border-collapse:collapse;min-width:800px; }
.mj-table th { padding:0;border-right:1px solid #e8ecf2; }
.mj-table td { border-right:1px solid #e8ecf2;border-bottom:1px solid #f0f4f8;vertical-align:top; }
.mj-table tr:last-child td { border-bottom:none; }
.mj-table th:last-child,.mj-table td:last-child { border-right:none; }
.mj-th-name { padding:12px 14px;background:#fafbfc;border-bottom:2px solid #e2e8f0;font-size:10.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;white-space:nowrap; }
.mj-th-day { padding:0;border-bottom:2px solid #e2e8f0;background:#fafbfc;min-width:80px; }
.mj-th-day-inner { padding:8px 6px;text-align:center; }
.mj-th-day-name { font-size:9.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px; }
.mj-th-day-num  { font-size:16px;font-weight:800;color:#1e293b;line-height:1.1;margin-top:1px;font-family:'JetBrains Mono',monospace; }
.mj-th-day.today .mj-th-day-num { color:#00c896; }
.mj-th-day.today .mj-th-day-name { color:#00a874; }
.mj-th-day.weekend { background:#fafaf8; }
.mj-th-day.weekend .mj-th-day-num { color:#94a3b8; }
.mj-td-name { padding:10px 12px;min-width:160px;max-width:200px;background:#fafbfc;border-right:2px solid #e2e8f0!important;position:sticky;left:0;z-index:2; }
.mj-emp-name { font-size:12px;font-weight:700;color:#0f172a;line-height:1.3; }
.mj-emp-meta { font-size:10px;color:#94a3b8;margin-top:1px; }
.mj-emp-nik  { font-family:'JetBrains Mono',monospace;font-size:9.5px;color:#6366f1; }
.mj-slot { padding:4px 5px;min-height:52px;cursor:pointer;transition:background .1s;position:relative; }
.mj-slot:hover { background:#f0f9ff; }
.mj-slot.weekend { background:#fafaf8; }
.mj-slot.weekend:hover { background:#f0fdf9; }
.mj-slot.today { background:#f0fdf9!important; }
.mj-pill { display:flex;align-items:center;gap:4px;padding:4px 7px;border-radius:6px;font-size:10.5px;font-weight:700;color:#fff;line-height:1.2;cursor:pointer;transition:opacity .12s;border:none;width:100%;font-family:inherit;margin-bottom:2px; }
.mj-pill:hover { opacity:.85; }
.mj-pill-kode { font-size:11px;font-weight:800;flex-shrink:0; }
.mj-pill-jam  { font-size:9px;opacity:.85;font-family:'JetBrains Mono',monospace; }
.mj-pill-libur  { background:#94a3b8!important; }
.mj-pill-cuti   { background:#8b5cf6!important; }
.mj-pill-dinas  { background:#0ea5e9!important; }
.mj-pill-izin   { background:#f97316!important; }
.mj-legend { display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px; }
.mj-legend-item { display:flex;align-items:center;gap:5px;font-size:10.5px;color:#64748b;font-weight:500; }
.mj-legend-dot  { width:14px;height:14px;border-radius:4px;flex-shrink:0; }
.mj-summary { display:flex;gap:8px;flex-wrap:wrap;padding:10px 14px;background:#fafbfc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px; }
.mj-summary-item { display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:#64748b; }
.mj-summary-num { font-size:14px;font-weight:800;color:#0f172a;font-family:monospace; }
.mj-filter { display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px; }
.mj-filter label { font-size:11px;font-weight:600;color:#64748b; }
.mj-filter select { height:32px;padding:0 10px;border:1.5px solid #e2e8f0;border-radius:7px;background:#fff;font-size:12px;font-family:inherit;color:#1e293b;outline:none;transition:border-color .15s;min-width:160px; }
.mj-filter select:focus { border-color:#00c896; }
.mj-copy-btn { display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border:1.5px solid #e2e8f0;border-radius:7px;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .12s; }
.mj-copy-btn:hover { border-color:#00c896;color:#00c896;background:#f0fdf9; }
.mj-empty { padding:48px 24px;text-align:center;color:#94a3b8; }
.mj-empty i { font-size:28px;display:block;margin-bottom:10px; }

/* ══════════════════════════════════════════
   POPOVER JADWAL — 2 kolom melebar
══════════════════════════════════════════ */
.mj-pop-backdrop { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9998; }
.mj-pop-backdrop.open { display:block; }

/* Modal lebih lebar, tidak memanjang ke bawah */
.mj-pop {
    display:none;position:fixed;z-index:9999;
    background:#fff;border-radius:14px;
    box-shadow:0 20px 60px rgba(0,0,0,.22);
    width:min(680px, 96vw);          /* LEBAR */
    max-height:90vh;
    overflow:hidden;
    animation:popIn .18s ease;
    top:50%;left:50%;
    transform:translate(-50%,-50%);
}
.mj-pop.open { display:flex;flex-direction:column; }
@keyframes popIn { from{opacity:0;transform:translate(-50%,-50%) scale(.95);}to{opacity:1;transform:translate(-50%,-50%) scale(1);} }

/* Header */
.mj-pop-header {
    position:sticky;top:0;background:#fff;z-index:2;
    padding:12px 16px 10px;
    border-bottom:1px solid #f0f2f7;
    display:flex;align-items:center;justify-content:space-between;
    flex-shrink:0;
}
.mj-pop-hd  { font-size:13px;font-weight:800;color:#0f172a;line-height:1.2; }
.mj-pop-sub { font-size:10.5px;color:#94a3b8;margin-top:2px; }

/* Body scrollable */
.mj-pop-body {
    padding:14px 16px;
    overflow-y:auto;
    flex:1;
}
.mj-pop-body::-webkit-scrollbar { width:4px; }
.mj-pop-body::-webkit-scrollbar-thumb { background:#e2e8f0;border-radius:2px; }

/* Baris tipe */
.mj-pop-types { display:flex;gap:4px;flex-wrap:wrap;margin-bottom:12px; }
.mj-pop-type { padding:4px 12px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;border:1.5px solid transparent;transition:all .12s; }
.mj-pop-type.active { border-color:#0f172a; }

/* 2-kolom: shift kiri, lokasi kanan */
.mj-pop-2col {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    margin-bottom:12px;
}
@media(max-width:520px){ .mj-pop-2col{ grid-template-columns:1fr; } }

.mj-pop-col-title {
    font-size:9.5px;font-weight:700;color:#94a3b8;
    text-transform:uppercase;letter-spacing:.5px;
    margin-bottom:6px;display:flex;align-items:center;gap:5px;
}
.mj-pop-col-title i { color:#00c896;font-size:8px; }

/* Grid shift (3 per baris dalam 1 kolom) */
.mj-pop-shifts {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:5px;
}
.mj-pop-shift { padding:7px 6px;border-radius:8px;cursor:pointer;border:2px solid transparent;transition:all .12s;text-align:center; }
.mj-pop-shift:hover { border-color:rgba(0,0,0,.2);transform:scale(1.03); }
.mj-pop-shift.selected { border-color:#0f172a;box-shadow:0 0 0 2px rgba(15,23,42,.12); }
.mj-pop-shift-kode { font-size:14px;font-weight:800;color:#fff;line-height:1; }
.mj-pop-shift-nama { font-size:9px;color:rgba(255,255,255,.8);margin-top:1px; }
.mj-pop-shift-jam  { font-size:9px;color:rgba(255,255,255,.9);font-family:monospace;margin-top:2px; }

/* Lokasi list (kolom kanan, scrollable) */
.mj-pop-lokasi-wrap {
    max-height:200px;
    overflow-y:auto;
    display:flex;flex-direction:column;gap:3px;
    padding-right:2px;
}
.mj-pop-lokasi-wrap::-webkit-scrollbar { width:3px; }
.mj-pop-lokasi-wrap::-webkit-scrollbar-thumb { background:#e2e8f0;border-radius:2px; }

.mj-pop-lokasi-semua {
    display:flex;align-items:center;gap:6px;
    padding:5px 9px;border-radius:6px;border:1.5px dashed #e2e8f0;
    cursor:pointer;font-size:11px;color:#94a3b8;font-weight:600;
    transition:all .12s;background:#fff;
}
.mj-pop-lokasi-semua:hover { border-color:#94a3b8;color:#64748b; }
.mj-pop-lokasi-semua.selected { border-color:#94a3b8;background:#f1f5f9;color:#64748b; }

.mj-pop-lokasi-item {
    display:flex;align-items:center;gap:7px;
    padding:6px 9px;border-radius:6px;border:1.5px solid #e2e8f0;
    cursor:pointer;transition:all .12s;background:#fafbfc;
    font-size:11px;font-weight:600;color:#374151;
}
.mj-pop-lokasi-item:hover { border-color:#00c896;background:#f0fdf9;color:#059669; }
.mj-pop-lokasi-item.selected { border-color:#00c896;background:#f0fdf9;color:#059669; }
.mj-pop-lokasi-item.selected::before { content:'✓ ';font-weight:800; }
.mj-pop-lokasi-item .lo-rad { font-size:9px;color:#94a3b8;font-weight:400;margin-left:auto;white-space:nowrap; }

/* Keterangan + footer */
.mj-pop-ket { width:100%;border:1.5px solid #e2e8f0;border-radius:6px;padding:5px 9px;font-size:11.5px;font-family:inherit;margin-bottom:0;resize:none;height:34px;transition:border-color .15s; }
.mj-pop-ket:focus { outline:none;border-color:#00c896; }

.mj-pop-footer {
    padding:10px 16px 14px;
    border-top:1px solid #f0f2f7;
    display:flex;gap:6px;
    flex-shrink:0;
    background:#fff;
}
.mj-pop-save { flex:1;padding:8px;background:#0d1b2e;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .12s;display:flex;align-items:center;justify-content:center;gap:6px; }
.mj-pop-save:hover { background:#1a3a5c; }
.mj-pop-del  { padding:8px 12px;background:#fee2e2;color:#b91c1c;border:none;border-radius:8px;font-size:12px;cursor:pointer;font-family:inherit;transition:background .12s; }
.mj-pop-del:hover { background:#fecaca; }
.mj-pop-cancel { padding:8px 14px;background:#f1f5f9;color:#64748b;border:none;border-radius:8px;font-size:12px;cursor:pointer;font-family:inherit; }

/* ── MODAL LOKASI ── */
.lo-ov {
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,.6);z-index:99999;
    align-items:flex-start;justify-content:center;
    padding:20px 16px;backdrop-filter:blur(3px);
    overflow-y:auto;
}
.lo-ov.open { display:flex; }
.lo-modal {
    background:#fff;border-radius:14px;
    box-shadow:0 24px 64px rgba(0,0,0,.25);
    width:100%;max-width:640px;
    animation:loIn .2s ease;
    margin:auto;
    overflow:hidden;
}
@keyframes loIn { from{opacity:0;transform:translateY(16px) scale(.97);}to{opacity:1;transform:none;} }
.lo-mhd {
    background:linear-gradient(130deg,#0d1b2e,#163354);
    padding:14px 18px;
    display:flex;align-items:center;gap:12px;color:#fff;
}
.lo-mhd-ico { width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center; }
.lo-mhd-ico i { color:#00c896;font-size:14px; }
.lo-mhd-title { font-size:14px;font-weight:700; }
.lo-mhd-sub   { font-size:11px;color:rgba(255,255,255,.45);margin-top:1px; }
.lo-close { margin-left:auto;width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.1);border:none;color:#fff;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;transition:background .12s; }
.lo-close:hover { background:#ef4444; }

.lo-list { padding:12px 16px;max-height:260px;overflow-y:auto; }
.lo-list::-webkit-scrollbar { width:4px; }
.lo-list::-webkit-scrollbar-thumb { background:#e2e8f0;border-radius:2px; }
.lo-item {
    display:flex;align-items:center;gap:10px;
    padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:9px;
    margin-bottom:8px;background:#fafbfc;transition:border-color .12s;
}
.lo-item:hover { border-color:#a7f3d0; }
.lo-item:last-child { margin-bottom:0; }
.lo-item-icon { font-size:18px;flex-shrink:0; }
.lo-item-nama { font-size:12.5px;font-weight:700;color:#0f172a; }
.lo-item-detail { font-size:10.5px;color:#94a3b8;margin-top:1px; }
.lo-item-coord { font-family:'JetBrains Mono',monospace;font-size:10px;color:#0369a1;margin-top:2px; }
.lo-item-actions { margin-left:auto;display:flex;gap:5px;flex-shrink:0; }
.lo-item-badge { font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:4px; }
.lo-item-badge.aktif    { background:#dcfce7;color:#15803d; }
.lo-item-badge.nonaktif { background:#f1f5f9;color:#64748b; }

.lo-form-wrap { padding:14px 16px;border-top:1px solid #e2e8f0;background:#f8fafc; }
.lo-form-title { font-size:11px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:flex;align-items:center;gap:6px; }
.lo-form-title i { color:#00c896; }
.lo-g2 { display:grid;grid-template-columns:1fr 1fr;gap:8px; }
.lo-fg { display:flex;flex-direction:column;gap:3px;margin-bottom:8px; }
.lo-fg label { font-size:10.5px;font-weight:700;color:#374151; }
.lo-fg input,.lo-fg select,.lo-fg textarea {
    border:1.5px solid #e2e8f0;border-radius:7px;padding:0 10px;
    font-size:12px;font-family:inherit;background:#fff;color:#111827;
    transition:border-color .15s;width:100%;
}
.lo-fg input,.lo-fg select { height:34px; }
.lo-fg textarea { padding:7px 10px;resize:none;height:52px; }
.lo-fg input:focus,.lo-fg select:focus,.lo-fg textarea:focus {
    outline:none;border-color:#00c896;
    box-shadow:0 0 0 3px rgba(0,200,150,.09);
}
.lo-btn-gps {
    width:100%;height:34px;border-radius:7px;
    border:1.5px solid #00c896;background:#f0fdf9;
    color:#059669;font-size:11.5px;font-weight:700;
    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;
    font-family:inherit;transition:all .12s;margin-bottom:8px;
}
.lo-btn-gps:hover { background:#00c896;color:#fff; }
.lo-map-mini {
    width:100%;height:160px;border-radius:8px;border:1px solid #e2e8f0;
    overflow:hidden;background:#f0f9ff;margin-bottom:8px;position:relative;
}
.lo-map-mini iframe { width:100%;height:100%;border:none; }
.lo-map-ph { position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;font-size:11px;color:#94a3b8; }
.lo-map-ph i { font-size:22px;color:#cbd5e1; }
.lo-form-btns { display:flex;gap:6px;margin-top:4px; }
.lo-btn-save   { flex:1;height:36px;background:#0d1b2e;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .12s; }
.lo-btn-save:hover { background:#1a3a5c; }
.lo-btn-cancel2 { height:36px;padding:0 14px;background:#f1f5f9;color:#64748b;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit; }

/* Toast */
.mj-toast { position:fixed;bottom:20px;right:20px;background:#0d1b2e;color:#fff;padding:10px 16px;border-radius:8px;font-size:12px;font-weight:600;z-index:999999;display:flex;align-items:center;gap:7px;box-shadow:0 4px 20px rgba(0,0,0,.2);animation:toastIn .2s ease;max-width:280px; }
.mj-toast.ok  i { color:#00c896; }
.mj-toast.err i { color:#ef4444; }
@keyframes toastIn { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;} }
@keyframes loSpin  { to{transform:rotate(360deg);} }
@media(max-width:560px){ .lo-g2{grid-template-columns:1fr;} }
</style>

<div class="page-header">
    <h4><i class="fa fa-calendar-days text-primary"></i> &nbsp;Master Jadwal Karyawan</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
        <a href="<?= APP_URL ?>/pages/master_karyawan.php">SDM</a><span class="sep">/</span>
        <a href="<?= APP_URL ?>/pages/master_shift.php">Master Shift</a><span class="sep">/</span>
        <span class="cur">Master Jadwal</span>
    </div>
</div>

<div class="content mj">
    <?= showFlash() ?>

    <!-- NAVIGATION BAR -->
    <div class="mj-nav">
        <div class="mj-nav-title">
            <i class="fa fa-calendar-days"></i> Penjadwalan Shift
        </div>

        <div class="mj-month-nav">
            <?php
                $prev_b = $bulan-1; $prev_t = $tahun; if($prev_b<1){$prev_b=12;$prev_t--;}
                $next_b = $bulan+1; $next_t = $tahun; if($next_b>12){$next_b=1;$next_t++;}
            ?>
            <a href="?bulan=<?=$prev_b?>&tahun=<?=$prev_t?>&view=<?=$view_mode?>&bagian=<?=$bagian_id?>" class="mj-month-btn"><i class="fa fa-chevron-left"></i></a>
            <span class="mj-month-label"><?= $nama_bulan[$bulan] ?> <?= $tahun ?></span>
            <a href="?bulan=<?=$next_b?>&tahun=<?=$next_t?>&view=<?=$view_mode?>&bagian=<?=$bagian_id?>" class="mj-month-btn"><i class="fa fa-chevron-right"></i></a>
        </div>

        <div class="mj-view-toggle">
            <a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&view=minggu&bagian=<?=$bagian_id?>" class="mj-view-btn <?=$view_mode==='minggu'?'active':''?>" style="text-decoration:none;">
                <i class="fa fa-calendar-week" style="margin-right:4px;"></i> Mingguan
            </a>
            <a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&view=bulan&bagian=<?=$bagian_id?>" class="mj-view-btn <?=$view_mode==='bulan'?'active':''?>" style="text-decoration:none;">
                <i class="fa fa-calendar" style="margin-right:4px;"></i> Bulanan
            </a>
        </div>

        <a href="?bulan=<?=date('n')?>&tahun=<?=date('Y')?>&view=<?=$view_mode?>&bagian=<?=$bagian_id?>" class="mj-copy-btn" style="margin-left:auto;text-decoration:none;">
            <i class="fa fa-crosshairs"></i> Hari Ini
        </a>

        <?php if ($view_mode === 'minggu'): ?>
        <button class="mj-copy-btn" id="btn-copy-week" data-mulai="<?=$tgl_mulai?>" data-bagian="<?=$bagian_id?>">
            <i class="fa fa-copy"></i> Copy Minggu Lalu
        </button>
        <?php endif; ?>

        <button class="mj-lokasi-btn" id="btn-lokasi" title="Setting Lokasi Absen">
            <i class="fa fa-map-marker-alt"></i> Lokasi Absen
            <?php if ($lokasi_aktif_count > 0): ?>
            <span class="mj-lokasi-badge"><?= $lokasi_aktif_count ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- FILTER + LEGEND -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
        <div class="mj-filter">
            <label><i class="fa fa-building" style="font-size:10px;margin-right:3px;"></i> Unit/Bagian:</label>
            <select id="filter-bagian" onchange="goBagian(this.value)">
                <option value="0">Semua Unit</option>
                <?php foreach ($bagian_list as $b): ?>
                <option value="<?=$b['id']?>" <?=$bagian_id==$b['id']?'selected':''?>><?= htmlspecialchars($b['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mj-legend">
            <?php foreach ($shifts as $sh): ?>
            <div class="mj-legend-item">
                <div class="mj-legend-dot" style="background:<?= htmlspecialchars($sh['warna']) ?>;"></div>
                <span><?= htmlspecialchars($sh['kode']) ?> — <?= htmlspecialchars($sh['nama']) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="mj-legend-item"><div class="mj-legend-dot" style="background:#94a3b8;"></div><span>Libur</span></div>
            <div class="mj-legend-item"><div class="mj-legend-dot" style="background:#8b5cf6;"></div><span>Cuti</span></div>
            <div class="mj-legend-item"><div class="mj-legend-dot" style="background:#0ea5e9;"></div><span>Dinas</span></div>
        </div>
    </div>

    <!-- WEEK TABS -->
    <?php if ($view_mode === 'minggu'): ?>
    <div class="mj-week-tabs">
        <?php foreach ($weeks_in_month as $wi => $wk): ?>
        <?php $is_active = ($tgl_mulai >= $wk['mulai'] && $tgl_mulai <= $wk['akhir']); ?>
        <a href="?bulan=<?=$bulan?>&tahun=<?=$tahun?>&view=minggu&minggu=<?=$wi?>&bagian=<?=$bagian_id?>"
           class="mj-week-tab <?=$is_active?'active':''?>">
            Mg <?=$wi+1?> &nbsp;<span style="font-weight:400;opacity:.7;"><?=date('j',strtotime($wk['mulai']))?>–<?=date('j',strtotime($wk['akhir'])) ?> <?=$nama_bulan[(int)date('n',strtotime($wk['mulai']))]?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- SUMMARY -->
    <?php
        $cnt_shift=0;$cnt_libur=0;$cnt_cuti=0;
        foreach($jadwal_all as $j){ if($j['tipe']==='shift')$cnt_shift++;if($j['tipe']==='libur')$cnt_libur++;if($j['tipe']==='cuti')$cnt_cuti++; }
        $cnt_kosong_total = count($karyawan_list)*count($tanggal_list) - count($jadwal_all);
    ?>
    <div class="mj-summary">
        <div class="mj-summary-item"><span class="mj-summary-num"><?=count($karyawan_list)?></span> karyawan</div>
        <div class="mj-summary-item" style="padding-left:12px;border-left:1px solid #e2e8f0;"><span class="mj-summary-num" style="color:#00c896;"><?=$cnt_shift?></span> jadwal shift</div>
        <div class="mj-summary-item" style="padding-left:12px;border-left:1px solid #e2e8f0;"><span class="mj-summary-num" style="color:#94a3b8;"><?=$cnt_libur?></span> libur</div>
        <div class="mj-summary-item" style="padding-left:12px;border-left:1px solid #e2e8f0;"><span class="mj-summary-num" style="color:#8b5cf6;"><?=$cnt_cuti?></span> cuti</div>
        <div class="mj-summary-item" style="padding-left:12px;border-left:1px solid #e2e8f0;"><span class="mj-summary-num" style="color:#ef4444;"><?=$cnt_kosong_total?></span> belum dijadwalkan</div>
        <div style="margin-left:auto;font-size:11px;color:#94a3b8;"><?=date('d M',strtotime($tgl_mulai))?> – <?=date('d M Y',strtotime($tgl_akhir))?></div>
    </div>

    <!-- GRID JADWAL -->
    <?php if (empty($karyawan_list)): ?>
    <div class="panel"><div class="mj-empty">
        <i class="fa fa-users"></i>
        <div style="font-weight:700;color:#475569;margin-bottom:4px;">Tidak ada karyawan</div>
        <div style="font-size:12px;">Pilih unit/bagian atau tambah karyawan terlebih dahulu</div>
    </div></div>
    <?php else: ?>
    <div class="mj-table-wrap">
        <table class="mj-table">
            <thead>
                <tr>
                    <th><div class="mj-th-name">Karyawan</div></th>
                    <?php foreach ($tanggal_list as $tgl):
                        $dow=(int)date('N',strtotime($tgl));$is_wend=$dow>=6;$is_today=$tgl===date('Y-m-d');
                        $cls=($is_wend?'weekend':'').($is_today?' today':'');
                    ?>
                    <th class="mj-th-day <?=$cls?>">
                        <div class="mj-th-day-inner">
                            <div class="mj-th-day-name"><?=$nama_hari[$dow-1]?></div>
                            <div class="mj-th-day-num"><?=date('j',strtotime($tgl))?></div>
                            <?php if ((int)date('j',strtotime($tgl))===1): ?>
                            <div style="font-size:8.5px;color:#94a3b8;font-weight:600;"><?=$nama_bulan[(int)date('n',strtotime($tgl))]?></div>
                            <?php endif; ?>
                        </div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php $cur_divisi=null; foreach($karyawan_list as $kry): if($kry['divisi']!==$cur_divisi): $cur_divisi=$kry['divisi']; ?>
            <tr>
                <td colspan="<?=count($tanggal_list)+1?>" style="padding:6px 14px;background:#f8fafc;border-bottom:1px solid #e8ecf2;">
                    <span style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;">
                        <i class="fa fa-building" style="font-size:9px;margin-right:4px;"></i>
                        <?=htmlspecialchars($kry['divisi']?:'Tanpa Divisi')?>
                    </span>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="mj-td-name">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#00e5b0,#00c896);color:#0a0f14;font-size:9px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <?php $ws=array_filter(explode(' ',$kry['nama']));echo strtoupper(implode('',array_map(fn($w)=>mb_substr($w,0,1),array_slice(array_values($ws),0,2)))); ?>
                        </div>
                        <div style="min-width:0;">
                            <div class="mj-emp-name"><?=($kry['gelar_depan']?htmlspecialchars($kry['gelar_depan']).' ':'').htmlspecialchars($kry['nama'])?></div>
                            <?php if($kry['nik_rs']): ?><div class="mj-emp-nik"><?=htmlspecialchars($kry['nik_rs'])?></div><?php endif; ?>
                            <?php if($kry['jenis_karyawan']): ?><div class="mj-emp-meta"><?=htmlspecialchars($kry['jenis_karyawan'])?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <?php foreach($tanggal_list as $tgl):
                    $dow=(int)date('N',strtotime($tgl));$is_wend=$dow>=6;$is_today=$tgl===date('Y-m-d');
                    $j=$jadwal_map[$kry['id']][$tgl]??null;
                    $slot_cls=($is_wend?'weekend ':'').($is_today?'today ':'');
                ?>
                <td>
                    <div class="mj-slot <?=$slot_cls?>" data-uid="<?=$kry['id']?>" data-nama="<?=htmlspecialchars($kry['nama'],ENT_QUOTES)?>" data-tgl="<?=$tgl?>" data-lokasi="<?= $j ? (int)($j['lokasi_id']??0) : 0 ?>" onclick="openPop(this,event)">
                        <?php if($j): ?>
                            <?php if($j['tipe']==='shift'&&$j['shift_id']): ?>
                            <button class="mj-pill" style="background:<?=htmlspecialchars($j['shift_warna']??'#64748b')?>" type="button">
                                <span class="mj-pill-kode"><?=htmlspecialchars($j['shift_kode']??'')?></span>
                                <span class="mj-pill-jam"><?=substr($j['jam_masuk']??'',0,5)?>-<?=substr($j['jam_keluar']??'',0,5)?></span>
                            </button>
                            <?php if (!empty($j['lokasi_nama'])): ?>
                            <div style="font-size:9px;color:#0369a1;font-weight:600;padding:1px 5px;background:#e0f2fe;border-radius:4px;display:inline-flex;align-items:center;gap:3px;margin-top:1px;">
                                <i class="fa fa-map-marker-alt" style="font-size:7px;"></i><?= htmlspecialchars($j['lokasi_nama']) ?>
                            </div>
                            <?php endif; ?>
                            <?php elseif($j['tipe']==='libur'): ?>
                            <button class="mj-pill mj-pill-libur" type="button"><i class="fa fa-moon" style="font-size:9px;"></i><span>Libur<?=$j['keterangan']?' · '.htmlspecialchars($j['keterangan']):''?></span></button>
                            <?php elseif($j['tipe']==='cuti'): ?>
                            <button class="mj-pill mj-pill-cuti" type="button"><i class="fa fa-umbrella-beach" style="font-size:9px;"></i><span>Cuti<?=$j['keterangan']?' · '.htmlspecialchars($j['keterangan']):''?></span></button>
                            <?php elseif($j['tipe']==='dinas'): ?>
                            <button class="mj-pill mj-pill-dinas" type="button"><i class="fa fa-briefcase" style="font-size:9px;"></i><span>Dinas<?=$j['keterangan']?' · '.htmlspecialchars($j['keterangan']):''?></span></button>
                            <?php elseif($j['tipe']==='izin'): ?>
                            <button class="mj-pill mj-pill-izin" type="button"><i class="fa fa-hand" style="font-size:9px;"></i><span>Izin<?=$j['keterangan']?' · '.htmlspecialchars($j['keterangan']):''?></span></button>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="display:flex;align-items:center;justify-content:center;height:40px;color:#e2e8f0;font-size:16px;"><i class="fa fa-plus"></i></div>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div><!-- /.content -->

<!-- ══ MODAL POPOVER JADWAL ══ -->
<div id="mj-backdrop" class="mj-pop-backdrop" onclick="closePop()"></div>
<div id="mj-pop" class="mj-pop">

    <!-- Header -->
    <div class="mj-pop-header">
        <div>
            <div class="mj-pop-hd" id="pop-nama"></div>
            <div class="mj-pop-sub" id="pop-tgl"></div>
        </div>
        <button onclick="closePop()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:15px;padding:0;line-height:1;"><i class="fa fa-times"></i></button>
    </div>

    <!-- Body -->
    <div class="mj-pop-body">

        <!-- Tipe baris -->
        <div style="margin-bottom:10px;">
            <div style="font-size:9.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Tipe</div>
            <div class="mj-pop-types">
                <span class="mj-pop-type active" data-tipe="shift"  style="background:#f0f9ff;color:#0369a1;"  onclick="setTipe('shift')">Shift</span>
                <span class="mj-pop-type"        data-tipe="libur"  style="background:#f1f5f9;color:#475569;"  onclick="setTipe('libur')">Libur</span>
                <span class="mj-pop-type"        data-tipe="cuti"   style="background:#faf5ff;color:#6d28d9;"  onclick="setTipe('cuti')">Cuti</span>
                <span class="mj-pop-type"        data-tipe="dinas"  style="background:#e0f2fe;color:#0369a1;"  onclick="setTipe('dinas')">Dinas</span>
                <span class="mj-pop-type"        data-tipe="izin"   style="background:#fff7ed;color:#c2410c;"  onclick="setTipe('izin')">Izin</span>
            </div>
        </div>

        <!-- 2 kolom: shift | lokasi -->
        <div id="pop-shift-section" class="mj-pop-2col">

            <!-- Kolom kiri: Pilih Shift -->
            <div>
                <div class="mj-pop-col-title"><i class="fa fa-clock"></i> Pilih Shift</div>
                <div class="mj-pop-shifts" id="pop-shifts">
                    <?php foreach($shifts as $sh): ?>
                    <div class="mj-pop-shift" data-shift="<?=$sh['id']?>" style="background:<?=htmlspecialchars($sh['warna'])?>;" onclick="selShift(<?=$sh['id']?>)">
                        <div class="mj-pop-shift-kode"><?=htmlspecialchars($sh['kode'])?></div>
                        <div class="mj-pop-shift-nama"><?=htmlspecialchars($sh['nama'])?></div>
                        <?php if($sh['jenis']!=='oncall'): ?>
                        <div class="mj-pop-shift-jam"><?=substr($sh['jam_masuk'],0,5)?>-<?=substr($sh['jam_keluar'],0,5)?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Kolom kanan: Lokasi Absen -->
            <?php if (!empty($lokasi_list_aktif)): ?>
            <div>
                <div class="mj-pop-col-title"><i class="fa fa-map-marker-alt"></i> Lokasi Absen <span style="font-weight:400;font-size:9px;color:#94a3b8;text-transform:none;">(opsional)</span></div>
                <div class="mj-pop-lokasi-wrap" id="pop-lokasi-list">
                    <div class="mj-pop-lokasi-semua selected" data-lokasi="0" onclick="selLokasi(0)">
                        <i class="fa fa-globe" style="font-size:10px;color:#94a3b8;"></i>
                        <span>Semua / bebas</span>
                    </div>
                    <?php foreach($lokasi_list_aktif as $lok): ?>
                    <div class="mj-pop-lokasi-item" data-lokasi="<?=$lok['id']?>" onclick="selLokasi(<?=$lok['id']?>)">
                        <i class="fa fa-map-marker-alt" style="font-size:10px;color:#00c896;flex-shrink:0;"></i>
                        <span style="flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?=htmlspecialchars($lok['nama'])?></span>
                        <span class="lo-rad">r=<?=$lok['radius']?>m</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Keterangan -->
        <div>
            <div style="font-size:9.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Keterangan</div>
            <textarea class="mj-pop-ket" id="pop-ket" placeholder="Opsional…"></textarea>
        </div>
    </div>

    <!-- Footer -->
    <div class="mj-pop-footer">
        <button class="mj-pop-save" onclick="saveSlot()"><i class="fa fa-save"></i> Simpan</button>
        <button class="mj-pop-del"  onclick="deleteSlot()" title="Hapus jadwal"><i class="fa fa-trash"></i></button>
        <button class="mj-pop-cancel" onclick="closePop()">Batal</button>
    </div>
</div>

<!-- ══ MODAL LOKASI ABSEN ══ -->
<div id="lo-modal" class="lo-ov">
    <div class="lo-modal">
        <div class="lo-mhd">
            <div class="lo-mhd-ico"><i class="fa fa-map-marker-alt"></i></div>
            <div>
                <div class="lo-mhd-title">Setting Lokasi Absen</div>
                <div class="lo-mhd-sub">Daftarkan area yang diizinkan untuk absen karyawan</div>
            </div>
            <button type="button" class="lo-close" id="lo-close"><i class="fa fa-times"></i></button>
        </div>

        <div class="lo-list" id="lo-list">
            <div style="text-align:center;padding:24px;color:#94a3b8;font-size:12px;">
                <div style="width:28px;height:28px;border:3px solid #e2e8f0;border-top-color:#00c896;border-radius:50%;animation:loSpin .7s linear infinite;margin:0 auto 8px;"></div>
                Memuat data lokasi…
            </div>
        </div>

        <div class="lo-form-wrap">
            <div class="lo-form-title">
                <i class="fa fa-plus-circle"></i>
                <span id="lo-form-title-text">Tambah Lokasi Baru</span>
            </div>
            <div class="lo-map-mini" id="lo-map-mini">
                <div class="lo-map-ph" id="lo-map-ph">
                    <i class="fa fa-map-location-dot"></i>
                    <span>Masukkan koordinat untuk preview peta</span>
                </div>
                <iframe id="lo-map-iframe" style="display:none;width:100%;height:100%;border:none;"></iframe>
            </div>
            <button type="button" class="lo-btn-gps" id="lo-btn-gps">
                <i class="fa fa-crosshairs"></i> Gunakan Lokasi Saya Saat Ini
            </button>
            <input type="hidden" id="lo-edit-id" value="">
            <div class="lo-g2">
                <div class="lo-fg">
                    <label>Nama Lokasi *</label>
                    <input type="text" id="lo-nama" placeholder="Kantor Pusat">
                </div>
                <div class="lo-fg">
                    <label>Radius (meter)</label>
                    <input type="number" id="lo-radius" value="100" min="10" max="5000">
                </div>
            </div>
            <div class="lo-fg">
                <label>Alamat <span style="font-weight:400;color:#94a3b8;">(opsional)</span></label>
                <textarea id="lo-alamat" placeholder="Jl. Contoh No.1…"></textarea>
            </div>
            <div class="lo-g2">
                <div class="lo-fg">
                    <label>Latitude *</label>
                    <input type="number" id="lo-lat" placeholder="-6.200000" step="0.0000001">
                </div>
                <div class="lo-fg">
                    <label>Longitude *</label>
                    <input type="number" id="lo-lon" placeholder="106.816666" step="0.0000001">
                </div>
            </div>
            <div style="font-size:10px;color:#94a3b8;margin:-4px 0 8px;">
                <i class="fa fa-lightbulb" style="color:#f59e0b;"></i>
                Google Maps → klik kanan lokasi → salin koordinat
            </div>
            <div class="lo-g2">
                <div class="lo-fg">
                    <label>Status</label>
                    <select id="lo-status">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                <div class="lo-fg">
                    <label>Keterangan <span style="font-weight:400;color:#94a3b8;">(opsional)</span></label>
                    <input type="text" id="lo-ket" placeholder="Catatan…">
                </div>
            </div>
            <div class="lo-form-btns">
                <button class="lo-btn-save" id="lo-btn-save"><i class="fa fa-save" style="margin-right:4px;"></i>Simpan Lokasi</button>
                <button class="lo-btn-cancel2" id="lo-btn-cancel2" style="display:none;" onclick="loResetForm()">Batal Edit</button>
            </div>
        </div>
    </div>
</div>

<div id="mj-toast" style="display:none;" class="mj-toast"><i></i><span></span></div>

<script>
const APP_URL = '<?= APP_URL ?>';
const SHIFTS  = <?= json_encode($shifts) ?>;

/* ══════════════════════════════════════════
   JADWAL — Popover slot
══════════════════════════════════════════ */
var _uid=null,_tgl=null,_shiftId=null,_tipe='shift',_lokasiId=0;

function openPop(cell,e){
    e.stopPropagation();
    _uid=cell.dataset.uid; _tgl=cell.dataset.tgl;
    _lokasiId = parseInt(cell.dataset.lokasi||0)||0;
    document.getElementById('pop-nama').textContent=cell.dataset.nama;
    var d=new Date(_tgl+'T00:00:00');
    document.getElementById('pop-tgl').textContent=d.toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
    _shiftId=null; _tipe='shift';
    document.getElementById('pop-ket').value='';
    document.querySelectorAll('.mj-pop-shift').forEach(function(el){el.classList.remove('selected');});
    setTipe('shift',false);
    selLokasi(_lokasiId, false);
    var pill=cell.querySelector('.mj-pill');
    if(pill){
        var kls=pill.className;
        if(kls.includes('mj-pill-libur'))       setTipe('libur',false);
        else if(kls.includes('mj-pill-cuti'))   setTipe('cuti',false);
        else if(kls.includes('mj-pill-dinas'))  setTipe('dinas',false);
        else if(kls.includes('mj-pill-izin'))   setTipe('izin',false);
        else{
            setTipe('shift',false);
            var kode=pill.querySelector('.mj-pill-kode');
            if(kode){var sh=SHIFTS.find(function(s){return s.kode===kode.textContent.trim();});if(sh)selShift(sh.id,false);}
        }
        var pt=pill.textContent.trim(),ks=pt.indexOf(' · ');
        if(ks>-1)document.getElementById('pop-ket').value=pt.substring(ks+3);
    }
    document.getElementById('mj-pop').classList.add('open');
    document.getElementById('mj-backdrop').classList.add('open');
    document.body.style.overflow='hidden';
}

function closePop(){
    document.getElementById('mj-pop').classList.remove('open');
    document.getElementById('mj-backdrop').classList.remove('open');
    document.body.style.overflow='';
    _uid=null; _tgl=null;
}

function setTipe(tipe,upd){
    _tipe=tipe;
    document.querySelectorAll('.mj-pop-type').forEach(function(el){el.classList.toggle('active',el.dataset.tipe===tipe);});
    document.getElementById('pop-shift-section').style.display=tipe==='shift'?'grid':'none';
    if(upd!==false&&tipe!=='shift') _shiftId=null;
}

function selShift(id){
    _shiftId=id;
    document.querySelectorAll('.mj-pop-shift').forEach(function(el){el.classList.toggle('selected',parseInt(el.dataset.shift)===id);});
}

function selLokasi(id, updateState){
    if(updateState!==false) _lokasiId=id;
    document.querySelectorAll('.mj-pop-lokasi-item, .mj-pop-lokasi-semua').forEach(function(el){
        el.classList.toggle('selected', parseInt(el.dataset.lokasi)===id);
    });
}

function saveSlot(){
    if(!_uid||!_tgl) return;
    if(_tipe==='shift'&&!_shiftId){showToast('Pilih shift terlebih dahulu',false); return;}
    var fd=new FormData();
    fd.append('ajax_simpan_slot','1'); fd.append('user_id',_uid); fd.append('tanggal',_tgl);
    fd.append('shift_id',_shiftId||'');
    fd.append('lokasi_id',_lokasiId||'');
    fd.append('tipe',_tipe); fd.append('keterangan',document.getElementById('pop-ket').value);
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.ok){showToast(d.msg,true);closePop();setTimeout(function(){location.reload();},400);}
            else showToast(d.msg||'Gagal',false);
        })
        .catch(function(){showToast('Kesalahan jaringan',false);});
}

function deleteSlot(){
    if(!_uid||!_tgl) return;
    var fd=new FormData();
    fd.append('ajax_simpan_slot','1'); fd.append('user_id',_uid); fd.append('tanggal',_tgl);
    fd.append('tipe','kosong'); fd.append('shift_id',''); fd.append('lokasi_id',''); fd.append('keterangan','');
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){if(d.ok){showToast('Jadwal dihapus',true);closePop();setTimeout(function(){location.reload();},400);}});
}

/* ── Copy minggu ── */
var copyBtn=document.getElementById('btn-copy-week');
if(copyBtn){
    copyBtn.onclick=function(){
        if(!confirm('Salin jadwal dari minggu lalu?\nSlot yang sudah ada tidak akan tertimpa.')) return;
        var fd=new FormData();
        fd.append('ajax_copy_minggu','1'); fd.append('tgl_mulai',this.dataset.mulai); fd.append('bagian_id',this.dataset.bagian);
        fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(d){showToast(d.msg,d.ok);if(d.ok&&d.copied>0)setTimeout(function(){location.reload();},400);});
    };
}

/* ── Filter bagian ── */
function goBagian(bid){var url=new URL(location.href);url.searchParams.set('bagian',bid);location.href=url.toString();}

/* ══════════════════════════════════════════
   LOKASI ABSEN — Modal
══════════════════════════════════════════ */
var loModal = document.getElementById('lo-modal');

document.getElementById('btn-lokasi').onclick = function(){
    loModal.classList.add('open');
    document.body.style.overflow='hidden';
    loLoadList();
};
document.getElementById('lo-close').onclick = loCloseModal;
loModal.addEventListener('click', function(e){ if(e.target===loModal) loCloseModal(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ if(loModal.classList.contains('open')) loCloseModal(); else closePop(); } });

function loCloseModal(){
    loModal.classList.remove('open');
    document.body.style.overflow='';
    loResetForm();
}

function loLoadList(){
    var listEl = document.getElementById('lo-list');
    listEl.innerHTML = '<div style="text-align:center;padding:20px;color:#94a3b8;font-size:12px;"><div style="width:24px;height:24px;border:3px solid #e2e8f0;border-top-color:#00c896;border-radius:50%;animation:loSpin .7s linear infinite;margin:0 auto 8px;"></div>Memuat…</div>';
    var fd=new FormData(); fd.append('ajax_lokasi','1'); fd.append('act','list');
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d.ok||!d.data.length){
                listEl.innerHTML='<div style="text-align:center;padding:20px;color:#94a3b8;font-size:12px;"><i class="fa fa-map-marker-alt" style="font-size:20px;display:block;margin-bottom:6px;"></i>Belum ada lokasi terdaftar.<br><span style="font-size:10.5px;">Absen bisa dilakukan dari mana saja.</span></div>';
                return;
            }
            listEl.innerHTML = d.data.map(function(lok){
                return '<div class="lo-item" id="lo-item-'+lok.id+'">'
                    +'<div class="lo-item-icon">📍</div>'
                    +'<div style="flex:1;min-width:0;">'
                        +'<div class="lo-item-nama">'+escHtml(lok.nama)+'</div>'
                        +(lok.alamat?'<div class="lo-item-detail">'+escHtml(lok.alamat)+'</div>':'')
                        +'<div class="lo-item-coord">'+parseFloat(lok.lat).toFixed(6)+', '+parseFloat(lok.lon).toFixed(6)+' &nbsp;·&nbsp; r='+lok.radius+'m</div>'
                    +'</div>'
                    +'<div class="lo-item-actions">'
                        +'<span class="lo-item-badge '+lok.status+'">'+lok.status+'</span>'
                        +'<button onclick="loToggle('+lok.id+')" class="btn btn-sm btn-default" style="font-size:10px;" title="Toggle status"><i class="fa '+(lok.status==='aktif'?'fa-eye-slash':'fa-eye')+'"></i></button>'
                        +'<button onclick="loEdit('+JSON.stringify(lok).replace(/"/g,'&quot;')+')" class="btn btn-sm btn-primary" style="font-size:10px;"><i class="fa fa-pen"></i></button>'
                        +'<button onclick="loHapus('+lok.id+',\''+escHtml(lok.nama)+'\')" class="btn btn-sm btn-danger" style="font-size:10px;"><i class="fa fa-trash"></i></button>'
                    +'</div>'
                    +'</div>';
            }).join('');
        });
}

function loEdit(lok){
    document.getElementById('lo-edit-id').value = lok.id;
    document.getElementById('lo-nama').value    = lok.nama||'';
    document.getElementById('lo-alamat').value  = lok.alamat||'';
    document.getElementById('lo-lat').value     = lok.lat||'';
    document.getElementById('lo-lon').value     = lok.lon||'';
    document.getElementById('lo-radius').value  = lok.radius||100;
    document.getElementById('lo-status').value  = lok.status||'aktif';
    document.getElementById('lo-ket').value     = lok.keterangan||'';
    document.getElementById('lo-form-title-text').textContent = 'Edit Lokasi: '+lok.nama;
    document.getElementById('lo-btn-cancel2').style.display='inline-flex';
    loUpdateMap(lok.lat, lok.lon);
    document.querySelector('.lo-form-wrap').scrollIntoView({behavior:'smooth',block:'start'});
}

function loToggle(id){
    var fd=new FormData(); fd.append('ajax_lokasi','1'); fd.append('act','toggle'); fd.append('id',id);
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){ if(d.ok){ showToast(d.msg,true); loLoadList(); updateNavBadge(); } });
}

function loHapus(id,nama){
    if(!confirm('Hapus lokasi "'+nama+'"?')) return;
    var fd=new FormData(); fd.append('ajax_lokasi','1'); fd.append('act','hapus'); fd.append('id',id);
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){ if(d.ok){ showToast(d.msg,true); loLoadList(); updateNavBadge(); } });
}

document.getElementById('lo-btn-save').onclick = function(){
    var id     = document.getElementById('lo-edit-id').value;
    var nama   = document.getElementById('lo-nama').value.trim();
    var lat    = document.getElementById('lo-lat').value.trim();
    var lon    = document.getElementById('lo-lon').value.trim();
    if(!nama){ showToast('Nama lokasi wajib diisi','err'); return; }
    if(!lat||!lon||isNaN(lat)||isNaN(lon)){ showToast('Koordinat tidak valid','err'); return; }
    var fd=new FormData();
    fd.append('ajax_lokasi','1');
    fd.append('act',    id ? 'update' : 'simpan');
    fd.append('id',     id);
    fd.append('nama',   nama);
    fd.append('alamat', document.getElementById('lo-alamat').value);
    fd.append('lat',    lat);
    fd.append('lon',    lon);
    fd.append('radius', document.getElementById('lo-radius').value);
    fd.append('status', document.getElementById('lo-status').value);
    fd.append('keterangan', document.getElementById('lo-ket').value);
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.ok){ showToast(d.msg,true); loResetForm(); loLoadList(); updateNavBadge(); }
            else showToast(d.msg||'Gagal menyimpan','err');
        });
};

function loResetForm(){
    ['lo-edit-id','lo-nama','lo-alamat','lo-lat','lo-lon','lo-ket'].forEach(function(id){ document.getElementById(id).value=''; });
    document.getElementById('lo-radius').value=100;
    document.getElementById('lo-status').value='aktif';
    document.getElementById('lo-form-title-text').textContent='Tambah Lokasi Baru';
    document.getElementById('lo-btn-cancel2').style.display='none';
    loResetMap();
}

var loMapTimer;
function loUpdateMap(lat,lon){
    if(!lat||!lon||isNaN(lat)||isNaN(lon)){loResetMap();return;}
    var fLat=parseFloat(lat),fLon=parseFloat(lon);
    var url='https://www.openstreetmap.org/export/embed.html?bbox='+(fLon-0.003)+','+(fLat-0.003)+','+(fLon+0.003)+','+(fLat+0.003)+'&layer=mapnik&marker='+fLat+','+fLon;
    var iframe=document.getElementById('lo-map-iframe');
    iframe.src=url; iframe.style.display='block';
    document.getElementById('lo-map-ph').style.display='none';
}
function loResetMap(){
    document.getElementById('lo-map-iframe').style.display='none';
    document.getElementById('lo-map-ph').style.display='flex';
}
document.getElementById('lo-lat').addEventListener('input',function(){ clearTimeout(loMapTimer); loMapTimer=setTimeout(function(){ loUpdateMap(document.getElementById('lo-lat').value,document.getElementById('lo-lon').value); },800); });
document.getElementById('lo-lon').addEventListener('input',function(){ clearTimeout(loMapTimer); loMapTimer=setTimeout(function(){ loUpdateMap(document.getElementById('lo-lat').value,document.getElementById('lo-lon').value); },800); });

document.getElementById('lo-btn-gps').onclick = function(){
    var btn=this;
    btn.innerHTML='<i class="fa fa-spinner fa-spin"></i> Mendapatkan lokasi…';
    btn.disabled=true;
    if(!navigator.geolocation){showToast('GPS tidak didukung','err');btn.innerHTML='<i class="fa fa-crosshairs"></i> Gunakan Lokasi Saya Saat Ini';btn.disabled=false;return;}
    navigator.geolocation.getCurrentPosition(function(pos){
        document.getElementById('lo-lat').value=pos.coords.latitude.toFixed(7);
        document.getElementById('lo-lon').value=pos.coords.longitude.toFixed(7);
        loUpdateMap(pos.coords.latitude,pos.coords.longitude);
        showToast('Lokasi berhasil (±'+Math.round(pos.coords.accuracy)+'m)','ok');
        btn.innerHTML='<i class="fa fa-check" style="color:#00c896;"></i> Lokasi berhasil diambil';
        setTimeout(function(){btn.innerHTML='<i class="fa fa-crosshairs"></i> Gunakan Lokasi Saya Saat Ini';btn.disabled=false;},2500);
    },function(err){
        var msgs={1:'Akses ditolak.',2:'Sinyal GPS lemah.',3:'Timeout GPS.'};
        showToast(msgs[err.code]||'GPS error','err');
        btn.innerHTML='<i class="fa fa-crosshairs"></i> Gunakan Lokasi Saya Saat Ini'; btn.disabled=false;
    },{enableHighAccuracy:true,timeout:10000});
};

function updateNavBadge(){
    var fd=new FormData(); fd.append('ajax_lokasi','1'); fd.append('act','list');
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d.ok) return;
            var aktif=d.data.filter(function(l){return l.status==='aktif';}).length;
            var btn=document.getElementById('btn-lokasi');
            var badge=btn.querySelector('.mj-lokasi-badge');
            if(aktif>0){
                if(!badge){badge=document.createElement('span');badge.className='mj-lokasi-badge';btn.appendChild(badge);}
                badge.textContent=aktif;
            } else { if(badge) badge.remove(); }
        });
}

function escHtml(s){ var d=document.createElement('div');d.textContent=s||'';return d.innerHTML; }

function showToast(msg,ok){
    var t=document.getElementById('mj-toast');
    t.className='mj-toast '+(ok?'ok':'err');
    t.querySelector('i').className=ok?'fa fa-circle-check':'fa fa-circle-xmark';
    t.querySelector('span').textContent=msg;
    t.style.display='flex';
    clearTimeout(t._tmr);
    t._tmr=setTimeout(function(){t.style.display='none';},2800);
}
</script>

<?php include '../includes/footer.php'; ?>