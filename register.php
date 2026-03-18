<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => false,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);
require_once 'config.php';
if (isLoggedIn()) redirect(APP_URL . '/dashboard.php');

// ── Pastikan kolom sdm_karyawan ada (sdm_karyawan dibuat via master_karyawan/profil) ──

// ── AMBIL DATA DROPDOWN ────────────────────────────────────
$divisi_list  = getBagianList($pdo);
$jabatan_list = [];
try {
    $jabatan_list = $pdo->query("SELECT id, nama, kode FROM jabatan WHERE status='aktif' ORDER BY urutan ASC, level ASC, nama ASC")->fetchAll();
} catch (Exception $e) {}

// ── CSRF & CAPTCHA ─────────────────────────────────────────
if (empty($_SESSION['csrf_token_reg'])) {
    $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
}

function generateCaptchaReg() {
    $ops = ['+','-','+','+','-']; $op = $ops[array_rand($ops)];
    if ($op==='+') { $a=rand(1,9); $b=rand(1,9); $ans=$a+$b; }
    else             { $a=rand(3,9); $b=rand(1,$a); $ans=$a-$b; }
    $_SESSION['reg_captcha_answer'] = $ans;
    return "$a $op $b";
}
if (empty($_SESSION['reg_captcha_answer'])) $captcha_soal = generateCaptchaReg();
else $captcha_soal = $_SESSION['reg_captcha_soal'] ?? generateCaptchaReg();
$_SESSION['reg_captcha_soal'] = $captcha_soal;

function generateUsername(PDO $pdo, string $nama): string {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', trim($nama))[0]));
    if (strlen($base) < 3) $base = 'user' . $base;
    $uname = $base; $i = 1;
    while (true) {
        $st = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $st->execute([$uname]);
        if (!$st->fetch()) break;
        $uname = $base . $i++;
    }
    return $uname;
}

