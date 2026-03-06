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
    $log_parsed=parseUserAgent($log_ua); $log_input=trim($_POST['username']??'');
    if(isLocked()){
        $sisa=remainingSeconds(); $mnt=ceil($sisa/60);
        $error="Terlalu banyak percobaan gagal. Coba lagi dalam <strong>{$sisa} detik</strong> (~{$mnt} menit).";
        recordLoginLog($pdo,['user_id'=>null,'username_input'=>$log_input,'status'=>'terkunci','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>"Percobaan saat terkunci, sisa {$sisa} detik",'is_new_ip'=>0]);
    }elseif(!hash_equals($_SESSION['csrf_token']??'',$_POST['csrf_token']??'')){
        $error='Permintaan tidak valid. Silakan muat ulang halaman.';
    }elseif((int)($_POST['captcha']??-999)!==(int)($_SESSION['captcha_answer']??-1)){
        $error='Jawaban captcha salah. Silakan coba lagi.'; $captcha_soal=generateCaptcha(); $_SESSION['captcha_soal']=$captcha_soal; recordFail();
        recordLoginLog($pdo,['user_id'=>null,'username_input'=>$log_input,'status'=>'gagal','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>'Captcha salah','is_new_ip'=>0]);
    }else{
        $u=trim($_POST['username']??''); $p=$_POST['password']??'';
        if(!$u||!$p){ $error='Username dan password wajib diisi.'; }
        else{
            $st=$pdo->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND status='aktif' LIMIT 1");
            $st->execute([$u,$u]); $user=$st->fetch();
            if($user&&password_verify($p,$user['password'])){
                $log_new_ip=isNewIPForUser($pdo,(int)$user['id'],$log_ip);
                recordLoginLog($pdo,['user_id'=>$user['id'],'username_input'=>$u,'status'=>'berhasil','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>null,'is_new_ip'=>$log_new_ip?1:0]);
                resetAttempts(); session_regenerate_id(true);
                $_SESSION['user_id']=$user['id']; $_SESSION['user_nama']=$user['nama']; $_SESSION['user_role']=$user['role'];
                $_SESSION['user_divisi']=$user['divisi']; $_SESSION['last_activity']=time(); $_SESSION['login_ip']=$log_ip;
                unset($_SESSION['csrf_token'],$_SESSION['captcha_answer'],$_SESSION['captcha_soal']);
                if($log_new_ip) setFlash('warning','<i class="fa fa-shield-halved"></i> Login dari perangkat/lokasi baru terdeteksi <strong>('.htmlspecialchars($log_ip).')</strong>. Jika bukan Anda, segera hubungi administrator.');
                redirect(APP_URL.'/dashboard.php');
            }else{
                recordFail(); $captcha_soal=generateCaptcha(); $_SESSION['captcha_soal']=$captcha_soal;
                $sisa_coba=remainingAttempts(); $keterangan=$user?'Password salah':'Username/email tidak ditemukan';
                recordLoginLog($pdo,['user_id'=>$user['id']??null,'username_input'=>$u,'status'=>'gagal','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>$keterangan,'is_new_ip'=>0]);
                if(isLocked()){$sisa=remainingSeconds();$error="Akun dikunci selama <strong>".LOCKOUT_SECS." detik</strong> karena terlalu banyak percobaan gagal.";}
                elseif($sisa_coba<=2){$error="Username atau password salah. <strong>Sisa percobaan: {$sisa_coba}x</strong> sebelum dikunci.";}
                else{$error='Username atau password salah.';}
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
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
<?php include 'includes/auth_style.php'; ?>
</head>
<body>
<div class="wrap">

  <?php include 'includes/auth_left.php'; ?>

  <!-- RIGHT: Login Form -->
  <div class="right">
    <h3>Selamat Datang</h3>
    <p class="sub">Masuk untuk mengakses sistem</p>

    <?php if($error): ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i><span><?= $error ?></span></div><?php endif; ?>
    <?php if($msg):   ?><div class="alert alert-info"><i class="fa fa-info-circle"></i><span><?= htmlspecialchars($msg) ?></span></div><?php endif; ?>

    <?php if($is_locked): ?>
    <div class="lockout-box">
      <div class="lc-icon"><i class="fa fa-lock"></i></div>
      <div class="lc-title">Akun Sementara Dikunci</div>
      <div class="lc-timer" id="countdown"><?= gmdate('i:s',remainingSeconds()) ?></div>
      <div class="lc-sub">Terlalu banyak percobaan gagal. Silakan tunggu.</div>
    </div>
    <?php else: ?>

    <?php if($attempts_data['count']>0): ?>
    <div style="margin-bottom:9px;">
      <div style="font-size:11px;color:#64748b;margin-bottom:4px;">
        <i class="fa fa-shield-halved" style="color:#f59e0b;"></i>
        Percobaan gagal: <strong><?= $attempts_data['count'] ?>/<?= MAX_ATTEMPTS ?></strong>
        &mdash; Sisa: <strong style="color:<?= $sisa_coba<=2?'#dc2626':'#16a34a' ?>;"><?= $sisa_coba ?>x</strong>
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
        <label>Username / Email</label>
        <div class="iw"><i class="fa fa-user ic"></i>
          <input type="text" name="username" placeholder="Masukkan username atau email..." value="<?= htmlspecialchars($_POST['username']??'') ?>" autofocus autocomplete="username">
        </div>
      </div>
      <div class="fg">
        <label>Password</label>
        <div class="iw"><i class="fa fa-lock ic"></i>
          <input type="password" name="password" id="pwd" placeholder="Masukkan password..." autocomplete="current-password">
          <button type="button" class="eye" onclick="togglePwd()"><i class="fa fa-eye" id="eye-ic"></i></button>
        </div>
      </div>
      <div class="captcha-box">
        <div class="captcha-label"><i class="fa fa-robot"></i> Verifikasi: berapa hasilnya?</div>
        <div class="captcha-row">
          <div class="captcha-soal"><?= htmlspecialchars($captcha_soal) ?></div>
          <div class="captcha-eq">=</div>
          <input type="number" name="captcha" class="captcha-input" placeholder="?" required autocomplete="off" min="0" max="99">
        </div>
      </div>
      <div class="rem-row">
        <label><input type="checkbox" name="remember"> Ingat saya</label>
        <a href="#">Lupa password?</a>
      </div>
      <button type="submit" class="btn-submit"><i class="fa fa-sign-in-alt"></i> Masuk</button>
    </form>
    <?php endif; ?>

    <hr class="hr">
    <div class="switch-link">Belum punya akun? <a href="<?= APP_URL ?>/register.php">Daftar di sini</a></div>
  </div>

</div>
<script>
function togglePwd(){
  var p=document.getElementById('pwd'),i=document.getElementById('eye-ic');
  if(p.type==='password'){p.type='text';i.className='fa fa-eye-slash';}
  else{p.type='password';i.className='fa fa-eye';}
}
<?php if($is_locked): ?>
var sisa=<?= remainingSeconds() ?>,el=document.getElementById('countdown');
var tmr=setInterval(function(){sisa--;if(sisa<=0){clearInterval(tmr);location.reload();return;}
var m=Math.floor(sisa/60),s=sisa%60;el.textContent=(m<10?'0':'')+m+':'+(s<10?'0':'')+s;},1000);
<?php endif; ?>
</script>
</body>
</html>