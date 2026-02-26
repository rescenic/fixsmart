<?php
session_start();
require_once 'config.php';
requireLogin();
$page_title = 'Dashboard';
$active_menu = 'dashboard';

if (hasRole('user')) {
    // ── DASHBOARD USER: status tiket miliknya ──
    $uid = $_SESSION['user_id'];

    $my_stats = [];
    $st = $pdo->prepare("SELECT status, COUNT(*) as n FROM tiket WHERE user_id=? GROUP BY status");
    $st->execute([$uid]); foreach ($st->fetchAll() as $r) $my_stats[$r['status']] = $r['n'];
    $my_stats['total'] = array_sum($my_stats);

    $my_tiket = $pdo->prepare("
        SELECT t.*, k.nama as kat_nama, tek.nama as tek_nama
        FROM tiket t
        LEFT JOIN kategori k ON k.id=t.kategori_id
        LEFT JOIN users tek ON tek.id=t.teknisi_id
        WHERE t.user_id=?
        ORDER BY t.created_at DESC LIMIT 10
    ");
    $my_tiket->execute([$uid]);
    $my_tiket = $my_tiket->fetchAll();

} else {
    // ── DASHBOARD IT / ADMIN ──
    $stats = [];
    $st = $pdo->query("SELECT status, COUNT(*) as n FROM tiket GROUP BY status");
    foreach ($st->fetchAll() as $r) $stats[$r['status']] = $r['n'];
    $stats['total'] = array_sum($stats);

    // Tiket terbaru masuk (antrian)
    $antrian = $pdo->query("
        SELECT t.*, k.nama as kat_nama, u.nama as req_nama, u.divisi
        FROM tiket t
        LEFT JOIN kategori k ON k.id=t.kategori_id
        LEFT JOIN users u ON u.id=t.user_id
        WHERE t.status='menunggu'
        ORDER BY
          CASE t.prioritas WHEN 'Tinggi' THEN 1 WHEN 'Sedang' THEN 2 ELSE 3 END,
          t.waktu_submit ASC
        LIMIT 8
    ")->fetchAll();

    // Tiket diproses
    $diproses = $pdo->query("
        SELECT t.*, k.nama as kat_nama, u.nama as req_nama, tek.nama as tek_nama,
               k.sla_jam
        FROM tiket t
        LEFT JOIN kategori k ON k.id=t.kategori_id
        LEFT JOIN users u ON u.id=t.user_id
        LEFT JOIN users tek ON tek.id=t.teknisi_id
        WHERE t.status='diproses'
        ORDER BY t.waktu_diproses ASC LIMIT 8
    ")->fetchAll();

    // SLA summary bulan ini
    $sla_summary = $pdo->query("
        SELECT
          COUNT(*) as total,
          SUM(CASE WHEN status IN ('selesai','ditolak','tidak_bisa') THEN 1 ELSE 0 END) as selesai,
          SUM(CASE WHEN status='selesai' AND durasi_selesai_menit <= (SELECT sla_jam*60 FROM kategori WHERE id=kategori_id) THEN 1 ELSE 0 END) as sla_met,
          AVG(durasi_respon_menit) as avg_respon,
          AVG(durasi_selesai_menit) as avg_selesai
        FROM tiket
        WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    ")->fetch();

    // Chart 7 hari
    $chart_data = [];
    for ($i=6; $i>=0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $lbl = date('d/m', strtotime("-$i days"));
        $st = $pdo->prepare("SELECT COUNT(*) FROM tiket WHERE DATE(created_at)=?");
        $st->execute([$d]); $chart_data[] = ['lbl'=>$lbl,'n'=>(int)$st->fetchColumn()];
    }
    $chart_max = max(array_column($chart_data,'n')) ?: 1;
}

include 'includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-home text-primary"></i> &nbsp;Dashboard</h4>
  <div class="breadcrumb"><span class="cur">Beranda</span></div>
</div>

<div class="content">
<?= showFlash() ?>

<?php if (hasRole('user')): ?>
<!-- ============================================================ -->
<!-- TAMPILAN USER                                                  -->
<!-- ============================================================ -->

<!-- Banner ajakan -->
<div class="panel" style="border-left:4px solid var(--primary);margin-bottom:14px;">
  <div class="panel-bd" style="display:flex;align-items:center;gap:14px;">
    <i class="fa fa-headset" style="font-size:32px;color:var(--primary);opacity:.4;"></i>
    <div style="flex:1;">
      <strong style="font-size:13px;">Selamat datang, <?= clean($_SESSION['user_nama']) ?>!</strong>
      <p style="color:#888;font-size:12px;margin-top:2px;">Ada masalah dengan perangkat IT Anda? Buat tiket dan tim IT kami akan segera menangani.</p>
    </div>
    <a href="<?= APP_URL ?>/pages/buat_tiket.php" class="btn btn-primary" style="white-space:nowrap;">
      <i class="fa fa-plus"></i> Buat Tiket Baru
    </a>
  </div>
</div>

<!-- Stat user -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
  <?php
  $user_stats_def = [
    'total'     => ['Total Tiket',    'fa-ticket-alt', 'c-total'],
    'menunggu'  => ['Menunggu',       'fa-clock',      'c-menunggu'],
    'diproses'  => ['Diproses',       'fa-cogs',       'c-diproses'],
    'selesai'   => ['Selesai',        'fa-check-circle','c-selesai'],
    'ditolak'   => ['Ditolak/Tdk Bisa','fa-ban',       'c-ditolak'],
  ];
  foreach ($user_stats_def as $key=>[$lbl,$ic,$cls]):
    $val = $key === 'ditolak' ? ($my_stats['ditolak']??0)+($my_stats['tidak_bisa']??0) : ($my_stats[$key]??0);
  ?>
  <div class="stat-card <?= $cls ?>">
    <i class="fa <?= $ic ?> sc-icon"></i>
    <div><div class="sc-num"><?= $val ?></div><div class="sc-lbl"><?= $lbl ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tiket user terbaru -->
<div class="panel">
  <div class="panel-hd">
    <h5><i class="fa fa-list text-primary"></i> &nbsp;Tiket Saya Terbaru</h5>
    <a href="<?= APP_URL ?>/pages/tiket_saya.php" class="btn btn-default btn-sm">Lihat Semua</a>
  </div>
  <div class="panel-bd np tbl-wrap">
    <table>
      <thead><tr><th>No. Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Teknisi</th><th>Tanggal</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($my_tiket)): ?>
        <tr><td colspan="8" class="td-empty"><i class="fa fa-inbox"></i>Belum ada tiket. <a href="<?= APP_URL ?>/pages/buat_tiket.php">Buat tiket pertama Anda</a></td></tr>
        <?php else: foreach ($my_tiket as $t): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
          <td style="max-width:170px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($t['judul']) ?>"><?= clean($t['judul']) ?></span></td>
          <td><?= clean($t['kat_nama']??'-') ?></td>
          <td><?= badgePrioritas($t['prioritas']) ?></td>
          <td><?= badgeStatus($t['status']) ?></td>
          <td><?= $t['tek_nama'] ? '<div class="d-flex ai-c gap6"><div class="av av-xs av-blue">'.getInitials($t['tek_nama']).'</div>'.clean($t['tek_nama']).'</div>' : '<span class="text-muted">—</span>' ?></td>
          <td style="color:#aaa;font-size:11px;white-space:nowrap;"><?= formatTanggal($t['waktu_submit']) ?></td>
          <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- ============================================================ -->
<!-- TAMPILAN IT (ADMIN / TEKNISI)                                 -->
<!-- ============================================================ -->

<!-- Stat cards -->
<div class="stats-grid">
  <?php
  $it_stats_def = [
    'total'     => ['Total Tiket',   'fa-ticket-alt','c-total'],
    'menunggu'  => ['Menunggu',      'fa-clock',     'c-menunggu'],
    'diproses'  => ['Diproses',      'fa-cogs',      'c-diproses'],
    'selesai'   => ['Selesai',       'fa-check-circle','c-selesai'],
    'ditolak'   => ['Ditolak/Tdk Bisa','fa-ban',     'c-ditolak'],
  ];
  foreach ($it_stats_def as $key=>[$lbl,$ic,$cls]):
    $val = $key==='ditolak' ? ($stats['ditolak']??0)+($stats['tidak_bisa']??0) : ($stats[$key]??0);
  ?>
  <div class="stat-card <?= $cls ?>">
    <i class="fa <?= $ic ?> sc-icon"></i>
    <div><div class="sc-num"><?= $val ?></div><div class="sc-lbl"><?= $lbl ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="g3">
  <!-- Antrian Menunggu -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-inbox text-primary"></i> &nbsp;Antrian Menunggu <span style="color:#aaa;font-weight:400;">(<?= count($antrian) ?>)</span></h5>
      <a href="<?= APP_URL ?>/pages/antrian.php" class="btn btn-default btn-sm">Lihat Semua</a>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead><tr><th>Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Pemohon</th><th>Masuk</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (empty($antrian)): ?>
          <tr><td colspan="7" class="td-empty"><i class="fa fa-check-circle" style="color:var(--green);"></i> Tidak ada antrian</td></tr>
          <?php else: foreach ($antrian as $t):
            $durasi = durasiSekarang($t['waktu_submit']); ?>
          <tr>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
            <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($t['judul']) ?>"><?= clean($t['judul']) ?></span></td>
            <td><?= clean($t['kat_nama']??'-') ?></td>
            <td><?= badgePrioritas($t['prioritas']) ?></td>
            <td>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs"><?= getInitials($t['req_nama']) ?></div>
                <div><?= clean($t['req_nama']) ?><br><span class="text-muted text-sm"><?= clean($t['divisi']??'') ?></span></div>
              </div>
            </td>
            <td style="white-space:nowrap;">
              <span style="font-size:11px;color:#aaa;"><?= formatTanggal($t['waktu_submit'],true) ?></span>
              <?php if ($durasi > 60): ?><br><span style="font-size:10px;color:var(--red);font-weight:700;"><i class="fa fa-clock"></i> <?= formatDurasi($durasi) ?></span><?php endif; ?>
            </td>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fa fa-wrench"></i> Proses</a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right side -->
  <div>
    <!-- SLA Bulan Ini -->
    <div class="panel">
      <div class="panel-hd"><h5><i class="fa fa-chart-line text-primary"></i> &nbsp;SLA Bulan Ini</h5>
        <a href="<?= APP_URL ?>/pages/sla.php" class="btn btn-default btn-sm">Detail</a>
      </div>
      <div class="panel-bd">
        <?php
        $sla_pct = $sla_summary['selesai'] > 0 ? round($sla_summary['sla_met']/$sla_summary['selesai']*100) : 0;
        $sla_cls = $sla_pct >= 90 ? 'pg-green' : ($sla_pct >= 70 ? 'pg-orange' : 'pg-red');
        ?>
        <div style="text-align:center;margin-bottom:12px;">
          <div style="font-size:32px;font-weight:700;color:<?= $sla_pct>=90?'var(--green)':($sla_pct>=70?'var(--orange)':'var(--red)') ?>;"><?= $sla_pct ?>%</div>
          <div style="font-size:11px;color:#aaa;">Tiket selesai dalam target SLA</div>
        </div>
        <div class="progress" style="height:8px;margin-bottom:14px;">
          <div class="progress-fill <?= $sla_cls ?>" style="width:<?= $sla_pct ?>%"></div>
        </div>
        <?php
        $sla_items = [
          ['Total Tiket',      $sla_summary['total']??0,              null],
          ['Sudah Selesai',    $sla_summary['selesai']??0,            null],
          ['Dalam Target SLA', $sla_summary['sla_met']??0,            null],
          ['Avg. Respon',      formatDurasi($sla_summary['avg_respon']??null), null],
          ['Avg. Penyelesaian',formatDurasi($sla_summary['avg_selesai']??null),null],
        ];
        foreach ($sla_items as [$lbl,$val,$_]):
        ?>
        <div class="d-flex ai-c" style="justify-content:space-between;margin-bottom:7px;font-size:12px;">
          <span style="color:#888;"><?= $lbl ?></span>
          <strong style="color:#333;"><?= $val ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Chart 7 hari -->
    <div class="panel">
      <div class="panel-hd"><h5><i class="fa fa-chart-bar text-primary"></i> &nbsp;7 Hari Terakhir</h5></div>
      <div class="panel-bd">
        <div class="chart-wrap" style="height:80px;">
          <?php foreach ($chart_data as $cd): ?>
          <div class="chart-col" title="<?= $cd['lbl'] ?>: <?= $cd['n'] ?> tiket">
            <?php if ($cd['n']): ?><div class="chart-val"><?= $cd['n'] ?></div><?php endif; ?>
            <div class="chart-bar" style="background:var(--primary);opacity:.7;height:<?= $cd['n'] ? round($cd['n']/$chart_max*60)+10 : 4 ?>px;width:100%;"></div>
            <div class="chart-lbl"><?= $cd['lbl'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tiket Sedang Diproses -->
<?php if (!empty($diproses)): ?>
<div class="panel">
  <div class="panel-hd">
    <h5><i class="fa fa-cogs text-primary"></i> &nbsp;Sedang Diproses</h5>
    <a href="<?= APP_URL ?>/pages/semua_tiket.php?status=diproses" class="btn btn-default btn-sm">Lihat Semua</a>
  </div>
  <div class="panel-bd np tbl-wrap">
    <table>
      <thead><tr><th>Tiket</th><th>Judul</th><th>Teknisi</th><th>Prioritas</th><th>Mulai Proses</th><th>Durasi</th><th>SLA</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($diproses as $t):
          $dur = durasiSekarang($t['waktu_submit']);
          $sla = slaStatus($dur, $t['sla_jam']);
        ?>
        <tr>
          <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
          <td style="max-width:160px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($t['judul']) ?></span>
            <small style="color:#aaa;"><?= clean($t['req_nama']) ?></small></td>
          <td><?= $t['tek_nama'] ? '<div class="d-flex ai-c gap6"><div class="av av-xs av-blue">'.getInitials($t['tek_nama']).'</div>'.clean($t['tek_nama']).'</div>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= badgePrioritas($t['prioritas']) ?></td>
          <td style="font-size:11px;color:#aaa;white-space:nowrap;"><?= formatTanggal($t['waktu_diproses'],true) ?></td>
          <td style="font-weight:700;color:<?= $sla&&$sla['status']==='breach'?'var(--red)':'#333' ?>;"><?= formatDurasi($dur) ?></td>
          <td>
            <?php if ($sla): ?>
            <span class="sla-badge" style="background:<?= $sla['status']==='aman'?'#d1fae5':($sla['status']==='warning'?'#fef3c7':'#fee2e2') ?>;color:<?= $sla['status']==='aman'?'#065f46':($sla['status']==='warning'?'#92400e':'#991b1b') ?>;">
              <?= $sla['persen'] ?>%
            </span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; // end role check ?>
</div><!-- /.content -->

<?php include 'includes/footer.php'; ?>
