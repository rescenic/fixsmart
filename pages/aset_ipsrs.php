<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title  = 'Aset IPSRS';
$active_menu = 'aset_ipsrs';

// ── Helper: generate nomor inventaris ────────────────────────────────────────
function generateNoInventarisIPSRS(PDO $pdo): string {
    $tahun = date('Y');
    $last  = $pdo->query("SELECT no_inventaris FROM aset_ipsrs ORDER BY id DESC LIMIT 1")->fetchColumn();
    $seq   = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) $seq = (int)$m[1] + 1;
    return 'INV-IPSRS-' . $tahun . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    if ($act === 'tambah' || $act === 'edit') {
        $auto_inv           = ($_POST['auto_inv'] ?? '0') === '1';
        $no_inv             = $auto_inv ? generateNoInventarisIPSRS($pdo) : trim($_POST['no_inventaris'] ?? '');
        $jenis_aset         = trim($_POST['jenis_aset']              ?? 'Non-Medis');
        $nama               = trim($_POST['nama_aset']               ?? '');
        $kategori           = trim($_POST['kategori']                ?? '');
        $merek              = trim($_POST['merek']                   ?? '');
        $model              = trim($_POST['model_aset']              ?? '');
        $serial             = trim($_POST['serial_number']           ?? '');
        $no_aset_rs         = trim($_POST['no_aset_rs']              ?? '');
        $kondisi            = trim($_POST['kondisi']                 ?? 'Baik');
        $status_pakai       = trim($_POST['status_pakai']            ?? 'Terpakai');
        $bagian_id          = (int)($_POST['bagian_id']              ?? 0) ?: null;
        $pj_user_id         = (int)($_POST['pj_user_id']             ?? 0) ?: null;
        $tgl_beli           = $_POST['tanggal_beli']                 ?: null;
        $harga              = strlen($_POST['harga_beli'] ?? '') ? (int)$_POST['harga_beli'] : null;
        $sumber_dana        = trim($_POST['sumber_dana']             ?? '');
        $no_bast            = trim($_POST['no_bast']                 ?? '');
        $garansi            = $_POST['garansi_sampai']               ?: null;
        $tgl_kal_terakhir   = $_POST['tgl_kalibrasi_terakhir']       ?: null;
        $tgl_kal_berikutnya = $_POST['tgl_kalibrasi_berikutnya']     ?: null;
        $no_sertifikat_kal  = trim($_POST['no_sertifikat_kalibrasi'] ?? '');
        $tgl_svc_terakhir   = $_POST['tgl_service_terakhir']         ?: null;
        $tgl_svc_berikutnya = $_POST['tgl_service_berikutnya']       ?: null;
        $vendor_service     = trim($_POST['vendor_service']          ?? '');
        $keterangan         = trim($_POST['keterangan']              ?? '');

        $lokasi_teks = '';
        if ($bagian_id) {
            $s = $pdo->prepare("SELECT nama FROM bagian WHERE id=?");
            $s->execute([$bagian_id]);
            $lokasi_teks = $s->fetchColumn() ?: '';
        }
        $pj_nama = '';
        if ($pj_user_id) {
            $s = $pdo->prepare("SELECT nama FROM users WHERE id=?");
            $s->execute([$pj_user_id]);
            $pj_nama = $s->fetchColumn() ?: '';
        }

        if ($act === 'tambah') {
            $pdo->prepare("INSERT INTO aset_ipsrs
                (no_inventaris, jenis_aset, nama_aset, kategori, merek, model_aset,
                 serial_number, no_aset_rs, kondisi, status_pakai,
                 bagian_id, lokasi, pj_user_id, penanggung_jawab,
                 tanggal_beli, harga_beli, sumber_dana, no_bast, garansi_sampai,
                 tgl_kalibrasi_terakhir, tgl_kalibrasi_berikutnya, no_sertifikat_kalibrasi,
                 tgl_service_terakhir, tgl_service_berikutnya, vendor_service,
                 keterangan, created_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([
                    $no_inv,$jenis_aset,$nama,$kategori,$merek,$model,
                    $serial,$no_aset_rs,$kondisi,$status_pakai,
                    $bagian_id,$lokasi_teks,$pj_user_id,$pj_nama,
                    $tgl_beli,$harga,$sumber_dana,$no_bast,$garansi,
                    $tgl_kal_terakhir,$tgl_kal_berikutnya,$no_sertifikat_kal,
                    $tgl_svc_terakhir,$tgl_svc_berikutnya,$vendor_service,
                    $keterangan,$_SESSION['user_id']
                ]);
            setFlash('success','Aset <strong>'.htmlspecialchars($nama).'</strong> berhasil ditambahkan dengan nomor <code>'.$no_inv.'</code>.');
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE aset_ipsrs SET
                no_inventaris=?,jenis_aset=?,nama_aset=?,kategori=?,merek=?,model_aset=?,
                serial_number=?,no_aset_rs=?,kondisi=?,status_pakai=?,
                bagian_id=?,lokasi=?,pj_user_id=?,penanggung_jawab=?,
                tanggal_beli=?,harga_beli=?,sumber_dana=?,no_bast=?,garansi_sampai=?,
                tgl_kalibrasi_terakhir=?,tgl_kalibrasi_berikutnya=?,no_sertifikat_kalibrasi=?,
                tgl_service_terakhir=?,tgl_service_berikutnya=?,vendor_service=?,
                keterangan=?,updated_at=NOW()
                WHERE id=?")
                ->execute([
                    $no_inv,$jenis_aset,$nama,$kategori,$merek,$model,
                    $serial,$no_aset_rs,$kondisi,$status_pakai,
                    $bagian_id,$lokasi_teks,$pj_user_id,$pj_nama,
                    $tgl_beli,$harga,$sumber_dana,$no_bast,$garansi,
                    $tgl_kal_terakhir,$tgl_kal_berikutnya,$no_sertifikat_kal,
                    $tgl_svc_terakhir,$tgl_svc_berikutnya,$vendor_service,
                    $keterangan,$id
                ]);
            setFlash('success','Aset berhasil diperbarui.');
        }
        redirect(APP_URL.'/pages/aset_ipsrs.php');
    }

    if ($act === 'hapus' && hasRole('admin')) {
        $pdo->prepare("DELETE FROM aset_ipsrs WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success','Aset berhasil dihapus.');
        redirect(APP_URL.'/pages/aset_ipsrs.php');
    }
}

// ── AJAX ─────────────────────────────────────────────────────────────────────
if (isset($_GET['get_aset'])) {
    $s = $pdo->prepare("SELECT * FROM aset_ipsrs WHERE id=?");
    $s->execute([(int)$_GET['get_aset']]);
    header('Content-Type: application/json');
    echo json_encode($s->fetch(PDO::FETCH_ASSOC));
    exit;
}
if (isset($_GET['preview_no_inv'])) {
    header('Content-Type: application/json');
    echo json_encode(['no' => generateNoInventarisIPSRS($pdo)]);
    exit;
}

// ── Pagination & Filter ───────────────────────────────────────────────────────
$page     = max(1,(int)($_GET['page']         ?? 1));
$per_page = 15;
$fk       = $_GET['kategori']     ?? '';
$fkondisi = $_GET['kondisi']      ?? '';
$fstatus  = $_GET['status_pakai'] ?? '';
$fjenis   = $_GET['jenis_aset']   ?? '';
$search   = $_GET['q']            ?? '';

$where = ['1=1']; $params = [];
if ($fk)       { $where[] = 'a.kategori=?';    $params[] = $fk; }
if ($fkondisi) { $where[] = 'a.kondisi=?';      $params[] = $fkondisi; }
if ($fstatus)  { $where[] = 'a.status_pakai=?'; $params[] = $fstatus; }
if ($fjenis)   { $where[] = 'a.jenis_aset=?';   $params[] = $fjenis; }
if ($search)   {
    $where[]  = '(a.no_inventaris LIKE ? OR a.no_aset_rs LIKE ? OR a.nama_aset LIKE ? OR a.merek LIKE ? OR b.nama LIKE ? OR u.nama LIKE ?)';
    $params   = array_merge($params, array_fill(0, 6, "%$search%"));
}
$wsql = implode(' AND ', $where);

$st_cnt = $pdo->prepare("SELECT COUNT(*) FROM aset_ipsrs a LEFT JOIN bagian b ON b.id=a.bagian_id LEFT JOIN users u ON u.id=a.pj_user_id WHERE $wsql");
$st_cnt->execute($params);
$total  = (int)$st_cnt->fetchColumn();
$pages  = max(1,ceil($total/$per_page));
$page   = min($page,$pages);
$offset = ($page-1)*$per_page;

$st_data = $pdo->prepare("
    SELECT a.*,
           b.nama AS bagian_nama, b.kode AS bagian_kode, b.lokasi AS bagian_lokasi,
           u.nama AS pj_nama_db, u.divisi AS pj_divisi
    FROM aset_ipsrs a
    LEFT JOIN bagian b ON b.id = a.bagian_id
    LEFT JOIN users  u ON u.id = a.pj_user_id
    WHERE $wsql ORDER BY a.id DESC
    LIMIT $per_page OFFSET $offset");
$st_data->execute($params);
$asets = $st_data->fetchAll();

// Stats
$stats_kondisi = [];
foreach ($pdo->query("SELECT kondisi, COUNT(*) n FROM aset_ipsrs GROUP BY kondisi")->fetchAll() as $r)
    $stats_kondisi[$r['kondisi']] = $r['n'];
$total_all = array_sum($stats_kondisi);

$stats_pakai = [];
foreach ($pdo->query("SELECT status_pakai, COUNT(*) n FROM aset_ipsrs GROUP BY status_pakai")->fetchAll() as $r)
    $stats_pakai[$r['status_pakai']] = $r['n'];

$stats_jenis = [];
foreach ($pdo->query("SELECT jenis_aset, COUNT(*) n FROM aset_ipsrs GROUP BY jenis_aset")->fetchAll() as $r)
    $stats_jenis[$r['jenis_aset']] = $r['n'];

$jml_kal_exp = (int)$pdo->query("SELECT COUNT(*) FROM aset_ipsrs WHERE tgl_kalibrasi_berikutnya IS NOT NULL AND tgl_kalibrasi_berikutnya < CURDATE()")->fetchColumn();
$jml_kal_due = (int)$pdo->query("SELECT COUNT(*) FROM aset_ipsrs WHERE tgl_kalibrasi_berikutnya IS NOT NULL AND tgl_kalibrasi_berikutnya BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$jml_svc_due = (int)$pdo->query("SELECT COUNT(*) FROM aset_ipsrs WHERE tgl_service_berikutnya IS NOT NULL AND tgl_service_berikutnya BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

// Dropdown
$bagian_list = $pdo->query("SELECT id,nama,kode,lokasi FROM bagian WHERE status='aktif' ORDER BY urutan,nama")->fetchAll();
$users_list  = $pdo->query("SELECT id,nama,divisi,role FROM users WHERE status='aktif' AND role IN ('teknisi_ipsrs','admin') ORDER BY nama")->fetchAll();
$kat_opts    = $pdo->query("SELECT DISTINCT kategori FROM aset_ipsrs WHERE kategori IS NOT NULL AND kategori!='' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

$kat_medis     = ['Peralatan Diagnostik','Peralatan Terapi','Peralatan Bedah','Peralatan Laboratorium','Peralatan Radiologi','Peralatan ICU/ICCU','Peralatan Sterilisasi','Alat Bantu Pasien','Inkubator / Infant Warmer','Defibrilator / AED','Ventilator','Infus Pump / Syringe Pump','Peralatan Gigi','Peralatan Mata','USG / Imaging','Endoskopi','Lainnya (Medis)'];
$kat_non_medis = ['Peralatan Listrik','Generator / Panel Listrik','Pompa / Kompresor','Peralatan HVAC / AC','Peralatan Sanitasi / Plumbing','Peralatan Dapur / Gizi','Peralatan Laundry','Peralatan Kebersihan','Peralatan Keamanan / CCTV','Kendaraan / Ambulans','Alat Angkat / Angkut','Peralatan Las / Bengkel','Furniture / Mebel','Lainnya (Non-Medis)'];

include '../includes/header.php';
?>

<style>
.kondisi-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
.kb-baik      {background:#dcfce7;color:#166534;}
.kb-rusak     {background:#fee2e2;color:#991b1b;}
.kb-perbaikan {background:#fef9c3;color:#854d0e;}
.kb-tidak     {background:#f1f5f9;color:#64748b;}
.sp-terpakai  {background:#dbeafe;color:#1e40af;}
.sp-tidak     {background:#d1fae5;color:#065f46;}
.sp-dipinjam  {background:#fef3c7;color:#92400e;}
.jenis-medis     {display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;font-size:10.5px;font-weight:700;background:#fce7f3;color:#9d174d;white-space:nowrap;}
.jenis-non-medis {display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;font-size:10.5px;font-weight:700;background:#eff6ff;color:#1e40af;white-space:nowrap;}

tr.row-tidak-terpakai{background:linear-gradient(90deg,#f0fdf4,#f7fffe) !important;}
tr.row-tidak-terpakai td{background:transparent !important;opacity:.82;}
tr.row-tidak-terpakai td:first-child{border-left:4px solid #22c55e;}
tr.row-tidak-terpakai .nama-aset-txt{color:#6b7280 !important;text-decoration:line-through;text-decoration-color:#86efac;}
tr.row-tidak-terpakai .inv-badge{background:linear-gradient(135deg,#f0fdf4,#dcfce7) !important;color:#15803d !important;border-color:#86efac !important;opacity:.8;}
tr.row-dipinjam{background:linear-gradient(90deg,#fffbeb,#fefce8) !important;}
tr.row-dipinjam td{background:transparent !important;}
tr.row-dipinjam td:first-child{border-left:4px solid #f59e0b;}
tr.row-kal-expired td:first-child{border-left:4px solid #ef4444 !important;}

.inv-badge{font-family:'Courier New',monospace;font-size:11px;font-weight:700;background:linear-gradient(135deg,#fff7ed,#ffedd5);color:#c2410c;border:1px solid #fed7aa;padding:2px 8px;border-radius:5px;white-space:nowrap;}
.stat-card-aset{background:#fff;border-radius:8px;border:1px solid #e8ecf0;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .2s;}
.stat-card-aset:hover{box-shadow:0 4px 12px rgba(0,0,0,.07);}
.stat-card-aset.active-filter{border-color:#f97316;box-shadow:0 0 0 2px rgba(249,115,22,.18);}
.stat-icon-aset{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.kal-exp{color:#ef4444;font-weight:700;} .kal-soon{color:#f59e0b;font-weight:700;} .kal-ok{color:#22c55e;} .kal-none{color:#cbd5e1;}
.alert-banner{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:7px;margin-bottom:14px;flex-wrap:wrap;}
.sp-legend-bar{display:flex;align-items:center;gap:16px;padding:9px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;margin-bottom:14px;flex-wrap:wrap;}
.sp-legend-item{display:flex;align-items:center;gap:6px;font-size:11.5px;color:#374151;}
.sp-legend-dot{width:12px;height:12px;border-radius:3px;flex-shrink:0;}
.inv-toggle-wrap{display:flex;align-items:center;gap:8px;padding:7px 11px;border-radius:6px;background:#f8fafc;border:1px solid #e2e8f0;cursor:pointer;user-select:none;transition:all .18s;flex-shrink:0;}
.inv-toggle-wrap:hover{border-color:#f97316;background:#fff7ed;}
.inv-sw{position:relative;width:34px;height:18px;flex-shrink:0;}
.inv-sw input{opacity:0;width:0;height:0;}
.inv-sl{position:absolute;inset:0;background:#cbd5e1;border-radius:34px;transition:.2s;cursor:pointer;}
.inv-sl:before{content:'';position:absolute;width:12px;height:12px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
.inv-sw input:checked~.inv-sl{background:#f97316;}
.inv-sw input:checked~.inv-sl:before{transform:translateX(16px);}
.f-label{font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px;}
.f-inp{width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:6px;font-size:12.5px;box-sizing:border-box;font-family:inherit;transition:border .18s;background:#fff;}
.f-inp:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12);}
.f-inp:disabled{background:#f8fafc;color:#94a3b8;cursor:not-allowed;}
.grs-modal{height:1px;background:#f0f0f0;margin:14px 0;}
.modal-sec{font-size:11px;font-weight:800;color:#fff;background:linear-gradient(135deg,#c2410c,#f97316);padding:5px 11px;border-radius:5px;margin:14px 0 10px;display:flex;align-items:center;gap:6px;}
.jenis-selector{display:flex;gap:8px;margin-bottom:14px;}
.jenis-btn{flex:1;padding:12px 10px;border:2px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;text-align:center;transition:all .18s;font-family:inherit;}
.jenis-btn .jb-icon{font-size:22px;margin-bottom:4px;}
.jenis-btn .jb-label{font-size:12.5px;font-weight:700;color:#374151;}
.jenis-btn .jb-sub{font-size:10.5px;color:#94a3b8;margin-top:2px;}
.jenis-btn.active-medis{border-color:#ec4899;background:#fdf2f8;box-shadow:0 0 0 3px rgba(236,72,153,.1);}
.jenis-btn.active-non-medis{border-color:#3b82f6;background:#eff6ff;box-shadow:0 0 0 3px rgba(59,130,246,.1);}
#f-status_pakai.val-tidak{border-color:#22c55e;background:#f0fdf4;color:#065f46;}
#f-status_pakai.val-dipinjam{border-color:#f59e0b;background:#fffbeb;color:#92400e;}
#f-status_pakai.val-terpakai{border-color:#3b82f6;background:#eff6ff;color:#1e40af;}
.cetak-drop-wrap{position:relative;display:inline-block;}
.cetak-drop-menu{display:none;position:absolute;right:0;top:36px;z-index:9999;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 8px 28px rgba(0,0,0,.13);min-width:248px;overflow:hidden;}
.cetak-drop-menu.open{display:block;}
.cdm-head{padding:8px 13px;background:#f8fafc;border-bottom:1px solid #e2e8f0;}
.cdm-head-txt{font-size:11px;font-weight:700;color:#374151;}
.cdm-section{padding:6px 13px 4px;font-size:9.5px;font-weight:700;color:#94a3b8;letter-spacing:1.2px;text-transform:uppercase;border-bottom:1px solid #f1f5f9;}
.cdm-item{display:flex;align-items:center;gap:10px;padding:9px 13px;text-decoration:none;color:#1e293b;border-bottom:1px solid #f1f5f9;transition:background .14s;}
.cdm-item:hover{background:#fff7ed;}
.cdm-icon{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;}
.cdm-item-sm{display:flex;align-items:center;gap:9px;padding:7px 13px;text-decoration:none;color:#1e293b;border-bottom:1px solid #f1f5f9;font-size:12px;transition:background .14s;}
.cdm-item-sm:hover{background:#fff7ed;}
.cdm-foot{padding:6px 13px;background:#f8fafc;border-top:1px solid #e2e8f0;}
.cdm-foot-txt{font-size:10px;color:#94a3b8;}
</style>

<div class="page-header">
  <h4><i class="fa fa-toolbox" style="color:#f97316;"></i> &nbsp;Aset IPSRS</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span><span class="cur">Aset IPSRS</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <?php if ($jml_kal_exp > 0 || $jml_kal_due > 0 || $jml_svc_due > 0): ?>
  <div class="alert-banner" style="background:#fff7ed;border:1px solid #fed7aa;">
    <i class="fa fa-triangle-exclamation" style="color:#f59e0b;font-size:18px;flex-shrink:0;"></i>
    <div style="flex:1;">
      <div style="font-weight:700;color:#92400e;font-size:13px;">Perhatian — Jadwal Pemeliharaan Aset</div>
      <div style="font-size:11.5px;color:#78350f;margin-top:3px;display:flex;gap:18px;flex-wrap:wrap;">
        <?php if($jml_kal_exp>0): ?><span><i class="fa fa-circle-xmark" style="color:#ef4444;"></i> <strong><?= $jml_kal_exp ?></strong> kalibrasi <span style="color:#ef4444;font-weight:700;">sudah expired</span></span><?php endif; ?>
        <?php if($jml_kal_due>0): ?><span><i class="fa fa-flask-vial" style="color:#f59e0b;"></i> <strong><?= $jml_kal_due ?></strong> kalibrasi jatuh tempo &lt;30 hari</span><?php endif; ?>
        <?php if($jml_svc_due>0): ?><span><i class="fa fa-wrench" style="color:#f59e0b;"></i> <strong><?= $jml_svc_due ?></strong> service jatuh tempo &lt;30 hari</span><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Stats Kondisi -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin-bottom:10px;">
    <div class="stat-card-aset">
      <div class="stat-icon-aset" style="background:#fff7ed;"><i class="fa fa-toolbox" style="color:#f97316;"></i></div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $total_all ?></div>
        <div style="font-size:11px;color:#94a3b8;">Total Aset</div>
        <div style="font-size:10px;margin-top:2px;">
          <span style="color:#ec4899;font-weight:700;">M:<?= $stats_jenis['Medis']??0 ?></span>
          <span style="color:#94a3b8;"> &middot; </span>
          <span style="color:#3b82f6;font-weight:700;">NM:<?= $stats_jenis['Non-Medis']??0 ?></span>
        </div>
      </div>
    </div>
    <?php foreach([
      ['Baik',           '#dcfce7','#22c55e','fa-circle-check'],
      ['Rusak',          '#fee2e2','#ef4444','fa-circle-xmark'],
      ['Dalam Perbaikan','#fef9c3','#f59e0b','fa-wrench'],
      ['Tidak Aktif',    '#f1f5f9','#94a3b8','fa-ban'],
    ] as [$k,$bg,$ic,$ico]): ?>
    <div class="stat-card-aset">
      <div class="stat-icon-aset" style="background:<?= $bg ?>;"><i class="fa <?= $ico ?>" style="color:<?= $ic ?>;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stats_kondisi[$k]??0 ?></div><div style="font-size:11px;color:#94a3b8;"><?= $k ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Stats Status Pakai + Jenis Filter -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin-bottom:16px;">
    <?php foreach([
      ['Terpakai',      '#dbeafe','#1d4ed8','fa-circle-dot',  'Aktif digunakan'],
      ['Tidak Terpakai','#d1fae5','#065f46','fa-circle',       'Belum/tidak digunakan'],
      ['Dipinjam',      '#fef3c7','#92400e','fa-hand-holding', 'Sedang dipinjam'],
    ] as [$k,$bg,$ic,$ico,$sub]):
      $isActive = ($fstatus===$k); ?>
    <a href="?status_pakai=<?= urlencode($isActive?'':$k) ?>&kondisi=<?= urlencode($fkondisi) ?>&jenis_aset=<?= urlencode($fjenis) ?>&q=<?= urlencode($search) ?>"
       class="stat-card-aset <?= $isActive?'active-filter':'' ?>" style="text-decoration:none;cursor:pointer;">
      <div class="stat-icon-aset" style="background:<?= $bg ?>;"><i class="fa <?= $ico ?>" style="color:<?= $ic ?>;"></i></div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stats_pakai[$k]??0 ?></div>
        <div style="font-size:11px;color:#94a3b8;"><?= $k ?></div>
        <div style="font-size:10px;color:#cbd5e1;"><?= $sub ?></div>
      </div>
      <?php if($isActive): ?><i class="fa fa-check-circle" style="margin-left:auto;color:#f97316;font-size:14px;"></i><?php endif; ?>
    </a>
    <?php endforeach; ?>

    <!-- Filter Medis -->
    <a href="?jenis_aset=<?= $fjenis==='Medis'?'':'Medis' ?>&kondisi=<?= urlencode($fkondisi) ?>&status_pakai=<?= urlencode($fstatus) ?>"
       class="stat-card-aset <?= $fjenis==='Medis'?'active-filter':'' ?>" style="text-decoration:none;cursor:pointer;border-color:<?= $fjenis==='Medis'?'#ec4899':'#e8ecf0' ?>;">
      <div class="stat-icon-aset" style="background:#fdf2f8;"><i class="fa fa-kit-medical" style="color:#ec4899;"></i></div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stats_jenis['Medis']??0 ?></div>
        <div style="font-size:11px;color:#94a3b8;">Medis</div>
        <div style="font-size:10px;color:#cbd5e1;">Peralatan medis</div>
      </div>
      <?php if($fjenis==='Medis'): ?><i class="fa fa-check-circle" style="margin-left:auto;color:#ec4899;font-size:14px;"></i><?php endif; ?>
    </a>

    <!-- Filter Non-Medis -->
    <a href="?jenis_aset=<?= $fjenis==='Non-Medis'?'':'Non-Medis' ?>&kondisi=<?= urlencode($fkondisi) ?>&status_pakai=<?= urlencode($fstatus) ?>"
       class="stat-card-aset <?= $fjenis==='Non-Medis'?'active-filter':'' ?>" style="text-decoration:none;cursor:pointer;border-color:<?= $fjenis==='Non-Medis'?'#3b82f6':'#e8ecf0' ?>;">
      <div class="stat-icon-aset" style="background:#eff6ff;"><i class="fa fa-screwdriver-wrench" style="color:#3b82f6;"></i></div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stats_jenis['Non-Medis']??0 ?></div>
        <div style="font-size:11px;color:#94a3b8;">Non-Medis</div>
        <div style="font-size:10px;color:#cbd5e1;">Sarana & prasarana</div>
      </div>
      <?php if($fjenis==='Non-Medis'): ?><i class="fa fa-check-circle" style="margin-left:auto;color:#3b82f6;font-size:14px;"></i><?php endif; ?>
    </a>
  </div>

  <!-- Legend -->
  <div class="sp-legend-bar">
    <span style="font-size:11px;font-weight:700;color:#374151;margin-right:4px;"><i class="fa fa-palette" style="color:#f97316;"></i> Keterangan warna:</span>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#fff;border:1.5px solid #e2e8f0;"></div> Normal = <strong>Terpakai</strong></div>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#d1fae5;border:1.5px solid #22c55e;"></div> Hijau + <s>coret</s> = <strong>Tidak Terpakai</strong></div>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#fef3c7;border:1.5px solid #f59e0b;"></div> Kuning = <strong>Dipinjam</strong></div>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#fee2e2;border:1.5px solid #ef4444;"></div> Merah kiri = <strong>Kalibrasi Expired</strong></div>
    <div style="margin-left:auto;display:flex;gap:6px;">
      <span class="jenis-medis"><i class="fa fa-kit-medical"></i> Medis</span>
      <span class="jenis-non-medis"><i class="fa fa-screwdriver-wrench"></i> Non-Medis</span>
    </div>
  </div>

  <!-- Quick filter kondisi -->
  <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
    <?php foreach([''=> 'Semua','Baik'=>'Baik','Rusak'=>'Rusak','Dalam Perbaikan'=>'Dalam Perbaikan','Tidak Aktif'=>'Tidak Aktif'] as $v=>$l):
      $cnt=$v===''?$total_all:($stats_kondisi[$v]??0); ?>
    <a href="?kondisi=<?= urlencode($v) ?>&status_pakai=<?= urlencode($fstatus) ?>&jenis_aset=<?= urlencode($fjenis) ?>"
       class="btn <?= $fkondisi===$v?'btn-primary':'btn-default' ?>" style="font-size:12px;">
      <?= $l ?> <span style="background:<?= $fkondisi===$v?'rgba(255,255,255,.3)':'#ddd' ?>;border-radius:9px;padding:0 6px;font-size:10px;"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf-ipsrs" style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">
          <?php if($fkondisi): ?><input type="hidden" name="kondisi"      value="<?= clean($fkondisi) ?>"><?php endif; ?>
          <?php if($fstatus):  ?><input type="hidden" name="status_pakai" value="<?= clean($fstatus) ?>"><?php endif; ?>
          <?php if($fjenis):   ?><input type="hidden" name="jenis_aset"   value="<?= clean($fjenis) ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search" placeholder="Cari nama, no. inv, no. aset RS…" onchange="document.getElementById('sf-ipsrs').submit()">
          <select name="kategori" class="sel-filter" onchange="document.getElementById('sf-ipsrs').submit()">
            <option value="">Semua Kategori</option>
            <?php foreach($kat_opts as $k): ?>
            <option value="<?= clean($k) ?>" <?= $fk===$k?'selected':'' ?>><?= clean($k) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="status_pakai" class="sel-filter" onchange="document.getElementById('sf-ipsrs').submit()" style="border-color:<?= $fstatus?'#f97316':'#d1d5db' ?>;">
            <option value="">Semua Status</option>
            <option value="Terpakai"       <?= $fstatus==='Terpakai'       ?'selected':'' ?>>🔵 Terpakai</option>
            <option value="Tidak Terpakai" <?= $fstatus==='Tidak Terpakai' ?'selected':'' ?>>🟢 Tidak Terpakai</option>
            <option value="Dipinjam"       <?= $fstatus==='Dipinjam'       ?'selected':'' ?>>🟡 Dipinjam</option>
          </select>
          <?php if($search||$fk||$fstatus||$fjenis): ?>
          <a href="?kondisi=<?= urlencode($fkondisi) ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Reset</a>
          <?php endif; ?>
        </form>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="tbl-info">
          <?= $total ?> aset
          <?php if($fjenis): ?><span style="background:<?= $fjenis==='Medis'?'#fdf2f8':'#eff6ff' ?>;color:<?= $fjenis==='Medis'?'#9d174d':'#1e40af' ?>;padding:1px 8px;border-radius:9px;font-size:10px;font-weight:700;margin-left:4px;"><?= clean($fjenis) ?></span><?php endif; ?>
        </span>

        <!-- Dropdown Cetak -->
        <div class="cetak-drop-wrap" id="wrap-cetak">
          <button type="button" onclick="toggleCetakDrop(event)" class="btn btn-default btn-sm" style="border-color:#f97316;color:#c2410c;font-weight:600;">
            <i class="fa fa-print"></i> Cetak <i class="fa fa-chevron-down" style="font-size:9px;margin-left:3px;"></i>
          </button>
          <div class="cetak-drop-menu" id="cetak-drop">
            <div class="cdm-head"><div class="cdm-head-txt"><i class="fa fa-file-pdf" style="color:#ef4444;margin-right:5px;"></i> Laporan Aset IPSRS</div></div>
            <a href="<?= APP_URL ?>/pages/cetak_aset_ipsrs.php?mode=semua" target="_blank" class="cdm-item">
              <div class="cdm-icon" style="background:#fff7ed;"><i class="fa fa-toolbox" style="color:#f97316;"></i></div>
              <div><div style="font-size:12.5px;font-weight:700;">Semua Aset IPSRS</div><div style="font-size:10.5px;color:#94a3b8;">Laporan lengkap medis & non-medis</div></div>
              <i class="fa fa-arrow-up-right-from-square" style="margin-left:auto;color:#cbd5e1;font-size:10px;"></i>
            </a>
            <a href="<?= APP_URL ?>/pages/cetak_aset_ipsrs.php?mode=semua&jenis_aset=Medis" target="_blank" class="cdm-item">
              <div class="cdm-icon" style="background:#fdf2f8;"><i class="fa fa-kit-medical" style="color:#ec4899;"></i></div>
              <div><div style="font-size:12.5px;font-weight:700;">Aset Medis</div><div style="font-size:10.5px;color:#94a3b8;">Khusus peralatan medis</div></div>
              <i class="fa fa-arrow-up-right-from-square" style="margin-left:auto;color:#cbd5e1;font-size:10px;"></i>
            </a>
            <a href="<?= APP_URL ?>/pages/cetak_aset_ipsrs.php?mode=semua&jenis_aset=Non-Medis" target="_blank" class="cdm-item">
              <div class="cdm-icon" style="background:#eff6ff;"><i class="fa fa-screwdriver-wrench" style="color:#3b82f6;"></i></div>
              <div><div style="font-size:12.5px;font-weight:700;">Aset Non-Medis</div><div style="font-size:10.5px;color:#94a3b8;">Sarana, prasarana & utilitas</div></div>
              <i class="fa fa-arrow-up-right-from-square" style="margin-left:auto;color:#cbd5e1;font-size:10px;"></i>
            </a>
            <a href="<?= APP_URL ?>/pages/cetak_aset_ipsrs.php?mode=semua&status_pakai=Tidak+Terpakai" target="_blank" class="cdm-item">
              <div class="cdm-icon" style="background:#d1fae5;"><i class="fa fa-circle" style="color:#16a34a;"></i></div>
              <div><div style="font-size:12.5px;font-weight:700;">Tidak Terpakai</div><div style="font-size:10.5px;color:#94a3b8;">Aset yang belum/tidak digunakan</div></div>
              <i class="fa fa-arrow-up-right-from-square" style="margin-left:auto;color:#cbd5e1;font-size:10px;"></i>
            </a>
            <a href="<?= APP_URL ?>/pages/cetak_aset_ipsrs.php?mode=kalibrasi" target="_blank" class="cdm-item">
              <div class="cdm-icon" style="background:#fef9c3;"><i class="fa fa-flask-vial" style="color:#d97706;"></i></div>
              <div><div style="font-size:12.5px;font-weight:700;">Jadwal Kalibrasi</div><div style="font-size:10.5px;color:#94a3b8;">Kalibrasi jatuh tempo / expired</div></div>
              <i class="fa fa-arrow-up-right-from-square" style="margin-left:auto;color:#cbd5e1;font-size:10px;"></i>
            </a>
            <?php if(!empty($kat_opts)): ?>
            <div class="cdm-section">Per Kategori</div>
            <?php foreach($kat_opts as $k): ?>
            <a href="<?= APP_URL ?>/pages/cetak_aset_ipsrs.php?mode=kategori&kategori=<?= urlencode($k) ?>" target="_blank" class="cdm-item-sm">
              <div style="width:22px;height:22px;border-radius:5px;background:#fff7ed;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa fa-tag" style="color:#f97316;font-size:10px;"></i>
              </div>
              <span style="font-weight:600;"><?= clean($k) ?></span>
              <i class="fa fa-arrow-up-right-from-square" style="color:#e2e8f0;font-size:9px;margin-left:auto;"></i>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
            <div class="cdm-section">Filter Kondisi</div>
            <?php foreach(['Baik'=>['fa-circle-check','#16a34a','#dcfce7'],'Rusak'=>['fa-circle-xmark','#dc2626','#fee2e2'],'Dalam Perbaikan'=>['fa-wrench','#d97706','#fef9c3'],'Tidak Aktif'=>['fa-ban','#64748b','#f1f5f9']] as $kond=>[$kico,$kcol,$kbg]): ?>
            <a href="<?= APP_URL ?>/pages/cetak_aset_ipsrs.php?mode=semua&kondisi=<?= urlencode($kond) ?>" target="_blank" class="cdm-item-sm">
              <div style="width:22px;height:22px;border-radius:50%;background:<?= $kbg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fa <?= $kico ?>" style="color:<?= $kcol ?>;font-size:10px;"></i></div>
              <span>Kondisi: <strong><?= $kond ?></strong></span>
              <span style="margin-left:auto;font-size:10.5px;background:<?= $kbg ?>;color:<?= $kcol ?>;padding:1px 7px;border-radius:9px;font-weight:700;"><?= $stats_kondisi[$kond]??0 ?></span>
            </a>
            <?php endforeach; ?>
            <div class="cdm-foot"><div class="cdm-foot-txt"><i class="fa fa-circle-info" style="color:#f97316;"></i> Laporan terbuka di tab baru sebagai PDF</div></div>
          </div>
        </div>

        <?php if(hasRole(['admin','teknisi_ipsrs'])): ?>
        <button onclick="bukaModalTambah()" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Tambah Aset</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tabel -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:32px;">#</th>
            <th>No. Inventaris</th>
            <th>No. Aset RS</th>
            <th>Nama Aset</th>
            <th>Jenis / Kategori</th>
            <th>Merek / Model</th>
            <th>Lokasi / Instalasi</th>
            <th>Penanggung Jawab</th>
            <th>Status Pakai</th>
            <th>Kondisi</th>
            <th>Kalibrasi Berikutnya</th>
            <th>Service Berikutnya</th>
            <th style="width:90px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($asets)): ?>
          <tr><td colspan="13" class="td-empty"><i class="fa fa-toolbox"></i> Tidak ada data aset IPSRS</td></tr>
          <?php else: $no=$offset+1; foreach($asets as $a):
            $sp    = $a['status_pakai'] ?? 'Terpakai';
            $spmap = ['Terpakai'=>'sp-terpakai','Tidak Terpakai'=>'sp-tidak','Dipinjam'=>'sp-dipinjam'];
            $spico = ['Terpakai'=>'fa-circle-dot','Tidak Terpakai'=>'fa-circle','Dipinjam'=>'fa-hand-holding'];
            $spc   = $spmap[$sp] ?? 'sp-terpakai';
            $spi   = $spico[$sp] ?? 'fa-circle-dot';
            $kmap  = ['Baik'=>'kb-baik','Rusak'=>'kb-rusak','Dalam Perbaikan'=>'kb-perbaikan','Tidak Aktif'=>'kb-tidak'];
            $kico  = ['Baik'=>'fa-circle-check','Rusak'=>'fa-circle-xmark','Dalam Perbaikan'=>'fa-wrench','Tidak Aktif'=>'fa-ban'];
            $kc    = $kmap[$a['kondisi']] ?? 'kb-tidak';
            $ki    = $kico[$a['kondisi']] ?? 'fa-circle';
            $kal_next = $a['tgl_kalibrasi_berikutnya'] ?? null;
            $kal_exp  = $kal_next && strtotime($kal_next) < time();
            $kal_soon = $kal_next && !$kal_exp && strtotime($kal_next) < strtotime('+30 days');
            $svc_next = $a['tgl_service_berikutnya'] ?? null;
            $svc_exp  = $svc_next && strtotime($svc_next) < time();
            $svc_soon = $svc_next && !$svc_exp && strtotime($svc_next) < strtotime('+30 days');
            $row_class = '';
            if ($sp === 'Tidak Terpakai')   $row_class = 'row-tidak-terpakai';
            elseif ($sp === 'Dipinjam')     $row_class = 'row-dipinjam';
            elseif ($kal_exp)               $row_class = 'row-kal-expired';
            $lokasi_disp = $a['bagian_nama']
                ? ($a['bagian_kode']?'['.clean($a['bagian_kode']).'] ':'').clean($a['bagian_nama'])
                : clean($a['lokasi']?:'—');
            $pj_disp = $a['pj_nama_db'] ?: clean($a['penanggung_jawab']?:'—');
            $pj_init = $a['pj_nama_db'] ? getInitials($a['pj_nama_db']) : ($a['penanggung_jawab']?getInitials($a['penanggung_jawab']):'?');
            $jenis   = $a['jenis_aset'] ?? 'Non-Medis';
          ?>
          <tr class="<?= $row_class ?>">
            <td style="color:#bbb;"><?= $no++ ?></td>
            <td><span class="inv-badge"><?= clean($a['no_inventaris']) ?></span></td>
            <td>
              <?php if($a['no_aset_rs']): ?>
                <span style="font-family:monospace;font-size:11px;background:#f1f5f9;color:#475569;padding:2px 6px;border-radius:4px;"><?= clean($a['no_aset_rs']) ?></span>
              <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
            </td>
            <td>
              <div class="nama-aset-txt" style="font-weight:600;color:#1e293b;font-size:13px;"><?= clean($a['nama_aset']) ?></div>
              <?php if($a['keterangan']): ?><small style="color:#aaa;font-size:10.5px;"><?= mb_strimwidth(clean($a['keterangan']),0,40,'…') ?></small><?php endif; ?>
              <?php if($sp==='Tidak Terpakai'): ?><div style="margin-top:2px;"><span style="font-size:9.5px;background:#d1fae5;color:#065f46;padding:1px 6px;border-radius:3px;font-weight:700;"><i class="fa fa-circle" style="font-size:7px;"></i> TIDAK TERPAKAI</span></div><?php endif; ?>
              <?php if($sp==='Dipinjam'): ?><div style="margin-top:2px;"><span style="font-size:9.5px;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:3px;font-weight:700;"><i class="fa fa-hand-holding" style="font-size:7px;"></i> DIPINJAM</span></div><?php endif; ?>
            </td>
            <td style="font-size:11px;">
              <div style="margin-bottom:3px;">
                <?php if($jenis==='Medis'): ?>
                  <span class="jenis-medis"><i class="fa fa-kit-medical"></i> Medis</span>
                <?php else: ?>
                  <span class="jenis-non-medis"><i class="fa fa-screwdriver-wrench"></i> Non-Medis</span>
                <?php endif; ?>
              </div>
              <span style="color:#475569;"><?= clean($a['kategori']?:'—') ?></span>
            </td>
            <td style="font-size:12px;">
              <span style="font-weight:600;"><?= clean($a['merek']?:'—') ?></span>
              <?php if($a['model_aset']): ?><br><small style="color:#aaa;"><?= clean($a['model_aset']) ?></small><?php endif; ?>
            </td>
            <td style="font-size:12px;">
              <div><i class="fa fa-building" style="color:#94a3b8;font-size:10px;"></i> <?= $lokasi_disp ?></div>
              <?php if($a['bagian_lokasi']): ?><small style="color:#aaa;font-size:10px;"><i class="fa fa-location-dot" style="font-size:9px;"></i> <?= clean($a['bagian_lokasi']) ?></small><?php endif; ?>
            </td>
            <td>
              <?php if($pj_disp!=='—'): ?>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs"><?= $pj_init ?></div>
                <div>
                  <div style="font-size:12px;"><?= $pj_disp ?></div>
                  <?php if($a['pj_divisi']): ?><small style="color:#aaa;font-size:10px;"><?= clean($a['pj_divisi']) ?></small><?php endif; ?>
                </div>
              </div>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td><span class="kondisi-badge <?= $spc ?>"><i class="fa <?= $spi ?>"></i> <?= clean($sp) ?></span></td>
            <td><span class="kondisi-badge <?= $kc ?>"><i class="fa <?= $ki ?>"></i> <?= clean($a['kondisi']) ?></span></td>
            <td style="font-size:11px;white-space:nowrap;">
              <?php if(!$kal_next): ?><span class="kal-none">—</span>
              <?php elseif($kal_exp): ?><span class="kal-exp"><i class="fa fa-triangle-exclamation"></i> Expired</span><br><small style="color:#f87171;"><?= date('d M Y',strtotime($kal_next)) ?></small>
              <?php elseif($kal_soon): ?><span class="kal-soon"><i class="fa fa-clock"></i> Segera</span><br><small style="color:#fbbf24;"><?= date('d M Y',strtotime($kal_next)) ?></small>
              <?php else: ?><span class="kal-ok"><?= date('d M Y',strtotime($kal_next)) ?></span><?php endif; ?>
              <?php if($a['tgl_kalibrasi_terakhir']): ?><div style="font-size:10px;color:#94a3b8;">Terakhir: <?= date('d M Y',strtotime($a['tgl_kalibrasi_terakhir'])) ?></div><?php endif; ?>
            </td>
            <td style="font-size:11px;white-space:nowrap;">
              <?php if(!$svc_next): ?><span class="kal-none">—</span>
              <?php elseif($svc_exp): ?><span class="kal-exp"><i class="fa fa-triangle-exclamation"></i> Expired</span><br><small style="color:#f87171;"><?= date('d M Y',strtotime($svc_next)) ?></small>
              <?php elseif($svc_soon): ?><span class="kal-soon"><i class="fa fa-clock"></i> Segera</span><br><small style="color:#fbbf24;"><?= date('d M Y',strtotime($svc_next)) ?></small>
              <?php else: ?><span class="kal-ok"><?= date('d M Y',strtotime($svc_next)) ?></span><?php endif; ?>
              <?php if($a['tgl_service_terakhir']): ?><div style="font-size:10px;color:#94a3b8;">Terakhir: <?= date('d M Y',strtotime($a['tgl_service_terakhir'])) ?></div><?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:4px;">
                <button onclick="editAset(<?= $a['id'] ?>)" class="btn btn-warning btn-sm" title="Edit"><i class="fa fa-pen"></i></button>
                <?php if(hasRole('admin')): ?>
                <button onclick="hapusAset(<?= $a['id'] ?>,'<?= addslashes(clean($a['nama_aset'])) ?>')" class="btn btn-danger btn-sm" title="Hapus"><i class="fa fa-trash"></i></button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="tbl-footer">
      <span class="tbl-info">Menampilkan <?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?></span>
      <?php if($pages>1): ?>
      <div class="pagination">
        <?php if($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pag-btn"><i class="fa fa-chevron-left"></i></a><?php endif; ?>
        <?php for($i=1;$i<=$pages;$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if($page<$pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pag-btn"><i class="fa fa-chevron-right"></i></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>


<!-- ════════════════════════════════════════════════════
     MODAL TAMBAH / EDIT
════════════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-tambah-aset" style="align-items:flex-start;justify-content:center;padding-top:20px;">
  <div style="background:#fff;width:100%;max-width:800px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:15px 20px;background:linear-gradient(135deg,#7c2d12,#ea580c);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:32px;height:32px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:7px;display:flex;align-items:center;justify-content:center;">
          <i id="modal-icon" class="fa fa-plus" style="color:#fff;font-size:13px;"></i>
        </div>
        <div>
          <div id="modal-title" style="color:#fff;font-size:14px;font-weight:700;">Tambah Aset IPSRS Baru</div>
          <div style="color:rgba(255,255,255,.5);font-size:10.5px;">Isi data aset dengan lengkap dan benar</div>
        </div>
      </div>
      <button onclick="tutupModal()" style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.15);border:none;cursor:pointer;color:#fff;font-size:13px;display:flex;align-items:center;justify-content:center;"
        onmouseover="this.style.background='#ef4444';" onmouseout="this.style.background='rgba(255,255,255,.15)';"><i class="fa fa-times"></i></button>
    </div>

    <form method="POST" action="<?= APP_URL ?>/pages/aset_ipsrs.php" id="form-aset">
      <input type="hidden" name="_action"   id="f-action"     value="tambah">
      <input type="hidden" name="id"        id="f-id"         value="">
      <input type="hidden" name="auto_inv"  id="f-auto-inv"   value="1">
      <input type="hidden" name="jenis_aset" id="f-jenis_aset" value="Non-Medis">

      <div style="padding:18px 20px;max-height:74vh;overflow-y:auto;">

        <!-- ① PILIH JENIS ASET -->
        <div style="margin-bottom:6px;"><label class="f-label"><i class="fa fa-tag" style="color:#f97316;"></i> Jenis Aset <span style="color:#ef4444;">*</span></label></div>
        <div class="jenis-selector">
          <button type="button" id="btn-medis" class="jenis-btn" onclick="setJenis('Medis')">
            <div class="jb-icon">🏥</div>
            <div class="jb-label" style="color:#9d174d;">Medis</div>
            <div class="jb-sub">Peralatan medis & elektromedis</div>
          </button>
          <button type="button" id="btn-non-medis" class="jenis-btn active-non-medis" onclick="setJenis('Non-Medis')">
            <div class="jb-icon">🔧</div>
            <div class="jb-label" style="color:#1e40af;">Non-Medis</div>
            <div class="jb-sub">Sarana, prasarana & utilitas</div>
          </button>
        </div>

        <!-- ② NO. INVENTARIS -->
        <div style="margin-bottom:14px;">
          <label class="f-label">No. Inventaris <span style="color:#ef4444;">*</span></label>
          <div style="display:flex;align-items:center;gap:9px;">
            <div style="flex:1;position:relative;">
              <input type="text" name="no_inventaris" id="f-no_inventaris" class="f-inp"
                placeholder="Akan digenerate otomatis…"
                style="font-family:'Courier New',monospace;padding-right:36px;" disabled>
              <i id="inv-icon" class="fa fa-lock" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;pointer-events:none;"></i>
            </div>
            <label class="inv-toggle-wrap">
              <label class="inv-sw" style="margin:0;">
                <input type="checkbox" id="toggle-manual-inv" onchange="toggleInv(this)" style="position:absolute;opacity:0;width:0;height:0;">
                <span class="inv-sl"></span>
              </label>
              <div>
                <div style="font-size:11.5px;font-weight:700;color:#374151;" id="inv-toggle-label">Auto</div>
                <div style="font-size:10px;color:#94a3b8;" id="inv-toggle-sub">Klik untuk manual</div>
              </div>
            </label>
          </div>
          <div id="inv-preview" style="margin-top:5px;font-size:10.5px;color:#64748b;">
            <i class="fa fa-circle-info" style="color:#f97316;"></i>
            Nomor: <span id="inv-preview-val" style="font-family:monospace;color:#c2410c;font-weight:700;">memuat…</span>
          </div>
        </div>

        <!-- ③ IDENTITAS ASET -->
        <div class="modal-sec"><i class="fa fa-id-card"></i> Identitas Aset</div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Nama Aset <span style="color:#ef4444;">*</span></label>
            <input type="text" name="nama_aset" id="f-nama_aset" required class="f-inp" placeholder="Contoh: Sterilisator, Pompa Air, Ventilator">
          </div>
          <div>
            <label class="f-label">No. Aset RS <small style="color:#94a3b8;font-weight:400;">(nomor registrasi internal RS)</small></label>
            <input type="text" name="no_aset_rs" id="f-no_aset_rs" class="f-inp" placeholder="Contoh: RS-MED-001" style="font-family:'Courier New',monospace;">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Kategori <small style="color:#94a3b8;font-weight:400;" id="kat-hint-txt">(sesuaikan dengan jenis yang dipilih)</small></label>
            <input type="text" name="kategori" id="f-kategori" class="f-inp" placeholder="Pilih atau ketik kategori…" list="kat-dl-ipsrs">
            <datalist id="kat-dl-ipsrs">
              <?php foreach($kat_non_medis as $k): ?><option value="<?= $k ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="f-label">Kondisi</label>
            <select name="kondisi" id="f-kondisi" class="f-inp">
              <option value="Baik">✅ Baik</option>
              <option value="Dalam Perbaikan">🔧 Dalam Perbaikan</option>
              <option value="Rusak">❌ Rusak</option>
              <option value="Tidak Aktif">⛔ Tidak Aktif</option>
            </select>
          </div>
        </div>

        <!-- Status Pakai -->
        <div style="margin-bottom:12px;">
          <label class="f-label"><i class="fa fa-circle-dot" style="color:#f97316;"></i> Status Pemakaian <span style="color:#ef4444;">*</span></label>
          <select name="status_pakai" id="f-status_pakai" class="f-inp val-terpakai" onchange="updateStatusPakaiStyle(this)">
            <option value="Terpakai">🔵 Terpakai — Aset sedang aktif digunakan</option>
            <option value="Tidak Terpakai">🟢 Tidak Terpakai — Aset belum/tidak digunakan</option>
            <option value="Dipinjam">🟡 Dipinjam — Aset sedang dipinjam</option>
          </select>
          <div id="sp-preview-bar" style="margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;">
            <i id="sp-preview-icon" class="fa fa-circle-dot"></i>
            <span id="sp-preview-txt">Baris tabel akan tampil normal</span>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Merek / Pabrikan</label>
            <input type="text" name="merek" id="f-merek" class="f-inp" placeholder="GE, Siemens, Philips, Grundfos…">
          </div>
          <div>
            <label class="f-label">Model / Tipe</label>
            <input type="text" name="model_aset" id="f-model_aset" class="f-inp" placeholder="Model / tipe alat">
          </div>
          <div>
            <label class="f-label">Serial Number</label>
            <input type="text" name="serial_number" id="f-serial_number" class="f-inp" placeholder="Nomor seri" style="font-family:'Courier New',monospace;">
          </div>
        </div>

        <div class="grs-modal"></div>

        <!-- ④ LOKASI & PJ -->
        <div class="modal-sec"><i class="fa fa-location-dot"></i> Lokasi & Penanggung Jawab</div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Lokasi / Instalasi / Ruangan</label>
            <select name="bagian_id" id="f-bagian_id" class="f-inp" onchange="updateLokasiHint(this)">
              <option value="">— Pilih Instalasi / Bagian —</option>
              <?php foreach($bagian_list as $b): ?>
              <option value="<?= $b['id'] ?>" data-lokasi="<?= clean($b['lokasi']??'') ?>" data-kode="<?= clean($b['kode']??'') ?>">
                <?= ($b['kode']?'['.$b['kode'].'] ':'').clean($b['nama']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div id="lokasi-hint" style="margin-top:4px;font-size:10.5px;color:#64748b;display:none;">
              <i class="fa fa-location-dot" style="color:#f97316;"></i> <span id="lokasi-hint-val"></span>
            </div>
          </div>
          <div>
            <label class="f-label">Penanggung Jawab <small style="color:#94a3b8;font-weight:400;">(Teknisi IPSRS / Admin)</small></label>
            <select name="pj_user_id" id="f-pj_user_id" class="f-inp" onchange="updatePjHint(this)">
              <option value="">— Pilih Teknisi IPSRS / PIC —</option>
              <?php foreach($users_list as $u): ?>
              <option value="<?= $u['id'] ?>" data-divisi="<?= clean($u['divisi']??'') ?>" data-role="<?= clean($u['role']) ?>">
                <?= clean($u['nama']) ?><?php if($u['divisi']): ?> — <?= clean($u['divisi']) ?><?php endif; ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div id="pj-hint" style="margin-top:4px;font-size:10.5px;color:#64748b;display:none;">
              <i class="fa fa-id-badge" style="color:#f97316;"></i> <span id="pj-hint-val"></span>
            </div>
          </div>
        </div>

        <div class="grs-modal"></div>

        <!-- ⑤ DATA PENGADAAN -->
        <div class="modal-sec"><i class="fa fa-receipt"></i> Data Pengadaan</div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Tanggal Pembelian / Pengadaan</label>
            <input type="date" name="tanggal_beli" id="f-tanggal_beli" class="f-inp">
          </div>
          <div>
            <label class="f-label">Harga Perolehan (Rp)</label>
            <input type="number" name="harga_beli" id="f-harga_beli" class="f-inp" placeholder="0" min="0">
          </div>
          <div>
            <label class="f-label">Garansi Sampai</label>
            <input type="date" name="garansi_sampai" id="f-garansi_sampai" class="f-inp">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Sumber Dana</label>
            <input type="text" name="sumber_dana" id="f-sumber_dana" class="f-inp" placeholder="APBN, APBD, BLUD, Hibah…" list="sumber-dl">
            <datalist id="sumber-dl">
              <?php foreach(['APBN','APBD','BLUD','Hibah','Pinjaman','Pembelian Mandiri','Donasi','CSR','Lainnya'] as $s): ?><option value="<?= $s ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="f-label">No. BAST <small style="color:#94a3b8;font-weight:400;">(Berita Acara Serah Terima)</small></label>
            <input type="text" name="no_bast" id="f-no_bast" class="f-inp" placeholder="Nomor BAST" style="font-family:'Courier New',monospace;">
          </div>
        </div>

        <div class="grs-modal"></div>

        <!-- ⑥ KALIBRASI -->
        <div class="modal-sec"><i class="fa fa-flask-vial"></i> Kalibrasi <span style="font-size:9.5px;background:rgba(255,255,255,.2);padding:1px 8px;border-radius:9px;font-weight:600;" id="kal-badge-modal">Umumnya untuk peralatan Medis</span></div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Tgl Kalibrasi Terakhir</label>
            <input type="date" name="tgl_kalibrasi_terakhir" id="f-tgl_kalibrasi_terakhir" class="f-inp">
          </div>
          <div>
            <label class="f-label">Tgl Kalibrasi Berikutnya</label>
            <input type="date" name="tgl_kalibrasi_berikutnya" id="f-tgl_kalibrasi_berikutnya" class="f-inp">
          </div>
          <div>
            <label class="f-label">No. Sertifikat Kalibrasi</label>
            <input type="text" name="no_sertifikat_kalibrasi" id="f-no_sertifikat_kalibrasi" class="f-inp" placeholder="No. sertifikat" style="font-family:'Courier New',monospace;">
          </div>
        </div>

        <div class="grs-modal"></div>

        <!-- ⑦ SERVICE / PEMELIHARAAN -->
        <div class="modal-sec"><i class="fa fa-wrench"></i> Pemeliharaan & Service</div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Tgl Service Terakhir</label>
            <input type="date" name="tgl_service_terakhir" id="f-tgl_service_terakhir" class="f-inp">
          </div>
          <div>
            <label class="f-label">Tgl Service Berikutnya</label>
            <input type="date" name="tgl_service_berikutnya" id="f-tgl_service_berikutnya" class="f-inp">
          </div>
          <div>
            <label class="f-label">Vendor / Teknisi Service</label>
            <input type="text" name="vendor_service" id="f-vendor_service" class="f-inp" placeholder="Nama vendor / teknisi">
          </div>
        </div>

        <!-- ⑧ KETERANGAN -->
        <div>
          <label class="f-label">Keterangan / Catatan Tambahan</label>
          <textarea name="keterangan" id="f-keterangan" rows="3" class="f-inp" placeholder="Catatan tambahan tentang kondisi, riwayat, atau informasi lain…" style="resize:vertical;"></textarea>
        </div>

      </div><!-- /scroll area -->

      <!-- Footer -->
      <div style="padding:12px 20px;border-top:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
        <span style="font-size:11px;color:#94a3b8;"><i class="fa fa-asterisk" style="color:#ef4444;font-size:8px;"></i> Wajib diisi</span>
        <div style="display:flex;gap:8px;">
          <button type="button" onclick="tutupModal()" style="padding:7px 16px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;"
            onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f1f5f9';">Batal</button>
          <button type="submit" style="padding:7px 18px;background:linear-gradient(135deg,#f97316,#ea580c);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"
            onmouseover="this.style.opacity='.85';" onmouseout="this.style.opacity='1';"><i class="fa fa-save"></i> <span id="btn-submit-label">Simpan Aset</span></button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- ════════════════════════════════════════════════════
     MODAL HAPUS
════════════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-hapus-aset" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:380px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:20px 22px 14px;text-align:center;">
      <div style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;"><i class="fa fa-trash" style="color:#ef4444;font-size:22px;"></i></div>
      <div style="font-size:15px;font-weight:700;color:#1e293b;">Hapus Aset?</div>
      <div style="font-size:12.5px;color:#64748b;margin-top:6px;">Aset <strong id="hapus-nama"></strong> akan dihapus permanen.</div>
    </div>
    <form method="POST" action="<?= APP_URL ?>/pages/aset_ipsrs.php">
      <input type="hidden" name="_action" value="hapus">
      <input type="hidden" name="id" id="hapus-id">
      <div style="padding:12px 20px;display:flex;gap:8px;justify-content:center;border-top:1px solid #f0f0f0;">
        <button type="button" onclick="closeModal('m-hapus-aset')" style="padding:8px 20px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12.5px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
        <button type="submit" style="padding:8px 20px;background:#ef4444;border:none;border-radius:5px;font-size:12.5px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"><i class="fa fa-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>


<!-- ════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════ -->
<script>
const APP_URL   = '<?= APP_URL ?>';
const KAT_MEDIS = <?= json_encode($kat_medis) ?>;
const KAT_NON   = <?= json_encode($kat_non_medis) ?>;

/* ── Dropdown Cetak ── */
function toggleCetakDrop(e) {
    e.stopPropagation();
    document.getElementById('cetak-drop').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const w = document.getElementById('wrap-cetak');
    if (w && !w.contains(e.target)) document.getElementById('cetak-drop').classList.remove('open');
});

/* ── Set Jenis Aset (Medis / Non-Medis) ── */
function setJenis(jenis) {
    document.getElementById('f-jenis_aset').value = jenis;
    document.getElementById('btn-medis').className     = 'jenis-btn' + (jenis === 'Medis'     ? ' active-medis'     : '');
    document.getElementById('btn-non-medis').className = 'jenis-btn' + (jenis === 'Non-Medis' ? ' active-non-medis' : '');

    // Update datalist
    const dl   = document.getElementById('kat-dl-ipsrs');
    const list = jenis === 'Medis' ? KAT_MEDIS : KAT_NON;
    dl.innerHTML = '';
    list.forEach(k => { const o = document.createElement('option'); o.value = k; dl.appendChild(o); });

    // Reset kategori jika tidak cocok
    const katInp = document.getElementById('f-kategori');
    if (!list.includes(katInp.value)) katInp.value = '';
    katInp.placeholder = jenis === 'Medis' ? 'Diagnostik, Ventilator, Sterilisasi…' : 'Listrik, HVAC, Pompa, Kendaraan…';

    // Update badge kalibrasi
    const badge = document.getElementById('kal-badge-modal');
    if (badge) badge.textContent = jenis === 'Medis' ? 'Wajib untuk peralatan Medis' : 'Opsional untuk Non-Medis';
}

/* ── Status Pakai preview ── */
function updateStatusPakaiStyle(sel) {
    const val = sel.value;
    const bar = document.getElementById('sp-preview-bar');
    const ico = document.getElementById('sp-preview-icon');
    const txt = document.getElementById('sp-preview-txt');
    sel.className = 'f-inp';
    if (val === 'Tidak Terpakai') {
        sel.classList.add('val-tidak');
        bar.style.cssText = 'margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;background:#d1fae5;color:#065f46;border:1px solid #86efac;';
        ico.className = 'fa fa-circle';
        txt.textContent = 'Baris tabel akan berwarna HIJAU + nama aset dicoret';
    } else if (val === 'Dipinjam') {
        sel.classList.add('val-dipinjam');
        bar.style.cssText = 'margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;';
        ico.className = 'fa fa-hand-holding';
        txt.textContent = 'Baris tabel akan berwarna KUNING';
    } else {
        sel.classList.add('val-terpakai');
        bar.style.cssText = 'margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;';
        ico.className = 'fa fa-circle-dot';
        txt.textContent = 'Baris tabel akan tampil normal';
    }
}

/* ── Toggle Auto/Manual No. Inventaris ── */
function toggleInv(chk) {
    const inp  = document.getElementById('f-no_inventaris');
    const icon = document.getElementById('inv-icon');
    const lbl  = document.getElementById('inv-toggle-label');
    const sub  = document.getElementById('inv-toggle-sub');
    const prev = document.getElementById('inv-preview');
    document.getElementById('f-auto-inv').value = chk.checked ? '0' : '1';
    if (chk.checked) {
        inp.disabled = false; inp.placeholder = 'Ketik nomor inventaris…'; inp.value = ''; inp.focus();
        icon.className = 'fa fa-pen'; icon.style.color = '#f97316';
        lbl.textContent = 'Manual'; sub.textContent = 'Klik untuk auto';
        prev.style.display = 'none';
    } else {
        inp.disabled = true; inp.placeholder = 'Akan digenerate otomatis…'; inp.value = '';
        icon.className = 'fa fa-lock'; icon.style.color = '#94a3b8';
        lbl.textContent = 'Auto'; sub.textContent = 'Klik untuk manual';
        prev.style.display = '';
        previewNoInv();
    }
}
function previewNoInv() {
    fetch(APP_URL + '/pages/aset_ipsrs.php?preview_no_inv=1')
        .then(r => r.json()).then(d => { document.getElementById('inv-preview-val').textContent = d.no; });
}

/* ── Hint Lokasi & PJ ── */
function updateLokasiHint(sel) {
    const lok = sel.options[sel.selectedIndex].dataset.lokasi || '';
    const h   = document.getElementById('lokasi-hint');
    if (lok && sel.value) { document.getElementById('lokasi-hint-val').textContent = lok; h.style.display = ''; }
    else h.style.display = 'none';
}
function updatePjHint(sel) {
    const opt = sel.options[sel.selectedIndex];
    const d   = opt.dataset.divisi || '', r = opt.dataset.role || '';
    const h   = document.getElementById('pj-hint');
    if (sel.value && (d || r)) {
        let t = [];
        if (r) t.push('Role: ' + r.charAt(0).toUpperCase() + r.slice(1));
        if (d) t.push('Divisi: ' + d);
        document.getElementById('pj-hint-val').textContent = t.join('  •  ');
        h.style.display = '';
    } else h.style.display = 'none';
}

/* ── Buka Modal Tambah ── */
function bukaModalTambah() { resetFormAset(); openModal('m-tambah-aset'); previewNoInv(); }

/* ── Edit Aset ── */
function editAset(id) {
    fetch(APP_URL + '/pages/aset_ipsrs.php?get_aset=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('modal-title').textContent      = 'Edit Aset IPSRS';
            document.getElementById('modal-icon').className         = 'fa fa-pen';
            document.getElementById('btn-submit-label').textContent = 'Perbarui Aset';
            document.getElementById('f-action').value = 'edit';
            document.getElementById('f-id').value     = d.id;

            // Mode manual
            const chk = document.getElementById('toggle-manual-inv');
            chk.checked = true; toggleInv(chk);
            document.getElementById('f-no_inventaris').value = d.no_inventaris || '';

            // Jenis aset
            setJenis(d.jenis_aset || 'Non-Medis');

            // Semua field
            const fields = ['nama_aset','no_aset_rs','kategori','kondisi','merek','model_aset','serial_number',
                'tanggal_beli','harga_beli','garansi_sampai','sumber_dana','no_bast',
                'tgl_kalibrasi_terakhir','tgl_kalibrasi_berikutnya','no_sertifikat_kalibrasi',
                'tgl_service_terakhir','tgl_service_berikutnya','vendor_service','keterangan'];
            fields.forEach(f => {
                const el = document.getElementById('f-' + f);
                if (el) el.value = d[f] || '';
            });

            // Status pakai
            const sp = document.getElementById('f-status_pakai');
            sp.value = d.status_pakai || 'Terpakai'; updateStatusPakaiStyle(sp);

            // Bagian & PJ
            const sBag = document.getElementById('f-bagian_id');
            sBag.value = d.bagian_id || ''; updateLokasiHint(sBag);
            const sPj = document.getElementById('f-pj_user_id');
            sPj.value = d.pj_user_id || ''; updatePjHint(sPj);

            openModal('m-tambah-aset');
        });
}

/* ── Hapus ── */
function hapusAset(id, nama) {
    document.getElementById('hapus-id').value         = id;
    document.getElementById('hapus-nama').textContent = nama;
    openModal('m-hapus-aset');
}

/* ── Tutup & Reset ── */
function tutupModal() { closeModal('m-tambah-aset'); resetFormAset(); }
function resetFormAset() {
    document.getElementById('form-aset').reset();
    document.getElementById('f-action').value               = 'tambah';
    document.getElementById('f-id').value                   = '';
    document.getElementById('modal-title').textContent      = 'Tambah Aset IPSRS Baru';
    document.getElementById('modal-icon').className         = 'fa fa-plus';
    document.getElementById('btn-submit-label').textContent = 'Simpan Aset';
    const chk = document.getElementById('toggle-manual-inv');
    chk.checked = false; toggleInv(chk);
    document.getElementById('lokasi-hint').style.display = 'none';
    document.getElementById('pj-hint').style.display     = 'none';
    const sp = document.getElementById('f-status_pakai');
    sp.value = 'Terpakai'; updateStatusPakaiStyle(sp);
    setJenis('Non-Medis');
}

document.getElementById('m-tambah-aset').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
</script>

<?php include '../includes/footer.php'; ?>