<?php
// pages/cetak_laporan_cuti.php — PDF Laporan Cuti Semua Karyawan (HRD/Admin)
session_start();
require_once '../config.php';
requireLogin();

if (!in_array($_SESSION['user_role'], ['admin','hrd'])) {
    die('Akses ditolak.');
}

$dompdf_path = __DIR__ . '/../dompdf/autoload.inc.php';
if (!file_exists($dompdf_path)) { die('dompdf tidak ditemukan.'); }
require_once $dompdf_path;

use Dompdf\Dompdf;
use Dompdf\Options;

// ── Parameter (sama persis dengan laporan_cuti.php) ───────
$f_tahun  = (int)($_GET['tahun']   ?? date('Y'));
$f_status = $_GET['status']  ?? '';
$f_divisi = $_GET['divisi']  ?? '';
$f_jenis  = (int)($_GET['jenis']   ?? 0);
$f_user   = (int)($_GET['user_id'] ?? 0);
$f_bulan  = (int)($_GET['bulan']   ?? 0);

// ── Query ─────────────────────────────────────────────────
$where  = ["YEAR(pc.tgl_mulai) = ?"];
$params = [$f_tahun];
if ($f_status)  { $where[] = "pc.status = ?";          $params[] = $f_status; }
if ($f_divisi)  { $where[] = "u.divisi = ?";            $params[] = $f_divisi; }
if ($f_jenis)   { $where[] = "pc.jenis_cuti_id = ?";    $params[] = $f_jenis; }
if ($f_user)    { $where[] = "pc.user_id = ?";           $params[] = $f_user; }
if ($f_bulan)   { $where[] = "MONTH(pc.tgl_mulai) = ?"; $params[] = $f_bulan; }
$wsql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT pc.*,
           jc.kode jenis_kode, jc.nama jenis_nama,
           u.nama  pemohon, u.divisi,
           ua1.nama approver1_nama,
           ua2.nama approver2_nama,
           ud.nama  delegasi_nama,
           GROUP_CONCAT(ct.tanggal ORDER BY ct.tanggal SEPARATOR ',') tgl_list
    FROM pengajuan_cuti pc
    JOIN users u ON u.id = pc.user_id
    LEFT JOIN master_jenis_cuti jc ON jc.id = pc.jenis_cuti_id
    LEFT JOIN users ua1 ON ua1.id = pc.approver1_id
    LEFT JOIN users ua2 ON ua2.id = pc.approver2_id
    LEFT JOIN users ud  ON ud.id  = pc.delegasi_id
    LEFT JOIN cuti_tanggal ct ON ct.pengajuan_id = pc.id
    WHERE $wsql
    GROUP BY pc.id
    ORDER BY u.divisi, u.nama, pc.tgl_mulai ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Rekap jatah per karyawan ──────────────────────────────
