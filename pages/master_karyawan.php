<?php
// pages/master_karyawan.php
ob_start(); // Buffer output agar redirect tidak "headers already sent"
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'hrd'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Master Karyawan';
$active_menu = 'master_karyawan';

// ── Auto-create tabel sdm_karyawan jika belum ada ─────────────────────────────
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

// Upgrade kolom jika perlu
foreach ([
    "ALTER TABLE sdm_karyawan ADD COLUMN IF NOT EXISTS `kota_domisili` VARCHAR(100) DEFAULT NULL AFTER `alamat_domisili`",
    "ALTER TABLE sdm_karyawan ADD COLUMN IF NOT EXISTS `tgl_terbit_str` DATE DEFAULT NULL AFTER `no_str`",
    "ALTER TABLE sdm_karyawan ADD COLUMN IF NOT EXISTS `tgl_terbit_sip` DATE DEFAULT NULL AFTER `no_sip`",
] as $sql) { try { $pdo->exec($sql); } catch (Exception $e) {} }

// ── CEK ENUM role ─────────────────────────────────────────────────────────────
$_hrd_in_enum = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    if ($col && stripos($col['Type'], 'hrd') !== false) $_hrd_in_enum = true;
} catch (Exception $e) {}

$role_labels = ['admin'=>'Admin','teknisi'=>'Teknisi IT','teknisi_ipsrs'=>'Teknisi IPSRS','user'=>'User'];
if ($_hrd_in_enum) $role_labels['hrd'] = 'HRD';

