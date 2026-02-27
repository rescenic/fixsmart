<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title  = 'Pemantauan Ruangan Server';
$active_menu = 'server_room';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    if ($act === 'tambah' || $act === 'edit') {
        $tanggal        = $_POST['tanggal']         ?: date('Y-m-d');
        $waktu          = $_POST['waktu']           ?: date('H:i');
        $petugas        = trim($_POST['petugas']    ?? '');
        $suhu_in        = $_POST['suhu_in']         !== '' ? (float)$_POST['suhu_in']        : null;
        $suhu_out       = $_POST['suhu_out']        !== '' ? (float)$_POST['suhu_out']       : null;
        $kelembaban     = $_POST['kelembaban']      !== '' ? (float)$_POST['kelembaban']     : null;
        $tegangan_pln   = $_POST['tegangan_pln']    !== '' ? (float)$_POST['tegangan_pln']   : null;
        $tegangan_ups   = $_POST['tegangan_ups']    !== '' ? (float)$_POST['tegangan_ups']   : null;
        $beban_ups      = $_POST['beban_ups']       !== '' ? (float)$_POST['beban_ups']      : null;
        $baterai_ups    = $_POST['baterai_ups']     !== '' ? (float)$_POST['baterai_ups']    : null;
        $kondisi_ac1    = trim($_POST['kondisi_ac1']    ?? 'Normal');
        $kondisi_ac2    = trim($_POST['kondisi_ac2']    ?? 'Normal');
        $kondisi_listrik= trim($_POST['kondisi_listrik']?? 'Normal');
        $kondisi_kebersihan = trim($_POST['kondisi_kebersihan'] ?? 'Bersih');
        $kondisi_pintu  = trim($_POST['kondisi_pintu']  ?? 'Terkunci');
        $kondisi_cctv   = trim($_POST['kondisi_cctv']   ?? 'Normal');
        $ada_alarm      = isset($_POST['ada_alarm']) ? 1 : 0;
        $ada_banjir     = isset($_POST['ada_banjir']) ? 1 : 0;
        $ada_asap       = isset($_POST['ada_asap']) ? 1 : 0;
        $catatan        = trim($_POST['catatan']    ?? '');
        $status_overall = trim($_POST['status_overall'] ?? 'Normal');

        if ($act === 'tambah') {
            $pdo->prepare("INSERT INTO server_room_log
                (tanggal, waktu, petugas, suhu_in, suhu_out, kelembaban,
                 tegangan_pln, tegangan_ups, beban_ups, baterai_ups,
                 kondisi_ac1, kondisi_ac2, kondisi_listrik, kondisi_kebersihan,
                 kondisi_pintu, kondisi_cctv,
                 ada_alarm, ada_banjir, ada_asap,
                 catatan, status_overall, created_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$tanggal,$waktu,$petugas,$suhu_in,$suhu_out,$kelembaban,
                           $tegangan_pln,$tegangan_ups,$beban_ups,$baterai_ups,
                           $kondisi_ac1,$kondisi_ac2,$kondisi_listrik,$kondisi_kebersihan,
                           $kondisi_pintu,$kondisi_cctv,
                           $ada_alarm,$ada_banjir,$ada_asap,
                           $catatan,$status_overall,$_SESSION['user_id']]);
            setFlash('success','Data pemantauan berhasil disimpan.');
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE server_room_log SET
                tanggal=?,waktu=?,petugas=?,suhu_in=?,suhu_out=?,kelembaban=?,
                tegangan_pln=?,tegangan_ups=?,beban_ups=?,baterai_ups=?,
                kondisi_ac1=?,kondisi_ac2=?,kondisi_listrik=?,kondisi_kebersihan=?,
                kondisi_pintu=?,kondisi_cctv=?,
                ada_alarm=?,ada_banjir=?,ada_asap=?,
                catatan=?,status_overall=?,updated_at=NOW()
                WHERE id=?")
                ->execute([$tanggal,$waktu,$petugas,$suhu_in,$suhu_out,$kelembaban,
                           $tegangan_pln,$tegangan_ups,$beban_ups,$baterai_ups,
                           $kondisi_ac1,$kondisi_ac2,$kondisi_listrik,$kondisi_kebersihan,
                           $kondisi_pintu,$kondisi_cctv,
                           $ada_alarm,$ada_banjir,$ada_asap,
                           $catatan,$status_overall,$id]);
            setFlash('success','Data pemantauan berhasil diperbarui.');
        }
        redirect(APP_URL.'/pages/server_room.php');
    }

    if ($act === 'hapus' && hasRole('admin')) {
        $pdo->prepare("DELETE FROM server_room_log WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success','Data berhasil dihapus.');
        redirect(APP_URL.'/pages/server_room.php');
    }
}

// ── AJAX: get single row ──────────────────────────────────────────────────────
if (isset($_GET['get_log'])) {
    $s = $pdo->prepare("SELECT * FROM server_room_log WHERE id=?");
    $s->execute([(int)$_GET['get_log']]);
    header('Content-Type: application/json');
    echo json_encode($s->fetch(PDO::FETCH_ASSOC));
    exit;
}

// ── Pagination & Filter ───────────────────────────────────────────────────────
$page     = max(1,(int)($_GET['page'] ?? 1));
$per_page = 20;
$f_tgl    = $_GET['tgl']    ?? '';
$f_status = $_GET['status'] ?? '';
$f_bulan  = $_GET['bulan']  ?? '';

$where  = ['1=1']; $params = [];
if ($f_tgl)    { $where[] = 'tanggal=?';                       $params[] = $f_tgl; }
if ($f_status) { $where[] = 'status_overall=?';                $params[] = $f_status; }
if ($f_bulan)  { $where[] = "DATE_FORMAT(tanggal,'%Y-%m')=?";  $params[] = $f_bulan; }
$wsql = implode(' AND ', $where);

$st_cnt = $pdo->prepare("SELECT COUNT(*) FROM server_room_log WHERE $wsql");
$st_cnt->execute($params);
$total  = (int)$st_cnt->fetchColumn();
$pages  = max(1, ceil($total/$per_page));
$page   = min($page, $pages);
$offset = ($page-1)*$per_page;

