<?php
// pages/lacak_aset_it.php — Lacak Aset IT
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

$page_title  = 'Lacak Aset IT';
$active_menu = 'lacak_aset';

// ── AJAX: Live search aset ────────────────────────────────────────────────────
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
        ORDER BY a.nama_aset ASC
        LIMIT 25
    ");
    $st->execute([$q,$q,$q,$q,$q,$q]);
    header('Content-Type: application/json');
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: Detail aset + riwayat mutasi ───────────────────────────────────────
if (isset($_GET['detail'])) {
    $id = (int)$_GET['detail'];

    // Data aset lengkap
    $st = $pdo->prepare("
        SELECT a.*,
               b.nama AS bagian_nama, b.kode AS bagian_kode, b.lokasi AS bagian_lokasi,
               u.nama AS pj_nama_db, u.divisi AS pj_divisi, u.role AS pj_role
        FROM aset_it a
        LEFT JOIN bagian b ON b.id = a.bagian_id
        LEFT JOIN users  u ON u.id = a.pj_user_id
        WHERE a.id = ?
    ");
    $st->execute([$id]);
    $aset = $st->fetch(PDO::FETCH_ASSOC);

    // Riwayat mutasi
    $sm = $pdo->prepare("
        SELECT m.*, a2.nama_aset, a2.no_inventaris
        FROM mutasi_aset m
        LEFT JOIN aset_it a2 ON a2.id = m.aset_id
        WHERE m.aset_id = ?
        ORDER BY m.tanggal_mutasi ASC, m.created_at ASC
    ");
    $sm->execute([$id]);
    $mutasi = $sm->fetchAll(PDO::FETCH_ASSOC);

    // Riwayat tiket terkait (jika ada)
    $tiket = [];
    try {
        $st2 = $pdo->prepare("
            SELECT id, judul, status, prioritas, created_at
            FROM tiket WHERE aset_id = ? ORDER BY created_at DESC LIMIT 5
        ");
        $st2->execute([$id]);
        $tiket = $st2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    header('Content-Type: application/json');
    echo json_encode(['aset' => $aset, 'mutasi' => $mutasi, 'tiket' => $tiket]);
    exit;
}

// ── Stats untuk hero ──────────────────────────────────────────────────────────
$total_aset   = (int)$pdo->query("SELECT COUNT(*) FROM aset_it")->fetchColumn();
$total_mutasi = 0;
try { $total_mutasi = (int)$pdo->query("SELECT COUNT(*) FROM mutasi_aset WHERE status_mutasi='selesai'")->fetchColumn(); } catch(Exception $e){}
$total_bagian = (int)$pdo->query("SELECT COUNT(*) FROM bagian WHERE status='aktif'")->fetchColumn();
$aset_terpakai= (int)$pdo->query("SELECT COUNT(*) FROM aset_it WHERE status_pakai='Terpakai'")->fetchColumn();

include '../includes/header.php';
?>

<style>
/* ══════════════════════════════════════════════
   LACAK ASET IT — Tracking Interface
   Tone: Precision/Industrial — dark accents,
   teal highlights, clean data density
══════════════════════════════════════════════ */

/* ── Hero Search Bar ── */
.lacak-hero {
    background: linear-gradient(135deg, #0a0f14 0%, #0d1a2a 50%, #0f1f35 100%);
    border-radius: 14px;
    padding: 28px 32px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.lacak-hero::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(0,229,176,.12) 0%, transparent 70%);
    pointer-events: none;
}
.lacak-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; left: 100px;
    width: 300px; height: 200px;
    background: radial-gradient(circle, rgba(99,102,241,.08) 0%, transparent 70%);
    pointer-events: none;
}
.lacak-hero-title {
    font-size: 22px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.lacak-hero-sub {
    font-size: 12px;
    color: rgba(255,255,255,.4);
    margin-bottom: 20px;
}

/* ── Search Input ── */
.lacak-search-wrap {
    position: relative;
    max-width: 600px;
}
.lacak-search-inp {
    width: 100%;
    padding: 14px 50px 14px 48px;
    background: rgba(255,255,255,.06);
    border: 1.5px solid rgba(0,229,176,.25);
    border-radius: 10px;
    color: #fff;
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
    box-sizing: border-box;
}
.lacak-search-inp::placeholder { color: rgba(255,255,255,.3); }
.lacak-search-inp:focus {
    border-color: #00e5b0;
    background: rgba(255,255,255,.09);
    box-shadow: 0 0 0 3px rgba(0,229,176,.12);
}
.lacak-search-icon {
    position: absolute;
    left: 15px; top: 50%;
    transform: translateY(-50%);
    color: #00e5b0;
    font-size: 15px;
    pointer-events: none;
}
.lacak-search-clear {
    position: absolute;
    right: 14px; top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,.3);
    cursor: pointer;
    font-size: 14px;
    display: none;
    background: none;
    border: none;
    padding: 0;
}
.lacak-search-clear:hover { color: #ef4444; }

/* ── Suggestions dropdown ── */
.lacak-suggestions {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    left: 0; right: 0;
    background: #1a2535;
    border: 1px solid rgba(0,229,176,.2);
    border-radius: 10px;
    max-height: 320px;
    overflow-y: auto;
    z-index: 9999;
    box-shadow: 0 16px 48px rgba(0,0,0,.4);
}
.lacak-suggestions.show { display: block; }
.lacak-sug-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 16px;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,.05);
    transition: background .12s;
}
.lacak-sug-item:hover { background: rgba(0,229,176,.07); }
.lacak-sug-item:last-child { border-bottom: none; }
.lacak-sug-ikon {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: rgba(0,229,176,.1);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.lacak-sug-ikon i { color: #00e5b0; font-size: 14px; }
.lacak-sug-inv {
    font-family: 'Courier New', monospace;
    font-size: 10px; font-weight: 700;
    background: rgba(99,102,241,.2);
    color: #a78bfa;
    padding: 1px 6px; border-radius: 3px;
    margin-bottom: 2px; display: inline-block;
}
.lacak-sug-nama { font-size: 12.5px; font-weight: 600; color: #e2e8f0; }
.lacak-sug-meta { font-size: 10.5px; color: rgba(255,255,255,.35); margin-top: 1px; }

/* ── Hero stats ── */
.hero-stats {
    display: flex;
    gap: 20px;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid rgba(255,255,255,.07);
    flex-wrap: wrap;
}
.hero-stat { display: flex; align-items: center; gap: 8px; }
.hero-stat-val { font-size: 20px; font-weight: 800; color: #00e5b0; line-height: 1; }
.hero-stat-lbl { font-size: 10px; color: rgba(255,255,255,.35); line-height: 1.3; }
.hero-stat-sep { width: 1px; height: 28px; background: rgba(255,255,255,.08); }

/* ── Layout grid ── */
.lacak-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 16px;
    align-items: start;
}

/* ── Empty state ── */
.lacak-empty {
    padding: 48px 24px;
    text-align: center;
    color: #64748b;
}
.lacak-empty-icon {
    width: 72px; height: 72px;
    background: #f1f5f9;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
.lacak-empty-icon i { font-size: 28px; color: #cbd5e1; }

/* ── Daftar hasil aset ── */
.aset-list-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background .12s;
    position: relative;
}
.aset-list-item:hover { background: #f8fafc; }
.aset-list-item.active {
    background: linear-gradient(90deg, #f0fdf9 0%, #f8fafc 100%);
    border-left: 3px solid #00c896;
}
.aset-list-item.active::after {
    content: '';
    position: absolute;
    right: 0; top: 50%;
    transform: translateY(-50%);
    width: 0; height: 0;
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
    border-right: 8px solid #f0f4ff;
}
.ali-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.ali-inv {
    font-family: 'Courier New', monospace;
    font-size: 10px; font-weight: 700;
    color: #6366f1; background: #eef2ff;
    padding: 1px 6px; border-radius: 3px;
    margin-bottom: 2px; display: inline-block;
}
.ali-nama { font-size: 12.5px; font-weight: 700; color: #1e293b; line-height: 1.3; }
.ali-meta { font-size: 10.5px; color: #94a3b8; margin-top: 2px; }

/* ── Detail panel ── */
.detail-panel {
    display: none;
}
.detail-panel.show { display: block; }

/* ── Aset detail card ── */
.aset-card-header {
    background: linear-gradient(135deg, #0a0f14, #132030);
    border-radius: 12px 12px 0 0;
    padding: 20px 22px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}
.aset-card-body { padding: 20px 22px; }
.aset-card-icon-big {
    width: 52px; height: 52px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* ── Info grid ── */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}
.info-item { }
.info-lbl {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; color: #94a3b8; margin-bottom: 3px;
}
.info-val {
    font-size: 12.5px; font-weight: 600; color: #1e293b;
    display: flex; align-items: center; gap: 5px;
}

/* ── Status chips ── */
.chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700;
}
.chip-baik      { background: #dcfce7; color: #15803d; }
.chip-rusak     { background: #fee2e2; color: #b91c1c; }
.chip-perbaikan { background: #fef9c3; color: #a16207; }
.chip-tidak-aktif{ background: #f1f5f9; color: #475569; }
.chip-terpakai  { background: #dbeafe; color: #1e40af; }
.chip-tidak-terpakai { background: #d1fae5; color: #065f46; }
.chip-dipinjam  { background: #fef3c7; color: #92400e; }

/* ══ TIMELINE ══ */
.timeline-wrap { position: relative; padding-left: 28px; }
.timeline-wrap::before {
    content: '';
    position: absolute;
    left: 9px; top: 6px; bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, #00e5b0 0%, #6366f1 50%, #e2e8f0 100%);
    border-radius: 2px;
}

.tl-item {
    position: relative;
    margin-bottom: 18px;
    animation: tlIn .3s ease both;
}
@keyframes tlIn {
    from { opacity:0; transform:translateX(-8px); }
    to   { opacity:1; transform:translateX(0); }
}
.tl-dot {
    position: absolute;
    left: -23px; top: 4px;
    width: 16px; height: 16px;
    border-radius: 50%;
    border: 2px solid #fff;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 7px;
    color: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.tl-dot-awal    { background: #6366f1; }
.tl-dot-mutasi  { background: #00c896; }
.tl-dot-kini    { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.2); }
.tl-dot-tiket   { background: #ef4444; }

.tl-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    padding: 11px 14px;
    transition: box-shadow .15s;
}
.tl-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.07); }
.tl-card.tl-kini {
    border-color: #f59e0b;
    background: linear-gradient(135deg, #fffbeb, #fff);
    box-shadow: 0 0 0 2px rgba(245,158,11,.15);
}
.tl-card.tl-awal { border-color: #c7d2fe; background: linear-gradient(135deg, #eef2ff, #fff); }
.tl-card.tl-batal { opacity: .55; border-style: dashed; }

.tl-date {
    font-size: 10px; font-weight: 700;
    color: #94a3b8; text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 5px;
    display: flex; align-items: center; gap: 5px;
}
.tl-title { font-size: 12.5px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
.tl-sub   { font-size: 11px; color: #64748b; line-height: 1.6; }

/* ── Pihak dari→ke ── */
.tl-pihak {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 7px;
    padding: 7px 10px;
    background: #f8fafc;
    border-radius: 6px;
}
.tl-pihak-dari { flex:1; font-size:11px; color:#92400e; }
.tl-pihak-arr  { color:#00c896; font-size:13px; flex-shrink:0; }
.tl-pihak-ke   { flex:1; font-size:11px; color:#065f46; font-weight:700; }

/* ── No data ── */
.tl-nodata {
    text-align: center; padding: 24px;
    color: #94a3b8; font-size: 12px;
}

/* ── Section title in panel ── */
.panel-sec {
    font-size: 10px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1px; color: #94a3b8;
    margin: 18px 0 10px;
    display: flex; align-items: center; gap: 8px;
}
.panel-sec::after {
    content: ''; flex: 1;
    height: 1px; background: #f1f5f9;
}

/* ── Loading ── */
.lacak-loading {
    display: none;
    text-align: center;
    padding: 40px;
    color: #64748b;
}
.lacak-loading.show { display: block; }
.spin {
    display: inline-block;
    width: 28px; height: 28px;
    border: 3px solid #e2e8f0;
    border-top-color: #00c896;
    border-radius: 50%;
    animation: spin .7s linear infinite;
    margin-bottom: 8px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Kondisi progress bar ── */
.kondisi-bar { height: 4px; border-radius: 2px; margin-top: 4px; }

/* ── Print button ── */
.btn-lacak-print {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 7px;
    background: linear-gradient(135deg,#00e5b0,#00c896);
    color: #0a0f14; font-size: 11.5px; font-weight: 700;
    border: none; cursor: pointer; font-family: inherit;
    text-decoration: none; transition: opacity .15s;
}
.btn-lacak-print:hover { opacity: .85; }

/* ── Responsive ── */
@media (max-width: 900px) {
    .lacak-grid { grid-template-columns: 1fr; }
}
</style>

<div class="page-header">
    <h4><i class="fa fa-location-crosshairs text-primary"></i> &nbsp;Lacak Aset IT</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <span class="sep">/</span>
        <a href="<?= APP_URL ?>/pages/aset_it.php">Aset IT</a>
        <span class="sep">/</span>
        <span class="cur">Lacak Aset</span>
    </div>
</div>

<div class="content">

    <!-- ══ HERO SEARCH ══ -->
    <div class="lacak-hero">
        <div class="lacak-hero-title">
            <div style="width:36px;height:36px;background:rgba(0,229,176,.15);border:1px solid rgba(0,229,176,.3);border-radius:9px;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-location-crosshairs" style="color:#00e5b0;font-size:15px;"></i>
            </div>
            Lacak Posisi &amp; Riwayat Aset IT
        </div>
        <div class="lacak-hero-sub">Cari aset berdasarkan nama, nomor inventaris, bagian, PIC, atau serial number</div>

        <div class="lacak-search-wrap">
            <i class="fa fa-magnifying-glass lacak-search-icon"></i>
            <input type="text" class="lacak-search-inp" id="lacak-inp"
                   placeholder="Cari: nama aset, no. inventaris, bagian, PIC, serial number…"
                   autocomplete="off">
            <button class="lacak-search-clear" id="lacak-clear" onclick="clearSearch()">
                <i class="fa fa-times"></i>
            </button>
            <div class="lacak-suggestions" id="lacak-suggestions"></div>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <div>
                    <div class="hero-stat-val"><?= $total_aset ?></div>
                    <div class="hero-stat-lbl">Total Aset<br>Terdaftar</div>
                </div>
            </div>
            <div class="hero-stat-sep"></div>
            <div class="hero-stat">
                <div>
                    <div class="hero-stat-val"><?= $aset_terpakai ?></div>
                    <div class="hero-stat-lbl">Aset<br>Terpakai</div>
                </div>
            </div>
            <div class="hero-stat-sep"></div>
            <div class="hero-stat">
                <div>
                    <div class="hero-stat-val"><?= $total_mutasi ?></div>
                    <div class="hero-stat-lbl">Total Mutasi<br>Tercatat</div>
                </div>
            </div>
            <div class="hero-stat-sep"></div>
            <div class="hero-stat">
                <div>
                    <div class="hero-stat-val"><?= $total_bagian ?></div>
                    <div class="hero-stat-lbl">Bagian<br>Aktif</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ MAIN GRID ══ -->
    <div class="lacak-grid" id="lacak-grid" style="display:none;">

        <!-- Kolom kiri: daftar hasil -->
        <div class="panel" style="padding:0;">
            <div class="panel-hd">
                <h5 id="hasil-label">
                    <i class="fa fa-list text-primary"></i> Hasil Pencarian
                </h5>
                <span id="hasil-count" style="font-size:11px;color:#94a3b8;"></span>
            </div>
            <div id="aset-list" style="max-height:600px;overflow-y:auto;">
                <!-- diisi JS -->
            </div>
        </div>

        <!-- Kolom kanan: detail + timeline -->
        <div>
            <!-- Loading state -->
            <div class="lacak-loading" id="detail-loading">
                <div class="spin"></div>
                <div style="font-size:12px;">Memuat detail aset…</div>
            </div>

            <!-- Empty state sebelum pilih -->
            <div class="panel" id="detail-empty">
                <div class="lacak-empty">
                    <div class="lacak-empty-icon">
                        <i class="fa fa-hand-pointer"></i>
                    </div>
                    <div style="font-weight:700;color:#475569;margin-bottom:6px;">Pilih aset dari daftar</div>
                    <div style="font-size:12px;color:#94a3b8;">Klik salah satu aset di sebelah kiri<br>untuk melihat detail dan riwayat perpindahannya</div>
                </div>
            </div>

            <!-- Detail panel -->
            <div id="detail-panel" class="detail-panel">

                <!-- Card aset -->
                <div class="panel" style="padding:0;margin-bottom:14px;">
                    <div class="aset-card-header" id="dc-header">
                        <!-- diisi JS -->
                    </div>
                    <div class="aset-card-body" id="dc-body">
                        <!-- diisi JS -->
                    </div>
                </div>

                <!-- Timeline riwayat -->
                <div class="panel">
                    <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;">
                        <h5>
                            <i class="fa fa-clock-rotate-left text-primary"></i>
                            Riwayat Perjalanan Aset
                        </h5>
                        <div style="display:flex;gap:7px;align-items:center;">
                            <span id="tl-count" style="font-size:11px;color:#94a3b8;"></span>
                            <a id="btn-print-aset" href="#" target="_blank" class="btn-lacak-print">
                                <i class="fa fa-print"></i> Cetak PDF
                            </a>
                        </div>
                    </div>
                    <div style="padding:16px 20px;" id="timeline-wrap">
                        <!-- diisi JS -->
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Empty state awal (sebelum search) -->
    <div id="lacak-start">
        <div class="panel">
            <div class="lacak-empty" style="padding:60px 24px;">
                <div class="lacak-empty-icon" style="width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,#f0fdf9,#d1fae5);">
                    <i class="fa fa-magnifying-glass-location" style="font-size:32px;color:#00c896;"></i>
                </div>
                <div style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:8px;">Mulai Lacak Aset</div>
                <div style="font-size:12.5px;color:#64748b;max-width:360px;margin:0 auto;line-height:1.7;">
                    Ketik minimal 2 karakter di kolom pencarian di atas untuk mulai melacak posisi dan riwayat perpindahan aset IT.
                </div>
                <div style="display:flex;gap:10px;justify-content:center;margin-top:20px;flex-wrap:wrap;">
                    <?php foreach(['Laptop','Printer','Server','Switch','Monitor'] as $hint): ?>
                    <button onclick="quickSearch('<?= $hint ?>')"
                        style="padding:6px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:20px;
                               font-size:11.5px;cursor:pointer;color:#475569;font-family:inherit;transition:all .15s;"
                        onmouseover="this.style.background='#e0fdf4';this.style.borderColor='#00c896';this.style.color='#0f766e';"
                        onmouseout="this.style.background='#f1f5f9';this.style.borderColor='#e2e8f0';this.style.color='#475569';">
                        <i class="fa fa-tag" style="font-size:10px;"></i> <?= $hint ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.content -->

<script>
const APP_URL  = '<?= APP_URL ?>';
let _timer     = null;
let _activeId  = null;

/* ═══════════════════════════════════════════════════════════
   SEARCH
═══════════════════════════════════════════════════════════ */
document.getElementById('lacak-inp').addEventListener('input', function() {
    var q = this.value.trim();
    document.getElementById('lacak-clear').style.display = q ? 'block' : 'none';
    clearTimeout(_timer);
    if (q.length < 2) {
        hideSuggestions();
        return;
    }
    _timer = setTimeout(function(){ doSearch(q); }, 260);
});

document.getElementById('lacak-inp').addEventListener('keydown', function(e) {
    if (e.key === 'Escape') clearSearch();
});

function quickSearch(kata) {
    document.getElementById('lacak-inp').value = kata;
    document.getElementById('lacak-clear').style.display = 'block';
    doSearch(kata);
}

function doSearch(q) {
    fetch(APP_URL + '/pages/lacak_aset_it.php?search=1&q=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(data) {
            renderSuggestions(data, q);
            renderList(data);
        });
}

/* ── Suggestions dropdown ── */
function renderSuggestions(data, q) {
    var sug = document.getElementById('lacak-suggestions');
    sug.innerHTML = '';
    if (!data.length) {
        sug.innerHTML = '<div style="padding:14px 16px;font-size:12px;color:rgba(255,255,255,.3);text-align:center;">'
            + '<i class="fa fa-inbox" style="margin-right:6px;"></i>Tidak ditemukan</div>';
        sug.classList.add('show');
        return;
    }
    var shown = data.slice(0, 8);
    shown.forEach(function(a) {
        var el = document.createElement('div');
        el.className = 'lacak-sug-item';
        var kclr = {'Baik':'#00c896','Rusak':'#ef4444','Dalam Perbaikan':'#f59e0b','Tidak Aktif':'#64748b'}[a.kondisi] || '#64748b';
        el.innerHTML =
            '<div class="lacak-sug-ikon"><i class="fa fa-box"></i></div>' +
            '<div style="flex:1;min-width:0;">' +
                '<div><span class="lacak-sug-inv">' + escH(a.no_inventaris) + '</span></div>' +
                '<div class="lacak-sug-nama">' + escH(a.nama_aset) + '</div>' +
                '<div class="lacak-sug-meta">' + escH(a.merek||'') + ' · ' + escH(a.bagian_nama||'Tanpa Lokasi') + '</div>' +
            '</div>' +
            '<div style="text-align:right;">' +
                '<span style="font-size:9px;font-weight:700;color:' + kclr + ';">' + escH(a.kondisi||'') + '</span>' +
            '</div>';
        el.onclick = function() {
            hideSuggestions();
            pilihAset(a.id, a.nama_aset);
        };
        sug.appendChild(el);
    });
    if (data.length > 8) {
        sug.innerHTML += '<div style="padding:8px 16px;font-size:10.5px;color:rgba(255,255,255,.3);text-align:center;border-top:1px solid rgba(255,255,255,.05);">'
            + '+ ' + (data.length - 8) + ' aset lainnya di bawah</div>';
    }
    sug.classList.add('show');
}

function hideSuggestions() {
    document.getElementById('lacak-suggestions').classList.remove('show');
}

document.addEventListener('click', function(e) {
    var wrap = document.querySelector('.lacak-search-wrap');
    if (wrap && !wrap.contains(e.target)) hideSuggestions();
});

/* ── Daftar hasil kiri ── */
function renderList(data) {
    document.getElementById('lacak-start').style.display = 'none';
    document.getElementById('lacak-grid').style.display  = 'grid';

    var list = document.getElementById('aset-list');
    var count= document.getElementById('hasil-count');
    count.textContent = data.length + ' ditemukan';

    if (!data.length) {
        list.innerHTML = '<div class="lacak-empty"><div class="lacak-empty-icon"><i class="fa fa-box-open"></i></div>'
            + '<div style="font-size:12px;color:#64748b;">Tidak ada aset ditemukan</div></div>';
        return;
    }

    var palettes = [
        ['#dbeafe','#1d4ed8'],['#d1fae5','#065f46'],['#fef3c7','#92400e'],
        ['#ede9fe','#5b21b6'],['#fce7f3','#9d174d'],['#f0fdf4','#15803d'],
    ];
    list.innerHTML = '';
    data.forEach(function(a, i) {
        var pal = palettes[i % palettes.length];
        var spChip = {
            'Terpakai':       ['#dbeafe','#1e40af'],
            'Tidak Terpakai': ['#d1fae5','#065f46'],
            'Dipinjam':       ['#fef3c7','#92400e'],
        }[a.status_pakai] || ['#f1f5f9','#64748b'];

        var el = document.createElement('div');
        el.className = 'aset-list-item';
        el.dataset.id = a.id;
        el.innerHTML =
            '<div class="ali-icon" style="background:' + pal[0] + ';">' +
                '<i class="fa fa-box" style="color:' + pal[1] + ';font-size:15px;"></i>' +
            '</div>' +
            '<div style="flex:1;min-width:0;">' +
                '<div><span class="ali-inv">' + escH(a.no_inventaris) + '</span></div>' +
                '<div class="ali-nama">' + escH(a.nama_aset) + '</div>' +
                '<div class="ali-meta">' +
                    '<i class="fa fa-building" style="font-size:9px;"></i> ' + escH(a.bagian_nama||'—') + ' · ' +
                    '<i class="fa fa-user" style="font-size:9px;"></i> ' + escH(a.pj_nama||'—') +
                '</div>' +
            '</div>' +
            '<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:' + spChip[0] + ';color:' + spChip[1] + ';white-space:nowrap;">' +
                escH(a.status_pakai||'Terpakai') +
            '</span>';
        el.onclick = function() { pilihAset(a.id, a.nama_aset); };
        list.appendChild(el);
    });
}

function clearSearch() {
    document.getElementById('lacak-inp').value = '';
    document.getElementById('lacak-clear').style.display = 'none';
    hideSuggestions();
    document.getElementById('lacak-start').style.display = 'block';
    document.getElementById('lacak-grid').style.display  = 'none';
    _activeId = null;
}

/* ═══════════════════════════════════════════════════════════
   PILIH ASET → load detail
═══════════════════════════════════════════════════════════ */
function pilihAset(id, nama) {
    _activeId = id;
    hideSuggestions();

    // Highlight item aktif
    document.querySelectorAll('.aset-list-item').forEach(function(el) {
        el.classList.toggle('active', parseInt(el.dataset.id) === id);
    });

    // Show loading
    document.getElementById('detail-empty').style.display   = 'none';
    document.getElementById('detail-panel').classList.remove('show');
    document.getElementById('detail-loading').classList.add('show');

    fetch(APP_URL + '/pages/lacak_aset_it.php?detail=' + id)
        .then(function(r){ return r.json(); })
        .then(function(data) {
            document.getElementById('detail-loading').classList.remove('show');
            renderDetail(data.aset, data.mutasi, data.tiket || []);
            document.getElementById('detail-panel').classList.add('show');
        });
}

/* ═══════════════════════════════════════════════════════════
   RENDER DETAIL CARD
═══════════════════════════════════════════════════════════ */
function renderDetail(a, mutasi, tiket) {
    if (!a) return;

    // ── Warna kondisi ──
    var kMap = {
        'Baik':            ['#00c896','#d1fae5','#065f46'],
        'Rusak':           ['#ef4444','#fee2e2','#991b1b'],
        'Dalam Perbaikan': ['#f59e0b','#fef3c7','#92400e'],
        'Tidak Aktif':     ['#94a3b8','#f1f5f9','#475569'],
    };
    var kClr = kMap[a.kondisi] || ['#94a3b8','#f1f5f9','#475569'];

    var spMap = {
        'Terpakai':       ['#1d4ed8','#dbeafe'],
        'Tidak Terpakai': ['#065f46','#d1fae5'],
        'Dipinjam':       ['#92400e','#fef3c7'],
    };
    var spClr = spMap[a.status_pakai] || ['#475569','#f1f5f9'];

    // ── Header ──
    var garansiInfo = '';
    if (a.garansi_sampai) {
        var gExp  = new Date(a.garansi_sampai) < new Date();
        var gSoon = !gExp && new Date(a.garansi_sampai) < new Date(Date.now() + 30*24*3600*1000);
        if (gExp) garansiInfo = '<span style="color:#ef4444;font-size:10px;font-weight:700;"><i class="fa fa-triangle-exclamation"></i> Garansi Expired</span>';
        else if (gSoon) garansiInfo = '<span style="color:#f59e0b;font-size:10px;font-weight:700;"><i class="fa fa-clock"></i> Garansi Segera Habis</span>';
    }

    document.getElementById('dc-header').innerHTML =
        '<div class="aset-card-icon-big" style="background:rgba(' + hexToRgb(kClr[0]) + ',.15);border:1px solid rgba(' + hexToRgb(kClr[0]) + ',.3);">' +
            '<i class="fa fa-box" style="color:' + kClr[0] + ';font-size:22px;"></i>' +
        '</div>' +
        '<div style="flex:1;">' +
            '<div style="font-family:monospace;font-size:11px;font-weight:700;color:rgba(255,255,255,.4);margin-bottom:3px;">' + escH(a.no_inventaris||'') + '</div>' +
            '<div style="font-size:17px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:5px;">' + escH(a.nama_aset||'—') + '</div>' +
            '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">' +
                '<span style="padding:3px 10px;border-radius:20px;font-size:10.5px;font-weight:700;background:' + kClr[1] + ';color:' + kClr[2] + ';">' + escH(a.kondisi||'') + '</span>' +
                '<span style="padding:3px 10px;border-radius:20px;font-size:10.5px;font-weight:700;background:rgba(' + hexToRgb(spClr[1]) + ',.2);color:rgba(255,255,255,.8);">' + escH(a.status_pakai||'Terpakai') + '</span>' +
                (a.kategori ? '<span style="padding:3px 10px;border-radius:20px;font-size:10.5px;font-weight:700;background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);">' + escH(a.kategori) + '</span>' : '') +
                garansiInfo +
            '</div>' +
        '</div>';

    // ── Body ──
    var kondisiPct = {'Baik':100,'Dalam Perbaikan':50,'Rusak':20,'Tidak Aktif':0}[a.kondisi] || 0;
    var hargaFmt   = a.harga_beli ? 'Rp ' + parseInt(a.harga_beli).toLocaleString('id-ID') : '—';

    document.getElementById('dc-body').innerHTML =
        '<div class="info-grid">' +
            infoItem('fa-building','Lokasi / Bagian', (a.bagian_kode ? '['+escH(a.bagian_kode)+'] ' : '') + escH(a.bagian_nama||'—') + (a.bagian_lokasi ? '<br><span style="font-size:10.5px;color:#94a3b8;"><i class="fa fa-location-dot" style="font-size:9px;"></i> ' + escH(a.bagian_lokasi) + '</span>' : '')) +
            infoItem('fa-user','Penanggung Jawab', escH(a.pj_nama_db||a.penanggung_jawab||'—') + (a.pj_divisi ? '<br><span style="font-size:10.5px;color:#94a3b8;">' + escH(a.pj_divisi) + '</span>' : '')) +
            infoItem('fa-tag','Merek / Model', escH((a.merek||'—') + (a.model_aset ? ' / ' + a.model_aset : ''))) +
            infoItem('fa-fingerprint','Serial Number', '<span style="font-family:monospace;font-size:11px;color:#6366f1;">' + escH(a.serial_number||'—') + '</span>') +
            infoItem('fa-calendar','Tanggal Beli', a.tanggal_beli ? fmtDate(a.tanggal_beli) : '—') +
            infoItem('fa-shield','Garansi s/d', a.garansi_sampai ? fmtDate(a.garansi_sampai) : '—') +
            infoItem('fa-coins','Harga Beli', hargaFmt) +
            infoItem('fa-right-left','Total Mutasi', mutasi.length + ' perpindahan') +
        '</div>' +
        '<div style="margin-bottom:4px;">' +
            '<div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Kondisi Aset</div>' +
            '<div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">' +
                '<div style="height:6px;border-radius:3px;background:' + kClr[0] + ';width:' + kondisiPct + '%;transition:width .5s ease;"></div>' +
            '</div>' +
            '<div style="display:flex;justify-content:space-between;font-size:9px;color:#94a3b8;margin-top:3px;">' +
                '<span>Tidak Aktif</span><span>Rusak</span><span>Perbaikan</span><span>Baik</span>' +
            '</div>' +
        '</div>' +
        (a.keterangan ? '<div style="margin-top:10px;padding:8px 11px;background:#f8fafc;border-left:3px solid #e2e8f0;border-radius:0 6px 6px 0;font-size:11.5px;color:#64748b;">' + escH(a.keterangan) + '</div>' : '');

    // ── Timeline ──
    renderTimeline(a, mutasi, tiket);

    // Tombol cetak
    document.getElementById('btn-print-aset').href =
        APP_URL + '/pages/cetak_lacak_aset.php?id=' + a.id;
}

function infoItem(ico, lbl, val) {
    return '<div class="info-item">' +
        '<div class="info-lbl"><i class="fa ' + ico + '" style="font-size:9px;"></i> ' + lbl + '</div>' +
        '<div class="info-val">' + val + '</div>' +
    '</div>';
}

/* ═══════════════════════════════════════════════════════════
   RENDER TIMELINE
═══════════════════════════════════════════════════════════ */
function renderTimeline(aset, mutasi, tiket) {
    var tw   = document.getElementById('timeline-wrap');
    var tlCnt= document.getElementById('tl-count');
    var items = [];

    // Item 1: Registrasi awal
    items.push({
        type:  'awal',
        date:  aset.created_at,
        title: 'Aset Terdaftar',
        sub:   'Aset pertama kali dimasukkan ke sistem inventaris.',
        extra: null,
    });

    // Item dari mutasi
    mutasi.forEach(function(m) {
        var jmap = {'keduanya':'↔ Pindah Lokasi + PIC','pindah_lokasi':'📍 Pindah Lokasi','pindah_pic':'👤 Pindah PIC'};
        items.push({
            type:   m.status_mutasi === 'batal' ? 'batal' : 'mutasi',
            date:   m.created_at,
            title:  (jmap[m.jenis] || m.jenis) + (m.status_mutasi === 'batal' ? ' <span style="color:#ef4444;">[DIBATALKAN]</span>' : ''),
            sub:    'No. ' + escH(m.no_mutasi) + ' · oleh ' + escH(m.dibuat_nama||'—'),
            dari:   (m.dari_bagian_nama||'—') + (m.dari_pic_nama ? ' · ' + m.dari_pic_nama : ''),
            ke:     (m.ke_bagian_nama||(m.jenis==='pindah_pic'?'(sama)':'—')) + (m.ke_pic_nama ? ' · ' + m.ke_pic_nama : (m.jenis==='pindah_lokasi'?' · (PIC sama)':'')),
            kondisi_sesudah: m.kondisi_sesudah,
            batal:  m.status_mutasi === 'batal',
            no_mutasi: m.no_mutasi,
        });
    });

    // Item posisi kini (jika ada mutasi)
    if (mutasi.length > 0) {
        items.push({
            type:  'kini',
            date:  null,
            title: '📍 Posisi Saat Ini',
            sub:   null,
            lokasi: (aset.bagian_nama||'Tanpa Lokasi'),
            pj:    (aset.pj_nama_db || aset.penanggung_jawab || 'Tanpa PIC'),
            kondisi: aset.kondisi,
            status_pakai: aset.status_pakai,
        });
    }

    tlCnt.textContent = items.length + ' entri';

    if (!items.length) {
        tw.innerHTML = '<div class="tl-nodata"><i class="fa fa-inbox"></i> Belum ada riwayat</div>';
        return;
    }

    var html = '<div class="timeline-wrap">';
    items.forEach(function(item, idx) {
        var dotClass = {
            'awal':   'tl-dot-awal',
            'mutasi': 'tl-dot-mutasi',
            'kini':   'tl-dot-kini',
            'batal':  'tl-dot-mutasi',
        }[item.type] || 'tl-dot-mutasi';

        var dotIcon = {
            'awal':   'fa-plus',
            'mutasi': 'fa-right-left',
            'kini':   'fa-location-dot',
            'batal':  'fa-ban',
        }[item.type] || 'fa-circle';

        var cardClass = 'tl-card';
        if (item.type === 'kini')   cardClass += ' tl-kini';
        if (item.type === 'awal')   cardClass += ' tl-awal';
        if (item.type === 'batal' || item.batal) cardClass += ' tl-batal';

        html += '<div class="tl-item" style="animation-delay:' + (idx * 0.05) + 's;">' +
            '<div class="tl-dot ' + dotClass + '"><i class="fa ' + dotIcon + '" style="font-size:6px;"></i></div>' +
            '<div class="' + cardClass + '">';

        // Date
        if (item.date) {
            html += '<div class="tl-date"><i class="fa fa-calendar" style="font-size:9px;"></i> ' + fmtDatetime(item.date) + '</div>';
        }

        html += '<div class="tl-title">' + item.title + '</div>';

        if (item.sub) {
            html += '<div class="tl-sub">' + item.sub + '</div>';
        }

        // Dari → Ke (mutasi)
        if (item.type === 'mutasi' && !item.batal) {
            html += '<div class="tl-pihak">' +
                '<div class="tl-pihak-dari"><i class="fa fa-arrow-up-from-bracket" style="font-size:9px;"></i> ' + escH(item.dari) + '</div>' +
                '<div class="tl-pihak-arr">→</div>' +
                '<div class="tl-pihak-ke"><i class="fa fa-arrow-down-to-bracket" style="font-size:9px;"></i> ' + escH(item.ke) + '</div>' +
            '</div>';
            if (item.kondisi_sesudah) {
                var kClr2 = {'Baik':'#16a34a','Rusak':'#dc2626','Dalam Perbaikan':'#d97706','Tidak Aktif':'#64748b'}[item.kondisi_sesudah] || '#64748b';
                html += '<div style="margin-top:5px;"><span style="font-size:10px;font-weight:700;color:' + kClr2 + ';">' +
                    '<i class="fa fa-circle" style="font-size:7px;"></i> Kondisi sesudah: ' + escH(item.kondisi_sesudah) + '</span></div>';
            }
        }

        // Posisi kini
        if (item.type === 'kini') {
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">' +
                '<div style="padding:8px 10px;background:rgba(255,255,255,.7);border-radius:6px;border:1px solid #fde68a;">' +
                    '<div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:3px;">Lokasi</div>' +
                    '<div style="font-size:12px;font-weight:700;color:#1e293b;">' + escH(item.lokasi) + '</div>' +
                '</div>' +
                '<div style="padding:8px 10px;background:rgba(255,255,255,.7);border-radius:6px;border:1px solid #fde68a;">' +
                    '<div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:3px;">PIC</div>' +
                    '<div style="font-size:12px;font-weight:700;color:#1e293b;">' + escH(item.pj) + '</div>' +
                '</div>' +
            '</div>';
        }

        html += '</div></div>';
    });

    html += '</div>';
    tw.innerHTML = html;
}

/* ═══════════════════════════════════════════════════════════
   HELPERS
═══════════════════════════════════════════════════════════ */
function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});
}
function fmtDatetime(d) {
    if (!d) return '—';
    var dt = new Date(d);
    return dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) +
           ' ' + dt.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
}
function hexToRgb(hex) {
    var r = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return r ? parseInt(r[1],16)+','+parseInt(r[2],16)+','+parseInt(r[3],16) : '0,0,0';
}
</script>

<?php include '../includes/footer.php'; ?>