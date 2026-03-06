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
/* ═══════════════════════════════════════════════════════
   CSS VARIABLES
═══════════════════════════════════════════════════════ */
:root {
  --sb-width: 220px;
  --sb-width-collapsed: 0px;
  --topnav-h: 54px;
  --primary: #26B99A;
  --primary-dark: #1a8a6e;
  --primary-soft: rgba(38,185,154,.12);
  --primary-border: rgba(38,185,154,.25);
  --sb-bg: #111c27;
  --sb-text: rgba(255,255,255,.55);
  --sb-text-hover: rgba(255,255,255,.9);
  --sb-text-active: #2ed8af;
  --sb-item-hover: rgba(255,255,255,.05);
  --sb-active-bg: rgba(38,185,154,.12);
  --radius-sm: 7px;
  --radius-md: 10px;
  --transition: .18s ease;
}

/* ═══════════════════════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; }

body, p, span:not(.fa):not(.fas):not(.far):not(.fab):not(.fal),
div, h1, h2, h3, h4, h5, h6, a, li, ul, ol, td, th, label,
input, button, select, textarea, table {
  font-family: 'Nunito', sans-serif !important;
}
.fa,.fas,.far,.fab,.fal,.fad,[class^="fa-"],[class*=" fa-"],i.fa,i.fas,i.far,i.fab {
  font-family: 'Font Awesome 6 Free','Font Awesome 6 Brands' !important;
}

/* ═══════════════════════════════════════════════════════
   LAYOUT SHELL
═══════════════════════════════════════════════════════ */
.app-shell {
  display: flex;
  min-height: 100vh;
}

/* ═══════════════════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════════════════ */
.sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  width: var(--sb-width);
  background: var(--sb-bg);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  overflow-x: hidden;
  z-index: 300;
  transition: transform var(--transition), width var(--transition);
  border-right: 1px solid rgba(255,255,255,.04);
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.08) transparent;
}
.sidebar::-webkit-scrollbar { width: 3px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 99px; }

