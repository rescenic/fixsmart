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
$mode         = $_GET['mode']         ?? 'semua';   // semua | kategori | kalibrasi
$kategori     = $_GET['kategori']     ?? '';
$kondisi      = $_GET['kondisi']      ?? '';
$status_pakai = $_GET['status_pakai'] ?? '';
$jenis_aset   = $_GET['jenis_aset']   ?? '';        // Medis | Non-Medis | (kosong=semua)

// ── Query ─────────────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($mode === 'kategori' && $kategori !== '') {
    $where[]  = 'a.kategori = ?';
    $params[] = $kategori;
}
if ($mode === 'kalibrasi') {
    // Tampilkan yang punya jadwal kalibrasi (expired atau akan jatuh tempo)
    $where[] = "(a.tgl_kalibrasi_berikutnya IS NOT NULL AND a.tgl_kalibrasi_berikutnya <= DATE_ADD(CURDATE(), INTERVAL 60 DAY))";
}
if ($kondisi      !== '') { $where[] = 'a.kondisi = ?';      $params[] = $kondisi; }
if ($status_pakai !== '') { $where[] = 'a.status_pakai = ?'; $params[] = $status_pakai; }
if ($jenis_aset   !== '') { $where[] = 'a.jenis_aset = ?';   $params[] = $jenis_aset; }

$wsql = implode(' AND ', $where);

