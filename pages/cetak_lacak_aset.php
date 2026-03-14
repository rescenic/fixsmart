<?php
// pages/cetak_lacak_aset.php — Cetak Lacak Aset IT (PDF per aset)
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

$dompdf_path = __DIR__ . '/../dompdf/autoload.inc.php';
if (!file_exists($dompdf_path)) { die('dompdf tidak ditemukan.'); }
require_once $dompdf_path;

use Dompdf\Dompdf;
use Dompdf\Options;

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('ID aset tidak valid.');

// ── Data aset ─────────────────────────────────────────────────────────────────
$st = $pdo->prepare("
    SELECT a.*,
           b.nama AS bagian_nama, b.kode AS bagian_kode, b.lokasi AS bagian_lokasi,
           u.nama AS pj_nama_db, u.divisi AS pj_divisi
    FROM aset_it a
    LEFT JOIN bagian b ON b.id = a.bagian_id
    LEFT JOIN users  u ON u.id = a.pj_user_id
    WHERE a.id = ?
");
$st->execute([$id]);
$aset = $st->fetch();
if (!$aset) die('Aset tidak ditemukan.');

// ── Riwayat mutasi ────────────────────────────────────────────────────────────
$sm = $pdo->prepare("
    SELECT * FROM mutasi_aset
    WHERE aset_id = ?
    ORDER BY tanggal_mutasi ASC, created_at ASC
");
$sm->execute([$id]);
$mutasi = $sm->fetchAll();

$total_mut   = count($mutasi);
$mut_selesai = count(array_filter($mutasi, fn($m) => $m['status_mutasi'] === 'selesai'));

// ── Helper: Tanggal Bahasa Indonesia (TANPA setlocale, tanpa emoji) ──────────
$BULAN_ID = [
    1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
    5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
    9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember',
];

function tglId(string $raw, bool $withTime = false, array $bulan = []): string {
    global $BULAN_ID;
    if (!$bulan) $bulan = $BULAN_ID;
    $ts = strtotime($raw);
    if (!$ts) return '—';
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    $str = $d . ' ' . ($bulan[$m] ?? '?') . ' ' . $y;
    if ($withTime) $str .= ', ' . date('H:i', $ts);
    return $str;
}

// ── Helpers style ─────────────────────────────────────────────────────────────
function kondisiStyleL(string $k): array {
    return match ($k) {
        'Baik'            => ['#dcfce7', '#15803d', '#16a34a'],
        'Rusak'           => ['#fee2e2', '#b91c1c', '#ef4444'],
        'Dalam Perbaikan' => ['#fef9c3', '#a16207', '#f59e0b'],
        'Tidak Aktif'     => ['#f1f5f9', '#475569', '#94a3b8'],
        default           => ['#f1f5f9', '#475569', '#94a3b8'],
    };
}
function spStyleL(string $s): array {
    return match ($s) {
        'Terpakai'       => ['#dbeafe', '#1e40af'],
        'Tidak Terpakai' => ['#d1fae5', '#065f46'],
        'Dipinjam'       => ['#fef3c7', '#92400e'],
        default          => ['#f1f5f9', '#64748b'],
    };
}
function jenisLabelL(string $j): string {
    // HANYA karakter ASCII/latin — aman untuk dompdf
    return match ($j) {
        'keduanya'      => 'Pindah Lokasi + PIC',
        'pindah_lokasi' => 'Pindah Lokasi',
        'pindah_pic'    => 'Pindah PIC',
        default         => $j,
    };
}

$kst         = kondisiStyleL($aset['kondisi'] ?? '');
$sst         = spStyleL($aset['status_pakai'] ?? 'Terpakai');
$no_dok      = 'LACAK-' . preg_replace('/[^A-Z0-9\-]/', '', strtoupper($aset['no_inventaris'])) . '-' . date('Ymd');
$kondisi_pct = match ($aset['kondisi'] ?? '') { 'Baik' => 100, 'Dalam Perbaikan' => 50, 'Rusak' => 20, default => 0 };

$g_exp  = $aset['garansi_sampai'] && strtotime($aset['garansi_sampai']) < time();
$g_soon = $aset['garansi_sampai'] && !$g_exp && strtotime($aset['garansi_sampai']) < strtotime('+30 days');

// Tanggal cetak Indonesia
$tgl_cetak = tglId(date('Y-m-d H:i:s'), true);

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
    font-size: 9pt; color: #1a1a2e; background:#fff;
    line-height: 1.5; margin: 36px 44px;
}

/* ── KOP ── */
.kop           { display:table; width:100%; padding-bottom:10px; border-bottom:3px solid #0f172a; margin-bottom:4px; }
.kop-logo-area { display:table-cell; vertical-align:middle; width:56px; }
.kop-logo-box  { width:44px; height:44px; background:#0f172a; border-radius:6px; text-align:center; line-height:44px; font-size:18px; color:#fff; font-weight:bold; }
.kop-text      { display:table-cell; vertical-align:middle; padding-left:11px; }
.kop-org       { font-size:14pt; font-weight:bold; color:#0f172a; }
.kop-sub       { font-size:8pt; color:#475569; }
.kop-right     { display:table-cell; vertical-align:middle; text-align:right; }
.kop-rlabel    { font-size:7pt; color:#94a3b8; letter-spacing:1px; text-transform:uppercase; }
.kop-rval      { font-size:8.5pt; color:#334155; font-weight:bold; }
.kop-rule2     { height:1px; background:#cbd5e1; margin-bottom:14px; }

/* ── Judul ── */
.rpt-title { text-align:center; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #e2e8f0; }
.rpt-label { font-size:7pt; letter-spacing:3px; text-transform:uppercase; color:#64748b; margin-bottom:4px; }
.rpt-main  { font-size:14pt; font-weight:bold; color:#0f172a; text-transform:uppercase; margin-bottom:4px; }
.rpt-sub   { font-size:10pt; color:#4f46e5; font-weight:bold; }
.rpt-no    { font-size:7.5pt; color:#94a3b8; margin-top:4px; }

/* ── Aset Header ── */
.aset-hd       { background:#0f172a; border-radius:8px; padding:16px 18px; display:table; width:100%; margin-bottom:14px; }
.ah-stripe     { display:table-cell; vertical-align:middle; width:10px; }
.ah-stripe-bar { width:5px; height:50px; border-radius:3px; }
.ah-info       { display:table-cell; vertical-align:middle; padding-left:14px; }
.ah-inv        { font-family:'Courier New',monospace; font-size:8pt; font-weight:700; color:rgba(255,255,255,.4); margin-bottom:3px; }
.ah-nama       { font-size:14pt; font-weight:800; color:#fff; line-height:1.2; margin-bottom:5px; }
.ah-merek      { font-size:9pt; color:rgba(255,255,255,.45); }
.ah-chips      { display:table-cell; vertical-align:middle; text-align:right; padding-left:14px; }

/* ── Chip badge ── */
.chip { display:inline-block; padding:2px 8px; border-radius:20px; font-size:8pt; font-weight:bold; }

/* ── Info Tabel ── */
.info-tbl      { width:100%; border-collapse:collapse; margin-bottom:14px; border:1px solid #e2e8f0; }
.info-tbl td   { padding:6px 9px; border:1px solid #e2e8f0; font-size:8.5pt; vertical-align:top; }
.it-lbl        { background:#f8fafc; color:#475569; font-weight:bold; width:18%; font-size:8pt; }
.it-val        { color:#1e293b; }

/* ── Kondisi bar ── */
.kbar-wrap { background:#e2e8f0; height:6px; border-radius:3px; overflow:hidden; margin-top:4px; display:inline-block; width:180px; vertical-align:middle; }
.kbar-fill { height:6px; border-radius:3px; background:<?= $kst[2] ?>; width:<?= $kondisi_pct ?>%; }

/* ── Stat cards ── */
.stat-tbl   { width:100%; border-collapse:collapse; margin-bottom:14px; }
.stat-tbl td { border:none; vertical-align:middle; }
.stat-card  { padding:10px 14px; border-radius:7px; border-left:3px solid; text-align:center; }
.stat-val   { font-size:18pt; font-weight:800; line-height:1; margin-bottom:2px; }
.stat-lbl   { font-size:7.5pt; text-transform:uppercase; letter-spacing:.5px; }

/* ── Section ── */
.sec        { margin:14px 0 8px; }
.sec-title  { font-size:10pt; font-weight:bold; color:#0f172a; border-bottom:2px solid #4f46e5; padding-bottom:4px; margin-bottom:10px; }

/* ── Timeline ── */
.tl-wrap    { position:relative; padding-left:26px; }
.tl-line    {
    position:absolute; left:9px; top:8px; bottom:0;
    width:2px; background:#e2e8f0; border-radius:2px;
}

.tl-item     { position:relative; margin-bottom:12px; page-break-inside:avoid; }
.tl-dot      {
    position:absolute; left:-21px; top:6px;
    width:14px; height:14px; border-radius:50%;
    border:2px solid #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,.18);
}
.tl-dot-awal   { background:#4f46e5; }
.tl-dot-mutasi { background:#00c896; }
.tl-dot-kini   { background:#f59e0b; }
.tl-dot-batal  { background:#ef4444; }

.tl-card       { border:1px solid #e2e8f0; border-radius:7px; padding:9px 12px; background:#fff; }
.tl-card-awal  { border-color:#c7d2fe; background:#eef2ff; }
.tl-card-kini  { border-color:#fde68a; background:#fffbeb; }
.tl-card-batal { border-style:dashed; background:#fef2f2; }

.tl-meta    { font-size:7.5pt; color:#94a3b8; font-weight:bold; text-transform:uppercase; letter-spacing:.4px; margin-bottom:3px; }
.tl-title   { font-size:9.5pt; font-weight:bold; color:#1e293b; margin-bottom:3px; }
.tl-sub     { font-size:8pt; color:#64748b; margin-bottom:4px; }

/* ── Pihak dari/ke ── */
.pihak-tbl   { width:100%; border-collapse:collapse; margin-top:7px; }
.pihak-dari  { width:44%; padding:7px 10px; background:#fff7ed; border-radius:5px 0 0 5px; border:1px solid #fed7aa; font-size:8pt; color:#92400e; vertical-align:top; }
.pihak-arr   { width:12%; text-align:center; font-size:14pt; color:#00c896; font-weight:bold; vertical-align:middle; }
.pihak-ke    { width:44%; padding:7px 10px; background:#f0fdf4; border-radius:0 5px 5px 0; border:1px solid #bbf7d0; font-size:8pt; color:#065f46; font-weight:bold; vertical-align:top; }
.pihak-lbl   { font-size:7pt; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
.pihak-dari .pihak-lbl { color:#c2410c; }
.pihak-ke   .pihak-lbl { color:#047857; }
.pihak-val  { font-size:8.5pt; font-weight:bold; }
.pihak-sub  { font-size:7.5pt; font-weight:normal; margin-top:2px; color:#666; }

/* ── Posisi kini grid ── */
.kini-tbl    { width:100%; border-collapse:collapse; margin-top:8px; }
.kini-td     { padding:8px 10px; border:1px solid #fde68a; border-radius:5px; vertical-align:top; background:#fff; }
.kini-lbl    { font-size:7.5pt; color:#94a3b8; font-weight:bold; text-transform:uppercase; margin-bottom:3px; }
.kini-val    { font-size:9pt; font-weight:700; color:#1e293b; }
.kini-sub    { font-size:7.5pt; color:#94a3b8; margin-top:2px; }

/* ── Tanda tangan ── */
.ttd-section { margin-top:22px; display:table; width:100%; }
.ttd-box     { display:table-cell; width:33.3%; text-align:center; padding:0 10px; vertical-align:top; }
.ttd-title   { font-size:8.5pt; color:#475569; margin-bottom:48px; }
.ttd-line    { border-top:1px solid #334155; margin:0 12px 4px; }
.ttd-name    { font-size:8.5pt; font-weight:bold; color:#0f172a; }
.ttd-role    { font-size:7.5pt; color:#64748b; margin-top:2px; }

/* ── Footer ── */
.page-footer  { margin-top:16px; padding-top:7px; border-top:1px solid #cbd5e1; display:table; width:100%; }
.pf-left      { display:table-cell; font-size:7pt; color:#94a3b8; vertical-align:middle; }
.pf-right     { display:table-cell; text-align:right; font-size:7pt; color:#94a3b8; vertical-align:middle; }
</style>
</head>
<body>

<!-- ════ KOP ════ -->
<div class="kop">
    <div class="kop-logo-area"><div class="kop-logo-box">FS</div></div>
    <div class="kop-text">
        <div class="kop-org">FixSmart Helpdesk</div>
        <div class="kop-sub">Divisi Teknologi Informasi &mdash; Lacak &amp; Riwayat Aset IT</div>
    </div>
    <div class="kop-right">
        <div class="kop-rlabel">No. Dokumen</div>
        <div class="kop-rval"><?= htmlspecialchars($no_dok) ?></div>
        <div style="margin-top:4px;">
            <div class="kop-rlabel">Tanggal Cetak</div>
            <div class="kop-rval"><?= date('d/m/Y') ?></div>
        </div>
    </div>
</div>
<div class="kop-rule2"></div>

<!-- ════ JUDUL ════ -->
<div class="rpt-title">
    <div class="rpt-label">Kartu Lacak Aset &mdash; Internal Use Only</div>
    <div class="rpt-main">Riwayat Perjalanan Aset IT</div>
    <div class="rpt-sub"><?= htmlspecialchars($aset['nama_aset']) ?></div>
    <div class="rpt-no">
        Dicetak oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
        &nbsp;|&nbsp; <?= $tgl_cetak ?> WIB
    </div>
</div>

<!-- ════ HEADER CARD ASET ════ -->
<div class="aset-hd">
    <div class="ah-stripe">
        <div class="ah-stripe-bar" style="background:<?= $kst[2] ?>;"></div>
    </div>
    <div class="ah-info">
        <div class="ah-inv"><?= htmlspecialchars($aset['no_inventaris']) ?></div>
        <div class="ah-nama"><?= htmlspecialchars($aset['nama_aset']) ?></div>
        <div class="ah-merek">
            <?= htmlspecialchars($aset['merek'] ?? '') ?>
            <?php if ($aset['model_aset']): ?> / <?= htmlspecialchars($aset['model_aset']) ?><?php endif; ?>
        </div>
    </div>
    <div class="ah-chips">
        <div style="margin-bottom:5px;">
            <span class="chip" style="background:<?= $kst[0] ?>;color:<?= $kst[1] ?>;">
                <?= htmlspecialchars($aset['kondisi'] ?? '—') ?>
            </span>
        </div>
        <div style="margin-bottom:5px;">
            <span class="chip" style="background:<?= $sst[0] ?>;color:<?= $sst[1] ?>;">
                <?= htmlspecialchars($aset['status_pakai'] ?? 'Terpakai') ?>
            </span>
        </div>
        <?php if ($aset['kategori']): ?>
        <div>
            <span class="chip" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.45);">
                <?= htmlspecialchars($aset['kategori']) ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ════ INFO DETAIL ════ -->
<table class="info-tbl">
    <tr>
        <td class="it-lbl">Lokasi / Bagian</td>
        <td class="it-val">
            <strong>
                <?= $aset['bagian_kode'] ? '['.htmlspecialchars($aset['bagian_kode']).'] ' : '' ?>
                <?= htmlspecialchars($aset['bagian_nama'] ?? '—') ?>
            </strong>
            <?php if ($aset['bagian_lokasi']): ?>
            &nbsp;&mdash;&nbsp;
            <span style="color:#64748b;"><?= htmlspecialchars($aset['bagian_lokasi']) ?></span>
            <?php endif; ?>
        </td>
        <td class="it-lbl">Penanggung Jawab</td>
        <td class="it-val">
            <strong><?= htmlspecialchars($aset['pj_nama_db'] ?? ($aset['penanggung_jawab'] ?? '—')) ?></strong>
            <?php if ($aset['pj_divisi']): ?>
            <br><span style="color:#64748b;font-size:8pt;"><?= htmlspecialchars($aset['pj_divisi']) ?></span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td class="it-lbl">Serial Number</td>
        <td class="it-val">
            <span style="font-family:'Courier New',monospace;color:#4f46e5;">
                <?= htmlspecialchars($aset['serial_number'] ?? '—') ?>
            </span>
        </td>
        <td class="it-lbl">Tanggal Beli</td>
        <td class="it-val">
            <?= $aset['tanggal_beli'] ? tglId($aset['tanggal_beli']) : '—' ?>
        </td>
    </tr>
    <tr>
        <td class="it-lbl">Garansi s/d</td>
        <td class="it-val">
            <?php if (!$aset['garansi_sampai']): ?>
                <span style="color:#cbd5e1;">—</span>
            <?php elseif ($g_exp): ?>
                <span style="color:#ef4444;font-weight:bold;">EXPIRED</span>
                &nbsp; <span style="color:#94a3b8;"><?= tglId($aset['garansi_sampai']) ?></span>
            <?php elseif ($g_soon): ?>
                <span style="color:#f59e0b;font-weight:bold;">Segera Habis</span>
                &nbsp; <span style="color:#94a3b8;"><?= tglId($aset['garansi_sampai']) ?></span>
            <?php else: ?>
                <span style="color:#16a34a;"><?= tglId($aset['garansi_sampai']) ?></span>
            <?php endif; ?>
        </td>
        <td class="it-lbl">Harga Beli</td>
        <td class="it-val">
            <?= $aset['harga_beli'] ? 'Rp ' . number_format($aset['harga_beli'], 0, ',', '.') : '—' ?>
        </td>
    </tr>
    <tr>
        <td class="it-lbl">Kondisi</td>
        <td class="it-val" colspan="3">
            <span class="chip" style="background:<?= $kst[0] ?>;color:<?= $kst[1] ?>;">
                <?= htmlspecialchars($aset['kondisi'] ?? '—') ?>
            </span>
            <div class="kbar-wrap"><div class="kbar-fill"></div></div>
            <span style="font-size:8pt;color:#94a3b8;margin-left:6px;"><?= $kondisi_pct ?>%</span>
        </td>
    </tr>
    <?php if ($aset['keterangan']): ?>
    <tr>
        <td class="it-lbl">Keterangan</td>
        <td class="it-val" colspan="3"><?= htmlspecialchars($aset['keterangan']) ?></td>
    </tr>
    <?php endif; ?>
</table>

<!-- ════ RINGKASAN MUTASI ════ -->
<table class="stat-tbl">
    <tr>
        <?php foreach ([
            [$total_mut,                    'Total Perpindahan', '#4f46e5', '#eef2ff'],
            [$mut_selesai,                  'Mutasi Selesai',    '#059669', '#d1fae5'],
            [$total_mut - $mut_selesai,     'Batal / Draft',     '#dc2626', '#fee2e2'],
        ] as [$val, $lbl, $clr, $bg]): ?>
        <td style="padding:4px;">
            <div class="stat-card" style="background:<?= $bg ?>;border-color:<?= $clr ?>;">
                <div class="stat-val" style="color:<?= $clr ?>;"><?= $val ?></div>
                <div class="stat-lbl" style="color:<?= $clr ?>;"><?= $lbl ?></div>
            </div>
        </td>
        <?php endforeach; ?>
    </tr>
</table>

<!-- ════ TIMELINE ════ -->
<div class="sec">
    <div class="sec-title">Timeline Riwayat Perjalanan Aset</div>
</div>

<div class="tl-wrap">
    <div class="tl-line"></div>

    <!-- Item 1: Registrasi Awal -->
    <div class="tl-item">
        <div class="tl-dot tl-dot-awal"></div>
        <div class="tl-card tl-card-awal">
            <div class="tl-meta">
                <?= $aset['created_at'] ? tglId($aset['created_at'], true) : '—' ?>
            </div>
            <div class="tl-title">Aset Terdaftar ke Sistem</div>
            <div class="tl-sub">
                Aset pertama kali dicatat dalam inventaris IT.
                <?php if ($aset['bagian_nama']): ?>
                Lokasi awal: <strong><?= htmlspecialchars($aset['bagian_nama']) ?></strong>.
                <?php endif; ?>
                <?php if ($aset['pj_nama_db'] || $aset['penanggung_jawab']): ?>
                PIC awal: <strong><?= htmlspecialchars($aset['pj_nama_db'] ?: $aset['penanggung_jawab']) ?></strong>.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Items: Mutasi -->
    <?php foreach ($mutasi as $idx => $m):
        $isBatal      = $m['status_mutasi'] === 'batal';
        $kond_berubah = ($m['kondisi_sebelum'] !== $m['kondisi_sesudah']) && $m['kondisi_sesudah'];
        $kst_m        = kondisiStyleL($m['kondisi_sesudah'] ?? '');
        $kst_b        = kondisiStyleL($m['kondisi_sebelum'] ?? '');

        // Teks dari/ke berdasarkan jenis
        $dari_lokasi = $m['dari_bagian_nama'] ?? '—';
        $dari_pic    = $m['dari_pic_nama']    ?? '—';
        $ke_lokasi   = $m['ke_bagian_nama']   ?: ($m['jenis'] === 'pindah_pic'    ? '(sama seperti sebelum)' : '—');
        $ke_pic      = $m['ke_pic_nama']      ?: ($m['jenis'] === 'pindah_lokasi' ? '(sama seperti sebelum)' : '—');
    ?>
    <div class="tl-item">
        <div class="tl-dot <?= $isBatal ? 'tl-dot-batal' : 'tl-dot-mutasi' ?>"></div>
        <div class="tl-card <?= $isBatal ? 'tl-card-batal' : '' ?>">

            <div class="tl-meta">
                <?= tglId($m['tanggal_mutasi']) ?>
                &nbsp;|&nbsp; Dicatat: <?= tglId($m['created_at'], true) ?>
            </div>

            <div class="tl-title">
                <?= htmlspecialchars(jenisLabelL($m['jenis'])) ?>
                &nbsp;&mdash;&nbsp;
                <span style="font-family:'Courier New',monospace;font-size:8.5pt;color:#4f46e5;">
                    <?= htmlspecialchars($m['no_mutasi']) ?>
                </span>
                <?php if ($isBatal): ?>
                <span style="color:#ef4444;font-size:8pt;"> [DIBATALKAN]</span>
                <?php endif; ?>
            </div>

            <div class="tl-sub">
                Oleh: <strong><?= htmlspecialchars($m['dibuat_nama'] ?? '—') ?></strong>
                <?php if ($m['keterangan']): ?>
                &nbsp;&mdash;&nbsp; <?= htmlspecialchars(mb_strimwidth($m['keterangan'], 0, 70, '...')) ?>
                <?php endif; ?>
            </div>

            <?php if (!$isBatal): ?>
            <!-- Dari → Ke -->
            <table class="pihak-tbl">
                <tr>
                    <td class="pihak-dari">
                        <div class="pihak-lbl">Dari (Asal)</div>
                        <div class="pihak-val"><?= htmlspecialchars($dari_lokasi) ?></div>
                        <div class="pihak-sub">PIC: <?= htmlspecialchars($dari_pic) ?></div>
                    </td>
                    <td class="pihak-arr">&rarr;</td>
                    <td class="pihak-ke">
                        <div class="pihak-lbl">Ke (Tujuan)</div>
                        <div class="pihak-val"><?= htmlspecialchars($ke_lokasi) ?></div>
                        <div class="pihak-sub">PIC: <?= htmlspecialchars($ke_pic) ?></div>
                    </td>
                </tr>
            </table>

            <?php if ($kond_berubah): ?>
            <div style="margin-top:6px;">
                <span style="font-size:7.5pt;color:#64748b;">Kondisi berubah: </span>
                <span class="chip" style="background:<?= $kst_b[0] ?>;color:<?= $kst_b[1] ?>;font-size:7.5pt;">
                    <?= htmlspecialchars($m['kondisi_sebelum']) ?>
                </span>
                <span style="color:#94a3b8;margin:0 4px;">&rarr;</span>
                <span class="chip" style="background:<?= $kst_m[0] ?>;color:<?= $kst_m[1] ?>;font-size:7.5pt;">
                    <?= htmlspecialchars($m['kondisi_sesudah']) ?>
                </span>
            </div>
            <?php endif; ?>
            <?php endif; // !isBatal ?>

        </div>
    </div>
    <?php endforeach; ?>

    <!-- Item: Posisi Kini -->
    <div class="tl-item">
        <div class="tl-dot tl-dot-kini"></div>
        <div class="tl-card tl-card-kini">
            <div class="tl-meta">
                Posisi Saat Ini &mdash; Dicetak <?= $tgl_cetak ?> WIB
            </div>
            <div class="tl-title">Lokasi &amp; PIC Aktif</div>

            <table class="kini-tbl">
                <tr>
                    <td class="kini-td" style="width:49%;margin-right:4px;">
                        <div class="kini-lbl">Lokasi / Bagian</div>
                        <div class="kini-val"><?= htmlspecialchars($aset['bagian_nama'] ?? 'Tanpa Lokasi') ?></div>
                        <?php if ($aset['bagian_lokasi']): ?>
                        <div class="kini-sub"><?= htmlspecialchars($aset['bagian_lokasi']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="width:2%;"></td>
                    <td class="kini-td" style="width:49%;">
                        <div class="kini-lbl">Penanggung Jawab</div>
                        <div class="kini-val"><?= htmlspecialchars($aset['pj_nama_db'] ?? ($aset['penanggung_jawab'] ?? 'Tanpa PIC')) ?></div>
                        <?php if ($aset['pj_divisi']): ?>
                        <div class="kini-sub"><?= htmlspecialchars($aset['pj_divisi']) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div style="margin-top:8px;">
                <span class="chip" style="background:<?= $kst[0] ?>;color:<?= $kst[1] ?>;">
                    <?= htmlspecialchars($aset['kondisi'] ?? '—') ?>
                </span>
                &nbsp;
                <span class="chip" style="background:<?= $sst[0] ?>;color:<?= $sst[1] ?>;">
                    <?= htmlspecialchars($aset['status_pakai'] ?? 'Terpakai') ?>
                </span>
            </div>
        </div>
    </div>

</div><!-- /tl-wrap -->

<!-- ════ TANDA TANGAN ════ -->
<div style="margin-top:22px;font-size:8.5pt;color:#475569;margin-bottom:4px;">Dokumen ini diverifikasi oleh:</div>
<div class="ttd-section">
    <div class="ttd-box">
        <div class="ttd-title">Dicetak Oleh,</div>
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
        <div class="ttd-title">Diketahui Oleh,</div>
        <div class="ttd-line"></div>
        <div class="ttd-name">___________________</div>
        <div class="ttd-role">Manajer / Pimpinan</div>
    </div>
</div>

<!-- ════ FOOTER ════ -->
<div class="page-footer">
    <div class="pf-left">
        FixSmart Helpdesk &mdash; Kartu Lacak Aset IT &mdash; Dicetak: <?= date('d/m/Y H:i:s') ?> WIB<br>
        Dokumen ini bersifat rahasia dan hanya untuk keperluan internal.
    </div>
    <div class="pf-right">
        No. Dok: <?= htmlspecialchars($no_dok) ?> &nbsp;|&nbsp; <?= $total_mut ?> mutasi tercatat<br>
        Aset: <?= htmlspecialchars($aset['no_inventaris']) ?>
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

$slug     = preg_replace('/[^a-zA-Z0-9]/', '_', $aset['no_inventaris']);
$filename = 'Lacak_Aset_' . $slug . '_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;