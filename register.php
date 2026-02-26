<?php
session_start();
require_once 'config.php';
if (isLoggedIn()) redirect(APP_URL . '/dashboard.php');

$errors = []; $success = '';
$divisi_list = getBagianList($pdo); // dari master bagian

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = trim($_POST['nama'] ?? '');
    $uname  = trim($_POST['username'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $divisi = $_POST['divisi'] ?? '';
    $no_hp  = trim($_POST['no_hp'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $cnf    = $_POST['confirm'] ?? '';

    if (!$nama)   $errors[] = 'Nama lengkap wajib diisi.';
    if (!$uname || strlen($uname)<3) $errors[] = 'Username minimal 3 karakter.';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $uname)) $errors[] = 'Username hanya huruf, angka, underscore.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (strlen($pass) < 6) $errors[] = 'Password minimal 6 karakter.';
    if ($pass !== $cnf) $errors[] = 'Konfirmasi password tidak cocok.';

    if (empty($errors)) {
        $st = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $st->execute([$uname, $email]);
        if ($st->fetch()) { $errors[] = 'Username atau email sudah digunakan.'; }
        else {
            $pdo->prepare("INSERT INTO users (nama,username,email,password,divisi,no_hp) VALUES (?,?,?,?,?,?)")
                ->execute([$nama, $uname, $email, password_hash($pass, PASSWORD_BCRYPT), $divisi, $no_hp]);
            $success = 'Akun berhasil dibuat! Silakan login.';
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daftar — <?= APP_NAME ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:13px;background:#2a3f54;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.box{background:#fff;width:100%;max-width:520px;border-radius:6px;box-shadow:0 20px 60px rgba(0,0,0,.4);overflow:hidden;}
.box-hd{background:linear-gradient(135deg,#1a2e3f,#2a3f54);padding:18px 24px;display:flex;align-items:center;gap:11px;}
.box-hd .ic{color:#26B99A;font-size:22px;}
.box-hd h3{color:#fff;font-size:15px;font-weight:700;}
.box-hd p{color:rgba(255,255,255,.5);font-size:11px;margin-top:2px;}
.box-bd{padding:20px 24px;}
.alert{padding:9px 12px;border-radius:3px;font-size:12px;margin-bottom:14px;display:flex;align-items:flex-start;gap:7px;}
.alert-danger{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
.alert ul{padding-left:14px;margin-top:4px;}
.alert ul li{margin-bottom:2px;}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:11px;}
.fg{margin-bottom:12px;}
.fg label{display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:4px;}
.req{color:#e74c3c;}
.iw{position:relative;}
.iw i{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#ccc;font-size:11px;}
.iw input,.iw select,.iw textarea{width:100%;padding:7px 9px 7px 28px;border:1px solid #ddd;border-radius:3px;font-size:12px;font-family:inherit;outline:none;color:#555;transition:border-color .2s;}
.iw input:focus,.iw select:focus{border-color:#26B99A;box-shadow:0 0 0 2px rgba(38,185,154,.1);}
.sec-title{font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;}
.hr{border:none;border-top:1px solid #f0f0f0;margin:13px 0;}
.btn-reg{width:100%;padding:9px;background:#26B99A;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:7px;margin-top:4px;}
.btn-reg:hover{background:#1e9980;}
.login-link{text-align:center;font-size:12px;color:#aaa;margin-top:13px;}
.login-link a{color:#26B99A;font-weight:700;}
.str-bar{height:4px;border-radius:2px;background:#eee;margin-top:4px;overflow:hidden;}
.str-fill{height:100%;border-radius:2px;transition:width .3s,background .3s;}
.str-lbl{font-size:10px;color:#aaa;margin-top:2px;}
.eye-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#ccc;font-size:11px;}
</style>
</head>
<body>
<div class="box">
  <div class="box-hd">
    <i class="fa fa-user-plus ic"></i>
    <div><h3>Buat Akun Baru</h3><p><?= APP_NAME ?> — IT Work Order System</p></div>
  </div>
  <div class="box-bd">
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i>
      <?= clean($success) ?> &nbsp;<a href="<?= APP_URL ?>/login.php" style="color:#065f46;font-weight:700;">Login sekarang →</a>
    </div>
    <?php elseif ($errors): ?>
    <div class="alert alert-danger">
      <i class="fa fa-exclamation-circle"></i>
      <div><strong>Harap perbaiki:</strong><ul><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul></div>
    </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <p class="sec-title"><i class="fa fa-user"></i> Data Diri</p>
      <div class="form-row">
        <div class="fg">
          <label>Nama Lengkap <span class="req">*</span></label>
          <div class="iw"><i class="fa fa-id-card"></i><input type="text" name="nama" value="<?= clean($_POST['nama']??'') ?>" placeholder="Nama lengkap..."></div>
        </div>
        <div class="fg">
          <label>Username <span class="req">*</span></label>
          <div class="iw"><i class="fa fa-at"></i><input type="text" name="username" value="<?= clean($_POST['username']??'') ?>" placeholder="username..."></div>
        </div>
      </div>
      <div class="fg">
        <label>Email <span class="req">*</span></label>
        <div class="iw"><i class="fa fa-envelope"></i><input type="email" name="email" value="<?= clean($_POST['email']??'') ?>" placeholder="email@perusahaan.com"></div>
      </div>
      <div class="form-row">
        <div class="fg">
          <label>Divisi</label>
          <div class="iw"><i class="fa fa-building"></i>
            <select name="divisi">
              <option value="">-- Pilih Divisi --</option>
              <?php foreach ($divisi_list as $d): ?>
              <option value="<?= clean($d['nama']) ?>" <?= ($_POST['divisi']??'')===$d['nama']?'selected':'' ?>>
                <?= clean($d['nama']) ?><?= $d['kode'] ? ' ('.$d['kode'].')' : '' ?>
                <?= $d['lokasi'] ? ' — '.$d['lokasi'] : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="fg">
          <label>No. HP</label>
          <div class="iw"><i class="fa fa-phone"></i><input type="text" name="no_hp" value="<?= clean($_POST['no_hp']??'') ?>" placeholder="08xx..."></div>
        </div>
      </div>
      <hr class="hr">
      <p class="sec-title"><i class="fa fa-lock"></i> Keamanan</p>
      <div class="form-row">
        <div class="fg">
          <label>Password <span class="req">*</span></label>
          <div class="iw" style="position:relative;">
            <i class="fa fa-lock"></i>
            <input type="password" name="password" id="pw1" placeholder="Min. 6 karakter..." oninput="checkStr(this.value)">
            <button type="button" class="eye-btn" onclick="tog('pw1','e1')"><i class="fa fa-eye" id="e1"></i></button>
          </div>
          <div class="str-bar"><div class="str-fill" id="str-fill"></div></div>
          <div class="str-lbl" id="str-lbl"></div>
        </div>
        <div class="fg">
          <label>Konfirmasi Password <span class="req">*</span></label>
          <div class="iw" style="position:relative;">
            <i class="fa fa-lock"></i>
            <input type="password" name="confirm" id="pw2" placeholder="Ulangi password...">
            <button type="button" class="eye-btn" onclick="tog('pw2','e2')"><i class="fa fa-eye" id="e2"></i></button>
          </div>
        </div>
      </div>
      <button type="submit" class="btn-reg"><i class="fa fa-user-plus"></i> Buat Akun</button>
    </form>
    <?php endif; ?>
    <div class="login-link">Sudah punya akun? <a href="<?= APP_URL ?>/login.php">Login di sini</a></div>
  </div>
</div>
<script>
function tog(id,ic){
  const e=document.getElementById(id),i=document.getElementById(ic);
  e.type=e.type==='password'?'text':'password';
  i.className=e.type==='text'?'fa fa-eye-slash':'fa fa-eye';
}
function checkStr(v){
  let s=0;
  if(v.length>=6)s++;if(v.length>=10)s++;
  if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^a-zA-Z0-9]/.test(v))s++;
  const lvl=[{w:'20%',c:'#e74c3c',t:'Sangat Lemah'},{w:'40%',c:'#e67e22',t:'Lemah'},{w:'60%',c:'#f39c12',t:'Cukup'},{w:'80%',c:'#26B99A',t:'Kuat'},{w:'100%',c:'#27ae60',t:'Sangat Kuat'}];
  const f=document.getElementById('str-fill'),l=document.getElementById('str-lbl');
  if(!v.length){f.style.width='0';l.textContent='';return;}
  const i=Math.max(0,Math.min(s-1,4));
  f.style.width=lvl[i].w;f.style.background=lvl[i].c;
  l.textContent=lvl[i].t;l.style.color=lvl[i].c;
}
</script>
</body></html>