$st = $pdo->prepare("
    SELECT a.*,
           b.nama   AS bagian_nama,
           b.kode   AS bagian_kode,
           b.lokasi AS bagian_lokasi,
           u.nama   AS pj_nama_db,
           u.divisi AS pj_divisi
    FROM aset_ipsrs a
    LEFT JOIN bagian b ON b.id = a.bagian_id
    LEFT JOIN users  u ON u.id = a.pj_user_id
    WHERE $wsql
    ORDER BY a.jenis_aset ASC, a.kategori ASC, a.nama_aset ASC
");
$st->execute($params);
$asets = $st->fetchAll();

// ── Statistik ─────────────────────────────────────────────────────────────────
$stats_kondisi = [];
foreach ($asets as $a) {
    $k = $a['kondisi'] ?? 'Lainnya';
    $stats_kondisi[$k] = ($stats_kondisi[$k] ?? 0) + 1;
}
$stats_pakai = [];
foreach ($asets as $a) {
    $s = $a['status_pakai'] ?? 'Terpakai';
    $stats_pakai[$s] = ($stats_pakai[$s] ?? 0) + 1;
}
$stats_jenis = [];
foreach ($asets as $a) {
    $j = $a['jenis_aset'] ?? 'Non-Medis';
    $stats_jenis[$j] = ($stats_jenis[$j] ?? 0) + 1;
}

$total_aset  = count($asets);
$jml_kal_exp = 0;
$jml_kal_due = 0;
$jml_svc_due = 0;
foreach ($asets as $a) {
    $kn = $a['tgl_kalibrasi_berikutnya'] ?? null;
    $sn = $a['tgl_service_berikutnya']   ?? null;
    if ($kn && strtotime($kn) < time())                                         $jml_kal_exp++;
    elseif ($kn && strtotime($kn) < strtotime('+30 days'))                      $jml_kal_due++;
    if ($sn && strtotime($sn) > 0 && strtotime($sn) < strtotime('+30 days'))   $jml_svc_due++;
}

// Kelompokkan per jenis → per kategori
$by_jenis_kat = [];
foreach ($asets as $a) {
    $j   = $a['jenis_aset'] ?? 'Non-Medis';
    $kat = $a['kategori']   ?: 'Tanpa Kategori';
    $by_jenis_kat[$j][$kat][] = $a;
}

// Judul laporan
$judul_parts = [];
if ($jenis_aset   !== '') $judul_parts[] = 'Jenis: '    . htmlspecialchars($jenis_aset);
if ($mode === 'kategori' && $kategori !== '') $judul_parts[] = 'Kategori: ' . htmlspecialchars($kategori);
if ($kondisi      !== '') $judul_parts[] = 'Kondisi: '  . htmlspecialchars($kondisi);
if ($status_pakai !== '') $judul_parts[] = 'Status: '   . htmlspecialchars($status_pakai);
if ($mode === 'kalibrasi') $judul_parts[] = 'Kalibrasi Jatuh Tempo / Expired';

$no_dok = 'RPT-IPSRS-' . date('Ymd') . '-' . strtoupper(substr(md5($mode.$kategori.$kondisi.$status_pakai.$jenis_aset), 0, 4));

// ── Helpers ───────────────────────────────────────────────────────────────────
function ipsrs_kondisiStyle(string $k): array {
    return match ($k) {
        'Baik'            => ['bg' => '#dcfce7', 'fg' => '#15803d'],
        'Rusak'           => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        'Dalam Perbaikan' => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'Tidak Aktif'     => ['bg' => '#f1f5f9', 'fg' => '#475569'],
        default           => ['bg' => '#f1f5f9', 'fg' => '#64748b'],
    };
}
function ipsrs_statusPakaiStyle(string $s): array {
    return match ($s) {
        'Terpakai'       => ['bg' => '#dbeafe', 'fg' => '#1e40af'],
        'Tidak Terpakai' => ['bg' => '#d1fae5', 'fg' => '#065f46'],
        'Dipinjam'       => ['bg' => '#fef3c7', 'fg' => '#92400e'],
        default          => ['bg' => '#f1f5f9', 'fg' => '#64748b'],
    };
}
function ipsrs_jenisStyle(string $j): array {
    return $j === 'Medis'
        ? ['bg' => '#fce7f3', 'fg' => '#9d174d']
        : ['bg' => '#eff6ff', 'fg' => '#1e40af'];
}
function ipsrs_kalStyle(?string $tgl): string {
    if (!$tgl) return 'none';
    if (strtotime($tgl) < time())                   return 'expired';
    if (strtotime($tgl) < strtotime('+30 days'))    return 'soon';
    return 'ok';
}
function ipsrs_fmtTgl(?string $tgl): string {
    return $tgl ? date('d/m/Y', strtotime($tgl)) : '—';
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
.kop { display: table; width: 100%; padding-bottom: 10px; border-bottom: 3px solid #7c2d12; margin-bottom: 4px; }
.kop-logo-area { display: table-cell; vertical-align: middle; width: 56px; }
.kop-logo-box  { width: 44px; height: 44px; background: linear-gradient(135deg,#7c2d12,#ea580c); border-radius: 6px; text-align: center; line-height: 44px; font-size: 16px; color: #fff; font-weight: bold; }
.kop-text      { display: table-cell; vertical-align: middle; padding-left: 11px; }
.kop-org       { font-size: 14pt; font-weight: bold; color: #7c2d12; line-height: 1.2; }
.kop-sub       { font-size: 8pt; color: #78350f; margin-top: 1px; }
.kop-right     { display: table-cell; vertical-align: middle; text-align: right; }
.kop-right-label { font-size: 7pt; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; }
.kop-right-val   { font-size: 8.5pt; color: #334155; font-weight: bold; }
.kop-rule2     { height: 1px; background: #fed7aa; margin-bottom: 14px; }

/* ── JUDUL ── */
.report-title-block { text-align: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #fed7aa; }
.report-label       { font-size: 7pt; letter-spacing: 3px; text-transform: uppercase; color: #92400e; margin-bottom: 4px; }
.report-main-title  { font-size: 14pt; font-weight: bold; color: #7c2d12; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.report-sub         { font-size: 10pt; color: #ea580c; font-weight: bold; }
.report-no          { font-size: 7.5pt; color: #94a3b8; margin-top: 4px; }

/* ── INFO DOKUMEN ── */
.doc-info    { width: 100%; border-collapse: collapse; margin-bottom: 16px; border: 1px solid #fed7aa; }
.doc-info td { padding: 5px 9px; font-size: 8.5pt; border: 1px solid #fed7aa; vertical-align: top; }
.di-label    { background: #fff7ed; color: #92400e; font-weight: bold; width: 20%; }
.di-val      { color: #1e293b; width: 30%; }

/* ── SECTION ── */
.sec       { margin-top: 16px; margin-bottom: 8px; page-break-after: avoid; }
.sec-num   { font-size: 7pt; letter-spacing: 2px; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px; }
.sec-title { font-size: 10pt; font-weight: bold; color: #7c2d12; border-bottom: 2px solid #ea580c; padding-bottom: 4px; display: table; width: 100%; }
.sec-title-text { display: table-cell; }
.sec-title-rule { display: table-cell; text-align: right; font-size: 7.5pt; font-weight: normal; color: #94a3b8; vertical-align: bottom; padding-bottom: 1px; }

/* ── KPI CARDS ── */
.kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
.kpi-td    { vertical-align: top; }
.kpi-card  { border: 1px solid #e2e8f0; border-top: 3px solid #ea580c; border-radius: 0 0 4px 4px; padding: 10px 12px 8px; background: #fff; }
.kpi-card .k-label { font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.kpi-card .k-val   { font-size: 18pt; font-weight: bold; line-height: 1; margin-bottom: 3px; }
.kpi-card .k-desc  { font-size: 7.5pt; color: #94a3b8; line-height: 1.4; }

/* ── STATUS PAKAI CARDS ── */
.sp-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
.sp-card  { border: 1px solid #e2e8f0; border-left: 4px solid #ea580c; border-radius: 0 4px 4px 0; padding: 8px 12px; background: #fff; display: table; width: 100%; }
.sp-card-l { display: table-cell; vertical-align: middle; }
.sp-card-r { display: table-cell; vertical-align: middle; text-align: right; }
.sp-val    { font-size: 16pt; font-weight: bold; line-height: 1; }
.sp-lbl    { font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #64748b; }
.sp-pct    { font-size: 9pt; font-weight: bold; }

/* ── JENIS CARDS (Medis vs Non-Medis) ── */
.jenis-cards { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
.jenis-card  { border: 1px solid #e2e8f0; border-left: 4px solid #ec4899; border-radius: 0 4px 4px 0; padding: 8px 12px; background: #fff; display: table; width: 100%; }

/* ── TABEL DATA ── */
.kat-jenis-header { background: linear-gradient(135deg,#7c2d12,#c2410c); color: #fff; padding: 5px 10px; font-size: 8.5pt; font-weight: bold; border-radius: 3px 3px 0 0; margin-top: 16px; display: table; width: 100%; }
.kat-jenis-header-l { display: table-cell; }
.kat-jenis-header-r { display: table-cell; text-align: right; font-size: 7.5pt; font-weight: normal; color: rgba(255,255,255,.7); }
.kat-sub-header { background: linear-gradient(90deg,#fff7ed,#ffedd5); border-left: 4px solid #f97316; padding: 5px 10px; font-size: 8.5pt; font-weight: bold; color: #c2410c; margin-top: 10px; display: table; width: 100%; }
.kat-sub-header-l { display: table-cell; }
.kat-sub-header-r { display: table-cell; text-align: right; font-size: 7.5pt; color: #78350f; }

.data-tbl { width: 100%; border-collapse: collapse; font-size: 7.8pt; page-break-inside: auto; }
.data-tbl thead tr    { background: #1e293b; color: #fff; }
.data-tbl thead th    { padding: 6px 7px; text-align: left; font-size: 7pt; font-weight: bold; letter-spacing: 0.3px; border-right: 1px solid rgba(255,255,255,0.08); }
.data-tbl thead th:last-child { border-right: none; }
.data-tbl tbody tr td { padding: 5px 7px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 7.8pt; }
.data-tbl tbody tr:nth-child(even) td { background: #fafafa; }
.data-tbl tbody tr:last-child td { border-bottom: 2px solid #fed7aa; }
.data-tbl tfoot td    { padding: 5px 7px; font-weight: bold; background: #fff7ed; border-top: 2px solid #fed7aa; color: #7c2d12; font-size: 8pt; }

/* Row highlight status pakai */
.data-tbl tbody tr.row-tidak td   { background: #f0fdf4 !important; border-left: 3px solid #22c55e; }
.data-tbl tbody tr.row-dipinjam td { background: #fffbeb !important; border-left: 3px solid #f59e0b; }
.data-tbl tbody tr.row-kal-exp td  { background: #fff1f2 !important; border-left: 3px solid #ef4444; }

/* Badge styles */
.kondisi-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; white-space: nowrap; }
.sp-badge      { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; white-space: nowrap; }
.jenis-badge   { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7pt; font-weight: bold; white-space: nowrap; }
.inv-code      { font-family: 'Courier New', monospace; font-size: 7.5pt; font-weight: bold; background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 1px 5px; border-radius: 3px; white-space: nowrap; }

/* Kalibrasi / service indicators */
.kal-exp  { color: #ef4444; font-weight: bold; }
.kal-soon { color: #f59e0b; font-weight: bold; }
.kal-ok   { color: #16a34a; }
.kal-none { color: #cbd5e1; }

/* ── RINGKASAN PER KATEGORI ── */
.summ-tbl    { width: 100%; border-collapse: collapse; font-size: 8pt; }
.summ-tbl th { background: #1e293b; color: #fff; padding: 6px 8px; font-size: 7.5pt; text-align: left; }
.summ-tbl td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; font-size: 8pt; }
.summ-tbl tr:nth-child(even) td { background: #fafafa; }
.summ-tbl tfoot td { background: #fff7ed; border-top: 2px solid #fed7aa; font-weight: bold; color: #7c2d12; }

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
.page-footer { margin-top: 18px; padding-top: 7px; border-top: 1px solid #fed7aa; display: table; width: 100%; }
.pf-left  { display: table-cell; font-size: 7pt; color: #94a3b8; vertical-align: middle; }
.pf-right { display: table-cell; text-align: right; font-size: 7pt; color: #94a3b8; vertical-align: middle; }

/* ── EXECUTIVE BOX ── */
.exec-box { border-left: 4px solid #ea580c; background: #fff7ed; padding: 10px 14px; margin-bottom: 6px; border-radius: 0 4px 4px 0; font-size: 9pt; color: #1e293b; line-height: 1.7; }

/* ── ALERT BANNER ── */
.alert-banner { display: table; width: 100%; padding: 7px 12px; border-radius: 4px; margin-bottom: 10px; font-size: 8.5pt; font-weight: bold; }
.alert-banner-l { display: table-cell; vertical-align: middle; }
.alert-banner-r { display: table-cell; vertical-align: middle; text-align: right; font-size: 8pt; }

/* ── MODE KALIBRASI: jadwal tabel khusus ── */
.kal-tbl    { width: 100%; border-collapse: collapse; font-size: 8pt; }
.kal-tbl th { background: #1e293b; color: #fff; padding: 6px 8px; text-align: left; font-size: 7.5pt; }
.kal-tbl td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; font-size: 8pt; vertical-align: middle; }
.kal-tbl tr:nth-child(even) td { background: #fafafa; }
.kal-tbl tbody tr.kal-row-exp td  { background: #fff1f2 !important; }
.kal-tbl tbody tr.kal-row-soon td { background: #fffbeb !important; }
</style>
</head>
<body>

<!-- ═══════════════════ KOP ═══════════════════ -->
<div class="kop">
  <div class="kop-logo-area">
    <div class="kop-logo-box">&#128295;</div>
  </div>
  <div class="kop-text">
    <div class="kop-org">FixSmart Helpdesk</div>
    <div class="kop-sub">IPSRS &mdash; Instalasi Pemeliharaan Sarana Rumah Sakit &mdash; Sistem Manajemen Aset</div>
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
  <div class="report-main-title">
    <?= $mode === 'kalibrasi' ? 'Laporan Jadwal Kalibrasi Aset IPSRS' : 'Laporan Data Aset IPSRS' ?>
  </div>
  <div class="report-sub">
    <?php if (!empty($judul_parts)): ?>
      <?= implode(' &nbsp;&mdash;&nbsp; ', $judul_parts) ?>
    <?php else: ?>
      Semua Aset &mdash; Medis &amp; Non-Medis
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
    <td class="di-val">IPSRS &mdash; Inst. Pemeliharaan Sarana RS</td>
    <td class="di-label">Tanggal Cetak</td>
    <td class="di-val"><?= date('d F Y, H:i') ?> WIB</td>
  </tr>
  <tr>
    <td class="di-label">Jenis Laporan</td>
    <td class="di-val"><?= $mode === 'kalibrasi' ? 'Jadwal Kalibrasi' : 'Inventarisasi Aset IPSRS' ?></td>
    <td class="di-label">Status Dokumen</td>
    <td class="di-val">Final &mdash; Confidential</td>
  </tr>
  <tr>
    <td class="di-label">Filter Jenis Aset</td>
    <td class="di-val">
      <?php if ($jenis_aset !== ''):
        $js = ipsrs_jenisStyle($jenis_aset); ?>
        <span class="jenis-badge" style="background:<?= $js['bg'] ?>;color:<?= $js['fg'] ?>;"><?= htmlspecialchars($jenis_aset) ?></span>
      <?php else: ?>Medis &amp; Non-Medis<?php endif; ?>
    </td>
    <td class="di-label">Filter Kondisi</td>
    <td class="di-val"><?= $kondisi !== '' ? htmlspecialchars($kondisi) : 'Semua Kondisi' ?></td>
  </tr>
  <tr>
    <td class="di-label">Filter Status Pakai</td>
    <td class="di-val">
      <?php if ($status_pakai !== ''):
        $spst = ipsrs_statusPakaiStyle($status_pakai); ?>
        <span class="sp-badge" style="background:<?= $spst['bg'] ?>;color:<?= $spst['fg'] ?>;"><?= htmlspecialchars($status_pakai) ?></span>
      <?php else: ?>Semua Status<?php endif; ?>
    </td>
    <td class="di-label">Total Aset</td>
    <td class="di-val">
      <strong><?= $total_aset ?> unit</strong>
      &nbsp;|&nbsp;
      <span style="color:#9d174d;font-weight:bold;">Medis: <?= $stats_jenis['Medis'] ?? 0 ?></span>
      &nbsp;&middot;&nbsp;
      <span style="color:#1e40af;font-weight:bold;">Non-Medis: <?= $stats_jenis['Non-Medis'] ?? 0 ?></span>
    </td>
  </tr>
</table>

<?php if ($jml_kal_exp > 0 || $jml_kal_due > 0 || $jml_svc_due > 0): ?>
<!-- Alert kalibrasi / service -->
<div class="alert-banner" style="background:#fff7ed;border:1px solid #fed7aa;">
  <div class="alert-banner-l" style="color:#92400e;">
    &#9888; Perhatian &mdash; Jadwal Pemeliharaan Aset
  </div>
  <div class="alert-banner-r" style="color:#78350f;">
    <?php if($jml_kal_exp>0): ?>
      <span style="color:#ef4444;font-weight:bold;"><?= $jml_kal_exp ?> kalibrasi EXPIRED</span> &nbsp;
    <?php endif; ?>
    <?php if($jml_kal_due>0): ?>
      <?= $jml_kal_due ?> kalibrasi jatuh tempo &lt;30 hari &nbsp;
    <?php endif; ?>
    <?php if($jml_svc_due>0): ?>
      <?= $jml_svc_due ?> service jatuh tempo &lt;30 hari
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($status_pakai === 'Tidak Terpakai'): ?>
<div class="alert-banner" style="background:#d1fae5;border:1px solid #86efac;">
  <div class="alert-banner-l" style="color:#065f46;">&#9679; LAPORAN KHUSUS: Aset Tidak Terpakai</div>
  <div class="alert-banner-r" style="color:#065f46;">Total <?= $total_aset ?> unit aset yang saat ini tidak digunakan</div>
</div>
<?php elseif ($status_pakai === 'Dipinjam'): ?>
<div class="alert-banner" style="background:#fef3c7;border:1px solid #fde68a;">
  <div class="alert-banner-l" style="color:#92400e;">&#9679; LAPORAN KHUSUS: Aset Sedang Dipinjam</div>
  <div class="alert-banner-r" style="color:#92400e;">Total <?= $total_aset ?> unit aset dalam status dipinjam</div>
</div>
<?php endif; ?>

<?php if ($mode !== 'kalibrasi'): ?>
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
$jml_medis     = $stats_jenis['Medis'] ?? 0;
$jml_nonmedis  = $stats_jenis['Non-Medis'] ?? 0;
?>
<div class="exec-box">
  Laporan ini menyajikan data inventarisasi aset IPSRS
  <?php if ($jenis_aset !== ''): ?>untuk jenis <strong><?= htmlspecialchars($jenis_aset) ?></strong>
  <?php else: ?>mencakup <strong>seluruh jenis aset</strong> (Medis &amp; Non-Medis)<?php endif; ?>
  <?php if ($mode === 'kategori' && $kategori !== ''): ?>, kategori <strong><?= htmlspecialchars($kategori) ?></strong><?php endif; ?>.
  <?php if ($status_pakai !== ''): ?>Laporan ini <strong>difilter berdasarkan status: <?= htmlspecialchars($status_pakai) ?></strong>.<?php endif; ?>
  Total aset yang tercatat sebanyak <strong><?= $total_aset ?> unit</strong>:
  <strong><?= $jml_medis ?> unit Medis</strong> dan <strong><?= $jml_nonmedis ?> unit Non-Medis</strong>.
  Dari sisi kondisi: <strong><?= $jml_baik ?> unit (<?= $pct_baik ?>%)</strong> dalam kondisi baik,
  <strong><?= $jml_rusak ?> unit</strong> rusak, <strong><?= $jml_perbaikan ?> unit</strong> dalam perbaikan.
  Status pemakaian: <strong><?= $jml_terpakai ?> unit (<?= $pct_terpakai ?>%)</strong> terpakai,
  <strong><?= $jml_tidak ?> unit (<?= $pct_tidak ?>%)</strong> tidak terpakai,
  <strong><?= $jml_dipinjam ?> unit</strong> dipinjam.
  <?php if ($jml_kal_exp > 0): ?>
    <strong style="color:#ef4444;">Terdapat <?= $jml_kal_exp ?> aset dengan kalibrasi expired yang perlu ditindaklanjuti segera.</strong>
  <?php endif; ?>
</div>

<!-- ═══════════════════ II. STATISTIK KONDISI ═══════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian II</div>
  <div class="sec-title"><span class="sec-title-text">Statistik Kondisi Aset</span></div>
</div>
<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#ea580c;">
        <div class="k-label">Total Aset IPSRS</div>
        <div class="k-val" style="color:#ea580c;"><?= $total_aset ?></div>
        <div class="k-desc">Seluruh unit aset tercatat di IPSRS</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#ec4899;">
        <div class="k-label">Aset Medis</div>
        <div class="k-val" style="color:#ec4899;"><?= $stats_jenis['Medis'] ?? 0 ?></div>
        <div class="k-desc">Peralatan &amp; alat kesehatan</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#3b82f6;">
        <div class="k-label">Aset Non-Medis</div>
        <div class="k-val" style="color:#3b82f6;"><?= $stats_jenis['Non-Medis'] ?? 0 ?></div>
        <div class="k-desc">Sarana, prasarana &amp; utilitas</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#16a34a;">
        <div class="k-label">Kondisi Baik</div>
        <div class="k-val" style="color:#16a34a;"><?= $stats_kondisi['Baik'] ?? 0 ?></div>
        <div class="k-desc">Siap beroperasi normal</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#dc2626;">
        <div class="k-label">Rusak</div>
        <div class="k-val" style="color:#dc2626;"><?= $stats_kondisi['Rusak'] ?? 0 ?></div>
        <div class="k-desc">Perlu penggantian / tindak lanjut</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#d97706;">
        <div class="k-label">Dalam Perbaikan</div>
        <div class="k-val" style="color:#d97706;"><?= $stats_kondisi['Dalam Perbaikan'] ?? 0 ?></div>
        <div class="k-desc">Sedang proses perbaikan</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#ef4444;">
        <div class="k-label">Kalibrasi Exp.</div>
        <div class="k-val" style="color:#ef4444;"><?= $jml_kal_exp ?></div>
        <div class="k-desc">Kalibrasi sudah terlewat</div>
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
    <td class="kpi-td">
      <div class="sp-card" style="border-left-color:#1d4ed8;">
        <div class="sp-card-l">
          <div class="sp-lbl">Terpakai</div>
          <div class="sp-val" style="color:#1d4ed8;"><?= $stats_pakai['Terpakai'] ?? 0 ?></div>
          <div style="font-size:7.5pt;color:#94a3b8;">Aktif digunakan</div>
        </div>
        <div class="sp-card-r">
          <div class="sp-pct" style="color:#1d4ed8;"><?= $total_aset > 0 ? round(($stats_pakai['Terpakai'] ?? 0) / $total_aset * 100) : 0 ?>%</div>
          <div class="bar-wrap" style="width:50px;display:block;margin-top:4px;">
            <div class="bar-fill" style="width:<?= $total_aset > 0 ? round(($stats_pakai['Terpakai'] ?? 0) / $total_aset * 100) : 0 ?>%;background:#1d4ed8;"></div>
          </div>
        </div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="sp-card" style="border-left-color:#16a34a;background:#f0fdf4;">
        <div class="sp-card-l">
          <div class="sp-lbl" style="color:#065f46;">Tidak Terpakai</div>
          <div class="sp-val" style="color:#16a34a;"><?= $stats_pakai['Tidak Terpakai'] ?? 0 ?></div>
          <div style="font-size:7.5pt;color:#94a3b8;">Belum / tidak digunakan</div>
        </div>
        <div class="sp-card-r">
          <div class="sp-pct" style="color:#16a34a;"><?= $total_aset > 0 ? round(($stats_pakai['Tidak Terpakai'] ?? 0) / $total_aset * 100) : 0 ?>%</div>
          <div class="bar-wrap" style="width:50px;display:block;margin-top:4px;">
            <div class="bar-fill" style="width:<?= $total_aset > 0 ? round(($stats_pakai['Tidak Terpakai'] ?? 0) / $total_aset * 100) : 0 ?>%;background:#16a34a;"></div>
          </div>
        </div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="sp-card" style="border-left-color:#d97706;background:#fffbeb;">
        <div class="sp-card-l">
          <div class="sp-lbl" style="color:#92400e;">Dipinjam</div>
          <div class="sp-val" style="color:#d97706;"><?= $stats_pakai['Dipinjam'] ?? 0 ?></div>
          <div style="font-size:7.5pt;color:#94a3b8;">Sedang dalam status pinjam</div>
        </div>
        <div class="sp-card-r">
          <div class="sp-pct" style="color:#d97706;"><?= $total_aset > 0 ? round(($stats_pakai['Dipinjam'] ?? 0) / $total_aset * 100) : 0 ?>%</div>
          <div class="bar-wrap" style="width:50px;display:block;margin-top:4px;">
            <div class="bar-fill" style="width:<?= $total_aset > 0 ? round(($stats_pakai['Dipinjam'] ?? 0) / $total_aset * 100) : 0 ?>%;background:#d97706;"></div>
          </div>
        </div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="sp-card" style="border-left-color:#ef4444;background:#fff1f2;">
        <div class="sp-card-l">
          <div class="sp-lbl" style="color:#991b1b;">Kal. Expired</div>
          <div class="sp-val" style="color:#ef4444;"><?= $jml_kal_exp ?></div>
          <div style="font-size:7.5pt;color:#94a3b8;">Kalibrasi telah melewati jatuh tempo</div>
        </div>
        <div class="sp-card-r">
          <div class="sp-pct" style="color:#f59e0b;"><?= $jml_kal_due ?> &lt;30hr</div>
          <div style="font-size:7pt;color:#94a3b8;margin-top:2px;">Service: <?= $jml_svc_due ?> due</div>
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
    <span class="sec-title-rule">Dikelompokkan per Jenis: Medis &amp; Non-Medis</span>
  </div>
</div>
<?php
// Hitung ringkasan per kategori (gabungan semua jenis)
$by_kat_flat = [];
foreach ($asets as $a) {
    $key = ($a['jenis_aset'] ?? 'Non-Medis') . ' — ' . ($a['kategori'] ?: 'Tanpa Kategori');
    if (!isset($by_kat_flat[$key])) $by_kat_flat[$key] = ['jenis' => $a['jenis_aset'] ?? 'Non-Medis', 'items' => []];
    $by_kat_flat[$key]['items'][] = $a;
}
?>
<table class="summ-tbl">
  <thead>
    <tr>
      <th style="width:22px;">No.</th>
      <th style="width:55px;">Jenis</th>
      <th>Kategori</th>
      <th style="text-align:center;">Total</th>
      <th style="text-align:center;">Baik</th>
      <th style="text-align:center;">Rusak</th>
      <th style="text-align:center;">Perb.</th>
      <th style="text-align:center;">Terpakai</th>
      <th style="text-align:center;">Tdk Terp.</th>
      <th style="text-align:center;">Kal.Exp</th>
      <th style="text-align:center;">% Baik</th>
      <th>Proporsi</th>
    </tr>
  </thead>
  <tbody>
    <?php $no_kat = 0; foreach ($by_kat_flat as $key => $data):
      $no_kat++;
      $items   = $data['items'];
      $jenis   = $data['jenis'];
      $kat_nm  = explode(' — ', $key, 2)[1] ?? $key;
      $n_total = count($items);
      $n_baik  = count(array_filter($items, fn($x) => $x['kondisi'] === 'Baik'));
      $n_rusak = count(array_filter($items, fn($x) => $x['kondisi'] === 'Rusak'));
      $n_perb  = count(array_filter($items, fn($x) => $x['kondisi'] === 'Dalam Perbaikan'));
      $n_terp  = count(array_filter($items, fn($x) => ($x['status_pakai'] ?? '') === 'Terpakai'));
      $n_tdk   = count(array_filter($items, fn($x) => ($x['status_pakai'] ?? '') === 'Tidak Terpakai'));
      $n_kexp  = count(array_filter($items, fn($x) => $x['tgl_kalibrasi_berikutnya'] && strtotime($x['tgl_kalibrasi_berikutnya']) < time()));
      $pct_b   = $n_total > 0 ? round($n_baik / $n_total * 100) : 0;
      $pct_tot = $total_aset > 0 ? round($n_total / $total_aset * 100) : 0;
      $js      = ipsrs_jenisStyle($jenis);
    ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $no_kat ?></td>
      <td><span class="jenis-badge" style="background:<?= $js['bg'] ?>;color:<?= $js['fg'] ?>;"><?= $jenis === 'Medis' ? 'Medis' : 'Non-Med.' ?></span></td>
      <td style="font-weight:bold;"><?= htmlspecialchars($kat_nm) ?></td>
      <td style="text-align:center;font-weight:bold;"><?= $n_total ?></td>
      <td style="text-align:center;color:#16a34a;font-weight:bold;"><?= $n_baik ?></td>
      <td style="text-align:center;color:#dc2626;"><?= $n_rusak ?></td>
      <td style="text-align:center;color:#d97706;"><?= $n_perb ?></td>
      <td style="text-align:center;color:#1d4ed8;font-weight:bold;"><?= $n_terp ?></td>
      <td style="text-align:center;color:#16a34a;"><?= $n_tdk > 0 ? '<strong>'.$n_tdk.'</strong>' : '0' ?></td>
      <td style="text-align:center;color:<?= $n_kexp > 0 ? '#ef4444' : '#94a3b8' ?>;font-weight:<?= $n_kexp > 0 ? 'bold' : 'normal' ?>;"><?= $n_kexp > 0 ? $n_kexp : '—' ?></td>
      <td style="text-align:center;">
        <span style="font-weight:bold;color:<?= $pct_b >= 80 ? '#16a34a' : ($pct_b >= 50 ? '#d97706' : '#dc2626') ?>;"><?= $pct_b ?>%</span>
      </td>
      <td>
        <div class="bar-wrap" style="width:60px;">
          <div class="bar-fill" style="width:<?= $pct_tot ?>%;background:#ea580c;"></div>
        </div>
        <span style="font-size:7.5pt;margin-left:3px;"><?= $pct_tot ?>%</span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3">TOTAL KESELURUHAN</td>
      <td style="text-align:center;"><?= $total_aset ?></td>
      <td style="text-align:center;color:#16a34a;"><?= $stats_kondisi['Baik'] ?? 0 ?></td>
      <td style="text-align:center;color:#dc2626;"><?= $stats_kondisi['Rusak'] ?? 0 ?></td>
      <td style="text-align:center;color:#d97706;"><?= $stats_kondisi['Dalam Perbaikan'] ?? 0 ?></td>
      <td style="text-align:center;color:#1d4ed8;"><?= $stats_pakai['Terpakai'] ?? 0 ?></td>
      <td style="text-align:center;color:#16a34a;"><strong><?= $stats_pakai['Tidak Terpakai'] ?? 0 ?></strong></td>
      <td style="text-align:center;color:#ef4444;font-weight:bold;"><?= $jml_kal_exp ?: '—' ?></td>
      <td style="text-align:center;"><?= $pct_baik ?>%</td>
      <td>100%</td>
    </tr>
  </tfoot>
</table>

<!-- ═══════════════════ V. DETAIL ASET ═══════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian V</div>
  <div class="sec-title">
    <span class="sec-title-text">Detail Data Aset IPSRS</span>
    <span class="sec-title-rule">Dikelompokkan per Jenis &amp; Kategori &nbsp;|&nbsp; &#9632; Merah kiri = Kal.Expired &nbsp; &#9632; Hijau = Tdk Terpakai &nbsp; &#9632; Kuning = Dipinjam</span>
  </div>
</div>

<?php if (empty($asets)): ?>
<div style="text-align:center;color:#94a3b8;padding:20px;border:1px dashed #fed7aa;border-radius:4px;font-style:italic;">
  Tidak ada data aset untuk filter yang dipilih.
</div>
<?php else: foreach ($by_jenis_kat as $jenis => $kats):
  $js = ipsrs_jenisStyle($jenis);
  $jml_jenis = array_sum(array_map('count', $kats));
?>

<div class="kat-jenis-header">
  <span class="kat-jenis-header-l">
    <?= $jenis === 'Medis' ? '&#128138;' : '&#128295;' ?> &nbsp;
    ASET <?= strtoupper(htmlspecialchars($jenis)) ?>
  </span>
  <span class="kat-jenis-header-r"><?= $jml_jenis ?> unit</span>
</div>

<?php foreach ($kats as $kat => $items):
  $n_kat    = count($items);
  $n_terp   = count(array_filter($items, fn($x) => ($x['status_pakai'] ?? '') === 'Terpakai'));
  $n_tdk_p  = count(array_filter($items, fn($x) => ($x['status_pakai'] ?? '') === 'Tidak Terpakai'));
  $n_dipinj = count(array_filter($items, fn($x) => ($x['status_pakai'] ?? '') === 'Dipinjam'));
?>

<div class="kat-sub-header">
  <span class="kat-sub-header-l">&#9654; <?= htmlspecialchars($kat) ?></span>
  <span class="kat-sub-header-r">
    <?= $n_kat ?> unit &nbsp;|&nbsp;
    Terpakai: <?= $n_terp ?> &nbsp;
    Tdk Terpakai: <?= $n_tdk_p ?> &nbsp;
    Dipinjam: <?= $n_dipinj ?>
  </span>
</div>

<table class="data-tbl">
  <thead>
    <tr>
      <th style="width:20px;">#</th>
      <th style="width:85px;">No. Inventaris</th>
      <th style="width:80px;">No. Aset RS</th>
      <th style="width:110px;">Nama Aset</th>
      <th style="width:65px;">Merek/Model</th>
      <th>Lokasi / Instalasi</th>
      <th style="width:70px;">Penangg. Jawab</th>
      <th style="width:58px;">Status</th>
      <th style="width:58px;">Kondisi</th>
      <th style="width:62px;">Kal. Berikutnya</th>
      <th style="width:62px;">Svc. Berikutnya</th>
      <th style="width:56px;">Garansi s/d</th>
    </tr>
  </thead>
  <tbody>
    <?php $no_item = 0; foreach ($items as $a):
      $no_item++;
      $sp       = $a['status_pakai'] ?? 'Terpakai';
      $sstyle   = ipsrs_statusPakaiStyle($sp);
      $kstyle   = ipsrs_kondisiStyle($a['kondisi'] ?? '');
      $kal_st   = ipsrs_kalStyle($a['tgl_kalibrasi_berikutnya'] ?? null);
      $svc_st   = ipsrs_kalStyle($a['tgl_service_berikutnya']   ?? null);
      $g_exp    = $a['garansi_sampai'] && strtotime($a['garansi_sampai']) < time();
      $g_soon   = $a['garansi_sampai'] && !$g_exp && strtotime($a['garansi_sampai']) < strtotime('+30 days');
      $lokasi   = $a['bagian_nama']
        ? ($a['bagian_kode'] ? '['.htmlspecialchars($a['bagian_kode']).'] ' : '').htmlspecialchars($a['bagian_nama'])
        : htmlspecialchars($a['lokasi'] ?? '—');
      $pj       = $a['pj_nama_db'] ?: ($a['penanggung_jawab'] ?: '—');

      $row_class = '';
      if ($kal_st === 'expired')          $row_class = 'row-kal-exp';
      elseif ($sp === 'Tidak Terpakai')   $row_class = 'row-tidak';
      elseif ($sp === 'Dipinjam')         $row_class = 'row-dipinjam';
    ?>
    <tr class="<?= $row_class ?>">
      <td style="text-align:center;color:#94a3b8;"><?= $no_item ?></td>
      <td><span class="inv-code"><?= htmlspecialchars($a['no_inventaris'] ?? '—') ?></span></td>
      <td style="font-family:'Courier New',monospace;font-size:7pt;color:#475569;"><?= htmlspecialchars($a['no_aset_rs'] ?? '—') ?></td>
      <td>
        <strong style="font-size:7.8pt;<?= $sp === 'Tidak Terpakai' ? 'text-decoration:line-through;color:#6b7280;' : '' ?>">
          <?= htmlspecialchars($a['nama_aset'] ?? '—') ?>
        </strong>
        <?php if ($a['serial_number']): ?>
          <br><span style="font-size:6.5pt;color:#94a3b8;font-family:'Courier New',monospace;">SN: <?= htmlspecialchars($a['serial_number']) ?></span>
        <?php endif; ?>
      </td>
      <td>
        <span style="font-weight:bold;font-size:7.8pt;"><?= htmlspecialchars($a['merek'] ?? '—') ?></span>
        <?php if ($a['model_aset']): ?><br><span style="font-size:6.5pt;color:#94a3b8;"><?= htmlspecialchars($a['model_aset']) ?></span><?php endif; ?>
      </td>
      <td>
        <div style="font-size:7.8pt;"><?= $lokasi ?></div>
        <?php if ($a['bagian_lokasi']): ?><span style="font-size:6.5pt;color:#94a3b8;"><?= htmlspecialchars($a['bagian_lokasi']) ?></span><?php endif; ?>
      </td>
      <td>
        <div style="font-size:7.8pt;"><?= htmlspecialchars($pj) ?></div>
        <?php if ($a['pj_divisi']): ?><span style="font-size:6.5pt;color:#94a3b8;"><?= htmlspecialchars($a['pj_divisi']) ?></span><?php endif; ?>
      </td>
      <td>
        <span class="sp-badge" style="background:<?= $sstyle['bg'] ?>;color:<?= $sstyle['fg'] ?>;">
          <?= htmlspecialchars($sp) ?>
        </span>
      </td>
      <td>
        <span class="kondisi-badge" style="background:<?= $kstyle['bg'] ?>;color:<?= $kstyle['fg'] ?>;">
          <?= htmlspecialchars($a['kondisi'] ?? '—') ?>
        </span>
      </td>
      <!-- Kalibrasi -->
      <td style="white-space:nowrap;">
        <?php if ($kal_st === 'none'): ?>
          <span class="kal-none">—</span>
        <?php elseif ($kal_st === 'expired'): ?>
          <span class="kal-exp">&#9888; Expired</span><br>
          <span style="font-size:7pt;color:#f87171;"><?= ipsrs_fmtTgl($a['tgl_kalibrasi_berikutnya']) ?></span>
        <?php elseif ($kal_st === 'soon'): ?>
          <span class="kal-soon">&#8987; Segera</span><br>
          <span style="font-size:7pt;color:#fbbf24;"><?= ipsrs_fmtTgl($a['tgl_kalibrasi_berikutnya']) ?></span>
        <?php else: ?>
          <span class="kal-ok"><?= ipsrs_fmtTgl($a['tgl_kalibrasi_berikutnya']) ?></span>
        <?php endif; ?>
        <?php if ($a['tgl_kalibrasi_terakhir']): ?>
          <br><span style="font-size:6.5pt;color:#94a3b8;">Terakhir: <?= ipsrs_fmtTgl($a['tgl_kalibrasi_terakhir']) ?></span>
        <?php endif; ?>
      </td>
      <!-- Service -->
      <td style="white-space:nowrap;">
        <?php if ($svc_st === 'none'): ?>
          <span class="kal-none">—</span>
        <?php elseif ($svc_st === 'expired'): ?>
          <span class="kal-exp">&#9888; Expired</span><br>
          <span style="font-size:7pt;color:#f87171;"><?= ipsrs_fmtTgl($a['tgl_service_berikutnya']) ?></span>
        <?php elseif ($svc_st === 'soon'): ?>
          <span class="kal-soon">&#8987; Segera</span><br>
          <span style="font-size:7pt;color:#fbbf24;"><?= ipsrs_fmtTgl($a['tgl_service_berikutnya']) ?></span>
        <?php else: ?>
          <span class="kal-ok"><?= ipsrs_fmtTgl($a['tgl_service_berikutnya']) ?></span>
        <?php endif; ?>
        <?php if ($a['vendor_service']): ?>
          <br><span style="font-size:6.5pt;color:#94a3b8;"><?= htmlspecialchars(mb_strimwidth($a['vendor_service'],0,20,'…')) ?></span>
        <?php endif; ?>
      </td>
      <!-- Garansi -->
      <td style="font-size:7.5pt;white-space:nowrap;">
        <?php if (!$a['garansi_sampai']): ?><span style="color:#cbd5e1;">—</span>
        <?php elseif ($g_exp): ?>
          <span style="color:#ef4444;font-weight:bold;">Expired</span><br>
          <span style="font-size:6.5pt;color:#f87171;"><?= ipsrs_fmtTgl($a['garansi_sampai']) ?></span>
        <?php elseif ($g_soon): ?>
          <span style="color:#f59e0b;font-weight:bold;">Segera</span><br>
          <span style="font-size:6.5pt;color:#fbbf24;"><?= ipsrs_fmtTgl($a['garansi_sampai']) ?></span>
        <?php else: ?>
          <span style="color:#16a34a;"><?= ipsrs_fmtTgl($a['garansi_sampai']) ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3">Subtotal: <?= htmlspecialchars($kat) ?></td>
      <td colspan="7">
        <?= $n_kat ?> unit &nbsp;|&nbsp;
        <span style="color:#1d4ed8;">Terpakai: <?= $n_terp ?></span> &nbsp;
        <span style="color:#16a34a;">Tdk Terpakai: <?= $n_tdk_p ?></span> &nbsp;
        <span style="color:#d97706;">Dipinjam: <?= $n_dipinj ?></span>
      </td>
      <td style="text-align:right;" colspan="2">
        <?php $th = array_sum(array_column($items, 'harga_beli'));
        echo $th > 0 ? 'Rp '.number_format($th,0,',','.') : '—'; ?>
      </td>
    </tr>
  </tfoot>
</table>

<?php endforeach; // end $kats
endforeach; // end $by_jenis_kat
endif; ?>

<?php else: // MODE KALIBRASI ?>

<!-- ═══════════════════ TABEL KHUSUS KALIBRASI ═══════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian I</div>
  <div class="sec-title">
    <span class="sec-title-text">Jadwal Kalibrasi Aset IPSRS</span>
    <span class="sec-title-rule">Menampilkan kalibrasi expired &amp; jatuh tempo dalam 60 hari ke depan</span>
  </div>
</div>

<?php if (empty($asets)): ?>
<div style="text-align:center;color:#94a3b8;padding:20px;border:1px dashed #fed7aa;border-radius:4px;font-style:italic;">
  Tidak ada aset dengan jadwal kalibrasi yang mendekati jatuh tempo.
</div>
<?php else: ?>

<div class="exec-box">
  Laporan ini menampilkan <strong><?= $total_aset ?> aset</strong> yang memiliki jadwal kalibrasi
  dengan status <strong>expired atau akan jatuh tempo dalam 60 hari ke depan</strong>.
  <?php if ($jml_kal_exp > 0): ?><strong style="color:#ef4444;">Terdapat <?= $jml_kal_exp ?> aset dengan kalibrasi yang sudah expired — perlu tindakan segera.</strong><?php endif; ?>
</div>

<table class="kal-tbl">
  <thead>
    <tr>
      <th style="width:22px;">#</th>
      <th style="width:80px;">No. Inventaris</th>
      <th style="width:110px;">Nama Aset</th>
      <th style="width:50px;">Jenis</th>
      <th>Kategori</th>
      <th style="width:60px;">Merek</th>
      <th>Lokasi / Instalasi</th>
      <th style="width:70px;">Penangg. Jawab</th>
      <th style="width:58px;">Kondisi</th>
      <th style="width:70px;">Kal. Terakhir</th>
      <th style="width:70px;">Kal. Berikutnya</th>
      <th style="width:75px;">No. Sertifikat</th>
      <th style="width:65px;">Status Kal.</th>
    </tr>
  </thead>
  <tbody>
    <?php $no_k = 0; foreach ($asets as $a):
      $no_k++;
      $kal_next = $a['tgl_kalibrasi_berikutnya'] ?? null;
      $is_exp   = $kal_next && strtotime($kal_next) < time();
      $is_soon  = $kal_next && !$is_exp && strtotime($kal_next) < strtotime('+30 days');
      $kstyle   = ipsrs_kondisiStyle($a['kondisi'] ?? '');
      $js       = ipsrs_jenisStyle($a['jenis_aset'] ?? 'Non-Medis');
      $lokasi   = $a['bagian_nama']
        ? ($a['bagian_kode'] ? '['.htmlspecialchars($a['bagian_kode']).'] ' : '').htmlspecialchars($a['bagian_nama'])
        : htmlspecialchars($a['lokasi'] ?? '—');
      $pj       = $a['pj_nama_db'] ?: ($a['penanggung_jawab'] ?: '—');
      $row_kal  = $is_exp ? 'kal-row-exp' : ($is_soon ? 'kal-row-soon' : '');
    ?>
    <tr class="<?= $row_kal ?>">
      <td style="text-align:center;color:#94a3b8;"><?= $no_k ?></td>
      <td><span class="inv-code"><?= htmlspecialchars($a['no_inventaris'] ?? '—') ?></span></td>
      <td><strong style="font-size:7.8pt;"><?= htmlspecialchars($a['nama_aset'] ?? '—') ?></strong></td>
      <td><span class="jenis-badge" style="background:<?= $js['bg'] ?>;color:<?= $js['fg'] ?>;"><?= htmlspecialchars($a['jenis_aset'] ?? '—') ?></span></td>
      <td><?= htmlspecialchars($a['kategori'] ?? '—') ?></td>
      <td><strong><?= htmlspecialchars($a['merek'] ?? '—') ?></strong></td>
      <td>
        <?= $lokasi ?>
        <?php if ($a['bagian_lokasi']): ?><br><span style="font-size:6.5pt;color:#94a3b8;"><?= htmlspecialchars($a['bagian_lokasi']) ?></span><?php endif; ?>
      </td>
      <td><?= htmlspecialchars($pj) ?></td>
      <td><span class="kondisi-badge" style="background:<?= $kstyle['bg'] ?>;color:<?= $kstyle['fg'] ?>;"><?= htmlspecialchars($a['kondisi'] ?? '—') ?></span></td>
      <td style="font-size:7.5pt;"><?= ipsrs_fmtTgl($a['tgl_kalibrasi_terakhir'] ?? null) ?></td>
      <td style="font-size:7.5pt;white-space:nowrap;font-weight:bold;color:<?= $is_exp ? '#ef4444' : ($is_soon ? '#f59e0b' : '#16a34a') ?>;">
        <?= ipsrs_fmtTgl($kal_next) ?>
      </td>
      <td style="font-family:'Courier New',monospace;font-size:7pt;color:#475569;"><?= htmlspecialchars($a['no_sertifikat_kalibrasi'] ?? '—') ?></td>
      <td>
        <?php if ($is_exp): ?>
          <span class="kondisi-badge" style="background:#fee2e2;color:#b91c1c;">&#9888; Expired</span>
        <?php elseif ($is_soon): ?>
          <span class="kondisi-badge" style="background:#fef9c3;color:#a16207;">&#8987; Segera</span>
        <?php else: ?>
          <span class="kondisi-badge" style="background:#dcfce7;color:#15803d;">OK</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php endif; // end kalibrasi data check
endif; // end mode kalibrasi ?>

<!-- ═══════════════════ NILAI TOTAL ASET ═══════════════════ -->
<?php $grand_total = array_sum(array_column($asets, 'harga_beli'));
if ($grand_total > 0): ?>
<div style="margin-top:14px;padding:10px 14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:4px;display:table;width:100%;">
  <div style="display:table-cell;font-weight:bold;color:#7c2d12;font-size:9pt;">Total Nilai Aset IPSRS (tercatat)</div>
  <div style="display:table-cell;text-align:right;font-size:12pt;font-weight:bold;color:#ea580c;">Rp <?= number_format($grand_total, 0, ',', '.') ?></div>
</div>
<?php endif; ?>

<!-- ═══════════════════ KETERANGAN WARNA ═══════════════════ -->
<table style="width:100%;border-collapse:collapse;margin-top:10px;border:1px solid #fed7aa;font-size:7.5pt;">
  <tr>
    <td style="padding:5px 9px;background:#fff7ed;font-weight:bold;color:#92400e;border-right:1px solid #fed7aa;width:12%;">Keterangan</td>
    <?php foreach ([
      ['Baik','#dcfce7','#15803d'],['Rusak','#fee2e2','#b91c1c'],
      ['Dalam Perbaikan','#fef9c3','#a16207'],['Tidak Aktif','#f1f5f9','#475569'],
    ] as [$lbl,$bg,$fg]): ?>
    <td style="padding:5px 8px;border-right:1px solid #fed7aa;">
      <span class="kondisi-badge" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= $lbl ?></span>
    </td>
    <?php endforeach; ?>
    <td style="padding:5px 8px;border-right:1px solid #fed7aa;background:#f0fdf4;">
      <span class="sp-badge" style="background:#d1fae5;color:#065f46;">Tidak Terpakai</span>
      <span style="font-size:7pt;color:#6b7280;margin-left:4px;">Baris hijau + nama dicoret</span>
    </td>
    <td style="padding:5px 8px;border-right:1px solid #fed7aa;background:#fffbeb;">
      <span class="sp-badge" style="background:#fef3c7;color:#92400e;">Dipinjam</span>
      <span style="font-size:7pt;color:#6b7280;margin-left:4px;">Baris kuning</span>
    </td>
    <td style="padding:5px 8px;background:#fff1f2;">
      <span class="sp-badge" style="background:#fee2e2;color:#991b1b;">&#9888; Kal. Expired</span>
      <span style="font-size:7pt;color:#6b7280;margin-left:4px;">Baris merah muda</span>
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
    <div class="ttd-role">Teknisi IPSRS / Operator</div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Diperiksa Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Kepala IPSRS</div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Disetujui Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Direktur / Pimpinan RS</div>
  </div>
</div>

<!-- ═══════════════════ FOOTER ═══════════════════ -->
<div class="page-footer">
  <div class="pf-left">
    FixSmart Helpdesk &mdash; Laporan Aset IPSRS &mdash; Dicetak: <?= date('d/m/Y H:i:s') ?> WIB<br>
    Dokumen ini bersifat rahasia dan hanya untuk keperluan internal manajemen IPSRS / RS.
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
$options->set('fontDir',    __DIR__ . '/../dompdf/vendor/dompdf/dompdf/lib/fonts/');
$options->set('fontCache',  __DIR__ . '/../dompdf/vendor/dompdf/dompdf/lib/fonts/');
$options->set('defaultFont', 'helvetica');
$options->set('dpi', 150);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Nama file dinamis
$slug_parts = [];
if ($jenis_aset   !== '') $slug_parts[] = preg_replace('/[^a-zA-Z0-9]/', '_', $jenis_aset);
if ($kategori     !== '') $slug_parts[] = preg_replace('/[^a-zA-Z0-9]/', '_', $kategori);
if ($kondisi      !== '') $slug_parts[] = preg_replace('/[^a-zA-Z0-9]/', '_', $kondisi);
if ($status_pakai !== '') $slug_parts[] = preg_replace('/[^a-zA-Z0-9]/', '_', $status_pakai);
if ($mode === 'kalibrasi') $slug_parts[] = 'Kalibrasi';

$filename = 'Laporan_Aset_IPSRS'
    . (!empty($slug_parts) ? '_' . implode('_', $slug_parts) : '')
    . '_' . date('Ymd_His') . '.pdf';

$dompdf->stream($filename, ['Attachment' => false]);
exit;