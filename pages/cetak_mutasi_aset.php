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

// ── Helper: Tanggal Bahasa Indonesia ──────────────────────────────────────────
$BULAN_ID = [
    1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
    5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
    9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember',
];
function tglIdM(string $raw, bool $withTime = false): string {
    global $BULAN_ID;
    $ts = strtotime($raw);
    if (!$ts) return '—';
    $str = (int)date('j',$ts) . ' ' . ($BULAN_ID[(int)date('n',$ts)] ?? '?') . ' ' . date('Y',$ts);
    if ($withTime) $str .= ', ' . date('H:i', $ts);
    return $str;
}

// ── Parameter Filter ─────────────────────────────────────────────────────────
$mode         = $_GET['mode']         ?? 'semua';   // semua | periode | aset
$dari         = $_GET['dari']         ?? date('Y-m-01');   // tgl awal
$sampai       = $_GET['sampai']       ?? date('Y-m-d');    // tgl akhir
$fstatus      = $_GET['status']       ?? '';   // selesai | draft | batal | ''
$fjenis       = $_GET['jenis']        ?? '';   // pindah_lokasi | pindah_pic | keduanya | ''
$faset        = trim($_GET['aset']    ?? '');  // nama / no.inv aset (opsional)
$fbagian      = trim($_GET['bagian']  ?? '');  // ke_bagian_nama filter (opsional)

// Validasi tanggal
if (!strtotime($dari))   $dari   = date('Y-m-01');
if (!strtotime($sampai)) $sampai = date('Y-m-d');
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];

// ── Query ────────────────────────────────────────────────────────────────────
$where  = ['DATE(m.tanggal_mutasi) BETWEEN ? AND ?'];
$params = [$dari, $sampai];

if ($fstatus !== '') { $where[] = 'm.status_mutasi = ?'; $params[] = $fstatus; }
if ($fjenis  !== '') { $where[] = 'm.jenis = ?';         $params[] = $fjenis;  }
if ($faset   !== '') {
    $where[]  = '(a.nama_aset LIKE ? OR a.no_inventaris LIKE ?)';
    $params[] = "%$faset%";
    $params[] = "%$faset%";
}
if ($fbagian !== '') {
    $where[]  = '(m.ke_bagian_nama LIKE ? OR m.dari_bagian_nama LIKE ?)';
    $params[] = "%$fbagian%";
    $params[] = "%$fbagian%";
}

$wsql = implode(' AND ', $where);