$st_data = $pdo->prepare("SELECT * FROM server_room_log WHERE $wsql ORDER BY tanggal DESC, waktu DESC LIMIT $per_page OFFSET $offset");
$st_data->execute($params);
$logs = $st_data->fetchAll();

// Stats ringkasan
$stats_status = [];
foreach ($pdo->query("SELECT status_overall, COUNT(*) n FROM server_room_log GROUP BY status_overall")->fetchAll() as $r)
    $stats_status[$r['status_overall']] = $r['n'];
$total_all = array_sum($stats_status);

// Data terbaru untuk dashboard
$latest = $pdo->query("SELECT * FROM server_room_log ORDER BY tanggal DESC, waktu DESC LIMIT 1")->fetch();

// Bulan list untuk filter
$bulan_list = $pdo->query("SELECT DISTINCT DATE_FORMAT(tanggal,'%Y-%m') as bln, DATE_FORMAT(tanggal,'%M %Y') as label FROM server_room_log ORDER BY bln DESC LIMIT 24")->fetchAll();

include '../includes/header.php';

// Helper status
function srStatus(string $s): array {
    return match($s) {
        'Normal'   => ['bg'=>'#dcfce7','fg'=>'#15803d','icon'=>'fa-circle-check'],
        'Perhatian'=> ['bg'=>'#fef9c3','fg'=>'#a16207','icon'=>'fa-triangle-exclamation'],
        'Kritis'   => ['bg'=>'#fee2e2','fg'=>'#b91c1c','icon'=>'fa-circle-xmark'],
        default    => ['bg'=>'#f1f5f9','fg'=>'#64748b','icon'=>'fa-circle'],
    };
}
function srKondisi(string $k): array {
    $ok  = ['Normal','Baik','Bersih','Terkunci','Aktif','On'];
    $warn= ['Kotor','Perlu Service','Tidak Terkunci'];
    if (in_array($k, $ok))   return ['bg'=>'#dcfce7','fg'=>'#15803d'];
    if (in_array($k, $warn)) return ['bg'=>'#fef9c3','fg'=>'#a16207'];
    return                          ['bg'=>'#fee2e2','fg'=>'#b91c1c'];
}
function suhuColor(float $v): string {
    if ($v <= 22) return '#0891b2';
    if ($v <= 26) return '#16a34a';
    if ($v <= 30) return '#d97706';
    return '#dc2626';
}
function lembabColor(float $v): string {
    if ($v < 40)  return '#0891b2';
    if ($v <= 60) return '#16a34a';
    if ($v <= 70) return '#d97706';
    return '#dc2626';
}
?>

