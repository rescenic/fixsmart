<?php
// includes/header.php — $page_title, $active_menu harus sudah di-set

$_in_pages = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'pages');
$_css_url  = APP_URL . '/assets/css/style.css';
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= clean($page_title ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= $_css_url ?>" rel="stylesheet">
<style>
/* ── FS Button di sidebar ── */
.fs-about-btn {
    display: flex; align-items: center; gap: 10px;
    width: calc(100% - 20px); margin: 0 10px 6px;
    padding: 9px 12px;
    background: linear-gradient(135deg, #1a2e3f 0%, #1b5c4a 100%);
    border: 1px solid rgba(38,185,154,.35); border-radius: 8px;
    cursor: pointer; font-family: inherit; text-decoration: none; transition: all .2s;
}
.fs-about-btn:hover {
    background: linear-gradient(135deg, #26B99A 0%, #1a7a5e 100%);
    border-color: #26B99A; transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(38,185,154,.3);
}
.fs-logo-badge {
    width: 32px; height: 32px; background: rgba(38,185,154,.2);
    border: 1.5px solid rgba(38,185,154,.5); border-radius: 7px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    font-size: 11px; font-weight: 900; color: #26B99A; letter-spacing: -0.5px; transition: all .2s;
}
.fs-about-btn:hover .fs-logo-badge { background: rgba(255,255,255,.2); border-color: rgba(255,255,255,.5); color: #fff; }
.fs-btn-text { flex: 1; }
.fs-btn-label { font-size: 11.5px; font-weight: 700; color: #e2e8f0; line-height: 1.2; }
.fs-btn-sub   { font-size: 9.5px; color: rgba(255,255,255,.4); margin-top: 1px; }
.fs-about-btn:hover .fs-btn-label { color: #fff; }
.fs-about-btn:hover .fs-btn-sub   { color: rgba(255,255,255,.7); }
.fs-chevron { color: rgba(255,255,255,.3); font-size: 10px; transition: all .2s; }
.fs-about-btn:hover .fs-chevron   { color: rgba(255,255,255,.8); transform: translateX(2px); }

/* ── Collapsible nav group (IPSRS / IT) ── */
.nav-group-toggle {
    display: flex; align-items: center; gap: 0;
    width: 100%; cursor: pointer; user-select: none;
    background: none; border: none; font-family: inherit; padding: 0;
}
.nav-group-toggle .nav-sec-label {
    flex: 1; font-size: 10px; font-weight: 700; color: rgba(255,255,255,.35);
    text-transform: uppercase; letter-spacing: 1px;
    padding: 14px 16px 6px; display: block;
}
.nav-group-toggle .nav-sec-caret {
    font-size: 9px; color: rgba(255,255,255,.25);
    padding-right: 14px; padding-top: 8px;
    transition: transform .2s;
}
.nav-group-toggle.open .nav-sec-caret { transform: rotate(180deg); }
.nav-group-body { overflow: hidden; transition: max-height .3s ease; max-height: 500px; }
.nav-group-body.collapsed { max-height: 0 !important; overflow: hidden; }
/* ── Accordion Nav Group (details/summary) ── */
.nav-group { border: none; }
.nav-group summary.nav-group-hd {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 12px 9px 14px;
    font-size: 11px; font-weight: 700;
    color: rgba(255,255,255,.55);
    text-transform: uppercase; letter-spacing: .5px;
    cursor: pointer; list-style: none;
    user-select: none;
    transition: color .2s ease, background .2s ease;
    border-left: 3px solid transparent;
    white-space: nowrap; overflow: hidden;
}
.nav-group summary.nav-group-hd span {
    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.nav-group summary.nav-group-hd::-webkit-details-marker { display: none; }
.nav-group summary.nav-group-hd:hover { color: rgba(255,255,255,.85); background: rgba(255,255,255,.05); }
.nav-group[open] summary.nav-group-hd { color: #26B99A; border-left-color: #26B99A; background: rgba(38,185,154,.08); }
.nav-group summary.nav-group-hd .ni-grp { width: 16px; text-align: center; font-size: 13px; flex-shrink: 0; }
.nav-group summary.nav-group-hd span   { flex: 1; }
.nav-group summary.nav-group-hd .caret-grp { font-size: 9px; color: rgba(255,255,255,.25); transition: transform .2s; }
.nav-group[open] summary.nav-group-hd .caret-grp { transform: rotate(180deg); color: #26B99A; }
.nav-group summary.nav-group-hd .nc-grp {
    font-size: 10px; font-weight: 700;
    background: #e74c3c; color: #fff;
    padding: 1px 6px; border-radius: 10px;
    margin-right: 4px;
}
.nav-group-bd {
    padding: 2px 0 6px 0;
    overflow: hidden;
    /* TIDAK ada animation di sini — animasi hanya via JS saat user klik */
}
/* Kelas animasi yang disuntik JS saja */
.nav-group-bd.anim-open  { animation: slideDown .22s ease; }
.nav-group-bd.anim-close { animation: slideUp .18s ease forwards; }
@keyframes slideUp {
    from { opacity: 1; transform: translateY(0); }
    to   { opacity: 0; transform: translateY(-6px); }
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.nav-group-bd .nav-item a {
    padding-left: 36px !important;
    transition: background .15s ease, color .15s ease, padding-left .15s ease;
}
.nav-group-bd .nav-item a:hover {
    padding-left: 40px !important;
}

/* ── Hapus CSS nav-group-toggle lama (override) ── */
.nav-group-toggle { display: none !important; }
.nav-group-body   { display: none !important; }


/* ── Sidebar collapsed state ── */
@media (min-width: 769px) {
  .sidebar.collapsed { width: 0; min-width: 0; overflow: hidden; }
  .main.sb-collapsed,
  .topnav.sb-collapsed { margin-left: 0 !important; }
}
</style>
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<nav class="sidebar" id="sidebar">

  <!-- Logo -->
  <div class="sb-logo">
    <div class="logo-icon"><i class="fa fa-desktop"></i></div>
    <div>
      <div class="logo-text"><?= APP_NAME ?></div>
      <div class="logo-sub">Work Order System</div>
    </div>
  </div>

  <!-- Info User Login -->
  <div class="sb-user">
    <div class="av av-sm"><?= getInitials($_SESSION['user_nama']) ?></div>
    <div>
      <div class="uname"><?= clean($_SESSION['user_nama']) ?></div>
      <div class="urole"><?= ucfirst($_SESSION['user_role']) ?> &mdash; <?= clean($_SESSION['user_divisi'] ?? '-') ?></div>
    </div>
  </div>

  <?php
  // ── Hitung badge notifikasi ──────────────────────────────────────────────────
  $cnt_menunggu = (int)$pdo->query("SELECT COUNT(*) FROM tiket WHERE status='menunggu'")->fetchColumn();
  $cnt_diproses = (int)$pdo->query("SELECT COUNT(*) FROM tiket WHERE status='diproses'")->fetchColumn();

  // Badge maintenance IT urgent (≤7 hari)
  $cnt_mnt_urgent = 0;
  try {
      $cnt_mnt_urgent = (int)$pdo->query("
          SELECT COUNT(DISTINCT aset_id) FROM maintenance_it
          WHERE id IN (SELECT MAX(id) FROM maintenance_it GROUP BY aset_id)
            AND tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)
      ")->fetchColumn();
  } catch(Exception $e) {}

  // Badge koneksi offline
  $cnt_offline = 0;
  try {
      $cnt_offline = (int)$pdo->query("
          SELECT COUNT(*) FROM koneksi_monitor m
          WHERE aktif = 1
            AND (SELECT status FROM koneksi_log WHERE monitor_id = m.id ORDER BY cek_at DESC LIMIT 1) = 'offline'
      ")->fetchColumn();
  } catch(Exception $e) {}

  // Badge tiket aktif milik user (IT)
  $cnt_user_aktif = 0;
  if (hasRole('user')) {
      $st2 = $pdo->prepare("SELECT COUNT(*) FROM tiket WHERE user_id=? AND status IN ('menunggu','diproses')");
      $st2->execute([$_SESSION['user_id']]);
      $cnt_user_aktif = (int)$st2->fetchColumn();
  }

  // Badge tiket IPSRS aktif milik user
  $cnt_user_ipsrs_aktif = 0;
  if (hasRole('user')) {
      try {
          $st3 = $pdo->prepare("SELECT COUNT(*) FROM tiket_ipsrs WHERE user_id=? AND status IN ('menunggu','diproses')");
          $st3->execute([$_SESSION['user_id']]);
          $cnt_user_ipsrs_aktif = (int)$st3->fetchColumn();
      } catch(Exception $e) {}
  }

  // ── Badge IPSRS ──────────────────────────────────────────────────────────────
  // Tiket IPSRS menunggu & diproses
  $cnt_ipsrs_menunggu = 0;
  $cnt_ipsrs_diproses = 0;
  try {
      $cnt_ipsrs_menunggu = (int)$pdo->query("SELECT COUNT(*) FROM tiket_ipsrs WHERE status='menunggu'")->fetchColumn();
      $cnt_ipsrs_diproses = (int)$pdo->query("SELECT COUNT(*) FROM tiket_ipsrs WHERE status='diproses'")->fetchColumn();
  } catch(Exception $e) {}

  // Badge maintenance IPSRS urgent (≤7 hari)
  $cnt_mnt_ipsrs_urgent = 0;
  try {
      $cnt_mnt_ipsrs_urgent = (int)$pdo->query("
          SELECT COUNT(DISTINCT aset_id) FROM maintenance_ipsrs
          WHERE id IN (SELECT MAX(id) FROM maintenance_ipsrs GROUP BY aset_id)
            AND tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)
      ")->fetchColumn();
  } catch(Exception $e) {}


  // ── Deteksi grup aktif untuk auto-expand ──────────────────────────────────
  $grp_tiket_it    = in_array($active_menu??'', ['antrian','semua_tiket','sla','dashboard']);
  $grp_tiket_ipsrs = in_array($active_menu??'', ['antrian_ipsrs','semua_tiket_ipsrs','sla_ipsrs']);
  $grp_aset_it     = in_array($active_menu??'', ['aset_it','maintenance_it','cek_koneksi','server_room']);
  $grp_aset_ipsrs  = in_array($active_menu??'', ['aset_ipsrs','maintenance_ipsrs']);
  $grp_master      = in_array($active_menu??'', ['kategori','kategori_ipsrs','bagian','users']);
  $grp_setting     = in_array($active_menu??'', ['setting_telegram']);
  $grp_tiket_saya  = in_array($active_menu??'', ['buat_tiket','tiket_saya']);
  $grp_tiket_saya_ipsrs = in_array($active_menu??'', ['buat_tiket_sarpras','tiket_saya_ipsrs']);
  ?>

  <!-- ══════════════════════════════════════════════
       MENU: USER BIASA
  ══════════════════════════════════════════════ -->
  <?php if (hasRole('user')): ?>

  <div class="nav-item <?= ($active_menu??'')==='dashboard'?'active':'' ?>">
    <a href="<?= APP_URL ?>/dashboard.php"><i class="fa fa-home ni"></i><span class="nl">Dashboard</span></a>
  </div>

  <!-- Tiket IT Saya -->
  <details class="nav-group" <?= $grp_tiket_saya ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-desktop ni-grp"></i><span>Tiket IT</span><i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='buat_tiket'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/buat_tiket.php"><i class="fa fa-plus-circle ni"></i><span class="nl">Buat Tiket IT</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='tiket_saya'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/tiket_saya.php"><i class="fa fa-list-alt ni"></i><span class="nl">Tiket Saya</span>
          <?php if ($cnt_user_aktif): ?><span class="nc"><?= $cnt_user_aktif ?></span><?php endif; ?>
        </a>
      </div>
    </div>
  </details>

  <!-- Tiket IPSRS Saya -->
  <details class="nav-group" <?= $grp_tiket_saya_ipsrs ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-toolbox ni-grp"></i><span>Tiket IPSRS</span><i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')=='buat_tiket_sarpras'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/buat_tiket_sarpras.php"><i class="fa fa-plus-circle ni"></i><span class="nl">Buat Tiket IPSRS</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')=='tiket_saya_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/tiket_saya_ipsrs.php"><i class="fa fa-list-alt ni"></i><span class="nl">Tiket IPSRS Saya</span>
          <?php if ($cnt_user_ipsrs_aktif): ?><span class="nc"><?= $cnt_user_ipsrs_aktif ?></span><?php endif; ?>
        </a>
      </div>
    </div>
  </details>


  <!-- ══════════════════════════════════════════════
       MENU: TEKNISI IT
  ══════════════════════════════════════════════ -->
  <?php elseif (hasRole('teknisi')): ?>

  <div class="nav-item <?= ($active_menu??'')==='dashboard'?'active':'' ?>">
    <a href="<?= APP_URL ?>/dashboard.php"><i class="fa fa-home ni"></i><span class="nl">Dashboard</span></a>
  </div>

  <details class="nav-group" <?= $grp_tiket_it ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-desktop ni-grp"></i><span>Tiket IT</span>
      <?php if ($cnt_menunggu): ?><span class="nc-grp"><?= $cnt_menunggu ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='antrian'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/antrian.php"><i class="fa fa-inbox ni"></i><span class="nl">Antrian</span>
          <?php if ($cnt_menunggu): ?><span class="nc"><?= $cnt_menunggu ?></span><?php endif; ?>
        </a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='semua_tiket'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/semua_tiket.php"><i class="fa fa-ticket-alt ni"></i><span class="nl">Semua Tiket</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='sla'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/sla.php"><i class="fa fa-chart-line ni"></i><span class="nl">Laporan SLA</span></a>
      </div>
    </div>
  </details>

  <details class="nav-group" <?= $grp_aset_it ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-server ni-grp"></i><span>Aset IT</span>
      <?php if ($cnt_mnt_urgent): ?><span class="nc-grp"><?= $cnt_mnt_urgent ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='aset_it'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/aset_it.php"><i class="fa fa-server ni"></i><span class="nl">Aset IT</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='maintenance_it'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/maintenance_it.php"><i class="fa fa-screwdriver-wrench ni"></i><span class="nl">Maintenance IT</span>
          <?php if ($cnt_mnt_urgent): ?><span class="nc"><?= $cnt_mnt_urgent ?></span><?php endif; ?>
        </a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='cek_koneksi'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/cek_koneksi.php"><i class="fa fa-wifi ni"></i><span class="nl">Monitor Koneksi</span>
          <?php if ($cnt_offline): ?><span class="nc" style="background:#ef4444;"><?= $cnt_offline ?></span><?php endif; ?>
        </a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='server_room'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/server_room.php"><i class="fa fa-server ni"></i><span class="nl">Monitoring Server</span></a>
      </div>
    </div>
  </details>


  <!-- ══════════════════════════════════════════════
       MENU: TEKNISI IPSRS
  ══════════════════════════════════════════════ -->
  <?php elseif (hasRole('teknisi_ipsrs')): ?>

  <div class="nav-item <?= ($active_menu??'')==='dashboard'?'active':'' ?>">
    <a href="<?= APP_URL ?>/dashboard.php"><i class="fa fa-home ni"></i><span class="nl">Dashboard</span></a>
  </div>

  <details class="nav-group" <?= $grp_tiket_ipsrs ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-toolbox ni-grp"></i><span>Tiket IPSRS</span>
      <?php if ($cnt_ipsrs_menunggu): ?><span class="nc-grp"><?= $cnt_ipsrs_menunggu ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='antrian_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/antrian_ipsrs.php"><i class="fa fa-inbox ni"></i><span class="nl">Antrian IPSRS</span>
          <?php if ($cnt_ipsrs_menunggu): ?><span class="nc"><?= $cnt_ipsrs_menunggu ?></span><?php endif; ?>
        </a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='semua_tiket_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/semua_tiket_ipsrs.php"><i class="fa fa-ticket-alt ni"></i><span class="nl">Semua Tiket IPSRS</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='sla_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/sla_ipsrs.php"><i class="fa fa-chart-line ni"></i><span class="nl">Laporan SLA IPSRS</span></a>
      </div>
    </div>
  </details>

  <details class="nav-group" <?= $grp_aset_ipsrs ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-wrench ni-grp"></i><span>Aset IPSRS</span>
      <?php if ($cnt_mnt_ipsrs_urgent): ?><span class="nc-grp"><?= $cnt_mnt_ipsrs_urgent ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='aset_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/aset_ipsrs.php"><i class="fa fa-toolbox ni"></i><span class="nl">Aset IPSRS</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='maintenance_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/maintenance_ipsrs.php"><i class="fa fa-wrench ni"></i><span class="nl">Maintenance IPSRS</span>
          <?php if ($cnt_mnt_ipsrs_urgent): ?><span class="nc"><?= $cnt_mnt_ipsrs_urgent ?></span><?php endif; ?>
        </a>
      </div>
    </div>
  </details>


  <!-- ══════════════════════════════════════════════
       MENU: ADMIN
  ══════════════════════════════════════════════ -->
  <?php else: // admin ?>

  <div class="nav-item <?= ($active_menu??'')==='dashboard'?'active':'' ?>">
    <a href="<?= APP_URL ?>/dashboard.php"><i class="fa fa-home ni"></i><span class="nl">Dashboard</span></a>
  </div>

  <!-- Tiket IT -->
  <details class="nav-group" <?= $grp_tiket_it ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-desktop ni-grp"></i><span>Tiket IT</span>
      <?php if ($cnt_menunggu): ?><span class="nc-grp"><?= $cnt_menunggu ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='antrian'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/antrian.php"><i class="fa fa-inbox ni"></i><span class="nl">Antrian Tiket</span>
          <?php if ($cnt_menunggu): ?><span class="nc"><?= $cnt_menunggu ?></span><?php endif; ?>
        </a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='semua_tiket'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/semua_tiket.php"><i class="fa fa-ticket-alt ni"></i><span class="nl">Semua Tiket</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='sla'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/sla.php"><i class="fa fa-chart-line ni"></i><span class="nl">Laporan SLA</span></a>
      </div>
    </div>
  </details>

  <!-- Tiket IPSRS -->
  <details class="nav-group" <?= $grp_tiket_ipsrs ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-toolbox ni-grp"></i><span>Tiket IPSRS</span>
      <?php if ($cnt_ipsrs_menunggu): ?><span class="nc-grp"><?= $cnt_ipsrs_menunggu ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='antrian_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/antrian_ipsrs.php"><i class="fa fa-inbox ni"></i><span class="nl">Antrian IPSRS</span>
          <?php if ($cnt_ipsrs_menunggu): ?><span class="nc"><?= $cnt_ipsrs_menunggu ?></span><?php endif; ?>
        </a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='semua_tiket_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/semua_tiket_ipsrs.php"><i class="fa fa-ticket-alt ni"></i><span class="nl">Semua Tiket IPSRS</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='sla_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/sla_ipsrs.php"><i class="fa fa-chart-line ni"></i><span class="nl">Laporan SLA IPSRS</span></a>
      </div>
    </div>
  </details>

  <!-- Aset & Perawatan IT -->
  <details class="nav-group" <?= $grp_aset_it ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-server ni-grp"></i><span>Aset IT</span>
      <?php if ($cnt_mnt_urgent||$cnt_offline): ?><span class="nc-grp"><?= $cnt_mnt_urgent+$cnt_offline ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='aset_it'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/aset_it.php"><i class="fa fa-server ni"></i><span class="nl">Aset IT</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='maintenance_it'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/maintenance_it.php"><i class="fa fa-screwdriver-wrench ni"></i><span class="nl">Maintenance IT</span>
          <?php if ($cnt_mnt_urgent): ?><span class="nc" style="background:#f59e0b;"><?= $cnt_mnt_urgent ?></span><?php endif; ?>
        </a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='cek_koneksi'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/cek_koneksi.php"><i class="fa fa-wifi ni"></i><span class="nl">Monitor Koneksi</span>
          <?php if ($cnt_offline): ?><span class="nc" style="background:#ef4444;"><?= $cnt_offline ?></span><?php endif; ?>
        </a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='server_room'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/server_room.php"><i class="fa fa-server ni"></i><span class="nl">Monitoring Server</span></a>
      </div>
    </div>
  </details>

  <!-- Aset & Perawatan IPSRS -->
  <details class="nav-group" <?= $grp_aset_ipsrs ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-wrench ni-grp"></i><span>Aset IPSRS</span>
      <?php if ($cnt_mnt_ipsrs_urgent): ?><span class="nc-grp"><?= $cnt_mnt_ipsrs_urgent ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='aset_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/aset_ipsrs.php"><i class="fa fa-toolbox ni"></i><span class="nl">Aset IPSRS</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='maintenance_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/maintenance_ipsrs.php"><i class="fa fa-wrench ni"></i><span class="nl">Maintenance IPSRS</span>
          <?php if ($cnt_mnt_ipsrs_urgent): ?><span class="nc" style="background:#f59e0b;"><?= $cnt_mnt_ipsrs_urgent ?></span><?php endif; ?>
        </a>
      </div>
    </div>
  </details>

  <!-- Master Data -->
  <details class="nav-group" <?= $grp_master ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-database ni-grp"></i><span>Master Data</span>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='kategori'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/kategori.php"><i class="fa fa-tags ni"></i><span class="nl">Kategori IT</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='kategori_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/kategori_ipsrs.php"><i class="fa fa-tags ni" style="color:#f97316;"></i><span class="nl">Kategori IPSRS</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='bagian'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/bagian.php"><i class="fa fa-building ni"></i><span class="nl">Bagian / Divisi</span></a>
      </div>
      <div class="nav-item <?= ($active_menu??'')==='users'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/users.php"><i class="fa fa-users ni"></i><span class="nl">Pengguna</span></a>
      </div>
    </div>
  </details>

  <!-- Pengaturan -->
  <details class="nav-group" <?= $grp_setting ? 'open' : '' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-cog ni-grp"></i><span>Pengaturan</span>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= ($active_menu??'')==='setting_telegram'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/setting_telegram.php">
          <i class="fa fa-paper-plane ni" style="color:#0088cc;"></i><span class="nl">Notif Telegram</span>
          <?php try {
            $tg_on = $pdo->query("SELECT value FROM settings WHERE `key`='telegram_enabled'")->fetchColumn();
            echo $tg_on=='1'
              ? '<span style="width:7px;height:7px;border-radius:50%;background:#27ae60;display:inline-block;margin-left:2px;"></span>'
              : '<span style="width:7px;height:7px;border-radius:50%;background:#bbb;display:inline-block;margin-left:2px;"></span>';
          } catch(Exception $e) {} ?>
        </a>
      </div>
    </div>
  </details>

  <?php endif; // end role check ?>

  <!-- Profil (semua role) -->
  <div class="nav-item <?= ($active_menu??'')==='profil'?'active':'' ?>">
    <a href="<?= APP_URL ?>/pages/profil.php"><i class="fa fa-user-circle ni"></i><span class="nl">Profil Saya</span></a>
  </div>



  <!-- ── BOTTOM: Tombol About + Keluar ── -->
  <div class="sb-bottom">
    <button onclick="openModal('m-about')" class="fs-about-btn">
      <div class="fs-logo-badge">FS</div>
      <div class="fs-btn-text">
        <div class="fs-btn-label">FixSmart Helpdesk</div>
        <div class="fs-btn-sub">Versi 1.0.0 &mdash; Tentang Aplikasi</div>
      </div>
      <i class="fa fa-chevron-right fs-chevron"></i>
    </button>
    <div class="nav-item">
      <a href="<?= APP_URL ?>/logout.php">
        <i class="fa fa-sign-out-alt ni"></i><span class="nl">Keluar</span>
      </a>
    </div>
  </div>

</nav><!-- /sidebar -->


<!-- ========== TOP NAV ========== -->
<div class="topnav" id="topnav">
  <div class="tn-left">
    <button class="btn-toggle" onclick="toggleSidebar()"><i class="fa fa-bars"></i></button>
    <span class="tn-title"><?= APP_NAME ?></span>
  </div>
  <div class="tn-right">
    <?php if (hasRole(['admin','teknisi']) && $cnt_menunggu): ?>
    <a href="<?= APP_URL ?>/pages/antrian.php"
       style="font-size:12px;color:var(--orange);font-weight:600;text-decoration:none;">
      <i class="fa fa-bell"></i> <?= $cnt_menunggu ?> tiket IT
    </a>
    <?php endif; ?>
    <?php if (hasRole(['admin','teknisi_ipsrs']) && $cnt_ipsrs_menunggu): ?>
    <a href="<?= APP_URL ?>/pages/antrian_ipsrs.php"
       style="font-size:12px;color:var(--orange);font-weight:600;text-decoration:none;">
      <i class="fa fa-bell"></i> <?= $cnt_ipsrs_menunggu ?> tiket IPSRS
    </a>
    <?php endif; ?>

    <!-- Tombol Tentang -->
    <button onclick="openModal('m-about')" title="Tentang Aplikasi"
      style="display:flex;align-items:center;gap:6px;padding:5px 11px;border-radius:20px;border:1px solid #e0e0e0;background:#fff;cursor:pointer;font-size:12px;color:#555;font-family:inherit;transition:all .2s;"
      onmouseover="this.style.background='#26B99A';this.style.color='#fff';this.style.borderColor='#26B99A';"
      onmouseout="this.style.background='#fff';this.style.color='#555';this.style.borderColor='#e0e0e0';">
      <i class="fa fa-circle-info" style="font-size:13px;"></i>
      <span>Tentang</span>
    </button>

    <!-- Dropdown user -->
    <div class="tn-user" onclick="toggleDropdown(this)">
      <div class="tn-user-info">
        <div class="av-sm"><?= getInitials($_SESSION['user_nama']) ?></div>
        <span><?= clean($_SESSION['user_nama']) ?></span>
        <i class="fa fa-chevron-down caret"></i>
      </div>
      <div class="tn-dropdown">
        <a href="<?= APP_URL ?>/pages/profil.php"><i class="fa fa-user"></i> Profil Saya</a>
        <a href="<?= APP_URL ?>/logout.php" class="red-link"><i class="fa fa-sign-out-alt"></i> Keluar</a>
      </div>
    </div>
  </div>
</div>


<!-- ========== MODAL TENTANG APLIKASI ========== -->
<div class="modal-ov" id="m-about" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:780px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;display:flex;animation:mIn .2s ease;">

    <!-- Kolom Kiri -->
    <div style="width:260px;flex-shrink:0;background:linear-gradient(175deg,#1a2e3f 0%,#2a3f54 55%,#1b5c4a 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px 20px;text-align:center;">
      <div style="width:68px;height:68px;background:rgba(255,255,255,.1);border:2px solid rgba(38,185,154,.5);border-radius:18px;display:flex;align-items:center;justify-content:center;margin-bottom:13px;">
        <i class="fa fa-desktop" style="font-size:30px;color:#26B99A;"></i>
      </div>
      <div style="color:#fff;font-size:17px;font-weight:700;letter-spacing:.3px;line-height:1.2;">FixSmart Helpdesk</div>
      <div style="color:rgba(255,255,255,.45);font-size:10.5px;margin-top:4px;">Work Order System</div>
      <div style="margin-top:9px;background:rgba(38,185,154,.2);border:1px solid rgba(38,185,154,.4);color:#5eead4;padding:2px 12px;border-radius:20px;font-size:10px;font-weight:700;">
        Versi 1.0.0
      </div>
      <div style="width:100%;height:1px;background:rgba(255,255,255,.08);margin:16px 0;"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;width:100%;">
        <?php foreach ([
          ['fa-ticket-alt',        '#60a5fa', 'Tiket'],
          ['fa-chart-line',        '#a78bfa', 'SLA'],
          ['fa-users',             '#fbbf24', 'Multi Role'],
          ['fa-paper-plane',       '#38bdf8', 'Telegram'],
          ['fa-screwdriver-wrench','#34d399', 'Maintenance'],
          ['fa-shield-alt',        '#f87171', 'Keamanan'],
        ] as [$ic,$cl,$lb]): ?>
        <div style="background:rgba(255,255,255,.07);border-radius:5px;padding:6px 5px;font-size:10px;color:rgba(255,255,255,.75);display:flex;align-items:center;gap:6px;">
          <i class="fa <?= $ic ?>" style="color:<?= $cl ?>;font-size:11px;width:13px;text-align:center;flex-shrink:0;"></i><?= $lb ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="width:100%;height:1px;background:rgba(255,255,255,.08);margin:14px 0;"></div>
      <div style="text-align:center;">
        <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.7px;margin-bottom:7px;">Dikembangkan oleh</div>
        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#26B99A,#1a2e3f);border:2px solid rgba(38,185,154,.4);display:flex;align-items:center;justify-content:center;margin:0 auto 6px;">
          <i class="fa fa-user" style="color:#fff;font-size:14px;"></i>
        </div>
        <div style="color:#fff;font-size:12px;font-weight:700;">M. Wira Satria Buana, S. Kom</div>
        <div style="color:rgba(255,255,255,.4);font-size:10px;margin-top:3px;">
          <i class="fa fa-envelope" style="color:#26B99A;"></i> wiramuhammad16@gmail.com
        </div>
        <div style="color:rgba(255,255,255,.4);font-size:10px;margin-top:3px;">
          <i class="fa fa-phone" style="color:#26B99A;"></i> 0821 7784 6209
        </div>
      </div>
      <div style="margin-top:14px;font-size:10px;color:rgba(255,255,255,.25);line-height:1.6;">
        &copy; 2025 FixSmart &bull; PHP + MySQL<br>Open Source &amp; Free Forever
      </div>
    </div>

    <!-- Kolom Kanan -->
    <div style="flex:1;display:flex;flex-direction:column;min-height:0;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px 10px;border-bottom:1px solid #f0f0f0;">
        <div style="font-size:13px;font-weight:700;color:#1e293b;">
          <i class="fa fa-circle-info" style="color:#26B99A;"></i> &nbsp;Tentang Aplikasi
        </div>
        <button onclick="closeModal('m-about')"
          style="width:26px;height:26px;border-radius:50%;background:#f3f4f6;border:none;cursor:pointer;color:#888;font-size:13px;display:flex;align-items:center;justify-content:center;"
          onmouseover="this.style.background='#ef4444';this.style.color='#fff';"
          onmouseout="this.style.background='#f3f4f6';this.style.color='#888';">
          <i class="fa fa-times"></i>
        </button>
      </div>
      <div style="flex:1;padding:14px 18px;display:flex;flex-direction:column;gap:11px;">
        <p style="font-size:11.5px;color:#64748b;line-height:1.7;margin:0;">
          Sistem manajemen tiket IT berbasis web untuk membantu pengelolaan <em>work order</em>,
          pelacakan SLA, dan pelaporan kinerja tim IT. Ringan, mudah dikustomisasi, dan siap pakai.
        </p>
        <div style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1px solid #6ee7b7;border-radius:7px;padding:10px 13px;display:flex;align-items:center;gap:10px;">
          <div style="width:34px;height:34px;background:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa fa-gift" style="color:#fff;font-size:14px;"></i>
          </div>
          <div>
            <div style="font-size:12px;font-weight:700;color:#065f46;">Aplikasi 100% Gratis</div>
            <div style="font-size:10.5px;color:#059669;margin-top:1px;line-height:1.5;">
              Bebas digunakan &amp; dimodifikasi.
              <strong>Dilarang diperjualbelikan</strong> dalam bentuk apapun, karena tujuan aplikasi dibuat untuk saling membantu.
            </div>
          </div>
        </div>
        <div style="background:linear-gradient(135deg,#fffbeb,#fef9c3);border:1px solid #fde68a;border-radius:7px;padding:11px 13px;">
          <div style="display:flex;align-items:center;gap:7px;margin-bottom:8px;">
            <i class="fa fa-heart" style="color:#ef4444;font-size:14px;"></i>
            <span style="font-size:12px;font-weight:700;color:#78350f;">Dukung Pengembangan</span>
            <span style="font-size:10px;color:#a16207;margin-left:auto;">Sukarela &amp; ikhlas &#9749;</span>
          </div>
          <?php foreach ([
            ['#6c3db5','fa fa-wallet','OVO / DANA', '0821 7784 6209','0821 7784 6209',null],
            ['#00703c','',            'Bank BSI',   '7134197557',    '7134197557',    null],
          ] as [$bg,$ic,$lbl,$display,$copy,$link]): ?>
          <div style="display:flex;align-items:center;gap:9px;background:#fff;border:1px solid #fde68a;border-radius:5px;padding:7px 10px;margin-bottom:6px;">
            <div style="width:26px;height:26px;border-radius:5px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <?php if ($ic): ?>
              <i class="<?= $ic ?>" style="color:#fff;font-size:11px;"></i>
              <?php else: ?>
              <span style="color:#fff;font-size:9px;font-weight:900;">BSI</span>
              <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:10px;font-weight:700;color:#555;"><?= $lbl ?></div>
              <div style="font-size:11px;color:#333;font-family:monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= $display ?></div>
            </div>
            <?php if ($copy): ?>
            <button onclick="copyText('<?= $copy ?>',this)"
              style="background:none;border:1px solid #d1d5db;border-radius:4px;padding:3px 8px;font-size:10px;cursor:pointer;color:#888;font-family:inherit;white-space:nowrap;flex-shrink:0;transition:all .18s;"
              onmouseover="this.style.background='#26B99A';this.style.color='#fff';this.style.borderColor='#26B99A';"
              onmouseout="this.style.background='none';this.style.color='#888';this.style.borderColor='#d1d5db';">
              <i class="fa fa-copy"></i> Salin
            </button>
            <?php else: ?>
            <a href="<?= $link ?>" target="_blank"
              style="background:none;border:1px solid #d1d5db;border-radius:4px;padding:3px 8px;font-size:10px;cursor:pointer;color:#888;text-decoration:none;white-space:nowrap;flex-shrink:0;transition:all .18s;"
              onmouseover="this.style.background='#26B99A';this.style.color='#fff';this.style.borderColor='#26B99A';"
              onmouseout="this.style.background='none';this.style.color='#888';this.style.borderColor='#d1d5db';">
              <i class="fa fa-external-link-alt"></i> Buka
            </a>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="padding:9px 18px;border-top:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:10px;color:#cbd5e1;">
          Dibuat dengan <i class="fa fa-heart" style="color:#ef4444;"></i> di Indonesia
        </span>
        <button onclick="closeModal('m-about')"
          style="padding:5px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;font-size:11px;cursor:pointer;color:#64748b;font-family:inherit;transition:all .18s;"
          onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f1f5f9';">
          <i class="fa fa-times"></i> Tutup
        </button>
      </div>
    </div>

  </div>
</div>

<script>
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-check"></i> Disalin!';
    btn.style.background = '#26B99A'; btn.style.color = '#fff'; btn.style.borderColor = '#26B99A';
    setTimeout(() => {
      btn.innerHTML = orig;
      btn.style.background = 'none'; btn.style.color = '#888'; btn.style.borderColor = '#d1d5db';
    }, 2000);
  });
}

/* ── Sidebar toggle ── */
function toggleSidebar() {
  const sb   = document.getElementById('sidebar');
  const main = document.getElementById('main');
  const tn   = document.getElementById('topnav');
  if (!sb) return;
  sb.classList.toggle('collapsed');
  if (main) main.classList.toggle('sb-collapsed');
  if (tn)   tn.classList.toggle('sb-collapsed');
  try { localStorage.setItem('sb_collapsed', sb.classList.contains('collapsed') ? '1' : '0'); } catch(e) {}
}

/* ── Restore sidebar state ── */
(function() {
  try {
    if (localStorage.getItem('sb_collapsed') === '1') {
      const sb   = document.getElementById('sidebar');
      const main = document.getElementById('main');
      const tn   = document.getElementById('topnav');
      if (sb)   sb.classList.add('collapsed');
      if (main) main.classList.add('sb-collapsed');
      if (tn)   tn.classList.add('sb-collapsed');
    }
  } catch(e) {}
})();

/* ── Modal open / close ── */
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
}
// Tutup modal klik overlay
document.addEventListener('click', function(e) {
  if (e.target && e.target.classList.contains('modal-ov')) closeModal(e.target.id);
});
// Tutup modal tekan Esc
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-ov').forEach(function(m) {
      if (m.style.display === 'flex') closeModal(m.id);
    });
  }
});

/* ── Dropdown user topnav ── */
function toggleDropdown(el) {
  el.classList.toggle('open');
}
document.addEventListener('click', function(e) {
  document.querySelectorAll('.tn-user.open').forEach(function(d) {
    if (!d.contains(e.target)) d.classList.remove('open');
  });
});

/* ── Flash alert auto-dismiss ── */
setTimeout(function() {
  document.querySelectorAll('.alert').forEach(function(a) {
    a.style.transition = 'opacity .4s';
    a.style.opacity = '0';
    setTimeout(function() { if (a.parentNode) a.parentNode.removeChild(a); }, 400);
  });
}, 4000);

/* ── Collapsible nav group ── */
function toggleNavGroup(id, btn) {
  const body = document.getElementById(id);
  if (!body) return;
  const isOpen = !body.classList.contains('collapsed');
  if (isOpen) {
    body.classList.add('collapsed');
    btn.classList.remove('open');
  } else {
    body.classList.remove('collapsed');
    btn.classList.add('open');
  }
}

/* ── Smooth accordion untuk <details> ── */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('details.nav-group').forEach(function(det) {
    det.addEventListener('click', function(e) {
      if (!e.target.closest('summary')) return;

      var bd = det.querySelector('.nav-group-bd');
      if (!bd) return;

      if (det.open) {
        /* ── Menutup: animasi slideUp ── */
        e.preventDefault();
        bd.classList.remove('anim-open');
        bd.classList.add('anim-close');
        setTimeout(function() {
          det.removeAttribute('open');
          bd.classList.remove('anim-close');
        }, 160);
      } else {
        /* ── Membuka: animasi slideDown ── */
        /* Biarkan browser buka dulu, baru tambah kelas animasi */
        requestAnimationFrame(function() {
          bd.classList.remove('anim-close');
          bd.classList.add('anim-open');
          bd.addEventListener('animationend', function onEnd() {
            bd.classList.remove('anim-open');
            bd.removeEventListener('animationend', onEnd);
          });
        });
      }
    });
  });
});

</script>

<!-- ========== MAIN ========== -->
<div class="main" id="main">