/* ── Logo ── */
.sb-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 16px 14px;
  border-bottom: 1px solid rgba(255,255,255,.05);
  flex-shrink: 0;
}
.sb-logo-icon {
  width: 34px; height: 34px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(38,185,154,.3);
}
.sb-logo-icon i { color: #fff; font-size: 14px; }
.sb-logo-name { font-size: 13px; font-weight: 700; color: #f0f4f8; letter-spacing: .1px; line-height: 1.2; }
.sb-logo-sub  { font-size: 10px; color: rgba(255,255,255,.28); margin-top: 1px; }

/* ── User card ── */
.sb-user {
  margin: 10px 10px 6px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: var(--radius-md);
  padding: 9px 11px;
  display: flex;
  align-items: center;
  gap: 9px;
}
.sb-user-av {
  width: 30px; height: 30px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sb-user-name { font-size: 12px; font-weight: 600; color: #e2e8f0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-user-role { font-size: 10px; color: rgba(255,255,255,.3); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Nav scroll area ── */
.sb-nav {
  flex: 1;
  padding: 4px 0 8px;
  overflow-y: auto;
  overflow-x: hidden;
}

/* ── Nav item ── */
.nav-item a {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 13px;
  margin: 1px 8px;
  border-radius: var(--radius-sm);
  font-size: 12.5px;
  font-weight: 500;
  color: var(--sb-text) !important;
  text-decoration: none !important;
  transition: background var(--transition), color var(--transition), transform var(--transition);
  white-space: nowrap;
  overflow: hidden;
}
.nav-item a:hover {
  background: var(--sb-item-hover) !important;
  color: var(--sb-text-hover) !important;
  transform: translateX(2px);
}
.nav-item.active a {
  background: var(--sb-active-bg) !important;
  color: var(--sb-text-active) !important;
  border-left: 2px solid var(--primary);
  font-weight: 600;
}
.nav-item a .ni { width: 16px; text-align: center; font-size: 12.5px; flex-shrink: 0; opacity: .7; }
.nav-item.active a .ni { opacity: 1; }
.nav-item a .nl { flex: 1; overflow: hidden; text-overflow: ellipsis; }

/* Badge count */
.nc {
  font-size: 9.5px !important; font-weight: 700 !important;
  background: #ef4444 !important; color: #fff !important;
  padding: 1px 6px !important; border-radius: 20px !important;
  line-height: 1.6 !important; flex-shrink: 0 !important;
}

/* ── Accordion group ── */
details.nav-group { border: none; }
details.nav-group > summary.nav-group-hd {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 13px;
  margin: 1px 8px;
  border-radius: var(--radius-sm);
  font-size: 11px;
  font-weight: 700;
  color: rgba(255,255,255,.35);
  text-transform: uppercase;
  letter-spacing: .55px;
  cursor: pointer;
  list-style: none;
  user-select: none;
  transition: background var(--transition), color var(--transition);
}
details.nav-group > summary.nav-group-hd::-webkit-details-marker { display: none; }
details.nav-group > summary.nav-group-hd:hover {
  background: rgba(255,255,255,.04);
  color: rgba(255,255,255,.65);
}
details.nav-group[open] > summary.nav-group-hd {
  color: var(--primary);
  background: rgba(38,185,154,.07);
}
details.nav-group > summary.nav-group-hd .ni-grp {
  width: 16px; text-align: center; font-size: 12.5px; flex-shrink: 0;
}
details.nav-group > summary.nav-group-hd > span {
  flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.nc-grp {
  font-size: 9.5px; font-weight: 700;
  background: #ef4444; color: #fff;
  padding: 1px 6px; border-radius: 20px; flex-shrink: 0;
}
.caret-grp {
  font-size: 9px;
  color: rgba(255,255,255,.18);
  flex-shrink: 0;
  transition: transform .25s cubic-bezier(.4,0,.2,1), color .2s ease;
}
details.nav-group[open] > summary.nav-group-hd .caret-grp {
  transform: rotate(180deg);
  color: var(--primary);
}

/* ── Submenu ── */
.nav-group-bd { overflow: hidden; }
.nav-group-bd .nav-item a {
  padding-left: 36px !important;
  font-size: 12px !important;
  color: rgba(255,255,255,.4) !important;
}
.nav-group-bd .nav-item a:hover { padding-left: 39px !important; color: rgba(255,255,255,.82) !important; }
.nav-group-bd .nav-item.active a { color: var(--sb-text-active) !important; }

/* ── Bottom area ── */
.sb-bottom {
  flex-shrink: 0;
  border-top: 1px solid rgba(255,255,255,.05);
  padding: 8px 0 4px;
}

/* About button */
.fs-about-btn {
  display: flex; align-items: center; gap: 9px;
  width: calc(100% - 20px); margin: 0 10px 4px;
  padding: 8px 11px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: var(--radius-md);
  cursor: pointer; text-decoration: none;
  transition: background .2s, border-color .2s, transform .15s;
}
.fs-about-btn:hover {
  background: rgba(38,185,154,.1);
  border-color: var(--primary-border);
  transform: translateY(-1px);
}
.fs-logo-badge {
  width: 28px; height: 28px;
  background: var(--primary-soft);
  border: 1.5px solid var(--primary-border);
  border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  font-size: 9.5px; font-weight: 900; color: var(--primary);
  flex-shrink: 0;
}
.fs-btn-label { font-size: 11px; font-weight: 600; color: rgba(255,255,255,.65); line-height: 1.2; }
.fs-btn-sub   { font-size: 9.5px; color: rgba(255,255,255,.22); margin-top: 1px; }
.fs-chevron   { color: rgba(255,255,255,.13); font-size: 9px; margin-left: auto; transition: all .2s; }
.fs-about-btn:hover .fs-chevron { color: rgba(38,185,154,.6); transform: translateX(2px); }

/* ── Sidebar collapsed (desktop) ── */
.sidebar.collapsed {
  transform: translateX(calc(-1 * var(--sb-width)));
}

/* ── Mobile overlay ── */
.sb-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  z-index: 299;
  backdrop-filter: blur(2px);
}
.sb-overlay.active { display: block; }

/* ═══════════════════════════════════════════════════════
   TOP NAV
═══════════════════════════════════════════════════════ */
.topnav {
  position: fixed;
  top: 0;
  left: var(--sb-width);
  right: 0;
  height: var(--topnav-h);
  background: #ffffff;
  border-bottom: 1px solid rgba(0,0,0,.07);
  box-shadow: 0 1px 6px rgba(0,0,0,.04);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  z-index: 200;
  gap: 10px;
  transition: left var(--transition);
}
.topnav.sb-collapsed { left: 0; }

.tn-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.tn-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

/* Toggle btn */
.btn-toggle {
  background: none; border: none; cursor: pointer;
  width: 34px; height: 34px;
  border-radius: var(--radius-sm);
  display: flex; align-items: center; justify-content: center;
  color: #9ca3af;
  font-size: 15px;
  transition: background var(--transition), color var(--transition);
  flex-shrink: 0;
}
.btn-toggle:hover { background: #f3f4f6; color: #374151; }

.tn-title {
  font-size: 13.5px !important;
  font-weight: 600 !important;
  color: #374151 !important;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Alert pill */
.tn-alert-pill {
  display: flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 600;
  text-decoration: none;
  padding: 4px 11px;
  border-radius: 20px;
  border: 1px solid #fde68a;
  background: #fffbeb;
  color: #92400e;
  white-space: nowrap;
  transition: background var(--transition);
}
.tn-alert-pill:hover { background: #fef3c7; }
.tn-alert-pill i { font-size: 11px; }

/* Tentang btn */
.tn-about-btn {
  display: flex; align-items: center; gap: 5px;
  padding: 5px 12px;
  border-radius: 20px;
  border: 1px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  font-size: 12px; color: #6b7280;
  font-weight: 500;
  white-space: nowrap;
  transition: all .2s;
}
.tn-about-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.tn-about-btn i { font-size: 12px; }

/* User dropdown */
.tn-user {
  position: relative;
  cursor: pointer;
  user-select: none;
}
.tn-user-info {
  display: flex; align-items: center; gap: 7px;
  padding: 4px 8px;
  border-radius: var(--radius-sm);
  transition: background var(--transition);
}
.tn-user:hover .tn-user-info,
.tn-user.open .tn-user-info { background: #f3f4f6; }
.tn-user-av {
  width: 30px; height: 30px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.tn-user-name { font-size: 12.5px; font-weight: 500; color: #374151; max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tn-caret { font-size: 9px; color: #9ca3af; transition: transform .2s; }
.tn-user.open .tn-caret { transform: rotate(180deg); }

.tn-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: var(--radius-md);
  box-shadow: 0 8px 24px rgba(0,0,0,.1);
  min-width: 160px;
  overflow: hidden;
  z-index: 500;
}
.tn-user.open .tn-dropdown { display: block; }
.tn-dropdown a {
  display: flex !important;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  font-size: 12.5px;
  color: #374151 !important;
  text-decoration: none;
  transition: background var(--transition);
}
.tn-dropdown a:hover { background: #f8fafc; }
.tn-dropdown a.red-link { color: #ef4444 !important; }
.tn-dropdown a.red-link:hover { background: #fff1f2; }
.tn-dropdown a i { width: 14px; text-align: center; }

/* ═══════════════════════════════════════════════════════
   MAIN CONTENT
═══════════════════════════════════════════════════════ */
.main {
  margin-left: var(--sb-width);
  margin-top: var(--topnav-h);
  min-height: calc(100vh - var(--topnav-h));
  transition: margin-left var(--transition);
  width: calc(100% - var(--sb-width));
  max-width: 100%;
}
.main.sb-collapsed {
  margin-left: 0;
  width: 100%;
}

/* ═══════════════════════════════════════════════════════
   MODAL ABOUT
═══════════════════════════════════════════════════════ */
.modal-ov {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.45);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  padding: 16px;
  backdrop-filter: blur(3px);
}
@keyframes mIn {
  from { opacity:0; transform:translateY(12px) scale(.97); }
  to   { opacity:1; transform:translateY(0) scale(1); }
}

/* ═══════════════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════════════ */

/* ── Tablet (769px – 1024px) ── */
@media (max-width: 1024px) and (min-width: 769px) {
  :root { --sb-width: 200px; }
  .tn-user-name { display: none; }
  .tn-about-btn span { display: none; }
  .tn-about-btn { padding: 5px 9px; }
}

/* ── Mobile (≤768px) ── */
@media (max-width: 768px) {
  :root { --sb-width: 240px; }

  /* Sidebar hides off-screen by default on mobile */
  .sidebar {
    transform: translateX(calc(-1 * var(--sb-width)));
    z-index: 400;
  }
  .sidebar.mobile-open {
    transform: translateX(0);
  }

  /* Topnav full width on mobile */
  .topnav {
    left: 0 !important;
    padding: 0 12px;
  }

  /* Main full width on mobile */
  .main {
    margin-left: 0 !important;
    width: 100% !important;
  }

  /* Hide user name on mobile */
  .tn-user-name { display: none; }

  /* Hide alert pills text on very small screens */
  .tn-alert-pill { padding: 4px 8px; }

  /* About btn icon only on mobile */
  .tn-about-btn span { display: none; }
  .tn-about-btn { padding: 5px 9px; }

  /* Modal full width on mobile */
  #m-about > div {
    flex-direction: column !important;
    max-height: 90vh;
    overflow-y: auto;
  }
  #m-about > div > div:first-child {
    width: 100% !important;
    padding: 20px !important;
    flex-direction: row !important;
    flex-wrap: wrap;
    justify-content: flex-start !important;
    text-align: left !important;
    gap: 12px;
  }
}

/* ── Small phones (≤400px) ── */
@media (max-width: 400px) {
  .tn-alert-pill { display: none; }
  .topnav { padding: 0 8px; gap: 6px; }
}

/* Hide old toggles */
.nav-group-toggle, .nav-group-body { display: none !important; }
</style>
</head>
<body>

<!-- ══ MOBILE OVERLAY ══ -->
<div class="sb-overlay" id="sb-overlay" onclick="closeSidebarMobile()"></div>

<!-- ════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ -->
<nav class="sidebar" id="sidebar">

  <!-- Logo -->
  <div class="sb-logo">
    <div class="sb-logo-icon"><i class="fa fa-desktop"></i></div>
    <div>
      <div class="sb-logo-name"><?= APP_NAME ?></div>
      <div class="sb-logo-sub">Work Order System</div>
    </div>
  </div>

  <!-- User card -->
  <div class="sb-user">
    <div class="sb-user-av"><?= getInitials($_SESSION['user_nama']) ?></div>
    <div style="min-width:0;">
      <div class="sb-user-name"><?= clean($_SESSION['user_nama']) ?></div>
      <?php
      $_role_map = ['admin'=>'Admin','teknisi'=>'Teknisi IT','teknisi_ipsrs'=>'Teknisi IPSRS','user'=>'User'];
      $_role_display = $_role_map[$_SESSION['user_role']] ?? ucfirst($_SESSION['user_role']);
      ?>
      <div class="sb-user-role"><?= $_role_display ?> &mdash; <?= clean($_SESSION['user_divisi'] ?? '-') ?></div>
    </div>
  </div>

  <!-- Nav area -->
  <div class="sb-nav">
  <?php
  // ── Badge counts ──────────────────────────────────────────────────
  $_cur_role = $_SESSION['user_role'] ?? 'user';

  $cnt_menunggu = 0;
  if (in_array($_cur_role, ['teknisi', 'admin']))
      $cnt_menunggu = (int)$pdo->query("SELECT COUNT(*) FROM tiket WHERE status='menunggu'")->fetchColumn();

  $cnt_mnt_urgent = 0;
  if (in_array($_cur_role, ['teknisi', 'admin'])) {
      try { $cnt_mnt_urgent = (int)$pdo->query("
          SELECT COUNT(DISTINCT aset_id) FROM maintenance_it
          WHERE id IN (SELECT MAX(id) FROM maintenance_it GROUP BY aset_id)
            AND tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)
      ")->fetchColumn(); } catch(Exception $e) {}
  }

  $cnt_offline = 0;
  if (in_array($_cur_role, ['teknisi', 'admin'])) {
      try { $cnt_offline = (int)$pdo->query("
          SELECT COUNT(*) FROM koneksi_monitor m
          WHERE aktif=1
            AND (SELECT status FROM koneksi_log WHERE monitor_id=m.id ORDER BY cek_at DESC LIMIT 1)='offline'
      ")->fetchColumn(); } catch(Exception $e) {}
  }

  $cnt_user_aktif = 0; $cnt_user_ipsrs_aktif = 0;
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

  $cnt_ipsrs_menunggu = 0;
  if (in_array($_cur_role, ['teknisi_ipsrs', 'admin'])) {
      try { $cnt_ipsrs_menunggu = (int)$pdo->query("SELECT COUNT(*) FROM tiket_ipsrs WHERE status='menunggu'")->fetchColumn(); } catch(Exception $e) {}
  }

  $cnt_mnt_ipsrs_urgent = 0;
  if (in_array($_cur_role, ['teknisi_ipsrs', 'admin'])) {
      try { $cnt_mnt_ipsrs_urgent = (int)$pdo->query("
          SELECT COUNT(DISTINCT aset_id) FROM maintenance_ipsrs
          WHERE id IN (SELECT MAX(id) FROM maintenance_ipsrs GROUP BY aset_id)
            AND tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)
      ")->fetchColumn(); } catch(Exception $e) {}
  }

  // Active group detection
  $grp_tiket_it    = in_array($active_menu??'', ['antrian','semua_tiket','sla']);
  $grp_tiket_ipsrs = in_array($active_menu??'', ['antrian_ipsrs','semua_tiket_ipsrs','sla_ipsrs']);
  $grp_aset_it     = in_array($active_menu??'', ['aset_it','maintenance_it','cek_koneksi','server_room']);
  $grp_aset_ipsrs  = in_array($active_menu??'', ['aset_ipsrs','maintenance_ipsrs']);
  $grp_master      = in_array($active_menu??'', ['kategori','kategori_ipsrs','bagian','users','login_log']);
  $grp_setting     = in_array($active_menu??'', ['setting_telegram']);
  $grp_tiket_saya  = in_array($active_menu??'', ['buat_tiket','tiket_saya']);
  $grp_tiket_saya_ipsrs = in_array($active_menu??'', ['buat_tiket_sarpras','tiket_saya_ipsrs']);
  ?>

  <!-- ══ USER BIASA ══ -->
  <?php if (hasRole('user')): ?>

    <div class="nav-item <?= ($active_menu??'')==='dashboard'?'active':'' ?>">
      <a href="<?= APP_URL ?>/dashboard.php"><i class="fa fa-home ni"></i><span class="nl">Dashboard</span></a>
    </div>

    <details class="nav-group" <?= $grp_tiket_saya ? 'open' : '' ?>>
      <summary class="nav-group-hd"><i class="fa fa-desktop ni-grp"></i><span>Tiket IT</span><i class="fa fa-chevron-down caret-grp"></i></summary>
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
      <summary class="nav-group-hd"><i class="fa fa-toolbox ni-grp"></i><span>Tiket IPSRS</span><i class="fa fa-chevron-down caret-grp"></i></summary>
      <div class="nav-group-bd">
        <div class="nav-item <?= ($active_menu??'')==='buat_tiket_sarpras'?'active':'' ?>">
          <a href="<?= APP_URL ?>/pages/buat_tiket_sarpras.php"><i class="fa fa-plus-circle ni"></i><span class="nl">Buat Tiket IPSRS</span></a>
        </div>
        <div class="nav-item <?= ($active_menu??'')==='tiket_saya_ipsrs'?'active':'' ?>">
          <a href="<?= APP_URL ?>/pages/tiket_saya_ipsrs.php"><i class="fa fa-list-alt ni"></i><span class="nl">Tiket IPSRS Saya</span>
            <?php if ($cnt_user_ipsrs_aktif): ?><span class="nc"><?= $cnt_user_ipsrs_aktif ?></span><?php endif; ?>
          </a>
        </div>
      </div>
    </details>

  <!-- ══ TEKNISI IT ══ -->
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
            <?php if ($cnt_offline): ?><span class="nc"><?= $cnt_offline ?></span><?php endif; ?>
          </a>
        </div>
        <div class="nav-item <?= ($active_menu??'')==='server_room'?'active':'' ?>">
          <a href="<?= APP_URL ?>/pages/server_room.php"><i class="fa fa-server ni"></i><span class="nl">Monitoring Server</span></a>
        </div>
      </div>
    </details>

  <!-- ══ TEKNISI IPSRS ══ -->
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
            <?php if ($cnt_mnt_ipsrs_urgent): ?><span class="nc" style="background:#f59e0b;"><?= $cnt_mnt_ipsrs_urgent ?></span><?php endif; ?>
          </a>
        </div>
      </div>
    </details>

  <!-- ══ ADMIN ══ -->
  <?php else: ?>

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
            <?php if ($cnt_offline): ?><span class="nc"><?= $cnt_offline ?></span><?php endif; ?>
          </a>
        </div>
        <div class="nav-item <?= ($active_menu??'')==='server_room'?'active':'' ?>">
          <a href="<?= APP_URL ?>/pages/server_room.php"><i class="fa fa-server ni"></i><span class="nl">Monitoring Server</span></a>
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
            <?php if ($cnt_mnt_ipsrs_urgent): ?><span class="nc" style="background:#f59e0b;"><?= $cnt_mnt_ipsrs_urgent ?></span><?php endif; ?>
          </a>
        </div>
      </div>
    </details>

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
          <a href="<?= APP_URL ?>/pages/login_log.php"><i class="fa fa-shield-halved ni" style="color:#8b5cf6;"></i><span class="nl">Log Login</span></a>
        </div>
      </div>
    </details>

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
                ? '<span style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block;flex-shrink:0;"></span>'
                : '<span style="width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.18);display:inline-block;flex-shrink:0;"></span>';
            } catch(Exception $e) {} ?>
          </a>
        </div>
      </div>
    </details>

  <?php endif; ?>

  <!-- Profil (semua role) -->
  <div class="nav-item <?= ($active_menu??'')==='profil'?'active':'' ?>" style="margin-top:4px;">
    <a href="<?= APP_URL ?>/pages/profil.php"><i class="fa fa-user-circle ni"></i><span class="nl">Profil Saya</span></a>
  </div>

  </div><!-- /sb-nav -->

  <!-- Bottom -->
  <div class="sb-bottom">
    <button onclick="openModal('m-about')" class="fs-about-btn">
      <div class="fs-logo-badge">FS</div>
      <div style="flex:1;min-width:0;">
        <div class="fs-btn-label">FixSmart Helpdesk</div>
        <div class="fs-btn-sub">v1.0.0 &mdash; Tentang Aplikasi</div>
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


<!-- ════════════════════════════════════════
     TOP NAV
════════════════════════════════════════ -->
<div class="topnav" id="topnav">

  <!-- Left -->
  <div class="tn-left">
    <button class="btn-toggle" id="btn-toggle" onclick="handleToggle()" aria-label="Toggle sidebar">
      <i class="fa fa-bars"></i>
    </button>
    <span class="tn-title"><?= clean($page_title ?? 'Dashboard') ?></span>
  </div>

  <!-- Right -->
  <div class="tn-right">

    <?php if (hasRole(['admin','teknisi']) && $cnt_menunggu): ?>
    <a href="<?= APP_URL ?>/pages/antrian.php" class="tn-alert-pill">
      <i class="fa fa-bell"></i> <span><?= $cnt_menunggu ?> tiket IT</span>
    </a>
    <?php endif; ?>

    <?php if (hasRole(['admin','teknisi_ipsrs']) && $cnt_ipsrs_menunggu): ?>
    <a href="<?= APP_URL ?>/pages/antrian_ipsrs.php" class="tn-alert-pill">
      <i class="fa fa-bell"></i> <span><?= $cnt_ipsrs_menunggu ?> IPSRS</span>
    </a>
    <?php endif; ?>

    <button onclick="openModal('m-about')" class="tn-about-btn" title="Tentang Aplikasi">
      <i class="fa fa-circle-info"></i><span>Tentang</span>
    </button>

    <!-- User dropdown -->
    <div class="tn-user" id="tn-user" onclick="toggleDropdown()">
      <div class="tn-user-info">
        <div class="tn-user-av"><?= getInitials($_SESSION['user_nama']) ?></div>
        <span class="tn-user-name"><?= clean($_SESSION['user_nama']) ?></span>
        <i class="fa fa-chevron-down tn-caret"></i>
      </div>
      <div class="tn-dropdown">
        <a href="<?= APP_URL ?>/pages/profil.php"><i class="fa fa-user"></i> Profil Saya</a>
        <a href="<?= APP_URL ?>/logout.php" class="red-link"><i class="fa fa-sign-out-alt"></i> Keluar</a>
      </div>
    </div>

  </div>
</div>


<!-- ════════════════════════════════════════
     MODAL TENTANG
════════════════════════════════════════ -->
<div class="modal-ov" id="m-about">
  <div style="background:#fff;width:100%;max-width:760px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.18);overflow:hidden;display:flex;animation:mIn .22s cubic-bezier(.4,0,.2,1);max-height:90vh;">

    <!-- Kiri -->
    <div style="width:250px;flex-shrink:0;background:linear-gradient(170deg,#0f1923 0%,#162435 55%,#0f2a1f 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px 18px;text-align:center;">
      <div style="width:58px;height:58px;background:rgba(38,185,154,.12);border:1.5px solid rgba(38,185,154,.3);border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;">
        <i class="fa fa-desktop" style="font-size:26px;color:#26B99A;"></i>
      </div>
      <div style="color:#f1f5f9;font-size:15px;font-weight:700;">FixSmart Helpdesk</div>
      <div style="color:rgba(255,255,255,.3);font-size:10px;margin-top:3px;">Work Order System</div>
      <div style="margin-top:8px;background:rgba(38,185,154,.14);border:1px solid rgba(38,185,154,.28);color:#5eead4;padding:2px 12px;border-radius:20px;font-size:10px;font-weight:700;">Versi 1.0.0</div>
      <div style="width:100%;height:1px;background:rgba(255,255,255,.07);margin:14px 0;"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;width:100%;">
        <?php foreach ([['fa-ticket-alt','#60a5fa','Tiket'],['fa-chart-line','#a78bfa','SLA'],['fa-users','#fbbf24','Multi Role'],['fa-paper-plane','#38bdf8','Telegram'],['fa-screwdriver-wrench','#34d399','Maintenance'],['fa-shield-alt','#f87171','Keamanan']] as [$ic,$cl,$lb]): ?>
        <div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.06);border-radius:6px;padding:5px;font-size:10px;color:rgba(255,255,255,.6);display:flex;align-items:center;gap:5px;">
          <i class="fa <?= $ic ?>" style="color:<?= $cl ?>;font-size:11px;width:13px;text-align:center;"></i><?= $lb ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="width:100%;height:1px;background:rgba(255,255,255,.07);margin:14px 0;"></div>
      <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.22);text-transform:uppercase;letter-spacing:.7px;margin-bottom:7px;">Dikembangkan oleh</div>
      <div style="color:#f1f5f9;font-size:12px;font-weight:600;">M. Wira Satria Buana, S.Kom</div>
      <div style="color:rgba(255,255,255,.3);font-size:10px;margin-top:3px;"><i class="fa fa-envelope" style="color:#26B99A;"></i> wiramuhammad16@gmail.com</div>
      <div style="color:rgba(255,255,255,.3);font-size:10px;margin-top:2px;"><i class="fa fa-phone" style="color:#26B99A;"></i> 0821 7784 6209</div>
      <div style="margin-top:12px;font-size:10px;color:rgba(255,255,255,.18);line-height:1.6;">&copy; 2025 FixSmart &bull; PHP + MySQL<br>Open Source &amp; Free Forever</div>
    </div>

    <!-- Kanan -->
    <div style="flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 16px 10px;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
        <div style="font-size:13px;font-weight:700;color:#1e293b;"><i class="fa fa-circle-info" style="color:#26B99A;"></i> &nbsp;Tentang Aplikasi</div>
        <button onclick="closeModal('m-about')"
          style="width:26px;height:26px;border-radius:50%;background:#f3f4f6;border:none;cursor:pointer;color:#9ca3af;font-size:12px;display:flex;align-items:center;justify-content:center;transition:all .18s;"
          onmouseover="this.style.background='#ef4444';this.style.color='#fff';" onmouseout="this.style.background='#f3f4f6';this.style.color='#9ca3af';">
          <i class="fa fa-times"></i>
        </button>
      </div>
      <div style="flex:1;padding:14px 16px;display:flex;flex-direction:column;gap:10px;overflow-y:auto;">
        <p style="font-size:11.5px;color:#64748b;line-height:1.75;margin:0;">
          Sistem manajemen tiket IT berbasis web untuk membantu pengelolaan <em>work order</em>,
          pelacakan SLA, dan pelaporan kinerja tim IT.
        </p>
        <div style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1px solid #a7f3d0;border-radius:9px;padding:10px 12px;display:flex;align-items:center;gap:10px;">
          <div style="width:32px;height:32px;background:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa fa-gift" style="color:#fff;font-size:13px;"></i>
          </div>
          <div>
            <div style="font-size:12px;font-weight:700;color:#065f46;">Aplikasi 100% Gratis</div>
            <div style="font-size:10.5px;color:#059669;margin-top:1px;line-height:1.5;">Bebas digunakan &amp; dimodifikasi. <strong>Dilarang diperjualbelikan.</strong></div>
          </div>
        </div>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:9px;padding:11px 12px;">
          <div style="display:flex;align-items:center;gap:7px;margin-bottom:8px;">
            <i class="fa fa-heart" style="color:#ef4444;font-size:13px;"></i>
            <span style="font-size:12px;font-weight:700;color:#92400e;">Dukung Pengembangan</span>
            <span style="font-size:10px;color:#a16207;margin-left:auto;">Sukarela ☕</span>
          </div>
          <?php foreach ([['#6c3db5','fa fa-wallet','OVO / DANA','0821 7784 6209','0821 7784 6209'],['#00703c','','Bank BSI','7134197557','7134197557']] as [$bg,$ic,$lbl,$display,$copy]): ?>
          <div style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #fde68a;border-radius:6px;padding:7px 9px;margin-bottom:6px;">
            <div style="width:24px;height:24px;border-radius:5px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <?php if ($ic): ?><i class="<?= $ic ?>" style="color:#fff;font-size:10px;"></i>
              <?php else: ?><span style="color:#fff;font-size:8px;font-weight:900;">BSI</span><?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:10px;font-weight:600;color:#6b7280;"><?= $lbl ?></div>
              <div style="font-size:11px;color:#1e293b;font-family:monospace;"><?= $display ?></div>
            </div>
            <button onclick="copyText('<?= $copy ?>',this)"
              style="background:none;border:1px solid #e5e7eb;border-radius:5px;padding:3px 8px;font-size:10px;cursor:pointer;color:#9ca3af;font-family:inherit;transition:all .18s;"
              onmouseover="this.style.background='#26B99A';this.style.color='#fff';this.style.borderColor='#26B99A';"
              onmouseout="this.style.background='none';this.style.color='#9ca3af';this.style.borderColor='#e5e7eb';">
              <i class="fa fa-copy"></i> Salin
            </button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="padding:9px 16px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <span style="font-size:10px;color:#cbd5e1;">Dibuat dengan <i class="fa fa-heart" style="color:#ef4444;"></i> di Indonesia</span>
        <button onclick="closeModal('m-about')"
          style="padding:5px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:11px;cursor:pointer;color:#64748b;font-family:inherit;font-weight:500;transition:all .18s;"
          onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f8fafc';">
          <i class="fa fa-times"></i> Tutup
        </button>
      </div>
    </div>

  </div>
</div>


<!-- ════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════ -->
<script>
/* ── Detect mobile ── */
function isMobile() { return window.innerWidth <= 768; }

/* ── Toggle sidebar ── */
function handleToggle() {
  const sb  = document.getElementById('sidebar');
  const tn  = document.getElementById('topnav');
  const mn  = document.getElementById('main');
  const ov  = document.getElementById('sb-overlay');
  if (!sb) return;

  if (isMobile()) {
    // Mobile: slide in/out with overlay
    const isOpen = sb.classList.contains('mobile-open');
    if (isOpen) {
      sb.classList.remove('mobile-open');
      ov.classList.remove('active');
    } else {
      sb.classList.add('mobile-open');
      ov.classList.add('active');
    }
  } else {
    // Desktop: collapse/expand
    const isCollapsed = sb.classList.contains('collapsed');
    if (isCollapsed) {
      sb.classList.remove('collapsed');
      tn && tn.classList.remove('sb-collapsed');
      mn && mn.classList.remove('sb-collapsed');
      try { localStorage.setItem('sb_collapsed','0'); } catch(e) {}
    } else {
      sb.classList.add('collapsed');
      tn && tn.classList.add('sb-collapsed');
      mn && mn.classList.add('sb-collapsed');
      try { localStorage.setItem('sb_collapsed','1'); } catch(e) {}
    }
  }
}

function closeSidebarMobile() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sb-overlay');
  if (sb) sb.classList.remove('mobile-open');
  if (ov) ov.classList.remove('active');
}

/* ── Restore desktop sidebar state ── */
(function() {
  if (isMobile()) return;
  try {
    if (localStorage.getItem('sb_collapsed') === '1') {
      const sb = document.getElementById('sidebar');
      const tn = document.getElementById('topnav');
      const mn = document.getElementById('main');
      if (sb) sb.classList.add('collapsed');
      if (tn) tn.classList.add('sb-collapsed');
      if (mn) mn.classList.add('sb-collapsed');
    }
  } catch(e) {}
})();

/* ── Close sidebar on resize to desktop ── */
window.addEventListener('resize', function() {
  if (!isMobile()) {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sb-overlay');
    if (sb) sb.classList.remove('mobile-open');
    if (ov) ov.classList.remove('active');
  }
});

/* ── Modal ── */
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
  if (e.key === 'Escape')
    document.querySelectorAll('.modal-ov').forEach(function(m) { if (m.style.display==='flex') closeModal(m.id); });
});

/* ── Dropdown ── */
function toggleDropdown() {
  document.getElementById('tn-user').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const d = document.getElementById('tn-user');
  if (d && !d.contains(e.target)) d.classList.remove('open');
});

/* ── Copy to clipboard ── */
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-check"></i> Disalin!';
    btn.style.cssText = 'background:#26B99A;color:#fff;border-color:#26B99A;border:1px solid;border-radius:5px;padding:3px 8px;font-size:10px;cursor:pointer;font-family:inherit;';
    setTimeout(() => { btn.innerHTML = orig; btn.style.cssText = ''; }, 2000);
  });
}

/* ── Flash alert auto-dismiss ── */
setTimeout(function() {
  document.querySelectorAll('.alert').forEach(function(a) {
    a.style.transition = 'opacity .4s';
    a.style.opacity = '0';
    setTimeout(function() { if (a.parentNode) a.parentNode.removeChild(a); }, 400);
  });
}, 4000);

/* ── Smooth accordion ── */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('details.nav-group').forEach(function(det) {
    var bd = det.querySelector('.nav-group-bd');
    if (!bd) return;
    if (det.open) {
      bd.style.maxHeight = bd.scrollHeight + 'px';
      bd.style.opacity = '1';
    } else {
      bd.style.maxHeight = '0';
      bd.style.opacity = '0';
      bd.style.overflow = 'hidden';
    }
    det.addEventListener('click', function(e) {
      if (!e.target.closest('summary')) return;
      e.preventDefault();
      var isOpen = det.open;
      if (isOpen) {
        bd.style.transition = 'max-height .25s cubic-bezier(.4,0,.2,1), opacity .2s ease';
        bd.style.maxHeight = bd.scrollHeight + 'px';
        bd.offsetHeight;
        bd.style.maxHeight = '0';
        bd.style.opacity = '0';
        bd.addEventListener('transitionend', function onC() {
          bd.removeEventListener('transitionend', onC);
          det.removeAttribute('open');
        });
      } else {
        det.setAttribute('open', '');
        bd.style.transition = 'none';
        bd.style.maxHeight = '0';
        bd.style.opacity = '0';
        bd.offsetHeight;
        bd.style.transition = 'max-height .28s cubic-bezier(.4,0,.2,1), opacity .22s ease';
        bd.style.maxHeight = bd.scrollHeight + 'px';
        bd.style.opacity = '1';
        bd.addEventListener('transitionend', function onO() {
          bd.removeEventListener('transitionend', onO);
          bd.style.maxHeight = 'none';
        });
      }
    });
  });
});
</script>

<!-- ════════════════════════════════════════
     MAIN WRAPPER START
════════════════════════════════════════ -->
<div class="main" id="main">