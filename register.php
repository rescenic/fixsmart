<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => false,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);
require_once 'config.php';
if (isLoggedIn()) redirect(APP_URL . '/dashboard.php');

$divisi_list = getBagianList($pdo);
if (empty($_SESSION['csrf_token_reg'])) $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));

function generateCaptchaReg() {
    $ops=['+','-','+','+','-']; $op=$ops[array_rand($ops)];
    if($op==='+'){$a=rand(1,9);$b=rand(1,9);$ans=$a+$b;}else{$a=rand(3,9);$b=rand(1,$a);$ans=$a-$b;}
    $_SESSION['reg_captcha_answer']=$ans; return "$a $op $b";
}
if(empty($_SESSION['reg_captcha_answer'])) $captcha_soal=generateCaptchaReg();
else $captcha_soal=$_SESSION['reg_captcha_soal']??generateCaptchaReg();
$_SESSION['reg_captcha_soal']=$captcha_soal;

$errors=[]; $success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($_SESSION['csrf_token_reg']??'',$_POST['csrf_token']??'')){
        $errors[]='Permintaan tidak valid.';
    }elseif((int)($_POST['captcha']??-999)!==(int)($_SESSION['reg_captcha_answer']??-1)){
        $errors[]='Jawaban captcha salah.'; $captcha_soal=generateCaptchaReg(); $_SESSION['reg_captcha_soal']=$captcha_soal;
    }else{
        $nama=trim($_POST['nama']??''); $uname=trim($_POST['username']??''); $email=trim($_POST['email']??'');
        $divisi=$_POST['divisi']??''; $no_hp=trim($_POST['no_hp']??''); $pass=$_POST['password']??''; $cnf=$_POST['confirm']??'';
        if(!$nama) $errors[]='Nama lengkap wajib diisi.';
        if(!$uname||strlen($uname)<3) $errors[]='Username minimal 3 karakter.';
        if(!preg_match('/^[a-zA-Z0-9_]+$/',$uname)) $errors[]='Username hanya huruf, angka, underscore.';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Format email tidak valid.';
        if(strlen($pass)<6) $errors[]='Password minimal 6 karakter.';
        if($pass!==$cnf) $errors[]='Konfirmasi password tidak cocok.';
        if(empty($errors)){
            $st=$pdo->prepare("SELECT id FROM users WHERE username=? OR email=?"); $st->execute([$uname,$email]);
            if($st->fetch()){ $errors[]='Username atau email sudah digunakan.'; }
            else{
                $pdo->prepare("INSERT INTO users (nama,username,email,password,divisi,no_hp) VALUES (?,?,?,?,?,?)")
                    ->execute([$nama,$uname,$email,password_hash($pass,PASSWORD_BCRYPT),$divisi,$no_hp]);
                $success=true;
                unset($_SESSION['reg_captcha_answer'],$_SESSION['reg_captcha_soal']);
                $_SESSION['csrf_token_reg']=bin2hex(random_bytes(32));
            }
        }
    }
    if(!empty($errors)){
        $_SESSION['csrf_token_reg']=bin2hex(random_bytes(32));
        $captcha_soal=generateCaptchaReg(); $_SESSION['reg_captcha_soal']=$captcha_soal;
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun — <?= APP_NAME ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
<?php include 'includes/auth_style.php'; ?>
</head>
<body>
<div class="wrap">

  <?php include 'includes/auth_left.php'; ?>

  <!-- RIGHT: Register Form -->
  <div class="right">
    <h3>Buat Akun Baru</h3>
    <p class="sub">Isi data di bawah untuk mendaftar</p>

    <?php if($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i><span>Akun berhasil dibuat! &nbsp;<a href="<?= APP_URL ?>/login.php" style="color:#065f46;font-weight:700;">Login sekarang →</a></span></div>
    <?php endif; ?>
    <?php if(!empty($errors)): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i><div><strong>Harap perbaiki:</strong><ul><?php foreach($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>

    <?php if(!$success): ?>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token_reg']) ?>">

      <p class="sec-title"><i class="fa fa-user"></i> Data Diri</p>
      <div class="form-row">
        <div class="fg">
          <label>Nama Lengkap <span class="req">*</span></label>
          <div class="iw"><i class="fa fa-id-card ic"></i>
            <input type="text" name="nama" placeholder="Nama lengkap..." value="<?= clean($_POST['nama']??'') ?>">
          </div>
        </div>
        <div class="fg">
          <label>Username <span class="req">*</span></label>
          <div class="iw"><i class="fa fa-at ic"></i>
            <input type="text" name="username" placeholder="min. 3 karakter..." value="<?= clean($_POST['username']??'') ?>">
          </div>
        </div>
      </div>
      <div class="fg">
        <label>Email <span class="req">*</span></label>
        <div class="iw"><i class="fa fa-envelope ic"></i>
          <input type="email" name="email" placeholder="email@perusahaan.com" value="<?= clean($_POST['email']??'') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="fg">
          <label>Divisi / Unit</label>
          <div class="iw"><i class="fa fa-building ic"></i>
            <select name="divisi">
              <option value="">-- Pilih Divisi --</option>
              <?php foreach($divisi_list as $d): ?>
              <option value="<?= clean($d['nama']) ?>" <?= (($_POST['divisi']??'')===$d['nama'])?'selected':'' ?>>
                <?= clean($d['nama']) ?><?= $d['kode']?' ('.$d['kode'].')':'' ?><?= $d['lokasi']?' — '.$d['lokasi']:'' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="fg">
          <label>No. HP</label>
          <div class="iw"><i class="fa fa-phone ic"></i>
            <input type="text" name="no_hp" placeholder="08xx..." value="<?= clean($_POST['no_hp']??'') ?>">
          </div>
        </div>
      </div>

      <hr class="hr">
      <p class="sec-title"><i class="fa fa-lock"></i> Keamanan</p>
      <div class="form-row">
        <div class="fg">
          <label>Password <span class="req">*</span></label>
          <div class="iw"><i class="fa fa-lock ic"></i>
            <input type="password" name="password" id="pw1" placeholder="Min. 6 karakter..." oninput="checkStr(this.value)" autocomplete="new-password">
            <button type="button" class="eye" onclick="tog('pw1','e1')"><i class="fa fa-eye" id="e1"></i></button>
          </div>
          <div class="str-bar"><div class="str-fill" id="str-fill"></div></div>
          <div class="str-lbl" id="str-lbl"></div>
        </div>
        <div class="fg">
          <label>Konfirmasi Password <span class="req">*</span></label>
          <div class="iw"><i class="fa fa-lock ic"></i>
            <input type="password" name="confirm" id="pw2" placeholder="Ulangi password..." oninput="checkMatch()" autocomplete="new-password">
            <button type="button" class="eye" onclick="tog('pw2','e2')"><i class="fa fa-eye" id="e2"></i></button>
          </div>
          <div class="match-hint" id="match-hint"></div>
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
      <button type="submit" class="btn-submit"><i class="fa fa-user-plus"></i> Buat Akun</button>
    </form>
    <?php endif; ?>

    <hr class="hr">
    <div class="switch-link">Sudah punya akun? <a href="<?= APP_URL ?>/login.php">Masuk di sini</a></div>
  </div>

</div>
<script>
function tog(id,ic){var e=document.getElementById(id),i=document.getElementById(ic);e.type=e.type==='password'?'text':'password';i.className=e.type==='text'?'fa fa-eye-slash':'fa fa-eye';}
function checkStr(v){
  var s=0;if(v.length>=6)s++;if(v.length>=10)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^a-zA-Z0-9]/.test(v))s++;
  var lvl=[{w:'20%',c:'#e74c3c',t:'Sangat Lemah'},{w:'40%',c:'#e67e22',t:'Lemah'},{w:'60%',c:'#f39c12',t:'Cukup'},{w:'80%',c:'#26B99A',t:'Kuat'},{w:'100%',c:'#27ae60',t:'Sangat Kuat'}];
  var f=document.getElementById('str-fill'),l=document.getElementById('str-lbl');
  if(!v.length){f.style.width='0';l.textContent='';return;}
  var idx=Math.max(0,Math.min(s-1,4));f.style.width=lvl[idx].w;f.style.background=lvl[idx].c;l.textContent=lvl[idx].t;l.style.color=lvl[idx].c;
}
function checkMatch(){
  var p1=document.getElementById('pw1').value,p2=document.getElementById('pw2').value,h=document.getElementById('match-hint');
  if(!p2){h.textContent='';return;}
  if(p1===p2){h.textContent='✅ Password cocok';h.style.color='#16a34a';}
  else{h.textContent='❌ Password tidak cocok';h.style.color='#ef4444';}
}
</script>
</body>
</html>