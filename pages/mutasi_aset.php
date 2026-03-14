<?php
// pages/mutasi_aset.php — Mutasi Aset IT
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger', 'Akses ditolak.'); redirect(APP_URL . '/dashboard.php'); }

$page_title  = 'Mutasi Aset IT';
$active_menu = 'mutasi_aset';

// ── Buat tabel mutasi jika belum ada ──────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mutasi_aset (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            no_mutasi        VARCHAR(30)  NOT NULL UNIQUE,
            aset_id          INT UNSIGNED NOT NULL,
            tanggal_mutasi   DATE         NOT NULL,
            jenis            ENUM('pindah_lokasi','pindah_pic','keduanya') NOT NULL DEFAULT 'keduanya',
            dari_bagian_id   INT UNSIGNED DEFAULT NULL,
            dari_bagian_nama VARCHAR(100) DEFAULT NULL,
            dari_pic_id      INT UNSIGNED DEFAULT NULL,
            dari_pic_nama    VARCHAR(100) DEFAULT NULL,
            ke_bagian_id     INT UNSIGNED DEFAULT NULL,
            ke_bagian_nama   VARCHAR(100) DEFAULT NULL,
            ke_pic_id        INT UNSIGNED DEFAULT NULL,
            ke_pic_nama      VARCHAR(100) DEFAULT NULL,
            kondisi_sebelum  VARCHAR(50)  DEFAULT NULL,
            kondisi_sesudah  VARCHAR(50)  DEFAULT NULL,
            status_pakai     VARCHAR(30)  DEFAULT 'Terpakai',
            keterangan       TEXT         DEFAULT NULL,
            dibuat_oleh      INT UNSIGNED DEFAULT NULL,
            dibuat_nama      VARCHAR(100) DEFAULT NULL,
            status_mutasi    ENUM('draft','selesai','batal') NOT NULL DEFAULT 'selesai',
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
} catch (Exception $e) {}