$rekap_stmt = $pdo->prepare("
    SELECT u.id, u.nama, u.divisi,
           COALESCE(SUM(jt.kuota),0)    total_kuota,
           COALESCE(SUM(jt.terpakai),0) total_terpakai,
           COALESCE(SUM(jt.sisa),0)     total_sisa
    FROM users u
    LEFT JOIN jatah_cuti jt ON jt.user_id=u.id AND jt.tahun=?
    WHERE u.status='aktif'
    " . ($f_divisi ? "AND u.divisi=?" : "") . "
    GROUP BY u.id
    ORDER BY u.divisi, u.nama
");
$rekap_params = [$f_tahun];
if ($f_divisi) $rekap_params[] = $f_divisi;
$rekap_stmt->execute($rekap_params);
$rekap_rows = $rekap_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Statistik ─────────────────────────────────────────────
$total = count($rows);
$tot_disetujui = $tot_menunggu = $tot_ditolak = $tot_hari = 0;
$by_divisi = []; $by_jenis = [];
foreach ($rows as $r) {
    if ($r['status']==='disetujui') { $tot_disetujui++; $tot_hari += (int)$r['jumlah_hari']; }
    if ($r['status']==='menunggu')  $tot_menunggu++;
    if ($r['status']==='ditolak')   $tot_ditolak++;
    $div = $r['divisi'] ?: 'Tanpa Divisi';
    $by_divisi[$div] = ($by_divisi[$div] ?? 0) + 1;
    $jn  = $r['jenis_kode'] ?: '-';
    $by_jenis[$jn]  = ($by_jenis[$jn]  ?? 0) + (int)$r['jumlah_hari'];
}
arsort($by_divisi); arsort($by_jenis);

// ── Helpers ───────────────────────────────────────────────
function fmtD(?string $d): string {
    if (!$d || $d==='0000-00-00') return '-';
    return date('d/m/Y', strtotime($d));
}
function fmtDLong(?string $d): string {
    if (!$d || $d==='0000-00-00') return '-';
    $bln=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return date('j',strtotime($d)).' '.$bln[(int)date('n',strtotime($d))].' '.date('Y',strtotime($d));
}
function stsLabel(string $s): array {
    return match($s) {
        'disetujui'  => ['Disetujui',  '#dcfce7','#15803d'],
        'menunggu'   => ['Menunggu',   '#fef9c3','#a16207'],
        'ditolak'    => ['Ditolak',    '#fee2e2','#b91c1c'],
        'dibatalkan' => ['Dibatalkan', '#f3f4f6','#6b7280'],
        default      => [ucfirst($s),  '#f3f4f6','#374151'],
    };
}
function apprLabel(string $s): array {
    return match($s) {
        'disetujui' => ['ACC',   '#dcfce7','#15803d'],
        'ditolak'   => ['TOLAK', '#fee2e2','#b91c1c'],
        default     => ['TUNGGU','#fef9c3','#a16207'],
    };
}

// ── Label filter untuk judul ──────────────────────────────
$bln_nm = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$label_periode = 'Tahun '.$f_tahun;
if ($f_bulan) $label_periode = $bln_nm[$f_bulan].' '.$f_tahun;
$label_divisi  = $f_divisi ?: 'Semua Divisi';
$label_status  = $f_status ? ucfirst(str_replace('_',' ',$f_status)) : 'Semua Status';
$label_jenis   = '-';
if ($f_jenis) {
    $jn = $pdo->prepare("SELECT kode,nama FROM master_jenis_cuti WHERE id=?");
    $jn->execute([$f_jenis]);
    $jn = $jn->fetch();
    $label_jenis = $jn ? $jn['kode'].' - '.$jn['nama'] : '-';
} else { $label_jenis = 'Semua Jenis'; }

// Nama karyawan jika filter per orang
$label_karyawan = 'Semua Karyawan';
if ($f_user) {
    $uk = $pdo->prepare("SELECT nama FROM users WHERE id=?");
    $uk->execute([$f_user]);
    $uk = $uk->fetchColumn();
    if ($uk) $label_karyawan = $uk;
}

$no_dok  = 'LPC-'.date('Ymd').'-'.strtoupper(substr(md5($f_tahun.$f_bulan.$f_divisi.$f_status),0,4));
$sla_pct = $total > 0 ? round($tot_disetujui/$total*100) : 0;
$avg_hari= $tot_disetujui > 0 ? round($tot_hari/$tot_disetujui,1) : 0;

// ── Kelompokkan data per divisi ───────────────────────────
$by_divisi_rows = [];
foreach ($rows as $r) {
    $d = $r['divisi'] ?: 'Tanpa Divisi';
    $by_divisi_rows[$d][] = $r;
}

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
    font-size: 8.5pt;
    color: #1a1a2e;
    background: #fff;
    line-height: 1.5;
    margin: 28px 36px 28px 36px;
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
.kop-rule2 { height:1px; background:#cbd5e1; margin-bottom:12px; }

/* ── JUDUL ── */
.report-title-block { text-align:center; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #e2e8f0; }
.report-label      { font-size:7pt; letter-spacing:3px; text-transform:uppercase; color:#64748b; margin-bottom:4px; }
.report-main-title { font-size:14pt; font-weight:bold; color:#0f172a; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.report-sub        { font-size:10pt; color:#1d4ed8; font-weight:bold; }
.report-no         { font-size:7.5pt; color:#94a3b8; margin-top:4px; }

/* ── INFO DOKUMEN ── */
.doc-info { width:100%; border-collapse:collapse; margin-bottom:12px; border:1px solid #e2e8f0; }
.doc-info td { padding:5px 9px; font-size:8.5pt; border:1px solid #e2e8f0; vertical-align:top; }
.di-label { background:#f8fafc; color:#475569; font-weight:bold; width:18%; }
.di-val   { color:#1e293b; width:32%; }

/* ── SECTION ── */
.sec { margin-top:14px; margin-bottom:7px; page-break-after:avoid; }
.sec-num  { font-size:7pt; letter-spacing:2px; text-transform:uppercase; color:#94a3b8; margin-bottom:2px; }
.sec-title { font-size:10pt; font-weight:bold; color:#0f172a; border-bottom:2px solid #1d4ed8; padding-bottom:4px; display:table; width:100%; }
.sec-title-l { display:table-cell; }
.sec-title-r { display:table-cell; text-align:right; font-size:7.5pt; font-weight:normal; color:#94a3b8; vertical-align:bottom; }

/* ── KPI CARDS ── */
.kpi-table { width:100%; border-collapse:separate; border-spacing:4px; margin:0 -4px; }
.kpi-td    { vertical-align:top; }
.kpi-card  { border:1px solid #e2e8f0; border-top:3px solid #1d4ed8; border-radius:0 0 4px 4px; padding:7px 9px 6px; background:#fff; }
.k-label   { font-size:6.5pt; letter-spacing:1px; text-transform:uppercase; color:#64748b; margin-bottom:3px; }
.k-val     { font-size:16pt; font-weight:bold; line-height:1; margin-bottom:2px; }
.k-desc    { font-size:6.5pt; color:#94a3b8; line-height:1.4; }

/* ── DISTRIBUSI ── */
.dist-tbl { width:100%; border-collapse:collapse; font-size:8pt; }
.dist-tbl th { background:#0f172a; color:#fff; padding:6px 8px; font-size:7pt; }
.dist-tbl td { padding:5px 8px; border-bottom:1px solid #f1f5f9; }
.dist-tbl tr:nth-child(even) td { background:#f8fafc; }
.dist-tbl tfoot td { background:#eff6ff; border-top:2px solid #bfdbfe; font-weight:bold; color:#1e3a8a; }
.bar-wrap { background:#e2e8f0; height:5px; border-radius:3px; overflow:hidden; display:inline-block; vertical-align:middle; }
.bar-fill { height:5px; border-radius:3px; }

/* ── TABEL DETAIL ── */
.data-tbl { width:100%; border-collapse:collapse; font-size:7pt; page-break-inside:auto; }
.data-tbl thead tr { background:#0f172a; color:#fff; }
.data-tbl thead th { padding:5px 5px; text-align:left; font-size:6.5pt; font-weight:bold; border-right:1px solid rgba(255,255,255,.08); }
.data-tbl thead th:last-child { border-right:none; }
.data-tbl tbody td { padding:4px 5px; border-bottom:1px solid #f1f5f9; vertical-align:top; font-size:6.5pt; }
.data-tbl tbody tr:nth-child(even) td { background:#f8fafc; }
.data-tbl tfoot td { padding:5px 5px; font-weight:bold; background:#eff6ff; border-top:2px solid #bfdbfe; color:#1e3a8a; font-size:7.5pt; }

/* ── DIVISI HEADER DALAM TABEL ── */
.div-row td { background:#e8f0fe !important; padding:5px 8px; font-size:8pt; font-weight:700; color:#1d4ed8; border-bottom:1px solid #bfdbfe; letter-spacing:.3px; }

/* ── REKAP JATAH ── */
.rekap-tbl { width:100%; border-collapse:collapse; font-size:7.5pt; }
.rekap-tbl th { background:#0f172a; color:#fff; padding:6px 7px; font-size:7pt; text-align:left; }
.rekap-tbl td { padding:5px 7px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.rekap-tbl tr:nth-child(even) td { background:#f8fafc; }
.rekap-tbl tfoot td { background:#eff6ff; border-top:2px solid #bfdbfe; font-weight:bold; color:#1e3a8a; font-size:7.5pt; }
.rekap-div-row td { background:#f0fdf4 !important; padding:4px 7px; font-size:7.5pt; font-weight:700; color:#065f46; border-bottom:1px solid #bbf7d0; }

.badge { display:inline-block; padding:2px 6px; border-radius:3px; font-size:7pt; font-weight:bold; white-space:nowrap; }

/* ── TTD ── */
.ttd-section { margin-top:20px; display:table; width:100%; }
.ttd-box  { display:table-cell; width:33.3%; text-align:center; padding:0 10px; vertical-align:top; }
.ttd-title{ font-size:8.5pt; color:#475569; margin-bottom:44px; }
.ttd-line { border-top:1px solid #334155; margin:0 12px 4px; }
.ttd-name { font-size:8.5pt; font-weight:bold; color:#0f172a; }
.ttd-role { font-size:7.5pt; color:#64748b; margin-top:2px; }

/* ── FOOTER ── */
.page-footer { margin-top:14px; padding-top:7px; border-top:1px solid #cbd5e1; display:table; width:100%; }
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
    <div class="kop-sub">Divisi SDM &amp; Kepegawaian &mdash; Sistem Manajemen Cuti Karyawan</div>
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
  <div class="report-label">Dokumen Resmi &mdash; Internal Use Only</div>
  <div class="report-main-title">LAPORAN REKAPITULASI CUTI KARYAWAN</div>
  <div class="report-sub">
    Periode: <?= $label_periode ?>
    &mdash; <?= htmlspecialchars($label_divisi) ?>
    &mdash; <?= htmlspecialchars($label_jenis) ?>
  </div>
  <div class="report-no">
    Disiapkan oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
    &nbsp;|&nbsp; Dicetak: <?= date('d F Y, H:i') ?> WIB
    &nbsp;|&nbsp; Karyawan: <?= htmlspecialchars($label_karyawan) ?>
    &nbsp;|&nbsp; Status: <?= htmlspecialchars($label_status) ?>
  </div>
</div>

<!-- ══ INFO DOKUMEN ══ -->
<table class="doc-info">
  <tr>
    <td class="di-label">Unit Penyusun</td>
    <td class="di-val">Divisi SDM / HRD</td>
    <td class="di-label">Periode Laporan</td>
    <td class="di-val"><?= $label_periode ?></td>
  </tr>
  <tr>
    <td class="di-label">Filter Divisi</td>
    <td class="di-val"><?= htmlspecialchars($label_divisi) ?></td>
    <td class="di-label">Filter Jenis Cuti</td>
    <td class="di-val"><?= htmlspecialchars($label_jenis) ?></td>
  </tr>
  <tr>
    <td class="di-label">Filter Status</td>
    <td class="di-val"><?= htmlspecialchars($label_status) ?></td>
    <td class="di-label">Filter Karyawan</td>
    <td class="di-val"><?= htmlspecialchars($label_karyawan) ?></td>
  </tr>
  <tr>
    <td class="di-label">Total Pengajuan</td>
    <td class="di-val"><strong><?= $total ?> pengajuan</strong></td>
    <td class="di-label">Status Dokumen</td>
    <td class="di-val">Final &mdash; Confidential</td>
  </tr>
</table>

<!-- ══ I. KPI ══ -->
<div class="sec">
  <div class="sec-num">Bagian I</div>
  <div class="sec-title"><span class="sec-title-l">Indikator Kinerja Cuti</span></div>
</div>
<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#1d4ed8;">
        <div class="k-label">Total Pengajuan</div>
        <div class="k-val" style="color:#1d4ed8;"><?= $total ?></div>
        <div class="k-desc">Semua status periode ini</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#16a34a;">
        <div class="k-label">Disetujui</div>
        <div class="k-val" style="color:#16a34a;"><?= $tot_disetujui ?></div>
        <div class="k-desc"><?= $sla_pct ?>% dari total pengajuan</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#d97706;">
        <div class="k-label">Menunggu</div>
        <div class="k-val" style="color:#d97706;"><?= $tot_menunggu ?></div>
        <div class="k-desc">Perlu ditindaklanjuti</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#dc2626;">
        <div class="k-label">Ditolak</div>
        <div class="k-val" style="color:#dc2626;"><?= $tot_ditolak ?></div>
        <div class="k-desc"><?= $total>0 ? round($tot_ditolak/$total*100) : 0 ?>% dari total</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#6366f1;">
        <div class="k-label">Total Hari Disetujui</div>
        <div class="k-val" style="color:#6366f1;"><?= $tot_hari ?></div>
        <div class="k-desc">Hari kerja efektif cuti</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#0891b2;">
        <div class="k-label">Rata-rata / Pengajuan</div>
        <div class="k-val" style="color:#0891b2;font-size:12pt;padding-top:3px;"><?= $avg_hari ?></div>
        <div class="k-desc">Hari per pengajuan ACC</div>
      </div>
    </td>
  </tr>
</table>

<!-- ══ II. DISTRIBUSI ══ -->
<?php if ($by_divisi || $by_jenis): ?>
<div class="sec">
  <div class="sec-num">Bagian II</div>
  <div class="sec-title"><span class="sec-title-l">Distribusi Pengajuan</span></div>
</div>
<table style="width:100%;border-collapse:separate;border-spacing:8px 0;">
  <tr>
    <!-- Per Divisi -->
    <?php if ($by_divisi): ?>
    <td style="vertical-align:top;width:50%;">
      <table class="dist-tbl">
        <thead>
          <tr>
            <th>Divisi</th>
            <th style="text-align:right;width:40px;">Jml</th>
            <th style="text-align:right;width:35px;">%</th>
            <th style="width:80px;">Proporsi</th>
          </tr>
        </thead>
        <tbody>
          <?php $tot_d = max($total,1); foreach ($by_divisi as $div=>$cnt):
            $pp = round($cnt/$tot_d*100);
          ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($div) ?></td>
            <td style="text-align:right;font-weight:bold;"><?= $cnt ?></td>
            <td style="text-align:right;"><?= $pp ?>%</td>
            <td>
              <div class="bar-wrap" style="width:70px;">
                <div class="bar-fill" style="width:<?= $pp ?>%;background:#1d4ed8;"></div>
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
            <td>-</td>
          </tr>
        </tfoot>
      </table>
    </td>
    <?php endif; ?>

    <!-- Per Jenis -->
    <?php if ($by_jenis): ?>
    <td style="vertical-align:top;width:50%;">
      <table class="dist-tbl">
        <thead>
          <tr>
            <th>Jenis Cuti</th>
            <th style="text-align:right;width:50px;">Hari</th>
            <th style="text-align:right;width:35px;">%</th>
            <th style="width:80px;">Proporsi</th>
          </tr>
        </thead>
        <tbody>
          <?php $tot_h = max($tot_hari,1); foreach ($by_jenis as $kode=>$hari):
            $pp = round($hari/$tot_h*100);
          ?>
          <tr>
            <td style="font-weight:600;">
              <span style="font-family:monospace;background:#f1f5f9;padding:1px 4px;border-radius:2px;font-size:6.5pt;"><?= htmlspecialchars($kode) ?></span>
            </td>
            <td style="text-align:right;font-weight:bold;"><?= $hari ?></td>
            <td style="text-align:right;"><?= $pp ?>%</td>
            <td>
              <div class="bar-wrap" style="width:70px;">
                <div class="bar-fill" style="width:<?= $pp ?>%;background:#6366f1;"></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td>TOTAL</td>
            <td style="text-align:right;"><?= $tot_hari ?></td>
            <td style="text-align:right;">100%</td>
            <td>-</td>
          </tr>
        </tfoot>
      </table>
    </td>
    <?php endif; ?>
  </tr>
</table>
<?php endif; ?>

<!-- ══ III. DETAIL PENGAJUAN ══ -->
<div class="sec">
  <div class="sec-num">Bagian III</div>
  <div class="sec-title">
    <span class="sec-title-l">Detail Pengajuan Cuti</span>
    <span class="sec-title-r">Diurutkan per Divisi &amp; Nama &nbsp;|&nbsp; Total: <?= $total ?> pengajuan</span>
  </div>
</div>

<?php if (empty($rows)): ?>
<div style="text-align:center;color:#94a3b8;padding:18px;border:1px dashed #e2e8f0;border-radius:4px;font-style:italic;">
  Tidak ada data pengajuan cuti untuk filter yang dipilih.
</div>
<?php else: ?>

<table class="data-tbl">
  <thead>
    <tr>
      <th style="width:16px;">#</th>
      <th style="width:90px;">Karyawan</th>
      <th style="width:48px;">Jenis</th>
      <th style="width:76px;">Tgl Cuti</th>
      <th style="width:22px;text-align:center;">Hr</th>
      <th style="width:80px;">Tanggal Dipilih</th>
      <th style="width:80px;">Keperluan</th>
      <th style="width:55px;">Delegasi</th>
      <th style="width:42px;text-align:center;">Status</th>
      <th style="width:76px;">Approval</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $no  = 0;
    $prev_div_tbl = null;
    // Data sudah diurutkan divisi + nama dari query
    foreach ($rows as $r):
        $no++;
        $cur_div = $r['divisi'] ?: 'Tanpa Divisi';
        $tgls    = $r['tgl_list'] ? explode(',',$r['tgl_list']) : [];
        [$slbl,$sbg,$sfg] = stsLabel($r['status']);
        [$a1lbl,$a1bg,$a1fg] = apprLabel($r['status_approver1']);
        $a2_en = $r['status_approver1']==='disetujui' || $r['approver1_id']===null;
        [$a2lbl,$a2bg,$a2fg] = $a2_en ? apprLabel($r['status_approver2']) : ['-','#f3f4f6','#94a3b8'];
    ?>
    <?php if ($prev_div_tbl !== $cur_div): $prev_div_tbl = $cur_div; ?>
    <tr class="div-row">
      <td colspan="10">
        <?= htmlspecialchars($cur_div) ?>
        <span style="font-weight:400;color:#6b7280;font-size:7pt;margin-left:6px;"><?= count($by_divisi_rows[$cur_div] ?? []) ?> pengajuan</span>
      </td>
    </tr>
    <?php endif; ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $no ?></td>
      <td>
        <div style="font-weight:700;font-size:7pt;color:#1e293b;"><?= htmlspecialchars(mb_strimwidth($r['pemohon'],0,24,'...')) ?></div>
        <div style="font-size:6pt;color:#94a3b8;"><?= fmtD($r['created_at']) ?></div>
      </td>
      <td>
        <span style="font-family:monospace;background:#f1f5f9;padding:1px 4px;border-radius:2px;font-size:6.5pt;"><?= htmlspecialchars($r['jenis_kode']??'') ?></span>
        <div style="font-size:6pt;color:#94a3b8;margin-top:1px;"><?= htmlspecialchars(mb_strimwidth($r['jenis_nama']??'',0,16,'...')) ?></div>
      </td>
      <td style="font-size:7pt;white-space:nowrap;">
        <?= fmtD($r['tgl_mulai']) ?>
        <?php if ($r['tgl_mulai']!==$r['tgl_selesai']): ?>
        <br><span style="color:#94a3b8;">s.d.</span> <?= fmtD($r['tgl_selesai']) ?>
        <?php endif; ?>
      </td>
      <td style="text-align:center;font-weight:bold;font-size:10pt;color:#1d4ed8;">
        <?= $r['jumlah_hari'] ?>
        <div style="font-size:6pt;font-weight:normal;color:#94a3b8;">hari</div>
      </td>
      <td style="font-size:6pt;">
        <?php
        $chips = array_map(fn($t)=>date('d/m',strtotime($t)), $tgls);
        echo implode(', ', array_slice($chips,0,6));
        if (count($chips)>6) echo ' +'.( count($chips)-6);
        ?>
      </td>
      <td style="font-size:6.5pt;color:#374151;">
        <?= htmlspecialchars(mb_strimwidth($r['keperluan']??'-',0,55,'...')) ?>
        <?php if ($r['delegasi_nama']): ?>
        <div style="font-size:6pt;color:#94a3b8;margin-top:1px;">Delegasi: <?= htmlspecialchars($r['delegasi_nama']) ?></div>
        <?php endif; ?>
      </td>
      <td style="font-size:6.5pt;color:#475569;">
        <?= htmlspecialchars(mb_strimwidth($r['delegasi_nama']??'-',0,18,'...')) ?>
      </td>
      <td style="text-align:center;">
        <span class="badge" style="background:<?= $sbg ?>;color:<?= $sfg ?>;"><?= $slbl ?></span>
      </td>
      <td style="font-size:6.5pt;">
        <div style="margin-bottom:2px;">
          <span class="badge" style="background:<?= $a1bg ?>;color:<?= $a1fg ?>;"><?= $a1lbl ?></span>
          <span style="color:#94a3b8;margin-left:2px;"><?= htmlspecialchars(mb_strimwidth(explode(' ',$r['approver1_nama']??'Atasan')[0],0,12,'...')) ?></span>
        </div>
        <?php if ($r['catatan_approver1'] && $r['status_approver1']==='ditolak'): ?>
        <div style="font-style:italic;color:#dc2626;font-size:6pt;margin-bottom:2px;">"<?= htmlspecialchars(mb_strimwidth($r['catatan_approver1'],0,30,'...')) ?>"</div>
        <?php endif; ?>
        <div>
          <span class="badge" style="background:<?= $a2bg ?>;color:<?= $a2fg ?>;"><?= $a2lbl ?></span>
          <span style="color:#94a3b8;margin-left:2px;"><?= $a2_en ? htmlspecialchars(mb_strimwidth(explode(' ',$r['approver2_nama']??'HRD')[0],0,12,'...')) : 'HRD' ?></span>
        </div>
        <?php if ($r['catatan_approver2'] && $r['status_approver2']==='ditolak'): ?>
        <div style="font-style:italic;color:#dc2626;font-size:6pt;">"<?= htmlspecialchars(mb_strimwidth($r['catatan_approver2'],0,30,'...')) ?>"</div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4">TOTAL KESELURUHAN</td>
      <td style="text-align:center;"><?= $tot_hari ?> hr</td>
      <td colspan="3"><?= $total ?> pengajuan &nbsp;|&nbsp; <?= $tot_disetujui ?> disetujui &nbsp;/&nbsp; <?= $tot_menunggu ?> menunggu &nbsp;/&nbsp; <?= $tot_ditolak ?> ditolak</td>
      <td colspan="2">Avg: <?= $avg_hari ?> hr/pengajuan</td>
    </tr>
  </tfoot>
</table>
<?php endif; ?>

<!-- ══ IV. REKAP JATAH PER KARYAWAN ══ -->
<?php if ($rekap_rows): ?>
<div class="sec" style="page-break-before:auto;">
  <div class="sec-num">Bagian IV</div>
  <div class="sec-title">
    <span class="sec-title-l">Rekap Saldo Jatah Cuti Karyawan</span>
    <span class="sec-title-r">Tahun <?= $f_tahun ?> &nbsp;|&nbsp; <?= count($rekap_rows) ?> karyawan</span>
  </div>
</div>
<table class="rekap-tbl">
  <thead>
    <tr>
      <th style="width:20px;">#</th>
      <th>Nama Karyawan</th>
      <th>Divisi</th>
      <th style="text-align:center;width:55px;">Kuota</th>
      <th style="text-align:center;width:60px;">Terpakai</th>
      <th style="text-align:center;width:50px;">Sisa</th>
      <th style="text-align:right;width:35px;">%</th>
      <th style="width:90px;">Penggunaan</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $prev_div_rek = null;
    $tot_kuota_all = $tot_terpakai_all = $tot_sisa_all = 0;
    foreach ($rekap_rows as $i=>$rk):
        $tot_kuota_all    += $rk['total_kuota'];
        $tot_terpakai_all += $rk['total_terpakai'];
        $tot_sisa_all     += $rk['total_sisa'];
        $pct     = $rk['total_kuota']>0 ? round($rk['total_terpakai']/$rk['total_kuota']*100) : 0;
        $bar_col = $pct>=80?'#dc2626':($pct>=50?'#d97706':'#16a34a');
        $sisa_col= $rk['total_sisa']>3?'#16a34a':($rk['total_sisa']>0?'#d97706':'#dc2626');
        $cur_div_r = $rk['divisi'] ?: 'Tanpa Divisi';
    ?>
    <?php if ($prev_div_rek !== $cur_div_r): $prev_div_rek=$cur_div_r; ?>
    <tr class="rekap-div-row">
      <td colspan="8"><?= htmlspecialchars($cur_div_r) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;font-size:7pt;"><?= $i+1 ?></td>
      <td style="font-weight:700;font-size:7.5pt;"><?= htmlspecialchars($rk['nama']) ?></td>
      <td style="font-size:7pt;color:#64748b;"><?= htmlspecialchars($rk['divisi']??'-') ?></td>
      <td style="text-align:center;font-weight:700;"><?= $rk['total_kuota'] ?></td>
      <td style="text-align:center;font-weight:700;color:<?= $rk['total_terpakai']>0?'#dc2626':'#94a3b8' ?>;"><?= $rk['total_terpakai'] ?></td>
      <td style="text-align:center;">
        <strong style="font-size:9pt;color:<?= $sisa_col ?>;"><?= $rk['total_sisa'] ?></strong>
      </td>
      <td style="text-align:right;font-weight:700;font-size:7pt;color:<?= $bar_col ?>;"><?= $rk['total_kuota']>0 ? $pct.'%' : '-' ?></td>
      <td>
        <?php if ($rk['total_kuota']>0): ?>
        <div class="bar-wrap" style="width:80px;">
          <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $bar_col ?>;"></div>
        </div>
        <?php else: ?>
        <span style="font-size:6.5pt;color:#94a3b8;font-style:italic;">Belum diinisialisasi</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3">TOTAL</td>
      <td style="text-align:center;"><?= $tot_kuota_all ?></td>
      <td style="text-align:center;color:#dc2626;"><?= $tot_terpakai_all ?></td>
      <td style="text-align:center;color:#16a34a;"><?= $tot_sisa_all ?></td>
      <td style="text-align:right;"><?= $tot_kuota_all>0?round($tot_terpakai_all/$tot_kuota_all*100).'%':'-' ?></td>
      <td>-</td>
    </tr>
  </tfoot>
</table>
<?php endif; ?>

<!-- ══ KETERANGAN ══ -->
<table style="width:100%;border-collapse:collapse;margin-top:8px;border:1px solid #e2e8f0;font-size:7.5pt;">
  <tr>
    <td style="padding:5px 9px;background:#f8fafc;font-weight:bold;color:#475569;border-right:1px solid #e2e8f0;width:12%;">Keterangan</td>
    <td style="padding:5px 10px;border-right:1px solid #e2e8f0;">
      <span class="badge" style="background:#dcfce7;color:#15803d;">Disetujui</span> Cuti disetujui dua level
    </td>
    <td style="padding:5px 10px;border-right:1px solid #e2e8f0;">
      <span class="badge" style="background:#fef9c3;color:#a16207;">Menunggu</span> Masih dalam proses
    </td>
    <td style="padding:5px 10px;border-right:1px solid #e2e8f0;">
      <span class="badge" style="background:#fee2e2;color:#b91c1c;">Ditolak</span> Tidak disetujui
    </td>
    <td style="padding:5px 10px;">
      <span class="badge" style="background:#dcfce7;color:#15803d;">ACC</span> /
      <span class="badge" style="background:#fef9c3;color:#a16207;">TUNGGU</span> /
      <span class="badge" style="background:#fee2e2;color:#b91c1c;">TOLAK</span> Status per approver
    </td>
  </tr>
</table>

<!-- ══ TTD ══ -->
<div style="margin-top:16px;font-size:8.5pt;color:#475569;margin-bottom:4px;">
  Laporan ini telah diperiksa dan disetujui oleh:
</div>
<div class="ttd-section">
  <div class="ttd-box">
    <div class="ttd-title">Disiapkan Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name"><?= htmlspecialchars($_SESSION['user_nama'] ?? '___________________') ?></div>
    <div class="ttd-role">Staff SDM / HRD</div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Diperiksa Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Kepala Bagian SDM</div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Disetujui Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Pimpinan / Direktur</div>
  </div>
</div>

<!-- ══ FOOTER ══ -->
<div class="page-footer">
  <div class="pf-left">
    FixSmart Helpdesk &mdash; Laporan Rekapitulasi Cuti &mdash; <?= $label_periode ?> &mdash; <?= htmlspecialchars($label_divisi) ?><br>
    Dokumen ini bersifat rahasia dan hanya untuk keperluan internal manajemen SDM.
  </div>
  <div class="pf-right">
    No. Dok: <?= $no_dok ?> &nbsp;|&nbsp; <?= $total ?> pengajuan / <?= $tot_hari ?> hari<br>
    Dicetak: <?= date('d/m/Y H:i:s') ?> WIB
  </div>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ── Render PDF ────────────────────────────────────────────
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
$dompdf->setPaper('A4', 'landscape');  // landscape karena banyak kolom
$dompdf->render();

$safe_periode = $f_bulan ? $bln_nm[$f_bulan].'_'.$f_tahun : 'Tahun_'.$f_tahun;
$safe_divisi  = $f_divisi ? '_'.preg_replace('/[^a-zA-Z0-9]/', '_', $f_divisi) : '';
$filename     = 'Laporan_Cuti_'.$safe_periode.$safe_divisi.'.pdf';

$dompdf->stream($filename, ['Attachment' => false]);
exit;