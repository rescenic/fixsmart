<?php
session_start();
require_once 'config.php';
if (isLoggedIn()) redirect(APP_URL . '/dashboard.php');

$error = '';
$msg_map = ['timeout' => 'Sesi Anda berakhir, silakan login kembali.', 'logout' => 'Anda berhasil keluar.'];
$msg = $msg_map[$_GET['msg'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (!$u || !$p) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $st = $pdo->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND status='aktif' LIMIT 1");
        $st->execute([$u, $u]);
        $user = $st->fetch();
        if ($user && password_verify($p, $user['password'])) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_nama']   = $user['nama'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['user_divisi'] = $user['divisi'];
            $_SESSION['last_activity'] = time();
            redirect(APP_URL . '/dashboard.php');
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — <?= APP_NAME ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Source Sans Pro',sans-serif;font-size:13px;background:#2a3f54;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
body::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(45deg,rgba(255,255,255,.015) 0,rgba(255,255,255,.015) 1px,transparent 1px,transparent 28px);}
.wrap{width:100%;max-width:860px;display:flex;border-radius:6px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.4);position:relative;z-index:1;}

/* ── LEFT ── */
.left{flex:1;background:linear-gradient(150deg,#1a2e3f,#2a3f54);padding:36px 28px;display:flex;flex-direction:column;color:#fff;}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
.brand-icon{width:46px;height:46px;background:#26B99A;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
.brand-name{font-size:18px;font-weight:700;}
.brand-sub{font-size:10px;color:rgba(255,255,255,.5);margin-top:2px;}
.left h2{font-size:15px;font-weight:700;margin-bottom:6px;}
.left > p{color:rgba(255,255,255,.55);font-size:12px;line-height:1.7;margin-bottom:14px;}

/* Modul cards */
.modul-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:14px;}
.modul-card{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:9px 10px;display:flex;align-items:flex-start;gap:8px;}
.modul-ic{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
.modul-ic.c-it    {background:rgba(59,130,246,.35);color:#93c5fd;}
.modul-ic.c-ipsrs {background:rgba(16,185,129,.35);color:#6ee7b7;}
.modul-ic.c-maint {background:rgba(245,158,11,.35);color:#fcd34d;}
.modul-ic.c-aset  {background:rgba(168,85,247,.35);color:#d8b4fe;}
.modul-title{font-size:11px;font-weight:700;color:#fff;margin-bottom:2px;}
.modul-desc{font-size:10px;color:rgba(255,255,255,.5);line-height:1.5;}

/* Fitur list */
.feat{list-style:none;margin-bottom:14px;}
.feat li{display:flex;align-items:center;gap:9px;font-size:11.5px;color:rgba(255,255,255,.7);margin-bottom:7px;}
.feat li i{color:#26B99A;width:14px;flex-shrink:0;}

/* WA */
.wa-group-box{margin-top:14px;}
.wa-group-link{display:inline-flex;align-items:center;gap:8px;padding:9px 13px;background:#25D366;color:#fff;border-radius:6px;font-weight:600;text-decoration:none;transition:.2s;font-size:12px;}
.wa-group-link:hover{background:#1ebe5d;color:#fff;}
.wa-group-link i{font-size:16px;}
.wa-group-desc{display:block;margin-top:5px;font-size:11px;color:rgba(255,255,255,.45);}
.wa-qr-wrap{margin-top:10px;display:flex;align-items:center;gap:10px;}
.wa-qr-wrap img{width:100px;height:100px;border-radius:8px;border:2px solid #25D366;object-fit:cover;flex-shrink:0;}
.wa-qr-info{font-size:10.5px;color:rgba(255,255,255,.5);line-height:1.6;}
.wa-qr-info strong{display:block;color:#25D366;font-size:11px;margin-bottom:2px;}

.left-foot{margin-top:auto;padding-top:16px;font-size:11px;color:rgba(255,255,255,.25);}

/* ── RIGHT ── */
.right{flex:0 0 350px;background:#fff;padding:35px 28px;display:flex;flex-direction:column;justify-content:center;}
.right h3{font-size:17px;font-weight:700;color:#333;margin-bottom:4px;}
.right .sub{font-size:12px;color:#aaa;margin-bottom:22px;}
.alert{padding:9px 12px;border-radius:3px;font-size:12px;margin-bottom:15px;display:flex;align-items:center;gap:7px;}
.alert-danger{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
.alert-info  {background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;}
.fg{margin-bottom:13px;}
.fg label{display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:4px;}
.iw{position:relative;}
.iw i.ic{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#ccc;font-size:12px;}
.iw input{width:100%;padding:8px 10px 8px 30px;border:1px solid #ddd;border-radius:3px;font-size:13px;font-family:inherit;outline:none;color:#555;transition:border-color .2s;}
.iw input:focus{border-color:#26B99A;box-shadow:0 0 0 2px rgba(38,185,154,.1);}
.eye{position:absolute;right:9px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#ccc;font-size:12px;}
.rem-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px;font-size:12px;}
.rem-row label{display:flex;align-items:center;gap:5px;cursor:pointer;color:#666;}
.rem-row a{color:#26B99A;}
.btn-login{width:100%;padding:9px;background:#26B99A;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:7px;}
.btn-login:hover{background:#1e9980;}
.hr{border:none;border-top:1px solid #eee;margin:16px 0;}
.reg-link{text-align:center;font-size:12px;color:#aaa;}
.reg-link a{color:#26B99A;font-weight:700;}

@media(max-width:640px){.left{display:none;}.right{flex:0 0 100%;}.wrap{max-width:380px;}}
</style>
</head>
<body>
<div class="wrap">

  <!-- ══ KIRI ══ -->
  <div class="left">
    <div class="brand">
      <div class="brand-icon"><i class="fa fa-desktop"></i></div>
      <div>
        <div class="brand-name"><?= APP_NAME ?></div>
        <div class="brand-sub">Integrated Helpdesk &amp; Asset Management</div>
      </div>
    </div>

    <h2>Platform Layanan Terpadu Rumah Sakit</h2>
    <p>Satu sistem untuk mengelola semua permintaan layanan IT, IPSRS, pemeliharaan, dan aset secara terintegrasi dan real-time.</p>

    <!-- 4 Modul -->
    <div class="modul-grid">
      <div class="modul-card">
        <div class="modul-ic c-it"><i class="fa fa-laptop"></i></div>
        <div>
          <div class="modul-title">Order Tiket IT</div>
          <div class="modul-desc">Komputer, jaringan, printer &amp; perangkat IT lainnya</div>
        </div>
      </div>
      <div class="modul-card">
        <div class="modul-ic c-ipsrs"><i class="fa fa-toolbox"></i></div>
        <div>
          <div class="modul-title">Order Tiket IPSRS</div>
          <div class="modul-desc">Alat medis, non-medis &amp; sarana prasarana RS</div>
        </div>
      </div>
      <div class="modul-card">
        <div class="modul-ic c-maint"><i class="fa fa-wrench"></i></div>
        <div>
          <div class="modul-title">Maintenance</div>
          <div class="modul-desc">Jadwal &amp; histori pemeliharaan preventif berkala</div>
        </div>
      </div>
      <div class="modul-card">
        <div class="modul-ic c-aset"><i class="fa fa-boxes-stacked"></i></div>
        <div>
          <div class="modul-title">Manajemen Aset</div>
          <div class="modul-desc">Inventaris, kondisi &amp; tracking aset IT &amp; IPSRS</div>
        </div>
      </div>
    </div>

    <!-- Fitur unggulan -->
    <ul class="feat">
      <li><i class="fa fa-check-circle"></i> Pantau status tiket real-time: Menunggu → Diproses → Selesai</li>
      <li><i class="fa fa-check-circle"></i> Notifikasi Telegram otomatis setiap perubahan status</li>
      <li><i class="fa fa-check-circle"></i> Upload foto bukti pengerjaan langsung dari tiket</li>
      <li><i class="fa fa-check-circle"></i> Pengukuran SLA &amp; laporan kinerja teknisi otomatis</li>
      <li><i class="fa fa-check-circle"></i> Riwayat &amp; histori lengkap semua aktivitas per tiket</li>
    </ul>

    <!-- WhatsApp Group -->
    <div class="wa-group-box">
      <a href="https://chat.whatsapp.com/JlLw0jaANMG0m1oAu7wYWP?mode=gi_t"
         target="_blank"
         class="wa-group-link">
        <i class="fab fa-whatsapp"></i>
        Gabung Grup WhatsApp FixSmart Helpdesk
      </a>
      <small class="wa-group-desc">
        Info update, pengumuman &amp; bantuan cepat seputar aplikasi FixSmart.
      </small>
      <div class="wa-qr-wrap">
        <img src="<?= APP_URL ?>/barcode_grup_wa.png" alt="QR Code Grup WhatsApp FixSmart">
        <div class="wa-qr-info">
          <strong>Scan QR Code</strong>
          Arahkan kamera HP ke barcode ini untuk langsung bergabung ke grup WhatsApp FixSmart Helpdesk.
        </div>
      </div>
    </div>

    <div class="left-foot">© <?= date('Y') ?> <?= APP_NAME ?>. M. Wira Sb.S. Kom — 082177846209.</div>
  </div>

  <!-- ══ KANAN ══ -->
  <div class="right">
    <h3>Selamat Datang</h3>
    <p class="sub">Masuk untuk mengakses sistem</p>
    <?php if ($error): ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= clean($error) ?></div><?php endif; ?>
    <?php if ($msg):   ?><div class="alert alert-info"><i class="fa fa-info-circle"></i> <?= clean($msg) ?></div><?php endif; ?>
    <form method="POST">
      <div class="fg">
        <label>Username / Email</label>
        <div class="iw"><i class="fa fa-user ic"></i>
          <input type="text" name="username" placeholder="Masukkan username atau email..." value="<?= clean($_POST['username'] ?? '') ?>" autofocus>
        </div>
      </div>
      <div class="fg">
        <label>Password</label>
        <div class="iw"><i class="fa fa-lock ic"></i>
          <input type="password" name="password" id="pwd" placeholder="Masukkan password...">
          <button type="button" class="eye" onclick="togglePwd()"><i class="fa fa-eye" id="eye-ic"></i></button>
        </div>
      </div>
      <div class="rem-row">
        <label><input type="checkbox" name="remember"> Ingat saya</label>
        <a href="#">Lupa password?</a>
      </div>
      <button type="submit" class="btn-login"><i class="fa fa-sign-in-alt"></i> Masuk</button>
    </form>
    <hr class="hr">
    <div class="reg-link">Belum punya akun? <a href="<?= APP_URL ?>/register.php">Daftar di sini</a></div>
  </div>

</div>
<script>
function togglePwd(){
  const p=document.getElementById('pwd'),i=document.getElementById('eye-ic');
  if(p.type==='password'){p.type='text';i.className='fa fa-eye-slash';}
  else{p.type='password';i.className='fa fa-eye';}
}
</script>
</body></html>