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

// ── CSRF Token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token_reg'])) {
    $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
}

// ── Captcha ───────────────────────────────────────────────────────────────────
function generateCaptchaReg() {
    $ops = ['+', '-', '+', '+', '-'];
    $op  = $ops[array_rand($ops)];
    if ($op === '+') { $a = rand(1,9); $b = rand(1,9); $ans = $a + $b; }
    else             { $a = rand(3,9); $b = rand(1,$a); $ans = $a - $b; }
    $_SESSION['reg_captcha_answer'] = $ans;
    return "$a $op $b";
}
if (empty($_SESSION['reg_captcha_answer'])) {
    $captcha_soal = generateCaptchaReg();
} else {
    $captcha_soal = $_SESSION['reg_captcha_soal'] ?? generateCaptchaReg();
}
$_SESSION['reg_captcha_soal'] = $captcha_soal;

// ── Proses POST ───────────────────────────────────────────────────────────────
$errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (!hash_equals($_SESSION['csrf_token_reg'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';

    // Captcha
    } elseif ((int)($_POST['captcha'] ?? -999) !== (int)($_SESSION['reg_captcha_answer'] ?? -1)) {
        $errors[] = 'Jawaban captcha salah. Silakan coba lagi.';
        $captcha_soal = generateCaptchaReg();
        $_SESSION['reg_captcha_soal'] = $captcha_soal;

    } else {
        $nama   = trim($_POST['nama']     ?? '');
        $uname  = trim($_POST['username'] ?? '');
        $email  = trim($_POST['email']    ?? '');
        $divisi = $_POST['divisi']        ?? '';
        $no_hp  = trim($_POST['no_hp']    ?? '');
        $pass   = $_POST['password']      ?? '';
        $cnf    = $_POST['confirm']       ?? '';

        if (!$nama)                                    $errors[] = 'Nama lengkap wajib diisi.';
        if (!$uname || strlen($uname) < 3)             $errors[] = 'Username minimal 3 karakter.';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $uname)) $errors[] = 'Username hanya huruf, angka, underscore.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
        if (strlen($pass) < 6)                         $errors[] = 'Password minimal 6 karakter.';
        if ($pass !== $cnf)                            $errors[] = 'Konfirmasi password tidak cocok.';

        if (empty($errors)) {
            $st = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
            $st->execute([$uname, $email]);
            if ($st->fetch()) {
                $errors[] = 'Username atau email sudah digunakan.';
            } else {
                $pdo->prepare("INSERT INTO users (nama,username,email,password,divisi,no_hp) VALUES (?,?,?,?,?,?)")
                    ->execute([$nama, $uname, $email, password_hash($pass, PASSWORD_BCRYPT), $divisi, $no_hp]);
                $success = true;
                // Reset captcha & CSRF setelah sukses
                unset($_SESSION['reg_captcha_answer'], $_SESSION['reg_captcha_soal']);
                $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
            }
        }
    }

    // Regenerasi CSRF & captcha jika ada error
    if (!empty($errors)) {
        $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
        $captcha_soal = generateCaptchaReg();
        $_SESSION['reg_captcha_soal'] = $captcha_soal;
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daftar Akun — <?= APP_NAME ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Source Sans Pro',sans-serif;font-size:13px;background:#2a3f54;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
body::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(45deg,rgba(255,255,255,.015) 0,rgba(255,255,255,.015) 1px,transparent 1px,transparent 28px);}
.wrap{width:100%;max-width:900px;display:flex;border-radius:6px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.4);position:relative;z-index:1;}

