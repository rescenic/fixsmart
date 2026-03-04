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
$mode         = $_GET['mode']         ?? 'semua';
$kategori     = $_GET['kategori']     ?? '';
$kondisi      = $_GET['kondisi']      ?? '';
$status_pakai = $_GET['status_pakai'] ?? '';

// ── Query ────────────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($mode === 'kategori' && $kategori !== '') {
    $where[]  = 'a.kategori = ?';
    $params[] = $kategori;
}
if ($kondisi !== '') {
    $where[]  = 'a.kondisi = ?';
    $params[] = $kondisi;
}
if ($status_pakai !== '') {
    $where[]  = 'a.status_pakai = ?';
    $params[] = $status_pakai;
}

$wsql = implode(' AND ', $where);

$st = $pdo->prepare("
    SELECT a.*,
           b.nama   AS bagian_nama,
           b.kode   AS bagian_kode,
           b.lokasi AS bagian_lokasi,
           u.nama   AS pj_nama_db,
           u.divisi AS pj_divisi
    FROM aset_it a
    LEFT JOIN bagian b ON b.id = a.bagian_id
    LEFT JOIN users  u ON u.id = a.pj_user_id
    WHERE $wsql
    ORDER BY a.kategori ASC, a.nama_aset ASC
");
$st->execute($params);
$asets = $st->fetchAll();

// Stats ringkasan kondisi
$stats_kondisi = [];
foreach ($asets as $a) {
    $k = $a['kondisi'] ?? 'Lainnya';
    $stats_kondisi[$k] = ($stats_kondisi[$k] ?? 0) + 1;
}

// Stats ringkasan status_pakai
$stats_pakai = [];
foreach ($asets as $a) {
    $s = $a['status_pakai'] ?? 'Terpakai';
    $stats_pakai[$s] = ($stats_pakai[$s] ?? 0) + 1;
}

$total_aset = count($asets);

// Kelompokkan per kategori
$by_kat = [];
foreach ($asets as $a) {
    $kat = $a['kategori'] ?: 'Tanpa Kategori';
    $by_kat[$kat][] = $a;
}

// Judul laporan
$judul_parts = [];
if ($mode === 'kategori' && $kategori !== '') $judul_parts[] = 'Kategori: ' . htmlspecialchars($kategori);
if ($kondisi      !== '') $judul_parts[] = 'Kondisi: '      . htmlspecialchars($kondisi);
if ($status_pakai !== '') $judul_parts[] = 'Status: '       . htmlspecialchars($status_pakai);

$no_dok = 'RPT-ASET-' . date('Ymd') . '-' . strtoupper(substr(md5($mode.$kategori.$kondisi.$status_pakai), 0, 4));

// Helpers
function kondisiStyle(string $k): array {
    return match ($k) {
        'Baik'            => ['bg' => '#dcfce7', 'fg' => '#15803d'],
        'Rusak'           => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        'Dalam Perbaikan' => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'Tidak Aktif'     => ['bg' => '#f1f5f9', 'fg' => '#475569'],
        default           => ['bg' => '#f1f5f9', 'fg' => '#64748b'],
    };
}
function statusPakaiStyle(string $s): array {
    return match ($s) {
        'Terpakai'       => ['bg' => '#dbeafe', 'fg' => '#1e40af'],
        'Tidak Terpakai' => ['bg' => '#d1fae5', 'fg' => '#065f46'],
        'Dipinjam'       => ['bg' => '#fef3c7', 'fg' => '#92400e'],
        default          => ['bg' => '#f1f5f9', 'fg' => '#64748b'],
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

/* ── KOP ── */
.kop { display: table; width: 100%; padding-bottom: 10px; border-bottom: 3px solid #0f172a; margin-bottom: 4px; }
.kop-logo-area { display: table-cell; vertical-align: middle; width: 56px; }
.kop-logo-box { width: 44px; height: 44px; background: #0f172a; border-radius: 6px; text-align: center; line-height: 44px; font-size: 18px; color: #fff; font-weight: bold; }
.kop-text { display: table-cell; vertical-align: middle; padding-left: 11px; }
.kop-org  { font-size: 14pt; font-weight: bold; color: #0f172a; line-height: 1.2; }
.kop-sub  { font-size: 8pt; color: #475569; margin-top: 1px; }
.kop-right { display: table-cell; vertical-align: middle; text-align: right; }
.kop-right-label { font-size: 7pt; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; }
.kop-right-val   { font-size: 8.5pt; color: #334155; font-weight: bold; }
.kop-rule2 { height: 1px; background: #cbd5e1; margin-bottom: 14px; }

/* ── JUDUL ── */
.report-title-block { text-align: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
.report-label      { font-size: 7pt; letter-spacing: 3px; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.report-main-title { font-size: 14pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.report-sub        { font-size: 10pt; color: #1d4ed8; font-weight: bold; }
.report-no         { font-size: 7.5pt; color: #94a3b8; margin-top: 4px; }

/* ── INFO DOKUMEN ── */
.doc-info { width: 100%; border-collapse: collapse; margin-bottom: 16px; border: 1px solid #e2e8f0; }
.doc-info td { padding: 5px 9px; font-size: 8.5pt; border: 1px solid #e2e8f0; vertical-align: top; }
.di-label { background: #f8fafc; color: #475569; font-weight: bold; width: 20%; }
.di-val   { color: #1e293b; width: 30%; }

/* ── SECTION ── */
.sec { margin-top: 16px; margin-bottom: 8px; page-break-after: avoid; }
.sec-num   { font-size: 7pt; letter-spacing: 2px; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px; }
.sec-title { font-size: 10pt; font-weight: bold; color: #0f172a; border-bottom: 2px solid #1d4ed8; padding-bottom: 4px; display: table; width: 100%; }
.sec-title-text { display: table-cell; }
.sec-title-rule { display: table-cell; text-align: right; font-size: 7.5pt; font-weight: normal; color: #94a3b8; vertical-align: bottom; padding-bottom: 1px; }

/* ── KPI CARDS ── */
.kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
.kpi-td    { vertical-align: top; }
.kpi-card  { border: 1px solid #e2e8f0; border-top: 3px solid #1d4ed8; border-radius: 0 0 4px 4px; padding: 10px 12px 8px; background: #fff; }
.kpi-card .k-label { font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.kpi-card .k-val   { font-size: 18pt; font-weight: bold; line-height: 1; margin-bottom: 3px; }
.kpi-card .k-desc  { font-size: 7.5pt; color: #94a3b8; line-height: 1.4; }

/* ── STATUS PAKAI CARDS (lebih kecil) ── */
.sp-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
.sp-card  { border: 1px solid #e2e8f0; border-left: 4px solid #1d4ed8; border-radius: 0 4px 4px 0; padding: 8px 12px; background: #fff; display: table; width: 100%; }
.sp-card-l { display: table-cell; vertical-align: middle; }
.sp-card-r { display: table-cell; vertical-align: middle; text-align: right; }
.sp-val    { font-size: 16pt; font-weight: bold; line-height: 1; }
.sp-lbl    { font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #64748b; }
.sp-pct    { font-size: 9pt; font-weight: bold; }

/* ── TABEL ASET ── */
.kat-header { background: #1d4ed8; color: #fff; padding: 6px 10px; font-size: 9pt; font-weight: bold; border-radius: 3px 3px 0 0; margin-top: 14px; display: table; width: 100%; }
.kat-header-l { display: table-cell; }
.kat-header-r { display: table-cell; text-align: right; font-size: 7.5pt; font-weight: normal; color: rgba(255,255,255,.7); }

.data-tbl { width: 100%; border-collapse: collapse; font-size: 8pt; page-break-inside: auto; }
.data-tbl thead tr { background: #0f172a; color: #fff; }
.data-tbl thead th { padding: 7px 8px; text-align: left; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.3px; border-right: 1px solid rgba(255,255,255,0.08); }
.data-tbl thead th:last-child { border-right: none; }
.data-tbl tbody tr td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 8pt; }
.data-tbl tbody tr:nth-child(even) td { background: #f8fafc; }
.data-tbl tbody tr:last-child td { border-bottom: 2px solid #e2e8f0; }
.data-tbl tfoot td { padding: 6px 8px; font-weight: bold; background: #eff6ff; border-top: 2px solid #bfdbfe; color: #1e3a8a; font-size: 8.5pt; }

/* ── Row highlight status pakai (PDF) ── */
.data-tbl tbody tr.row-tidak td { background: #f0fdf4 !important; border-left: 3px solid #22c55e; }
.data-tbl tbody tr.row-dipinjam td { background: #fffbeb !important; border-left: 3px solid #f59e0b; }

.kondisi-badge { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; white-space: nowrap; }
.sp-badge      { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; white-space: nowrap; }
.inv-code { font-family: 'Courier New', monospace; font-size: 7.5pt; font-weight: bold; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 1px 5px; border-radius: 3px; white-space: nowrap; }

/* ── RINGKASAN PER KATEGORI ── */
.summ-tbl { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
.summ-tbl th { background: #0f172a; color: #fff; padding: 7px 9px; font-size: 8pt; text-align: left; }
.summ-tbl td { padding: 6px 9px; border-bottom: 1px solid #f1f5f9; }
.summ-tbl tr:nth-child(even) td { background: #f8fafc; }
.summ-tbl tfoot td { background: #eff6ff; border-top: 2px solid #bfdbfe; font-weight: bold; color: #1e3a8a; }

.bar-wrap { background: #e2e8f0; height: 5px; border-radius: 3px; overflow: hidden; display: inline-block; vertical-align: middle; }
.bar-fill { height: 5px; border-radius: 3px; }

/* ── TANDA TANGAN ── */
.ttd-section { margin-top: 24px; display: table; width: 100%; }
.ttd-box     { display: table-cell; width: 33.3%; text-align: center; padding: 0 10px; vertical-align: top; }
.ttd-title   { font-size: 8.5pt; color: #475569; margin-bottom: 48px; }
.ttd-line    { border-top: 1px solid #334155; margin: 0 12px 4px; }
.ttd-name    { font-size: 8.5pt; font-weight: bold; color: #0f172a; }
.ttd-role    { font-size: 7.5pt; color: #64748b; margin-top: 2px; }

/* ── FOOTER ── */
.page-footer { margin-top: 18px; padding-top: 7px; border-top: 1px solid #cbd5e1; display: table; width: 100%; }
.pf-left  { display: table-cell; font-size: 7pt; color: #94a3b8; vertical-align: middle; }
.pf-right { display: table-cell; text-align: right; font-size: 7pt; color: #94a3b8; vertical-align: middle; }

/* ── EXEC BOX ── */
.exec-box { border-left: 4px solid #1d4ed8; background: #f0f6ff; padding: 10px 14px; margin-bottom: 6px; border-radius: 0 4px 4px 0; font-size: 9pt; color: #1e293b; line-height: 1.7; }

/* ── STATUS PAKAI BANNER (jika filter aktif) ── */
.sp-filter-banner { display: table; width: 100%; padding: 8px 13px; border-radius: 4px; margin-bottom: 10px; }
.sp-filter-banner-l { display: table-cell; vertical-align: middle; font-size: 8.5pt; font-weight: bold; }
.sp-filter-banner-r { display: table-cell; vertical-align: middle; text-align: right; font-size: 8pt; }
</style>
</head>
<body>

<!-- ═══════════════════ KOP ═══════════════════ -->
<div class="kop">
  <div class="kop-logo-area"><div class="kop-logo-box">FS</div></div>
  <div class="kop-text">
    <div class="kop-org">FixSmart Helpdesk</div>
    <div class="kop-sub">Divisi Teknologi Informasi &mdash; Sistem Manajemen Aset IT</div>
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

<!-- ═══════════════════ JUDUL ═══════════════════ -->
<div class="report-title-block">
  <div class="report-label">Laporan Resmi &mdash; Internal Use Only</div>
  <div class="report-main-title">Laporan Data Aset IT</div>
  <div class="report-sub">
    <?php if (!empty($judul_parts)): ?>
      <?= implode(' &nbsp;&mdash;&nbsp; ', $judul_parts) ?>
    <?php else: ?>
      Semua Kategori &mdash; Semua Status
    <?php endif; ?>
  </div>
  <div class="report-no">
    Disiapkan oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
    &nbsp;|&nbsp; Dicetak: <?= date('d F Y, H:i') ?> WIB
  </div>
</div>

<!-- ═══════════════════ INFO DOKUMEN ═══════════════════ -->
<table class="doc-info">
  <tr>
    <td class="di-label">Unit</td>
    <td class="di-val">Divisi Teknologi Informasi</td>
    <td class="di-label">Tanggal Cetak</td>
    <td class="di-val"><?= date('d F Y, H:i') ?> WIB</td>
  </tr>
  <tr>
    <td class="di-label">Jenis Laporan</td>
    <td class="di-val">Inventarisasi Aset IT</td>
    <td class="di-label">Status Dokumen</td>
    <td class="di-val">Final &mdash; Confidential</td>
  </tr>
  <tr>
    <td class="di-label">Filter Kategori</td>
    <td class="di-val"><?= ($mode === 'kategori' && $kategori !== '') ? htmlspecialchars($kategori) : 'Semua Kategori' ?></td>
    <td class="di-label">Filter Kondisi</td>
    <td class="di-val"><?= $kondisi !== '' ? htmlspecialchars($kondisi) : 'Semua Kondisi' ?></td>
  </tr>
  <tr>
    <td class="di-label">Filter Status Pakai</td>
    <td class="di-val">
      <?php if ($status_pakai !== ''):
        $spst = statusPakaiStyle($status_pakai); ?>
        <span class="sp-badge" style="background:<?= $spst['bg'] ?>;color:<?= $spst['fg'] ?>;"><?= htmlspecialchars($status_pakai) ?></span>
      <?php else: ?>
        Semua Status
      <?php endif; ?>
    </td>
    <td class="di-label">Total Aset</td>
    <td class="di-val"><strong><?= $total_aset ?> unit</strong> &nbsp;|&nbsp; <?= count($by_kat) ?> kategori</td>
  </tr>
</table>

<?php if ($status_pakai === 'Tidak Terpakai'): ?>
<!-- Banner peringatan filter tidak terpakai -->
<div class="sp-filter-banner" style="background:#d1fae5;border:1px solid #86efac;">
  <div class="sp-filter-banner-l" style="color:#065f46;">
    &#9679; LAPORAN KHUSUS: Aset Tidak Terpakai
  </div>
  <div class="sp-filter-banner-r" style="color:#065f46;">
    Total <?= $total_aset ?> unit aset yang saat ini tidak digunakan
  </div>
</div>
<?php elseif ($status_pakai === 'Dipinjam'): ?>
<div class="sp-filter-banner" style="background:#fef3c7;border:1px solid #fde68a;">
  <div class="sp-filter-banner-l" style="color:#92400e;">
    &#9679; LAPORAN KHUSUS: Aset Sedang Dipinjam
  </div>
  <div class="sp-filter-banner-r" style="color:#92400e;">
    Total <?= $total_aset ?> unit aset yang sedang dalam status dipinjam
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════ I. RINGKASAN EKSEKUTIF ═══════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian I</div>
  <div class="sec-title">
    <span class="sec-title-text">Ringkasan Eksekutif</span>
    <span class="sec-title-rule">Executive Summary</span>
  </div>
</div>
<?php
$jml_baik      = $stats_kondisi['Baik'] ?? 0;
$jml_rusak     = $stats_kondisi['Rusak'] ?? 0;
$jml_perbaikan = $stats_kondisi['Dalam Perbaikan'] ?? 0;
$pct_baik      = $total_aset > 0 ? round($jml_baik / $total_aset * 100) : 0;
$jml_terpakai  = $stats_pakai['Terpakai'] ?? 0;
$jml_tidak     = $stats_pakai['Tidak Terpakai'] ?? 0;
$jml_dipinjam  = $stats_pakai['Dipinjam'] ?? 0;
$pct_terpakai  = $total_aset > 0 ? round($jml_terpakai / $total_aset * 100) : 0;
$pct_tidak     = $total_aset > 0 ? round($jml_tidak    / $total_aset * 100) : 0;
?>
<div class="exec-box">
  Laporan ini menyajikan data inventarisasi aset IT
  <?= ($mode === 'kategori' && $kategori !== '') ? 'untuk kategori <strong>' . htmlspecialchars($kategori) . '</strong>' : 'untuk <strong>semua kategori</strong>' ?>.
  <?php if ($status_pakai !== ''): ?>
    Laporan ini <strong>difilter berdasarkan status pemakaian: <?= htmlspecialchars($status_pakai) ?></strong>.
  <?php endif; ?>
  Total aset yang tercatat sebanyak <strong><?= $total_aset ?> unit</strong> dalam <strong><?= count($by_kat) ?> kategori</strong>.
  Dari sisi kondisi: <strong><?= $jml_baik ?> unit (<?= $pct_baik ?>%)</strong> dalam kondisi baik,
  <strong><?= $jml_rusak ?> unit</strong> rusak, dan <strong><?= $jml_perbaikan ?> unit</strong> dalam perbaikan.
  Dari sisi pemakaian: <strong><?= $jml_terpakai ?> unit (<?= $pct_terpakai ?>%)</strong> aktif terpakai,
  <strong><?= $jml_tidak ?> unit (<?= $pct_tidak ?>%)</strong> tidak terpakai,
  dan <strong><?= $jml_dipinjam ?> unit</strong> sedang dipinjam.
</div>

<!-- ═══════════════════ II. STATISTIK KONDISI ═══════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian II</div>
  <div class="sec-title"><span class="sec-title-text">Statistik Kondisi Aset</span></div>
</div>
<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#1d4ed8;">
        <div class="k-label">Total Aset</div>
        <div class="k-val" style="color:#1d4ed8;"><?= $total_aset ?></div>
        <div class="k-desc">Seluruh unit aset IT tercatat</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#16a34a;">
        <div class="k-label">Kondisi Baik</div>
        <div class="k-val" style="color:#16a34a;"><?= $stats_kondisi['Baik'] ?? 0 ?></div>
        <div class="k-desc">Siap digunakan / beroperasi normal</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#dc2626;">
        <div class="k-label">Kondisi Rusak</div>
        <div class="k-val" style="color:#dc2626;"><?= $stats_kondisi['Rusak'] ?? 0 ?></div>
        <div class="k-desc">Memerlukan penggantian / tindak lanjut</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#d97706;">
        <div class="k-label">Dalam Perbaikan</div>
        <div class="k-val" style="color:#d97706;"><?= $stats_kondisi['Dalam Perbaikan'] ?? 0 ?></div>
        <div class="k-desc">Sedang dalam proses perbaikan</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#64748b;">
        <div class="k-label">Tidak Aktif</div>
        <div class="k-val" style="color:#64748b;"><?= $stats_kondisi['Tidak Aktif'] ?? 0 ?></div>
        <div class="k-desc">Sudah tidak dioperasikan</div>
      </div>
    </td>
  </tr>
</table>

<!-- ═══════════════════ III. STATISTIK STATUS PAKAI ═══════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian III</div>
  <div class="sec-title"><span class="sec-title-text">Statistik Status Pemakaian Aset</span></div>
</div>
<table class="sp-table">
  <tr>
    <!-- Terpakai -->
    <td class="kpi-td">
      <div class="sp-card" style="border-left-color:#1d4ed8;">
        <div class="sp-card-l">
          <div class="sp-lbl">Terpakai</div>
          <div class="sp-val" style="color:#1d4ed8;"><?= $stats_pakai['Terpakai'] ?? 0 ?></div>
          <div style="font-size:7.5pt;color:#94a3b8;">Aktif digunakan sehari-hari</div>
        </div>
        <div class="sp-card-r">
          <div class="sp-pct" style="color:#1d4ed8;">
            <?= $total_aset > 0 ? round(($stats_pakai['Terpakai'] ?? 0) / $total_aset * 100) : 0 ?>%
          </div>
          <div class="bar-wrap" style="width:50px;display:block;margin-top:4px;">
            <div class="bar-fill" style="width:<?= $total_aset > 0 ? round(($stats_pakai['Terpakai'] ?? 0) / $total_aset * 100) : 0 ?>%;background:#1d4ed8;"></div>
          </div>
        </div>
      </div>
    </td>
    <!-- Tidak Terpakai -->
    <td class="kpi-td">
      <div class="sp-card" style="border-left-color:#16a34a;background:#f0fdf4;">
        <div class="sp-card-l">
          <div class="sp-lbl" style="color:#065f46;">Tidak Terpakai</div>
          <div class="sp-val" style="color:#16a34a;"><?= $stats_pakai['Tidak Terpakai'] ?? 0 ?></div>
          <div style="font-size:7.5pt;color:#94a3b8;">Belum / tidak digunakan</div>
        </div>
        <div class="sp-card-r">
          <div class="sp-pct" style="color:#16a34a;">
            <?= $total_aset > 0 ? round(($stats_pakai['Tidak Terpakai'] ?? 0) / $total_aset * 100) : 0 ?>%
          </div>
          <div class="bar-wrap" style="width:50px;display:block;margin-top:4px;">
            <div class="bar-fill" style="width:<?= $total_aset > 0 ? round(($stats_pakai['Tidak Terpakai'] ?? 0) / $total_aset * 100) : 0 ?>%;background:#16a34a;"></div>
          </div>
        </div>
      </div>
    </td>
    <!-- Dipinjam -->
    <td class="kpi-td">
      <div class="sp-card" style="border-left-color:#d97706;background:#fffbeb;">
        <div class="sp-card-l">
          <div class="sp-lbl" style="color:#92400e;">Dipinjam</div>
          <div class="sp-val" style="color:#d97706;"><?= $stats_pakai['Dipinjam'] ?? 0 ?></div>
          <div style="font-size:7.5pt;color:#94a3b8;">Sedang dalam status pinjam</div>
        </div>
        <div class="sp-card-r">
          <div class="sp-pct" style="color:#d97706;">
            <?= $total_aset > 0 ? round(($stats_pakai['Dipinjam'] ?? 0) / $total_aset * 100) : 0 ?>%
          </div>
          <div class="bar-wrap" style="width:50px;display:block;margin-top:4px;">
            <div class="bar-fill" style="width:<?= $total_aset > 0 ? round(($stats_pakai['Dipinjam'] ?? 0) / $total_aset * 100) : 0 ?>%;background:#d97706;"></div>
          </div>
        </div>
      </div>
    </td>
  </tr>
</table>

<!-- ═══════════════════ IV. RINGKASAN PER KATEGORI ═══════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian IV</div>
  <div class="sec-title">
    <span class="sec-title-text">Ringkasan Aset per Kategori</span>
  </div>
</div>
<table class="summ-tbl">
  <thead>
    <tr>
      <th style="width:28px;">No.</th>
      <th>Kategori</th>
      <th style="text-align:center;">Total</th>
      <th style="text-align:center;">Baik</th>
      <th style="text-align:center;">Rusak</th>
      <th style="text-align:center;">Perbaikan</th>
      <th style="text-align:center;">Tidak Aktif</th>
      <th style="text-align:center;">Terpakai</th>
      <th style="text-align:center;">Tdk Terpakai</th>
      <th style="text-align:center;">Dipinjam</th>
      <th style="text-align:center;">% Baik</th>
      <th>Proporsi</th>
    </tr>
  </thead>
  <tbody>
    <?php $no = 0; foreach ($by_kat as $kat => $items):
      $no++;
      $n_baik    = count(array_filter($items, fn($x) => $x['kondisi'] === 'Baik'));
      $n_rusak   = count(array_filter($items, fn($x) => $x['kondisi'] === 'Rusak'));
      $n_perb    = count(array_filter($items, fn($x) => $x['kondisi'] === 'Dalam Perbaikan'));
      $n_tdk_a   = count(array_filter($items, fn($x) => $x['kondisi'] === 'Tidak Aktif'));
      $n_terpakai= count(array_filter($items, fn($x) => ($x['status_pakai'] ?? '') === 'Terpakai'));
      $n_tdk_p   = count(array_filter($items, fn($x) => ($x['status_pakai'] ?? '') === 'Tidak Terpakai'));
      $n_dipinjam= count(array_filter($items, fn($x) => ($x['status_pakai'] ?? '') === 'Dipinjam'));
      $n_total   = count($items);
      $pct_b     = $n_total > 0 ? round($n_baik / $n_total * 100) : 0;
      $pct_tot   = $total_aset > 0 ? round($n_total / $total_aset * 100) : 0;
    ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $no ?></td>
      <td style="font-weight:bold;"><?= htmlspecialchars($kat) ?></td>
      <td style="text-align:center;font-weight:bold;"><?= $n_total ?></td>
      <td style="text-align:center;color:#16a34a;font-weight:bold;"><?= $n_baik ?></td>
      <td style="text-align:center;color:#dc2626;"><?= $n_rusak ?></td>
      <td style="text-align:center;color:#d97706;"><?= $n_perb ?></td>
      <td style="text-align:center;color:#64748b;"><?= $n_tdk_a ?></td>
      <td style="text-align:center;color:#1d4ed8;font-weight:bold;"><?= $n_terpakai ?></td>
      <td style="text-align:center;color:#16a34a;"><?= $n_tdk_p > 0 ? '<strong>'.$n_tdk_p.'</strong>' : '0' ?></td>
      <td style="text-align:center;color:#d97706;"><?= $n_dipinjam ?></td>
      <td style="text-align:center;">
        <span style="font-weight:bold;color:<?= $pct_b >= 80 ? '#16a34a' : ($pct_b >= 50 ? '#d97706' : '#dc2626') ?>;"><?= $pct_b ?>%</span>
      </td>
      <td>
        <div class="bar-wrap" style="width:70px;">
          <div class="bar-fill" style="width:<?= $pct_tot ?>%;background:#1d4ed8;"></div>
        </div>
        <span style="font-size:7.5pt;margin-left:3px;"><?= $pct_tot ?>%</span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">TOTAL KESELURUHAN</td>
      <td style="text-align:center;"><?= $total_aset ?></td>
      <td style="text-align:center;color:#16a34a;"><?= $stats_kondisi['Baik'] ?? 0 ?></td>
      <td style="text-align:center;color:#dc2626;"><?= $stats_kondisi['Rusak'] ?? 0 ?></td>
      <td style="text-align:center;color:#d97706;"><?= $stats_kondisi['Dalam Perbaikan'] ?? 0 ?></td>
      <td style="text-align:center;color:#64748b;"><?= $stats_kondisi['Tidak Aktif'] ?? 0 ?></td>
      <td style="text-align:center;color:#1d4ed8;"><?= $stats_pakai['Terpakai'] ?? 0 ?></td>
      <td style="text-align:center;color:#16a34a;"><strong><?= $stats_pakai['Tidak Terpakai'] ?? 0 ?></strong></td>
      <td style="text-align:center;color:#d97706;"><?= $stats_pakai['Dipinjam'] ?? 0 ?></td>
      <td style="text-align:center;"><?= $pct_baik ?>%</td>
      <td>100%</td>
    </tr>
  </tfoot>
</table>

<!-- ═══════════════════ V. DETAIL ASET PER KATEGORI ═══════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian V</div>
  <div class="sec-title">
    <span class="sec-title-text">Detail Data Aset IT</span>
    <span class="sec-title-rule">Diurutkan per Kategori &mdash; Warna baris: Hijau = Tidak Terpakai &nbsp;|&nbsp; Kuning = Dipinjam</span>
  </div>
</div>

<?php if (empty($asets)): ?>
<div style="text-align:center;color:#94a3b8;padding:20px;border:1px dashed #e2e8f0;border-radius:4px;font-style:italic;">
  Tidak ada data aset untuk filter yang dipilih.
</div>
<?php else: foreach ($by_kat as $kat => $items): ?>

<div class="kat-header">
  <span class="kat-header-l">&#9654; <?= htmlspecialchars($kat) ?></span>
  <span class="kat-header-r"><?= count($items) ?> unit &nbsp;|&nbsp;
    Terpakai: <?= count(array_filter($items, fn($x) => ($x['status_pakai']??'') === 'Terpakai')) ?> &nbsp;
    Tidak Terpakai: <?= count(array_filter($items, fn($x) => ($x['status_pakai']??'') === 'Tidak Terpakai')) ?> &nbsp;
    Dipinjam: <?= count(array_filter($items, fn($x) => ($x['status_pakai']??'') === 'Dipinjam')) ?>
  </span>
</div>

<table class="data-tbl">
  <thead>
    <tr>
      <th style="width:24px;">#</th>
      <th style="width:100px;">No. Inventaris</th>
      <th style="width:130px;">Nama Aset</th>
      <th style="width:75px;">Merek / Model</th>
      <th style="width:85px;">Serial Number</th>
      <th>Lokasi / Bagian</th>
      <th>Penanggung Jawab</th>
      <th style="width:68px;">Status Pakai</th>
      <th style="width:68px;">Kondisi</th>
      <th style="width:60px;">Tgl Beli</th>
      <th style="width:65px;">Garansi s/d</th>
      <th style="width:68px;">Harga (Rp)</th>
    </tr>
  </thead>
  <tbody>
    <?php $no_item = 0; foreach ($items as $a):
      $no_item++;
      $g_exp  = $a['garansi_sampai'] && strtotime($a['garansi_sampai']) < time();
      $g_soon = $a['garansi_sampai'] && !$g_exp && strtotime($a['garansi_sampai']) < strtotime('+30 days');
      $kstyle = kondisiStyle($a['kondisi'] ?? '');
      $sp     = $a['status_pakai'] ?? 'Terpakai';
      $sstyle = statusPakaiStyle($sp);
      $lokasi = $a['bagian_nama']
        ? ($a['bagian_kode'] ? '[' . htmlspecialchars($a['bagian_kode']) . '] ' : '') . htmlspecialchars($a['bagian_nama'])
        : htmlspecialchars($a['lokasi'] ?? '—');
      $pj = $a['pj_nama_db'] ?: ($a['penanggung_jawab'] ?: '—');

      // Row class untuk highlight
      $row_class = '';
      if ($sp === 'Tidak Terpakai') $row_class = 'row-tidak';
      elseif ($sp === 'Dipinjam')   $row_class = 'row-dipinjam';
    ?>
    <tr class="<?= $row_class ?>">
      <td style="text-align:center;color:#94a3b8;"><?= $no_item ?></td>
      <td><span class="inv-code"><?= htmlspecialchars($a['no_inventaris'] ?? '—') ?></span></td>
      <td>
        <strong style="font-size:8pt;<?= $sp === 'Tidak Terpakai' ? 'text-decoration:line-through;color:#6b7280;' : '' ?>">
          <?= htmlspecialchars($a['nama_aset'] ?? '—') ?>
        </strong>
        <?php if ($a['keterangan']): ?>
          <br><span style="color:#94a3b8;font-size:7pt;"><?= htmlspecialchars(mb_strimwidth($a['keterangan'], 0, 45, '…')) ?></span>
        <?php endif; ?>
      </td>
      <td>
        <span style="font-weight:bold;"><?= htmlspecialchars($a['merek'] ?? '—') ?></span>
        <?php if ($a['model_aset']): ?><br><span style="color:#94a3b8;font-size:7pt;"><?= htmlspecialchars($a['model_aset']) ?></span><?php endif; ?>
      </td>
      <td style="font-family:'Courier New',monospace;font-size:7.5pt;color:#475569;"><?= htmlspecialchars($a['serial_number'] ?? '—') ?></td>
      <td>
        <div style="font-size:8pt;"><?= $lokasi ?></div>
        <?php if ($a['bagian_lokasi']): ?><span style="color:#94a3b8;font-size:7pt;"><?= htmlspecialchars($a['bagian_lokasi']) ?></span><?php endif; ?>
      </td>
      <td>
        <div style="font-size:8pt;"><?= htmlspecialchars($pj) ?></div>
        <?php if ($a['pj_divisi']): ?><span style="color:#94a3b8;font-size:7pt;"><?= htmlspecialchars($a['pj_divisi']) ?></span><?php endif; ?>
      </td>
      <!-- STATUS PAKAI -->
      <td>
        <span class="sp-badge" style="background:<?= $sstyle['bg'] ?>;color:<?= $sstyle['fg'] ?>;">
          <?= htmlspecialchars($sp) ?>
        </span>
      </td>
      <!-- KONDISI -->
      <td>
        <span class="kondisi-badge" style="background:<?= $kstyle['bg'] ?>;color:<?= $kstyle['fg'] ?>;">
          <?= htmlspecialchars($a['kondisi'] ?? '—') ?>
        </span>
      </td>
      <td style="font-size:7.5pt;color:#64748b;white-space:nowrap;"><?= $a['tanggal_beli'] ? date('d/m/Y', strtotime($a['tanggal_beli'])) : '—' ?></td>
      <td style="font-size:7.5pt;white-space:nowrap;">
        <?php if (!$a['garansi_sampai']): ?><span style="color:#cbd5e1;">—</span>
        <?php elseif ($g_exp): ?><span style="color:#ef4444;font-weight:bold;">Expired</span><br><span style="color:#f87171;font-size:7pt;"><?= date('d/m/Y', strtotime($a['garansi_sampai'])) ?></span>
        <?php elseif ($g_soon): ?><span style="color:#f59e0b;font-weight:bold;">Segera</span><br><span style="color:#fbbf24;font-size:7pt;"><?= date('d/m/Y', strtotime($a['garansi_sampai'])) ?></span>
        <?php else: ?><span style="color:#16a34a;"><?= date('d/m/Y', strtotime($a['garansi_sampai'])) ?></span>
        <?php endif; ?>
      </td>
      <td style="font-size:7.5pt;text-align:right;color:#0f172a;"><?= $a['harga_beli'] ? number_format($a['harga_beli'], 0, ',', '.') : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">Subtotal: <?= htmlspecialchars($kat) ?></td>
      <td colspan="9"><?= count($items) ?> unit &nbsp;|&nbsp;
        <span style="color:#1d4ed8;">Terpakai: <?= count(array_filter($items, fn($x)=>($x['status_pakai']??'')==='Terpakai')) ?></span> &nbsp;
        <span style="color:#16a34a;">Tdk Terpakai: <?= count(array_filter($items, fn($x)=>($x['status_pakai']??'')==='Tidak Terpakai')) ?></span> &nbsp;
        <span style="color:#d97706;">Dipinjam: <?= count(array_filter($items, fn($x)=>($x['status_pakai']??'')==='Dipinjam')) ?></span>
      </td>
      <td style="text-align:right;">
        <?php $total_harga = array_sum(array_column($items, 'harga_beli'));
        echo $total_harga > 0 ? 'Rp ' . number_format($total_harga, 0, ',', '.') : '—'; ?>
      </td>
    </tr>
  </tfoot>
</table>

<?php endforeach; endif; ?>

<!-- ═══════════════════ TOTAL NILAI ═══════════════════ -->
<?php $grand_total_nilai = array_sum(array_column($asets, 'harga_beli'));
if ($grand_total_nilai > 0): ?>
<div style="margin-top:14px;padding:10px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:4px;display:table;width:100%;">
  <div style="display:table-cell;font-weight:bold;color:#1e3a8a;font-size:9pt;">Total Nilai Aset IT</div>
  <div style="display:table-cell;text-align:right;font-size:12pt;font-weight:bold;color:#1d4ed8;">Rp <?= number_format($grand_total_nilai, 0, ',', '.') ?></div>
</div>
<?php endif; ?>

<!-- ═══════════════════ KETERANGAN WARNA ═══════════════════ -->
<table style="width:100%;border-collapse:collapse;margin-top:10px;border:1px solid #e2e8f0;font-size:8pt;">
  <tr>
    <td style="padding:5px 9px;background:#f8fafc;font-weight:bold;color:#475569;border-right:1px solid #e2e8f0;width:14%;">Keterangan</td>
    <?php foreach ([
      ['Baik','#dcfce7','#15803d'],['Rusak','#fee2e2','#b91c1c'],
      ['Dalam Perbaikan','#fef9c3','#a16207'],['Tidak Aktif','#f1f5f9','#475569'],
    ] as [$lbl,$bg,$fg]): ?>
    <td style="padding:5px 8px;border-right:1px solid #e2e8f0;">
      <span class="kondisi-badge" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= $lbl ?></span>
    </td>
    <?php endforeach; ?>
    <td style="padding:5px 8px;border-right:1px solid #e2e8f0;background:#f0fdf4;">
      <span class="sp-badge" style="background:#d1fae5;color:#065f46;">Tidak Terpakai</span>
      <span style="font-size:7pt;color:#6b7280;margin-left:4px;">Baris hijau + nama dicoret</span>
    </td>
    <td style="padding:5px 8px;background:#fffbeb;">
      <span class="sp-badge" style="background:#fef3c7;color:#92400e;">Dipinjam</span>
      <span style="font-size:7pt;color:#6b7280;margin-left:4px;">Baris kuning</span>
    </td>
  </tr>
</table>

<!-- ═══════════════════ TANDA TANGAN ═══════════════════ -->
<div style="margin-top:22px;font-size:8.5pt;color:#475569;margin-bottom:4px;">Laporan ini telah diperiksa dan disetujui oleh:</div>
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

<!-- ═══════════════════ FOOTER ═══════════════════ -->
<div class="page-footer">
  <div class="pf-left">
    FixSmart Helpdesk &mdash; Laporan Aset IT &mdash; Dicetak: <?= date('d/m/Y H:i:s') ?> WIB<br>
    Dokumen ini bersifat rahasia dan hanya untuk keperluan internal manajemen.
  </div>
  <div class="pf-right">
    No. Dok: <?= $no_dok ?> &nbsp;|&nbsp; Total: <?= $total_aset ?> unit<br>
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

$kat_slug = $kategori     !== '' ? '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $kategori)     : '';
$sp_slug  = $status_pakai !== '' ? '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $status_pakai) : '';
$filename = 'Laporan_Aset_IT' . $kat_slug . $sp_slug . '_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;