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

// ── Parameter ─────────────────────────────────────────────────────────────────
// ids = single id atau comma-separated untuk cetak banyak label sekaligus
$ids_raw = $_GET['ids'] ?? ($_GET['id'] ?? '');
$ids     = array_filter(array_map('intval', explode(',', $ids_raw)));

if (empty($ids)) { die('Parameter id tidak valid.'); }

// ── Ambil data aset ───────────────────────────────────────────────────────────
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$st = $pdo->prepare("
    SELECT a.*,
           b.nama  AS bagian_nama,
           b.kode  AS bagian_kode,
           b.lokasi AS bagian_lokasi,
           u.nama  AS pj_nama_db
    FROM aset_ipsrs a
    LEFT JOIN bagian b ON b.id = a.bagian_id
    LEFT JOIN users  u ON u.id = a.pj_user_id
    WHERE a.id IN ($placeholders)
    ORDER BY a.nama_aset ASC
");
$st->execute($ids);
$asets = $st->fetchAll();

if (empty($asets)) { die('Data aset tidak ditemukan.'); }

// ── Helper ────────────────────────────────────────────────────────────────────
function kondisiColor(string $k): array {
    return match($k) {
        'Baik'            => ['#dcfce7', '#15803d'],
        'Rusak'           => ['#fee2e2', '#b91c1c'],
        'Dalam Perbaikan' => ['#fef9c3', '#a16207'],
        'Tidak Aktif'     => ['#f1f5f9', '#475569'],
        default           => ['#f1f5f9', '#64748b'],
    };
}

function qrCodeSvg(string $text, int $size = 60): string {
    // Simple visual placeholder QR — pakai text box styled agar mudah dibaca
    // Pada implementasi nyata, bisa pakai library phpqrcode
    $escaped = htmlspecialchars($text);
    return '
    <div style="width:'.$size.'px;height:'.$size.'px;border:2px solid #1e293b;border-radius:4px;
         display:flex;flex-direction:column;align-items:center;justify-content:center;
         background:#fff;flex-shrink:0;">
        <div style="font-size:7px;font-weight:900;color:#1e293b;letter-spacing:0;text-align:center;line-height:1.2;
             font-family:\'Courier New\',monospace;word-break:break-all;padding:3px;">
            &#9632;&#9633;&#9632;<br>&#9633;&#9632;&#9633;<br>&#9632;&#9633;&#9632;
        </div>
        <div style="font-size:5.5px;color:#64748b;text-align:center;padding:0 2px;line-height:1.3;
             font-family:\'Courier New\',monospace;word-break:break-all;max-width:'.$size.'px;overflow:hidden;">
            '.mb_strimwidth($escaped, 0, 18, '').'
        </div>
    </div>';
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
    background: #f5f5f5;
    font-size: 9pt;
    padding: 8mm;
}

/* Label wrapper — 90mm x 55mm (ukuran kartu nama) */
.labels-grid {
    display: table;
    width: 100%;
    border-collapse: separate;
    border-spacing: 4mm;
}
.labels-row   { display: table-row; }
.labels-cell  { display: table-cell; vertical-align: top; width: 50%; }

.label-card {
    width: 90mm;
    min-height: 50mm;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    page-break-inside: avoid;
    margin-bottom: 4mm;
    display: table;
    width: 100%;
}

/* ── Header strip ── */
.lbl-header {
    display: table;
    width: 100%;
    padding: 4px 7px;
    background: linear-gradient(135deg, #7c2d12, #ea580c);
}
.lbl-header-left  { display: table-cell; vertical-align: middle; }
.lbl-header-right { display: table-cell; vertical-align: middle; text-align: right; }
.lbl-org   { font-size: 7.5pt; font-weight: 800; color: #fff; letter-spacing: 0.3px; line-height: 1.2; }
.lbl-sub   { font-size: 6pt;   color: rgba(255,255,255,.65); margin-top: 1px; }
.lbl-rs-badge {
    display: inline-block;
    background: rgba(255,255,255,.2);
    border: 1px solid rgba(255,255,255,.35);
    color: #fff;
    font-size: 5.5pt;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    white-space: nowrap;
}

/* ── Body ── */
.lbl-body {
    padding: 5px 7px;
    display: table;
    width: 100%;
}
.lbl-body-left  { display: table-cell; vertical-align: top; padding-right: 5px; }
.lbl-body-right { display: table-cell; vertical-align: top; width: 62px; text-align: center; }

/* Nama aset */
.lbl-nama {
    font-size: 10pt;
    font-weight: 800;
    color: #1e293b;
    line-height: 1.25;
    margin-bottom: 3px;
    word-break: break-word;
}

/* Info rows */
.lbl-row {
    display: table;
    width: 100%;
    margin-bottom: 2px;
}
.lbl-row-icon  { display: table-cell; width: 13px; vertical-align: top; color: #ea580c; font-size: 7pt; }
.lbl-row-label { display: table-cell; width: 54px; vertical-align: top; font-size: 6.5pt; color: #64748b; font-weight: 700; }
.lbl-row-val   { display: table-cell; vertical-align: top; font-size: 7pt; color: #1e293b; font-weight: 600; word-break: break-word; }

/* No inventaris — prominent */
.lbl-inv-box {
    background: linear-gradient(135deg, #fff7ed, #ffedd5);
    border: 1px solid #fed7aa;
    border-radius: 4px;
    padding: 3px 6px;
    margin-bottom: 5px;
    display: table;
    width: 100%;
}
.lbl-inv-label { display: table-cell; font-size: 5.5pt; color: #92400e; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; width: 60px; vertical-align: middle; }
.lbl-inv-val   { display: table-cell; font-size: 8.5pt; font-weight: 900; color: #c2410c; font-family: 'Courier New', monospace; vertical-align: middle; text-align: right; }

/* Jenis badge */
.lbl-jenis-medis {
    display: inline-block;
    background: #fce7f3; color: #9d174d;
    font-size: 6pt; font-weight: 800;
    padding: 1px 5px; border-radius: 3px;
    margin-bottom: 4px;
}
.lbl-jenis-non {
    display: inline-block;
    background: #eff6ff; color: #1e40af;
    font-size: 6pt; font-weight: 800;
    padding: 1px 5px; border-radius: 3px;
    margin-bottom: 4px;
}

/* Kondisi badge */
.lbl-kondisi {
    display: inline-block;
    font-size: 6pt; font-weight: 800;
    padding: 1px 6px; border-radius: 10px;
    white-space: nowrap;
}

/* QR placeholder */
.lbl-qr-wrap {
    width: 58px; height: 58px;
    border: 1.5px solid #e2e8f0;
    border-radius: 4px;
    background: #fff;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column;
    overflow: hidden;
}
.qr-pattern {
    display: table; border-collapse: collapse;
    margin-bottom: 2px;
}
.qr-cell { display: table-cell; width: 5px; height: 5px; }
.qr-dark  { background: #1e293b; }
.qr-light { background: #fff; }
.qr-label {
    font-size: 5pt; color: #94a3b8; text-align: center;
    font-family: 'Courier New', monospace;
    padding: 0 2px; line-height: 1.3;
    word-break: break-all; max-width: 56px;
}

/* ── Footer strip ── */
.lbl-footer {
    display: table;
    width: 100%;
    padding: 3px 7px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}
.lbl-footer-left  { display: table-cell; vertical-align: middle; font-size: 5.5pt; color: #94a3b8; }
.lbl-footer-right { display: table-cell; vertical-align: middle; text-align: right; font-size: 5.5pt; color: #94a3b8; }

/* ── Print styles ── */
@media print {
    body { padding: 4mm; background: #fff; }
    .label-card { box-shadow: none; }
}
@page {
    size: A4 portrait;
    margin: 8mm;
}
</style>
</head>
<body>

<?php
// Bagi aset ke baris per 2 kolom
$chunks = array_chunk($asets, 2);
foreach ($chunks as $row):
?>
<div class="labels-grid">
  <div class="labels-row">
    <?php foreach ($row as $a):
      $kondisi     = $a['kondisi'] ?? 'Baik';
      [$kBg,$kFg]  = kondisiColor($kondisi);
      $noInv       = $a['no_inventaris'] ?? '—';
      $namaAset    = $a['nama_aset']     ?? '—';
      $jenisAset   = $a['jenis_aset']    ?? 'Non-Medis';
      $kategori    = $a['kategori']      ?? '';
      $merek       = trim(($a['merek'] ?? '') . ($a['model_aset'] ? ' / '.$a['model_aset'] : ''));
      $serial      = $a['serial_number'] ?? '';
      $noAsetRS    = $a['no_aset_rs']    ?? '';
      $lokasiDisp  = $a['bagian_nama']
        ? (($a['bagian_kode'] ? '['.$a['bagian_kode'].'] ' : '') . $a['bagian_nama'])
        : ($a['lokasi'] ?? '');
      $pjDisp      = $a['pj_nama_db'] ?: ($a['penanggung_jawab'] ?? '');
      $tglBeli     = $a['tanggal_beli'] ? date('Y', strtotime($a['tanggal_beli'])) : '';
      $tglKalBerik = $a['tgl_kalibrasi_berikutnya'] ?? '';
      $tglSvcBerik = $a['tgl_service_berikutnya']   ?? '';
      $garansi     = $a['garansi_sampai'] ? date('d/m/Y', strtotime($a['garansi_sampai'])) : '';

      // QR pattern visual — 8x8 pseudo-random berdasarkan no_inventaris
      $seed = crc32($noInv);
      $qrRows = [];
      for ($r = 0; $r < 8; $r++) {
          $qrCols = [];
          for ($c = 0; $c < 8; $c++) {
              // sudut kiri atas & kanan atas & kiri bawah selalu solid (finder pattern)
              $isFinder = ($r < 3 && $c < 3) || ($r < 3 && $c > 4) || ($r > 4 && $c < 3);
              $bit = $isFinder ? true : (bool)(($seed >> (($r * 8 + $c) % 32)) & 1);
              $qrCols[] = $bit ? '<td class="qr-cell qr-dark"></td>' : '<td class="qr-cell qr-light"></td>';
          }
          $qrRows[] = '<tr>' . implode('', $qrCols) . '</tr>';
      }
      $qrHtml = '<table class="qr-pattern">' . implode('', $qrRows) . '</table>';
    ?>
    <div class="labels-cell">
      <div class="label-card">

        <!-- ── HEADER ── -->
        <div class="lbl-header">
          <div class="lbl-header-left">
            <div class="lbl-org">FixSmart &mdash; IPSRS</div>
            <div class="lbl-sub">Instalasi Pemeliharaan Sarana Rumah Sakit</div>
          </div>
          <div class="lbl-header-right">
            <?php if ($noAsetRS): ?>
            <div style="font-size:5.5pt;color:rgba(255,255,255,.55);margin-bottom:2px;">No. Aset RS</div>
            <span class="lbl-rs-badge"><?= htmlspecialchars($noAsetRS) ?></span>
            <?php else: ?>
            <span style="font-size:6pt;color:rgba(255,255,255,.4);font-style:italic;">—</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── BODY ── -->
        <div class="lbl-body">
          <div class="lbl-body-left">

            <!-- Jenis badge -->
            <?php if ($jenisAset === 'Medis'): ?>
              <span class="lbl-jenis-medis">&#9829; MEDIS</span>
            <?php else: ?>
              <span class="lbl-jenis-non">&#9998; NON-MEDIS</span>
            <?php endif; ?>
            <?php if ($kondisi !== 'Baik'): ?>
              <span class="lbl-kondisi" style="background:<?= $kBg ?>;color:<?= $kFg ?>;">&#9650; <?= htmlspecialchars($kondisi) ?></span>
            <?php endif; ?>

            <!-- Nama aset -->
            <div class="lbl-nama"><?= htmlspecialchars($namaAset) ?></div>

            <!-- No. Inventaris -->
            <div class="lbl-inv-box">
              <div class="lbl-inv-label">No. Inventaris</div>
              <div class="lbl-inv-val"><?= htmlspecialchars($noInv) ?></div>
            </div>

            <!-- Info rows -->
            <?php if ($kategori): ?>
            <div class="lbl-row">
              <div class="lbl-row-icon">&#9632;</div>
              <div class="lbl-row-label">Kategori</div>
              <div class="lbl-row-val"><?= htmlspecialchars($kategori) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($merek): ?>
            <div class="lbl-row">
              <div class="lbl-row-icon">&#9670;</div>
              <div class="lbl-row-label">Merek/Model</div>
              <div class="lbl-row-val"><?= htmlspecialchars($merek) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($serial): ?>
            <div class="lbl-row">
              <div class="lbl-row-icon">#</div>
              <div class="lbl-row-label">Serial No.</div>
              <div class="lbl-row-val" style="font-family:'Courier New',monospace;font-size:6.5pt;"><?= htmlspecialchars($serial) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($lokasiDisp): ?>
            <div class="lbl-row">
              <div class="lbl-row-icon">&#9679;</div>
              <div class="lbl-row-label">Lokasi</div>
              <div class="lbl-row-val"><?= htmlspecialchars($lokasiDisp) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($pjDisp): ?>
            <div class="lbl-row">
              <div class="lbl-row-icon">&#9786;</div>
              <div class="lbl-row-label">PJ</div>
              <div class="lbl-row-val"><?= htmlspecialchars($pjDisp) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($tglKalBerik): ?>
            <div class="lbl-row">
              <div class="lbl-row-icon" style="color:#d97706;">&#9202;</div>
              <div class="lbl-row-label" style="color:#92400e;">Kal. Berikut</div>
              <div class="lbl-row-val" style="color:#92400e;font-weight:700;"><?= date('d/m/Y', strtotime($tglKalBerik)) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($tglSvcBerik): ?>
            <div class="lbl-row">
              <div class="lbl-row-icon" style="color:#16a34a;">&#9881;</div>
              <div class="lbl-row-label" style="color:#166534;">Servis Berikut</div>
              <div class="lbl-row-val" style="color:#166534;font-weight:700;"><?= date('d/m/Y', strtotime($tglSvcBerik)) ?></div>
            </div>
            <?php endif; ?>

          </div>
          <div class="lbl-body-right">
            <!-- QR Code visual -->
            <div class="lbl-qr-wrap">
              <?= $qrHtml ?>
              <div class="qr-label"><?= htmlspecialchars(substr($noInv, 0, 16)) ?></div>
            </div>
            <!-- Kondisi badge (bawah QR) -->
            <div style="margin-top:5px;">
              <span class="lbl-kondisi" style="background:<?= $kBg ?>;color:<?= $kFg ?>;font-size:5.5pt;padding:2px 5px;border-radius:3px;display:block;text-align:center;font-weight:800;">
                <?= htmlspecialchars($kondisi) ?>
              </span>
            </div>
            <?php if ($tglBeli): ?>
            <div style="margin-top:3px;font-size:5.5pt;color:#94a3b8;text-align:center;">Tahun: <?= $tglBeli ?></div>
            <?php endif; ?>
            <?php if ($garansi): ?>
            <div style="margin-top:2px;font-size:5pt;color:#94a3b8;text-align:center;line-height:1.3;">Garansi s.d.<br><?= $garansi ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── FOOTER ── -->
        <div class="lbl-footer">
          <div class="lbl-footer-left">
            Dicetak: <?= date('d/m/Y') ?>
            <?php if ($tglBeli): ?> &nbsp;|&nbsp; Thn: <?= $tglBeli ?><?php endif; ?>
          </div>
          <div class="lbl-footer-right">
            IPSRS &mdash; <?= htmlspecialchars($noInv) ?>
          </div>
        </div>

      </div><!-- /label-card -->
    </div><!-- /labels-cell -->
    <?php endforeach; ?>

    <!-- Isi sel kosong jika ganjil -->
    <?php if (count($row) === 1): ?>
    <div class="labels-cell"></div>
    <?php endif; ?>

  </div>
</div>
<?php endforeach; ?>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->setChroot(__DIR__ . '/../dompdf');
$options->set('fontDir',    __DIR__ . '/../dompdf/vendor/dompdf/dompdf/lib/fonts/');
$options->set('fontCache',  __DIR__ . '/../dompdf/vendor/dompdf/dompdf/lib/fonts/');
$options->set('defaultFont', 'helvetica');
$options->set('dpi', 150);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$suffix   = count($asets) === 1 ? '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $asets[0]['no_inventaris']) : '_batch_'.count($asets);
$filename = 'Label_Aset_IPSRS' . $suffix . '_' . date('Ymd') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;