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

// ── Helpers ────────────────────────────────────────────────────────────────────
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
    return match ($j) {
        'keduanya'      => '↔ Pindah Lokasi + PIC',
        'pindah_lokasi' => '↗ Pindah Lokasi',
        'pindah_pic'    => '→ Pindah PIC',
        default         => $j,
    };
}

$kst = kondisiStyleL($aset['kondisi'] ?? '');
$sst = spStyleL($aset['status_pakai'] ?? 'Terpakai');
$no_dok = 'LACAK-' . $aset['no_inventaris'] . '-' . date('Ymd');
$kondisi_pct = match ($aset['kondisi'] ?? '') { 'Baik' => 100, 'Dalam Perbaikan' => 50, 'Rusak' => 20, default => 0 };

$g_exp  = $aset['garansi_sampai'] && strtotime($aset['garansi_sampai']) < time();
$g_soon = $aset['garansi_sampai'] && !$g_exp && strtotime($aset['garansi_sampai']) < strtotime('+30 days');

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
.kop { display:table; width:100%; padding-bottom:10px; border-bottom:3px solid #0f172a; margin-bottom:4px; }
.kop-logo-area { display:table-cell; vertical-align:middle; width:56px; }
.kop-logo-box  { width:44px; height:44px; background:#0f172a; border-radius:6px; text-align:center; line-height:44px; font-size:18px; color:#fff; font-weight:bold; }
.kop-text  { display:table-cell; vertical-align:middle; padding-left:11px; }
.kop-org   { font-size:14pt; font-weight:bold; color:#0f172a; }
.kop-sub   { font-size:8pt; color:#475569; }
.kop-right { display:table-cell; vertical-align:middle; text-align:right; }
.kop-right-label { font-size:7pt; color:#94a3b8; letter-spacing:1px; text-transform:uppercase; }
.kop-right-val   { font-size:8.5pt; color:#334155; font-weight:bold; }
.kop-rule2 { height:1px; background:#cbd5e1; margin-bottom:14px; }

/* ── Judul ── */
.rpt-title { text-align:center; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #e2e8f0; }
.rpt-label { font-size:7pt; letter-spacing:3px; text-transform:uppercase; color:#64748b; margin-bottom:4px; }
.rpt-main  { font-size:14pt; font-weight:bold; color:#0f172a; text-transform:uppercase; margin-bottom:4px; }
.rpt-sub   { font-size:10pt; color:#6366f1; font-weight:bold; }
.rpt-no    { font-size:7.5pt; color:#94a3b8; margin-top:4px; }

/* ── Aset Header Card ── */
.aset-header {
    background: linear-gradient(135deg, #0a0f14, #132030);
    border-radius: 8px; padding: 16px 18px;
    display: table; width: 100%; margin-bottom: 14px;
}
.ah-icon-cell { display:table-cell; vertical-align:middle; width:64px; }
.ah-icon-box  {
    width:52px; height:52px; border-radius:10px;
    background:<?= $kst[0] ?>; border:1px solid <?= $kst[2] ?>;
    display:flex; align-items:center; justify-content:center;
    text-align:center; line-height:52px;
}
.ah-info { display:table-cell; vertical-align:middle; padding-left:14px; }
.ah-inv  { font-family:'Courier New',monospace; font-size:8pt; font-weight:700; color:rgba(255,255,255,.4); margin-bottom:3px; }
.ah-nama { font-size:15pt; font-weight:800; color:#fff; line-height:1.2; margin-bottom:6px; }
.ah-chips { display:table-cell; vertical-align:middle; text-align:right; }

/* ── Info Grid ── */
.info-tbl { width:100%; border-collapse:collapse; margin-bottom:14px; border:1px solid #e2e8f0; }
.info-tbl td { padding:6px 9px; border:1px solid #e2e8f0; font-size:8.5pt; vertical-align:top; }
.it-lbl { background:#f8fafc; color:#475569; font-weight:bold; width:18%; font-size:8pt; }
.it-val { color:#1e293b; }

/* ── Kondisi bar ── */
.kbar-wrap { background:#e2e8f0; height:6px; border-radius:3px; overflow:hidden; margin-top:4px; }
.kbar-fill { height:6px; border-radius:3px; background:<?= $kst[2] ?>; width:<?= $kondisi_pct ?>%; }

/* ── Section ── */
.sec { margin:14px 0 8px; }
.sec-title { font-size:10pt; font-weight:bold; color:#0f172a; border-bottom:2px solid #6366f1; padding-bottom:4px; margin-bottom:10px; }

/* ── Timeline ── */
.tl-wrap { position:relative; padding-left:24px; }
.tl-line {
    position:absolute; left:8px; top:8px; bottom:0;
    width:2px; background:linear-gradient(180deg,#6366f1,#00c896,#e2e8f0);
    border-radius:2px;
}

.tl-item { position:relative; margin-bottom:14px; page-break-inside:avoid; }
.tl-dot  {
    position:absolute; left:-19px; top:5px;
    width:14px; height:14px; border-radius:50%;
    border:2px solid #fff;
    display:table; text-align:center; line-height:10px;
    box-shadow:0 1px 4px rgba(0,0,0,.15);
}
.tl-dot-awal   { background:#6366f1; }
.tl-dot-mutasi { background:#00c896; }
.tl-dot-kini   { background:#f59e0b; }
.tl-dot-batal  { background:#ef4444; }

.tl-card {
    border:1px solid #e2e8f0; border-radius:7px;
    padding:9px 12px; background:#fff;
}
.tl-card-awal  { border-color:#c7d2fe; background:#eef2ff; }
.tl-card-kini  { border-color:#fde68a; background:#fffbeb; box-shadow:0 0 0 2px rgba(245,158,11,.12); }
.tl-card-batal { opacity:.6; border-style:dashed; }

.tl-date  { font-size:7.5pt; color:#94a3b8; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
.tl-title { font-size:9.5pt; font-weight:bold; color:#1e293b; margin-bottom:3px; }
.tl-sub   { font-size:8pt; color:#64748b; }

.tl-pihak { display:table; width:100%; margin-top:6px; background:#f8fafc; border-radius:5px; padding:6px 9px; }
.tl-dari  { display:table-cell; font-size:8pt; color:#92400e; vertical-align:middle; }
.tl-arr   { display:table-cell; font-size:11pt; color:#00c896; text-align:center; width:24px; vertical-align:middle; }
.tl-ke    { display:table-cell; font-size:8pt; color:#065f46; font-weight:bold; vertical-align:middle; }

.kini-grid { display:table; width:100%; margin-top:8px; border-spacing:6px; }
.kini-cell { display:table-cell; padding:8px 10px; border-radius:6px; background:#fff; border:1px solid #fde68a; vertical-align:top; }
.kini-lbl  { font-size:7.5pt; color:#94a3b8; font-weight:bold; text-transform:uppercase; margin-bottom:3px; }
.kini-val  { font-size:9pt; font-weight:700; color:#1e293b; }

/* ── Chip badge ── */
.chip { display:inline-block; padding:2px 8px; border-radius:20px; font-size:8pt; font-weight:bold; }

/* ── Tanda tangan ── */
.ttd-section { margin-top:22px; display:table; width:100%; }
.ttd-box     { display:table-cell; width:33.3%; text-align:center; padding:0 10px; vertical-align:top; }
.ttd-title   { font-size:8.5pt; color:#475569; margin-bottom:48px; }
.ttd-line    { border-top:1px solid #334155; margin:0 12px 4px; }
.ttd-name    { font-size:8.5pt; font-weight:bold; color:#0f172a; }
.ttd-role    { font-size:7.5pt; color:#64748b; margin-top:2px; }

/* ── Footer ── */
.page-footer { margin-top:16px; padding-top:7px; border-top:1px solid #cbd5e1; display:table; width:100%; }
.pf-left  { display:table-cell; font-size:7pt; color:#94a3b8; vertical-align:middle; }
.pf-right { display:table-cell; text-align:right; font-size:7pt; color:#94a3b8; vertical-align:middle; }
</style>
</head>
<body>

<!-- KOP -->
<div class="kop">
    <div class="kop-logo-area"><div class="kop-logo-box">FS</div></div>
    <div class="kop-text">
        <div class="kop-org">FixSmart Helpdesk</div>
        <div class="kop-sub">Divisi Teknologi Informasi &mdash; Lacak &amp; Riwayat Aset IT</div>
    </div>
    <div class="kop-right">
        <div class="kop-right-label">No. Dokumen</div>
        <div class="kop-right-val"><?= htmlspecialchars($no_dok) ?></div>
        <div style="margin-top:4px;">
            <div class="kop-right-label">Tanggal Cetak</div>
            <div class="kop-right-val"><?= date('d/m/Y') ?></div>
        </div>
    </div>
</div>
<div class="kop-rule2"></div>

<!-- JUDUL -->
<div class="rpt-title">
    <div class="rpt-label">Kartu Lacak Aset &mdash; Internal Use Only</div>
    <div class="rpt-main">Riwayat Perjalanan Aset IT</div>
    <div class="rpt-sub"><?= htmlspecialchars($aset['nama_aset']) ?></div>
    <div class="rpt-no">
        Dicetak oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
        &nbsp;|&nbsp; <?= date('d F Y, H:i') ?> WIB
    </div>
</div>

<!-- HEADER CARD ASET -->
<div class="aset-header">
    <div class="ah-icon-cell">
        <div class="ah-icon-box">
            <span style="font-size:22px;color:<?= $kst[2] ?>;">&#9632;</span>
        </div>
    </div>
    <div class="ah-info">
        <div class="ah-inv"><?= htmlspecialchars($aset['no_inventaris']) ?></div>
        <div class="ah-nama"><?= htmlspecialchars($aset['nama_aset']) ?></div>
        <div>
            <?php if ($aset['merek']): ?>
            <span style="color:rgba(255,255,255,.5);font-size:9pt;"><?= htmlspecialchars($aset['merek']) ?></span>
            <?php endif; ?>
            <?php if ($aset['model_aset']): ?>
            <span style="color:rgba(255,255,255,.35);font-size:8.5pt;"> / <?= htmlspecialchars($aset['model_aset']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="ah-chips">
        <div style="margin-bottom:6px;">
            <span class="chip" style="background:<?= $kst[0] ?>;color:<?= $kst[1] ?>;"><?= htmlspecialchars($aset['kondisi']??'—') ?></span>
        </div>
        <div style="margin-bottom:6px;">
            <span class="chip" style="background:<?= $sst[0] ?>;color:<?= $sst[1] ?>;"><?= htmlspecialchars($aset['status_pakai']??'Terpakai') ?></span>
        </div>
        <?php if ($aset['kategori']): ?>
        <div>
            <span class="chip" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);"><?= htmlspecialchars($aset['kategori']) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- INFO DETAIL -->
<table class="info-tbl">
    <tr>
        <td class="it-lbl">Lokasi / Bagian</td>
        <td class="it-val">
            <strong><?= ($aset['bagian_kode']?'['.$aset['bagian_kode'].'] ':'') . htmlspecialchars($aset['bagian_nama']??'—') ?></strong>
            <?php if ($aset['bagian_lokasi']): ?> &nbsp;·&nbsp; <span style="color:#64748b;"><?= htmlspecialchars($aset['bagian_lokasi']) ?></span><?php endif; ?>
        </td>
        <td class="it-lbl">Penanggung Jawab</td>
        <td class="it-val">
            <strong><?= htmlspecialchars($aset['pj_nama_db']??($aset['penanggung_jawab']??'—')) ?></strong>
            <?php if ($aset['pj_divisi']): ?><br><span style="color:#64748b;font-size:8pt;"><?= htmlspecialchars($aset['pj_divisi']) ?></span><?php endif; ?>
        </td>
    </tr>
    <tr>
        <td class="it-lbl">Serial Number</td>
        <td class="it-val"><span style="font-family:'Courier New',monospace;color:#6366f1;"><?= htmlspecialchars($aset['serial_number']??'—') ?></span></td>
        <td class="it-lbl">Tanggal Beli</td>
        <td class="it-val"><?= $aset['tanggal_beli'] ? date('d F Y', strtotime($aset['tanggal_beli'])) : '—' ?></td>
    </tr>
    <tr>
        <td class="it-lbl">Garansi s/d</td>
        <td class="it-val">
            <?php if (!$aset['garansi_sampai']): ?>—
            <?php elseif ($g_exp): ?><span style="color:#ef4444;font-weight:bold;">EXPIRED</span> &nbsp; <?= date('d/m/Y', strtotime($aset['garansi_sampai'])) ?>
            <?php elseif ($g_soon): ?><span style="color:#f59e0b;font-weight:bold;">Segera Habis</span> &nbsp; <?= date('d/m/Y', strtotime($aset['garansi_sampai'])) ?>
            <?php else: ?><span style="color:#16a34a;"><?= date('d F Y', strtotime($aset['garansi_sampai'])) ?></span>
            <?php endif; ?>
        </td>
        <td class="it-lbl">Harga Beli</td>
        <td class="it-val"><?= $aset['harga_beli'] ? 'Rp ' . number_format($aset['harga_beli'],0,',','.') : '—' ?></td>
    </tr>
    <tr>
        <td class="it-lbl">Kondisi</td>
        <td class="it-val" colspan="3">
            <span class="chip" style="background:<?= $kst[0] ?>;color:<?= $kst[1] ?>;"><?= htmlspecialchars($aset['kondisi']??'—') ?></span>
            <div class="kbar-wrap" style="width:200px;display:inline-block;vertical-align:middle;margin-left:8px;">
                <div class="kbar-fill"></div>
            </div>
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

<!-- RINGKASAN MUTASI -->
<div style="display:table;width:100%;margin-bottom:14px;border-collapse:separate;border-spacing:8px;">
    <?php foreach ([
        [$total_mut,   'Total Perpindahan',     '#6366f1','#eef2ff'],
        [$mut_selesai, 'Mutasi Selesai',         '#059669','#d1fae5'],
        [$total_mut - $mut_selesai, 'Batal/Draft', '#dc2626','#fee2e2'],
    ] as [$val,$lbl,$clr,$bg]): ?>
    <div style="display:table-cell;padding:10px 14px;background:<?= $bg ?>;border-radius:7px;border-left:3px solid <?= $clr ?>;text-align:center;vertical-align:middle;">
        <div style="font-size:18pt;font-weight:800;color:<?= $clr ?>;line-height:1;"><?= $val ?></div>
        <div style="font-size:7.5pt;color:<?= $clr ?>;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- TIMELINE RIWAYAT -->
<div class="sec">
    <div class="sec-title">&#8635; Timeline Riwayat Perjalanan Aset</div>
</div>

<div class="tl-wrap">
    <div class="tl-line"></div>

    <!-- Item: Registrasi Awal -->
    <div class="tl-item">
        <div class="tl-dot tl-dot-awal"></div>
        <div class="tl-card tl-card-awal">
            <div class="tl-date">&#128197; <?= $aset['created_at'] ? date('d F Y, H:i', strtotime($aset['created_at'])) : '—' ?></div>
            <div class="tl-title">&#10133; Aset Terdaftar ke Sistem</div>
            <div class="tl-sub">
                Aset pertama kali dicatat di inventaris IT.
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
        $isBatal = $m['status_mutasi'] === 'batal';
        $jmap    = ['keduanya'=>'↔ Pindah Lokasi + PIC','pindah_lokasi'=>'↗ Pindah Lokasi','pindah_pic'=>'→ Pindah PIC'];
        $kond_berubah = $m['kondisi_sebelum'] !== $m['kondisi_sesudah'] && $m['kondisi_sesudah'];
        $kst_m = kondisiStyleL($m['kondisi_sesudah'] ?? '');
        $kst_b = kondisiStyleL($m['kondisi_sebelum'] ?? '');
    ?>
    <div class="tl-item">
        <div class="tl-dot <?= $isBatal ? 'tl-dot-batal' : 'tl-dot-mutasi' ?>"></div>
        <div class="tl-card <?= $isBatal ? 'tl-card-batal' : '' ?>">
            <div class="tl-date">
                &#128197; <?= date('d F Y', strtotime($m['tanggal_mutasi'])) ?>
                &nbsp;|&nbsp; Dibuat: <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?>
            </div>
            <div class="tl-title">
                <?= htmlspecialchars($jmap[$m['jenis']] ?? $m['jenis']) ?>
                &nbsp;
                <span style="font-family:'Courier New',monospace;font-size:8pt;color:#6366f1;"><?= htmlspecialchars($m['no_mutasi']) ?></span>
                <?php if ($isBatal): ?>
                <span style="color:#ef4444;font-size:8pt;font-weight:bold;"> [DIBATALKAN]</span>
                <?php endif; ?>
            </div>
            <div class="tl-sub">Oleh: <strong><?= htmlspecialchars($m['dibuat_nama']??'—') ?></strong><?= $m['keterangan'] ? ' &nbsp;·&nbsp; ' . htmlspecialchars(mb_strimwidth($m['keterangan'],0,60,'…')) : '' ?></div>

            <?php if (!$isBatal): ?>
            <!-- Dari → Ke -->
            <div class="tl-pihak">
                <div class="tl-dari">
                    <strong>Dari:</strong><br>
                    &#127968; <?= htmlspecialchars($m['dari_bagian_nama']??'—') ?><br>
                    &#128100; <?= htmlspecialchars($m['dari_pic_nama']??'—') ?>
                </div>
                <div class="tl-arr">&#8594;</div>
                <div class="tl-ke">
                    <strong>Ke:</strong><br>
                    &#127968; <?= htmlspecialchars($m['ke_bagian_nama'] ?: ($m['jenis']==='pindah_pic'?'(sama seperti sebelum)':'—')) ?><br>
                    &#128100; <?= htmlspecialchars($m['ke_pic_nama'] ?: ($m['jenis']==='pindah_lokasi'?'(sama seperti sebelum)':'—')) ?>
                </div>
            </div>
            <?php if ($kond_berubah): ?>
            <div style="margin-top:5px;">
                <span class="chip" style="background:<?= $kst_b[0] ?>;color:<?= $kst_b[1] ?>;font-size:7.5pt;"><?= htmlspecialchars($m['kondisi_sebelum']) ?></span>
                <span style="color:#94a3b8;font-size:10pt;margin:0 4px;">&#8594;</span>
                <span class="chip" style="background:<?= $kst_m[0] ?>;color:<?= $kst_m[1] ?>;font-size:7.5pt;"><?= htmlspecialchars($m['kondisi_sesudah']) ?></span>
                <span style="font-size:7.5pt;color:#64748b;margin-left:4px;">(Kondisi berubah)</span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Item: Posisi Kini -->
    <div class="tl-item">
        <div class="tl-dot tl-dot-kini"></div>
        <div class="tl-card tl-card-kini">
            <div class="tl-date">&#9200; Posisi Saat Ini &mdash; Dicetak <?= date('d F Y, H:i') ?> WIB</div>
            <div class="tl-title">&#128205; Lokasi &amp; PIC Aktif</div>
            <div class="kini-grid">
                <div class="kini-cell" style="width:50%;">
                    <div class="kini-lbl">Lokasi / Bagian</div>
                    <div class="kini-val"><?= htmlspecialchars($aset['bagian_nama']??'Tanpa Lokasi') ?></div>
                    <?php if ($aset['bagian_lokasi']): ?>
                    <div style="font-size:7.5pt;color:#94a3b8;margin-top:2px;">&#128205; <?= htmlspecialchars($aset['bagian_lokasi']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="kini-cell" style="width:50%;">
                    <div class="kini-lbl">Penanggung Jawab</div>
                    <div class="kini-val"><?= htmlspecialchars($aset['pj_nama_db']??($aset['penanggung_jawab']??'Tanpa PIC')) ?></div>
                    <?php if ($aset['pj_divisi']): ?>
                    <div style="font-size:7.5pt;color:#94a3b8;margin-top:2px;"><?= htmlspecialchars($aset['pj_divisi']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="margin-top:8px;display:table;width:100%;">
                <div style="display:table-cell;vertical-align:middle;">
                    <span class="chip" style="background:<?= $kst[0] ?>;color:<?= $kst[1] ?>;"><?= htmlspecialchars($aset['kondisi']??'—') ?></span>
                    &nbsp;
                    <span class="chip" style="background:<?= $sst[0] ?>;color:<?= $sst[1] ?>;"><?= htmlspecialchars($aset['status_pakai']??'Terpakai') ?></span>
                </div>
            </div>
        </div>
    </div>

</div><!-- /tl-wrap -->

<!-- TANDA TANGAN -->
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

<!-- FOOTER -->
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