// ── Helper: generate no mutasi ────────────────────────────────────────────────
function generateNoMutasi(PDO $pdo): string {
    $prefix = 'MUT-' . date('Ym') . '-';
    $last   = $pdo->query("SELECT no_mutasi FROM mutasi_aset WHERE no_mutasi LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $seq    = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) $seq = (int)$m[1] + 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// ── AJAX: get aset data ────────────────────────────────────────────────────────
if (isset($_GET['get_aset'])) {
    $s = $pdo->prepare("
        SELECT a.*, b.nama AS bagian_nama, b.kode AS bagian_kode,
               u.nama AS pj_nama_db, u.divisi AS pj_divisi
        FROM aset_it a
        LEFT JOIN bagian b ON b.id = a.bagian_id
        LEFT JOIN users  u ON u.id = a.pj_user_id
        WHERE a.id = ?
    ");
    $s->execute([(int)$_GET['get_aset']]);
    header('Content-Type: application/json');
    echo json_encode($s->fetch(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: search aset ─────────────────────────────────────────────────────────
if (isset($_GET['search_aset'])) {
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $s = $pdo->prepare("
        SELECT a.id, a.no_inventaris, a.nama_aset, a.kategori, a.merek, a.kondisi, a.status_pakai,
               b.nama AS bagian_nama, u.nama AS pj_nama_db
        FROM aset_it a
        LEFT JOIN bagian b ON b.id = a.bagian_id
        LEFT JOIN users  u ON u.id = a.pj_user_id
        WHERE a.no_inventaris LIKE ? OR a.nama_aset LIKE ? OR a.merek LIKE ?
        ORDER BY a.nama_aset LIMIT 20
    ");
    $s->execute([$q, $q, $q]);
    header('Content-Type: application/json');
    echo json_encode($s->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: preview no mutasi ────────────────────────────────────────────────────
if (isset($_GET['preview_no_mut'])) {
    header('Content-Type: application/json');
    echo json_encode(['no' => generateNoMutasi($pdo)]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// POST: simpan mutasi
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'simpan') {

    $aset_id         = (int)($_POST['aset_id']        ?? 0);
    $tgl             = $_POST['tanggal_mutasi']        ?? date('Y-m-d');
    $jenis           = $_POST['jenis']                 ?? 'keduanya';
    $kondisi_sesudah = trim($_POST['kondisi_sesudah']  ?? '');
    $status_pakai    = trim($_POST['status_pakai']     ?? 'Terpakai');
    $keterangan      = trim($_POST['keterangan']       ?? '');

    // ── PERBAIKAN BUG #1: Hanya ambil ke_bagian_id / ke_pic_id sesuai jenis ──
    $ke_bagian_id = null;
    $ke_pic_id    = null;

    if (in_array($jenis, ['pindah_lokasi', 'keduanya'])) {
        $raw = (int)($_POST['ke_bagian_id'] ?? 0);
        $ke_bagian_id = $raw > 0 ? $raw : null;
    }
    if (in_array($jenis, ['pindah_pic', 'keduanya'])) {
        $raw = (int)($_POST['ke_pic_id'] ?? 0);
        $ke_pic_id = $raw > 0 ? $raw : null;
    }

    // ── Validasi wajib ────────────────────────────────────────────────────────
    if (!$aset_id) {
        setFlash('danger', 'Pilih aset terlebih dahulu.');
        redirect(APP_URL . '/pages/mutasi_aset.php');
    }
    if (in_array($jenis, ['pindah_lokasi', 'keduanya']) && !$ke_bagian_id) {
        setFlash('danger', 'Lokasi / Bagian tujuan wajib dipilih untuk jenis mutasi ini.');
        redirect(APP_URL . '/pages/mutasi_aset.php');
    }
    if (in_array($jenis, ['pindah_pic', 'keduanya']) && !$ke_pic_id) {
        setFlash('danger', 'Penanggung Jawab baru wajib dipilih untuk jenis mutasi ini.');
        redirect(APP_URL . '/pages/mutasi_aset.php');
    }

    // ── Ambil data aset saat ini (posisi SEBELUM mutasi) ─────────────────────
    $st = $pdo->prepare("
        SELECT a.*, b.nama AS bagian_nama, u.nama AS pj_nama_db
        FROM aset_it a
        LEFT JOIN bagian b ON b.id = a.bagian_id
        LEFT JOIN users  u ON u.id = a.pj_user_id
        WHERE a.id = ?
    ");
    $st->execute([$aset_id]);
    $aset = $st->fetch();

    if (!$aset) {
        setFlash('danger', 'Aset tidak ditemukan.');
        redirect(APP_URL . '/pages/mutasi_aset.php');
    }

    // ── Resolve nama tujuan ───────────────────────────────────────────────────
    $ke_bagian_nama = '';
    if ($ke_bagian_id) {
        $s = $pdo->prepare("SELECT nama FROM bagian WHERE id=?");
        $s->execute([$ke_bagian_id]);
        $ke_bagian_nama = $s->fetchColumn() ?: '';
    }
    $ke_pic_nama = '';
    if ($ke_pic_id) {
        $s = $pdo->prepare("SELECT nama FROM users WHERE id=?");
        $s->execute([$ke_pic_id]);
        $ke_pic_nama = $s->fetchColumn() ?: '';
    }

    // ── PERBAIKAN BUG #3: kondisi_sesudah selalu tersimpan dengan nilai aktual ─
    $kondisi_final = $kondisi_sesudah ?: $aset['kondisi'];
    // Simpan nilai aktual (bukan string kosong) ke kolom kondisi_sesudah
    $kondisi_sesudah_simpan = $kondisi_final;

    $no_mutasi   = generateNoMutasi($pdo);
    $dibuat_nama = $_SESSION['nama'] ?? '';
    if (!$dibuat_nama) {
        $u = $pdo->prepare("SELECT nama FROM users WHERE id=?");
        $u->execute([$_SESSION['user_id']]);
        $dibuat_nama = $u->fetchColumn() ?: '';
    }

    // ── Simpan record mutasi ──────────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO mutasi_aset
        (no_mutasi, aset_id, tanggal_mutasi, jenis,
         dari_bagian_id, dari_bagian_nama, dari_pic_id, dari_pic_nama,
         ke_bagian_id, ke_bagian_nama, ke_pic_id, ke_pic_nama,
         kondisi_sebelum, kondisi_sesudah, status_pakai, keterangan,
         dibuat_oleh, dibuat_nama, status_mutasi)
        VALUES (?,?,?,?,  ?,?,?,?,  ?,?,?,?,  ?,?,?,?,  ?,?,'selesai')
    ")->execute([
        $no_mutasi, $aset_id, $tgl, $jenis,
        $aset['bagian_id'], $aset['bagian_nama'] ?? '',
        $aset['pj_user_id'], $aset['pj_nama_db'] ?? '',
        $ke_bagian_id, $ke_bagian_nama,
        $ke_pic_id,    $ke_pic_nama,
        $aset['kondisi'], $kondisi_sesudah_simpan,
        $status_pakai, $keterangan,
        $_SESSION['user_id'], $dibuat_nama,
    ]);

    // ── PERBAIKAN BUG #1: Update aset_it hanya field yang sesuai jenis ────────
    $update_fields = ['kondisi=?', 'status_pakai=?', 'updated_at=NOW()'];
    $update_params = [$kondisi_final, $status_pakai];

    // Hanya update lokasi jika jenis mencakup pindah_lokasi
    if ($ke_bagian_id && in_array($jenis, ['pindah_lokasi', 'keduanya'])) {
        $update_fields[] = 'bagian_id=?';
        $update_fields[] = 'lokasi=?';
        $update_params[] = $ke_bagian_id;
        $update_params[] = $ke_bagian_nama;
    }

    // Hanya update PIC jika jenis mencakup pindah_pic
    if ($ke_pic_id && in_array($jenis, ['pindah_pic', 'keduanya'])) {
        $update_fields[] = 'pj_user_id=?';
        $update_fields[] = 'penanggung_jawab=?';
        $update_params[] = $ke_pic_id;
        $update_params[] = $ke_pic_nama;
    }

    $update_params[] = $aset_id;
    $pdo->prepare("UPDATE aset_it SET " . implode(', ', $update_fields) . " WHERE id=?")
        ->execute($update_params);

    setFlash('success', "Mutasi <strong>{$no_mutasi}</strong> berhasil disimpan. Aset <strong>" . htmlspecialchars($aset['nama_aset']) . "</strong> telah diperbarui.");
    redirect(APP_URL . '/pages/mutasi_aset.php');
}

// ── POST: batal mutasi ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'batal') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id && hasRole('admin')) {
        $pdo->prepare("UPDATE mutasi_aset SET status_mutasi='batal' WHERE id=?")->execute([$id]);
        setFlash('warning', 'Mutasi dibatalkan.');
    }
    redirect(APP_URL . '/pages/mutasi_aset.php');
}

// ── Filter & Pagination ────────────────────────────────────────────────────────
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$search   = trim($_GET['q'] ?? '');
$fstatus  = $_GET['status'] ?? '';

$where = ['1=1']; $params = [];
if ($search) {
    $where[]  = '(m.no_mutasi LIKE ? OR a.nama_aset LIKE ? OR a.no_inventaris LIKE ? OR m.ke_bagian_nama LIKE ? OR m.ke_pic_nama LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%", "%$search%");
}
if ($fstatus) { $where[] = 'm.status_mutasi=?'; $params[] = $fstatus; }
$wsql = implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM mutasi_aset m LEFT JOIN aset_it a ON a.id=m.aset_id WHERE $wsql");
$cnt->execute($params); $total = (int)$cnt->fetchColumn();
$pages  = max(1, ceil($total / $per_page));
$page   = min($page, $pages);
$offset = ($page - 1) * $per_page;

$st = $pdo->prepare("
    SELECT m.*, a.nama_aset, a.no_inventaris, a.kategori, a.merek
    FROM mutasi_aset m
    LEFT JOIN aset_it a ON a.id = m.aset_id
    WHERE $wsql
    ORDER BY m.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$st->execute($params);
$list = $st->fetchAll();

// Stats
$stats = [];
foreach ($pdo->query("SELECT status_mutasi, COUNT(*) n FROM mutasi_aset GROUP BY status_mutasi")->fetchAll() as $r)
    $stats[$r['status_mutasi']] = (int)$r['n'];
$total_all = array_sum($stats);

// Dropdown
$bagian_list = $pdo->query("SELECT id,nama,kode,lokasi FROM bagian WHERE status='aktif' ORDER BY urutan,nama")->fetchAll();
$users_list  = $pdo->query("SELECT id,nama,divisi,role FROM users WHERE status='aktif' ORDER BY nama")->fetchAll();

include '../includes/header.php';
?>

<style>
/* ════════════════════════════════════════════════
   MODAL BULLETPROOF — sama teknik seperti kategori
════════════════════════════════════════════════ */
#m-mutasi, #m-detail {
    visibility: hidden !important;
    opacity: 0 !important;
    position: fixed !important;
    top: 0 !important; left: 0 !important;
    width: 100% !important; height: 100% !important;
    background: rgba(0,0,0,.55) !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: flex-start !important;
    justify-content: center !important;
    padding-top: 30px !important;
    box-sizing: border-box !important;
    transition: opacity .22s ease, visibility .22s ease !important;
    pointer-events: none !important;
}
#m-mutasi.mm-open, #m-detail.mm-open {
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}
#m-detail {
    align-items: center !important;
    padding-top: 0 !important;
}
.mm-box {
    background: #fff !important;
    border-radius: 12px !important;
    width: 100% !important;
    box-shadow: 0 24px 64px rgba(0,0,0,.25) !important;
    overflow: hidden !important;
    transform: translateY(20px) scale(.98);
    transition: transform .22s ease !important;
    display: flex !important;
    flex-direction: column !important;
    min-height: 0 !important;
}
#m-mutasi.mm-open .mm-box,
#m-detail.mm-open .mm-box {
    transform: translateY(0) scale(1) !important;
}

/* ── Styles khusus mutasi ── */
.mut-no { font-family:'Courier New',monospace; font-size:11px; font-weight:700;
          background:linear-gradient(135deg,#fdf4ff,#ede9fe); color:#5b21b6;
          border:1px solid #ddd6fe; padding:2px 8px; border-radius:5px; white-space:nowrap; }

.mut-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:9px;font-size:11px;font-weight:700; }
.mb-selesai { background:#d1fae5; color:#065f46; }
.mb-draft   { background:#fef3c7; color:#92400e; }
.mb-batal   { background:#fee2e2; color:#991b1b; }

.kondisi-badge { display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700; }
.kb-baik      { background:#dcfce7; color:#166534; }
.kb-rusak     { background:#fee2e2; color:#991b1b; }
.kb-perbaikan { background:#fef9c3; color:#854d0e; }
.kb-tidak     { background:#f1f5f9; color:#64748b; }

/* ── Pihak box di detail ── */
.pihak-box { display:flex; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.pihak-pemberi  { flex:1; padding:11px 14px; background:linear-gradient(135deg,#fff7ed,#fef3c7); border-right:1px solid #e2e8f0; }
.pihak-penerima { flex:1; padding:11px 14px; background:linear-gradient(135deg,#f0fdf4,#d1fae5); }
.pihak-label { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.8px; margin-bottom:5px; }
.pihak-pemberi  .pihak-label { color:#92400e; }
.pihak-penerima .pihak-label { color:#065f46; }
.pihak-icon { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
.pihak-pemberi  .pihak-icon { background:#fed7aa; color:#c2410c; }
.pihak-penerima .pihak-icon { background:#a7f3d0; color:#047857; }
.mut-mid-arrow { display:flex; align-items:center; justify-content:center; width:36px; background:#f8fafc; flex-shrink:0; border-left:1px dashed #e2e8f0; border-right:1px dashed #e2e8f0; }

/* ── Aset search ── */
.aset-search-wrap { position:relative; }
.aset-suggestions {
    display:none;
    position:absolute;
    top:100%; left:0; right:0;
    background:#fff; border:1px solid #d1d5db;
    border-radius:8px; max-height:260px; overflow-y:auto;
    z-index:999999; box-shadow:0 8px 28px rgba(0,0,0,.18);
    margin-top:3px;
}
.aset-suggestions.show { display:block; }
.aset-sug-item {
    padding:9px 13px; cursor:pointer; border-bottom:1px solid #f8fafc;
    transition:background .12s; display:flex; align-items:center; gap:10px;
}
.aset-sug-item:hover { background:#f0fdf9; }
.aset-sug-item:last-child { border-bottom:none; }
.aset-sug-inv { font-family:monospace; font-size:10.5px; font-weight:700; background:#eff6ff; color:#1e40af; padding:1px 6px; border-radius:3px; flex-shrink:0; }

/* ── Aset selected preview ── */
.aset-selected-card {
    display:none; background:linear-gradient(135deg,#f0fdf9,#e6fdf6);
    border:1.5px solid #6ee7b7; border-radius:10px; padding:12px 14px;
    margin-top:8px; position:relative;
}
.aset-selected-card.show { display:block; }

/* ── Jenis mutasi info badge ── */
.jenis-info {
    display:none; margin-top:6px;
    padding:7px 11px; border-radius:6px; font-size:11.5px; font-weight:600;
    border-left:3px solid; align-items:center; gap:7px;
}
.jenis-info.show { display:flex; }

/* ── Form inputs ── */
.f-label { font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px; }
.f-inp { width:100%; padding:8px 11px; border:1px solid #d1d5db; border-radius:6px; font-size:12.5px; box-sizing:border-box; font-family:inherit; transition:border .18s; }
.f-inp:focus { outline:none; border-color:#00c896; box-shadow:0 0 0 3px rgba(0,200,150,.1); }
.section-title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin:16px 0 10px; display:flex; align-items:center; gap:7px; }
.section-title::after { content:''; flex:1; height:1px; background:#f1f5f9; }

/* ── Highlight aset berubah di tabel ── */
.aset-updated-badge {
    display:inline-flex; align-items:center; gap:4px;
    font-size:10px; font-weight:700; padding:1px 6px; border-radius:4px;
    background:#dbeafe; color:#1e40af; margin-left:4px;
}
</style>

<div class="page-header">
    <h4><i class="fa fa-right-left text-primary"></i> &nbsp;Mutasi Aset IT</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <span class="sep">/</span>
        <a href="<?= APP_URL ?>/pages/aset_it.php">Aset IT</a>
        <span class="sep">/</span>
        <span class="cur">Mutasi Aset</span>
    </div>
</div>

<div class="content">
    <?= showFlash() ?>

    <!-- Stats -->
    <div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;">
        <?php foreach ([
            [$total_all,           'Total Mutasi', 'fa-right-left',  '#f5f3ff','#7c3aed'],
            [$stats['selesai']??0, 'Selesai',      'fa-circle-check','#d1fae5','#059669'],
            [$stats['draft']??0,   'Draft',         'fa-clock',       '#fef9c3','#d97706'],
            [$stats['batal']??0,   'Dibatalkan',    'fa-ban',         '#fee2e2','#dc2626'],
        ] as [$val,$lbl,$ico,$bg,$clr]): ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;min-width:130px;">
            <div style="width:36px;height:36px;border-radius:8px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa <?= $ico ?>" style="color:<?= $clr ?>;font-size:14px;"></i>
            </div>
            <div>
                <div style="font-size:22px;font-weight:800;color:#111827;line-height:1;"><?= $val ?></div>
                <div style="font-size:11px;color:#9ca3af;margin-top:1px;"><?= $lbl ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:16px;align-items:start;">

        <!-- ══ TABEL RIWAYAT ══ -->
        <div class="panel">
            <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;">
                <h5><i class="fa fa-list text-primary"></i> Riwayat Mutasi <span style="color:#aaa;font-weight:400;">(<?= $total ?>)</span></h5>
                <div style="display:flex;gap:7px;align-items:center;">
                    <!-- Tombol Cetak Laporan -->
                    <div style="position:relative;" id="wrap-cetak-mut">
                        <button type="button" onclick="toggleCetakMut(event)"
                            class="btn btn-default btn-sm"
                            style="border-color:#7c3aed;color:#5b21b6;font-weight:600;">
                            <i class="fa fa-print"></i> Cetak
                            <i class="fa fa-chevron-down" style="font-size:9px;margin-left:3px;"></i>
                        </button>
                        <div id="cetak-mut-drop" style="display:none;position:absolute;right:0;top:36px;z-index:9999;
                            background:#fff;border:1px solid #e2e8f0;border-radius:10px;
                            box-shadow:0 8px 28px rgba(0,0,0,.13);min-width:300px;overflow:hidden;">
                            <div style="padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                                <div style="font-size:11px;font-weight:700;color:#374151;">
                                    <i class="fa fa-file-pdf" style="color:#7c3aed;margin-right:5px;"></i>
                                    Cetak Laporan Mutasi PDF
                                </div>
                            </div>
                            <div style="padding:12px 14px;">
                                <!-- Filter Periode -->
                                <div style="margin-bottom:10px;">
                                    <label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px;">
                                        <i class="fa fa-calendar"></i> Periode
                                    </label>
                                    <div style="display:flex;gap:6px;align-items:center;">
                                        <input type="date" id="cp-dari" value="<?= date('Y-m-01') ?>"
                                            style="flex:1;padding:5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:11px;font-family:inherit;">
                                        <span style="font-size:10px;color:#94a3b8;">s/d</span>
                                        <input type="date" id="cp-sampai" value="<?= date('Y-m-d') ?>"
                                            style="flex:1;padding:5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:11px;font-family:inherit;">
                                    </div>
                                </div>
                                <!-- Filter Status -->
                                <div style="margin-bottom:10px;">
                                    <label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px;">
                                        <i class="fa fa-filter"></i> Status Mutasi
                                    </label>
                                    <select id="cp-status" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:11px;font-family:inherit;">
                                        <option value="">Semua Status</option>
                                        <option value="selesai">✅ Selesai</option>
                                        <option value="draft">📝 Draft</option>
                                        <option value="batal">❌ Dibatalkan</option>
                                    </select>
                                </div>
                                <!-- Filter Jenis -->
                                <div style="margin-bottom:12px;">
                                    <label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px;">
                                        <i class="fa fa-right-left"></i> Jenis Mutasi
                                    </label>
                                    <select id="cp-jenis" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:11px;font-family:inherit;">
                                        <option value="">Semua Jenis</option>
                                        <option value="keduanya">↔ Pindah Lokasi + PIC</option>
                                        <option value="pindah_lokasi">📍 Pindah Lokasi Saja</option>
                                        <option value="pindah_pic">👤 Pindah PIC Saja</option>
                                    </select>
                                </div>
                                <!-- Tombol aksi -->
                                <div style="display:flex;gap:6px;">
                                    <button type="button" onclick="bukaCetakMut()"
                                        style="flex:1;padding:7px;background:linear-gradient(135deg,#7c3aed,#5b21b6);border:none;
                                               border-radius:6px;color:#fff;font-size:12px;font-weight:700;cursor:pointer;
                                               font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;">
                                        <i class="fa fa-file-pdf"></i> Cetak PDF
                                    </button>
                                    <button type="button" onclick="document.getElementById('cetak-mut-drop').style.display='none';"
                                        style="padding:7px 12px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;
                                               font-size:11px;cursor:pointer;color:#64748b;font-family:inherit;">
                                        Batal
                                    </button>
                                </div>
                            </div>
                            <div style="padding:6px 14px;background:#f8fafc;border-top:1px solid #e2e8f0;">
                                <div style="font-size:10px;color:#94a3b8;">
                                    <i class="fa fa-circle-info" style="color:#7c3aed;"></i>
                                    PDF buka di tab baru — A4 Landscape
                                </div>
                            </div>
                        </div>
                    </div>
                    <button onclick="mmOpen()" class="btn btn-primary btn-sm">
                        <i class="fa fa-plus"></i> Buat Mutasi
                    </button>
                </div>
            </div>

            <!-- Filter toolbar -->
            <div style="padding:10px 14px;border-bottom:1px solid #f0f0f0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <form method="GET" id="sf-mut" style="display:flex;gap:7px;align-items:center;flex-wrap:wrap;">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="inp-search"
                           placeholder="Cari no. mutasi, aset, PIC…"
                           onchange="document.getElementById('sf-mut').submit()">
                    <select name="status" class="sel-filter" onchange="document.getElementById('sf-mut').submit()">
                        <option value="">Semua Status</option>
                        <option value="selesai" <?= $fstatus==='selesai'?'selected':'' ?>>✅ Selesai</option>
                        <option value="draft"   <?= $fstatus==='draft'  ?'selected':'' ?>>📝 Draft</option>
                        <option value="batal"   <?= $fstatus==='batal'  ?'selected':'' ?>>❌ Dibatalkan</option>
                    </select>
                    <?php if ($search || $fstatus): ?>
                    <a href="?" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No. Mutasi</th>
                            <th>Aset</th>
                            <th>Tanggal</th>
                            <th>Pemberi → Penerima</th>
                            <th>Kondisi</th>
                            <th>Status</th>
                            <th style="width:70px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list)): ?>
                        <tr><td colspan="7" class="td-empty"><i class="fa fa-right-left"></i> Belum ada riwayat mutasi</td></tr>
                        <?php else: foreach ($list as $m):
                            $kbmap = ['Baik'=>'kb-baik','Rusak'=>'kb-rusak','Dalam Perbaikan'=>'kb-perbaikan','Tidak Aktif'=>'kb-tidak'];
                            $kc_ses = $kbmap[$m['kondisi_sesudah']] ?? 'kb-tidak';
                        ?>
                        <tr>
                            <td>
                                <div class="mut-no"><?= htmlspecialchars($m['no_mutasi']) ?></div>
                                <div style="font-size:10px;color:#94a3b8;margin-top:2px;"><?= date('d M Y H:i', strtotime($m['created_at'])) ?></div>
                            </td>
                            <td>
                                <div style="font-size:12.5px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($m['nama_aset'] ?? '—') ?></div>
                                <div style="font-family:monospace;font-size:10px;color:#94a3b8;"><?= htmlspecialchars($m['no_inventaris'] ?? '') ?></div>
                                <?php if ($m['kategori']): ?>
                                <span style="font-size:10px;background:#f1f5f9;color:#64748b;padding:1px 6px;border-radius:3px;"><?= htmlspecialchars($m['kategori']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:11.5px;color:#374151;white-space:nowrap;">
                                <?= date('d M Y', strtotime($m['tanggal_mutasi'])) ?>
                                <div style="font-size:10px;color:#94a3b8;margin-top:2px;">
                                    <?= ['pindah_lokasi'=>'📍 Lokasi','pindah_pic'=>'👤 PIC','keduanya'=>'↔ Keduanya'][$m['jenis']] ?? $m['jenis'] ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:11px;color:#92400e;margin-bottom:3px;">
                                    <i class="fa fa-arrow-up-from-bracket" style="font-size:9px;"></i>
                                    <strong>Dari:</strong>
                                    <?= htmlspecialchars($m['dari_bagian_nama'] ?: '—') ?>
                                    <?php if ($m['dari_pic_nama']): ?>
                                    <span style="color:#94a3b8;"> · <?= htmlspecialchars($m['dari_pic_nama']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;align-items:center;gap:4px;margin:3px 0;">
                                    <div style="flex:1;height:1px;background:linear-gradient(90deg,#fde68a,#00c896);"></div>
                                    <i class="fa fa-arrow-down" style="color:#00c896;font-size:9px;"></i>
                                    <div style="flex:1;height:1px;background:linear-gradient(90deg,#00c896,#a7f3d0);"></div>
                                </div>
                                <div style="font-size:11px;color:#065f46;">
                                    <i class="fa fa-arrow-down-to-bracket" style="font-size:9px;"></i>
                                    <strong>Ke:</strong>
                                    <?= htmlspecialchars($m['ke_bagian_nama'] ?: ($m['jenis']==='pindah_pic' ? '(sama)' : '—')) ?>
                                    <?php if ($m['ke_pic_nama']): ?>
                                    <span style="color:#94a3b8;"> · <?= htmlspecialchars($m['ke_pic_nama']) ?></span>
                                    <?php elseif ($m['jenis']==='pindah_lokasi'): ?>
                                    <span style="color:#94a3b8;"> · (PIC sama)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($m['kondisi_sesudah']): ?>
                                <span class="kondisi-badge <?= $kc_ses ?>"><?= htmlspecialchars($m['kondisi_sesudah']) ?></span>
                                <?php endif; ?>
                                <?php if ($m['status_pakai']): ?>
                                <div style="margin-top:3px;font-size:10.5px;color:#64748b;"><?= htmlspecialchars($m['status_pakai']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="mut-badge mb-<?= $m['status_mutasi'] ?>">
                                    <?= ucfirst($m['status_mutasi']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:3px;">
                                    <button onclick="detailMutasi(<?= htmlspecialchars(json_encode($m)) ?>)"
                                        class="btn btn-default btn-sm" title="Lihat Detail">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                    <?php if ($m['status_mutasi'] === 'selesai' && hasRole('admin')): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Batalkan mutasi ini?')">
                                        <input type="hidden" name="_action" value="batal">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Batalkan">
                                            <i class="fa fa-ban"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="tbl-footer">
                <span class="tbl-info">Menampilkan <?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?></span>
                <?php if ($pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pag-btn"><i class="fa fa-chevron-left"></i></a><?php endif; ?>
                    <?php for ($i=1;$i<=$pages;$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
                    <?php if ($page < $pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pag-btn"><i class="fa fa-chevron-right"></i></a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══ PANEL SAMPING ══ -->
        <div>
            <div class="panel">
                <div class="panel-hd"><h5><i class="fa fa-circle-info text-primary"></i> Tentang Mutasi Aset</h5></div>
                <div class="panel-bd">
                    <div style="font-size:12.5px;color:#374151;line-height:1.7;">
                        Mutasi aset mencatat <strong>perpindahan aset IT</strong> dan otomatis memperbarui data aset sesuai jenis mutasi.
                    </div>

                    <!-- ── Diagram alur update ── -->
                    <div style="margin-top:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:12px 14px;">
                        <div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;">
                            <i class="fa fa-diagram-project" style="color:#7c3aed;"></i> Yang Diupdate di Aset IT
                        </div>
                        <?php foreach ([
                            ['keduanya',      '↔ Keduanya',       '#7c3aed','#f5f3ff', ['Bagian/Lokasi', 'Penanggung Jawab', 'Kondisi', 'Status Pakai']],
                            ['pindah_lokasi', '📍 Pindah Lokasi', '#1d4ed8','#eff6ff', ['Bagian/Lokasi', 'Kondisi', 'Status Pakai']],
                            ['pindah_pic',    '👤 Pindah PIC',    '#059669','#f0fdf4', ['Penanggung Jawab', 'Kondisi', 'Status Pakai']],
                        ] as [$j,$lbl,$clr,$bg,$fields]): ?>
                        <div style="margin-bottom:9px;padding:9px 11px;background:<?= $bg ?>;border-radius:7px;border-left:3px solid <?= $clr ?>;">
                            <div style="font-size:11.5px;font-weight:700;color:<?= $clr ?>;margin-bottom:5px;"><?= $lbl ?></div>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                <?php foreach ($fields as $f): ?>
                                <span style="font-size:10.5px;background:#fff;color:#374151;padding:1px 7px;border-radius:4px;border:1px solid #e2e8f0;font-weight:600;">
                                    <i class="fa fa-check" style="color:#22c55e;font-size:9px;"></i> <?= $f ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top:10px;padding:8px 11px;background:#fef9c3;border:1px solid #fde68a;border-radius:7px;font-size:11px;color:#92400e;">
                        <i class="fa fa-triangle-exclamation"></i>
                        <strong>Penting:</strong> Data di tabel <code>aset_it</code> hanya diupdate untuk field yang sesuai jenis mutasi. Field lain tidak berubah.
                    </div>
                </div>
            </div>

            <?php if (!empty($list)): ?>
            <div class="panel" style="margin-top:12px;">
                <div class="panel-hd"><h5><i class="fa fa-clock-rotate-left text-primary"></i> Mutasi Terakhir</h5></div>
                <div class="panel-bd" style="padding:0;">
                    <?php foreach (array_slice($list, 0, 5) as $m): ?>
                    <div style="padding:10px 14px;border-bottom:1px solid #f8fafc;display:flex;gap:9px;align-items:center;">
                        <div style="width:32px;height:32px;border-radius:8px;background:#f5f3ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa fa-right-left" style="color:#7c3aed;font-size:11px;"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:12px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($m['nama_aset'] ?? '—') ?></div>
                            <div style="font-size:10.5px;color:#94a3b8;">
                                <?= htmlspecialchars($m['ke_bagian_nama'] ?: ($m['ke_pic_nama'] ?: '—')) ?>
                                · <?= date('d M', strtotime($m['tanggal_mutasi'])) ?>
                            </div>
                        </div>
                        <span class="mut-badge mb-<?= $m['status_mutasi'] ?>" style="font-size:10px;"><?= ucfirst($m['status_mutasi']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /grid -->
</div><!-- /.content -->


<!-- ══════════════════════════════════════════════════════════
     MODAL BUAT MUTASI (bulletproof)
══════════════════════════════════════════════════════════════ -->
<div id="m-mutasi">
    <div class="mm-box" style="max-width:640px;max-height:88vh;height:88vh;">

        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:15px 20px;background:linear-gradient(135deg,#1a0a2e,#2a1a4e);flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;background:rgba(124,58,237,.3);border:1px solid rgba(167,139,250,.4);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa fa-right-left" style="color:#a78bfa;font-size:14px;"></i>
                </div>
                <div>
                    <div style="color:#fff;font-size:14px;font-weight:700;">Buat Mutasi Aset</div>
                    <div style="color:rgba(255,255,255,.4);font-size:10.5px;">Catat perpindahan aset IT</div>
                </div>
            </div>
            <button onclick="mmClose()"
                style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .15s;"
                onmouseover="this.style.background='#ef4444';" onmouseout="this.style.background='rgba(255,255,255,.1)';">
                <i class="fa fa-times"></i>
            </button>
        </div>

        <form method="POST" id="form-mutasi" style="display:flex;flex-direction:column;flex:1;min-height:0;">
            <input type="hidden" name="_action" value="simpan">
            <input type="hidden" name="aset_id" id="m-aset-id" value="">

            <div style="padding:18px 20px;overflow-y:auto;flex:1;min-height:0;">

                <!-- ① Pilih Aset -->
                <div class="section-title"><i class="fa fa-box" style="color:#7c3aed;"></i> Pilih Aset</div>
                <div class="aset-search-wrap">
                    <input type="text" id="aset-search-inp" class="f-inp"
                        placeholder="Ketik nama aset, no. inventaris, atau merek…"
                        autocomplete="off" oninput="cariAset(this.value)">
                    <div class="aset-suggestions" id="aset-suggestions"></div>
                </div>

                <!-- Preview aset terpilih -->
                <div class="aset-selected-card" id="aset-card">
                    <button type="button" onclick="clearAset()"
                        style="position:absolute;top:8px;right:8px;width:20px;height:20px;border-radius:50%;background:rgba(0,0,0,.08);border:none;cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;color:#64748b;">✕</button>
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                        <div style="width:40px;height:40px;border-radius:9px;background:linear-gradient(135deg,#00e5b0,#00c896);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa fa-box" style="color:#0a0f14;font-size:15px;"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:13px;font-weight:700;color:#0f172a;" id="ac-nama">—</div>
                            <div style="font-family:monospace;font-size:10.5px;color:#0f766e;margin-top:1px;" id="ac-inv">—</div>
                            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:5px;">
                                <span id="ac-kondisi" class="mut-badge"></span>
                                <span id="ac-status" style="font-size:10.5px;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:9px;font-weight:700;"></span>
                            </div>
                        </div>
                    </div>
                    <!-- Posisi saat ini -->
                    <div style="margin-top:10px;padding:8px 10px;background:rgba(255,255,255,.7);border-radius:7px;border:1px solid rgba(0,200,150,.2);">
                        <div style="font-size:10px;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                            <i class="fa fa-arrow-up-from-bracket"></i> Posisi Saat Ini (Pemberi)
                        </div>
                        <div style="font-size:12px;color:#374151;">
                            <i class="fa fa-building" style="color:#94a3b8;font-size:10px;"></i>
                            <span id="ac-lokasi">—</span>
                        </div>
                        <div style="font-size:12px;color:#374151;margin-top:2px;">
                            <i class="fa fa-user" style="color:#94a3b8;font-size:10px;"></i>
                            <span id="ac-pj">—</span>
                        </div>
                    </div>
                </div>

                <!-- ② Detail Mutasi -->
                <div class="section-title" style="margin-top:18px;"><i class="fa fa-sliders" style="color:#7c3aed;"></i> Detail Mutasi</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label class="f-label">Tanggal Mutasi <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="tanggal_mutasi" id="m-tgl" class="f-inp" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label class="f-label">Jenis Mutasi</label>
                        <select name="jenis" id="m-jenis" class="f-inp" onchange="updateJenis(this.value)">
                            <option value="keduanya">↔ Pindah Lokasi + PIC</option>
                            <option value="pindah_lokasi">📍 Pindah Lokasi Saja</option>
                            <option value="pindah_pic">👤 Pindah PIC Saja</option>
                        </select>
                        <!-- Info jenis -->
                        <div id="jenis-info-keduanya" class="jenis-info show" style="background:#f5f3ff;color:#5b21b6;border-color:#7c3aed;">
                            <i class="fa fa-circle-info"></i> Bagian & PJ aset akan diperbarui
                        </div>
                        <div id="jenis-info-pindah_lokasi" class="jenis-info" style="background:#eff6ff;color:#1e40af;border-color:#3b82f6;">
                            <i class="fa fa-circle-info"></i> Hanya Bagian/Lokasi yang diperbarui, PJ tetap
                        </div>
                        <div id="jenis-info-pindah_pic" class="jenis-info" style="background:#f0fdf4;color:#065f46;border-color:#22c55e;">
                            <i class="fa fa-circle-info"></i> Hanya PJ yang diperbarui, Lokasi tetap
                        </div>
                    </div>
                </div>

                <!-- ③ Penerima -->
                <div style="background:linear-gradient(135deg,#f0fdf4,#d1fae5);border:1.5px solid #6ee7b7;border-radius:10px;padding:13px 14px;margin-bottom:12px;">
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#065f46;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <i class="fa fa-arrow-down-to-bracket"></i> Penerima (Tujuan)
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div id="wrap-bagian">
                            <label class="f-label">
                                Lokasi / Bagian Tujuan
                                <span id="req-bagian" style="color:#ef4444;">*</span>
                            </label>
                            <select name="ke_bagian_id" id="m-ke-bagian" class="f-inp">
                                <option value="">— Pilih Lokasi —</option>
                                <?php foreach ($bagian_list as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= ($b['kode']?'['.$b['kode'].'] ':'').htmlspecialchars($b['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="wrap-pic">
                            <label class="f-label">
                                Penanggung Jawab Baru
                                <span id="req-pic" style="color:#ef4444;">*</span>
                            </label>
                            <select name="ke_pic_id" id="m-ke-pic" class="f-inp">
                                <option value="">— Pilih PIC —</option>
                                <?php foreach ($users_list as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama']) ?><?= $u['divisi'] ? ' — '.$u['divisi'] : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ④ Kondisi & Status -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label class="f-label">Kondisi Sesudah Mutasi</label>
                        <select name="kondisi_sesudah" id="m-kondisi" class="f-inp">
                            <option value="">— Sama seperti sebelumnya —</option>
                            <option value="Baik">✅ Baik</option>
                            <option value="Dalam Perbaikan">🔧 Dalam Perbaikan</option>
                            <option value="Rusak">❌ Rusak</option>
                            <option value="Tidak Aktif">⛔ Tidak Aktif</option>
                        </select>
                    </div>
                    <div>
                        <label class="f-label">Status Pakai Setelah</label>
                        <select name="status_pakai" id="m-status-pakai" class="f-inp">
                            <option value="Terpakai">🔵 Terpakai</option>
                            <option value="Tidak Terpakai">🟢 Tidak Terpakai</option>
                            <option value="Dipinjam">🟡 Dipinjam</option>
                        </select>
                    </div>
                </div>

                <!-- ⑤ Keterangan -->
                <div>
                    <label class="f-label">Keterangan / Alasan Mutasi</label>
                    <textarea name="keterangan" id="m-keterangan" class="f-inp" rows="2"
                        placeholder="Contoh: Rotasi perangkat antar ruangan, mutasi karyawan, dll…"
                        style="resize:vertical;"></textarea>
                </div>

            </div><!-- /scroll body -->

            <!-- Footer -->
            <div style="padding:12px 20px;border-top:1px solid #f0f0f0;background:#f8fafc;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
                <span style="font-size:11px;color:#94a3b8;">
                    <i class="fa fa-circle-info" style="color:#7c3aed;"></i>
                    Data aset_it diperbarui otomatis sesuai jenis mutasi
                </span>
                <div style="display:flex;gap:8px;">
                    <button type="button" onclick="mmClose()" class="btn btn-default">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btn-simpan-mut" disabled>
                        <i class="fa fa-save"></i> Simpan Mutasi
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL DETAIL MUTASI (bulletproof)
══════════════════════════════════════════════════════════════ -->
<div id="m-detail">
    <div class="mm-box" style="max-width:520px;">
        <div style="padding:14px 18px;background:linear-gradient(135deg,#1a0a2e,#2a1a4e);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <div style="color:#fff;font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:8px;">
                <i class="fa fa-right-left" style="color:#a78bfa;"></i>
                Detail Mutasi — <span id="d-no" style="font-family:monospace;font-size:12px;color:#a78bfa;"></span>
            </div>
            <button onclick="mmCloseDetail()"
                style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:12px;display:flex;align-items:center;justify-content:center;transition:background .15s;"
                onmouseover="this.style.background='#ef4444';" onmouseout="this.style.background='rgba(255,255,255,.1)';">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <div style="padding:18px 20px;overflow-y:auto;flex:1;" id="d-body"></div>
        <div style="padding:10px 20px;border-top:1px solid #f0f0f0;background:#f8fafc;display:flex;justify-content:flex-end;flex-shrink:0;">
            <button onclick="mmCloseDetail()" class="btn btn-default btn-sm">Tutup</button>
        </div>
    </div>
</div>


<!-- JS -->
<script>
const APP_URL = '<?= APP_URL ?>';
let _searchTimer;

/* ═══════════════════════════════════════════════════════════
   MODAL OPEN / CLOSE — bulletproof
═══════════════════════════════════════════════════════════ */
var _mmMutasi = document.getElementById('m-mutasi');
var _mmDetail = document.getElementById('m-detail');

function mmOpen() {
    _mmMutasi.classList.add('mm-open');
    setTimeout(function(){ document.getElementById('aset-search-inp').focus(); }, 100);
}
function mmClose() { _mmMutasi.classList.remove('mm-open'); }
function mmCloseDetail() { _mmDetail.classList.remove('mm-open'); }

_mmMutasi.addEventListener('click', function(e){ if (e.target === _mmMutasi) mmClose(); });
_mmDetail.addEventListener('click', function(e){ if (e.target === _mmDetail) mmCloseDetail(); });
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') { mmClose(); mmCloseDetail(); }
});

/* ═══════════════════════════════════════════════════════════
   ASET SEARCH
═══════════════════════════════════════════════════════════ */
function cariAset(q) {
    clearTimeout(_searchTimer);
    const sug = document.getElementById('aset-suggestions');
    if (q.length < 2) { sug.classList.remove('show'); return; }
    _searchTimer = setTimeout(function() {
        fetch(APP_URL + '/pages/mutasi_aset.php?search_aset=1&q=' + encodeURIComponent(q))
            .then(function(r){ return r.json(); })
            .then(function(data) {
                sug.innerHTML = '';
                if (!data.length) {
                    sug.innerHTML = '<div style="padding:10px 13px;font-size:12px;color:#94a3b8;"><i class="fa fa-inbox"></i> Tidak ditemukan</div>';
                } else {
                    data.forEach(function(a) {
                        const el = document.createElement('div');
                        el.className = 'aset-sug-item';
                        const kmap = {'Baik':'#16a34a','Rusak':'#dc2626','Dalam Perbaikan':'#d97706','Tidak Aktif':'#64748b'};
                        el.innerHTML =
                            '<span class="aset-sug-inv">' + escH(a.no_inventaris) + '</span>' +
                            '<div style="flex:1;"><div style="font-size:12.5px;font-weight:600;color:#1e293b;">' + escH(a.nama_aset) + '</div>' +
                            '<div style="font-size:10.5px;color:#94a3b8;">' + escH(a.merek||'') + ' ' + escH(a.kategori||'') + ' · ' + escH(a.bagian_nama||'Tanpa Lokasi') + '</div></div>' +
                            '<span style="font-size:10px;font-weight:700;color:' + (kmap[a.kondisi]||'#64748b') + '">' + escH(a.kondisi||'') + '</span>';
                        el.onclick = function(){ pilihAset(a); };
                        sug.appendChild(el);
                    });
                }
                sug.classList.add('show');
            });
    }, 280);
}

function pilihAset(a) {
    document.getElementById('m-aset-id').value = a.id;
    document.getElementById('aset-search-inp').value = a.nama_aset + ' · ' + a.no_inventaris;
    document.getElementById('aset-suggestions').classList.remove('show');

    document.getElementById('ac-nama').textContent   = a.nama_aset || '—';
    document.getElementById('ac-inv').textContent    = a.no_inventaris || '—';
    document.getElementById('ac-lokasi').textContent = a.bagian_nama || 'Tanpa Lokasi';
    document.getElementById('ac-pj').textContent     = a.pj_nama_db  || 'Tanpa PIC';
    document.getElementById('ac-status').textContent = a.status_pakai || 'Terpakai';

    const kEl = document.getElementById('ac-kondisi');
    kEl.textContent = a.kondisi || 'Baik';
    var kmap2 = {'Baik':'kb-baik','Rusak':'kb-rusak','Dalam Perbaikan':'kb-perbaikan','Tidak Aktif':'kb-tidak'};
    kEl.className = 'mut-badge kondisi-badge ' + (kmap2[a.kondisi] || 'kb-tidak');

    document.getElementById('m-kondisi').value = '';
    document.getElementById('aset-card').classList.add('show');
    document.getElementById('btn-simpan-mut').disabled = false;
}

function clearAset() {
    document.getElementById('m-aset-id').value = '';
    document.getElementById('aset-search-inp').value = '';
    document.getElementById('aset-card').classList.remove('show');
    document.getElementById('btn-simpan-mut').disabled = true;
}

document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.aset-search-wrap');
    if (wrap && !wrap.contains(e.target))
        document.getElementById('aset-suggestions').classList.remove('show');
});

/* ═══════════════════════════════════════════════════════════
   UPDATE JENIS MUTASI
   PERBAIKAN BUG: clear value field yang disembunyikan
   agar server tidak menerima data field yang tidak relevan
═══════════════════════════════════════════════════════════ */
function updateJenis(val) {
    var wBagian = document.getElementById('wrap-bagian');
    var wPic    = document.getElementById('wrap-pic');

    // Sembunyikan semua info badge dulu
    ['keduanya','pindah_lokasi','pindah_pic'].forEach(function(j) {
        document.getElementById('jenis-info-' + j).classList.remove('show');
    });
    document.getElementById('jenis-info-' + val).classList.add('show');

    if (val === 'pindah_lokasi') {
        wBagian.style.display = '';
        wPic.style.display    = 'none';
        // ── PERBAIKAN BUG #1: Clear nilai PIC agar tidak terkirim ──
        document.getElementById('m-ke-pic').value = '';
        document.getElementById('req-bagian').style.display = '';
        document.getElementById('req-pic').style.display    = 'none';
    } else if (val === 'pindah_pic') {
        wBagian.style.display = 'none';
        wPic.style.display    = '';
        // ── PERBAIKAN BUG #1: Clear nilai bagian agar tidak terkirim ──
        document.getElementById('m-ke-bagian').value = '';
        document.getElementById('req-bagian').style.display = 'none';
        document.getElementById('req-pic').style.display    = '';
    } else { // keduanya
        wBagian.style.display = '';
        wPic.style.display    = '';
        document.getElementById('req-bagian').style.display = '';
        document.getElementById('req-pic').style.display    = '';
    }
}

/* ═══════════════════════════════════════════════════════════
   DETAIL MUTASI
═══════════════════════════════════════════════════════════ */
function detailMutasi(m) {
    document.getElementById('d-no').textContent = m.no_mutasi;

    var kmap3 = {'Baik':'kb-baik','Rusak':'kb-rusak','Dalam Perbaikan':'kb-perbaikan','Tidak Aktif':'kb-tidak'};
    var skmap = {'selesai':'mb-selesai','draft':'mb-draft','batal':'mb-batal'};
    var jmap  = {'keduanya':'↔ Pindah Lokasi + PIC','pindah_lokasi':'📍 Pindah Lokasi','pindah_pic':'👤 Pindah PIC'};

    // Baris aset berubah
    var asetChangedBadge = '';
    if (m.status_mutasi === 'selesai') {
        asetChangedBadge = '<span class="aset-updated-badge"><i class="fa fa-check" style="font-size:9px;"></i> aset_it diperbarui</span>';
    }

    document.getElementById('d-body').innerHTML =
        '<div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">' +
            '<span class="mut-badge ' + (skmap[m.status_mutasi]||'mb-selesai') + '">' + ucFirst(m.status_mutasi) + '</span>' +
            '<span style="font-size:11.5px;color:#64748b;"><i class="fa fa-calendar" style="font-size:10px;"></i> ' + fmtDate(m.tanggal_mutasi) + '</span>' +
            '<span style="font-size:11.5px;color:#7c3aed;font-weight:600;">' + (jmap[m.jenis]||m.jenis) + '</span>' +
            asetChangedBadge +
        '</div>' +

        '<div style="margin-bottom:12px;padding:10px 12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">' +
            '<div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;">Aset</div>' +
            '<div style="font-size:13px;font-weight:700;color:#1e293b;">' + escH(m.nama_aset||'—') + '</div>' +
            '<div style="font-family:monospace;font-size:10.5px;color:#1e40af;">' + escH(m.no_inventaris||'') + '</div>' +
        '</div>' +

        '<div class="pihak-box">' +
            '<div class="pihak-pemberi">' +
                '<div class="pihak-label"><i class="fa fa-arrow-up-from-bracket"></i> Pemberi (Asal)</div>' +
                '<div style="display:flex;align-items:center;gap:7px;">' +
                    '<div class="pihak-icon"><i class="fa fa-building"></i></div>' +
                    '<div>' +
                        '<div style="font-size:12px;font-weight:600;color:#1e293b;">' + escH(m.dari_bagian_nama||'—') + '</div>' +
                        '<div style="font-size:10.5px;color:#92400e;">' + escH(m.dari_pic_nama||'—') + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="mut-mid-arrow"><i class="fa fa-right-left" style="color:#00c896;font-size:12px;transform:rotate(90deg);"></i></div>' +
            '<div class="pihak-penerima">' +
                '<div class="pihak-label"><i class="fa fa-arrow-down-to-bracket"></i> Penerima (Tujuan)</div>' +
                '<div style="display:flex;align-items:center;gap:7px;">' +
                    '<div class="pihak-icon"><i class="fa fa-building"></i></div>' +
                    '<div>' +
                        '<div style="font-size:12px;font-weight:600;color:#1e293b;">' +
                            (m.ke_bagian_nama || (m.jenis === 'pindah_pic' ? '<span style="color:#94a3b8;font-style:italic;">Sama seperti sebelum</span>' : '—')) +
                        '</div>' +
                        '<div style="font-size:10.5px;color:#065f46;">' +
                            (m.ke_pic_nama || (m.jenis === 'pindah_lokasi' ? '<span style="color:#94a3b8;font-style:italic;">Sama seperti sebelum</span>' : '—')) +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>' +

        // Kondisi sebelum → sesudah
        '<div style="margin-top:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">' +
            '<span style="font-size:11px;color:#64748b;font-weight:600;">Kondisi:</span>' +
            (m.kondisi_sebelum ? '<span class="kondisi-badge ' + (kmap3[m.kondisi_sebelum]||'kb-tidak') + '">' + escH(m.kondisi_sebelum) + '</span>' : '') +
            '<i class="fa fa-arrow-right" style="color:#94a3b8;font-size:10px;"></i>' +
            (m.kondisi_sesudah ? '<span class="kondisi-badge ' + (kmap3[m.kondisi_sesudah]||'kb-tidak') + '">' + escH(m.kondisi_sesudah) + '</span>' : '') +
            (m.status_pakai ? '<span style="font-size:11px;background:#dbeafe;color:#1e40af;padding:2px 9px;border-radius:9px;font-weight:700;margin-left:4px;">' + escH(m.status_pakai) + '</span>' : '') +
        '</div>' +

        (m.keterangan ? '<div style="margin-top:10px;padding:9px 12px;background:#f8fafc;border-radius:7px;border-left:3px solid #7c3aed;font-size:12px;color:#374151;">"' + escH(m.keterangan) + '"</div>' : '') +

        '<div style="margin-top:10px;font-size:10.5px;color:#94a3b8;">' +
            '<i class="fa fa-user" style="font-size:9px;"></i> Dibuat oleh ' + escH(m.dibuat_nama||'—') + ' · ' + fmtDatetime(m.created_at) +
        '</div>';

    _mmDetail.classList.add('mm-open');
}

/* ── Helpers ── */
function escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function ucFirst(s) { return s ? s.charAt(0).toUpperCase()+s.slice(1) : ''; }
function fmtDate(d) {
    if (!d) return '—';
    var dt = new Date(d);
    return dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});
}
function fmtDatetime(d) {
    if (!d) return '—';
    var dt = new Date(d);
    return dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) +
           ' ' + dt.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
}

/* ═══════════════════════════════════════════════════════════
   DROPDOWN CETAK LAPORAN
═══════════════════════════════════════════════════════════ */
function toggleCetakMut(e) {
    e.stopPropagation();
    var d = document.getElementById('cetak-mut-drop');
    d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('wrap-cetak-mut');
    if (wrap && !wrap.contains(e.target))
        document.getElementById('cetak-mut-drop').style.display = 'none';
});
function bukaCetakMut() {
    var dari    = document.getElementById('cp-dari').value;
    var sampai  = document.getElementById('cp-sampai').value;
    var status  = document.getElementById('cp-status').value;
    var jenis   = document.getElementById('cp-jenis').value;
    if (!dari || !sampai) { alert('Pilih periode terlebih dahulu.'); return; }
    var url = APP_URL + '/pages/cetak_mutasi_aset.php?dari=' + encodeURIComponent(dari)
            + '&sampai=' + encodeURIComponent(sampai)
            + '&status=' + encodeURIComponent(status)
            + '&jenis='  + encodeURIComponent(jenis);
    window.open(url, '_blank');
    document.getElementById('cetak-mut-drop').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>