<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title  = 'Maintenance IPSRS';
$active_menu = 'maintenance_ipsrs';

// ── Helper: generate nomor maintenance ───────────────────────────────────────
function generateNoMaintenanceIPSRS(PDO $pdo): string {
    $bulan = date('Ym');
    $last  = $pdo->query("SELECT no_maintenance FROM maintenance_ipsrs ORDER BY id DESC LIMIT 1")->fetchColumn();
    $seq   = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) $seq = (int)$m[1] + 1;
    return 'MNT-IPSRS-' . $bulan . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    if ($act === 'tambah' || $act === 'edit') {
        $aset_id      = (int)($_POST['aset_id']      ?? 0) ?: null;
        $teknisi_id   = (int)($_POST['teknisi_id']   ?? 0) ?: null;
        $tgl_maint    = $_POST['tgl_maintenance']    ?: date('Y-m-d');
        $tgl_berikut  = date('Y-m-d', strtotime($tgl_maint . ' +3 months'));
        $jenis        = trim($_POST['jenis_maintenance'] ?? '');
        $kondisi_sbl  = trim($_POST['kondisi_sebelum']   ?? '');
        $kondisi_ssd  = trim($_POST['kondisi_sesudah']   ?? '');
        $temuan       = trim($_POST['temuan']            ?? '');
        $tindakan     = trim($_POST['tindakan']          ?? '');
        $biaya        = strlen($_POST['biaya'] ?? '') ? (int)$_POST['biaya'] : null;
        $status       = trim($_POST['status']            ?? 'Selesai');
        $keterangan   = trim($_POST['keterangan']        ?? '');

        $aset_nama = '';
        if ($aset_id) {
            $s = $pdo->prepare("SELECT CONCAT(no_inventaris,' – ',nama_aset) FROM aset_ipsrs WHERE id=?");
            $s->execute([$aset_id]);
            $aset_nama = $s->fetchColumn() ?: '';
        }
        $teknisi_nama = '';
        if ($teknisi_id) {
            $s = $pdo->prepare("SELECT nama FROM users WHERE id=?");
            $s->execute([$teknisi_id]);
            $teknisi_nama = $s->fetchColumn() ?: '';
        }

        if ($act === 'tambah') {
            $no = generateNoMaintenanceIPSRS($pdo);
            $pdo->prepare("INSERT INTO maintenance_ipsrs
                (no_maintenance,aset_id,aset_nama,teknisi_id,teknisi_nama,
                 tgl_maintenance,tgl_maintenance_berikut,jenis_maintenance,
                 kondisi_sebelum,kondisi_sesudah,temuan,tindakan,biaya,
                 status,keterangan,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$no,$aset_id,$aset_nama,$teknisi_id,$teknisi_nama,
                           $tgl_maint,$tgl_berikut,$jenis,
                           $kondisi_sbl,$kondisi_ssd,$temuan,$tindakan,$biaya,
                           $status,$keterangan,$_SESSION['user_id']]);
            if ($aset_id && $status === 'Selesai' && $kondisi_ssd) {
                $pdo->prepare("UPDATE aset_ipsrs SET kondisi=?,updated_at=NOW() WHERE id=?")->execute([$kondisi_ssd,$aset_id]);
            }
            setFlash('success','Maintenance <code>'.$no.'</code> berhasil dicatat. Pengingat berikutnya: <strong>'.date('d M Y',strtotime($tgl_berikut)).'</strong>.');
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE maintenance_ipsrs SET
                aset_id=?,aset_nama=?,teknisi_id=?,teknisi_nama=?,
                tgl_maintenance=?,tgl_maintenance_berikut=?,jenis_maintenance=?,
                kondisi_sebelum=?,kondisi_sesudah=?,temuan=?,tindakan=?,biaya=?,
                status=?,keterangan=?,updated_at=NOW() WHERE id=?")
                ->execute([$aset_id,$aset_nama,$teknisi_id,$teknisi_nama,
                           $tgl_maint,$tgl_berikut,$jenis,
                           $kondisi_sbl,$kondisi_ssd,$temuan,$tindakan,$biaya,
                           $status,$keterangan,$id]);
            if ($aset_id && $status === 'Selesai' && $kondisi_ssd) {
                $pdo->prepare("UPDATE aset_ipsrs SET kondisi=?,updated_at=NOW() WHERE id=?")->execute([$kondisi_ssd,$aset_id]);
            }
            setFlash('success','Data maintenance berhasil diperbarui.');
        }
        redirect(APP_URL.'/pages/maintenance_ipsrs.php');
    }

    if ($act === 'hapus' && hasRole('admin')) {
        $pdo->prepare("DELETE FROM maintenance_ipsrs WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success','Data maintenance berhasil dihapus.');
        redirect(APP_URL.'/pages/maintenance_ipsrs.php');
    }
}

