<?php
/**
 * cetak_label_aset.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Dual mode:
 *   • Jika dompdf tersedia  → render PDF langsung di browser
 *   • Jika dompdf TIDAK ada → tampil halaman HTML siap cetak (Ctrl+P / Print)
 *
 * Penggunaan:
 *   cetak_label_aset.php?id=6        → 1 label
 *   cetak_label_aset.php?id=1,2,3    → bulk (2 kolom per baris A4)
 * ─────────────────────────────────────────────────────────────────────────────
 */

session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { http_response_code(403); exit('Akses ditolak.'); }

// ── 1. Cek apakah dompdf tersedia ────────────────────────────────────────────
$dompdf_available = false;
$dp_autoload      = '';

// Cari autoload.inc.php di beberapa kemungkinan lokasi
foreach ([
    dirname(__DIR__) . '../dompdf/autoload.inc.php',           // fixsmart/dompdf/
    dirname(__DIR__) . '/vendor/dompdf/dompdf/autoload.inc.php', // composer
    __DIR__ . '/../dompdf/autoload.inc.php',
] as $p) {
    if (file_exists($p)) {
        $dp_autoload      = $p;
        $dompdf_available = true;
        break;
    }
}

// ── 2. Ambil ID aset ─────────────────────────────────────────────────────────
$raw = trim($_GET['id'] ?? '');
$ids = array_values(array_filter(array_map('intval', explode(',', $raw))));

