<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => false,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);
require_once 'config.php';
require_once 'includes/login_helper.php';

if (isLoggedIn()) redirect(APP_URL . '/dashboard.php');

define('MAX_ATTEMPTS',   5);
define('LOCKOUT_SECS',   180);

function loginKey() { $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; return 'login_' . md5($ip); }
function getAttempts() { $key=loginKey(); $data=isset($_SESSION[$key])?$_SESSION[$key]:null; if(!$data) return ['count'=>0,'first'=>0,'locked_until'=>0]; return $data; }
function recordFail() { $key=loginKey(); $data=getAttempts(); if($data['count']===0)$data['first']=time(); $data['count']++; if($data['count']>=MAX_ATTEMPTS)$data['locked_until']=time()+LOCKOUT_SECS; $_SESSION[$key]=$data; }
function resetAttempts() { unset($_SESSION[loginKey()]); }
function isLocked() { $data=getAttempts(); if($data['locked_until']>time())return true; if($data['locked_until']>0&&$data['locked_until']<=time())resetAttempts(); return false; }
function remainingSeconds() { $data=getAttempts(); return max(0,$data['locked_until']-time()); }
function remainingAttempts() { $data=getAttempts(); return max(0,MAX_ATTEMPTS-$data['count']); }

function generateCaptcha() {
    $ops=['+','-','+','+','-']; $op=$ops[array_rand($ops)];
    if($op==='+'){$a=rand(1,9);$b=rand(1,9);$ans=$a+$b;}else{$a=rand(3,9);$b=rand(1,$a);$ans=$a-$b;}
    $_SESSION['captcha_answer']=$ans; $_SESSION['captcha_token']=bin2hex(random_bytes(8));
    return "$a $op $b";
}
if(empty($_SESSION['captcha_answer'])) $captcha_soal=generateCaptcha();
else $captcha_soal=isset($_SESSION['captcha_soal'])?$_SESSION['captcha_soal']:generateCaptcha();
$_SESSION['captcha_soal']=$captcha_soal;

$error='';
$msg_map=['timeout'=>'Sesi Anda berakhir, silakan login kembali.','logout'=>'Anda berhasil keluar.'];
$msg=isset($msg_map[$_GET['msg']??''])?$msg_map[$_GET['msg']??'']:'';
if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));

