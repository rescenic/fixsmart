<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

$dompdf_path = __DIR__ . '/../dompdf/autoload.inc.php';
if (!file_exists($dompdf_path)) { die('dompdf tidak ditemukan.'); }
require_once $dompdf_path;

use Dompdf\Dompdf;
use Dompdf\Options;

// ── Parameter ────────────────────────────────────────────────────────────────
$tgl_dari  = $_GET['tgl_dari']  ?? date('Y-m-01');          // default awal bulan
$tgl_sampai= $_GET['tgl_sampai']?? date('Y-m-t');           // default akhir bulan
$fk        = $_GET['kat']       ?? '';                       // kategori_id
$fs        = $_GET['status']    ?? '';                       // status
$fp        = $_GET['prioritas'] ?? '';                       // prioritas

// ── Dropdown data ─────────────────────────────────────────────────────────────
$kat_list  = $pdo->query("SELECT * FROM kategori ORDER BY nama")->fetchAll();
$kat_map   = [];
foreach ($kat_list as $k) $kat_map[$k['id']] = $k['nama'];

// ── Query ────────────────────────────────────────────────────────────────────
$where  = ["DATE(t.waktu_submit) >= ?", "DATE(t.waktu_submit) <= ?"];
$params = [$tgl_dari, $tgl_sampai];

if ($fk) { $where[] = 't.kategori_id=?'; $params[] = $fk; }
if ($fs) { $where[] = 't.status=?';      $params[] = $fs; }
if ($fp) { $where[] = 't.prioritas=?';   $params[] = $fp; }

$wsql = implode(' AND ', $where);

