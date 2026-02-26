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

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli',
               'Agustus','September','Oktober','November','Desember'];

// ── Query ────────────────────────────────────────────────────────────────────
$sla_kat = $pdo->prepare("
    SELECT k.nama, k.sla_jam, k.sla_respon_jam,
        COUNT(t.id) as total,
        SUM(t.status IN ('selesai','ditolak','tidak_bisa')) as selesai,
        SUM(t.status='selesai') as solved,
        AVG(t.durasi_respon_menit) as avg_respon,
        AVG(t.durasi_selesai_menit) as avg_selesai,
        SUM(t.status='selesai' AND t.durasi_selesai_menit <= k.sla_jam*60) as sla_met,
        SUM(t.status IN ('menunggu','diproses')) as aktif
    FROM kategori k
    LEFT JOIN tiket t ON t.kategori_id=k.id AND MONTH(t.created_at)=? AND YEAR(t.created_at)=?
    GROUP BY k.id ORDER BY k.nama
");
$sla_kat->execute([$bulan, $tahun]);
$sla_kat = $sla_kat->fetchAll();

$sla_tek = $pdo->prepare("
    SELECT u.nama,
        COUNT(t.id) as total,
        SUM(t.status='selesai') as selesai,
        SUM(t.status='ditolak') as ditolak,
        SUM(t.status='tidak_bisa') as tdk_bisa,
        AVG(t.durasi_respon_menit) as avg_respon,
        AVG(t.durasi_selesai_menit) as avg_selesai,
        SUM(t.status='selesai' AND t.durasi_selesai_menit <= (SELECT k2.sla_jam*60 FROM kategori k2 WHERE k2.id=t.kategori_id)) as sla_met
    FROM users u
    LEFT JOIN tiket t ON t.teknisi_id = u.id
        AND (
            (t.status = 'selesai'    AND MONTH(t.waktu_selesai)  = ? AND YEAR(t.waktu_selesai)  = ?)
            OR
            (t.status != 'selesai'   AND MONTH(t.created_at)     = ? AND YEAR(t.created_at)     = ?)
        )
    WHERE u.role='teknisi' AND u.status='aktif'
    GROUP BY u.id ORDER BY selesai DESC
");
$sla_tek->execute([$bulan, $tahun, $bulan, $tahun]);
$sla_tek = $sla_tek->fetchAll();

$overall = $pdo->prepare("
    SELECT COUNT(*) as total,
        SUM(status IN ('selesai','ditolak','tidak_bisa')) as selesai,
        SUM(status='menunggu') as menunggu,
        SUM(status='diproses') as diproses,
        SUM(status='selesai') as solved,
        SUM(status='ditolak') as ditolak,
        SUM(status='tidak_bisa') as tidak_bisa,
        AVG(durasi_respon_menit) as avg_respon,
        AVG(durasi_selesai_menit) as avg_selesai,
        SUM(status='selesai' AND durasi_selesai_menit <= (SELECT k.sla_jam*60 FROM kategori k WHERE k.id=kategori_id)) as sla_met
    FROM tiket WHERE MONTH(created_at)=? AND YEAR(created_at)=?
");
$overall->execute([$bulan, $tahun]);
$ov = $overall->fetch();
$sla_pct = ($ov['solved'] > 0) ? round($ov['sla_met'] / $ov['solved'] * 100) : 0;

function slaColor($pct) {
    if ($pct >= 90) return ['bg' => '#dcfce7', 'fg' => '#15803d', 'bar' => '#16a34a', 'label' => 'BAIK'];
    if ($pct >= 70) return ['bg' => '#fef9c3', 'fg' => '#a16207', 'bar' => '#ca8a04', 'label' => 'CUKUP'];
    return ['bg'  => '#fee2e2', 'fg' => '#b91c1c', 'bar' => '#dc2626', 'label' => 'KURANG'];
}
$ov_color = slaColor($sla_pct);

$best_tek = null; $best_sla = -1;
foreach ($sla_tek as $tek) {
    $ts = ($tek['selesai'] > 0) ? round($tek['sla_met'] / $tek['selesai'] * 100) : 0;
    // Hanya jadi TERBAIK jika punya minimal 1 tiket selesai DAN SLA lebih tinggi dari yang lain
    if ($tek['selesai'] > 0 && $ts > $best_sla) {
        $best_sla = $ts;
        $best_tek = $tek['nama'];
    }
}

// Kesimpulan otomatis
if ($sla_pct >= 90)
    $kesimpulan = "Kinerja layanan IT pada periode {$nama_bulan[$bulan]} {$tahun} secara keseluruhan <strong>memenuhi standar SLA</strong> yang telah ditetapkan dengan tingkat pencapaian sebesar {$sla_pct}%. Tim IT diharapkan mempertahankan konsistensi performa ini pada periode berikutnya.";
elseif ($sla_pct >= 70)
    $kesimpulan = "Kinerja layanan IT pada periode {$nama_bulan[$bulan]} {$tahun} berada pada kategori <strong>cukup</strong> dengan tingkat pencapaian SLA sebesar {$sla_pct}%. Diperlukan upaya peningkatan untuk mencapai target minimal 90% pada periode mendatang.";
else
    $kesimpulan = "Kinerja layanan IT pada periode {$nama_bulan[$bulan]} {$tahun} <strong>belum memenuhi standar SLA</strong> yang ditetapkan dengan tingkat pencapaian hanya {$sla_pct}%. Manajemen perlu melakukan evaluasi menyeluruh terhadap proses dan kapasitas penanganan tiket.";

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
/* ═══════════════════════════════════════════
   BASE — A4 Portrait, margin 2cm semua sisi
═══════════════════════════════════════════ */
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #1a1a2e;
    background: #fff;
    line-height: 1.6;
    /* margin kertas — jarak isi ke pinggir */
    margin: 42px 52px 42px 52px;
}

/* ═══════════════════════════════════════════
   KOP SURAT / LETTERHEAD
═══════════════════════════════════════════ */
.kop {
    display: table;
    width: 100%;
    padding-bottom: 12px;
    border-bottom: 3px solid #0f172a;
    margin-bottom: 4px;
}
.kop-logo-area {
    display: table-cell;
    vertical-align: middle;
    width: 58px;
}
.kop-logo-box {
    width: 48px; height: 48px;
    background: #0f172a;
    border-radius: 6px;
    text-align: center;
    line-height: 48px;
    font-size: 20px;
    color: #fff;
    font-weight: bold;
    letter-spacing: -1px;
}
.kop-text {
    display: table-cell;
    vertical-align: middle;
    padding-left: 12px;
}
.kop-org {
    font-size: 15pt;
    font-weight: bold;
    color: #0f172a;
    letter-spacing: 0.3px;
    line-height: 1.2;
}
.kop-sub {
    font-size: 8.5pt;
    color: #475569;
    margin-top: 1px;
}
.kop-right {
    display: table-cell;
    vertical-align: middle;
    text-align: right;
}
.kop-right-label {
    font-size: 7.5pt;
    color: #94a3b8;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.kop-right-val {
    font-size: 9pt;
    color: #334155;
    font-weight: bold;
}

/* Garis tipis di bawah kop */
.kop-rule2 {
    height: 1px;
    background: #cbd5e1;
    margin-bottom: 18px;
}

/* ═══════════════════════════════════════════
   JUDUL LAPORAN
═══════════════════════════════════════════ */
.report-title-block {
    text-align: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}
.report-label {
    font-size: 7.5pt;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 5px;
}
.report-main-title {
    font-size: 16pt;
    font-weight: bold;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 4px;
}
.report-period {
    font-size: 11pt;
    color: #1d4ed8;
    font-weight: bold;
}
.report-no {
    font-size: 8pt;
    color: #94a3b8;
    margin-top: 4px;
}

/* ═══════════════════════════════════════════
   INFO DOKUMEN (tabel meta)
═══════════════════════════════════════════ */
.doc-info {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}
.doc-info td {
    padding: 6px 10px;
    font-size: 9pt;
    border: 1px solid #e2e8f0;
    vertical-align: top;
}
.doc-info .di-label {
    background: #f8fafc;
    color: #475569;
    font-weight: bold;
    width: 22%;
    font-size: 8.5pt;
}
.doc-info .di-val {
    color: #1e293b;
    width: 28%;
}

/* ═══════════════════════════════════════════
   SECTION HEADING
═══════════════════════════════════════════ */
.sec {
    margin-top: 20px;
    margin-bottom: 10px;
}
.sec-num {
    font-size: 7.5pt;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 2px;
}
.sec-title {
    font-size: 11pt;
    font-weight: bold;
    color: #0f172a;
    border-bottom: 2px solid #1d4ed8;
    padding-bottom: 5px;
    display: table;
    width: 100%;
}
.sec-title-text {
    display: table-cell;
}
.sec-title-rule {
    display: table-cell;
    text-align: right;
    font-size: 8pt;
    font-weight: normal;
    color: #94a3b8;
    vertical-align: bottom;
    padding-bottom: 2px;
}

/* ═══════════════════════════════════════════
   RINGKASAN EKSEKUTIF
═══════════════════════════════════════════ */
.exec-box {
    border-left: 4px solid #1d4ed8;
    background: #f0f6ff;
    padding: 12px 16px;
    margin-bottom: 6px;
    border-radius: 0 4px 4px 0;
}
.exec-box p {
    font-size: 9.5pt;
    color: #1e293b;
    line-height: 1.7;
}

/* ═══════════════════════════════════════════
   KPI CARDS — 3 per baris, 2 baris
═══════════════════════════════════════════ */
.kpi-table { width: 100%; border-collapse: separate; border-spacing: 7px; margin: 0 -7px; }
.kpi-td    { vertical-align: top; width: 33.3%; }
.kpi-card  {
    border: 1px solid #e2e8f0;
    border-top: 3px solid #1d4ed8;
    border-radius: 0 0 4px 4px;
    padding: 12px 14px 10px;
    background: #fff;
}
.kpi-card .k-label {
    font-size: 7.5pt;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 5px;
}
.kpi-card .k-val {
    font-size: 20pt;
    font-weight: bold;
    line-height: 1;
    margin-bottom: 4px;
}
.kpi-card .k-desc {
    font-size: 8pt;
    color: #94a3b8;
    line-height: 1.4;
}

/* ═══════════════════════════════════════════
   SLA GAUGE + DISTRIBUSI — side by side
═══════════════════════════════════════════ */
.sla-wrap { display: table; width: 100%; border-spacing: 0; }
.sla-left  { display: table-cell; width: 42%; vertical-align: top; padding-right: 14px; }
.sla-right { display: table-cell; vertical-align: top; }

.gauge-box {
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 20px 16px;
    text-align: center;
    background: #f8fafc;
}
.g-pct  { font-size: 44pt; font-weight: bold; line-height: 1; margin-bottom: 4px; }
.g-sub  { font-size: 8.5pt; color: #64748b; margin-bottom: 12px; line-height: 1.5; }
.g-track { background: #e2e8f0; height: 10px; border-radius: 5px; width: 180px; margin: 0 auto 6px; overflow: hidden; }
.g-fill  { height: 10px; border-radius: 5px; }
.g-hint  { font-size: 7.5pt; color: #94a3b8; margin-bottom: 12px; }
.g-chip  {
    display: inline-block;
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 8.5pt;
    font-weight: bold;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}
.g-top {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px dashed #cbd5e1;
    font-size: 8pt;
    color: #64748b;
    text-align: left;
}
.g-top strong { color: #0f172a; font-size: 9pt; }

/* ═══════════════════════════════════════════
   TABEL DATA
═══════════════════════════════════════════ */
.data-tbl { width: 100%; border-collapse: collapse; font-size: 9pt; }
.data-tbl thead tr {
    background: #0f172a;
    color: #fff;
}
.data-tbl thead th {
    padding: 8px 9px;
    text-align: left;
    font-size: 8pt;
    font-weight: bold;
    letter-spacing: 0.5px;
    border-right: 1px solid rgba(255,255,255,0.08);
}
.data-tbl thead th:last-child { border-right: none; }
.data-tbl tbody tr td {
    padding: 7px 9px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    font-size: 9pt;
}
.data-tbl tbody tr:nth-child(even) td { background: #f8fafc; }
.data-tbl tbody tr:last-child td     { border-bottom: none; }
.data-tbl tfoot td {
    padding: 8px 9px;
    font-weight: bold;
    background: #eff6ff;
    border-top: 2px solid #bfdbfe;
    color: #1e3a8a;
    font-size: 9pt;
}

.sla-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 8pt;
    font-weight: bold;
}
.bar-wrap { background: #e2e8f0; height: 5px; border-radius: 3px; overflow: hidden; display: inline-block; vertical-align: middle; }
.bar-fill { height: 5px; border-radius: 3px; }

/* ═══════════════════════════════════════════
   TANDA TANGAN
═══════════════════════════════════════════ */
.ttd-section {
    margin-top: 28px;
    display: table;
    width: 100%;
}
.ttd-box {
    display: table-cell;
    width: 33.3%;
    text-align: center;
    padding: 0 10px;
    vertical-align: top;
}
.ttd-title {
    font-size: 9pt;
    color: #475569;
    margin-bottom: 52px;
}
.ttd-line {
    border-top: 1px solid #334155;
    margin: 0 10px 5px;
}
.ttd-name {
    font-size: 9pt;
    font-weight: bold;
    color: #0f172a;
}
.ttd-role {
    font-size: 8pt;
    color: #64748b;
    margin-top: 2px;
}

/* ═══════════════════════════════════════════
   FOOTER HALAMAN
═══════════════════════════════════════════ */
.page-footer {
    margin-top: 22px;
    padding-top: 8px;
    border-top: 1px solid #cbd5e1;
    display: table;
    width: 100%;
}
.pf-left  { display: table-cell; font-size: 7.5pt; color: #94a3b8; vertical-align: middle; }
.pf-right { display: table-cell; text-align: right; font-size: 7.5pt; color: #94a3b8; vertical-align: middle; }

/* ═══════════════════════════════════════════
   DISTRIBUSI TABLE (kecil)
═══════════════════════════════════════════ */
.dist-tbl { width: 100%; border-collapse: collapse; font-size: 9pt; }
.dist-tbl th {
    background: #0f172a; color: #fff;
    padding: 7px 9px; font-size: 8pt; text-align: left;
}
.dist-tbl td { padding: 6px 9px; border-bottom: 1px solid #f1f5f9; font-size: 9pt; }
.dist-tbl tr:last-child td { border-bottom: none; }
.dist-tbl tr:nth-child(even) td { background: #f8fafc; }
.dot { width: 9px; height: 9px; background: #ccc; display: inline-block; vertical-align: middle; margin-right: 5px; }

/* BEST performer highlight */
.best-row td { background: #fffbeb !important; }
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
    <div class="kop-sub">Divisi Teknologi Informasi &mdash; Sistem Manajemen Layanan IT</div>
  </div>
  <div class="kop-right">
    <div class="kop-right-label">No. Dokumen</div>
    <div class="kop-right-val">RPT-SLA-<?= $tahun.sprintf('%02d',$bulan) ?></div>
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
  <div class="report-main-title">Laporan Service Level Agreement (SLA)</div>
  <div class="report-period">Periode: <?= $nama_bulan[$bulan].' '.$tahun ?></div>
  <div class="report-no">Disiapkan oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?> &nbsp;|&nbsp; Dicetak: <?= date('d F Y, H:i') ?> WIB</div>
</div>

<!-- ══════════════════════════════
     INFO DOKUMEN
══════════════════════════════ -->
<table class="doc-info">
  <tr>
    <td class="di-label">Unit</td>
    <td class="di-val">Divisi Teknologi Informasi</td>
    <td class="di-label">Periode Laporan</td>
    <td class="di-val"><?= $nama_bulan[$bulan].' '.$tahun ?></td>
  </tr>
  <tr>
    <td class="di-label">Jenis Laporan</td>
    <td class="di-val">Service Level Agreement (SLA)</td>
    <td class="di-label">Status Dokumen</td>
    <td class="di-val">Final &mdash; Confidential</td>
  </tr>
  <tr>
    <td class="di-label">Target SLA</td>
    <td class="di-val">Minimal 90% dalam waktu yang ditetapkan</td>
    <td class="di-label">Pencapaian SLA</td>
    <td class="di-val">
      <strong style="color:<?= $ov_color['bar'] ?>;"><?= $sla_pct ?>%</strong>
      &nbsp;&nbsp;
      <span class="sla-badge" style="background:<?= $ov_color['bg'] ?>;color:<?= $ov_color['fg'] ?>;"><?= $ov_color['label'] ?></span>
    </td>
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
  <p><?= $kesimpulan ?></p>
  <p style="margin-top:8px;">
    Laporan ini menyajikan data kinerja layanan IT meliputi total tiket yang masuk, tingkat penyelesaian,
    durasi respon dan penyelesaian, serta analisis SLA per kategori layanan dan per teknisi untuk periode
    <strong><?= $nama_bulan[$bulan].' '.$tahun ?></strong>.
    <?php if ($best_tek && $best_sla > 0): ?>
    Teknisi dengan performa terbaik pada periode ini adalah <strong><?= htmlspecialchars($best_tek) ?></strong>
    dengan pencapaian SLA sebesar <strong><?= $best_sla ?>%</strong>.
    <?php endif; ?>
  </p>
</div>

<!-- ══════════════════════════════
     II. KPI UTAMA
══════════════════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian II</div>
  <div class="sec-title">
    <span class="sec-title-text">Indikator Kinerja Utama (Key Performance Indicator)</span>
  </div>
</div>

<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#1d4ed8;">
        <div class="k-label">Total Tiket Masuk</div>
        <div class="k-val" style="color:#1d4ed8;"><?= $ov['total'] ?? 0 ?></div>
        <div class="k-desc">Seluruh tiket yang masuk pada periode ini</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#16a34a;">
        <div class="k-label">Tiket Selesai</div>
        <div class="k-val" style="color:#16a34a;"><?= $ov['solved'] ?? 0 ?></div>
        <div class="k-desc">Berhasil ditangani dan ditutup</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#0891b2;">
        <div class="k-label">Tiket Masih Aktif</div>
        <div class="k-val" style="color:#0891b2;"><?= ($ov['menunggu'] ?? 0) + ($ov['diproses'] ?? 0) ?></div>
        <div class="k-desc"><?= $ov['menunggu']??0 ?> menunggu &nbsp;/&nbsp; <?= $ov['diproses']??0 ?> sedang diproses</div>
      </div>
    </td>
  </tr>
  <tr>
    <td class="kpi-td" style="padding-top:7px;">
      <div class="kpi-card" style="border-top-color:#dc2626;">
        <div class="k-label">Ditolak / Tidak Bisa</div>
        <div class="k-val" style="color:#dc2626;"><?= ($ov['ditolak'] ?? 0) + ($ov['tidak_bisa'] ?? 0) ?></div>
        <div class="k-desc"><?= $ov['ditolak']??0 ?> ditolak &nbsp;/&nbsp; <?= $ov['tidak_bisa']??0 ?> tidak bisa ditangani</div>
      </div>
    </td>
    <td class="kpi-td" style="padding-top:7px;">
      <div class="kpi-card" style="border-top-color:#d97706;">
        <div class="k-label">Rata-rata Waktu Respon</div>
        <div class="k-val" style="color:#d97706;font-size:16pt;padding-top:4px;"><?= formatDurasi(round($ov['avg_respon'] ?? 0)) ?></div>
        <div class="k-desc">Rata-rata waktu dari submit hingga diproses</div>
      </div>
    </td>
    <td class="kpi-td" style="padding-top:7px;">
      <div class="kpi-card" style="border-top-color:#7c3aed;">
        <div class="k-label">Rata-rata Waktu Selesai</div>
        <div class="k-val" style="color:#7c3aed;font-size:16pt;padding-top:4px;"><?= formatDurasi(round($ov['avg_selesai'] ?? 0)) ?></div>
        <div class="k-desc">Rata-rata waktu dari submit hingga selesai</div>
      </div>
    </td>
  </tr>
</table>

<!-- ══════════════════════════════
     III. PENCAPAIAN SLA
══════════════════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian III</div>
  <div class="sec-title">
    <span class="sec-title-text">Pencapaian SLA &amp; Distribusi Status Tiket</span>
  </div>
</div>

<div class="sla-wrap">
  <!-- Gauge kiri -->
  <div class="sla-left">
    <div class="gauge-box">
      <div style="font-size:7.5pt;letter-spacing:2px;color:#64748b;text-transform:uppercase;margin-bottom:8px;">SLA Achievement Rate</div>
      <div class="g-pct" style="color:<?= $ov_color['bar'] ?>;"><?= $sla_pct ?>%</div>
      <div class="g-sub">
        <?= $ov['sla_met'] ?? 0 ?> dari <?= $ov['solved'] ?? 0 ?> tiket selesai<br>
        diselesaikan dalam target SLA
      </div>
      <div class="g-track">
        <div class="g-fill" style="width:<?= $sla_pct ?>%;background:<?= $ov_color['bar'] ?>;"></div>
      </div>
      <div class="g-hint">Target pencapaian minimal &gt;= 90%</div>
      <span class="g-chip" style="background:<?= $ov_color['bg'] ?>;color:<?= $ov_color['fg'] ?>;"><?= $ov_color['label'] ?></span>
      <?php if ($best_tek && $best_sla > 0): ?>
      <div class="g-top">
        <div style="font-size:7.5pt;color:#94a3b8;letter-spacing:1px;text-transform:uppercase;margin-bottom:3px;">Teknisi Terbaik</div>
        <strong><?= htmlspecialchars($best_tek) ?></strong>
        <span style="color:#94a3b8;font-size:8pt;"> &mdash; <?= $best_sla ?>% SLA</span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Distribusi kanan -->
  <div class="sla-right">
    <table class="dist-tbl">
      <thead>
        <tr>
          <th colspan="2">Status</th>
          <th style="text-align:right;">Jumlah</th>
          <th style="text-align:right;">Persentase</th>
          <th>Proporsi</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $dist = [
          ['Selesai',       $ov['solved']     ?? 0, '#16a34a'],
          ['Diproses',      $ov['diproses']   ?? 0, '#0891b2'],
          ['Menunggu',      $ov['menunggu']   ?? 0, '#d97706'],
          ['Ditolak',       $ov['ditolak']    ?? 0, '#dc2626'],
          ['Tidak Bisa',    $ov['tidak_bisa'] ?? 0, '#7c3aed'],
        ];
        $total_d = max((int)($ov['total'] ?? 0), 1);
        foreach ($dist as [$lbl, $val, $col]):
          $pct_d = round($val / $total_d * 100);
        ?>
        <tr>
          <td style="width:12px;padding-right:0;"><span class="dot" style="background:<?= $col ?>;"></span></td>
          <td style="font-weight:bold;"><?= $lbl ?></td>
          <td style="text-align:right;font-weight:bold;"><?= $val ?></td>
          <td style="text-align:right;"><?= $pct_d ?>%</td>
          <td>
            <div class="bar-wrap" style="width:80px;">
              <div class="bar-fill" style="width:<?= $pct_d ?>%;background:<?= $col ?>;"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2">TOTAL</td>
          <td style="text-align:right;"><?= $ov['total'] ?? 0 ?></td>
          <td style="text-align:right;">100%</td>
          <td>&mdash;</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══════════════════════════════
     IV. SLA PER KATEGORI
══════════════════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian IV</div>
  <div class="sec-title">
    <span class="sec-title-text">Analisis SLA per Kategori Layanan</span>
  </div>
</div>

<table class="data-tbl">
  <thead>
    <tr>
      <th>No.</th>
      <th>Kategori Layanan</th>
      <th style="text-align:center;">Target SLA</th>
      <th style="text-align:center;">Total</th>
      <th style="text-align:center;">Selesai</th>
      <th style="text-align:center;">Dlm. Target</th>
      <th style="text-align:center;">% SLA</th>
      <th style="text-align:center;">Avg. Respon</th>
      <th style="text-align:center;">Avg. Selesai</th>
      <th style="text-align:center;">Aktif</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $no = 0;
    $t_total = 0; $t_solved = 0; $t_met = 0;
    if (empty($sla_kat)):
    ?>
    <tr><td colspan="10" style="text-align:center;color:#aaa;padding:14px;font-style:italic;">Tidak ada data untuk periode ini.</td></tr>
    <?php else: foreach ($sla_kat as $k):
      $no++;
      $k_sla = ($k['solved'] > 0) ? round($k['sla_met'] / $k['solved'] * 100) : 0;
      $kc = slaColor($k_sla);
      $t_total  += $k['total']   ?? 0;
      $t_solved += $k['solved']  ?? 0;
      $t_met    += $k['sla_met'] ?? 0;
    ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $no ?></td>
      <td style="font-weight:bold;"><?= htmlspecialchars($k['nama']) ?></td>
      <td style="text-align:center;">
        <span style="background:#eff6ff;color:#1d4ed8;padding:2px 7px;border-radius:3px;font-size:8pt;font-weight:bold;"><?= $k['sla_jam'] ?> jam</span>
      </td>
      <td style="text-align:center;font-weight:bold;"><?= $k['total'] ?? 0 ?></td>
      <td style="text-align:center;color:#16a34a;font-weight:bold;"><?= $k['selesai'] ?? 0 ?></td>
      <td style="text-align:center;"><?= $k['sla_met'] ?? 0 ?></td>
      <td style="text-align:center;">
        <span class="sla-badge" style="background:<?= $kc['bg'] ?>;color:<?= $kc['fg'] ?>;"><?= $k_sla ?>%</span>
      </td>
      <td style="text-align:center;"><?= formatDurasi(round($k['avg_respon'] ?? 0)) ?></td>
      <td style="text-align:center;"><?= formatDurasi(round($k['avg_selesai'] ?? 0)) ?></td>
      <td style="text-align:center;">
        <?php if ($k['aktif'] > 0): ?>
        <span style="background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:3px;font-size:8pt;font-weight:bold;"><?= $k['aktif'] ?></span>
        <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
  <?php if (!empty($sla_kat)):
    $total_pct2 = ($t_solved > 0) ? round($t_met / $t_solved * 100) : 0;
    $tc3 = slaColor($total_pct2);
  ?>
  <tfoot>
    <tr>
      <td colspan="2">TOTAL KESELURUHAN</td>
      <td style="text-align:center;">—</td>
      <td style="text-align:center;"><?= $t_total ?></td>
      <td style="text-align:center;color:#16a34a;"><?= $t_solved ?></td>
      <td style="text-align:center;"><?= $t_met ?></td>
      <td style="text-align:center;">
        <span class="sla-badge" style="background:<?= $tc3['bg'] ?>;color:<?= $tc3['fg'] ?>;"><?= $total_pct2 ?>%</span>
      </td>
      <td style="text-align:center;"><?= formatDurasi(round($ov['avg_respon'] ?? 0)) ?></td>
      <td style="text-align:center;"><?= formatDurasi(round($ov['avg_selesai'] ?? 0)) ?></td>
      <td style="text-align:center;">—</td>
    </tr>
  </tfoot>
  <?php endif; ?>
</table>

<!-- ══════════════════════════════
     V. KINERJA TEKNISI
══════════════════════════════ -->
<div class="sec">
  <div class="sec-num">Bagian V</div>
  <div class="sec-title">
    <span class="sec-title-text">Kinerja Teknisi IT</span>
  </div>
</div>

<table class="data-tbl">
  <thead>
    <tr>
      <th style="text-align:center;">No.</th>
      <th>Nama Teknisi</th>
      <th style="text-align:center;">Total</th>
      <th style="text-align:center;">Selesai</th>
      <th style="text-align:center;">Ditolak</th>
      <th style="text-align:center;">Tdk Bisa</th>
      <th style="text-align:center;">% Selesai</th>
      <th style="text-align:center;">% SLA Met</th>
      <th style="text-align:center;">Avg. Respon</th>
      <th style="text-align:center;">Avg. Selesai</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if (empty($sla_tek)):
    ?>
    <tr><td colspan="10" style="text-align:center;color:#aaa;padding:14px;font-style:italic;">Tidak ada data teknisi untuk periode ini.</td></tr>
    <?php else:
      $rank = 0;
      foreach ($sla_tek as $tek):
        $rank++;
        $tek_sla  = ($tek['selesai'] > 0) ? round($tek['sla_met'] / $tek['selesai'] * 100) : 0;
        $tc4      = slaColor($tek_sla);
        $pct_done = ($tek['total']  > 0) ? round($tek['selesai'] / $tek['total'] * 100) : 0;
        $is_best  = ($best_tek !== null && $best_sla > 0 && $tek['nama'] === $best_tek);
    ?>
    <tr <?= $is_best ? 'class="best-row"' : '' ?>>
      <td style="text-align:center;color:#94a3b8;"><?= $rank ?></td>
      <td style="font-weight:bold;">
        <?php if ($is_best && $best_sla > 0): ?>
        <span style="color:#d97706;font-weight:bold;">TOP</span>&nbsp;
        <?php endif; ?>
        <?= htmlspecialchars($tek['nama']) ?>
        <?php if ($is_best && $best_sla > 0): ?>
        <span style="font-size:7.5pt;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:2px;margin-left:4px;font-weight:bold;">TERBAIK</span>
        <?php endif; ?>
      </td>
      <td style="text-align:center;font-weight:bold;"><?= $tek['total'] ?></td>
      <td style="text-align:center;color:#16a34a;font-weight:bold;"><?= $tek['selesai'] ?></td>
      <td style="text-align:center;color:#dc2626;"><?= $tek['ditolak'] ?? 0 ?></td>
      <td style="text-align:center;color:#7c3aed;"><?= $tek['tdk_bisa'] ?? 0 ?></td>
      <td style="text-align:center;">
        <div class="bar-wrap" style="width:44px;">
          <div class="bar-fill" style="width:<?= $pct_done ?>%;background:#0891b2;"></div>
        </div>
        <span style="font-size:8pt;margin-left:3px;"><?= $pct_done ?>%</span>
      </td>
      <td style="text-align:center;">
        <span class="sla-badge" style="background:<?= $tc4['bg'] ?>;color:<?= $tc4['fg'] ?>;"><?= $tek_sla ?>%</span>
      </td>
      <td style="text-align:center;"><?= formatDurasi(round($tek['avg_respon'] ?? 0)) ?></td>
      <td style="text-align:center;"><?= formatDurasi(round($tek['avg_selesai'] ?? 0)) ?></td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<!-- ══════════════════════════════
     KETERANGAN WARNA
══════════════════════════════ -->
<table style="width:100%;border-collapse:collapse;margin-top:6px;border:1px solid #e2e8f0;font-size:8.5pt;">
  <tr>
    <td style="padding:6px 10px;background:#f8fafc;font-weight:bold;color:#475569;border-right:1px solid #e2e8f0;width:18%;">Keterangan Badge</td>
    <td style="padding:6px 12px;border-right:1px solid #e2e8f0;">
      <span class="sla-badge" style="background:#dcfce7;color:#15803d;">BAIK</span>&nbsp; SLA &gt;= 90%
    </td>
    <td style="padding:6px 12px;border-right:1px solid #e2e8f0;">
      <span class="sla-badge" style="background:#fef9c3;color:#a16207;">CUKUP</span>&nbsp; SLA 70%&ndash;89%
    </td>
    <td style="padding:6px 12px;">
      <span class="sla-badge" style="background:#fee2e2;color:#b91c1c;">KURANG</span>&nbsp; SLA &lt; 70%
    </td>
  </tr>
</table>

<!-- ══════════════════════════════
     TANDA TANGAN
══════════════════════════════ -->
<div style="margin-top:30px;">
  <div style="font-size:9pt;color:#475569;margin-bottom:4px;">Laporan ini telah diperiksa dan disetujui oleh:</div>
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
    FixSmart Helpdesk &mdash; Laporan SLA &mdash; <?= $nama_bulan[$bulan].' '.$tahun ?><br>
    Dokumen ini bersifat rahasia dan hanya untuk keperluan internal manajemen.
  </div>
  <div class="pf-right">
    No. Dok: RPT-SLA-<?= $tahun.sprintf('%02d',$bulan) ?> &nbsp;|&nbsp; Halaman 1 dari 1<br>
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
$dompdf->setPaper('A4', 'portrait');   // ← A4 Portrait
$dompdf->render();

$filename = 'Laporan_SLA_' . $nama_bulan[$bulan] . '_' . $tahun . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;