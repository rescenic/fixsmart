<?php
// pages/cetak_cuti.php — PDF Laporan / Slip Cuti
session_start();
require_once '../config.php';
requireLogin();

$dompdf_path = __DIR__ . '/../dompdf/autoload.inc.php';
if (!file_exists($dompdf_path)) { die('dompdf tidak ditemukan. Pasang dompdf terlebih dahulu.'); }
require_once $dompdf_path;

use Dompdf\Dompdf;
use Dompdf\Options;

// ── Parameter ─────────────────────────────────────────────
$uid_target = (int)($_GET['uid']   ?? $_SESSION['user_id']);
$tahun      = (int)($_GET['tahun'] ?? date('Y'));
$pid        = (int)($_GET['pid']   ?? 0); // 0 = semua, > 0 = satu pengajuan

// Hanya admin/hrd bisa lihat user lain
$cur_role = $_SESSION['user_role'] ?? 'user';
if ($uid_target !== (int)$_SESSION['user_id'] && !in_array($cur_role, ['admin','hrd'])) {
    $uid_target = (int)$_SESSION['user_id'];
}

// ── Data karyawan ─────────────────────────────────────────
$kary = $pdo->prepare("
    SELECT u.*,
           COALESCE(s.nik_rs,'') nik_rs,
           COALESCE(s.gelar_depan,'') gelar_depan,
           COALESCE(s.gelar_belakang,'') gelar_belakang,
           COALESCE(s.jenis_karyawan,'') jenis_karyawan,
           COALESCE(s.status_kepegawaian,'') status_kep,
           COALESCE(s.tgl_masuk,'') tgl_masuk,
           COALESCE(s.jabatan_id,0) jabatan_id,
           COALESCE(jb.nama,'') jabatan_nama,
           COALESCE(ua.nama,'') atasan_nama,
           COALESCE(s.atasan_id,0) atasan_id
    FROM users u
    LEFT JOIN sdm_karyawan s  ON s.user_id = u.id
    LEFT JOIN jabatan jb      ON jb.id = s.jabatan_id
    LEFT JOIN users ua        ON ua.id = s.atasan_id
    WHERE u.id = ?
");
$kary->execute([$uid_target]);
$kary = $kary->fetch(PDO::FETCH_ASSOC);
if (!$kary) die('Karyawan tidak ditemukan.');

// ── Jatah cuti tahun ini ──────────────────────────────────
$jatah_list = $pdo->prepare("
    SELECT jt.*, jc.kode, jc.nama jenis_nama, jc.kuota_default
    FROM jatah_cuti jt
    JOIN master_jenis_cuti jc ON jc.id = jt.jenis_cuti_id
    WHERE jt.user_id = ? AND jt.tahun = ?
    ORDER BY jc.urutan
");
$jatah_list->execute([$uid_target, $tahun]);
$jatah_list = $jatah_list->fetchAll(PDO::FETCH_ASSOC);

// ── Riwayat pengajuan ─────────────────────────────────────
$where_pid = $pid ? "AND pc.id = $pid" : "AND YEAR(pc.tgl_mulai) = $tahun";
$pengajuan = $pdo->prepare("
    SELECT pc.*,
           jc.kode jenis_kode, jc.nama jenis_nama,
           ua1.nama approver1_nama, ua1.divisi approver1_div,
           ua2.nama approver2_nama,
           ud.nama  delegasi_nama, ud.divisi delegasi_div,
           GROUP_CONCAT(ct.tanggal ORDER BY ct.tanggal SEPARATOR ',') tgl_list
    FROM pengajuan_cuti pc
    LEFT JOIN master_jenis_cuti jc ON jc.id = pc.jenis_cuti_id
    LEFT JOIN users ua1 ON ua1.id = pc.approver1_id
    LEFT JOIN users ua2 ON ua2.id = pc.approver2_id
    LEFT JOIN users ud  ON ud.id  = pc.delegasi_id
    LEFT JOIN cuti_tanggal ct ON ct.pengajuan_id = pc.id
    WHERE pc.user_id = ? $where_pid
    GROUP BY pc.id
    ORDER BY pc.tgl_mulai ASC
");
$pengajuan->execute([$uid_target]);
$pengajuan = $pengajuan->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers ───────────────────────────────────────────────
function fmtTglLong(?string $d): string {
    if (!$d || $d === '0000-00-00') return '-';
    $bln = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return date('j', strtotime($d)) . ' ' . $bln[(int)date('n', strtotime($d))] . ' ' . date('Y', strtotime($d));
}
function fmtTglShort(?string $d): string {
    if (!$d || $d === '0000-00-00') return '-';
    return date('d/m/Y', strtotime($d));
}
function statusLabel2(string $s): array {
    return match($s) {
        'menunggu'   => ['Menunggu',   '#fef9c3','#a16207'],
        'disetujui'  => ['Disetujui',  '#dcfce7','#15803d'],
        'ditolak'    => ['Ditolak',    '#fee2e2','#b91c1c'],
        'dibatalkan' => ['Dibatalkan', '#f3f4f6','#6b7280'],
        default      => [ucfirst($s),  '#f3f4f6','#374151'],
    };
}
function statusApprLabel(string $s): array {
    return match($s) {
        'disetujui' => ['ACC','#dcfce7','#15803d'],
        'ditolak'   => ['TOLAK','#fee2e2','#b91c1c'],
        default     => ['TUNGGU', '#fef9c3','#a16207'],
    };
}

// ── Statistik jatah ───────────────────────────────────────
$total_kuota   = array_sum(array_column($jatah_list, 'kuota'));
$total_terpakai= array_sum(array_column($jatah_list, 'terpakai'));
$total_sisa    = array_sum(array_column($jatah_list, 'sisa'));
$total_pengajuan = count($pengajuan);
$tot_disetujui = count(array_filter($pengajuan, fn($r)=>$r['status']==='disetujui'));
$tot_menunggu  = count(array_filter($pengajuan, fn($r)=>$r['status']==='menunggu'));
$tot_ditolak   = count(array_filter($pengajuan, fn($r)=>$r['status']==='ditolak'));
$tot_hari_disetujui = array_sum(array_map(fn($r)=>$r['status']==='disetujui'?(int)$r['jumlah_hari']:0, $pengajuan));

// ── Meta dokumen ──────────────────────────────────────────
$no_dok    = 'SLP-CTI-' . date('Ymd') . '-' . str_pad($uid_target, 3, '0', STR_PAD_LEFT);
$nama_full = ($kary['gelar_depan'] ? $kary['gelar_depan'].' ' : '') . $kary['nama']
           . ($kary['gelar_belakang'] ? ', ' . $kary['gelar_belakang'] : '');
$label_period = $pid ? 'Per Pengajuan' : "Tahun $tahun";

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
.report-label       { font-size:7pt; letter-spacing:3px; text-transform:uppercase; color:#64748b; margin-bottom:4px; }
.report-main-title  { font-size:14pt; font-weight:bold; color:#0f172a; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.report-sub         { font-size:10pt; color:#1d4ed8; font-weight:bold; }
.report-no          { font-size:7.5pt; color:#94a3b8; margin-top:4px; }

/* ── INFO DOKUMEN ── */
.doc-info { width:100%; border-collapse:collapse; margin-bottom:12px; border:1px solid #e2e8f0; }
.doc-info td { padding:5px 9px; font-size:8.5pt; border:1px solid #e2e8f0; vertical-align:top; }
.di-label { background:#f8fafc; color:#475569; font-weight:bold; width:18%; }
.di-val   { color:#1e293b; width:32%; }

/* ── SECTION ── */
.sec { margin-top:14px; margin-bottom:8px; page-break-after:avoid; }
.sec-num   { font-size:7pt; letter-spacing:2px; text-transform:uppercase; color:#94a3b8; margin-bottom:2px; }
.sec-title { font-size:10pt; font-weight:bold; color:#0f172a; border-bottom:2px solid #1d4ed8; padding-bottom:4px; display:table; width:100%; }
.sec-title-text { display:table-cell; }
.sec-title-rule { display:table-cell; text-align:right; font-size:7.5pt; font-weight:normal; color:#94a3b8; vertical-align:bottom; }

/* ── KPI CARDS ── */
.kpi-table { width:100%; border-collapse:separate; border-spacing:4px; margin:0 -4px; }
.kpi-td    { vertical-align:top; }
.kpi-card  { border:1px solid #e2e8f0; border-top:3px solid #1d4ed8; border-radius:0 0 4px 4px; padding:7px 9px 6px; background:#fff; }
.kpi-card .k-label { font-size:6.5pt; letter-spacing:1px; text-transform:uppercase; color:#64748b; margin-bottom:3px; }
.kpi-card .k-val   { font-size:15pt; font-weight:bold; line-height:1; margin-bottom:2px; }
.kpi-card .k-desc  { font-size:6.5pt; color:#94a3b8; line-height:1.4; }

/* ── TABEL ── */
.summ-tbl { width:100%; border-collapse:collapse; font-size:8pt; }
.summ-tbl th { background:#0f172a; color:#fff; padding:6px 8px; font-size:7pt; text-align:left; }
.summ-tbl td { padding:5px 8px; border-bottom:1px solid #f1f5f9; font-size:8pt; }
.summ-tbl tr:nth-child(even) td { background:#f8fafc; }
.summ-tbl tfoot td { background:#eff6ff; border-top:2px solid #bfdbfe; font-weight:bold; color:#1e3a8a; }

/* ── TABEL DETAIL PENGAJUAN ── */
.data-tbl { width:100%; border-collapse:collapse; font-size:7pt; page-break-inside:auto; }
.data-tbl thead tr  { background:#0f172a; color:#fff; }
.data-tbl thead th  { padding:5px 5px; text-align:left; font-size:6.5pt; font-weight:bold; border-right:1px solid rgba(255,255,255,.08); }
.data-tbl thead th:last-child { border-right:none; }
.data-tbl tbody tr td { padding:4px 5px; border-bottom:1px solid #f1f5f9; vertical-align:top; font-size:6.5pt; }
.data-tbl tbody tr:nth-child(even) td { background:#f8fafc; }
.data-tbl tbody tr:last-child td { border-bottom:2px solid #e2e8f0; }
.data-tbl tfoot td  { padding:5px 5px; font-weight:bold; background:#eff6ff; border-top:2px solid #bfdbfe; color:#1e3a8a; font-size:7.5pt; }

.badge { display:inline-block; padding:2px 7px; border-radius:3px; font-size:7.5pt; font-weight:bold; white-space:nowrap; }
.bar-wrap { background:#e2e8f0; height:5px; border-radius:3px; overflow:hidden; display:inline-block; vertical-align:middle; }
.bar-fill { height:5px; border-radius:3px; }

/* ── PROFIL KARYAWAN CARD ── */
.kary-card { display:table; width:100%; border:1px solid #e2e8f0; border-top:3px solid #0f172a; border-radius:0 0 4px 4px; margin-bottom:12px; background:#fff; }
.kary-av   { display:table-cell; width:56px; vertical-align:middle; text-align:center; padding:14px 10px; }
.kary-av-circle { width:44px;height:44px;background:#0f172a;border-radius:50%;text-align:center;line-height:44px;color:#00e5b0;font-weight:bold;font-size:13pt;margin:0 auto; }
.kary-info { display:table-cell; vertical-align:middle; padding:12px 10px; }
.kary-nama { font-size:13pt; font-weight:bold; color:#0f172a; line-height:1.2; }
.kary-meta { font-size:8pt; color:#64748b; margin-top:4px; }
.kary-meta span { margin-right:14px; }
.kary-right { display:table-cell; vertical-align:middle; text-align:right; padding:12px 14px; }

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

/* ── APPROVAL STEPS ── */
.ap-step-row { display:table; width:300px; margin:4px 0; }
.ap-dot-cell { display:table-cell; width:28px; vertical-align:middle; text-align:center; }
.ap-dot-s { width:18px;height:18px;border-radius:50%;display:inline-block;text-align:center;line-height:18px;font-size:8pt;font-weight:bold;border:1px solid #e5e7eb; }
.ap-line-cell { display:table-cell; vertical-align:middle; padding:0 4px; }
.ap-line-s { width:30px;height:2px;background:#e5e7eb;display:inline-block;vertical-align:middle; }
.ap-line-ok { background:#16a34a; }
.ap-text-cell { display:table-cell; vertical-align:middle; font-size:7.5pt; color:#374151; }
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
  <div class="report-main-title">
    <?= $pid ? 'LEMBAR PENGAJUAN CUTI' : 'LAPORAN CUTI KARYAWAN' ?>
  </div>
  <div class="report-sub">
    <?= htmlspecialchars($nama_full) ?>
    <?php if ($pid && !empty($pengajuan)): ?>
    &mdash; <?= htmlspecialchars($pengajuan[0]['jenis_nama']??'') ?>
    &mdash; <?= (int)($pengajuan[0]['jumlah_hari']??0) ?> Hari
    <?php else: ?>
    &mdash; Tahun <?= $tahun ?>
    <?php endif; ?>
  </div>
  <div class="report-no">
    Disiapkan oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
    &nbsp;|&nbsp; Dicetak: <?= date('d F Y, H:i') ?> WIB
  </div>
</div>

<!-- ══ INFO DOKUMEN ══ -->
<table class="doc-info">
  <tr>
    <td class="di-label">Nama Karyawan</td>
    <td class="di-val"><strong><?= htmlspecialchars($nama_full) ?></strong></td>
    <td class="di-label">NIK RS</td>
    <td class="di-val"><?= htmlspecialchars($kary['nik_rs'] ?: '-') ?></td>
  </tr>
  <tr>
    <td class="di-label">Divisi / Unit</td>
    <td class="di-val"><?= htmlspecialchars($kary['divisi'] ?: '-') ?></td>
    <td class="di-label">Jabatan</td>
    <td class="di-val"><?= htmlspecialchars($kary['jabatan_nama'] ?: '-') ?></td>
  </tr>
  <tr>
    <td class="di-label">Jenis Karyawan</td>
    <td class="di-val"><?= htmlspecialchars($kary['jenis_karyawan'] ?: '-') ?></td>
    <td class="di-label">Status Kepegawaian</td>
    <td class="di-val"><?= htmlspecialchars($kary['status_kep'] ?: '-') ?></td>
  </tr>
  <tr>
    <td class="di-label">Tgl Masuk Kerja</td>
    <td class="di-val"><?= fmtTglLong($kary['tgl_masuk']) ?></td>
    <td class="di-label">Atasan Langsung</td>
    <td class="di-val"><?= htmlspecialchars($kary['atasan_nama'] ?: '-') ?></td>
  </tr>
  <?php if ($pid && !empty($pengajuan)): $slip0 = $pengajuan[0]; ?>
  <tr>
    <td class="di-label">Jenis Cuti</td>
    <td class="di-val"><strong><?= htmlspecialchars($slip0['jenis_nama']??'-') ?></strong></td>
    <td class="di-label">Jumlah Hari</td>
    <td class="di-val"><strong style="font-size:10pt;color:#1d4ed8;"><?= (int)$slip0['jumlah_hari'] ?> hari</strong></td>
  </tr>
  <tr>
    <td class="di-label">Tanggal Mulai</td>
    <td class="di-val"><?= fmtTglLong($slip0['tgl_mulai']) ?></td>
    <td class="di-label">Tanggal Selesai</td>
    <td class="di-val"><?= fmtTglLong($slip0['tgl_selesai']) ?></td>
  </tr>
  <tr>
    <td class="di-label">Tanggal Diajukan</td>
    <td class="di-val"><?= fmtTglLong($slip0['created_at']) ?></td>
    <td class="di-label">Status Pengajuan</td>
    <?php [$slbl0, $sbg0, $sfg0] = statusLabel2($slip0['status']); ?>
    <td class="di-val">
      <span style="display:inline-block;padding:2px 10px;border-radius:3px;font-weight:bold;font-size:8.5pt;background:<?= $sbg0 ?>;color:<?= $sfg0 ?>;"><?= $slbl0 ?></span>
    </td>
  </tr>
  <?php else: ?>
  <tr>
    <td class="di-label">Periode Laporan</td>
    <td class="di-val"><?= $label_period ?></td>
    <td class="di-label">Status Dokumen</td>
    <td class="di-val">Final &mdash; Confidential</td>
  </tr>
  <?php endif; ?>
</table>

<!-- ══ I. KPI RINGKASAN ══ -->
<div class="sec">
  <div class="sec-num">Bagian I</div>
  <div class="sec-title">
    <span class="sec-title-text">Ringkasan Cuti <?= $label_period ?></span>
  </div>
</div>

<?php if ($pid && !empty($pengajuan)):
  // ── MODE SLIP: tampilkan detail pengajuan ini saja ──────
  $slip = $pengajuan[0];
  [$slbl, $sbg, $sfg] = statusLabel2($slip['status']);
  [$a1lbl, $a1bg, $a1fg] = statusApprLabel($slip['status_approver1']);
  $a2_ok = $slip['status_approver1'] === 'disetujui';
  [$a2lbl, $a2bg, $a2fg] = $a2_ok ? statusApprLabel($slip['status_approver2']) : ['TUNGGU', '#f3f4f6', '#94a3b8'];
  $tgls_slip = $slip['tgl_list'] ? explode(',', $slip['tgl_list']) : [];
?>
<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#1d4ed8;">
        <div class="k-label">Jumlah Hari Diajukan</div>
        <div class="k-val" style="color:#1d4ed8;"><?= (int)$slip['jumlah_hari'] ?></div>
        <div class="k-desc">Hari kerja dalam pengajuan ini</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#0891b2;">
        <div class="k-label">Jenis Cuti</div>
        <div class="k-val" style="color:#0891b2;font-size:11pt;padding-top:3px;"><?= htmlspecialchars($slip['jenis_kode']??'-') ?></div>
        <div class="k-desc"><?= htmlspecialchars($slip['jenis_nama']??'-') ?></div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#7c3aed;">
        <div class="k-label">Periode Cuti</div>
        <div class="k-val" style="color:#7c3aed;font-size:9pt;padding-top:4px;"><?= fmtTglShort($slip['tgl_mulai']) ?></div>
        <div class="k-desc">
          <?php if ($slip['tgl_mulai'] !== $slip['tgl_selesai']): ?>
          s.d. <?= fmtTglShort($slip['tgl_selesai']) ?>
          <?php else: ?>
          Satu hari
          <?php endif; ?>
          &mdash; <?= count($tgls_slip) ?> hari dipilih
        </div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:<?= $sfg ?>;">
        <div class="k-label">Status Pengajuan</div>
        <div class="k-val" style="color:<?= $sfg ?>;font-size:11pt;padding-top:3px;"><?= $slbl ?></div>
        <div class="k-desc">
          Atasan: <strong style="color:<?= $a1fg ?>;"><?= $a1lbl ?></strong><br>
          HRD: <strong style="color:<?= $a2fg ?>;"><?= $a2lbl ?></strong>
        </div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#64748b;">
        <div class="k-label">Sisa Jatah Tahunan</div>
        <div class="k-val" style="color:<?= $total_sisa > 0 ? '#16a34a' : '#dc2626' ?>;"><?= $total_sisa ?></div>
        <div class="k-desc">
          Kuota: <?= $total_kuota ?> &mdash; Terpakai: <?= $total_terpakai ?><br>
          Tahun <?= $tahun ?>
        </div>
      </div>
    </td>
  </tr>
</table>

<?php else:
  // ── MODE TAHUNAN: tampilkan ringkasan semua pengajuan ───
?>
<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#1d4ed8;">
        <div class="k-label">Kuota Cuti <?= $tahun ?></div>
        <div class="k-val" style="color:#1d4ed8;"><?= $total_kuota ?></div>
        <div class="k-desc">Total hari cuti tersedia</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#dc2626;">
        <div class="k-label">Terpakai</div>
        <div class="k-val" style="color:#dc2626;"><?= $total_terpakai ?></div>
        <div class="k-desc">Hari cuti sudah diambil</div>
      </div>
    </td>
    <td class="kpi-td">
      <?php $sisa_col = $total_sisa > 3 ? '#16a34a' : ($total_sisa > 0 ? '#d97706' : '#dc2626'); ?>
      <div class="kpi-card" style="border-top-color:<?= $sisa_col ?>;">
        <div class="k-label">Sisa Cuti</div>
        <div class="k-val" style="color:<?= $sisa_col ?>;"><?= $total_sisa ?></div>
        <div class="k-desc">Hari cuti masih tersedia</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#0891b2;">
        <div class="k-label">Total Pengajuan</div>
        <div class="k-val" style="color:#0891b2;"><?= $total_pengajuan ?></div>
        <div class="k-desc">
          <?= $tot_disetujui ?> disetujui &mdash; <?= $tot_menunggu ?> menunggu<br>
          <?= $tot_ditolak ?> ditolak
        </div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:#7c3aed;">
        <div class="k-label">Total Hari Disetujui</div>
        <div class="k-val" style="color:#7c3aed;"><?= $tot_hari_disetujui ?></div>
        <div class="k-desc">
          Hari cuti ACC tahun <?= $tahun ?><br>
          dari <?= $tot_disetujui ?> pengajuan
        </div>
      </div>
    </td>
  </tr>
</table>
<?php endif; ?>

<!-- ══ II. JATAH PER JENIS ══ -->
<?php if ($jatah_list): ?>
<div class="sec">
  <div class="sec-num">Bagian II</div>
  <div class="sec-title">
    <span class="sec-title-text">Jatah Cuti per Jenis - Tahun <?= $tahun ?></span>
  </div>
</div>
<table class="summ-tbl">
  <thead>
    <tr>
      <th>No.</th>
      <th>Jenis Cuti</th>
      <th style="text-align:center;">Kuota</th>
      <th style="text-align:center;">Terpakai</th>
      <th style="text-align:center;">Sisa</th>
      <th style="text-align:center;">% Terpakai</th>
      <th>Proporsi Penggunaan</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($jatah_list as $i => $jt):
        $pct = $jt['kuota'] > 0 ? round($jt['terpakai'] / $jt['kuota'] * 100) : 0;
        $col = $pct >= 80 ? '#dc2626' : ($pct >= 50 ? '#d97706' : '#16a34a');
    ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $i+1 ?></td>
      <td style="font-weight:bold;">
        <span style="font-family:monospace;background:#f1f5f9;padding:1px 5px;border-radius:3px;font-size:7.5pt;margin-right:5px;"><?= htmlspecialchars($jt['kode']) ?></span>
        <?= htmlspecialchars($jt['jenis_nama']) ?>
      </td>
      <td style="text-align:center;font-weight:bold;"><?= $jt['kuota'] ?></td>
      <td style="text-align:center;color:#dc2626;font-weight:bold;"><?= $jt['terpakai'] ?></td>
      <td style="text-align:center;color:#16a34a;font-weight:bold;"><?= $jt['sisa'] ?></td>
      <td style="text-align:center;">
        <strong style="color:<?= $col ?>;"><?= $pct ?>%</strong>
      </td>
      <td>
        <div class="bar-wrap" style="width:100px;">
          <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">TOTAL</td>
      <td style="text-align:center;"><?= $total_kuota ?></td>
      <td style="text-align:center;color:#dc2626;"><?= $total_terpakai ?></td>
      <td style="text-align:center;color:#16a34a;"><?= $total_sisa ?></td>
      <td style="text-align:center;">
        <?= $total_kuota > 0 ? round($total_terpakai/$total_kuota*100) : 0 ?>%
      </td>
      <td>-</td>
    </tr>
  </tfoot>
</table>
<?php endif; ?>

<!-- ══ III. DETAIL PENGAJUAN ══ -->
<div class="sec">
  <div class="sec-num">Bagian III</div>
  <div class="sec-title">
    <span class="sec-title-text">
      <?= $pid ? 'Detail Pengajuan Cuti' : 'Riwayat Pengajuan Cuti - ' . $label_period ?>
    </span>
    <span class="sec-title-rule">Total: <?= $total_pengajuan ?> pengajuan &nbsp;|&nbsp; <?= $tot_hari_disetujui ?> hari disetujui</span>
  </div>
</div>

<?php if (empty($pengajuan)): ?>
<div style="text-align:center;color:#94a3b8;padding:18px;border:1px dashed #e2e8f0;border-radius:4px;font-style:italic;">
  Tidak ada data pengajuan cuti untuk periode ini.
</div>
<?php else: ?>

<table class="data-tbl">
  <thead>
    <tr>
      <th style="width:16px;">#</th>
      <th style="width:48px;">Jenis</th>
      <th style="width:80px;">Tgl Cuti</th>
      <th style="width:22px;text-align:center;">Hr</th>
      <th style="width:90px;">Tanggal-Tanggal</th>
      <th style="width:80px;">Keperluan</th>
      <th style="width:60px;">Delegasi</th>
      <th style="width:42px;text-align:center;">Status</th>
      <th style="width:82px;">Approval</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($pengajuan as $i => $r):
        [$slbl, $sbg, $sfg] = statusLabel2($r['status']);
        $tgls = $r['tgl_list'] ? explode(',', $r['tgl_list']) : [];
        $tgl_display = array_map(fn($t) => date('d/m', strtotime($t)), $tgls);

        // Approval level 1
        [$a1lbl, $a1bg, $a1fg] = statusApprLabel($r['status_approver1']);
        // Approval level 2
        $a2_enabled = $r['status_approver1'] === 'disetujui';
        [$a2lbl, $a2bg, $a2fg] = $a2_enabled ? statusApprLabel($r['status_approver2']) : ['-', '#f3f4f6', '#94a3b8'];
    ?>
    <tr>
      <td style="text-align:center;color:#94a3b8;"><?= $i+1 ?></td>
      <td>
        <span style="font-family:monospace;background:#f1f5f9;padding:1px 5px;border-radius:3px;font-size:7pt;"><?= htmlspecialchars($r['jenis_kode']??'') ?></span>
        <div style="font-size:6.5pt;color:#94a3b8;margin-top:1px;"><?= htmlspecialchars($r['jenis_nama']??'') ?></div>
      </td>
      <td style="font-size:7.5pt;white-space:nowrap;">
        <?= fmtTglShort($r['tgl_mulai']) ?>
        <?php if ($r['tgl_mulai'] !== $r['tgl_selesai']): ?>
        <br><span style="color:#94a3b8;">s.d.</span> <?= fmtTglShort($r['tgl_selesai']) ?>
        <?php endif; ?>
        <div style="font-size:6.5pt;color:#94a3b8;margin-top:1px;">Diajukan <?= fmtTglShort($r['created_at']) ?></div>
      </td>
      <td style="text-align:center;font-weight:bold;font-size:11pt;color:#1d4ed8;">
        <?= $r['jumlah_hari'] ?>
        <div style="font-size:6.5pt;font-weight:normal;color:#94a3b8;">hari</div>
      </td>
      <td style="font-size:6.5pt;">
        <?php
        $chips = array_map(fn($t) => date('d/m', strtotime($t)), $tgls);
        echo implode(', ', array_slice($chips, 0, 6));
        if (count($chips) > 6) echo ' +' . (count($chips)-6);
        ?>
      </td>
      <td style="font-size:7.5pt;color:#374151;">
        <?= htmlspecialchars(mb_strimwidth($r['keperluan']??'-', 0, 60, '...')) ?>
      </td>
      <td style="font-size:7.5pt;">
        <?php if ($r['delegasi_nama']): ?>
        <strong><?= htmlspecialchars($r['delegasi_nama']) ?></strong>
        <?php if ($r['delegasi_div']): ?>
        <div style="font-size:6.5pt;color:#94a3b8;"><?= htmlspecialchars($r['delegasi_div']) ?></div>
        <?php endif; ?>
        <?php if ($r['catatan_delegasi']): ?>
        <div style="font-size:6.5pt;color:#64748b;font-style:italic;"><?= htmlspecialchars($r['catatan_delegasi']) ?></div>
        <?php endif; ?>
        <?php else: ?>
        <span style="color:#cbd5e1;">-</span>
        <?php endif; ?>
      </td>
      <td style="text-align:center;">
        <span class="badge" style="background:<?= $sbg ?>;color:<?= $sfg ?>;"><?= $slbl ?></span>
      </td>
      <td style="font-size:7pt;">
        <!-- Atasan -->
        <div style="margin-bottom:3px;">
          <span class="badge" style="background:<?= $a1bg ?>;color:<?= $a1fg ?>;"><?= $a1lbl ?></span>
          <span style="color:#94a3b8;margin-left:3px;"><?= htmlspecialchars(mb_strimwidth($r['approver1_nama']??'Atasan',0,20,'...')) ?></span>
        </div>
        <?php if ($r['catatan_approver1'] && $r['status_approver1'] === 'ditolak'): ?>
        <div style="font-style:italic;color:#dc2626;font-size:6.5pt;margin-bottom:3px;">"<?= htmlspecialchars($r['catatan_approver1']) ?>"</div>
        <?php endif; ?>
        <!-- HRD -->
        <div>
          <span class="badge" style="background:<?= $a2bg ?>;color:<?= $a2fg ?>;"><?= $a2lbl ?></span>
          <span style="color:#94a3b8;margin-left:3px;"><?= $a2_enabled ? htmlspecialchars(mb_strimwidth($r['approver2_nama']??'HRD',0,20,'...')) : 'HRD' ?></span>
        </div>
        <?php if ($r['catatan_approver2'] && $r['status_approver2'] === 'ditolak'): ?>
        <div style="font-style:italic;color:#dc2626;font-size:6.5pt;">"<?= htmlspecialchars($r['catatan_approver2']) ?>"</div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3">TOTAL</td>
      <td style="text-align:center;"><?= $tot_hari_disetujui ?> hari</td>
      <td colspan="3"><?= $total_pengajuan ?> pengajuan &nbsp;|&nbsp; <?= $tot_disetujui ?> disetujui &nbsp;/&nbsp; <?= $tot_menunggu ?> menunggu &nbsp;/&nbsp; <?= $tot_ditolak ?> ditolak</td>
      <td colspan="2">&nbsp;</td>
    </tr>
  </tfoot>
</table>
<?php endif; ?>

<!-- ══ KETERANGAN ══ -->
<table style="width:100%;border-collapse:collapse;margin-top:8px;border:1px solid #e2e8f0;font-size:8pt;">
  <tr>
    <td style="padding:5px 9px;background:#f8fafc;font-weight:bold;color:#475569;border-right:1px solid #e2e8f0;width:12%;">Keterangan</td>
    <td style="padding:5px 10px;border-right:1px solid #e2e8f0;">
      <span class="badge" style="background:#dcfce7;color:#15803d;">ACC</span> Cuti telah disetujui semua level
    </td>
    <td style="padding:5px 10px;border-right:1px solid #e2e8f0;">
      <span class="badge" style="background:#fef9c3;color:#a16207;">TUNGGU</span> Menunggu approval
    </td>
    <td style="padding:5px 10px;">
      <span class="badge" style="background:#fee2e2;color:#b91c1c;">TOLAK</span> Pengajuan ditolak
    </td>
  </tr>
</table>

<!-- ══ TANDA TANGAN ══ -->
<div style="margin-top:16px;font-size:8.5pt;color:#475569;margin-bottom:4px;">
  Laporan ini telah diperiksa dan disetujui oleh:
</div>
<div class="ttd-section">
  <div class="ttd-box">
    <div class="ttd-title">Dibuat / Pemohon,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name"><?= htmlspecialchars($nama_full) ?></div>
    <div class="ttd-role"><?= htmlspecialchars($kary['jabatan_nama'] ?: $kary['jenis_karyawan'] ?: 'Karyawan') ?></div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Atasan Langsung,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name"><?= htmlspecialchars($kary['atasan_nama'] ?: '___________________') ?></div>
    <div class="ttd-role">Atasan Langsung</div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Disetujui HRD,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Bagian SDM / HRD</div>
  </div>
</div>

<!-- ══ FOOTER ══ -->
<div class="page-footer">
  <div class="pf-left">
    FixSmart Helpdesk &mdash; Laporan Cuti &mdash; <?= htmlspecialchars($nama_full) ?> &mdash; <?= htmlspecialchars($label_period) ?><br>
    Dokumen ini bersifat rahasia dan hanya untuk keperluan internal manajemen SDM.
  </div>
  <div class="pf-right">
    No. Dok: <?= $no_dok ?> &nbsp;|&nbsp; <?= $total_pengajuan ?> pengajuan / <?= $tot_hari_disetujui ?> hari<br>
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
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$safe_nama = preg_replace('/[^a-zA-Z0-9_-]/', '_', $kary['nama'] ?? 'karyawan');
$filename  = 'Laporan_Cuti_' . $safe_nama . '_' . $tahun . '.pdf';
if ($pid) $filename = 'Slip_Cuti_' . $safe_nama . '_' . $pid . '.pdf';

$dompdf->stream($filename, ['Attachment' => false]);
exit;