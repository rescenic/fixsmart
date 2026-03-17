<?php
// pages/lacak_aset_it.php — Lacak Aset IT
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

$page_title  = 'Lacak Aset IT';
$active_menu = 'lacak_aset';

if (isset($_GET['search'])) {
    $q  = '%' . trim($_GET['q'] ?? '') . '%';
    $st = $pdo->prepare("
        SELECT a.id, a.no_inventaris, a.nama_aset, a.kategori, a.merek, a.model_aset,
               a.kondisi, a.status_pakai, a.serial_number,
               b.nama AS bagian_nama, b.kode AS bagian_kode,
               u.nama AS pj_nama
        FROM aset_it a
        LEFT JOIN bagian b ON b.id = a.bagian_id
        LEFT JOIN users  u ON u.id = a.pj_user_id
        WHERE a.no_inventaris LIKE ? OR a.nama_aset LIKE ?
              OR a.merek LIKE ? OR b.nama LIKE ? OR u.nama LIKE ?
              OR a.serial_number LIKE ?
        ORDER BY a.nama_aset ASC LIMIT 25
    ");
    $st->execute([$q,$q,$q,$q,$q,$q]);
    header('Content-Type: application/json');
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if (isset($_GET['detail'])) {
    $id = (int)$_GET['detail'];
    $st = $pdo->prepare("
        SELECT a.*, b.nama AS bagian_nama, b.kode AS bagian_kode, b.lokasi AS bagian_lokasi,
               u.nama AS pj_nama_db, u.divisi AS pj_divisi, u.role AS pj_role
        FROM aset_it a
        LEFT JOIN bagian b ON b.id = a.bagian_id
        LEFT JOIN users  u ON u.id = a.pj_user_id
        WHERE a.id = ?
    ");
    $st->execute([$id]);
    $aset = $st->fetch(PDO::FETCH_ASSOC);
    $sm = $pdo->prepare("
        SELECT m.*, a2.nama_aset, a2.no_inventaris FROM mutasi_aset m
        LEFT JOIN aset_it a2 ON a2.id = m.aset_id
        WHERE m.aset_id = ? ORDER BY m.tanggal_mutasi ASC, m.created_at ASC
    ");
    $sm->execute([$id]);
    $mutasi = $sm->fetchAll(PDO::FETCH_ASSOC);
    $tiket = [];
    try {
        $st2 = $pdo->prepare("SELECT id, judul, status, prioritas, created_at FROM tiket WHERE aset_id = ? ORDER BY created_at DESC LIMIT 5");
        $st2->execute([$id]); $tiket = $st2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    header('Content-Type: application/json');
    echo json_encode(['aset' => $aset, 'mutasi' => $mutasi, 'tiket' => $tiket]);
    exit;
}

$total_aset    = (int)$pdo->query("SELECT COUNT(*) FROM aset_it")->fetchColumn();
$total_mutasi  = 0;
try { $total_mutasi = (int)$pdo->query("SELECT COUNT(*) FROM mutasi_aset WHERE status_mutasi='selesai'")->fetchColumn(); } catch(Exception $e){}
$total_bagian  = (int)$pdo->query("SELECT COUNT(*) FROM bagian WHERE status='aktif'")->fetchColumn();
$aset_terpakai = (int)$pdo->query("SELECT COUNT(*) FROM aset_it WHERE status_pakai='Terpakai'")->fetchColumn();

include '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

<style>
/* ================================================
   LACAK ASET IT — v3 Clean
   Light, structured, consistent with app theme
================================================ */
* { box-sizing: border-box; }
.lk { font-family: 'Inter', sans-serif; color: #1e293b; }

/* SEARCH HEADER */
.lk-search-header {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 18px 12px;
    margin-bottom: 12px;
}
.lk-search-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.lk-search-box { flex: 1; position: relative; }
.lk-search-inp {
    width: 100%; height: 40px;
    padding: 0 36px 0 38px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #f8fafc; color: #1e293b;
    font-size: 13px; font-family: 'Inter', sans-serif; font-weight: 500;
    outline: none; transition: all .15s;
}
.lk-search-inp:focus { border-color: #00c896; background: #fff; box-shadow: 0 0 0 3px rgba(0,200,150,.09); }
.lk-search-inp::placeholder { color: #94a3b8; font-weight: 400; }
.lk-s-ico { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; pointer-events: none; transition: color .15s; }
.lk-search-box:focus-within .lk-s-ico { color: #00c896; }
.lk-s-clear {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    display: none; background: #e2e8f0; border: none; border-radius: 50%;
    width: 18px; height: 18px; color: #64748b; cursor: pointer;
    font-size: 10px; align-items: center; justify-content: center; transition: all .12s;
}
.lk-s-clear:hover { background: #ef4444; color: #fff; }

.lk-hints { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.lk-hint-lbl { font-size: 10.5px; color: #94a3b8; font-weight: 600; }
.lk-hint {
    padding: 3px 10px; border: 1.5px solid #e2e8f0; border-radius: 20px;
    background: #fff; color: #64748b; font-size: 11px; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif; transition: all .12s;
}
.lk-hint:hover { border-color: #00c896; color: #00b386; background: #f0fdf9; }

/* Dropdown */
.lk-dropdown {
    display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0;
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 9px;
    z-index: 9999; box-shadow: 0 8px 28px rgba(0,0,0,.11);
    max-height: 300px; overflow-y: auto;
}
.lk-dropdown.open { display: block; }
.lk-dd-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 13px; cursor: pointer;
    border-bottom: 1px solid #f8fafc; transition: background .1s;
}
.lk-dd-item:hover { background: #f0fdf9; }
.lk-dd-item:last-child { border-bottom: none; }
.lk-dd-ico { width: 32px; height: 32px; border-radius: 7px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; flex-shrink: 0; }
.lk-dd-ico i { color: #64748b; font-size: 12px; }
.lk-dd-inv { font-family: 'JetBrains Mono', monospace; font-size: 9px; font-weight: 700; color: #6366f1; background: #eef2ff; padding: 1px 5px; border-radius: 3px; display: inline-block; margin-bottom: 1px; }
.lk-dd-name { font-size: 12px; font-weight: 700; color: #1e293b; }
.lk-dd-sub  { font-size: 10.5px; color: #94a3b8; margin-top: 1px; }
.lk-dd-k { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; white-space: nowrap; flex-shrink: 0; margin-left: auto; }
.lk-dd-empty { padding: 16px; text-align: center; font-size: 12px; color: #94a3b8; }

/* STAT STRIP */
.lk-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 14px; }
.lk-stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; }
.lk-stat-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.lk-stat-val  { font-size: 20px; font-weight: 800; color: #0f172a; font-family: 'JetBrains Mono', monospace; line-height: 1; }
.lk-stat-lbl  { font-size: 10.5px; color: #94a3b8; font-weight: 600; margin-top: 2px; }

/* BODY GRID */
.lk-body { display: grid; grid-template-columns: 296px 1fr; gap: 14px; align-items: start; }

/* LIST KIRI */
.lk-list-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.lk-list-head { padding: 10px 14px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
.lk-list-head-t { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #64748b; display: flex; align-items: center; gap: 5px; }
.lk-list-head-t i { color: #00c896; }
.lk-list-count { font-size: 10px; font-weight: 700; background: #e2e8f0; color: #64748b; padding: 2px 7px; border-radius: 9px; font-family: 'JetBrains Mono', monospace; }
.lk-list-scroll { max-height: 560px; overflow-y: auto; }
.lk-list-scroll::-webkit-scrollbar { width: 3px; }
.lk-list-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 2px; }

.lk-item { display: flex; align-items: center; gap: 10px; padding: 11px 14px; cursor: pointer; border-bottom: 1px solid #f8fafc; transition: background .1s; position: relative; }
.lk-item:last-child { border-bottom: none; }
.lk-item:hover { background: #f8fafc; }
.lk-item.active { background: #f0fdf9; border-bottom-color: #e0f9f3; }
.lk-item.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #00c896; border-radius: 0 2px 2px 0; }
.lk-item-ico { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 13px; }
.lk-item-inv { font-family: 'JetBrains Mono', monospace; font-size: 9px; font-weight: 700; color: #6366f1; background: #eef2ff; padding: 1px 5px; border-radius: 3px; display: inline-block; margin-bottom: 2px; }
.lk-item-name { font-size: 12px; font-weight: 700; color: #1e293b; line-height: 1.3; }
.lk-item-meta { font-size: 10.5px; color: #94a3b8; margin-top: 2px; }
.lk-item-badge { font-size: 9.5px; font-weight: 700; padding: 2px 7px; border-radius: 4px; white-space: nowrap; flex-shrink: 0; margin-left: auto; }
.lk-no-result { padding: 36px 16px; text-align: center; color: #94a3b8; font-size: 12px; }
.lk-no-result i { font-size: 20px; display: block; margin-bottom: 8px; }

/* STATE PANELS */
.lk-state-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 56px 32px; text-align: center; }
.lk-state-ico { width: 56px; height: 56px; border-radius: 13px; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; }
.lk-state-ico i { font-size: 21px; }
.lk-state-title { font-size: 13.5px; font-weight: 700; color: #334155; margin-bottom: 5px; }
.lk-state-sub   { font-size: 12px; color: #94a3b8; line-height: 1.7; }

/* LOADING */
.lk-loading { display: none; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 56px; text-align: center; }
.lk-loading.show { display: block; }
.lk-spin { display: inline-block; width: 26px; height: 26px; border: 3px solid #e2e8f0; border-top-color: #00c896; border-radius: 50%; animation: lk-s .65s linear infinite; margin-bottom: 9px; }
@keyframes lk-s { to { transform: rotate(360deg); } }

/* DETAIL */
.lk-detail { display: none; }
.lk-detail.show { display: block; }

/* ASET CARD */
.lk-aset-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 12px; }
.lk-aset-hdr { padding: 18px 20px; display: flex; align-items: flex-start; gap: 14px; background: linear-gradient(130deg, #0d1b2e 0%, #163354 100%); border-bottom: 1px solid rgba(255,255,255,.06); }
.lk-aset-hdr-ico { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.lk-aset-hdr-inv { font-family: 'JetBrains Mono', monospace; font-size: 9.5px; font-weight: 700; color: rgba(255,255,255,.38); margin-bottom: 4px; letter-spacing: .5px; }
.lk-aset-hdr-name { font-size: 17px; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 8px; letter-spacing: -.2px; }
.lk-chips { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
.lk-chip { display: inline-flex; align-items: center; gap: 3px; padding: 3px 9px; border-radius: 5px; font-size: 10.5px; font-weight: 700; }

/* Info section */
.lk-section-title { padding: 10px 18px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #94a3b8; background: #fafbfc; border-bottom: 1px solid #f0f2f7; }
.lk-info-table { width: 100%; border-collapse: collapse; }
.lk-info-table tr { border-bottom: 1px solid #f0f2f7; }
.lk-info-table tr:last-child { border-bottom: none; }
.lk-info-table td { padding: 10px 18px; vertical-align: top; width: 50%; border-right: 1px solid #f0f2f7; }
.lk-info-table td:last-child { border-right: none; }
.lk-info-table td:hover { background: #fafbfc; }
.lk-td-lbl { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; margin-bottom: 3px; display: flex; align-items: center; gap: 4px; }
.lk-td-lbl i { font-size: 8px; }
.lk-td-val { font-size: 12.5px; font-weight: 600; color: #1e293b; line-height: 1.4; }
.lk-td-sub { font-size: 10.5px; color: #94a3b8; margin-top: 2px; }
.lk-mono { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #6366f1; }

/* Kondisi bar */
.lk-k-wrap { padding: 12px 18px; border-top: 1px solid #f0f2f7; }
.lk-k-row  { display: flex; justify-content: space-between; font-size: 9.5px; font-weight: 600; color: #94a3b8; margin-bottom: 5px; }
.lk-k-track { height: 5px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
.lk-k-fill  { height: 5px; border-radius: 3px; transition: width .5s ease; }

/* Keterangan */
.lk-ktr { display: flex; gap: 8px; align-items: flex-start; padding: 10px 18px; background: #fffbeb; border-top: 1px solid #fde68a; font-size: 11.5px; color: #78350f; line-height: 1.6; }
.lk-ktr i { color: #f59e0b; flex-shrink: 0; margin-top: 1px; }

/* Print btn */
.lk-print-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 6px; background: #0d1b2e; color: #fff; font-size: 11px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; font-family: 'Inter', sans-serif; transition: background .14s; }
.lk-print-btn:hover { background: #1a3a5c; color: #fff; text-decoration: none; }
.lk-print-btn i { color: #00c896; }

/* TIMELINE CARD */
.lk-tl-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.lk-tl-head { padding: 11px 18px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
.lk-tl-head-t { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #64748b; display: flex; align-items: center; gap: 6px; }
.lk-tl-head-t i { color: #00c896; }
.lk-tl-head-r { display: flex; align-items: center; gap: 8px; }
.lk-tl-count { font-size: 10px; font-weight: 700; background: #e2e8f0; color: #64748b; padding: 2px 7px; border-radius: 9px; font-family: 'JetBrains Mono', monospace; }
.lk-tl-body { padding: 18px 20px 18px 18px; }

/* Timeline items */
.lk-tl-wrap { position: relative; padding-left: 32px; }
.lk-tl-wrap::before { content: ''; position: absolute; left: 10px; top: 12px; bottom: 12px; width: 1.5px; background: linear-gradient(180deg, #00c896 0%, #6366f1 60%, #e2e8f0 100%); }
.lk-tl-item { position: relative; margin-bottom: 11px; animation: tl-in .22s ease both; }
.lk-tl-item:last-child { margin-bottom: 0; }
@keyframes tl-in { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
.lk-tl-dot { position: absolute; left: -25px; top: 9px; width: 16px; height: 16px; border-radius: 50%; border: 2.5px solid #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 1px #e2e8f0; }
.lk-tl-dot i { font-size: 5px; color: #fff; }
.d-awal   { background: #6366f1; }
.d-mutasi { background: #00c896; }
.d-kini   { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.14); }
.d-batal  { background: #ef4444; }

/* TL Cards */
.lk-tc { background: #fff; border: 1px solid #e8ecf2; border-radius: 8px; padding: 10px 13px; transition: box-shadow .12s; }
.lk-tc:hover { box-shadow: 0 2px 10px rgba(0,0,0,.06); }
.lk-tc.tc-awal { border-color: #c7d2fe; background: #fafbff; }
.lk-tc.tc-kini { border-color: #fcd34d; background: #fffdf0; }
.lk-tc.tc-batal { border-style: dashed; border-color: #fca5a5; background: #fff8f8; opacity: .65; }

.lk-tc-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 4px; }
.lk-tc-date { font-size: 10px; font-weight: 600; color: #94a3b8; font-family: 'JetBrains Mono', monospace; display: flex; align-items: center; gap: 3px; }
.lk-tc-badge { font-size: 9.5px; font-weight: 700; padding: 1px 7px; border-radius: 3px; text-transform: uppercase; letter-spacing: .3px; }
.lk-tc-title { font-size: 12.5px; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
.lk-tc-sub   { font-size: 10.5px; color: #94a3b8; }

/* Route grid */
.lk-tc-route { display: grid; grid-template-columns: 1fr 20px 1fr; align-items: center; gap: 5px; margin-top: 7px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e8ecf2; border-radius: 6px; }
.lk-tc-r-lbl { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
.lk-tc-r-val { font-size: 11.5px; font-weight: 700; color: #1e293b; }
.lk-tc-r-val.to { color: #00a874; }
.lk-tc-r-pic { font-size: 10.5px; color: #64748b; margin-top: 1px; }
.lk-tc-r-arr { text-align: center; color: #00c896; font-size: 13px; }
.lk-tc-k-after { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 700; margin-top: 6px; padding: 2px 7px; border-radius: 4px; }

/* Kini */
.lk-kini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 7px; }
.lk-kini-cell { padding: 8px 10px; border-radius: 6px; background: rgba(255,255,255,.7); border: 1px solid rgba(252,211,77,.35); }
.lk-kini-lbl { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
.lk-kini-val { font-size: 12px; font-weight: 700; color: #1e293b; }

/* RESPONSIVE */
@media (max-width: 980px)  { .lk-body { grid-template-columns: 1fr; } .lk-stats { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 560px)  { .lk-stats { grid-template-columns: repeat(2,1fr); } }
</style>

<div class="lk">

    <!-- SEARCH HEADER -->
    <div class="lk-search-header">
        <div class="lk-search-row">
            <div class="lk-search-box">
                <i class="fa fa-magnifying-glass lk-s-ico"></i>
                <input type="text" class="lk-search-inp" id="lk-inp"
                       placeholder="Cari nama aset, no. inventaris, bagian, PIC, serial number…"
                       autocomplete="off">
                <button class="lk-s-clear" id="lk-clear" onclick="clearSearch()">
                    <i class="fa fa-times"></i>
                </button>
                <div class="lk-dropdown" id="lk-dd"></div>
            </div>
        </div>
        <div class="lk-hints">
            <span class="lk-hint-lbl">Pencarian cepat:</span>
            <?php foreach(['Laptop','Printer','Server','Switch','Monitor'] as $h): ?>
            <button class="lk-hint" onclick="quickSearch('<?= $h ?>')"><?= $h ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- STAT STRIP -->
    <div class="lk-stats">
        <div class="lk-stat">
            <div class="lk-stat-icon" style="background:#eff6ff;"><i class="fa fa-server" style="color:#3b82f6;font-size:14px;"></i></div>
            <div><div class="lk-stat-val"><?= $total_aset ?></div><div class="lk-stat-lbl">Total Aset</div></div>
        </div>
        <div class="lk-stat">
            <div class="lk-stat-icon" style="background:#f0fdf4;"><i class="fa fa-circle-check" style="color:#10b981;font-size:14px;"></i></div>
            <div><div class="lk-stat-val"><?= $aset_terpakai ?></div><div class="lk-stat-lbl">Terpakai</div></div>
        </div>
        <div class="lk-stat">
            <div class="lk-stat-icon" style="background:#fef9c3;"><i class="fa fa-right-left" style="color:#d97706;font-size:14px;"></i></div>
            <div><div class="lk-stat-val"><?= $total_mutasi ?></div><div class="lk-stat-lbl">Total Mutasi</div></div>
        </div>
        <div class="lk-stat">
            <div class="lk-stat-icon" style="background:#faf5ff;"><i class="fa fa-building" style="color:#7c3aed;font-size:14px;"></i></div>
            <div><div class="lk-stat-val"><?= $total_bagian ?></div><div class="lk-stat-lbl">Bagian Aktif</div></div>
        </div>
    </div>

    <!-- START STATE -->
    <div id="lk-start">
        <div class="lk-state-panel">
            <div class="lk-state-ico" style="background:#f0fdf9;"><i class="fa fa-magnifying-glass-location" style="color:#00c896;"></i></div>
            <div class="lk-state-title">Mulai Lacak Aset</div>
            <div class="lk-state-sub">Ketik minimal 2 karakter di kolom pencarian di atas<br>untuk melacak posisi dan riwayat perpindahan aset IT.</div>
        </div>
    </div>

    <!-- BODY GRID -->
    <div class="lk-body" id="lk-body" style="display:none;">

        <!-- LIST KIRI -->
        <div class="lk-list-card">
            <div class="lk-list-head">
                <span class="lk-list-head-t"><i class="fa fa-list"></i> Hasil Pencarian</span>
                <span class="lk-list-count" id="hasil-count">0</span>
            </div>
            <div class="lk-list-scroll" id="aset-list"></div>
        </div>

        <!-- PANEL KANAN -->
        <div>
            <div class="lk-loading" id="det-loading">
                <div class="lk-spin"></div>
                <div style="font-size:12px;color:#94a3b8;font-weight:500;">Memuat detail aset…</div>
            </div>
            <div class="lk-state-panel" id="det-empty">
                <div class="lk-state-ico" style="background:#f1f5f9;"><i class="fa fa-hand-pointer" style="color:#94a3b8;"></i></div>
                <div class="lk-state-title">Pilih aset dari daftar</div>
                <div class="lk-state-sub">Klik salah satu aset di sebelah kiri untuk melihat detail dan riwayat perpindahannya.</div>
            </div>

            <!-- DETAIL -->
            <div id="det-panel" class="lk-detail">

                <!-- CARD HEADER + INFO -->
                <div class="lk-aset-card">
                    <div class="lk-aset-hdr" id="dc-hdr"></div>
                    <div id="dc-body"></div>
                </div>

                <!-- TIMELINE -->
                <div class="lk-tl-card">
                    <div class="lk-tl-head">
                        <span class="lk-tl-head-t"><i class="fa fa-clock-rotate-left"></i> Riwayat Perjalanan Aset</span>
                        <div class="lk-tl-head-r">
                            <span class="lk-tl-count" id="tl-count"></span>
                            <a id="btn-print" href="#" target="_blank" class="lk-print-btn">
                                <i class="fa fa-print"></i> Cetak PDF
                            </a>
                        </div>
                    </div>
                    <div class="lk-tl-body" id="tl-wrap"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const APP_URL = '<?= APP_URL ?>';
let _tmr=null, _aid=null;

/* ─── SEARCH ─── */
var inp = document.getElementById('lk-inp');
inp.addEventListener('input', function(){
    var q=this.value.trim();
    document.getElementById('lk-clear').style.display=q?'flex':'none';
    clearTimeout(_tmr);
    if(q.length<2){closeDd();return;}
    _tmr=setTimeout(function(){doSearch(q);},240);
});
inp.addEventListener('keydown',function(e){if(e.key==='Escape')clearSearch();});
document.addEventListener('click',function(e){
    if(!document.querySelector('.lk-search-box').contains(e.target))closeDd();
});
function quickSearch(w){inp.value=w;document.getElementById('lk-clear').style.display='flex';doSearch(w);}
function doSearch(q){
    fetch(APP_URL+'/pages/lacak_aset_it.php?search=1&q='+encodeURIComponent(q))
        .then(r=>r.json()).then(data=>{renderDd(data);renderList(data);});
}
function clearSearch(){
    inp.value='';document.getElementById('lk-clear').style.display='none';closeDd();
    document.getElementById('lk-start').style.display='block';
    document.getElementById('lk-body').style.display='none';_aid=null;
}

/* ─ Dropdown ─ */
function renderDd(data){
    var dd=document.getElementById('lk-dd');dd.innerHTML='';
    var kc={'Baik':['#dcfce7','#15803d'],'Rusak':['#fee2e2','#b91c1c'],'Dalam Perbaikan':['#fef9c3','#a16207'],'Tidak Aktif':['#f1f5f9','#475569']};
    if(!data.length){dd.innerHTML='<div class="lk-dd-empty"><i class="fa fa-inbox" style="margin-right:5px;"></i>Tidak ditemukan</div>';dd.classList.add('open');return;}
    data.slice(0,8).forEach(function(a){
        var k=kc[a.kondisi]||['#f1f5f9','#475569'];
        var el=document.createElement('div');el.className='lk-dd-item';
        el.innerHTML='<div class="lk-dd-ico"><i class="fa fa-box"></i></div>'+
            '<div style="flex:1;min-width:0;"><div><span class="lk-dd-inv">'+s(a.no_inventaris)+'</span></div>'+
            '<div class="lk-dd-name">'+s(a.nama_aset)+'</div>'+
            '<div class="lk-dd-sub">'+s(a.bagian_nama||'—')+' · '+s(a.pj_nama||'—')+'</div></div>'+
            '<span class="lk-dd-k" style="background:'+k[0]+';color:'+k[1]+';">'+s(a.kondisi||'')+'</span>';
        el.onclick=function(){closeDd();pick(a.id);};
        dd.appendChild(el);
    });
    if(data.length>8){var m=document.createElement('div');m.style.cssText='padding:7px 13px;font-size:10.5px;color:#94a3b8;text-align:center;border-top:1px solid #f1f5f9;font-weight:600;';m.textContent='+ '+(data.length-8)+' lainnya';dd.appendChild(m);}
    dd.classList.add('open');
}
function closeDd(){document.getElementById('lk-dd').classList.remove('open');}

/* ─ List ─ */
function renderList(data){
    document.getElementById('lk-start').style.display='none';
    document.getElementById('lk-body').style.display='grid';
    document.getElementById('hasil-count').textContent=data.length;
    var list=document.getElementById('aset-list');
    if(!data.length){list.innerHTML='<div class="lk-no-result"><i class="fa fa-box-open"></i>Tidak ada aset ditemukan</div>';return;}
    var colors=[['#eff6ff','#3b82f6'],['#f0fdf4','#10b981'],['#fef9c3','#ca8a04'],['#faf5ff','#7c3aed'],['#fce7f3','#be185d'],['#fff7ed','#c2410c']];
    var sp={'Terpakai':['#dbeafe','#1d4ed8'],'Tidak Terpakai':['#dcfce7','#15803d'],'Dipinjam':['#fef3c7','#a16207']};
    list.innerHTML='';
    data.forEach(function(a,i){
        var c=colors[i%colors.length],b=sp[a.status_pakai]||['#f1f5f9','#64748b'];
        var el=document.createElement('div');el.className='lk-item';el.dataset.id=a.id;
        el.innerHTML='<div class="lk-item-ico" style="background:'+c[0]+';"><i class="fa fa-box" style="color:'+c[1]+'"></i></div>'+
            '<div style="flex:1;min-width:0;"><div><span class="lk-item-inv">'+s(a.no_inventaris)+'</span></div>'+
            '<div class="lk-item-name">'+s(a.nama_aset)+'</div>'+
            '<div class="lk-item-meta"><i class="fa fa-building" style="font-size:9px;"></i> '+s(a.bagian_nama||'—')+'</div></div>'+
            '<span class="lk-item-badge" style="background:'+b[0]+';color:'+b[1]+';">'+s(a.status_pakai||'Terpakai')+'</span>';
        el.onclick=function(){pick(a.id);};
        list.appendChild(el);
    });
}

/* ─── PICK ─── */
function pick(id){
    _aid=id;
    document.querySelectorAll('.lk-item').forEach(function(el){el.classList.toggle('active',parseInt(el.dataset.id)===id);});
    document.getElementById('det-empty').style.display='none';
    document.getElementById('det-panel').classList.remove('show');
    document.getElementById('det-loading').classList.add('show');
    fetch(APP_URL+'/pages/lacak_aset_it.php?detail='+id).then(r=>r.json()).then(function(data){
        document.getElementById('det-loading').classList.remove('show');
        renderDetail(data.aset,data.mutasi,data.tiket||[]);
        document.getElementById('det-panel').classList.add('show');
    });
}

/* ─── RENDER DETAIL ─── */
function renderDetail(a,mutasi,tiket){
    if(!a)return;
    var km={
        'Baik':{clr:'#10b981',bg:'rgba(16,185,129,.15)',chip:'#dcfce7',ct:'#15803d'},
        'Rusak':{clr:'#ef4444',bg:'rgba(239,68,68,.15)',chip:'#fee2e2',ct:'#b91c1c'},
        'Dalam Perbaikan':{clr:'#f59e0b',bg:'rgba(245,158,11,.15)',chip:'#fef9c3',ct:'#a16207'},
        'Tidak Aktif':{clr:'#94a3b8',bg:'rgba(148,163,184,.15)',chip:'#f1f5f9',ct:'#475569'},
    };
    var k=km[a.kondisi]||km['Tidak Aktif'];
    var sp={'Terpakai':{chip:'#dbeafe',ct:'#1d4ed8'},'Tidak Terpakai':{chip:'#dcfce7',ct:'#15803d'},'Dipinjam':{chip:'#fef9c3',ct:'#a16207'}};
    var sv=sp[a.status_pakai]||{chip:'#f1f5f9',ct:'#64748b'};
    var gw='';
    if(a.garansi_sampai){var gd=new Date(a.garansi_sampai),now=new Date();
        if(gd<now) gw='<span class="lk-chip" style="background:#fee2e2;color:#b91c1c;"><i class="fa fa-triangle-exclamation"></i> Expired</span>';
        else if(gd<new Date(now.getTime()+30*864e5)) gw='<span class="lk-chip" style="background:#fef9c3;color:#a16207;"><i class="fa fa-clock"></i> Segera Habis</span>';
    }

    /* Header */
    document.getElementById('dc-hdr').innerHTML=
        '<div class="lk-aset-hdr-ico" style="background:'+k.bg+';">'+
            '<i class="fa fa-box" style="color:'+k.clr+';font-size:20px;"></i>'+
        '</div>'+
        '<div style="flex:1;">'+
            '<div class="lk-aset-hdr-inv">'+s(a.no_inventaris||'')+'</div>'+
            '<div class="lk-aset-hdr-name">'+s(a.nama_aset||'—')+'</div>'+
            '<div class="lk-chips">'+
                '<span class="lk-chip" style="background:'+k.chip+';color:'+k.ct+';">'+
                    '<i class="fa fa-circle" style="font-size:5px;"></i> '+s(a.kondisi||'')+'</span>'+
                '<span class="lk-chip" style="background:'+sv.chip+';color:'+sv.ct+';">'+s(a.status_pakai||'')+'</span>'+
                (a.kategori?'<span class="lk-chip" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);">'+s(a.kategori)+'</span>':'')+
                gw+
            '</div>'+
        '</div>';

    /* Body */
    var kpct={'Baik':100,'Dalam Perbaikan':50,'Rusak':20,'Tidak Aktif':0}[a.kondisi]||0;
    var harga=a.harga_beli?'Rp '+parseInt(a.harga_beli).toLocaleString('id-ID'):'—';
    document.getElementById('dc-body').innerHTML=
        '<div class="lk-section-title">Informasi Aset</div>'+
        '<table class="lk-info-table">'+
            '<tr>'+
                td('fa-building','Lokasi / Bagian',
                    (a.bagian_kode?'<span class="lk-mono">['+s(a.bagian_kode)+']</span> ':'')+s(a.bagian_nama||'—'),
                    a.bagian_lokasi?s(a.bagian_lokasi):'')+
                td('fa-user','Penanggung Jawab',s(a.pj_nama_db||a.penanggung_jawab||'—'),a.pj_divisi?s(a.pj_divisi):'')+
            '</tr>'+
            '<tr>'+
                td('fa-tag','Merek / Model',s((a.merek||'—')+(a.model_aset?' · '+a.model_aset:'')),'')+
                td('fa-fingerprint','Serial Number','<span class="lk-mono">'+s(a.serial_number||'—')+'</span>','')+
            '</tr>'+
            '<tr>'+
                td('fa-calendar','Tanggal Beli',a.tanggal_beli?fmtD(a.tanggal_beli):'—','')+
                td('fa-shield-halved','Garansi s/d',a.garansi_sampai?fmtD(a.garansi_sampai):'—','')+
            '</tr>'+
            '<tr>'+
                td('fa-coins','Harga Beli',harga,'')+
                td('fa-right-left','Total Mutasi',mutasi.length+' perpindahan','')+
            '</tr>'+
        '</table>'+
        '<div class="lk-k-wrap">'+
            '<div class="lk-k-row">'+
                '<span style="display:flex;align-items:center;gap:4px;"><i class="fa fa-gauge" style="font-size:9px;"></i> Kondisi Aset</span>'+
                '<span style="font-weight:700;color:'+k.clr+';">'+s(a.kondisi||'')+'</span>'+
            '</div>'+
            '<div class="lk-k-track"><div class="lk-k-fill" style="width:'+kpct+'%;background:'+k.clr+';"></div></div>'+
        '</div>'+
        (a.keterangan?'<div class="lk-ktr"><i class="fa fa-circle-info"></i>'+s(a.keterangan)+'</div>':'');

    renderTL(a,mutasi);
    document.getElementById('btn-print').href=APP_URL+'/pages/cetak_lacak_aset.php?id='+a.id;
}

function td(ico,lbl,val,sub){
    return '<td><div class="lk-td-lbl"><i class="fa '+ico+'"></i> '+lbl+'</div>'+
        '<div class="lk-td-val">'+val+'</div>'+
        (sub?'<div class="lk-td-sub"><i class="fa fa-location-dot" style="font-size:9px;margin-right:2px;"></i>'+sub+'</div>':'')+
    '</td>';
}

/* ─── TIMELINE ─── */
function renderTL(aset,mutasi){
    var tw=document.getElementById('tl-wrap'),tc=document.getElementById('tl-count');
    var items=[];
    items.push({type:'awal',date:aset.created_at,title:'Aset Terdaftar',sub:'Pertama kali dimasukkan ke sistem inventaris.'});
    var jmap={'keduanya':'Pindah Lokasi + PIC','pindah_lokasi':'Pindah Lokasi','pindah_pic':'Pindah PIC'};
    mutasi.forEach(function(m){
        items.push({type:m.status_mutasi==='batal'?'batal':'mutasi',date:m.created_at,
            title:jmap[m.jenis]||m.jenis,sub:'No. '+s(m.no_mutasi)+' · '+s(m.dibuat_nama||'—'),
            dari:m.dari_bagian_nama||'—',dari_pic:m.dari_pic_nama||'',
            ke:m.ke_bagian_nama||(m.jenis==='pindah_pic'?'(sama)':'—'),ke_pic:m.ke_pic_nama||'',
            kondisi_sesudah:m.kondisi_sesudah,batal:m.status_mutasi==='batal'});
    });
    if(mutasi.length>0) items.push({type:'kini',title:'Posisi Saat Ini',
        lokasi:aset.bagian_nama||'Tanpa Lokasi',pj:aset.pj_nama_db||aset.penanggung_jawab||'Tanpa PIC'});
    tc.textContent=items.length+' entri';
    if(!items.length){tw.innerHTML='<div class="lk-no-result"><i class="fa fa-inbox"></i>Belum ada riwayat</div>';return;}

    var dcls={awal:'d-awal',mutasi:'d-mutasi',kini:'d-kini',batal:'d-batal'};
    var dico={awal:'fa-plus',mutasi:'fa-right-left',kini:'fa-location-dot',batal:'fa-ban'};
    var tcls={awal:'tc-awal',mutasi:'',kini:'tc-kini',batal:'tc-batal'};
    var bstyle={
        awal:'background:#eef2ff;color:#6366f1;',mutasi:'background:#f0fdf4;color:#15803d;',
        kini:'background:#fef9c3;color:#a16207;',batal:'background:#fee2e2;color:#b91c1c;'};
    var btxt={awal:'Registrasi',mutasi:'Mutasi',kini:'Sekarang',batal:'Dibatalkan'};
    var kClr={'Baik':'#10b981','Rusak':'#ef4444','Dalam Perbaikan':'#f59e0b','Tidak Aktif':'#94a3b8'};

    var html='<div class="lk-tl-wrap">';
    items.forEach(function(item,idx){
        var t=item.type;
        html+='<div class="lk-tl-item" style="animation-delay:'+(idx*.05)+'s;">'+
            '<div class="lk-tl-dot '+dcls[t]+'"><i class="fa '+dico[t]+'"></i></div>'+
            '<div class="lk-tc '+tcls[t]+'">';
        html+='<div class="lk-tc-top">';
        if(item.date) html+='<span class="lk-tc-date"><i class="fa fa-clock" style="font-size:8px;"></i> '+fmtDT(item.date)+'</span>';
        html+='<span class="lk-tc-badge" style="'+bstyle[t]+'">'+btxt[t]+'</span>';
        if(item.batal) html+='<span class="lk-tc-badge" style="background:#fee2e2;color:#b91c1c;">Dibatalkan</span>';
        html+='</div>';
        html+='<div class="lk-tc-title">'+s(item.title)+'</div>';
        if(item.sub) html+='<div class="lk-tc-sub">'+item.sub+'</div>';

        if(t==='mutasi'&&!item.batal){
            html+='<div class="lk-tc-route">'+
                '<div><div class="lk-tc-r-lbl">Dari</div><div class="lk-tc-r-val">'+s(item.dari)+'</div>'+
                (item.dari_pic?'<div class="lk-tc-r-pic"><i class="fa fa-user" style="font-size:8px;"></i> '+s(item.dari_pic)+'</div>':'')+
                '</div>'+
                '<div class="lk-tc-r-arr"><i class="fa fa-arrow-right"></i></div>'+
                '<div><div class="lk-tc-r-lbl">Ke</div><div class="lk-tc-r-val to">'+s(item.ke)+'</div>'+
                (item.ke_pic?'<div class="lk-tc-r-pic"><i class="fa fa-user" style="font-size:8px;"></i> '+s(item.ke_pic)+'</div>':'')+
                '</div>'+
            '</div>';
            if(item.kondisi_sesudah){var kc2=kClr[item.kondisi_sesudah]||'#94a3b8',kr=hexR(kc2);
                html+='<div><span class="lk-tc-k-after" style="background:rgba('+kr+',.1);color:'+kc2+';">'+
                    '<i class="fa fa-circle" style="font-size:5px;"></i> Kondisi sesudah: '+s(item.kondisi_sesudah)+'</span></div>';}
        }
        if(t==='kini'){
            html+='<div class="lk-kini-grid">'+
                '<div class="lk-kini-cell"><div class="lk-kini-lbl"><i class="fa fa-building" style="font-size:8px;margin-right:2px;"></i>Lokasi</div><div class="lk-kini-val">'+s(item.lokasi)+'</div></div>'+
                '<div class="lk-kini-cell"><div class="lk-kini-lbl"><i class="fa fa-user" style="font-size:8px;margin-right:2px;"></i>PIC</div><div class="lk-kini-val">'+s(item.pj)+'</div></div>'+
            '</div>';
        }
        html+='</div></div>';
    });
    html+='</div>';
    tw.innerHTML=html;
}

/* ─── HELPERS ─── */
function s(v){return String(v||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmtD(d){if(!d)return'—';return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});}
function fmtDT(d){if(!d)return'—';var dt=new Date(d);return dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'})+' · '+dt.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});}
function hexR(hex){var r=/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);return r?parseInt(r[1],16)+','+parseInt(r[2],16)+','+parseInt(r[3],16):'0,0,0';}
</script>

<?php include '../includes/footer.php'; ?>