// ── OPTIONS ───────────────────────────────────────────────────────────────────
$opt_agama       = ['Islam','Kristen Protestan','Kristen Katolik','Hindu','Buddha','Konghucu'];
$opt_gol_darah   = ['A','B','AB','O','A+','A-','B+','B-','AB+','AB-','O+','O-'];
$opt_status_nik  = ['Belum Menikah','Menikah','Cerai Hidup','Cerai Mati'];
$opt_pendidikan  = ['SD','SMP','SMA/SMK','D1','D3','D4','S1','S2','S3','Profesi','Spesialis','Sub-Spesialis'];
$opt_jenis_kary  = ['Medis','Non-Medis','Penunjang Medis'];
$opt_status_kep  = ['Tetap','Kontrak','Honorer','Magang','PPPK','Outsourcing'];
$opt_bank        = ['BRI','BNI','BCA','Mandiri','BTN','CIMB Niaga','Danamon','Permata','Syariah Indonesia','Lainnya'];

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ── Simpan / Update data SDM ──────────────────────────────────────────────
    if ($act === 'simpan_sdm') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if (!$uid) {
            setFlash('danger', 'User ID tidak valid.');
            redirect(APP_URL . '/pages/master_karyawan.php');
        }

        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // ── Kolom persis sesuai DESCRIBE sdm_karyawan ────────────────────
            // Kolom yang di-skip: id, user_id, masa_kerja_tahun (computed),
            //                     foto (upload file), status (jangan overwrite),
            //                     created_at, updated_at (auto)
            $d = [];

            // VARCHAR / TEXT
            $txt = [
                'nik_ktp','nik_rs','gelar_depan','gelar_belakang','tempat_lahir',
                'kewarganegaraan','suku',
                'no_hp','no_hp_darurat','kontak_darurat','hubungan_darurat',
                'kelurahan_ktp','kecamatan_ktp','kota_ktp','provinsi_ktp','kode_pos_ktp',
                'kota_domisili',
                'jurusan','universitas',
                'divisi','unit_kerja',
                'jenis_tenaga','spesialisasi','sub_spesialisasi',
                'no_bpjs_kes','no_bpjs_tk','no_npwp',
                'no_rekening','bank','atas_nama_rek',
                'no_str','no_sip','no_sik',
            ];
            $txt2 = ['alamat_ktp','alamat_domisili','kompetensi','alasan_resign','catatan'];

            // ENUM — hanya set jika ada value, null jika kosong
            $enm = [
                'jenis_kelamin','golongan_darah','agama','status_pernikahan',
                'pendidikan_terakhir','jenis_karyawan','status_kepegawaian',
            ];

            // DATE
            $dt = [
                'tgl_lahir','tgl_masuk','tgl_kontrak_mulai','tgl_kontrak_selesai',
                'tgl_pengangkatan','tgl_terbit_str','tgl_exp_str',
                'tgl_terbit_sip','tgl_exp_sip','tgl_exp_sik','tgl_resign',
            ];

            // INT / TINYINT / YEAR
            $int = ['jabatan_id','jumlah_anak','tahun_lulus'];

            foreach ($txt  as $f) $d[$f] = trim($_POST[$f] ?? '') ?: null;
            foreach ($txt2 as $f) $d[$f] = trim($_POST[$f] ?? '') ?: null;
            foreach ($enm  as $f) { $v = trim($_POST[$f] ?? ''); $d[$f] = $v !== '' ? $v : null; }
            foreach ($dt   as $f) { $v = trim($_POST[$f] ?? ''); $d[$f] = $v !== '' ? $v : null; }
            foreach ($int  as $f) { $v = (int)($_POST[$f] ?? 0); $d[$f] = $v > 0 ? $v : null; }

            $d['updated_by'] = (int)$_SESSION['user_id'];

            // ── Cek duplikat NIK KTP & NIK RS sebelum query ──────────────────
            if (!empty($d['nik_ktp'])) {
                $ck = $pdo->prepare("SELECT u.nama FROM sdm_karyawan s JOIN users u ON u.id=s.user_id WHERE s.nik_ktp=? AND s.user_id!=? LIMIT 1");
                $ck->execute([$d['nik_ktp'], $uid]);
                if ($who = $ck->fetchColumn()) {
                    throw new Exception('NIK KTP <strong>' . htmlspecialchars($d['nik_ktp']) . '</strong> sudah digunakan oleh <strong>' . htmlspecialchars($who) . '</strong>.');
                }
            }
            if (!empty($d['nik_rs'])) {
                $ck2 = $pdo->prepare("SELECT u.nama FROM sdm_karyawan s JOIN users u ON u.id=s.user_id WHERE s.nik_rs=? AND s.user_id!=? LIMIT 1");
                $ck2->execute([$d['nik_rs'], $uid]);
                if ($who2 = $ck2->fetchColumn()) {
                    throw new Exception('NIK RS <strong>' . htmlspecialchars($d['nik_rs']) . '</strong> sudah digunakan oleh <strong>' . htmlspecialchars($who2) . '</strong>.');
                }
            }

            // ── INSERT atau UPDATE sdm_karyawan ───────────────────────────────
            $cek = $pdo->prepare("SELECT COUNT(*) FROM sdm_karyawan WHERE user_id = ?");
            $cek->execute([$uid]);
            $exists = (int)$cek->fetchColumn() > 0;

            if ($exists) {
                $set_parts = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($d)));
                $vals = array_values($d);
                $vals[] = $uid;
                $pdo->prepare("UPDATE sdm_karyawan SET $set_parts WHERE user_id = ?")
                    ->execute($vals);
            } else {
                $d['user_id'] = $uid;
                $col_str = implode(', ', array_map(fn($k) => "`$k`", array_keys($d)));
                $ph_str  = implode(', ', array_fill(0, count($d), '?'));
                $pdo->prepare("INSERT INTO sdm_karyawan ($col_str) VALUES ($ph_str)")
                    ->execute(array_values($d));
            }

            // ── Sync no_hp ke tabel users ─────────────────────────────────────
            if (!empty($d['no_hp'])) {
                $pdo->prepare("UPDATE users SET no_hp = ? WHERE id = ?")
                    ->execute([$d['no_hp'], $uid]);
            }

            // data_karyawan tidak lagi digunakan — semua data SDM di sdm_karyawan

            setFlash('success', 'Data SDM berhasil disimpan.');

        } catch (Exception $e) {
            error_log('SDM save fatal error uid=' . $uid . ': ' . $e->getMessage());

            $msg = $e->getMessage();

            // Tangani error duplikat UNIQUE key secara user-friendly
            if (str_contains($msg, '1062') || str_contains($msg, 'Duplicate entry')) {
                if (str_contains($msg, 'uq_nik_ktp') || str_contains($msg, 'nik_ktp')) {
                    // Cari siapa yang pakai NIK KTP ini
                    $nik_input = trim($_POST['nik_ktp'] ?? '');
                    $who = '';
                    try {
                        $wq = $pdo->prepare("
                            SELECT u.nama FROM sdm_karyawan s
                            JOIN users u ON u.id = s.user_id
                            WHERE s.nik_ktp = ? AND s.user_id != ?
                            LIMIT 1
                        ");
                        $wq->execute([$nik_input, $uid]);
                        $who = $wq->fetchColumn();
                    } catch (Exception $e2) {}

                    $setFlash_msg = 'NIK KTP <strong>' . htmlspecialchars($nik_input) . '</strong> sudah digunakan';
                    if ($who) $setFlash_msg .= ' oleh <strong>' . htmlspecialchars($who) . '</strong>';
                    $setFlash_msg .= '. Setiap karyawan harus memiliki NIK KTP yang unik.';
                    setFlash('danger', $setFlash_msg);

                } elseif (str_contains($msg, 'uq_nik_rs') || str_contains($msg, 'nik_rs')) {
                    $nik_input = trim($_POST['nik_rs'] ?? '');
                    $who = '';
                    try {
                        $wq = $pdo->prepare("
                            SELECT u.nama FROM sdm_karyawan s
                            JOIN users u ON u.id = s.user_id
                            WHERE s.nik_rs = ? AND s.user_id != ?
                            LIMIT 1
                        ");
                        $wq->execute([$nik_input, $uid]);
                        $who = $wq->fetchColumn();
                    } catch (Exception $e2) {}

                    $setFlash_msg = 'NIK RS <strong>' . htmlspecialchars($nik_input) . '</strong> sudah digunakan';
                    if ($who) $setFlash_msg .= ' oleh <strong>' . htmlspecialchars($who) . '</strong>';
                    $setFlash_msg .= '. Setiap karyawan harus memiliki NIK RS yang unik.';
                    setFlash('danger', $setFlash_msg);

                } else {
                    setFlash('danger', 'Data duplikat: nilai yang Anda masukkan sudah dipakai karyawan lain.');
                }
            } elseif (str_contains($msg, '<strong>')) {
                // Pesan dari validasi kita sendiri — sudah aman, langsung tampilkan
                setFlash('danger', $msg);
            } else {
                setFlash('danger', 'Gagal menyimpan: ' . htmlspecialchars($msg));
            }
        }

        redirect(APP_URL . '/pages/master_karyawan.php');
    }

    // ── Toggle status akun ────────────────────────────────────────────────────
    if ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE users SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")
                ->execute([$id]);
            setFlash('success', 'Status akun berhasil diubah.');
        }
        redirect(APP_URL . '/pages/master_karyawan.php');
    }

    // ── Hapus akun ────────────────────────────────────────────────────────────
    if ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== (int)($_SESSION['user_id'] ?? 0)) {
            $r = $pdo->prepare("SELECT nama FROM users WHERE id=?");
            $r->execute([$id]);
            $nm = $r->fetchColumn();
            if ($nm) {
                $pdo->prepare("DELETE FROM sdm_karyawan WHERE user_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                setFlash('success', "Akun <strong>" . htmlspecialchars($nm) . "</strong> dihapus.");
            }
        } else {
            setFlash('warning', 'Tidak dapat menghapus akun sendiri.');
        }
        redirect(APP_URL . '/pages/master_karyawan.php');
    }

    redirect(APP_URL . '/pages/master_karyawan.php');
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$f_nama   = trim($_GET['nama']   ?? '');
$f_divisi = trim($_GET['divisi'] ?? '');
$f_role   = trim($_GET['role']   ?? '');
$f_status = trim($_GET['status'] ?? '');
$page_cur = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;

$where = []; $params = [];
if ($f_nama   !== '') { $where[] = '(u.nama LIKE ? OR u.email LIKE ?)'; $params[] = "%$f_nama%"; $params[] = "%$f_nama%"; }
if ($f_divisi !== '') { $where[] = 'u.divisi=?';  $params[] = $f_divisi; }
if ($f_role   !== '') { $where[] = 'u.role=?';    $params[] = $f_role; }
if ($f_status !== '') { $where[] = 'u.status=?';  $params[] = $f_status; }
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM users u $wsql");
$cnt->execute($params);
$total_rows  = (int)$cnt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page_cur    = min($page_cur, $total_pages);
$offset      = ($page_cur - 1) * $per_page;

