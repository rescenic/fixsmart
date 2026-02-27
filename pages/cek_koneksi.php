<?php
session_start();
require_once '../config.php';
requireLogin();

$page_title  = 'Monitor Koneksi';
$active_menu = 'cek_koneksi';

// ══════════════════════════════════════════════════
//  FUNGSI PING — cepat, tanpa exec
// ══════════════════════════════════════════════════
function pingIP(string $host, int $port = 80, int $timeout = 3): array {
    $clean = preg_replace('#^https?://#', '', $host);
    $clean = explode('/', $clean)[0];
    $start = microtime(true);
    $fp    = @fsockopen($clean, $port ?: 80, $errno, $errstr, $timeout);
    $ms    = round((microtime(true) - $start) * 1000, 1);
    if ($fp) {
        fclose($fp);
        return ['status' => 'online', 'ping_ms' => $ms, 'http_code' => null, 'pesan' => 'TCP OK'];
    }
    if ($ms >= $timeout * 1000 - 50) {
        return ['status' => 'timeout', 'ping_ms' => null, 'http_code' => null, 'pesan' => 'Timeout'];
    }
    return ['status' => 'offline', 'ping_ms' => null, 'http_code' => null, 'pesan' => $errstr ?: 'Unreachable'];
}

function pingURL(string $url, int $timeout = 3): array {
    if (!preg_match('#^https?://#', $url)) $url = 'http://' . $url;
    $start = microtime(true);
    $ch    = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
        CURLOPT_USERAGENT      => 'MediFix-Monitor/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_NOBODY         => true,
        CURLOPT_FRESH_CONNECT  => true,
    ]);
    curl_exec($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errNo = curl_errno($ch);
    $errStr= curl_error($ch);
    curl_close($ch);
    $ms = round((microtime(true) - $start) * 1000, 1);

    if ($errNo === CURLE_OPERATION_TIMEDOUT) {
        return ['status' => 'timeout', 'ping_ms' => null, 'http_code' => 0, 'pesan' => 'Timeout'];
    }
    if ($errNo) {
        return ['status' => 'offline', 'ping_ms' => $ms, 'http_code' => 0, 'pesan' => $errStr];
    }
    $ok = ($code >= 200 && $code < 400);
    return ['status' => $ok ? 'online' : 'offline', 'ping_ms' => $ms, 'http_code' => $code, 'pesan' => "HTTP $code"];
}

// ══════════════════════════════════════════════════
//  AJAX — CEK SATU HOST
// ══════════════════════════════════════════════════
if (isset($_GET['ajax_cek'])) {
    header('Content-Type: application/json');
    $id   = (int)$_GET['ajax_cek'];
    $stmt = $pdo->prepare("SELECT * FROM koneksi_monitor WHERE id=? AND aktif=1");
    $stmt->execute([$id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['error' => 'Not found']); exit; }

    $hasil = ($row['tipe'] === 'url')
        ? pingURL($row['host'], $row['timeout_detik'])
        : pingIP($row['host'], $row['port'] ?: 80, $row['timeout_detik']);

    $pdo->prepare("INSERT INTO koneksi_log (monitor_id,status,ping_ms,http_code,pesan,cek_at) VALUES (?,?,?,?,?,NOW())")
        ->execute([$id, $hasil['status'], $hasil['ping_ms'] ?? null, $hasil['http_code'] ?? null, $hasil['pesan'] ?? null]);

    echo json_encode(array_merge($hasil, ['id' => $id]));
    exit;
}

// ══════════════════════════════════════════════════
//  AJAX — CEK SEMUA HOST — PARALEL via multi-curl
// ══════════════════════════════════════════════════
if (isset($_GET['ajax_cek_semua'])) {
    header('Content-Type: application/json');
    set_time_limit(30);

    $rows    = $pdo->query("SELECT * FROM koneksi_monitor WHERE aktif=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) { echo json_encode([]); exit; }

    $urlRows = array_filter($rows, fn($r) => $r['tipe'] === 'url');
    $ipRows  = array_filter($rows, fn($r) => $r['tipe'] === 'ip');
    $results = [];

    // URL: parallel multi-curl
    if (!empty($urlRows)) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($urlRows as $row) {
            $url = $row['host'];
            if (!preg_match('#^https?://#', $url)) $url = 'http://' . $url;
            $to  = min($row['timeout_detik'], 5);
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $to,
                CURLOPT_CONNECTTIMEOUT => $to,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_USERAGENT      => 'MediFix-Monitor/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_NOBODY         => true,
                CURLOPT_FRESH_CONNECT  => true,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$row['id']] = ['ch' => $ch, 'start' => microtime(true), 'row' => $row];
        }
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.1);
        } while ($running > 0);

        foreach ($handles as $id => $h) {
            $ch    = $h['ch'];
            $ms    = round((microtime(true) - $h['start']) * 1000, 1);
            $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errNo = curl_errno($ch);
            $errStr= curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($errNo === CURLE_OPERATION_TIMEDOUT) {
                $r = ['status' => 'timeout', 'ping_ms' => null, 'http_code' => 0, 'pesan' => 'Timeout'];
            } elseif ($errNo) {
                $r = ['status' => 'offline', 'ping_ms' => $ms, 'http_code' => 0, 'pesan' => $errStr];
            } else {
                $ok = ($code >= 200 && $code < 400);
                $r  = ['status' => $ok ? 'online' : 'offline', 'ping_ms' => $ms, 'http_code' => $code, 'pesan' => "HTTP $code"];
            }
            $results[$id] = array_merge($r, ['id' => $id]);
        }
        curl_multi_close($mh);
    }

    // IP: fsockopen (tidak butuh exec)
    foreach ($ipRows as $row) {
        $clean = preg_replace('#^https?://#', '', $row['host']);
        $clean = explode('/', $clean)[0];
        $port  = $row['port'] ?: 80;
        $to    = min($row['timeout_detik'], 5);
        $start = microtime(true);
        $fp    = @fsockopen($clean, $port, $errno, $errstr, $to);
        $ms    = round((microtime(true) - $start) * 1000, 1);
        if ($fp) {
            fclose($fp);
            $r = ['status' => 'online', 'ping_ms' => $ms, 'http_code' => null, 'pesan' => 'TCP OK'];
        } elseif ($ms >= $to * 1000 - 50) {
            $r = ['status' => 'timeout', 'ping_ms' => null, 'http_code' => null, 'pesan' => 'Timeout'];
        } else {
            $r = ['status' => 'offline', 'ping_ms' => null, 'http_code' => null, 'pesan' => $errstr ?: 'Unreachable'];
        }
        $results[$row['id']] = array_merge($r, ['id' => $row['id']]);
    }

    // Bulk insert log
    if (!empty($results)) {
        $vals = []; $params = [];
        foreach ($results as $id => $r) {
            $vals[]  = "(?,?,?,?,?,NOW())";
            $params  = array_merge($params, [$id, $r['status'], $r['ping_ms'] ?? null, $r['http_code'] ?? null, $r['pesan'] ?? null]);
        }
        $pdo->prepare("INSERT INTO koneksi_log (monitor_id,status,ping_ms,http_code,pesan,cek_at) VALUES " . implode(',', $vals))
            ->execute($params);
    }

    echo json_encode(array_values($results));
    exit;
}