// ── POST HANDLER ───────────────────────────────────────────
$errors = []; $success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token_reg'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Permintaan tidak valid.';
    } elseif ((int)($_POST['captcha'] ?? -999) !== (int)($_SESSION['reg_captcha_answer'] ?? -1)) {
        $errors[] = 'Jawaban captcha salah.';
        $captcha_soal = generateCaptchaReg();
        $_SESSION['reg_captcha_soal'] = $captcha_soal;
    } else {
        $nama   = trim($_POST['nama']    ?? '');
        $email  = trim($_POST['email']   ?? '');
        $divisi = $_POST['divisi']       ?? '';
        $no_hp  = trim($_POST['no_hp']   ?? '');
        $nik    = trim($_POST['nik']     ?? '');
        $pass   = $_POST['password']    ?? '';
        $cnf    = $_POST['confirm']     ?? '';
        $jabatan_id = null; // diisi nanti oleh HRD

        if (!$nama)                                     $errors[] = 'Nama lengkap wajib diisi.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
        if (!$nik)                                      $errors[] = 'NIK RS wajib diisi.';
        if (strlen($pass) < 6)                          $errors[] = 'Password minimal 6 karakter.';
        if ($pass !== $cnf)                             $errors[] = 'Konfirmasi password tidak cocok.';

        if (!$errors && $nik !== '') {
            try {
                $ck = $pdo->prepare("SELECT u.nama FROM sdm_karyawan s JOIN users u ON u.id=s.user_id WHERE s.nik_rs=? LIMIT 1");
                $ck->execute([$nik]);
                $who = $ck->fetchColumn();
                if ($who) $errors[] = 'NIK RS <strong>' . htmlspecialchars($nik) . '</strong> sudah digunakan oleh <strong>' . htmlspecialchars($who) . '</strong>.';
            } catch (Exception $e) {}
        }

        if (empty($errors)) {
            $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $st->execute([$email]);
            if ($st->fetch()) {
                $errors[] = 'Email sudah digunakan. Silakan login atau gunakan email lain.';
            } else {
                $uname = generateUsername($pdo, $nama);
                $pdo->prepare("INSERT INTO users (nama,username,email,password,divisi,no_hp) VALUES (?,?,?,?,?,?)")
                    ->execute([$nama, $uname, $email, password_hash($pass, PASSWORD_BCRYPT), $divisi ?: null, $no_hp ?: null]);
                $new_user_id = (int)$pdo->lastInsertId();

                // INSERT sdm_karyawan — data awal dari register
                try {
                    $pdo->prepare("
                        INSERT INTO sdm_karyawan (user_id, nik_rs, no_hp, divisi, jabatan_id, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            nik_rs     = COALESCE(VALUES(nik_rs), nik_rs),
                            no_hp      = COALESCE(VALUES(no_hp), no_hp),
                            divisi     = COALESCE(VALUES(divisi), divisi),
                            jabatan_id = COALESCE(VALUES(jabatan_id), jabatan_id)
                    ")->execute([$new_user_id, $nik ?: null, $no_hp ?: null, $divisi ?: null, $jabatan_id, $new_user_id]);
                } catch (Exception $e) {
                    // sdm_karyawan akan dilengkapi HRD/Admin via Master Karyawan
                }

                $success = true;
                unset($_SESSION['reg_captcha_answer'], $_SESSION['reg_captcha_soal']);
                $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
        $captcha_soal = generateCaptchaReg();
        $_SESSION['reg_captcha_soal'] = $captcha_soal;
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun — <?= APP_NAME ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root {
    --bg:#0a0f14; --panel:#0d1520; --card:#111c2a; --border:#1e2f42;
    --teal:#00e5b0; --teal2:#00c896; --teal-dim:rgba(0,229,176,0.12);
    --teal-glow:rgba(0,229,176,0.25); --text:#e8f0f8; --muted:#5a7a96;
    --danger:#ff4d6d; --success:#10b981; --radius:14px;
  }
  html,body { height:100%; font-family:'Space Grotesk',sans-serif; background:var(--bg); color:var(--text); overflow:hidden; }
  .bg-canvas { position:fixed; inset:0; z-index:0; overflow:hidden; }
  .bg-canvas::before { content:''; position:absolute; inset:0;
    background:radial-gradient(ellipse 80% 60% at 15% 40%,rgba(0,229,176,0.07) 0%,transparent 60%),
               radial-gradient(ellipse 60% 80% at 85% 70%,rgba(0,150,120,0.05) 0%,transparent 55%); }
  .grid-lines { position:absolute; inset:0;
    background-image:linear-gradient(rgba(0,229,176,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,176,0.04) 1px,transparent 1px);
    background-size:60px 60px;
    mask-image:radial-gradient(ellipse 80% 80% at 50% 50%,black 30%,transparent 80%); }
  .orb { position:absolute; border-radius:50%; filter:blur(80px); opacity:0.35; animation:drift 12s ease-in-out infinite alternate; }
  .orb-1 { width:500px; height:500px; background:radial-gradient(circle,#00e5b0 0%,transparent 70%); top:-150px; left:-150px; }
  .orb-2 { width:350px; height:350px; background:radial-gradient(circle,#00a8ff 0%,transparent 70%); bottom:-100px; right:-80px; animation-delay:-6s; }
  @keyframes drift { from{transform:translate(0,0) scale(1)} to{transform:translate(40px,30px) scale(1.08)} }

  .wrap { position:relative; z-index:1; display:flex; height:100vh; width:100vw; overflow:hidden; }

  /* LEFT */
  .left { flex:1; display:flex; flex-direction:column; justify-content:space-between; padding:48px 56px; position:relative; overflow:hidden; border-right:1px solid var(--border); }
  .left::after { content:''; position:absolute; right:0; top:10%; bottom:10%; width:1px; background:linear-gradient(to bottom,transparent,var(--teal),transparent); opacity:0.3; }
  .brand { display:flex; align-items:center; gap:12px; }
  .brand-icon { width:44px; height:44px; border-radius:10px; background:linear-gradient(135deg,var(--teal),#00a8cc); display:flex; align-items:center; justify-content:center; font-size:20px; color:#0a0f14; font-weight:700; box-shadow:0 0 24px var(--teal-glow); }
  .brand-name { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; letter-spacing:-0.5px; }
  .hero { flex:1; display:flex; flex-direction:column; justify-content:center; }
  .hero-tag { display:inline-flex; align-items:center; gap:8px; background:var(--teal-dim); border:1px solid rgba(0,229,176,0.2); color:var(--teal); font-size:11px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; padding:5px 14px; border-radius:99px; width:fit-content; margin-bottom:28px; }
  .hero h1 { font-family:'Syne',sans-serif; font-size:clamp(36px,4vw,56px); font-weight:800; line-height:1.05; letter-spacing:-2px; margin-bottom:20px; }
  .hero h1 .accent  { color:var(--teal); display:block; }
  .hero h1 .outline { -webkit-text-stroke:1.5px rgba(255,255,255,0.15); color:transparent; display:block; }
  .hero p { font-size:14px; color:var(--muted); line-height:1.7; max-width:380px; margin-bottom:36px; }
  .stats { display:flex; border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; width:fit-content; }
  .stat { padding:16px 28px; text-align:center; border-right:1px solid var(--border); }
  .stat:last-child { border-right:none; }
  .stat-num   { font-family:'Syne',sans-serif; font-size:26px; font-weight:800; color:var(--teal); line-height:1; }
  .stat-label { font-size:11px; color:var(--muted); margin-top:4px; }
  .features { display:flex; flex-direction:column; gap:12px; }
  .feat-item { display:flex; align-items:center; gap:12px; font-size:13px; color:var(--muted); }
  .feat-dot  { width:6px; height:6px; border-radius:50%; background:var(--teal); box-shadow:0 0 8px var(--teal); flex-shrink:0; }
  .left-footer { display:flex; align-items:center; justify-content:space-between; }
  .copyright { font-size:11px; color:var(--muted); }
  .copyright a { color:var(--teal); text-decoration:none; }

  /* RIGHT — scrollable */
  .right { width:500px; flex-shrink:0; display:flex; align-items:flex-start; justify-content:center; padding:0; overflow-y:auto; background:var(--panel); }
  .right-inner { width:100%; padding:32px 44px 40px; }

  .form-header { margin-bottom:16px; }
  .form-header h2 { font-family:'Syne',sans-serif; font-size:26px; font-weight:800; letter-spacing:-1px; margin-bottom:4px; }
  .form-header p  { font-size:13px; color:var(--muted); }

  .alert { display:flex; align-items:flex-start; gap:10px; padding:10px 14px; border-radius:10px; font-size:12px; margin-bottom:12px; }
  .alert i { margin-top:1px; flex-shrink:0; font-size:14px; }
  .alert ul { margin:3px 0 0 14px; }
  .alert ul li { margin-bottom:1px; }
  .alert-danger { background:rgba(255,77,109,0.1); border:1px solid rgba(255,77,109,0.25); color:#ff8099; }

  .success-card { text-align:center; padding:48px 24px; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); }
  .success-icon { width:72px; height:72px; border-radius:50%; margin:0 auto 18px; background:rgba(16,185,129,0.12); border:2px solid rgba(16,185,129,0.3); display:flex; align-items:center; justify-content:center; font-size:30px; color:var(--success); animation:pop .4s cubic-bezier(.34,1.56,.64,1); }
  @keyframes pop { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
  .success-card h3 { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; margin-bottom:8px; }
  .success-card p  { font-size:13px; color:var(--muted); margin-bottom:24px; line-height:1.7; }
  .btn-login-now { display:inline-flex; align-items:center; gap:8px; background:linear-gradient(135deg,var(--teal),var(--teal2)); color:#0a0f14; font-family:'Syne',sans-serif; font-size:14px; font-weight:800; padding:12px 28px; border-radius:10px; text-decoration:none; box-shadow:0 4px 20px var(--teal-glow); }

  .sec-title { display:flex; align-items:center; gap:8px; font-size:10px; font-weight:700; color:var(--teal); letter-spacing:1.5px; text-transform:uppercase; margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--border); }
  .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .fg { margin-bottom:10px; }
  .fg label { display:block; font-size:11px; font-weight:600; color:var(--muted); letter-spacing:.5px; text-transform:uppercase; margin-bottom:6px; }
  .fg label .req { color:var(--danger); }
  .iw { position:relative; }
  .iw .ic { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:14px; pointer-events:none; }
  .iw input, .iw select { width:100%; height:46px; background:var(--card); border:1px solid var(--border); border-radius:10px; color:var(--text); font-family:inherit; font-size:14px; padding:0 44px 0 42px; outline:none; transition:border-color .2s,box-shadow .2s; appearance:none; }
  .iw select { padding-right:14px; cursor:pointer; }
  .iw select option { background:var(--card); }
  .iw input:focus, .iw select:focus { border-color:var(--teal); box-shadow:0 0 0 3px var(--teal-dim); }
  .iw input::placeholder { color:var(--muted); font-size:13px; }
  .eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--muted); cursor:pointer; padding:4px; font-size:14px; }
  .eye:hover { color:var(--teal); }
  .str-bar  { height:3px; background:var(--border); border-radius:99px; margin-top:5px; overflow:hidden; }
  .str-fill { height:100%; width:0; border-radius:99px; transition:width .3s,background .3s; }
  .str-lbl  { font-size:11px; margin-top:4px; font-weight:600; }
  .match-hint { font-size:11px; margin-top:4px; font-weight:600; }
  .section-divider { border:none; border-top:1px solid var(--border); margin:12px 0; }
  .field-hint { font-size:10.5px; color:var(--muted); margin-top:4px; display:flex; align-items:center; gap:4px; }
  .field-hint i { color:var(--teal); font-size:10px; }
  .captcha-box { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:12px 16px; margin-bottom:12px; }
  .captcha-label { font-size:11px; font-weight:600; color:var(--muted); letter-spacing:.5px; text-transform:uppercase; margin-bottom:8px; }
  .captcha-row { display:flex; align-items:center; gap:12px; }
  .captcha-soal { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; color:var(--teal); background:var(--teal-dim); border:1px solid rgba(0,229,176,0.2); padding:8px 18px; border-radius:8px; letter-spacing:2px; }
  .captcha-eq   { font-size:20px; color:var(--muted); font-weight:700; }
  .captcha-input { width:80px; height:42px; background:var(--bg); border:1px solid var(--border); border-radius:8px; color:var(--text); font-family:'Syne',sans-serif; font-size:18px; font-weight:700; text-align:center; outline:none; transition:border-color .2s,box-shadow .2s; }
  .captcha-input:focus { border-color:var(--teal); box-shadow:0 0 0 3px var(--teal-dim); }
  .btn-submit { width:100%; height:48px; background:linear-gradient(135deg,var(--teal),var(--teal2)); border:none; border-radius:10px; color:#0a0f14; font-family:'Syne',sans-serif; font-size:15px; font-weight:800; letter-spacing:.5px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; transition:transform .15s,box-shadow .15s; box-shadow:0 4px 20px var(--teal-glow); }
  .btn-submit:hover { transform:translateY(-2px); }
  .divider { display:flex; align-items:center; gap:12px; margin:14px 0 10px; }
  .divider::before, .divider::after { content:''; flex:1; height:1px; background:var(--border); }
  .divider span { font-size:11px; color:var(--muted); white-space:nowrap; }
  .switch-link { text-align:center; font-size:13px; color:var(--muted); }
  .switch-link a { color:var(--teal); font-weight:600; text-decoration:none; }

  /* Info box NIK */
  .nik-info { background:rgba(0,229,176,0.06); border:1px solid rgba(0,229,176,0.2); border-radius:8px; padding:8px 12px; margin-top:8px; font-size:11px; color:#9ca3af; display:flex; gap:8px; }
  .nik-info i { color:var(--teal); flex-shrink:0; margin-top:1px; }
  .nik-info table { border-collapse:collapse; width:100%; }
  .nik-info td { padding:1px 6px 1px 0; vertical-align:top; }
  .nik-info td:first-child { color:var(--teal); font-weight:600; white-space:nowrap; }

  @media (max-width:900px) { .left { display:none; } .right { width:100%; } }
  @media (max-width:480px) { .right-inner { padding:32px 24px; } }
</style>
</head>
<body>
<div class="bg-canvas"><div class="grid-lines"></div><div class="orb orb-1"></div><div class="orb orb-2"></div></div>

<div class="wrap">
  <!-- LEFT -->
  <div class="left">
    <div class="brand">
      <div class="brand-icon"><i class="fa fa-desktop"></i></div>
      <div class="brand-name"><?= defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'FixSmart' ?></div>
    </div>
    <div class="hero">
      <div class="hero-tag"><i class="fa fa-circle-dot"></i> Platform Helpdesk Terpadu</div>
      <h1>Satu Sistem.<span class="accent">Semua</span><span class="outline">Terkendali.</span></h1>
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
      <?php if (defined('WA_GROUP_LINK') && WA_GROUP_LINK): ?>
      <a href="<?= htmlspecialchars(WA_GROUP_LINK) ?>" target="_blank"
         style="display:inline-flex;align-items:center;gap:10px;background:rgba(37,211,102,0.1);border:1px solid rgba(37,211,102,0.3);color:#25d366;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;">
        <i class="fab fa-whatsapp"></i> Gabung Grup WhatsApp
      </a>
      <?php endif; ?>
    </div>
    <div class="left-footer">
      <div class="copyright">&copy; <?= date('Y') ?> <a href="#"><?= defined('APP_OWNER') ? htmlspecialchars(APP_OWNER) : 'FixSmart' ?></a></div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="right">
    <div class="right-inner">
      <?php if ($success): ?>
      <div class="success-card">
        <div class="success-icon"><i class="fa fa-check"></i></div>
        <h3>Akun Berhasil Dibuat!</h3>
        <p>Selamat, akun Anda telah terdaftar.<br>Silakan login menggunakan <strong>email</strong> dan password Anda.</p>
        <a href="<?= APP_URL ?>/login.php" class="btn-login-now"><i class="fa fa-sign-in-alt"></i> Login Sekarang</a>
      </div>
      <?php else: ?>

      <div class="form-header">
        <h2>Buat Akun Baru ✨</h2>
        <p>Isi data di bawah untuk mendaftar ke sistem</p>
      </div>

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <i class="fa fa-exclamation-circle"></i>
        <div><strong>Harap perbaiki:</strong>
          <ul><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token_reg']) ?>">

        <!-- DATA DIRI -->
        <div class="sec-title"><i class="fa fa-user"></i> Data Diri</div>

        <div class="form-row">
          <div class="fg">
            <label>Nama Lengkap <span class="req">*</span></label>
            <div class="iw">
              <i class="fa fa-id-card ic"></i>
              <input type="text" name="nama" placeholder="Nama lengkap..."
                     value="<?= clean($_POST['nama'] ?? '') ?>" autofocus>
            </div>
          </div>
          <div class="fg">
            <label>NIK RS <span class="req">*</span> <span style="font-size:9px;font-weight:400;text-transform:none;letter-spacing:0;">(Nomor Induk Karyawan)</span></label>
            <div class="iw">
              <i class="fa fa-id-badge ic"></i>
              <input type="text" name="nik" placeholder="Nomor internal RS..."
                     value="<?= clean($_POST['nik'] ?? '') ?>" maxlength="30" required>
            </div>
            <div class="field-hint"><i class="fa fa-circle-info"></i> Wajib diisi — bukan NIK KTP 16 digit</div>
          </div>
        </div>

        <!-- Info perbedaan NIK -->
        <div class="nik-info">
          <i class="fa fa-circle-info"></i>
          <div>
            <table>
              <tr><td>NIK RS</td><td>Nomor Induk Karyawan dari rumah sakit (isi di sini)</td></tr>
              <tr><td>NIK KTP</td><td>No. identitas 16 digit — diisi nanti di Data Kepegawaian</td></tr>
            </table>
          </div>
        </div>

        <div class="fg" style="margin-top:10px;">
          <label>Email <span class="req">*</span></label>
          <div class="iw">
            <i class="fa fa-envelope ic"></i>
            <input type="email" name="email" placeholder="email@.com"
                   value="<?= clean($_POST['email'] ?? '') ?>">
          </div>
          <div class="field-hint"><i class="fa fa-circle-info"></i> Email digunakan untuk login</div>
        </div>

        <div class="form-row">
          <div class="fg">
            <label>Divisi / Unit</label>
            <div class="iw">
              <i class="fa fa-building ic"></i>
              <select name="divisi">
                <option value="">-- Pilih Divisi --</option>
                <?php foreach ($divisi_list as $d): ?>
                <option value="<?= clean($d['nama']) ?>" <?= (($_POST['divisi'] ?? '') === $d['nama']) ? 'selected' : '' ?>>
                  <?= clean($d['nama']) ?><?= $d['kode'] ? ' (' . $d['kode'] . ')' : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="fg">
            <label>No. HP / WhatsApp</label>
            <div class="iw">
              <i class="fa fa-phone ic"></i>
              <input type="text" name="no_hp" placeholder="08xx..."
                     value="<?= clean($_POST['no_hp'] ?? '') ?>">
            </div>
          </div>
        </div>

        <hr class="section-divider">

        <!-- KEAMANAN -->
        <div class="sec-title"><i class="fa fa-lock"></i> Keamanan Akun</div>

        <div class="form-row">
          <div class="fg">
            <label>Password <span class="req">*</span></label>
            <div class="iw">
              <i class="fa fa-lock ic"></i>
              <input type="password" name="password" id="pw1" placeholder="Min. 6 karakter..."
                     oninput="checkStr(this.value)" autocomplete="new-password">
              <button type="button" class="eye" onclick="tog('pw1','e1')"><i class="fa fa-eye" id="e1"></i></button>
            </div>
            <div class="str-bar"><div class="str-fill" id="str-fill"></div></div>
            <div class="str-lbl" id="str-lbl"></div>
          </div>
          <div class="fg">
            <label>Konfirmasi Password <span class="req">*</span></label>
            <div class="iw">
              <i class="fa fa-lock ic"></i>
              <input type="password" name="confirm" id="pw2" placeholder="Ulangi password..."
                     oninput="checkMatch()" autocomplete="new-password">
              <button type="button" class="eye" onclick="tog('pw2','e2')"><i class="fa fa-eye" id="e2"></i></button>
            </div>
            <div class="match-hint" id="match-hint"></div>
          </div>
        </div>

        <!-- CAPTCHA -->
        <div class="captcha-box">
          <div class="captcha-label"><i class="fa fa-robot"></i> Verifikasi: berapa hasilnya?</div>
          <div class="captcha-row">
            <div class="captcha-soal"><?= htmlspecialchars($captcha_soal) ?></div>
            <div class="captcha-eq">=</div>
            <input type="number" name="captcha" class="captcha-input" placeholder="?" required autocomplete="off" min="0" max="99">
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <i class="fa fa-user-plus"></i> Buat Akun Sekarang
        </button>
      </form>
      <?php endif; ?>

      <div class="divider"><span>sudah punya akun?</span></div>
      <div class="switch-link"><a href="<?= APP_URL ?>/login.php">← Masuk di sini</a></div>
    </div>
  </div>
</div>

<script>
function tog(id, ic) {
  var e=document.getElementById(id), i=document.getElementById(ic);
  e.type = e.type==='password' ? 'text' : 'password';
  i.className = e.type==='text' ? 'fa fa-eye-slash' : 'fa fa-eye';
}
function checkStr(v) {
  var s=0;
  if(v.length>=6)s++; if(v.length>=10)s++;
  if(/[A-Z]/.test(v))s++; if(/[0-9]/.test(v))s++; if(/[^a-zA-Z0-9]/.test(v))s++;
  var lvl=[
    {w:'20%',c:'#ff4d6d',t:'Sangat Lemah'},{w:'40%',c:'#f97316',t:'Lemah'},
    {w:'60%',c:'#f59e0b',t:'Cukup'},{w:'80%',c:'#00c896',t:'Kuat'},{w:'100%',c:'#00e5b0',t:'Sangat Kuat'}
  ];
  var f=document.getElementById('str-fill'),l=document.getElementById('str-lbl');
  if(!v.length){f.style.width='0';l.textContent='';return;}
  var idx=Math.max(0,Math.min(s-1,4));
  f.style.width=lvl[idx].w; f.style.background=lvl[idx].c;
  l.textContent=lvl[idx].t; l.style.color=lvl[idx].c;
}
function checkMatch() {
  var p1=document.getElementById('pw1').value,
      p2=document.getElementById('pw2').value,
      h=document.getElementById('match-hint');
  if(!p2){h.textContent='';return;}
  if(p1===p2){h.textContent='✅ Password cocok';h.style.color='#00e5b0';}
  else       {h.textContent='❌ Tidak cocok';   h.style.color='#ff4d6d';}
}
</script>
</body>
</html>