/* ── LEFT (identik login) ── */
.left{flex:1;background:linear-gradient(150deg,#1a2e3f,#2a3f54);padding:36px 28px;display:flex;flex-direction:column;color:#fff;}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
.brand-icon{width:46px;height:46px;background:#26B99A;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
.brand-name{font-size:18px;font-weight:700;}
.brand-sub{font-size:10px;color:rgba(255,255,255,.5);margin-top:2px;}
.left h2{font-size:15px;font-weight:700;margin-bottom:6px;}
.left > p{color:rgba(255,255,255,.55);font-size:12px;line-height:1.7;margin-bottom:14px;}
.modul-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:14px;}
.modul-card{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:9px 10px;display:flex;align-items:flex-start;gap:8px;}
.modul-ic{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
.modul-ic.c-it    {background:rgba(59,130,246,.35);color:#93c5fd;}
.modul-ic.c-ipsrs {background:rgba(16,185,129,.35);color:#6ee7b7;}
.modul-ic.c-maint {background:rgba(245,158,11,.35);color:#fcd34d;}
.modul-ic.c-aset  {background:rgba(168,85,247,.35);color:#d8b4fe;}
.modul-title{font-size:11px;font-weight:700;color:#fff;margin-bottom:2px;}
.modul-desc{font-size:10px;color:rgba(255,255,255,.5);line-height:1.5;}
.feat{list-style:none;margin-bottom:14px;}
.feat li{display:flex;align-items:center;gap:9px;font-size:11.5px;color:rgba(255,255,255,.7);margin-bottom:7px;}
.feat li i{color:#26B99A;width:14px;flex-shrink:0;}
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
.right{flex:0 0 420px;background:#fff;padding:28px 26px;display:flex;flex-direction:column;justify-content:center;overflow-y:auto;max-height:100vh;}
.right h3{font-size:16px;font-weight:700;color:#333;margin-bottom:3px;}
.right .sub{font-size:12px;color:#aaa;margin-bottom:14px;}
.alert{padding:9px 12px;border-radius:3px;font-size:12px;margin-bottom:12px;display:flex;align-items:flex-start;gap:7px;line-height:1.5;}
.alert i{margin-top:1px;flex-shrink:0;}
.alert-danger {background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;}
.alert ul{padding-left:14px;margin-top:4px;}
.alert ul li{margin-bottom:2px;}
.sec-title{font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;display:flex;align-items:center;gap:5px;}
.hr{border:none;border-top:1px solid #f0f0f0;margin:11px 0;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.fg{margin-bottom:10px;}
.fg label{display:block;font-size:11.5px;font-weight:700;color:#555;margin-bottom:3px;}
.req{color:#e74c3c;}
.iw{position:relative;}
.iw i.ic{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#ccc;font-size:11px;}
.iw input,.iw select{width:100%;padding:7px 9px 7px 28px;border:1px solid #ddd;border-radius:3px;font-size:12px;font-family:inherit;outline:none;color:#555;transition:border-color .2s;background:#fff;}
.iw input:focus,.iw select:focus{border-color:#26B99A;box-shadow:0 0 0 2px rgba(38,185,154,.1);}
.eye-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#ccc;font-size:11px;}
/* Password strength */
.str-bar{height:3px;border-radius:2px;background:#eee;margin-top:4px;overflow:hidden;}
.str-fill{height:100%;border-radius:2px;transition:width .3s,background .3s;}
.str-lbl{font-size:10px;color:#aaa;margin-top:2px;}
.match-hint{font-size:10px;margin-top:2px;}
/* Captcha */
.captcha-box{background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:8px 11px;margin-bottom:10px;}
.captcha-label{font-size:11px;font-weight:700;color:#166534;margin-bottom:5px;display:flex;align-items:center;gap:5px;}
.captcha-row{display:flex;align-items:center;gap:8px;}
.captcha-soal{font-size:17px;font-weight:700;color:#1e293b;background:#fff;border:1px solid #ddd;border-radius:4px;padding:4px 10px;letter-spacing:2px;min-width:72px;text-align:center;}
.captcha-eq{font-size:15px;color:#64748b;font-weight:700;}
.captcha-input{flex:1;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-weight:700;text-align:center;font-family:inherit;outline:none;color:#1e293b;}
.captcha-input:focus{border-color:#26B99A;box-shadow:0 0 0 2px rgba(38,185,154,.15);}
.btn-reg{width:100%;padding:9px;background:#26B99A;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:7px;margin-top:4px;}
.btn-reg:hover{background:#1e9980;}
.hr2{border:none;border-top:1px solid #eee;margin:13px 0;}
.login-link{text-align:center;font-size:12px;color:#aaa;}
.login-link a{color:#26B99A;font-weight:700;}

@media(max-width:660px){.left{display:none;}.right{flex:0 0 100%;max-width:420px;}.wrap{max-width:420px;}.form-row{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="wrap">

  <!-- ══ KIRI (identik login) ══ -->
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
    <div class="modul-grid">
      <div class="modul-card">
        <div class="modul-ic c-it"><i class="fa fa-laptop"></i></div>
        <div><div class="modul-title">Order Tiket IT</div><div class="modul-desc">Komputer, jaringan, printer &amp; perangkat IT</div></div>
      </div>
      <div class="modul-card">
        <div class="modul-ic c-ipsrs"><i class="fa fa-toolbox"></i></div>
        <div><div class="modul-title">Order Tiket IPSRS</div><div class="modul-desc">Alat medis, non-medis &amp; sarana prasarana RS</div></div>
      </div>
      <div class="modul-card">
        <div class="modul-ic c-maint"><i class="fa fa-wrench"></i></div>
        <div><div class="modul-title">Maintenance</div><div class="modul-desc">Jadwal &amp; histori pemeliharaan preventif</div></div>
      </div>
      <div class="modul-card">
        <div class="modul-ic c-aset"><i class="fa fa-boxes-stacked"></i></div>
        <div><div class="modul-title">Manajemen Aset</div><div class="modul-desc">Inventaris, kondisi &amp; tracking aset</div></div>
      </div>
    </div>
    <ul class="feat">
      <li><i class="fa fa-check-circle"></i> Pantau status tiket real-time: Menunggu → Diproses → Selesai</li>
      <li><i class="fa fa-check-circle"></i> Notifikasi Telegram otomatis setiap perubahan status</li>
      <li><i class="fa fa-check-circle"></i> Upload foto bukti pengerjaan langsung dari tiket</li>
      <li><i class="fa fa-check-circle"></i> Pengukuran SLA &amp; laporan kinerja teknisi otomatis</li>
      <li><i class="fa fa-check-circle"></i> Riwayat &amp; histori lengkap semua aktivitas per tiket</li>
    </ul>
    <div class="wa-group-box">
      <a href="https://chat.whatsapp.com/JlLw0jaANMG0m1oAu7wYWP?mode=gi_t" target="_blank" class="wa-group-link">
        <i class="fab fa-whatsapp"></i> Gabung Grup WhatsApp FixSmart Helpdesk
      </a>
      <small class="wa-group-desc">Info update, pengumuman &amp; bantuan cepat seputar aplikasi FixSmart.</small>
      <div class="wa-qr-wrap">
        <img src="<?= APP_URL ?>/barcode_grup_wa.png" alt="QR Code Grup WhatsApp FixSmart">
        <div class="wa-qr-info">
          <strong>Scan QR Code</strong>
          Arahkan kamera HP untuk langsung bergabung ke grup WhatsApp FixSmart Helpdesk.
        </div>
      </div>
    </div>
    <div class="left-foot">© <?= date('Y') ?> <?= APP_NAME ?>. M. Wira Sb.S. Kom — 082177846209.</div>
  </div>

  <!-- ══ KANAN: Form Daftar ══ -->
  <div class="right">
    <h3>Buat Akun Baru</h3>
    <p class="sub">Isi data di bawah untuk mendaftar</p>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fa fa-check-circle"></i>
        <span>Akun berhasil dibuat! &nbsp;<a href="<?= APP_URL ?>/login.php" style="color:#065f46;font-weight:700;">Login sekarang →</a></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <i class="fa fa-exclamation-circle"></i>
        <div><strong>Harap perbaiki:</strong><ul><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul></div>
      </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token_reg']) ?>">

      <!-- Data Diri -->
      <p class="sec-title"><i class="fa fa-user"></i> Data Diri</p>
      <div class="form-row">
        <div class="fg">
          <label>Nama Lengkap <span class="req">*</span></label>
          <div class="iw"><i class="fa fa-id-card ic"></i>
            <input type="text" name="nama" placeholder="Nama lengkap..." value="<?= clean($_POST['nama'] ?? '') ?>">
          </div>
        </div>
        <div class="fg">
          <label>Username <span class="req">*</span></label>
          <div class="iw"><i class="fa fa-at ic"></i>
            <input type="text" name="username" placeholder="min. 3 karakter..." value="<?= clean($_POST['username'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="fg">
        <label>Email <span class="req">*</span></label>
        <div class="iw"><i class="fa fa-envelope ic"></i>
          <input type="email" name="email" placeholder="email@perusahaan.com" value="<?= clean($_POST['email'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="fg">
          <label>Divisi / Unit</label>
          <div class="iw"><i class="fa fa-building ic"></i>
            <select name="divisi">
              <option value="">-- Pilih Divisi --</option>
              <?php foreach ($divisi_list as $d): ?>
              <option value="<?= clean($d['nama']) ?>" <?= (($_POST['divisi'] ?? '') === $d['nama']) ? 'selected' : '' ?>>
                <?= clean($d['nama']) ?><?= $d['kode'] ? ' ('.$d['kode'].')' : '' ?><?= $d['lokasi'] ? ' — '.$d['lokasi'] : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="fg">
          <label>No. HP</label>
          <div class="iw"><i class="fa fa-phone ic"></i>
            <input type="text" name="no_hp" placeholder="08xx..." value="<?= clean($_POST['no_hp'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- Keamanan -->
      <hr class="hr">
      <p class="sec-title"><i class="fa fa-lock"></i> Keamanan</p>
      <div class="form-row">
        <div class="fg">
          <label>Password <span class="req">*</span></label>
          <div class="iw">
            <i class="fa fa-lock ic"></i>
            <input type="password" name="password" id="pw1" placeholder="Min. 6 karakter..." oninput="checkStr(this.value)" autocomplete="new-password">
            <button type="button" class="eye-btn" onclick="tog('pw1','e1')"><i class="fa fa-eye" id="e1"></i></button>
          </div>
          <div class="str-bar"><div class="str-fill" id="str-fill"></div></div>
          <div class="str-lbl" id="str-lbl"></div>
        </div>
        <div class="fg">
          <label>Konfirmasi Password <span class="req">*</span></label>
          <div class="iw">
            <i class="fa fa-lock ic"></i>
            <input type="password" name="confirm" id="pw2" placeholder="Ulangi password..." oninput="checkMatch()" autocomplete="new-password">
            <button type="button" class="eye-btn" onclick="tog('pw2','e2')"><i class="fa fa-eye" id="e2"></i></button>
          </div>
          <div class="match-hint" id="match-hint"></div>
        </div>
      </div>

      <!-- Captcha -->
      <div class="captcha-box">
        <div class="captcha-label"><i class="fa fa-robot"></i> Verifikasi: berapa hasilnya?</div>
        <div class="captcha-row">
          <div class="captcha-soal"><?= htmlspecialchars($captcha_soal) ?></div>
          <div class="captcha-eq">=</div>
          <input type="number" name="captcha" class="captcha-input" placeholder="?" required autocomplete="off" min="0" max="99">
        </div>
      </div>

      <button type="submit" class="btn-reg"><i class="fa fa-user-plus"></i> Buat Akun</button>
    </form>
    <?php endif; ?>

    <hr class="hr2">
    <div class="login-link">Sudah punya akun? <a href="<?= APP_URL ?>/login.php">Masuk di sini</a></div>
  </div>

</div>
<script>
function tog(id, ic) {
  var e = document.getElementById(id), i = document.getElementById(ic);
  e.type = e.type === 'password' ? 'text' : 'password';
  i.className = e.type === 'text' ? 'fa fa-eye-slash' : 'fa fa-eye';
}
function checkStr(v) {
  var s = 0;
  if (v.length >= 6)  s++;
  if (v.length >= 10) s++;
  if (/[A-Z]/.test(v)) s++;
  if (/[0-9]/.test(v)) s++;
  if (/[^a-zA-Z0-9]/.test(v)) s++;
  var lvl = [
    {w:'20%',c:'#e74c3c',t:'Sangat Lemah'},
    {w:'40%',c:'#e67e22',t:'Lemah'},
    {w:'60%',c:'#f39c12',t:'Cukup'},
    {w:'80%',c:'#26B99A',t:'Kuat'},
    {w:'100%',c:'#27ae60',t:'Sangat Kuat'}
  ];
  var f = document.getElementById('str-fill'), l = document.getElementById('str-lbl');
  if (!v.length) { f.style.width = '0'; l.textContent = ''; return; }
  var idx = Math.max(0, Math.min(s - 1, 4));
  f.style.width = lvl[idx].w; f.style.background = lvl[idx].c;
  l.textContent = lvl[idx].t; l.style.color = lvl[idx].c;
}
function checkMatch() {
  var p1 = document.getElementById('pw1').value;
  var p2 = document.getElementById('pw2').value;
  var h  = document.getElementById('match-hint');
  if (!p2) { h.textContent = ''; return; }
  if (p1 === p2) { h.textContent = '✅ Password cocok';      h.style.color = '#16a34a'; }
  else           { h.textContent = '❌ Password tidak cocok'; h.style.color = '#ef4444'; }
}
</script>
</body></html>