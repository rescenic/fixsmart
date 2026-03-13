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
            // Login dengan email saja
            $st=$pdo->prepare("SELECT * FROM users WHERE email=? AND status='aktif' LIMIT 1");
            $st->execute([$u]); $user=$st->fetch();

            if($user&&password_verify($p,$user['password'])){
                $log_new_ip=isNewIPForUser($pdo,(int)$user['id'],$log_ip);
                recordLoginLog($pdo,['user_id'=>$user['id'],'username_input'=>$u,'status'=>'berhasil','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>null,'is_new_ip'=>$log_new_ip?1:0]);
                resetAttempts(); session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_nama']  = $user['nama'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_divisi']= $user['divisi'];
                $_SESSION['last_activity'] = time();
                $_SESSION['login_ip']   = $log_ip;
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
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #0a0f14;
    --panel:    #0d1520;
    --card:     #111c2a;
    --border:   #1e2f42;
    --teal:     #00e5b0;
    --teal2:    #00c896;
    --teal-dim: rgba(0,229,176,0.12);
    --teal-glow:rgba(0,229,176,0.25);
    --text:     #e8f0f8;
    --muted:    #5a7a96;
    --danger:   #ff4d6d;
    --warn:     #f59e0b;
    --success:  #10b981;
    --radius:   14px;
  }

  html, body { height: 100%; font-family: 'Space Grotesk', sans-serif; background: var(--bg); color: var(--text); overflow: hidden; }

  .bg-canvas { position: fixed; inset: 0; z-index: 0; overflow: hidden; }
  .bg-canvas::before {
    content: ''; position: absolute; inset: 0;
    background:
      radial-gradient(ellipse 80% 60% at 15% 40%, rgba(0,229,176,0.07) 0%, transparent 60%),
      radial-gradient(ellipse 60% 80% at 85% 70%, rgba(0,150,120,0.05) 0%, transparent 55%);
  }
  .grid-lines {
    position: absolute; inset: 0;
    background-image:
      linear-gradient(rgba(0,229,176,0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0,229,176,0.04) 1px, transparent 1px);
    background-size: 60px 60px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 80%);
  }
  .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.35; animation: drift 12s ease-in-out infinite alternate; }
  .orb-1 { width: 500px; height: 500px; background: radial-gradient(circle, #00e5b0 0%, transparent 70%); top: -150px; left: -150px; animation-delay: 0s; }
  .orb-2 { width: 350px; height: 350px; background: radial-gradient(circle, #00a8ff 0%, transparent 70%); bottom: -100px; right: -80px; animation-delay: -6s; }
  @keyframes drift { from { transform: translate(0,0) scale(1); } to { transform: translate(40px,30px) scale(1.08); } }

  .wrap { position: relative; z-index: 1; display: flex; height: 100vh; width: 100vw; }

  /* LEFT */
  .left {
    flex: 1; display: flex; flex-direction: column; justify-content: space-between;
    padding: 48px 56px; position: relative; overflow: hidden;
    border-right: 1px solid var(--border);
  }
  .left::after {
    content: ''; position: absolute; right: 0; top: 10%; bottom: 10%;
    width: 1px; background: linear-gradient(to bottom, transparent, var(--teal), transparent); opacity: 0.3;
  }
  .brand { display: flex; align-items: center; gap: 12px; }
  .brand-icon { width: 44px; height: 44px; border-radius: 10px; background: linear-gradient(135deg, var(--teal), #00a8cc); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #0a0f14; font-weight: 700; box-shadow: 0 0 24px var(--teal-glow); }
  .brand-name { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
  .brand-name span { color: var(--teal); }
  .hero { flex: 1; display: flex; flex-direction: column; justify-content: center; }
  .hero-tag { display: inline-flex; align-items: center; gap: 8px; background: var(--teal-dim); border: 1px solid rgba(0,229,176,0.2); color: var(--teal); font-size: 11px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; padding: 5px 14px; border-radius: 99px; width: fit-content; margin-bottom: 28px; }
  .hero-tag i { font-size: 9px; }
  .hero h1 { font-family: 'Syne', sans-serif; font-size: clamp(36px, 4vw, 56px); font-weight: 800; line-height: 1.05; letter-spacing: -2px; margin-bottom: 20px; }
  .hero h1 .accent  { color: var(--teal); display: block; }
  .hero h1 .outline { -webkit-text-stroke: 1.5px rgba(255,255,255,0.15); color: transparent; display: block; }
  .hero p { font-size: 14px; color: var(--muted); line-height: 1.7; max-width: 380px; margin-bottom: 36px; }
  .stats { display: flex; gap: 0; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; width: fit-content; }
  .stat { padding: 16px 28px; text-align: center; border-right: 1px solid var(--border); }
  .stat:last-child { border-right: none; }
  .stat-num   { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; color: var(--teal); line-height: 1; }
  .stat-label { font-size: 11px; color: var(--muted); margin-top: 4px; }
  .features { display: flex; flex-direction: column; gap: 12px; }
  .feat-item { display: flex; align-items: center; gap: 12px; font-size: 13px; color: var(--muted); }
  .feat-dot  { width: 6px; height: 6px; border-radius: 50%; background: var(--teal); box-shadow: 0 0 8px var(--teal); flex-shrink: 0; }
  .wa-btn { display: inline-flex; align-items: center; gap: 10px; background: rgba(37,211,102,0.1); border: 1px solid rgba(37,211,102,0.3); color: #25d366; padding: 11px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all .2s; width: fit-content; }
  .wa-btn:hover { background: rgba(37,211,102,0.18); border-color: rgba(37,211,102,0.5); transform: translateY(-1px); }
  .wa-btn i { font-size: 16px; }
  .left-footer { display: flex; align-items: center; justify-content: space-between; }
  .copyright { font-size: 11px; color: var(--muted); }
  .copyright a { color: var(--teal); text-decoration: none; }

  /* RIGHT */
  .right { width: 480px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; padding: 40px 48px; overflow-y: auto; background: var(--panel); }
  .right-inner { width: 100%; max-width: 380px; }

  .form-header { margin-bottom: 32px; }
  .form-header h2 { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; letter-spacing: -1px; margin-bottom: 6px; }
  .form-header p { font-size: 13px; color: var(--muted); }

  .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; animation: slideIn .3s ease; }
  @keyframes slideIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
  .alert i { margin-top: 1px; flex-shrink: 0; font-size: 14px; }
  .alert-danger { background: rgba(255,77,109,0.1); border: 1px solid rgba(255,77,109,0.25); color: #ff8099; }
  .alert-info   { background: rgba(0,229,176,0.08); border: 1px solid rgba(0,229,176,0.2);  color: var(--teal); }

  /* lockout */
  .lockout-box { text-align: center; padding: 40px 24px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); }
  .lc-icon  { font-size: 48px; color: var(--danger); margin-bottom: 16px; animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.5; } }
  .lc-title { font-family:'Syne',sans-serif; font-size: 20px; font-weight: 800; margin-bottom: 12px; }
  .lc-timer { font-family:'Syne',sans-serif; font-size: 48px; font-weight: 800; color: var(--teal); letter-spacing: -2px; margin: 8px 0; }
  .lc-sub   { font-size: 13px; color: var(--muted); }

  /* attempts */
  .attempts-wrap { margin-bottom: 18px; }
  .attempts-info { display: flex; justify-content: space-between; font-size: 11px; color: var(--muted); margin-bottom: 6px; }
  .attempts-info strong { color: var(--text); }
  .attempts-bar { display: flex; gap: 4px; }
  .att-dot { flex: 1; height: 4px; border-radius: 99px; background: var(--border); transition: background .3s; }
  .att-dot.warn { background: var(--warn); }
  .att-dot.used { background: var(--danger); }

  /* form fields */
  .fg { margin-bottom: 16px; }
  .fg label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); letter-spacing: .5px; text-transform: uppercase; margin-bottom: 7px; }
  .iw { position: relative; }
  .iw .ic { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 14px; pointer-events: none; transition: color .2s; }
  .iw input { width: 100%; height: 46px; background: var(--card); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-family: inherit; font-size: 14px; padding: 0 44px 0 42px; outline: none; transition: border-color .2s, box-shadow .2s; }
  .iw input:focus { border-color: var(--teal); box-shadow: 0 0 0 3px var(--teal-dim); }
  .iw input::placeholder { color: var(--muted); font-size: 13px; }
  .eye { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; padding: 4px; font-size: 14px; transition: color .2s; }
  .eye:hover { color: var(--teal); }

  /* captcha */
  .captcha-box { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; }
  .captcha-label { font-size: 11px; font-weight: 600; color: var(--muted); letter-spacing: .5px; text-transform: uppercase; margin-bottom: 10px; }
  .captcha-label i { color: var(--teal); margin-right: 4px; }
  .captcha-row { display: flex; align-items: center; gap: 12px; }
  .captcha-soal  { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; color: var(--teal); background: var(--teal-dim); border: 1px solid rgba(0,229,176,0.2); padding: 8px 18px; border-radius: 8px; letter-spacing: 2px; }
  .captcha-eq    { font-size: 20px; color: var(--muted); font-weight: 700; }
  .captcha-input { width: 80px; height: 42px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; text-align: center; outline: none; transition: border-color .2s, box-shadow .2s; }
  .captcha-input:focus { border-color: var(--teal); box-shadow: 0 0 0 3px var(--teal-dim); }

  .rem-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .rem-row label { display: flex; align-items: center; gap: 7px; font-size: 13px; color: var(--muted); cursor: pointer; }
  .rem-row label input[type=checkbox] { accent-color: var(--teal); width: 14px; height: 14px; }
  .rem-row a { font-size: 13px; color: var(--teal); text-decoration: none; }
  .rem-row a:hover { text-decoration: underline; }

  .btn-submit { width: 100%; height: 48px; background: linear-gradient(135deg, var(--teal), var(--teal2)); border: none; border-radius: 10px; color: #0a0f14; font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 800; letter-spacing: .5px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: transform .15s, box-shadow .15s; position: relative; overflow: hidden; box-shadow: 0 4px 20px var(--teal-glow); }
  .btn-submit::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent); }
  .btn-submit:hover  { transform: translateY(-2px); box-shadow: 0 8px 28px var(--teal-glow); }
  .btn-submit:active { transform: translateY(0); }

  .divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; }
  .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
  .divider span { font-size: 11px; color: var(--muted); white-space: nowrap; }
  .switch-link { text-align: center; font-size: 13px; color: var(--muted); }
  .switch-link a { color: var(--teal); font-weight: 600; text-decoration: none; }
  .switch-link a:hover { text-decoration: underline; }

  @media (max-width: 900px) { .left { display: none; } .right { width: 100%; } }
  @media (max-width: 480px)  { .right { padding: 32px 24px; } }
</style>
</head>
<body>
<div class="bg-canvas">
  <div class="grid-lines"></div>
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
</div>

<div class="wrap">

  <!-- ══ LEFT PANEL ══ -->
  <div class="left">
    <div class="brand">
      <div class="brand-icon"><i class="fa fa-desktop"></i></div>
      <div class="brand-name"><?= defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'Fix<span>Smart</span>' ?></div>
    </div>

    <div class="hero">
      <div class="hero-tag"><i class="fa fa-circle-dot"></i> Platform Helpdesk Terpadu</div>
      <h1>
        Satu Sistem.
        <span class="accent">Semua</span>
        <span class="outline">Terkendali.</span>
      </h1>
      <p>Platform manajemen layanan IT &amp; IPSRS untuk rumah sakit. Kelola tiket, aset, maintenance, dan SLA dalam satu dashboard terintegrasi.</p>
      <div class="stats">
        <div class="stat"><div class="stat-num">2</div><div class="stat-label">Modul Utama</div></div>
        <div class="stat"><div class="stat-num">8</div><div class="stat-label">Fitur Lengkap</div></div>
        <div class="stat"><div class="stat-num">1</div><div class="stat-label">Platform Terpadu</div></div>
        <div class="stat"><div class="stat-num">24/7</div><div class="stat-label">Akses Kapanpun</div></div>
      </div>
    </div>

    <div>
      <div class="features" style="margin-bottom:20px;">
        <div class="feat-item"><div class="feat-dot"></div>Order &amp; Tracking Tiket IT / IPSRS</div>
        <div class="feat-item"><div class="feat-dot"></div>Manajemen Aset &amp; Maintenance</div>
        <div class="feat-item"><div class="feat-dot"></div>SLA Monitoring &amp; Dashboard Analytics</div>
      </div>
      <?php if(defined('WA_GROUP_LINK') && WA_GROUP_LINK): ?>
      <a href="<?= htmlspecialchars(WA_GROUP_LINK) ?>" target="_blank" class="wa-btn">
        <i class="fab fa-whatsapp"></i> Gabung Grup WhatsApp
      </a>
      <?php endif; ?>
    </div>

    <div class="left-footer">
      <div class="copyright">
        &copy; <?= date('Y') ?>
        <?php if(defined('APP_OWNER')): ?>
          <a href="#"><?= htmlspecialchars(APP_OWNER) ?></a>
        <?php else: ?>
          <a href="#">FixSmart</a>
        <?php endif; ?>
        &mdash; All rights reserved.
      </div>
    </div>
  </div>

  <!-- ══ RIGHT PANEL ══ -->
  <div class="right">
    <div class="right-inner">

      <div class="form-header">
        <h2>Selamat Datang 👋</h2>
        <p>Masuk menggunakan email dan password Anda</p>
      </div>

      <?php if($error): ?>
      <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i><span><?= $error ?></span></div>
      <?php endif; ?>
      <?php if($msg): ?>
      <div class="alert alert-info"><i class="fa fa-info-circle"></i><span><?= htmlspecialchars($msg) ?></span></div>
      <?php endif; ?>

      <?php if($is_locked): ?>
      <!-- LOCKOUT -->
      <div class="lockout-box">
        <div class="lc-icon"><i class="fa fa-lock"></i></div>
        <div class="lc-title">Akun Sementara Dikunci</div>
        <div class="lc-timer" id="countdown"><?= gmdate('i:s', remainingSeconds()) ?></div>
        <div class="lc-sub">Terlalu banyak percobaan gagal. Silakan tunggu.</div>
      </div>

      <?php else: ?>

      <?php if($attempts_data['count'] > 0): ?>
      <div class="attempts-wrap">
        <div class="attempts-info">
          <span><i class="fa fa-shield-halved" style="color:var(--warn);margin-right:5px;"></i>Percobaan gagal: <strong><?= $attempts_data['count'] ?>/<?= MAX_ATTEMPTS ?></strong></span>
          <span>Sisa: <strong style="color:<?= $sisa_coba<=2?'var(--danger)':'var(--success)' ?>;"><?= $sisa_coba ?>x</strong></span>
        </div>
        <div class="attempts-bar">
          <?php for($i=0;$i<MAX_ATTEMPTS;$i++): $cls=$i<$attempts_data['count']?($sisa_coba<=2?'used':'warn'):''; ?>
          <div class="att-dot <?= $cls ?>"></div>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="fg">
          <label>Email</label>
          <div class="iw">
            <i class="fa fa-envelope ic"></i>
            <input type="email" name="email"
                   placeholder="Masukkan email Anda..."
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   autofocus autocomplete="email">
          </div>
        </div>

        <div class="fg">
          <label>Password</label>
          <div class="iw">
            <i class="fa fa-lock ic"></i>
            <input type="password" name="password" id="pwd"
                   placeholder="Masukkan password..."
                   autocomplete="current-password">
            <button type="button" class="eye" onclick="togglePwd()">
              <i class="fa fa-eye" id="eye-ic"></i>
            </button>
          </div>
        </div>

        <div class="captcha-box">
          <div class="captcha-label"><i class="fa fa-robot"></i> Verifikasi: berapa hasilnya?</div>
          <div class="captcha-row">
            <div class="captcha-soal"><?= htmlspecialchars($captcha_soal) ?></div>
            <div class="captcha-eq">=</div>
            <input type="number" name="captcha" class="captcha-input"
                   placeholder="?" required autocomplete="off" min="0" max="99">
          </div>
        </div>

        <div class="rem-row">
          <label><input type="checkbox" name="remember"> Ingat saya</label>
          <a href="#">Lupa password?</a>
        </div>

        <button type="submit" class="btn-submit">
          <i class="fa fa-sign-in-alt"></i> Masuk Sekarang
        </button>
      </form>
      <?php endif; ?>

      <div class="divider"><span>belum punya akun?</span></div>
      <div class="switch-link"><a href="<?= APP_URL ?>/register.php">Daftar di sini &rarr;</a></div>

    </div>
  </div>

</div>

<script>
function togglePwd() {
  var p=document.getElementById('pwd'), i=document.getElementById('eye-ic');
  if(p.type==='password'){ p.type='text'; i.className='fa fa-eye-slash'; }
  else { p.type='password'; i.className='fa fa-eye'; }
}
<?php if($is_locked): ?>
var sisa=<?= remainingSeconds() ?>, el=document.getElementById('countdown');
var tmr=setInterval(function(){
  sisa--;
  if(sisa<=0){ clearInterval(tmr); location.reload(); return; }
  var m=Math.floor(sisa/60), s=sisa%60;
  el.textContent=(m<10?'0':'')+m+':'+(s<10?'0':'')+s;
},1000);
<?php endif; ?>
</script>
</body>
</html>