<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('<p style="font-family:sans-serif;padding:20px;color:red;">ID tidak valid.</p>');
}

$st = $pdo->prepare("
    SELECT m.*,
           a.no_inventaris,
           a.nama_aset      AS aset_nama_db,
           a.kategori       AS aset_kat,
           a.merek          AS aset_merek,
           a.model_aset,
           a.serial_number  AS aset_serial,
           a.kondisi        AS aset_kondisi_skrg,
           b.nama           AS bagian_nama,
           b.kode           AS bagian_kode,
           b.lokasi         AS bagian_lokasi,
           u.nama           AS tek_nama_db,
           u.divisi         AS tek_divisi,
           u.no_hp          AS tek_hp,
           uc.nama          AS dibuat_oleh
    FROM maintenance_it m
    LEFT JOIN aset_it a  ON a.id  = m.aset_id
    LEFT JOIN bagian  b  ON b.id  = a.bagian_id
    LEFT JOIN users   u  ON u.id  = m.teknisi_id
    LEFT JOIN users   uc ON uc.id = m.created_by
    WHERE m.id = ?
");
$st->execute([$id]);
$mnt = $st->fetch(PDO::FETCH_ASSOC);

if (!$mnt) {
    die('<p style="font-family:sans-serif;padding:20px;color:red;">Data maintenance tidak ditemukan (id='.$id.').</p>');
}

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
$bagian_disp = !empty($mnt['bagian_nama'])
    ? ((!empty($mnt['bagian_kode']) ? '[' . $mnt['bagian_kode'] . '] ' : '') . $mnt['bagian_nama'])
    : (!empty($mnt['bagian_lokasi']) ? $mnt['bagian_lokasi'] : '---');
$app_name = defined('APP_NAME') ? APP_NAME : 'FixSmart Helpdesk';

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kartu Maintenance - <?php echo x($mnt['no_maintenance']); ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{font-size:13px;}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f4f8;color:#1e293b;padding:24px 16px;}

/* Toolbar */
.toolbar{max-width:820px;margin:0 auto 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;background:#1e293b;border-radius:8px;padding:10px 16px;}
.toolbar .tb-info{font-size:12px;color:rgba(255,255,255,.6);display:flex;align-items:center;gap:8px;}
.toolbar .tb-info strong{color:#fff;}
.toolbar .tb-btns{display:flex;gap:8px;}
.btn-print{display:inline-flex;align-items:center;gap:6px;padding:7px 18px;background:linear-gradient(135deg,#26B99A,#1a7a5e);color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;}
.btn-print:hover{opacity:.85;}
.btn-back{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.2);border-radius:6px;font-size:12px;cursor:pointer;font-family:inherit;text-decoration:none;}
.btn-back:hover{background:rgba(255,255,255,.2);}

/* Kartu */
.kartu{max-width:820px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.12);overflow:hidden;}

/* Header kartu */
.kartu-header{background:linear-gradient(135deg,#1a2e3f 0%,#1b5c4a 100%);padding:18px 22px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;}
.kh-logo{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
.kh-logo-box{width:36px;height:36px;background:rgba(38,185,154,.25);border:1.5px solid rgba(38,185,154,.5);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;color:#26B99A;flex-shrink:0;}
.kh-appname{font-size:13px;font-weight:700;color:#fff;}
.kh-appsub{font-size:9.5px;color:rgba(255,255,255,.45);margin-top:2px;}
.kh-judul{font-size:18px;font-weight:800;color:#fff;}
.kh-sub{font-size:10px;color:rgba(255,255,255,.5);margin-top:3px;}
.kh-right{text-align:right;flex-shrink:0;}
.no-mnt-box{background:rgba(38,185,154,.15);border:1px solid rgba(38,185,154,.4);border-radius:7px;padding:8px 14px;display:inline-block;min-width:160px;}
.no-mnt-label{font-size:8.5px;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.8px;margin-bottom:3px;}
.no-mnt-val{font-size:14px;font-weight:900;color:#5eead4;font-family:'Courier New',monospace;letter-spacing:.5px;}

/* Section */
.section{border-bottom:1px solid #e8ecf0;}
.section:last-of-type{border-bottom:none;}
.sec-title{background:#f8fafc;border-bottom:1px solid #e8ecf0;padding:8px 20px;font-size:10px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.7px;display:flex;align-items:center;gap:7px;}
.sec-title .dot{width:7px;height:7px;border-radius:50%;background:#26B99A;display:inline-block;flex-shrink:0;}
.sec-body{padding:14px 20px;}

/* Grid 2 kolom */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:0 24px;}

/* Data row */
.dr{margin-bottom:10px;}
.dr:last-child{margin-bottom:0;}
.dr-lbl{font-size:9.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;}
.dr-val{font-size:12px;font-weight:600;color:#1e293b;line-height:1.4;}
.dr-val.mono{font-family:'Courier New',monospace;font-size:11.5px;}
.dr-val small{font-size:10.5px;color:#94a3b8;font-weight:400;}

/* Badge */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:10.5px;font-weight:700;}

/* Kondisi */
.kondisi-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.kondisi-lbl{font-size:8.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;}
.arrow{font-size:18px;color:#94a3b8;line-height:1;}

/* Teks box */
.teks-title{font-size:9.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;}
.teks-box{background:#f8fafc;border:1px solid #e8ecf0;border-radius:6px;padding:10px 12px;font-size:11.5px;color:#374151;line-height:1.7;min-height:54px;white-space:pre-wrap;word-break:break-word;}
.teks-box.kosong{color:#cbd5e1;font-style:italic;}

/* Pengingat */
.remind-box{margin:0 20px 16px;background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1.5px solid #fde68a;border-radius:8px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:14px;}
.remind-icon{width:40px;height:40px;background:#f59e0b;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;}
.remind-body{flex:1;}
.remind-label{font-size:9.5px;font-weight:700;color:#78350f;text-transform:uppercase;letter-spacing:.6px;}
.remind-date{font-size:16px;font-weight:800;color:#1e293b;margin-top:2px;}
.remind-cd{font-size:10px;font-weight:700;padding:4px 12px;border-radius:12px;background:#fff;border:1px solid #fde68a;text-align:center;white-space:nowrap;}
.remind-cd small{display:block;font-size:9px;font-weight:400;color:#a16207;margin-top:1px;}

/* TTD */
.ttd-wrap{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:16px 20px;border-top:1px solid #e8ecf0;}
.ttd-box{border:1px solid #e2e8f0;border-radius:7px;padding:10px 14px;text-align:center;}
.ttd-title{font-size:9.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:50px;}
.ttd-garis{border-top:1px dashed #e2e8f0;padding-top:6px;}
.ttd-nama{font-size:11px;font-weight:700;color:#1e293b;}
.ttd-sub{font-size:9.5px;color:#94a3b8;margin-top:2px;}

/* Footer */
.kartu-footer{background:#f8fafc;border-top:1px solid #e8ecf0;padding:9px 20px;display:flex;align-items:center;justify-content:space-between;}
.kartu-footer .kf-l{font-size:10px;color:#94a3b8;}
.kartu-footer .kf-l strong{color:#64748b;}
.kartu-footer .kf-r{font-size:10px;color:#cbd5e1;}

.divider{height:1px;background:#e8ecf0;margin:10px 0;}

@media print {
    *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}
    body{background:#fff!important;padding:0!important;}
    .toolbar{display:none!important;}
    .kartu{max-width:100%!important;box-shadow:none!important;border-radius:0!important;}
    @page{size:A4 portrait;margin:10mm 12mm;}
}
</style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
  <div class="tb-info">
    <i class="fa fa-file-alt" style="color:#26B99A;font-size:14px;"></i>
    Kartu Maintenance &mdash; <strong><?php echo x($mnt['no_maintenance']); ?></strong>
  </div>
  <div class="tb-btns">
    <a href="javascript:history.back()" class="btn-back">&#8592; Kembali</a>
    <button onclick="window.print()" class="btn-print">
      <i class="fa fa-print"></i> Cetak / Simpan PDF
    </button>
  </div>
</div>

<!-- Kartu -->
<div class="kartu">

  <!-- Header -->
  <div class="kartu-header">
    <div>
      <div class="kh-logo">
        <div class="kh-logo-box">FS</div>
        <div>
          <div class="kh-appname"><?php echo x($app_name); ?></div>
          <div class="kh-appsub">Work Order &amp; Asset Management System</div>
        </div>
      </div>
      <div class="kh-judul">Kartu Maintenance IT</div>
      <div class="kh-sub">Dokumen catatan perawatan dan pemeliharaan aset IT</div>
    </div>
    <div class="kh-right">
      <div class="no-mnt-box">
        <div class="no-mnt-label">No. Maintenance</div>
        <div class="no-mnt-val"><?php echo x($mnt['no_maintenance']); ?></div>
      </div>
      <div style="margin-top:6px;">
        <span class="badge" style="background:<?php echo $s_bg; ?>;color:<?php echo $s_tc; ?>;border:1px solid <?php echo $s_br; ?>;">
          <?php echo x($mnt['status']); ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Informasi Aset -->
  <div class="section">
    <div class="sec-title"><span class="dot"></span> Informasi Aset</div>
    <div class="sec-body">
      <div class="g2">
        <div>
          <div class="dr"><div class="dr-lbl">Nama Aset</div><div class="dr-val"><?php echo x($nama_aset); ?></div></div>
          <div class="dr"><div class="dr-lbl">No. Inventaris</div><div class="dr-val mono"><?php echo x($no_inv); ?></div></div>
          <div class="dr"><div class="dr-lbl">Kategori</div><div class="dr-val"><?php echo x($mnt['aset_kat'] ?? '---'); ?></div></div>
        </div>
        <div>
          <div class="dr"><div class="dr-lbl">Merek / Model</div><div class="dr-val"><?php echo x($merek_model); ?></div></div>
          <div class="dr"><div class="dr-lbl">Serial Number</div><div class="dr-val mono"><?php echo x($mnt['aset_serial'] ?? '---'); ?></div></div>
          <div class="dr"><div class="dr-lbl">Lokasi / Bagian</div><div class="dr-val"><?php echo x($bagian_disp); ?></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Detail Maintenance -->
  <div class="section">
    <div class="sec-title"><span class="dot"></span> Detail Maintenance</div>
    <div class="sec-body">
      <div class="g2">
        <div>
          <div class="dr"><div class="dr-lbl">Tanggal Maintenance</div><div class="dr-val"><?php echo fmtTgl($mnt['tgl_maintenance']); ?></div></div>
          <div class="dr"><div class="dr-lbl">Jenis Maintenance</div><div class="dr-val"><?php echo x($mnt['jenis_maintenance'] ?? '---'); ?></div></div>
          <div class="dr"><div class="dr-lbl">Biaya</div><div class="dr-val"><?php echo fmtRp($mnt['biaya']); ?></div></div>
        </div>
        <div>
          <div class="dr">
            <div class="dr-lbl">Teknisi Pelaksana</div>
            <div class="dr-val">
              <?php echo x($nama_tek); ?>
              <?php if(!empty($mnt['tek_divisi'])): ?>
              <br><small><?php echo x($mnt['tek_divisi']); ?></small>
              <?php endif; ?>
            </div>
          </div>
          <div class="dr"><div class="dr-lbl">No. HP Teknisi</div><div class="dr-val"><?php echo x($mnt['tek_hp'] ?? '---'); ?></div></div>
          <div class="dr"><div class="dr-lbl">Dicatat Oleh</div><div class="dr-val"><?php echo x($mnt['dibuat_oleh'] ?? '---'); ?></div></div>
        </div>
      </div>
      <div class="divider"></div>
      <div class="dr">
        <div class="dr-lbl" style="margin-bottom:7px;">Perubahan Kondisi Aset</div>
        <div class="kondisi-row">
          <div>
            <div class="kondisi-lbl">Sebelum</div>
            <span class="badge" style="background:<?php echo $ksbl_bg; ?>;color:<?php echo $ksbl_tc; ?>;"><?php echo x($mnt['kondisi_sebelum'] ?? '---'); ?></span>
          </div>
          <div class="arrow">&#8594;</div>
          <div>
            <div class="kondisi-lbl">Sesudah</div>
            <span class="badge" style="background:<?php echo $kssd_bg; ?>;color:<?php echo $kssd_tc; ?>;"><?php echo x($mnt['kondisi_sesudah'] ?? '---'); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Temuan & Tindakan -->
  <div class="section">
    <div class="sec-title"><span class="dot"></span> Temuan &amp; Tindakan</div>
    <div class="sec-body">
      <div class="g2" style="gap:14px;">
        <div>
          <div class="teks-title">Temuan / Masalah</div>
          <div class="teks-box <?php echo $mnt['temuan'] ? '' : 'kosong'; ?>">
            <?php echo $mnt['temuan'] ? x($mnt['temuan']) : 'Tidak ada temuan khusus.'; ?>
          </div>
        </div>
        <div>
          <div class="teks-title">Tindakan yang Dilakukan</div>
          <div class="teks-box <?php echo $mnt['tindakan'] ? '' : 'kosong'; ?>">
            <?php echo $mnt['tindakan'] ? x($mnt['tindakan']) : 'Tidak ada tindakan khusus.'; ?>
          </div>
        </div>
      </div>
      <?php if (!empty($mnt['keterangan'])): ?>
      <div style="margin-top:12px;">
        <div class="teks-title">Keterangan Tambahan</div>
        <div class="teks-box"><?php echo x($mnt['keterangan']); ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pengingat -->
  <div class="section" style="border-bottom:none;">
    <div class="sec-title"><span class="dot" style="background:#f59e0b;"></span> Pengingat Maintenance Berikutnya</div>
    <div style="padding:14px 20px 6px;">
      <div class="remind-box">
        <div class="remind-icon"><i class="fa fa-bell"></i></div>
        <div class="remind-body">
          <div class="remind-label">Jadwal Selanjutnya &mdash; Siklus 3 Bulan</div>
          <div class="remind-date"><?php echo fmtTgl($mnt['tgl_maintenance_berikut']); ?></div>
        </div>
        <?php if ($countdown_txt): ?>
        <div class="remind-cd" style="color:<?php echo $countdown_col; ?>;">
          <?php echo x($countdown_txt); ?>
          <small>dari hari ini</small>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- TTD -->
  <div class="ttd-wrap">
    <div class="ttd-box">
      <div class="ttd-title">Teknisi Pelaksana</div>
      <div class="ttd-garis">
        <div class="ttd-nama"><?php echo x($nama_tek ?: '..................................'); ?></div>
        <div class="ttd-sub"><?php echo x($mnt['tek_divisi'] ?? 'Teknisi IT'); ?></div>
      </div>
    </div>
    <div class="ttd-box">
      <div class="ttd-title">Mengetahui / Menyetujui</div>
      <div class="ttd-garis">
        <div class="ttd-nama">..................................</div>
        <div class="ttd-sub">Kepala Bagian IT</div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="kartu-footer">
    <div class="kf-l">
      Dicetak: <strong><?php echo date('d M Y, H:i'); ?> WIB</strong>
      &nbsp;&bull;&nbsp; Oleh: <strong><?php echo x($mnt['dibuat_oleh'] ?? '---'); ?></strong>
    </div>
    <div class="kf-r"><?php echo x($app_name); ?> &bull; Dokumen Resmi Maintenance IT</div>
  </div>

</div><!-- /kartu -->

<script>
<?php if (isset($_GET['autoprint'])): ?>
window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 400); });
<?php endif; ?>
</script>
</body>
</html>