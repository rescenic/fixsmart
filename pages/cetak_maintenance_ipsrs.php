<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

// ── Error reporting untuk debug ──────────────────────────────────────────────
ini_set('display_errors', 1);
error_reporting(E_ALL);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('<p style="font-family:sans-serif;padding:20px;color:red;">ID tidak valid.</p>');
}

// ── Deteksi kolom yang tersedia di aset_ipsrs ────────────────────────────────
$available_cols = [];
try {
    $cols_result = $pdo->query("DESCRIBE aset_ipsrs")->fetchAll(PDO::FETCH_COLUMN);
    $available_cols = $cols_result;
} catch (Exception $e) {}

// Bangun SELECT aset secara dinamis sesuai kolom yang ada
$aset_selects = [
    'a.no_inventaris',
    'a.nama_aset      AS aset_nama_db',
    'a.kondisi        AS aset_kondisi_skrg',
];
// Kolom opsional — hanya tambahkan jika ada di tabel
$optional = [
    'kategori'        => 'a.kategori       AS aset_kat',
    'merek'           => 'a.merek          AS aset_merek',
    'model_aset'      => 'a.model_aset',
    'serial_number'   => 'a.serial_number  AS aset_serial',
    'jenis_aset'      => 'a.jenis_aset',
    'lokasi'          => 'a.lokasi         AS aset_lokasi',
    'tahun_perolehan' => 'a.tahun_perolehan',
];
foreach ($optional as $col => $select) {
    if (in_array($col, $available_cols)) {
        $aset_selects[] = $select;
    }
}

