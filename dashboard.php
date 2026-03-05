<?php
session_start();
require_once 'config.php';
requireLogin();
$page_title  = 'Dashboard';
$active_menu = 'dashboard';

$_cur_role = $_SESSION['user_role'] ?? 'user';

// ══════════════════════════════════════════════════════════════════════════════
// DASHBOARD: USER BIASA
// ══════════════════════════════════════════════════════════════════════════════
if ($_cur_role === 'user') {
    $uid = $_SESSION['user_id'];

    // Stats tiket IT milik user
    $my_stats = ['total'=>0,'menunggu'=>0,'diproses'=>0,'selesai'=>0,'ditolak'=>0,'tidak_bisa'=>0];
    $st = $pdo->prepare("SELECT status, COUNT(*) as n FROM tiket WHERE user_id=? GROUP BY status");
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $r) $my_stats[$r['status']] = (int)$r['n'];
    $my_stats['total'] = array_sum($my_stats);

    // Stats tiket IPSRS milik user
    $my_stats_ipsrs = ['total'=>0,'menunggu'=>0,'diproses'=>0,'selesai'=>0,'ditolak'=>0,'tidak_bisa'=>0];
    try {
        $st2 = $pdo->prepare("SELECT status, COUNT(*) as n FROM tiket_ipsrs WHERE user_id=? GROUP BY status");
        $st2->execute([$uid]);
        foreach ($st2->fetchAll() as $r) $my_stats_ipsrs[$r['status']] = (int)$r['n'];
        $my_stats_ipsrs['total'] = array_sum($my_stats_ipsrs);
    } catch (Exception $e) {}

    // Tiket IT terbaru
    $my_tiket = $pdo->prepare("
        SELECT t.*, k.nama AS kat_nama, tek.nama AS tek_nama
        FROM tiket t
        LEFT JOIN kategori k ON k.id = t.kategori_id
        LEFT JOIN users tek ON tek.id = t.teknisi_id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $my_tiket->execute([$uid]);
    $my_tiket = $my_tiket->fetchAll();

    // Tiket IPSRS terbaru
    $my_tiket_ipsrs = [];
    try {
        $st3 = $pdo->prepare("
            SELECT t.*, k.nama AS kat_nama, tek.nama AS tek_nama
            FROM tiket_ipsrs t
            LEFT JOIN kategori_ipsrs k ON k.id = t.kategori_id
            LEFT JOIN users tek ON tek.id = t.teknisi_id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC LIMIT 5
        ");
        $st3->execute([$uid]);
        $my_tiket_ipsrs = $st3->fetchAll();
    } catch (Exception $e) {}
}

// ══════════════════════════════════════════════════════════════════════════════
// DASHBOARD: TEKNISI IPSRS
// ══════════════════════════════════════════════════════════════════════════════
elseif ($_cur_role === 'teknisi_ipsrs') {
    $uid = $_SESSION['user_id'];

    // Stats tiket IPSRS keseluruhan
    $stats_ipsrs = [];
    try {
        $st = $pdo->query("SELECT status, COUNT(*) as n FROM tiket_ipsrs GROUP BY status");
        foreach ($st->fetchAll() as $r) $stats_ipsrs[$r['status']] = (int)$r['n'];
        $stats_ipsrs['total'] = array_sum($stats_ipsrs);
    } catch (Exception $e) { $stats_ipsrs['total'] = 0; }

    // Antrian IPSRS menunggu
    $antrian_ipsrs = [];
    try {
        $antrian_ipsrs = $pdo->query("
            SELECT t.*, k.nama AS kat_nama, u.nama AS req_nama, u.divisi
            FROM tiket_ipsrs t
            LEFT JOIN kategori_ipsrs k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.user_id
            WHERE t.status = 'menunggu'
            ORDER BY CASE t.prioritas WHEN 'Tinggi' THEN 1 WHEN 'Sedang' THEN 2 ELSE 3 END,
                     t.waktu_submit ASC
            LIMIT 8
        ")->fetchAll();
    } catch (Exception $e) {}

    // SLA IPSRS bulan ini
    $sla_ipsrs = ['total'=>0,'selesai'=>0,'sla_met'=>0,'avg_respon'=>0,'avg_selesai'=>0];
    try {
        $sla_ipsrs = $pdo->query("
            SELECT COUNT(*) AS total,
                SUM(status IN ('selesai','ditolak','tidak_bisa')) AS selesai,
                SUM(status='selesai' AND durasi_selesai_menit <= (
                    SELECT k.sla_jam*60 FROM kategori_ipsrs k WHERE k.id=kategori_id
                )) AS sla_met,
                AVG(durasi_respon_menit)  AS avg_respon,
                AVG(durasi_selesai_menit) AS avg_selesai
            FROM tiket_ipsrs
            WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
        ")->fetch();
    } catch (Exception $e) {}

    // Tiket saya (yang ditangani)
    $my_handle = [];
    try {
        $st = $pdo->prepare("
            SELECT t.*, k.nama AS kat_nama, u.nama AS req_nama
            FROM tiket_ipsrs t
            LEFT JOIN kategori_ipsrs k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.user_id
            WHERE t.teknisi_id = ? AND t.status = 'diproses'
            ORDER BY t.waktu_submit ASC LIMIT 5
        ");
        $st->execute([$uid]);
        $my_handle = $st->fetchAll();
    } catch (Exception $e) {}

    // Maintenance IPSRS urgent
    $mnt_ipsrs_urgent = [];
    try {
        $mnt_ipsrs_urgent = $pdo->query("
            SELECT a.nama_aset, a.no_inventaris, m.tgl_maintenance_berikut,
                   DATEDIFF(m.tgl_maintenance_berikut, CURDATE()) AS sisa_hari
            FROM maintenance_ipsrs m
            JOIN aset_ipsrs a ON a.id = m.aset_id
            WHERE m.id IN (SELECT MAX(id) FROM maintenance_ipsrs GROUP BY aset_id)
              AND m.tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY m.tgl_maintenance_berikut ASC LIMIT 5
        ")->fetchAll();
    } catch (Exception $e) {}

    // Chart 7 hari IPSRS — 1 query
    $chart_data_ipsrs = [];
    try {
        $dates = [];
        for ($i = 6; $i >= 0; $i--) $dates[] = date('Y-m-d', strtotime("-$i days"));
        $st = $pdo->prepare("
            SELECT DATE(created_at) AS tgl, COUNT(*) AS n
            FROM tiket_ipsrs
            WHERE DATE(created_at) >= ?
            GROUP BY DATE(created_at)
        ");
        $st->execute([$dates[0]]);
        $map = [];
        foreach ($st->fetchAll() as $r) $map[$r['tgl']] = (int)$r['n'];
        foreach ($dates as $d) {
            $chart_data_ipsrs[] = ['lbl' => date('d/m', strtotime($d)), 'n' => $map[$d] ?? 0];
        }
    } catch (Exception $e) {
        for ($i = 6; $i >= 0; $i--)
            $chart_data_ipsrs[] = ['lbl' => date('d/m', strtotime("-$i days")), 'n' => 0];
    }
    $chart_max_ipsrs = max(array_column($chart_data_ipsrs, 'n')) ?: 1;
}

// ══════════════════════════════════════════════════════════════════════════════
// DASHBOARD: ADMIN & TEKNISI IT
// ══════════════════════════════════════════════════════════════════════════════
else { // admin atau teknisi

    // --- Tiket IT stats ---
    $stats = [];
    $st = $pdo->query("SELECT status, COUNT(*) as n FROM tiket GROUP BY status");
    foreach ($st->fetchAll() as $r) $stats[$r['status']] = (int)$r['n'];
    $stats['total'] = array_sum($stats);

    // --- Tiket IPSRS stats (untuk admin) ---
    $stats_ipsrs = [];
    if ($_cur_role === 'admin') {
        try {
            $st = $pdo->query("SELECT status, COUNT(*) as n FROM tiket_ipsrs GROUP BY status");
            foreach ($st->fetchAll() as $r) $stats_ipsrs[$r['status']] = (int)$r['n'];
            $stats_ipsrs['total'] = array_sum($stats_ipsrs);
        } catch (Exception $e) { $stats_ipsrs['total'] = 0; }
    }

    // --- Antrian IT ---
    $antrian = $pdo->query("
        SELECT t.*, k.nama AS kat_nama, u.nama AS req_nama, u.divisi
        FROM tiket t
        LEFT JOIN kategori k ON k.id = t.kategori_id
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.status = 'menunggu'
        ORDER BY CASE t.prioritas WHEN 'Tinggi' THEN 1 WHEN 'Sedang' THEN 2 ELSE 3 END,
                 t.waktu_submit ASC
        LIMIT 8
    ")->fetchAll();

    // --- Diproses ---
    $diproses = $pdo->query("
        SELECT t.*, k.nama AS kat_nama, u.nama AS req_nama, tek.nama AS tek_nama, k.sla_jam
        FROM tiket t
        LEFT JOIN kategori k ON k.id = t.kategori_id
        LEFT JOIN users u ON u.id = t.user_id
        LEFT JOIN users tek ON tek.id = t.teknisi_id
        WHERE t.status = 'diproses'
        ORDER BY t.waktu_diproses ASC LIMIT 8
    ")->fetchAll();

    // --- SLA IT bulan ini ---
    $sla_summary = $pdo->query("
        SELECT COUNT(*) AS total,
            SUM(status IN ('selesai','ditolak','tidak_bisa'))  AS selesai,
            SUM(status='selesai' AND durasi_selesai_menit <= (
                SELECT sla_jam*60 FROM kategori WHERE id=kategori_id
            )) AS sla_met,
            AVG(durasi_respon_menit)  AS avg_respon,
            AVG(durasi_selesai_menit) AS avg_selesai
        FROM tiket
        WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    ")->fetch();

    // --- Chart 7 hari IT — 1 query GROUP BY, bukan 7 query loop ---
    $chart_data = [];
    $dates = [];
    for ($i = 6; $i >= 0; $i--) $dates[] = date('Y-m-d', strtotime("-$i days"));
    $st = $pdo->prepare("
        SELECT DATE(created_at) AS tgl, COUNT(*) AS n
        FROM tiket
        WHERE DATE(created_at) >= ?
        GROUP BY DATE(created_at)
    ");
    $st->execute([$dates[0]]);
    $map = [];
    foreach ($st->fetchAll() as $r) $map[$r['tgl']] = (int)$r['n'];
    foreach ($dates as $d) {
        $chart_data[] = ['lbl' => date('d/m', strtotime($d)), 'n' => $map[$d] ?? 0];
    }
    $chart_max = max(array_column($chart_data, 'n')) ?: 1;

    // --- Aset IT ---
    $aset_stats = ['total'=>0,'baik'=>0,'rusak'=>0,'perbaikan'=>0];
    try {
        $ar = $pdo->query("SELECT kondisi, COUNT(*) n FROM aset_it GROUP BY kondisi")->fetchAll();
        foreach ($ar as $r) {
            $aset_stats['total'] += $r['n'];
            $k = strtolower($r['kondisi']);
            if ($k === 'baik')                      $aset_stats['baik']      += $r['n'];
            elseif ($k === 'rusak')                 $aset_stats['rusak']     += $r['n'];
            elseif (str_contains($k, 'perbaikan')) $aset_stats['perbaikan'] += $r['n'];
        }
    } catch (Exception $e) {}

    // --- Maintenance IT urgent ---
    $mnt_urgent = [];
    try {
        $mnt_urgent = $pdo->query("
            SELECT a.nama_aset, a.no_inventaris, m.tgl_maintenance_berikut,
                   DATEDIFF(m.tgl_maintenance_berikut, CURDATE()) AS sisa_hari
            FROM maintenance_it m
            JOIN aset_it a ON a.id = m.aset_id
            WHERE m.id IN (SELECT MAX(id) FROM maintenance_it GROUP BY aset_id)
              AND m.tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY m.tgl_maintenance_berikut ASC LIMIT 5
        ")->fetchAll();
    } catch (Exception $e) {}

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
            if ($s === 'online')        $koneksi_stats['online']++;
            elseif ($s === 'offline') { $koneksi_stats['offline']++; $koneksi_offline_list[] = $m; }
        }
    } catch (Exception $e) {}

    // --- Server Room ---
    $sr_latest = null;
    $sr_alert  = false;
    try {
        $sr_latest = $pdo->query("SELECT * FROM server_room_log ORDER BY tanggal DESC, waktu DESC LIMIT 1")->fetch();
        if ($sr_latest) {
            $sr_alert = $sr_latest['ada_alarm'] || $sr_latest['ada_banjir'] || $sr_latest['ada_asap']
                      || $sr_latest['status_overall'] === 'Kritis';
        }
    } catch (Exception $e) {}

    // --- Quick stats ---
    $tiket_hari_ini = (int)$pdo->query("SELECT COUNT(*) FROM tiket WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $tiket_bulan    = (int)$pdo->query("SELECT COUNT(*) FROM tiket WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

    // --- Top teknisi IT bulan ini (admin only) ---
    $top_teknisi = [];
    if ($_cur_role === 'admin') {
        try {
            $top_teknisi = $pdo->query("
                SELECT u.nama,
                    COUNT(t.id) AS total,
                    SUM(t.status='selesai') AS selesai,
                    AVG(t.durasi_selesai_menit) AS avg_selesai,
                    SUM(t.status='selesai' AND t.durasi_selesai_menit <= (
                        SELECT k.sla_jam*60 FROM kategori k WHERE k.id=t.kategori_id
                    )) AS sla_met
                FROM users u
                LEFT JOIN tiket t ON t.teknisi_id=u.id
                    AND MONTH(t.created_at)=MONTH(NOW()) AND YEAR(t.created_at)=YEAR(NOW())
                WHERE u.role IN ('teknisi','admin') AND u.status='aktif'
                GROUP BY u.id
                HAVING total > 0
                ORDER BY selesai DESC, avg_selesai ASC
                LIMIT 5
            ")->fetchAll();
        } catch (Exception $e) {}
    }

    // --- Top teknisi IPSRS bulan ini (admin only) ---
    $top_teknisi_ipsrs = [];
    if ($_cur_role === 'admin') {
        try {
            $top_teknisi_ipsrs = $pdo->query("
                SELECT u.nama,
                    COUNT(t.id) AS total,
                    SUM(t.status='selesai') AS selesai,
                    AVG(t.durasi_selesai_menit) AS avg_selesai,
                    SUM(t.status='selesai' AND t.durasi_selesai_menit <= (
                        SELECT k.sla_jam*60 FROM kategori_ipsrs k WHERE k.id=t.kategori_id
                    )) AS sla_met
                FROM users u
                LEFT JOIN tiket_ipsrs t ON t.teknisi_id=u.id
                    AND MONTH(t.created_at)=MONTH(NOW()) AND YEAR(t.created_at)=YEAR(NOW())
                WHERE u.role='teknisi_ipsrs' AND u.status='aktif'
                GROUP BY u.id
                HAVING total > 0
                ORDER BY selesai DESC, avg_selesai ASC
                LIMIT 5
            ")->fetchAll();
        } catch (Exception $e) {}
    }
}

include 'includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-home text-primary"></i> &nbsp;Dashboard</h4>
  <div class="breadcrumb"><span class="cur">Beranda</span></div>
</div>

<div class="content">
<?= showFlash() ?>

<?php // ══════════════════════════════════════════════════════════════
      // VIEW: USER
      // ══════════════════════════════════════════════════════════════
if ($_cur_role === 'user'): ?>

<!-- Welcome banner -->
<div class="panel" style="border-left:4px solid var(--primary);margin-bottom:14px;">
  <div class="panel-bd" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <i class="fa fa-headset" style="font-size:32px;color:var(--primary);opacity:.4;"></i>
    <div style="flex:1;">
      <strong style="font-size:13px;">Selamat datang, <?= clean($_SESSION['user_nama']) ?>!</strong>
      <p style="color:#888;font-size:12px;margin-top:2px;">
        Ada masalah dengan perangkat atau fasilitas? Buat tiket dan tim kami akan segera menangani.
      </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="<?= APP_URL ?>/pages/buat_tiket.php" class="btn btn-primary" style="white-space:nowrap;">
        <i class="fa fa-desktop"></i> Tiket IT
      </a>
      <a href="<?= APP_URL ?>/pages/buat_tiket_sarpras.php" class="btn btn-default" style="white-space:nowrap;border-color:#26B99A;color:#0f766e;">
        <i class="fa fa-toolbox"></i> Tiket IPSRS
      </a>
    </div>
  </div>
</div>

<!-- Stats IT + IPSRS -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">

  <!-- Tiket IT -->
  <div class="panel" style="margin-bottom:0;">
    <div class="panel-hd" style="border-bottom:2px solid #eff6ff;">
      <h5><i class="fa fa-desktop" style="color:#3b82f6;"></i> &nbsp;Tiket IT Saya</h5>
      <a href="<?= APP_URL ?>/pages/tiket_saya.php" class="btn btn-default btn-sm">Lihat Semua</a>
    </div>
    <div class="panel-bd" style="padding:12px 16px;">
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;">
        <?php foreach (['total'=>['Total','#1e293b','#f8fafc'],'menunggu'=>['Menunggu','#92400e','#fef3c7'],'diproses'=>['Diproses','#1e40af','#dbeafe'],'selesai'=>['Selesai','#065f46','#d1fae5'],'ditolak'=>['Ditolak','#991b1b','#fee2e2']] as $k=>[$l,$c,$bg]):
          $v = $k==='ditolak' ? ($my_stats['ditolak']??0)+($my_stats['tidak_bisa']??0) : ($my_stats[$k]??0); ?>
        <div style="text-align:center;padding:10px 6px;background:<?= $bg ?>;border-radius:8px;">
          <div style="font-size:20px;font-weight:800;color:<?= $c ?>;line-height:1;"><?= $v ?></div>
          <div style="font-size:9.5px;color:<?= $c ?>;opacity:.8;margin-top:3px;"><?= $l ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Tiket IPSRS -->
  <div class="panel" style="margin-bottom:0;">
    <div class="panel-hd" style="border-bottom:2px solid #f0fdf4;">
      <h5><i class="fa fa-toolbox" style="color:#16a34a;"></i> &nbsp;Tiket IPSRS Saya</h5>
      <a href="<?= APP_URL ?>/pages/tiket_saya_ipsrs.php" class="btn btn-default btn-sm">Lihat Semua</a>
    </div>
    <div class="panel-bd" style="padding:12px 16px;">
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;">
        <?php foreach (['total'=>['Total','#1e293b','#f8fafc'],'menunggu'=>['Menunggu','#92400e','#fef3c7'],'diproses'=>['Diproses','#1e40af','#dbeafe'],'selesai'=>['Selesai','#065f46','#d1fae5'],'ditolak'=>['Ditolak','#991b1b','#fee2e2']] as $k=>[$l,$c,$bg]):
          $v = $k==='ditolak' ? ($my_stats_ipsrs['ditolak']??0)+($my_stats_ipsrs['tidak_bisa']??0) : ($my_stats_ipsrs[$k]??0); ?>
        <div style="text-align:center;padding:10px 6px;background:<?= $bg ?>;border-radius:8px;">
          <div style="font-size:20px;font-weight:800;color:<?= $c ?>;line-height:1;"><?= $v ?></div>
          <div style="font-size:9.5px;color:<?= $c ?>;opacity:.8;margin-top:3px;"><?= $l ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Tiket IT Terbaru -->
<div class="panel">
  <div class="panel-hd">
    <h5><i class="fa fa-desktop text-primary"></i> &nbsp;Tiket IT Terbaru</h5>
    <a href="<?= APP_URL ?>/pages/tiket_saya.php" class="btn btn-default btn-sm">Lihat Semua</a>
  </div>
  <div class="panel-bd np tbl-wrap">
    <table>
      <thead><tr><th>No. Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Teknisi</th><th>Tanggal</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($my_tiket)): ?>
        <tr><td colspan="8" class="td-empty"><i class="fa fa-inbox"></i> Belum ada tiket IT. <a href="<?= APP_URL ?>/pages/buat_tiket.php">Buat sekarang</a></td></tr>
        <?php else: foreach ($my_tiket as $t): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
          <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($t['judul']) ?></span></td>
          <td><?= clean($t['kat_nama'] ?? '—') ?></td>
          <td><?= badgePrioritas($t['prioritas']) ?></td>
          <td><?= badgeStatus($t['status']) ?></td>
          <td><?= $t['tek_nama'] ? '<div class="d-flex ai-c gap6"><div class="av av-xs av-blue">'.getInitials($t['tek_nama']).'</div>'.clean($t['tek_nama']).'</div>' : '<span class="text-muted">—</span>' ?></td>
          <td style="color:#94a3b8;font-size:11px;white-space:nowrap;"><?= formatTanggal($t['waktu_submit']) ?></td>
          <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Tiket IPSRS Terbaru -->
<?php if (!empty($my_tiket_ipsrs)): ?>
<div class="panel">
  <div class="panel-hd">
    <h5><i class="fa fa-toolbox" style="color:#16a34a;"></i> &nbsp;Tiket IPSRS Terbaru</h5>
    <a href="<?= APP_URL ?>/pages/tiket_saya_ipsrs.php" class="btn btn-default btn-sm">Lihat Semua</a>
  </div>
  <div class="panel-bd np tbl-wrap">
    <table>
      <thead><tr><th>No. Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Teknisi</th><th>Tanggal</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($my_tiket_ipsrs as $t): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>" style="color:#16a34a;font-weight:700;"><?= clean($t['nomor']) ?></a></td>
          <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($t['judul']) ?></span></td>
          <td><?= clean($t['kat_nama'] ?? '—') ?></td>
          <td><?= badgePrioritas($t['prioritas']) ?></td>
          <td><?= badgeStatus($t['status']) ?></td>
          <td><?= $t['tek_nama'] ? '<div class="d-flex ai-c gap6"><div class="av av-xs av-green">'.getInitials($t['tek_nama']).'</div>'.clean($t['tek_nama']).'</div>' : '<span class="text-muted">—</span>' ?></td>
          <td style="color:#94a3b8;font-size:11px;white-space:nowrap;"><?= formatTanggal($t['waktu_submit']) ?></td>
          <td><a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════════════
      // VIEW: TEKNISI IPSRS
      // ══════════════════════════════════════════════════════════════
elseif ($_cur_role === 'teknisi_ipsrs'): ?>

<!-- Stat cards IPSRS -->
<div class="stats-grid" style="margin-bottom:14px;">
  <?php foreach (['total'=>['Total Tiket','fa-ticket-alt','c-total'],'menunggu'=>['Menunggu','fa-clock','c-menunggu'],'diproses'=>['Diproses','fa-cogs','c-diproses'],'selesai'=>['Selesai','fa-check-circle','c-selesai'],'ditolak'=>['Ditolak/Tdk Bisa','fa-ban','c-ditolak']] as $k=>[$l,$ic,$cls]):
    $v = $k==='ditolak' ? ($stats_ipsrs['ditolak']??0)+($stats_ipsrs['tidak_bisa']??0) : ($stats_ipsrs[$k]??0); ?>
  <div class="stat-card <?= $cls ?>">
    <i class="fa <?= $ic ?> sc-icon"></i>
    <div><div class="sc-num"><?= $v ?></div><div class="sc-lbl"><?= $l ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Mini info cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;">
  <div style="background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
    <div style="width:38px;height:38px;background:#f0fdf4;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-calendar-day" style="color:#16a34a;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;">
        <?php try { echo (int)$pdo->query("SELECT COUNT(*) FROM tiket_ipsrs WHERE DATE(created_at)=CURDATE()")->fetchColumn(); } catch(Exception $e){ echo 0; } ?>
      </div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Tiket Hari Ini</div>
    </div>
  </div>
  <div style="background:#fff;border:1px solid <?= !empty($mnt_ipsrs_urgent)?'#fde68a':'#e8ecf0' ?>;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
    <div style="width:38px;height:38px;background:<?= !empty($mnt_ipsrs_urgent)?'#fffbeb':'#f8fafc' ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-wrench" style="color:<?= !empty($mnt_ipsrs_urgent)?'#d97706':'#94a3b8' ?>;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;"><?= count($mnt_ipsrs_urgent) ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Maintenance 7 Hari</div>
    </div>
  </div>
  <?php
  $sla_p = (int)($sla_ipsrs['selesai']??0) > 0
           ? round((int)($sla_ipsrs['sla_met']??0) / (int)$sla_ipsrs['selesai'] * 100) : 0;
  $sla_color = $sla_p >= 90 ? '#16a34a' : ($sla_p >= 70 ? '#d97706' : '#dc2626');
  $sla_bg    = $sla_p >= 90 ? '#d1fae5' : ($sla_p >= 70 ? '#fef3c7' : '#fee2e2');
  ?>
  <a href="<?= APP_URL ?>/pages/sla_ipsrs.php" style="text-decoration:none;background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
    <div style="width:38px;height:38px;background:<?= $sla_bg ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-chart-line" style="color:<?= $sla_color ?>;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:<?= $sla_color ?>;line-height:1;"><?= $sla_p ?>%</div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">SLA Bulan Ini</div>
    </div>
  </a>
</div>

<div class="g3">
  <!-- Antrian IPSRS -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-inbox text-primary"></i> &nbsp;Antrian IPSRS <span style="color:#aaa;font-weight:400;">(<?= count($antrian_ipsrs) ?>)</span></h5>
      <a href="<?= APP_URL ?>/pages/antrian_ipsrs.php" class="btn btn-default btn-sm">Lihat Semua</a>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead><tr><th>Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Pemohon</th><th>Masuk</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (empty($antrian_ipsrs)): ?>
          <tr><td colspan="7" class="td-empty"><i class="fa fa-check-circle" style="color:var(--green);"></i> Tidak ada antrian</td></tr>
          <?php else: foreach ($antrian_ipsrs as $t): $dur = durasiSekarang($t['waktu_submit']); ?>
          <tr>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
            <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($t['judul']) ?>"><?= clean($t['judul']) ?></span></td>
            <td style="font-size:11px;"><?= clean($t['kat_nama'] ?? '—') ?></td>
            <td><?= badgePrioritas($t['prioritas']) ?></td>
            <td><div class="d-flex ai-c gap6"><div class="av av-xs"><?= getInitials($t['req_nama']) ?></div><?= clean($t['req_nama']) ?></div></td>
            <td style="white-space:nowrap;font-size:11px;color:#94a3b8;"><?= formatTanggal($t['waktu_submit'], true) ?></td>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fa fa-wrench"></i> Proses</a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Kolom kanan -->
  <div>
    <!-- SLA IPSRS -->
    <div class="panel">
      <div class="panel-hd">
        <h5><i class="fa fa-chart-line text-primary"></i> &nbsp;SLA IPSRS Bulan Ini</h5>
        <a href="<?= APP_URL ?>/pages/sla_ipsrs.php" class="btn btn-default btn-sm">Detail</a>
      </div>
      <div class="panel-bd">
        <div style="text-align:center;margin-bottom:12px;">
          <div style="font-size:32px;font-weight:800;color:<?= $sla_color ?>;"><?= $sla_p ?>%</div>
          <div style="font-size:11px;color:#94a3b8;">Tiket selesai dalam target SLA</div>
        </div>
        <div class="progress" style="height:8px;margin-bottom:14px;">
          <div class="progress-fill <?= $sla_p>=90?'pg-green':($sla_p>=70?'pg-orange':'pg-red') ?>" style="width:<?= $sla_p ?>%"></div>
        </div>
        <?php foreach ([['Total',($sla_ipsrs['total']??0)],['Selesai',($sla_ipsrs['selesai']??0)],['Dalam SLA',($sla_ipsrs['sla_met']??0)],['Avg. Respon',formatDurasi((int)round($sla_ipsrs['avg_respon']??0))],['Avg. Selesai',formatDurasi((int)round($sla_ipsrs['avg_selesai']??0))]] as [$l,$v]): ?>
        <div class="d-flex ai-c" style="justify-content:space-between;margin-bottom:7px;font-size:12px;">
          <span style="color:#888;"><?= $l ?></span><strong style="color:#333;"><?= $v ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- Chart IPSRS -->
    <div class="panel">
      <div class="panel-hd"><h5><i class="fa fa-chart-bar text-primary"></i> &nbsp;7 Hari Terakhir</h5></div>
      <div class="panel-bd">
        <div class="chart-wrap" style="height:80px;">
          <?php foreach ($chart_data_ipsrs as $cd): ?>
          <div class="chart-col" title="<?= $cd['lbl'] ?>: <?= $cd['n'] ?> tiket">
            <?php if ($cd['n']): ?><div class="chart-val"><?= $cd['n'] ?></div><?php endif; ?>
            <div class="chart-bar" style="background:#16a34a;opacity:.7;height:<?= $cd['n'] ? round($cd['n']/$chart_max_ipsrs*60)+10 : 4 ?>px;width:100%;"></div>
            <div class="chart-lbl"><?= $cd['lbl'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Maintenance IPSRS urgent -->
<?php if (!empty($mnt_ipsrs_urgent)): ?>
<div class="panel" style="border-left:3px solid #f59e0b;">
  <div class="panel-hd">
    <h5><i class="fa fa-wrench" style="color:#d97706;"></i> &nbsp;Maintenance IPSRS Mendekat</h5>
    <a href="<?= APP_URL ?>/pages/maintenance_ipsrs.php" class="btn btn-default btn-sm">Lihat</a>
  </div>
  <div class="panel-bd" style="display:flex;flex-wrap:wrap;gap:8px;">
    <?php foreach ($mnt_ipsrs_urgent as $m):
      $sisa = (int)$m['sisa_hari'];
      $c = $sisa<=0?'#dc2626':($sisa<=2?'#d97706':'#2563eb');
      $bg = $sisa<=0?'#fee2e2':($sisa<=2?'#fef9c3':'#eff6ff');
    ?>
    <div style="padding:8px 12px;background:<?= $bg ?>;border-radius:7px;font-size:12px;">
      <strong style="color:<?= $c ?>;"><?= $sisa<=0?'Hari ini':'Dalam '.$sisa.' hari' ?></strong>
      — <?= htmlspecialchars($m['nama_aset']) ?>
      <span style="color:#94a3b8;"> · <?= date('d M', strtotime($m['tgl_maintenance_berikut'])) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>


<?php // ══════════════════════════════════════════════════════════════
      // VIEW: ADMIN & TEKNISI IT
      // ══════════════════════════════════════════════════════════════
else: ?>

<!-- Alert kritis -->
<?php
$ada_alert = ($stats['menunggu']??0) > 5 || !empty($mnt_urgent) || ($koneksi_stats['offline']??0) > 0 || $sr_alert;
if ($ada_alert): ?>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
  <?php if (($stats['menunggu']??0) > 5): ?>
  <a href="<?= APP_URL ?>/pages/antrian.php" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:#fef3c7;border:1px solid #fde68a;border-radius:7px;text-decoration:none;color:#92400e;font-size:12px;font-weight:600;">
    <i class="fa fa-triangle-exclamation"></i> <?= $stats['menunggu'] ?> tiket menunggu penanganan
  </a>
  <?php endif; ?>
  <?php if (($koneksi_stats['offline']??0) > 0): ?>
  <a href="<?= APP_URL ?>/pages/cek_koneksi.php" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:#fee2e2;border:1px solid #fecaca;border-radius:7px;text-decoration:none;color:#991b1b;font-size:12px;font-weight:600;">
    <i class="fa fa-wifi"></i> <?= $koneksi_stats['offline'] ?> perangkat jaringan offline
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

<!-- Stat cards IT -->
<div class="stats-grid" style="margin-bottom:14px;">
  <?php foreach (['total'=>['Total Tiket','fa-ticket-alt','c-total'],'menunggu'=>['Menunggu','fa-clock','c-menunggu'],'diproses'=>['Diproses','fa-cogs','c-diproses'],'selesai'=>['Selesai','fa-check-circle','c-selesai'],'ditolak'=>['Ditolak/Tdk Bisa','fa-ban','c-ditolak']] as $k=>[$l,$ic,$cls]):
    $v = $k==='ditolak' ? ($stats['ditolak']??0)+($stats['tidak_bisa']??0) : ($stats[$k]??0); ?>
  <div class="stat-card <?= $cls ?>">
    <i class="fa <?= $ic ?> sc-icon"></i>
    <div><div class="sc-num"><?= $v ?></div><div class="sc-lbl"><?= $l ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Stat cards IPSRS untuk admin -->
<?php if ($_cur_role === 'admin' && !empty($stats_ipsrs)): ?>
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px;">
  <?php foreach (['total'=>['Total IPSRS','#065f46','#d1fae5'],'menunggu'=>['Menunggu','#92400e','#fef3c7'],'diproses'=>['Diproses','#1e40af','#dbeafe'],'selesai'=>['Selesai','#065f46','#dcfce7'],'ditolak'=>['Ditolak','#991b1b','#fee2e2']] as $k=>[$l,$c,$bg]):
    $v = $k==='ditolak' ? ($stats_ipsrs['ditolak']??0)+($stats_ipsrs['tidak_bisa']??0) : ($stats_ipsrs[$k]??0); ?>
  <div style="background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:10px 14px;display:flex;align-items:center;gap:10px;">
    <div style="width:32px;height:32px;background:<?= $bg ?>;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-toolbox" style="color:<?= $c ?>;font-size:13px;"></i>
    </div>
    <div>
      <div style="font-size:18px;font-weight:800;color:#1e293b;line-height:1;"><?= $v ?></div>
      <div style="font-size:10px;color:#94a3b8;margin-top:1px;"><?= $l ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Mini infra cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;">

  <div style="background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
    <div style="width:38px;height:38px;background:#eff6ff;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-calendar-day" style="color:#3b82f6;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;"><?= $tiket_hari_ini ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Tiket Hari Ini</div>
    </div>
  </div>

  <div style="background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
    <div style="width:38px;height:38px;background:#f5f3ff;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-calendar-alt" style="color:#7c3aed;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;"><?= $tiket_bulan ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Tiket Bulan Ini</div>
    </div>
  </div>

  <a href="<?= APP_URL ?>/pages/aset_it.php" style="text-decoration:none;background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .18s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.08)';" onmouseout="this.style.boxShadow='none';">
    <div style="width:38px;height:38px;background:#ecfdf5;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-laptop" style="color:#10b981;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;"><?= $aset_stats['total'] ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Total Aset IT</div>
      <div style="font-size:10px;margin-top:2px;">
        <span style="color:#16a34a;"><?= $aset_stats['baik'] ?> Baik</span>
        <?php if ($aset_stats['rusak']): ?>&nbsp;<span style="color:#dc2626;"><?= $aset_stats['rusak'] ?> Rusak</span><?php endif; ?>
      </div>
    </div>
  </a>

  <a href="<?= APP_URL ?>/pages/cek_koneksi.php" style="text-decoration:none;background:#fff;border:1px solid <?= ($koneksi_stats['offline']??0)>0?'#fecaca':'#e8ecf0' ?>;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .18s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.08)';" onmouseout="this.style.boxShadow='none';">
    <div style="width:38px;height:38px;background:<?= ($koneksi_stats['offline']??0)>0?'#fff1f2':'#f0fdf4' ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-wifi" style="color:<?= ($koneksi_stats['offline']??0)>0?'#f43f5e':'#22c55e' ?>;font-size:15px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;"><?= $koneksi_stats['online']??0 ?><span style="font-size:12px;color:#94a3b8;font-weight:400;">/<?= $koneksi_stats['total']??0 ?></span></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Koneksi Online</div>
      <?php if (($koneksi_stats['offline']??0)>0): ?><div style="font-size:10px;color:#f43f5e;font-weight:600;margin-top:2px;"><?= $koneksi_stats['offline'] ?> offline</div><?php endif; ?>
    </div>
  </a>

  <a href="<?= APP_URL ?>/pages/server_room.php" style="text-decoration:none;background:#fff;border:1px solid <?= $sr_alert?'#fecaca':'#e8ecf0' ?>;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .18s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.08)';" onmouseout="this.style.boxShadow='none';">
    <div style="width:38px;height:38px;background:<?= $sr_alert?'#fff1f2':'#f8fafc' ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-server" style="color:<?= $sr_alert?'#f43f5e':'#64748b' ?>;font-size:15px;"></i>
    </div>
    <div>
      <?php if ($sr_latest): ?>
      <div style="font-size:15px;font-weight:800;color:<?= $sr_latest['status_overall']==='Normal'?'#16a34a':($sr_latest['status_overall']==='Perhatian'?'#d97706':'#dc2626') ?>;line-height:1;"><?= $sr_latest['status_overall'] ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Ruangan Server</div>
      <div style="font-size:10px;color:#94a3b8;margin-top:1px;"><?= $sr_latest['suhu_in']!==null?$sr_latest['suhu_in'].'°C':'' ?><?= $sr_latest['kelembaban']!==null?' · '.$sr_latest['kelembaban'].'%RH':'' ?></div>
      <?php else: ?><div style="font-size:13px;font-weight:600;color:#94a3b8;">Belum ada data</div><div style="font-size:11px;color:#94a3b8;margin-top:2px;">Ruangan Server</div><?php endif; ?>
    </div>
  </a>

  <a href="<?= APP_URL ?>/pages/maintenance_it.php" style="text-decoration:none;background:#fff;border:1px solid <?= !empty($mnt_urgent)?'#fde68a':'#e8ecf0' ?>;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .18s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.08)';" onmouseout="this.style.boxShadow='none';">
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

<!-- Antrian + SLA + Chart -->
<div class="g3">
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
          <?php else: foreach ($antrian as $t): $dur = durasiSekarang($t['waktu_submit']); ?>
          <tr>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
            <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($t['judul']) ?>"><?= clean($t['judul']) ?></span></td>
            <td><?= clean($t['kat_nama'] ?? '—') ?></td>
            <td><?= badgePrioritas($t['prioritas']) ?></td>
            <td><div class="d-flex ai-c gap6"><div class="av av-xs"><?= getInitials($t['req_nama']) ?></div><div><?= clean($t['req_nama']) ?><br><span class="text-muted text-sm"><?= clean($t['divisi']??'') ?></span></div></div></td>
            <td style="white-space:nowrap;"><span style="font-size:11px;color:#94a3b8;"><?= formatTanggal($t['waktu_submit'], true) ?></span>
              <?php if ($dur > 60): ?><br><span style="font-size:10px;color:var(--red);font-weight:700;"><i class="fa fa-clock"></i> <?= formatDurasi($dur) ?></span><?php endif; ?>
            </td>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fa fa-wrench"></i> Proses</a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Kolom kanan -->
  <div>
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
          <div style="font-size:32px;font-weight:800;color:<?= $sla_pct>=90?'var(--green)':($sla_pct>=70?'var(--orange)':'var(--red)') ?>;"><?= $sla_pct ?>%</div>
          <div style="font-size:11px;color:#94a3b8;">Tiket selesai dalam target SLA</div>
        </div>
        <div class="progress" style="height:8px;margin-bottom:14px;">
          <div class="progress-fill <?= $sla_cls ?>" style="width:<?= $sla_pct ?>%"></div>
        </div>
        <?php foreach ([['Total Tiket',$sla_summary['total']??0],['Sudah Selesai',$sla_summary['selesai']??0],['Dalam Target SLA',$sla_summary['sla_met']??0],['Avg. Respon',formatDurasi((int)round($sla_summary['avg_respon']??0))],['Avg. Penyelesaian',formatDurasi((int)round($sla_summary['avg_selesai']??0))]] as [$l,$v]): ?>
        <div class="d-flex ai-c" style="justify-content:space-between;margin-bottom:7px;font-size:12px;">
          <span style="color:#888;"><?= $l ?></span><strong style="color:#333;"><?= $v ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
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

<!-- Diproses + Maintenance + Server Room -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-top:0;">

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
            $sla = isset($t['sla_jam']) && $t['sla_jam'] ? slaStatus($dur, $t['sla_jam']) : null;
          ?>
          <tr>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
            <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($t['judul']) ?></span><small style="color:#94a3b8;"><?= clean($t['req_nama']) ?></small></td>
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
  <?php else: ?><div></div><?php endif; ?>

  <div style="display:flex;flex-direction:column;gap:14px;">
    <!-- Maintenance urgent -->
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
          $sisa = (int)$m['sisa_hari'];
          $c  = $sisa <= 0 ? '#dc2626' : ($sisa <= 2 ? '#d97706' : '#2563eb');
          $bg = $sisa <= 0 ? '#fee2e2' : ($sisa <= 2 ? '#fef9c3' : '#eff6ff');
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #f8fafc;">
          <div style="width:34px;height:34px;background:<?= $bg ?>;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:800;color:<?= $c ?>;">
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

    <!-- Server Room -->
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
          $sr_st_c  = $sr_latest['status_overall']==='Normal'?'#16a34a':($sr_latest['status_overall']==='Perhatian'?'#d97706':'#dc2626');
          $sr_st_bg = $sr_latest['status_overall']==='Normal'?'#dcfce7':($sr_latest['status_overall']==='Perhatian'?'#fef9c3':'#fee2e2');
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
          <span style="font-size:11px;color:#64748b;">Update: <?= date('d M, H:i',strtotime($sr_latest['tanggal'].' '.$sr_latest['waktu'])) ?></span>
          <span style="padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;background:<?= $sr_st_bg ?>;color:<?= $sr_st_c ?>;"><?= $sr_latest['status_overall'] ?></span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <?php foreach ([
            ['Suhu',$sr_latest['suhu_in']!==null?$sr_latest['suhu_in'].'°C':'—',(float)($sr_latest['suhu_in']??0)>27?'#dc2626':'#16a34a'],
            ['Kelembaban',$sr_latest['kelembaban']!==null?$sr_latest['kelembaban'].'%':'—',(float)($sr_latest['kelembaban']??0)>65?'#d97706':'#16a34a'],
            ['PLN',$sr_latest['tegangan_pln']!==null?$sr_latest['tegangan_pln'].'V':'—','#2563eb'],
            ['Baterai UPS',$sr_latest['baterai_ups']!==null?$sr_latest['baterai_ups'].'%':'—',(float)($sr_latest['baterai_ups']??100)<30?'#dc2626':'#16a34a'],
          ] as [$l,$v,$vc]): ?>
          <div style="background:#f8fafc;border-radius:6px;padding:8px 10px;">
            <div style="font-size:9.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;"><?= $l ?></div>
            <div style="font-size:15px;font-weight:800;color:<?= $vc ?>;"><?= $v ?></div>
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
        <div style="margin-top:8px;border-top:1px solid #f1f5f9;padding-top:8px;display:flex;gap:5px;flex-wrap:wrap;font-size:11px;">
          <?php foreach([['AC 1',$sr_latest['kondisi_ac1']??'—'],['AC 2',$sr_latest['kondisi_ac2']??'—'],['Pintu',$sr_latest['kondisi_pintu']??'—'],['CCTV',$sr_latest['kondisi_cctv']??'—']] as [$l,$v]):
            $ok = in_array($v,['Normal','Baik','Terkunci','Aktif']); ?>
          <span style="background:<?= $ok?'#f0fdf4':'#fef2f2' ?>;color:<?= $ok?'#15803d':'#b91c1c' ?>;padding:2px 7px;border-radius:4px;font-weight:600;"><?= $l ?>: <?= $v ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Top Teknisi (admin only) -->
<?php if ($_cur_role === 'admin' && !empty($top_teknisi)): ?>
<div class="panel" style="margin-top:14px;">
  <div class="panel-hd">
    <h5><i class="fa fa-trophy" style="color:#d97706;"></i> &nbsp;Top Teknisi IT Bulan Ini</h5>
    <a href="<?= APP_URL ?>/pages/sla.php" class="btn btn-default btn-sm">Laporan SLA</a>
  </div>
  <div class="panel-bd np">
    <table>
      <thead>
        <tr><th>#</th><th>Teknisi</th><th>Total</th><th>Selesai</th><th>SLA Met</th><th>Avg. Selesai</th></tr>
      </thead>
      <tbody>
        <?php foreach ($top_teknisi as $i => $tek):
          $tek_sla = (int)($tek['selesai']??0) > 0 ? round((int)($tek['sla_met']??0) / (int)$tek['selesai'] * 100) : 0;
          $medals  = ['🥇','🥈','🥉'];
        ?>
        <tr>
          <td style="font-size:16px;text-align:center;"><?= $medals[$i] ?? ($i+1) ?></td>
          <td>
            <div class="d-flex ai-c gap6">
              <div class="av av-xs av-blue"><?= getInitials($tek['nama']) ?></div>
              <span style="font-weight:600;"><?= clean($tek['nama']) ?></span>
            </div>
          </td>
          <td style="font-weight:700;"><?= (int)($tek['total']??0) ?></td>
          <td style="color:var(--green);font-weight:700;"><?= (int)($tek['selesai']??0) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;">
              <div class="progress" style="width:50px;">
                <div class="progress-fill <?= $tek_sla>=90?'pg-green':($tek_sla>=70?'pg-orange':'pg-red') ?>" style="width:<?= $tek_sla ?>%;"></div>
              </div>
              <span style="font-size:11px;font-weight:700;"><?= $tek_sla ?>%</span>
            </div>
          </td>
          <td style="font-size:12px;"><?= formatDurasi((int)round($tek['avg_selesai']??0)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Top Teknisi IPSRS (admin only) -->
<?php if ($_cur_role === 'admin' && !empty($top_teknisi_ipsrs)): ?>
<div class="panel" style="margin-top:14px;">
  <div class="panel-hd">
    <h5><i class="fa fa-trophy" style="color:#16a34a;"></i> &nbsp;Top Teknisi IPSRS Bulan Ini</h5>
    <a href="<?= APP_URL ?>/pages/sla_ipsrs.php" class="btn btn-default btn-sm">Laporan SLA IPSRS</a>
  </div>
  <div class="panel-bd np">
    <table>
      <thead>
        <tr><th>#</th><th>Teknisi</th><th>Total</th><th>Selesai</th><th>SLA Met</th><th>Avg. Selesai</th></tr>
      </thead>
      <tbody>
        <?php foreach ($top_teknisi_ipsrs as $i => $tek):
          $tek_sla = (int)($tek['selesai']??0) > 0 ? round((int)($tek['sla_met']??0) / (int)$tek['selesai'] * 100) : 0;
          $medals  = ['🥇','🥈','🥉'];
        ?>
        <tr>
          <td style="font-size:16px;text-align:center;"><?= $medals[$i] ?? ($i+1) ?></td>
          <td>
            <div class="d-flex ai-c gap6">
              <div class="av av-xs av-green"><?= getInitials($tek['nama']) ?></div>
              <span style="font-weight:600;"><?= clean($tek['nama']) ?></span>
            </div>
          </td>
          <td style="font-weight:700;"><?= (int)($tek['total']??0) ?></td>
          <td style="color:var(--green);font-weight:700;"><?= (int)($tek['selesai']??0) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;">
              <div class="progress" style="width:50px;">
                <div class="progress-fill <?= $tek_sla>=90?'pg-green':($tek_sla>=70?'pg-orange':'pg-red') ?>"
                     style="width:<?= $tek_sla ?>%;"></div>
              </div>
              <span style="font-size:11px;font-weight:700;"><?= $tek_sla ?>%</span>
            </div>
          </td>
          <td style="font-size:12px;"><?= formatDurasi((int)round($tek['avg_selesai']??0)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Koneksi offline list -->
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
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.5;transform:scale(1.3);} }
</style>
<?php endif; ?>

<?php endif; // end role views ?>
</div>

<?php include 'includes/footer.php'; ?>