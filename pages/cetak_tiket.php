<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../config.php';
requireLogin();

$dompdf_path = __DIR__ . '/../dompdf/autoload.inc.php';
if (!file_exists($dompdf_path)) { die('dompdf tidak ditemukan.'); }
require_once $dompdf_path;

use Dompdf\Dompdf;
use Dompdf\Options;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { die('ID tiket tidak valid.'); }

// Ambil data tiket
try {
$st = $pdo->prepare("
    SELECT t.*,
        k.nama  as kat_nama, k.sla_jam, k.sla_respon_jam,
        req.nama as req_nama, req.email as req_email,
        req.departemen as req_dept,
        tek.nama as tek_nama, tek.email as tek_email
    FROM tiket t
    LEFT JOIN kategori k  ON k.id  = t.kategori_id
    LEFT JOIN users req   ON req.id = t.user_id
    LEFT JOIN users tek   ON tek.id = t.teknisi_id
    WHERE t.id = ?
");
$st->execute([$id]);
$t = $st->fetch();
} catch (Exception $e) {
    // Kolom departemen mungkin tidak ada, coba tanpa departemen
    $st = $pdo->prepare("
        SELECT t.*,
            k.nama  as kat_nama, k.sla_jam, k.sla_respon_jam,
            req.nama as req_nama, req.email as req_email,
            NULL as req_dept,
            tek.nama as tek_nama, tek.email as tek_email
        FROM tiket t
        LEFT JOIN kategori k  ON k.id  = t.kategori_id
        LEFT JOIN users req   ON req.id = t.user_id
        LEFT JOIN users tek   ON tek.id = t.teknisi_id
        WHERE t.id = ?
    ");
    $st->execute([$id]);
    $t = $st->fetch();
}

if (!$t) { die('Tiket tidak ditemukan.'); }

// Hak akses: user hanya bisa cetak tiket miliknya sendiri
if (hasRole('user') && $t['user_id'] != $_SESSION['user_id']) {
    die('Akses ditolak.');
}

// Ambil riwayat aktivitas
$logs = $pdo->prepare("
    SELECT l.*, u.nama as user_nama, u.role as user_role
    FROM tiket_log l
    LEFT JOIN users u ON u.id = l.user_id
    WHERE l.tiket_id = ?
    ORDER BY l.created_at ASC
");
$logs->execute([$id]);
$logs = $logs->fetchAll();

// Ambil komentar
$komens = $pdo->prepare("
    SELECT k.*, u.nama, u.role
    FROM komentar k
    LEFT JOIN users u ON u.id = k.user_id
    WHERE k.tiket_id = ?
    ORDER BY k.created_at ASC
");
$komens->execute([$id]);
$komens = $komens->fetchAll();

// ── Helper ────────────────────────────────────────────────────────────────────
function durStr($menit) {
    if ($menit === null || $menit === '') return '-';
    $menit = (int)$menit;
    if ($menit < 60)  return $menit . ' menit';
    if ($menit < 1440) return round($menit/60,1) . ' jam';
    return round($menit/1440,1) . ' hari';
}

function statusLabel($s) {
    return match($s) {
        'menunggu'   => 'Menunggu',
        'diproses'   => 'Diproses',
        'selesai'    => 'Selesai',
        'ditolak'    => 'Ditolak',
        'tidak_bisa' => 'Tidak Bisa Ditangani',
        default      => ucfirst($s),
    };
}

function statusColor($s) {
    return match($s) {
        'menunggu'   => ['bg' => '#fef3c7', 'fg' => '#92400e', 'bar' => '#f59e0b'],
        'diproses'   => ['bg' => '#dbeafe', 'fg' => '#1e40af', 'bar' => '#3b82f6'],
        'selesai'    => ['bg' => '#dcfce7', 'fg' => '#15803d', 'bar' => '#16a34a'],
        'ditolak'    => ['bg' => '#fee2e2', 'fg' => '#b91c1c', 'bar' => '#ef4444'],
        'tidak_bisa' => ['bg' => '#ede9fe', 'fg' => '#6d28d9', 'bar' => '#8b5cf6'],
        default      => ['bg' => '#f1f5f9', 'fg' => '#475569', 'bar' => '#94a3b8'],
    };
}

function prioritasLabel($p) {
    return match($p) {
        'rendah'  => 'Low',
        'sedang'  => 'Medium',
        'tinggi'  => 'High',
        default   => ucfirst($p),
    };
}

function prioritasColor($p) {
    return match($p) {
        'rendah'  => ['bg' => '#dcfce7', 'fg' => '#15803d'],
        'sedang'  => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'tinggi'  => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        default   => ['bg' => '#f1f5f9', 'fg' => '#475569'],
    };
}

function fmtDt($dt) {
    if (!$dt) return '-';
    return date('d M Y, H:i', strtotime($dt)) . ' WIB';
}

$sc  = statusColor($t['status']);
$pc  = prioritasColor($t['prioritas']);
$is_final = in_array($t['status'], ['selesai','ditolak','tidak_bisa']);

// SLA
$sla_pct = null; $sla_label = ''; $sla_color = '#16a34a';
if ($t['sla_jam'] && $t['durasi_selesai_menit']) {
    $target_mnt = $t['sla_jam'] * 60;
    $sla_pct    = min(100, round($t['durasi_selesai_menit'] / $target_mnt * 100));
    if ($sla_pct <= 80)       { $sla_label = 'Dalam Target';  $sla_color = '#16a34a'; }
    elseif ($sla_pct <= 100)  { $sla_label = 'Hampir Batas';  $sla_color = '#f59e0b'; }
    else                      { $sla_label = 'Melewati SLA';  $sla_color = '#ef4444'; }
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
/* ─── RESET & BASE ─── */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #1e293b;
    background: #fff;
    margin: 44px 50px 40px 50px;
    line-height: 1.55;
}

/* ─── KOP ─── */
.kop { display: table; width: 100%; padding-bottom: 12px; border-bottom: 3px solid #0f172a; margin-bottom: 3px; }
.kop-logo { display: table-cell; vertical-align: middle; width: 54px; }
.kop-logo-box {
    width: 46px; height: 46px;
    background: #0f172a; border-radius: 5px;
    text-align: center; line-height: 46px;
    font-size: 17px; font-weight: bold; color: #fff;
    letter-spacing: -1px;
}
.kop-text { display: table-cell; vertical-align: middle; padding-left: 11px; }
.kop-org  { font-size: 14pt; font-weight: bold; color: #0f172a; line-height: 1.2; }
.kop-sub  { font-size: 8pt; color: #64748b; margin-top: 1px; }
.kop-right { display: table-cell; vertical-align: middle; text-align: right; }
.kop-lbl  { font-size: 7pt; color: #94a3b8; letter-spacing: 1.5px; text-transform: uppercase; }
.kop-val  { font-size: 9pt; font-weight: bold; color: #334155; }
.kop-sub2 { height: 1px; background: #cbd5e1; margin-bottom: 16px; }

/* ─── DOC TITLE ─── */
.doc-title {
    text-align: center;
    padding-bottom: 14px;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 16px;
}
.doc-label { font-size: 7pt; letter-spacing: 3px; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px; }
.doc-main  { font-size: 15pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 3px; }
.doc-num   { font-size: 14pt; color: #1d4ed8; font-weight: bold; margin-bottom: 3px; }
.doc-meta  { font-size: 8pt; color: #94a3b8; }

/* ─── STATUS BAR ─── */
.status-bar {
    display: table; width: 100%;
    border-radius: 4px; overflow: hidden;
    margin-bottom: 16px;
}
.sb-left  { display: table-cell; vertical-align: middle; padding: 10px 16px; width: 60%; }
.sb-right { display: table-cell; vertical-align: middle; text-align: right; padding: 10px 16px; }
.sb-label { font-size: 7.5pt; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 3px; }
.sb-val   { font-size: 13pt; font-weight: bold; }

/* ─── SECTION ─── */
.sec { margin-top: 16px; margin-bottom: 8px; }
.sec-title {
    font-size: 8pt; font-weight: bold; letter-spacing: 2px;
    text-transform: uppercase; color: #1e40af;
    border-bottom: 2px solid #1e40af;
    padding-bottom: 4px; margin-bottom: 8px;
}

/* ─── INFO GRID (2 kolom) ─── */
.info-grid { display: table; width: 100%; border-collapse: collapse; margin-bottom: 4px; }
.info-row  { display: table-row; }
.info-l    { display: table-cell; width: 50%; vertical-align: top; padding-right: 12px; padding-bottom: 2px; }
.info-r    { display: table-cell; width: 50%; vertical-align: top; padding-bottom: 2px; }

/* ─── FIELD ─── */
.field { margin-bottom: 8px; }
.field-lbl { font-size: 7.5pt; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 2px; }
.field-val { font-size: 9.5pt; color: #1e293b; font-weight: bold; }
.field-val-normal { font-size: 9.5pt; color: #1e293b; }

/* ─── BADGE ─── */
.badge {
    display: inline-block; padding: 2px 9px;
    border-radius: 3px; font-size: 8pt; font-weight: bold;
}

/* ─── DESKRIPSI BOX ─── */
.desc-box {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-left: 4px solid #1d4ed8;
    border-radius: 0 4px 4px 0;
    padding: 10px 14px; font-size: 9.5pt;
    color: #334155; line-height: 1.7;
    white-space: pre-wrap;
    margin-bottom: 4px;
}

/* ─── CATATAN PENOLAKAN ─── */
.reject-box {
    background: #fff8f8; border: 1px solid #fca5a5;
    border-left: 4px solid #ef4444;
    border-radius: 0 4px 4px 0;
    padding: 10px 14px; font-size: 9.5pt;
    color: #334155; line-height: 1.7;
}

/* ─── SLA CARD ─── */
.sla-card {
    border: 1px solid #e2e8f0; border-radius: 4px;
    overflow: hidden; margin-bottom: 4px;
}
.sla-header {
    background: #0f172a; color: #fff;
    padding: 7px 12px; font-size: 8pt;
    font-weight: bold; letter-spacing: 1px;
    text-transform: uppercase;
}
.sla-body { padding: 10px 12px; }
.sla-row  { display: table; width: 100%; margin-bottom: 5px; }
.sla-lbl  { display: table-cell; font-size: 9pt; color: #64748b; width: 50%; }
.sla-val  { display: table-cell; font-size: 9pt; font-weight: bold; color: #1e293b; text-align: right; }

.bar-track { background: #e2e8f0; height: 7px; border-radius: 4px; overflow: hidden; margin: 6px 0 3px; }
.bar-fill  { height: 7px; border-radius: 4px; }
.bar-hint  { font-size: 8pt; color: #94a3b8; }

/* ─── TIMELINE ─── */
.timeline { padding: 0; }
.tl-item  {
    display: table; width: 100%;
    margin-bottom: 7px; font-size: 9pt;
}
.tl-dot-col { display: table-cell; width: 14px; vertical-align: top; padding-top: 2px; }
.tl-dot {
    width: 9px; height: 9px;
    border-radius: 50%; display: inline-block;
    margin-top: 2px;
}
.tl-body { display: table-cell; vertical-align: top; padding-left: 8px; }
.tl-time { font-size: 8pt; color: #94a3b8; }
.tl-status { font-weight: bold; color: #1e293b; }
.tl-ket  { font-size: 8.5pt; color: #475569; margin-top: 1px; line-height: 1.5; }
.tl-by   { font-size: 8pt; color: #94a3b8; }

/* ─── KOMENTAR ─── */
.comment-box {
    border: 1px solid #e2e8f0; border-radius: 4px;
    padding: 8px 11px; margin-bottom: 6px;
    background: #f8fafc;
}
.comment-meta { font-size: 8pt; color: #94a3b8; margin-bottom: 3px; }
.comment-name { font-weight: bold; color: #1e293b; font-size: 9pt; }
.comment-role { font-size: 8pt; color: #64748b; }
.comment-body { font-size: 9.5pt; color: #334155; line-height: 1.6; margin-top: 3px; }

/* ─── TANDA TANGAN ─── */
.ttd-wrap  { display: table; width: 100%; margin-top: 24px; }
.ttd-cell  { display: table-cell; text-align: center; padding: 0 10px; vertical-align: top; width: 33.3%; }
.ttd-title { font-size: 9pt; color: #475569; margin-bottom: 50px; }
.ttd-line  { border-top: 1px solid #334155; margin: 0 12px 5px; }
.ttd-name  { font-size: 9pt; font-weight: bold; color: #0f172a; }
.ttd-role  { font-size: 8pt; color: #64748b; margin-top: 2px; }

/* ─── FOOTER ─── */
.page-footer {
    margin-top: 18px; padding-top: 7px;
    border-top: 1px solid #e2e8f0;
    display: table; width: 100%;
}
.pf-l { display: table-cell; font-size: 7.5pt; color: #94a3b8; vertical-align: middle; }
.pf-r { display: table-cell; text-align: right; font-size: 7.5pt; color: #94a3b8; vertical-align: middle; }

/* ─── DIVIDER ─── */
.hr { height: 1px; background: #f1f5f9; margin: 12px 0; }
</style>
</head>
<body>

<!-- ══ KOP SURAT ══ -->
<div class="kop">
  <div class="kop-logo"><div class="kop-logo-box">FS</div></div>
  <div class="kop-text">
    <div class="kop-org">FixSmart Helpdesk</div>
    <div class="kop-sub">IT Service Desk &mdash; Divisi Teknologi Informasi</div>
  </div>
  <div class="kop-right">
    <div class="kop-lbl">Ticket Number</div>
    <div class="kop-val"><?= htmlspecialchars($t['nomor']) ?></div>
    <div style="margin-top:4px;">
      <div class="kop-lbl">Printed</div>
      <div class="kop-val"><?= date('d/m/Y H:i') ?> WIB</div>
    </div>
  </div>
</div>
<div class="kop-sub2"></div>

<!-- ══ JUDUL DOKUMEN ══ -->
<div class="doc-title">
  <div class="doc-label">IT Helpdesk &mdash; Official Ticket Record</div>
  <div class="doc-main">Service Request Ticket</div>
  <div class="doc-num"><?= htmlspecialchars($t['nomor']) ?></div>
  <div class="doc-meta">
    Submitted: <?= fmtDt($t['waktu_submit']) ?>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    Printed by: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
  </div>
</div>

<!-- ══ STATUS BAR ══ -->
<div class="status-bar" style="background:<?= $sc['bg'] ?>;border:1px solid <?= $sc['bar'] ?>;">
  <div class="sb-left">
    <div class="sb-label" style="color:<?= $sc['fg'] ?>;">Current Status</div>
    <div class="sb-val" style="color:<?= $sc['fg'] ?>;"><?= statusLabel($t['status']) ?></div>
  </div>
  <div class="sb-right">
    <span class="badge" style="background:<?= $pc['bg'] ?>;color:<?= $pc['fg'] ?>;">
      <?= prioritasLabel($t['prioritas']) ?> Priority
    </span>
    &nbsp;&nbsp;
    <span class="badge" style="background:#eff6ff;color:#1d4ed8;">
      <?= htmlspecialchars($t['kat_nama'] ?? '-') ?>
    </span>
  </div>
</div>

<!-- ══ SECTION 1: TICKET INFORMATION ══ -->
<div class="sec">
  <div class="sec-title">01 &mdash; Ticket Information</div>
  <div class="field">
    <div class="field-lbl">Subject / Judul Masalah</div>
    <div style="font-size:12pt;font-weight:bold;color:#0f172a;"><?= htmlspecialchars($t['judul']) ?></div>
  </div>
  <div class="info-grid">
    <div class="info-row">
      <div class="info-l">
        <div class="field">
          <div class="field-lbl">Category</div>
          <div class="field-val"><?= htmlspecialchars($t['kat_nama'] ?? '-') ?></div>
        </div>
      </div>
      <div class="info-r">
        <div class="field">
          <div class="field-lbl">Priority</div>
          <div class="field-val">
            <span class="badge" style="background:<?= $pc['bg'] ?>;color:<?= $pc['fg'] ?>;"><?= prioritasLabel($t['prioritas']) ?></span>
          </div>
        </div>
      </div>
    </div>
    <div class="info-row">
      <div class="info-l">
        <div class="field">
          <div class="field-lbl">Location / Lokasi</div>
          <div class="field-val"><?= htmlspecialchars($t['lokasi'] ?? '-') ?></div>
        </div>
      </div>
      <div class="info-r">
        <div class="field">
          <div class="field-lbl">Status</div>
          <div class="field-val">
            <span class="badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['fg'] ?>;"><?= statusLabel($t['status']) ?></span>
          </div>
        </div>
      </div>
    </div>
    <div class="info-row">
      <div class="info-l">
        <div class="field">
          <div class="field-lbl">Date Submitted</div>
          <div class="field-val-normal"><?= fmtDt($t['waktu_submit']) ?></div>
        </div>
      </div>
      <div class="info-r">
        <div class="field">
          <div class="field-lbl">Date Resolved</div>
          <div class="field-val-normal"><?= $t['waktu_selesai'] ? fmtDt($t['waktu_selesai']) : '-' ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="field">
    <div class="field-lbl">Problem Description / Deskripsi Masalah</div>
    <div class="desc-box"><?= htmlspecialchars($t['deskripsi'] ?? '-') ?></div>
  </div>

  <?php if ($t['catatan_penolakan']): ?>
  <div class="field">
    <div class="field-lbl">IT Notes / Keterangan IT</div>
    <div class="reject-box"><?= htmlspecialchars($t['catatan_penolakan']) ?></div>
  </div>
  <?php endif; ?>
</div>

<!-- ══ SECTION 2: REQUESTER & TECHNICIAN ══ -->
<div class="sec">
  <div class="sec-title">02 &mdash; Requester &amp; Technician</div>
  <div class="info-grid">
    <div class="info-row">
      <!-- Requester -->
      <div class="info-l">
        <div style="border:1px solid #e2e8f0;border-top:3px solid #0891b2;border-radius:0 0 4px 4px;padding:10px 12px;">
          <div style="font-size:7.5pt;letter-spacing:1.5px;text-transform:uppercase;color:#0891b2;margin-bottom:6px;">Requester / Pemohon</div>
          <div class="field">
            <div class="field-lbl">Full Name</div>
            <div class="field-val"><?= htmlspecialchars($t['req_nama'] ?? '-') ?></div>
          </div>
          <div class="field">
            <div class="field-lbl">Email</div>
            <div class="field-val-normal"><?= htmlspecialchars($t['req_email'] ?? '-') ?></div>
          </div>
          <div class="field" style="margin-bottom:0;">
            <div class="field-lbl">Department / Jabatan</div>
            <div class="field-val-normal"><?= htmlspecialchars($t['req_dept'] ?? '-') ?></div>
          </div>
        </div>
      </div>
      <!-- Technician -->
      <div class="info-r">
        <div style="border:1px solid #e2e8f0;border-top:3px solid #1d4ed8;border-radius:0 0 4px 4px;padding:10px 12px;">
          <div style="font-size:7.5pt;letter-spacing:1.5px;text-transform:uppercase;color:#1d4ed8;margin-bottom:6px;">Assigned Technician / Teknisi</div>
          <div class="field">
            <div class="field-lbl">Full Name</div>
            <div class="field-val"><?= $t['tek_nama'] ? htmlspecialchars($t['tek_nama']) : '<span style="color:#94a3b8;font-weight:normal;">Not Assigned</span>' ?></div>
          </div>
          <div class="field">
            <div class="field-lbl">Email</div>
            <div class="field-val-normal"><?= $t['tek_email'] ? htmlspecialchars($t['tek_email']) : '-' ?></div>
          </div>
          <div class="field" style="margin-bottom:0;">
            <div class="field-lbl">Response Time</div>
            <div class="field-val-normal"><?= $t['waktu_diproses'] ? fmtDt($t['waktu_diproses']) : '-' ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══ SECTION 3: SLA & TIMING ══ -->
<?php if ($t['sla_jam']): ?>
<div class="sec">
  <div class="sec-title">03 &mdash; SLA &amp; Resolution Time</div>
  <div class="sla-card">
    <div class="sla-header">Service Level Agreement Monitoring</div>
    <div class="sla-body">
      <div class="info-grid">
        <div class="info-row">
          <div class="info-l">
            <div class="sla-row">
              <div class="sla-lbl">SLA Target (Penyelesaian)</div>
              <div class="sla-val"><?= $t['sla_jam'] ?> jam</div>
            </div>
            <div class="sla-row">
              <div class="sla-lbl">SLA Target (Respon)</div>
              <div class="sla-val"><?= $t['sla_respon_jam'] ?? '-' ?> jam</div>
            </div>
            <div class="sla-row">
              <div class="sla-lbl">Actual Response Time</div>
              <div class="sla-val"><?= durStr($t['durasi_respon_menit']) ?></div>
            </div>
          </div>
          <div class="info-r">
            <div class="sla-row">
              <div class="sla-lbl">Total Resolution Time</div>
              <div class="sla-val"><?= durStr($t['durasi_selesai_menit']) ?></div>
            </div>
            <div class="sla-row">
              <div class="sla-lbl">SLA Achievement</div>
              <div class="sla-val">
                <?php if ($sla_pct !== null): ?>
                <span class="badge" style="background:<?= $sla_pct<=100?'#dcfce7':'#fee2e2' ?>;color:<?= $sla_pct<=100?'#15803d':'#b91c1c' ?>;">
                  <?= $sla_label ?>
                </span>
                <?php else: ?>
                <span style="color:#94a3b8;">-</span>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($sla_pct !== null): ?>
            <div style="margin-top:6px;">
              <div class="bar-track">
                <div class="bar-fill" style="width:<?= min($sla_pct,100) ?>%;background:<?= $sla_color ?>;"></div>
              </div>
              <div class="bar-hint"><?= $sla_pct ?>% dari target <?= $t['sla_jam'] ?> jam digunakan</div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ SECTION 4: ACTIVITY LOG ══ -->
<?php if (!empty($logs)): ?>
<div class="sec">
  <div class="sec-title">04 &mdash; Activity Log / Riwayat Aktivitas</div>
  <div class="timeline">
    <?php
    $dot_colors = [
        'menunggu'   => '#f59e0b',
        'diproses'   => '#3b82f6',
        'selesai'    => '#16a34a',
        'ditolak'    => '#ef4444',
        'tidak_bisa' => '#8b5cf6',
    ];
    foreach ($logs as $log):
        $dot_c = $dot_colors[$log['status_ke']] ?? '#94a3b8';
    ?>
    <div class="tl-item">
      <div class="tl-dot-col"><div class="tl-dot" style="background:<?= $dot_c ?>;"></div></div>
      <div class="tl-body">
        <div style="display:table;width:100%;">
          <div style="display:table-cell;">
            <span class="tl-status"><?= statusLabel($log['status_ke']) ?></span>
            <span style="color:#94a3b8;font-size:8.5pt;margin-left:5px;">oleh <?= htmlspecialchars($log['user_nama'] ?? '-') ?></span>
          </div>
          <div style="display:table-cell;text-align:right;">
            <span class="tl-time"><?= fmtDt($log['created_at']) ?></span>
          </div>
        </div>
        <?php if ($log['keterangan']): ?>
        <div class="tl-ket"><?= htmlspecialchars($log['keterangan']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ══ SECTION 5: COMMENTS ══ -->
<?php if (!empty($komens)): ?>
<div class="sec">
  <div class="sec-title">05 &mdash; Discussion &amp; Comments</div>
  <?php foreach ($komens as $km): ?>
  <div class="comment-box">
    <div style="display:table;width:100%;margin-bottom:3px;">
      <div style="display:table-cell;">
        <span class="comment-name"><?= htmlspecialchars($km['nama'] ?? '-') ?></span>
        <span class="comment-role">&nbsp;&mdash;&nbsp;<?= ucfirst($km['role'] ?? '') ?></span>
      </div>
      <div style="display:table-cell;text-align:right;">
        <span class="tl-time"><?= fmtDt($km['created_at']) ?></span>
      </div>
    </div>
    <div class="comment-body"><?= nl2br(htmlspecialchars($km['isi'])) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ TANDA TANGAN ══ -->
<div style="margin-top:22px;">
  <div style="font-size:9pt;color:#475569;margin-bottom:4px;">Dokumen ini telah diverifikasi dan disetujui oleh:</div>
</div>
<div class="ttd-wrap">
  <div class="ttd-cell">
    <div class="ttd-title">Pemohon / Requester,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name"><?= htmlspecialchars($t['req_nama'] ?? '_______________') ?></div>
    <div class="ttd-role">Pemohon</div>
  </div>
  <div class="ttd-cell">
    <div class="ttd-title">Teknisi / Technician,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name"><?= $t['tek_nama'] ? htmlspecialchars($t['tek_nama']) : '_______________' ?></div>
    <div class="ttd-role">Teknisi IT</div>
  </div>
  <div class="ttd-cell">
    <div class="ttd-title">Diketahui / Acknowledged,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Kepala Divisi IT</div>
  </div>
</div>

<!-- ══ FOOTER ══ -->
<div class="page-footer">
  <div class="pf-l">
    FixSmart Helpdesk &mdash; <?= htmlspecialchars($t['nomor']) ?><br>
    Dokumen resmi, harap disimpan dengan baik. &mdash; Internal Use Only
  </div>
  <div class="pf-r">
    Dicetak: <?= date('d/m/Y H:i:s') ?> WIB &nbsp;|&nbsp; Halaman 1 dari 1<br>
    Oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
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

$filename = 'Tiket_' . $t['nomor'] . '_' . date('Ymd') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;