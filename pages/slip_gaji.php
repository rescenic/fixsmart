<?php
// pages/slip_gaji.php
ob_start();
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'keuangan'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// ── Ambil data instansi ───────────────────────────────────────────────────────
$instansi = defined('APP_NAME') ? APP_NAME : 'Sistem Penggajian';
try {
    $ins = $pdo->query("SELECT value FROM settings WHERE `key`='nama_instansi' LIMIT 1")->fetchColumn();
    if ($ins) $instansi = $ins;
} catch (Exception $e) {}

// ── Mode: satu slip (id) atau semua slip satu periode ────────────────────────
$id      = (int)($_GET['id']      ?? 0);
$periode = trim($_GET['periode']  ?? '');

if (!$id && !$periode) {
    echo '<p style="padding:20px;color:red;">Parameter tidak valid. Sertakan ?id=X atau ?periode=YYYY-MM</p>';
    exit;
}

// Ambil baris transaksi
if ($id) {
    $st = $pdo->prepare("
        SELECT tg.*, u.nama,
               sk.divisi, sk.jabatan_id, sk.nik_rs, sk.status_kepegawaian,
               sk.bank, sk.no_rekening, sk.atas_nama_rek
        FROM transaksi_gaji tg
        JOIN users u              ON u.id       = tg.user_id
        LEFT JOIN sdm_karyawan sk ON sk.user_id = tg.user_id
        WHERE tg.id = ?
    ");
    $st->execute([$id]);
    $transaksis = $st->fetchAll(PDO::FETCH_ASSOC);
} else {
    $st = $pdo->prepare("
        SELECT tg.*, u.nama,
               sk.divisi, sk.jabatan_id, sk.nik_rs, sk.status_kepegawaian,
               sk.bank, sk.no_rekening, sk.atas_nama_rek
        FROM transaksi_gaji tg
        JOIN users u              ON u.id       = tg.user_id
        LEFT JOIN sdm_karyawan sk ON sk.user_id = tg.user_id
        WHERE tg.periode = ?
        ORDER BY u.nama ASC
    ");
    $st->execute([$periode]);
    $transaksis = $st->fetchAll(PDO::FETCH_ASSOC);
}

if (!$transaksis) {
    echo '<p style="padding:20px;color:red;">Data tidak ditemukan.</p>';
    exit;
}

// Preload semua detail sekaligus
$tids = array_column($transaksis, 'id');
$detail_map = [];
if ($tids) {
    $in  = implode(',', array_fill(0, count($tids), '?'));
    $det = $pdo->prepare("
        SELECT tgd.*, mp.nama AS nama_p, mpt.nama AS nama_t
        FROM transaksi_gaji_detail tgd
        LEFT JOIN master_penerimaan mp  ON mp.id  = tgd.penerimaan_id
        LEFT JOIN master_potongan   mpt ON mpt.id = tgd.potongan_id
        WHERE tgd.transaksi_id IN ($in)
        ORDER BY tgd.tipe DESC, tgd.id ASC
    ");
    $det->execute($tids);
    foreach ($det->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $detail_map[$d['transaksi_id']][] = $d;
    }
}

ob_end_clean();

// Label periode
function periodeLabel(string $p, array $nb): string {
    [$y,$m] = explode('-', $p);
    return ($nb[(int)$m] ?? $m) . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Slip Gaji — <?= count($transaksis) > 1 ? periodeLabel($transaksis[0]['periode'], $nama_bulan) : htmlspecialchars($transaksis[0]['nama']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
    background: #e5e7eb;
    color: #1e293b;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* ── Toolbar cetak ── */
.toolbar {
    background: #1e293b;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    position: sticky;
    top: 0;
    z-index: 99;
}
.btn-cetak {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 18px;
    background: linear-gradient(135deg,#f59e0b,#d97706);
    color: #1e293b;
    border: none;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: transparent;
    border: 1px solid rgba(255,255,255,.25);
    color: #e2e8f0;
    border-radius: 7px;
    font-size: 13px;
    cursor: pointer;
    font-family: inherit;
    text-decoration: none;
}
.toolbar-info {
    margin-left: auto;
    font-size: 12px;
    color: rgba(255,255,255,.5);
}

/* ── Halaman slip ── */
.page-wrap {
    max-width: 680px;
    margin: 24px auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-bottom: 40px;
}

/* ── Kartu slip ── */
.slip {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(0,0,0,.10);
    overflow: hidden;
    page-break-inside: avoid;
    break-inside: avoid;
}

/* ── Header slip ── */
.slip-hd {
    background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);
    padding: 16px 20px 13px;
    position: relative;
    overflow: hidden;
}
.slip-hd::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2.5px;
    background: linear-gradient(90deg,#f59e0b,#fbbf24,transparent);
}
.hd-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.hd-logo { display: flex; align-items: center; gap: 9px; }
.hd-logo-box {
    width: 34px; height: 34px;
    background: linear-gradient(135deg,#f59e0b,#d97706);
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
}
.hd-logo-box i { color: #1e293b; font-size: 14px; }
.hd-org { font-size: 13px; font-weight: 800; color: #f1f5f9; line-height: 1.2; }
.hd-sub { font-size: 9.5pt; color: rgba(255,255,255,.4); margin-top: 1px; }
.hd-right { text-align: right; }
.hd-per-lbl { font-size: 8pt; color: rgba(255,255,255,.4); letter-spacing: .5px; }
.hd-per-val { font-size: 12pt; font-weight: 800; color: #fbbf24; }

/* ── Info karyawan di header ── */
.emp-bar {
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 6px;
    padding: 8px 13px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}
.emp-nama { font-size: 12pt; font-weight: 800; color: #fff; }
.emp-sub  { font-size: 8.5pt; color: rgba(255,255,255,.45); margin-top: 1px; }
.slip-no  { font-family: monospace; font-size: 9pt; color: rgba(255,255,255,.55); white-space: nowrap; }

/* ── Status bar ── */
.status-bar {
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    padding: 7px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 10.5pt;
    color: #64748b;
}
.badge-paid  { background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;font-size:9.5pt;font-weight:700;padding:2px 10px;border-radius:20px; }
.badge-draft { background:#fef9c3;color:#854d0e;border:1px solid #fde68a;font-size:9.5pt;font-weight:700;padding:2px 10px;border-radius:20px; }

/* ── Body slip ── */
.slip-body { padding: 16px 20px; }

/* ── Grid dua kolom ── */
.two-col { display: table; width: 100%; border-collapse: separate; border-spacing: 14px 0; margin: 0 -14px; }
.col-l, .col-r { display: table-cell; vertical-align: top; width: 50%; }

/* ── Section label ── */
.sec-lbl {
    font-size: 8pt;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #94a3b8;
    padding-bottom: 4px;
    border-bottom: 1.5px solid #f1f5f9;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* ── Tabel komponen ── */
.comp-tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.comp-tbl td { padding: 4px 2px; font-size: 9.5pt; border-bottom: 1px solid #f8fafc; }
.comp-tbl tr:last-child td { border-bottom: none; }
.comp-tbl td:last-child { text-align: right; font-weight: 600; white-space: nowrap; }
.comp-tbl .sub-row td { font-weight: 700; border-top: 1.5px dashed #e2e8f0; padding-top: 6px; background: #f8fafc; }

/* ── Rekap kotak ── */
.rekap-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    padding: 10px 13px;
    margin-bottom: 10px;
}
.rekap-row { display: flex; justify-content: space-between; font-size: 9.5pt; margin-bottom: 4px; }
.rekap-row:last-child { margin-bottom: 0; }
.rekap-lbl { color: #64748b; }
.rekap-val { font-weight: 600; }
.rekap-sep { border-top: 1.5px dashed #e2e8f0; margin: 6px 0; }

/* ── Gaji bersih ── */
.bersih-box {
    background: linear-gradient(135deg,#0f172a,#1e3a5f);
    border-radius: 8px;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    position: relative;
    overflow: hidden;
}
.bersih-box::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg,#f59e0b,#fbbf24);
}
.bersih-lbl { font-size: 9pt; color: rgba(255,255,255,.6); }
.bersih-lbl-main { font-size: 10.5pt; font-weight: 700; color: #fff; margin-top: 2px; }
.bersih-amt { font-size: 15pt; font-weight: 900; color: #fbbf24; }

/* ── Bank info ── */
.bank-box {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-left: 3px solid #0ea5e9;
    border-radius: 6px;
    padding: 9px 12px;
}
.bank-row { display: flex; justify-content: space-between; font-size: 9.5pt; margin-bottom: 3px; }
.bank-row:last-child { margin-bottom: 0; }
.bank-key { color: #64748b; }
.bank-val { font-weight: 600; font-family: monospace; font-size: 9pt; }

/* ── Footer slip ── */
.slip-ft {
    border-top: 1px solid #f1f5f9;
    padding: 9px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
}
.ft-note { font-size: 8.5pt; color: #94a3b8; line-height: 1.5; }
.ft-sign { text-align: right; }
.ft-sign-lbl { font-size: 8.5pt; color: #94a3b8; margin-bottom: 28px; }
.ft-sign-name { font-size: 9pt; font-weight: 700; color: #374151; border-top: 1px solid #cbd5e1; padding-top: 3px; }

/* ── Print styles ── */
@media print {
    body { background: #fff; }
    .toolbar { display: none !important; }
    .page-wrap { margin: 0; padding: 0; gap: 0; max-width: 100%; }
    .slip { border-radius: 0; box-shadow: none; margin-bottom: 0; page-break-after: always; break-after: page; }
    .slip:last-child { page-break-after: avoid; break-after: avoid; }
}
</style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
  <a href="javascript:history.back()" class="btn-back"><i class="fa fa-arrow-left"></i> Kembali</a>
  <button onclick="window.print()" class="btn-cetak"><i class="fa fa-print"></i> Cetak Slip</button>
  <div class="toolbar-info">
    <?= count($transaksis) ?> slip &nbsp;|&nbsp;
    <?= periodeLabel($transaksis[0]['periode'], $nama_bulan) ?> &nbsp;|&nbsp;
    Dicetak: <?= date('d/m/Y H:i') ?>
  </div>
</div>

<div class="page-wrap">

<?php foreach ($transaksis as $tg):
  $details    = $detail_map[$tg['id']] ?? [];
  $pen_items  = array_filter($details, fn($d) => $d['tipe'] === 'penerimaan');
  $pot_items  = array_filter($details, fn($d) => $d['tipe'] === 'potongan');
  $per_label  = periodeLabel($tg['periode'], $nama_bulan);

  // Nama komponen (fallback ke nama_komponen snapshot di tabel)
  $get_nama = fn($d) => $d['tipe'] === 'penerimaan'
      ? ($d['nama_p'] ?: ($d['nama_komponen'] ?? '—'))
      : ($d['nama_t'] ?: ($d['nama_komponen'] ?? '—'));

  // Avatar inisial
  $words   = array_filter(explode(' ', trim($tg['nama'])));
  $inisial = strtoupper(implode('', array_map(fn($w) => mb_substr($w,0,1), array_slice(array_values($words),0,2))));

  // Ambil data bank: dari transaksi (snapshot) lalu fallback ke sdm_karyawan
  $bank_nama = $tg['bank']          ?? '—';
  $bank_rek  = $tg['no_rekening']   ?? '—';
  $bank_atas = $tg['atas_nama_rek'] ?? $tg['nama'];

  $bruto = $tg['gaji_pokok'] + $tg['total_penerimaan'];
?>

<div class="slip">

  <!-- Header -->
  <div class="slip-hd">
    <div class="hd-row">
      <div class="hd-logo">
        <div class="hd-logo-box"><i class="fa fa-building-columns"></i></div>
        <div>
          <div class="hd-org"><?= htmlspecialchars($instansi) ?></div>
          <div class="hd-sub">Slip Gaji Karyawan</div>
        </div>
      </div>
      <div class="hd-right">
        <div class="hd-per-lbl">Periode</div>
        <div class="hd-per-val"><?= $per_label ?></div>
      </div>
    </div>
    <div class="emp-bar">
      <div>
        <div class="emp-nama"><?= htmlspecialchars($tg['nama']) ?></div>
        <div class="emp-sub">
          <?= htmlspecialchars($tg['divisi'] ?? '—') ?>
          <?php if (!empty($tg['status_kepegawaian'])): ?> &bull; <?= htmlspecialchars($tg['status_kepegawaian']) ?><?php endif; ?>
          <?php if (!empty($tg['nik_rs'])): ?> &bull; <?= htmlspecialchars($tg['nik_rs']) ?><?php endif; ?>
        </div>
      </div>
      <div class="slip-no">SLIP-<?= str_pad($tg['id'],6,'0',STR_PAD_LEFT) ?></div>
    </div>
  </div>

  <!-- Status bar -->
  <div class="status-bar">
    <span>
      <i class="fa fa-calendar" style="color:#f59e0b;"></i>
      <?= $tg['tgl_bayar'] ? 'Dibayar: <strong>'.date('d F Y', strtotime($tg['tgl_bayar'])).'</strong>' : 'Belum ada tanggal pembayaran' ?>
    </span>
    <?php if ($tg['status'] === 'dibayar'): ?>
    <span class="badge-paid"><i class="fa fa-check"></i> SUDAH DIBAYAR</span>
    <?php else: ?>
    <span class="badge-draft">DRAFT</span>
    <?php endif; ?>
  </div>

  <!-- Body -->
  <div class="slip-body">
    <div class="two-col">

      <!-- Kolom Kiri: Penerimaan + Potongan -->
      <div class="col-l">
        <!-- Penerimaan -->
        <div class="sec-lbl"><i class="fa fa-circle-plus" style="color:#22c55e;"></i> Penerimaan</div>
        <table class="comp-tbl">
          <tr>
            <td>Gaji Pokok</td>
            <td style="color:#166534;">Rp <?= number_format($tg['gaji_pokok'],0,',','.') ?></td>
          </tr>
          <?php foreach ($pen_items as $item): ?>
          <tr>
            <td><?= htmlspecialchars($get_nama($item)) ?></td>
            <td style="color:#166534;">Rp <?= number_format($item['nilai'],0,',','.') ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="sub-row">
            <td>Total Penerimaan</td>
            <td style="color:#166534;">Rp <?= number_format($bruto,0,',','.') ?></td>
          </tr>
        </table>

        <!-- Potongan -->
        <div class="sec-lbl" style="margin-top:10px;"><i class="fa fa-circle-minus" style="color:#ef4444;"></i> Potongan</div>
        <?php if ($pot_items || $tg['pph21'] > 0): ?>
        <table class="comp-tbl">
          <?php foreach ($pot_items as $item): ?>
          <tr>
            <td><?= htmlspecialchars($get_nama($item)) ?></td>
            <td style="color:#991b1b;">Rp <?= number_format($item['nilai'],0,',','.') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if ($tg['pph21'] > 0): ?>
          <tr>
            <td>
              PPh 21
              <?php if (!empty($tg['ptkp_kode'])): ?>
              <span style="font-size:8pt;background:#f5f3ff;color:#5b21b6;padding:1px 5px;border-radius:4px;margin-left:3px;"><?= htmlspecialchars($tg['ptkp_kode']) ?></span>
              <?php endif; ?>
            </td>
            <td style="color:#7c3aed;">Rp <?= number_format($tg['pph21'],0,',','.') ?></td>
          </tr>
          <?php endif; ?>
          <tr class="sub-row">
            <td>Total Potongan</td>
            <td style="color:#991b1b;">Rp <?= number_format($tg['total_potongan'] + $tg['pph21'],0,',','.') ?></td>
          </tr>
        </table>
        <?php else: ?>
        <div style="font-size:9.5pt;color:#cbd5e1;padding:4px 0;">Tidak ada potongan.</div>
        <?php endif; ?>
      </div>

      <!-- Kolom Kanan: Rekap + Gaji Bersih + Bank -->
      <div class="col-r">
        <!-- Rekap -->
        <div class="sec-lbl"><i class="fa fa-calculator" style="color:#3b82f6;"></i> Rekap</div>
        <div class="rekap-box">
          <div class="rekap-row">
            <span class="rekap-lbl">Gaji Pokok</span>
            <span class="rekap-val">Rp <?= number_format($tg['gaji_pokok'],0,',','.') ?></span>
          </div>
          <div class="rekap-row">
            <span class="rekap-lbl">+ Tunjangan</span>
            <span class="rekap-val" style="color:#166534;">Rp <?= number_format($tg['total_penerimaan'],0,',','.') ?></span>
          </div>
          <div class="rekap-row">
            <span class="rekap-lbl">= Bruto</span>
            <span class="rekap-val" style="color:#1d4ed8;">Rp <?= number_format($bruto,0,',','.') ?></span>
          </div>
          <div class="rekap-sep"></div>
          <div class="rekap-row">
            <span class="rekap-lbl">− Potongan</span>
            <span class="rekap-val" style="color:#991b1b;">Rp <?= number_format($tg['total_potongan'],0,',','.') ?></span>
          </div>
          <div class="rekap-row">
            <span class="rekap-lbl">− PPh 21</span>
            <span class="rekap-val" style="color:#7c3aed;">Rp <?= number_format($tg['pph21'],0,',','.') ?></span>
          </div>
        </div>

        <!-- Gaji Bersih -->
        <div class="bersih-box">
          <div>
            <div class="bersih-lbl">Take Home Pay</div>
            <div class="bersih-lbl-main">Gaji Bersih</div>
          </div>
          <div class="bersih-amt">Rp <?= number_format($tg['gaji_bersih'],0,',','.') ?></div>
        </div>

        <!-- Bank -->
        <?php if ($bank_nama !== '—' || $bank_rek !== '—'): ?>
        <div class="sec-lbl" style="margin-top:10px;"><i class="fa fa-building-columns" style="color:#0ea5e9;"></i> Transfer ke</div>
        <div class="bank-box">
          <?php if ($bank_nama !== '—'): ?>
          <div class="bank-row"><span class="bank-key">Bank</span><span class="bank-val" style="font-family:inherit;font-size:9.5pt;"><?= htmlspecialchars($bank_nama) ?></span></div>
          <?php endif; ?>
          <?php if ($bank_rek !== '—'): ?>
          <div class="bank-row"><span class="bank-key">No. Rekening</span><span class="bank-val"><?= htmlspecialchars($bank_rek) ?></span></div>
          <?php endif; ?>
          <div class="bank-row"><span class="bank-key">Atas Nama</span><span class="bank-val" style="font-family:inherit;font-size:9.5pt;"><?= htmlspecialchars($bank_atas) ?></span></div>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /two-col -->
  </div><!-- /slip-body -->

  <!-- Footer -->
  <div class="slip-ft">
    <div class="ft-note">
      <?= htmlspecialchars($instansi) ?> &bull; <?= $per_label ?> &bull; No: SLIP-<?= str_pad($tg['id'],6,'0',STR_PAD_LEFT) ?><br>
      Dicetak: <?= date('d/m/Y H:i') ?> &bull; <?= htmlspecialchars($_SESSION['user_nama'] ?? 'Sistem') ?>
    </div>
    <div class="ft-sign">
      <div class="ft-sign-lbl">Pejabat Keuangan,</div>
      <div class="ft-sign-name">( ________________________ )</div>
    </div>
  </div>

</div><!-- /slip -->

<?php endforeach; ?>

</div><!-- /page-wrap -->
</body>
</html>