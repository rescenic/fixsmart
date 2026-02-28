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
$mode       = $_GET['mode']    ?? 'semua';   // 'semua' | 'jenis' | 'teknisi'
$fjenis     = $_GET['jenis']   ?? '';
$fstatus    = $_GET['status']  ?? '';
$fteknisi   = $_GET['teknisi'] ?? '';
$tgl_dari   = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';

// ── Query ─────────────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($mode === 'jenis' && $fjenis !== '') {
    $where[]  = 'm.jenis_maintenance = ?';
    $params[] = $fjenis;
}
if ($fstatus !== '') {
    $where[]  = 'm.status = ?';
    $params[] = $fstatus;
}
if ($fteknisi !== '') {
    $where[]  = 'm.teknisi_id = ?';
    $params[] = (int)$fteknisi;
}
if ($tgl_dari !== '') {
    $where[]  = 'm.tgl_maintenance >= ?';
    $params[] = $tgl_dari;
}
if ($tgl_sampai !== '') {
    $where[]  = 'm.tgl_maintenance <= ?';
    $params[] = $tgl_sampai;
}

$wsql = implode(' AND ', $where);

$st = $pdo->prepare("
    SELECT m.*,
           a.no_inventaris, a.nama_aset AS aset_nama_db, a.kategori AS aset_kat,
           a.merek AS aset_merek, a.lokasi AS aset_lokasi,
           u.nama AS tek_nama_db, u.divisi AS tek_divisi
    FROM maintenance_it m
    LEFT JOIN aset_it a ON a.id = m.aset_id
    LEFT JOIN users   u ON u.id = m.teknisi_id
    WHERE $wsql
    ORDER BY m.tgl_maintenance DESC
");
$st->execute($params);
$mnts = $st->fetchAll();

$total_mnt = count($mnts);

// ── Stats ─────────────────────────────────────────────────────────────────────
$stat_status  = [];
$stat_jenis   = [];
$total_biaya  = 0;

foreach ($mnts as $m) {
    $s = $m['status'] ?: 'Lainnya';
    $j = $m['jenis_maintenance'] ?: 'Lainnya';
    $stat_status[$s] = ($stat_status[$s] ?? 0) + 1;
    $stat_jenis[$j]  = ($stat_jenis[$j]  ?? 0) + 1;
    $total_biaya    += (int)($m['biaya'] ?? 0);
}

// Hitung maintenance jatuh tempo & terlambat dalam data ini
$today       = date('Y-m-d');
$jml_selesai = $stat_status['Selesai'] ?? 0;
$jml_proses  = $stat_status['Dalam Proses'] ?? 0;
$pct_selesai = $total_mnt > 0 ? round($jml_selesai / $total_mnt * 100) : 0;

$jml_terlambat = 0;
$jml_segera    = 0;
foreach ($mnts as $m) {
    if (!$m['tgl_maintenance_berikut']) continue;
    $selisih = (int)floor((strtotime($m['tgl_maintenance_berikut']) - strtotime($today)) / 86400);
    if ($selisih < 0)        $jml_terlambat++;
    elseif ($selisih <= 14)  $jml_segera++;
}

// Kelompokkan per jenis
$by_jenis = [];
foreach ($mnts as $m) {
    $j = $m['jenis_maintenance'] ?: 'Tanpa Jenis';
    $by_jenis[$j][] = $m;
}

// Judul laporan
$judul_filter = '';
if ($mode === 'jenis' && $fjenis !== '') $judul_filter = 'Jenis: ' . $fjenis;
if ($fstatus !== '')  $judul_filter .= ($judul_filter ? ' | ' : '') . 'Status: ' . $fstatus;
if ($tgl_dari !== '') $judul_filter .= ($judul_filter ? ' | ' : '') . 'Dari: ' . date('d/m/Y', strtotime($tgl_dari));
if ($tgl_sampai !== '') $judul_filter .= ($judul_filter ? ' | ' : '') . 'S/d: ' . date('d/m/Y', strtotime($tgl_sampai));

$no_dok = 'RPT-MNT-' . date('Ymd') . '-' . strtoupper(substr(md5($mode.$fjenis.$fstatus.$tgl_dari.$tgl_sampai), 0, 4));

// Helper: status badge style
function statusStyle(string $s): array {
    return match ($s) {
        'Selesai'      => ['bg' => '#dcfce7', 'fg' => '#15803d'],
        'Dalam Proses' => ['bg' => '#dbeafe', 'fg' => '#1e40af'],
        'Ditunda'      => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'Dibatalkan'   => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        default        => ['bg' => '#f1f5f9', 'fg' => '#64748b'],
    };
}

function kondisiStyle2(string $k): array {
    return match ($k) {
        'Baik'            => ['bg' => '#dcfce7', 'fg' => '#15803d'],
        'Rusak'           => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        'Dalam Perbaikan' => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'Tidak Aktif'     => ['bg' => '#f1f5f9', 'fg' => '#475569'],
        default           => ['bg' => '#f1f5f9', 'fg' => '#64748b'],
    };
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
/* ═══════════════════════════════════════════
   BASE — A4 Landscape
═══════════════════════════════════════════ */
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    color: #1a1a2e;
    background: #fff;
    line-height: 1.5;
    margin: 36px 44px 36px 44px;
}

/* ═══════════════════════════════════════════
   KOP SURAT
═══════════════════════════════════════════ */
.kop {
    display: table;
    width: 100%;
    padding-bottom: 10px;
    border-bottom: 3px solid #0f172a;
    margin-bottom: 4px;
}
.kop-logo-area { display: table-cell; vertical-align: middle; width: 56px; }
.kop-logo-box {
    width: 44px; height: 44px;
    background: #0f172a;
    border-radius: 6px;
    text-align: center;
    line-height: 44px;
    font-size: 18px;
    color: #fff;
    font-weight: bold;
}
.kop-text { display: table-cell; vertical-align: middle; padding-left: 11px; }
.kop-org  { font-size: 14pt; font-weight: bold; color: #0f172a; line-height: 1.2; }
.kop-sub  { font-size: 8pt; color: #475569; margin-top: 1px; }
.kop-right { display: table-cell; vertical-align: middle; text-align: right; }
.kop-right-label { font-size: 7pt; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; }
.kop-right-val   { font-size: 8.5pt; color: #334155; font-weight: bold; }
.kop-rule2 { height: 1px; background: #cbd5e1; margin-bottom: 14px; }

/* ═══════════════════════════════════════════
   JUDUL LAPORAN
═══════════════════════════════════════════ */
.report-title-block {
    text-align: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
}
.report-label      { font-size: 7pt; letter-spacing: 3px; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.report-main-title { font-size: 14pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.report-sub        { font-size: 10pt; color: #7c3aed; font-weight: bold; }
.report-no         { font-size: 7.5pt; color: #94a3b8; margin-top: 4px; }

/* ═══════════════════════════════════════════
   INFO DOKUMEN
═══════════════════════════════════════════ */
.doc-info { width: 100%; border-collapse: collapse; margin-bottom: 16px; border: 1px solid #e2e8f0; }
.doc-info td { padding: 5px 9px; font-size: 8.5pt; border: 1px solid #e2e8f0; vertical-align: top; }
.di-label { background: #f8fafc; color: #475569; font-weight: bold; width: 20%; }
.di-val   { color: #1e293b; width: 30%; }

/* ═══════════════════════════════════════════
   SECTION HEADING
═══════════════════════════════════════════ */
.sec { margin-top: 16px; margin-bottom: 8px; page-break-after: avoid; }
.sec-num   { font-size: 7pt; letter-spacing: 2px; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px; }
.sec-title {
    font-size: 10pt; font-weight: bold; color: #0f172a;
    border-bottom: 2px solid #7c3aed;
    padding-bottom: 4px;
    display: table; width: 100%;
}
.sec-title-text { display: table-cell; }
.sec-title-rule { display: table-cell; text-align: right; font-size: 7.5pt; font-weight: normal; color: #94a3b8; vertical-align: bottom; padding-bottom: 1px; }

/* ═══════════════════════════════════════════
   KPI STATS CARDS
═══════════════════════════════════════════ */
.kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
.kpi-td    { vertical-align: top; }
.kpi-card  {
    border: 1px solid #e2e8f0;
    border-top: 3px solid #7c3aed;
    border-radius: 0 0 4px 4px;
    padding: 10px 12px 8px;
    background: #fff;
}
.kpi-card .k-label { font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.kpi-card .k-val   { font-size: 18pt; font-weight: bold; line-height: 1; margin-bottom: 3px; }
.kpi-card .k-desc  { font-size: 7.5pt; color: #94a3b8; line-height: 1.4; }

/* ═══════════════════════════════════════════
   TABEL DATA
═══════════════════════════════════════════ */
.jenis-header {
    background: #7c3aed;
    color: #fff;
    padding: 6px 10px;
    font-size: 9pt;
    font-weight: bold;
    border-radius: 3px 3px 0 0;
    margin-top: 14px;
    display: table;
    width: 100%;
}
.jenis-header-l { display: table-cell; }
.jenis-header-r { display: table-cell; text-align: right; font-size: 7.5pt; font-weight: normal; color: rgba(255,255,255,.7); }

.data-tbl { width: 100%; border-collapse: collapse; font-size: 8pt; page-break-inside: auto; }
.data-tbl thead tr { background: #0f172a; color: #fff; }
.data-tbl thead th {
    padding: 7px 8px;
    text-align: left;
    font-size: 7.5pt;
    font-weight: bold;
    letter-spacing: 0.3px;
    border-right: 1px solid rgba(255,255,255,0.08);
}
.data-tbl thead th:last-child { border-right: none; }
.data-tbl tbody tr td {
    padding: 6px 8px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
    font-size: 8pt;
}
.data-tbl tbody tr:nth-child(even) td { background: #f8fafc; }
.data-tbl tbody tr:last-child td     { border-bottom: 2px solid #e2e8f0; }
.data-tbl tfoot td {
    padding: 6px 8px;
    font-weight: bold;
    background: #f5f3ff;
    border-top: 2px solid #ddd6fe;
    color: #4c1d95;
    font-size: 8.5pt;
}

.status-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 3px;
    font-size: 7.5pt;
    font-weight: bold;
    white-space: nowrap;
}
.kondisi-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 3px;
    font-size: 7pt;
    font-weight: bold;
    white-space: nowrap;
}
.no-mnt-code {
    font-family: 'Courier New', monospace;
    font-size: 7.5pt;
    font-weight: bold;
    background: #f5f3ff;
    color: #6d28d9;
    border: 1px solid #ddd6fe;
    padding: 1px 5px;
    border-radius: 3px;
    white-space: nowrap;
}

/* ═══════════════════════════════════════════
   RINGKASAN PER JENIS
═══════════════════════════════════════════ */
.summ-tbl { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
.summ-tbl th { background: #0f172a; color: #fff; padding: 7px 9px; font-size: 8pt; text-align: left; }
.summ-tbl td { padding: 6px 9px; border-bottom: 1px solid #f1f5f9; }
.summ-tbl tr:nth-child(even) td { background: #f8fafc; }
.summ-tbl tfoot td { background: #f5f3ff; border-top: 2px solid #ddd6fe; font-weight: bold; color: #4c1d95; }

.bar-wrap { background: #e2e8f0; height: 5px; border-radius: 3px; overflow: hidden; display: inline-block; vertical-align: middle; }
.bar-fill { height: 5px; border-radius: 3px; }

/* ═══════════════════════════════════════════
   TANDA TANGAN
═══════════════════════════════════════════ */
.ttd-section { margin-top: 24px; display: table; width: 100%; }
.ttd-box     { display: table-cell; width: 33.3%; text-align: center; padding: 0 10px; vertical-align: top; }
.ttd-title   { font-size: 8.5pt; color: #475569; margin-bottom: 48px; }
.ttd-line    { border-top: 1px solid #334155; margin: 0 12px 4px; }
.ttd-name    { font-size: 8.5pt; font-weight: bold; color: #0f172a; }
.ttd-role    { font-size: 7.5pt; color: #64748b; margin-top: 2px; }

/* ═══════════════════════════════════════════
   FOOTER
═══════════════════════════════════════════ */
.page-footer {
    margin-top: 18px;
    padding-top: 7px;
    border-top: 1px solid #cbd5e1;
    display: table;
    width: 100%;
}
.pf-left  { display: table-cell; font-size: 7pt; color: #94a3b8; vertical-align: middle; }
.pf-right { display: table-cell; text-align: right; font-size: 7pt; color: #94a3b8; vertical-align: middle; }

/* ═══════════════════════════════════════════
   EXEC BOX
═══════════════════════════════════════════ */
.exec-box {
    border-left: 4px solid #7c3aed;
    background: #faf5ff;
    padding: 10px 14px;
    margin-bottom: 6px;
    border-radius: 0 4px 4px 0;
    font-size: 9pt;
    color: #1e293b;
    line-height: 1.7;
}

/* ═══════════════════════════════════════════
   REMINDER ALERT
═══════════════════════════════════════════ */
.alert-box {
    display: table;
    width: 100%;
    margin-bottom: 10px;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 8.5pt;
}
.alert-warn { background: #fef9c3; border-left: 4px solid #f59e0b; color: #78350f; }
.alert-danger { background: #fee2e2; border-left: 4px solid #ef4444; color: #7f1d1d; }
</style>
</head>
<body>

<!-- ══════════════════════════════
     KOP SURAT
══════════════════════════════ -->
<div class="kop">
  <div class="kop-logo-area">
    <div class="kop-logo-box">FS</div>
  </div>
  <div class="kop-text">
    <div class="kop-org">FixSmart Helpdesk</div>
    <div class="kop-sub">Divisi Teknologi Informasi &mdash; Sistem Manajemen Maintenance IT</div>
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

<!-- ══════════════════════════════
     JUDUL
══════════════════════════════ -->
<div class="report-title-block">
  <div class="report-label">Laporan Resmi &mdash; Internal Use Only</div>
  <div class="report-main-title">Laporan Maintenance IT</div>
  <div class="report-sub">
    <?php if ($mode === 'jenis' && $fjenis !== ''): ?>
      Jenis: <?= htmlspecialchars($fjenis) ?>
    <?php else: ?>
      Semua Jenis Maintenance
    <?php endif; ?>
    <?php if ($judul_filter): ?> &mdash; <?= htmlspecialchars($judul_filter) ?><?php endif; ?>
  </div>
  <div class="report-no">
    Disiapkan oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
    &nbsp;|&nbsp; Dicetak: <?= date('d F Y, H:i') ?> WIB
  </div>
</div>

<!-- ══════════════════════════════
     INFO DOKUMEN
══════════════════════════════ -->
<table class="doc-info">
  <tr>
    <td class="di-label">Unit</td>
    <td class="di-val">Divisi Teknologi Informasi</td>
    <td class="di-label">Tanggal Cetak</td>
    <td class="di-val"><?= date('d F Y, H:i') ?> WIB</td>
  </tr>
  <tr>
    <td class="di-label">Jenis Laporan</td>
    <td class="di-val">Catatan Maintenance Aset IT</td>
    <td class="di-label">Status Dokumen</td>
    <td class="di-val">Final &mdash; Confidential</td>
  </tr>
  <tr>
    <td class="di-label">Filter Jenis</td>
    <td class="di-val"><?= ($mode === 'jenis' && $fjenis !== '') ? htmlspecialchars($fjenis) : 'Semua Jenis' ?></td>
    <td class="di-label">Filter Status</td>
    <td class="di-val"><?= $fstatus !== '' ? htmlspecialchars($fstatus) : 'Semua Status' ?></td>
  </tr>
  <tr>
    <td class="di-label">Periode</td>
    <td class="di-val">
      <?= ($tgl_dari !== '' || $tgl_sampai !== '')
        ? (($tgl_dari !== '' ? date('d/m/Y', strtotime($tgl_dari)) : '—') . ' s/d ' . ($tgl_sampai !== '' ? date('d/m/Y', strtotime($tgl_sampai)) : 'Sekarang'))
        : 'Semua Periode' ?>
    </td>
    <td class="di-label">Total Catatan</td>
    <td class="di-val"><strong><?= $total_mnt ?> catatan</strong></td>
  </tr>
</table>

<!-- ══════════════════════════════
     I. RINGKASAN EKSEKUTIF
══════════════════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian I</div>
  <div class="sec-title">
    <span class="sec-title-text">Ringkasan Eksekutif</span>
    <span class="sec-title-rule">Executive Summary</span>
  </div>
</div>
<div class="exec-box">
  Laporan ini menyajikan data catatan maintenance IT
  <?= ($mode === 'jenis' && $fjenis !== '') ? 'untuk jenis <strong>' . htmlspecialchars($fjenis) . '</strong>' : 'untuk <strong>semua jenis maintenance</strong>' ?>.
  Total catatan yang tercatat sebanyak <strong><?= $total_mnt ?> catatan</strong> yang terdiri dari <strong><?= count($by_jenis) ?> jenis maintenance</strong>.
  Dari total tersebut, <strong><?= $jml_selesai ?> catatan (<?= $pct_selesai ?>%)</strong> telah berstatus selesai,
  dan <strong><?= $jml_proses ?> catatan</strong> masih dalam proses.
  <?php if ($jml_terlambat > 0): ?>
  Terdapat <strong><?= $jml_terlambat ?> aset</strong> yang jadwal maintenance-nya telah terlambat dan membutuhkan tindakan segera.
  <?php endif; ?>
  Total biaya maintenance yang tercatat: <strong>Rp <?= number_format($total_biaya, 0, ',', '.') ?></strong>.
  Laporan ini bersifat rahasia dan hanya dipergunakan untuk keperluan internal manajemen.
</div>

<!-- Alert jika ada terlambat / segera jatuh tempo -->
<?php if ($jml_terlambat > 0): ?>
<div class="alert-box alert-danger">
  &#9888; <strong>Perhatian:</strong> Terdapat <strong><?= $jml_terlambat ?> aset</strong> yang jadwal maintenance berikutnya sudah melewati tanggal jatuh tempo. Segera jadwalkan maintenance untuk aset tersebut.
</div>
<?php endif; ?>
<?php if ($jml_segera > 0): ?>
<div class="alert-box alert-warn">
  &#9200; <strong>Pengingat:</strong> Terdapat <strong><?= $jml_segera ?> aset</strong> yang maintenance berikutnya jatuh tempo dalam 14 hari ke depan.
</div>
<?php endif; ?>

<!-- ══════════════════════════════
     II. KPI / STATISTIK
══════════════════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian II</div>
  <div class="sec-title">
    <span class="sec-title-text">Statistik Maintenance berdasarkan Status</span>
  </div>
</div>

<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#7c3aed;">
        <div class="k-label">Total Catatan</div>
        <div class="k-val" style="color:#7c3aed;"><?= $total_mnt ?></div>
        <div class="k-desc">Seluruh catatan maintenance IT</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#16a34a;">
        <div class="k-label">Selesai</div>
        <div class="k-val" style="color:#16a34a;"><?= $stat_status['Selesai'] ?? 0 ?></div>
        <div class="k-desc">Maintenance telah selesai dilakukan</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#3b82f6;">
        <div class="k-label">Dalam Proses</div>
        <div class="k-val" style="color:#3b82f6;"><?= $stat_status['Dalam Proses'] ?? 0 ?></div>
        <div class="k-desc">Sedang dalam proses pengerjaan</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#f59e0b;">
        <div class="k-label">Ditunda</div>
        <div class="k-val" style="color:#f59e0b;"><?= $stat_status['Ditunda'] ?? 0 ?></div>
        <div class="k-desc">Maintenance ditunda / belum terjadwal</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#ef4444;">
        <div class="k-label">Terlambat</div>
        <div class="k-val" style="color:#ef4444;"><?= $jml_terlambat ?></div>
        <div class="k-desc">Jadwal berikutnya sudah lewat</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#0ea5e9;">
        <div class="k-label">Total Biaya</div>
        <div class="k-val" style="color:#0ea5e9;font-size:12pt;">Rp<br><?= $total_biaya > 0 ? number_format($total_biaya/1000000, 1).'jt' : '0' ?></div>
        <div class="k-desc">Akumulasi biaya maintenance tercatat</div>
      </div>
    </td>
  </tr>
</table>

<!-- ══════════════════════════════
     III. RINGKASAN PER JENIS
══════════════════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian III</div>
  <div class="sec-title">
    <span class="sec-title-text">Ringkasan Maintenance per Jenis</span>
  </div>
</div>

<table class="summ-tbl">
  <thead>
    <tr>
      <th style="width:30px;">No.</th>
      <th>Jenis Maintenance</th>
      <th style="text-align:center;">Total</th>
      <th style="text-align:center;">Selesai</th>
      <th style="text-align:center;">Dalam Proses</th>
      <th style="text-align:center;">Ditunda</th>
      <th style="text-align:center;">Dibatalkan</th>
      <th style="text-align:center;">% Selesai</th>
      <th style="text-align:right;">Total Biaya</th>
      <th>Proporsi</th>
    </tr>
  </thead>
  <tbody>
    <?php $no = 0; foreach ($by_jenis as $jenis => $items):
      $no++;
      $n_selesai = count(array_filter($items, fn($x) => $x['status'] === 'Selesai'));
      $n_proses  = count(array_filter($items, fn($x) => $x['status'] === 'Dalam Proses'));
      $n_tunda   = count(array_filter($items, fn($x) => $x['status'] === 'Ditunda'));
      $n_batal   = count(array_filter($items, fn($x) => $x['status'] === 'Dibatalkan'));
      $n_total   = count($items);
      $pct_s     = $n_total > 0 ? round($n_selesai / $n_total * 100) : 0;
      $pct_tot   = $total_mnt > 0 ? round($n_total / $total_mnt * 100) : 0;
      $biaya_j   = array_sum(array_column($items, 'biaya'));
    ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $no ?></td>
      <td style="font-weight:bold;"><?= htmlspecialchars($jenis) ?></td>
      <td style="text-align:center;font-weight:bold;"><?= $n_total ?></td>
      <td style="text-align:center;color:#16a34a;font-weight:bold;"><?= $n_selesai ?></td>
      <td style="text-align:center;color:#3b82f6;"><?= $n_proses ?></td>
      <td style="text-align:center;color:#f59e0b;"><?= $n_tunda ?></td>
      <td style="text-align:center;color:#dc2626;"><?= $n_batal ?></td>
      <td style="text-align:center;">
        <span style="font-weight:bold;color:<?= $pct_s >= 80 ? '#16a34a' : ($pct_s >= 50 ? '#f59e0b' : '#dc2626') ?>;"><?= $pct_s ?>%</span>
      </td>
      <td style="text-align:right;font-size:7.5pt;">
        <?= $biaya_j > 0 ? 'Rp '.number_format($biaya_j, 0, ',', '.') : '—' ?>
      </td>
      <td>
        <div class="bar-wrap" style="width:70px;">
          <div class="bar-fill" style="width:<?= $pct_tot ?>%;background:#7c3aed;"></div>
        </div>
        <span style="font-size:7.5pt;margin-left:4px;"><?= $pct_tot ?>%</span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">TOTAL KESELURUHAN</td>
      <td style="text-align:center;"><?= $total_mnt ?></td>
      <td style="text-align:center;color:#16a34a;"><?= $stat_status['Selesai'] ?? 0 ?></td>
      <td style="text-align:center;color:#3b82f6;"><?= $stat_status['Dalam Proses'] ?? 0 ?></td>
      <td style="text-align:center;color:#f59e0b;"><?= $stat_status['Ditunda'] ?? 0 ?></td>
      <td style="text-align:center;color:#dc2626;"><?= $stat_status['Dibatalkan'] ?? 0 ?></td>
      <td style="text-align:center;"><?= $pct_selesai ?>%</td>
      <td style="text-align:right;"><?= $total_biaya > 0 ? 'Rp '.number_format($total_biaya, 0, ',', '.') : '—' ?></td>
      <td>100%</td>
    </tr>
  </tfoot>
</table>

<!-- ══════════════════════════════
     IV. DETAIL MAINTENANCE
══════════════════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian IV</div>
  <div class="sec-title">
    <span class="sec-title-text">Detail Catatan Maintenance IT</span>
    <span class="sec-title-rule">Diurutkan per Jenis</span>
  </div>
</div>

<?php if (empty($mnts)): ?>
<div style="text-align:center;color:#94a3b8;padding:20px;border:1px dashed #e2e8f0;border-radius:4px;font-style:italic;">
  Tidak ada data maintenance untuk filter yang dipilih.
</div>
<?php else: foreach ($by_jenis as $jenis => $items): ?>

<div class="jenis-header">
  <span class="jenis-header-l">&#9654; <?= htmlspecialchars($jenis) ?></span>
  <span class="jenis-header-r"><?= count($items) ?> catatan</span>
</div>

<table class="data-tbl">
  <thead>
    <tr>
      <th style="width:28px;">#</th>
      <th style="width:105px;">No. Maintenance</th>
      <th style="width:130px;">Aset IT</th>
      <th style="width:90px;">No. Inventaris</th>
      <th style="width:85px;">Teknisi</th>
      <th style="width:68px;">Tgl Maintenance</th>
      <th style="width:70px;">Kondisi Sebelum</th>
      <th style="width:70px;">Kondisi Sesudah</th>
      <th>Temuan / Tindakan</th>
      <th style="width:60px;">Status</th>
      <th style="width:68px;">Mnt Berikut</th>
      <th style="width:72px;">Biaya (Rp)</th>
    </tr>
  </thead>
  <tbody>
    <?php $no_item = 0; foreach ($items as $m):
      $no_item++;
      $sstyle  = statusStyle($m['status']   ?? '');
      $kstyle  = kondisiStyle2($m['kondisi_sesudah'] ?? '');
      $kstyle2 = kondisiStyle2($m['kondisi_sebelum'] ?? '');

      $selisih_next = null;
      $next_warn    = '';
      if ($m['tgl_maintenance_berikut']) {
          $selisih_next = (int)floor((strtotime($m['tgl_maintenance_berikut']) - strtotime($today)) / 86400);
          if ($selisih_next < 0)       $next_warn = '#b91c1c';
          elseif ($selisih_next <= 14) $next_warn = '#d97706';
      }

      $aset_nama = $m['aset_nama_db'] ?: $m['aset_nama'];
      $tek_nama  = $m['tek_nama_db']  ?: $m['teknisi_nama'];
      $temuan    = $m['temuan']   ? mb_strimwidth($m['temuan'],   0, 50, '…') : '';
      $tindakan  = $m['tindakan'] ? mb_strimwidth($m['tindakan'], 0, 50, '…') : '';
    ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $no_item ?></td>
      <td><span class="no-mnt-code"><?= htmlspecialchars($m['no_maintenance'] ?? '—') ?></span></td>
      <td>
        <div style="font-weight:bold;font-size:8pt;color:#1e293b;"><?= htmlspecialchars($aset_nama ?: '—') ?></div>
        <?php if ($m['aset_kat']): ?>
          <span style="font-size:7pt;color:#94a3b8;"><?= htmlspecialchars($m['aset_kat']) ?></span>
        <?php endif; ?>
      </td>
      <td style="font-family:'Courier New',monospace;font-size:7.5pt;color:#475569;">
        <?= htmlspecialchars($m['no_inventaris'] ?? '—') ?>
      </td>
      <td>
        <div style="font-size:8pt;"><?= htmlspecialchars($tek_nama ?: '—') ?></div>
        <?php if ($m['tek_divisi']): ?>
          <span style="font-size:7pt;color:#94a3b8;"><?= htmlspecialchars($m['tek_divisi']) ?></span>
        <?php endif; ?>
      </td>
      <td style="font-size:7.5pt;color:#64748b;white-space:nowrap;">
        <?= $m['tgl_maintenance'] ? date('d/m/Y', strtotime($m['tgl_maintenance'])) : '—' ?>
      </td>
      <td>
        <?php if ($m['kondisi_sebelum']): ?>
          <span class="kondisi-badge" style="background:<?= $kstyle2['bg'] ?>;color:<?= $kstyle2['fg'] ?>;"><?= htmlspecialchars($m['kondisi_sebelum']) ?></span>
        <?php else: ?><span style="color:#cbd5e1;font-size:7.5pt;">—</span><?php endif; ?>
      </td>
      <td>
        <?php if ($m['kondisi_sesudah']): ?>
          <span class="kondisi-badge" style="background:<?= $kstyle['bg'] ?>;color:<?= $kstyle['fg'] ?>;"><?= htmlspecialchars($m['kondisi_sesudah']) ?></span>
        <?php else: ?><span style="color:#cbd5e1;font-size:7.5pt;">—</span><?php endif; ?>
      </td>
      <td style="font-size:7.5pt;">
        <?php if ($temuan): ?><div><span style="font-weight:bold;color:#475569;">Temuan:</span> <?= htmlspecialchars($temuan) ?></div><?php endif; ?>
        <?php if ($tindakan): ?><div style="margin-top:2px;"><span style="font-weight:bold;color:#475569;">Tindakan:</span> <?= htmlspecialchars($tindakan) ?></div><?php endif; ?>
        <?php if (!$temuan && !$tindakan): ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
      </td>
      <td>
        <span class="status-badge" style="background:<?= $sstyle['bg'] ?>;color:<?= $sstyle['fg'] ?>;"><?= htmlspecialchars($m['status'] ?? '—') ?></span>
      </td>
      <td style="font-size:7.5pt;white-space:nowrap;">
        <?php if ($m['tgl_maintenance_berikut']): ?>
          <div style="font-weight:bold;color:<?= $next_warn ?: '#1e293b' ?>;"><?= date('d/m/Y', strtotime($m['tgl_maintenance_berikut'])) ?></div>
          <?php if ($selisih_next !== null): ?>
            <div style="font-size:7pt;color:<?= $next_warn ?: '#64748b' ?>;">
              <?= $selisih_next < 0 ? 'Terlambat '.abs($selisih_next).'h' : $selisih_next.' hari lagi' ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <span style="color:#cbd5e1;">—</span>
        <?php endif; ?>
      </td>
      <td style="font-size:7.5pt;text-align:right;color:#0f172a;">
        <?= $m['biaya'] ? number_format($m['biaya'], 0, ',', '.') : '—' ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">Subtotal: <?= htmlspecialchars($jenis) ?></td>
      <td colspan="8"><?= count($items) ?> catatan</td>
      <td colspan="2" style="text-align:right;">
        <?php
        $subtotal_biaya = array_sum(array_column($items, 'biaya'));
        echo $subtotal_biaya > 0 ? 'Rp ' . number_format($subtotal_biaya, 0, ',', '.') : '—';
        ?>
      </td>
    </tr>
  </tfoot>
</table>

<?php endforeach; endif; ?>

<!-- ══════════════════════════════
     TOTAL BIAYA
══════════════════════════════ -->
<?php if ($total_biaya > 0): ?>
<div style="margin-top:14px;padding:10px 14px;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:4px;display:table;width:100%;">
  <div style="display:table-cell;font-weight:bold;color:#4c1d95;font-size:9pt;">
    Total Biaya Maintenance IT<?= ($mode === 'jenis' && $fjenis !== '') ? ' (Jenis: ' . htmlspecialchars($fjenis) . ')' : '' ?>
  </div>
  <div style="display:table-cell;text-align:right;font-size:12pt;font-weight:bold;color:#7c3aed;">
    Rp <?= number_format($total_biaya, 0, ',', '.') ?>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════
     KETERANGAN BADGE
══════════════════════════════ -->
<table style="width:100%;border-collapse:collapse;margin-top:10px;border:1px solid #e2e8f0;font-size:8pt;">
  <tr>
    <td style="padding:5px 9px;background:#f8fafc;font-weight:bold;color:#475569;border-right:1px solid #e2e8f0;width:14%;">Keterangan Status</td>
    <?php foreach ([
      ['Selesai',      '#dcfce7', '#15803d'],
      ['Dalam Proses', '#dbeafe', '#1e40af'],
      ['Ditunda',      '#fef9c3', '#a16207'],
      ['Dibatalkan',   '#fee2e2', '#b91c1c'],
    ] as [$lbl, $bg, $fg]): ?>
    <td style="padding:5px 10px;border-right:1px solid #e2e8f0;">
      <span class="status-badge" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= $lbl ?></span>
    </td>
    <?php endforeach; ?>
    <td style="padding:5px 10px;font-size:7.5pt;color:#94a3b8;">
      Tgl Berikut <span style="color:#b91c1c;font-weight:bold;">merah</span> = terlambat &nbsp;|&nbsp;
      <span style="color:#d97706;font-weight:bold;">kuning</span> = &lt;14 hari lagi
    </td>
  </tr>
</table>

<!-- ══════════════════════════════
     TANDA TANGAN
══════════════════════════════ -->
<div style="margin-top:22px;font-size:8.5pt;color:#475569;margin-bottom:4px;">
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

<!-- ══════════════════════════════
     FOOTER
══════════════════════════════ -->
<div class="page-footer">
  <div class="pf-left">
    FixSmart Helpdesk &mdash; Laporan Maintenance IT &mdash; Dicetak: <?= date('d/m/Y H:i:s') ?> WIB<br>
    Dokumen ini bersifat rahasia dan hanya untuk keperluan internal manajemen.
  </div>
  <div class="pf-right">
    No. Dok: <?= $no_dok ?> &nbsp;|&nbsp; Total Catatan: <?= $total_mnt ?><br>
    Dicetak oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
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

$jenis_slug = $fjenis !== '' ? '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $fjenis) : '';
$filename   = 'Laporan_Maintenance_IT' . $jenis_slug . '_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;