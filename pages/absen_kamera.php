<?php
// pages/absen_kamera.php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config.php';
requireLogin();

$uid      = (int)$_SESSION['user_id'];
$tgl_hari = date('Y-m-d');
$jam_skrg = date('H:i:s');

// ── Debug endpoint ────────────────────────────────────────
if (isset($_GET['debug'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $absen_terakhir = [];
    try {
        $dq = $pdo->prepare("SELECT id,tanggal,jam_masuk,jam_keluar,status,input_oleh,created_at FROM absensi WHERE user_id=? ORDER BY tanggal DESC, created_at DESC LIMIT 10");
        $dq->execute([$uid]);
        $absen_terakhir = $dq->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e){ $absen_terakhir = ['error'=>$e->getMessage()]; }
    $user_info = [];
    try {
        $uq = $pdo->prepare("SELECT id,nama,email,status FROM users WHERE id=?");
        $uq->execute([$uid]);
        $user_info = $uq->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e){}
    echo json_encode([
        'ok'               => true,
        'php'              => PHP_VERSION,
        'uid'              => $uid,
        'user'             => $user_info,
        'session_aktif'    => session_id() ? true : false,
        'upload_writable'  => is_writable(dirname(__DIR__).'/uploads'),
        'post_max'         => ini_get('post_max_size'),
        'tanggal_server'   => date('Y-m-d H:i:s'),
        'timezone'         => date_default_timezone_get(),
        'absensi_terakhir' => $absen_terakhir,
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX: Simpan absen ────────────────────────────────────
if (isset($_POST['ajax_absen_kamera'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $tipe     = in_array($_POST['tipe'] ?? '', ['masuk','keluar']) ? $_POST['tipe'] : 'masuk';
    $tgl      = (isset($_POST['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal'])) ? $_POST['tanggal'] : $tgl_hari;
    $jam      = (isset($_POST['jam']) && preg_match('/^\d{2}:\d{2}/', $_POST['jam'])) ? $_POST['jam'] : date('H:i:s');
    if (strlen($jam) === 5) $jam .= ':00';
    $foto_b64 = $_POST['foto'] ?? '';
    $device   = substr(strip_tags($_POST['device'] ?? ''), 0, 255);
    $lat      = isset($_POST['lat']) && is_numeric($_POST['lat']) ? (float)$_POST['lat'] : null;
    $lon      = isset($_POST['lon']) && is_numeric($_POST['lon']) ? (float)$_POST['lon'] : null;

    // ── Wajib foto dari kamera (bukan upload) ────────────
    if (empty($foto_b64) || strpos($foto_b64, 'data:image/') !== 0) {
        echo json_encode(['ok'=>false,'msg'=>'Foto wajib diambil langsung dari kamera.']);
        exit;
    }

    // ── Validasi lokasi dari jadwal karyawan ─────────────
    if ($lat && $lon) {
        try {
            $qlok = $pdo->prepare("
                SELECT la.id, la.nama, la.lat, la.lon, la.radius
                FROM jadwal_karyawan j
                JOIN lokasi_absen la ON la.id = j.lokasi_id
                WHERE j.user_id = ? AND j.tanggal = ?
                  AND la.status = 'aktif'
                  AND j.lokasi_id IS NOT NULL
            ");
            $qlok->execute([$uid, $tgl]);
            $lok = $qlok->fetch(PDO::FETCH_ASSOC);

            if ($lok) {
                $R    = 6371000;
                $dLat = deg2rad($lat - (float)$lok['lat']);
                $dLon = deg2rad($lon - (float)$lok['lon']);
                $a    = sin($dLat/2)*sin($dLat/2)
                      + cos(deg2rad((float)$lok['lat']))*cos(deg2rad($lat))
                      * sin($dLon/2)*sin($dLon/2);
                $jarak = $R * 2 * atan2(sqrt($a), sqrt(1-$a));

                if ($jarak > (float)$lok['radius']) {
                    $jarak_fmt = $jarak >= 1000
                        ? round($jarak/1000,1).' km'
                        : round($jarak).' m';
                    echo json_encode([
                        'ok'     => false,
                        'msg'    => "Absen ditolak. Anda berada di luar area \"{$lok['nama']}\" ({$jarak_fmt} dari lokasi, max {$lok['radius']}m).",
                        'jarak'  => round($jarak),
                        'diluar' => true,
                    ]);
                    exit;
                }
            }
        } catch(Exception $e) {
            // Tabel belum ada / kolom belum ada → bypass
        }
    }

    // ── Simpan foto ──────────────────────────────────────
    $foto_path = null;
    $foto_dir = dirname(__DIR__) . '/uploads/absensi/' . date('Y/m');
    if (!is_dir($foto_dir)) @mkdir($foto_dir, 0755, true);
    if (is_dir($foto_dir)) {
        $fname    = 'absen_' . $uid . '_' . $tipe . '_' . date('Ymd_His') . '.jpg';
        $b64clean = preg_replace('#^data:image/[a-z]+;base64,#i', '', $foto_b64);
        $img_data = base64_decode($b64clean, true);
        if ($img_data && @file_put_contents($foto_dir.'/'.$fname, $img_data) !== false) {
            $foto_path = 'uploads/absensi/' . date('Y/m') . '/' . $fname;
        }
    }

    // ── Cari jadwal shift ────────────────────────────────
    $shift_id = null; $sch_masuk = null;
    $terlambat = 0;   $toleransi = 15;
    try {
        $jd = $pdo->prepare("
            SELECT j.shift_id, ms.jam_masuk, ms.jam_keluar,
                   COALESCE(ms.toleransi_masuk,15)  toleransi_masuk,
                   COALESCE(ms.toleransi_pulang,0)  toleransi_pulang,
                   COALESCE(ms.lintas_hari,0)        lintas_hari
            FROM jadwal_karyawan j
            LEFT JOIN master_shift ms ON ms.id = j.shift_id
            WHERE j.user_id = ? AND j.tanggal = ?
        ");
        $jd->execute([$uid, $tgl]);
        $jdw = $jd->fetch(PDO::FETCH_ASSOC);
        if ($jdw) {
            $shift_id  = $jdw['shift_id'];
            $sch_masuk = $jdw['jam_masuk'];
            $toleransi = (int)$jdw['toleransi_masuk'];
        }
    } catch(Exception $e) {}

    // ── Hitung terlambat ─────────────────────────────────
    $status = 'hadir';
    if ($tipe === 'masuk' && $sch_masuk) {
        $diff = (strtotime($tgl.' '.$jam) - strtotime($tgl.' '.$sch_masuk)) / 60;
        if ($diff > $toleransi) {
            $terlambat = (int)$diff;
            $status    = 'terlambat';
        }
    }

    // ── Simpan ke DB ─────────────────────────────────────
    try {
        $ex = $pdo->prepare("SELECT id, jam_masuk, jam_keluar FROM absensi WHERE user_id=? AND tanggal=?");
        $ex->execute([$uid, $tgl]);
        $existing = $ex->fetch(PDO::FETCH_ASSOC);

        try {
            if ($existing) {
                if ($tipe === 'masuk') {
                    if ($existing['jam_masuk']) {
                        echo json_encode(['ok'=>false,'msg'=>'Anda sudah absen masuk hari ini pukul '.substr($existing['jam_masuk'],0,5)]);
                        exit;
                    }
                    $pdo->prepare("UPDATE absensi SET jam_masuk=?,status=?,terlambat_menit=?,foto_masuk=?,lat_masuk=?,lon_masuk=?,device_info=?,shift_id=?,input_oleh='self',updated_by=? WHERE id=?")
                        ->execute([$jam,$status,$terlambat,$foto_path,$lat,$lon,$device,$shift_id,$uid,$existing['id']]);
                } else {
                    if (!$existing['jam_masuk']) {
                        echo json_encode(['ok'=>false,'msg'=>'Anda belum absen masuk hari ini.']);
                        exit;
                    }
                    $durasi = null;
                    $ti = strtotime($tgl.' '.$existing['jam_masuk']);
                    $to = strtotime($tgl.' '.$jam);
                    if ($to < $ti) $to += 86400;
                    $durasi = (int)(($to-$ti)/60);
                    $pdo->prepare("UPDATE absensi SET jam_keluar=?,foto_keluar=?,lat_keluar=?,lon_keluar=?,durasi_kerja=?,updated_by=? WHERE id=?")
                        ->execute([$jam,$foto_path,$lat,$lon,$durasi,$uid,$existing['id']]);
                }
            } else {
                if ($tipe === 'keluar') {
                    echo json_encode(['ok'=>false,'msg'=>'Anda belum absen masuk hari ini.']);
                    exit;
                }
                $pdo->prepare("INSERT INTO absensi (user_id,tanggal,jam_masuk,status,terlambat_menit,foto_masuk,lat_masuk,lon_masuk,shift_id,device_info,input_oleh,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,'self',?)")
                    ->execute([$uid,$tgl,$jam,$status,$terlambat,$foto_path,$lat,$lon,$shift_id,$device,$uid]);
            }

            $msg = $tipe === 'masuk'
                ? 'Absen masuk berhasil pukul '.substr($jam,0,5).($terlambat>0?' (terlambat '.$terlambat.' menit)':'')
                : 'Absen keluar berhasil pukul '.substr($jam,0,5);

            echo json_encode([
                'ok'        => true,
                'msg'       => $msg,
                'tipe'      => $tipe,
                'jam'       => substr($jam,0,5),
                'status'    => $status,
                'terlambat' => $terlambat,
                'foto'      => $foto_path,
            ]);
        } catch(Exception $e) {
            echo json_encode(['ok'=>false,'msg'=>'DB Error: '.$e->getMessage()]);
        }
    } catch(Exception $eOuter) {
        echo json_encode(['ok'=>false,'msg'=>'Server error: '.$eOuter->getMessage()]);
    }
    exit;
}

// ── Fetch data karyawan ───────────────────────────────────
$user = $pdo->prepare("
    SELECT u.*, COALESCE(s.gelar_depan,'') gelar_depan, COALESCE(s.gelar_belakang,'') gelar_belakang,
           COALESCE(s.nik_rs,'') nik_rs, COALESCE(s.jenis_karyawan,'') jenis_karyawan
    FROM users u LEFT JOIN sdm_karyawan s ON s.user_id=u.id
    WHERE u.id=?
");
$user->execute([$uid]);
$user = $user->fetch(PDO::FETCH_ASSOC);

// ── Absensi hari ini ──────────────────────────────────────
$absen_hari = null;
try {
    $ab = $pdo->prepare("SELECT * FROM absensi WHERE user_id=? AND tanggal=?");
    $ab->execute([$uid, $tgl_hari]);
    $absen_hari = $ab->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// ── Jadwal hari ini (termasuk lokasi yang di-assign) ──────
$jadwal_hari = null;
try {
    $jd = $pdo->prepare("
        SELECT j.*,
               ms.kode shift_kode, ms.nama shift_nama, ms.warna shift_warna,
               ms.jam_masuk sch_masuk, ms.jam_keluar sch_keluar,
               COALESCE(ms.toleransi_masuk,15) toleransi_masuk,
               la.nama lokasi_nama, la.radius lokasi_radius, la.alamat lokasi_alamat
        FROM jadwal_karyawan j
        LEFT JOIN master_shift ms ON ms.id = j.shift_id
        LEFT JOIN lokasi_absen la ON la.id  = j.lokasi_id
        WHERE j.user_id=? AND j.tanggal=?
    ");
    $jd->execute([$uid, $tgl_hari]);
    $jadwal_hari = $jd->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// ── Label tanggal ─────────────────────────────────────────
$nama_hari_map  = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$nama_bulan_map = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$label_hari = $nama_hari_map[date('l')].', '.date('j').' '.$nama_bulan_map[(int)date('n')].' '.date('Y');

// ── Inisial ───────────────────────────────────────────────
$ws   = array_filter(explode(' ', $user['nama']));
$init = strtoupper(implode('', array_map(fn($w)=>mb_substr($w,0,1), array_slice(array_values($ws),0,2))));

// ── Riwayat 7 hari terakhir ───────────────────────────────
$riwayat = [];
try {
    $rw = $pdo->prepare("
        SELECT a.tanggal, a.jam_masuk, a.jam_keluar, a.status,
               a.terlambat_menit, a.durasi_kerja, a.foto_masuk
        FROM absensi a
        WHERE a.user_id=? AND a.tanggal < ?
          AND a.tanggal >= DATE_SUB(?,INTERVAL 7 DAY)
        ORDER BY a.tanggal DESC LIMIT 5
    ");
    $rw->execute([$uid, $tgl_hari, $tgl_hari]);
    $riwayat = $rw->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

include '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

<style>
* { box-sizing: border-box; }
.ak-wrap { font-family:'Inter',sans-serif; max-width:560px; margin:0 auto; padding:0 4px; color:#1e293b; }

/* Profile */
.ak-profile { background:linear-gradient(130deg,#0d1b2e 0%,#163354 100%); border-radius:14px; padding:18px 20px; display:flex; align-items:center; gap:14px; margin-bottom:14px; color:#fff; position:relative; overflow:hidden; }
.ak-profile::after { content:''; position:absolute; right:-20px; top:-20px; width:120px; height:120px; background:radial-gradient(circle,rgba(0,229,176,.1) 0%,transparent 70%); pointer-events:none; }
.ak-av { width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#00e5b0,#00c896);color:#0a0f14;font-size:16px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,.2); }
.ak-nama { font-size:15px;font-weight:800;line-height:1.2; }
.ak-nik  { font-size:10.5px;color:rgba(255,255,255,.45);margin-top:3px;font-family:'JetBrains Mono',monospace; }
.ak-div  { font-size:11px;color:rgba(255,255,255,.5);margin-top:2px; }
.ak-jam-live { margin-left:auto;text-align:right;flex-shrink:0; }
.ak-jam-live .jam { font-size:26px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;color:#00e5b0; }
.ak-jam-live .tgl { font-size:10px;color:rgba(255,255,255,.4);margin-top:3px; }

/* Shift bar */
.ak-shift-bar { background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap; }
.ak-shift-pill { display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;color:#fff;font-size:11px;font-weight:700; }
.ak-shift-jam  { font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:#0f172a; }
.ak-shift-tol  { font-size:10.5px;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:4px;font-weight:600; }
.ak-shift-none { font-size:12px;color:#94a3b8;font-style:italic; }

/* Lokasi badge di shift bar */
.ak-lokasi-badge {
    display:inline-flex;align-items:center;gap:4px;
    padding:3px 9px;border-radius:5px;
    background:#e0f2fe;color:#0369a1;
    font-size:10.5px;font-weight:700;
}
.ak-lokasi-badge.bebas { background:#f1f5f9;color:#94a3b8; }

/* Status absen */
.ak-status-bar { background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;display:grid;grid-template-columns:1fr 1fr;gap:12px; }
.ak-status-item { display:flex;flex-direction:column;gap:3px; }
.ak-status-lbl  { font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;display:flex;align-items:center;gap:4px; }
.ak-status-val  { font-size:18px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;color:#0f172a; }
.ak-status-val.empty { color:#e2e8f0;font-size:14px;font-family:'Inter',sans-serif;font-weight:400; }
.ak-status-badge { display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:5px;font-size:10.5px;font-weight:700;margin-top:2px; }

/* Kamera */
.ak-cam-card { background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:visible;margin-bottom:14px; }
.ak-cam-header { padding:12px 16px;border-bottom:1px solid #f0f2f7;background:#fafbfc;display:flex;align-items:center;justify-content:space-between; }
.ak-cam-title { font-size:12px;font-weight:700;color:#374151;display:flex;align-items:center;gap:7px; }
.ak-cam-title i { color:#00c896; }
.ak-video-wrap { position:relative;background:#0a0f14;aspect-ratio:4/3;overflow:hidden; }
#ak-video { width:100%;height:100%;object-fit:cover;transform:scaleX(-1);display:block; }
#ak-canvas { display:none; }
.ak-cam-overlay { position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none; }
.ak-face-guide { width:180px;height:220px;border:2.5px solid rgba(0,229,176,.5);border-radius:50% / 45%;position:relative;transition:border-color .3s; }
.ak-face-guide.detected { border-color:#00e5b0;box-shadow:0 0 0 4px rgba(0,229,176,.15); }
.ak-face-guide.no-face  { border-color:rgba(239,68,68,.5); }
.ak-scan-line { position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#00e5b0,transparent);animation:ak-scan 2s ease-in-out infinite;opacity:0; }
.ak-face-guide.detected .ak-scan-line { opacity:1; }
@keyframes ak-scan { 0%{top:10%;}50%{top:85%;}100%{top:10%;} }
.ak-face-guide::before,.ak-face-guide::after { content:'';position:absolute;width:20px;height:20px;border-color:#00e5b0;border-style:solid; }
.ak-face-guide::before { top:-3px;left:-3px;border-width:3px 0 0 3px;border-radius:3px 0 0 0; }
.ak-face-guide::after  { bottom:-3px;right:-3px;border-width:0 3px 3px 0;border-radius:0 0 3px 0; }
.ak-face-status { margin-top:14px;font-size:12px;font-weight:700;padding:5px 14px;border-radius:20px;transition:all .2s;backdrop-filter:blur(4px); }
.ak-face-status.waiting  { background:rgba(0,0,0,.5);color:rgba(255,255,255,.6); }
.ak-face-status.detected { background:rgba(0,229,176,.15);color:#00e5b0;border:1px solid rgba(0,229,176,.3); }
.ak-face-status.no-face  { background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3); }
.ak-face-status.loading  { background:rgba(99,102,241,.15);color:#a5b4fc;border:1px solid rgba(99,102,241,.3); }
.ak-countdown-wrap { position:absolute;top:14px;right:14px;display:none; }
.ak-countdown-ring { width:44px;height:44px;transform:rotate(-90deg); }
.ak-countdown-ring circle { fill:none;stroke-width:3; }
.ak-countdown-ring .bg { stroke:rgba(255,255,255,.1); }
.ak-countdown-ring .fg { stroke:#00e5b0;stroke-dasharray:120;stroke-linecap:round;transition:stroke-dashoffset .1s; }
.ak-countdown-num { position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;color:#00e5b0;font-family:'JetBrains Mono',monospace; }
.ak-snap-preview { display:none;position:absolute;inset:0;background:#0a0f14; }
.ak-snap-preview img { width:100%;height:100%;object-fit:cover; }
.ak-snap-ok { position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;background:rgba(0,200,150,.85);animation:snapFlash .3s ease; }
.ak-snap-ok i { font-size:44px;color:#fff; }
.ak-snap-ok span { font-size:14px;font-weight:700;color:#fff; }
@keyframes snapFlash { from{opacity:0;transform:scale(1.04);}to{opacity:1;transform:none;} }
.ak-model-load { position:absolute;inset:0;background:rgba(10,15,20,.85);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:#fff; }
.ak-model-load .spin { width:32px;height:32px;border:3px solid rgba(255,255,255,.1);border-top-color:#00e5b0;border-radius:50%;animation:akspin .7s linear infinite; }
@keyframes akspin { to{transform:rotate(360deg);} }
.ak-model-load .msg { font-size:12px;color:rgba(255,255,255,.6);font-weight:500; }

/* Pesan kamera tidak tersedia */
.ak-cam-unavail {
    display:none;
    flex-direction:column;align-items:center;justify-content:center;
    gap:14px;padding:36px 24px;background:#f8fafc;
    border-top:1px solid #e2e8f0;text-align:center;
}
.ak-cam-unavail-icon {
    width:64px;height:64px;border-radius:16px;
    background:#fee2e2;display:flex;align-items:center;justify-content:center;
    margin:0 auto;
}
.ak-cam-unavail-icon i { font-size:26px;color:#ef4444; }
.ak-cam-unavail-title { font-size:14px;font-weight:800;color:#0f172a; }
.ak-cam-unavail-desc  { font-size:12px;color:#64748b;line-height:1.65;max-width:280px; }

.ak-cam-footer { padding:14px 16px;display:flex;gap:10px;border-top:1px solid #f0f2f7;border-radius:0 0 14px 14px;background:#fff; }
.ak-btn-absen { flex:1;height:48px;border-radius:10px;border:none;font-size:13.5px;font-weight:800;font-family:'Inter',sans-serif;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .15s;letter-spacing:.2px; }
.ak-btn-masuk  { background:linear-gradient(135deg,#00c896,#00a874);color:#fff; }
.ak-btn-masuk:hover:not(:disabled)  { background:linear-gradient(135deg,#00b386,#009660);transform:translateY(-1px);box-shadow:0 4px 16px rgba(0,200,150,.35); }
.ak-btn-keluar { background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff; }
.ak-btn-keluar:hover:not(:disabled) { background:linear-gradient(135deg,#e08a00,#c26a00);transform:translateY(-1px);box-shadow:0 4px 16px rgba(245,158,11,.35); }
.ak-btn-absen:disabled { opacity:.4;cursor:not-allowed;transform:none!important;box-shadow:none!important; }

/* History */
.ak-history { background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:14px; }
.ak-history-head { padding:10px 16px;border-bottom:1px solid #f0f2f7;background:#fafbfc;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b; }
.ak-history-item { display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid #f8fafc; }
.ak-history-item:last-child { border-bottom:none; }
.ak-history-tgl  { font-size:11px;color:#94a3b8;font-family:'JetBrains Mono',monospace;min-width:70px; }
.ak-history-jams { display:flex;gap:8px;align-items:center; }
.ak-history-jam  { font-size:12.5px;font-weight:700;font-family:'JetBrains Mono',monospace; }
.ak-history-badge { font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:4px;margin-left:auto; }
.ak-foto-thumb { width:40px;height:40px;border-radius:6px;object-fit:cover;cursor:pointer;border:1.5px solid #e2e8f0;transition:transform .12s; }
.ak-foto-thumb:hover { transform:scale(1.1); }

/* Toast */
.ak-toast { position:fixed;bottom:20px;left:50%;transform:translateX(-50%);padding:11px 20px;border-radius:10px;background:#0d1b2e;color:#fff;font-size:13px;font-weight:600;z-index:99999;display:none;align-items:center;gap:8px;box-shadow:0 6px 24px rgba(0,0,0,.25);animation:akToast .2s ease;max-width:90vw;text-align:center; }
.ak-toast.ok   { background:#0d1b2e; }
.ak-toast.ok   i { color:#00e5b0; }
.ak-toast.err  { background:#7f1d1d; }
.ak-toast.err  i { color:#fca5a5; }
.ak-toast.warn { background:#78350f; }
.ak-toast.warn i { color:#fcd34d; }
@keyframes akToast { from{opacity:0;transform:translateX(-50%) translateY(8px);}to{opacity:1;transform:translateX(-50%) translateY(0);} }

/* ── Notifikasi tengah layar ── */
.ak-notif-ov {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.65); z-index: 999999;
    align-items: center; justify-content: center;
    padding: 20px;
    backdrop-filter: blur(4px);
    animation: akNovIn .2s ease;
}
.ak-notif-ov.open { display: flex; }
@keyframes akNovIn { from{opacity:0;} to{opacity:1;} }

.ak-notif-box {
    background: #fff;
    border-radius: 20px;
    padding: 32px 28px 24px;
    max-width: 340px;
    width: 100%;
    text-align: center;
    box-shadow: 0 24px 64px rgba(0,0,0,.3);
    animation: akNovBox .25s cubic-bezier(.34,1.56,.64,1);
    position: relative;
}
@keyframes akNovBox { from{opacity:0;transform:scale(.85) translateY(20px);}to{opacity:1;transform:none;} }

.ak-notif-icon-wrap {
    width: 72px; height: 72px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    position: relative;
}
.ak-notif-icon-wrap::after {
    content: '';
    position: absolute; inset: -6px;
    border-radius: 50%;
    border: 2px solid currentColor;
    opacity: .2;
    animation: akPulseRing 1.5s ease infinite;
}
@keyframes akPulseRing { 0%{transform:scale(.9);opacity:.3;} 50%{transform:scale(1.05);opacity:.15;} 100%{transform:scale(.9);opacity:.3;} }

.ak-notif-icon-wrap.err  { background: #fee2e2; color: #ef4444; }
.ak-notif-icon-wrap.warn { background: #fef3c7; color: #f59e0b; }
.ak-notif-icon-wrap.ok   { background: #dcfce7; color: #10b981; }
.ak-notif-icon-wrap i { font-size: 30px; }

.ak-notif-title {
    font-size: 16px; font-weight: 800; color: #0f172a;
    margin-bottom: 8px; line-height: 1.3;
}
.ak-notif-msg {
    font-size: 13px; color: #64748b; line-height: 1.6;
    margin-bottom: 20px;
}
.ak-notif-btn {
    width: 100%; height: 44px; border-radius: 10px; border: none;
    font-size: 13.5px; font-weight: 800; cursor: pointer;
    font-family: inherit; transition: all .15s;
    display: flex; align-items: center; justify-content: center; gap: 7px;
}
.ak-notif-btn.err  { background: linear-gradient(135deg,#ef4444,#dc2626); color: #fff; }
.ak-notif-btn.err:hover  { background: linear-gradient(135deg,#dc2626,#b91c1c); transform: translateY(-1px); }
.ak-notif-btn.warn { background: linear-gradient(135deg,#f59e0b,#d97706); color: #fff; }
.ak-notif-btn.warn:hover { background: linear-gradient(135deg,#d97706,#b45309); transform: translateY(-1px); }
.ak-notif-btn.ok   { background: linear-gradient(135deg,#10b981,#059669); color: #fff; }
.ak-notif-btn.ok:hover   { background: linear-gradient(135deg,#059669,#047857); transform: translateY(-1px); }

/* Lightbox */
.ak-lb { display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:99998;align-items:center;justify-content:center; }
.ak-lb.open { display:flex; }
.ak-lb img { max-width:90vw;max-height:90vh;border-radius:10px; }
.ak-lb-close { position:absolute;top:16px;right:16px;background:rgba(255,255,255,.15);border:none;color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center; }

@media(max-width:480px) { .ak-wrap{padding:0;} .ak-face-guide{width:150px;height:185px;} }
</style>

<div class="page-header">
    <h4><i class="fa fa-camera text-primary"></i> &nbsp;Absen via Kamera</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
        <span class="cur">Absen Kamera</span>
    </div>
</div>

<div class="content">
<div class="ak-wrap">

    <!-- PROFIL + JAM -->
    <div class="ak-profile">
        <div class="ak-av"><?= htmlspecialchars($init) ?></div>
        <div style="flex:1;min-width:0;">
            <div class="ak-nama">
                <?= ($user['gelar_depan']?htmlspecialchars($user['gelar_depan']).' ':'') . htmlspecialchars($user['nama']) ?>
                <?= ($user['gelar_belakang']?', '.htmlspecialchars($user['gelar_belakang']):'') ?>
            </div>
            <?php if ($user['nik_rs']): ?>
            <div class="ak-nik"><?= htmlspecialchars($user['nik_rs']) ?></div>
            <?php endif; ?>
            <div class="ak-div"><?= htmlspecialchars($user['divisi']?:'—') ?><?= $user['jenis_karyawan']?' · '.htmlspecialchars($user['jenis_karyawan']):'' ?></div>
        </div>
        <div class="ak-jam-live">
            <div class="jam" id="ak-live-jam"><?= date('H:i') ?></div>
            <div class="tgl"><?= $label_hari ?></div>
        </div>
    </div>

    <!-- INFO SHIFT + LOKASI -->
    <div class="ak-shift-bar">
        <?php if ($jadwal_hari && $jadwal_hari['shift_id']): ?>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span class="ak-shift-pill" style="background:<?= htmlspecialchars($jadwal_hari['shift_warna']) ?>;">
                <i class="fa fa-clock" style="font-size:10px;"></i>
                <?= htmlspecialchars($jadwal_hari['shift_kode']) ?> — <?= htmlspecialchars($jadwal_hari['shift_nama']) ?>
            </span>
            <span class="ak-shift-jam">
                <?= substr($jadwal_hari['sch_masuk'],0,5) ?> – <?= substr($jadwal_hari['sch_keluar'],0,5) ?>
            </span>
            <span class="ak-shift-tol">
                <i class="fa fa-hourglass-half" style="font-size:9px;"></i> Toleransi <?= $jadwal_hari['toleransi_masuk'] ?> menit
            </span>
        </div>
        <!-- Badge lokasi -->
        <?php if (!empty($jadwal_hari['lokasi_nama'])): ?>
        <span class="ak-lokasi-badge">
            <i class="fa fa-map-marker-alt" style="font-size:9px;"></i>
            <?= htmlspecialchars($jadwal_hari['lokasi_nama']) ?>
            <span style="font-weight:400;opacity:.7;">(r=<?= $jadwal_hari['lokasi_radius'] ?>m)</span>
        </span>
        <?php else: ?>
        <span class="ak-lokasi-badge bebas">
            <i class="fa fa-globe" style="font-size:9px;"></i> Lokasi bebas
        </span>
        <?php endif; ?>
        <?php elseif ($jadwal_hari): ?>
        <span style="font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:5px;"><?= ucfirst($jadwal_hari['tipe'] ?? '') ?></span>
        <?php else: ?>
        <span class="ak-shift-none"><i class="fa fa-calendar-xmark" style="margin-right:5px;"></i>Belum ada jadwal shift hari ini</span>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/pages/master_jadwal.php" style="margin-left:auto;font-size:10.5px;color:#94a3b8;text-decoration:none;font-weight:600;">
            <i class="fa fa-calendar"></i> Lihat Jadwal
        </a>
    </div>

    <!-- STATUS ABSEN HARI INI -->
    <div class="ak-status-bar">
        <div class="ak-status-item">
            <div class="ak-status-lbl"><i class="fa fa-arrow-right-to-bracket" style="color:#10b981;font-size:9px;"></i> Jam Masuk</div>
            <?php if ($absen_hari && $absen_hari['jam_masuk']): ?>
            <div class="ak-status-val" id="disp-masuk"><?= substr($absen_hari['jam_masuk'],0,5) ?></div>
            <?php if ($absen_hari['terlambat_menit'] > 0): ?>
            <span class="ak-status-badge" style="background:#fef3c7;color:#a16207;">
                <i class="fa fa-triangle-exclamation" style="font-size:8px;"></i> Terlambat <?= $absen_hari['terlambat_menit'] ?>m
            </span>
            <?php else: ?>
            <span class="ak-status-badge" style="background:#dcfce7;color:#15803d;">
                <i class="fa fa-circle-check" style="font-size:8px;"></i> Tepat waktu
            </span>
            <?php endif; ?>
            <?php else: ?>
            <div class="ak-status-val empty" id="disp-masuk">Belum absen</div>
            <?php endif; ?>
        </div>
        <div class="ak-status-item">
            <div class="ak-status-lbl"><i class="fa fa-arrow-right-from-bracket" style="color:#f59e0b;font-size:9px;"></i> Jam Keluar</div>
            <?php if ($absen_hari && $absen_hari['jam_keluar']): ?>
            <div class="ak-status-val" id="disp-keluar"><?= substr($absen_hari['jam_keluar'],0,5) ?></div>
            <?php if ($absen_hari['durasi_kerja']): ?>
            <span class="ak-status-badge" style="background:#e0f2fe;color:#0369a1;">
                <i class="fa fa-clock" style="font-size:8px;"></i>
                <?= floor($absen_hari['durasi_kerja']/60) ?>j <?= $absen_hari['durasi_kerja']%60 ?>m
            </span>
            <?php endif; ?>
            <?php else: ?>
            <div class="ak-status-val empty" id="disp-keluar">Belum absen</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- KAMERA CARD -->
    <div class="ak-cam-card">
        <div class="ak-cam-header">
            <span class="ak-cam-title"><i class="fa fa-camera"></i> Kamera Absensi</span>
            <div style="display:flex;align-items:center;gap:6px;">
                <span id="ak-face-count" style="font-size:10.5px;color:#94a3b8;font-weight:600;"></span>
                <span id="ak-cam-status-dot" style="width:8px;height:8px;border-radius:50%;background:#e2e8f0;"></span>
            </div>
        </div>

        <div class="ak-video-wrap" id="ak-video-wrap">
            <video id="ak-video" autoplay playsinline muted></video>
            <canvas id="ak-canvas"></canvas>
            <div class="ak-model-load" id="ak-model-load">
                <div class="spin"></div>
                <div class="msg" id="ak-load-msg">Memuat sistem deteksi wajah…</div>
            </div>
            <div class="ak-cam-overlay">
                <div class="ak-face-guide" id="ak-face-guide">
                    <div class="ak-scan-line"></div>
                </div>
                <div class="ak-face-status waiting" id="ak-face-status">Arahkan wajah ke kamera</div>
            </div>
            <div class="ak-countdown-wrap" id="ak-countdown-wrap">
                <svg class="ak-countdown-ring" viewBox="0 0 44 44">
                    <circle class="bg" cx="22" cy="22" r="19"/>
                    <circle class="fg" id="ak-ring-fg" cx="22" cy="22" r="19" stroke-dashoffset="0"/>
                </svg>
                <div class="ak-countdown-num" id="ak-countdown-num">3</div>
            </div>
            <div class="ak-snap-preview" id="ak-snap-preview">
                <img id="ak-snap-img" src="" alt="Foto absen">
                <div class="ak-snap-ok" id="ak-snap-ok">
                    <i class="fa fa-circle-check"></i>
                    <span id="ak-snap-msg">Absen berhasil!</span>
                </div>
            </div>
        </div>

        <!-- Pesan kamera tidak tersedia — TIDAK ADA fallback upload -->
        <div class="ak-cam-unavail" id="ak-cam-unavail">
            <div class="ak-cam-unavail-icon">
                <i class="fa fa-camera-slash"></i>
            </div>
            <div class="ak-cam-unavail-title">Kamera Tidak Dapat Dibuka</div>
            <div class="ak-cam-unavail-desc">
                Absensi hanya bisa dilakukan dengan foto langsung dari kamera.<br>
                Pastikan Anda mengakses halaman ini via <strong>HTTPS</strong> dan mengizinkan akses kamera di browser.
            </div>
        </div>

        <div class="ak-cam-footer">
            <button class="ak-btn-absen ak-btn-masuk" id="btn-masuk" <?= ($absen_hari&&$absen_hari['jam_masuk'])?'disabled':'' ?>>
                <i class="fa fa-arrow-right-to-bracket"></i>
                <?= ($absen_hari&&$absen_hari['jam_masuk'])?'Sudah Absen Masuk':'Absen Masuk' ?>
            </button>
            <button class="ak-btn-absen ak-btn-keluar" id="btn-keluar"
                <?= ($absen_hari&&$absen_hari['jam_keluar'])?'disabled':((!($absen_hari&&$absen_hari['jam_masuk']))?'disabled':'') ?>>
                <i class="fa fa-arrow-right-from-bracket"></i>
                <?= ($absen_hari&&$absen_hari['jam_keluar'])?'Sudah Absen Keluar':'Absen Keluar' ?>
            </button>
        </div>
    </div>

    <!-- FOTO BUKTI -->
    <?php if ($absen_hari && !empty($absen_hari['foto_masuk'])): ?>
    <div class="ak-history" style="margin-bottom:14px;">
        <div class="ak-history-head"><i class="fa fa-image" style="color:#00c896;margin-right:5px;"></i>Foto Bukti Hari Ini</div>
        <div style="display:flex;gap:12px;padding:12px 16px;align-items:center;">
            <div style="text-align:center;">
                <img src="<?= APP_URL ?>/<?= htmlspecialchars($absen_hari['foto_masuk']) ?>" class="ak-foto-thumb" onclick="openLightbox(this.src)" alt="Masuk">
                <div style="font-size:9px;color:#94a3b8;margin-top:3px;font-weight:600;">Masuk</div>
            </div>
            <?php if (!empty($absen_hari['foto_keluar'])): ?>
            <div style="text-align:center;">
                <img src="<?= APP_URL ?>/<?= htmlspecialchars($absen_hari['foto_keluar']) ?>" class="ak-foto-thumb" onclick="openLightbox(this.src)" alt="Keluar">
                <div style="font-size:9px;color:#94a3b8;margin-top:3px;font-weight:600;">Keluar</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- RIWAYAT -->
    <?php if ($riwayat): ?>
    <div class="ak-history">
        <div class="ak-history-head"><i class="fa fa-clock-rotate-left" style="color:#00c896;margin-right:5px;"></i>Riwayat 7 Hari Terakhir</div>
        <?php
        $st_cfg = [
            'hadir'     =>['#dcfce7','#15803d','Hadir'],
            'terlambat' =>['#fef3c7','#a16207','Terlambat'],
            'alpha'     =>['#fee2e2','#b91c1c','Alpha'],
            'izin'      =>['#fff7ed','#c2410c','Izin'],
            'cuti'      =>['#faf5ff','#6d28d9','Cuti'],
            'libur'     =>['#f1f5f9','#475569','Libur'],
        ];
        foreach ($riwayat as $r):
            $sc = $st_cfg[$r['status']] ?? ['#f1f5f9','#64748b',$r['status']];
            $hari_r = ['Sun'=>'Min','Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab','Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab'][date('D',strtotime($r['tanggal']))] ?? '';
        ?>
        <div class="ak-history-item">
            <div class="ak-history-tgl"><?= $hari_r ?>, <?= date('d/m',strtotime($r['tanggal'])) ?></div>
            <div class="ak-history-jams">
                <span class="ak-history-jam" style="color:#10b981;"><?= $r['jam_masuk']?substr($r['jam_masuk'],0,5):'--:--' ?></span>
                <span style="color:#e2e8f0;">→</span>
                <span class="ak-history-jam" style="color:#f59e0b;"><?= $r['jam_keluar']?substr($r['jam_keluar'],0,5):'--:--' ?></span>
            </div>
            <?php if ($r['foto_masuk']): ?>
            <img src="<?= APP_URL ?>/<?= htmlspecialchars($r['foto_masuk']) ?>" class="ak-foto-thumb" onclick="openLightbox(this.src)" style="width:32px;height:32px;" alt="">
            <?php endif; ?>
            <span class="ak-history-badge" style="background:<?=$sc[0]?>;color:<?=$sc[1]?>;"><?=$sc[2]?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- /.ak-wrap -->
</div><!-- /.content -->

<div class="ak-lb" id="ak-lb" onclick="closeLightbox()">
    <button class="ak-lb-close" onclick="closeLightbox()"><i class="fa fa-times"></i></button>
    <img id="ak-lb-img" src="" alt="">
</div>
<div class="ak-toast" id="ak-toast"><i></i><span></span></div>

<!-- ── NOTIFIKASI TENGAH LAYAR ── -->
<div class="ak-notif-ov" id="ak-notif-ov" onclick="closeNotif()">
    <div class="ak-notif-box" onclick="event.stopPropagation()">
        <div class="ak-notif-icon-wrap err" id="ak-notif-icon-wrap">
            <i class="fa fa-circle-xmark" id="ak-notif-icon"></i>
        </div>
        <div class="ak-notif-title" id="ak-notif-title">Absen Gagal</div>
        <div class="ak-notif-msg"   id="ak-notif-msg"></div>
        <button class="ak-notif-btn err" id="ak-notif-btn" onclick="closeNotif()">
            <i class="fa fa-rotate-right"></i> Coba Lagi
        </button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
const APP_URL  = '<?= rtrim(APP_URL,'/') ?>';
const AK_PAGE  = window.location.pathname + window.location.search;
const SUDAH_IN  = <?= ($absen_hari&&$absen_hari['jam_masuk'])  ? 'true':'false' ?>;
const SUDAH_OUT = <?= ($absen_hari&&$absen_hari['jam_keluar']) ? 'true':'false' ?>;
const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model';

let stream=null,detecting=false,faceDetected=false,cameraReady=false;
let countdownAct=false,countdownSec=3,countdownTmr=null,pendingTipe=null;
let geoLat=null,geoLon=null;

const video       = document.getElementById('ak-video');
const canvas      = document.getElementById('ak-canvas');
const modelLoad   = document.getElementById('ak-model-load');
const loadMsg     = document.getElementById('ak-load-msg');
const faceGuide   = document.getElementById('ak-face-guide');
const faceStatus  = document.getElementById('ak-face-status');
const countWrap   = document.getElementById('ak-countdown-wrap');
const countNum    = document.getElementById('ak-countdown-num');
const ringFg      = document.getElementById('ak-ring-fg');
const snapPrev    = document.getElementById('ak-snap-preview');
const snapImg     = document.getElementById('ak-snap-img');
const snapOk      = document.getElementById('ak-snap-ok');
const snapMsg     = document.getElementById('ak-snap-msg');
const btnMasuk    = document.getElementById('btn-masuk');
const btnKeluar   = document.getElementById('btn-keluar');
const faceCountEl = document.getElementById('ak-face-count');
const camDot      = document.getElementById('ak-cam-status-dot');
const camUnavail  = document.getElementById('ak-cam-unavail');
const videoWrap   = document.getElementById('ak-video-wrap');

/* Jam live */
(function tick(){
    var d=new Date();
    document.getElementById('ak-live-jam').textContent=
        String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')+':'+String(d.getSeconds()).padStart(2,'0');
    setTimeout(tick,500);
})();

/* GPS */
if(navigator.geolocation){
    navigator.geolocation.getCurrentPosition(
        function(p){ geoLat=p.coords.latitude; geoLon=p.coords.longitude; },
        function(){},
        { timeout:5000 }
    );
}

function isCameraSupported(){ return !!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia); }

/* Kamera tidak tersedia → tampilkan pesan, nonaktifkan tombol */
function showCameraUnavailable(reason){
    modelLoad.style.display='none';
    videoWrap.style.display='none';
    camUnavail.style.display='flex';
    camDot.style.background='#ef4444';
    faceCountEl.textContent='Kamera tidak tersedia';
    // Nonaktifkan kedua tombol absen
    btnMasuk.disabled=true;
    btnKeluar.disabled=true;
    btnMasuk.innerHTML='<i class="fa fa-camera-slash"></i> Kamera Diperlukan';
    btnKeluar.innerHTML='<i class="fa fa-camera-slash"></i> Kamera Diperlukan';
    console.warn('[AbsenKamera] Kamera tidak tersedia:', reason);
}

async function loadModels(){
    if(!isCameraSupported()){
        showCameraUnavailable('getUserMedia tidak didukung browser ini');
        return;
    }
    loadMsg.textContent='Mengunduh model deteksi wajah…';
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        loadMsg.textContent='Membuka kamera…';
        await startCamera();
        modelLoad.style.display='none';
        camDot.style.background='#00c896';
        cameraReady=true;
        startDetection();
    } catch(err){
        showCameraUnavailable(err.message);
    }
}

async function startCamera(){
    if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia)
        throw new Error('getUserMedia tidak tersedia');
    stream=await navigator.mediaDevices.getUserMedia({ video:{facingMode:'user',width:{ideal:640},height:{ideal:480}}, audio:false });
    video.srcObject=stream;
    await new Promise(function(res,rej){ video.onloadedmetadata=res; video.onerror=rej; setTimeout(rej,10000); });
    canvas.width=video.videoWidth||640; canvas.height=video.videoHeight||480;
}

async function startDetection(){
    detecting=true;
    const opts=new faceapi.TinyFaceDetectorOptions({inputSize:224,scoreThreshold:0.5});
    async function loop(){
        if(!detecting) return;
        try {
            const det=await faceapi.detectAllFaces(video,opts);
            const cnt=det.length;
            faceCountEl.textContent=cnt>0?cnt+' wajah terdeteksi':'';
            if(cnt>0){ if(!faceDetected){ faceDetected=true; setFaceUI('detected'); } }
            else { if(faceDetected){ faceDetected=false; setFaceUI('no-face'); cancelCountdown(); } }
        } catch(e){}
        setTimeout(loop,400);
    }
    loop();
}

function setFaceUI(state){
    faceGuide.className='ak-face-guide '+state;
    faceStatus.className='ak-face-status';
    if(state==='detected'){ faceStatus.classList.add('detected'); faceStatus.textContent='✓ Wajah terdeteksi — tekan tombol absen'; camDot.style.background='#00e5b0'; }
    else if(state==='no-face'){ faceStatus.classList.add('no-face'); faceStatus.textContent='Wajah tidak terdeteksi'; camDot.style.background='#ef4444'; }
    else { faceStatus.classList.add('waiting'); faceStatus.textContent='Arahkan wajah ke kamera'; camDot.style.background='#e2e8f0'; }
}

btnMasuk.addEventListener('click',function(){
    if(SUDAH_IN) return;
    if(!cameraReady){ showNotif('Kamera tidak tersedia. Absensi hanya dapat dilakukan dengan foto langsung dari kamera.','err'); return; }
    if(!faceDetected){ showNotif('Wajah belum terdeteksi.<br>Arahkan wajah Anda ke kamera terlebih dahulu.','warn'); return; }
    startAbsen('masuk');
});
btnKeluar.addEventListener('click',function(){
    if(SUDAH_OUT) return;
    if(!cameraReady){ showNotif('Kamera tidak tersedia. Absensi hanya dapat dilakukan dengan foto langsung dari kamera.','err'); return; }
    if(!SUDAH_IN&&!document.getElementById('disp-masuk').textContent.match(/\d{2}:\d{2}/)){ showNotif('Anda belum absen masuk hari ini.','warn'); return; }
    if(!faceDetected){ showNotif('Wajah belum terdeteksi.<br>Arahkan wajah Anda ke kamera terlebih dahulu.','warn'); return; }
    startAbsen('keluar');
});

function startAbsen(tipe){
    if(countdownAct) return;
    pendingTipe=tipe; countdownAct=true; countdownSec=3;
    setFaceUI('detected'); faceStatus.textContent='Bersiap…'; faceStatus.className='ak-face-status loading';
    countWrap.style.display='block'; countNum.textContent=countdownSec; ringFg.style.strokeDashoffset='0';
    countdownTmr=setInterval(function(){
        countdownSec--;
        countNum.textContent=countdownSec;
        ringFg.style.strokeDashoffset=((3-countdownSec)/3*120)+'';
        if(countdownSec<=0){ clearInterval(countdownTmr); countWrap.style.display='none'; captureAndSend(tipe); }
    },1000);
}
function cancelCountdown(){
    if(!countdownAct) return;
    clearInterval(countdownTmr); countdownAct=false; countWrap.style.display='none'; pendingTipe=null;
}

async function captureAndSend(tipe){
    // Foto HANYA dari kamera — tidak ada fallback upload
    if(!stream || video.readyState < 2){
        countdownAct=false;
        showNotif('Kamera tidak aktif. Absensi hanya dapat dilakukan dengan foto langsung dari kamera.','err');
        return;
    }

    var flash=document.createElement('div');
    flash.style.cssText='position:absolute;inset:0;background:#fff;opacity:.7;pointer-events:none;z-index:10;';
    video.parentElement.appendChild(flash);
    setTimeout(function(){flash.remove();},300);

    var ctx=canvas.getContext('2d');
    ctx.save(); ctx.translate(canvas.width,0); ctx.scale(-1,1); ctx.drawImage(video,0,0); ctx.restore();
    var tmpC=document.createElement('canvas');
    var sc=Math.min(1,640/canvas.width,480/canvas.height);
    tmpC.width=Math.round(canvas.width*sc); tmpC.height=Math.round(canvas.height*sc);
    tmpC.getContext('2d').drawImage(canvas,0,0,tmpC.width,tmpC.height);
    var dataUrl=tmpC.toDataURL('image/jpeg',0.60);

    snapImg.src=dataUrl; snapOk.style.display='none'; snapPrev.style.display='block';

    var now=new Date();
    var jamStr=String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0')+':'+String(now.getSeconds()).padStart(2,'0');
    var tglStr=now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0');

    var fd=new FormData();
    fd.append('ajax_absen_kamera','1');
    fd.append('tipe',tipe); fd.append('tanggal',tglStr); fd.append('jam',jamStr);
    fd.append('foto',dataUrl); fd.append('device',(navigator.userAgent||'').substring(0,200));
    fd.append('lat',geoLat||''); fd.append('lon',geoLon||'');

    try {
        var res=await fetch(AK_PAGE,{method:'POST',body:fd,credentials:'same-origin'});
        var raw=await res.text(); var data;
        try{ data=JSON.parse(raw); }
        catch(e){
            console.error('Non-JSON response:',raw.substring(0,1000));
            countdownAct=false; snapPrev.style.display='none';
            var phpErr=raw.match(/Fatal error.*?on line \d+/i)||raw.match(/Parse error.*?on line \d+/i);
            showNotif(phpErr ? phpErr[0] : 'Terjadi kesalahan server.<br>Cek Console (F12) untuk detail.','err');
            setFaceUI('detected'); return;
        }
        countdownAct=false;
        if(data.ok){
            snapMsg.textContent=data.msg; snapOk.style.display='flex';
            if(tipe==='masuk'){
                document.getElementById('disp-masuk').textContent=data.jam;
                document.getElementById('disp-masuk').classList.remove('empty');
                btnMasuk.disabled=true; btnMasuk.innerHTML='<i class="fa fa-check"></i> Sudah Absen Masuk';
                btnKeluar.disabled=false;
            } else {
                document.getElementById('disp-keluar').textContent=data.jam;
                document.getElementById('disp-keluar').classList.remove('empty');
                btnKeluar.disabled=true; btnKeluar.innerHTML='<i class="fa fa-check"></i> Sudah Absen Keluar';
            }
            setTimeout(function(){ snapPrev.style.display='none'; setFaceUI('detected'); },3000);
        } else {
            snapPrev.style.display='none';
            var isLokasi = data.diluar || (data.msg && data.msg.indexOf('luar area') > -1);
            showNotif(data.msg||'Gagal menyimpan absensi', isLokasi ? 'lokasi' : 'err');
            setFaceUI('detected');
        }
    } catch(err){
        countdownAct=false; snapPrev.style.display='none';
        showNotif('Koneksi bermasalah.<br>Periksa jaringan dan coba lagi.','err');
        setFaceUI('detected');
    }
}

function openLightbox(src){ document.getElementById('ak-lb-img').src=src; document.getElementById('ak-lb').classList.add('open'); }
function closeLightbox(){ document.getElementById('ak-lb').classList.remove('open'); }

/* ── Notifikasi tengah layar ── */
var NOTIF_CFG = {
    err:    { title:'Absen Gagal',        icon:'fa-circle-xmark',        btn:'Coba Lagi',     btnIcon:'fa-rotate-right' },
    warn:   { title:'Perhatian',           icon:'fa-triangle-exclamation', btn:'Mengerti',      btnIcon:'fa-check' },
    ok:     { title:'Absen Berhasil',      icon:'fa-circle-check',         btn:'Tutup',         btnIcon:'fa-check' },
    lokasi: { title:'Di Luar Area Absen',  icon:'fa-map-marker-alt',       btn:'Oke, Mengerti', btnIcon:'fa-check' },
};
function showNotif(msg, type) {
    type = type || 'err';
    var cfg  = NOTIF_CFG[type] || NOTIF_CFG.err;
    var base = (type === 'lokasi') ? 'err' : type;

    document.getElementById('ak-notif-icon-wrap').className = 'ak-notif-icon-wrap ' + base;
    document.getElementById('ak-notif-icon').className      = 'fa ' + cfg.icon;
    document.getElementById('ak-notif-title').textContent   = cfg.title;
    document.getElementById('ak-notif-msg').innerHTML       = msg;
    document.getElementById('ak-notif-btn').className       = 'ak-notif-btn ' + base;
    document.getElementById('ak-notif-btn').innerHTML       = '<i class="fa '+cfg.btnIcon+'"></i> ' + cfg.btn;
    document.getElementById('ak-notif-ov').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeNotif(){
    document.getElementById('ak-notif-ov').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){
    if(e.key==='Escape') closeNotif();
});

function showToast(msg,type){
    var t=document.getElementById('ak-toast');
    t.className='ak-toast '+(type||'ok');
    var icons={ok:'fa-circle-check',err:'fa-circle-xmark',warn:'fa-triangle-exclamation'};
    t.querySelector('i').className='fa '+(icons[type]||'fa-circle-check');
    t.querySelector('span').textContent=msg;
    t.style.display='flex';
    clearTimeout(t._t);
    t._t=setTimeout(function(){t.style.display='none';},3500);
}

/* Debug test */
fetch(window.location.pathname+'?debug=1',{credentials:'same-origin'})
    .then(function(r){return r.json();})
    .then(function(d){console.log('[AbsenKamera] Server OK:',d);})
    .catch(function(e){console.warn('[AbsenKamera] Server error:',e);});

loadModels();
</script>

<?php include '../includes/footer.php'; ?>