// Deteksi kolom users
$user_selects = ['u.nama AS tek_nama_db'];
$user_cols = [];
try {
    $user_cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {}
if (in_array('divisi', $user_cols)) $user_selects[] = 'u.divisi AS tek_divisi';
if (in_array('no_hp',  $user_cols)) $user_selects[]  = 'u.no_hp  AS tek_hp';

$select_aset  = implode(",\n           ", $aset_selects);
$select_users = implode(",\n           ", $user_selects);

$sql = "
    SELECT m.*,
           {$select_aset},
           {$select_users},
           uc.nama AS dibuat_oleh
    FROM maintenance_ipsrs m
    LEFT JOIN aset_ipsrs a  ON a.id  = m.aset_id
    LEFT JOIN users      u  ON u.id  = m.teknisi_id
    LEFT JOIN users      uc ON uc.id = m.created_by
    WHERE m.id = ?
";

try {
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    $mnt = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('<div style="font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;max-width:700px;margin:20px auto;">
        <strong>❌ Query Error:</strong><br><pre style="white-space:pre-wrap;font-size:12px;">'
        . htmlspecialchars($e->getMessage()) . '</pre>
        <hr style="margin:10px 0;">
        <strong>SQL:</strong><pre style="white-space:pre-wrap;font-size:11px;">' . htmlspecialchars($sql) . '</pre>
    </div>');
}

if (!$mnt) {
    die('<p style="font-family:sans-serif;padding:20px;color:red;">Data maintenance tidak ditemukan (id='.$id.').</p>');
}

// Isi default untuk kolom opsional yang tidak ada
$mnt += [
    'aset_kat'         => null,
    'aset_merek'       => null,
    'model_aset'       => null,
    'aset_serial'      => null,
    'jenis_aset'       => 'Non-Medis',
    'aset_lokasi'      => null,
    'tahun_perolehan'  => null,
    'tek_divisi'       => null,
    'tek_hp'           => null,
];

function fmtTgl(?string $tgl): string {
    if (!$tgl || $tgl === '0000-00-00') return '---';
    $bln = ['','Januari','Februari','Maret','April','Mei','Juni',
            'Juli','Agustus','September','Oktober','November','Desember'];
    list($y,$m,$d) = explode('-', $tgl);
    return (int)$d . ' ' . ($bln[(int)$m] ?? '?') . ' ' . $y;
}
function fmtRp($n): string {
    return ($n !== null && $n !== '') ? 'Rp ' . number_format((int)$n, 0, ',', '.') : '---';
}
function x(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

$status_map = [
    'Selesai'      => ['#dcfce7','#166534','#bbf7d0'],
    'Dalam Proses' => ['#dbeafe','#1e40af','#bfdbfe'],
    'Ditunda'      => ['#fef9c3','#854d0e','#fde68a'],
    'Dibatalkan'   => ['#fee2e2','#991b1b','#fecaca'],
];
$kondisi_map = [
    'Baik'            => ['#dcfce7','#166534'],
    'Rusak'           => ['#fee2e2','#991b1b'],
    'Dalam Perbaikan' => ['#fef9c3','#854d0e'],
    'Tidak Aktif'     => ['#f1f5f9','#475569'],
];

list($s_bg,$s_tc,$s_br) = $status_map[$mnt['status']]           ?? ['#f1f5f9','#475569','#e2e8f0'];
list($ksbl_bg,$ksbl_tc) = $kondisi_map[$mnt['kondisi_sebelum']] ?? ['#f1f5f9','#475569'];
list($kssd_bg,$kssd_tc) = $kondisi_map[$mnt['kondisi_sesudah']] ?? ['#f1f5f9','#475569'];

$countdown_txt = '';
$countdown_col = '#64748b';
if (!empty($mnt['tgl_maintenance_berikut'])) {
    $sisa = (int)floor((strtotime($mnt['tgl_maintenance_berikut']) - time()) / 86400);
    if ($sisa < 0)       { $countdown_txt = 'Terlambat ' . abs($sisa) . ' hari'; $countdown_col = '#ef4444'; }
    elseif ($sisa <= 14) { $countdown_txt = $sisa . ' hari lagi'; $countdown_col = '#f59e0b'; }
    else                 { $countdown_txt = $sisa . ' hari lagi'; $countdown_col = '#22c55e'; }
}

$nama_aset   = $mnt['aset_nama_db']  ?: ($mnt['aset_nama'] ?? '---');
$nama_tek    = $mnt['tek_nama_db']   ?: ($mnt['teknisi_nama'] ?? '---');
$no_inv      = $mnt['no_inventaris'] ?: '---';
$merek_model = trim(($mnt['aset_merek'] ?? '') . ' ' . ($mnt['model_aset'] ?? '')) ?: '---';
$jenis_aset  = $mnt['jenis_aset']    ?: 'Non-Medis';
$lokasi      = $mnt['aset_lokasi']   ?: '---';
$app_name    = defined('APP_NAME') ? APP_NAME : 'FixSmart Helpdesk';

// Warna tema IPSRS = oranye (beda dari IT yang hijau)
$ipsrs_primary = '#ea580c';
$ipsrs_dark    = '#7c2d12';
$ipsrs_light   = '#fff7ed';
$ipsrs_border  = '#fed7aa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kartu Maintenance IPSRS - <?= x($mnt['no_maintenance']) ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { font-size:13px; }
body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f4f8; color:#1e293b; padding:24px 16px; }

/* ── Toolbar ── */
.toolbar { max-width:820px; margin:0 auto 14px; display:flex; align-items:center; justify-content:space-between; gap:10px; background:#1e293b; border-radius:8px; padding:10px 16px; }
.toolbar .tb-info { font-size:12px; color:rgba(255,255,255,.6); display:flex; align-items:center; gap:8px; }
.toolbar .tb-info strong { color:#fff; }
.toolbar .tb-btns { display:flex; gap:8px; }
.btn-print { display:inline-flex; align-items:center; gap:6px; padding:7px 18px; background:linear-gradient(135deg,#f97316,#ea580c); color:#fff; border:none; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; font-family:inherit; }
.btn-print:hover { opacity:.85; }
.btn-back  { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; background:rgba(255,255,255,.1); color:rgba(255,255,255,.8); border:1px solid rgba(255,255,255,.2); border-radius:6px; font-size:12px; cursor:pointer; font-family:inherit; text-decoration:none; }
.btn-back:hover { background:rgba(255,255,255,.2); }

/* ── Kartu ── */
.kartu { max-width:820px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 8px 32px rgba(0,0,0,.12); overflow:hidden; }

/* ── Header ── */
.kartu-header { background:linear-gradient(135deg,#3b1206 0%,#7c2d12 40%,#c2410c 100%); padding:18px 22px; display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
.kh-logo { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.kh-logo-box { width:36px; height:36px; background:rgba(249,115,22,.25); border:1.5px solid rgba(249,115,22,.6); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:900; color:#fed7aa; flex-shrink:0; }
.kh-appname { font-size:13px; font-weight:700; color:#fff; }
.kh-appsub  { font-size:9.5px; color:rgba(255,255,255,.45); margin-top:2px; }
.kh-judul   { font-size:18px; font-weight:800; color:#fff; }
.kh-sub     { font-size:10px; color:rgba(255,255,255,.5); margin-top:3px; }
.kh-right   { text-align:right; flex-shrink:0; }
.no-mnt-box { background:rgba(249,115,22,.15); border:1px solid rgba(249,115,22,.45); border-radius:7px; padding:8px 14px; display:inline-block; min-width:180px; }
.no-mnt-label { font-size:8.5px; color:rgba(255,255,255,.45); text-transform:uppercase; letter-spacing:.8px; margin-bottom:3px; }
.no-mnt-val   { font-size:13.5px; font-weight:900; color:#fdba74; font-family:'Courier New',monospace; letter-spacing:.5px; }

/* ── Jenis aset chip ── */
.jenis-medis    { display:inline-flex; align-items:center; gap:3px; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:700; background:#fce7f3; color:#9d174d; border:1px solid #fbcfe8; }
.jenis-non      { display:inline-flex; align-items:center; gap:3px; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:700; background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }

/* ── Sections ── */
.section { border-bottom:1px solid #e8ecf0; }
.section:last-of-type { border-bottom:none; }
.sec-title { background:#fff7ed; border-bottom:1px solid #fed7aa; padding:8px 20px; font-size:10px; font-weight:800; color:#9a3412; text-transform:uppercase; letter-spacing:.7px; display:flex; align-items:center; gap:7px; }
.sec-title .dot { width:7px; height:7px; border-radius:50%; background:#f97316; display:inline-block; flex-shrink:0; }
.sec-body { padding:14px 20px; }

/* ── Grid ── */
.g2 { display:grid; grid-template-columns:1fr 1fr; gap:0 24px; }
.g3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:0 18px; }

/* ── Data rows ── */
.dr { margin-bottom:10px; }
.dr:last-child { margin-bottom:0; }
.dr-lbl { font-size:9.5px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-bottom:2px; }
.dr-val { font-size:12px; font-weight:600; color:#1e293b; line-height:1.4; }
.dr-val.mono { font-family:'Courier New',monospace; font-size:11.5px; }
.dr-val small { font-size:10.5px; color:#94a3b8; font-weight:400; }

/* ── Badge ── */
.badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:10.5px; font-weight:700; }

/* ── Kondisi arrow ── */
.kondisi-row  { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.kondisi-lbl  { font-size:8.5px; color:#94a3b8; text-transform:uppercase; letter-spacing:.4px; margin-bottom:3px; }
.arrow        { font-size:18px; color:#94a3b8; line-height:1; }

/* ── Teks box ── */
.teks-title { font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; }
.teks-box   { background:#f8fafc; border:1px solid #e8ecf0; border-radius:6px; padding:10px 12px; font-size:11.5px; color:#374151; line-height:1.7; min-height:54px; white-space:pre-wrap; word-break:break-word; }
.teks-box.kosong { color:#cbd5e1; font-style:italic; }

/* ── Pengingat box ── */
.remind-box   { margin:0 20px 16px; background:linear-gradient(135deg,#fffbeb,#fef3c7); border:1.5px solid #fde68a; border-radius:8px; padding:14px 18px; display:flex; align-items:center; justify-content:space-between; gap:14px; }
.remind-icon  { width:40px; height:40px; background:#f59e0b; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:17px; color:#fff; flex-shrink:0; }
.remind-body  { flex:1; }
.remind-label { font-size:9.5px; font-weight:700; color:#78350f; text-transform:uppercase; letter-spacing:.6px; }
.remind-date  { font-size:16px; font-weight:800; color:#1e293b; margin-top:2px; }
.remind-cd    { font-size:10px; font-weight:700; padding:4px 12px; border-radius:12px; background:#fff; border:1px solid #fde68a; text-align:center; white-space:nowrap; }
.remind-cd small { display:block; font-size:9px; font-weight:400; color:#a16207; margin-top:1px; }

/* ── Informasi IPSRS banner ── */
.ipsrs-banner { margin:0 20px 6px; background:linear-gradient(135deg,#fff7ed,#ffedd5); border:1px solid #fed7aa; border-radius:7px; padding:9px 14px; display:flex; align-items:center; gap:10px; }
.ipsrs-banner i { color:#f97316; font-size:16px; flex-shrink:0; }
.ipsrs-banner-txt { font-size:11px; color:#9a3412; }
.ipsrs-banner-txt strong { color:#7c2d12; }

/* ── TTD ── */
.ttd-wrap { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:16px 20px; border-top:1px solid #e8ecf0; }
.ttd-box  { border:1px solid #e2e8f0; border-radius:7px; padding:10px 14px; text-align:center; }
.ttd-title { font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; margin-bottom:50px; }
.ttd-garis { border-top:1px dashed #e2e8f0; padding-top:6px; }
.ttd-nama  { font-size:11px; font-weight:700; color:#1e293b; }
.ttd-sub   { font-size:9.5px; color:#94a3b8; margin-top:2px; }

/* ── Footer ── */
.kartu-footer { background:#fff7ed; border-top:2px solid #fed7aa; padding:9px 20px; display:flex; align-items:center; justify-content:space-between; }
.kartu-footer .kf-l { font-size:10px; color:#9a3412; }
.kartu-footer .kf-l strong { color:#7c2d12; }
.kartu-footer .kf-r { font-size:10px; color:#c2410c; font-weight:600; }

.divider { height:1px; background:#e8ecf0; margin:10px 0; }

/* ── Biaya highlight ── */
.biaya-box { display:inline-flex; align-items:center; gap:6px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:4px 10px; }
.biaya-box span { font-size:13px; font-weight:800; color:#15803d; }

@media print {
    *{ -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
    body { background:#fff !important; padding:0 !important; }
    .toolbar { display:none !important; }
    .kartu { max-width:100% !important; box-shadow:none !important; border-radius:0 !important; }
    @page { size:A4 portrait; margin:10mm 12mm; }
}
</style>
</head>
<body>

<!-- ══ Toolbar ══ -->
<div class="toolbar">
  <div class="tb-info">
    <i class="fa fa-wrench" style="color:#f97316;font-size:14px;"></i>
    Kartu Maintenance IPSRS &mdash; <strong><?= x($mnt['no_maintenance']) ?></strong>
  </div>
  <div class="tb-btns">
    <a href="javascript:history.back()" class="btn-back">&#8592; Kembali</a>
    <button onclick="window.print()" class="btn-print">
      <i class="fa fa-print"></i> Cetak / Simpan PDF
    </button>
  </div>
</div>

<!-- ══ KARTU ══ -->
<div class="kartu">

  <!-- ── Header ── -->
  <div class="kartu-header">
    <div>
      <div class="kh-logo">
        <div class="kh-logo-box">FS</div>
        <div>
          <div class="kh-appname"><?= x($app_name) ?></div>
          <div class="kh-appsub">Work Order &amp; Asset Management System</div>
        </div>
      </div>
      <div class="kh-judul">Kartu Maintenance IPSRS</div>
      <div class="kh-sub" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        Dokumen catatan perawatan dan pemeliharaan aset IPSRS
        <?php if ($jenis_aset === 'Medis'): ?>
          &nbsp;<span style="background:rgba(249,115,22,.25);border:1px solid rgba(249,115,22,.5);color:#fed7aa;padding:1px 9px;border-radius:12px;font-size:9.5px;font-weight:700;">
            <i class="fa fa-kit-medical"></i> Alat Medis
          </span>
        <?php else: ?>
          &nbsp;<span style="background:rgba(249,115,22,.15);border:1px solid rgba(249,115,22,.4);color:#fdba74;padding:1px 9px;border-radius:12px;font-size:9.5px;font-weight:700;">
            <i class="fa fa-screwdriver-wrench"></i> Non-Medis / Sarpras
          </span>
        <?php endif; ?>
      </div>
    </div>
    <div class="kh-right">
      <div class="no-mnt-box">
        <div class="no-mnt-label">No. Maintenance</div>
        <div class="no-mnt-val"><?= x($mnt['no_maintenance']) ?></div>
      </div>
      <div style="margin-top:7px;display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
        <span class="badge" style="background:<?= $s_bg ?>;color:<?= $s_tc ?>;border:1px solid <?= $s_br ?>;">
          <?= x($mnt['status']) ?>
        </span>
        <span style="font-size:9px;color:rgba(255,255,255,.35);">
          <?= x($mnt['jenis_maintenance'] ?? '---') ?>
        </span>
      </div>
    </div>
  </div>

  <!-- ── Informasi Aset IPSRS ── -->
  <div class="section">
    <div class="sec-title"><span class="dot"></span> Informasi Aset IPSRS</div>
    <div class="sec-body">
      <div class="g2">
        <div>
          <div class="dr">
            <div class="dr-lbl">Nama Aset</div>
            <div class="dr-val" style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
              <?= x($nama_aset) ?>
              <?php if ($jenis_aset === 'Medis'): ?>
                <span class="jenis-medis"><i class="fa fa-kit-medical"></i> Medis</span>
              <?php else: ?>
                <span class="jenis-non"><i class="fa fa-screwdriver-wrench"></i> Non-Medis</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="dr">
            <div class="dr-lbl">No. Inventaris</div>
            <div class="dr-val mono"><?= x($no_inv) ?></div>
          </div>
          <div class="dr">
            <div class="dr-lbl">Kategori</div>
            <div class="dr-val"><?= x($mnt['aset_kat'] ?? '---') ?></div>
          </div>
          <?php if (!empty($mnt['tahun_perolehan'])): ?>
          <div class="dr">
            <div class="dr-lbl">Tahun Perolehan</div>
            <div class="dr-val"><?= x($mnt['tahun_perolehan']) ?></div>
          </div>
          <?php endif; ?>
        </div>
        <div>
          <div class="dr">
            <div class="dr-lbl">Merek / Model</div>
            <div class="dr-val"><?= x($merek_model) ?></div>
          </div>
          <div class="dr">
            <div class="dr-lbl">Serial Number</div>
            <div class="dr-val mono"><?= x($mnt['aset_serial'] ?? '---') ?></div>
          </div>
          <div class="dr">
            <div class="dr-lbl">Lokasi</div>
            <div class="dr-val"><?= x($lokasi) ?></div>
          </div>
          <div class="dr">
            <div class="dr-lbl">Kondisi Saat Ini</div>
            <?php
            list($kskrg_bg,$kskrg_tc) = $kondisi_map[$mnt['aset_kondisi_skrg'] ?? ''] ?? ['#f1f5f9','#475569'];
            ?>
            <div class="dr-val">
              <span class="badge" style="background:<?= $kskrg_bg ?>;color:<?= $kskrg_tc ?>;">
                <?= x($mnt['aset_kondisi_skrg'] ?? '---') ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Detail Maintenance ── -->
  <div class="section">
    <div class="sec-title"><span class="dot"></span> Detail Pelaksanaan Maintenance</div>
    <div class="sec-body">
      <div class="g3" style="margin-bottom:12px;">
        <div>
          <div class="dr">
            <div class="dr-lbl">Tanggal Maintenance</div>
            <div class="dr-val"><?= fmtTgl($mnt['tgl_maintenance']) ?></div>
          </div>
          <div class="dr">
            <div class="dr-lbl">Jenis Maintenance</div>
            <div class="dr-val"><?= x($mnt['jenis_maintenance'] ?? '---') ?></div>
          </div>
        </div>
        <div>
          <div class="dr">
            <div class="dr-lbl">Teknisi Pelaksana</div>
            <div class="dr-val">
              <?= x($nama_tek) ?>
              <?php if (!empty($mnt['tek_divisi'])): ?>
                <br><small><?= x($mnt['tek_divisi']) ?></small>
              <?php endif; ?>
            </div>
          </div>
          <div class="dr">
            <div class="dr-lbl">No. HP Teknisi</div>
            <div class="dr-val"><?= x($mnt['tek_hp'] ?? '---') ?></div>
          </div>
        </div>
        <div>
          <div class="dr">
            <div class="dr-lbl">Biaya Maintenance</div>
            <div class="dr-val">
              <?php if ($mnt['biaya']): ?>
                <div class="biaya-box"><i class="fa fa-receipt" style="color:#16a34a;font-size:11px;"></i><span><?= fmtRp($mnt['biaya']) ?></span></div>
              <?php else: ?>
                <span style="color:#94a3b8;font-style:italic;font-size:11.5px;">Tidak ada biaya</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="dr">
            <div class="dr-lbl">Dicatat Oleh</div>
            <div class="dr-val"><?= x($mnt['dibuat_oleh'] ?? '---') ?></div>
          </div>
        </div>
      </div>

      <div class="divider"></div>

      <!-- Perubahan kondisi -->
      <div class="dr">
        <div class="dr-lbl" style="margin-bottom:7px;">Perubahan Kondisi Aset</div>
        <div class="kondisi-row">
          <div>
            <div class="kondisi-lbl">Kondisi Sebelum</div>
            <span class="badge" style="background:<?= $ksbl_bg ?>;color:<?= $ksbl_tc ?>;">
              <?= x($mnt['kondisi_sebelum'] ?? '---') ?>
            </span>
          </div>
          <div class="arrow">&#8594;</div>
          <div>
            <div class="kondisi-lbl">Kondisi Sesudah</div>
            <span class="badge" style="background:<?= $kssd_bg ?>;color:<?= $kssd_tc ?>;">
              <?= x($mnt['kondisi_sesudah'] ?? '---') ?>
            </span>
          </div>
          <?php if ($mnt['kondisi_sebelum'] && $mnt['kondisi_sesudah'] && $mnt['kondisi_sebelum'] !== $mnt['kondisi_sesudah']): ?>
          <div style="font-size:10.5px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:5px;padding:3px 9px;color:#166534;font-weight:600;">
            <i class="fa fa-arrow-trend-up"></i> Kondisi diperbarui
          </div>
          <?php elseif ($mnt['kondisi_sebelum'] && $mnt['kondisi_sesudah'] && $mnt['kondisi_sebelum'] === $mnt['kondisi_sesudah']): ?>
          <div style="font-size:10.5px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:5px;padding:3px 9px;color:#64748b;font-weight:600;">
            <i class="fa fa-equals"></i> Kondisi tetap
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Temuan & Tindakan ── -->
  <div class="section">
    <div class="sec-title"><span class="dot"></span> Temuan &amp; Tindakan</div>
    <div class="sec-body">
      <div class="g2" style="gap:14px;">
        <div>
          <div class="teks-title"><i class="fa fa-magnifying-glass" style="color:#f97316;margin-right:4px;"></i> Temuan / Masalah</div>
          <div class="teks-box <?= $mnt['temuan'] ? '' : 'kosong' ?>">
            <?= $mnt['temuan'] ? x($mnt['temuan']) : 'Tidak ada temuan khusus.' ?>
          </div>
        </div>
        <div>
          <div class="teks-title"><i class="fa fa-list-check" style="color:#f97316;margin-right:4px;"></i> Tindakan yang Dilakukan</div>
          <div class="teks-box <?= $mnt['tindakan'] ? '' : 'kosong' ?>">
            <?= $mnt['tindakan'] ? x($mnt['tindakan']) : 'Tidak ada tindakan khusus.' ?>
          </div>
        </div>
      </div>

      <?php if (!empty($mnt['keterangan'])): ?>
      <div style="margin-top:12px;">
        <div class="teks-title"><i class="fa fa-note-sticky" style="color:#f97316;margin-right:4px;"></i> Keterangan Tambahan</div>
        <div class="teks-box"><?= x($mnt['keterangan']) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Pengingat Berikutnya ── -->
  <div class="section" style="border-bottom:none;">
    <div class="sec-title">
      <span class="dot" style="background:#f59e0b;"></span>
      Pengingat Maintenance Berikutnya
    </div>
    <div style="padding:14px 20px 6px;">
      <div class="remind-box">
        <div class="remind-icon"><i class="fa fa-bell"></i></div>
        <div class="remind-body">
          <div class="remind-label">Jadwal Selanjutnya &mdash; Siklus 3 Bulan</div>
          <div class="remind-date"><?= fmtTgl($mnt['tgl_maintenance_berikut']) ?></div>
          <div style="font-size:10px;color:#78350f;margin-top:3px;">
            Dihitung otomatis 3 bulan dari tanggal maintenance: <strong><?= fmtTgl($mnt['tgl_maintenance']) ?></strong>
          </div>
        </div>
        <?php if ($countdown_txt): ?>
        <div class="remind-cd" style="color:<?= $countdown_col ?>;">
          <?= x($countdown_txt) ?>
          <small>dari hari ini</small>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── TTD ── -->
  <div class="ttd-wrap">
    <div class="ttd-box">
      <div class="ttd-title">Teknisi Pelaksana IPSRS</div>
      <div class="ttd-garis">
        <div class="ttd-nama"><?= x($nama_tek ?: '..................................') ?></div>
        <div class="ttd-sub"><?= x($mnt['tek_divisi'] ?? 'Teknisi IPSRS') ?></div>
      </div>
    </div>
    <div class="ttd-box">
      <div class="ttd-title">Mengetahui / Menyetujui</div>
      <div class="ttd-garis">
        <div class="ttd-nama">..................................</div>
        <div class="ttd-sub">Kepala Bagian IPSRS</div>
      </div>
    </div>
  </div>

  <!-- ── Footer ── -->
  <div class="kartu-footer">
    <div class="kf-l">
      Dicetak: <strong><?= date('d M Y, H:i') ?> WIB</strong>
      &nbsp;&bull;&nbsp; Oleh: <strong><?= x($mnt['dibuat_oleh'] ?? '---') ?></strong>
      &nbsp;&bull;&nbsp; Dokumen: <strong><?= x($mnt['no_maintenance']) ?></strong>
    </div>
    <div class="kf-r">
      <i class="fa fa-wrench" style="margin-right:4px;"></i>
      <?= x($app_name) ?> &bull; Dokumen Resmi Maintenance IPSRS
    </div>
  </div>

</div><!-- /kartu -->

<script>
<?php if (isset($_GET['autoprint'])): ?>
window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 400); });
<?php endif; ?>
</script>

</body>
</html>