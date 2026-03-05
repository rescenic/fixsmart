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
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= $_css_url ?>" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   GLOBAL BASE — Nunito font, exclude FA icons
══════════════════════════════════════════════ */
body, p, span:not(.fa):not(.fas):not(.far):not(.fab):not(.fal),
div, h1, h2, h3, h4, h5, h6, a, li, ul, ol, td, th, label,
input, button, select, textarea, table {
  font-family: 'Nunito', sans-serif !important;
}
/* Pastikan Font Awesome tidak ter-override */
.fa, .fas, .far, .fab, .fal, .fad,
[class^="fa-"], [class*=" fa-"],
i.fa, i.fas, i.far, i.fab {
  font-family: 'Font Awesome 6 Free', 'Font Awesome 6 Brands' !important;
}

/* ══════════════════════════════════════════════
   SIDEBAR — soft redesign
══════════════════════════════════════════════ */
.sidebar {
  background: #0f1923 !important;
  border-right: 1px solid rgba(255,255,255,.04) !important;
}

/* Logo area */
.sb-logo {
  padding: 20px 16px 16px !important;
  border-bottom: 1px solid rgba(255,255,255,.05) !important;
}
.logo-text { font-size: 14px !important; font-weight: 700 !important; color: #f1f5f9 !important; letter-spacing: .2px !important; }
.logo-sub  { font-size: 10px !important; color: rgba(255,255,255,.3) !important; margin-top: 1px !important; }

/* User card */
.sb-user {
  margin: 10px 10px 6px !important;
  background: rgba(255,255,255,.04) !important;
  border: 1px solid rgba(255,255,255,.06) !important;
  border-radius: 10px !important;
  padding: 10px 12px !important;
}
.uname { font-size: 12px !important; font-weight: 600 !important; color: #e2e8f0 !important; }
.urole { font-size: 10px !important; color: rgba(255,255,255,.35) !important; margin-top: 2px !important; }

/* ── Nav item ── */
.nav-item a {
  display: flex; align-items: center; gap: 9px;
  padding: 8px 14px !important;
  margin: 1px 8px !important;
  border-radius: 8px !important;
  font-size: 12.5px !important; font-weight: 500 !important;
  color: rgba(255,255,255,.5) !important;
  text-decoration: none !important;
  transition: background .18s ease, color .18s ease, transform .15s ease !important;
  white-space: nowrap; overflow: hidden;
}
.nav-item a:hover {
  background: rgba(255,255,255,.06) !important;
  color: rgba(255,255,255,.88) !important;
  transform: translateX(2px) !important;
}
.nav-item.active a {
  background: linear-gradient(135deg, rgba(38,185,154,.18), rgba(38,185,154,.08)) !important;
  color: #2ed8af !important;
  border-left: 2px solid #26B99A !important;
  font-weight: 600 !important;
}
.nav-item a .ni { width: 16px; text-align: center; font-size: 13px; flex-shrink: 0; opacity: .7; }
.nav-item.active a .ni { opacity: 1; }
.nav-item a .nl { flex: 1; overflow: hidden; text-overflow: ellipsis; }

/* Badge count */
.nc {
  font-size: 9.5px !important; font-weight: 700 !important;
  background: #ef4444 !important; color: #fff !important;
  padding: 1px 6px !important; border-radius: 20px !important;
  line-height: 1.6 !important; flex-shrink: 0 !important;
}

/* ── Accordion nav group ── */
.nav-group { border: none; }

details.nav-group > summary.nav-group-hd {
  display: flex; align-items: center; gap: 9px;
  padding: 8px 14px 8px 14px;
  margin: 1px 8px;
  border-radius: 8px;
  font-size: 11px; font-weight: 600;
  color: rgba(255,255,255,.38);
  text-transform: uppercase; letter-spacing: .6px;
  cursor: pointer; list-style: none; user-select: none;
  transition: background .18s ease, color .18s ease;
}
details.nav-group > summary.nav-group-hd::-webkit-details-marker { display: none; }
details.nav-group > summary.nav-group-hd:hover {
  background: rgba(255,255,255,.04);
  color: rgba(255,255,255,.65);
}
details.nav-group[open] > summary.nav-group-hd {
  color: #26B99A;
  background: rgba(38,185,154,.07);
}
details.nav-group > summary.nav-group-hd .ni-grp {
  width: 16px; text-align: center; font-size: 13px; flex-shrink: 0;
}
details.nav-group > summary.nav-group-hd > span {
  flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
details.nav-group > summary.nav-group-hd .nc-grp {
  font-size: 9.5px; font-weight: 700;
  background: #ef4444; color: #fff;
  padding: 1px 6px; border-radius: 20px; flex-shrink: 0;
}
/* Caret — soft rotation */
details.nav-group > summary.nav-group-hd .caret-grp {
  font-size: 9px;
  color: rgba(255,255,255,.2);
  flex-shrink: 0;
  transition: transform .25s cubic-bezier(.4,0,.2,1), color .2s ease;
  will-change: transform;
}
details.nav-group[open] > summary.nav-group-hd .caret-grp {
  transform: rotate(180deg);
  color: #26B99A;
}

/* ── Submenu body ── */
.nav-group-bd {
  overflow: hidden;
  will-change: max-height, opacity;
}
.nav-group-bd .nav-item a {
  padding-left: 38px !important;
  font-size: 12px !important;
  color: rgba(255,255,255,.42) !important;
}
.nav-group-bd .nav-item a:hover {
  padding-left: 41px !important;
  color: rgba(255,255,255,.82) !important;
}
.nav-group-bd .nav-item.active a {
  color: #2ed8af !important;
}

/* JS-driven smooth slide */
.nav-group-bd.is-sliding {
  transition: max-height .3s cubic-bezier(.4,0,.2,1), opacity .25s ease;
}
.nav-group-bd.bd-closed {
  max-height: 0 !important;
  opacity: 0;
}
.nav-group-bd.bd-open {
  opacity: 1;
}

/* Hide old toggle remnants */
.nav-group-toggle { display: none !important; }
.nav-group-body   { display: none !important; }

/* ── Scrollbar slim ── */
.sidebar::-webkit-scrollbar { width: 3px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 10px; }

/* ── Section divider label ── */
.nav-section-label {
  font-size: 9.5px; font-weight: 700;
  color: rgba(255,255,255,.18);
  text-transform: uppercase; letter-spacing: 1px;
  padding: 14px 22px 4px;
}

/* ── Profile item ── */
.nav-item.profil-item a {
  margin-top: 2px !important;
}

/* ── FS About button ── */
.fs-about-btn {
  display: flex; align-items: center; gap: 10px;
  width: calc(100% - 20px); margin: 0 10px 6px;
  padding: 9px 12px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 10px;
  cursor: pointer; font-family: inherit; text-decoration: none;
  transition: background .2s ease, border-color .2s ease, transform .15s ease;
}
.fs-about-btn:hover {
  background: rgba(38,185,154,.12);
  border-color: rgba(38,185,154,.3);
  transform: translateY(-1px);
}
.fs-logo-badge {
  width: 30px; height: 30px;
  background: rgba(38,185,154,.15);
  border: 1.5px solid rgba(38,185,154,.35);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  font-size: 10px; font-weight: 900; color: #26B99A; letter-spacing: -0.5px;
  transition: all .2s;
}
.fs-about-btn:hover .fs-logo-badge { background: rgba(38,185,154,.28); border-color: #26B99A; }
.fs-btn-text { flex: 1; }
.fs-btn-label { font-size: 11.5px; font-weight: 600; color: rgba(255,255,255,.7); line-height: 1.2; }
.fs-btn-sub   { font-size: 9.5px; color: rgba(255,255,255,.25); margin-top: 1px; }
.fs-about-btn:hover .fs-btn-label { color: rgba(255,255,255,.9); }
.fs-about-btn:hover .fs-btn-sub   { color: rgba(255,255,255,.45); }
.fs-chevron { color: rgba(255,255,255,.15); font-size: 10px; transition: all .2s; }
.fs-about-btn:hover .fs-chevron   { color: rgba(38,185,154,.7); transform: translateX(2px); }

/* ── Sidebar bottom ── */
.sb-bottom {
  border-top: 1px solid rgba(255,255,255,.05) !important;
  padding-top: 8px !important;
}

/* ── Sidebar collapsed ── */
@media (min-width: 769px) {
  .sidebar.collapsed { width: 0; min-width: 0; overflow: hidden; }
  .main.sb-collapsed,
  .topnav.sb-collapsed { margin-left: 0 !important; }
}

/* ══════════════════════════════════════════════
   TOP NAV — softer
══════════════════════════════════════════════ */
.topnav {
  background: #ffffff !important;
  border-bottom: 1px solid rgba(0,0,0,.06) !important;
  box-shadow: 0 1px 8px rgba(0,0,0,.05) !important;
}
.tn-title { font-size: 13.5px !important; font-weight: 600 !important; color: #374151 !important; }

/* ══════════════════════════════════════════════
   MODAL ABOUT — unchanged logic, slightly refined
══════════════════════════════════════════════ */
@keyframes mIn {
  from { opacity: 0; transform: translateY(10px) scale(.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<nav class="sidebar" id="sidebar">

  <!-- Logo -->
  <div class="sb-logo">
    <div style="display:flex;align-items:center;gap:10px;">
      <div class="logo-icon" style="width:34px;height:34px;background:linear-gradient(135deg,#26B99A,#1a8a6e);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-desktop" style="color:#fff;font-size:15px;"></i>
      </div>
      <div>
        <div class="logo-text"><?= APP_NAME ?></div>
        <div class="logo-sub">Work Order System</div>
      </div>
    </div>
  </div>

  <!-- Info User Login -->
  <div class="sb-user">
    <div style="display:flex;align-items:center;gap:9px;">
      <div class="av av-sm" style="width:30px;height:30px;font-size:11px;font-weight:700;border-radius:50%;background:linear-gradient(135deg,#26B99A,#1a6e55);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <?= getInitials($_SESSION['user_nama']) ?>
      </div>
      <div style="min-width:0;">
        <div class="uname"><?= clean($_SESSION['user_nama']) ?></div>
        <?php
        $_role_label_map = [
            'admin'         => 'Admin',
            'teknisi'       => 'Teknisi IT',
            'teknisi_ipsrs' => 'Teknisi IPSRS',
            'user'          => 'User',
        ];
        $_role_display = $_role_label_map[$_SESSION['user_role']] ?? ucfirst($_SESSION['user_role']);
        ?>
        <div class="urole"><?= $_role_display ?> &mdash; <?= clean($_SESSION['user_divisi'] ?? '-') ?></div>
      </div>
    </div>
  </div>

  <?php
  // ── Hitung badge notifikasi (hanya untuk role yang membutuhkan) ──────────────
  $_cur_role = $_SESSION['user_role'] ?? 'user';

  // Badge tiket IT menunggu — hanya teknisi & admin
  $cnt_menunggu = 0;
  if (in_array($_cur_role, ['teknisi', 'admin'])) {
      $cnt_menunggu = (int)$pdo->query("SELECT COUNT(*) FROM tiket WHERE status='menunggu'")->fetchColumn();
  }

  // Badge maintenance IT urgent — hanya teknisi & admin
  $cnt_mnt_urgent = 0;
  if (in_array($_cur_role, ['teknisi', 'admin'])) {
      try {
          $cnt_mnt_urgent = (int)$pdo->query("
              SELECT COUNT(DISTINCT aset_id) FROM maintenance_it
              WHERE id IN (SELECT MAX(id) FROM maintenance_it GROUP BY aset_id)
                AND tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)
          ")->fetchColumn();
      } catch(Exception $e) {}
  }

  // Badge koneksi offline — hanya teknisi & admin
  $cnt_offline = 0;
  if (in_array($_cur_role, ['teknisi', 'admin'])) {
      try {
          $cnt_offline = (int)$pdo->query("
              SELECT COUNT(*) FROM koneksi_monitor m
              WHERE aktif = 1
                AND (SELECT status FROM koneksi_log WHERE monitor_id = m.id ORDER BY cek_at DESC LIMIT 1) = 'offline'
          ")->fetchColumn();
      } catch(Exception $e) {}
  }

  // Badge tiket aktif milik user (role=user saja)
  $cnt_user_aktif = 0;
  $cnt_user_ipsrs_aktif = 0;
  if ($_cur_role === 'user') {
      $st2 = $pdo->prepare("SELECT COUNT(*) FROM tiket WHERE user_id=? AND status IN ('menunggu','diproses')");
      $st2->execute([$_SESSION['user_id']]);
      $cnt_user_aktif = (int)$st2->fetchColumn();

      try {
          $st3 = $pdo->prepare("SELECT COUNT(*) FROM tiket_ipsrs WHERE user_id=? AND status IN ('menunggu','diproses')");
          $st3->execute([$_SESSION['user_id']]);
          $cnt_user_ipsrs_aktif = (int)$st3->fetchColumn();
      } catch(Exception $e) {}
  }

  // Badge tiket IPSRS menunggu — hanya teknisi_ipsrs & admin
  $cnt_ipsrs_menunggu = 0;
  if (in_array($_cur_role, ['teknisi_ipsrs', 'admin'])) {
      try {
          $cnt_ipsrs_menunggu = (int)$pdo->query("SELECT COUNT(*) FROM tiket_ipsrs WHERE status='menunggu'")->fetchColumn();
      } catch(Exception $e) {}
  }

  // Badge maintenance IPSRS urgent — hanya teknisi_ipsrs & admin
  $cnt_mnt_ipsrs_urgent = 0;
  if (in_array($_cur_role, ['teknisi_ipsrs', 'admin'])) {
      try {
          $cnt_mnt_ipsrs_urgent = (int)$pdo->query("
              SELECT COUNT(DISTINCT aset_id) FROM maintenance_ipsrs
              WHERE id IN (SELECT MAX(id) FROM maintenance_ipsrs GROUP BY aset_id)
                AND tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)
          ")->fetchColumn();
      } catch(Exception $e) {}
  }

  // ── Deteksi grup aktif untuk auto-expand ──────────────────────────────────
  // PENTING: 'dashboard' TIDAK dimasukkan ke $grp_tiket_it agar
  // grup Tiket IT tidak ikut ter-expand saat berada di halaman Dashboard.
  $grp_tiket_it    = in_array($active_menu??'', ['antrian','semua_tiket','sla']);
  $grp_tiket_ipsrs = in_array($active_menu??'', ['antrian_ipsrs','semua_tiket_ipsrs','sla_ipsrs']);
  $grp_aset_it     = in_array($active_menu??'', ['aset_it','maintenance_it','cek_koneksi','server_room']);
  $grp_aset_ipsrs  = in_array($active_menu??'', ['aset_ipsrs','maintenance_ipsrs']);
  $grp_master      = in_array($active_menu??'', ['kategori','kategori_ipsrs','bagian','users','login_log']);
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

  <!-- Aset IT -->
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

  <!-- Aset IPSRS -->
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
      <div class="nav-item <?= ($active_menu??'')==='login_log'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/login_log.php">
          <i class="fa fa-shield-halved ni" style="color:#8b5cf6;"></i>
          <span class="nl">Log Login</span>
        </a>
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
              ? '<span style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block;margin-left:2px;flex-shrink:0;"></span>'
              : '<span style="width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.2);display:inline-block;margin-left:2px;flex-shrink:0;"></span>';
          } catch(Exception $e) {} ?>
        </a>
      </div>
    </div>
  </details>

  <?php endif; // end role check ?>

  <!-- Profil (semua role) -->
  <div class="nav-item profil-item <?= ($active_menu??'')==='profil'?'active':'' ?>">
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
        <i class="fa fa-sign-out-alt ni" style="color:#f87171;"></i>
        <span class="nl" style="color:#f87171 !important;">Keluar</span>
      </a>
    </div>
  </div>

</nav><!-- /sidebar -->


<!-- ========== TOP NAV ========== -->
<div class="topnav" id="topnav">
  <div class="tn-left">
    <button class="btn-toggle" onclick="toggleSidebar()" style="background:none;border:none;cursor:pointer;padding:6px 8px;border-radius:6px;color:#6b7280;font-size:16px;transition:background .15s,color .15s;"
      onmouseover="this.style.background='#f3f4f6';this.style.color='#374151';"
      onmouseout="this.style.background='none';this.style.color='#6b7280';">
      <i class="fa fa-bars"></i>
    </button>
    <span class="tn-title"><?= APP_NAME ?></span>
  </div>
  <div class="tn-right">
    <?php if (hasRole(['admin','teknisi']) && $cnt_menunggu): ?>
    <a href="<?= APP_URL ?>/pages/antrian.php"
       style="display:flex;align-items:center;gap:5px;font-size:12px;color:#f59e0b;font-weight:600;text-decoration:none;background:#fffbeb;border:1px solid #fde68a;padding:4px 10px;border-radius:20px;transition:all .18s;"
       onmouseover="this.style.background='#fef9c3';"
       onmouseout="this.style.background='#fffbeb';">
      <i class="fa fa-bell" style="font-size:11px;"></i> <?= $cnt_menunggu ?> tiket IT
    </a>
    <?php endif; ?>
    <?php if (hasRole(['admin','teknisi_ipsrs']) && $cnt_ipsrs_menunggu): ?>
    <a href="<?= APP_URL ?>/pages/antrian_ipsrs.php"
       style="display:flex;align-items:center;gap:5px;font-size:12px;color:#f59e0b;font-weight:600;text-decoration:none;background:#fffbeb;border:1px solid #fde68a;padding:4px 10px;border-radius:20px;transition:all .18s;"
       onmouseover="this.style.background='#fef9c3';"
       onmouseout="this.style.background='#fffbeb';">
      <i class="fa fa-bell" style="font-size:11px;"></i> <?= $cnt_ipsrs_menunggu ?> tiket IPSRS
    </a>
    <?php endif; ?>

    <!-- Tombol Tentang -->
    <button onclick="openModal('m-about')" title="Tentang Aplikasi"
      style="display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:12px;color:#6b7280;font-family:inherit;font-weight:500;transition:all .2s;"
      onmouseover="this.style.background='#26B99A';this.style.color='#fff';this.style.borderColor='#26B99A';"
      onmouseout="this.style.background='#fff';this.style.color='#6b7280';this.style.borderColor='#e5e7eb';">
      <i class="fa fa-circle-info" style="font-size:12px;"></i>
      <span>Tentang</span>
    </button>

    <!-- Dropdown user -->
    <div class="tn-user" onclick="toggleDropdown(this)">
      <div class="tn-user-info">
        <div class="av-sm" style="width:30px;height:30px;font-size:11px;font-weight:700;border-radius:50%;background:linear-gradient(135deg,#26B99A,#1a6e55);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <?= getInitials($_SESSION['user_nama']) ?>
        </div>
        <span style="font-size:12.5px;font-weight:500;color:#374151;"><?= clean($_SESSION['user_nama']) ?></span>
        <i class="fa fa-chevron-down caret" style="font-size:9px;color:#9ca3af;transition:transform .2s;"></i>
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
  <div style="background:#fff;width:100%;max-width:780px;border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.18);overflow:hidden;display:flex;animation:mIn .22s cubic-bezier(.4,0,.2,1);">

    <!-- Kolom Kiri -->
    <div style="width:260px;flex-shrink:0;background:linear-gradient(175deg,#0f1923 0%,#162435 55%,#0f2a1f 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px 20px;text-align:center;">
      <div style="width:64px;height:64px;background:rgba(38,185,154,.12);border:1.5px solid rgba(38,185,154,.35);border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:13px;">
        <i class="fa fa-desktop" style="font-size:28px;color:#26B99A;"></i>
      </div>
      <div style="color:#f1f5f9;font-size:16px;font-weight:700;letter-spacing:.2px;line-height:1.2;">FixSmart Helpdesk</div>
      <div style="color:rgba(255,255,255,.35);font-size:10px;margin-top:4px;">Work Order System</div>
      <div style="margin-top:9px;background:rgba(38,185,154,.15);border:1px solid rgba(38,185,154,.3);color:#5eead4;padding:2px 12px;border-radius:20px;font-size:10px;font-weight:700;">
        Versi 1.0.0
      </div>
      <div style="width:100%;height:1px;background:rgba(255,255,255,.07);margin:16px 0;"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;width:100%;">
        <?php foreach ([
          ['fa-ticket-alt',        '#60a5fa', 'Tiket'],
          ['fa-chart-line',        '#a78bfa', 'SLA'],
          ['fa-users',             '#fbbf24', 'Multi Role'],
          ['fa-paper-plane',       '#38bdf8', 'Telegram'],
          ['fa-screwdriver-wrench','#34d399', 'Maintenance'],
          ['fa-shield-alt',        '#f87171', 'Keamanan'],
        ] as [$ic,$cl,$lb]): ?>
        <div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.06);border-radius:7px;padding:6px 5px;font-size:10px;color:rgba(255,255,255,.65);display:flex;align-items:center;gap:6px;">
          <i class="fa <?= $ic ?>" style="color:<?= $cl ?>;font-size:11px;width:13px;text-align:center;flex-shrink:0;"></i><?= $lb ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="width:100%;height:1px;background:rgba(255,255,255,.07);margin:14px 0;"></div>
      <div style="text-align:center;">
        <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:.7px;margin-bottom:7px;">Dikembangkan oleh</div>
        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#26B99A,#0f1923);border:1.5px solid rgba(38,185,154,.35);display:flex;align-items:center;justify-content:center;margin:0 auto 6px;">
          <i class="fa fa-user" style="color:#fff;font-size:13px;"></i>
        </div>
        <div style="color:#f1f5f9;font-size:12px;font-weight:600;">M. Wira Satria Buana, S. Kom</div>
        <div style="color:rgba(255,255,255,.35);font-size:10px;margin-top:3px;">
          <i class="fa fa-envelope" style="color:#26B99A;"></i> wiramuhammad16@gmail.com
        </div>
        <div style="color:rgba(255,255,255,.35);font-size:10px;margin-top:3px;">
          <i class="fa fa-phone" style="color:#26B99A;"></i> 0821 7784 6209
        </div>
      </div>
      <div style="margin-top:14px;font-size:10px;color:rgba(255,255,255,.2);line-height:1.6;">
        &copy; 2025 FixSmart &bull; PHP + MySQL<br>Open Source &amp; Free Forever
      </div>
    </div>

    <!-- Kolom Kanan -->
    <div style="flex:1;display:flex;flex-direction:column;min-height:0;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px 10px;border-bottom:1px solid #f1f5f9;">
        <div style="font-size:13px;font-weight:700;color:#1e293b;">
          <i class="fa fa-circle-info" style="color:#26B99A;"></i> &nbsp;Tentang Aplikasi
        </div>
        <button onclick="closeModal('m-about')"
          style="width:26px;height:26px;border-radius:50%;background:#f3f4f6;border:none;cursor:pointer;color:#9ca3af;font-size:12px;display:flex;align-items:center;justify-content:center;transition:all .18s;"
          onmouseover="this.style.background='#ef4444';this.style.color='#fff';"
          onmouseout="this.style.background='#f3f4f6';this.style.color='#9ca3af';">
          <i class="fa fa-times"></i>
        </button>
      </div>
      <div style="flex:1;padding:14px 18px;display:flex;flex-direction:column;gap:11px;overflow-y:auto;">
        <p style="font-size:11.5px;color:#64748b;line-height:1.75;margin:0;">
          Sistem manajemen tiket IT berbasis web untuk membantu pengelolaan <em>work order</em>,
          pelacakan SLA, dan pelaporan kinerja tim IT. Ringan, mudah dikustomisasi, dan siap pakai.
        </p>
        <div style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1px solid #a7f3d0;border-radius:10px;padding:10px 13px;display:flex;align-items:center;gap:10px;">
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
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:11px 13px;">
          <div style="display:flex;align-items:center;gap:7px;margin-bottom:8px;">
            <i class="fa fa-heart" style="color:#ef4444;font-size:14px;"></i>
            <span style="font-size:12px;font-weight:700;color:#92400e;">Dukung Pengembangan</span>
            <span style="font-size:10px;color:#a16207;margin-left:auto;">Sukarela &amp; ikhlas &#9749;</span>
          </div>
          <?php foreach ([
            ['#6c3db5','fa fa-wallet','OVO / DANA', '0821 7784 6209','0821 7784 6209',null],
            ['#00703c','',            'Bank BSI',   '7134197557',    '7134197557',    null],
          ] as [$bg,$ic,$lbl,$display,$copy,$link]): ?>
          <div style="display:flex;align-items:center;gap:9px;background:#fff;border:1px solid #fde68a;border-radius:7px;padding:8px 10px;margin-bottom:6px;">
            <div style="width:26px;height:26px;border-radius:6px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <?php if ($ic): ?>
              <i class="<?= $ic ?>" style="color:#fff;font-size:11px;"></i>
              <?php else: ?>
              <span style="color:#fff;font-size:9px;font-weight:900;">BSI</span>
              <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:10px;font-weight:600;color:#6b7280;"><?= $lbl ?></div>
              <div style="font-size:11.5px;color:#1e293b;font-family:monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= $display ?></div>
            </div>
            <?php if ($copy): ?>
            <button onclick="copyText('<?= $copy ?>',this)"
              style="background:none;border:1px solid #e5e7eb;border-radius:5px;padding:4px 9px;font-size:10px;cursor:pointer;color:#9ca3af;font-family:inherit;white-space:nowrap;flex-shrink:0;transition:all .18s;"
              onmouseover="this.style.background='#26B99A';this.style.color='#fff';this.style.borderColor='#26B99A';"
              onmouseout="this.style.background='none';this.style.color='#9ca3af';this.style.borderColor='#e5e7eb';">
              <i class="fa fa-copy"></i> Salin
            </button>
            <?php else: ?>
            <a href="<?= $link ?>" target="_blank"
              style="background:none;border:1px solid #e5e7eb;border-radius:5px;padding:4px 9px;font-size:10px;cursor:pointer;color:#9ca3af;text-decoration:none;white-space:nowrap;flex-shrink:0;transition:all .18s;"
              onmouseover="this.style.background='#26B99A';this.style.color='#fff';this.style.borderColor='#26B99A';"
              onmouseout="this.style.background='none';this.style.color='#9ca3af';this.style.borderColor='#e5e7eb';">
              <i class="fa fa-external-link-alt"></i> Buka
            </a>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="padding:9px 18px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:10px;color:#cbd5e1;">
          Dibuat dengan <i class="fa fa-heart" style="color:#ef4444;"></i> di Indonesia
        </span>
        <button onclick="closeModal('m-about')"
          style="padding:5px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:11px;cursor:pointer;color:#64748b;font-family:inherit;font-weight:500;transition:all .18s;"
          onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f8fafc';">
          <i class="fa fa-times"></i> Tutup
        </button>
      </div>
    </div>

  </div>
</div>

<script>
/* ── Copy to clipboard ── */
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-check"></i> Disalin!';
    btn.style.background = '#26B99A'; btn.style.color = '#fff'; btn.style.borderColor = '#26B99A';
    setTimeout(() => {
      btn.innerHTML = orig;
      btn.style.background = 'none'; btn.style.color = '#9ca3af'; btn.style.borderColor = '#e5e7eb';
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
document.addEventListener('click', function(e) {
  if (e.target && e.target.classList.contains('modal-ov')) closeModal(e.target.id);
});
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

/* ══════════════════════════════════════════════
   SMOOTH ACCORDION — max-height interpolation
   Jauh lebih lembut dari animasi keyframe
══════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('details.nav-group').forEach(function(det) {
    var bd = det.querySelector('.nav-group-bd');
    if (!bd) return;

    /* Set initial max-height for already-open groups */
    if (det.open) {
      bd.style.maxHeight = bd.scrollHeight + 'px';
      bd.style.opacity   = '1';
    } else {
      bd.style.maxHeight = '0';
      bd.style.opacity   = '0';
      bd.style.overflow  = 'hidden';
    }

    det.addEventListener('click', function(e) {
      if (!e.target.closest('summary')) return;
      e.preventDefault();

      var isOpen = det.open;

      if (isOpen) {
        /* ── CLOSING ── */
        bd.style.transition = 'max-height .28s cubic-bezier(.4,0,.2,1), opacity .22s ease';
        bd.style.maxHeight  = bd.scrollHeight + 'px';
        /* Trigger reflow */
        bd.offsetHeight;
        bd.style.maxHeight = '0';
        bd.style.opacity   = '0';
        bd.addEventListener('transitionend', function onClose() {
          bd.removeEventListener('transitionend', onClose);
          det.removeAttribute('open');
        });
      } else {
        /* ── OPENING ── */
        det.setAttribute('open', '');
        bd.style.transition = 'none';
        bd.style.maxHeight  = '0';
        bd.style.opacity    = '0';
        /* Trigger reflow */
        bd.offsetHeight;
        bd.style.transition = 'max-height .3s cubic-bezier(.4,0,.2,1), opacity .25s ease';
        bd.style.maxHeight  = bd.scrollHeight + 'px';
        bd.style.opacity    = '1';
        bd.addEventListener('transitionend', function onOpen() {
          bd.removeEventListener('transitionend', onOpen);
          bd.style.maxHeight = 'none'; /* Let content grow freely */
        });
      }
    });
  });
});
</script>

<!-- ========== MAIN ========== -->
<div class="main" id="main">