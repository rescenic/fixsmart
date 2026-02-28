<?php
session_start();
require_once 'config.php';
requireLogin();
$page_title  = 'Dashboard';
$active_menu = 'dashboard';

if (hasRole('user')) {
    // ── DASHBOARD USER ──
    $uid = $_SESSION['user_id'];
    $my_stats = [];
    $st = $pdo->prepare("SELECT status, COUNT(*) as n FROM tiket WHERE user_id=? GROUP BY status");
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $r) $my_stats[$r['status']] = $r['n'];
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
    // ── DASHBOARD IT (ADMIN / TEKNISI) ──

    // --- Tiket stats ---
    $stats = [];
    $st = $pdo->query("SELECT status, COUNT(*) as n FROM tiket GROUP BY status");
    foreach ($st->fetchAll() as $r) $stats[$r['status']] = $r['n'];
    $stats['total'] = array_sum($stats);

    $antrian = $pdo->query("
        SELECT t.*, k.nama as kat_nama, u.nama as req_nama, u.divisi
        FROM tiket t
        LEFT JOIN kategori k ON k.id=t.kategori_id
        LEFT JOIN users u ON u.id=t.user_id
        WHERE t.status='menunggu'
        ORDER BY CASE t.prioritas WHEN 'Tinggi' THEN 1 WHEN 'Sedang' THEN 2 ELSE 3 END, t.waktu_submit ASC
        LIMIT 8
    ")->fetchAll();

    $diproses = $pdo->query("
        SELECT t.*, k.nama as kat_nama, u.nama as req_nama, tek.nama as tek_nama, k.sla_jam
        FROM tiket t
        LEFT JOIN kategori k ON k.id=t.kategori_id
        LEFT JOIN users u ON u.id=t.user_id
        LEFT JOIN users tek ON tek.id=t.teknisi_id
        WHERE t.status='diproses'
        ORDER BY t.waktu_diproses ASC LIMIT 8
    ")->fetchAll();

    $sla_summary = $pdo->query("
        SELECT COUNT(*) as total,
          SUM(CASE WHEN status IN ('selesai','ditolak','tidak_bisa') THEN 1 ELSE 0 END) as selesai,
          SUM(CASE WHEN status='selesai' AND durasi_selesai_menit <= (SELECT sla_jam*60 FROM kategori WHERE id=kategori_id) THEN 1 ELSE 0 END) as sla_met,
          AVG(durasi_respon_menit) as avg_respon,
          AVG(durasi_selesai_menit) as avg_selesai
        FROM tiket
        WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    ")->fetch();

    $chart_data = [];
    for ($i=6; $i>=0; $i--) {
        $d   = date('Y-m-d', strtotime("-$i days"));
        $lbl = date('d/m', strtotime("-$i days"));
        $st  = $pdo->prepare("SELECT COUNT(*) FROM tiket WHERE DATE(created_at)=?");
        $st->execute([$d]);
        $chart_data[] = ['lbl'=>$lbl,'n'=>(int)$st->fetchColumn()];
    }
    $chart_max = max(array_column($chart_data,'n')) ?: 1;

    // --- Aset IT ---
    $aset_stats = ['total'=>0,'baik'=>0,'rusak'=>0,'perbaikan'=>0];
    try {
        $ar = $pdo->query("SELECT kondisi, COUNT(*) n FROM aset_it GROUP BY kondisi")->fetchAll();
        foreach ($ar as $r) {
            $aset_stats['total'] += $r['n'];
            $k = strtolower($r['kondisi']);
            if ($k==='baik')                     $aset_stats['baik']      += $r['n'];
            elseif ($k==='rusak')                $aset_stats['rusak']     += $r['n'];
            elseif (str_contains($k,'perbaikan'))$aset_stats['perbaikan'] += $r['n'];
        }
    } catch(Exception $e) {}

    // --- Maintenance urgent (≤7 hari) ---
    $mnt_urgent = [];
    try {
        $mnt_urgent = $pdo->query("
            SELECT a.nama_aset, a.no_inventaris, m.tgl_maintenance_berikut,
                   DATEDIFF(m.tgl_maintenance_berikut, CURDATE()) as sisa_hari
            FROM maintenance_it m
            JOIN aset_it a ON a.id = m.aset_id
            WHERE m.id IN (SELECT MAX(id) FROM maintenance_it GROUP BY aset_id)
              AND m.tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY m.tgl_maintenance_berikut ASC
            LIMIT 5
        ")->fetchAll();
    } catch(Exception $e) {}

    // --- Monitor Koneksi ---
    $koneksi_stats = ['total'=>0,'online'=>0,'offline'=>0];
    $koneksi_offline_list = [];
    try {
        $monitors = $pdo->query("SELECT id, nama, ip_address FROM koneksi_monitor WHERE aktif=1")->fetchAll();
        $koneksi_stats['total'] = count($monitors);
        foreach ($monitors as $m) {
            $last = $pdo->prepare("SELECT status FROM koneksi_log WHERE monitor_id=? ORDER BY cek_at DESC LIMIT 1");
            $last->execute([$m['id']]);
            $s = $last->fetchColumn();
            if ($s === 'online')       $koneksi_stats['online']++;
            elseif ($s === 'offline') {
                $koneksi_stats['offline']++;
                $koneksi_offline_list[] = $m;
            }
        }
    } catch(Exception $e) {}

    // --- Server Room (data terbaru) ---
    $sr_latest = null;
    $sr_alert  = false;
    try {
        $sr_latest = $pdo->query("SELECT * FROM server_room_log ORDER BY tanggal DESC, waktu DESC LIMIT 1")->fetch();
        if ($sr_latest) {
            $sr_alert = $sr_latest['ada_alarm'] || $sr_latest['ada_banjir'] || $sr_latest['ada_asap']
                      || $sr_latest['status_overall'] === 'Kritis';
        }
    } catch(Exception $e) {}

    // --- Quick stats: tiket hari ini ---
    $tiket_hari_ini = (int)$pdo->query("SELECT COUNT(*) FROM tiket WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $tiket_bulan    = (int)$pdo->query("SELECT COUNT(*) FROM tiket WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
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
<!-- ════════════════════════════════════════════════
     TAMPILAN USER
════════════════════════════════════════════════ -->
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

<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
  <?php foreach (['total'=>['Total Tiket','fa-ticket-alt','c-total'],'menunggu'=>['Menunggu','fa-clock','c-menunggu'],'diproses'=>['Diproses','fa-cogs','c-diproses'],'selesai'=>['Selesai','fa-check-circle','c-selesai'],'ditolak'=>['Ditolak/Tdk Bisa','fa-ban','c-ditolak']] as $key=>[$lbl,$ic,$cls]):
    $val = $key==='ditolak' ? ($my_stats['ditolak']??0)+($my_stats['tidak_bisa']??0) : ($my_stats[$key]??0); ?>
  <div class="stat-card <?= $cls ?>">
    <i class="fa <?= $ic ?> sc-icon"></i>
    <div><div class="sc-num"><?= $val ?></div><div class="sc-lbl"><?= $lbl ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

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
        <tr><td colspan="8" class="td-empty"><i class="fa fa-inbox"></i> Belum ada tiket. <a href="<?= APP_URL ?>/pages/buat_tiket.php">Buat tiket pertama Anda</a></td></tr>
        <?php else: foreach ($my_tiket as $t): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
          <td style="max-width:170px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($t['judul']) ?></span></td>
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
<!-- ════════════════════════════════════════════════
     TAMPILAN IT (ADMIN / TEKNISI)
════════════════════════════════════════════════ -->

<!-- ── BARIS 1: Alert kritis (jika ada) ── -->
<?php
$ada_alert = ($stats['menunggu']??0) > 5
    || !empty($mnt_urgent)
    || $koneksi_stats['offline'] > 0
    || $sr_alert;
if ($ada_alert):
?>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
  <?php if (($stats['menunggu']??0) > 5): ?>
  <a href="<?= APP_URL ?>/pages/antrian.php" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:#fef3c7;border:1px solid #fde68a;border-radius:7px;text-decoration:none;color:#92400e;font-size:12px;font-weight:600;">
    <i class="fa fa-triangle-exclamation"></i> <?= $stats['menunggu'] ?> tiket menunggu penanganan
  </a>
  <?php endif; ?>
  <?php if ($koneksi_stats['offline'] > 0): ?>
  <a href="<?= APP_URL ?>/pages/cek_koneksi.php" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:#fee2e2;border:1px solid #fecaca;border-radius:7px;text-decoration:none;color:#991b1b;font-size:12px;font-weight:600;">
    <i class="fa fa-wifi-slash"></i> <?= $koneksi_stats['offline'] ?> perangkat jaringan offline
  </a>
  <?php endif; ?>
  <?php if ($sr_alert && $sr_latest): ?>
  <a href="<?= APP_URL ?>/pages/server_room.php" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:#fee2e2;border:1px solid #fecaca;border-radius:7px;text-decoration:none;color:#991b1b;font-size:12px;font-weight:600;">
    <i class="fa fa-server"></i> Ruangan server — status <?= $sr_latest['status_overall'] ?>
  </a>
  <?php endif; ?>
  <?php if (!empty($mnt_urgent)): ?>
  <a href="<?= APP_URL ?>/pages/maintenance_it.php" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:#fef9c3;border:1px solid #fde047;border-radius:7px;text-decoration:none;color:#713f12;font-size:12px;font-weight:600;">
    <i class="fa fa-screwdriver-wrench"></i> <?= count($mnt_urgent) ?> aset perlu maintenance dalam 7 hari
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── BARIS 2: Stat cards tiket ── -->
<div class="stats-grid" style="margin-bottom:14px;">
  <?php foreach (['total'=>['Total Tiket','fa-ticket-alt','c-total'],'menunggu'=>['Menunggu','fa-clock','c-menunggu'],'diproses'=>['Diproses','fa-cogs','c-diproses'],'selesai'=>['Selesai','fa-check-circle','c-selesai'],'ditolak'=>['Ditolak/Tdk Bisa','fa-ban','c-ditolak']] as $key=>[$lbl,$ic,$cls]):
    $val = $key==='ditolak' ? ($stats['ditolak']??0)+($stats['tidak_bisa']??0) : ($stats[$key]??0); ?>
  <div class="stat-card <?= $cls ?>">
    <i class="fa <?= $ic ?> sc-icon"></i>
    <div><div class="sc-num"><?= $val ?></div><div class="sc-lbl"><?= $lbl ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── BARIS 3: Tiket hari ini / bulan + mini infra cards ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;">

  <!-- Tiket hari ini -->
  <div style="background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
    <div style="width:38px;height:38px;background:#eff6ff;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-calendar-day" style="color:#3b82f6;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;"><?= $tiket_hari_ini ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Tiket Hari Ini</div>
    </div>
  </div>

  <!-- Tiket bulan ini -->
  <div style="background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
    <div style="width:38px;height:38px;background:#f5f3ff;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-calendar-alt" style="color:#7c3aed;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;"><?= $tiket_bulan ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Tiket Bulan Ini</div>
    </div>
  </div>

  <!-- Aset IT total -->
  <a href="<?= APP_URL ?>/pages/aset_it.php" style="text-decoration:none;background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.08)';" onmouseout="this.style.boxShadow='none';">
    <div style="width:38px;height:38px;background:#ecfdf5;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-laptop" style="color:#10b981;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;"><?= $aset_stats['total'] ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Total Aset IT</div>
      <div style="font-size:10px;margin-top:2px;">
        <span style="color:#16a34a;"><?= $aset_stats['baik'] ?> Baik</span>
        <?php if ($aset_stats['rusak']): ?>
        &nbsp;<span style="color:#dc2626;"><?= $aset_stats['rusak'] ?> Rusak</span>
        <?php endif; ?>
      </div>
    </div>
  </a>

  <!-- Monitor Koneksi -->
  <a href="<?= APP_URL ?>/pages/cek_koneksi.php" style="text-decoration:none;background:#fff;border:1px solid <?= $koneksi_stats['offline']>0?'#fecaca':'#e8ecf0' ?>;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.08)';" onmouseout="this.style.boxShadow='none';">
    <div style="width:38px;height:38px;background:<?= $koneksi_stats['offline']>0?'#fff1f2':'#f0fdf4' ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-wifi" style="color:<?= $koneksi_stats['offline']>0?'#f43f5e':'#22c55e' ?>;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;"><?= $koneksi_stats['online'] ?><span style="font-size:12px;color:#94a3b8;font-weight:400;">/<?= $koneksi_stats['total'] ?></span></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Koneksi Online</div>
      <?php if ($koneksi_stats['offline']>0): ?>
      <div style="font-size:10px;color:#f43f5e;font-weight:600;margin-top:2px;"><?= $koneksi_stats['offline'] ?> offline</div>
      <?php endif; ?>
    </div>
  </a>

  <!-- Server Room -->
  <a href="<?= APP_URL ?>/pages/server_room.php" style="text-decoration:none;background:#fff;border:1px solid <?= $sr_alert?'#fecaca':'#e8ecf0' ?>;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.08)';" onmouseout="this.style.boxShadow='none';">
    <div style="width:38px;height:38px;background:<?= $sr_alert?'#fff1f2':'#f8fafc' ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-server" style="color:<?= $sr_alert?'#f43f5e':'#64748b' ?>;font-size:15px;"></i>
    </div>
    <div>
      <?php if ($sr_latest): ?>
      <div style="font-size:15px;font-weight:800;color:<?= $sr_latest['status_overall']==='Normal'?'#16a34a':($sr_latest['status_overall']==='Perhatian'?'#d97706':'#dc2626') ?>;line-height:1;"><?= $sr_latest['status_overall'] ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Ruangan Server</div>
      <div style="font-size:10px;color:#94a3b8;margin-top:1px;"><?= $sr_latest['suhu_in'] !== null ? $sr_latest['suhu_in'].'°C' : '' ?><?= $sr_latest['kelembaban'] !== null ? ' · '.$sr_latest['kelembaban'].'%RH' : '' ?></div>
      <?php else: ?>
      <div style="font-size:13px;font-weight:600;color:#94a3b8;line-height:1;">Belum ada data</div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Ruangan Server</div>
      <?php endif; ?>
    </div>
  </a>

  <!-- Maintenance urgent -->
  <a href="<?= APP_URL ?>/pages/maintenance_it.php" style="text-decoration:none;background:#fff;border:1px solid <?= !empty($mnt_urgent)?'#fde68a':'#e8ecf0' ?>;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.08)';" onmouseout="this.style.boxShadow='none';">
    <div style="width:38px;height:38px;background:<?= !empty($mnt_urgent)?'#fffbeb':'#f8fafc' ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-screwdriver-wrench" style="color:<?= !empty($mnt_urgent)?'#d97706':'#94a3b8' ?>;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:<?= !empty($mnt_urgent)?'#d97706':'#1e293b' ?>;line-height:1;"><?= count($mnt_urgent) ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Jadwal Maintenance</div>
      <div style="font-size:10px;color:#94a3b8;margin-top:1px;">dalam 7 hari ke depan</div>
    </div>
  </a>

</div>

<!-- ── BARIS 4: Antrian + SLA + Chart ── -->
<div class="g3">
  <!-- Antrian -->
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
            <td><div class="d-flex ai-c gap6"><div class="av av-xs"><?= getInitials($t['req_nama']) ?></div><div><?= clean($t['req_nama']) ?><br><span class="text-muted text-sm"><?= clean($t['divisi']??'') ?></span></div></div></td>
            <td style="white-space:nowrap;"><span style="font-size:11px;color:#aaa;"><?= formatTanggal($t['waktu_submit'],true) ?></span>
              <?php if ($durasi > 60): ?><br><span style="font-size:10px;color:var(--red);font-weight:700;"><i class="fa fa-clock"></i> <?= formatDurasi($durasi) ?></span><?php endif; ?>
            </td>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fa fa-wrench"></i> Proses</a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Kolom kanan: SLA + Chart -->
  <div>
    <!-- SLA Bulan Ini -->
    <div class="panel">
      <div class="panel-hd">
        <h5><i class="fa fa-chart-line text-primary"></i> &nbsp;SLA Bulan Ini</h5>
        <a href="<?= APP_URL ?>/pages/sla.php" class="btn btn-default btn-sm">Detail</a>
      </div>
      <div class="panel-bd">
        <?php
        $sla_pct = ($sla_summary['selesai']??0) > 0 ? round(($sla_summary['sla_met']??0)/($sla_summary['selesai']??1)*100) : 0;
        $sla_cls = $sla_pct >= 90 ? 'pg-green' : ($sla_pct >= 70 ? 'pg-orange' : 'pg-red');
        ?>
        <div style="text-align:center;margin-bottom:12px;">
          <div style="font-size:32px;font-weight:700;color:<?= $sla_pct>=90?'var(--green)':($sla_pct>=70?'var(--orange)':'var(--red)') ?>;"><?= $sla_pct ?>%</div>
          <div style="font-size:11px;color:#aaa;">Tiket selesai dalam target SLA</div>
        </div>
        <div class="progress" style="height:8px;margin-bottom:14px;">
          <div class="progress-fill <?= $sla_cls ?>" style="width:<?= $sla_pct ?>%"></div>
        </div>
        <?php foreach ([['Total Tiket',$sla_summary['total']??0],['Sudah Selesai',$sla_summary['selesai']??0],['Dalam Target SLA',$sla_summary['sla_met']??0],['Avg. Respon',formatDurasi($sla_summary['avg_respon']??null)],['Avg. Penyelesaian',formatDurasi($sla_summary['avg_selesai']??null)]] as [$lbl,$val]): ?>
        <div class="d-flex ai-c" style="justify-content:space-between;margin-bottom:7px;font-size:12px;">
          <span style="color:#888;"><?= $lbl ?></span><strong style="color:#333;"><?= $val ?></strong>
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

<!-- ── BARIS 5: Diproses + Maintenance urgent + Server Room ── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-top:0;">

  <!-- Tiket Sedang Diproses -->
  <?php if (!empty($diproses)): ?>
  <div class="panel" style="margin-bottom:0;">
    <div class="panel-hd">
      <h5><i class="fa fa-cogs text-primary"></i> &nbsp;Sedang Diproses</h5>
      <a href="<?= APP_URL ?>/pages/semua_tiket.php?status=diproses" class="btn btn-default btn-sm">Lihat Semua</a>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead><tr><th>Tiket</th><th>Judul</th><th>Teknisi</th><th>Prioritas</th><th>Durasi</th><th>SLA</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($diproses as $t):
            $dur = durasiSekarang($t['waktu_submit']);
            $sla = slaStatus($dur, $t['sla_jam']);
          ?>
          <tr>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
            <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($t['judul']) ?></span><small style="color:#aaa;"><?= clean($t['req_nama']) ?></small></td>
            <td><?= $t['tek_nama'] ? '<div class="d-flex ai-c gap6"><div class="av av-xs av-blue">'.getInitials($t['tek_nama']).'</div>'.clean($t['tek_nama']).'</div>' : '<span class="text-muted">—</span>' ?></td>
            <td><?= badgePrioritas($t['prioritas']) ?></td>
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
  <?php else: ?>
  <div></div>
  <?php endif; ?>

  <!-- Kolom kanan: Maintenance + Server Room ringkasan -->
  <div style="display:flex;flex-direction:column;gap:14px;">

    <!-- Jadwal Maintenance Urgent -->
    <div class="panel" style="margin-bottom:0;">
      <div class="panel-hd">
        <h5><i class="fa fa-screwdriver-wrench text-primary"></i> &nbsp;Maintenance Mendekat</h5>
        <a href="<?= APP_URL ?>/pages/maintenance_it.php" class="btn btn-default btn-sm">Lihat</a>
      </div>
      <div class="panel-bd">
        <?php if (empty($mnt_urgent)): ?>
        <div style="text-align:center;color:#94a3b8;font-size:12px;padding:10px 0;">
          <i class="fa fa-circle-check" style="color:#22c55e;font-size:22px;display:block;margin-bottom:6px;"></i>
          Tidak ada jadwal mendekat
        </div>
        <?php else: foreach ($mnt_urgent as $m):
          $sisa  = (int)$m['sisa_hari'];
          $color = $sisa <= 0 ? '#dc2626' : ($sisa <= 2 ? '#d97706' : '#2563eb');
          $bg    = $sisa <= 0 ? '#fee2e2' : ($sisa <= 2 ? '#fef9c3' : '#eff6ff');
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #f8fafc;">
          <div style="width:34px;height:34px;background:<?= $bg ?>;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:800;color:<?= $color ?>;">
            <?= $sisa <= 0 ? 'NOW' : $sisa.'h' ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($m['nama_aset']) ?></div>
            <div style="font-size:10.5px;color:#94a3b8;"><?= htmlspecialchars($m['no_inventaris']??'') ?> &nbsp;&bull;&nbsp; <?= date('d M Y',strtotime($m['tgl_maintenance_berikut'])) ?></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Server Room ringkasan -->
    <div class="panel" style="margin-bottom:0;">
      <div class="panel-hd">
        <h5><i class="fa fa-server text-primary"></i> &nbsp;Ruangan Server</h5>
        <a href="<?= APP_URL ?>/pages/server_room.php" class="btn btn-default btn-sm">Detail</a>
      </div>
      <div class="panel-bd">
        <?php if (!$sr_latest): ?>
        <div style="text-align:center;color:#94a3b8;font-size:12px;padding:10px 0;">
          <i class="fa fa-server" style="font-size:22px;display:block;margin-bottom:6px;color:#cbd5e1;"></i>
          Belum ada data pemantauan
        </div>
        <?php else:
          $sr_st_color = $sr_latest['status_overall']==='Normal'?'#16a34a':($sr_latest['status_overall']==='Perhatian'?'#d97706':'#dc2626');
          $sr_st_bg    = $sr_latest['status_overall']==='Normal'?'#dcfce7':($sr_latest['status_overall']==='Perhatian'?'#fef9c3':'#fee2e2');
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
          <span style="font-size:11px;color:#64748b;">Update terakhir: <?= date('d M, H:i',strtotime($sr_latest['tanggal'].' '.$sr_latest['waktu'])) ?></span>
          <span style="padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;background:<?= $sr_st_bg ?>;color:<?= $sr_st_color ?>;"><?= $sr_latest['status_overall'] ?></span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <?php
          $sr_items = [
            ['Suhu Dalam',  $sr_latest['suhu_in']!==null    ? $sr_latest['suhu_in'].'°C' : '—',  $sr_latest['suhu_in']!==null&&(float)$sr_latest['suhu_in']>27?'#dc2626':'#16a34a'],
            ['Kelembaban',  $sr_latest['kelembaban']!==null ? $sr_latest['kelembaban'].'%' : '—', $sr_latest['kelembaban']!==null&&(float)$sr_latest['kelembaban']>65?'#d97706':'#16a34a'],
            ['Tegangan PLN',$sr_latest['tegangan_pln']!==null? $sr_latest['tegangan_pln'].'V' : '—', '#2563eb'],
            ['Baterai UPS', $sr_latest['baterai_ups']!==null ? $sr_latest['baterai_ups'].'%' : '—', (float)($sr_latest['baterai_ups']??100)<30?'#dc2626':'#16a34a'],
          ];
          foreach ($sr_items as [$lbl,$val,$vc]): ?>
          <div style="background:#f8fafc;border-radius:6px;padding:8px 10px;">
            <div style="font-size:9.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;"><?= $lbl ?></div>
            <div style="font-size:15px;font-weight:800;color:<?= $vc ?>;"><?= $val ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($sr_latest['ada_alarm']||$sr_latest['ada_banjir']||$sr_latest['ada_asap']): ?>
        <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
          <?php
          if ($sr_latest['ada_alarm'])  echo '<span style="background:#fee2e2;color:#b91c1c;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;"><i class="fa fa-bell"></i> Alarm</span>';
          if ($sr_latest['ada_banjir']) echo '<span style="background:#fff1f2;color:#e11d48;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;"><i class="fa fa-water"></i> Banjir</span>';
          if ($sr_latest['ada_asap'])   echo '<span style="background:#fef9c3;color:#a16207;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;"><i class="fa fa-smog"></i> Asap</span>';
          ?>
        </div>
        <?php endif; ?>
        <!-- Kondisi AC -->
        <div style="margin-top:8px;border-top:1px solid #f1f5f9;padding-top:8px;display:flex;gap:6px;flex-wrap:wrap;font-size:11px;">
          <?php foreach([['AC 1',$sr_latest['kondisi_ac1']??'—'],['AC 2',$sr_latest['kondisi_ac2']??'—'],['Pintu',$sr_latest['kondisi_pintu']??'—'],['CCTV',$sr_latest['kondisi_cctv']??'—']] as [$lbl,$val]):
            $ok = in_array($val,['Normal','Baik','Terkunci','Aktif']);
          ?>
          <span style="background:<?= $ok?'#f0fdf4':'#fef2f2' ?>;color:<?= $ok?'#15803d':'#b91c1c' ?>;padding:2px 7px;border-radius:4px;font-weight:600;"><?= $lbl ?>: <?= $val ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- ── BARIS 6: Koneksi Offline List (jika ada) ── -->
<?php if (!empty($koneksi_offline_list)): ?>
<div class="panel" style="border-left:3px solid #ef4444;margin-top:14px;">
  <div class="panel-hd">
    <h5><i class="fa fa-wifi" style="color:#ef4444;"></i> &nbsp;Perangkat Jaringan Offline <span style="color:#ef4444;">(<?= count($koneksi_offline_list) ?>)</span></h5>
    <a href="<?= APP_URL ?>/pages/cek_koneksi.php" class="btn btn-default btn-sm">Monitor Koneksi</a>
  </div>
  <div class="panel-bd" style="display:flex;flex-wrap:wrap;gap:8px;">
    <?php foreach ($koneksi_offline_list as $ko): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:7px 12px;background:#fff1f2;border:1px solid #fecaca;border-radius:7px;min-width:180px;">
      <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;flex-shrink:0;animation:pulse 1.2s infinite;"></span>
      <div>
        <div style="font-size:12px;font-weight:700;color:#1e293b;"><?= htmlspecialchars($ko['nama']) ?></div>
        <div style="font-size:10.5px;color:#94a3b8;"><?= htmlspecialchars($ko['ip_address']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<style>
@keyframes pulse {
  0%,100%{opacity:1;transform:scale(1);}
  50%{opacity:.5;transform:scale(1.3);}
}
</style>
<?php endif; ?>

<?php endif; // end role check ?>
</div>

<?php include 'includes/footer.php'; ?>