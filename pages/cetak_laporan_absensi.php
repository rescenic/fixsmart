<?php
/**
 * cetak_laporan_absensi.php
 * Cetak Laporan Absensi — Bulanan & Mingguan
 * Desain seragam dengan laporan_aset_it (dompdf, A4 landscape)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'hrd'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

// ── dompdf ───────────────────────────────────────────────────────────────────
$dompdf_available = false;
$dp_autoload      = '';
foreach ([
    __DIR__ . '/../dompdf/autoload.inc.php',
    __DIR__ . '/../dompdf/vendor/autoload.php',
    __DIR__ . '/../vendor/dompdf/dompdf/autoload.inc.php',
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__) . '/dompdf/autoload.inc.php',
    dirname(__DIR__) . '/vendor/autoload.php',
] as $p) {
    if (file_exists($p)) { $dp_autoload = $p; $dompdf_available = true; break; }
}

// ── Helper tanggal ───────────────────────────────────────────────────────────
$NAMA_BULAN = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
    5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
    9=>'September',10=>'Oktober',11=>'November',12=>'Desember',
];
$NAMA_HARI_P = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'];

function tglId(string $raw, bool $withTime = false): string {
    global $NAMA_BULAN;
    $ts = strtotime($raw);
    if (!$ts) return '-';
    $str = (int)date('j',$ts) . ' ' . ($NAMA_BULAN[(int)date('n',$ts)] ?? '?') . ' ' . date('Y',$ts);
    if ($withTime) $str .= ', ' . date('H:i',$ts);
    return $str;
}

function statusBadge(string $st): array {
    return match($st) {
        'hadir'        => ['bg'=>'#dcfce7','fg'=>'#15803d','lbl'=>'Hadir'],
        'terlambat'    => ['bg'=>'#fef3c7','fg'=>'#a16207','lbl'=>'Terlambat'],
        'alpha'        => ['bg'=>'#fee2e2','fg'=>'#b91c1c','lbl'=>'Alpha'],
        'izin'         => ['bg'=>'#fff7ed','fg'=>'#c2410c','lbl'=>'Izin'],
        'cuti'         => ['bg'=>'#faf5ff','fg'=>'#6d28d9','lbl'=>'Cuti'],
        'dinas'        => ['bg'=>'#e0f2fe','fg'=>'#0369a1','lbl'=>'Dinas'],
        'setengah_hari'=> ['bg'=>'#fef9c3','fg'=>'#a16207','lbl'=>'1/2 Hari'],
        'libur'        => ['bg'=>'#f1f5f9','fg'=>'#475569','lbl'=>'Libur'],
        default        => ['bg'=>'#f1f5f9','fg'=>'#64748b','lbl'=>ucfirst($st)],
    };
}

// ── Parameter ────────────────────────────────────────────────────────────────
$mode      = in_array($_GET['mode'] ?? '', ['bulanan','mingguan']) ? $_GET['mode'] : 'bulanan';
$bulan     = (int)($_GET['bulan']   ?? date('n'));
$tahun     = (int)($_GET['tahun']   ?? date('Y'));
$bagian_id = (int)($_GET['bagian']  ?? 0);
$f_nama    = trim($_GET['nama']     ?? '');
$minggu_ke = max(1, min(6, (int)($_GET['minggu'] ?? 1)));

if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
if ($tahun < 2020 || $tahun > 2040) $tahun = (int)date('Y');

// ── Rentang tanggal ───────────────────────────────────────────────────────────
$tgl_awal_bln  = sprintf('%04d-%02d-01', $tahun, $bulan);
$tgl_akhir_bln = date('Y-m-t', strtotime($tgl_awal_bln));
$jml_hari_bln  = (int)date('t', strtotime($tgl_awal_bln));

if ($mode === 'mingguan') {
    $d_awal    = ($minggu_ke - 1) * 7 + 1;
    $d_akhir   = min($d_awal + 6, $jml_hari_bln);
    $tgl_awal  = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d_awal);
    $tgl_akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d_akhir);
    $judul_periode = "Minggu ke-{$minggu_ke} ({$d_awal}-{$d_akhir} " . $NAMA_BULAN[$bulan] . " {$tahun})";
} else {
    $tgl_awal      = $tgl_awal_bln;
    $tgl_akhir     = $tgl_akhir_bln;
    $judul_periode = $NAMA_BULAN[$bulan] . ' ' . $tahun;
}

$tgl_list = [];
$cur = strtotime($tgl_awal);
$end = strtotime($tgl_akhir);
while ($cur <= $end) {
    $tgl = date('Y-m-d', $cur);
    $dow = (int)date('N', $cur);
    $tgl_list[] = ['tgl'=>$tgl,'dow'=>$dow,'weekend'=>($dow>=6),'hari'=>(int)date('j',$cur)];
    $cur = strtotime('+1 day', $cur);
}
$jml_hari_periode = count($tgl_list);

// ── Minggu options ────────────────────────────────────────────────────────────
$minggu_options = [];
for ($m = 1; $m <= 6; $m++) {
    $da = ($m-1)*7+1;
    if ($da > $jml_hari_bln) break;
    $dz = min($da+6, $jml_hari_bln);
    $minggu_options[$m] = "Minggu {$m} ({$da}-{$dz} " . $NAMA_BULAN[$bulan] . ")";
}

// ── Bagian ────────────────────────────────────────────────────────────────────
$bagian_list = [];
try { $bagian_list = $pdo->query("SELECT id,nama FROM bagian WHERE status='aktif' ORDER BY urutan,nama")->fetchAll(); } catch(Exception $e){}
$nama_bagian_filter = '';
foreach ($bagian_list as $b) { if ($b['id'] == $bagian_id) { $nama_bagian_filter = $b['nama']; break; } }

// ── Karyawan ──────────────────────────────────────────────────────────────────
$w_bag  = $bagian_id ? "AND u.bagian_id=".(int)$bagian_id : '';
$w_nama = ''; $p_nama = [];
if ($f_nama !== '') { $w_nama = "AND u.nama LIKE ?"; $p_nama = ["%$f_nama%"]; }
$karyawan = [];
try {
    $stm = $pdo->prepare("
        SELECT u.id, u.nama, u.divisi,
               COALESCE(s.gelar_depan,'')    gelar_depan,
               COALESCE(s.gelar_belakang,'') gelar_belakang,
               COALESCE(s.nik_rs,'')         nik_rs,
               COALESCE(s.jenis_karyawan,'') jenis_karyawan
        FROM users u
        LEFT JOIN sdm_karyawan s ON s.user_id = u.id
        WHERE u.status = 'aktif' $w_bag $w_nama
        ORDER BY u.divisi, u.nama
    ");
    $stm->execute($p_nama);
    $karyawan = $stm->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){}

// ── Absensi ───────────────────────────────────────────────────────────────────
$absen_map = [];
try {
    $stm = $pdo->prepare("
        SELECT user_id, tanggal, status,
               COALESCE(terlambat_menit,0) terlambat_menit,
               COALESCE(durasi_kerja,0)    durasi_kerja,
               jam_masuk, jam_keluar
        FROM absensi WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal
    ");
    $stm->execute([$tgl_awal, $tgl_akhir]);
    foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $a)
        $absen_map[$a['user_id']][$a['tanggal']] = $a;
} catch(Exception $e){}

// ── Jadwal ────────────────────────────────────────────────────────────────────
$jadwal_map = [];
try {
    $stm = $pdo->prepare("SELECT user_id, tanggal, tipe FROM jadwal_karyawan WHERE tanggal BETWEEN ? AND ?");
    $stm->execute([$tgl_awal, $tgl_akhir]);
    foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $j)
        $jadwal_map[$j['user_id']][$j['tanggal']] = $j['tipe'];
} catch(Exception $e){}

// ── Rekap ─────────────────────────────────────────────────────────────────────
$rekap = [];
foreach ($karyawan as $k) {
    $r = ['hadir'=>0,'terlambat'=>0,'alpha'=>0,'izin'=>0,'cuti'=>0,'dinas'=>0,
          'libur'=>0,'setengah_hari'=>0,'hari_kerja'=>0,'total_terlambat'=>0,'total_durasi'=>0];
    foreach ($tgl_list as $td) {
        $jdw = $jadwal_map[$k['id']][$td['tgl']] ?? null;
        $ab  = $absen_map[$k['id']][$td['tgl']]  ?? null;
        if ($jdw === 'libur') { $r['libur']++; continue; }
        $r['hari_kerja']++;
        if ($ab) {
            $st = $ab['status'] ?? 'hadir';
            if (isset($r[$st])) $r[$st]++; else $r['hadir']++;
            $r['total_terlambat'] += max(0,(int)($ab['terlambat_menit']??0));
            $r['total_durasi']    += max(0,(int)($ab['durasi_kerja']??0));
        } elseif (in_array($jdw, ['cuti','izin','dinas'])) {
            $r[$jdw]++;
        }
    }
    $r['pct_hadir'] = $r['hari_kerja'] > 0
        ? round(($r['hadir']+$r['terlambat']+$r['setengah_hari'])/$r['hari_kerja']*100) : 0;
    $rekap[$k['id']] = $r;
}

$tot = ['hadir'=>0,'terlambat'=>0,'alpha'=>0,'izin'=>0,'cuti'=>0,'dinas'=>0,
        'total_terlambat'=>0,'total_durasi'=>0,'hari_kerja'=>0];
foreach ($rekap as $r) foreach (array_keys($tot) as $k2) $tot[$k2] += ($r[$k2]??0);

$jml_karyawan  = count($karyawan);
$tot_hadir_ef  = $tot['hadir'] + $tot['terlambat'];
$pct_kehadiran = ($jml_karyawan * $jml_hari_periode) > 0
    ? round($tot_hadir_ef / ($jml_karyawan * $jml_hari_periode) * 100) : 0;

$by_div = [];
foreach ($karyawan as $k) {
    $div = $k['divisi'] ?: 'Tanpa Divisi';
    $by_div[$div][] = $k;
}

$pencetak = $_SESSION['nama'] ?? $_SESSION['user_nama'] ?? 'Admin';
$no_dok   = 'LA-' . ($mode==='bulanan'?'BLN':'MGG') . '-'
          . sprintf('%02d',$bulan) . $tahun . '-'
          . strtoupper(substr(md5($mode.$bulan.$tahun.$bagian_id),0,4));

// ═════════════════════════════════════════════════════════════════════════════
// HTML DOKUMEN
// ═════════════════════════════════════════════════════════════════════════════
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
    font-size: 9pt;
    color: #1a1a2e;
    background: #fff;
    line-height: 1.5;
    margin: 36px 44px 36px 44px;
}

/* ── KOP ── */
.kop { display:table; width:100%; padding-bottom:10px; border-bottom:3px solid #0f172a; margin-bottom:4px; }
.kop-logo-area { display:table-cell; vertical-align:middle; width:56px; }
.kop-logo-box  { width:44px; height:44px; background:#0f172a; border-radius:6px; text-align:center; line-height:44px; font-size:14px; color:#fff; font-weight:bold; }
.kop-text  { display:table-cell; vertical-align:middle; padding-left:11px; }
.kop-org   { font-size:14pt; font-weight:bold; color:#0f172a; line-height:1.2; }
.kop-sub   { font-size:8pt; color:#475569; margin-top:1px; }
.kop-right { display:table-cell; vertical-align:middle; text-align:right; }
.kop-right-label { font-size:7pt; color:#94a3b8; letter-spacing:1px; text-transform:uppercase; }
.kop-right-val   { font-size:8.5pt; color:#334155; font-weight:bold; }
.kop-rule2 { height:1px; background:#cbd5e1; margin-bottom:14px; }

/* ── JUDUL ── */
.report-title-block { text-align:center; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #e2e8f0; }
.report-label       { font-size:7pt; letter-spacing:3px; text-transform:uppercase; color:#64748b; margin-bottom:4px; }
.report-main-title  { font-size:14pt; font-weight:bold; color:#0f172a; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.report-sub         { font-size:10pt; color:#1d4ed8; font-weight:bold; }
.report-no          { font-size:7.5pt; color:#94a3b8; margin-top:4px; }

/* ── INFO DOKUMEN ── */
.doc-info { width:100%; border-collapse:collapse; margin-bottom:16px; border:1px solid #e2e8f0; }
.doc-info td { padding:5px 9px; font-size:8.5pt; border:1px solid #e2e8f0; vertical-align:top; }
.di-label { background:#f8fafc; color:#475569; font-weight:bold; width:18%; }
.di-val   { color:#1e293b; width:32%; }

/* ── SECTION ── */
.sec { margin-top:16px; margin-bottom:8px; page-break-after:avoid; }
.sec-num   { font-size:7pt; letter-spacing:2px; text-transform:uppercase; color:#94a3b8; margin-bottom:2px; }
.sec-title { font-size:10pt; font-weight:bold; color:#0f172a; border-bottom:2px solid #1d4ed8; padding-bottom:4px; display:table; width:100%; }
.sec-title-text { display:table-cell; }
.sec-title-rule { display:table-cell; text-align:right; font-size:7.5pt; font-weight:normal; color:#94a3b8; vertical-align:bottom; padding-bottom:1px; font-style:italic; }

/* ── KPI CARDS ── */
.kpi-table { width:100%; border-collapse:separate; border-spacing:6px; margin:0 -6px; }
.kpi-td    { vertical-align:top; }
.kpi-card  { border:1px solid #e2e8f0; border-top:3px solid #1d4ed8; padding:10px 12px 8px; background:#fff; }
.k-label   { font-size:7pt; letter-spacing:1px; text-transform:uppercase; color:#64748b; margin-bottom:4px; }
.k-val     { font-size:18pt; font-weight:bold; line-height:1; margin-bottom:3px; }
.k-desc    { font-size:7.5pt; color:#94a3b8; line-height:1.4; }

/* ── EXEC BOX ── */
.exec-box { border-left:4px solid #1d4ed8; background:#f0f6ff; padding:10px 14px; margin-bottom:6px; font-size:9pt; color:#1e293b; line-height:1.7; }

/* ── DIVISI HEADER ── */
.div-header { background:#1d4ed8; color:#fff; padding:6px 10px; font-size:9pt; font-weight:bold; margin-top:14px; display:table; width:100%; }
.div-header-l { display:table-cell; }
.div-header-r { display:table-cell; text-align:right; font-size:7.5pt; font-weight:normal; color:rgba(255,255,255,.7); }

/* ── DATA TABLE ── */
.data-tbl { width:100%; border-collapse:collapse; font-size:8pt; page-break-inside:auto; }
.data-tbl thead tr { background:#0f172a; color:#fff; }
.data-tbl thead th { padding:7px 8px; text-align:left; font-size:7.5pt; font-weight:bold; letter-spacing:.3px; border-right:1px solid rgba(255,255,255,.08); }
.data-tbl thead th:last-child { border-right:none; }
.data-tbl thead th.tc { text-align:center; }
.data-tbl tbody td { padding:6px 8px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.data-tbl tbody tr:nth-child(even) td { background:#f8fafc; }
.data-tbl tbody tr:last-child td { border-bottom:2px solid #e2e8f0; }
.data-tbl tfoot td { padding:6px 8px; font-weight:bold; background:#eff6ff; border-top:2px solid #bfdbfe; color:#1e3a8a; font-size:8.5pt; }
.data-tbl tfoot td.tc { text-align:center; }
.tc  { text-align:center; }
.n-g { color:#15803d; font-weight:bold; }
.n-y { color:#a16207; font-weight:bold; }
.n-r { color:#b91c1c; font-weight:bold; }
.n-o { color:#c2410c; font-weight:bold; }
.n-p { color:#6d28d9; font-weight:bold; }
.n-b { color:#0369a1; font-weight:bold; }
.n-0 { color:#d1d5db; }
.alpha-b { background:#fee2e2; color:#b91c1c; padding:2px 6px; font-weight:bold; font-size:7.5pt; }
.bar-wrap { background:#e2e8f0; height:5px; overflow:hidden; display:inline-block; vertical-align:middle; }
.bar-fill { height:5px; }

/* ── RINGKASAN DIVISI ── */
.summ-tbl { width:100%; border-collapse:collapse; font-size:8.5pt; }
.summ-tbl th { background:#0f172a; color:#fff; padding:7px 9px; font-size:8pt; text-align:left; }
.summ-tbl th.tc { text-align:center; }
.summ-tbl td { padding:6px 9px; border-bottom:1px solid #f1f5f9; }
.summ-tbl tr:nth-child(even) td { background:#f8fafc; }
.summ-tbl tfoot td { background:#eff6ff; border-top:2px solid #bfdbfe; font-weight:bold; color:#1e3a8a; }
.summ-tbl tfoot td.tc { text-align:center; }

/* ── KALENDER ── */
.cal-tbl { width:100%; border-collapse:collapse; font-size:7.5pt; page-break-inside:avoid; }
.cal-tbl th { padding:4px 3px; background:#0f172a; color:#fff; border:1px solid #1e293b; text-align:center; font-size:7pt; font-weight:bold; }
.cal-tbl th.cal-nm { text-align:left; padding:4px 8px; min-width:130px; }
.cal-tbl td { padding:2px; border:1px solid #e8ecf2; text-align:center; }
.cal-tbl td.cal-nm-td { text-align:left; padding:4px 7px; background:#fafbfc; border-right:2px solid #cbd5e1; font-size:7.5pt; font-weight:bold; white-space:nowrap; }
.cal-tbl td.wknd { background:#fafaf8; }
.cal-tbl tr:nth-child(even) td { background:#fafbfc; }
.cal-tbl tr:nth-child(even) td.wknd { background:#f5f5f0; }
.cal-dot { width:22px; height:22px; display:inline-block; text-align:center; line-height:22px; font-size:8pt; font-weight:800; color:#fff; }
.H { background:#10b981; } .T { background:#f59e0b; } .A { background:#ef4444; }
.I { background:#f97316; } .C { background:#8b5cf6; } .D { background:#0ea5e9; }
.L { background:#e2e8f0; color:#94a3b8; } .S { background:#fbbf24; color:#1e293b; }
.E { background:#f8fafc; color:#e2e8f0; border:1px solid #f1f5f9; font-size:0; }

/* ── JAM DETAIL ── */
.jam-head-blk { background:#0f172a; color:#fff; padding:7px 10px; margin-top:12px; display:table; width:100%; page-break-after:avoid; }
.jam-head-l { display:table-cell; vertical-align:middle; }
.jam-head-r { display:table-cell; vertical-align:middle; text-align:right; }
.jam-nama { font-size:9pt; font-weight:bold; color:#fff; }
.jam-sub  { font-size:7.5pt; color:rgba(255,255,255,.5); margin-top:1px; }
.jam-stat { display:inline-block; text-align:center; padding:0 10px; }
.jam-stat-num { font-size:12pt; font-weight:bold; line-height:1; }
.jam-stat-lbl { font-size:6.5pt; text-transform:uppercase; letter-spacing:.3px; color:rgba(255,255,255,.5); margin-top:1px; }
.jam-badge { display:inline-block; padding:2px 7px; font-size:7.5pt; font-weight:bold; }

/* ── TANDA TANGAN ── */
.ttd-section { margin-top:24px; display:table; width:100%; }
.ttd-box  { display:table-cell; width:33.3%; text-align:center; padding:0 10px; vertical-align:top; }
.ttd-title{ font-size:8.5pt; color:#475569; margin-bottom:48px; }
.ttd-line { border-top:1px solid #334155; margin:0 12px 4px; }
.ttd-name { font-size:8.5pt; font-weight:bold; color:#0f172a; }
.ttd-role { font-size:7.5pt; color:#64748b; margin-top:2px; }

/* ── FOOTER ── */
.page-footer { margin-top:18px; padding-top:7px; border-top:1px solid #cbd5e1; display:table; width:100%; }
.pf-left  { display:table-cell; font-size:7pt; color:#94a3b8; vertical-align:middle; }
.pf-right { display:table-cell; text-align:right; font-size:7pt; color:#94a3b8; vertical-align:middle; }
.ket-badge { display:inline-block; padding:2px 7px; font-size:7.5pt; font-weight:bold; }
</style>
</head>
<body>

<!-- ══════ KOP ══════ -->
<div class="kop">
  <div class="kop-logo-area"><div class="kop-logo-box">SDM</div></div>
  <div class="kop-text">
    <div class="kop-org"><?= defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'Sistem Informasi Rumah Sakit' ?></div>
    <div class="kop-sub">Departemen SDM / HRD &mdash; Laporan Kehadiran Karyawan</div>
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

<!-- ══════ JUDUL ══════ -->
<div class="report-title-block">
  <div class="report-label">Laporan Resmi &mdash; Internal HRD</div>
  <div class="report-main-title">Laporan Absensi Karyawan</div>
  <div class="report-sub">
    <?= $mode==='bulanan' ? 'Rekap Bulan ' : 'Rekap Mingguan &mdash; ' ?><?= htmlspecialchars($judul_periode) ?>
    <?php if ($nama_bagian_filter): ?>&nbsp;&mdash;&nbsp; Unit: <?= htmlspecialchars($nama_bagian_filter) ?><?php endif; ?>
  </div>
  <div class="report-no">
    Disiapkan oleh: <?= htmlspecialchars($pencetak) ?>
    &nbsp;|&nbsp; Dicetak: <?= tglId(date('Y-m-d H:i:s'), true) ?> WIB
  </div>
</div>

<!-- ══════ INFO DOKUMEN ══════ -->
<table class="doc-info">
  <tr>
    <td class="di-label">Unit / Departemen</td>
    <td class="di-val">SDM / HRD</td>
    <td class="di-label">Tanggal Cetak</td>
    <td class="di-val"><?= tglId(date('Y-m-d H:i:s'), true) ?> WIB</td>
  </tr>
  <tr>
    <td class="di-label">Jenis Laporan</td>
    <td class="di-val">Rekap Absensi <?= $mode==='bulanan'?'Bulanan':'Mingguan' ?></td>
    <td class="di-label">Status Dokumen</td>
    <td class="di-val">Final &mdash; Confidential</td>
  </tr>
  <tr>
    <td class="di-label">Periode</td>
    <td class="di-val"><strong><?= htmlspecialchars($judul_periode) ?></strong></td>
    <td class="di-label">Rentang Tanggal</td>
    <td class="di-val"><?= tglId($tgl_awal) ?> &mdash; <?= tglId($tgl_akhir) ?></td>
  </tr>
  <tr>
    <td class="di-label">Filter Unit</td>
    <td class="di-val"><?= $nama_bagian_filter ?: 'Semua Unit' ?></td>
    <td class="di-label">Jumlah Karyawan</td>
    <td class="di-val"><strong><?= $jml_karyawan ?> orang</strong> &nbsp;|&nbsp; <?= count($by_div) ?> divisi</td>
  </tr>
</table>

<!-- ══════ I. RINGKASAN EKSEKUTIF ══════ -->
<div class="sec">
  <div class="sec-num">Bagian I</div>
  <div class="sec-title">
    <span class="sec-title-text">Ringkasan Eksekutif</span>
    <span class="sec-title-rule">Executive Summary</span>
  </div>
</div>
<div class="exec-box">
  Laporan ini menyajikan data kehadiran karyawan untuk periode <strong><?= htmlspecialchars($judul_periode) ?></strong>
  <?php if ($nama_bagian_filter): ?>, Unit: <strong><?= htmlspecialchars($nama_bagian_filter) ?></strong><?php endif; ?>.
  Total karyawan yang dicakup: <strong><?= $jml_karyawan ?> orang</strong> dalam <strong><?= count($by_div) ?> divisi</strong>,
  selama <strong><?= $jml_hari_periode ?> hari</strong>.
  Tercatat <strong><?= $tot_hadir_ef ?> hari-orang hadir efektif</strong>
  (termasuk <?= $tot['terlambat'] ?> terlambat),
  <strong><?= $tot['alpha'] ?> alpha</strong>,
  <strong><?= $tot['izin'] ?> izin</strong>,
  <strong><?= $tot['cuti'] ?> cuti</strong>,
  <strong><?= $tot['dinas'] ?> dinas</strong>.
  Akumulasi keterlambatan: <strong><?= $tot['total_terlambat'] ?> menit</strong>.
  Tingkat kehadiran rata-rata: <strong><?= $pct_kehadiran ?>%</strong>.
</div>

<!-- ══════ II. STATISTIK ══════ -->
<div class="sec">
  <div class="sec-num">Bagian II</div>
  <div class="sec-title"><span class="sec-title-text">Statistik Kehadiran (Hari-Orang)</span></div>
</div>
<table class="kpi-table">
  <tr>
    <?php foreach ([
        ['Karyawan',    $jml_karyawan,          '#1d4ed8','Jumlah karyawan dalam periode'],
        ['Hadir',       $tot['hadir'],           '#16a34a','Tepat waktu'],
        ['Terlambat',   $tot['terlambat'],       '#d97706','Hadir melebihi jam masuk'],
        ['Alpha',       $tot['alpha'],           '#dc2626','Tanpa keterangan'],
        ['Izin',        $tot['izin'],            '#c2410c','Izin resmi'],
        ['Cuti',        $tot['cuti'],            '#6d28d9','Cuti disetujui'],
        ['Dinas',       $tot['dinas'],           '#0369a1','Perjalanan dinas'],
        ['% Kehadiran', $pct_kehadiran.'%',      $pct_kehadiran>=90?'#16a34a':($pct_kehadiran>=75?'#d97706':'#dc2626'),'Rata-rata kehadiran efektif'],
    ] as [$lbl,$val,$tc,$desc]): ?>
    <td class="kpi-td">
      <div class="kpi-card" style="border-top-color:<?= $tc ?>;">
        <div class="k-label"><?= $lbl ?></div>
        <div class="k-val" style="color:<?= $tc ?>;"><?= $val ?></div>
        <div class="k-desc"><?= $desc ?></div>
      </div>
    </td>
    <?php endforeach; ?>
  </tr>
</table>

<!-- ══════ III. RINGKASAN PER DIVISI ══════ -->
<div class="sec">
  <div class="sec-num">Bagian III</div>
  <div class="sec-title"><span class="sec-title-text">Ringkasan Absensi per Divisi</span></div>
</div>
<table class="summ-tbl">
  <thead>
    <tr>
      <th style="width:24px;">No.</th>
      <th>Divisi / Unit</th>
      <th class="tc">Karyawan</th>
      <th class="tc">Hadir</th>
      <th class="tc">Terlambat</th>
      <th class="tc">Alpha</th>
      <th class="tc">Izin</th>
      <th class="tc">Cuti</th>
      <th class="tc">Dinas</th>
      <th class="tc">Total Tlbt (mnt)</th>
      <th class="tc">% Hadir</th>
      <th>Proporsi</th>
    </tr>
  </thead>
  <tbody>
    <?php $no_d=0; foreach ($by_div as $div => $dkar):
      $no_d++; $dh=0; $dt=0; $da=0; $di=0; $dc=0; $dd=0; $dtl=0; $ddk=0;
      foreach ($dkar as $dk) { $rr=$rekap[$dk['id']]; $dh+=$rr['hadir']; $dt+=$rr['terlambat']; $da+=$rr['alpha']; $di+=$rr['izin']; $dc+=$rr['cuti']; $dd+=$rr['dinas']; $dtl+=$rr['total_terlambat']; $ddk+=$rr['hari_kerja']; }
      $n_div=count($dkar); $dpct=$ddk>0?round(($dh+$dt)/$ddk*100):0; $ppct=$jml_karyawan>0?round($n_div/$jml_karyawan*100):0;
    ?>
    <tr>
      <td class="tc" style="color:#94a3b8;"><?= $no_d ?></td>
      <td style="font-weight:bold;"><?= htmlspecialchars($div) ?></td>
      <td class="tc" style="font-weight:bold;"><?= $n_div ?></td>
      <td class="tc n-g"><?= $dh ?></td>
      <td class="tc n-y"><?= $dt ?></td>
      <td class="tc n-r"><?= $da > 0 ? '<strong>'.$da.'</strong>' : '0' ?></td>
      <td class="tc n-o"><?= $di ?></td>
      <td class="tc n-p"><?= $dc ?></td>
      <td class="tc n-b"><?= $dd ?></td>
      <td class="tc" style="color:<?= $dtl>60?'#b91c1c':($dtl>0?'#a16207':'#94a3b8') ?>;<?= $dtl>60?'font-weight:bold;':'' ?>">
        <?= $dtl>0?$dtl.'m':'—' ?>
      </td>
      <td class="tc" style="font-weight:bold;color:<?= $dpct>=90?'#16a34a':($dpct>=75?'#d97706':'#dc2626') ?>;"><?= $dpct ?>%</td>
      <td>
        <div class="bar-wrap" style="width:70px;"><div class="bar-fill" style="width:<?= $ppct ?>%;background:#1d4ed8;"></div></div>
        <span style="font-size:7.5pt;margin-left:3px;"><?= $ppct ?>%</span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">TOTAL KESELURUHAN</td>
      <td class="tc"><?= $jml_karyawan ?></td>
      <td class="tc" style="color:#15803d;"><?= $tot['hadir'] ?></td>
      <td class="tc" style="color:#a16207;"><?= $tot['terlambat'] ?></td>
      <td class="tc" style="color:#b91c1c;"><?= $tot['alpha'] ?></td>
      <td class="tc" style="color:#c2410c;"><?= $tot['izin'] ?></td>
      <td class="tc" style="color:#6d28d9;"><?= $tot['cuti'] ?></td>
      <td class="tc" style="color:#0369a1;"><?= $tot['dinas'] ?></td>
      <td class="tc" style="color:#a16207;"><?= $tot['total_terlambat'] ?>m</td>
      <td class="tc"><?= $pct_kehadiran ?>%</td>
      <td>100%</td>
    </tr>
  </tfoot>
</table>

<!-- ══════ IV. DETAIL REKAP PER KARYAWAN ══════ -->
<div class="sec">
  <div class="sec-num">Bagian IV</div>
  <div class="sec-title">
    <span class="sec-title-text">Detail Rekap Absensi per Karyawan</span>
    <span class="sec-title-rule">Diurutkan per Divisi</span>
  </div>
</div>
<?php foreach ($by_div as $div => $dkar):
  $n_div_k=count($dkar); $dhd=0; $dht=0; $dha=0;
  foreach ($dkar as $dk2) { $rr2=$rekap[$dk2['id']]; $dhd+=$rr2['hadir']; $dht+=$rr2['terlambat']; $dha+=$rr2['alpha']; }
?>
<div class="div-header">
  <span class="div-header-l">&gt; <?= htmlspecialchars($div) ?></span>
  <span class="div-header-r"><?= $n_div_k ?> karyawan &nbsp;|&nbsp; Hadir: <?= $dhd ?> &nbsp; Terlambat: <?= $dht ?> &nbsp; Alpha: <?= $dha ?></span>
</div>
<table class="data-tbl">
  <thead>
    <tr>
      <th style="width:24px;">#</th>
      <th style="min-width:140px;">Nama Karyawan</th>
      <th style="width:75px;">NIK / NIP</th>
      <th class="tc" style="width:45px;">Hari Kerja</th>
      <th class="tc" style="width:38px;">Hadir</th>
      <th class="tc" style="width:50px;">Terlambat</th>
      <th class="tc" style="width:38px;">Alpha</th>
      <th class="tc" style="width:38px;">Izin</th>
      <th class="tc" style="width:38px;">Cuti</th>
      <th class="tc" style="width:38px;">Dinas</th>
      <th class="tc" style="width:65px;">Total Tlbt</th>
      <th class="tc" style="width:60px;">Jam Kerja</th>
      <th class="tc" style="width:80px;">% Hadir</th>
    </tr>
  </thead>
  <tbody>
  <?php $no_k=0; foreach ($dkar as $k): $r=$rekap[$k['id']]; $no_k++;
    $nama_t=($k['gelar_depan']?$k['gelar_depan'].' ':'').$k['nama'].($k['gelar_belakang']?', '.$k['gelar_belakang']:'');
  ?>
  <tr>
    <td class="tc" style="color:#94a3b8;"><?= $no_k ?></td>
    <td>
      <div style="font-weight:bold;font-size:8pt;"><?= htmlspecialchars($nama_t) ?></div>
      <?php if ($k['jenis_karyawan']): ?><div style="font-size:7pt;color:#94a3b8;"><?= htmlspecialchars($k['jenis_karyawan']) ?></div><?php endif; ?>
    </td>
    <td style="font-family:'Courier New',monospace;font-size:7.5pt;color:#475569;"><?= htmlspecialchars($k['nik_rs']?:'—') ?></td>
    <td class="tc" style="font-weight:600;"><?= $r['hari_kerja'] ?></td>
    <td class="tc n-g"><?= $r['hadir'] ?: '<span class="n-0">0</span>' ?></td>
    <td class="tc n-y"><?= $r['terlambat'] ?: '<span class="n-0">0</span>' ?></td>
    <td class="tc n-r"><?= $r['alpha']>0 ? '<span class="alpha-b">'.$r['alpha'].'</span>' : '<span class="n-0">0</span>' ?></td>
    <td class="tc n-o"><?= $r['izin'] ?: '<span class="n-0">0</span>' ?></td>
    <td class="tc n-p"><?= $r['cuti'] ?: '<span class="n-0">0</span>' ?></td>
    <td class="tc n-b"><?= $r['dinas'] ?: '<span class="n-0">0</span>' ?></td>
    <td class="tc" style="font-size:8pt;color:<?= $r['total_terlambat']>60?'#b91c1c':($r['total_terlambat']>0?'#a16207':'#d1d5db') ?>;<?= $r['total_terlambat']>60?'font-weight:bold;':'' ?>">
      <?= $r['total_terlambat']>0 ? $r['total_terlambat'].'m' : '—' ?>
    </td>
    <td class="tc" style="font-size:8pt;color:#0369a1;">
      <?= $r['total_durasi']>0 ? floor($r['total_durasi']/60).'j '.($r['total_durasi']%60).'m' : '<span class="n-0">—</span>' ?>
    </td>
    <td class="tc">
      <span style="font-weight:bold;font-size:8pt;color:<?= $r['pct_hadir']>=90?'#16a34a':($r['pct_hadir']>=75?'#d97706':'#dc2626') ?>;"><?= $r['pct_hadir'] ?>%</span>
      <div class="bar-wrap" style="width:48px;display:block;margin-top:2px;">
        <div class="bar-fill" style="width:<?= $r['pct_hadir'] ?>%;background:<?= $r['pct_hadir']>=90?'#10b981':($r['pct_hadir']>=75?'#f59e0b':'#ef4444') ?>;"></div>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="2">Subtotal: <?= htmlspecialchars($div) ?></td>
      <td></td><td class="tc">—</td>
      <td class="tc" style="color:#15803d;"><?= array_sum(array_map(fn($dk)=>$rekap[$dk['id']]['hadir'],$dkar)) ?></td>
      <td class="tc" style="color:#a16207;"><?= array_sum(array_map(fn($dk)=>$rekap[$dk['id']]['terlambat'],$dkar)) ?></td>
      <td class="tc" style="color:#b91c1c;"><?= array_sum(array_map(fn($dk)=>$rekap[$dk['id']]['alpha'],$dkar)) ?></td>
      <td class="tc" style="color:#c2410c;"><?= array_sum(array_map(fn($dk)=>$rekap[$dk['id']]['izin'],$dkar)) ?></td>
      <td class="tc" style="color:#6d28d9;"><?= array_sum(array_map(fn($dk)=>$rekap[$dk['id']]['cuti'],$dkar)) ?></td>
      <td class="tc" style="color:#0369a1;"><?= array_sum(array_map(fn($dk)=>$rekap[$dk['id']]['dinas'],$dkar)) ?></td>
      <td class="tc" style="color:#a16207;"><?= array_sum(array_map(fn($dk)=>$rekap[$dk['id']]['total_terlambat'],$dkar)) ?>m</td>
      <td class="tc">—</td><td class="tc">—</td>
    </tr>
  </tfoot>
</table>
<?php endforeach; ?>

<!-- ══════ V. KALENDER KEHADIRAN ══════ -->
<div class="sec">
  <div class="sec-num">Bagian V</div>
  <div class="sec-title">
    <span class="sec-title-text">Kalender Kehadiran</span>
    <span class="sec-title-rule">H=Hadir &nbsp; T=Terlambat &nbsp; A=Alpha &nbsp; I=Izin &nbsp; C=Cuti &nbsp; D=Dinas &nbsp; L=Libur &nbsp; S=1/2 Hari</span>
  </div>
</div>
<?php foreach ($by_div as $div => $dkar): ?>
<div style="margin-bottom:3px;font-size:7.5pt;font-weight:bold;color:#475569;padding:3px 6px;background:#f1f5f9;border-left:3px solid #1d4ed8;">
  <?= htmlspecialchars($div) ?> (<?= count($dkar) ?> orang)
</div>
<table class="cal-tbl" style="margin-bottom:10px;">
  <thead>
    <tr>
      <th class="cal-nm">Nama Karyawan</th>
      <?php foreach ($tgl_list as $td): ?>
      <th class="<?= $td['weekend']?'wknd':'' ?>" style="min-width:26px;">
        <div style="font-size:6pt;opacity:.6;"><?= $NAMA_HARI_P[$td['dow']-1] ?></div>
        <div><?= $td['hari'] ?></div>
      </th>
      <?php endforeach; ?>
      <th style="min-width:24px;background:#1d4ed8;">H</th>
      <th style="min-width:24px;background:#1d4ed8;">T</th>
      <th style="min-width:24px;background:#1d4ed8;">A</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($dkar as $k):
    $rr3=$rekap[$k['id']];
    $nm_s=($k['gelar_depan']?$k['gelar_depan'].' ':'').mb_strimwidth($k['nama'],0,20,'...');
  ?>
  <tr>
    <td class="cal-nm-td">
      <div><?= htmlspecialchars($nm_s) ?></div>
      <?php if ($k['nik_rs']): ?><div style="font-size:7pt;color:#94a3b8;"><?= htmlspecialchars($k['nik_rs']) ?></div><?php endif; ?>
    </td>
    <?php foreach ($tgl_list as $td):
      $jdw5=$jadwal_map[$k['id']][$td['tgl']]??null;
      $ab5 =$absen_map[$k['id']][$td['tgl']] ??null;
      $kode='E';
      if ($jdw5==='libur'&&!$ab5) { $kode='L'; }
      elseif ($ab5) { $st5=$ab5['status']??'hadir'; $km=['hadir'=>'H','terlambat'=>'T','alpha'=>'A','izin'=>'I','cuti'=>'C','dinas'=>'D','setengah_hari'=>'S','libur'=>'L']; $kode=$km[$st5]??'H'; }
      elseif (in_array($jdw5,['cuti','izin','dinas'])) { $km2=['cuti'=>'C','izin'=>'I','dinas'=>'D']; $kode=$km2[$jdw5]; }
    ?>
    <td class="<?= $td['weekend']?'wknd':'' ?>">
      <span class="cal-dot <?= $kode ?>"><?= $kode==='E'?'':($kode==='L'?'-':$kode) ?></span>
    </td>
    <?php endforeach; ?>
    <td class="tc n-g"><?= $rr3['hadir']+$rr3['terlambat'] ?></td>
    <td class="tc n-y"><?= $rr3['terlambat']?:'—' ?></td>
    <td class="tc n-r"><?= $rr3['alpha']>0?'<strong>'.$rr3['alpha'].'</strong>':'—' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>

<!-- ══════ VI. DETAIL JAM ABSEN ══════ -->
<?php if ($mode==='mingguan' || $jml_karyawan<=15): ?>
<div class="sec">
  <div class="sec-num">Bagian VI</div>
  <div class="sec-title">
    <span class="sec-title-text">Detail Jam Masuk &amp; Keluar per Karyawan</span>
    <span class="sec-title-rule">Individual Attendance Detail</span>
  </div>
</div>
<?php foreach ($karyawan as $k):
  $rr4=$rekap[$k['id']];
  $nm4=($k['gelar_depan']?htmlspecialchars($k['gelar_depan']).' ':'').htmlspecialchars($k['nama']);
?>
<div class="jam-head-blk">
  <div class="jam-head-l">
    <div class="jam-nama"><?= $nm4 ?></div>
    <div class="jam-sub"><?= $k['nik_rs']?htmlspecialchars($k['nik_rs']).' &middot; ':'' ?><?= htmlspecialchars($k['divisi']?:'—') ?><?= $k['jenis_karyawan']?' &middot; '.htmlspecialchars($k['jenis_karyawan']):'' ?></div>
  </div>
  <div class="jam-head-r">
    <?php foreach ([
        [$rr4['hadir']+$rr4['terlambat'],'#00e5b0','Hadir'],
        [$rr4['terlambat'],'#f59e0b','Terlambat'],
        [$rr4['alpha'],'#ef4444','Alpha'],
        [$rr4['total_terlambat'].'m','#fbbf24','Total Tlbt'],
        [$rr4['pct_hadir'].'%',$rr4['pct_hadir']>=90?'#00e5b0':($rr4['pct_hadir']>=75?'#fbbf24':'#ef4444'),'Kehadiran'],
    ] as [$v,$c,$l]): ?>
    <div class="jam-stat">
      <div class="jam-stat-num" style="color:<?= $c ?>;"><?= $v ?></div>
      <div class="jam-stat-lbl"><?= $l ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<table class="data-tbl" style="margin-bottom:0;">
  <thead>
    <tr>
      <th style="width:24px;">#</th>
      <th style="min-width:95px;">Tanggal</th>
      <th style="width:40px;">Hari</th>
      <th class="tc" style="width:65px;">Jam Masuk</th>
      <th class="tc" style="width:65px;">Jam Keluar</th>
      <th class="tc" style="width:65px;">Durasi</th>
      <th class="tc" style="width:80px;">Terlambat</th>
      <th class="tc" style="width:80px;">Status</th>
    </tr>
  </thead>
  <tbody>
  <?php $no_j4=0; foreach ($tgl_list as $td4):
    $jdw4=$jadwal_map[$k['id']][$td4['tgl']]??null;
    $ab4 =$absen_map[$k['id']][$td4['tgl']] ??null;
    if ($jdw4==='libur'&&!$ab4) continue;
    $rs4='kosong';
    if ($ab4) $rs4=$ab4['status']??'hadir';
    elseif (in_array($jdw4,['cuti','izin','dinas'])) $rs4=$jdw4;
    $sb4=statusBadge($rs4);
    if ($rs4==='kosong') $sb4=['bg'=>'#f8fafc','fg'=>'#94a3b8','lbl'=>'—'];
    $no_j4++;
    $tgl_fmt4=(int)date('d',strtotime($td4['tgl'])).' '.($NAMA_BULAN[(int)date('n',strtotime($td4['tgl']))]??'');
  ?>
  <tr>
    <td class="tc" style="color:#94a3b8;"><?= $no_j4 ?></td>
    <td style="font-family:'Courier New',monospace;font-size:8pt;font-weight:bold;"><?= $tgl_fmt4 ?></td>
    <td style="color:#64748b;font-size:8pt;font-weight:600;"><?= $NAMA_HARI_P[$td4['dow']-1] ?></td>
    <td class="tc" style="font-family:'Courier New',monospace;font-weight:bold;font-size:8pt;color:#10b981;">
      <?= ($ab4&&$ab4['jam_masuk'])?substr($ab4['jam_masuk'],0,5):'<span class="n-0">—</span>' ?>
    </td>
    <td class="tc" style="font-family:'Courier New',monospace;font-weight:bold;font-size:8pt;color:#f59e0b;">
      <?= ($ab4&&$ab4['jam_keluar'])?substr($ab4['jam_keluar'],0,5):'<span class="n-0">—</span>' ?>
    </td>
    <td class="tc" style="font-size:8pt;color:#0369a1;font-weight:600;">
      <?= ($ab4&&$ab4['durasi_kerja']>0)?floor($ab4['durasi_kerja']/60).'j '.($ab4['durasi_kerja']%60).'m':'<span class="n-0">—</span>' ?>
    </td>
    <td class="tc" style="font-size:8pt;">
      <?php if ($ab4&&$ab4['terlambat_menit']>0): ?>
        <span style="color:#a16207;font-weight:bold;background:#fef3c7;padding:2px 5px;">+<?= $ab4['terlambat_menit'] ?>m</span>
      <?php elseif ($rs4==='hadir'): ?>
        <span style="color:#15803d;font-size:7.5pt;">Tepat waktu</span>
      <?php else: ?><span class="n-0">—</span><?php endif; ?>
    </td>
    <td class="tc">
      <span class="jam-badge" style="background:<?= $sb4['bg'] ?>;color:<?= $sb4['fg'] ?>;"><?= $sb4['lbl'] ?></span>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>
<?php else: ?>
<div style="margin-top:8px;padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-left:4px solid #f59e0b;font-size:8.5pt;color:#713f12;">
  <strong>Catatan:</strong> Detail jam absen tidak ditampilkan (jumlah karyawan &gt;15 pada mode bulanan).
  Gunakan mode <strong>Mingguan</strong> atau filter per unit untuk melihat detail.
</div>
<?php endif; ?>

<!-- ══════ KETERANGAN WARNA ══════ -->
<table style="width:100%;border-collapse:collapse;margin-top:14px;border:1px solid #e2e8f0;font-size:8pt;">
  <tr>
    <td style="padding:5px 9px;background:#f8fafc;font-weight:bold;color:#475569;border-right:1px solid #e2e8f0;width:12%;">Keterangan</td>
    <?php foreach ([
        ['H','#dcfce7','#15803d','Hadir'],
        ['T','#fef3c7','#a16207','Terlambat'],
        ['A','#fee2e2','#b91c1c','Alpha'],
        ['I','#fff7ed','#c2410c','Izin'],
        ['C','#faf5ff','#6d28d9','Cuti'],
        ['D','#e0f2fe','#0369a1','Dinas'],
        ['L','#f1f5f9','#64748b','Libur'],
        ['S','#fef9c3','#a16207','1/2 Hari'],
    ] as [$kd,$bg,$fg,$lbl]): ?>
    <td style="padding:5px 8px;border-right:1px solid #e2e8f0;">
      <span class="ket-badge" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= $kd ?> = <?= $lbl ?></span>
    </td>
    <?php endforeach; ?>
  </tr>
</table>

<!-- ══════ TANDA TANGAN ══════ -->
<div style="margin-top:22px;font-size:8.5pt;color:#475569;margin-bottom:4px;">Laporan ini telah diperiksa dan disetujui oleh:</div>
<div class="ttd-section">
  <div class="ttd-box">
    <div class="ttd-title">Dibuat Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name"><?= htmlspecialchars($pencetak) ?></div>
    <div class="ttd-role">Staff HRD / Admin</div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Diperiksa Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Kepala / Supervisor HRD</div>
  </div>
  <div class="ttd-box">
    <div class="ttd-title">Disetujui Oleh,</div>
    <div class="ttd-line"></div>
    <div class="ttd-name">___________________</div>
    <div class="ttd-role">Manajer / Pimpinan</div>
  </div>
</div>

<!-- ══════ FOOTER ══════ -->
<div class="page-footer">
  <div class="pf-left">
    <?= defined('APP_NAME')?htmlspecialchars(APP_NAME):'Sistem Informasi RS' ?> &mdash; Laporan Absensi &mdash; <?= htmlspecialchars($judul_periode) ?><br>
    Dokumen ini bersifat rahasia dan hanya untuk keperluan internal HRD.
  </div>
  <div class="pf-right">
    No. Dok: <?= $no_dok ?> &nbsp;|&nbsp; Total: <?= $jml_karyawan ?> karyawan<br>
    Dicetak oleh: <?= htmlspecialchars($pencetak) ?>
  </div>
</div>

</body>
</html>
<?php
$html_doc = ob_get_clean();

// ── Generate PDF via dompdf ────────────────────────────────────────────────────
if ($dompdf_available) {
    require_once $dp_autoload;
    $dC = class_exists('\Dompdf\Dompdf') ? '\Dompdf\Dompdf' : 'Dompdf\Dompdf';
    $oC = class_exists('\Dompdf\Options') ? '\Dompdf\Options' : 'Dompdf\Options';
    if (class_exists($dC)) {
        $opt = new $oC();
        $opt->set('isHtml5ParserEnabled', true);
        $opt->set('isRemoteEnabled', false);
        $opt->set('defaultFont', 'helvetica');
        $opt->set('dpi', 150);
        $dp = new $dC($opt);
        $dp->loadHtml($html_doc, 'UTF-8');
        $dp->setPaper('A4', 'landscape');
        $dp->render();
        $slug  = ($mode==='bulanan'?'Bulanan':'Mingguan-'.$minggu_ke).'_'.sprintf('%02d',$bulan).$tahun;
        $fname = 'Laporan_Absensi_'.$slug.'_'.date('Ymd').'.pdf';
        $dp->stream($fname, ['Attachment' => false]);
        exit;
    }
}

// ── Fallback: tampil HTML di browser ──────────────────────────────────────────
// Tambahkan toolbar screen-only
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Absensi &mdash; <?= htmlspecialchars($judul_periode) ?></title>
<style>
.toolbar { background:#0f172a; padding:11px 22px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; position:sticky; top:0; z-index:99; font-family:Arial,Helvetica,sans-serif; }
.tb-brand .t1 { color:#00e5b0; font-size:13px; font-weight:bold; }
.tb-brand .t2 { color:rgba(255,255,255,.4); font-size:10px; margin-top:1px; }
.tb-controls { margin-left:auto; display:flex; gap:7px; align-items:center; flex-wrap:wrap; }
.tb-select { height:30px; padding:0 9px; border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.08); color:#fff; border-radius:5px; font-size:11px; font-family:inherit; outline:none; cursor:pointer; }
.tb-select option { background:#1e293b; }
.tb-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; border-radius:5px; font-size:11px; font-weight:700; cursor:pointer; font-family:inherit; border:none; text-decoration:none; }
.tb-btn-sec { background:rgba(255,255,255,.09); color:rgba(255,255,255,.8); border:1px solid rgba(255,255,255,.15); }
.tb-btn-sec:hover { background:rgba(255,255,255,.18); color:#fff; }
.tb-btn-pdf { background:#00e5b0; color:#0a0f14; }
.tb-btn-pdf:hover { background:#00c896; }
body { background:#e5e7eb; margin:0; }
.doc-outer { max-width:1050px; margin:20px auto; background:#fff; box-shadow:0 2px 16px rgba(0,0,0,.1); border-radius:6px; overflow:hidden; }
@media print { .toolbar{display:none!important;} body{background:#fff;} .doc-outer{max-width:none;box-shadow:none;border-radius:0;margin:0;} }
</style>
</head>
<body>
<div class="toolbar">
  <div class="tb-brand">
    <div class="t1">Laporan Absensi</div>
    <div class="t2"><?= htmlspecialchars($judul_periode) ?></div>
  </div>
  <div class="tb-controls">
    <form method="GET" style="display:contents;" id="tf">
      <input type="hidden" name="bagian" value="<?= $bagian_id ?>">
      <input type="hidden" name="nama"   value="<?= htmlspecialchars($f_nama) ?>">
      <input type="hidden" name="bulan"  value="<?= $bulan ?>">
      <input type="hidden" name="tahun"  value="<?= $tahun ?>">
      <input type="hidden" name="minggu" value="<?= $minggu_ke ?>">
      <select name="mode" class="tb-select" onchange="document.getElementById('tf').submit()">
        <option value="bulanan"  <?= $mode==='bulanan' ?'selected':'' ?>>Bulanan</option>
        <option value="mingguan" <?= $mode==='mingguan'?'selected':'' ?>>Mingguan</option>
      </select>
      <input type="month" value="<?= sprintf('%04d-%02d',$tahun,$bulan) ?>"
             onchange="var p=this.value.split('-');document.querySelector('[name=bulan]').value=parseInt(p[1]);document.querySelector('[name=tahun]').value=p[0];document.getElementById('tf').submit();"
             style="height:30px;padding:0 8px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.08);color:#fff;border-radius:5px;font-size:11px;font-family:inherit;outline:none;cursor:pointer;">
      <?php if ($mode==='mingguan'): ?>
      <select name="minggu" class="tb-select" onchange="document.getElementById('tf').submit()">
        <?php foreach ($minggu_options as $mk=>$ml): ?>
        <option value="<?= $mk ?>" <?= $mk==$minggu_ke?'selected':'' ?>><?= htmlspecialchars($ml) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
    </form>
    <a href="<?= APP_URL ?>/pages/laporan_absensi.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&bagian=<?= $bagian_id ?>" class="tb-btn tb-btn-sec">&#8592; Kembali</a>
    <button onclick="window.print()" class="tb-btn tb-btn-pdf">&#128438; Cetak / PDF</button>
  </div>
</div>
<div class="doc-outer">
<?= $html_doc ?>
</div>
</body>
</html>
<?php