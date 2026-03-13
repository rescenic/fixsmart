<?php
// pages/profil.php
session_start();
require_once '../config.php';
requireLogin();

$page_title  = 'Profil Saya';
$active_menu = 'profil';
$divs        = getBagianList($pdo);

$st = $pdo->prepare("SELECT * FROM users WHERE id=?");
$st->execute([$_SESSION['user_id']]);
$user = $st->fetch();

// ── Pastikan tabel sdm_karyawan ada ──────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sdm_karyawan` (
      `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `user_id`             INT UNSIGNED NOT NULL UNIQUE,
      `nik_ktp`             VARCHAR(16)  DEFAULT NULL,
      `nik_rs`              VARCHAR(30)  DEFAULT NULL,
      `gelar_depan`         VARCHAR(30)  DEFAULT NULL,
      `gelar_belakang`      VARCHAR(50)  DEFAULT NULL,
      `tempat_lahir`        VARCHAR(100) DEFAULT NULL,
      `tgl_lahir`           DATE         DEFAULT NULL,
      `jenis_kelamin`       ENUM('L','P') DEFAULT NULL,
      `golongan_darah`      VARCHAR(5)   DEFAULT NULL,
      `agama`               VARCHAR(30)  DEFAULT NULL,
      `status_pernikahan`   VARCHAR(30)  DEFAULT NULL,
      `jumlah_anak`         TINYINT UNSIGNED DEFAULT 0,
      `no_hp`               VARCHAR(20)  DEFAULT NULL,
      `no_hp_darurat`       VARCHAR(20)  DEFAULT NULL,
      `kontak_darurat`      VARCHAR(100) DEFAULT NULL,
      `hubungan_darurat`    VARCHAR(50)  DEFAULT NULL,
      `alamat_ktp`          TEXT         DEFAULT NULL,
      `kota_ktp`            VARCHAR(100) DEFAULT NULL,
      `provinsi_ktp`        VARCHAR(100) DEFAULT NULL,
      `kode_pos_ktp`        VARCHAR(10)  DEFAULT NULL,
      `alamat_domisili`     TEXT         DEFAULT NULL,
      `kota_domisili`       VARCHAR(100) DEFAULT NULL,
      `pendidikan_terakhir` VARCHAR(30)  DEFAULT NULL,
      `jurusan`             VARCHAR(150) DEFAULT NULL,
      `universitas`         VARCHAR(150) DEFAULT NULL,
      `tahun_lulus`         YEAR         DEFAULT NULL,
      `jabatan_id`          INT UNSIGNED DEFAULT NULL,
      `jenis_karyawan`      VARCHAR(30)  DEFAULT NULL,
      `jenis_tenaga`        VARCHAR(100) DEFAULT NULL,
      `status_kepegawaian`  VARCHAR(30)  DEFAULT 'Tetap',
      `tgl_masuk`           DATE         DEFAULT NULL,
      `tgl_kontrak_mulai`   DATE         DEFAULT NULL,
      `tgl_kontrak_selesai` DATE         DEFAULT NULL,
      `tgl_pengangkatan`    DATE         DEFAULT NULL,
      `no_bpjs_kes`         VARCHAR(30)  DEFAULT NULL,
      `no_bpjs_tk`          VARCHAR(30)  DEFAULT NULL,
      `no_npwp`             VARCHAR(20)  DEFAULT NULL,
      `no_rekening`         VARCHAR(30)  DEFAULT NULL,
      `bank`                VARCHAR(50)  DEFAULT NULL,
      `atas_nama_rek`       VARCHAR(100) DEFAULT NULL,
      `no_str`              VARCHAR(100) DEFAULT NULL,
      `tgl_terbit_str`      DATE         DEFAULT NULL,
      `tgl_exp_str`         DATE         DEFAULT NULL,
      `no_sip`              VARCHAR(100) DEFAULT NULL,
      `tgl_terbit_sip`      DATE         DEFAULT NULL,
      `tgl_exp_sip`         DATE         DEFAULT NULL,
      `no_sik`              VARCHAR(100) DEFAULT NULL,
      `tgl_exp_sik`         DATE         DEFAULT NULL,
      `spesialisasi`        VARCHAR(150) DEFAULT NULL,
      `kompetensi`          TEXT         DEFAULT NULL,
      `status`              VARCHAR(20)  DEFAULT 'aktif',
      `catatan`             TEXT         DEFAULT NULL,
      `updated_by`          INT UNSIGNED DEFAULT NULL,
      `created_at`          DATETIME     DEFAULT CURRENT_TIMESTAMP,
      `updated_at`          DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Tambah kolom baru jika belum ada (upgrade DB lama)
foreach ([
    "ALTER TABLE sdm_karyawan ADD COLUMN IF NOT EXISTS `kota_domisili` VARCHAR(100) DEFAULT NULL AFTER `alamat_domisili`",
    "ALTER TABLE sdm_karyawan ADD COLUMN IF NOT EXISTS `tgl_terbit_str` DATE DEFAULT NULL AFTER `no_str`",
    "ALTER TABLE sdm_karyawan ADD COLUMN IF NOT EXISTS `tgl_terbit_sip` DATE DEFAULT NULL AFTER `no_sip`",
] as $sql) { try { $pdo->exec($sql); } catch (Exception $e) {} }

// ── Ambil data SDM milik user ini ────────────────────────
$sdmSt = $pdo->prepare("SELECT * FROM sdm_karyawan WHERE user_id=? LIMIT 1");
$sdmSt->execute([$_SESSION['user_id']]);
$sdm = $sdmSt->fetch(PDO::FETCH_ASSOC) ?: [];

// ── OPTIONS ──────────────────────────────────────────────
$opt_agama      = ['Islam','Kristen Protestan','Kristen Katolik','Hindu','Buddha','Konghucu'];
$opt_gol_darah  = ['A','B','AB','O','A+','A-','B+','B-','AB+','AB-','O+','O-'];
$opt_status_nik = ['Belum Menikah','Menikah','Cerai Hidup','Cerai Mati'];
$opt_pendidikan = ['SD','SMP','SMA/SMK','D1','D3','D4','S1','S2','S3','Profesi','Spesialis','Sub-Spesialis'];
$opt_jenis_kary = ['Medis','Non-Medis','Penunjang Medis'];
$opt_status_kep = ['Tetap','Kontrak','Honorer','Magang','PPPK','Outsourcing'];
$opt_bank       = ['BRI','BNI','BCA','Mandiri','BTN','CIMB Niaga','Danamon','Permata','Syariah Indonesia','Lainnya'];

$jabatan_list = [];
try { $jabatan_list = $pdo->query("SELECT id,nama,kode FROM jabatan WHERE status='aktif' ORDER BY level,urutan,nama")->fetchAll(); } catch (Exception $e) {}

// Helper: tampilkan nilai SDM dari $sdm array
function sdmVal(array $sdm, string $key, $default = ''): string {
    $v = $sdm[$key] ?? $default;
    return htmlspecialchars((string)($v ?? $default));
}
function sdmSel(array $sdm, string $key, string $val): string {
    return (isset($sdm[$key]) && (string)$sdm[$key] === $val) ? 'selected' : '';
}
function sdmDate(array $sdm, string $key): string {
    $v = $sdm[$key] ?? '';
    if ($v && $v !== '0000-00-00') return date('Y-m-d', strtotime($v));
    return '';
}

// ── POST HANDLER ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ── Update profil dasar ──
    if ($act === 'profil') {
        $n  = trim($_POST['nama']  ?? '');
        $e  = trim($_POST['email'] ?? '');
        $d  = $_POST['divisi']     ?? '';
        $hp = trim($_POST['no_hp'] ?? '');
        if ($n && $e) {
            $pdo->prepare("UPDATE users SET nama=?,email=?,divisi=?,no_hp=? WHERE id=?")
                ->execute([$n, $e, $d, $hp, $_SESSION['user_id']]);
            $_SESSION['user_nama'] = $n;
            setFlash('success', 'Profil berhasil diperbarui.');
        } else {
            setFlash('danger', 'Nama dan email wajib diisi.');
        }
    }

    // ── Ganti password ──
    if ($act === 'password') {
        $old = $_POST['old'] ?? '';
        $new = $_POST['new'] ?? '';
        $cnf = $_POST['cnf'] ?? '';
        if (!password_verify($old, $user['password']))
            setFlash('danger', 'Password lama salah.');
        elseif (strlen($new) < 6)
            setFlash('danger', 'Password baru minimal 6 karakter.');
        elseif ($new !== $cnf)
            setFlash('danger', 'Konfirmasi password tidak cocok.');
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_BCRYPT), $_SESSION['user_id']]);
            setFlash('success', 'Password berhasil diubah.');
        }
    }

    // ── Simpan data SDM mandiri ──
    if ($act === 'simpan_sdm') {
        $uid = (int)$_SESSION['user_id'];

        $fields_text  = ['nik_ktp','nik_rs','gelar_depan','gelar_belakang','tempat_lahir','kota_ktp',
                         'provinsi_ktp','kode_pos_ktp','jurusan','universitas','jenis_tenaga',
                         'no_bpjs_kes','no_bpjs_tk','no_npwp','no_rekening','bank','atas_nama_rek',
                         'no_str','no_sip','no_sik','spesialisasi','no_hp','no_hp_darurat',
                         'kontak_darurat','hubungan_darurat','kota_domisili'];
        $fields_text2 = ['alamat_ktp','alamat_domisili','kompetensi','catatan'];
        $fields_enum  = ['jenis_kelamin','golongan_darah','agama','status_pernikahan',
                         'pendidikan_terakhir','jenis_karyawan','status_kepegawaian'];
        $fields_date  = ['tgl_lahir','tgl_masuk','tgl_kontrak_mulai','tgl_kontrak_selesai',
                         'tgl_pengangkatan','tgl_terbit_str','tgl_exp_str',
                         'tgl_terbit_sip','tgl_exp_sip','tgl_exp_sik'];
        $fields_int   = ['jabatan_id','jumlah_anak','tahun_lulus'];

        $d = [];
        foreach ($fields_text  as $f) $d[$f] = trim($_POST[$f] ?? '') ?: null;
        foreach ($fields_text2 as $f) $d[$f] = trim($_POST[$f] ?? '') ?: null;
        foreach ($fields_enum  as $f) { $d[$f] = $_POST[$f] ?? null; if (!$d[$f]) $d[$f] = null; }
        foreach ($fields_date  as $f) $d[$f] = ($_POST[$f] ?? '') ?: null;
        foreach ($fields_int   as $f) $d[$f] = (int)($_POST[$f] ?? 0) ?: null;
        $d['updated_by'] = $uid;

        // Cek sudah ada record?
        $ex = $pdo->prepare("SELECT id FROM sdm_karyawan WHERE user_id=?");
        $ex->execute([$uid]);
        if ($ex->fetchColumn()) {
            $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($d)));
            $vals = array_values($d);
            $vals[] = $uid;
            $pdo->prepare("UPDATE sdm_karyawan SET $sets WHERE user_id=?")->execute($vals);
        } else {
            $d['user_id'] = $uid;
            $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($d)));
            $phs  = implode(',', array_fill(0, count($d), '?'));
            $pdo->prepare("INSERT INTO sdm_karyawan ($cols) VALUES ($phs)")->execute(array_values($d));
        }

        // Sync no_hp ke tabel users
        if ($d['no_hp']) {
            $pdo->prepare("UPDATE users SET no_hp=? WHERE id=?")->execute([$d['no_hp'], $uid]);
        }

        setFlash('success', 'Data kepegawaian berhasil disimpan.');
    }

    redirect(APP_URL . '/pages/profil.php');
}

// Reload user & SDM setelah redirect
$st->execute([$_SESSION['user_id']]);
$user = $st->fetch();
$sdmSt->execute([$_SESSION['user_id']]);
$sdm = $sdmSt->fetch(PDO::FETCH_ASSOC) ?: [];

// Statistik tiket
$my = $pdo->prepare("SELECT status, COUNT(*) n FROM tiket WHERE user_id=? GROUP BY status");
$my->execute([$_SESSION['user_id']]);
$ms = [];
foreach ($my->fetchAll() as $r) $ms[$r['status']] = $r['n'];

// Kelengkapan SDM (%)
$sdm_fields_check = ['nik_rs','no_hp','tgl_lahir','tgl_masuk','jenis_karyawan','status_kepegawaian','no_bpjs_kes'];
$sdm_filled = count(array_filter($sdm_fields_check, fn($f) => !empty($sdm[$f])));
$sdm_pct    = (int)round($sdm_filled / count($sdm_fields_check) * 100);

include '../includes/header.php';
?>

<style>
/* ═══════════════════════════════════════
   PROFIL PAGE — EXTRA STYLES
═══════════════════════════════════════ */
.prf-wrap { display:grid; grid-template-columns:260px 1fr; gap:18px; align-items:start; }

/* Kartu kiri */
.prf-card {
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:14px;
  overflow:hidden;
  position:sticky;
  top:20px;
}
.prf-card-top {
  background:linear-gradient(135deg,#0a0f14 0%,#1a2535 100%);
  padding:28px 20px 20px;
  text-align:center;
  position:relative;
}
.prf-card-top::after {
  content:'';
  position:absolute;
  bottom:-1px; left:0; right:0;
  height:24px;
  background:#fff;
  border-radius:24px 24px 0 0;
}
.prf-av {
  width:72px; height:72px;
  border-radius:50%;
  background:linear-gradient(135deg,#00e5b0,#00c896);
  color:#0a0f14;
  font-size:24px; font-weight:800;
  display:flex; align-items:center; justify-content:center;
  margin:0 auto 12px;
  border:3px solid rgba(255,255,255,.15);
  box-shadow:0 8px 24px rgba(0,200,150,.3);
}
.prf-nama {
  font-size:15px; font-weight:700;
  color:#fff; line-height:1.3;
  margin-bottom:4px;
  position:relative;z-index:1;
}
.prf-email { font-size:11.5px; color:#9ca3af; position:relative;z-index:1; }
.prf-role-badge {
  display:inline-flex;align-items:center;gap:5px;
  font-size:11px; font-weight:700;
  padding:3px 10px; border-radius:20px;
  margin-top:8px;
  position:relative;z-index:1;
}
.prf-card-info { padding:16px 18px; }
.prf-info-row {
  display:flex; align-items:center; gap:8px;
  padding:7px 0;
  border-bottom:1px solid #f3f4f6;
  font-size:12px;
}
.prf-info-row:last-child { border-bottom:none; }
.prf-info-row .prf-info-icon {
  width:28px; height:28px;
  border-radius:7px;
  background:#f0fdf4;
  color:#00c896;
  display:flex; align-items:center; justify-content:center;
  font-size:11px; flex-shrink:0;
}
.prf-info-lbl { font-size:10px; color:#9ca3af; line-height:1; }
.prf-info-val { font-size:12px; font-weight:600; color:#111827; margin-top:1px; }

/* Progress kelengkapan */
.prf-progress-wrap { padding:14px 18px; border-top:1px solid #f3f4f6; }
.prf-progress-label { display:flex; justify-content:space-between; font-size:11px; margin-bottom:6px; }
.prf-progress-label span:first-child { color:#6b7280; font-weight:600; }
.prf-progress-label span:last-child  { color:#00c896; font-weight:700; }
.prf-progress-bar {
  height:6px; border-radius:99px;
  background:#e5e7eb;
  overflow:hidden;
}
.prf-progress-fill {
  height:100%; border-radius:99px;
  background:linear-gradient(90deg,#00e5b0,#00c896);
  transition:width .6s ease;
}

/* Statistik tiket */
.prf-stat-grid {
  display:grid; grid-template-columns:1fr 1fr;
  gap:8px;
  padding:14px 18px;
  border-top:1px solid #f3f4f6;
}
.prf-stat-item {
  background:#f9fafb; border-radius:8px;
  padding:10px 12px; text-align:center;
}
.prf-stat-num { font-size:20px; font-weight:800; line-height:1; }
.prf-stat-lbl { font-size:10px; color:#9ca3af; font-weight:500; margin-top:3px; }

/* Panel kanan — tabs */
.prf-panel {
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:14px;
  overflow:hidden;
}
.prf-tabs {
  display:flex;
  border-bottom:2px solid #e5e7eb;
  background:#fafafa;
  overflow-x:auto;
  flex-shrink:0;
}
.prf-tab {
  padding:12px 18px;
  font-size:12px; font-weight:600;
  color:#6b7280;
  border:none; background:none;
  cursor:pointer;
  white-space:nowrap;
  border-bottom:2px solid transparent;
  margin-bottom:-2px;
  transition:all .15s;
  display:flex; align-items:center; gap:6px;
}
.prf-tab.active { color:#00c896; border-bottom-color:#00c896; }
.prf-tab:hover:not(.active) { color:#374151; background:#f3f4f6; }
.prf-tab-pane { display:none; padding:22px 24px; }
.prf-tab-pane.active { display:block; }

/* Form styling konsisten */
.prf-sec {
  font-size:10px; font-weight:700;
  color:#00c896; letter-spacing:1.2px;
  text-transform:uppercase;
  margin:18px 0 10px;
  padding-bottom:6px;
  border-bottom:1px solid #e5e7eb;
  display:flex; align-items:center; gap:6px;
}
.prf-sec:first-child { margin-top:0; }
.prf-g2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.prf-g3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.prf-g4 { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
.prf-fg { display:flex; flex-direction:column; gap:4px; margin-bottom:10px; }
.prf-fg label { font-size:11px; font-weight:600; color:#374151; }
.prf-fg label .opt { font-size:9.5px; color:#9ca3af; font-weight:400; }
.prf-fg .req { color:#ef4444; }
.prf-fg input,
.prf-fg select,
.prf-fg textarea {
  border:1px solid #d1d5db;
  border-radius:8px;
  padding:0 11px;
  font-size:12.5px;
  font-family:inherit;
  background:#f9fafb;
  color:#111827;
  transition:border-color .15s, background .15s, box-shadow .15s;
  box-sizing:border-box;
  width:100%;
}
.prf-fg input, .prf-fg select { height:36px; }
.prf-fg textarea { padding:8px 11px; resize:vertical; min-height:60px; }
.prf-fg input:focus,
.prf-fg select:focus,
.prf-fg textarea:focus {
  outline:none;
  border-color:#00c896;
  background:#fff;
  box-shadow:0 0 0 3px rgba(0,200,150,.1);
}

/* Info box SDM */
.prf-info-box {
  background:linear-gradient(135deg,#f0fdf4,#ecfdf5);
  border:1px solid #bbf7d0;
  border-radius:10px;
  padding:12px 16px;
  margin-bottom:18px;
  font-size:12px;
  color:#065f46;
  display:flex; align-items:flex-start; gap:10px;
}
.prf-info-box i { font-size:14px; margin-top:1px; flex-shrink:0; }

/* Readonly field */
.prf-fg input[readonly] {
  background:#f3f4f6;
  color:#9ca3af;
  cursor:not-allowed;
}

/* Save button row */
.prf-save-row {
  display:flex; align-items:center; justify-content:flex-end;
  gap:10px;
  padding:16px 24px;
  border-top:1px solid #e5e7eb;
  background:#fafafa;
}

@media(max-width:900px) {
  .prf-wrap { grid-template-columns:1fr; }
  .prf-card { position:static; }
  .prf-g2,.prf-g3,.prf-g4 { grid-template-columns:1fr 1fr; }
}
@media(max-width:560px) {
  .prf-g2,.prf-g3,.prf-g4 { grid-template-columns:1fr; }
  .prf-tab-pane { padding:16px; }
}
</style>

<div class="page-header">
  <h4><i class="fa fa-user-circle text-primary"></i> &nbsp;Profil Saya</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Profil</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <div class="prf-wrap">

    <!-- ══ KOLOM KIRI: Kartu Profil ══ -->
    <div>
      <div class="prf-card">
        <!-- Atas: avatar + nama -->
        <div class="prf-card-top">
          <div class="prf-av"><?= getInitials($user['nama']) ?></div>
          <div class="prf-nama"><?= htmlspecialchars(($sdm['gelar_depan'] ?? '') ? $sdm['gelar_depan'].' '.clean($user['nama']) : clean($user['nama'])) ?><?= !empty($sdm['gelar_belakang']) ? ', '.$sdm['gelar_belakang'] : '' ?></div>
          <div class="prf-email"><?= clean($user['email']) ?></div>
          <?php
            $rs = ['admin'=>['background:#ede9fe;color:#5b21b6;','Admin'],
                   'teknisi'=>['background:#dbeafe;color:#1d4ed8;','Teknisi IT'],
                   'teknisi_ipsrs'=>['background:#ffedd5;color:#c2410c;','Teknisi IPSRS'],
                   'hrd'=>['background:#fce7f3;color:#9d174d;','HRD'],
                   'user'=>['background:#f3f4f6;color:#374151;','User']];
            [$rs_style, $rs_label] = $rs[$user['role']] ?? ['background:#f3f4f6;color:#374151;', ucfirst($user['role'])];
          ?>
          <div class="prf-role-badge" style="<?= $rs_style ?>">
            <i class="fa fa-shield-halved"></i> <?= $rs_label ?>
          </div>
        </div>

        <!-- Info baris -->
        <div class="prf-card-info">
          <?php if(!empty($sdm['nik_rs'])): ?>
          <div class="prf-info-row">
            <div class="prf-info-icon"><i class="fa fa-id-badge"></i></div>
            <div><div class="prf-info-lbl">NIK RS</div><div class="prf-info-val"><?= clean($sdm['nik_rs']) ?></div></div>
          </div>
          <?php endif; ?>
          <?php if(!empty($user['divisi'])): ?>
          <div class="prf-info-row">
            <div class="prf-info-icon"><i class="fa fa-building"></i></div>
            <div><div class="prf-info-lbl">Divisi</div><div class="prf-info-val"><?= clean($user['divisi']) ?></div></div>
          </div>
          <?php endif; ?>
          <?php if(!empty($sdm['jenis_karyawan'])): ?>
          <div class="prf-info-row">
            <div class="prf-info-icon"><i class="fa fa-briefcase"></i></div>
            <div><div class="prf-info-lbl">Jenis Karyawan</div><div class="prf-info-val"><?= clean($sdm['jenis_karyawan']) ?><?= !empty($sdm['jenis_tenaga']) ? ' · '.clean($sdm['jenis_tenaga']) : '' ?></div></div>
          </div>
          <?php endif; ?>
          <?php if(!empty($sdm['status_kepegawaian'])): ?>
          <div class="prf-info-row">
            <div class="prf-info-icon"><i class="fa fa-file-contract"></i></div>
            <div><div class="prf-info-lbl">Status</div><div class="prf-info-val"><?= clean($sdm['status_kepegawaian']) ?></div></div>
          </div>
          <?php endif; ?>
          <?php if(!empty($sdm['tgl_masuk'])): ?>
          <div class="prf-info-row">
            <div class="prf-info-icon"><i class="fa fa-calendar-check"></i></div>
            <div><div class="prf-info-lbl">Tgl Masuk</div><div class="prf-info-val"><?= date('d M Y', strtotime($sdm['tgl_masuk'])) ?></div></div>
          </div>
          <?php endif; ?>
          <?php if(!empty($user['no_hp'])): ?>
          <div class="prf-info-row">
            <div class="prf-info-icon"><i class="fa fa-phone"></i></div>
            <div><div class="prf-info-lbl">No. HP</div><div class="prf-info-val"><?= clean($user['no_hp']) ?></div></div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Progress kelengkapan data SDM -->
        <div class="prf-progress-wrap">
          <div class="prf-progress-label">
            <span>Kelengkapan Data SDM</span>
            <span><?= $sdm_pct ?>%</span>
          </div>
          <div class="prf-progress-bar">
            <div class="prf-progress-fill" style="width:<?= $sdm_pct ?>%;"></div>
          </div>
          <?php if($sdm_pct < 100): ?>
          <div style="font-size:10.5px;color:#9ca3af;margin-top:5px;">Lengkapi data kepegawaian Anda di tab <strong style="color:#00c896;">Data Kepegawaian</strong></div>
          <?php endif; ?>
        </div>

        <!-- Statistik tiket -->
        <div class="prf-stat-grid">
          <?php foreach([
            ['Menunggu', $ms['menunggu']??0, '#f59e0b'],
            ['Diproses',  $ms['diproses'] ??0, '#3b82f6'],
            ['Selesai',   $ms['selesai']  ??0, '#10b981'],
            ['Ditolak',   $ms['ditolak']  ??0, '#ef4444'],
          ] as [$lbl,$val,$clr]): ?>
          <div class="prf-stat-item">
            <div class="prf-stat-num" style="color:<?= $clr ?>;"><?= (int)$val ?></div>
            <div class="prf-stat-lbl"><?= $lbl ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ══ KOLOM KANAN: Tab panel ══ -->
    <div class="prf-panel">

      <!-- Tab header -->
      <div class="prf-tabs">
        <button type="button" class="prf-tab active" data-pane="tp-profil">
          <i class="fa fa-user"></i> Edit Profil
        </button>
        <button type="button" class="prf-tab" data-pane="tp-sdm">
          <i class="fa fa-id-card"></i> Data Kepegawaian
          <?php if($sdm_pct < 50): ?>
          <span style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;padding:1px 5px;border-radius:8px;margin-left:2px;">Belum lengkap</span>
          <?php elseif($sdm_pct >= 100): ?>
          <span style="background:#d1fae5;color:#065f46;font-size:9px;font-weight:700;padding:1px 5px;border-radius:8px;margin-left:2px;">✓ Lengkap</span>
          <?php endif; ?>
        </button>
        <button type="button" class="prf-tab" data-pane="tp-pw">
          <i class="fa fa-lock"></i> Ganti Password
        </button>
      </div>

      <!-- ══ TAB: Edit Profil ══ -->
      <div id="tp-profil" class="prf-tab-pane active">
        <form method="POST">
          <input type="hidden" name="action" value="profil">
          <div class="prf-sec"><i class="fa fa-user"></i> Informasi Akun</div>
          <div class="prf-g2">
            <div class="prf-fg">
              <label>Nama Lengkap <span class="req">*</span></label>
              <input type="text" name="nama" value="<?= clean($user['nama']) ?>" required placeholder="Nama lengkap tanpa gelar">
            </div>
            <div class="prf-fg">
              <label>Email <span class="req">*</span></label>
              <input type="email" name="email" value="<?= clean($user['email']) ?>" required>
            </div>
          </div>
          <div class="prf-g2">
            <div class="prf-fg">
              <label>Divisi / Unit</label>
              <select name="divisi">
                <option value="">— Pilih Divisi —</option>
                <?php foreach($divs as $dv): ?>
                <option value="<?= clean($dv['nama']) ?>" <?= $user['divisi']===$dv['nama']?'selected':'' ?>>
                  <?= clean($dv['nama']) ?><?= $dv['kode'] ? ' ('.$dv['kode'].')' : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="prf-fg">
              <label>No. HP / WhatsApp</label>
              <input type="text" name="no_hp" value="<?= clean($user['no_hp']??'') ?>" placeholder="08xxxxxxxxxx">
            </div>
          </div>
          <div class="prf-fg">
            <label>Role <span class="opt">(tidak dapat diubah)</span></label>
            <input type="text" value="<?= $rs_label ?>" readonly>
          </div>
          <div style="margin-top:4px;">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Profil</button>
          </div>
        </form>
      </div>

      <!-- ══ TAB: Data Kepegawaian (SDM) ══ -->
      <div id="tp-sdm" class="prf-tab-pane">
        <div class="prf-info-box">
          <i class="fa fa-circle-info"></i>
          <div>Data kepegawaian ini akan digunakan oleh HRD/Admin. Pastikan data yang Anda isi sudah benar dan sesuai dokumen resmi. Perubahan akan langsung tersimpan ke sistem.</div>
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="simpan_sdm">

          <!-- SUB-TABS dalam form SDM -->
          <div style="display:flex;border-bottom:1px solid #e5e7eb;margin-bottom:18px;overflow-x:auto;gap:0;">
            <?php foreach([
              ['sdm-t-id','fa-id-card','Identitas'],
              ['sdm-t-kt','fa-location-dot','Kontak & Alamat'],
              ['sdm-t-kp','fa-briefcase','Kepegawaian'],
              ['sdm-t-ku','fa-credit-card','Keuangan'],
              ['sdm-t-ls','fa-stethoscope','Lisensi'],
            ] as $i=>[$pid,$ico,$lbl]): ?>
            <button type="button" class="sdm-subtab <?= $i===0?'active':'' ?>" data-spane="<?= $pid ?>">
              <i class="fa <?= $ico ?>"></i> <?= $lbl ?>
            </button>
            <?php endforeach; ?>
          </div>

          <!-- IDENTITAS -->
          <div class="sdm-subpane active" id="sdm-t-id">
            <div class="prf-sec"><i class="fa fa-id-card"></i> Identitas Pribadi</div>
            <div class="prf-g3">
              <div class="prf-fg">
                <label>NIK RS <span class="opt">(Nomor Induk Karyawan)</span></label>
                <input type="text" name="nik_rs" value="<?= sdmVal($sdm,'nik_rs') ?>" placeholder="Nomor Induk RS">
              </div>
              <div class="prf-fg">
                <label>NIK KTP <span class="opt">(16 digit)</span></label>
                <input type="text" name="nik_ktp" value="<?= sdmVal($sdm,'nik_ktp') ?>" maxlength="16" placeholder="3271xxxxxxxxxxxx">
              </div>
              <div class="prf-fg">
                <label>Jenis Kelamin</label>
                <select name="jenis_kelamin">
                  <option value="">— Pilih —</option>
                  <option value="L" <?= sdmSel($sdm,'jenis_kelamin','L') ?>>Laki-laki</option>
                  <option value="P" <?= sdmSel($sdm,'jenis_kelamin','P') ?>>Perempuan</option>
                </select>
              </div>
            </div>
            <div class="prf-g3">
              <div class="prf-fg">
                <label>Gelar Depan <span class="opt">dr., Ns., dll</span></label>
                <input type="text" name="gelar_depan" value="<?= sdmVal($sdm,'gelar_depan') ?>" placeholder="dr. / Ns. / drg.">
              </div>
              <div class="prf-fg">
                <label>Gelar Belakang <span class="opt">M.Kes, Sp.A</span></label>
                <input type="text" name="gelar_belakang" value="<?= sdmVal($sdm,'gelar_belakang') ?>" placeholder="M.Kes / Sp.A / S.Kep">
              </div>
              <div class="prf-fg">
                <label>Golongan Darah</label>
                <select name="golongan_darah">
                  <option value="">— Pilih —</option>
                  <?php foreach($opt_gol_darah as $g): ?>
                  <option value="<?= $g ?>" <?= sdmSel($sdm,'golongan_darah',$g) ?>><?= $g ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="prf-g3">
              <div class="prf-fg">
                <label>Tempat Lahir</label>
                <input type="text" name="tempat_lahir" value="<?= sdmVal($sdm,'tempat_lahir') ?>" placeholder="Kota tempat lahir">
              </div>
              <div class="prf-fg">
                <label>Tanggal Lahir</label>
                <input type="date" name="tgl_lahir" value="<?= sdmDate($sdm,'tgl_lahir') ?>">
              </div>
              <div class="prf-fg">
                <label>Agama</label>
                <select name="agama">
                  <option value="">— Pilih —</option>
                  <?php foreach($opt_agama as $a): ?>
                  <option value="<?= $a ?>" <?= sdmSel($sdm,'agama',$a) ?>><?= $a ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="prf-g3">
              <div class="prf-fg">
                <label>Status Pernikahan</label>
                <select name="status_pernikahan">
                  <option value="">— Pilih —</option>
                  <?php foreach($opt_status_nik as $sn): ?>
                  <option value="<?= $sn ?>" <?= sdmSel($sdm,'status_pernikahan',$sn) ?>><?= $sn ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="prf-fg">
                <label>Jumlah Anak</label>
                <input type="number" name="jumlah_anak" value="<?= (int)($sdm['jumlah_anak']??0) ?>" min="0" max="20">
              </div>
            </div>

            <div class="prf-sec"><i class="fa fa-graduation-cap"></i> Pendidikan</div>
            <div class="prf-g4">
              <div class="prf-fg">
                <label>Pendidikan Terakhir</label>
                <select name="pendidikan_terakhir">
                  <option value="">— Pilih —</option>
                  <?php foreach($opt_pendidikan as $p): ?>
                  <option value="<?= $p ?>" <?= sdmSel($sdm,'pendidikan_terakhir',$p) ?>><?= $p ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="prf-fg">
                <label>Jurusan / Prodi</label>
                <input type="text" name="jurusan" value="<?= sdmVal($sdm,'jurusan') ?>" placeholder="Keperawatan…">
              </div>
              <div class="prf-fg">
                <label>Universitas</label>
                <input type="text" name="universitas" value="<?= sdmVal($sdm,'universitas') ?>" placeholder="Nama institusi">
              </div>
              <div class="prf-fg">
                <label>Tahun Lulus</label>
                <input type="number" name="tahun_lulus" value="<?= sdmVal($sdm,'tahun_lulus') ?>" placeholder="2019" min="1970" max="2099">
              </div>
            </div>
          </div>

          <!-- KONTAK & ALAMAT -->
          <div class="sdm-subpane" id="sdm-t-kt">
            <div class="prf-sec"><i class="fa fa-phone"></i> Kontak</div>
            <div class="prf-g3">
              <div class="prf-fg">
                <label>No. HP / WhatsApp</label>
                <input type="text" name="no_hp" value="<?= sdmVal($sdm,'no_hp',$user['no_hp']??'') ?>" placeholder="08xxxxxxxxxx">
              </div>
              <div class="prf-fg">
                <label>No. HP Darurat</label>
                <input type="text" name="no_hp_darurat" value="<?= sdmVal($sdm,'no_hp_darurat') ?>" placeholder="08xxxxxxxxxx">
              </div>
              <div class="prf-fg">
                <label>Nama Kontak Darurat</label>
                <input type="text" name="kontak_darurat" value="<?= sdmVal($sdm,'kontak_darurat') ?>" placeholder="Nama keluarga">
              </div>
            </div>
            <div class="prf-g3">
              <div class="prf-fg">
                <label>Hubungan Kontak Darurat</label>
                <input type="text" name="hubungan_darurat" value="<?= sdmVal($sdm,'hubungan_darurat') ?>" placeholder="Istri / Suami / Orang Tua…">
              </div>
            </div>

            <div class="prf-sec"><i class="fa fa-location-dot"></i> Alamat KTP</div>
            <div class="prf-fg">
              <label>Alamat Lengkap</label>
              <textarea name="alamat_ktp" placeholder="Jalan, RT/RW, Kelurahan, Kecamatan…"><?= sdmVal($sdm,'alamat_ktp') ?></textarea>
            </div>
            <div class="prf-g4">
              <div class="prf-fg"><label>Kota / Kabupaten</label><input type="text" name="kota_ktp" value="<?= sdmVal($sdm,'kota_ktp') ?>"></div>
              <div class="prf-fg"><label>Provinsi</label><input type="text" name="provinsi_ktp" value="<?= sdmVal($sdm,'provinsi_ktp') ?>"></div>
              <div class="prf-fg"><label>Kode Pos</label><input type="text" name="kode_pos_ktp" value="<?= sdmVal($sdm,'kode_pos_ktp') ?>" maxlength="10"></div>
            </div>

            <div class="prf-sec"><i class="fa fa-house"></i> Alamat Domisili <span style="font-size:10px;color:#6b7280;font-weight:400;">(kosongkan jika sama KTP)</span></div>
            <div class="prf-fg">
              <label>Alamat Domisili</label>
              <textarea name="alamat_domisili" placeholder="Kosongkan jika sama dengan KTP…"><?= sdmVal($sdm,'alamat_domisili') ?></textarea>
            </div>
            <div class="prf-g2">
              <div class="prf-fg"><label>Kota Domisili</label><input type="text" name="kota_domisili" value="<?= sdmVal($sdm,'kota_domisili') ?>"></div>
            </div>
          </div>

          <!-- KEPEGAWAIAN -->
          <div class="sdm-subpane" id="sdm-t-kp">
            <div class="prf-sec"><i class="fa fa-briefcase"></i> Data Kepegawaian</div>
            <div class="prf-g3">
              <div class="prf-fg">
                <label>Jenis Karyawan</label>
                <select name="jenis_karyawan">
                  <option value="">— Pilih —</option>
                  <?php foreach($opt_jenis_kary as $jk): ?>
                  <option value="<?= $jk ?>" <?= sdmSel($sdm,'jenis_karyawan',$jk) ?>><?= $jk ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="prf-fg">
                <label>Jenis Tenaga / Profesi</label>
                <input type="text" name="jenis_tenaga" value="<?= sdmVal($sdm,'jenis_tenaga') ?>" placeholder="Dokter / Perawat / Bidan…">
              </div>
              <div class="prf-fg">
                <label>Spesialisasi</label>
                <input type="text" name="spesialisasi" value="<?= sdmVal($sdm,'spesialisasi') ?>" placeholder="Sp.A / ICU / IGD / —">
              </div>
            </div>
            <div class="prf-g3">
              <div class="prf-fg">
                <label>Jabatan</label>
                <select name="jabatan_id">
                  <option value="">— Pilih Jabatan —</option>
                  <?php foreach($jabatan_list as $jb): ?>
                  <option value="<?= (int)$jb['id'] ?>" <?= (isset($sdm['jabatan_id']) && (int)$sdm['jabatan_id']===(int)$jb['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($jb['nama']) ?><?= $jb['kode']?' ('.$jb['kode'].')':'' ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="prf-fg">
                <label>Status Kepegawaian</label>
                <select name="status_kepegawaian">
                  <?php foreach($opt_status_kep as $sk): ?>
                  <option value="<?= $sk ?>" <?= sdmSel($sdm,'status_kepegawaian',$sk) ?>><?= $sk ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="prf-g4">
              <div class="prf-fg"><label>Tanggal Masuk</label><input type="date" name="tgl_masuk" value="<?= sdmDate($sdm,'tgl_masuk') ?>"></div>
              <div class="prf-fg"><label>Awal Kontrak</label><input type="date" name="tgl_kontrak_mulai" value="<?= sdmDate($sdm,'tgl_kontrak_mulai') ?>"></div>
              <div class="prf-fg"><label>Akhir Kontrak</label><input type="date" name="tgl_kontrak_selesai" value="<?= sdmDate($sdm,'tgl_kontrak_selesai') ?>"></div>
              <div class="prf-fg"><label>Tgl Pengangkatan Tetap</label><input type="date" name="tgl_pengangkatan" value="<?= sdmDate($sdm,'tgl_pengangkatan') ?>"></div>
            </div>
            <div class="prf-fg">
              <label>Catatan</label>
              <textarea name="catatan" placeholder="Catatan kepegawaian…"><?= sdmVal($sdm,'catatan') ?></textarea>
            </div>
          </div>

          <!-- KEUANGAN -->
          <div class="sdm-subpane" id="sdm-t-ku">
            <div class="prf-sec"><i class="fa fa-shield-halved"></i> BPJS &amp; Pajak</div>
            <div class="prf-g3">
              <div class="prf-fg"><label>No. BPJS Kesehatan</label><input type="text" name="no_bpjs_kes" value="<?= sdmVal($sdm,'no_bpjs_kes') ?>" placeholder="0001xxxxxxxxx"></div>
              <div class="prf-fg"><label>No. BPJS Ketenagakerjaan</label><input type="text" name="no_bpjs_tk" value="<?= sdmVal($sdm,'no_bpjs_tk') ?>" placeholder="Nomor BPJS TK"></div>
              <div class="prf-fg"><label>No. NPWP</label><input type="text" name="no_npwp" value="<?= sdmVal($sdm,'no_npwp') ?>" placeholder="XX.XXX.XXX.X-XXX.XXX"></div>
            </div>

            <div class="prf-sec"><i class="fa fa-building-columns"></i> Rekening Bank</div>
            <div class="prf-g3">
              <div class="prf-fg">
                <label>Bank</label>
                <select name="bank">
                  <option value="">— Pilih Bank —</option>
                  <?php foreach($opt_bank as $b): ?>
                  <option value="<?= $b ?>" <?= sdmSel($sdm,'bank',$b) ?>><?= $b ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="prf-fg"><label>No. Rekening</label><input type="text" name="no_rekening" value="<?= sdmVal($sdm,'no_rekening') ?>" placeholder="Nomor rekening"></div>
              <div class="prf-fg"><label>Atas Nama</label><input type="text" name="atas_nama_rek" value="<?= sdmVal($sdm,'atas_nama_rek') ?>" placeholder="Nama pemilik rekening"></div>
            </div>
          </div>

          <!-- LISENSI -->
          <div class="sdm-subpane" id="sdm-t-ls">
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#065f46;display:flex;align-items:center;gap:8px;">
              <i class="fa fa-circle-info"></i> Untuk karyawan <strong>Non-Medis</strong>, isi dengan <code>-</code> atau kosongkan saja.
            </div>

            <div class="prf-sec"><i class="fa fa-id-badge"></i> STR — Surat Tanda Registrasi</div>
            <div class="prf-g3">
              <div class="prf-fg"><label>Nomor STR</label><input type="text" name="no_str" value="<?= sdmVal($sdm,'no_str') ?>" placeholder="Nomor STR atau -"></div>
              <div class="prf-fg"><label>Tgl Terbit STR</label><input type="date" name="tgl_terbit_str" value="<?= sdmDate($sdm,'tgl_terbit_str') ?>"></div>
              <div class="prf-fg"><label>Exp. STR</label><input type="date" name="tgl_exp_str" value="<?= sdmDate($sdm,'tgl_exp_str') ?>">
                <?php if(!empty($sdm['tgl_exp_str']) && strtotime($sdm['tgl_exp_str']) < strtotime('+30 days')): ?>
                <span style="font-size:10px;color:#dc2626;font-weight:700;margin-top:2px;"><i class="fa fa-triangle-exclamation"></i> Segera expired!</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="prf-sec"><i class="fa fa-file-medical"></i> SIP — Surat Izin Praktik</div>
            <div class="prf-g3">
              <div class="prf-fg"><label>Nomor SIP</label><input type="text" name="no_sip" value="<?= sdmVal($sdm,'no_sip') ?>" placeholder="Nomor SIP atau -"></div>
              <div class="prf-fg"><label>Tgl Terbit SIP</label><input type="date" name="tgl_terbit_sip" value="<?= sdmDate($sdm,'tgl_terbit_sip') ?>"></div>
              <div class="prf-fg"><label>Exp. SIP</label><input type="date" name="tgl_exp_sip" value="<?= sdmDate($sdm,'tgl_exp_sip') ?>">
                <?php if(!empty($sdm['tgl_exp_sip']) && strtotime($sdm['tgl_exp_sip']) < strtotime('+30 days')): ?>
                <span style="font-size:10px;color:#dc2626;font-weight:700;margin-top:2px;"><i class="fa fa-triangle-exclamation"></i> Segera expired!</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="prf-sec"><i class="fa fa-file-signature"></i> SIK — Surat Izin Kerja</div>
            <div class="prf-g3">
              <div class="prf-fg"><label>Nomor SIK</label><input type="text" name="no_sik" value="<?= sdmVal($sdm,'no_sik') ?>" placeholder="Nomor SIK atau -"></div>
              <div class="prf-fg"><label>Exp. SIK</label><input type="date" name="tgl_exp_sik" value="<?= sdmDate($sdm,'tgl_exp_sik') ?>"></div>
            </div>

            <div class="prf-sec"><i class="fa fa-certificate"></i> Kompetensi &amp; Sertifikasi</div>
            <div class="prf-fg">
              <label>Kompetensi / Sertifikasi Tambahan</label>
              <textarea name="kompetensi" placeholder="BLS, ACLS, BTCLS, PONEK, dll. Pisahkan koma. Isi '-' jika tidak ada."><?= sdmVal($sdm,'kompetensi') ?></textarea>
            </div>
          </div>

          <!-- Tombol simpan SDM -->
          <div class="prf-save-row">
            <span style="font-size:11px;color:#9ca3af;"><i class="fa fa-clock"></i> Terakhir diperbarui: <?= !empty($sdm['updated_at']) ? date('d M Y H:i', strtotime($sdm['updated_at'])) : '—' ?></span>
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Data Kepegawaian</button>
          </div>
        </form>
      </div>

      <!-- ══ TAB: Ganti Password ══ -->
      <div id="tp-pw" class="prf-tab-pane">
        <div class="prf-sec"><i class="fa fa-lock"></i> Ubah Password</div>
        <form method="POST" style="max-width:420px;">
          <input type="hidden" name="action" value="password">
          <div class="prf-fg">
            <label>Password Lama <span class="req">*</span></label>
            <input type="password" name="old" required placeholder="Masukkan password saat ini">
          </div>
          <div class="prf-fg">
            <label>Password Baru <span class="req">*</span></label>
            <input type="password" name="new" id="pw-new" required placeholder="Min. 6 karakter">
          </div>
          <div class="prf-fg">
            <label>Konfirmasi Password Baru <span class="req">*</span></label>
            <input type="password" name="cnf" id="pw-cnf" required placeholder="Ulangi password baru">
          </div>
          <div id="pw-match-msg" style="font-size:11.5px;margin-bottom:10px;display:none;"></div>
          <button type="submit" class="btn btn-primary"><i class="fa fa-lock"></i> Ganti Password</button>
        </form>
      </div>

    </div><!-- /.prf-panel -->
  </div><!-- /.prf-wrap -->
</div><!-- /.content -->

<style>
/* Sub-tabs SDM */
.sdm-subtab {
  padding:8px 14px;
  font-size:11.5px; font-weight:600;
  color:#6b7280;
  border:none; background:none;
  cursor:pointer;
  white-space:nowrap;
  border-bottom:2px solid transparent;
  margin-bottom:-1px;
  transition:all .15s;
  display:inline-flex; align-items:center; gap:5px;
}
.sdm-subtab.active  { color:#00c896; border-bottom-color:#00c896; background:#f0fdf4; border-radius:6px 6px 0 0; }
.sdm-subtab:hover:not(.active) { color:#374151; background:#f9fafb; }
.sdm-subpane { display:none; }
.sdm-subpane.active { display:block; }
</style>

<script>
(function(){
  // ── Tab utama (Profil / SDM / Password) ──
  document.querySelectorAll('.prf-tab').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.prf-tab').forEach(function(b){ b.classList.remove('active'); });
      document.querySelectorAll('.prf-tab-pane').forEach(function(p){ p.classList.remove('active'); });
      btn.classList.add('active');
      var pane = document.getElementById(btn.getAttribute('data-pane'));
      if (pane) pane.classList.add('active');
    });
  });

  // ── Sub-tabs di dalam form SDM ──
  document.querySelectorAll('.sdm-subtab').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.sdm-subtab').forEach(function(b){ b.classList.remove('active'); });
      document.querySelectorAll('.sdm-subpane').forEach(function(p){ p.classList.remove('active'); });
      btn.classList.add('active');
      var pane = document.getElementById(btn.getAttribute('data-spane'));
      if (pane) pane.classList.add('active');
    });
  });

  // ── Validasi real-time konfirmasi password ──
  var pwNew = document.getElementById('pw-new');
  var pwCnf = document.getElementById('pw-cnf');
  var pwMsg = document.getElementById('pw-match-msg');
  function checkPw(){
    if (!pwCnf.value) { pwMsg.style.display='none'; return; }
    if (pwNew.value === pwCnf.value) {
      pwMsg.style.display='block';
      pwMsg.innerHTML='<span style="color:#10b981;"><i class="fa fa-check"></i> Password cocok</span>';
    } else {
      pwMsg.style.display='block';
      pwMsg.innerHTML='<span style="color:#ef4444;"><i class="fa fa-times"></i> Password tidak cocok</span>';
    }
  }
  if (pwNew) pwNew.addEventListener('input', checkPw);
  if (pwCnf) pwCnf.addEventListener('input', checkPw);

  // ── Buka tab SDM otomatis jika URL ada #sdm ──
  if (window.location.hash === '#sdm') {
    var sdmTab = document.querySelector('.prf-tab[data-pane="tp-sdm"]');
    if (sdmTab) sdmTab.click();
  }
})();
</script>

<?php include '../includes/footer.php'; ?>