// JOIN ke sdm_karyawan untuk badge & warning exp
$stm = $pdo->prepare("
    SELECT u.*,
           s.id       AS sdm_id,
           s.nik_rs,
           s.jenis_karyawan,
           s.status_kepegawaian,
           s.tgl_masuk,
           s.tgl_exp_str,
           s.tgl_exp_sip,
           s.gelar_depan,
           s.gelar_belakang
    FROM users u
    LEFT JOIN sdm_karyawan s ON s.user_id = u.id
    $wsql
    ORDER BY u.nama ASC
    LIMIT $per_page OFFSET $offset
");
$stm->execute($params);
$list = $stm->fetchAll(PDO::FETCH_ASSOC);

// ── Data pendukung ────────────────────────────────────────────────────────────
$bagian_list = [];
try {
    $bagian_list = $pdo->query("SELECT nama FROM bagian WHERE status='aktif' ORDER BY urutan,nama")
                       ->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$jabatan_list = [];
try {
    $jabatan_list = $pdo->query("SELECT id,nama,kode FROM jabatan WHERE status='aktif' ORDER BY level,urutan,nama")
                        ->fetchAll();
} catch (Exception $e) {}

// ── Statistik ─────────────────────────────────────────────────────────────────
$stats = ['total'=>0,'aktif'=>0,'nonaktif'=>0,'admin_cnt'=>0,'teknisi_cnt'=>0,'ipsrs_cnt'=>0,'hrd_cnt'=>0,'user_cnt'=>0,'sdm_lengkap'=>0];
try {
    $s = $pdo->query("
        SELECT COUNT(*) total,
               SUM(u.status='aktif')         aktif,
               SUM(u.status='nonaktif')      nonaktif,
               SUM(role='admin')             admin_cnt,
               SUM(role='teknisi')           teknisi_cnt,
               SUM(role='teknisi_ipsrs')     ipsrs_cnt,
               SUM(role='hrd')               hrd_cnt,
               SUM(role='user')              user_cnt
        FROM users u
    ")->fetch(PDO::FETCH_ASSOC);
    if ($s) $stats = array_merge($stats, $s);

    $sdm_cnt = $pdo->query("SELECT COUNT(*) FROM sdm_karyawan WHERE tgl_masuk IS NOT NULL AND jenis_karyawan IS NOT NULL")
                   ->fetchColumn();
    $stats['sdm_lengkap'] = (int)$sdm_cnt;
} catch (Exception $e) {}

include '../includes/header.php';
?>
<style>
/* ══════════════════════════════════════════
   MODAL SDM
══════════════════════════════════════════ */
.sdm-ov {
  display: none; position: fixed;
  top:0; left:0; right:0; bottom:0;
  background: rgba(0,0,0,.6);
  z-index: 99999;
  align-items: center; justify-content: center;
  padding: 16px;
  backdrop-filter: blur(3px);
}
.sdm-ov.open { display: flex; }
.sdm-modal {
  background: #fff; border-radius: 14px;
  box-shadow: 0 24px 80px rgba(0,0,0,.3);
  width: 100%; max-width: 820px;
  height: 86vh;          /* tinggi TETAP — konsisten semua tab */
  min-height: 520px;
  max-height: 92vh;
  display: flex; flex-direction: column;
  animation: sdmIn .22s ease;
  overflow: hidden;
  position: relative;
}
@keyframes sdmIn {
  from { opacity:0; transform:translateY(16px) scale(.97); }
  to   { opacity:1; transform:none; }
}
.sdm-info-bar {
  background: linear-gradient(135deg,#0a0f14,#1a2535);
  padding: 14px 20px; display: flex; align-items: center;
  gap: 14px; color: #fff; flex-shrink: 0;
}
.sdm-info-av {
  width:42px; height:42px; border-radius:50%;
  background: linear-gradient(135deg,#00e5b0,#00c896);
  color:#0a0f14; font-size:14px; font-weight:800;
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.sdm-info-nama { font-size:14px; font-weight:700; line-height:1.3; }
.sdm-info-sub  { font-size:11px; color:#9ca3af; margin-top:2px; }
.sdm-mclose {
  margin-left:auto; width:30px; height:30px; border-radius:50%;
  border:none; background:rgba(255,255,255,.15); color:#fff;
  cursor:pointer; font-size:12px;
  display:flex; align-items:center; justify-content:center;
  transition:background .15s; flex-shrink:0;
}
.sdm-mclose:hover { background:#ef4444; }
.sdm-tabs {
  display:flex; border-bottom:2px solid #e5e7eb;
  background:#fafafa; flex-shrink:0; overflow-x:auto;
}
.sdm-tab {
  padding:10px 16px; font-size:12px; font-weight:600;
  color:#6b7280; border:none; background:none;
  cursor:pointer; white-space:nowrap;
  border-bottom:2px solid transparent; margin-bottom:-2px;
  transition:all .15s; display:flex; align-items:center; gap:5px;
}
.sdm-tab.active { color:#00c896; border-bottom-color:#00c896; }
.sdm-tab:hover:not(.active) { color:#374151; background:#f3f4f6; }
.sdm-mbody {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
  overscroll-behavior: contain;
  /* Batasi tinggi mbody agar mft selalu terlihat */
  max-height: calc(90vh - 180px); /* 180px = info-bar + tabs + mft */
}
.sdm-tab-pane { display:none; padding:18px 22px; }
.sdm-tab-pane.active { display:block; }
.sdm-mft {
  padding:12px 20px; border-top:1px solid #e5e7eb;
  display:flex; gap:8px; justify-content:flex-end;
  flex-shrink:0; background:#fafafa;
}
.sdm-g2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.sdm-g3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
.sdm-g4 { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
.sdm-fg { display:flex; flex-direction:column; gap:3px; margin-bottom:8px; }
.sdm-fg label { font-size:11px; font-weight:600; color:#374151; }
.sdm-fg label .opt { font-size:9.5px; color:#9ca3af; font-weight:400; }
.sdm-fg input, .sdm-fg select, .sdm-fg textarea {
  border:1px solid #d1d5db; border-radius:7px;
  padding:0 10px; font-size:12.5px; font-family:inherit;
  background:#f9fafb; color:#111827;
  transition:border-color .15s, background .15s;
  box-sizing:border-box; width:100%;
}
.sdm-fg input, .sdm-fg select { height:34px; }
.sdm-fg textarea { padding:7px 10px; resize:vertical; min-height:58px; }
.sdm-fg input:focus, .sdm-fg select:focus, .sdm-fg textarea:focus {
  outline:none; border-color:#00c896; background:#fff;
  box-shadow:0 0 0 3px rgba(0,200,150,.1);
}
.sdm-sec {
  font-size:10px; font-weight:700; color:#00c896;
  letter-spacing:1.2px; text-transform:uppercase;
  margin:14px 0 10px; padding-bottom:6px;
  border-bottom:1px solid #e5e7eb;
  display:flex; align-items:center; gap:6px;
}
.sdm-sec:first-child { margin-top:0; }
.sdm-req { color:#ef4444; }
.sdm-badge-ok { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700; background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:10px; }
.sdm-badge-no { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700; background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:10px; }
.sdm-loading {
  display:none; position:absolute; inset:0;
  background:rgba(255,255,255,.75); z-index:10;
  align-items:center; justify-content:center;
  border-radius:14px; font-size:13px; color:#00c896;
  font-weight:600; gap:8px;
}

/* ── Info banner saat data SDM belum diisi ── */
.sdm-prefill-banner {
  display:none;
  background:#fef9c3; border:1px solid #fde047;
  border-radius:8px; padding:9px 13px;
  font-size:12px; color:#854d0e;
  margin-bottom:12px;
  align-items:center; gap:8px;
}

@media(max-width:640px){
  .sdm-g2,.sdm-g3,.sdm-g4 { grid-template-columns:1fr; }
  .sdm-modal { max-height:98vh; border-radius:10px; }
  .sdm-tab-pane { padding:14px; }
}
</style>

<div class="page-header">
  <h4><i class="fa fa-users text-primary"></i> &nbsp;Master Karyawan</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Master Karyawan</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <?php if (!$_hrd_in_enum): ?>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:11px 14px;
              margin-bottom:14px;font-size:12.5px;color:#92400e;
              display:flex;align-items:flex-start;gap:8px;">
    <i class="fa fa-triangle-exclamation" style="margin-top:2px;flex-shrink:0;"></i>
    <div><strong>Info:</strong> Role <code>hrd</code> belum ada di ENUM. Jalankan:<br>
    <code style="display:block;margin-top:4px;background:#fef3c7;padding:5px 10px;
                 border-radius:4px;font-size:11.5px;">
    ALTER TABLE users MODIFY COLUMN role ENUM('admin','teknisi','teknisi_ipsrs','user','hrd') NOT NULL DEFAULT 'user';
    </code></div>
  </div>
  <?php endif; ?>

  <!-- Stats cards -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;margin-bottom:18px;">
    <?php foreach ([
      ['Total',       $stats['total'],        '#374151','#00e5b0'],
      ['Aktif',       $stats['aktif'],        '#065f46','#00e5b0'],
      ['Nonaktif',    $stats['nonaktif'],     '#6b7280','#d1d5db'],
      ['Data SDM',    $stats['sdm_lengkap'],  '#1d4ed8','#3b82f6'],
      ['Admin',       $stats['admin_cnt'],    '#5b21b6','#8b5cf6'],
      ['Teknisi IT',  $stats['teknisi_cnt'],  '#1d4ed8','#60a5fa'],
      ['T. IPSRS',    $stats['ipsrs_cnt'],    '#c2410c','#f97316'],
      ['HRD',         $stats['hrd_cnt'],      '#9d174d','#ec4899'],
    ] as [$lbl,$val,$tc,$bc]): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;">
      <div style="font-size:22px;font-weight:800;color:<?= $tc ?>;line-height:1;"><?= (int)$val ?></div>
      <div style="font-size:11px;color:#6b7280;font-weight:500;margin-top:3px;"><?= $lbl ?></div>
      <div style="height:3px;border-radius:99px;background:<?= $bc ?>;margin-top:5px;opacity:.5;"></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filter -->
  <form method="GET">
    <div class="panel" style="margin-bottom:14px;">
      <div class="panel-bd">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
          <div>
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:3px;">Cari Nama / Email</label>
            <input type="text" name="nama" class="form-control" style="width:180px;height:34px;"
                   placeholder="Ketik…" value="<?= htmlspecialchars($f_nama) ?>">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:3px;">Divisi</label>
            <select name="divisi" class="form-control" style="min-width:130px;height:34px;">
              <option value="">Semua Divisi</option>
              <?php foreach ($bagian_list as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>" <?= $f_divisi === $b ? 'selected' : '' ?>>
                <?= htmlspecialchars($b) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:3px;">Role</label>
            <select name="role" class="form-control" style="min-width:120px;height:34px;">
              <option value="">Semua Role</option>
              <?php foreach ($role_labels as $rv => $rl): ?>
              <option value="<?= $rv ?>" <?= $f_role === $rv ? 'selected' : '' ?>><?= $rl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:3px;">Status</label>
            <select name="status" class="form-control" style="min-width:100px;height:34px;">
              <option value="">Semua</option>
              <option value="aktif"    <?= $f_status === 'aktif'    ? 'selected' : '' ?>>Aktif</option>
              <option value="nonaktif" <?= $f_status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
          <div style="display:flex;gap:6px;">
            <button type="submit" class="btn btn-primary" style="height:34px;">
              <i class="fa fa-search"></i> Filter
            </button>
            <a href="master_karyawan.php" class="btn btn-default"
               style="height:34px;display:inline-flex;align-items:center;gap:5px;">
              <i class="fa fa-rotate-left"></i> Reset
            </a>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- Tabel -->
  <div class="panel">
    <div class="panel-hd">
      <h5>
        <i class="fa fa-users text-primary"></i> &nbsp;Daftar Karyawan
        <span style="color:#aaa;font-weight:400;">(<?= number_format($total_rows) ?>)</span>
        <?php if ($f_nama || $f_divisi || $f_role || $f_status): ?>
        <span style="font-size:11px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;
                     padding:1px 8px;border-radius:10px;font-weight:600;margin-left:6px;">
          filter aktif
        </span>
        <?php endif; ?>
      </h5>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th>Nama Karyawan</th>
            <th>Email</th>
            <th>Divisi</th>
            <th>Role</th>
            <th>Data SDM</th>
            <th>Status Akun</th>
            <th style="width:190px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($list)): ?>
          <tr>
            <td colspan="8" class="td-empty">
              <i class="fa fa-users"></i> Tidak ada data karyawan
            </td>
          </tr>
          <?php else: foreach ($list as $i => $k):
            $words   = array_filter(explode(' ', trim($k['nama'])));
            $inisial = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(array_values($words), 0, 2))));
            if (!$inisial) $inisial = '?';
            $is_self = (int)$k['id'] === (int)($_SESSION['user_id'] ?? 0);
            $rs_style = [
              'admin'         => 'background:#ede9fe;color:#5b21b6;',
              'teknisi'       => 'background:#dbeafe;color:#1d4ed8;',
              'teknisi_ipsrs' => 'background:#ffedd5;color:#c2410c;',
              'hrd'           => 'background:#fce7f3;color:#9d174d;',
              'akreditasi'    => 'background:#fef9c3;color:#854d0e;',
              'user'          => 'background:#f3f4f6;color:#374151;',
            ];
            $nama_tampil = ($k['gelar_depan'] ? $k['gelar_depan'] . ' ' : '')
                         . htmlspecialchars($k['nama'])
                         . ($k['gelar_belakang'] ? ', ' . $k['gelar_belakang'] : '');
            $sdm_ada = !empty($k['sdm_id']);
            $exp_warning = '';
            if ($k['tgl_exp_str'] && strtotime($k['tgl_exp_str']) < strtotime('+30 days'))
              $exp_warning = 'STR';
            if ($k['tgl_exp_sip'] && strtotime($k['tgl_exp_sip']) < strtotime('+30 days'))
              $exp_warning .= ($exp_warning ? '/' : '') . 'SIP';
          ?>
          <tr>
            <td style="color:#bbb;font-size:12px;"><?= $offset + $i + 1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:34px;height:34px;border-radius:50%;
                            background:linear-gradient(135deg,#00e5b0,#00c896);
                            color:#0a0f14;font-size:11px;font-weight:800;
                            display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <?= htmlspecialchars($inisial) ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:13px;"><?= $nama_tampil ?></div>
                  <div style="font-size:10.5px;color:#9ca3af;">
                    <?= $k['nik_rs'] ? 'NIK: ' . htmlspecialchars($k['nik_rs']) : '—' ?>
                    <?php if ($is_self): ?>
                    &nbsp;<span style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;
                                       padding:0 5px;border-radius:8px;font-size:9.5px;font-weight:700;">
                      Anda
                    </span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </td>
            <td style="font-size:12px;">
              <?= $k['email'] ? htmlspecialchars($k['email']) : '<span style="color:#bbb;">—</span>' ?>
              <?php if ($k['no_hp'] ?? ''): ?>
              <br><span style="font-size:11px;color:#888;">
                <i class="fa fa-phone" style="font-size:10px;"></i> <?= htmlspecialchars($k['no_hp'] ?? '') ?>
              </span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;">
              <?= $k['divisi'] ? htmlspecialchars($k['divisi']) : '<span style="color:#bbb;">—</span>' ?>
              <?php if ($k['jenis_karyawan']): ?>
              <br><span style="font-size:10px;color:#888;"><?= htmlspecialchars($k['jenis_karyawan'] ?? '') ?></span>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;
                           <?= $rs_style[$k['role']] ?? 'background:#f3f4f6;color:#374151;' ?>">
                <?= $role_labels[$k['role']] ?? ucfirst($k['role']) ?>
              </span>
            </td>
            <td>
              <?php if ($sdm_ada): ?>
              <span class="sdm-badge-ok"><i class="fa fa-check"></i> Lengkap</span>
              <?php if ($exp_warning): ?>
              <br><span style="font-size:9.5px;color:#dc2626;font-weight:700;">
                <i class="fa fa-triangle-exclamation"></i> Exp. <?= $exp_warning ?>
              </span>
              <?php endif; ?>
              <?php else: ?>
              <span class="sdm-badge-no"><i class="fa fa-clock"></i> Belum diisi</span>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;
                           background:<?= $k['status'] === 'aktif' ? '#d1fae5' : '#f3f4f6' ?>;
                           color:<?= $k['status'] === 'aktif' ? '#065f46' : '#6b7280' ?>;">
                <?= $k['status'] === 'aktif' ? '● Aktif' : '○ Nonaktif' ?>
              </span>
            </td>
            <td style="white-space:nowrap;">
              <!-- Tombol Isi/Edit SDM -->
              <button type="button"
                      class="btn btn-primary btn-sm btn-open-sdm"
                      data-uid="<?= (int)$k['id'] ?>"
                      data-nama="<?= htmlspecialchars($k['nama'], ENT_QUOTES) ?>"
                      title="<?= $sdm_ada ? 'Edit' : 'Lengkapi' ?> Data SDM">
                <i class="fa fa-id-card"></i> <?= $sdm_ada ? 'Edit' : 'Isi' ?> SDM
              </button>

              <!-- Toggle status -->
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                <button type="submit"
                  class="btn btn-sm <?= $k['status'] === 'aktif' ? 'btn-default' : 'btn-success' ?>"
                  title="<?= $k['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>"
                  onclick="return confirm('<?= $k['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?> akun ini?')">
                  <i class="fa <?= $k['status'] === 'aktif' ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                </button>
              </form>

              <!-- Hapus -->
              <?php if (!$is_self): ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Hapus akun <?= addslashes(htmlspecialchars($k['nama'])) ?>?')">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
              <?php else: ?>
              <button class="btn btn-sm btn-default" disabled title="Tidak dapat menghapus akun sendiri">
                <i class="fa fa-lock" style="color:#bbb;"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;
                padding:10px 14px;border-top:1px solid #e5e7eb;flex-wrap:wrap;gap:8px;">
      <div style="font-size:11.5px;color:#6b7280;">
        Halaman <?= $page_cur ?> dari <?= $total_pages ?> &mdash; <?= number_format($total_rows) ?> data
      </div>
      <div style="display:flex;gap:4px;">
        <?php
          $qs    = http_build_query(array_filter(['nama'=>$f_nama,'divisi'=>$f_divisi,'role'=>$f_role,'status'=>$f_status]));
          $qs    = $qs ? '&' . $qs : '';
          $start = max(1, $page_cur - 2);
          $end   = min($total_pages, $start + 4);
          $start = max(1, $end - 4);
        ?>
        <a href="?p=1<?= $qs ?>" class="btn btn-sm btn-default"><i class="fa fa-angles-left"></i></a>
        <a href="?p=<?= max(1,$page_cur-1) ?><?= $qs ?>" class="btn btn-sm btn-default"><i class="fa fa-angle-left"></i></a>
        <?php for ($pg = $start; $pg <= $end; $pg++): ?>
        <a href="?p=<?= $pg ?><?= $qs ?>"
           class="btn btn-sm <?= $pg === $page_cur ? 'btn-primary' : 'btn-default' ?>">
          <?= $pg ?>
        </a>
        <?php endfor; ?>
        <a href="?p=<?= min($total_pages,$page_cur+1) ?><?= $qs ?>" class="btn btn-sm btn-default"><i class="fa fa-angle-right"></i></a>
        <a href="?p=<?= $total_pages ?><?= $qs ?>" class="btn btn-sm btn-default"><i class="fa fa-angles-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div><!-- /.content -->


<!-- ══════════════════════════════════════════════════════
     MODAL FORM DATA SDM
══════════════════════════════════════════════════════ -->
<div id="sdm-modal" class="sdm-ov">
  <div class="sdm-modal">
    <div class="sdm-loading" id="sdm-loading">
      <i class="fa fa-spinner fa-spin"></i> Memuat data…
    </div>

    <div class="sdm-info-bar">
      <div class="sdm-info-av" id="sdm-av">?</div>
      <div>
        <div class="sdm-info-nama" id="sdm-nama-display">—</div>
        <div class="sdm-info-sub" id="sdm-source-info">Melengkapi data SDM karyawan</div>
      </div>
      <button type="button" class="sdm-mclose" id="sdm-close-btn"><i class="fa fa-times"></i></button>
    </div>

    <div class="sdm-tabs">
      <button type="button" class="sdm-tab active" data-pane="t-identitas">
        <i class="fa fa-id-card"></i> Identitas
      </button>
      <button type="button" class="sdm-tab" data-pane="t-kontak">
        <i class="fa fa-location-dot"></i> Kontak &amp; Alamat
      </button>
      <button type="button" class="sdm-tab" data-pane="t-kepegawaian">
        <i class="fa fa-briefcase"></i> Kepegawaian
      </button>
      <button type="button" class="sdm-tab" data-pane="t-keuangan">
        <i class="fa fa-credit-card"></i> Keuangan
      </button>
      <button type="button" class="sdm-tab" data-pane="t-lisensi">
        <i class="fa fa-stethoscope"></i> Lisensi
      </button>
    </div>

    <form method="POST" id="sdm-form">
      <input type="hidden" name="action" value="simpan_sdm">
      <input type="hidden" name="user_id" id="sdm-user-id" value="">

      <div class="sdm-mbody">

        <!-- ══ TAB 1: IDENTITAS ══ -->
        <div class="sdm-tab-pane active" id="t-identitas">
          <!-- Banner saat data SDM belum ada -->
          <div class="sdm-prefill-banner" id="sdm-prefill-banner">
            <i class="fa fa-wand-magic-sparkles" style="flex-shrink:0;"></i>
            <div>Data di bawah diambil dari data awal. Periksa dan simpan untuk melengkapi data SDM.</div>
          </div>

          <div class="sdm-sec"><i class="fa fa-id-card"></i> Identitas Pribadi</div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>NIK RS <span class="opt">(Nomor Induk Karyawan Rumah Sakit)</span></label>
              <input type="text" name="nik_rs" id="s-nik_rs" placeholder="Nomor yang diberikan RS">
              <small style="color:#9ca3af;font-size:10px;margin-top:2px;display:block;">
                <i class="fa fa-info-circle"></i> Berbeda dari NIK KTP — ini nomor internal RS
              </small>
            </div>
            <div class="sdm-fg">
              <label>NIK KTP <span class="opt">(16 digit — No. Identitas Nasional)</span></label>
              <input type="text" name="nik_ktp" id="s-nik_ktp" placeholder="3271xxxxxxxxxxxx" maxlength="16">
            </div>
            <div class="sdm-fg">
              <label>Jenis Kelamin</label>
              <select name="jenis_kelamin" id="s-jenis_kelamin">
                <option value="">— Pilih —</option>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
              </select>
            </div>
          </div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>Gelar Depan <span class="opt">dr., Ns., dll</span></label>
              <input type="text" name="gelar_depan" id="s-gelar_depan" placeholder="dr. / Ns. / drg.">
            </div>
            <div class="sdm-fg">
              <label>Gelar Belakang <span class="opt">M.Kes, Sp.A</span></label>
              <input type="text" name="gelar_belakang" id="s-gelar_belakang" placeholder="M.Kes / Sp.A / S.Kep">
            </div>
            <div class="sdm-fg">
              <label>Golongan Darah</label>
              <select name="golongan_darah" id="s-golongan_darah">
                <option value="">— Pilih —</option>
                <?php foreach ($opt_gol_darah as $g): ?>
                <option value="<?= $g ?>"><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>Tempat Lahir</label>
              <input type="text" name="tempat_lahir" id="s-tempat_lahir" placeholder="Kota tempat lahir">
            </div>
            <div class="sdm-fg">
              <label>Tanggal Lahir</label>
              <input type="date" name="tgl_lahir" id="s-tgl_lahir">
            </div>
            <div class="sdm-fg">
              <label>Agama</label>
              <select name="agama" id="s-agama">
                <option value="">— Pilih —</option>
                <?php foreach ($opt_agama as $a): ?>
                <option value="<?= $a ?>"><?= $a ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>Status Pernikahan</label>
              <select name="status_pernikahan" id="s-status_pernikahan">
                <option value="">— Pilih —</option>
                <?php foreach ($opt_status_nik as $sn): ?>
                <option value="<?= $sn ?>"><?= $sn ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sdm-fg">
              <label>Jumlah Anak</label>
              <input type="number" name="jumlah_anak" id="s-jumlah_anak" min="0" max="20" value="0">
            </div>
            <div class="sdm-fg">
              <label>Kewarganegaraan</label>
              <input type="text" name="kewarganegaraan" id="s-kewarganegaraan" value="WNI" placeholder="WNI / WNA">
            </div>
          </div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>Suku / Etnis</label>
              <input type="text" name="suku" id="s-suku" placeholder="Jawa / Minang / Batak…">
            </div>

          </div>
          <div class="sdm-sec"><i class="fa fa-graduation-cap"></i> Pendidikan</div>
          <div class="sdm-g4">
            <div class="sdm-fg">
              <label>Pendidikan Terakhir</label>
              <select name="pendidikan_terakhir" id="s-pendidikan_terakhir">
                <option value="">— Pilih —</option>
                <?php foreach ($opt_pendidikan as $p): ?>
                <option value="<?= $p ?>"><?= $p ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sdm-fg">
              <label>Jurusan / Prodi</label>
              <input type="text" name="jurusan" id="s-jurusan" placeholder="Keperawatan…">
            </div>
            <div class="sdm-fg">
              <label>Universitas</label>
              <input type="text" name="universitas" id="s-universitas" placeholder="Nama institusi">
            </div>
            <div class="sdm-fg">
              <label>Tahun Lulus</label>
              <input type="number" name="tahun_lulus" id="s-tahun_lulus" placeholder="2019" min="1970" max="2099">
            </div>
          </div>
        </div>

        <!-- ══ TAB 2: KONTAK & ALAMAT ══ -->
        <div class="sdm-tab-pane" id="t-kontak">
          <div class="sdm-sec"><i class="fa fa-phone"></i> Kontak</div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>No. HP / WhatsApp</label>
              <input type="text" name="no_hp" id="s-no_hp" placeholder="08xxxxxxxxxx">
            </div>
            <div class="sdm-fg">
              <label>No. HP Darurat</label>
              <input type="text" name="no_hp_darurat" id="s-no_hp_darurat" placeholder="08xxxxxxxxxx">
            </div>
            <div class="sdm-fg">
              <label>Nama Kontak Darurat</label>
              <input type="text" name="kontak_darurat" id="s-kontak_darurat" placeholder="Nama keluarga">
            </div>
          </div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>Hubungan Kontak Darurat</label>
              <input type="text" name="hubungan_darurat" id="s-hubungan_darurat" placeholder="Istri / Suami…">
            </div>
          </div>
          <div class="sdm-sec"><i class="fa fa-location-dot"></i> Alamat KTP</div>
          <div class="sdm-fg">
            <label>Alamat Lengkap KTP</label>
            <textarea name="alamat_ktp" id="s-alamat_ktp" placeholder="Jalan, RT/RW, Kelurahan, Kecamatan…"></textarea>
          </div>
          <div class="sdm-g4">
            <div class="sdm-fg"><label>Kelurahan</label><input type="text" name="kelurahan_ktp" id="s-kelurahan_ktp" placeholder="Nama kelurahan"></div>
            <div class="sdm-fg"><label>Kecamatan</label><input type="text" name="kecamatan_ktp" id="s-kecamatan_ktp" placeholder="Nama kecamatan"></div>
            <div class="sdm-fg"><label>Kota / Kabupaten</label><input type="text" name="kota_ktp" id="s-kota_ktp"></div>
            <div class="sdm-fg"><label>Provinsi</label><input type="text" name="provinsi_ktp" id="s-provinsi_ktp"></div>
          </div>
          <div class="sdm-g2">
            <div class="sdm-fg"><label>Kode Pos</label><input type="text" name="kode_pos_ktp" id="s-kode_pos_ktp" maxlength="10"></div>
          </div>
          <div class="sdm-sec"><i class="fa fa-house"></i> Alamat Domisili <span style="font-size:10px;color:#6b7280;font-weight:400;">(kosongkan jika sama KTP)</span></div>
          <div class="sdm-fg">
            <label>Alamat Domisili</label>
            <textarea name="alamat_domisili" id="s-alamat_domisili" placeholder="Kosongkan jika sama KTP…"></textarea>
          </div>
          <div class="sdm-g2">
            <div class="sdm-fg"><label>Kota Domisili</label><input type="text" name="kota_domisili" id="s-kota_domisili"></div>
          </div>
        </div>

        <!-- ══ TAB 3: KEPEGAWAIAN ══ -->
        <div class="sdm-tab-pane" id="t-kepegawaian">
          <div class="sdm-sec"><i class="fa fa-briefcase"></i> Data Kepegawaian</div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>Jenis Karyawan <span class="sdm-req">*</span></label>
              <select name="jenis_karyawan" id="s-jenis_karyawan">
                <option value="">— Pilih —</option>
                <?php foreach ($opt_jenis_kary as $jk): ?>
                <option value="<?= $jk ?>"><?= $jk ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sdm-fg">
              <label>Jenis Tenaga / Profesi</label>
              <input type="text" name="jenis_tenaga" id="s-jenis_tenaga" placeholder="Dokter / Perawat / Bidan…">
            </div>
            <div class="sdm-fg">
              <label>Spesialisasi</label>
              <input type="text" name="spesialisasi" id="s-spesialisasi" placeholder="Sp.A / ICU / IGD / —">
            </div>
          </div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>Sub Spesialisasi</label>
              <input type="text" name="sub_spesialisasi" id="s-sub_spesialisasi" placeholder="Sub-spesialisasi jika ada">
            </div>
            <div class="sdm-fg">
              <label>Unit Kerja <span class="opt">(sub divisi)</span></label>
              <input type="text" name="unit_kerja" id="s-unit_kerja" placeholder="ICU, IGD, Poli Anak…">
            </div>
          </div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>Jabatan</label>
              <select name="jabatan_id" id="s-jabatan_id">
                <option value="">— Pilih Jabatan —</option>
                <?php foreach ($jabatan_list as $jb): ?>
                <option value="<?= (int)$jb['id'] ?>">
                  <?= htmlspecialchars($jb['nama']) ?><?= $jb['kode'] ? ' (' . $jb['kode'] . ')' : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sdm-fg">
              <label>Status Kepegawaian <span class="sdm-req">*</span></label>
              <select name="status_kepegawaian" id="s-status_kepegawaian">
                <?php foreach ($opt_status_kep as $sk): ?>
                <option value="<?= $sk ?>"><?= $sk ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="sdm-g4">
            <div class="sdm-fg"><label>Tanggal Masuk <span class="sdm-req">*</span></label><input type="date" name="tgl_masuk" id="s-tgl_masuk"></div>
            <div class="sdm-fg"><label>Awal Kontrak</label><input type="date" name="tgl_kontrak_mulai" id="s-tgl_kontrak_mulai"></div>
            <div class="sdm-fg"><label>Akhir Kontrak</label><input type="date" name="tgl_kontrak_selesai" id="s-tgl_kontrak_selesai"></div>
            <div class="sdm-fg"><label>Tgl Pengangkatan Tetap</label><input type="date" name="tgl_pengangkatan" id="s-tgl_pengangkatan"></div>
          </div>
          <div class="sdm-fg">
            <label>Catatan</label>
            <textarea name="catatan" id="s-catatan" placeholder="Catatan kepegawaian…"></textarea>
          </div>
        </div>

        <!-- ══ TAB 4: KEUANGAN ══ -->
        <div class="sdm-tab-pane" id="t-keuangan">
          <div class="sdm-sec"><i class="fa fa-shield-halved"></i> BPJS &amp; Pajak</div>
          <div class="sdm-g3">
            <div class="sdm-fg"><label>No. BPJS Kesehatan</label><input type="text" name="no_bpjs_kes" id="s-no_bpjs_kes" placeholder="0001xxxxxxxxx"></div>
            <div class="sdm-fg"><label>No. BPJS Ketenagakerjaan</label><input type="text" name="no_bpjs_tk" id="s-no_bpjs_tk"></div>
            <div class="sdm-fg"><label>No. NPWP</label><input type="text" name="no_npwp" id="s-no_npwp" placeholder="XX.XXX.XXX.X-XXX.XXX"></div>
          </div>
          <div class="sdm-sec"><i class="fa fa-building-columns"></i> Rekening Bank</div>
          <div class="sdm-g3">
            <div class="sdm-fg">
              <label>Bank</label>
              <select name="bank" id="s-bank">
                <option value="">— Pilih Bank —</option>
                <?php foreach ($opt_bank as $b): ?>
                <option value="<?= $b ?>"><?= $b ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sdm-fg"><label>No. Rekening</label><input type="text" name="no_rekening" id="s-no_rekening"></div>
            <div class="sdm-fg"><label>Atas Nama</label><input type="text" name="atas_nama_rek" id="s-atas_nama_rek"></div>
          </div>
        </div>

        <!-- ══ TAB 5: LISENSI ══ -->
        <div class="sdm-tab-pane" id="t-lisensi">
          <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                      padding:10px 14px;margin-bottom:12px;font-size:12px;color:#065f46;
                      display:flex;align-items:center;gap:8px;">
            <i class="fa fa-circle-info"></i>
            Untuk karyawan <strong>Non-Medis</strong>, isi dengan <code>-</code> atau kosongkan saja.
          </div>
          <div class="sdm-sec"><i class="fa fa-id-badge"></i> STR — Surat Tanda Registrasi</div>
          <div class="sdm-g3">
            <div class="sdm-fg"><label>Nomor STR</label><input type="text" name="no_str" id="s-no_str" placeholder="Nomor STR atau -"></div>
            <div class="sdm-fg"><label>Tgl Terbit STR</label><input type="date" name="tgl_terbit_str" id="s-tgl_terbit_str"></div>
            <div class="sdm-fg"><label>Exp. STR</label><input type="date" name="tgl_exp_str" id="s-tgl_exp_str"></div>
          </div>
          <div class="sdm-sec"><i class="fa fa-file-medical"></i> SIP — Surat Izin Praktik</div>
          <div class="sdm-g3">
            <div class="sdm-fg"><label>Nomor SIP</label><input type="text" name="no_sip" id="s-no_sip" placeholder="Nomor SIP atau -"></div>
            <div class="sdm-fg"><label>Tgl Terbit SIP</label><input type="date" name="tgl_terbit_sip" id="s-tgl_terbit_sip"></div>
            <div class="sdm-fg"><label>Exp. SIP</label><input type="date" name="tgl_exp_sip" id="s-tgl_exp_sip"></div>
          </div>
          <div class="sdm-sec"><i class="fa fa-file-signature"></i> SIK — Surat Izin Kerja</div>
          <div class="sdm-g3">
            <div class="sdm-fg"><label>Nomor SIK</label><input type="text" name="no_sik" id="s-no_sik" placeholder="Nomor SIK atau -"></div>
            <div class="sdm-fg"><label>Exp. SIK</label><input type="date" name="tgl_exp_sik" id="s-tgl_exp_sik"></div>
          </div>
          <div class="sdm-sec"><i class="fa fa-certificate"></i> Kompetensi &amp; Sertifikasi</div>
          <div class="sdm-fg">
            <label>Kompetensi / Sertifikasi Tambahan</label>
            <textarea name="kompetensi" id="s-kompetensi" placeholder="BLS, ACLS, BTCLS, PONEK, dll. Pisahkan koma."></textarea>
          </div>
        </div>

      </div><!-- /sdm-mbody -->

      <div class="sdm-mft">
        <button type="button" class="btn btn-default" id="sdm-cancel-btn">
          <i class="fa fa-times"></i> Batal
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-save"></i> Simpan Data SDM
        </button>
      </div>
    </form>
  </div>
</div>


<script>
(function () {
  'use strict';

  var modal    = document.getElementById('sdm-modal');
  var loading  = document.getElementById('sdm-loading');
  var form     = document.getElementById('sdm-form');
  var uidInput = document.getElementById('sdm-user-id');

  function openModal(uid, nama) {
    form.reset();
    switchTab(document.querySelector('.sdm-tab[data-pane="t-identitas"]'), 't-identitas');
    document.getElementById('sdm-prefill-banner').style.display = 'none';
    document.getElementById('sdm-source-info').textContent = 'Melengkapi data SDM karyawan';

    uidInput.value = uid;
    document.getElementById('sdm-nama-display').textContent = nama;
    var words = nama.trim().split(/\s+/).filter(Boolean);
    var init  = words.slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('');
    document.getElementById('sdm-av').textContent = init || '?';

    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetchSdmData(uid);
  }

  function closeModal() {
    modal.classList.remove('open');
    document.body.style.overflow = '';
  }

  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  document.getElementById('sdm-close-btn').addEventListener('click', closeModal);
  document.getElementById('sdm-cancel-btn').addEventListener('click', closeModal);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
  });

  // ── Tabs ──────────────────────────────────────────────────────────────────
  function switchTab(btn, paneId) {
    document.querySelectorAll('.sdm-tab').forEach(function (b) { b.classList.remove('active'); });
    document.querySelectorAll('.sdm-tab-pane').forEach(function (p) { p.classList.remove('active'); });
    btn.classList.add('active');
    var pane = document.getElementById(paneId);
    if (pane) pane.classList.add('active');
  }
  document.querySelectorAll('.sdm-tab').forEach(function (btn) {
    btn.addEventListener('click', function () { switchTab(btn, btn.getAttribute('data-pane')); });
  });

  // ── Fetch data SDM dari server ─────────────────────────────────────────────
  // Semua kolom sdm_karyawan yang ditampilkan di form
  var allFields = [
    'nik_rs','nik_ktp','gelar_depan','gelar_belakang','tempat_lahir',
    'kewarganegaraan','suku',
    'no_hp','no_hp_darurat','kontak_darurat','hubungan_darurat',
    'kelurahan_ktp','kecamatan_ktp','kota_ktp','provinsi_ktp','kode_pos_ktp',
    'kota_domisili','alamat_ktp','alamat_domisili',
    'jurusan','universitas','tahun_lulus',
    'divisi','unit_kerja','jabatan_id',
    'jenis_tenaga','spesialisasi','sub_spesialisasi',
    'no_bpjs_kes','no_bpjs_tk','no_npwp',
    'no_rekening','bank','atas_nama_rek',
    'no_str','tgl_terbit_str','tgl_exp_str',
    'no_sip','tgl_terbit_sip','tgl_exp_sip',
    'no_sik','tgl_exp_sik',
    'kompetensi','catatan',
    'tgl_lahir','tgl_masuk','tgl_kontrak_mulai','tgl_kontrak_selesai',
    'tgl_pengangkatan','tgl_resign','alasan_resign',
    'jenis_kelamin','golongan_darah','agama','status_pernikahan',
    'pendidikan_terakhir','jenis_karyawan','status_kepegawaian',
    'jumlah_anak',
  ];

  function fetchSdmData(uid) {
    loading.style.display = 'flex';

    fetch('ajax_sdm.php?user_id=' + uid, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (d) {
        loading.style.display = 'none';

        // Object kosong = belum ada data sama sekali
        if (!d || Object.keys(d).length === 0) return;

        // Isi semua field form
        allFields.forEach(function (f) {
          var el = document.getElementById('s-' + f);
          if (!el) return;
          var val = (d[f] !== null && d[f] !== undefined) ? d[f] : '';
          el.value = val;
        });

        // Tampilkan banner jika data berasal dari prefill data_karyawan
        if (d._is_prefill) {
          document.getElementById('sdm-prefill-banner').style.display = 'flex';
          document.getElementById('sdm-source-info').textContent = 'Data awal tersedia — periksa dan simpan';
        }
      })
      .catch(function (err) {
        loading.style.display = 'none';
        console.warn('SDM fetch error:', err);
        // Modal tetap terbuka, form kosong — user bisa isi manual
      });
  }

  // ── Event delegation untuk tombol "Isi/Edit SDM" ─────────────────────────
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-open-sdm');
    if (!btn) return;
    openModal(btn.getAttribute('data-uid'), btn.getAttribute('data-nama'));
  });

})();
</script>

<?php include '../includes/footer.php'; ?>