// ── AJAX: get single row ──────────────────────────────────────────────────────
if (isset($_GET['get_mnt'])) {
    $s = $pdo->prepare("SELECT * FROM maintenance_ipsrs WHERE id=?");
    $s->execute([(int)$_GET['get_mnt']]);
    header('Content-Type: application/json');
    echo json_encode($s->fetch(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: cari aset ipsrs ─────────────────────────────────────────────────────
if (isset($_GET['cari_aset'])) {
    $q  = '%'.trim($_GET['cari_aset']).'%';
    $st = $pdo->prepare("SELECT id, no_inventaris, nama_aset, kategori, merek, kondisi, lokasi, jenis_aset FROM aset_ipsrs WHERE (no_inventaris LIKE ? OR nama_aset LIKE ? OR merek LIKE ?) ORDER BY nama_aset LIMIT 20");
    $st->execute([$q,$q,$q]);
    header('Content-Type: application/json');
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── Pagination & Filter ───────────────────────────────────────────────────────
$page     = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15;
$fstatus  = $_GET['status'] ?? '';
$fjenis   = $_GET['jenis']  ?? '';
$search   = $_GET['q']      ?? '';
$ftab     = $_GET['tab']    ?? '';

$where = ['1=1']; $params = [];
$today   = date('Y-m-d');
$date_14 = date('Y-m-d', strtotime('+14 days'));

if ($ftab === 'akan_jatuh_tempo') {
    $where[] = "m.tgl_maintenance_berikut BETWEEN ? AND ?";
    $params  = array_merge($params, [$today, $date_14]);
} elseif ($ftab === 'terlambat') {
    $where[] = "m.tgl_maintenance_berikut < ?";
    $params[] = $today;
}
if ($fstatus) { $where[] = 'm.status=?';            $params[] = $fstatus; }
if ($fjenis)  { $where[] = 'm.jenis_maintenance=?'; $params[] = $fjenis; }
if ($search)  {
    $where[]  = '(m.no_maintenance LIKE ? OR m.aset_nama LIKE ? OR m.teknisi_nama LIKE ?)';
    $params   = array_merge($params, array_fill(0,3,"%$search%"));
}
$wsql = implode(' AND ', $where);

$st_cnt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_ipsrs m WHERE $wsql");
$st_cnt->execute($params);
$total  = (int)$st_cnt->fetchColumn();
$pages  = max(1,ceil($total/$per_page));
$page   = min($page,$pages);
$offset = ($page-1)*$per_page;

$st_data = $pdo->prepare("
    SELECT m.*,
           a.no_inventaris, a.nama_aset AS aset_nama_db, a.kategori AS aset_kat,
           a.merek AS aset_merek, a.jenis_aset,
           u.nama AS tek_nama_db, u.divisi AS tek_divisi
    FROM maintenance_ipsrs m
    LEFT JOIN aset_ipsrs a ON a.id = m.aset_id
    LEFT JOIN users      u ON u.id = m.teknisi_id
    WHERE $wsql
    ORDER BY m.tgl_maintenance DESC
    LIMIT $per_page OFFSET $offset");
$st_data->execute($params);
$mnts = $st_data->fetchAll();

// ── Stats ─────────────────────────────────────────────────────────────────────
$stat_total     = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_ipsrs")->fetchColumn();
$stat_selesai   = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_ipsrs WHERE status='Selesai'")->fetchColumn();
$stat_proses    = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_ipsrs WHERE status='Dalam Proses'")->fetchColumn();
$stat_segera    = (int)$pdo->query("SELECT COUNT(DISTINCT aset_id) FROM maintenance_ipsrs WHERE tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 14 DAY)")->fetchColumn();
$stat_terlambat = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT aset_id,MAX(tgl_maintenance_berikut) AS next_mnt FROM maintenance_ipsrs GROUP BY aset_id) x WHERE x.next_mnt < CURDATE()")->fetchColumn();

// Pengingat ≤30 hari
$pengingat = $pdo->query("
    SELECT m.aset_id, m.aset_nama, m.tgl_maintenance_berikut, a.kondisi, a.no_inventaris, a.nama_aset, a.jenis_aset
    FROM maintenance_ipsrs m
    LEFT JOIN aset_ipsrs a ON a.id=m.aset_id
    WHERE m.id IN (SELECT MAX(id) FROM maintenance_ipsrs GROUP BY aset_id)
      AND m.tgl_maintenance_berikut <= DATE_ADD(CURDATE(),INTERVAL 30 DAY)
    ORDER BY m.tgl_maintenance_berikut ASC
    LIMIT 6")->fetchAll();

// Dropdown
$aset_list    = $pdo->query("SELECT id,no_inventaris,nama_aset,kategori,merek,kondisi,jenis_aset FROM aset_ipsrs ORDER BY nama_aset")->fetchAll();
$teknisi_list = $pdo->query("SELECT id,nama,divisi FROM users WHERE status='aktif' AND role IN ('admin','teknisi_ipsrs') ORDER BY nama")->fetchAll();
$jenis_opts   = ['Preventif','Korektif','Rutin Bulanan','Penggantian Part','Kalibrasi','Inspeksi','Servis Berkala','Lainnya'];
$kondisi_opts = ['Baik','Dalam Perbaikan','Rusak','Tidak Aktif'];

include '../includes/header.php';
?>

<style>
/* ── Maintenance IPSRS ── */
.mnt-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
.mb-selesai {background:#dcfce7;color:#166534;}
.mb-proses  {background:#dbeafe;color:#1e40af;}
.mb-tunda   {background:#fef9c3;color:#854d0e;}
.mb-batal   {background:#fee2e2;color:#991b1b;}

.no-mnt-badge{font-family:'Courier New',monospace;font-size:11px;font-weight:700;background:linear-gradient(135deg,#fff7ed,#ffedd5);color:#c2410c;border:1px solid #fed7aa;padding:2px 8px;border-radius:5px;white-space:nowrap;}

.jenis-medis-sm    {display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#fce7f3;color:#9d174d;white-space:nowrap;}
.jenis-non-medis-sm{display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#eff6ff;color:#1e40af;white-space:nowrap;}

.stat-card-mnt{background:#fff;border-radius:8px;border:1px solid #e8ecf0;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .2s;cursor:pointer;text-decoration:none;}
.stat-card-mnt:hover{box-shadow:0 4px 14px rgba(0,0,0,.09);transform:translateY(-1px);}
.stat-icon-mnt{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}

.remind-card{background:#fff;border-radius:8px;border:1px solid #e8ecf0;padding:11px 14px;display:flex;align-items:center;gap:11px;transition:box-shadow .2s;}
.remind-card.urgent {border-left:3px solid #ef4444;background:linear-gradient(135deg,#fff,#fff5f5);}
.remind-card.soon   {border-left:3px solid #f59e0b;background:linear-gradient(135deg,#fff,#fffbeb);}
.remind-card.normal {border-left:3px solid #f97316;background:linear-gradient(135deg,#fff,#fff7ed);}

.tab-nav{display:flex;gap:4px;border-bottom:2px solid #e8ecf0;margin-bottom:14px;}
.tab-btn{padding:8px 16px;font-size:12px;font-weight:600;color:#64748b;background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;font-family:inherit;transition:.18s;border-radius:4px 4px 0 0;}
.tab-btn:hover{color:#f97316;background:#fff7ed;}
.tab-btn.active{color:#f97316;border-bottom-color:#f97316;background:#fff7ed;}

.grs-modal{height:1px;background:#f0f0f0;margin:12px 0;}
.f-label{font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px;}
.f-inp{width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:6px;font-size:12.5px;box-sizing:border-box;font-family:inherit;transition:border .18s;}
.f-inp:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12);}

.aset-picker-box{border:1px solid #d1d5db;border-radius:6px;overflow:hidden;}
.aset-picker-search{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;}
.aset-picker-search input{flex:1;padding:8px 11px;border:none;outline:none;font-size:12.5px;font-family:inherit;}
.aset-picker-search .search-btn{padding:8px 12px;background:#f97316;color:#fff;border:none;cursor:pointer;font-size:13px;}
.aset-picker-list{max-height:180px;overflow-y:auto;}
.aset-pick-item{padding:8px 11px;cursor:pointer;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px;transition:background .15s;}
.aset-pick-item:hover{background:#fff7ed;}
.aset-pick-item.selected{background:#ffedd5;}
.aset-pick-item:last-child{border-bottom:none;}
.aset-selected-info{margin-top:6px;padding:8px 11px;background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1px solid #fed7aa;border-radius:6px;font-size:11.5px;display:none;}

.countdown-chip{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:12px;font-size:10.5px;font-weight:700;}
.cc-red   {background:#fee2e2;color:#991b1b;}
.cc-orange{background:#ffedd5;color:#9a3412;}
.cc-yellow{background:#fef9c3;color:#854d0e;}
.cc-green {background:#dcfce7;color:#166534;}
.cc-gray  {background:#f1f5f9;color:#475569;}

.btn-cetak{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:5px;font-size:11px;font-weight:700;background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;border:none;cursor:pointer;font-family:inherit;transition:opacity .18s;white-space:nowrap;text-decoration:none;}
.btn-cetak:hover{opacity:.85;}
.btn-cetak-outline{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;background:#fff;color:#7c3aed;border:1.5px solid #7c3aed;cursor:pointer;font-family:inherit;transition:all .18s;text-decoration:none;}
.btn-cetak-outline:hover{background:#7c3aed;color:#fff;}

.mcm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;align-items:center;justify-content:center;}
.mcm-overlay.open{display:flex;}
.mcm-box{background:#fff;width:100%;max-width:540px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;}
.mcm-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:linear-gradient(135deg,#7c2d12,#ea580c);}
.mcm-head-icon{width:30px;height:30px;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);border-radius:6px;display:flex;align-items:center;justify-content:center;}
.mcm-head-title{color:#fff;font-size:13.5px;font-weight:700;margin-left:10px;}
.mcm-head-sub{color:rgba(255,255,255,.5);font-size:10px;margin-left:10px;}
.mcm-close{width:25px;height:25px;border-radius:50%;background:rgba(255,255,255,.15);border:none;cursor:pointer;color:#fff;font-size:12px;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.mcm-close:hover{background:#ef4444;}
.mcm-body{padding:18px 20px;}
.mcm-section{margin-bottom:16px;}
.mcm-section-label{font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.mcm-section-label i{color:#f97316;}
.mcm-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.mcm-label{font-size:11px;font-weight:600;color:#475569;display:block;margin-bottom:4px;}
.mcm-inp{width:100%;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box;font-family:inherit;transition:border .15s;}
.mcm-inp:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.1);}
.mcm-period-chips{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;}
.mcm-period-chip{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;cursor:pointer;transition:all .14s;white-space:nowrap;}
.mcm-period-chip:hover,.mcm-period-chip.active{background:#f97316;color:#fff;border-color:#f97316;}
.mcm-report-opts{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.mcm-report-opt{border:2px solid #e2e8f0;border-radius:8px;padding:10px 12px;cursor:pointer;transition:all .15s;background:#fff;}
.mcm-report-opt:hover{border-color:#f97316;background:#fff7ed;}
.mcm-report-opt.selected{border-color:#f97316;background:#fff7ed;}
.mcm-report-opt-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:6px;}
.mcm-report-opt-title{font-size:12px;font-weight:700;color:#1e293b;}
.mcm-report-opt-sub{font-size:10px;color:#94a3b8;margin-top:2px;line-height:1.4;}
.mcm-foot{padding:12px 18px;border-top:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;}
.mcm-preview-bar{font-size:10.5px;color:#9a3412;background:#fff7ed;border:1px solid #fed7aa;border-radius:5px;padding:6px 12px;margin:0 18px 12px;display:none;line-height:1.6;}
.mcm-preview-bar.show{display:block;}
</style>


<!-- ═══════════════════════════════════════
     PAGE HEADER
═══════════════════════════════════════ -->
<div class="page-header">
  <h4><i class="fa fa-wrench" style="color:#f97316;"></i> &nbsp;Maintenance IPSRS</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span><span class="cur">Maintenance IPSRS</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- ── Stats ── -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:10px;margin-bottom:16px;">
    <a href="?" class="stat-card-mnt" style="text-decoration:none;">
      <div class="stat-icon-mnt" style="background:#fff7ed;"><i class="fa fa-clipboard-list" style="color:#f97316;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stat_total ?></div><div style="font-size:11px;color:#94a3b8;">Total Catatan</div></div>
    </a>
    <a href="?status=Selesai" class="stat-card-mnt" style="text-decoration:none;">
      <div class="stat-icon-mnt" style="background:#dcfce7;"><i class="fa fa-circle-check" style="color:#22c55e;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stat_selesai ?></div><div style="font-size:11px;color:#94a3b8;">Selesai</div></div>
    </a>
    <a href="?status=Dalam+Proses" class="stat-card-mnt" style="text-decoration:none;">
      <div class="stat-icon-mnt" style="background:#dbeafe;"><i class="fa fa-gears" style="color:#3b82f6;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stat_proses ?></div><div style="font-size:11px;color:#94a3b8;">Dalam Proses</div></div>
    </a>
    <a href="?tab=akan_jatuh_tempo" class="stat-card-mnt" style="text-decoration:none;">
      <div class="stat-icon-mnt" style="background:#fef9c3;"><i class="fa fa-clock" style="color:#f59e0b;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stat_segera ?></div><div style="font-size:11px;color:#94a3b8;">Jatuh Tempo 14 Hari</div></div>
    </a>
    <a href="?tab=terlambat" class="stat-card-mnt" style="text-decoration:none;">
      <div class="stat-icon-mnt" style="background:#fee2e2;"><i class="fa fa-triangle-exclamation" style="color:#ef4444;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stat_terlambat ?></div><div style="font-size:11px;color:#94a3b8;">Terlambat</div></div>
    </a>
  </div>

  <!-- ── Pengingat ── -->
  <?php if (!empty($pengingat)): ?>
  <div style="background:#fff;border-radius:8px;border:1px solid #e8ecf0;margin-bottom:16px;overflow:hidden;">
    <div style="padding:11px 16px;background:linear-gradient(135deg,#fff7ed,#ffedd5);border-bottom:1px solid #fed7aa;display:flex;align-items:center;justify-content:space-between;">
      <div style="display:flex;align-items:center;gap:8px;">
        <i class="fa fa-bell" style="color:#f59e0b;font-size:14px;"></i>
        <span style="font-size:13px;font-weight:700;color:#92400e;">Pengingat Maintenance IPSRS (±30 Hari)</span>
      </div>
      <span style="font-size:10.5px;color:#c2410c;">Siklus maintenance: setiap 3 bulan</span>
    </div>
    <div style="padding:12px 14px;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:8px;">
      <?php foreach ($pengingat as $pg):
        $selisih = (int)floor((strtotime($pg['tgl_maintenance_berikut']) - time()) / 86400);
        if ($selisih < 0)       { $cls='urgent'; $chip='cc-red';    $ic='fa-circle-xmark'; $txt='Terlambat '.abs($selisih).' hari'; }
        elseif ($selisih <= 7)  { $cls='urgent'; $chip='cc-orange'; $ic='fa-triangle-exclamation'; $txt=$selisih.' hari lagi'; }
        elseif ($selisih <= 14) { $cls='soon';   $chip='cc-yellow'; $ic='fa-clock';        $txt=$selisih.' hari lagi'; }
        else                    { $cls='normal'; $chip='cc-green';  $ic='fa-calendar';     $txt=$selisih.' hari lagi'; }
        $jenis_a = $pg['jenis_aset'] ?? 'Non-Medis';
      ?>
      <div class="remind-card <?= $cls ?>">
        <div style="width:34px;height:34px;border-radius:8px;background:<?= $cls==='urgent'?'#fee2e2':($cls==='soon'?'#fef9c3':'#ffedd5') ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fa <?= $ic ?>" style="color:<?= $cls==='urgent'?'#ef4444':($cls==='soon'?'#f59e0b':'#f97316') ?>;font-size:14px;"></i>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:12px;font-weight:700;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($pg['nama_aset'] ?: $pg['aset_nama']) ?></div>
          <div style="font-size:10.5px;color:#64748b;margin-top:1px;display:flex;align-items:center;gap:5px;">
            <span><?= clean($pg['no_inventaris'] ?? '—') ?></span>
            <?php if($jenis_a==='Medis'): ?>
              <span class="jenis-medis-sm"><i class="fa fa-kit-medical"></i> Medis</span>
            <?php else: ?>
              <span class="jenis-non-medis-sm"><i class="fa fa-screwdriver-wrench"></i> Non-Medis</span>
            <?php endif; ?>
          </div>
          <div style="margin-top:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <span class="countdown-chip <?= $chip ?>"><i class="fa <?= $ic ?>" style="font-size:9px;"></i> <?= $txt ?></span>
            <span style="font-size:10px;color:#94a3b8;"><?= date('d M Y',strtotime($pg['tgl_maintenance_berikut'])) ?></span>
          </div>
        </div>
        <button onclick="bukaModalTambah(<?= $pg['aset_id'] ?>)"
          style="flex-shrink:0;padding:5px 10px;background:#f97316;border:none;border-radius:5px;color:#fff;font-size:10.5px;font-weight:700;cursor:pointer;font-family:inherit;">
          <i class="fa fa-plus"></i> Catat
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Panel Tabel ── -->
  <div class="panel">
    <div style="padding:14px 16px 0;">
      <div class="tab-nav">
        <button onclick="gotoTab('')"                 class="tab-btn <?= $ftab===''?'active':'' ?>">Semua <span style="font-size:10px;background:#e2e8f0;border-radius:9px;padding:0 6px;"><?= $stat_total ?></span></button>
        <button onclick="gotoTab('akan_jatuh_tempo')" class="tab-btn <?= $ftab==='akan_jatuh_tempo'?'active':'' ?>"><i class="fa fa-clock" style="color:#f59e0b;"></i> Jatuh Tempo <span style="font-size:10px;background:#fef9c3;color:#854d0e;border-radius:9px;padding:0 6px;"><?= $stat_segera ?></span></button>
        <button onclick="gotoTab('terlambat')"        class="tab-btn <?= $ftab==='terlambat'?'active':'' ?>"><i class="fa fa-triangle-exclamation" style="color:#ef4444;"></i> Terlambat <span style="font-size:10px;background:#fee2e2;color:#991b1b;border-radius:9px;padding:0 6px;"><?= $stat_terlambat ?></span></button>
      </div>
    </div>

    <div class="tbl-tools" style="padding-top:6px;">
      <div class="tbl-tools-l">
        <form method="GET" id="sf-mnt" style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">
          <input type="hidden" name="tab" value="<?= clean($ftab) ?>">
          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search" placeholder="Cari no, aset, teknisi…" onchange="document.getElementById('sf-mnt').submit()">
          <select name="status" class="sel-filter" onchange="document.getElementById('sf-mnt').submit()">
            <option value="">Semua Status</option>
            <?php foreach(['Selesai','Dalam Proses','Ditunda','Dibatalkan'] as $s): ?>
            <option value="<?= $s ?>" <?= $fstatus===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <select name="jenis" class="sel-filter" onchange="document.getElementById('sf-mnt').submit()">
            <option value="">Semua Jenis</option>
            <?php foreach($jenis_opts as $j): ?>
            <option value="<?= $j ?>" <?= $fjenis===$j?'selected':'' ?>><?= $j ?></option>
            <?php endforeach; ?>
          </select>
          <?php if($search||$fstatus||$fjenis): ?>
          <a href="?tab=<?= $ftab ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i></a>
          <?php endif; ?>
        </form>
      </div>

      <div style="display:flex;align-items:center;gap:8px;">
        <span class="tbl-info"><?= $total ?> catatan</span>
        <button type="button" onclick="bukaModalCetakMnt()"
          class="btn btn-default btn-sm"
          style="border-color:#f97316;color:#c2410c;font-weight:600;">
          <i class="fa fa-file-pdf"></i> Cetak Laporan
        </button>
        <?php if(hasRole(['admin','teknisi_ipsrs'])): ?>
        <button onclick="bukaModalTambah()" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Catat Maintenance</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Table -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:35px;">#</th>
            <th>No. Maintenance</th>
            <th>Aset IPSRS</th>
            <th>Jenis</th>
            <th>Teknisi</th>
            <th>Tgl Maintenance</th>
            <th>Kondisi Sesudah</th>
            <th>Status</th>
            <th>Maintenance Berikut</th>
            <th>Biaya</th>
            <th style="width:130px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($mnts)): ?>
          <tr><td colspan="11" class="td-empty"><i class="fa fa-wrench"></i> Tidak ada data maintenance IPSRS</td></tr>
          <?php else: $no=$offset+1; foreach($mnts as $m):
            $sel_next = $m['tgl_maintenance_berikut'] ? (int)floor((strtotime($m['tgl_maintenance_berikut'])-time())/86400) : null;
            if ($sel_next !== null) {
                if ($sel_next < 0)       { $next_cls='cc-red';    $next_ic='fa-circle-xmark'; }
                elseif ($sel_next <= 7)  { $next_cls='cc-orange'; $next_ic='fa-triangle-exclamation'; }
                elseif ($sel_next <= 14) { $next_cls='cc-yellow'; $next_ic='fa-clock'; }
                else                     { $next_cls='cc-green';  $next_ic='fa-calendar-check'; }
            }
            $smap = ['Selesai'=>'mb-selesai','Dalam Proses'=>'mb-proses','Ditunda'=>'mb-tunda','Dibatalkan'=>'mb-batal'];
            $sc   = $smap[$m['status']] ?? 'mb-tunda';
            $jenis_a = $m['jenis_aset'] ?? 'Non-Medis';
          ?>
          <tr>
            <td style="color:#bbb;"><?= $no++ ?></td>
            <td><span class="no-mnt-badge"><?= clean($m['no_maintenance']) ?></span></td>
            <td>
              <div style="font-weight:600;font-size:12.5px;color:#1e293b;"><?= clean($m['aset_nama_db'] ?: $m['aset_nama']) ?></div>
              <div style="margin-top:2px;display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                <small style="color:#aaa;font-family:monospace;font-size:10px;"><?= clean($m['no_inventaris'] ?? '—') ?></small>
                <?php if($jenis_a==='Medis'): ?>
                  <span class="jenis-medis-sm"><i class="fa fa-kit-medical"></i> Medis</span>
                <?php else: ?>
                  <span class="jenis-non-medis-sm"><i class="fa fa-screwdriver-wrench"></i> Non-Medis</span>
                <?php endif; ?>
                <?php if($m['aset_kat']): ?><small style="color:#aaa;font-size:10px;">· <?= clean($m['aset_kat']) ?></small><?php endif; ?>
              </div>
            </td>
            <td style="font-size:11.5px;"><?= clean($m['jenis_maintenance'] ?: '—') ?></td>
            <td>
              <?php if($m['tek_nama_db'] || $m['teknisi_nama']): ?>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs" style="background:#fff7ed;color:#c2410c;"><?= getInitials($m['tek_nama_db']?:$m['teknisi_nama']) ?></div>
                <div>
                  <div style="font-size:12px;"><?= clean($m['tek_nama_db']?:$m['teknisi_nama']) ?></div>
                  <?php if($m['tek_divisi']): ?><small style="color:#aaa;font-size:10px;"><?= clean($m['tek_divisi']) ?></small><?php endif; ?>
                </div>
              </div>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td style="font-size:12px;white-space:nowrap;"><?= date('d M Y',strtotime($m['tgl_maintenance'])) ?></td>
            <td>
              <?php if($m['kondisi_sesudah']): ?>
              <?php $kmap2=['Baik'=>'#dcfce7|#166534','Rusak'=>'#fee2e2|#991b1b','Dalam Perbaikan'=>'#fef9c3|#854d0e','Tidak Aktif'=>'#f1f5f9|#475569'];
              [$bg2,$tc2]=explode('|',$kmap2[$m['kondisi_sesudah']]??'#f1f5f9|#475569'); ?>
              <span style="background:<?= $bg2 ?>;color:<?= $tc2 ?>;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;"><?= clean($m['kondisi_sesudah']) ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td><span class="mnt-badge <?= $sc ?>"><?= clean($m['status']) ?></span></td>
            <td style="white-space:nowrap;">
              <?php if($m['tgl_maintenance_berikut']): ?>
              <div style="font-size:11.5px;font-weight:600;color:#1e293b;"><?= date('d M Y',strtotime($m['tgl_maintenance_berikut'])) ?></div>
              <span class="countdown-chip <?= $next_cls ?>">
                <i class="fa <?= $next_ic ?>" style="font-size:9px;"></i>
                <?= $sel_next < 0 ? 'Terlambat '.abs($sel_next).'h' : $sel_next.' hari lagi' ?>
              </span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td style="font-size:12px;">
              <?= $m['biaya'] ? '<span style="color:#10b981;font-weight:600;">Rp '.number_format($m['biaya'],0,',','.').'</span>' : '—' ?>
            </td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <button onclick="lihatDetail(<?= $m['id'] ?>)" class="btn btn-info btn-sm" title="Lihat Detail"><i class="fa fa-eye"></i></button>
                <?php if(hasRole(['admin','teknisi_ipsrs'])): ?>
                <button onclick="editMnt(<?= $m['id'] ?>)" class="btn btn-warning btn-sm" title="Edit"><i class="fa fa-pen"></i></button>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/pages/cetak_maintenance_ipsrs.php?id=<?= $m['id'] ?>&preview=1" target="_blank" class="btn-cetak" title="Cetak Kartu"><i class="fa fa-print"></i></a>
                <?php if(hasRole('admin')): ?>
                <button onclick="hapusMnt(<?= $m['id'] ?>,'<?= addslashes(clean($m['no_maintenance'])) ?>')" class="btn btn-danger btn-sm" title="Hapus"><i class="fa fa-trash"></i></button>
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
</div><!-- /content -->


<!-- ═══════════════════════════════════════════════════
     MODAL CETAK LAPORAN MAINTENANCE IPSRS
═══════════════════════════════════════════════════ -->
<div class="mcm-overlay" id="mcm-overlay">
  <div class="mcm-box">
    <div class="mcm-head">
      <div style="display:flex;align-items:center;">
        <div class="mcm-head-icon"><i class="fa fa-print" style="color:#fff;font-size:13px;"></i></div>
        <div>
          <div class="mcm-head-title">Cetak Laporan Maintenance IPSRS</div>
          <div class="mcm-head-sub">Pilih periode, jenis, dan mode laporan</div>
        </div>
      </div>
      <button class="mcm-close" onclick="tutupModalCetakMnt()"><i class="fa fa-times"></i></button>
    </div>
    <div class="mcm-body">
      <div class="mcm-section">
        <div class="mcm-section-label"><i class="fa fa-calendar-days"></i> Pilihan Cepat Periode</div>
        <div class="mcm-period-chips">
          <span class="mcm-period-chip" onclick="mcmSetPeriod('bulan_ini',this)">Bulan Ini</span>
          <span class="mcm-period-chip" onclick="mcmSetPeriod('bulan_lalu',this)">Bulan Lalu</span>
          <span class="mcm-period-chip" onclick="mcmSetPeriod('3_bulan',this)">3 Bulan Terakhir</span>
          <span class="mcm-period-chip" onclick="mcmSetPeriod('6_bulan',this)">6 Bulan Terakhir</span>
          <span class="mcm-period-chip" onclick="mcmSetPeriod('tahun_ini',this)">Tahun Ini</span>
          <span class="mcm-period-chip active" onclick="mcmSetPeriod('semua',this)">Semua Waktu</span>
        </div>
      </div>
      <div class="mcm-section">
        <div class="mcm-section-label"><i class="fa fa-calendar-range"></i> Rentang Tanggal (Opsional)</div>
        <div class="mcm-grid2">
          <div>
            <label class="mcm-label">Tanggal Mulai</label>
            <input type="date" id="mcm-tgl-dari" class="mcm-inp" onchange="mcmUpdatePreview();mcmClearChips()">
          </div>
          <div>
            <label class="mcm-label">Tanggal Sampai</label>
            <input type="date" id="mcm-tgl-sampai" class="mcm-inp" onchange="mcmUpdatePreview();mcmClearChips()">
          </div>
        </div>
      </div>
      <div class="mcm-section">
        <div class="mcm-section-label"><i class="fa fa-filter"></i> Filter Tambahan</div>
        <div class="mcm-grid2">
          <div>
            <label class="mcm-label">Jenis Maintenance</label>
            <select id="mcm-jenis" class="mcm-inp" onchange="mcmUpdatePreview()">
              <option value="">Semua Jenis</option>
              <?php foreach($jenis_opts as $j): ?>
              <option value="<?= $j ?>"><?= $j ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="mcm-label">Status</label>
            <select id="mcm-status" class="mcm-inp" onchange="mcmUpdatePreview()">
              <option value="">Semua Status</option>
              <option value="Selesai">✅ Selesai</option>
              <option value="Dalam Proses">🔧 Dalam Proses</option>
              <option value="Ditunda">⏸️ Ditunda</option>
              <option value="Dibatalkan">❌ Dibatalkan</option>
            </select>
          </div>
        </div>
      </div>
      <div class="mcm-section" style="margin-bottom:0;">
        <div class="mcm-section-label"><i class="fa fa-file-pdf"></i> Mode Laporan</div>
        <div class="mcm-report-opts">
          <div class="mcm-report-opt selected" id="mcm-opt-semua" onclick="mcmPilihMode('semua')">
            <div class="mcm-report-opt-icon" style="background:#fff7ed;"><i class="fa fa-layer-group" style="color:#f97316;"></i></div>
            <div class="mcm-report-opt-title">Semua Jenis</div>
            <div class="mcm-report-opt-sub">Laporan lengkap dikelompokkan per jenis maintenance</div>
          </div>
          <div class="mcm-report-opt" id="mcm-opt-jenis" onclick="mcmPilihMode('jenis')">
            <div class="mcm-report-opt-icon" style="background:#fff7ed;"><i class="fa fa-wrench" style="color:#f97316;"></i></div>
            <div class="mcm-report-opt-title">Per Jenis Terpilih</div>
            <div class="mcm-report-opt-sub">Hanya tampilkan jenis yang dipilih di filter atas</div>
          </div>
        </div>
      </div>
    </div>
    <div class="mcm-preview-bar show" id="mcm-preview-bar">
      <i class="fa fa-circle-info" style="color:#f97316;"></i>
      <span id="mcm-preview-text">Laporan akan memuat semua data maintenance IPSRS...</span>
    </div>
    <div class="mcm-foot">
      <div style="font-size:10.5px;color:#94a3b8;">
        <i class="fa fa-file-pdf" style="color:#ef4444;"></i> PDF A4 Landscape — terbuka di tab baru
      </div>
      <div style="display:flex;gap:8px;">
        <button type="button" onclick="tutupModalCetakMnt()"
          style="padding:7px 15px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
        <button type="button" onclick="mcmCetak()"
          style="padding:7px 18px;background:linear-gradient(135deg,#f97316,#ea580c);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;">
          <i class="fa fa-print"></i> Cetak PDF
        </button>
      </div>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════
     MODAL TAMBAH / EDIT
═══════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-tambah-mnt" style="align-items:flex-start;justify-content:center;padding-top:24px;">
  <div style="background:#fff;width:100%;max-width:740px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:15px 20px;background:linear-gradient(135deg,#7c2d12,#ea580c);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:32px;height:32px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:7px;display:flex;align-items:center;justify-content:center;">
          <i id="modal-mnt-icon" class="fa fa-plus" style="color:#fff;font-size:13px;"></i>
        </div>
        <div>
          <div id="modal-mnt-title" style="color:#fff;font-size:14px;font-weight:700;">Catat Maintenance IPSRS</div>
          <div style="color:rgba(255,255,255,.5);font-size:10.5px;">Pengingat berikutnya akan otomatis dihitung +3 bulan</div>
        </div>
      </div>
      <button onclick="tutupModalMnt()" style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.15);border:none;cursor:pointer;color:#fff;font-size:13px;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='#ef4444';" onmouseout="this.style.background='rgba(255,255,255,.15)';"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/pages/maintenance_ipsrs.php" id="form-mnt">
      <input type="hidden" name="_action" id="fm-action" value="tambah">
      <input type="hidden" name="id"      id="fm-id"     value="">
      <div style="padding:18px 20px;max-height:74vh;overflow-y:auto;">

        <!-- Pilih Aset -->
        <div style="margin-bottom:14px;">
          <label class="f-label"><i class="fa fa-toolbox" style="color:#f97316;"></i> Pilih Aset IPSRS <span style="color:#ef4444;">*</span></label>
          <input type="hidden" name="aset_id" id="fm-aset_id" required>
          <div class="aset-picker-box">
            <div class="aset-picker-search">
              <input type="text" id="aset-search-inp" placeholder="Ketik nama / no. inventaris aset IPSRS…" oninput="cariAset(this.value)" autocomplete="off">
              <button type="button" class="search-btn"><i class="fa fa-search"></i></button>
            </div>
            <div class="aset-picker-list" id="aset-picker-list">
              <div style="padding:10px 12px;color:#94a3b8;font-size:12px;text-align:center;"><i class="fa fa-search" style="margin-right:5px;"></i>Ketik minimal 1 karakter untuk mencari aset</div>
            </div>
          </div>
          <div class="aset-selected-info" id="aset-selected-info">
            <div style="display:flex;align-items:center;gap:8px;">
              <i class="fa fa-circle-check" style="color:#22c55e;font-size:14px;"></i>
              <div>
                <div style="font-weight:700;color:#1e293b;" id="aset-sel-nama"></div>
                <div style="color:#64748b;font-size:10.5px;margin-top:1px;" id="aset-sel-detail"></div>
              </div>
            </div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Jenis Maintenance <span style="color:#ef4444;">*</span></label>
            <select name="jenis_maintenance" id="fm-jenis" class="f-inp" required>
              <option value="">— Pilih Jenis —</option>
              <?php foreach($jenis_opts as $j): ?><option value="<?= $j ?>"><?= $j ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="f-label">Status</label>
            <select name="status" id="fm-status" class="f-inp">
              <option value="Selesai">✅ Selesai</option>
              <option value="Dalam Proses">🔧 Dalam Proses</option>
              <option value="Ditunda">⏸️ Ditunda</option>
              <option value="Dibatalkan">❌ Dibatalkan</option>
            </select>
          </div>
        </div>

        <div style="margin-bottom:12px;">
          <label class="f-label"><i class="fa fa-user-gear" style="color:#f97316;"></i> Teknisi Pelaksana IPSRS</label>
          <select name="teknisi_id" id="fm-teknisi_id" class="f-inp">
            <option value="">— Pilih Teknisi IPSRS —</option>
            <?php foreach($teknisi_list as $t): ?>
            <option value="<?= $t['id'] ?>"><?= clean($t['nama']) ?><?= $t['divisi']?' — '.clean($t['divisi']):'' ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label"><i class="fa fa-calendar" style="color:#f97316;"></i> Tanggal Maintenance <span style="color:#ef4444;">*</span></label>
            <input type="date" name="tgl_maintenance" id="fm-tgl" class="f-inp" value="<?= date('Y-m-d') ?>" required onchange="hitungBerikutnya(this.value)">
          </div>
          <div>
            <label class="f-label" style="display:flex;align-items:center;gap:5px;">
              <i class="fa fa-bell" style="color:#f59e0b;"></i> Maintenance Berikutnya
              <span style="background:#fff7ed;color:#c2410c;font-size:9.5px;padding:1px 6px;border-radius:10px;font-weight:600;">Otomatis +3 Bulan</span>
            </label>
            <input type="text" id="fm-berikut-display" class="f-inp" disabled style="background:#fff7ed;color:#92400e;font-weight:700;cursor:default;">
          </div>
        </div>

        <div class="grs-modal"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Kondisi Sebelum</label>
            <select name="kondisi_sebelum" id="fm-kondisi_sbl" class="f-inp">
              <option value="">— Pilih —</option>
              <?php foreach($kondisi_opts as $k): ?><option value="<?= $k ?>"><?= $k ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="f-label">Kondisi Sesudah <span style="font-size:9.5px;color:#94a3b8;font-weight:400;">(akan update kondisi aset)</span></label>
            <select name="kondisi_sesudah" id="fm-kondisi_ssd" class="f-inp">
              <option value="">— Pilih —</option>
              <?php foreach($kondisi_opts as $k): ?><option value="<?= $k ?>"><?= $k ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Temuan / Masalah</label>
            <textarea name="temuan" id="fm-temuan" rows="3" class="f-inp" placeholder="Apa yang ditemukan saat maintenance?" style="resize:vertical;"></textarea>
          </div>
          <div>
            <label class="f-label">Tindakan yang Dilakukan</label>
            <textarea name="tindakan" id="fm-tindakan" rows="3" class="f-inp" placeholder="Apa yang sudah dilakukan?" style="resize:vertical;"></textarea>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;">
          <div>
            <label class="f-label">Biaya (Rp)</label>
            <input type="number" name="biaya" id="fm-biaya" class="f-inp" placeholder="0" min="0">
          </div>
          <div>
            <label class="f-label">Keterangan Tambahan</label>
            <input type="text" name="keterangan" id="fm-keterangan" class="f-inp" placeholder="Catatan lain…">
          </div>
        </div>
      </div>

      <div style="padding:12px 20px;border-top:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
        <div style="font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:6px;">
          <i class="fa fa-circle-info" style="color:#f97316;"></i>
          Pengingat berikutnya otomatis 3 bulan dari tanggal maintenance
        </div>
        <div style="display:flex;gap:8px;">
          <button type="button" onclick="tutupModalMnt()" style="padding:7px 16px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
          <button type="submit" style="padding:7px 18px;background:linear-gradient(135deg,#f97316,#ea580c);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;">
            <i class="fa fa-save"></i> <span id="fm-btn-label">Simpan Maintenance</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════
     MODAL DETAIL
═══════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-detail-mnt" style="align-items:flex-start;justify-content:center;padding-top:40px;">
  <div style="background:#fff;width:100%;max-width:600px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:14px 18px;background:linear-gradient(135deg,#7c2d12,#9a3412);display:flex;align-items:center;justify-content:space-between;">
      <div style="display:flex;align-items:center;gap:9px;">
        <i class="fa fa-clipboard-check" style="color:#fed7aa;font-size:16px;"></i>
        <div>
          <div id="detail-no" style="color:#fff;font-size:13px;font-weight:700;"></div>
          <div style="color:rgba(255,255,255,.5);font-size:10px;">Detail Catatan Maintenance IPSRS</div>
        </div>
      </div>
      <button onclick="closeModal('m-detail-mnt')" style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.15);border:none;cursor:pointer;color:#fff;font-size:13px;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='#ef4444';" onmouseout="this.style.background='rgba(255,255,255,.15)';"><i class="fa fa-times"></i></button>
    </div>
    <div id="detail-body" style="padding:18px 20px;max-height:65vh;overflow-y:auto;"></div>
    <div style="padding:11px 18px;border-top:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
      <button onclick="closeModal('m-detail-mnt')" style="padding:6px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;"><i class="fa fa-times"></i> Tutup</button>
      <div style="display:flex;gap:8px;">
        <a id="btn-preview-pdf" href="#" target="_blank" class="btn-cetak-outline"><i class="fa fa-eye"></i> Preview PDF</a>
        <a id="btn-download-pdf" href="#" class="btn-cetak"><i class="fa fa-print"></i> Cetak / Unduh Kartu</a>
      </div>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════
     MODAL HAPUS
═══════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-hapus-mnt" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:380px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:20px 22px 14px;text-align:center;">
      <div style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;"><i class="fa fa-trash" style="color:#ef4444;font-size:22px;"></i></div>
      <div style="font-size:15px;font-weight:700;color:#1e293b;">Hapus Catatan?</div>
      <div style="font-size:12.5px;color:#64748b;margin-top:6px;">Catatan <strong id="hapus-mnt-no"></strong> akan dihapus permanen.</div>
    </div>
    <form method="POST" action="<?= APP_URL ?>/pages/maintenance_ipsrs.php">
      <input type="hidden" name="_action" value="hapus">
      <input type="hidden" name="id" id="hapus-mnt-id">
      <div style="padding:12px 20px;display:flex;gap:8px;justify-content:center;border-top:1px solid #f0f0f0;">
        <button type="button" onclick="closeModal('m-hapus-mnt')" style="padding:8px 20px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12.5px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
        <button type="submit" style="padding:8px 20px;background:#ef4444;border:none;border-radius:5px;font-size:12.5px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"><i class="fa fa-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════ -->
<script>
const APP_URL   = '<?= APP_URL ?>';
let asetSearchTimer = null;
let allAsetData = <?= json_encode($aset_list) ?>;

/* ── Tab navigation ── */
function gotoTab(tab) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

/* ── Hitung +3 bulan ── */
function hitungBerikutnya(tgl) {
    if (!tgl) return;
    const d = new Date(tgl);
    d.setMonth(d.getMonth() + 3);
    document.getElementById('fm-berikut-display').value =
        d.toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' });
}

/* ── Cari aset IPSRS ── */
function cariAset(q) {
    clearTimeout(asetSearchTimer);
    const list = document.getElementById('aset-picker-list');
    if (!q.trim()) {
        list.innerHTML = '<div style="padding:10px 12px;color:#94a3b8;font-size:12px;text-align:center;"><i class="fa fa-search" style="margin-right:5px;"></i>Ketik minimal 1 karakter untuk mencari aset</div>';
        return;
    }
    list.innerHTML = '<div style="padding:10px 12px;color:#94a3b8;font-size:12px;text-align:center;"><i class="fa fa-spinner fa-spin"></i> Mencari…</div>';
    asetSearchTimer = setTimeout(() => {
        fetch(APP_URL + '/pages/maintenance_ipsrs.php?cari_aset=' + encodeURIComponent(q))
            .then(r => r.json()).then(data => renderAsetList(data));
    }, 300);
}

function renderAsetList(data) {
    const list = document.getElementById('aset-picker-list');
    if (!data.length) {
        list.innerHTML = '<div style="padding:10px 12px;color:#94a3b8;font-size:12px;text-align:center;"><i class="fa fa-circle-xmark"></i> Aset tidak ditemukan</div>';
        return;
    }
    const kc  = {'Baik':'#22c55e','Rusak':'#ef4444','Dalam Perbaikan':'#f59e0b','Tidak Aktif':'#94a3b8'};
    const selId = document.getElementById('fm-aset_id').value;
    list.innerHTML = data.map(a => `
        <div class="aset-pick-item ${selId == a.id ? 'selected' : ''}"
             onclick="pilihAset(${a.id},'${escJs(a.no_inventaris)}','${escJs(a.nama_aset)}','${escJs(a.kategori||'')}','${escJs(a.merek||'')}','${escJs(a.kondisi)}','${escJs(a.jenis_aset||'Non-Medis')}')">
          <div style="width:30px;height:30px;border-radius:6px;background:${a.jenis_aset==='Medis'?'#fce7f3':'#fff7ed'};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:900;color:${a.jenis_aset==='Medis'?'#9d174d':'#c2410c'};">
            ${a.jenis_aset === 'Medis' ? '🏥' : '🔧'}
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:12.5px;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(a.nama_aset)}</div>
            <div style="font-size:10.5px;color:#94a3b8;font-family:monospace;">${escHtml(a.no_inventaris)} ${a.merek ? '· '+escHtml(a.merek) : ''}</div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:2px;">
            <span style="font-size:10px;font-weight:700;color:${kc[a.kondisi]||'#94a3b8'};white-space:nowrap;">${escHtml(a.kondisi)}</span>
            <span style="font-size:9px;${a.jenis_aset==='Medis'?'color:#9d174d;background:#fce7f3;':'color:#1e40af;background:#eff6ff;'}padding:1px 5px;border-radius:3px;font-weight:700;">${escHtml(a.jenis_aset||'Non-Medis')}</span>
          </div>
        </div>`).join('');
}

function pilihAset(id, noInv, nama, kat, merek, kondisi, jenisAset) {
    document.getElementById('fm-aset_id').value = id;
    document.querySelectorAll('.aset-pick-item').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('aset-sel-nama').textContent   = nama;
    document.getElementById('aset-sel-detail').textContent = noInv + (kat?' · '+kat:'') + (merek?' · '+merek:'') + ' · '+jenisAset+' · Kondisi: '+kondisi;
    document.getElementById('aset-selected-info').style.display = '';
    const selSbl = document.getElementById('fm-kondisi_sbl');
    if (selSbl && kondisi) selSbl.value = kondisi;
}

function bukaModalTambah(asetId) {
    resetFormMnt();
    openModal('m-tambah-mnt');
    hitungBerikutnya(document.getElementById('fm-tgl').value);
    if (asetId) {
        const found = allAsetData.find(a => a.id == asetId);
        if (found) {
            document.getElementById('aset-search-inp').value = found.nama_aset;
            cariAset(found.nama_aset);
            setTimeout(() => pilihAsetById(asetId), 500);
        }
    } else {
        renderAsetList(allAsetData.slice(0, 20));
    }
}

function pilihAsetById(id) {
    const a = allAsetData.find(x => x.id == id);
    if (!a) return;
    document.getElementById('fm-aset_id').value = id;
    document.getElementById('aset-sel-nama').textContent   = a.nama_aset;
    document.getElementById('aset-sel-detail').textContent = a.no_inventaris + (a.kategori?' · '+a.kategori:'') + (a.merek?' · '+a.merek:'') + ' · '+(a.jenis_aset||'Non-Medis')+' · Kondisi: '+a.kondisi;
    document.getElementById('aset-selected-info').style.display = '';
    if (a.kondisi) document.getElementById('fm-kondisi_sbl').value = a.kondisi;
}

function editMnt(id) {
    fetch(APP_URL + '/pages/maintenance_ipsrs.php?get_mnt=' + id)
        .then(r => r.json())
        .then(d => {
            resetFormMnt();
            document.getElementById('modal-mnt-title').textContent = 'Edit Maintenance IPSRS';
            document.getElementById('modal-mnt-icon').className    = 'fa fa-pen';
            document.getElementById('fm-btn-label').textContent    = 'Perbarui Data';
            document.getElementById('fm-action').value = 'edit';
            document.getElementById('fm-id').value     = d.id;
            document.getElementById('fm-aset_id').value = d.aset_id || '';
            if (d.aset_id) {
                const a = allAsetData.find(x => x.id == d.aset_id);
                if (a) {
                    document.getElementById('aset-search-inp').value = a.nama_aset;
                    cariAset(a.nama_aset);
                    setTimeout(() => pilihAsetById(d.aset_id), 500);
                } else {
                    document.getElementById('aset-sel-nama').textContent  = d.aset_nama || '—';
                    document.getElementById('aset-sel-detail').textContent = '';
                    document.getElementById('aset-selected-info').style.display = '';
                }
            }
            document.getElementById('fm-jenis').value       = d.jenis_maintenance || '';
            document.getElementById('fm-status').value      = d.status            || 'Selesai';
            document.getElementById('fm-teknisi_id').value  = d.teknisi_id        || '';
            document.getElementById('fm-tgl').value         = d.tgl_maintenance   || '';
            document.getElementById('fm-kondisi_sbl').value = d.kondisi_sebelum   || '';
            document.getElementById('fm-kondisi_ssd').value = d.kondisi_sesudah   || '';
            document.getElementById('fm-temuan').value      = d.temuan            || '';
            document.getElementById('fm-tindakan').value    = d.tindakan          || '';
            document.getElementById('fm-biaya').value       = d.biaya             || '';
            document.getElementById('fm-keterangan').value  = d.keterangan        || '';
            hitungBerikutnya(d.tgl_maintenance);
            openModal('m-tambah-mnt');
        });
}

function lihatDetail(id) {
    document.getElementById('btn-preview-pdf').href  = APP_URL + '/pages/cetak_maintenance_ipsrs.php?id=' + id + '&preview=1';
    document.getElementById('btn-download-pdf').href = APP_URL + '/pages/cetak_maintenance_ipsrs.php?id=' + id;
    fetch(APP_URL + '/pages/maintenance_ipsrs.php?get_mnt=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('detail-no').textContent = d.no_maintenance || '—';
            const fmtDate = s => s ? new Date(s).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'}) : '—';
            const row = (label, val, icon='') => `
                <div style="display:flex;gap:10px;padding:7px 0;border-bottom:1px solid #f3f4f6;">
                  <div style="width:140px;flex-shrink:0;font-size:11.5px;font-weight:700;color:#64748b;">
                    ${icon ? '<i class="fa '+icon+'" style="color:#f97316;width:14px;"></i> ' : ''} ${label}
                  </div>
                  <div style="font-size:12.5px;color:#1e293b;">${val || '<span style="color:#cbd5e1;">—</span>'}</div>
                </div>`;
            document.getElementById('detail-body').innerHTML = `
                ${row('No. Maintenance','<code style="font-size:12px;background:#fff7ed;padding:1px 6px;border-radius:4px;color:#c2410c;">'+escHtml(d.no_maintenance)+'</code>')}
                ${row('Aset IPSRS',     escHtml(d.aset_nama),          'fa-toolbox')}
                ${row('Jenis',          escHtml(d.jenis_maintenance),  'fa-wrench')}
                ${row('Teknisi',        escHtml(d.teknisi_nama),       'fa-user-gear')}
                ${row('Tgl Maintenance',fmtDate(d.tgl_maintenance),    'fa-calendar')}
                ${row('Kondisi Sebelum',escHtml(d.kondisi_sebelum),    'fa-circle')}
                ${row('Kondisi Sesudah',escHtml(d.kondisi_sesudah),    'fa-circle-check')}
                ${row('Temuan',         escHtml(d.temuan),             'fa-magnifying-glass')}
                ${row('Tindakan',       escHtml(d.tindakan),           'fa-list-check')}
                ${row('Biaya',          d.biaya ? 'Rp '+Number(d.biaya).toLocaleString('id-ID') : '—', 'fa-receipt')}
                ${row('Status',         escHtml(d.status),             'fa-flag')}
                ${row('Keterangan',     escHtml(d.keterangan),         'fa-note-sticky')}
                <div style="margin-top:12px;padding:10px 12px;background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1px solid #fed7aa;border-radius:7px;display:flex;align-items:center;gap:8px;">
                  <i class="fa fa-bell" style="color:#f59e0b;font-size:15px;"></i>
                  <div>
                    <div style="font-size:11px;font-weight:700;color:#92400e;">Maintenance Berikutnya</div>
                    <div style="font-size:13px;font-weight:800;color:#1e293b;">${fmtDate(d.tgl_maintenance_berikut)}</div>
                  </div>
                </div>`;
            openModal('m-detail-mnt');
        });
}

function hapusMnt(id, no) {
    document.getElementById('hapus-mnt-id').value       = id;
    document.getElementById('hapus-mnt-no').textContent = no;
    openModal('m-hapus-mnt');
}

function tutupModalMnt() { closeModal('m-tambah-mnt'); resetFormMnt(); }
function resetFormMnt() {
    document.getElementById('form-mnt').reset();
    document.getElementById('fm-action').value              = 'tambah';
    document.getElementById('fm-id').value                  = '';
    document.getElementById('modal-mnt-title').textContent  = 'Catat Maintenance IPSRS';
    document.getElementById('modal-mnt-icon').className     = 'fa fa-plus';
    document.getElementById('fm-btn-label').textContent     = 'Simpan Maintenance';
    document.getElementById('fm-aset_id').value             = '';
    document.getElementById('aset-search-inp').value        = '';
    document.getElementById('aset-selected-info').style.display = 'none';
    document.getElementById('aset-picker-list').innerHTML   = '<div style="padding:10px 12px;color:#94a3b8;font-size:12px;text-align:center;"><i class="fa fa-search" style="margin-right:5px;"></i>Ketik minimal 1 karakter untuk mencari aset</div>';
    document.getElementById('fm-tgl').value                 = new Date().toISOString().split('T')[0];
    hitungBerikutnya(document.getElementById('fm-tgl').value);
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(s) { return String(s||'').replace(/'/g,"\\'"); }

document.getElementById('m-tambah-mnt').addEventListener('click', function(e) {
    if (e.target === this) tutupModalMnt();
});

hitungBerikutnya(document.getElementById('fm-tgl').value);
document.getElementById('aset-search-inp').addEventListener('focus', function() {
    if (!document.querySelector('.aset-pick-item')) renderAsetList(allAsetData.slice(0, 20));
});


/* ═══════════════════════════════════════════════
   MODAL CETAK LAPORAN
═══════════════════════════════════════════════ */
let mcmMode = 'semua';

function bukaModalCetakMnt() {
    document.getElementById('mcm-overlay').classList.add('open');
    mcmUpdatePreview();
}
function tutupModalCetakMnt() {
    document.getElementById('mcm-overlay').classList.remove('open');
}

function mcmSetPeriod(type, el) {
    document.querySelectorAll('.mcm-period-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    const now = new Date();
    let dari, sampai;
    if      (type === 'bulan_ini')  { dari = new Date(now.getFullYear(), now.getMonth(), 1);     sampai = new Date(now.getFullYear(), now.getMonth() + 1, 0); }
    else if (type === 'bulan_lalu') { dari = new Date(now.getFullYear(), now.getMonth() - 1, 1); sampai = new Date(now.getFullYear(), now.getMonth(), 0); }
    else if (type === '3_bulan')    { dari = new Date(now.getFullYear(), now.getMonth() - 2, 1); sampai = new Date(now.getFullYear(), now.getMonth() + 1, 0); }
    else if (type === '6_bulan')    { dari = new Date(now.getFullYear(), now.getMonth() - 5, 1); sampai = new Date(now.getFullYear(), now.getMonth() + 1, 0); }
    else if (type === 'tahun_ini')  { dari = new Date(now.getFullYear(), 0, 1);                  sampai = new Date(now.getFullYear(), 11, 31); }
    else { document.getElementById('mcm-tgl-dari').value = ''; document.getElementById('mcm-tgl-sampai').value = ''; mcmUpdatePreview(); return; }
    document.getElementById('mcm-tgl-dari').value   = mcmFmtDate(dari);
    document.getElementById('mcm-tgl-sampai').value = mcmFmtDate(sampai);
    mcmUpdatePreview();
}

function mcmClearChips() {
    document.querySelectorAll('.mcm-period-chip').forEach(c => c.classList.remove('active'));
}
function mcmFmtDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

function mcmPilihMode(mode) {
    mcmMode = mode;
    document.getElementById('mcm-opt-semua').classList.toggle('selected', mode === 'semua');
    document.getElementById('mcm-opt-jenis').classList.toggle('selected', mode === 'jenis');
    const jenisSel = document.getElementById('mcm-jenis');
    if (mode === 'jenis') { jenisSel.style.borderColor = '#f97316'; jenisSel.style.boxShadow = '0 0 0 3px rgba(249,115,22,.12)'; jenisSel.focus(); }
    else { jenisSel.style.borderColor = ''; jenisSel.style.boxShadow = ''; }
    mcmUpdatePreview();
}

function mcmUpdatePreview() {
    const dari   = document.getElementById('mcm-tgl-dari').value;
    const sampai = document.getElementById('mcm-tgl-sampai').value;
    const jenis  = document.getElementById('mcm-jenis');
    const status = document.getElementById('mcm-status');
    const jenisLabel  = jenis.options[jenis.selectedIndex].text;
    const statusLabel = status.options[status.selectedIndex].text;
    let periodeStr = '<strong>Semua Periode</strong>';
    if (dari && sampai) {
        const diff = Math.round((new Date(sampai) - new Date(dari)) / 86400000) + 1;
        periodeStr = `${dari} s.d. ${sampai} <strong>(${diff} hari)</strong>`;
    } else if (dari) { periodeStr = `Mulai <strong>${dari}</strong>`; }
    else if (sampai) { periodeStr = `S.d. <strong>${sampai}</strong>`; }
    const modeStr = (mcmMode === 'jenis' && jenis.value) ? `Jenis: <strong>${jenisLabel}</strong>` : `<strong>Semua Jenis</strong>`;
    document.getElementById('mcm-preview-text').innerHTML =
        `&#128197; Periode: ${periodeStr} &nbsp;|&nbsp; &#128295; ${modeStr} &nbsp;|&nbsp; Status: <strong>${statusLabel}</strong>`;
    document.getElementById('mcm-preview-bar').classList.add('show');
}

function mcmCetak() {
    const dari   = document.getElementById('mcm-tgl-dari').value;
    const sampai = document.getElementById('mcm-tgl-sampai').value;
    const jenis  = document.getElementById('mcm-jenis').value;
    const status = document.getElementById('mcm-status').value;
    if (mcmMode === 'jenis' && !jenis) {
        alert('Mode "Per Jenis Terpilih" aktif.\nSilakan pilih jenis maintenance, atau ubah mode ke "Semua Jenis".');
        document.getElementById('mcm-jenis').focus(); return;
    }
    if (dari && sampai && new Date(dari) > new Date(sampai)) {
        alert('Tanggal mulai tidak boleh lebih besar dari tanggal sampai.'); return;
    }
    let params = `mode=${encodeURIComponent(mcmMode)}`;
    if (jenis)  params += `&jenis=${encodeURIComponent(jenis)}`;
    if (status) params += `&status=${encodeURIComponent(status)}`;
    if (dari)   params += `&tgl_dari=${encodeURIComponent(dari)}`;
    if (sampai) params += `&tgl_sampai=${encodeURIComponent(sampai)}`;
    window.open(`${APP_URL}/pages/cetak_laporan_maintenance_ipsrs.php?${params}`, '_blank');
    tutupModalCetakMnt();
}

document.getElementById('mcm-overlay').addEventListener('click', function(e) {
    if (e.target === this) tutupModalCetakMnt();
});

document.addEventListener('DOMContentLoaded', () => mcmUpdatePreview());
</script>

<?php include '../includes/footer.php'; ?>