if (empty($ids)) {
    die('<p style="font-family:sans-serif;color:#ef4444;padding:20px;">
         Parameter <code>id</code> tidak valid atau kosong.</p>');
}

// ── 3. Fetch data dari database ───────────────────────────────────────────────
$ph  = implode(',', array_fill(0, count($ids), '?'));
$stm = $pdo->prepare("
    SELECT  a.*,
            b.nama   AS bagian_nama,
            b.kode   AS bagian_kode,
            u.nama   AS pj_nama_db
    FROM    aset_it a
    LEFT JOIN bagian b ON b.id = a.bagian_id
    LEFT JOIN users  u ON u.id = a.pj_user_id
    WHERE   a.id IN ($ph)
    ORDER BY a.id ASC
");
$stm->execute($ids);
$asets = $stm->fetchAll(PDO::FETCH_ASSOC);

if (empty($asets)) {
    die('<p style="font-family:sans-serif;color:#ef4444;padding:20px;">
         Data aset tidak ditemukan.</p>');
}

// ── 4. Helper functions ───────────────────────────────────────────────────────
function qrUrl(string $text, int $px = 130): string {
    return 'https://chart.googleapis.com/chart'
         . '?chs=' . $px . 'x' . $px
         . '&cht=qr&choe=UTF-8&chld=M|2'
         . '&chl=' . rawurlencode($text);
}

function detailUrl(int $id): string {
    return APP_URL . '/pages/aset_it.php?detail=' . $id;
}

function kondisiColor(string $k): array {
    return match ($k) {
        'Baik'            => ['#d1fae5', '#065f46'],
        'Rusak'           => ['#fee2e2', '#991b1b'],
        'Dalam Perbaikan' => ['#fef9c3', '#854d0e'],
        default           => ['#f1f5f9', '#475569'],
    };
}

// ── 5. Build HTML label (dipakai untuk kedua mode) ────────────────────────────
function buildLabelHTML(array $a, bool $forPdf = false): string
{
    $id      = (int)$a['id'];
    $no_inv  = htmlspecialchars($a['no_inventaris']   ?? '');
    $nama    = htmlspecialchars(mb_strimwidth($a['nama_aset'] ?? '', 0, 44, '…'));
    $kat     = htmlspecialchars($a['kategori']         ?? '');
    $merek   = htmlspecialchars(trim(($a['merek'] ?? '') . ' ' . ($a['model_aset'] ?? '')));
    $sn      = htmlspecialchars($a['serial_number']    ?? '');
    $kondisi = htmlspecialchars($a['kondisi']          ?? 'Baik');
    $lokasi  = htmlspecialchars(
                    $a['bagian_nama']
                    ? (($a['bagian_kode'] ? '[' . $a['bagian_kode'] . '] ' : '') . $a['bagian_nama'])
                    : ($a['lokasi'] ?? '—')
               );
    $pj = htmlspecialchars($a['pj_nama_db'] ?? $a['penanggung_jawab'] ?? '—');

    [$kb_bg, $kb_col] = kondisiColor($a['kondisi'] ?? '');

    $qr         = qrUrl(detailUrl($id), $forPdf ? 120 : 140);
    $qr_size    = $forPdf ? '90' : '110';

    // Barlines dekoratif
    $bars = '';
    $heights = [9,5,13,7,10,5,12,6,9,14,5,10,7,13,5,9,11,6,14,5,10,8,13,6,9,5,12,7];
    foreach ($heights as $i => $h) {
        $col   = ($i % 3 === 0) ? '#00e5b0' : '#1e2f42';
        $bars .= "<span style=\"display:inline-block;width:2px;height:{$h}px;"
               . "background:{$col};border-radius:1px;margin-right:1.5px;\"></span>";
    }

    // Merek line
    $merek_html = $merek
        ? "<div style=\"font-size:7pt;color:#64748b;margin-bottom:3px;\">{$merek}</div>"
        : '';

    // SN line
    $sn_html = $sn
        ? "<div style=\"font-size:6pt;color:#94a3b8;margin-top:3px;font-family:'Courier New',monospace;\">S/N: {$sn}</div>"
        : '';

    return <<<HTML
<div class="label-card">

  <!-- Header strip -->
  <div style="background:linear-gradient(135deg,#0a0f14,#0d1a2a);padding:5px 9px;
              display:flex;justify-content:space-between;align-items:center;">
    <span style="color:#00e5b0;font-size:7.5pt;font-weight:bold;letter-spacing:.3px;">FixSmart Helpdesk</span>
    <span style="color:rgba(255,255,255,.45);font-size:6.5pt;background:rgba(0,229,176,.12);
          border:1px solid rgba(0,229,176,.22);padding:1px 6px;border-radius:8px;">{$kat}</span>
  </div>

  <!-- Body: QR kiri + Info kanan -->
  <div style="display:flex;padding:7px;">
    <!-- QR -->
    <div style="width:32mm;flex-shrink:0;text-align:center;padding-right:7px;
                border-right:1px dashed #e2e8f0;display:flex;flex-direction:column;
                align-items:center;justify-content:center;">
      <img src="{$qr}" width="{$qr_size}" height="{$qr_size}"
           style="border:1px solid #e2e8f0;border-radius:4px;padding:2px;background:#fff;"
           alt="QR">
      <div style="font-size:5.5pt;color:#94a3b8;margin-top:3px;line-height:1.3;text-align:center;">
        Scan untuk<br>info lengkap
      </div>
    </div>

    <!-- Info -->
    <div style="padding-left:8px;flex:1;">
      <div style="font-family:'Courier New',Courier,monospace;font-size:9.5pt;font-weight:bold;
                  color:#0a0f14;background:linear-gradient(135deg,#eff6ff,#dbeafe);
                  border:1px solid #bfdbfe;padding:2px 8px;border-radius:4px;
                  display:inline-block;margin-bottom:4px;letter-spacing:.5px;">
        {$no_inv}
      </div>
      <div style="font-size:8.5pt;font-weight:bold;color:#1e293b;line-height:1.25;margin-bottom:3px;">
        {$nama}
      </div>
      {$merek_html}
      <div style="font-size:7pt;color:#475569;margin-bottom:2px;">
        <span style="color:#00e5b0;font-weight:bold;">&#9679;</span> {$lokasi}
      </div>
      <div style="font-size:7pt;color:#475569;margin-bottom:3px;">
        <span style="color:#00e5b0;font-weight:bold;">&#9679;</span> {$pj}
      </div>
      <span style="display:inline-block;padding:1px 8px;border-radius:9px;
                   font-size:6.5pt;font-weight:bold;
                   background:{$kb_bg};color:{$kb_col};">{$kondisi}</span>
      {$sn_html}
    </div>
  </div>

  <!-- Footer: barlines + ID -->
  <div style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:4px 9px;
              display:flex;align-items:flex-end;justify-content:space-between;">
    <div style="line-height:0;">{$bars}</div>
    <span style="font-size:6pt;color:#94a3b8;font-family:'Courier New',monospace;white-space:nowrap;">
      ID #{$id}
    </span>
  </div>

</div>
HTML;
}

// ── 6. Susun grid HTML (2 kolom) ──────────────────────────────────────────────
function buildGrid(array $asets, bool $forPdf = false): string {
    $n    = count($asets);
    $html = '';
    for ($i = 0; $i < $n; $i += 2) {
        $c1 = buildLabelHTML($asets[$i],   $forPdf);
        $c2 = isset($asets[$i + 1]) ? buildLabelHTML($asets[$i + 1], $forPdf) : '';
        $html .= <<<ROW
<tr>
  <td class="col">{$c1}</td>
  <td class="col">{$c2}</td>
</tr>
ROW;
    }
    return $html;
}

// ── 7a. MODE PDF: gunakan dompdf ──────────────────────────────────────────────
if ($dompdf_available) {
    require_once $dp_autoload;
    use Dompdf\Dompdf;
    use Dompdf\Options;

    $n     = count($asets);
    $title = $n === 1
           ? 'Label — ' . ($asets[0]['no_inventaris'] ?? 'Aset')
           : "Label Aset IT ({$n} item) — " . date('d/m/Y');

    $grid  = buildGrid($asets, true);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>{$title}</title>
<style>
@page { size:A4 portrait; margin:10mm; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,Helvetica,sans-serif; font-size:8pt; background:#fff; }
.grid { width:100%; border-collapse:collapse; }
.col  { width:50%; padding:3mm; vertical-align:top; }
.label-card {
    border:1.5px solid #1e2f42;
    border-radius:6px;
    overflow:hidden;
    background:#fff;
    page-break-inside:avoid;
}
</style>
</head>
<body>
<table class="grid">{$grid}</table>
</body>
</html>
HTML;

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $slug = $n === 1
          ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $asets[0]['no_inventaris'] ?? 'aset')
          : 'label-aset-it-' . $n . 'item';

    $dompdf->stream($slug . '-' . date('Ymd') . '.pdf', ['Attachment' => false]);
    exit;
}

// ── 7b. MODE HTML PRINT: fallback jika dompdf belum ada ──────────────────────
// Tampil halaman HTML indah + tombol Print. Tidak butuh library apapun.
$n     = count($asets);
$title = $n === 1
       ? 'Label — ' . ($asets[0]['no_inventaris'] ?? 'Aset')
       : "Label Aset IT ({$n} item)";

$grid = buildGrid($asets, false);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<style>
/* ── Reset & Base ──────────────────────────────── */
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8pt;
    background: #f0f4f8;
    color: #1e293b;
}

/* ── Toolbar (hanya tampil di layar, tidak tercetak) ── */
.toolbar {
    background: linear-gradient(135deg, #0a0f14, #0d1a2a);
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    gap: 12px;
}
.toolbar-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.toolbar-logo {
    color: #00e5b0;
    font-size: 14px;
    font-weight: bold;
    letter-spacing: .3px;
}
.toolbar-sub {
    color: rgba(255,255,255,.4);
    font-size: 11px;
}
.btn-print {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 22px;
    background: linear-gradient(135deg, #00e5b0, #00c896);
    color: #0a0f14;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    box-shadow: 0 3px 12px rgba(0,229,176,.3);
    transition: opacity .18s;
    text-decoration: none;
}
.btn-print:hover { opacity: .85; }
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: rgba(255,255,255,.08);
    color: rgba(255,255,255,.7);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 7px;
    font-size: 12px;
    cursor: pointer;
    font-family: inherit;
    text-decoration: none;
    transition: all .18s;
}
.btn-back:hover { background: rgba(255,255,255,.14); color: #fff; }

/* ── Info banner ── */
.info-bar {
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 8px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11.5px;
    color: #475569;
}
.info-bar strong { color: #0f766e; }

/* ── Paper area ── */
.paper-wrap {
    max-width: 210mm;
    margin: 20px auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 24px rgba(0,0,0,.1);
    overflow: hidden;
    padding: 10mm;
}

/* ── Label grid ── */
.grid { width: 100%; border-collapse: collapse; }
.col  { width: 50%; padding: 3mm; vertical-align: top; }
.label-card {
    border: 1.5px solid #1e2f42;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
    break-inside: avoid;
    page-break-inside: avoid;
}

/* ── No-dompdf notice ── */
.nodompdf-notice {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 12px 16px;
    margin: 16px 20px 0;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 12px;
    color: #92400e;
}
.nodompdf-notice a { color: #0284c7; }

/* ── PRINT media ─────────────────────────────── */
@media print {
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    @page { size: A4 portrait; margin: 10mm; }
    body { background: #fff; }
    .toolbar, .info-bar, .nodompdf-notice { display: none !important; }
    .paper-wrap {
        max-width: 100%;
        margin: 0;
        padding: 0;
        box-shadow: none;
        border-radius: 0;
    }
    .label-card { break-inside: avoid; page-break-inside: avoid; }
}
</style>
</head>
<body>

<!-- ── Toolbar ──────────────────────────────────────────────────── -->
<div class="toolbar">
  <div class="toolbar-left">
    <div>
      <div class="toolbar-logo">&#9632; FixSmart Helpdesk</div>
      <div class="toolbar-sub">
        <?= htmlspecialchars($title) ?>
        &nbsp;·&nbsp; <?= $n ?> label
      </div>
    </div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <a class="btn-back" href="javascript:history.back()">
      &#8592; Kembali
    </a>
    <button class="btn-print" onclick="window.print()">
      &#128438; Cetak / Simpan PDF
    </button>
  </div>
</div>

<!-- ── Notice: dompdf tidak tersedia ──────────────────────────── -->
<div class="nodompdf-notice">
  <span style="font-size:16px;flex-shrink:0;">&#9888;</span>
  <div>
    <strong>Mode cetak browser</strong> — DomPDF belum terinstall, tampilan cetak menggunakan browser print.
    Klik <strong>"Cetak / Simpan PDF"</strong> di atas, lalu pilih <em>"Save as PDF"</em> di dialog print.
    <br>Untuk install DomPDF:
    <a href="https://github.com/dompdf/dompdf/releases" target="_blank">download di sini</a>
    lalu ekstrak ke <code>fixsmart/dompdf/</code>
  </div>
</div>

<!-- ── Info bar ─────────────────────────────────────────────────── -->
<div class="info-bar">
  <span>&#128203;</span>
  Pratinjau label aset —
  <strong><?= $n ?> label</strong> siap cetak.
  Ukuran kertas <strong>A4</strong>, 2 kolom per baris.
  Setelah cetak, gunting dan tempel di fisik aset.
</div>

<!-- ── Paper / Label area ───────────────────────────────────────── -->
<div class="paper-wrap">
  <table class="grid">
    <?= $grid ?>
  </table>
</div>

<script>
// Auto-trigger print dialog jika ada query ?autoprint=1
if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 600));
}
</script>
</body>
</html>