<?php
// pages/master_berkas.php
ob_start();
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'hrd'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Master Berkas';
$active_menu = 'master_berkas';

// ── Auto-create tabel ─────────────────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `master_kategori_berkas` (
      `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `nama`       VARCHAR(100) NOT NULL,
      `icon`       VARCHAR(50)  DEFAULT 'fa-folder',
      `warna`      VARCHAR(20)  DEFAULT '#6366f1',
      `urutan`     TINYINT UNSIGNED DEFAULT 0,
      `status`     ENUM('aktif','nonaktif') DEFAULT 'aktif',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `master_jenis_berkas` (
      `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `kategori_id`    INT UNSIGNED NOT NULL,
      `nama`           VARCHAR(150) NOT NULL,
      `icon`           VARCHAR(50)  DEFAULT 'fa-file',
      `keterangan`     TEXT         DEFAULT NULL,
      `wajib`          TINYINT(1)   DEFAULT 0,
      `has_exp`        TINYINT(1)   DEFAULT 0,
      `has_tgl_terbit` TINYINT(1)   DEFAULT 0,
      `format_file`    VARCHAR(100) DEFAULT 'pdf,jpg,jpeg,png',
      `urutan`         TINYINT UNSIGNED DEFAULT 0,
      `status`         ENUM('aktif','nonaktif') DEFAULT 'aktif',
      `created_by`     INT UNSIGNED DEFAULT NULL,
      `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
      `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY `idx_kategori` (`kategori_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── Seed data awal ────────────────────────────────────────────────────────────
try {
    if ((int)$pdo->query("SELECT COUNT(*) FROM master_kategori_berkas")->fetchColumn() === 0) {
        $sk = $pdo->prepare("INSERT INTO master_kategori_berkas (urutan,nama,icon,warna) VALUES (?,?,?,?)");
        foreach ([
            [1,'Identitas & Administrasi','fa-id-card',        '#1d4ed8'],
            [2,'Pendidikan & Pelatihan',  'fa-graduation-cap', '#059669'],
            [3,'Lisensi & Registrasi',    'fa-id-badge',       '#9d174d'],
            [4,'Kepegawaian',             'fa-briefcase',      '#b45309'],
            [5,'Jaminan & Keuangan',      'fa-building-columns','#5b21b6'],
            [6,'Berkas Tambahan',         'fa-folder-plus',    '#475569'],
        ] as $r) $sk->execute($r);

        $sj = $pdo->prepare("INSERT INTO master_jenis_berkas (kategori_id,nama,icon,keterangan,wajib,has_exp,has_tgl_terbit,format_file,urutan) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ([
            [1,'Foto 3×4 Terbaru',                   'fa-image',          'Foto terbaru ukuran 3×4',                               1,0,0,'jpg,jpeg,png',1],
            [1,'Fotokopi KTP',                        'fa-id-card',        'Kartu Tanda Penduduk',                                  1,0,0,'pdf,jpg,jpeg,png',2],
            [1,'Fotokopi Kartu Keluarga',             'fa-house-user',     'Kartu Keluarga (KK)',                                   1,0,0,'pdf,jpg,jpeg,png',3],
            [1,'Fotokopi NPWP',                       'fa-file-invoice',   'Nomor Pokok Wajib Pajak',                               0,0,0,'pdf,jpg,jpeg,png',4],
            [1,'Surat Lamaran Kerja',                 'fa-envelope-open-text','Surat lamaran kerja',                               1,0,0,'pdf,jpg,jpeg,png,docx,doc',5],
            [1,'CV / Daftar Riwayat Hidup',          'fa-file-lines',     'Curriculum Vitae',                                      1,0,0,'pdf,docx,doc',6],
            [2,'Ijazah Terakhir',                     'fa-graduation-cap', 'Fotokopi ijazah pendidikan terakhir',                   1,0,1,'pdf,jpg,jpeg,png',1],
            [2,'Transkrip Nilai',                     'fa-list-ol',        'Transkrip nilai akademik',                              1,0,1,'pdf,jpg,jpeg,png',2],
            [2,'Sertifikat BLS / BCLS',              'fa-heart-pulse',    'Basic Life Support / Basic Cardiac Life Support',       0,1,1,'pdf,jpg,jpeg,png',3],
            [2,'Sertifikat ACLS',                     'fa-kit-medical',    'Advanced Cardiac Life Support',                         0,1,1,'pdf,jpg,jpeg,png',4],
            [2,'Sertifikat ATLS',                     'fa-suitcase-medical','Advanced Trauma Life Support',                         0,1,1,'pdf,jpg,jpeg,png',5],
            [2,'Sertifikat BTCLS / PPGD',            'fa-kit-medical',    'Basic Trauma Cardiac Life Support',                    0,1,1,'pdf,jpg,jpeg,png',6],
            [2,'Sertifikat PONEK',                    'fa-baby',           'Penanganan Obstetri Neonatal Emergensi Komprehensif',   0,1,1,'pdf,jpg,jpeg,png',7],
            [2,'Sertifikat PPI',                      'fa-shield-halved',  'Pencegahan dan Pengendalian Infeksi',                   0,1,1,'pdf,jpg,jpeg,png',8],
            [2,'Sertifikat K3 Rumah Sakit',          'fa-helmet-safety',  'Keselamatan dan Kesehatan Kerja RS',                   0,1,1,'pdf,jpg,jpeg,png',9],
            [2,'Sertifikat Kompetensi',              'fa-award',          'Sertifikat kompetensi profesi',                         0,1,1,'pdf,jpg,jpeg,png',10],
            [2,'Sertifikat Pelatihan Lainnya',       'fa-certificate',    'Sertifikat pelatihan / diklat lainnya',                0,1,1,'pdf,jpg,jpeg,png',11],
            [3,'STR (Surat Tanda Registrasi)',       'fa-id-badge',       'STR yang masih berlaku',                                0,1,1,'pdf,jpg,jpeg,png',1],
            [3,'SIP (Surat Izin Praktik)',           'fa-file-medical',   'SIP yang masih berlaku',                                0,1,1,'pdf,jpg,jpeg,png',2],
            [3,'SIK (Surat Izin Kerja)',             'fa-file-signature', 'Surat Izin Kerja tenaga kesehatan',                    0,1,1,'pdf,jpg,jpeg,png',3],
            [3,'STRA (Apoteker)',                     'fa-pills',          'Surat Tanda Registrasi Apoteker',                       0,1,1,'pdf,jpg,jpeg,png',4],
            [3,'SIPA (Apoteker)',                     'fa-prescription-bottle-medical','Surat Izin Praktik Apoteker',              0,1,1,'pdf,jpg,jpeg,png',5],
            [4,'SK Pengangkatan / Kontrak Kerja',    'fa-briefcase',      'SK Pengangkatan atau Kontrak Kerja',                   1,1,1,'pdf,jpg,jpeg,png,docx,doc',1],
            [4,'Perjanjian Kerja (PKB/PKWT)',        'fa-handshake',      'Perjanjian Kerja Bersama / Waktu Tertentu',            0,1,1,'pdf,jpg,jpeg,png',2],
            [4,'SK Jabatan Terakhir',                'fa-user-tie',       'Surat Keputusan jabatan terakhir',                     0,0,1,'pdf,jpg,jpeg,png,docx,doc',3],
            [4,'Surat Keterangan Sehat',             'fa-notes-medical',  'Surat keterangan sehat dari dokter',                   1,1,1,'pdf,jpg,jpeg,png',4],
            [4,'Hasil Pemeriksaan Kesehatan (MCU)',  'fa-stethoscope',    'Hasil Medical Check Up',                               0,1,1,'pdf,jpg,jpeg,png',5],
            [4,'Surat Bebas Narkoba',               'fa-shield-halved',  'Surat keterangan bebas narkoba',                       0,1,1,'pdf,jpg,jpeg,png',6],
            [4,'SKCK',                               'fa-shield',         'Surat Keterangan Catatan Kepolisian',                  0,1,1,'pdf,jpg,jpeg,png',7],
            [4,'Surat Referensi / Rekomendasi',     'fa-file-circle-check','Surat referensi dari tempat kerja sebelumnya',       0,0,0,'pdf,jpg,jpeg,png,docx,doc',8],
            [5,'Fotokopi Kartu BPJS Kesehatan',     'fa-hospital',       'Kartu BPJS Kesehatan aktif',                           0,0,0,'pdf,jpg,jpeg,png',1],
            [5,'Fotokopi Kartu BPJS TK',            'fa-building-shield','Kartu BPJS Ketenagakerjaan',                           0,0,0,'pdf,jpg,jpeg,png',2],
            [5,'Fotokopi Buku Rekening',            'fa-building-columns','Halaman pertama buku rekening bank',                  1,0,0,'pdf,jpg,jpeg,png',3],
            [6,'Akta Nikah',                         'fa-heart',          'Buku nikah / akta perkawinan',                         0,0,0,'pdf,jpg,jpeg,png',1],
            [6,'Akta Kelahiran Anak',               'fa-baby',           'Akta kelahiran anak',                                  0,0,0,'pdf,jpg,jpeg,png',2],
            [6,'Ijazah Sebelumnya',                 'fa-graduation-cap', 'Ijazah pendidikan sebelum pendidikan terakhir',        0,0,0,'pdf,jpg,jpeg,png',3],
            [6,'Surat Keterangan Lainnya',          'fa-file-circle-plus','Dokumen atau surat keterangan lainnya',               0,0,0,'pdf,jpg,jpeg,png,docx,doc',4],
        ] as $r) $sj->execute($r);
    }
} catch (Exception $e) {}

// ── Options ───────────────────────────────────────────────────────────────────
$icon_options = [
    'fa-file'=>'File Umum','fa-id-card'=>'KTP / ID','fa-image'=>'Foto',
    'fa-graduation-cap'=>'Ijazah','fa-certificate'=>'Sertifikat','fa-award'=>'Penghargaan',
    'fa-file-medical'=>'Berkas Medis','fa-id-badge'=>'Lisensi','fa-file-signature'=>'Surat Izin',
    'fa-briefcase'=>'Kepegawaian','fa-handshake'=>'Perjanjian','fa-notes-medical'=>'Ket. Sehat',
    'fa-stethoscope'=>'Kesehatan','fa-shield'=>'SKCK','fa-shield-halved'=>'Bebas Narkoba',
    'fa-heart-pulse'=>'BLS','fa-kit-medical'=>'ACLS/ATLS','fa-suitcase-medical'=>'BTCLS',
    'fa-pills'=>'Farmasi','fa-hospital'=>'RS / BPJS','fa-building-columns'=>'Bank',
    'fa-building-shield'=>'BPJS TK','fa-house-user'=>'KK','fa-file-invoice'=>'NPWP',
    'fa-file-lines'=>'CV','fa-envelope-open-text'=>'Surat Lamaran',
    'fa-heart'=>'Akta Nikah','fa-baby'=>'Akta Lahir','fa-people-roof'=>'Keluarga',
    'fa-user-tie'=>'SK Jabatan','fa-folder-plus'=>'Lainnya',
    'fa-file-circle-plus'=>'Tambahan','fa-file-circle-check'=>'Rekomendasi',
    'fa-list-ol'=>'Transkrip','fa-helmet-safety'=>'K3',
    'fa-prescription-bottle-medical'=>'Apoteker',
];
$warna_options = [
    '#1d4ed8'=>'Biru','#059669'=>'Hijau','#9d174d'=>'Pink',
    '#b45309'=>'Kuning','#5b21b6'=>'Ungu','#475569'=>'Abu',
    '#dc2626'=>'Merah','#ea580c'=>'Oranye','#0891b2'=>'Cyan',
    '#374151'=>'Gelap','#6366f1'=>'Indigo','#15803d'=>'Hijau Tua',
];

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'simpan_kategori') {
        $id    = (int)($_POST['id']     ?? 0);
        $nama  = trim($_POST['nama']    ?? '');
        $icon  = trim($_POST['icon']    ?? 'fa-folder');
        $warna = trim($_POST['warna']   ?? '#6366f1');
        $urut  = (int)($_POST['urutan'] ?? 0);
        if (!$nama) { setFlash('danger','Nama kategori wajib diisi.'); redirect(APP_URL.'/pages/master_berkas.php'); }
        try {
            if ($id) {
                $pdo->prepare("UPDATE master_kategori_berkas SET nama=?,icon=?,warna=?,urutan=? WHERE id=?")
                    ->execute([$nama,$icon,$warna,$urut,$id]);
                setFlash('success','Kategori berhasil diperbarui.');
            } else {
                $pdo->prepare("INSERT INTO master_kategori_berkas (nama,icon,warna,urutan) VALUES (?,?,?,?)")
                    ->execute([$nama,$icon,$warna,$urut]);
                setFlash('success','Kategori berhasil ditambahkan.');
            }
        } catch (Exception $e) { setFlash('danger','Gagal: '.htmlspecialchars($e->getMessage())); }
        redirect(APP_URL.'/pages/master_berkas.php?tab=kategori');
    }

    if ($act === 'toggle_kategori') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $pdo->prepare("UPDATE master_kategori_berkas SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]); setFlash('success','Status kategori diubah.'); }
        redirect(APP_URL.'/pages/master_berkas.php?tab=kategori');
    }

    if ($act === 'hapus_kategori') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $cek = (int)$pdo->query("SELECT COUNT(*) FROM master_jenis_berkas WHERE kategori_id=$id")->fetchColumn();
            if ($cek > 0) { setFlash('warning','Kategori masih memiliki jenis berkas, tidak bisa dihapus.'); }
            else { $pdo->prepare("DELETE FROM master_kategori_berkas WHERE id=?")->execute([$id]); setFlash('success','Kategori dihapus.'); }
        }
        redirect(APP_URL.'/pages/master_berkas.php?tab=kategori');
    }

    if ($act === 'simpan_jenis') {
        $id             = (int)($_POST['id']          ?? 0);
        $kategori_id    = (int)($_POST['kategori_id'] ?? 0);
        $nama           = trim($_POST['nama']          ?? '');
        $icon           = trim($_POST['icon']          ?? 'fa-file');
        $keterangan     = trim($_POST['keterangan']    ?? '') ?: null;
        $wajib          = isset($_POST['wajib'])          ? 1 : 0;
        $has_exp        = isset($_POST['has_exp'])         ? 1 : 0;
        $has_tgl_terbit = isset($_POST['has_tgl_terbit']) ? 1 : 0;
        $format_file    = trim($_POST['format_file']   ?? 'pdf,jpg,jpeg,png');
        $urutan         = (int)($_POST['urutan']       ?? 0);
        if (!$nama || !$kategori_id) { setFlash('danger','Nama dan kategori wajib diisi.'); redirect(APP_URL.'/pages/master_berkas.php'); }
        try {
            if ($id) {
                $pdo->prepare("UPDATE master_jenis_berkas SET kategori_id=?,nama=?,icon=?,keterangan=?,wajib=?,has_exp=?,has_tgl_terbit=?,format_file=?,urutan=? WHERE id=?")
                    ->execute([$kategori_id,$nama,$icon,$keterangan,$wajib,$has_exp,$has_tgl_terbit,$format_file,$urutan,$id]);
                setFlash('success','Jenis berkas diperbarui.');
            } else {
                $pdo->prepare("INSERT INTO master_jenis_berkas (kategori_id,nama,icon,keterangan,wajib,has_exp,has_tgl_terbit,format_file,urutan,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$kategori_id,$nama,$icon,$keterangan,$wajib,$has_exp,$has_tgl_terbit,$format_file,$urutan,(int)$_SESSION['user_id']]);
                setFlash('success','Jenis berkas ditambahkan.');
            }
        } catch (Exception $e) { setFlash('danger','Gagal: '.htmlspecialchars($e->getMessage())); }
        redirect(APP_URL.'/pages/master_berkas.php');
    }

    if ($act === 'toggle_jenis') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $pdo->prepare("UPDATE master_jenis_berkas SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]); setFlash('success','Status berkas diubah.'); }
        redirect(APP_URL.'/pages/master_berkas.php');
    }

    if ($act === 'hapus_jenis') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $pdo->prepare("DELETE FROM master_jenis_berkas WHERE id=?")->execute([$id]); setFlash('success','Jenis berkas dihapus.'); }
        redirect(APP_URL.'/pages/master_berkas.php');
    }

    redirect(APP_URL.'/pages/master_berkas.php');
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'jenis';

$kategori_list = $pdo->query("SELECT * FROM master_kategori_berkas ORDER BY urutan,nama")->fetchAll(PDO::FETCH_ASSOC);

$f_kat    = trim($_GET['kategori_id'] ?? '');
$f_status = trim($_GET['status']      ?? '');
$where = []; $params = [];
if ($f_kat    !== '') { $where[] = 'j.kategori_id=?'; $params[] = $f_kat; }
if ($f_status !== '') { $where[] = 'j.status=?';      $params[] = $f_status; }
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$jenis_list = $pdo->prepare("
    SELECT j.*, k.nama AS nama_kategori, k.icon AS kat_icon, k.warna AS kat_warna
    FROM master_jenis_berkas j
    LEFT JOIN master_kategori_berkas k ON k.id=j.kategori_id
    $wsql
    ORDER BY k.urutan, k.nama, j.urutan, j.nama
");
$jenis_list->execute($params);
$jenis_list = $jenis_list->fetchAll(PDO::FETCH_ASSOC);

$total_jenis = (int)$pdo->query("SELECT COUNT(*) FROM master_jenis_berkas")->fetchColumn();
$total_wajib = (int)$pdo->query("SELECT COUNT(*) FROM master_jenis_berkas WHERE wajib=1 AND status='aktif'")->fetchColumn();
$total_aktif = (int)$pdo->query("SELECT COUNT(*) FROM master_jenis_berkas WHERE status='aktif'")->fetchColumn();

include '../includes/header.php';
?>
<style>
/* ── Tab bar ── */
.mb-tab-bar { display:flex; gap:4px; background:#f1f5f9; border-radius:10px; padding:4px; margin-bottom:18px; width:fit-content; }
.mb-tab { padding:7px 18px; border-radius:8px; border:none; font-size:12.5px; font-weight:600; cursor:pointer; background:none; color:#64748b; transition:all .18s; display:flex; align-items:center; gap:6px; }
.mb-tab.active { background:#fff; color:#1e293b; box-shadow:0 1px 6px rgba(0,0,0,.1); }
.mb-tab:hover:not(.active) { background:rgba(255,255,255,.6); color:#374151; }

/* ── Modal overlay — nama unik mb-overlay agar tidak konflik dengan header ── */
.mb-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(15,23,42,.6); z-index: 99999;
  align-items: center; justify-content: center;
  padding: 16px; backdrop-filter: blur(4px);
}
.mb-overlay.open { display: flex; }
.mb-modal-box {
  background: #fff; border-radius: 14px;
  box-shadow: 0 24px 80px rgba(0,0,0,.28);
  width: 100%; max-width: 540px;
  animation: mbIn .2s ease;
}
.mb-modal-box.sm { max-width: 440px; }
@keyframes mbIn { from{opacity:0;transform:translateY(14px) scale(.97);} to{opacity:1;transform:none;} }
.mb-mhead { padding:16px 20px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:10px; }
.mb-mhead-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; background:#6366f120; }
.mb-mhead-title { font-size:14px; font-weight:700; color:#1e293b; flex:1; }
.mb-mclose { width:28px; height:28px; border-radius:7px; border:none; background:#f3f4f6; color:#9ca3af; cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center; transition:all .15s; }
.mb-mclose:hover { background:#dc2626; color:#fff; }
.mb-mbody { padding:18px 20px; }
.mb-mft { padding:12px 20px; border-top:1px solid #e5e7eb; display:flex; gap:8px; justify-content:flex-end; background:#f8fafc; border-radius:0 0 14px 14px; }
.mb-fg { margin-bottom:12px; }
.mb-fg label { font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
.mb-fg input, .mb-fg select, .mb-fg textarea {
  width:100%; border:1px solid #d1d5db; border-radius:8px;
  padding:7px 11px; font-size:12.5px; font-family:inherit;
  background:#f9fafb; color:#111827; box-sizing:border-box; transition:border-color .15s;
}
.mb-fg input:focus, .mb-fg select:focus, .mb-fg textarea:focus {
  outline:none; border-color:#6366f1; background:#fff; box-shadow:0 0 0 3px rgba(99,102,241,.1);
}
.mb-fg textarea { resize:vertical; min-height:60px; padding-top:8px; }
.mb-g2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.mb-check-row { display:flex; align-items:center; gap:8px; padding:8px 11px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; cursor:pointer; transition:all .15s; }
.mb-check-row:hover { border-color:#6366f1; background:#f5f3ff; }
.mb-check-row input[type=checkbox] { width:15px; height:15px; accent-color:#6366f1; flex-shrink:0; }
.mb-check-label { font-size:12px; font-weight:600; color:#374151; }
.mb-check-sub   { font-size:10.5px; color:#9ca3af; margin-top:1px; }

/* badge */
.badge-wajib    { background:#fee2e2;color:#dc2626;font-size:9.5px;font-weight:700;padding:1px 7px;border-radius:10px; }
.badge-opsional { background:#f1f5f9;color:#64748b;font-size:9.5px;font-weight:700;padding:1px 7px;border-radius:10px; }
.badge-exp      { background:#fef3c7;color:#b45309;font-size:9.5px;font-weight:700;padding:1px 6px;border-radius:8px; }
.badge-aktif    { background:#dcfce7;color:#15803d;font-size:9.5px;font-weight:700;padding:1px 7px;border-radius:10px; }
.badge-nonaktif { background:#f3f4f6;color:#9ca3af;font-size:9.5px;font-weight:700;padding:1px 7px;border-radius:10px; }

/* warna picker */
.warna-picker { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.warna-opt { width:26px; height:26px; border-radius:50%; cursor:pointer; border:3px solid transparent; transition:all .15s; }
.warna-opt.selected, .warna-opt:hover { border-color:#1e293b; transform:scale(1.15); }

/* icon preview */
.icon-preview { display:flex; align-items:center; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; margin-top:8px; }
.icon-prev-box { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; }

/* kat group row */
.kat-group-hd { background:#f8fafc; border-top:2px solid #e2e8f0; }
.kat-group-hd td { padding:8px 14px !important; font-size:11.5px; font-weight:700; color:#374151; }
</style>

<div class="page-header">
  <h4><i class="fa fa-folder-tree text-primary"></i> &nbsp;Master Berkas</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Master Berkas</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:18px;">
    <?php foreach ([
      ['Kategori',     count($kategori_list), '#1e293b','#6366f1'],
      ['Total Jenis',  $total_jenis,          '#1e293b','#0ea5e9'],
      ['Aktif',        $total_aktif,          '#065f46','#00c896'],
      ['Berkas Wajib', $total_wajib,          '#991b1b','#dc3545'],
    ] as [$lbl,$val,$tc,$bc]): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;">
      <div style="font-size:24px;font-weight:800;color:<?= $tc ?>;line-height:1;"><?= (int)$val ?></div>
      <div style="font-size:11px;color:#6b7280;font-weight:500;margin-top:3px;"><?= $lbl ?></div>
      <div style="height:3px;border-radius:99px;background:<?= $bc ?>;margin-top:6px;opacity:.5;"></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tab bar -->
  <div class="mb-tab-bar">
    <button class="mb-tab <?= $tab !== 'kategori' ? 'active' : '' ?>" onclick="mbSwitchTab('jenis')">
      <i class="fa fa-file-lines"></i> Jenis Berkas
    </button>
    <button class="mb-tab <?= $tab === 'kategori' ? 'active' : '' ?>" onclick="mbSwitchTab('kategori')">
      <i class="fa fa-layer-group"></i> Kategori
    </button>
  </div>

  <!-- ══ TAB JENIS ══ -->
  <div id="tab-jenis" <?= $tab === 'kategori' ? 'style="display:none;"' : '' ?>>
    <div class="panel">
      <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h5><i class="fa fa-file-lines text-primary"></i> Jenis Berkas</h5>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <form method="GET" style="display:flex;gap:6px;align-items:center;">
            <input type="hidden" name="tab" value="jenis">
            <select name="kategori_id" class="form-control" style="height:32px;font-size:12px;min-width:140px;"
                    onchange="this.form.submit()">
              <option value="">Semua Kategori</option>
              <?php foreach ($kategori_list as $k): ?>
              <option value="<?= $k['id'] ?>" <?= $f_kat == (string)$k['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($k['nama']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <select name="status" class="form-control" style="height:32px;font-size:12px;min-width:100px;"
                    onchange="this.form.submit()">
              <option value="">Semua Status</option>
              <option value="aktif"    <?= $f_status === 'aktif'    ? 'selected' : '' ?>>Aktif</option>
              <option value="nonaktif" <?= $f_status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
            <?php if ($f_kat || $f_status): ?>
            <a href="?tab=jenis" class="btn btn-default btn-sm"><i class="fa fa-rotate-left"></i></a>
            <?php endif; ?>
          </form>
          <button type="button" class="btn btn-primary btn-sm" onclick="mbOpenJenis()">
            <i class="fa fa-plus"></i> Tambah Jenis Berkas
          </button>
        </div>
      </div>
      <div class="panel-bd np tbl-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:40px;">#</th>
              <th>Nama Berkas</th>
              <th>Kategori</th>
              <th>Sifat</th>
              <th>Fitur</th>
              <th>Format</th>
              <th>Status</th>
              <th style="width:160px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($jenis_list)): ?>
            <tr><td colspan="8" class="td-empty"><i class="fa fa-file-lines"></i> Tidak ada data</td></tr>
            <?php else:
              $prev_kat = null; $no = 0;
              foreach ($jenis_list as $j):
                if ($j['nama_kategori'] !== $prev_kat):
                  $prev_kat = $j['nama_kategori'];
            ?>
            <tr class="kat-group-hd">
              <td colspan="8">
                <span style="display:inline-flex;align-items:center;gap:7px;">
                  <span style="width:22px;height:22px;border-radius:6px;
                               background:<?= htmlspecialchars($j['kat_warna']) ?>22;
                               display:inline-flex;align-items:center;justify-content:center;">
                    <i class="fa <?= htmlspecialchars($j['kat_icon']) ?>"
                       style="color:<?= htmlspecialchars($j['kat_warna']) ?>;font-size:10px;"></i>
                  </span>
                  <?= htmlspecialchars($j['nama_kategori'] ?? '—') ?>
                </span>
              </td>
            </tr>
            <?php endif; $no++; ?>
            <tr>
              <td style="color:#bbb;font-size:12px;padding-left:28px;"><?= $no ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="width:28px;height:28px;border-radius:7px;flex-shrink:0;
                              background:<?= htmlspecialchars($j['kat_warna'] ?? '#6366f1') ?>18;
                              display:flex;align-items:center;justify-content:center;">
                    <i class="fa <?= htmlspecialchars($j['icon']) ?>"
                       style="color:<?= htmlspecialchars($j['kat_warna'] ?? '#6366f1') ?>;font-size:11px;"></i>
                  </div>
                  <div>
                    <div style="font-weight:600;font-size:12.5px;"><?= htmlspecialchars($j['nama']) ?></div>
                    <?php if ($j['keterangan']): ?>
                    <div style="font-size:10.5px;color:#9ca3af;margin-top:1px;">
                      <?= htmlspecialchars(mb_substr($j['keterangan'], 0, 55)) ?><?= mb_strlen($j['keterangan']) > 55 ? '…' : '' ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <span style="background:<?= htmlspecialchars($j['kat_warna'] ?? '#6366f1') ?>18;
                             color:<?= htmlspecialchars($j['kat_warna'] ?? '#6366f1') ?>;
                             font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;">
                  <?= htmlspecialchars($j['nama_kategori'] ?? '—') ?>
                </span>
              </td>
              <td>
                <?php if ($j['wajib']): ?>
                <span class="badge-wajib"><i class="fa fa-asterisk" style="font-size:8px;"></i> WAJIB</span>
                <?php else: ?>
                <span class="badge-opsional">Opsional</span>
                <?php endif; ?>
              </td>
              <td style="font-size:11px;">
                <?php if ($j['has_exp']): ?>
                <span class="badge-exp"><i class="fa fa-clock"></i> Exp</span>
                <?php endif; ?>
                <?php if ($j['has_tgl_terbit']): ?>
                &nbsp;<span style="background:#ede9fe;color:#6d28d9;font-size:9.5px;font-weight:700;padding:1px 6px;border-radius:8px;">Tgl Terbit</span>
                <?php endif; ?>
                <?php if (!$j['has_exp'] && !$j['has_tgl_terbit']): ?>
                <span style="color:#d1d5db;">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:11px;color:#6b7280;"><?= htmlspecialchars($j['format_file'] ?? '') ?></td>
              <td>
                <?= $j['status'] === 'aktif'
                    ? '<span class="badge-aktif">● Aktif</span>'
                    : '<span class="badge-nonaktif">○ Nonaktif</span>' ?>
              </td>
              <td style="white-space:nowrap;">
                <button type="button" class="btn btn-primary btn-sm"
                        onclick="mbEditJenis(<?= htmlspecialchars(json_encode($j), ENT_QUOTES) ?>)">
                  <i class="fa fa-pen"></i> Edit
                </button>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Ubah status?')">
                  <input type="hidden" name="action" value="toggle_jenis">
                  <input type="hidden" name="id"     value="<?= $j['id'] ?>">
                  <button type="submit"
                          class="btn btn-sm <?= $j['status'] === 'aktif' ? 'btn-default' : 'btn-success' ?>">
                    <i class="fa <?= $j['status'] === 'aktif' ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                  </button>
                </form>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Hapus jenis berkas ini?')">
                  <input type="hidden" name="action" value="hapus_jenis">
                  <input type="hidden" name="id"     value="<?= $j['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fa fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══ TAB KATEGORI ══ -->
  <div id="tab-kategori" <?= $tab !== 'kategori' ? 'style="display:none;"' : '' ?>>
    <div class="panel">
      <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;">
        <h5><i class="fa fa-layer-group text-primary"></i> Kategori Berkas</h5>
        <button type="button" class="btn btn-primary btn-sm" onclick="mbOpenKategori()">
          <i class="fa fa-plus"></i> Tambah Kategori
        </button>
      </div>
      <div class="panel-bd np tbl-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:40px;">#</th>
              <th>Kategori</th>
              <th>Icon</th>
              <th>Jumlah Jenis</th>
              <th>Urutan</th>
              <th>Status</th>
              <th style="width:160px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($kategori_list)): ?>
            <tr><td colspan="7" class="td-empty">Tidak ada kategori</td></tr>
            <?php else: foreach ($kategori_list as $i => $k):
              $cnt_j = (int)$pdo->query("SELECT COUNT(*) FROM master_jenis_berkas WHERE kategori_id={$k['id']}")->fetchColumn();
            ?>
            <tr>
              <td style="color:#bbb;font-size:12px;"><?= $i + 1 ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:9px;">
                  <div style="width:32px;height:32px;border-radius:9px;flex-shrink:0;
                              background:<?= htmlspecialchars($k['warna']) ?>22;
                              display:flex;align-items:center;justify-content:center;">
                    <i class="fa <?= htmlspecialchars($k['icon']) ?>"
                       style="color:<?= htmlspecialchars($k['warna']) ?>;font-size:13px;"></i>
                  </div>
                  <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($k['nama']) ?></div>
                </div>
              </td>
              <td style="font-size:12px;color:#6b7280;">
                <code style="font-size:11px;background:#f1f5f9;padding:2px 6px;border-radius:4px;">
                  <?= htmlspecialchars($k['icon']) ?>
                </code>
              </td>
              <td>
                <span style="font-size:13px;font-weight:700;color:#1e293b;"><?= $cnt_j ?></span>
                <span style="font-size:11px;color:#9ca3af;"> jenis</span>
              </td>
              <td style="font-size:12px;color:#6b7280;"><?= (int)$k['urutan'] ?></td>
              <td>
                <?= $k['status'] === 'aktif'
                    ? '<span class="badge-aktif">● Aktif</span>'
                    : '<span class="badge-nonaktif">○ Nonaktif</span>' ?>
              </td>
              <td style="white-space:nowrap;">
                <button type="button" class="btn btn-primary btn-sm"
                        onclick='mbEditKategori(<?= htmlspecialchars(json_encode($k), ENT_QUOTES) ?>)'>
                  <i class="fa fa-pen"></i> Edit
                </button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_kategori">
                  <input type="hidden" name="id"     value="<?= $k['id'] ?>">
                  <button type="submit"
                          class="btn btn-sm <?= $k['status'] === 'aktif' ? 'btn-default' : 'btn-success' ?>">
                    <i class="fa <?= $k['status'] === 'aktif' ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                  </button>
                </form>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Hapus kategori ini?')">
                  <input type="hidden" name="action" value="hapus_kategori">
                  <input type="hidden" name="id"     value="<?= $k['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fa fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /content -->


<!-- ══════════════════════════════════════
     MODAL JENIS BERKAS
══════════════════════════════════════ -->
<div id="mb-jenis-modal" class="mb-overlay">
  <div class="mb-modal-box">
    <div class="mb-mhead">
      <div class="mb-mhead-icon"><i class="fa fa-file text-primary"></i></div>
      <div class="mb-mhead-title" id="mb-jenis-title">Tambah Jenis Berkas</div>
      <button type="button" class="mb-mclose" onclick="mbCloseJenis()"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" id="mb-jenis-form">
      <input type="hidden" name="action" value="simpan_jenis">
      <input type="hidden" name="id" id="mb-jenis-id" value="">
      <div class="mb-mbody">

        <div class="mb-fg">
          <label>Kategori <span style="color:#dc2626;">*</span></label>
          <select name="kategori_id" id="mb-jenis-kategori" required>
            <option value="">— Pilih Kategori —</option>
            <?php foreach ($kategori_list as $k): ?>
            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-fg">
          <label>Nama Jenis Berkas <span style="color:#dc2626;">*</span></label>
          <input type="text" name="nama" id="mb-jenis-nama" required
                 placeholder="Misal: Sertifikat BLS, Fotokopi KTP…">
        </div>

        <div class="mb-g2">
          <div class="mb-fg">
            <label>Icon (Font Awesome)</label>
            <select name="icon" id="mb-jenis-icon" onchange="mbPreviewIcon()">
              <?php foreach ($icon_options as $cls => $lbl): ?>
              <option value="<?= $cls ?>"><?= $lbl ?> (<?= $cls ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-fg">
            <label>Urutan</label>
            <input type="number" name="urutan" id="mb-jenis-urutan" min="0" max="99" value="0">
          </div>
        </div>

        <div class="icon-preview">
          <div class="icon-prev-box" id="mb-icon-prev-box" style="background:#6366f120;">
            <i class="fa fa-file" id="mb-icon-prev-i" style="color:#6366f1;"></i>
          </div>
          <span style="font-size:12px;color:#64748b;">Preview icon</span>
        </div>

        <div class="mb-fg" style="margin-top:12px;">
          <label>Keterangan <span style="font-size:10px;color:#9ca3af;">(opsional)</span></label>
          <textarea name="keterangan" id="mb-jenis-keterangan"
                    placeholder="Penjelasan singkat…"></textarea>
        </div>

        <div class="mb-fg">
          <label>Format File yang Diizinkan</label>
          <input type="text" name="format_file" id="mb-jenis-format"
                 value="pdf,jpg,jpeg,png" placeholder="pdf,jpg,jpeg,png,docx">
          <small style="font-size:10px;color:#9ca3af;margin-top:3px;display:block;">
            Pisahkan dengan koma. Contoh: pdf,jpg,jpeg,png,docx
          </small>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:4px;">
          <label class="mb-check-row">
            <input type="checkbox" name="wajib" id="mb-jenis-wajib">
            <div>
              <div class="mb-check-label" style="color:#dc2626;">
                <i class="fa fa-asterisk" style="font-size:9px;"></i> Wajib
              </div>
              <div class="mb-check-sub">Harus dilengkapi</div>
            </div>
          </label>
          <label class="mb-check-row">
            <input type="checkbox" name="has_tgl_terbit" id="mb-jenis-has-terbit">
            <div>
              <div class="mb-check-label">
                <i class="fa fa-calendar-plus" style="color:#6366f1;font-size:10px;"></i> Tgl Terbit
              </div>
              <div class="mb-check-sub">Ada kolom tgl terbit</div>
            </div>
          </label>
          <label class="mb-check-row">
            <input type="checkbox" name="has_exp" id="mb-jenis-has-exp">
            <div>
              <div class="mb-check-label">
                <i class="fa fa-clock" style="color:#f59e0b;font-size:10px;"></i> Kadaluarsa
              </div>
              <div class="mb-check-sub">Ada tgl exp</div>
            </div>
          </label>
        </div>

      </div>
      <div class="mb-mft">
        <button type="button" class="btn btn-default" onclick="mbCloseJenis()">
          <i class="fa fa-times"></i> Batal
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-save"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>


<!-- ══════════════════════════════════════
     MODAL KATEGORI
══════════════════════════════════════ -->
<div id="mb-kat-modal" class="mb-overlay">
  <div class="mb-modal-box sm">
    <div class="mb-mhead">
      <div class="mb-mhead-icon"><i class="fa fa-layer-group text-primary"></i></div>
      <div class="mb-mhead-title" id="mb-kat-title">Tambah Kategori</div>
      <button type="button" class="mb-mclose" onclick="mbCloseKategori()"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" id="mb-kat-form">
      <input type="hidden" name="action" value="simpan_kategori">
      <input type="hidden" name="id" id="mb-kat-id" value="">
      <div class="mb-mbody">

        <div class="mb-fg">
          <label>Nama Kategori <span style="color:#dc2626;">*</span></label>
          <input type="text" name="nama" id="mb-kat-nama" required
                 placeholder="Misal: Lisensi & Registrasi">
        </div>

        <div class="mb-g2">
          <div class="mb-fg">
            <label>Icon (Font Awesome)</label>
            <select name="icon" id="mb-kat-icon" onchange="mbPreviewKat()">
              <?php foreach ($icon_options as $cls => $lbl): ?>
              <option value="<?= $cls ?>"><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-fg">
            <label>Urutan</label>
            <input type="number" name="urutan" id="mb-kat-urutan" min="0" max="99" value="0">
          </div>
        </div>

        <div class="mb-fg">
          <label>Warna Kategori</label>
          <input type="hidden" name="warna" id="mb-kat-warna" value="#6366f1">
          <div class="warna-picker" id="mb-warna-picker">
            <?php foreach ($warna_options as $hex => $nm): ?>
            <div class="warna-opt <?= $hex === '#6366f1' ? 'selected' : '' ?>"
                 style="background:<?= $hex ?>;" title="<?= $nm ?>"
                 onclick="mbPilihWarna('<?= $hex ?>')"></div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Preview -->
        <div class="icon-preview">
          <div class="icon-prev-box" id="mb-kat-prev-box" style="background:#6366f120;">
            <i class="fa fa-layer-group" id="mb-kat-prev-i" style="color:#6366f1;font-size:15px;"></i>
          </div>
          <span style="font-size:12px;color:#374151;font-weight:600;" id="mb-kat-prev-label">Kategori</span>
        </div>

      </div>
      <div class="mb-mft">
        <button type="button" class="btn btn-default" onclick="mbCloseKategori()">
          <i class="fa fa-times"></i> Batal
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-save"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>


<script>
// ══════════════════════════════════════════════════════
// SEMUA FUNGSI DIBERI PREFIX mb agar tidak konflik
// dengan openModal / closeModal di header.php
// ══════════════════════════════════════════════════════

// ── Tab switch ────────────────────────────────────────
function mbSwitchTab(tab) {
  document.getElementById('tab-jenis').style.display    = tab === 'jenis'    ? '' : 'none';
  document.getElementById('tab-kategori').style.display = tab === 'kategori' ? '' : 'none';
  document.querySelectorAll('.mb-tab').forEach(function (btn, i) {
    btn.classList.toggle('active', (i === 0) === (tab === 'jenis'));
  });
}

// ── Helper buka/tutup overlay ─────────────────────────
function mbOpen(id)  {
  var el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function mbClose(id) {
  var el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
// Tutup jika klik di luar modal
document.addEventListener('click', function (e) {
  if (e.target && e.target.classList.contains('mb-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.mb-overlay.open').forEach(function (m) {
      m.classList.remove('open');
    });
    document.body.style.overflow = '';
  }
});

// ── MODAL JENIS BERKAS ────────────────────────────────
function mbOpenJenis() {
  document.getElementById('mb-jenis-id').value        = '';
  document.getElementById('mb-jenis-nama').value      = '';
  document.getElementById('mb-jenis-keterangan').value= '';
  document.getElementById('mb-jenis-kategori').value  = '';
  document.getElementById('mb-jenis-icon').value      = 'fa-file';
  document.getElementById('mb-jenis-urutan').value    = '0';
  document.getElementById('mb-jenis-format').value    = 'pdf,jpg,jpeg,png';
  document.getElementById('mb-jenis-wajib').checked       = false;
  document.getElementById('mb-jenis-has-exp').checked     = false;
  document.getElementById('mb-jenis-has-terbit').checked  = false;
  document.getElementById('mb-jenis-title').textContent   = 'Tambah Jenis Berkas';
  mbPreviewIcon();
  mbOpen('mb-jenis-modal');
}
function mbCloseJenis() { mbClose('mb-jenis-modal'); }

function mbEditJenis(data) {
  document.getElementById('mb-jenis-id').value        = data.id;
  document.getElementById('mb-jenis-nama').value      = data.nama;
  document.getElementById('mb-jenis-keterangan').value= data.keterangan || '';
  document.getElementById('mb-jenis-kategori').value  = data.kategori_id;
  document.getElementById('mb-jenis-icon').value      = data.icon || 'fa-file';
  document.getElementById('mb-jenis-urutan').value    = data.urutan || 0;
  document.getElementById('mb-jenis-format').value    = data.format_file || 'pdf,jpg,jpeg,png';
  document.getElementById('mb-jenis-wajib').checked       = data.wajib == 1;
  document.getElementById('mb-jenis-has-exp').checked     = data.has_exp == 1;
  document.getElementById('mb-jenis-has-terbit').checked  = data.has_tgl_terbit == 1;
  document.getElementById('mb-jenis-title').textContent   = 'Edit: ' + data.nama;
  mbPreviewIcon();
  mbOpen('mb-jenis-modal');
}

function mbPreviewIcon() {
  var icon = document.getElementById('mb-jenis-icon').value || 'fa-file';
  document.getElementById('mb-icon-prev-i').className = 'fa ' + icon;
}

// ── MODAL KATEGORI ────────────────────────────────────
var _mbWarna = '#6366f1';

function mbOpenKategori() {
  document.getElementById('mb-kat-id').value     = '';
  document.getElementById('mb-kat-nama').value   = '';
  document.getElementById('mb-kat-icon').value   = 'fa-folder';
  document.getElementById('mb-kat-urutan').value = '0';
  mbPilihWarna('#6366f1');
  document.getElementById('mb-kat-title').textContent = 'Tambah Kategori';
  mbPreviewKat();
  mbOpen('mb-kat-modal');
}
function mbCloseKategori() { mbClose('mb-kat-modal'); }

function mbEditKategori(data) {
  document.getElementById('mb-kat-id').value     = data.id;
  document.getElementById('mb-kat-nama').value   = data.nama;
  document.getElementById('mb-kat-icon').value   = data.icon || 'fa-folder';
  document.getElementById('mb-kat-urutan').value = data.urutan || 0;
  mbPilihWarna(data.warna || '#6366f1');
  document.getElementById('mb-kat-title').textContent = 'Edit: ' + data.nama;
  mbPreviewKat();
  mbOpen('mb-kat-modal');
}

function mbPilihWarna(hex) {
  _mbWarna = hex;
  document.getElementById('mb-kat-warna').value = hex;
  document.querySelectorAll('.warna-opt').forEach(function (el) {
    var bg = el.style.backgroundColor;
    el.classList.toggle('selected', mbRgbToHex(bg) === hex.toLowerCase());
  });
  mbPreviewKat();
}

function mbPreviewKat() {
  var icon  = document.getElementById('mb-kat-icon').value || 'fa-folder';
  var nama  = document.getElementById('mb-kat-nama').value || 'Kategori';
  document.getElementById('mb-kat-prev-i').className         = 'fa ' + icon;
  document.getElementById('mb-kat-prev-i').style.color       = _mbWarna;
  document.getElementById('mb-kat-prev-box').style.background= _mbWarna + '20';
  document.getElementById('mb-kat-prev-label').textContent   = nama;
}

document.getElementById('mb-kat-nama').addEventListener('input', mbPreviewKat);
document.getElementById('mb-kat-icon').addEventListener('change', mbPreviewKat);

function mbRgbToHex(rgb) {
  if (!rgb) return '';
  var r = rgb.match(/\d+/g);
  if (!r || r.length < 3) return rgb.toLowerCase();
  return '#' + r.slice(0, 3).map(function (v) {
    return ('0' + parseInt(v).toString(16)).slice(-2);
  }).join('');
}
</script>

<?php include '../includes/footer.php'; ?>