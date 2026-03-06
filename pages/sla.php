<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title='Laporan SLA'; $active_menu='sla';

$bulan = (int)($_GET['bulan']??date('m'));
$tahun = (int)($_GET['tahun']??date('Y'));

// SLA per kategori
$sla_kat = $pdo->prepare("
    SELECT k.nama, k.sla_jam, k.sla_respon_jam,
        COUNT(t.id) as total,
        SUM(t.status IN ('selesai','ditolak','tidak_bisa')) as selesai,
        SUM(t.status='selesai') as solved,
        AVG(t.durasi_respon_menit) as avg_respon,
        AVG(t.durasi_selesai_menit) as avg_selesai,
        SUM(t.status='selesai' AND t.durasi_selesai_menit <= k.sla_jam*60) as sla_met,
        SUM(t.status IN ('menunggu','diproses')) as aktif
    FROM kategori k
    LEFT JOIN tiket t ON t.kategori_id=k.id AND MONTH(t.created_at)=? AND YEAR(t.created_at)=?
    GROUP BY k.id ORDER BY k.nama
");
$sla_kat->execute([$bulan,$tahun]); $sla_kat = $sla_kat->fetchAll();

// SLA per teknisi
$sla_tek = $pdo->prepare("
    SELECT u.nama,
        COUNT(t.id) as total,
        SUM(t.status='selesai') as selesai,
        SUM(t.status='ditolak') as ditolak,
        SUM(t.status='tidak_bisa') as tdk_bisa,
        AVG(t.durasi_respon_menit) as avg_respon,
        AVG(t.durasi_selesai_menit) as avg_selesai,
        SUM(t.status='selesai' AND t.durasi_selesai_menit <= (SELECT k2.sla_jam*60 FROM kategori k2 WHERE k2.id=t.kategori_id)) as sla_met
    FROM users u
    LEFT JOIN tiket t ON t.teknisi_id = u.id
        AND (
            (t.status = 'selesai'  AND MONTH(t.waktu_selesai) = ? AND YEAR(t.waktu_selesai) = ?)
            OR
            (t.status != 'selesai' AND MONTH(t.created_at)    = ? AND YEAR(t.created_at)    = ?)
        )
    WHERE u.role='teknisi' AND u.status='aktif'
    GROUP BY u.id ORDER BY selesai DESC
");
$sla_tek->execute([$bulan,$tahun,$bulan,$tahun]); $sla_tek=$sla_tek->fetchAll();

// Overall
$overall = $pdo->prepare("
    SELECT COUNT(*) as total,
        SUM(status IN ('selesai','ditolak','tidak_bisa')) as selesai,
        SUM(status='menunggu') as menunggu, SUM(status='diproses') as diproses,
        SUM(status='selesai') as solved, SUM(status='ditolak') as ditolak,
        SUM(status='tidak_bisa') as tidak_bisa,
        AVG(durasi_respon_menit) as avg_respon,
        AVG(durasi_selesai_menit) as avg_selesai,
        SUM(status='selesai' AND durasi_selesai_menit <= (SELECT k.sla_jam*60 FROM kategori k WHERE k.id=kategori_id)) as sla_met
    FROM tiket WHERE MONTH(created_at)=? AND YEAR(created_at)=?
");
$overall->execute([$bulan,$tahun]); $ov=$overall->fetch();
$sla_pct = $ov['solved']>0 ? round($ov['sla_met']/$ov['solved']*100) : 0;

$nama_bulan=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

include '../includes/header.php';
?>
<div class="page-header">
  <h4><i class="fa fa-chart-line text-primary"></i> &nbsp;Laporan SLA</h4>
  <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">SLA</span></div>
</div>
<div class="content">

 <!-- Filter + Tombol Cetak -->
<div class="panel">
  <div class="panel-bd" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:space-between;">
    <form method="GET" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <label style="font-size:12px;font-weight:700;">Periode:</label>
      <select name="bulan" class="sel-filter">
        <?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m==$bulan?'selected':'' ?>><?= $nama_bulan[$m] ?></option><?php endfor; ?>
      </select>
      <select name="tahun" class="sel-filter">
        <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?><option value="<?= $y ?>" <?= $y==$tahun?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Tampilkan</button>
    </form>

    <!-- Tombol Aksi -->
    <div style="display:flex;gap:7px;align-items:center;">
      <a href="<?= APP_URL ?>/pages/cetak_sla.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>"
         class="btn btn-danger btn-sm" target="_blank"
         title="Download laporan SLA sebagai PDF">
        <i class="fa fa-file-pdf"></i> &nbsp;Cetak PDF
      </a>
      <a href="<?= APP_URL ?>/pages/export_sla.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>"
         class="btn btn-sm"
         style="background:#16a34a;color:#fff;border-color:#15803d;font-weight:600;"
         title="Download laporan SLA sebagai Excel"
         onclick="this.innerHTML='<i class=\'fa fa-spinner fa-spin\'></i> &nbsp;Generating...';setTimeout(()=>this.innerHTML='<i class=\'fa fa-file-excel\'></i> &nbsp;Export Excel',4000);">
        <i class="fa fa-file-excel"></i> &nbsp;Export Excel
      </a>
    </div>
  </div>
</div>

  <!-- Periode aktif -->
  <div style="font-size:12px;color:#888;margin-bottom:12px;padding:0 2px;">
    Menampilkan data: <strong><?= $nama_bulan[$bulan].' '.$tahun ?></strong>
  </div>

  <!-- Overall stats -->
  <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
    <div class="stat-card c-total"><i class="fa fa-ticket-alt sc-icon"></i><div><div class="sc-num"><?= $ov['total']??0 ?></div><div class="sc-lbl">Total Tiket</div></div></div>
    <div class="stat-card c-selesai"><i class="fa fa-check-circle sc-icon"></i><div><div class="sc-num"><?= $ov['solved']??0 ?></div><div class="sc-lbl">Selesai</div></div></div>
    <div class="stat-card c-ditolak"><i class="fa fa-ban sc-icon"></i><div><div class="sc-num"><?= ($ov['ditolak']??0)+($ov['tidak_bisa']??0) ?></div><div class="sc-lbl">Ditolak / Tdk Bisa</div></div></div>
    <div class="stat-card c-diproses"><i class="fa fa-stopwatch sc-icon"></i><div><div class="sc-num"><?= formatDurasi(round($ov['avg_respon']??0)) ?></div><div class="sc-lbl">Avg. Respon</div></div></div>
    <div class="stat-card c-menunggu"><i class="fa fa-clock sc-icon"></i><div><div class="sc-num"><?= formatDurasi(round($ov['avg_selesai']??0)) ?></div><div class="sc-lbl">Avg. Selesai</div></div></div>
  </div>

  <!-- SLA Gauge -->
  <div class="g2">
    <div class="panel">
      <div class="panel-hd"><h5><i class="fa fa-tachometer-alt text-primary"></i> &nbsp;Pencapaian SLA Bulan Ini</h5></div>
      <div class="panel-bd" style="text-align:center;padding:20px;">
        <div style="font-size:52px;font-weight:700;color:<?= $sla_pct>=90?'var(--green)':($sla_pct>=70?'var(--orange)':'var(--red)') ?>;"><?= $sla_pct ?>%</div>
        <div style="font-size:13px;color:#aaa;margin-bottom:15px;"><?= $ov['sla_met']??0 ?> dari <?= $ov['solved']??0 ?> tiket selesai dalam target SLA</div>
        <div class="progress" style="height:12px;max-width:300px;margin:0 auto;">
          <div class="progress-fill <?= $sla_pct>=90?'pg-green':($sla_pct>=70?'pg-orange':'pg-red') ?>" style="width:<?= $sla_pct ?>%;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;max-width:300px;margin:5px auto 0;font-size:10px;color:#bbb;">
          <span>0%</span><span>Target ≥ 90%</span><span>100%</span>
        </div>
      </div>
    </div>

    <!-- SLA per Teknisi -->
    <div class="panel">
      <div class="panel-hd"><h5><i class="fa fa-users text-primary"></i> &nbsp;SLA per Teknisi</h5></div>
      <div class="panel-bd np">
        <table>
          <thead><tr><th>Teknisi</th><th>Total</th><th>Selesai</th><th>Ditolak</th><th>SLA Met</th><th>Avg. Selesai</th></tr></thead>
          <tbody>
            <?php if (empty($sla_tek)): ?><tr><td colspan="6" class="td-empty"><i class="fa fa-users"></i> Tidak ada data</td></tr>
            <?php else: foreach ($sla_tek as $tek):
              $tek_sla = $tek['selesai']>0 ? round($tek['sla_met']/$tek['selesai']*100) : 0; ?>
            <tr>
              <td><div class="d-flex ai-c gap6"><div class="av av-xs av-blue"><?= getInitials($tek['nama']) ?></div><?= clean($tek['nama']) ?></div></td>
              <td style="font-weight:700;"><?= $tek['total'] ?></td>
              <td style="color:var(--green);font-weight:700;"><?= $tek['selesai'] ?></td>
              <td style="color:var(--red);"><?= ($tek['ditolak']??0)+($tek['tdk_bisa']??0) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:6px;">
                  <div class="progress" style="width:50px;"><div class="progress-fill <?= $tek_sla>=90?'pg-green':($tek_sla>=70?'pg-orange':'pg-red') ?>" style="width:<?= $tek_sla ?>%;"></div></div>
                  <span style="font-size:11px;font-weight:700;"><?= $tek_sla ?>%</span>
                </div>
              </td>
              <td style="font-size:12px;"><?= formatDurasi(round($tek['avg_selesai']??0)) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- SLA per Kategori -->
  <div class="panel">
    <div class="panel-hd"><h5><i class="fa fa-tags text-primary"></i> &nbsp;SLA per Kategori</h5></div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead><tr><th>Kategori</th><th>Target SLA</th><th>Total Tiket</th><th>Selesai</th><th>Dalam Target</th><th>% SLA</th><th>Avg. Respon</th><th>Avg. Selesai</th><th>Masih Aktif</th></tr></thead>
        <tbody>
          <?php foreach ($sla_kat as $k):
            $k_sla = $k['solved']>0 ? round($k['sla_met']/$k['solved']*100) : 0;
            $sla_cls = $k_sla>=90?'#d1fae5;color:#065f46':($k_sla>=70?'#fef3c7;color:#92400e':'#fee2e2;color:#991b1b');
          ?>
          <tr>
            <td style="font-weight:600;"><?= clean($k['nama']) ?></td>
            <td><span style="font-size:11px;color:#888;"><?= $k['sla_jam'] ?> jam</span></td>
            <td style="font-weight:700;"><?= $k['total']??0 ?></td>
            <td style="color:var(--green);font-weight:700;"><?= $k['selesai']??0 ?></td>
            <td><?= $k['sla_met']??0 ?></td>
            <td>
              <span class="sla-badge" style="background:<?= $sla_cls ?>;"><?= $k_sla ?>%</span>
            </td>
            <td style="font-size:12px;"><?= formatDurasi(round($k['avg_respon']??0)) ?></td>
            <td style="font-size:12px;"><?= formatDurasi(round($k['avg_selesai']??0)) ?></td>
            <td><?= $k['aktif']??0 ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php include '../includes/footer.php'; ?>