// ══════════════════════════════════════════════════
//  AJAX — EDIT & LOG
// ══════════════════════════════════════════════════
if (isset($_GET['get_monitor'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT * FROM koneksi_monitor WHERE id=?");
    $stmt->execute([(int)$_GET['get_monitor']]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}
if (isset($_GET['get_log'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT * FROM koneksi_log WHERE monitor_id=? ORDER BY cek_at DESC LIMIT 20");
    $stmt->execute([(int)$_GET['get_log']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ══════════════════════════════════════════════════
//  POST — TAMBAH / EDIT / HAPUS
// ══════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'tambah' || $act === 'edit') {
        $nama    = trim($_POST['nama']         ?? '');
        $host    = trim($_POST['host']         ?? '');
        $tipe    = in_array($_POST['tipe'] ?? '', ['ip','url']) ? $_POST['tipe'] : 'url';
        $kat     = trim($_POST['kategori']     ?? 'Umum');
        $port    = strlen($_POST['port'] ?? '') ? (int)$_POST['port'] : null;
        $timeout = max(1, min(10, (int)($_POST['timeout_detik'] ?? 3)));
        $aktif   = isset($_POST['aktif']) ? 1 : 0;

        if ($act === 'tambah') {
            $pdo->prepare("INSERT INTO koneksi_monitor (nama,host,tipe,kategori,port,timeout_detik,aktif,created_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$nama,$host,$tipe,$kat,$port,$timeout,$aktif,$_SESSION['user_id']??null]);
            setFlash('success', "Host <strong>".htmlspecialchars($nama)."</strong> berhasil ditambahkan.");
        } else {
            $pdo->prepare("UPDATE koneksi_monitor SET nama=?,host=?,tipe=?,kategori=?,port=?,timeout_detik=?,aktif=?,updated_at=NOW() WHERE id=?")
                ->execute([$nama,$host,$tipe,$kat,$port,$timeout,$aktif,(int)$_POST['id']]);
            setFlash('success', "Host <strong>".htmlspecialchars($nama)."</strong> berhasil diperbarui.");
        }
        redirect(APP_URL.'/pages/cek_koneksi.php');
    }
    if ($act === 'hapus') {
        $pdo->prepare("DELETE FROM koneksi_monitor WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', 'Host berhasil dihapus.');
        redirect(APP_URL.'/pages/cek_koneksi.php');
    }
}

// ══════════════════════════════════════════════════
//  DATA UTAMA — dari DB saja, TIDAK ping saat load
// ══════════════════════════════════════════════════
try {
    $monitors = $pdo->query("
        SELECT m.*,
               l.status    AS last_status,
               l.ping_ms   AS last_ping,
               l.cek_at    AS last_cek,
               l.http_code AS last_http
        FROM koneksi_monitor m
        LEFT JOIN koneksi_log l ON l.id = (
            SELECT id FROM koneksi_log WHERE monitor_id = m.id ORDER BY cek_at DESC LIMIT 1
        )
        ORDER BY m.kategori, m.nama ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($monitors as $m) $grouped[$m['kategori']][] = $m;

    $total   = count($monitors);
    $online  = count(array_filter($monitors, fn($m) => $m['last_status'] === 'online'));
    $offline = count(array_filter($monitors, fn($m) => $m['last_status'] === 'offline'));
    $timeout = count(array_filter($monitors, fn($m) => $m['last_status'] === 'timeout'));
    $belum   = $total - $online - $offline - $timeout;
    $kategori_list = array_unique(array_column($monitors, 'kategori'));

} catch (PDOException $e) {
    $monitors = []; $grouped = []; $total = $online = $offline = $timeout = $belum = 0;
    $kategori_list = [];
    $setup_error = $e->getMessage();
}

include '../includes/header.php';
?>

<style>
:root { --online:#10b981; --offline:#ef4444; --timeout:#f59e0b; --unknown:#94a3b8; --primary:#26B99A; }

.mon-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; margin-bottom:20px; }
.mon-stat  { background:#fff; border-radius:8px; border:1px solid #e8ecf0; border-top:3px solid; padding:14px 16px; display:flex; align-items:center; gap:12px; }
.mon-stat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; color:#fff; }
.mon-stat-val  { font-size:26px; font-weight:800; color:#1e293b; line-height:1; }
.mon-stat-lbl  { font-size:11px; color:#94a3b8; margin-top:2px; }

.kat-label { display:flex; align-items:center; gap:10px; margin:20px 0 10px; font-size:12px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:.07em; }
.kat-label::after { content:''; flex:1; height:1px; background:#e8ecf0; }

.mon-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:12px; margin-bottom:6px; }

.mon-card { background:#fff; border-radius:10px; border:1px solid #e8ecf0; overflow:hidden; position:relative; transition:box-shadow .2s, transform .2s; }
.mon-card:hover { box-shadow:0 4px 14px rgba(0,0,0,.09); transform:translateY(-1px); }
.mon-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:#e2e8f0; transition:background .3s; }
.mon-card[data-status="online"]::before  { background:var(--online); }
.mon-card[data-status="offline"]::before { background:var(--offline); }
.mon-card[data-status="timeout"]::before { background:var(--timeout); }

.mon-card-head { padding:12px 13px 8px 18px; display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
.mon-card-title { font-size:13px; font-weight:800; color:#1e293b; line-height:1.3; }
.mon-card-host  { font-size:10px; color:#94a3b8; font-family:'Courier New',monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px; margin-top:2px; }

.status-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:3px; background:var(--unknown); transition:background .3s; }
.status-dot.online  { background:var(--online);  animation:pulse-g 2s infinite; }
.status-dot.offline { background:var(--offline); }
.status-dot.timeout { background:var(--timeout); animation:pulse-o 2s infinite; }
@keyframes pulse-g { 0%,100%{box-shadow:0 0 0 3px rgba(16,185,129,.2)} 50%{box-shadow:0 0 0 6px rgba(16,185,129,.05)} }
@keyframes pulse-o { 0%,100%{box-shadow:0 0 0 3px rgba(245,158,11,.2)}  50%{box-shadow:0 0 0 6px rgba(245,158,11,.05)} }

.status-chip { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:800; white-space:nowrap; }
.sc-online  { background:#dcfce7; color:#166534; }
.sc-offline { background:#fee2e2; color:#991b1b; }
.sc-timeout { background:#fef9c3; color:#854d0e; }
.sc-unknown { background:#f1f5f9; color:#475569; }

.tipe-chip { font-size:9px; font-weight:800; padding:1px 6px; border-radius:8px; text-transform:uppercase; letter-spacing:.04em; }
.tipe-ip  { background:#ede9fe; color:#7c3aed; }
.tipe-url { background:#dbeafe; color:#1d4ed8; }

.gauge-wrap { display:flex; flex-direction:column; align-items:center; padding:2px 0 0; }
.gauge-svg  { width:100px; height:54px; }

.mon-meter-wrap { padding:0 16px 10px 18px; }
.meter-label    { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
.meter-lbl-l    { font-size:10px; color:#94a3b8; font-weight:600; }
.meter-ping-v   { font-size:14px; font-weight:800; }
.meter-ping-v.good    { color:var(--online);  }
.meter-ping-v.medium  { color:var(--timeout); }
.meter-ping-v.bad     { color:var(--offline); }
.meter-ping-v.unknown { color:var(--unknown); }
.meter-bar  { height:7px; border-radius:4px; background:#f1f5f9; overflow:hidden; }
.meter-fill { height:100%; border-radius:4px; width:0; transition:width .8s cubic-bezier(.4,0,.2,1), background .3s; }
.meter-fill.good   { background:linear-gradient(90deg,#34d399,#10b981); }
.meter-fill.medium { background:linear-gradient(90deg,#fcd34d,#f59e0b); }
.meter-fill.bad    { background:linear-gradient(90deg,#f87171,#ef4444); }

.mon-card-foot    { padding:7px 13px 10px 18px; border-top:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:5px; }
.mon-foot-meta    { font-size:10px; color:#cbd5e1; }
.mon-foot-meta span { color:#94a3b8; font-weight:600; }
.mon-card-actions { display:flex; gap:4px; }
.btn-mon { padding:3px 8px; border-radius:5px; font-size:10px; font-weight:700; border:1px solid; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:3px; transition:all .15s; white-space:nowrap; }
.btn-mon-cek:hover  { background:#26B99A; color:#fff; } .btn-mon-cek  { background:#f0fdf9; color:#26B99A; border-color:#26B99A; }
.btn-mon-edit:hover { background:#fbbf24; color:#fff; } .btn-mon-edit { background:#fefce8; color:#ca8a04; border-color:#fbbf24; }
.btn-mon-log:hover  { background:#3b82f6; color:#fff; } .btn-mon-log  { background:#eff6ff; color:#3b82f6; border-color:#93c5fd; }
.btn-mon-del:hover  { background:#ef4444; color:#fff; } .btn-mon-del  { background:#fff1f2; color:#ef4444; border-color:#fca5a5; }

.mon-card-loading { position:absolute; inset:0; background:rgba(255,255,255,.75); display:none; align-items:center; justify-content:center; border-radius:10px; z-index:5; }
.mon-card-loading.show { display:flex; }
.spin-ring { width:26px; height:26px; border:3px solid #e8ecf0; border-top-color:var(--primary); border-radius:50%; animation:spin .6s linear infinite; }
@keyframes spin { to{transform:rotate(360deg)} }

.btn-cek-semua { display:inline-flex; align-items:center; gap:7px; padding:8px 18px; border-radius:6px; font-size:12px; font-weight:700; background:linear-gradient(135deg,#26B99A,#1a7a5e); color:#fff; border:none; cursor:pointer; font-family:inherit; transition:opacity .2s; }
.btn-cek-semua:hover    { opacity:.88; }
.btn-cek-semua:disabled { opacity:.6; cursor:not-allowed; }

.f-label { font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px; }
.f-inp   { width:100%; padding:8px 11px; border:1px solid #d1d5db; border-radius:6px; font-size:12.5px; box-sizing:border-box; font-family:inherit; transition:border .18s; }
.f-inp:focus { outline:none; border-color:#26B99A; box-shadow:0 0 0 3px rgba(38,185,154,.12); }
.f-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }

.log-item { display:flex; align-items:center; gap:9px; padding:6px 10px; border-radius:6px; margin-bottom:5px; font-size:11.5px; }
.log-item.online  { background:#f0fdf9; border:1px solid #bbf7d0; }
.log-item.offline { background:#fff5f5; border:1px solid #fecaca; }
.log-item.timeout { background:#fffbeb; border:1px solid #fde68a; }
</style>

<div class="page-header">
  <h4><i class="fa fa-wifi text-primary"></i> &nbsp;Monitor Koneksi</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span><span class="cur">Monitor Koneksi</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <?php if (isset($setup_error)): ?>
  <div style="background:#fff8f0;border:1px solid #fcd34d;border-radius:8px;padding:14px 18px;margin-bottom:20px;">
    <div style="font-weight:700;color:#92400e;"><i class="fa fa-triangle-exclamation"></i> Tabel belum dibuat — import <code>koneksi_monitor.sql</code> terlebih dahulu.</div>
    <code style="font-size:10px;color:#6b7280;display:block;margin-top:4px;"><?= htmlspecialchars($setup_error) ?></code>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="mon-stats">
    <div class="mon-stat" style="border-top-color:#3b82f6;">
      <div class="mon-stat-icon" style="background:#eff6ff;"><i class="fa fa-server" style="color:#3b82f6;"></i></div>
      <div><div class="mon-stat-val" id="stat-total"><?= $total ?></div><div class="mon-stat-lbl">Total Host</div></div>
    </div>
    <div class="mon-stat" style="border-top-color:var(--online);">
      <div class="mon-stat-icon" style="background:#dcfce7;"><i class="fa fa-circle-check" style="color:var(--online);"></i></div>
      <div><div class="mon-stat-val" id="stat-online" style="color:var(--online);"><?= $online ?></div><div class="mon-stat-lbl">Online</div></div>
    </div>
    <div class="mon-stat" style="border-top-color:var(--offline);">
      <div class="mon-stat-icon" style="background:#fee2e2;"><i class="fa fa-circle-xmark" style="color:var(--offline);"></i></div>
      <div><div class="mon-stat-val" id="stat-offline" style="color:var(--offline);"><?= $offline ?></div><div class="mon-stat-lbl">Offline</div></div>
    </div>
    <div class="mon-stat" style="border-top-color:var(--timeout);">
      <div class="mon-stat-icon" style="background:#fef9c3;"><i class="fa fa-hourglass-half" style="color:var(--timeout);"></i></div>
      <div><div class="mon-stat-val" id="stat-timeout" style="color:var(--timeout);"><?= $timeout ?></div><div class="mon-stat-lbl">Timeout</div></div>
    </div>
    <div class="mon-stat" style="border-top-color:#94a3b8;">
      <div class="mon-stat-icon" style="background:#f1f5f9;"><i class="fa fa-circle-question" style="color:#94a3b8;"></i></div>
      <div><div class="mon-stat-val" id="stat-belum" style="color:#94a3b8;"><?= $belum ?></div><div class="mon-stat-lbl">Belum Dicek</div></div>
    </div>
  </div>

  <!-- Toolbar -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <button id="btn-cek-semua" class="btn-cek-semua" onclick="cekSemua()">
        <i class="fa fa-rotate-right" id="cek-icon"></i> Cek Semua
      </button>
      <span id="last-refresh" style="font-size:10.5px;color:#94a3b8;"></span>
    </div>
    <button onclick="bukaModalTambah()" class="btn btn-primary btn-sm">
      <i class="fa fa-plus"></i> Tambah Host
    </button>
  </div>

  <!-- Grid Kartu -->
  <?php if (empty($monitors)): ?>
  <div style="background:#f8fafc;border:2px dashed #e2e8f0;border-radius:10px;padding:40px;text-align:center;">
    <i class="fa fa-wifi" style="font-size:40px;color:#cbd5e1;margin-bottom:12px;display:block;"></i>
    <div style="font-size:14px;font-weight:700;color:#64748b;">Belum ada host yang ditambahkan</div>
    <div style="font-size:12px;color:#94a3b8;margin-top:6px;">Klik <strong>Tambah Host</strong> untuk mulai memonitor koneksi</div>
  </div>
  <?php else: ?>

  <?php foreach ($grouped as $kategori => $items): ?>
  <div class="kat-label">
    <i class="fa fa-folder" style="color:var(--primary);"></i>
    <?= htmlspecialchars($kategori) ?>
    <span style="background:#f1f5f9;color:#64748b;font-size:10px;padding:1px 7px;border-radius:9px;font-weight:700;letter-spacing:0;"><?= count($items) ?></span>
  </div>
  <div class="mon-grid">
    <?php foreach ($items as $mon):
      $ls  = $mon['last_status'] ?? null;
      $lp  = $mon['last_ping']   ?? null;
      $lc  = $mon['last_cek']    ?? null;

      $pc  = 'unknown'; $pct = 0;
      if ($ls === 'online' && $lp !== null) {
          if     ($lp < 50)  { $pc = 'good';   $pct = max(5, 100 - ($lp/50)*40); }
          elseif ($lp < 200) { $pc = 'medium'; $pct = max(5, 60  - (($lp-50)/150)*30); }
          else               { $pc = 'bad';    $pct = max(5, 30  - min(25,($lp-200)/50)); }
      } elseif ($ls === 'offline') { $pc = 'bad';    $pct = 100; }
      elseif ($ls === 'timeout')   { $pc = 'medium'; $pct = 50; }

      $gc         = $ls==='online' ? '#10b981' : ($ls==='timeout' ? '#f59e0b' : ($ls==='offline' ? '#ef4444' : '#e2e8f0'));
      $dashOffset = round(141.3 * (1 - $pct/100), 1);
      $gaugeText  = $ls==='online' && $lp!==null ? round($lp).'ms' : ($ls==='offline' ? 'DOWN' : ($ls==='timeout' ? 'T.O.' : '—'));
    ?>
    <div class="mon-card" id="card-<?= $mon['id'] ?>" data-status="<?= $ls ?? '' ?>">
      <div class="mon-card-loading" id="loading-<?= $mon['id'] ?>"><div class="spin-ring"></div></div>

      <div class="mon-card-head">
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
            <span class="tipe-chip tipe-<?= $mon['tipe'] ?>"><?= strtoupper($mon['tipe']) ?></span>
            <?php if (!$mon['aktif']): ?><span style="font-size:9px;background:#f1f5f9;color:#94a3b8;padding:1px 6px;border-radius:8px;font-weight:700;">NONAKTIF</span><?php endif; ?>
          </div>
          <div class="mon-card-title"><?= htmlspecialchars($mon['nama']) ?></div>
          <div class="mon-card-host" title="<?= htmlspecialchars($mon['host']) ?>"><?= htmlspecialchars($mon['host']) ?><?= $mon['port'] ? ':'.$mon['port'] : '' ?></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
          <div class="status-dot <?= $ls ?? '' ?>" id="dot-<?= $mon['id'] ?>"></div>
          <span class="status-chip sc-<?= $ls ?? 'unknown' ?>" id="chip-<?= $mon['id'] ?>">
            <?php
              if     ($ls==='online')  echo '<i class="fa fa-circle-check"></i> Online';
              elseif ($ls==='offline') echo '<i class="fa fa-circle-xmark"></i> Offline';
              elseif ($ls==='timeout') echo '<i class="fa fa-hourglass"></i> Timeout';
              else                     echo '<i class="fa fa-circle-question"></i> Belum';
            ?>
          </span>
        </div>
      </div>

      <div class="gauge-wrap">
        <svg class="gauge-svg" viewBox="0 0 110 60" id="gauge-<?= $mon['id'] ?>" style="overflow:visible;">
          <path d="M10,55 A45,45 0 0,1 100,55" fill="none" stroke="#f1f5f9" stroke-width="9" stroke-linecap="round"/>
          <path d="M10,55 A45,45 0 0,1 100,55" fill="none" stroke="<?= $gc ?>" stroke-width="9"
                stroke-linecap="round" stroke-dasharray="141.3" stroke-dashoffset="<?= $dashOffset ?>"
                id="gauge-fill-<?= $mon['id'] ?>" style="transition:stroke-dashoffset .8s ease,stroke .3s;"/>
          <text x="55" y="51" text-anchor="middle" font-size="12" font-weight="800"
                fill="<?= $gc ?>" id="gauge-text-<?= $mon['id'] ?>"><?= $gaugeText ?></text>
        </svg>
      </div>

      <div class="mon-meter-wrap">
        <div class="meter-label">
          <span class="meter-lbl-l">Latency</span>
          <span class="meter-ping-v <?= $pc ?>" id="ping-val-<?= $mon['id'] ?>">
            <?= $ls==='online' && $lp!==null ? round($lp).' ms' : ($ls ? strtoupper($ls) : '—') ?>
          </span>
        </div>
        <div class="meter-bar">
          <div class="meter-fill <?= $pc ?>" id="meter-<?= $mon['id'] ?>" style="width:<?= $pct ?>%;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:9px;color:#e2e8f0;margin-top:2px;">
          <span>Cepat</span><span>Sedang</span><span>Lambat</span>
        </div>
      </div>

      <div class="mon-card-foot">
        <div class="mon-foot-meta">
          <?php if ($lc): ?>Cek: <span><?= date('H:i d/m', strtotime($lc)) ?></span>
          <?php else: ?><span>Belum pernah dicek</span><?php endif; ?>
        </div>
        <div class="mon-card-actions">
          <button class="btn-mon btn-mon-log"  onclick="lihatLog(<?= $mon['id'] ?>,'<?= htmlspecialchars(addslashes($mon['nama'])) ?>')" title="Log"><i class="fa fa-clock-rotate-left"></i></button>
          <button class="btn-mon btn-mon-edit" onclick="editMonitor(<?= $mon['id'] ?>)" title="Edit"><i class="fa fa-pen"></i></button>
          <button class="btn-mon btn-mon-cek"  onclick="cekSatu(<?= $mon['id'] ?>)" id="btn-cek-<?= $mon['id'] ?>" title="Cek"><i class="fa fa-rotate-right"></i> Cek</button>
          <?php if (hasRole('admin')): ?>
          <button class="btn-mon btn-mon-del"  onclick="hapusMonitor(<?= $mon['id'] ?>,'<?= htmlspecialchars(addslashes($mon['nama'])) ?>')" title="Hapus"><i class="fa fa-trash"></i></button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; endif; ?>
</div>


<!-- ══ MODAL TAMBAH/EDIT ══ -->
<div class="modal-ov" id="m-tambah-mon" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:500px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 18px;background:linear-gradient(135deg,#1a2e3f,#1b5c4a);">
      <div style="display:flex;align-items:center;gap:9px;">
        <div style="width:28px;height:28px;background:rgba(38,185,154,.25);border-radius:6px;display:flex;align-items:center;justify-content:center;">
          <i id="modal-mon-icon" class="fa fa-plus" style="color:#26B99A;font-size:11px;"></i>
        </div>
        <div id="modal-mon-title" style="color:#fff;font-size:13px;font-weight:700;">Tambah Host Monitor</div>
      </div>
      <button onclick="closeModal('m-tambah-mon')" style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:12px;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='#ef4444';this.style.color='#fff';" onmouseout="this.style.background='rgba(255,255,255,.1)';this.style.color='#ccc';"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/pages/cek_koneksi.php" id="form-mon">
      <input type="hidden" name="_action" id="fm-mon-action" value="tambah">
      <input type="hidden" name="id"      id="fm-mon-id"     value="">
      <div style="padding:16px 18px;">
        <div class="f-grid-2">
          <div>
            <label class="f-label">Label / Nama <span style="color:#ef4444;">*</span></label>
            <input type="text" name="nama" id="fm-mon-nama" class="f-inp" placeholder="cth: Server SIMRS" required>
          </div>
          <div>
            <label class="f-label">Kategori</label>
            <input type="text" name="kategori" id="fm-mon-kategori" class="f-inp" list="kat-list" value="Umum">
            <datalist id="kat-list">
              <?php foreach($kategori_list as $k): ?><option value="<?= htmlspecialchars($k) ?>"><?php endforeach; ?>
              <option value="Server"><option value="Internet"><option value="Printer"><option value="NAS"><option value="Kamera">
            </datalist>
          </div>
        </div>
        <div style="margin-bottom:12px;">
          <label class="f-label">Host / URL <span style="color:#ef4444;">*</span></label>
          <input type="text" name="host" id="fm-mon-host" class="f-inp" placeholder="192.168.1.1 atau https://contoh.com" required>
          <div style="font-size:10px;color:#94a3b8;margin-top:3px;">IP: masukkan alamat IP. URL: sertakan http:// atau https://</div>
        </div>
        <div class="f-grid-2">
          <div>
            <label class="f-label">Tipe</label>
            <select name="tipe" id="fm-mon-tipe" class="f-inp" onchange="togglePort(this.value)">
              <option value="url">🌐 URL (HTTP/HTTPS)</option>
              <option value="ip">🖥️ IP (TCP Port)</option>
            </select>
          </div>
          <div id="port-wrap">
            <label class="f-label">Port <span style="color:#94a3b8;font-weight:400;">(opsional)</span></label>
            <input type="number" name="port" id="fm-mon-port" class="f-inp" placeholder="80, 443, 3306…" min="1" max="65535">
          </div>
        </div>
        <div class="f-grid-2" style="margin-top:12px;">
          <div>
            <label class="f-label">Timeout (maks 10 detik)</label>
            <input type="number" name="timeout_detik" id="fm-mon-timeout" class="f-inp" value="3" min="1" max="10">
          </div>
          <div style="display:flex;align-items:flex-end;padding-bottom:2px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12.5px;font-weight:600;color:#374151;">
              <input type="checkbox" name="aktif" id="fm-mon-aktif" checked style="width:15px;height:15px;accent-color:#26B99A;">
              Aktifkan monitor
            </label>
          </div>
        </div>
      </div>
      <div style="padding:10px 18px;border-top:1px solid #f0f0f0;display:flex;justify-content:flex-end;gap:8px;background:#f8fafc;">
        <button type="button" onclick="closeModal('m-tambah-mon')" style="padding:7px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
        <button type="submit" style="padding:7px 16px;background:linear-gradient(135deg,#26B99A,#1a7a5e);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"><i class="fa fa-save"></i> <span id="fm-mon-btn-lbl">Simpan</span></button>
      </div>
    </form>
  </div>
</div>


<!-- ══ MODAL LOG ══ -->
<div class="modal-ov" id="m-log-mon" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:460px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:12px 16px;background:linear-gradient(135deg,#1a2e3f,#2a3f54);display:flex;align-items:center;justify-content:space-between;">
      <div style="display:flex;align-items:center;gap:8px;">
        <i class="fa fa-clock-rotate-left" style="color:#26B99A;"></i>
        <div id="log-title" style="color:#fff;font-size:13px;font-weight:700;"></div>
      </div>
      <button onclick="closeModal('m-log-mon')" style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:12px;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='#ef4444';this.style.color='#fff';" onmouseout="this.style.background='rgba(255,255,255,.1)';this.style.color='#ccc';"><i class="fa fa-times"></i></button>
    </div>
    <div id="log-body" style="padding:12px 14px;max-height:55vh;overflow-y:auto;"></div>
    <div style="padding:9px 14px;border-top:1px solid #f0f0f0;text-align:right;background:#f8fafc;">
      <button onclick="closeModal('m-log-mon')" style="padding:5px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;">Tutup</button>
    </div>
  </div>
</div>


<!-- ══ MODAL HAPUS ══ -->
<div class="modal-ov" id="m-hapus-mon" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:340px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:20px 20px 14px;text-align:center;">
      <div style="width:46px;height:46px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;"><i class="fa fa-trash" style="color:#ef4444;font-size:18px;"></i></div>
      <div style="font-size:14px;font-weight:700;color:#1e293b;">Hapus Host?</div>
      <div style="font-size:12px;color:#64748b;margin-top:5px;">Host <strong id="hapus-mon-nama"></strong> dan semua log akan dihapus permanen.</div>
    </div>
    <form method="POST" action="<?= APP_URL ?>/pages/cek_koneksi.php">
      <input type="hidden" name="_action" value="hapus">
      <input type="hidden" name="id" id="hapus-mon-id">
      <div style="padding:10px 18px;display:flex;gap:8px;justify-content:center;border-top:1px solid #f0f0f0;">
        <button type="button" onclick="closeModal('m-hapus-mon')" style="padding:7px 16px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;font-family:inherit;">Batal</button>
        <button type="submit" style="padding:7px 16px;background:#ef4444;border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"><i class="fa fa-trash"></i> Hapus</button>
      </div>
    </form>
  </div>
</div>


<script>
const APP_URL = '<?= APP_URL ?>';

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function pingClass(ms){
    if (ms==null) return 'unknown';
    if (ms<50)    return 'good';
    if (ms<200)   return 'medium';
    return 'bad';
}
function pingPct(ms,status){
    if (status==='offline') return 100;
    if (status==='timeout') return 50;
    if (!ms) return 0;
    if (ms<50)  return Math.max(5,100-(ms/50)*40);
    if (ms<200) return Math.max(5,60-((ms-50)/150)*30);
    return Math.max(5,30-Math.min(25,(ms-200)/50));
}

function updateCard(data){
    const {id,status,ping_ms} = data;
    const card  = document.getElementById('card-'       +id); if(!card) return;
    const dot   = document.getElementById('dot-'        +id);
    const chip  = document.getElementById('chip-'       +id);
    const meter = document.getElementById('meter-'      +id);
    const pval  = document.getElementById('ping-val-'   +id);
    const gFill = document.getElementById('gauge-fill-' +id);
    const gText = document.getElementById('gauge-text-' +id);

    card.dataset.status = status;
    dot.className = 'status-dot ' + status;

    const chipMap = {online:'<i class="fa fa-circle-check"></i> Online',offline:'<i class="fa fa-circle-xmark"></i> Offline',timeout:'<i class="fa fa-hourglass"></i> Timeout'};
    chip.className = 'status-chip sc-' + status;
    chip.innerHTML = chipMap[status] || '—';

    const cls   = pingClass(ping_ms);
    const pct   = pingPct(ping_ms,status);
    const label = status==='online' && ping_ms!=null ? Math.round(ping_ms)+' ms' : status.toUpperCase();
    pval.className   = 'meter-ping-v '+cls;
    pval.textContent = label;
    meter.className  = 'meter-fill '+cls;
    setTimeout(()=>{ meter.style.width = pct+'%'; }, 30);

    const gc = {online:'#10b981',offline:'#ef4444',timeout:'#f59e0b'}[status] || '#e2e8f0';
    gFill.setAttribute('stroke', gc);
    gFill.setAttribute('stroke-dashoffset', Math.round(141.3*(1-pct/100)*10)/10);
    gText.setAttribute('fill', gc);
    gText.textContent = status==='online' && ping_ms!=null ? Math.round(ping_ms)+'ms' : (status==='offline'?'DOWN':'T.O.');
}

function updateStats(){
    let ol=0,off=0,to=0,bel=0;
    document.querySelectorAll('.mon-card').forEach(c=>{
        const s=c.dataset.status;
        if(s==='online')ol++; else if(s==='offline')off++; else if(s==='timeout')to++; else bel++;
    });
    document.getElementById('stat-online').textContent  = ol;
    document.getElementById('stat-offline').textContent = off;
    document.getElementById('stat-timeout').textContent = to;
    document.getElementById('stat-belum').textContent   = bel;
}

function setLoading(id,show){
    const el  = document.getElementById('loading-'+id);
    const btn = document.getElementById('btn-cek-'+id);
    if(el)  el.classList.toggle('show',show);
    if(btn) btn.disabled = show;
}

function cekSatu(id){
    setLoading(id,true);
    fetch(APP_URL+'/pages/cek_koneksi.php?ajax_cek='+id)
        .then(r=>r.json())
        .then(data=>{ setLoading(id,false); updateCard(data); updateStats(); })
        .catch(()=>setLoading(id,false));
}

function cekSemua(){
    const btn  = document.getElementById('btn-cek-semua');
    const icon = document.getElementById('cek-icon');
    btn.disabled   = true;
    icon.className = 'fa fa-rotate-right fa-spin';
    document.querySelectorAll('.mon-card').forEach(c=>setLoading(c.id.replace('card-',''),true));

    fetch(APP_URL+'/pages/cek_koneksi.php?ajax_cek_semua=1')
        .then(r=>r.json())
        .then(results=>{
            results.forEach(data=>{ setLoading(data.id,false); updateCard(data); });
            updateStats();
            document.getElementById('last-refresh').textContent =
                'Diperbarui: '+new Date().toLocaleTimeString('id-ID');
        })
        .catch(()=>document.querySelectorAll('.mon-card').forEach(c=>setLoading(c.id.replace('card-',''),false)))
        .finally(()=>{ btn.disabled=false; icon.className='fa fa-rotate-right'; });
}

function lihatLog(id,nama){
    document.getElementById('log-title').textContent = nama;
    document.getElementById('log-body').innerHTML = '<div style="text-align:center;color:#94a3b8;padding:20px;"><i class="fa fa-spinner fa-spin"></i> Memuat…</div>';
    openModal('m-log-mon');
    fetch(APP_URL+'/pages/cek_koneksi.php?get_log='+id)
        .then(r=>r.json())
        .then(logs=>{
            if(!logs.length){ document.getElementById('log-body').innerHTML='<div style="text-align:center;color:#94a3b8;padding:20px;"><i class="fa fa-inbox"></i> Belum ada riwayat</div>'; return; }
            const cMap={online:'#10b981',offline:'#ef4444',timeout:'#f59e0b'};
            const iMap={online:'fa-circle-check',offline:'fa-circle-xmark',timeout:'fa-hourglass-half'};
            document.getElementById('log-body').innerHTML=logs.map(l=>{
                const dt=new Date(l.cek_at).toLocaleString('id-ID');
                const col=cMap[l.status]||'#94a3b8';
                const ic =iMap[l.status]||'fa-circle-question';
                const pingTxt=l.ping_ms!=null?Math.round(l.ping_ms)+' ms':'—';
                return `<div class="log-item ${escHtml(l.status)}">
                    <i class="fa ${ic}" style="color:${col};font-size:13px;flex-shrink:0;"></i>
                    <div style="flex:1;">
                      <div style="font-weight:700;font-size:12px;color:#1e293b;">${escHtml(l.status.toUpperCase())} <span style="font-weight:400;color:#94a3b8;font-size:11px;">${escHtml(l.pesan||'')}</span></div>
                      <div style="font-size:10px;color:#94a3b8;">${dt}${l.http_code?` · HTTP ${escHtml(String(l.http_code))}`:''}
                    </div>
                    </div>
                    <div style="font-size:12px;font-weight:800;color:${col};">${pingTxt}</div>
                </div>`;
            }).join('');
        });
}

function bukaModalTambah(){
    document.getElementById('form-mon').reset();
    document.getElementById('fm-mon-action').value      ='tambah';
    document.getElementById('fm-mon-id').value          ='';
    document.getElementById('modal-mon-title').textContent='Tambah Host Monitor';
    document.getElementById('modal-mon-icon').className  ='fa fa-plus';
    document.getElementById('fm-mon-btn-lbl').textContent='Simpan';
    document.getElementById('fm-mon-aktif').checked     =true;
    document.getElementById('fm-mon-timeout').value     ='3';
    document.getElementById('fm-mon-kategori').value    ='Umum';
    togglePort('url');
    openModal('m-tambah-mon');
}

function editMonitor(id){
    fetch(APP_URL+'/pages/cek_koneksi.php?get_monitor='+id)
        .then(r=>r.json())
        .then(d=>{
            document.getElementById('fm-mon-action').value      ='edit';
            document.getElementById('fm-mon-id').value          =d.id;
            document.getElementById('modal-mon-title').textContent='Edit Host Monitor';
            document.getElementById('modal-mon-icon').className  ='fa fa-pen';
            document.getElementById('fm-mon-btn-lbl').textContent='Perbarui';
            document.getElementById('fm-mon-nama').value    =d.nama      ||'';
            document.getElementById('fm-mon-host').value    =d.host      ||'';
            document.getElementById('fm-mon-tipe').value    =d.tipe      ||'url';
            document.getElementById('fm-mon-kategori').value=d.kategori  ||'Umum';
            document.getElementById('fm-mon-port').value    =d.port      ||'';
            document.getElementById('fm-mon-timeout').value =d.timeout_detik||3;
            document.getElementById('fm-mon-aktif').checked =d.aktif==1;
            togglePort(d.tipe);
            openModal('m-tambah-mon');
        });
}

function hapusMonitor(id,nama){
    document.getElementById('hapus-mon-id').value         =id;
    document.getElementById('hapus-mon-nama').textContent =nama;
    openModal('m-hapus-mon');
}

function togglePort(tipe){
    document.getElementById('port-wrap').style.opacity = tipe==='ip' ? '1' : '.4';
}
</script>

<?php include '../includes/footer.php'; ?>