$st = $pdo->prepare("
    SELECT t.*, k.nama as kat_nama, k.sla_jam,
           u.nama as req_nama, u.divisi,
           tek.nama as tek_nama
    FROM tiket t
    LEFT JOIN kategori k   ON k.id   = t.kategori_id
    LEFT JOIN users u      ON u.id   = t.user_id
    LEFT JOIN users tek    ON tek.id = t.teknisi_id
    WHERE $wsql
    ORDER BY t.waktu_submit ASC
");
$st->execute($params);
$tikets = $st->fetchAll();
$total  = count($tikets);

// ── Statistik ─────────────────────────────────────────────────────────────────
$stat_status   = [];
$stat_prioritas= [];
$stat_kategori = [];
$total_sla_met = 0;
$total_solved  = 0;
$total_nilai_durasi = 0;
$count_durasi  = 0;

foreach ($tikets as $t) {
    $stat_status[$t['status']]       = ($stat_status[$t['status']] ?? 0) + 1;
    $stat_prioritas[$t['prioritas']] = ($stat_prioritas[$t['prioritas']] ?? 0) + 1;
    $kat_n = $t['kat_nama'] ?: 'Tanpa Kategori';
    $stat_kategori[$kat_n]           = ($stat_kategori[$kat_n] ?? 0) + 1;
    if ($t['status'] === 'selesai') {
        $total_solved++;
        if ($t['durasi_selesai_menit'] && $t['sla_jam'] && $t['durasi_selesai_menit'] <= $t['sla_jam'] * 60)
            $total_sla_met++;
    }
    if ($t['durasi_selesai_menit']) {
        $total_nilai_durasi += $t['durasi_selesai_menit'];
        $count_durasi++;
    }
}
$sla_pct   = $total_solved > 0 ? round($total_sla_met / $total_solved * 100) : 0;
$avg_durasi= $count_durasi  > 0 ? round($total_nilai_durasi / $count_durasi) : 0;

// Kelompokkan per kategori
$by_kat = [];
foreach ($tikets as $t) {
    $kat_n = $t['kat_nama'] ?: 'Tanpa Kategori';
    $by_kat[$kat_n][] = $t;
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function statusLabel(string $s): array {
    return match($s) {
        'menunggu'   => ['Menunggu',      '#fef9c3','#a16207'],
        'diproses'   => ['Diproses',      '#dbeafe','#1d4ed8'],
        'selesai'    => ['Selesai',       '#dcfce7','#15803d'],
        'ditolak'    => ['Ditolak',       '#fee2e2','#b91c1c'],
        'tidak_bisa' => ['Tidak Bisa',    '#f3e8ff','#7c3aed'],
        default      => [$s,              '#f1f5f9','#64748b'],
    };
}
function prioritasLabel(string $p): array {
    return match($p) {
        'Tinggi' => ['Tinggi','#fee2e2','#b91c1c'],
        'Sedang' => ['Sedang','#fef9c3','#a16207'],
        'Rendah' => ['Rendah','#dcfce7','#15803d'],
        default  => [$p,      '#f1f5f9','#64748b'],
    };
}
function fmtDur(int $menit): string {
    if ($menit <= 0) return '—';
    if ($menit < 60) return $menit . ' mnt';
    if ($menit < 1440) return round($menit/60,1) . ' jam';
    return round($menit/1440,1) . ' hari';
}
function fmtTgl(?string $d): string {
    if (!$d) return '—';
    return date('d/m/Y H:i', strtotime($d));
}
function fmtTglShort(?string $d): string {
    if (!$d) return '—';
    return date('d/m/Y', strtotime($d));
}

function slaColor(int $pct): array {
    if ($pct >= 90) return ['bg'=>'#dcfce7','fg'=>'#15803d','bar'=>'#16a34a','label'=>'BAIK'];
    if ($pct >= 70) return ['bg'=>'#fef9c3','fg'=>'#a16207','bar'=>'#ca8a04','label'=>'CUKUP'];
    return              ['bg'=>'#fee2e2','fg'=>'#b91c1c','bar'=>'#dc2626','label'=>'KURANG'];
}
$ov_color = slaColor($sla_pct);

// ── Label filter ─────────────────────────────────────────────────────────────
$label_kat    = $fk ? ($kat_map[$fk] ?? 'Kategori #'.$fk) : 'Semua Kategori';
$label_status = $fs ? ucfirst(str_replace('_',' ',$fs)) : 'Semua Status';
$label_prior  = $fp ?: 'Semua Prioritas';
$periode_str  = date('d F Y', strtotime($tgl_dari)) . ' s.d. ' . date('d F Y', strtotime($tgl_sampai));
$no_dok       = 'RPT-TKT-' . date('Ymd') . '-' . strtoupper(substr(md5($tgl_dari.$tgl_sampai.$fk.$fs), 0, 4));

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    color: #1a1a2e;
    background: #fff;
    line-height: 1.5;
    margin: 36px 46px 36px 46px;
}

/* ── KOP ── */
.kop { display:table; width:100%; padding-bottom:10px; border-bottom:3px solid #0f172a; margin-bottom:4px; }
.kop-logo-area { display:table-cell; vertical-align:middle; width:54px; }
.kop-logo-box  { width:44px;height:44px;background:#0f172a;border-radius:6px;text-align:center;line-height:44px;font-size:17px;color:#fff;font-weight:bold; }
.kop-text  { display:table-cell; vertical-align:middle; padding-left:11px; }
.kop-org   { font-size:14pt; font-weight:bold; color:#0f172a; line-height:1.2; }
.kop-sub   { font-size:8pt; color:#475569; margin-top:1px; }
.kop-right { display:table-cell; vertical-align:middle; text-align:right; }
.kop-right-label { font-size:7pt; color:#94a3b8; letter-spacing:1px; text-transform:uppercase; }
.kop-right-val   { font-size:8.5pt; color:#334155; font-weight:bold; }
.kop-rule2 { height:1px; background:#cbd5e1; margin-bottom:14px; }

/* ── JUDUL ── */
.report-title-block { text-align:center; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid #e2e8f0; }
.report-label       { font-size:7pt; letter-spacing:3px; text-transform:uppercase; color:#64748b; margin-bottom:4px; }
.report-main-title  { font-size:14pt; font-weight:bold; color:#0f172a; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.report-sub         { font-size:10pt; color:#1d4ed8; font-weight:bold; }
.report-no          { font-size:7.5pt; color:#94a3b8; margin-top:4px; }

/* ── INFO DOKUMEN ── */
.doc-info { width:100%; border-collapse:collapse; margin-bottom:14px; border:1px solid #e2e8f0; }
.doc-info td { padding:5px 9px; font-size:8.5pt; border:1px solid #e2e8f0; vertical-align:top; }
.di-label { background:#f8fafc; color:#475569; font-weight:bold; width:18%; }
.di-val   { color:#1e293b; width:32%; }

/* ── SECTION ── */
.sec { margin-top:14px; margin-bottom:8px; page-break-after:avoid; }
.sec-num   { font-size:7pt; letter-spacing:2px; text-transform:uppercase; color:#94a3b8; margin-bottom:2px; }
.sec-title { font-size:10pt; font-weight:bold; color:#0f172a; border-bottom:2px solid #1d4ed8; padding-bottom:4px; display:table; width:100%; }
.sec-title-text { display:table-cell; }
.sec-title-rule { display:table-cell; text-align:right; font-size:7.5pt; font-weight:normal; color:#94a3b8; vertical-align:bottom; }

/* ── EXEC BOX ── */
.exec-box { border-left:4px solid #1d4ed8; background:#f0f6ff; padding:10px 14px; margin-bottom:6px; border-radius:0 4px 4px 0; font-size:9pt; color:#1e293b; line-height:1.7; }

/* ── KPI CARDS ── */
.kpi-table { width:100%; border-collapse:separate; border-spacing:6px; margin:0 -6px; }
.kpi-td    { vertical-align:top; }
.kpi-card  { border:1px solid #e2e8f0; border-top:3px solid #1d4ed8; border-radius:0 0 4px 4px; padding:9px 12px 8px; background:#fff; }
.kpi-card .k-label { font-size:7pt; letter-spacing:1px; text-transform:uppercase; color:#64748b; margin-bottom:4px; }
.kpi-card .k-val   { font-size:17pt; font-weight:bold; line-height:1; margin-bottom:3px; }
.kpi-card .k-desc  { font-size:7.5pt; color:#94a3b8; line-height:1.4; }

/* ── SLA GAUGE ── */
.sla-wrap  { display:table; width:100%; }
.sla-left  { display:table-cell; width:38%; vertical-align:top; padding-right:14px; }
.sla-right { display:table-cell; vertical-align:top; }
.gauge-box { border:1px solid #e2e8f0; border-radius:4px; padding:16px; text-align:center; background:#f8fafc; }
.g-pct     { font-size:38pt; font-weight:bold; line-height:1; margin-bottom:4px; }
.g-sub     { font-size:8pt; color:#64748b; margin-bottom:10px; line-height:1.5; }
.g-track   { background:#e2e8f0; height:9px; border-radius:5px; width:160px; margin:0 auto 5px; overflow:hidden; }
.g-fill    { height:9px; border-radius:5px; }
.g-hint    { font-size:7.5pt; color:#94a3b8; margin-bottom:10px; }
.g-chip    { display:inline-block; padding:3px 14px; border-radius:20px; font-size:8pt; font-weight:bold; letter-spacing:1.5px; text-transform:uppercase; }

/* ── TABEL RINGKASAN ── */
.summ-tbl { width:100%; border-collapse:collapse; font-size:8.5pt; }
.summ-tbl th { background:#0f172a; color:#fff; padding:7px 9px; font-size:7.5pt; text-align:left; }
.summ-tbl td { padding:6px 9px; border-bottom:1px solid #f1f5f9; font-size:8.5pt; }
.summ-tbl tr:nth-child(even) td { background:#f8fafc; }
.summ-tbl tfoot td { background:#eff6ff; border-top:2px solid #bfdbfe; font-weight:bold; color:#1e3a8a; }

/* ── TABEL TIKET ── */
.kat-header { background:#1d4ed8; color:#fff; padding:6px 10px; font-size:8.5pt; font-weight:bold; border-radius:3px 3px 0 0; margin-top:12px; display:table; width:100%; }
.kat-header-l { display:table-cell; }
.kat-header-r { display:table-cell; text-align:right; font-size:7.5pt; font-weight:normal; color:rgba(255,255,255,.7); }

.data-tbl { width:100%; border-collapse:collapse; font-size:8pt; page-break-inside:auto; }
.data-tbl thead tr  { background:#0f172a; color:#fff; }
.data-tbl thead th  { padding:6px 7px; text-align:left; font-size:7.5pt; font-weight:bold; border-right:1px solid rgba(255,255,255,.08); }
.data-tbl thead th:last-child { border-right:none; }
.data-tbl tbody tr td { padding:5px 7px; border-bottom:1px solid #f1f5f9; vertical-align:top; font-size:7.5pt; }
.data-tbl tbody tr:nth-child(even) td { background:#f8fafc; }
.data-tbl tbody tr:last-child td     { border-bottom:2px solid #e2e8f0; }
.data-tbl tfoot td  { padding:6px 7px; font-weight:bold; background:#eff6ff; border-top:2px solid #bfdbfe; color:#1e3a8a; font-size:8pt; }

.badge { display:inline-block; padding:2px 7px; border-radius:3px; font-size:7.5pt; font-weight:bold; white-space:nowrap; }
.bar-wrap { background:#e2e8f0; height:5px; border-radius:3px; overflow:hidden; display:inline-block; vertical-align:middle; }
.bar-fill { height:5px; border-radius:3px; }

/* ── TTD ── */
.ttd-section { margin-top:22px; display:table; width:100%; }
.ttd-box   { display:table-cell; width:33.3%; text-align:center; padding:0 10px; vertical-align:top; }
.ttd-title { font-size:8.5pt; color:#475569; margin-bottom:46px; }
.ttd-line  { border-top:1px solid #334155; margin:0 12px 4px; }
.ttd-name  { font-size:8.5pt; font-weight:bold; color:#0f172a; }
.ttd-role  { font-size:7.5pt; color:#64748b; margin-top:2px; }

/* ── FOOTER ── */
.page-footer { margin-top:16px; padding-top:7px; border-top:1px solid #cbd5e1; display:table; width:100%; }
.pf-left  { display:table-cell; font-size:7pt; color:#94a3b8; vertical-align:middle; }
.pf-right { display:table-cell; text-align:right; font-size:7pt; color:#94a3b8; vertical-align:middle; }
</style>
</head>
<body>

<!-- ══ KOP ══ -->
<div class="kop">
  <div class="kop-logo-area"><div class="kop-logo-box">FS</div></div>
  <div class="kop-text">
    <div class="kop-org">FixSmart Helpdesk</div>
    <div class="kop-sub">Divisi Teknologi Informasi &mdash; Sistem Manajemen Layanan IT</div>
  </div>
  <div class="kop-right">
    <div class="kop-right-label">No. Dokumen</div>
    <div class="kop-right-val"><?= $no_dok ?></div>
    <div style="margin-top:4px;">
      <div class="kop-right-label">Tanggal Cetak</div>
      <div class="kop-right-val"><?= date('d/m/Y') ?></div>
    </div>
  </div>
</div>
<div class="kop-rule2"></div>

<!-- ══ JUDUL ══ -->
<div class="report-title-block">
  <div class="report-label">Laporan Resmi &mdash; Internal Use Only</div>
  <div class="report-main-title">Laporan Data Tiket Helpdesk</div>
  <div class="report-sub">Periode: <?= $periode_str ?></div>
  <div class="report-no">
    Disiapkan oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
    &nbsp;|&nbsp; Dicetak: <?= date('d F Y, H:i') ?> WIB
  </div>
</div>

<!-- ══ INFO DOKUMEN ══ -->
<table class="doc-info">
  <tr>
    <td class="di-label">Unit</td>
    <td class="di-val">Divisi Teknologi Informasi</td>
    <td class="di-label">Periode Laporan</td>
    <td class="di-val"><?= $periode_str ?></td>
  </tr>
  <tr>
    <td class="di-label">Filter Kategori</td>
    <td class="di-val"><?= htmlspecialchars($label_kat) ?></td>
    <td class="di-label">Filter Status</td>
    <td class="di-val"><?= htmlspecialchars($label_status) ?></td>
  </tr>
  <tr>
    <td class="di-label">Filter Prioritas</td>
    <td class="di-val"><?= htmlspecialchars($label_prior) ?></td>
    <td class="di-label">Status Dokumen</td>
    <td class="di-val">Final &mdash; Confidential</td>
  </tr>
  <tr>
    <td class="di-label">Total Tiket</td>
    <td class="di-val"><strong><?= $total ?> tiket</strong></td>
    <td class="di-label">Pencapaian SLA</td>
    <td class="di-val">
      <strong style="color:<?= $ov_color['bar'] ?>;"><?= $sla_pct ?>%</strong>
      &nbsp;
      <span class="badge" style="background:<?= $ov_color['bg'] ?>;color:<?= $ov_color['fg'] ?>;"><?= $ov_color['label'] ?></span>
    </td>
  </tr>
</table>

<!-- ══ I. RINGKASAN EKSEKUTIF ══ -->
<div class="sec">
  <div class="sec-num">Bagian I</div>
  <div class="sec-title">
    <span class="sec-title-text">Ringkasan Eksekutif</span>
    <span class="sec-title-rule">Executive Summary</span>
  </div>
</div>
<div class="exec-box">
  Laporan ini menyajikan data tiket helpdesk IT untuk periode <strong><?= $periode_str ?></strong>,
  kategori <strong><?= htmlspecialchars($label_kat) ?></strong>,
  status <strong><?= htmlspecialchars($label_status) ?></strong>.
  Total tiket yang tercatat sebanyak <strong><?= $total ?> tiket</strong>
  yang tersebar dalam <strong><?= count($by_kat) ?> kategori</strong>.
  <?php
  $n_selesai = $stat_status['selesai']    ?? 0;
  $n_tunggu  = $stat_status['menunggu']   ?? 0;
  $n_proses  = $stat_status['diproses']   ?? 0;
  $n_tolak   = $stat_status['ditolak']    ?? 0;
  $n_tdk     = $stat_status['tidak_bisa'] ?? 0;
  $pct_selesai = $total > 0 ? round($n_selesai / $total * 100) : 0;
  ?>
  Dari total tersebut, <strong><?= $n_selesai ?> tiket (<?= $pct_selesai ?>%)</strong> telah diselesaikan,
  <strong><?= $n_tunggu ?></strong> menunggu, <strong><?= $n_proses ?></strong> sedang diproses,
  dan <strong><?= $n_tolak + $n_tdk ?></strong> ditolak/tidak bisa ditangani.
  Tingkat pencapaian SLA periode ini sebesar <strong><?= $sla_pct ?>%</strong>
  dengan rata-rata durasi penyelesaian <strong><?= fmtDur($avg_durasi) ?></strong>.
</div>

<!-- ══ II. KPI ══ -->
<div class="sec">
  <div class="sec-num">Bagian II</div>
  <div class="sec-title"><span class="sec-title-text">Indikator Kinerja Utama (KPI)</span></div>
</div>
<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#1d4ed8;">
        <div class="k-label">Total Tiket</div>
        <div class="k-val" style="color:#1d4ed8;"><?= $total ?></div>
        <div class="k-desc">Semua tiket periode ini</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#16a34a;">
        <div class="k-label">Selesai</div>
        <div class="k-val" style="color:#16a34a;"><?= $n_selesai ?></div>
        <div class="k-desc">Berhasil ditangani & ditutup</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#0891b2;">
        <div class="k-label">Aktif</div>
        <div class="k-val" style="color:#0891b2;"><?= $n_tunggu + $n_proses ?></div>
        <div class="k-desc"><?= $n_tunggu ?> menunggu &nbsp;/&nbsp; <?= $n_proses ?> diproses</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#dc2626;">
        <div class="k-label">Ditolak / Tdk Bisa</div>
        <div class="k-val" style="color:#dc2626;"><?= $n_tolak + $n_tdk ?></div>
        <div class="k-desc"><?= $n_tolak ?> ditolak &nbsp;/&nbsp; <?= $n_tdk ?> tidak bisa</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#7c3aed;">
        <div class="k-label">Avg. Durasi Selesai</div>
        <div class="k-val" style="color:#7c3aed;font-size:14pt;padding-top:2px;"><?= fmtDur($avg_durasi) ?></div>
        <div class="k-desc">Rata-rata penyelesaian tiket</div>
      </div>
    </td>
  </tr>
</table>

<!-- ══ III. SLA + DISTRIBUSI ══ -->
<div class="sec">
  <div class="sec-num">Bagian III</div>
  <div class="sec-title"><span class="sec-title-text">Pencapaian SLA &amp; Distribusi Status</span></div>
</div>
<div class="sla-wrap">
  <!-- Gauge -->
  <div class="sla-left">
    <div class="gauge-box">
      <div style="font-size:7.5pt;letter-spacing:2px;color:#64748b;text-transform:uppercase;margin-bottom:7px;">SLA Achievement Rate</div>
      <div class="g-pct" style="color:<?= $ov_color['bar'] ?>;"><?= $sla_pct ?>%</div>
      <div class="g-sub">
        <?= $total_sla_met ?> dari <?= $total_solved ?> tiket selesai<br>
        diselesaikan dalam target SLA
      </div>
      <div class="g-track">
        <div class="g-fill" style="width:<?= $sla_pct ?>%;background:<?= $ov_color['bar'] ?>;"></div>
      </div>
      <div class="g-hint">Target minimal &ge; 90%</div>
      <span class="g-chip" style="background:<?= $ov_color['bg'] ?>;color:<?= $ov_color['fg'] ?>;"><?= $ov_color['label'] ?></span>
    </div>
  </div>

  <!-- Distribusi status -->
  <div class="sla-right">
    <table class="summ-tbl">
      <thead>
        <tr>
          <th>Status</th>
          <th style="text-align:right;">Jumlah</th>
          <th style="text-align:right;">%</th>
          <th>Proporsi</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $dist_st = [
          ['Selesai',    $n_selesai,       '#16a34a'],
          ['Diproses',   $n_proses,        '#0891b2'],
          ['Menunggu',   $n_tunggu,        '#d97706'],
          ['Ditolak',    $n_tolak,         '#dc2626'],
          ['Tidak Bisa', $n_tdk,           '#7c3aed'],
        ];
        $tot_d = max($total, 1);
        foreach ($dist_st as [$lbl, $val, $col]):
          $p = round($val / $tot_d * 100);
        ?>
        <tr>
          <td style="font-weight:bold;">
            <span style="display:inline-block;width:8px;height:8px;background:<?= $col ?>;border-radius:50%;margin-right:5px;vertical-align:middle;"></span>
            <?= $lbl ?>
          </td>
          <td style="text-align:right;font-weight:bold;"><?= $val ?></td>
          <td style="text-align:right;"><?= $p ?>%</td>
          <td>
            <div class="bar-wrap" style="width:80px;">
              <div class="bar-fill" style="width:<?= $p ?>%;background:<?= $col ?>;"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td>TOTAL</td>
          <td style="text-align:right;"><?= $total ?></td>
          <td style="text-align:right;">100%</td>
          <td>&mdash;</td>
        </tr>
      </tfoot>
    </table>

    <!-- Distribusi prioritas -->
    <div style="margin-top:8px;">
    <table class="summ-tbl">
      <thead>
        <tr>
          <th>Prioritas</th>
          <th style="text-align:right;">Jumlah</th>
          <th style="text-align:right;">%</th>
          <th>Proporsi</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $pcol = ['Tinggi'=>'#dc2626','Sedang'=>'#d97706','Rendah'=>'#16a34a'];
        arsort($stat_prioritas);
        foreach ($stat_prioritas as $pk => $pv):
          $pp = round($pv / $tot_d * 100);
          $pc = $pcol[$pk] ?? '#64748b';
        ?>
        <tr>
          <td style="font-weight:bold;">
            <span style="display:inline-block;width:8px;height:8px;background:<?= $pc ?>;border-radius:50%;margin-right:5px;vertical-align:middle;"></span>
            <?= htmlspecialchars($pk) ?>
          </td>
          <td style="text-align:right;font-weight:bold;"><?= $pv ?></td>
          <td style="text-align:right;"><?= $pp ?>%</td>
          <td>
            <div class="bar-wrap" style="width:80px;">
              <div class="bar-fill" style="width:<?= $pp ?>%;background:<?= $pc ?>;"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- ══ IV. RINGKASAN PER KATEGORI ══ -->
<div class="sec">
  <div class="sec-num">Bagian IV</div>
  <div class="sec-title"><span class="sec-title-text">Ringkasan Tiket per Kategori</span></div>
</div>
<table class="summ-tbl">
  <thead>
    <tr>
      <th>No.</th>
      <th>Kategori</th>
      <th style="text-align:right;">Total</th>
      <th style="text-align:right;">Selesai</th>
      <th style="text-align:right;">Aktif</th>
      <th style="text-align:right;">Ditolak</th>
      <th style="text-align:right;">Tdk Bisa</th>
      <th style="text-align:right;">% Selesai</th>
      <th>Proporsi</th>
    </tr>
  </thead>
  <tbody>
    <?php $no2 = 0; foreach ($by_kat as $kn => $items):
      $no2++;
      $kn_sel  = count(array_filter($items, fn($x) => $x['status'] === 'selesai'));
      $kn_akt  = count(array_filter($items, fn($x) => in_array($x['status'],['menunggu','diproses'])));
      $kn_dtl  = count(array_filter($items, fn($x) => $x['status'] === 'ditolak'));
      $kn_tdk  = count(array_filter($items, fn($x) => $x['status'] === 'tidak_bisa'));
      $kn_tot  = count($items);
      $kn_psel = $kn_tot > 0 ? round($kn_sel / $kn_tot * 100) : 0;
      $kn_ptot = $total  > 0 ? round($kn_tot / $total  * 100) : 0;
    ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $no2 ?></td>
      <td style="font-weight:bold;"><?= htmlspecialchars($kn) ?></td>
      <td style="text-align:right;font-weight:bold;"><?= $kn_tot ?></td>
      <td style="text-align:right;color:#16a34a;font-weight:bold;"><?= $kn_sel ?></td>
      <td style="text-align:right;color:#0891b2;"><?= $kn_akt ?></td>
      <td style="text-align:right;color:#dc2626;"><?= $kn_dtl ?></td>
      <td style="text-align:right;color:#7c3aed;"><?= $kn_tdk ?></td>
      <td style="text-align:right;">
        <strong style="color:<?= $kn_psel >= 80 ? '#16a34a' : ($kn_psel >= 50 ? '#d97706' : '#dc2626') ?>;"><?= $kn_psel ?>%</strong>
      </td>
      <td>
        <div class="bar-wrap" style="width:70px;">
          <div class="bar-fill" style="width:<?= $kn_ptot ?>%;background:#1d4ed8;"></div>
        </div>
        <span style="font-size:7pt;margin-left:3px;"><?= $kn_ptot ?>%</span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">TOTAL</td>
      <td style="text-align:right;"><?= $total ?></td>
      <td style="text-align:right;color:#16a34a;"><?= $n_selesai ?></td>
      <td style="text-align:right;color:#0891b2;"><?= $n_tunggu + $n_proses ?></td>
      <td style="text-align:right;color:#dc2626;"><?= $n_tolak ?></td>
      <td style="text-align:right;color:#7c3aed;"><?= $n_tdk ?></td>
      <td style="text-align:right;"><?= $pct_selesai ?>%</td>
      <td>100%</td>
    </tr>
  </tfoot>
</table>

<!-- ══ V. DETAIL TIKET ══ -->
<div class="sec">
  <div class="sec-num">Bagian V</div>
  <div class="sec-title">
    <span class="sec-title-text">Detail Data Tiket</span>
    <span class="sec-title-rule">Diurutkan per Kategori &amp; Tanggal Masuk &nbsp;|&nbsp; Total: <?= $total ?> tiket</span>
  </div>
</div>

<?php if (empty($tikets)): ?>
<div style="text-align:center;color:#94a3b8;padding:18px;border:1px dashed #e2e8f0;border-radius:4px;font-style:italic;">
  Tidak ada data tiket untuk filter yang dipilih.
</div>
<?php else: ?>

<table class="data-tbl">
  <thead>
    <tr>
      <th style="width:22px;">#</th>
      <th style="width:82px;">No. Tiket</th>
      <th style="width:80px;">Kategori</th>
      <th style="width:148px;">Judul</th>
      <th style="width:50px;">Prioritas</th>
      <th style="width:95px;">Pemohon / Divisi</th>
      <th style="width:80px;">Teknisi</th>
      <th style="width:52px;">Status</th>
      <th style="width:72px;">Tgl Masuk</th>
      <th style="width:72px;">Tgl Selesai</th>
      <th style="width:46px;">Durasi</th>
      <th style="width:36px;">SLA</th>
    </tr>
  </thead>
  <tbody>
    <?php
    // Sort semua tiket: urut kategori lalu waktu_submit
    usort($tikets, function($a, $b) {
        $ka = $a['kat_nama'] ?? 'Tanpa Kategori';
        $kb = $b['kat_nama'] ?? 'Tanpa Kategori';
        if ($ka !== $kb) return strcmp($ka, $kb);
        return strcmp($a['waktu_submit'] ?? '', $b['waktu_submit'] ?? '');
    });

    $no_item    = 0;
    $prev_kat   = null;
    foreach ($tikets as $t):
        $no_item++;
        $kat_now  = $t['kat_nama'] ?: 'Tanpa Kategori';
        $kat_baru = ($prev_kat !== $kat_now);
        $prev_kat = $kat_now;

        [$slbl, $sbg, $sfg] = statusLabel($t['status']);
        [$plbl, $pbg, $pfg] = prioritasLabel($t['prioritas']);
        $is_final = in_array($t['status'], ['selesai','ditolak','tidak_bisa']);
        $dur_mnt  = $t['durasi_selesai_menit'] ?? 0;

        $sla_ok = null;
        if ($t['status'] === 'selesai' && $t['sla_jam'] && $dur_mnt)
            $sla_ok = $dur_mnt <= $t['sla_jam'] * 60;
    ?>
    <?php if ($kat_baru): ?>
    <tr style="background:#e8f0fe !important;">
      <td colspan="12" style="padding:5px 8px;font-size:8pt;font-weight:700;color:#1d4ed8;border-bottom:1px solid #bfdbfe;letter-spacing:0.3px;">
        <i style="display:inline-block;width:0;height:0;border-top:4px solid transparent;border-bottom:4px solid transparent;border-left:6px solid #1d4ed8;margin-right:5px;vertical-align:middle;"></i>
        <?= htmlspecialchars($kat_now) ?>
        <span style="font-weight:400;color:#6b7280;font-size:7.5pt;margin-left:6px;"><?= count($by_kat[$kat_now] ?? []) ?> tiket</span>
      </td>
    </tr>
    <?php endif; ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $no_item ?></td>
      <td style="font-family:'Courier New',monospace;font-size:7pt;font-weight:bold;color:#1e40af;"><?= htmlspecialchars($t['nomor']) ?></td>
      <td style="font-size:7pt;color:#475569;"><?= htmlspecialchars($kat_now) ?></td>
      <td>
        <div style="font-weight:600;font-size:7.5pt;color:#1e293b;"><?= htmlspecialchars(mb_strimwidth($t['judul'],0,50,'…')) ?></div>
      </td>
      <td style="text-align:center;">
        <span class="badge" style="background:<?= $pbg ?>;color:<?= $pfg ?>;"><?= $plbl ?></span>
      </td>
      <td>
        <div style="font-size:7.5pt;font-weight:600;"><?= htmlspecialchars($t['req_nama'] ?? '—') ?></div>
        <?php if ($t['divisi']): ?>
        <div style="font-size:6.5pt;color:#94a3b8;"><?= htmlspecialchars($t['divisi']) ?></div>
        <?php endif; ?>
      </td>
      <td style="font-size:7.5pt;"><?= htmlspecialchars($t['tek_nama'] ?? '—') ?></td>
      <td style="text-align:center;">
        <span class="badge" style="background:<?= $sbg ?>;color:<?= $sfg ?>;"><?= $slbl ?></span>
      </td>
      <td style="font-size:7pt;color:#64748b;white-space:nowrap;"><?= fmtTgl($t['waktu_submit']) ?></td>
      <td style="font-size:7pt;color:#64748b;white-space:nowrap;">
        <?= $t['waktu_selesai'] ? fmtTgl($t['waktu_selesai']) : '—' ?>
      </td>
      <td style="font-size:7.5pt;font-weight:bold;text-align:center;">
        <?= $is_final && $dur_mnt ? fmtDur($dur_mnt) : '—' ?>
      </td>
      <td style="text-align:center;">
        <?php if ($sla_ok === true): ?>
          <span class="badge" style="background:#dcfce7;color:#15803d;">OK</span>
        <?php elseif ($sla_ok === false): ?>
          <span class="badge" style="background:#fee2e2;color:#b91c1c;">Lewat</span>
        <?php else: ?>
          <span style="color:#cbd5e1;font-size:7pt;">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3">TOTAL KESELURUHAN</td>
      <td colspan="4"><?= $total ?> tiket &nbsp;|&nbsp; Selesai: <?= $n_selesai ?> &nbsp;/&nbsp; Aktif: <?= $n_tunggu + $n_proses ?> &nbsp;/&nbsp; Ditolak: <?= $n_tolak + $n_tdk ?></td>
      <td colspan="3">SLA Met: <?= $total_sla_met ?> / <?= $total_solved ?> (<?= $sla_pct ?>%)</td>
      <td colspan="2">Avg: <?= fmtDur($avg_durasi) ?></td>
    </tr>
  </tfoot>
</table>

<?php endif; ?>

<!-- ══ KETERANGAN BADGE ══ -->
<table style="width:100%;border-collapse:collapse;margin-top:8px;border:1px solid #e2e8f0;font-size:8pt;">
  <tr>
    <td style="padding:5px 9px;background:#f8fafc;font-weight:bold;color:#475569;border-right:1px solid #e2e8f0;width:15%;">Keterangan</td>
    <td style="padding:5px 10px;border-right:1px solid #e2e8f0;">
      <span class="badge" style="background:#dcfce7;color:#15803d;">OK SLA</span> Selesai dalam target
    </td>
    <td style="padding:5px 10px;border-right:1px solid #e2e8f0;">
      <span class="badge" style="background:#fee2e2;color:#b91c1c;">Lewat SLA</span> Melewati target
    </td>
    <td style="padding:5px 10px;border-right:1px solid #e2e8f0;">
      <span class="badge" style="background:#fef9c3;color:#a16207;">CUKUP</span> SLA 70–89%
    </td>
    <td style="padding:5px 10px;">
      <span class="badge" style="background:#dcfce7;color:#15803d;">BAIK</span> SLA &ge; 90%
    </td>
  </tr>
</table>

<!-- ══ TTD ══ -->
<div style="margin-top:18px;font-size:8.5pt;color:#475569;margin-bottom:4px;">
  Laporan ini telah diperiksa dan disetujui oleh:
</div>
<div class="ttd-section">
  <div class="ttd-box">
    <div class="ttd-title">Dibuat Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name"><?= htmlspecialchars($_SESSION['user_nama'] ?? '_______________') ?></div>
    <div class="ttd-role">Staff IT / Operator</div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Diperiksa Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Kepala Divisi IT</div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Disetujui Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Manajer / Pimpinan</div>
  </div>
</div>

<!-- ══ FOOTER ══ -->
<div class="page-footer">
  <div class="pf-left">
    FixSmart Helpdesk &mdash; Laporan Tiket &mdash; Periode: <?= $periode_str ?><br>
    Dokumen ini bersifat rahasia dan hanya untuk keperluan internal manajemen.
  </div>
  <div class="pf-right">
    No. Dok: <?= $no_dok ?> &nbsp;|&nbsp; Total: <?= $total ?> tiket<br>
    Dicetak: <?= date('d/m/Y H:i:s') ?> WIB
  </div>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->setChroot(__DIR__ . '/../dompdf');
$options->set('fontDir',   __DIR__ . '/../dompdf/vendor/dompdf/dompdf/lib/fonts/');
$options->set('fontCache', __DIR__ . '/../dompdf/vendor/dompdf/dompdf/lib/fonts/');
$options->set('defaultFont', 'helvetica');
$options->set('dpi', 150);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'Laporan_Tiket_' . $tgl_dari . '_sd_' . $tgl_sampai . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;