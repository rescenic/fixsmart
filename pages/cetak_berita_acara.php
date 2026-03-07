<?php
/**
 * cetak_berita_acara.php — FixSmart Helpdesk
 * Cetak Berita Acara Tidak Bisa Ditangani
 */
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

$dompdf_available = false; $dp_autoload = '';
foreach ([
    __DIR__ . '/../dompdf/autoload.inc.php',
    __DIR__ . '/../dompdf/vendor/autoload.php',
    __DIR__ . '/../vendor/dompdf/dompdf/autoload.inc.php',
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__) . '/dompdf/autoload.inc.php',
    dirname(__DIR__) . '/vendor/autoload.php',
] as $p) { if (file_exists($p)) { $dp_autoload=$p; $dompdf_available=true; break; } }

$tiket_id = (int)($_GET['tiket_id'] ?? 0);
if (!$tiket_id) die('Parameter tidak valid.');

$stm = $pdo->prepare("
    SELECT t.*, k.nama AS kat_nama,
           u.nama AS req_nama, u.divisi AS req_divisi,
           tek.nama AS tek_nama, tek.divisi AS tek_divisi,
           ba.id AS ba_id, ba.nomor_ba, ba.tanggal_ba, ba.jenis_tindak,
           ba.uraian_masalah, ba.kesimpulan, ba.tindak_lanjut,
           ba.nilai_estimasi, ba.diketahui_nama, ba.diketahui_jabatan,
           ba.mengetahui_nama, ba.mengetahui_jabatan, ba.catatan_tambahan,
           pb.nama AS ba_dibuat_nama, pb.divisi AS ba_dibuat_divisi
    FROM tiket t
    LEFT JOIN kategori k  ON k.id = t.kategori_id
    LEFT JOIN users u     ON u.id = t.user_id
    LEFT JOIN users tek   ON tek.id = t.teknisi_id
    LEFT JOIN berita_acara ba ON ba.tiket_id = t.id
    LEFT JOIN users pb    ON pb.id = ba.dibuat_oleh
    WHERE t.id = ? AND t.status = 'tidak_bisa'
    LIMIT 1
");
$stm->execute([$tiket_id]);
$d = $stm->fetch(PDO::FETCH_ASSOC);

if (!$d)         die('<p style="font-family:Arial;color:red;padding:20px;">Tiket tidak ditemukan atau bukan status Tidak Bisa Ditangani.</p>');
if (!$d['ba_id']) die('<p style="font-family:Arial;color:red;padding:20px;">Berita Acara belum dibuat untuk tiket ini.</p>');

function jenisTindakLabel(string $j): string {
    return match($j) {
        'pembelian_baru'          => 'Pengajuan Pembelian Perangkat Baru',
        'perbaikan_eksternal'     => 'Perbaikan oleh Pihak Eksternal / Vendor',
        'penghapusan_aset'        => 'Penghapusan Aset (Write-Off)',
        'penggantian_suku_cadang' => 'Penggantian Suku Cadang',
        'lainnya'                 => 'Tindak Lanjut Lainnya',
        default                   => ucwords(str_replace('_',' ',$j)),
    };
}

$jenis_label = jenisTindakLabel($d['jenis_tindak']);
$tgl_ba      = date('d F Y', strtotime($d['tanggal_ba']));
$tgl_tiket   = date('d F Y, H:i', strtotime($d['waktu_submit']));
$harga_str   = $d['nilai_estimasi'] ? 'Rp ' . number_format((int)$d['nilai_estimasi'], 0, ',', '.') : '';

ob_start();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Berita Acara - <?= htmlspecialchars($d['nomor_ba']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #111;
    background: #fff;
    line-height: 1.55;
}
.doc-wrap { margin: 30px 40px; }

/* ── TOOLBAR (screen only) ── */
.toolbar {
    background: #0f172a;
    padding: 11px 20px;
    display: table;
    width: 100%;
    margin-bottom: 24px;
}
.tb-l { display:table-cell; vertical-align:middle; }
.tb-l .t1 { color:#00e5b0; font-size:13px; font-weight:bold; }
.tb-l .t2 { color:rgba(255,255,255,.45); font-size:11px; margin-top:2px; }
.tb-r { display:table-cell; vertical-align:middle; text-align:right; }
.btn-back {
    display:inline-block; padding:7px 16px;
    background:rgba(255,255,255,.09); color:rgba(255,255,255,.75);
    border:1px solid rgba(255,255,255,.15); border-radius:6px;
    font-size:12px; cursor:pointer; font-family:inherit;
    text-decoration:none; margin-right:8px;
}
.btn-print {
    display:inline-block; padding:8px 20px;
    background:#00e5b0; color:#0a0f14;
    border:none; border-radius:6px;
    font-size:12px; font-weight:700; cursor:pointer; font-family:inherit;
}

/* ── KOP ── */
.kop { display:table; width:100%; border-bottom:3px solid #0f172a; padding-bottom:10px; margin-bottom:3px; }
.kop-logo  { display:table-cell; vertical-align:middle; width:52px; }
.logo-box  { width:44px; height:44px; background:#0f172a; border-radius:5px;
             text-align:center; line-height:44px; color:#fff; font-size:15px; font-weight:bold; }
.kop-mid   { display:table-cell; vertical-align:middle; padding-left:11px; }
.kop-nama  { font-size:14pt; font-weight:bold; color:#0f172a; }
.kop-sub   { font-size:8pt; color:#555; margin-top:1px; }
.kop-right { display:table-cell; vertical-align:middle; text-align:right; white-space:nowrap; }
.kop-right .lbl { font-size:7pt; color:#aaa; text-transform:uppercase; letter-spacing:1px; }
.kop-right .val { font-size:9pt; font-weight:bold; color:#222; }
.kop-line2 { height:1px; background:#ddd; margin:6px 0 18px; }

/* ── JUDUL ── */
.judul { text-align:center; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid #ddd; }
.judul .surat-ket { font-size:7.5pt; letter-spacing:3px; text-transform:uppercase; color:#777; margin-bottom:5px; }
.judul .judul-besar { font-size:15pt; font-weight:bold; color:#0f172a; text-transform:uppercase; letter-spacing:1px; margin-bottom:3px; }
.judul .judul-sub   { font-size:9.5pt; font-weight:bold; color:#dc2626; }
.judul .judul-meta  { font-size:8pt; color:#aaa; margin-top:5px; }

/* ── STATUS BANNER ── */
.status-banner {
    background:#fff5f5; border-left:5px solid #dc2626;
    padding:10px 14px; margin-bottom:18px; border-radius:0 5px 5px 0;
}
.status-banner .sb-title { font-size:10.5pt; font-weight:bold; color:#dc2626; margin-bottom:3px; }
.status-banner .sb-body  { font-size:9pt; color:#555; line-height:1.65; }
.status-banner .sb-body strong { color:#111; }

/* ── SECTION HEADER ── */
.sec-hd {
    margin-top:18px; margin-bottom:9px;
    border-bottom:2px solid #1d4ed8; padding-bottom:4px;
    display:table; width:100%;
}
.sec-hd-l { display:table-cell; font-size:10pt; font-weight:bold; color:#0f172a; }
.sec-hd-r { display:table-cell; text-align:right; font-size:7.5pt; color:#aaa; vertical-align:bottom; font-style:italic; }

/* ── INFO TABLE ── */
.info-tbl { width:100%; border-collapse:collapse; font-size:9pt; }
.info-tbl td { padding:5px 9px; border:1px solid #e2e8f0; vertical-align:top; }
.info-tbl .lbl { background:#f8fafc; color:#444; font-weight:bold; width:22%; }
.info-tbl .val { color:#111; }

/* ── CONTENT BOX ── */
.content-box {
    background:#f8fafc; border:1px solid #e2e8f0;
    border-radius:4px; padding:11px 13px;
    font-size:9.5pt; color:#111; line-height:1.75;
}
.note-box {
    margin-top:8px; padding:9px 12px;
    background:#fff8f8; border:1px solid #fca5a5;
    border-radius:4px; font-size:9pt; color:#991b1b;
}

/* ── TINDAK LANJUT ── */
.tl-wrap  { margin-top:10px; }
.tl-badge {
    display:inline-block; background:#1d4ed8; color:#fff;
    font-size:8pt; font-weight:bold; padding:3px 12px;
    border-radius:3px; letter-spacing:.5px; margin-bottom:9px;
    text-transform:uppercase;
}
.tl-content {
    background:#eef3ff; border:1px solid #bfdbfe;
    border-radius:4px; padding:11px 13px;
    font-size:9.5pt; color:#1e293b; line-height:1.75;
}

/* ── ESTIMASI BIAYA ── */
.est-wrap {
    margin-top:12px; background:#fffbeb;
    border:1px solid #f59e0b; border-radius:4px;
    padding:10px 14px; display:table; width:100%;
}
.est-label-cell { display:table-cell; vertical-align:middle; }
.est-label { font-size:8pt; font-weight:bold; color:#92400e; text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
.est-value { font-size:14pt; font-weight:bold; color:#b45309; }
.est-right { display:table-cell; vertical-align:middle; text-align:right; }
.est-note  { font-size:8pt; color:#b45309; font-style:italic; }

/* ── CATATAN TAMBAHAN ── */
.catatan-wrap {
    margin-top:12px; background:#fefce8;
    border:1px solid #fde68a; border-radius:4px;
    padding:10px 13px; font-size:9pt; color:#713f12; line-height:1.7;
}
.catatan-wrap .c-title { font-weight:bold; margin-bottom:4px; text-transform:uppercase; font-size:8pt; letter-spacing:.5px; }

/* ── TANDA TANGAN ── */
.ttd-wrap { margin-top:24px; }
.ttd-table { width:100%; border-collapse:collapse; }
.ttd-td { width:33.3%; text-align:center; padding:0 10px; vertical-align:top; }
.ttd-role { font-size:8.5pt; color:#555; margin-bottom:50px; }
.ttd-line { border-top:1px solid #334155; margin:0 10px 5px; }
.ttd-nama { font-size:9pt; font-weight:bold; color:#0f172a; }
.ttd-jabatan { font-size:8pt; color:#64748b; margin-top:2px; }

/* ── FOOTER ── */
.doc-footer {
    margin-top:22px; padding-top:8px;
    border-top:1px solid #ddd; display:table; width:100%;
}
.df-l { display:table-cell; font-size:7.5pt; color:#aaa; vertical-align:middle; }
.df-r { display:table-cell; text-align:right; font-size:7.5pt; color:#aaa; vertical-align:middle; }

/* ── PRINT ── */
@media print {
    * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
    @page { size:A4 portrait; margin:15mm 18mm; }
    .toolbar { display:none !important; }
    .doc-wrap { margin:0; }
    body { background:#fff; }
}
</style>
</head>
<body>

<!-- TOOLBAR - layar saja, hilang saat print -->
<div class="toolbar">
  <div class="tb-l">
    <div class="t1">FixSmart Helpdesk</div>
    <div class="t2">Berita Acara &mdash; <?= htmlspecialchars($d['nomor_ba']) ?></div>
  </div>
  
</div>

<div class="doc-wrap">

<!-- KOP SURAT -->
<div class="kop">
  <div class="kop-logo"><div class="logo-box">FS</div></div>
  <div class="kop-mid">
    <div class="kop-nama">FixSmart Helpdesk</div>
    <div class="kop-sub">Divisi Teknologi Informasi &mdash; Sistem Manajemen Layanan IT</div>
  </div>
  <div class="kop-right">
    <div class="lbl">No. Dokumen</div>
    <div class="val"><?= htmlspecialchars($d['nomor_ba']) ?></div>
    <div style="margin-top:5px;">
      <div class="lbl">Tanggal</div>
      <div class="val"><?= $tgl_ba ?></div>
    </div>
  </div>
</div>
<div class="kop-line2"></div>

<!-- JUDUL -->
<div class="judul">
  <div class="surat-ket">Dokumen Resmi &mdash; Internal</div>
  <div class="judul-besar">Berita Acara</div>
  <div class="judul-sub">Tindak Lanjut Perbaikan/Pemeriksaan</div>
  <div class="judul-meta">
    Dibuat oleh: <?= htmlspecialchars($d['ba_dibuat_nama'] ?? '-') ?>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    Dicetak: <?= date('d F Y, H:i') ?> WIB
  </div>
</div>

<!-- STATUS BANNER -->
<div class="status-banner">
  <div class="sb-title">Tiket Tidak Dapat Ditangani oleh Tim IT Internal</div>
  <div class="sb-body">
    Tiket <strong><?= htmlspecialchars($d['nomor']) ?></strong>
    dinyatakan tidak dapat diselesaikan oleh tim IT internal dan memerlukan tindak lanjut:
    <strong><?= htmlspecialchars($jenis_label) ?></strong>
  </div>
</div>

<!-- I. INFORMASI TIKET -->
<div class="sec-hd">
  <div class="sec-hd-l">I.&nbsp; Informasi Tiket</div>
  <div class="sec-hd-r">Ticket Information</div>
</div>
<table class="info-tbl">
  <tr>
    <td class="lbl">Nomor Tiket</td>
    <td class="val"><strong><?= htmlspecialchars($d['nomor']) ?></strong></td>
    <td class="lbl">Tanggal Submit</td>
    <td class="val"><?= $tgl_tiket ?></td>
  </tr>
  <tr>
    <td class="lbl">Judul / Keluhan</td>
    <td class="val" colspan="3"><strong><?= htmlspecialchars($d['judul']) ?></strong></td>
  </tr>
  <tr>
    <td class="lbl">Kategori</td>
    <td class="val"><?= htmlspecialchars($d['kat_nama'] ?? '-') ?></td>
    <td class="lbl">Prioritas</td>
    <td class="val"><?= htmlspecialchars($d['prioritas']) ?></td>
  </tr>
  <tr>
    <td class="lbl">Lokasi</td>
    <td class="val"><?= htmlspecialchars($d['lokasi'] ?? '-') ?></td>
    <td class="lbl">Teknisi</td>
    <td class="val"><?= htmlspecialchars($d['tek_nama'] ?? '-') ?></td>
  </tr>
  <tr>
    <td class="lbl">Pemohon</td>
    <td class="val"><?= htmlspecialchars($d['req_nama'] ?? '-') ?></td>
    <td class="lbl">Divisi / Bagian</td>
    <td class="val"><?= htmlspecialchars($d['req_divisi'] ?? '-') ?></td>
  </tr>
</table>

<!-- II. URAIAN MASALAH -->
<div class="sec-hd">
  <div class="sec-hd-l">II.&nbsp; Uraian Permasalahan</div>
  <div class="sec-hd-r">Problem Description</div>
</div>
<div class="content-box">
  <?= nl2br(htmlspecialchars($d['uraian_masalah'] ?: ($d['deskripsi'] ?: '-'))) ?>
</div>

<!-- III. KESIMPULAN -->
<div class="sec-hd">
  <div class="sec-hd-l">III.&nbsp; Kesimpulan &amp; Analisa Teknis</div>
  <div class="sec-hd-r">Technical Analysis</div>
</div>
<div class="content-box">
  <?= nl2br(htmlspecialchars($d['kesimpulan'] ?: '-')) ?>
</div>
<?php if ($d['catatan_penolakan']): ?>
<div class="note-box">
  <strong>Keterangan sebelumnya dari teknisi:</strong><br>
  <?= nl2br(htmlspecialchars($d['catatan_penolakan'])) ?>
</div>
<?php endif; ?>

<!-- IV. TINDAK LANJUT -->
<div class="sec-hd">
  <div class="sec-hd-l">IV.&nbsp; Rekomendasi &amp; Tindak Lanjut</div>
  <div class="sec-hd-r">Action Plan</div>
</div>
<div class="tl-wrap">
  <div class="tl-badge"><?= htmlspecialchars($jenis_label) ?></div>
  <div class="tl-content">
    <?= nl2br(htmlspecialchars($d['tindak_lanjut'] ?: '-')) ?>
  </div>
</div>

<?php if ($d['nilai_estimasi']): ?>
<!-- ESTIMASI BIAYA -->
<div class="est-wrap">
  <div class="est-label-cell">
    <div class="est-label">Estimasi Biaya yang Diperlukan</div>
    <div class="est-value"><?= htmlspecialchars($harga_str) ?></div>
  </div>
  <div class="est-right">
    <div class="est-note">*estimasi awal, dapat berubah</div>
  </div>
</div>
<?php endif; ?>

<?php if ($d['catatan_tambahan']): ?>
<!-- CATATAN TAMBAHAN -->
<div class="catatan-wrap">
  <div class="c-title">Catatan Tambahan</div>
  <?= nl2br(htmlspecialchars($d['catatan_tambahan'])) ?>
</div>
<?php endif; ?>

<!-- V. TANDA TANGAN -->
<div class="sec-hd" style="margin-top:22px;">
  <div class="sec-hd-l">V.&nbsp; Persetujuan &amp; Tanda Tangan</div>
</div>
<div class="ttd-wrap">
  <table class="ttd-table">
    <tr>
      <td class="ttd-td">
        <div class="ttd-role">Dibuat Oleh,</div>
        <div class="ttd-line"></div>
        <div class="ttd-nama"><?= htmlspecialchars($d['ba_dibuat_nama'] ?? '_______________') ?></div>
        <div class="ttd-jabatan"><?= htmlspecialchars($d['ba_dibuat_divisi'] ?? 'Staff IT') ?></div>
      </td>
      <td class="ttd-td">
        <div class="ttd-role">Diketahui Oleh,</div>
        <div class="ttd-line"></div>
        <div class="ttd-nama"><?= htmlspecialchars($d['diketahui_nama'] ?: '_______________') ?></div>
        <div class="ttd-jabatan"><?= htmlspecialchars($d['diketahui_jabatan'] ?: 'Kepala Divisi IT') ?></div>
      </td>
      <td class="ttd-td">
        <div class="ttd-role">Menyetujui,</div>
        <div class="ttd-line"></div>
        <div class="ttd-nama"><?= htmlspecialchars($d['mengetahui_nama'] ?: '_______________') ?></div>
        <div class="ttd-jabatan"><?= htmlspecialchars($d['mengetahui_jabatan'] ?: 'Pimpinan / Manajer') ?></div>
      </td>
    </tr>
  </table>
</div>

<!-- FOOTER DOKUMEN -->
<div class="doc-footer">
  <div class="df-l">
    FixSmart Helpdesk &mdash; Berita Acara No. <?= htmlspecialchars($d['nomor_ba']) ?><br>
    Dokumen ini bersifat resmi dan hanya untuk keperluan internal.
  </div>
  <div class="df-r">
    Dicetak: <?= date('d/m/Y H:i') ?> WIB<br>
    Ref. Tiket: <?= htmlspecialchars($d['nomor']) ?>
  </div>
</div>

</div><!-- /doc-wrap -->

<script>
if (new URLSearchParams(window.location.search).get('autoprint') === '1')
    window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 600); });
</script>
</body>
</html>
<?php
$html = ob_get_clean();

if ($dompdf_available) {
    require_once $dp_autoload;
    $dC = class_exists('\Dompdf\Dompdf') ? '\Dompdf\Dompdf' : 'Dompdf\Dompdf';
    $oC = class_exists('\Dompdf\Options') ? '\Dompdf\Options' : 'Dompdf\Options';
    if (class_exists($dC)) {
        $opt = new $oC();
        $opt->set('isHtml5ParserEnabled', true);
        $opt->set('isRemoteEnabled', false);
        $opt->set('defaultFont', 'Arial');
        $dp = new $dC($opt);
        $dp->loadHtml($html, 'UTF-8');
        $dp->setPaper('A4', 'portrait');
        $dp->render();
        $fname = 'BA-' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $d['nomor_ba']) . '-' . date('Ymd') . '.pdf';
        $dp->stream($fname, ['Attachment' => false]);
        exit;
    }
}

echo $html;