$st = $pdo->prepare("
    SELECT m.*,
           a.nama_aset, a.no_inventaris, a.kategori, a.merek, a.model_aset,
           a.kondisi AS kondisi_aset_kini, a.status_pakai AS status_pakai_kini,
           b_dari.lokasi AS dari_bagian_lokasi,
           b_ke.lokasi   AS ke_bagian_lokasi
    FROM mutasi_aset m
    LEFT JOIN aset_it a       ON a.id  = m.aset_id
    LEFT JOIN bagian  b_dari  ON b_dari.id = m.dari_bagian_id
    LEFT JOIN bagian  b_ke    ON b_ke.id   = m.ke_bagian_id
    WHERE $wsql
    ORDER BY m.tanggal_mutasi ASC, m.created_at ASC
");
$st->execute($params);
$list = $st->fetchAll();

$total = count($list);

// ── Statistik ─────────────────────────────────────────────────────────────────
$stats_status = [];
$stats_jenis  = [];
$aset_unik    = [];
$bagian_tujuan= [];

foreach ($list as $m) {
    $stats_status[$m['status_mutasi']] = ($stats_status[$m['status_mutasi']] ?? 0) + 1;
    $stats_jenis[$m['jenis']]          = ($stats_jenis[$m['jenis']]  ?? 0) + 1;
    if ($m['aset_id']) $aset_unik[$m['aset_id']] = true;
    if ($m['ke_bagian_nama']) $bagian_tujuan[$m['ke_bagian_nama']] = ($bagian_tujuan[$m['ke_bagian_nama']] ?? 0) + 1;
}
arsort($bagian_tujuan);

// Kelompokkan per bulan untuk timeline
$by_bulan = [];
foreach ($list as $m) {
    $bln = date('Y-m', strtotime($m['tanggal_mutasi']));
    $by_bulan[$bln][] = $m;
}

// ── Judul ─────────────────────────────────────────────────────────────────────
$tgl_dari_fmt   = tglIdM($dari);
$tgl_sampai_fmt = tglIdM($sampai);
$periode_label  = $dari === $sampai ? $tgl_dari_fmt : "$tgl_dari_fmt s/d $tgl_sampai_fmt";

$judul_parts = ["Periode: $periode_label"];
if ($fstatus !== '') $judul_parts[] = 'Status: ' . ucfirst($fstatus);
if ($fjenis  !== '') {
    $jmap = ['keduanya'=>'Pindah Lokasi + PIC','pindah_lokasi'=>'Pindah Lokasi','pindah_pic'=>'Pindah PIC'];
    $judul_parts[] = 'Jenis: ' . ($jmap[$fjenis] ?? $fjenis);
}
if ($faset   !== '') $judul_parts[] = 'Aset: ' . $faset;

$no_dok = 'RPT-MUT-' . date('Ymd') . '-' . strtoupper(substr(md5($dari.$sampai.$fstatus.$fjenis), 0, 4));

// ── Helpers ───────────────────────────────────────────────────────────────────
function statusMuStyle(string $s): array {
    return match ($s) {
        'selesai' => ['bg' => '#d1fae5', 'fg' => '#065f46'],
        'draft'   => ['bg' => '#fef3c7', 'fg' => '#92400e'],
        'batal'   => ['bg' => '#fee2e2', 'fg' => '#991b1b'],
        default   => ['bg' => '#f1f5f9', 'fg' => '#64748b'],
    };
}
function jenisLabel(string $j): string {
    return match ($j) {
        'keduanya'      => 'Lok + PIC',
        'pindah_lokasi' => 'Lokasi',
        'pindah_pic'    => 'PIC',
        default         => $j,
    };
}
function jenisColor(string $j): string {
    return match ($j) {
        'keduanya'      => '#7c3aed',
        'pindah_lokasi' => '#1d4ed8',
        'pindah_pic'    => '#059669',
        default         => '#64748b',
    };
}
function kondisiStyle2(string $k): array {
    return match ($k) {
        'Baik'            => ['bg' => '#dcfce7', 'fg' => '#15803d'],
        'Rusak'           => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        'Dalam Perbaikan' => ['bg' => '#fef9c3', 'fg' => '#a16207'],
        'Tidak Aktif'     => ['bg' => '#f1f5f9', 'fg' => '#475569'],
        default           => ['bg' => '#f1f5f9', 'fg' => '#64748b'],
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
.kop-logo-box  { width: 44px; height: 44px; background: #0f172a; border-radius: 6px; text-align: center; line-height: 44px; font-size: 18px; color: #fff; font-weight: bold; }
.kop-text   { display: table-cell; vertical-align: middle; padding-left: 11px; }
.kop-org    { font-size: 14pt; font-weight: bold; color: #0f172a; line-height: 1.2; }
.kop-sub    { font-size: 8pt; color: #475569; margin-top: 1px; }
.kop-right  { display: table-cell; vertical-align: middle; text-align: right; }
.kop-right-label { font-size: 7pt; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; }
.kop-right-val   { font-size: 8.5pt; color: #334155; font-weight: bold; }
.kop-rule2  { height: 1px; background: #cbd5e1; margin-bottom: 14px; }

/* ── JUDUL ── */
.report-title-block { text-align: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
.report-label       { font-size: 7pt; letter-spacing: 3px; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.report-main-title  { font-size: 14pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.report-sub         { font-size: 10pt; color: #7c3aed; font-weight: bold; }
.report-no          { font-size: 7.5pt; color: #94a3b8; margin-top: 4px; }

/* ── INFO DOKUMEN ── */
.doc-info    { width: 100%; border-collapse: collapse; margin-bottom: 16px; border: 1px solid #e2e8f0; }
.doc-info td { padding: 5px 9px; font-size: 8.5pt; border: 1px solid #e2e8f0; vertical-align: top; }
.di-label    { background: #f8fafc; color: #475569; font-weight: bold; width: 20%; }
.di-val      { color: #1e293b; width: 30%; }

/* ── SECTION ── */
.sec       { margin-top: 16px; margin-bottom: 8px; page-break-after: avoid; }
.sec-num   { font-size: 7pt; letter-spacing: 2px; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px; }
.sec-title { font-size: 10pt; font-weight: bold; color: #0f172a; border-bottom: 2px solid #7c3aed; padding-bottom: 4px; display: table; width: 100%; }
.sec-title-text { display: table-cell; }
.sec-title-rule { display: table-cell; text-align: right; font-size: 7.5pt; font-weight: normal; color: #94a3b8; vertical-align: bottom; padding-bottom: 1px; }

/* ── KPI CARDS ── */
.kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
.kpi-td    { vertical-align: top; }
.kpi-card  { border: 1px solid #e2e8f0; border-top: 3px solid #7c3aed; border-radius: 0 0 4px 4px; padding: 10px 12px 8px; background: #fff; }
.kpi-card .k-label { font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.kpi-card .k-val   { font-size: 18pt; font-weight: bold; line-height: 1; margin-bottom: 3px; }
.kpi-card .k-desc  { font-size: 7.5pt; color: #94a3b8; line-height: 1.4; }

/* ── EXEC BOX ── */
.exec-box { border-left: 4px solid #7c3aed; background: #f5f3ff; padding: 10px 14px; margin-bottom: 6px; border-radius: 0 4px 4px 0; font-size: 9pt; color: #1e293b; line-height: 1.7; }

/* ── RINGKASAN TABLE ── */
.summ-tbl      { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
.summ-tbl th   { background: #0f172a; color: #fff; padding: 7px 9px; font-size: 8pt; text-align: left; }
.summ-tbl td   { padding: 6px 9px; border-bottom: 1px solid #f1f5f9; }
.summ-tbl tr:nth-child(even) td { background: #f8fafc; }
.summ-tbl tfoot td { background: #f5f3ff; border-top: 2px solid #ddd6fe; font-weight: bold; color: #4c1d95; }

/* ── TABEL MUTASI ── */
.kat-header   { background: #4c1d95; color: #fff; padding: 6px 10px; font-size: 9pt; font-weight: bold; border-radius: 3px 3px 0 0; margin-top: 14px; display: table; width: 100%; }
.kat-header-l { display: table-cell; }
.kat-header-r { display: table-cell; text-align: right; font-size: 7.5pt; font-weight: normal; color: rgba(255,255,255,.7); }

.data-tbl { width: 100%; border-collapse: collapse; font-size: 8pt; page-break-inside: auto; }
.data-tbl thead tr       { background: #0f172a; color: #fff; }
.data-tbl thead th       { padding: 7px 8px; text-align: left; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.3px; border-right: 1px solid rgba(255,255,255,0.08); }
.data-tbl thead th:last-child { border-right: none; }
.data-tbl tbody tr td    { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 8pt; }
.data-tbl tbody tr:nth-child(even) td { background: #f8fafc; }
.data-tbl tbody tr:last-child td      { border-bottom: 2px solid #e2e8f0; }
.data-tbl tfoot td { padding: 6px 8px; font-weight: bold; background: #f5f3ff; border-top: 2px solid #ddd6fe; color: #4c1d95; font-size: 8.5pt; }

/* ── Row batal ── */
.data-tbl tbody tr.row-batal td { background: #fef2f2 !important; opacity: .75; }

/* ── Badges ── */
.mut-badge   { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; white-space: nowrap; }
.no-mut-code { font-family: 'Courier New', monospace; font-size: 7.5pt; font-weight: bold; background: #f5f3ff; color: #5b21b6; border: 1px solid #ddd6fe; padding: 1px 5px; border-radius: 3px; white-space: nowrap; }
.inv-code    { font-family: 'Courier New', monospace; font-size: 7.5pt; font-weight: bold; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 1px 5px; border-radius: 3px; white-space: nowrap; }

/* ── Arrow asal→tujuan ── */
.arrow-cell  { color: #00c896; font-size: 10pt; text-align: center; padding: 0 3px; }

/* ── Bar ── */
.bar-wrap { background: #e2e8f0; height: 5px; border-radius: 3px; overflow: hidden; display: inline-block; vertical-align: middle; }
.bar-fill { height: 5px; border-radius: 3px; }

/* ── Tanda tangan ── */
.ttd-section { margin-top: 24px; display: table; width: 100%; }
.ttd-box     { display: table-cell; width: 33.3%; text-align: center; padding: 0 10px; vertical-align: top; }
.ttd-title   { font-size: 8.5pt; color: #475569; margin-bottom: 48px; }
.ttd-line    { border-top: 1px solid #334155; margin: 0 12px 4px; }
.ttd-name    { font-size: 8.5pt; font-weight: bold; color: #0f172a; }
.ttd-role    { font-size: 7.5pt; color: #64748b; margin-top: 2px; }

/* ── Footer ── */
.page-footer { margin-top: 18px; padding-top: 7px; border-top: 1px solid #cbd5e1; display: table; width: 100%; }
.pf-left  { display: table-cell; font-size: 7pt; color: #94a3b8; vertical-align: middle; }
.pf-right { display: table-cell; text-align: right; font-size: 7pt; color: #94a3b8; vertical-align: middle; }

/* ── Pihak pemberi/penerima ── */
.pihak-dari { font-size: 7.5pt; color: #92400e; margin-bottom: 2px; }
.pihak-ke   { font-size: 7.5pt; color: #065f46; margin-top: 2px; }
.pihak-sep  { height: 1px; background: linear-gradient(90deg,#fde68a,#6ee7b7); margin: 2px 0; }
</style>
</head>
<body>

<!-- ═══════════════════ KOP ═══════════════════ -->
<div class="kop">
    <div class="kop-logo-area"><div class="kop-logo-box">FS</div></div>
    <div class="kop-text">
        <div class="kop-org">FixSmart Helpdesk</div>
        <div class="kop-sub">Divisi Teknologi Informasi &mdash; Sistem Manajemen Mutasi Aset IT</div>
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
    <div class="report-main-title">Laporan Mutasi Aset IT</div>
    <div class="report-sub"><?= implode(' &nbsp;&mdash;&nbsp; ', array_map('htmlspecialchars', $judul_parts)) ?></div>
    <div class="report-no">
        Disiapkan oleh: <?= htmlspecialchars($_SESSION['user_nama'] ?? '-') ?>
        &nbsp;|&nbsp; Dicetak: <?= tglIdM(date('Y-m-d H:i:s'), true) ?> WIB
    </div>
</div>

<!-- ═══════════════════ INFO DOKUMEN ═══════════════════ -->
<table class="doc-info">
    <tr>
        <td class="di-label">Unit</td>
        <td class="di-val">Divisi Teknologi Informasi</td>
        <td class="di-label">Tanggal Cetak</td>
        <td class="di-val"><?= tglIdM(date('Y-m-d H:i:s'), true) ?> WIB</td>
    </tr>
    <tr>
        <td class="di-label">Jenis Laporan</td>
        <td class="di-val">Riwayat Mutasi Aset IT</td>
        <td class="di-label">Status Dokumen</td>
        <td class="di-val">Final &mdash; Confidential</td>
    </tr>
    <tr>
        <td class="di-label">Periode Laporan</td>
        <td class="di-val"><strong><?= htmlspecialchars($periode_label) ?></strong></td>
        <td class="di-label">Filter Status</td>
        <td class="di-val">
            <?php if ($fstatus !== ''):
                $st2 = statusMuStyle($fstatus); ?>
                <span class="mut-badge" style="background:<?= $st2['bg'] ?>;color:<?= $st2['fg'] ?>;"><?= ucfirst(htmlspecialchars($fstatus)) ?></span>
            <?php else: ?>Semua Status<?php endif; ?>
        </td>
    </tr>
    <tr>
        <td class="di-label">Filter Jenis</td>
        <td class="di-val">
            <?php if ($fjenis !== ''):
                $jmap = ['keduanya'=>'Pindah Lokasi + PIC','pindah_lokasi'=>'Pindah Lokasi','pindah_pic'=>'Pindah PIC']; ?>
                <span style="font-weight:bold;color:<?= jenisColor($fjenis) ?>;"><?= htmlspecialchars($jmap[$fjenis] ?? $fjenis) ?></span>
            <?php else: ?>Semua Jenis<?php endif; ?>
        </td>
        <td class="di-label">Total Mutasi</td>
        <td class="di-val"><strong><?= $total ?> transaksi</strong> &nbsp;|&nbsp; <?= count($aset_unik) ?> aset unik</td>
    </tr>
</table>

<!-- ═══════════════════ I. RINGKASAN EKSEKUTIF ═══════════════════ -->
<div class="sec">
    <div class="sec-num">Bagian I</div>
    <div class="sec-title">
        <span class="sec-title-text">Ringkasan Eksekutif</span>
        <span class="sec-title-rule">Executive Summary</span>
    </div>
</div>

<?php
$n_selesai   = $stats_status['selesai'] ?? 0;
$n_draft     = $stats_status['draft']   ?? 0;
$n_batal     = $stats_status['batal']   ?? 0;
$n_keduanya  = $stats_jenis['keduanya']      ?? 0;
$n_lok       = $stats_jenis['pindah_lokasi'] ?? 0;
$n_pic       = $stats_jenis['pindah_pic']    ?? 0;
$pct_selesai = $total > 0 ? round($n_selesai / $total * 100) : 0;
$top_bagian  = array_key_first($bagian_tujuan);
$top_bagian_n= $top_bagian ? $bagian_tujuan[$top_bagian] : 0;
?>
<div class="exec-box">
    Laporan ini menyajikan riwayat <strong>mutasi aset IT</strong> pada periode
    <strong><?= htmlspecialchars($periode_label) ?></strong>.
    Total mutasi yang tercatat: <strong><?= $total ?> transaksi</strong> yang melibatkan
    <strong><?= count($aset_unik) ?> aset unik</strong>.
    <?php if ($total > 0): ?>
    Dari sisi status: <strong><?= $n_selesai ?> mutasi (<?= $pct_selesai ?>%) selesai</strong>,
    <?= $n_draft ?> draft, dan <?= $n_batal ?> dibatalkan.
    Dari sisi jenis: <strong><?= $n_keduanya ?> mutasi pindah lokasi &amp; PIC</strong>,
    <?= $n_lok ?> hanya pindah lokasi, dan <?= $n_pic ?> hanya pindah PIC.
    <?php if ($top_bagian): ?>
    Bagian tujuan yang paling banyak menerima aset adalah <strong><?= htmlspecialchars($top_bagian) ?></strong>
    sebanyak <?= $top_bagian_n ?> kali.
    <?php endif; ?>
    <?php else: ?>
    Tidak ada data mutasi untuk periode dan filter yang dipilih.
    <?php endif; ?>
</div>

<!-- ═══════════════════ II. STATISTIK MUTASI ═══════════════════ -->
<div class="sec">
    <div class="sec-num">Bagian II</div>
    <div class="sec-title"><span class="sec-title-text">Statistik Mutasi</span></div>
</div>
<table class="kpi-table">
    <tr>
        <td class="kpi-td">
            <div class="kpi-card" style="border-top-color:#7c3aed;">
                <div class="k-label">Total Mutasi</div>
                <div class="k-val" style="color:#7c3aed;"><?= $total ?></div>
                <div class="k-desc">Seluruh transaksi mutasi</div>
            </div>
        </td>
        <td class="kpi-td">
            <div class="kpi-card" style="border-top-color:#059669;">
                <div class="k-label">Selesai</div>
                <div class="k-val" style="color:#059669;"><?= $n_selesai ?></div>
                <div class="k-desc">Mutasi berhasil diproses</div>
            </div>
        </td>
        <td class="kpi-td">
            <div class="kpi-card" style="border-top-color:#d97706;">
                <div class="k-label">Draft</div>
                <div class="k-val" style="color:#d97706;"><?= $n_draft ?></div>
                <div class="k-desc">Belum final / menunggu</div>
            </div>
        </td>
        <td class="kpi-td">
            <div class="kpi-card" style="border-top-color:#dc2626;">
                <div class="k-label">Dibatalkan</div>
                <div class="k-val" style="color:#dc2626;"><?= $n_batal ?></div>
                <div class="k-desc">Mutasi dibatalkan</div>
            </div>
        </td>
        <td class="kpi-td">
            <div class="kpi-card" style="border-top-color:#0f766e;">
                <div class="k-label">Aset Unik</div>
                <div class="k-val" style="color:#0f766e;"><?= count($aset_unik) ?></div>
                <div class="k-desc">Jumlah aset yang dimutasi</div>
            </div>
        </td>
    </tr>
</table>

<!-- ── KPI Jenis ── -->
<table class="kpi-table" style="margin-top:8px;">
    <tr>
        <td class="kpi-td">
            <div class="kpi-card" style="border-top-color:#7c3aed;background:#faf5ff;">
                <div class="k-label">&#8596; Pindah Lok + PIC</div>
                <div class="k-val" style="color:#7c3aed;"><?= $n_keduanya ?></div>
                <div class="k-desc">Perpindahan lokasi &amp; PJ sekaligus</div>
            </div>
        </td>
        <td class="kpi-td">
            <div class="kpi-card" style="border-top-color:#1d4ed8;background:#eff6ff;">
                <div class="k-label">Pindah Lokasi</div>
                <div class="k-val" style="color:#1d4ed8;"><?= $n_lok ?></div>
                <div class="k-desc">Hanya perpindahan lokasi/bagian</div>
            </div>
        </td>
        <td class="kpi-td">
            <div class="kpi-card" style="border-top-color:#059669;background:#f0fdf4;">
                <div class="k-label">Pindah PIC</div>
                <div class="k-val" style="color:#059669;"><?= $n_pic ?></div>
                <div class="k-desc">Hanya pergantian penanggung jawab</div>
            </div>
        </td>
        <td class="kpi-td" colspan="2">
            <?php if (!empty($bagian_tujuan)): ?>
            <div class="kpi-card" style="border-top-color:#0f766e;">
                <div class="k-label">Top Bagian Tujuan</div>
                <div style="font-size:12pt;font-weight:bold;color:#0f766e;line-height:1.2;margin-bottom:3px;">
                    <?= htmlspecialchars($top_bagian ?? '—') ?>
                </div>
                <div class="k-desc"><?= $top_bagian_n ?> mutasi masuk ke bagian ini</div>
            </div>
            <?php endif; ?>
        </td>
    </tr>
</table>

<!-- ═══════════════════ III. RINGKASAN PER BULAN ═══════════════════ -->
<?php if (count($by_bulan) > 1): ?>
<div class="sec">
    <div class="sec-num">Bagian III</div>
    <div class="sec-title"><span class="sec-title-text">Ringkasan Mutasi per Bulan</span></div>
</div>
<table class="summ-tbl">
    <thead>
        <tr>
            <th style="width:28px;">No.</th>
            <th>Bulan</th>
            <th style="text-align:center;">Total</th>
            <th style="text-align:center;">Selesai</th>
            <th style="text-align:center;">Draft</th>
            <th style="text-align:center;">Batal</th>
            <th style="text-align:center;">Lok+PIC</th>
            <th style="text-align:center;">Lokasi</th>
            <th style="text-align:center;">PIC</th>
            <th style="text-align:center;">Aset Unik</th>
            <th>Proporsi</th>
        </tr>
    </thead>
    <tbody>
        <?php $no_b = 0; foreach ($by_bulan as $bln => $items_b):
            $no_b++;
            $ns = count(array_filter($items_b, fn($x) => $x['status_mutasi'] === 'selesai'));
            $nd = count(array_filter($items_b, fn($x) => $x['status_mutasi'] === 'draft'));
            $nb = count(array_filter($items_b, fn($x) => $x['status_mutasi'] === 'batal'));
            $nk = count(array_filter($items_b, fn($x) => $x['jenis'] === 'keduanya'));
            $nl = count(array_filter($items_b, fn($x) => $x['jenis'] === 'pindah_lokasi'));
            $np = count(array_filter($items_b, fn($x) => $x['jenis'] === 'pindah_pic'));
            $na = count(array_unique(array_column(array_filter($items_b, fn($x) => $x['aset_id']), 'aset_id')));
            $nt = count($items_b);
            $pct = $total > 0 ? round($nt / $total * 100) : 0;
            $ts_bln = strtotime($bln . '-01');
            $bln_label = ($BULAN_ID[(int)date('n',$ts_bln)] ?? '?') . ' ' . date('Y',$ts_bln);
        ?>
        <tr>
            <td style="text-align:center;color:#94a3b8;"><?= $no_b ?></td>
            <td style="font-weight:bold;"><?php $ts2=strtotime($bln.'-01'); echo ($BULAN_ID[(int)date('n',$ts2)]??'?').' '.date('Y',$ts2); ?></td>
            <td style="text-align:center;font-weight:bold;"><?= $nt ?></td>
            <td style="text-align:center;color:#059669;font-weight:bold;"><?= $ns ?></td>
            <td style="text-align:center;color:#d97706;"><?= $nd ?></td>
            <td style="text-align:center;color:#dc2626;"><?= $nb ?></td>
            <td style="text-align:center;color:#7c3aed;font-weight:bold;"><?= $nk ?></td>
            <td style="text-align:center;color:#1d4ed8;"><?= $nl ?></td>
            <td style="text-align:center;color:#059669;"><?= $np ?></td>
            <td style="text-align:center;color:#0f766e;font-weight:bold;"><?= $na ?></td>
            <td>
                <div class="bar-wrap" style="width:70px;">
                    <div class="bar-fill" style="width:<?= $pct ?>%;background:#7c3aed;"></div>
                </div>
                <span style="font-size:7.5pt;margin-left:3px;"><?= $pct ?>%</span>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTAL KESELURUHAN</td>
            <td style="text-align:center;"><?= $total ?></td>
            <td style="text-align:center;color:#059669;"><?= $n_selesai ?></td>
            <td style="text-align:center;color:#d97706;"><?= $n_draft ?></td>
            <td style="text-align:center;color:#dc2626;"><?= $n_batal ?></td>
            <td style="text-align:center;color:#7c3aed;"><?= $n_keduanya ?></td>
            <td style="text-align:center;color:#1d4ed8;"><?= $n_lok ?></td>
            <td style="text-align:center;color:#059669;"><?= $n_pic ?></td>
            <td style="text-align:center;color:#0f766e;"><?= count($aset_unik) ?></td>
            <td>100%</td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<!-- ═══════════════════ IV. RINGKASAN BAGIAN TUJUAN ═══════════════════ -->
<?php if (!empty($bagian_tujuan) && count($bagian_tujuan) > 1): ?>
<div class="sec">
    <div class="sec-num">Bagian <?= count($by_bulan) > 1 ? 'IV' : 'III' ?></div>
    <div class="sec-title"><span class="sec-title-text">Top Bagian Penerima Aset</span></div>
</div>
<table class="summ-tbl">
    <thead>
        <tr>
            <th style="width:28px;">No.</th>
            <th>Bagian / Lokasi Tujuan</th>
            <th style="text-align:center;">Jumlah Mutasi Masuk</th>
            <th>Proporsi</th>
        </tr>
    </thead>
    <tbody>
        <?php $no_bg = 0; foreach (array_slice($bagian_tujuan, 0, 10, true) as $bg => $cnt_bg):
            $no_bg++;
            $pct_bg = $total > 0 ? round($cnt_bg / $total * 100) : 0;
        ?>
        <tr>
            <td style="text-align:center;color:#94a3b8;"><?= $no_bg ?></td>
            <td style="font-weight:bold;"><?= htmlspecialchars($bg) ?></td>
            <td style="text-align:center;font-weight:bold;color:#7c3aed;"><?= $cnt_bg ?></td>
            <td>
                <div class="bar-wrap" style="width:100px;">
                    <div class="bar-fill" style="width:<?= $pct_bg ?>%;background:#7c3aed;"></div>
                </div>
                <span style="font-size:7.5pt;margin-left:4px;"><?= $pct_bg ?>%</span>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ═══════════════════ V. DETAIL MUTASI PER PERIODE ═══════════════════ -->
<?php
$sec_num_detail = 'IV';
if (count($by_bulan) > 1) $sec_num_detail = 'V';
elseif (!empty($bagian_tujuan) && count($bagian_tujuan) > 1) $sec_num_detail = 'IV';
?>
<div class="sec">
    <div class="sec-num">Bagian <?= $sec_num_detail ?></div>
    <div class="sec-title">
        <span class="sec-title-text">Detail Riwayat Mutasi</span>
        <span class="sec-title-rule">Diurutkan per Tanggal Mutasi &nbsp;|&nbsp; Baris merah muda = Dibatalkan</span>
    </div>
</div>

<?php if (empty($list)): ?>
<div style="text-align:center;color:#94a3b8;padding:20px;border:1px dashed #e2e8f0;border-radius:4px;font-style:italic;">
    Tidak ada data mutasi untuk periode dan filter yang dipilih.
</div>
<?php else:
    // Tampilkan per bulan jika multi-bulan, flat jika satu bulan
    $grouped = count($by_bulan) > 1 ? $by_bulan : ['Semua' => $list];
    foreach ($grouped as $bln_key => $items_grp):
        $bln_label_disp = $bln_key === 'Semua' ? '' : (function($bln) use ($BULAN_ID) {
            $ts = strtotime($bln . '-01');
            return ($BULAN_ID[(int)date('n',$ts)] ?? '?') . ' ' . date('Y',$ts);
        })($bln_key);
        $n_selesai_grp  = count(array_filter($items_grp, fn($x) => $x['status_mutasi'] === 'selesai'));
        $n_batal_grp    = count(array_filter($items_grp, fn($x) => $x['status_mutasi'] === 'batal'));
?>

<?php if ($bln_key !== 'Semua'): ?>
<div class="kat-header">
    <span class="kat-header-l"><?= htmlspecialchars($bln_label_disp) ?></span>
    <span class="kat-header-r">
        <?= count($items_grp) ?> mutasi &nbsp;|&nbsp;
        Selesai: <?= $n_selesai_grp ?> &nbsp;
        Batal: <?= $n_batal_grp ?>
    </span>
</div>
<?php endif; ?>

<table class="data-tbl">
    <thead>
        <tr>
            <th style="width:22px;">#</th>
            <th style="width:85px;">No. Mutasi</th>
            <th style="width:75px;">Tanggal</th>
            <th style="width:48px;text-align:center;">Jenis</th>
            <th style="width:90px;">No. Inventaris</th>
            <th style="width:100px;">Nama Aset</th>
            <th>Dari (Asal)</th>
            <th>Ke (Tujuan)</th>
            <th style="width:60px;">Kondisi</th>
            <th style="width:55px;">Status Pakai</th>
            <th style="width:55px;text-align:center;">Status Mut.</th>
            <th style="width:70px;">Dibuat Oleh</th>
        </tr>
    </thead>
    <tbody>
        <?php $no_item = 0; foreach ($items_grp as $m):
            $no_item++;
            $smst = statusMuStyle($m['status_mutasi']);
            $kst  = kondisiStyle2($m['kondisi_sesudah'] ?? '');
            $row_cls = $m['status_mutasi'] === 'batal' ? 'row-batal' : '';

            // Kondisi: sebelum → sesudah
            $kond_sebelum = $m['kondisi_sebelum'] ?? '';
            $kond_sesudah = $m['kondisi_sesudah'] ?? '';
            $kond_changed = $kond_sebelum !== $kond_sesudah && $kond_sesudah !== '';
        ?>
        <tr class="<?= $row_cls ?>">
            <td style="text-align:center;color:#94a3b8;"><?= $no_item ?></td>
            <td><span class="no-mut-code"><?= htmlspecialchars($m['no_mutasi']) ?></span></td>
            <td style="font-size:7.5pt;color:#374151;white-space:nowrap;">
                <?= tglIdM($m['tanggal_mutasi']) ?>
                <br><span style="color:#94a3b8;"><?= date('H:i', strtotime($m['created_at'])) ?></span>
            </td>
            <td style="text-align:center;">
                <span style="font-size:7pt;font-weight:bold;color:<?= jenisColor($m['jenis']) ?>;">
                    <?= jenisLabel($m['jenis']) ?>
                </span>
            </td>
            <td>
                <span class="inv-code"><?= htmlspecialchars($m['no_inventaris'] ?? '—') ?></span>
                <?php if ($m['kategori']): ?>
                <br><span style="font-size:7pt;color:#94a3b8;"><?= htmlspecialchars($m['kategori']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <strong style="font-size:8pt;"><?= htmlspecialchars($m['nama_aset'] ?? '—') ?></strong>
                <?php if ($m['merek'] || $m['model_aset']): ?>
                <br><span style="font-size:7pt;color:#94a3b8;"><?= htmlspecialchars(trim($m['merek'].' '.$m['model_aset'])) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <!-- DARI -->
                <div class="pihak-dari">
                    <?php if ($m['dari_bagian_nama']): ?>
                    <strong><?= htmlspecialchars($m['dari_bagian_nama']) ?></strong>
                    <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                    <?php if ($m['dari_pic_nama']): ?>
                    <br><span style="color:#94a3b8;"><?= htmlspecialchars($m['dari_pic_nama']) ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <!-- KE -->
                <div class="pihak-ke">
                    <?php if ($m['ke_bagian_nama']): ?>
                    <strong><?= htmlspecialchars($m['ke_bagian_nama']) ?></strong>
                    <?php elseif ($m['jenis'] === 'pindah_pic'): ?>
                    <span style="color:#94a3b8;font-style:italic;">(lokasi sama)</span>
                    <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                    <?php if ($m['ke_pic_nama']): ?>
                    <br><span style="color:#94a3b8;"><?= htmlspecialchars($m['ke_pic_nama']) ?></span>
                    <?php elseif ($m['jenis'] === 'pindah_lokasi'): ?>
                    <br><span style="color:#94a3b8;font-style:italic;">(PIC sama)</span>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <?php if ($kond_changed): ?>
                <!-- Kondisi berubah: tampilkan sebelum→sesudah -->
                <?php $ks_b = kondisiStyle2($kond_sebelum); ?>
                <span class="mut-badge" style="background:<?= $ks_b['bg'] ?>;color:<?= $ks_b['fg'] ?>;font-size:6.5pt;">
                    <?= htmlspecialchars($kond_sebelum) ?>
                </span>
                <span style="color:#94a3b8;font-size:8pt;">&rarr;</span>
                <span class="mut-badge" style="background:<?= $kst['bg'] ?>;color:<?= $kst['fg'] ?>;font-size:6.5pt;">
                    <?= htmlspecialchars($kond_sesudah) ?>
                </span>
                <?php elseif ($kond_sesudah): ?>
                <span class="mut-badge" style="background:<?= $kst['bg'] ?>;color:<?= $kst['fg'] ?>;">
                    <?= htmlspecialchars($kond_sesudah) ?>
                </span>
                <?php else: ?>
                <span style="color:#cbd5e1;">—</span>
                <?php endif; ?>
            </td>
            <td style="font-size:7.5pt;color:#374151;"><?= htmlspecialchars($m['status_pakai'] ?? 'Terpakai') ?></td>
            <td style="text-align:center;">
                <span class="mut-badge" style="background:<?= $smst['bg'] ?>;color:<?= $smst['fg'] ?>;">
                    <?= ucfirst(htmlspecialchars($m['status_mutasi'])) ?>
                </span>
            </td>
            <td style="font-size:7.5pt;color:#475569;"><?= htmlspecialchars($m['dibuat_nama'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">Subtotal: <?= $bln_key !== 'Semua' ? htmlspecialchars($bln_label_disp) : 'Keseluruhan' ?></td>
            <td colspan="7"><?= count($items_grp) ?> mutasi &nbsp;|&nbsp;
                <span style="color:#059669;">Selesai: <?= $n_selesai_grp ?></span> &nbsp;
                <span style="color:#dc2626;">Batal: <?= $n_batal_grp ?></span>
            </td>
            <td></td>
        </tr>
    </tfoot>
</table>

<?php endforeach; endif; ?>

<!-- ═══════════════════ KETERANGAN ═══════════════════ -->
<table style="width:100%;border-collapse:collapse;margin-top:10px;border:1px solid #e2e8f0;font-size:8pt;">
    <tr>
        <td style="padding:5px 9px;background:#f8fafc;font-weight:bold;color:#475569;border-right:1px solid #e2e8f0;width:12%;">Keterangan</td>
        <td style="padding:5px 8px;border-right:1px solid #e2e8f0;">
            <span class="mut-badge" style="background:#7c3aed;color:#fff;">Lok+PIC</span> Pindah Lokasi &amp; PIC
        </td>
        <td style="padding:5px 8px;border-right:1px solid #e2e8f0;">
            <span class="mut-badge" style="background:#1d4ed8;color:#fff;">Lokasi</span> Pindah Lokasi saja
        </td>
        <td style="padding:5px 8px;border-right:1px solid #e2e8f0;">
            <span class="mut-badge" style="background:#059669;color:#fff;">PIC</span> Pindah PIC saja
        </td>
        <td style="padding:5px 8px;border-right:1px solid #e2e8f0;">
            <span class="mut-badge" style="background:#d1fae5;color:#065f46;">Selesai</span>
            <span class="mut-badge" style="background:#fef3c7;color:#92400e;">Draft</span>
            <span class="mut-badge" style="background:#fee2e2;color:#991b1b;">Batal</span>
        </td>
        <td style="padding:5px 8px;background:#fef2f2;">
            Baris merah muda = Mutasi <strong>Dibatalkan</strong>
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
        FixSmart Helpdesk &mdash; Laporan Mutasi Aset IT &mdash; Dicetak: <?= date('d/m/Y H:i:s') ?> WIB<br>
        Dokumen ini bersifat rahasia dan hanya untuk keperluan internal manajemen.
    </div>
    <div class="pf-right">
        No. Dok: <?= $no_dok ?> &nbsp;|&nbsp; Total: <?= $total ?> mutasi<br>
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

$periode_slug = date('Ymd', strtotime($dari)) . '_' . date('Ymd', strtotime($sampai));
$st_slug      = $fstatus !== '' ? '_' . $fstatus : '';
$jn_slug      = $fjenis  !== '' ? '_' . str_replace('_','',$fjenis) : '';
$filename     = 'Laporan_Mutasi_Aset_IT_' . $periode_slug . $st_slug . $jn_slug . '_' . date('His') . '.pdf';

$dompdf->stream($filename, ['Attachment' => false]);
exit;