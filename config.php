<?php
// ============================================================
// config.php — Konfigurasi & Helper Functions
// ============================================================

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'fixsmart');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'FixSmart Helpdesk');
define('APP_VERSION', '2.0.0');

// Auto-detect APP_URL — menggunakan lokasi config.php sebagai anchor tetap
// config.php SELALU ada di root project, sehingga hasilnya konsisten
// dari file mana pun (root, pages/, includes/, dll)
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_docroot  = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '/')), '/');
$_cfgdir   = rtrim(str_replace('\\', '/', realpath(dirname(__FILE__))), '/');
// Hitung path relatif: /fixsmart (atau nama folder apapun)
$_relpath  = str_replace($_docroot, '', $_cfgdir);
define('APP_URL', $_protocol . '://' . $_host . $_relpath);

define('SESSION_TIMEOUT', 7200); // 2 jam
date_default_timezone_set('Asia/Jakarta');

// ============================================================
// Koneksi Database (PDO)
// ============================================================
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=".DB_CHARSET,
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title>
    <style>*{margin:0;padding:0;box-sizing:border-box;}body{font-family:sans-serif;background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh;}
    .box{background:#fff;padding:30px 35px;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:480px;border-top:4px solid #e74c3c;}
    h3{color:#e74c3c;margin-bottom:12px;font-size:16px;}p{color:#666;font-size:13px;line-height:1.8;}
    code{background:#f5f5f5;padding:1px 5px;border-radius:3px;font-size:12px;color:#e74c3c;}</style>
    </head><body><div class="box">
    <h3>&#9888; Gagal Terhubung ke Database</h3>
    <p>Pastikan hal berikut sudah benar di <code>config.php</code>:<br>
    &bull; MySQL sudah berjalan<br>
    &bull; Database <code>'.DB_NAME.'</code> sudah dibuat<br>
    &bull; File <code>database.sql</code> sudah diimport<br>
    &bull; Username &amp; password DB sesuai</p>
    <p style="margin-top:12px;color:#aaa;font-size:11px;">'.htmlspecialchars($e->getMessage()).'</p>
    </div></body></html>');
}

// ============================================================
// Status tiket
// ============================================================
define('STATUS_LIST', [
    'menunggu'   => ['label' => 'Menunggu',         'class' => 'st-menunggu',  'icon' => 'fa-clock',         'color' => '#f39c12'],
    'diproses'   => ['label' => 'Diproses',          'class' => 'st-diproses',  'icon' => 'fa-cogs',          'color' => '#3498db'],
    'selesai'    => ['label' => 'Selesai',            'class' => 'st-selesai',   'icon' => 'fa-check-circle',  'color' => '#27ae60'],
    'ditolak'    => ['label' => 'Ditolak',            'class' => 'st-ditolak',   'icon' => 'fa-times-circle',  'color' => '#e74c3c'],
    'tidak_bisa' => ['label' => 'Tidak Bisa Ditangani','class'=> 'st-tidakbisa','icon' => 'fa-ban',            'color' => '#8e44ad'],
]);

// ============================================================
// Helper Functions
// ============================================================

function clean($str) {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($role) {
    if (!isLoggedIn()) return false;
    if (is_array($role)) return in_array($_SESSION['user_role'], $role);
    return $_SESSION['user_role'] === $role;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/login.php');
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        redirect(APP_URL . '/login.php?msg=timeout');
    }
    $_SESSION['last_activity'] = time();
}

function formatTanggal($date, $withTime = false) {
    if (!$date || $date === '0000-00-00 00:00:00') return '-';
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $d = date_create($date);
    if (!$d) return '-';
    $fmt = $withTime ? 'd M Y, H:i' : 'd M Y';
    $result = date_format($d, $fmt);
    for ($i = 1; $i <= 12; $i++) {
        $result = str_replace(date('M', mktime(0,0,0,$i,1)), $bulan[$i], $result);
    }
    return $result;
}

function badgeStatus($status) {
    $list = STATUS_LIST;
    $s = $list[$status] ?? ['label' => ucfirst($status), 'class' => '', 'icon' => 'fa-circle'];
    return '<span class="badge-st '.$s['class'].'"><i class="fa '.$s['icon'].'"></i> '.$s['label'].'</span>';
}

function badgePrioritas($p) {
    $map = ['Tinggi' => 'pr-tinggi', 'Sedang' => 'pr-sedang', 'Rendah' => 'pr-rendah'];
    return '<span class="badge-pr '.($map[$p]??'').'">'.$p.'</span>';
}

function generateNomor($pdo) {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM tiket")->fetchColumn();
    return 'TKT-' . str_pad($total + 1, 5, '0', STR_PAD_LEFT);
}

function setFlash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function showFlash() {
    if (!isset($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icons = ['success'=>'fa-check-circle','danger'=>'fa-times-circle','warning'=>'fa-exclamation-triangle','info'=>'fa-info-circle'];
    $ic = $icons[$f['type']] ?? 'fa-info-circle';
    return '<div class="alert alert-'.$f['type'].'"><i class="fa '.$ic.'"></i> '.clean($f['msg']).'
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:16px;opacity:.6;">&times;</button></div>';
}

function getInitials($name) {
    $w = explode(' ', trim((string)$name));
    $i = '';
    foreach (array_slice($w, 0, 2) as $word) if ($word) $i .= strtoupper($word[0]);
    return $i ?: '?';
}

function timeAgo($dt) {
    if (!$dt) return '-';
    $diff = (new DateTime())->diff(new DateTime($dt));
    if ($diff->y) return $diff->y.' tahun lalu';
    if ($diff->m) return $diff->m.' bulan lalu';
    if ($diff->d) return $diff->d.' hari lalu';
    if ($diff->h) return $diff->h.' jam lalu';
    if ($diff->i) return $diff->i.' menit lalu';
    return 'Baru saja';
}

// Hitung durasi dalam format "X jam Y menit" dari menit
function formatDurasi($menit) {
    if ($menit === null) return '-';
    $menit = (int)$menit;
    if ($menit < 60) return $menit . ' menit';
    $jam = floor($menit / 60);
    $sisa = $menit % 60;
    return $jam . ' jam' . ($sisa ? ' ' . $sisa . ' mnt' : '');
}

// Hitung SLA status: apakah sudah melebihi target?
function slaStatus($durasi_menit, $target_jam) {
    if ($durasi_menit === null) return null;
    $target_menit = $target_jam * 60;
    $persen = round($durasi_menit / $target_menit * 100);
    if ($persen <= 70)  return ['status' => 'aman',     'persen' => $persen, 'label' => 'Dalam Target', 'class' => 'sla-aman'];
    if ($persen <= 100) return ['status' => 'warning',  'persen' => $persen, 'label' => 'Mendekati Batas', 'class' => 'sla-warning'];
    return              ['status' => 'breach',   'persen' => $persen, 'label' => 'Melewati Target', 'class' => 'sla-breach'];
}

// Hitung waktu berjalan dari submit hingga sekarang (jika belum selesai)
function durasiSekarang($waktu_submit) {
    if (!$waktu_submit) return null;
    $diff = (new DateTime())->diff(new DateTime($waktu_submit));
    return ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
}

// Helper: ambil daftar bagian aktif dari database
function getBagianList($pdo) {
    return $pdo->query("SELECT * FROM bagian WHERE status='aktif' ORDER BY urutan ASC, nama ASC")->fetchAll();
}

// Helper: ambil semua settings ke array key=>value
function getSettings($pdo) {
    $rows = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    return $out;
}

// Helper: kirim notifikasi Telegram
function sendTelegram($pdo, $message) {
    $cfg = getSettings($pdo);
    if (empty($cfg['telegram_enabled']) || $cfg['telegram_enabled'] != '1') return false;
    if (empty($cfg['telegram_bot_token']) || empty($cfg['telegram_chat_id']))  return false;

    $url  = 'https://api.telegram.org/bot' . $cfg['telegram_bot_token'] . '/sendMessage';
    $data = http_build_query([
        'chat_id'    => $cfg['telegram_chat_id'],
        'text'       => $message,
        'parse_mode' => 'HTML',
    ]);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $data,
        'timeout' => 5,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    return $result !== false;
}