if($_SERVER['REQUEST_METHOD']==='POST'){
    $log_ip=getClientIP(); $log_ua=$_SERVER['HTTP_USER_AGENT']??'';
    $log_parsed=parseUserAgent($log_ua); $log_input=trim($_POST['email']??'');

    if(isLocked()){
        $sisa=remainingSeconds(); $mnt=ceil($sisa/60);
        $error="Terlalu banyak percobaan gagal. Coba lagi dalam <strong>{$sisa} detik</strong> (~{$mnt} menit).";
        recordLoginLog($pdo,['user_id'=>null,'username_input'=>$log_input,'status'=>'terkunci','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>"Percobaan saat terkunci, sisa {$sisa} detik",'is_new_ip'=>0]);
    }elseif(!hash_equals($_SESSION['csrf_token']??'',$_POST['csrf_token']??'')){
        $error='Permintaan tidak valid. Silakan muat ulang halaman.';
    }elseif((int)($_POST['captcha']??-999)!==(int)($_SESSION['captcha_answer']??-1)){
        $error='Jawaban captcha salah. Silakan coba lagi.';
        $captcha_soal=generateCaptcha(); $_SESSION['captcha_soal']=$captcha_soal;
        recordFail();
        recordLoginLog($pdo,['user_id'=>null,'username_input'=>$log_input,'status'=>'gagal','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>'Captcha salah','is_new_ip'=>0]);
    }else{
        $u=trim($_POST['email']??''); $p=$_POST['password']??'';
        if(!$u||!$p){
            $error='Email dan password wajib diisi.';
        }else{
            $st=$pdo->prepare("SELECT * FROM users WHERE email=? AND status='aktif' LIMIT 1");
            $st->execute([$u]); $user=$st->fetch();
            if($user&&password_verify($p,$user['password'])){
                $log_new_ip=isNewIPForUser($pdo,(int)$user['id'],$log_ip);
                recordLoginLog($pdo,['user_id'=>$user['id'],'username_input'=>$u,'status'=>'berhasil','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>null,'is_new_ip'=>$log_new_ip?1:0]);
                resetAttempts(); session_regenerate_id(true);
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['user_nama']     = $user['nama'];
                $_SESSION['user_role']     = $user['role'];
                $_SESSION['user_divisi']   = $user['divisi'];
                $_SESSION['pokja_id']      = $user['pokja_id']      ?? null;
                $_SESSION['is_akreditasi'] = (int)($user['is_akreditasi'] ?? 0);
                $_SESSION['last_activity'] = time();
                $_SESSION['login_ip']      = $log_ip;
                unset($_SESSION['csrf_token'],$_SESSION['captcha_answer'],$_SESSION['captcha_soal']);
                if($log_new_ip) setFlash('warning','<i class="fa fa-shield-halved"></i> Login dari perangkat/lokasi baru terdeteksi <strong>('.htmlspecialchars($log_ip).')</strong>. Jika bukan Anda, segera hubungi administrator.');
                redirect(APP_URL.'/dashboard.php');
            }else{
                recordFail();
                $captcha_soal=generateCaptcha(); $_SESSION['captcha_soal']=$captcha_soal;
                $sisa_coba=remainingAttempts();
                $keterangan=$user?'Password salah':'Email tidak ditemukan';
                recordLoginLog($pdo,['user_id'=>$user['id']??null,'username_input'=>$u,'status'=>'gagal','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>$keterangan,'is_new_ip'=>0]);
                if(isLocked()){
                    $sisa=remainingSeconds();
                    $error="Akun dikunci selama <strong>".LOCKOUT_SECS." detik</strong> karena terlalu banyak percobaan gagal.";
                }elseif($sisa_coba<=2){
                    $error="Email atau password salah. <strong>Sisa percobaan: {$sisa_coba}x</strong> sebelum dikunci.";
                }else{
                    $error='Email atau password salah.';
                }
            }
        }
    }
    $_SESSION['csrf_token']=bin2hex(random_bytes(32));
}
$attempts_data=getAttempts(); $is_locked=isLocked(); $sisa_coba=remainingAttempts();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --bg:        #070d12;
  --surface:   #0d1520;
  --card:      #111c2a;
  --border:    #1a2d40;
  --border2:   #1e3550;
  --teal:      #00e5b0;
  --teal2:     #00c896;
  --teal-dim:  rgba(0,229,176,0.10);
  --teal-glow: rgba(0,229,176,0.22);
  --teal-soft: rgba(0,229,176,0.06);
  --text:      #e2eef8;
  --text2:     #a0b8cc;
  --muted:     #4d6e88;
  --danger:    #ff4d6d;
  --warn:      #f59e0b;
  --success:   #10b981;
  --r:         12px;
  --r2:        16px;
}
html,body { min-height:100vh; font-family:'Space Grotesk',sans-serif; background:var(--bg); color:var(--text); }

/* ── BACKGROUND ── */
.bg-wrap { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
.bg-grid {
  position:absolute; inset:0;
  background-image:
    linear-gradient(rgba(0,229,176,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,229,176,0.03) 1px, transparent 1px);
  background-size:48px 48px;
}
.bg-glow1 { position:absolute; width:600px; height:600px; border-radius:50%; background:radial-gradient(circle, rgba(0,229,176,0.08), transparent 70%); top:-200px; left:-100px; animation:pulse 8s ease-in-out infinite; }
.bg-glow2 { position:absolute; width:400px; height:400px; border-radius:50%; background:radial-gradient(circle, rgba(0,120,255,0.06), transparent 70%); bottom:-100px; right:0; animation:pulse 10s ease-in-out infinite reverse; }
.bg-glow3 { position:absolute; width:300px; height:300px; border-radius:50%; background:radial-gradient(circle, rgba(0,229,176,0.05), transparent 70%); top:40%; right:20%; animation:pulse 12s ease-in-out infinite 2s; }
@keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.15);opacity:.7} }

/* ── LAYOUT ── */
.page { position:relative; z-index:1; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:32px 20px; }

/* ── TOP BRAND BAR ── */
.topbar { display:flex; align-items:center; justify-content:space-between; width:100%; max-width:1100px; margin-bottom:40px; }
.brand { display:flex; align-items:center; gap:11px; }
.brand-logo { width:42px; height:42px; background:linear-gradient(135deg,var(--teal),#00a8cc); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; color:#07121e; font-weight:800; box-shadow:0 0 20px var(--teal-glow); flex-shrink:0; }
.brand-name { font-family:'Syne',sans-serif; font-size:18px; font-weight:800; letter-spacing:-.5px; color:var(--text); }
.brand-name em { color:var(--teal); font-style:normal; }
.brand-sub { font-size:10px; color:var(--muted); letter-spacing:.5px; margin-top:1px; }
.topbar-badge { display:flex; align-items:center; gap:6px; background:var(--teal-dim); border:1px solid rgba(0,229,176,0.18); color:var(--teal); font-size:10.5px; font-weight:600; padding:5px 12px; border-radius:99px; letter-spacing:.8px; text-transform:uppercase; }
.topbar-badge i { font-size:8px; }

/* ── MAIN GRID ── */
.main-grid { display:grid; grid-template-columns:1fr 420px; gap:32px; width:100%; max-width:1100px; align-items:start; }

/* ── LEFT: FEATURES ── */
.features-col { display:flex; flex-direction:column; gap:20px; }
.features-headline h1 { font-family:'Syne',sans-serif; font-size:clamp(28px,3.2vw,44px); font-weight:800; line-height:1.08; letter-spacing:-1.5px; margin-bottom:12px; }
.features-headline h1 .line1 { color:var(--text); }
.features-headline h1 .line2 { color:var(--teal); }
.features-headline h1 .line3 { -webkit-text-stroke:1.5px rgba(255,255,255,0.12); color:transparent; }
.features-headline p { font-size:13.5px; color:var(--text2); line-height:1.75; max-width:440px; }

/* Feature module groups */
.feat-groups { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.feat-group { background:var(--surface); border:1px solid var(--border); border-radius:var(--r2); padding:16px; transition:border-color .2s, transform .2s; cursor:default; }
.feat-group:hover { border-color:var(--border2); transform:translateY(-2px); }
.feat-group.highlight { border-color:rgba(0,229,176,0.2); background:linear-gradient(135deg, rgba(0,229,176,0.04), var(--surface)); }
.fg-head { display:flex; align-items:center; gap:9px; margin-bottom:11px; }
.fg-icon { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
.fg-icon.teal  { background:rgba(0,229,176,0.12); color:var(--teal); }
.fg-icon.blue  { background:rgba(59,130,246,0.12); color:#60a5fa; }
.fg-icon.purple{ background:rgba(139,92,246,0.12); color:#a78bfa; }
.fg-icon.orange{ background:rgba(249,115,22,0.12); color:#fb923c; }
.fg-icon.pink  { background:rgba(236,72,153,0.12); color:#f472b6; }
.fg-icon.amber { background:rgba(245,158,11,0.12); color:#fbbf24; }
.fg-title { font-size:12.5px; font-weight:700; color:var(--text); }
.fg-items { display:flex; flex-direction:column; gap:5px; }
.fg-item { display:flex; align-items:center; gap:7px; font-size:11.5px; color:var(--text2); }
.fg-dot  { width:4px; height:4px; border-radius:50%; background:var(--muted); flex-shrink:0; }
.fg-group.highlight .fg-dot { background:var(--teal); }

/* Stats strip */
.stats-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:0; border:1px solid var(--border); border-radius:var(--r2); overflow:hidden; }
.stat-item { padding:14px 10px; text-align:center; border-right:1px solid var(--border); }
.stat-item:last-child { border-right:none; }
.stat-num   { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; color:var(--teal); line-height:1; }
.stat-label { font-size:10px; color:var(--muted); margin-top:3px; letter-spacing:.3px; }

/* ── RIGHT: LOGIN CARD ── */
.login-card { background:var(--surface); border:1px solid var(--border); border-radius:20px; overflow:hidden; position:sticky; top:32px; }
.login-card-top { background:linear-gradient(135deg, rgba(0,229,176,0.08), rgba(0,168,204,0.04)); border-bottom:1px solid var(--border); padding:24px 28px 20px; }
.lct-title { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; letter-spacing:-.5px; margin-bottom:4px; }
.lct-sub   { font-size:12.5px; color:var(--text2); }
.lct-dots  { display:flex; gap:6px; margin-bottom:16px; }
.lct-dot   { width:8px; height:8px; border-radius:50%; }

.login-card-body { padding:24px 28px 28px; }

/* Alerts */
.alert { display:flex; align-items:flex-start; gap:10px; padding:11px 14px; border-radius:10px; font-size:12.5px; margin-bottom:16px; animation:fadeSlide .3s ease; }
@keyframes fadeSlide { from{opacity:0;transform:translateY(-5px)} to{opacity:1;transform:none} }
.alert i { margin-top:1px; flex-shrink:0; font-size:13px; }
.alert-danger { background:rgba(255,77,109,0.09); border:1px solid rgba(255,77,109,0.22); color:#ff8099; }
.alert-info   { background:rgba(0,229,176,0.07); border:1px solid rgba(0,229,176,0.18); color:var(--teal); }
.alert-warn   { background:rgba(245,158,11,0.09); border:1px solid rgba(245,158,11,0.22); color:#fbbf24; }

/* Lockout */
.lockout-box { text-align:center; padding:32px 20px; }
.lc-icon  { font-size:44px; color:var(--danger); margin-bottom:14px; animation:blink 2s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.4} }
.lc-title { font-family:'Syne',sans-serif; font-size:18px; font-weight:800; margin-bottom:8px; }
.lc-timer { font-family:'Syne',sans-serif; font-size:46px; font-weight:800; color:var(--teal); letter-spacing:-2px; margin:6px 0; }
.lc-sub   { font-size:12px; color:var(--muted); }

/* Attempts */
.attempts-row { display:flex; justify-content:space-between; align-items:center; font-size:11px; color:var(--muted); margin-bottom:5px; }
.attempts-row strong { color:var(--text); }
.att-bar { display:flex; gap:3px; margin-bottom:16px; }
.att-seg { flex:1; height:3px; border-radius:99px; background:var(--border); transition:background .3s; }
.att-seg.warn { background:var(--warn); }
.att-seg.used { background:var(--danger); }

/* Form fields */
.form-field { margin-bottom:14px; }
.form-field label { display:block; font-size:11px; font-weight:700; color:var(--muted); letter-spacing:.6px; text-transform:uppercase; margin-bottom:6px; }
.input-wrap { position:relative; }
.input-wrap .i-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:13px; pointer-events:none; transition:color .2s; }
.input-wrap input { width:100%; height:44px; background:var(--card); border:1px solid var(--border); border-radius:var(--r); color:var(--text); font-family:inherit; font-size:13.5px; padding:0 40px 0 40px; outline:none; transition:border-color .2s, box-shadow .2s; }
.input-wrap input:focus { border-color:var(--teal); box-shadow:0 0 0 3px var(--teal-dim); }
.input-wrap input:focus ~ .i-icon, .input-wrap input:focus + .i-icon { color:var(--teal); }
.input-wrap input::placeholder { color:var(--muted); font-size:12.5px; }
.i-icon-l { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:13px; pointer-events:none; transition:color .2s; z-index:1; }
.input-wrap:focus-within .i-icon-l { color:var(--teal); }
.eye-btn { position:absolute; right:11px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--muted); cursor:pointer; padding:4px; font-size:13px; transition:color .2s; }
.eye-btn:hover { color:var(--teal); }

/* Captcha */
.captcha-wrap { background:var(--card); border:1px solid var(--border); border-radius:var(--r); padding:13px 14px; margin-bottom:14px; }
.captcha-lbl { font-size:10.5px; font-weight:700; color:var(--muted); letter-spacing:.5px; text-transform:uppercase; margin-bottom:9px; display:flex; align-items:center; gap:6px; }
.captcha-lbl i { color:var(--teal); }
.captcha-inner { display:flex; align-items:center; gap:10px; }
.captcha-num { font-family:'Syne',sans-serif; font-size:20px; font-weight:800; color:var(--teal); background:var(--teal-dim); border:1px solid rgba(0,229,176,0.18); padding:7px 16px; border-radius:8px; letter-spacing:2px; white-space:nowrap; }
.captcha-eq { font-size:18px; color:var(--muted); font-weight:700; }
.captcha-ans { width:72px; height:40px; background:var(--bg); border:1px solid var(--border); border-radius:8px; color:var(--text); font-family:'Syne',sans-serif; font-size:17px; font-weight:700; text-align:center; outline:none; transition:border-color .2s, box-shadow .2s; }
.captcha-ans:focus { border-color:var(--teal); box-shadow:0 0 0 2px var(--teal-dim); }

/* Remember + forgot */
.rem-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
.rem-lbl  { display:flex; align-items:center; gap:7px; font-size:12.5px; color:var(--text2); cursor:pointer; }
.rem-lbl input[type=checkbox] { accent-color:var(--teal); width:13px; height:13px; }
.forgot-link { font-size:12.5px; color:var(--teal); text-decoration:none; }
.forgot-link:hover { text-decoration:underline; }

/* Submit */
.btn-login { width:100%; height:46px; background:linear-gradient(135deg,var(--teal),var(--teal2)); border:none; border-radius:var(--r); color:#07121e; font-family:'Syne',sans-serif; font-size:14px; font-weight:800; letter-spacing:.3px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:9px; transition:transform .15s, box-shadow .15s; box-shadow:0 4px 20px var(--teal-glow); position:relative; overflow:hidden; }
.btn-login::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg,rgba(255,255,255,0.12),transparent); }
.btn-login:hover  { transform:translateY(-2px); box-shadow:0 8px 28px var(--teal-glow); }
.btn-login:active { transform:none; }
.btn-login:disabled { opacity:.5; cursor:not-allowed; transform:none; }

/* Divider */
.divider { display:flex; align-items:center; gap:10px; margin:18px 0 14px; }
.divider::before,.divider::after { content:''; flex:1; height:1px; background:var(--border); }
.divider span { font-size:11px; color:var(--muted); white-space:nowrap; }

.register-row { text-align:center; font-size:12.5px; color:var(--muted); }
.register-row a { color:var(--teal); font-weight:600; text-decoration:none; }
.register-row a:hover { text-decoration:underline; }

/* Security badge inside card */
.sec-badge { display:flex; align-items:center; gap:8px; background:rgba(0,229,176,0.05); border:1px solid rgba(0,229,176,0.12); border-radius:8px; padding:8px 12px; margin-bottom:16px; }
.sec-badge i { color:var(--teal); font-size:12px; flex-shrink:0; }
.sec-badge span { font-size:11px; color:var(--text2); line-height:1.5; }

/* Footer */
.page-footer { width:100%; max-width:1100px; margin-top:28px; display:flex; align-items:center; justify-content:space-between; }
.footer-copy { font-size:11px; color:var(--muted); }
.footer-copy a { color:var(--teal); text-decoration:none; }
.footer-links { display:flex; gap:16px; }
.footer-links a { font-size:11px; color:var(--muted); text-decoration:none; transition:color .2s; }
.footer-links a:hover { color:var(--teal); }

/* Responsive */
@media (max-width:900px) {
  .main-grid { grid-template-columns:1fr; }
  .features-col { display:none; }
  .login-card { position:static; max-width:440px; margin:0 auto; width:100%; }
  .topbar { margin-bottom:28px; }
  .topbar-badge { display:none; }
}
@media (max-width:480px) {
  .login-card-top, .login-card-body { padding:18px 20px; }
  .feat-groups { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<div class="bg-wrap">
  <div class="bg-grid"></div>
  <div class="bg-glow1"></div>
  <div class="bg-glow2"></div>
  <div class="bg-glow3"></div>
</div>

<div class="page">

  <!-- ══ TOP BAR ══ -->
  <div class="topbar">
    <div class="brand">
      <div class="brand-logo"><i class="fa fa-desktop"></i></div>
      <div>
        <div class="brand-name">Fix<em>Smart</em></div>
        <div class="brand-sub">Management Work System</div>
      </div>
    </div>
    <div class="topbar-badge">
      <i class="fa fa-circle-dot"></i>
      Platform Terpadu Aktif
    </div>
  </div>

  <!-- ══ MAIN GRID ══ -->
  <div class="main-grid">

    <!-- ─── LEFT: FEATURES ─── -->
    <div class="features-col">
      <div class="features-headline">
        <h1>
          <span class="line1">Satu Platform.</span>
          <span class="line2">Semua Terkendali.</span>
        </h1>
        <p>Sistem manajemen kerja terpadu untuk pengelolaan IT, IPSRS, aset, dokumen akreditasi, cuti, dan absensi dalam satu dashboard terintegrasi.</p>
      </div>

      <!-- Feature Groups -->
      <div class="feat-groups">

        <div class="feat-group highlight">
          <div class="fg-head">
            <div class="fg-icon teal"><i class="fa fa-desktop"></i></div>
            <div class="fg-title">Management IT</div>
          </div>
          <div class="fg-items">
            <div class="fg-item"><div class="fg-dot"></div>Order &amp; Tracking Tiket IT</div>
            <div class="fg-item"><div class="fg-dot"></div>Manajemen Aset IT</div>
            <div class="fg-item"><div class="fg-dot"></div>Lacak &amp; Mutasi Aset</div>
            <div class="fg-item"><div class="fg-dot"></div>Laporan SLA &amp; Analytics</div>
          </div>
        </div>

        <div class="feat-group">
          <div class="fg-head">
            <div class="fg-icon orange"><i class="fa fa-toolbox"></i></div>
            <div class="fg-title">Management IPSRS</div>
          </div>
          <div class="fg-items">
            <div class="fg-item"><div class="fg-dot"></div>Order Tiket IPSRS</div>
            <div class="fg-item"><div class="fg-dot"></div>Manajemen Aset IPSRS</div>
            <div class="fg-item"><div class="fg-dot"></div>Maintenance &amp; Jadwal</div>
            <div class="fg-item"><div class="fg-dot"></div>Laporan &amp; SLA</div>
          </div>
        </div>

        <div class="feat-group">
          <div class="fg-head">
            <div class="fg-icon purple"><i class="fa fa-medal"></i></div>
            <div class="fg-title">Dokumen Akreditasi</div>
          </div>
          <div class="fg-items">
            <div class="fg-item"><div class="fg-dot"></div>Manajemen Pokja</div>
            <div class="fg-item"><div class="fg-dot"></div>Upload Dokumen</div>
            <div class="fg-item"><div class="fg-dot"></div>Monitoring Expired</div>
          </div>
        </div>

        <div class="feat-group">
          <div class="fg-head">
            <div class="fg-icon blue"><i class="fa fa-calendar-minus"></i></div>
            <div class="fg-title">Management Cuti</div>
          </div>
          <div class="fg-items">
            <div class="fg-item"><div class="fg-dot"></div>Pengajuan Multi-Tanggal</div>
            <div class="fg-item"><div class="fg-dot"></div>Approval 2 Level</div>
            <div class="fg-item"><div class="fg-dot"></div>Rekap &amp; Laporan PDF</div>
          </div>
        </div>

        <div class="feat-group">
          <div class="fg-head">
            <div class="fg-icon pink"><i class="fa fa-fingerprint"></i></div>
            <div class="fg-title">Absensi Karyawan</div>
          </div>
          <div class="fg-items">
            <div class="fg-item"><div class="fg-dot"></div>Absen via Kamera</div>
            <div class="fg-item"><div class="fg-dot"></div>Jadwal &amp; Shift</div>
            <div class="fg-item"><div class="fg-dot"></div>Rekap Kehadiran</div>
          </div>
        </div>

        <div class="feat-group">
          <div class="fg-head">
            <div class="fg-icon amber"><i class="fa fa-users"></i></div>
            <div class="fg-title">SDM &amp; Management</div>
          </div>
          <div class="fg-items">
            <div class="fg-item"><div class="fg-dot"></div>Master Karyawan</div>
            <div class="fg-item"><div class="fg-dot"></div>Multi Role &amp; Divisi</div>
            <div class="fg-item"><div class="fg-dot"></div>Notifikasi Telegram</div>
          </div>
        </div>

      </div>

    

    </div><!-- /features-col -->

    <!-- ─── RIGHT: LOGIN CARD ─── -->
    <div class="login-card">

      <!-- Card Top -->
      <div class="login-card-top">
        <div class="lct-dots">
          <div class="lct-dot" style="background:#ff4d6d;"></div>
          <div class="lct-dot" style="background:#f59e0b;"></div>
          <div class="lct-dot" style="background:#00e5b0;"></div>
        </div>
        <div class="lct-title">Selamat Datang</div>
        <div class="lct-sub">Masuk ke akun FixSmart Anda untuk melanjutkan</div>
      </div>

      <!-- Card Body -->
      <div class="login-card-body">

        <?php if($error): ?>
        <div class="alert alert-danger"><i class="fa fa-circle-exclamation"></i><span><?= $error ?></span></div>
        <?php endif; ?>
        <?php if($msg): ?>
        <div class="alert alert-info"><i class="fa fa-circle-info"></i><span><?= htmlspecialchars($msg) ?></span></div>
        <?php endif; ?>

        <?php if($is_locked): ?>
        <!-- LOCKED STATE -->
        <div class="lockout-box">
          <div class="lc-icon"><i class="fa fa-lock"></i></div>
          <div class="lc-title">Akun Sementara Dikunci</div>
          <div class="lc-timer" id="countdown"><?= gmdate('i:s', remainingSeconds()) ?></div>
          <div class="lc-sub">Terlalu banyak percobaan gagal.<br>Silakan tunggu sebelum mencoba kembali.</div>
        </div>

        <?php else: ?>

        <!-- Attempts indicator -->
        <?php if($attempts_data['count'] > 0): ?>
        <div class="attempts-row">
          <span><i class="fa fa-triangle-exclamation" style="color:var(--warn);margin-right:4px;font-size:10px;"></i>Percobaan gagal: <strong><?= $attempts_data['count'] ?>/<?= MAX_ATTEMPTS ?></strong></span>
          <span>Sisa: <strong style="color:<?= $sisa_coba<=2?'var(--danger)':'var(--success)' ?>;"><?= $sisa_coba ?>x</strong></span>
        </div>
        <div class="att-bar">
          <?php for($i=0;$i<MAX_ATTEMPTS;$i++): $cls=$i<$attempts_data['count']?($sisa_coba<=2?'used':'warn'):''; ?>
          <div class="att-seg <?= $cls ?>"></div>
          <?php endfor; ?>
        </div>
        <?php endif; ?>

        <!-- Security note -->
        <div class="sec-badge">
          <i class="fa fa-shield-halved"></i>
          <span>Sesi terenkripsi &bull; Data Anda aman &bull; Akses berbasis peran</span>
        </div>

        <!-- FORM -->
        <form method="POST" autocomplete="off" id="login-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

          <div class="form-field">
            <label>Alamat Email</label>
            <div class="input-wrap">
              <i class="fa fa-envelope i-icon-l"></i>
              <input type="email" name="email"
                     placeholder="nama@email.com"
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                     autofocus autocomplete="email">
            </div>
          </div>

          <div class="form-field">
            <label>Password</label>
            <div class="input-wrap">
              <i class="fa fa-lock i-icon-l"></i>
              <input type="password" name="password" id="pwd"
                     placeholder="Masukkan password Anda"
                     autocomplete="current-password">
              <button type="button" class="eye-btn" onclick="togglePwd()" tabindex="-1">
                <i class="fa fa-eye" id="eye-ic"></i>
              </button>
            </div>
          </div>

          <!-- Captcha -->
          <div class="captcha-wrap">
            <div class="captcha-lbl"><i class="fa fa-robot"></i> Verifikasi Anti-Bot</div>
            <div class="captcha-inner">
              <div class="captcha-num"><?= htmlspecialchars($captcha_soal) ?></div>
              <div class="captcha-eq">=</div>
              <input type="number" name="captcha" class="captcha-ans"
                     placeholder="?" required autocomplete="off" min="0" max="99">
              <span style="font-size:11px;color:var(--muted);margin-left:4px;">?</span>
            </div>
          </div>

          <div class="rem-row">
            <label class="rem-lbl"><input type="checkbox" name="remember"> Ingat saya</label>
            <a href="#" class="forgot-link">Lupa password?</a>
          </div>

          <button type="submit" class="btn-login" id="btn-submit">
            <i class="fa fa-right-to-bracket"></i>
            Masuk ke Dashboard
          </button>
        </form>

        <?php endif; ?>

        <div class="divider"><span>belum punya akun?</span></div>
        <div class="register-row">
          Hubungi admin atau <a href="<?= APP_URL ?>/register.php">daftar di sini &rarr;</a>
        </div>

      </div><!-- /login-card-body -->
    </div><!-- /login-card -->

  </div><!-- /main-grid -->

 
</div><!-- /page -->

<script>
function togglePwd() {
    var p = document.getElementById('pwd'), i = document.getElementById('eye-ic');
    if (p.type === 'password') { p.type = 'text'; i.className = 'fa fa-eye-slash'; }
    else { p.type = 'password'; i.className = 'fa fa-eye'; }
}

// Submit loading state
document.getElementById('login-form')?.addEventListener('submit', function() {
    var btn = document.getElementById('btn-submit');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
    }
});

<?php if($is_locked): ?>
var sisa = <?= remainingSeconds() ?>, el = document.getElementById('countdown');
var tmr = setInterval(function() {
    sisa--;
    if (sisa <= 0) { clearInterval(tmr); location.reload(); return; }
    var m = Math.floor(sisa / 60), s = sisa % 60;
    el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
}, 1000);
<?php endif; ?>
</script>

</body>
</html>