<style>
/* ══ Server Room Monitoring ══════════════════════════════════════ */
.sr-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:10px;margin-bottom:18px;}
.sr-stat{background:#fff;border-radius:8px;border:1px solid #e8ecf0;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .2s;}
.sr-stat:hover{box-shadow:0 4px 14px rgba(0,0,0,.07);}
.sr-stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.sr-stat-val{font-size:20px;font-weight:800;color:#1e293b;line-height:1;}
.sr-stat-lbl{font-size:11px;color:#94a3b8;margin-top:2px;}

/* Latest reading dashboard */
.sr-live{background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:10px;padding:18px 20px;margin-bottom:18px;color:#fff;}
.sr-live-title{font-size:12px;font-weight:700;color:rgba(255,255,255,.5);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:12px;}
.sr-live-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;}
.sr-live-item{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:10px 12px;}
.sr-live-item-label{font-size:9.5px;color:rgba(255,255,255,.45);letter-spacing:1px;text-transform:uppercase;margin-bottom:4px;}
.sr-live-item-val{font-size:18px;font-weight:800;line-height:1;}
.sr-live-item-unit{font-size:10px;color:rgba(255,255,255,.4);margin-top:1px;}

/* Alert flags */
.sr-alert-row{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;}
.sr-alert-flag{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:700;}
.sr-alert-on {background:#fee2e2;color:#b91c1c;}
.sr-alert-off{background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);}

/* Badge kondisi */
.sr-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;white-space:nowrap;}

/* Modal */
.f-label{font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px;}
.f-inp{width:100%;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12.5px;box-sizing:border-box;font-family:inherit;transition:border .15s;}
.f-inp:focus{outline:none;border-color:#26B99A;box-shadow:0 0 0 3px rgba(38,185,154,.1);}
.f-section{font-size:11px;font-weight:700;color:#26B99A;letter-spacing:1px;text-transform:uppercase;padding:8px 0 6px;border-bottom:1px solid #f0f0f0;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.f-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.f-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;}
.f-grid4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;}

/* Checkbox toggle */
.chk-wrap{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;transition:all .15s;}
.chk-wrap:hover{border-color:#ef4444;background:#fef2f2;}
.chk-wrap input[type=checkbox]{width:15px;height:15px;accent-color:#ef4444;cursor:pointer;}
.chk-wrap label{font-size:12px;font-weight:600;color:#374151;cursor:pointer;}

/* Suhu indicator bar */
.temp-bar-wrap{height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-top:3px;}
.temp-bar-fill{height:5px;border-radius:3px;transition:width .3s;}

/* Tabel */
.tbl-suhu{font-size:12px;font-weight:700;}
.tbl-temp-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;color:#fff;}
</style>

<div class="page-header">
  <h4><i class="fa fa-server text-primary"></i> &nbsp;Pemantauan Ruangan Server</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span><span class="cur">Pemantauan Ruangan Server</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- ══ LIVE DASHBOARD (data terbaru) ══ -->
  <?php if ($latest): ?>
  <div class="sr-live">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <div>
        <div class="sr-live-title"><i class="fa fa-signal"></i> &nbsp;Kondisi Terkini Ruangan Server</div>
        <div style="font-size:11px;color:rgba(255,255,255,.4);">
          Pemantauan terakhir:
          <strong style="color:rgba(255,255,255,.7);">
            <?= date('d M Y', strtotime($latest['tanggal'])) ?> pukul <?= $latest['waktu'] ?>
          </strong>
          &nbsp;&mdash;&nbsp; Petugas: <strong style="color:rgba(255,255,255,.7);"><?= htmlspecialchars($latest['petugas'] ?: '—') ?></strong>
        </div>
      </div>
      <?php $ls = srStatus($latest['status_overall'] ?? 'Normal'); ?>
      <span style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;background:<?= $ls['bg'] ?>;color:<?= $ls['fg'] ?>;">
        <i class="fa <?= $ls['icon'] ?>"></i> <?= $latest['status_overall'] ?>
      </span>
    </div>

    <div class="sr-live-grid">
      <!-- Suhu Dalam -->
      <?php $sc = $latest['suhu_in'] ? suhuColor((float)$latest['suhu_in']) : '#94a3b8'; ?>
      <div class="sr-live-item">
        <div class="sr-live-item-label"><i class="fa fa-temperature-half"></i> Suhu Dalam</div>
        <div class="sr-live-item-val" style="color:<?= $sc ?>;"><?= $latest['suhu_in'] !== null ? $latest['suhu_in'] : '—' ?></div>
        <div class="sr-live-item-unit">°C &nbsp;
          <?php if ($latest['suhu_in'] !== null):
            $sv = (float)$latest['suhu_in'];
            echo $sv <= 22 ? '<span style="color:#67e8f9;font-size:10px;">Dingin</span>'
               : ($sv <= 26 ? '<span style="color:#86efac;font-size:10px;">Ideal</span>'
               : ($sv <= 30 ? '<span style="color:#fcd34d;font-size:10px;">Hangat</span>'
               : '<span style="color:#fca5a5;font-size:10px;">Panas!</span>'));
          endif; ?>
        </div>
      </div>
      <!-- Suhu Luar -->
      <?php $sc2 = $latest['suhu_out'] ? suhuColor((float)$latest['suhu_out']) : '#94a3b8'; ?>
      <div class="sr-live-item">
        <div class="sr-live-item-label"><i class="fa fa-temperature-arrow-up"></i> Suhu Luar</div>
        <div class="sr-live-item-val" style="color:<?= $sc2 ?>;"><?= $latest['suhu_out'] !== null ? $latest['suhu_out'] : '—' ?></div>
        <div class="sr-live-item-unit">°C</div>
      </div>
      <!-- Kelembaban -->
      <?php $lc = $latest['kelembaban'] ? lembabColor((float)$latest['kelembaban']) : '#94a3b8'; ?>
      <div class="sr-live-item">
        <div class="sr-live-item-label"><i class="fa fa-droplet"></i> Kelembaban</div>
        <div class="sr-live-item-val" style="color:<?= $lc ?>;"><?= $latest['kelembaban'] !== null ? $latest['kelembaban'] : '—' ?></div>
        <div class="sr-live-item-unit">%RH &nbsp;
          <?php if ($latest['kelembaban'] !== null):
            $lv = (float)$latest['kelembaban'];
            echo $lv < 40 ? '<span style="color:#67e8f9;font-size:10px;">Kering</span>'
               : ($lv <= 60 ? '<span style="color:#86efac;font-size:10px;">Ideal</span>'
               : ($lv <= 70 ? '<span style="color:#fcd34d;font-size:10px;">Lembab</span>'
               : '<span style="color:#fca5a5;font-size:10px;">Sangat Lembab!</span>'));
          endif; ?>
        </div>
      </div>
      <!-- Tegangan PLN -->
      <div class="sr-live-item">
        <div class="sr-live-item-label"><i class="fa fa-bolt"></i> Tegangan PLN</div>
        <div class="sr-live-item-val" style="color:<?= $latest['tegangan_pln'] ? '#fbbf24' : '#94a3b8' ?>;"><?= $latest['tegangan_pln'] !== null ? $latest['tegangan_pln'] : '—' ?></div>
        <div class="sr-live-item-unit">Volt</div>
      </div>
      <!-- Tegangan UPS -->
      <div class="sr-live-item">
        <div class="sr-live-item-label"><i class="fa fa-battery-full"></i> Tegangan UPS</div>
        <div class="sr-live-item-val" style="color:<?= $latest['tegangan_ups'] ? '#34d399' : '#94a3b8' ?>;"><?= $latest['tegangan_ups'] !== null ? $latest['tegangan_ups'] : '—' ?></div>
        <div class="sr-live-item-unit">Volt</div>
      </div>
      <!-- Beban UPS -->
      <div class="sr-live-item">
        <div class="sr-live-item-label"><i class="fa fa-gauge-high"></i> Beban UPS</div>
        <?php $bu = (float)($latest['beban_ups'] ?? 0); ?>
        <div class="sr-live-item-val" style="color:<?= $bu > 80 ? '#f87171' : ($bu > 60 ? '#fbbf24' : '#34d399') ?>;"><?= $latest['beban_ups'] !== null ? $latest['beban_ups'] : '—' ?></div>
        <div class="sr-live-item-unit">%</div>
      </div>
      <!-- Baterai UPS -->
      <div class="sr-live-item">
        <div class="sr-live-item-label"><i class="fa fa-battery-three-quarters"></i> Baterai UPS</div>
        <?php $bat = (float)($latest['baterai_ups'] ?? 100); ?>
        <div class="sr-live-item-val" style="color:<?= $bat < 20 ? '#f87171' : ($bat < 50 ? '#fbbf24' : '#34d399') ?>;"><?= $latest['baterai_ups'] !== null ? $latest['baterai_ups'] : '—' ?></div>
        <div class="sr-live-item-unit">%</div>
      </div>
    </div>

    <!-- Kondisi & Alert Flags -->
    <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
      <?php foreach([
        ['AC 1', $latest['kondisi_ac1']      ?? '—'],
        ['AC 2', $latest['kondisi_ac2']      ?? '—'],
        ['Listrik', $latest['kondisi_listrik']  ?? '—'],
        ['Kebersihan', $latest['kondisi_kebersihan'] ?? '—'],
        ['Pintu', $latest['kondisi_pintu']    ?? '—'],
        ['CCTV', $latest['kondisi_cctv']     ?? '—'],
      ] as [$lbl, $val]):
        $ks = srKondisi($val);
      ?>
      <span style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:5px;padding:4px 10px;font-size:11px;color:rgba(255,255,255,.6);">
        <?= $lbl ?>:
        <span style="font-weight:700;color:<?= $ks['fg'] == '#15803d' ? '#86efac' : ($ks['fg'] == '#a16207' ? '#fcd34d' : '#fca5a5') ?>;"><?= htmlspecialchars($val) ?></span>
      </span>
      <?php endforeach; ?>

      <?php if ($latest['ada_alarm']): ?>
      <span class="sr-alert-flag sr-alert-on"><i class="fa fa-bell"></i> ALARM AKTIF</span>
      <?php endif; ?>
      <?php if ($latest['ada_banjir']): ?>
      <span class="sr-alert-flag sr-alert-on"><i class="fa fa-water"></i> DETEKSI BANJIR</span>
      <?php endif; ?>
      <?php if ($latest['ada_asap']): ?>
      <span class="sr-alert-flag sr-alert-on"><i class="fa fa-smog"></i> DETEKSI ASAP</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ STAT CARDS ══ -->
  <div class="sr-stat-grid">
    <div class="sr-stat">
      <div class="sr-stat-icon" style="background:#eff6ff;"><i class="fa fa-clipboard-list" style="color:#3b82f6;"></i></div>
      <div><div class="sr-stat-val"><?= $total_all ?></div><div class="sr-stat-lbl">Total Log</div></div>
    </div>
    <?php foreach([
      ['Normal',   '#dcfce7','#22c55e','fa-circle-check'],
      ['Perhatian','#fef9c3','#f59e0b','fa-triangle-exclamation'],
      ['Kritis',   '#fee2e2','#ef4444','fa-circle-xmark'],
    ] as [$k,$bg,$ic,$ico]): ?>
    <div class="sr-stat">
      <div class="sr-stat-icon" style="background:<?= $bg ?>;"><i class="fa <?= $ico ?>" style="color:<?= $ic ?>;"></i></div>
      <div><div class="sr-stat-val"><?= $stats_status[$k]??0 ?></div><div class="sr-stat-lbl"><?= $k ?></div></div>
    </div>
    <?php endforeach; ?>
    <!-- Rata-rata suhu dari data bulan ini -->
    <?php
    $avg_suhu = $pdo->query("SELECT AVG(suhu_in) FROM server_room_log WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();
    $avg_suhu = $avg_suhu ? round((float)$avg_suhu,1) : null;
    ?>
    <div class="sr-stat">
      <div class="sr-stat-icon" style="background:#fff7ed;"><i class="fa fa-temperature-half" style="color:#f97316;"></i></div>
      <div><div class="sr-stat-val" style="font-size:16px;"><?= $avg_suhu !== null ? $avg_suhu.'°C' : '—' ?></div><div class="sr-stat-lbl">Avg. Suhu Bulan Ini</div></div>
    </div>
    <?php
    $alert_count = (int)$pdo->query("SELECT COUNT(*) FROM server_room_log WHERE (ada_alarm=1 OR ada_banjir=1 OR ada_asap=1) AND MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();
    ?>
    <div class="sr-stat">
      <div class="sr-stat-icon" style="background:#fff1f2;"><i class="fa fa-bell" style="color:#f43f5e;"></i></div>
      <div><div class="sr-stat-val" style="color:<?= $alert_count > 0 ? '#ef4444' : '#1e293b' ?>;"><?= $alert_count ?></div><div class="sr-stat-lbl">Alert Bulan Ini</div></div>
    </div>
  </div>

  <!-- ══ PANEL TABEL ══ -->
  <div class="panel">
    <!-- Toolbar -->
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf-sr" style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">
          <select name="bulan" class="sel-filter" onchange="document.getElementById('sf-sr').submit()">
            <option value="">Semua Bulan</option>
            <?php foreach ($bulan_list as $b): ?>
            <option value="<?= $b['bln'] ?>" <?= $f_bulan===$b['bln']?'selected':'' ?>><?= $b['label'] ?></option>
            <?php endforeach; ?>
          </select>
          <select name="status" class="sel-filter" onchange="document.getElementById('sf-sr').submit()">
            <option value="">Semua Status</option>
            <option value="Normal"    <?= $f_status==='Normal'   ?'selected':'' ?>>Normal</option>
            <option value="Perhatian" <?= $f_status==='Perhatian'?'selected':'' ?>>Perhatian</option>
            <option value="Kritis"    <?= $f_status==='Kritis'   ?'selected':'' ?>>Kritis</option>
          </select>
          <input type="date" name="tgl" value="<?= clean($f_tgl) ?>" class="f-inp" style="width:140px;padding:6px 10px;font-size:12px;" onchange="document.getElementById('sf-sr').submit()">
          <?php if ($f_tgl||$f_status||$f_bulan): ?>
          <a href="?" class="btn btn-default btn-sm"><i class="fa fa-times"></i></a>
          <?php endif; ?>
        </form>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="tbl-info"><?= $total ?> log</span>
        <?php if(hasRole(['admin','teknisi'])): ?>
        <button onclick="bukaModalTambah()" class="btn btn-primary btn-sm">
          <i class="fa fa-plus"></i> Input Pemantauan
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Table -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:30px;">#</th>
            <th>Tanggal & Waktu</th>
            <th>Petugas</th>
            <th>Suhu Dalam</th>
            <th>Suhu Luar</th>
            <th>Kelembaban</th>
            <th>Tegangan PLN</th>
            <th>UPS (V/Beban/Bat)</th>
            <th>AC 1 / AC 2</th>
            <th>Listrik</th>
            <th>Pintu / CCTV</th>
            <th>Alert</th>
            <th>Status</th>
            <th style="width:80px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
          <tr><td colspan="14" class="td-empty"><i class="fa fa-server"></i> Belum ada data pemantauan</td></tr>
          <?php else: $no=$offset+1; foreach ($logs as $r):
            $st_d = srStatus($r['status_overall'] ?? 'Normal');
          ?>
          <tr>
            <td style="color:#bbb;"><?= $no++ ?></td>
            <td style="white-space:nowrap;">
              <div style="font-weight:700;font-size:12px;"><?= date('d M Y',strtotime($r['tanggal'])) ?></div>
              <small style="color:#94a3b8;"><?= substr($r['waktu'],0,5) ?> WIB</small>
            </td>
            <td style="font-size:12px;"><?= htmlspecialchars($r['petugas']?:'—') ?></td>
            <!-- Suhu Dalam -->
            <td>
              <?php if ($r['suhu_in'] !== null):
                $sc = suhuColor((float)$r['suhu_in']); ?>
              <span class="tbl-temp-badge" style="background:<?= $sc ?>;"><?= $r['suhu_in'] ?>°C</span>
              <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
            </td>
            <!-- Suhu Luar -->
            <td>
              <?php if ($r['suhu_out'] !== null):
                $sc2 = suhuColor((float)$r['suhu_out']); ?>
              <span class="tbl-temp-badge" style="background:<?= $sc2 ?>;"><?= $r['suhu_out'] ?>°C</span>
              <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
            </td>
            <!-- Kelembaban -->
            <td>
              <?php if ($r['kelembaban'] !== null):
                $lc = lembabColor((float)$r['kelembaban']); ?>
              <span style="font-weight:700;color:<?= $lc ?>;"><?= $r['kelembaban'] ?>%</span>
              <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
            </td>
            <!-- Tegangan PLN -->
            <td style="font-size:12px;font-weight:600;">
              <?= $r['tegangan_pln'] !== null ? $r['tegangan_pln'].' V' : '<span style="color:#cbd5e1;">—</span>' ?>
            </td>
            <!-- UPS -->
            <td style="font-size:11px;white-space:nowrap;">
              <?= $r['tegangan_ups'] !== null ? '<span style="color:#1d4ed8;font-weight:600;">'.$r['tegangan_ups'].'V</span>' : '—' ?>
              /
              <?php if ($r['beban_ups'] !== null):
                $bu = (float)$r['beban_ups'];
                $bc = $bu>80?'#dc2626':($bu>60?'#d97706':'#16a34a');
                echo '<span style="color:'.$bc.';font-weight:600;">'.$r['beban_ups'].'%</span>';
              else: echo '—'; endif; ?>
              /
              <?php if ($r['baterai_ups'] !== null):
                $bat = (float)$r['baterai_ups'];
                $batc = $bat<20?'#dc2626':($bat<50?'#d97706':'#16a34a');
                echo '<span style="color:'.$batc.';font-weight:600;">'.$r['baterai_ups'].'%</span>';
              else: echo '—'; endif; ?>
            </td>
            <!-- AC 1 / AC 2 -->
            <td>
              <?php
              $k1 = srKondisi($r['kondisi_ac1']??'—');
              $k2 = srKondisi($r['kondisi_ac2']??'—');
              ?>
              <span class="sr-badge" style="background:<?= $k1['bg'] ?>;color:<?= $k1['fg'] ?>;"><?= htmlspecialchars($r['kondisi_ac1']??'—') ?></span>
              <br>
              <span class="sr-badge" style="background:<?= $k2['bg'] ?>;color:<?= $k2['fg'] ?>;margin-top:2px;"><?= htmlspecialchars($r['kondisi_ac2']??'—') ?></span>
            </td>
            <!-- Listrik -->
            <td>
              <?php $kl = srKondisi($r['kondisi_listrik']??'—'); ?>
              <span class="sr-badge" style="background:<?= $kl['bg'] ?>;color:<?= $kl['fg'] ?>;"><?= htmlspecialchars($r['kondisi_listrik']??'—') ?></span>
            </td>
            <!-- Pintu / CCTV -->
            <td>
              <?php
              $kp  = srKondisi($r['kondisi_pintu']??'—');
              $kcc = srKondisi($r['kondisi_cctv']??'—');
              ?>
              <span class="sr-badge" style="background:<?= $kp['bg'] ?>;color:<?= $kp['fg'] ?>;" title="Pintu"><i class="fa fa-door-closed" style="font-size:9px;"></i> <?= htmlspecialchars($r['kondisi_pintu']??'—') ?></span>
              <br>
              <span class="sr-badge" style="background:<?= $kcc['bg'] ?>;color:<?= $kcc['fg'] ?>;margin-top:2px;" title="CCTV"><i class="fa fa-camera" style="font-size:9px;"></i> <?= htmlspecialchars($r['kondisi_cctv']??'—') ?></span>
            </td>
            <!-- Alert -->
            <td style="white-space:nowrap;">
              <?php
              $alerts = [];
              if ($r['ada_alarm'])  $alerts[] = '<span style="color:#dc2626;font-size:11px;" title="Alarm"><i class="fa fa-bell"></i></span>';
              if ($r['ada_banjir']) $alerts[] = '<span style="color:#0891b2;font-size:11px;" title="Banjir"><i class="fa fa-water"></i></span>';
              if ($r['ada_asap'])   $alerts[] = '<span style="color:#d97706;font-size:11px;" title="Asap"><i class="fa fa-smog"></i></span>';
              echo $alerts ? implode(' ', $alerts) : '<span style="color:#cbd5e1;font-size:11px;">—</span>';
              ?>
            </td>
            <!-- Status -->
            <td>
              <span class="sr-badge" style="background:<?= $st_d['bg'] ?>;color:<?= $st_d['fg'] ?>;">
                <i class="fa <?= $st_d['icon'] ?>" style="font-size:9px;"></i>
                <?= $r['status_overall'] ?>
              </span>
              <?php if ($r['catatan']): ?>
              <div style="font-size:10px;color:#94a3b8;margin-top:2px;" title="<?= htmlspecialchars($r['catatan']) ?>"><?= mb_strimwidth(htmlspecialchars($r['catatan']),0,25,'…') ?></div>
              <?php endif; ?>
            </td>
            <!-- Aksi -->
            <td>
              <div style="display:flex;gap:4px;">
                <button onclick="editLog(<?= $r['id'] ?>)" class="btn btn-warning btn-sm" title="Edit"><i class="fa fa-pen"></i></button>
                <?php if(hasRole('admin')): ?>
                <button onclick="hapusLog(<?= $r['id'] ?>,'<?= date("d M Y",strtotime($r["tanggal"])) ?>')" class="btn btn-danger btn-sm" title="Hapus"><i class="fa fa-trash"></i></button>
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
      <?php if ($pages>1): ?>
      <div class="pagination">
        <?php if($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pag-btn"><i class="fa fa-chevron-left"></i></a><?php endif; ?>
        <?php for($i=1;$i<=$pages;$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if($page<$pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pag-btn"><i class="fa fa-chevron-right"></i></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════
     MODAL INPUT / EDIT PEMANTAUAN
══════════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-sr" style="align-items:flex-start;justify-content:center;padding-top:24px;">
  <div style="background:#fff;width:100%;max-width:780px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:32px;height:32px;background:rgba(38,185,154,.25);border:1px solid rgba(38,185,154,.5);border-radius:7px;display:flex;align-items:center;justify-content:center;">
          <i id="m-icon" class="fa fa-plus" style="color:#26B99A;font-size:13px;"></i>
        </div>
        <div>
          <div id="m-title" style="color:#fff;font-size:14px;font-weight:700;">Input Pemantauan Ruangan Server</div>
          <div style="color:rgba(255,255,255,.4);font-size:10.5px;">Isi semua parameter dengan lengkap dan akurat</div>
        </div>
      </div>
      <button onclick="tutupModal()" style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:13px;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='#ef4444';this.style.color='#fff';" onmouseout="this.style.background='rgba(255,255,255,.1)';this.style.color='#ccc';"><i class="fa fa-times"></i></button>
    </div>

    <form method="POST" action="<?= APP_URL ?>/pages/server_room.php" id="form-sr">
      <input type="hidden" name="_action" id="f-action" value="tambah">
      <input type="hidden" name="id" id="f-id" value="">

      <div style="padding:18px 20px;max-height:74vh;overflow-y:auto;">

        <!-- ① Waktu & Petugas -->
        <div class="f-section"><i class="fa fa-clock"></i> Identitas Pemantauan</div>
        <div class="f-grid3" style="margin-bottom:12px;">
          <div>
            <label class="f-label">Tanggal <span style="color:#ef4444;">*</span></label>
            <input type="date" name="tanggal" id="f-tanggal" class="f-inp" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div>
            <label class="f-label">Waktu <span style="color:#ef4444;">*</span></label>
            <input type="time" name="waktu" id="f-waktu" class="f-inp" value="<?= date('H:i') ?>" required>
          </div>
          <div>
            <label class="f-label">Petugas / Teknisi <span style="color:#ef4444;">*</span></label>
            <input type="text" name="petugas" id="f-petugas" class="f-inp" placeholder="Nama petugas" required value="<?= htmlspecialchars($_SESSION['user_nama'] ?? '') ?>">
          </div>
        </div>

        <!-- ② Suhu & Kelembaban -->
        <div class="f-section"><i class="fa fa-temperature-half"></i> Suhu &amp; Kelembaban</div>
        <div class="f-grid3" style="margin-bottom:12px;">
          <div>
            <label class="f-label">Suhu Dalam Ruangan (°C)</label>
            <input type="number" name="suhu_in" id="f-suhu_in" class="f-inp" placeholder="Contoh: 22.5" step="0.1" min="0" max="60"
              oninput="updateSuhuHint(this,'hint-suhu-in')">
            <div id="hint-suhu-in" style="font-size:10.5px;margin-top:3px;height:14px;"></div>
          </div>
          <div>
            <label class="f-label">Suhu Luar Ruangan (°C)</label>
            <input type="number" name="suhu_out" id="f-suhu_out" class="f-inp" placeholder="Contoh: 30.0" step="0.1" min="0" max="60">
          </div>
          <div>
            <label class="f-label">Kelembaban Udara (%RH)</label>
            <input type="number" name="kelembaban" id="f-kelembaban" class="f-inp" placeholder="Contoh: 55" step="0.1" min="0" max="100"
              oninput="updateLembabHint(this,'hint-lembab')">
            <div id="hint-lembab" style="font-size:10.5px;margin-top:3px;height:14px;"></div>
          </div>
        </div>
        <div style="font-size:10.5px;color:#94a3b8;margin-top:-6px;margin-bottom:12px;padding:6px 10px;background:#f8fafc;border-radius:5px;border-left:3px solid #26B99A;">
          <i class="fa fa-circle-info" style="color:#26B99A;"></i>
          Standar ruangan server: Suhu <strong>18–27°C</strong> &nbsp;|&nbsp; Kelembaban <strong>40–60%RH</strong> (ASHRAE A2)
        </div>

        <!-- ③ Tegangan & UPS -->
        <div class="f-section"><i class="fa fa-bolt"></i> Kelistrikan &amp; UPS</div>
        <div class="f-grid4" style="margin-bottom:12px;">
          <div>
            <label class="f-label">Tegangan PLN (Volt)</label>
            <input type="number" name="tegangan_pln" id="f-tegangan_pln" class="f-inp" placeholder="220" step="0.1" min="0" max="500">
          </div>
          <div>
            <label class="f-label">Tegangan UPS Output (Volt)</label>
            <input type="number" name="tegangan_ups" id="f-tegangan_ups" class="f-inp" placeholder="220" step="0.1" min="0" max="500">
          </div>
          <div>
            <label class="f-label">Beban UPS (%)</label>
            <input type="number" name="beban_ups" id="f-beban_ups" class="f-inp" placeholder="0–100" step="0.1" min="0" max="100"
              oninput="updateBebanHint(this,'hint-beban')">
            <div id="hint-beban" style="font-size:10.5px;margin-top:3px;height:14px;"></div>
          </div>
          <div>
            <label class="f-label">Kapasitas Baterai UPS (%)</label>
            <input type="number" name="baterai_ups" id="f-baterai_ups" class="f-inp" placeholder="0–100" step="0.1" min="0" max="100"
              oninput="updateBatHint(this,'hint-bat')">
            <div id="hint-bat" style="font-size:10.5px;margin-top:3px;height:14px;"></div>
          </div>
        </div>

        <!-- ④ Kondisi Perangkat -->
        <div class="f-section"><i class="fa fa-wind"></i> Kondisi Perangkat &amp; Fasilitas</div>
        <div class="f-grid3" style="margin-bottom:12px;">
          <div>
            <label class="f-label">Kondisi AC Unit 1</label>
            <select name="kondisi_ac1" id="f-kondisi_ac1" class="f-inp">
              <option value="Normal">Normal</option>
              <option value="Perlu Service">Perlu Service</option>
              <option value="Mati">Mati</option>
              <option value="Rusak">Rusak</option>
            </select>
          </div>
          <div>
            <label class="f-label">Kondisi AC Unit 2</label>
            <select name="kondisi_ac2" id="f-kondisi_ac2" class="f-inp">
              <option value="Normal">Normal</option>
              <option value="Perlu Service">Perlu Service</option>
              <option value="Mati">Mati</option>
              <option value="Rusak">Rusak</option>
            </select>
          </div>
          <div>
            <label class="f-label">Kondisi Instalasi Listrik</label>
            <select name="kondisi_listrik" id="f-kondisi_listrik" class="f-inp">
              <option value="Normal">Normal</option>
              <option value="Perlu Pengecekan">Perlu Pengecekan</option>
              <option value="Bermasalah">Bermasalah</option>
            </select>
          </div>
          <div>
            <label class="f-label">Kebersihan Ruangan</label>
            <select name="kondisi_kebersihan" id="f-kondisi_kebersihan" class="f-inp">
              <option value="Bersih">Bersih</option>
              <option value="Kotor">Kotor</option>
              <option value="Perlu Pembersihan">Perlu Pembersihan</option>
            </select>
          </div>
          <div>
            <label class="f-label">Kondisi Pintu Akses</label>
            <select name="kondisi_pintu" id="f-kondisi_pintu" class="f-inp">
              <option value="Terkunci">Terkunci</option>
              <option value="Tidak Terkunci">Tidak Terkunci</option>
              <option value="Rusak">Rusak</option>
            </select>
          </div>
          <div>
            <label class="f-label">Kondisi CCTV</label>
            <select name="kondisi_cctv" id="f-kondisi_cctv" class="f-inp">
              <option value="Normal">Normal</option>
              <option value="Tidak Aktif">Tidak Aktif</option>
              <option value="Rusak">Rusak</option>
              <option value="Rekaman Penuh">Rekaman Penuh</option>
            </select>
          </div>
        </div>

        <!-- ⑤ Alert / Kejadian Khusus -->
        <div class="f-section"><i class="fa fa-bell"></i> Deteksi &amp; Alert</div>
        <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
          <label class="chk-wrap" style="flex:1;min-width:160px;">
            <input type="checkbox" name="ada_alarm" id="f-ada_alarm" value="1">
            <i class="fa fa-bell" style="color:#ef4444;font-size:13px;"></i>
            <label for="f-ada_alarm">Alarm Berbunyi</label>
          </label>
          <label class="chk-wrap" style="flex:1;min-width:160px;">
            <input type="checkbox" name="ada_banjir" id="f-ada_banjir" value="1">
            <i class="fa fa-water" style="color:#0891b2;font-size:13px;"></i>
            <label for="f-ada_banjir">Deteksi Air / Banjir</label>
          </label>
          <label class="chk-wrap" style="flex:1;min-width:160px;">
            <input type="checkbox" name="ada_asap" id="f-ada_asap" value="1">
            <i class="fa fa-smog" style="color:#d97706;font-size:13px;"></i>
            <label for="f-ada_asap">Deteksi Asap / Kebakaran</label>
          </label>
        </div>

        <!-- ⑥ Status Keseluruhan & Catatan -->
        <div class="f-section"><i class="fa fa-clipboard-check"></i> Penilaian &amp; Catatan</div>
        <div class="f-grid2" style="margin-bottom:12px;">
          <div>
            <label class="f-label">Status Keseluruhan <span style="color:#ef4444;">*</span></label>
            <select name="status_overall" id="f-status_overall" class="f-inp" required>
              <option value="Normal">Normal — Semua kondisi baik</option>
              <option value="Perhatian">Perhatian — Ada kondisi yang perlu dimonitor</option>
              <option value="Kritis">Kritis — Ada kondisi darurat / segera ditangani</option>
            </select>
          </div>
          <div>
            <label class="f-label">Catatan / Temuan</label>
            <textarea name="catatan" id="f-catatan" rows="2" class="f-inp" placeholder="Tuliskan temuan, tindakan yang dilakukan, atau catatan penting…" style="resize:vertical;"></textarea>
          </div>
        </div>

      </div><!-- /scroll -->

      <!-- Footer -->
      <div style="padding:12px 20px;border-top:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
        <span style="font-size:11px;color:#94a3b8;"><i class="fa fa-asterisk" style="color:#ef4444;font-size:8px;"></i> Wajib diisi</span>
        <div style="display:flex;gap:8px;">
          <button type="button" onclick="tutupModal()" style="padding:7px 16px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
          <button type="submit" style="padding:7px 18px;background:linear-gradient(135deg,#26B99A,#1a7a5e);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;">
            <i class="fa fa-save"></i> <span id="btn-submit-lbl">Simpan Data</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- ══ MODAL HAPUS ══ -->
<div class="modal-ov" id="m-hapus-sr" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:360px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:20px 22px 14px;text-align:center;">
      <div style="width:50px;height:50px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;"><i class="fa fa-trash" style="color:#ef4444;font-size:20px;"></i></div>
      <div style="font-size:14px;font-weight:700;color:#1e293b;">Hapus Data Pemantauan?</div>
      <div style="font-size:12px;color:#64748b;margin-top:6px;">Log tanggal <strong id="hapus-tgl"></strong> akan dihapus permanen.</div>
    </div>
    <form method="POST" action="<?= APP_URL ?>/pages/server_room.php">
      <input type="hidden" name="_action" value="hapus">
      <input type="hidden" name="id" id="hapus-id">
      <div style="padding:12px 20px;display:flex;gap:8px;justify-content:center;border-top:1px solid #f0f0f0;">
        <button type="button" onclick="closeModal('m-hapus-sr')" style="padding:7px 18px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
        <button type="submit" style="padding:7px 18px;background:#ef4444;border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"><i class="fa fa-trash"></i> Hapus</button>
      </div>
    </form>
  </div>
</div>


<script>
const APP_URL = '<?= APP_URL ?>';

/* ── Hint suhu ─────────────────────────────────────────────── */
function updateSuhuHint(inp, hintId) {
    const v = parseFloat(inp.value);
    const el = document.getElementById(hintId);
    if (isNaN(v)) { el.innerHTML=''; return; }
    if (v <= 18)      el.innerHTML = '<span style="color:#0891b2;">Terlalu Dingin — cek AC</span>';
    else if (v <= 22) el.innerHTML = '<span style="color:#0891b2;">Dingin — OK</span>';
    else if (v <= 26) el.innerHTML = '<span style="color:#16a34a;font-weight:700;">Ideal ✓</span>';
    else if (v <= 27) el.innerHTML = '<span style="color:#16a34a;">Batas atas ideal</span>';
    else if (v <= 30) el.innerHTML = '<span style="color:#d97706;font-weight:600;">Hangat — perlu perhatian</span>';
    else              el.innerHTML = '<span style="color:#dc2626;font-weight:700;">PANAS — segera cek AC!</span>';
}
function updateLembabHint(inp, hintId) {
    const v = parseFloat(inp.value);
    const el = document.getElementById(hintId);
    if (isNaN(v)) { el.innerHTML=''; return; }
    if (v < 40)       el.innerHTML = '<span style="color:#0891b2;">Terlalu Kering</span>';
    else if (v <= 60) el.innerHTML = '<span style="color:#16a34a;font-weight:700;">Ideal ✓</span>';
    else if (v <= 70) el.innerHTML = '<span style="color:#d97706;font-weight:600;">Lembab — perlu perhatian</span>';
    else              el.innerHTML = '<span style="color:#dc2626;font-weight:700;">Sangat Lembab — berbahaya!</span>';
}
function updateBebanHint(inp, hintId) {
    const v = parseFloat(inp.value);
    const el = document.getElementById(hintId);
    if (isNaN(v)) { el.innerHTML=''; return; }
    if (v <= 60)      el.innerHTML = '<span style="color:#16a34a;">Normal</span>';
    else if (v <= 80) el.innerHTML = '<span style="color:#d97706;font-weight:600;">Tinggi — perhatian</span>';
    else              el.innerHTML = '<span style="color:#dc2626;font-weight:700;">Overload — cek segera!</span>';
}
function updateBatHint(inp, hintId) {
    const v = parseFloat(inp.value);
    const el = document.getElementById(hintId);
    if (isNaN(v)) { el.innerHTML=''; return; }
    if (v >= 80)      el.innerHTML = '<span style="color:#16a34a;">Penuh</span>';
    else if (v >= 50) el.innerHTML = '<span style="color:#16a34a;">Cukup</span>';
    else if (v >= 20) el.innerHTML = '<span style="color:#d97706;font-weight:600;">Perlu Charge</span>';
    else              el.innerHTML = '<span style="color:#dc2626;font-weight:700;">Kritis — segera charge!</span>';
}

/* ── Buka modal tambah ──────────────────────────────────────── */
function bukaModalTambah() {
    resetForm();
    document.getElementById('f-tanggal').value = new Date().toISOString().slice(0,10);
    document.getElementById('f-waktu').value   = new Date().toTimeString().slice(0,5);
    openModal('m-sr');
}

/* ── Edit log ───────────────────────────────────────────────── */
function editLog(id) {
    fetch(APP_URL + '/pages/server_room.php?get_log=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('m-title').textContent      = 'Edit Data Pemantauan';
            document.getElementById('m-icon').className         = 'fa fa-pen';
            document.getElementById('btn-submit-lbl').textContent = 'Perbarui Data';
            document.getElementById('f-action').value = 'edit';
            document.getElementById('f-id').value     = d.id;

            document.getElementById('f-tanggal').value          = d.tanggal         || '';
            document.getElementById('f-waktu').value            = d.waktu           || '';
            document.getElementById('f-petugas').value          = d.petugas         || '';
            document.getElementById('f-suhu_in').value          = d.suhu_in         || '';
            document.getElementById('f-suhu_out').value         = d.suhu_out        || '';
            document.getElementById('f-kelembaban').value       = d.kelembaban      || '';
            document.getElementById('f-tegangan_pln').value     = d.tegangan_pln    || '';
            document.getElementById('f-tegangan_ups').value     = d.tegangan_ups    || '';
            document.getElementById('f-beban_ups').value        = d.beban_ups       || '';
            document.getElementById('f-baterai_ups').value      = d.baterai_ups     || '';
            document.getElementById('f-kondisi_ac1').value      = d.kondisi_ac1     || 'Normal';
            document.getElementById('f-kondisi_ac2').value      = d.kondisi_ac2     || 'Normal';
            document.getElementById('f-kondisi_listrik').value  = d.kondisi_listrik || 'Normal';
            document.getElementById('f-kondisi_kebersihan').value = d.kondisi_kebersihan || 'Bersih';
            document.getElementById('f-kondisi_pintu').value    = d.kondisi_pintu   || 'Terkunci';
            document.getElementById('f-kondisi_cctv').value     = d.kondisi_cctv    || 'Normal';
            document.getElementById('f-ada_alarm').checked      = d.ada_alarm  == 1;
            document.getElementById('f-ada_banjir').checked     = d.ada_banjir == 1;
            document.getElementById('f-ada_asap').checked       = d.ada_asap   == 1;
            document.getElementById('f-catatan').value          = d.catatan         || '';
            document.getElementById('f-status_overall').value   = d.status_overall  || 'Normal';

            // Update hints
            updateSuhuHint(document.getElementById('f-suhu_in'),    'hint-suhu-in');
            updateLembabHint(document.getElementById('f-kelembaban'),'hint-lembab');
            updateBebanHint(document.getElementById('f-beban_ups'), 'hint-beban');
            updateBatHint(document.getElementById('f-baterai_ups'), 'hint-bat');

            openModal('m-sr');
        });
}

/* ── Hapus ──────────────────────────────────────────────────── */
function hapusLog(id, tgl) {
    document.getElementById('hapus-id').value           = id;
    document.getElementById('hapus-tgl').textContent    = tgl;
    openModal('m-hapus-sr');
}

/* ── Reset & tutup ──────────────────────────────────────────── */
function tutupModal() {
    closeModal('m-sr');
    resetForm();
}
function resetForm() {
    document.getElementById('form-sr').reset();
    document.getElementById('f-action').value           = 'tambah';
    document.getElementById('f-id').value               = '';
    document.getElementById('m-title').textContent      = 'Input Pemantauan Ruangan Server';
    document.getElementById('m-icon').className         = 'fa fa-plus';
    document.getElementById('btn-submit-lbl').textContent = 'Simpan Data';
    ['hint-suhu-in','hint-lembab','hint-beban','hint-bat'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '';
    });
}

document.getElementById('m-sr').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
</script>

<?php include '../includes/footer.php'; ?>