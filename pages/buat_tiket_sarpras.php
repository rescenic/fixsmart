<?php
// pages/buat_tiket_sarpras.php
session_start();
require_once '../config.php';
requireLogin();
$page_title  = 'Buat Tiket IPSRS';
$active_menu = 'buat_tiket_sarpras';

// Kategori IPSRS
try {
    $kategori_list = $pdo->query("SELECT * FROM kategori_ipsrs ORDER BY jenis, nama")->fetchAll();
} catch (Exception $e) {
    $kategori_list = [];
}

// Lokasi dari master bagian
$lokasi_list = $pdo->query("
    SELECT nama, kode, lokasi FROM bagian
    WHERE status='aktif' AND lokasi IS NOT NULL AND lokasi != ''
    ORDER BY urutan ASC, nama ASC
")->fetchAll();

// Auto-select lokasi sesuai divisi user yang login
$user_lokasi_default = '';
if (!empty($_SESSION['user_divisi'])) {
    $stLok = $pdo->prepare("SELECT lokasi FROM bagian WHERE nama=? AND lokasi IS NOT NULL LIMIT 1");
    $stLok->execute([$_SESSION['user_divisi']]);
    $user_lokasi_default = $stLok->fetchColumn() ?: '';
}

// ── AJAX: Cari aset IPSRS untuk picker ────────────────────────────────────────
if (isset($_GET['aset_picker_search'])) {
    $q     = trim($_GET['q']     ?? '');
    $kat   = trim($_GET['kat']   ?? '');
    $jenis = trim($_GET['jenis'] ?? '');

    $where  = ["a.kondisi NOT IN ('Rusak','Tidak Aktif')"];
    $params = [];

    if ($q !== '') {
        $lq      = "%$q%";
        $where[] = "(a.no_inventaris LIKE ? OR a.nama_aset LIKE ? OR a.merek LIKE ? OR a.model_aset LIKE ? OR a.serial_number LIKE ?)";
        $params  = array_merge($params, [$lq, $lq, $lq, $lq, $lq]);
    }
    if ($jenis !== '') {
        $where[]  = "a.jenis_aset = ?";
        $params[] = $jenis;
    }
    if ($kat !== '') {
        $where[]  = "a.kategori = ?";
        $params[] = $kat;
    }

    $wsql = implode(' AND ', $where);
    try {
        $st = $pdo->prepare("
            SELECT a.id, a.no_inventaris, a.nama_aset, a.kategori, a.jenis_aset,
                   a.merek, a.model_aset, a.serial_number, a.kondisi,
                   b.nama AS bagian_nama, b.kode AS bagian_kode, b.lokasi AS bagian_lokasi,
                   u.nama AS pj_nama
            FROM aset_ipsrs a
            LEFT JOIN bagian b ON b.id = a.bagian_id
            LEFT JOIN users  u ON u.id = a.pj_user_id
            WHERE $wsql
            ORDER BY a.jenis_aset, a.nama_aset ASC
            LIMIT 60
        ");
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $rows = [];
    }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

// ── Handle POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul   = trim($_POST['judul']        ?? '');
    $kat_id  = (int)($_POST['kategori_id'] ?? 0);
    $desc    = trim($_POST['deskripsi']    ?? '');
    $prio    = $_POST['prioritas']         ?? 'Sedang';
    $jenis_t = $_POST['jenis_tiket']       ?? 'Non-Medis'; // Medis / Non-Medis

    $lokasi_pilih  = trim($_POST['lokasi_pilih']  ?? '');
    $lokasi_manual = trim($_POST['lokasi_manual'] ?? '');
    $lokasi = ($lokasi_pilih === '__manual__') ? $lokasi_manual : $lokasi_pilih;

    // Data aset terpilih
    $aset_id   = (int)($_POST['aset_id']  ?? 0) ?: null;
    $aset_info = trim($_POST['aset_info'] ?? '');

    if (!$judul || !$kat_id || !$desc) {
        setFlash('danger', 'Harap isi semua field yang wajib diisi (*).');
    } else {
        $desc_final = $desc;
        if ($aset_id && $aset_info) {
            $desc_final .= "\n\n---\n🔧 Aset terkait: " . $aset_info;
        }

        // Generate nomor tiket IPSRS: TKT-IPSRS-YYYY-NNNN
        $tahun   = date('Y');
        $maxNo   = $pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(nomor,'-',-1) AS UNSIGNED)) FROM tiket_ipsrs WHERE nomor LIKE 'TKT-IPSRS-{$tahun}-%'")->fetchColumn();
        $nomor   = 'TKT-IPSRS-' . $tahun . '-' . str_pad(($maxNo + 1), 4, '0', STR_PAD_LEFT);

        $pdo->prepare("
            INSERT INTO tiket_ipsrs
                (nomor, judul, deskripsi, kategori_id, jenis_tiket, prioritas, user_id, lokasi, aset_id, waktu_submit)
            VALUES (?,?,?,?,?,?,?,?,?,NOW())
        ")->execute([$nomor, $judul, $desc_final, $kat_id, $jenis_t, $prio, $_SESSION['user_id'], $lokasi, $aset_id]);
        $new_id = $pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO tiket_ipsrs_log (tiket_id, user_id, status_dari, status_ke, keterangan)
            VALUES (?,?,NULL,'menunggu',?)
        ")->execute([$new_id, $_SESSION['user_id'], 'Tiket dibuat oleh ' . $_SESSION['user_nama']]);

        // ── NOTIFIKASI TELEGRAM IPSRS ────────────────────────────────────────
        // Gunakan sendTelegramEvent() dengan saluran 'ipsrs' agar dikirim ke
        // grup Telegram IPSRS (bukan grup IT)
        $cfg_tg = getSettings($pdo);
        if (($cfg_tg['ipsrs_telegram_notif_tiket_baru'] ?? '0') === '1') {
            $kat_nama = '-';
            foreach ($kategori_list as $k) {
                if ((int)$k['id'] === $kat_id) { $kat_nama = $k['nama']; break; }
            }
            $emoji_prio = ['Rendah' => '🟢', 'Sedang' => '🟡', 'Tinggi' => '🔴'];
            $ep         = $emoji_prio[$prio] ?? '⚪';
            $aset_line  = $aset_info ? "\n🔧 <b>Aset     :</b> " . htmlspecialchars($aset_info, ENT_QUOTES) : '';

            $msg = "🔔 <b>Tiket IPSRS Baru — FixSmart Helpdesk</b>\n\n"
                 . "📋 <b>No Tiket :</b> {$nomor}\n"
                 . "📌 <b>Judul    :</b> " . htmlspecialchars($judul, ENT_QUOTES) . "\n"
                 . "🏷️ <b>Kategori :</b> " . htmlspecialchars($kat_nama, ENT_QUOTES) . "\n"
                 . "🏥 <b>Jenis    :</b> {$jenis_t}\n"
                 . "{$ep} <b>Prioritas:</b> {$prio}\n"
                 . "📍 <b>Lokasi   :</b> " . htmlspecialchars($lokasi ?: '-', ENT_QUOTES)
                 . $aset_line . "\n"
                 . "👤 <b>Pemohon  :</b> " . htmlspecialchars($_SESSION['user_nama'], ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n\n"
                 . "💬 <b>Deskripsi:</b>\n"
                 . htmlspecialchars(mb_substr($desc, 0, 200, 'UTF-8') . (mb_strlen($desc, 'UTF-8') > 200 ? '...' : ''), ENT_QUOTES) . "\n\n"
                 . "<i>Silakan cek dashboard untuk segera menangani tiket IPSRS ini.</i>";

            // Kirim ke saluran IPSRS
            sendTelegramEvent($pdo, 'tiket_baru', $msg, 'ipsrs');
        }
        // ────────────────────────────────────────────────────────────────────

        setFlash('success', "Tiket IPSRS <strong>$nomor</strong> berhasil dibuat. Tim IPSRS akan segera menghubungi Anda.");
        redirect(APP_URL . '/pages/detail_tiket_ipsrs.php?id=' . $new_id);
    }
}

$cur_pilih  = $_POST['lokasi_pilih']  ?? $user_lokasi_default;
$cur_manual = $_POST['lokasi_manual'] ?? '';

// Daftar kategori aset IPSRS untuk filter chips di modal
try {
    $kat_aset_opts = $pdo->query("
        SELECT DISTINCT kategori FROM aset_ipsrs
        WHERE kategori IS NOT NULL AND kategori != ''
          AND kondisi NOT IN ('Rusak','Tidak Aktif')
        ORDER BY kategori
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $kat_aset_opts = [];
}

include '../includes/header.php';
?>

<style>
/* ═══════════════════════════════════════════════════════
   JENIS TIKET SELECTOR
═══════════════════════════════════════════════════════ */
.jenis-selector { display: flex; gap: 10px; margin-bottom: 4px; }
.jenis-card {
    flex: 1; display: flex; align-items: center; gap: 10px;
    padding: 11px 14px; border: 2px solid #e2e8f0; border-radius: 9px;
    cursor: pointer; transition: all .18s; background: #fff;
    font-family: inherit; text-align: left;
}
.jenis-card:hover { border-color: #94a3b8; }
.jenis-card.active-medis    { border-color: #ec4899; background: #fdf2f8; }
.jenis-card.active-non      { border-color: #3b82f6; background: #eff6ff; }
.jenis-card-ic {
    width: 36px; height: 36px; border-radius: 8px; display: flex;
    align-items: center; justify-content: center; flex-shrink: 0; font-size: 16px;
    background: #f1f5f9; transition: all .18s;
}
.jenis-card.active-medis .jenis-card-ic { background: #fce7f3; }
.jenis-card.active-non   .jenis-card-ic { background: #dbeafe; }
.jenis-card-label { font-size: 12.5px; font-weight: 700; color: #374151; }
.jenis-card-sub   { font-size: 10.5px; color: #94a3b8; margin-top: 1px; }
.jenis-card.active-medis .jenis-card-label { color: #9d174d; }
.jenis-card.active-non   .jenis-card-label { color: #1e40af; }

/* ═══════════════════════════════════════════════════════
   ASET PICKER
═══════════════════════════════════════════════════════ */
.ap-trigger {
    display: flex; align-items: center; gap: 10px;
    width: 100%; padding: 10px 14px; text-align: left;
    border: 2px dashed #cbd5e1; border-radius: 8px;
    background: #f8fafc; cursor: pointer; box-sizing: border-box;
    font-family: inherit; font-size: 13px; font-weight: 600; color: #64748b;
    transition: all .18s;
}
.ap-trigger:hover         { border-color: #26B99A; background: #f0fdf9; color: #0f766e; }
.ap-trigger.has-aset      { border-style: solid; border-color: #86efac; background: linear-gradient(135deg,#f0fdf9,#dcfce7); color: #166534; }
.ap-trig-icon { width: 34px; height: 34px; border-radius: 7px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 15px; transition: all .18s; }
.ap-trigger.has-aset .ap-trig-icon { background: #15803d; }
.ap-trigger.has-aset .ap-trig-icon i { color: #fff !important; }
.ap-trig-txt   { flex: 1; min-width: 0; }
.ap-trig-name  { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ap-trig-sub   { font-size: 10.5px; color: #94a3b8; margin-top: 1px; }
.ap-trigger.has-aset .ap-trig-sub { color: #4ade80; }

.ap-selected-card {
    display: none; margin-top: 9px; padding: 11px 13px;
    background: linear-gradient(135deg,#f0fdf9,#ecfdf5);
    border: 1.5px solid #86efac; border-radius: 8px; position: relative;
}
.ap-selected-card.show { display: block; }
.asc-row    { display: flex; align-items: flex-start; gap: 11px; }
.asc-icon   { width: 38px; height: 38px; border-radius: 8px; background: #15803d; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.asc-body   { flex: 1; min-width: 0; }
.asc-nama   { font-size: 13px; font-weight: 700; color: #1e293b; }
.asc-inv    { display: inline-block; font-family: 'Courier New', monospace; font-size: 10.5px; font-weight: 700; background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 1px 7px; border-radius: 4px; margin-top: 2px; }
.asc-meta   { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
.asc-meta-i { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #64748b; }
.asc-meta-i i { color: #26B99A; font-size: 10px; }
.asc-kond   { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 10px; font-size: 10.5px; font-weight: 700; }
.asc-k-baik { background: #dcfce7; color: #166534; }
.asc-k-serv { background: #fef9c3; color: #854d0e; }
.asc-remove { position: absolute; top: 9px; right: 10px; width: 22px; height: 22px; border-radius: 50%; background: #fee2e2; border: none; cursor: pointer; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 10px; transition: background .14s; }
.asc-remove:hover { background: #fca5a5; }
.asc-jenis-m { display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#fce7f3;color:#9d174d; }
.asc-jenis-n { display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#dbeafe;color:#1e40af; }

/* ═══════════════════════════════════════════════════════
   MODAL ASET PICKER
═══════════════════════════════════════════════════════ */
.apm-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.52); z-index: 9200;
    align-items: center; justify-content: center; padding: 16px;
}
.apm-overlay.open { display: flex; }
.apm-box {
    background: #fff; width: 100%; max-width: 700px;
    border-radius: 12px; box-shadow: 0 28px 70px rgba(0,0,0,.25);
    overflow: hidden; animation: mIn .2s ease;
    display: flex; flex-direction: column; max-height: 88vh;
}
.apm-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; background: linear-gradient(135deg,#1a2e3f,#1b5c4a); flex-shrink: 0; }
.apm-head-l { display: flex; align-items: center; gap: 10px; }
.apm-head-ic { width: 30px; height: 30px; background: rgba(38,185,154,.25); border: 1px solid rgba(38,185,154,.5); border-radius: 6px; display: flex; align-items: center; justify-content: center; }
.apm-head-title { color: #fff; font-size: 13.5px; font-weight: 700; }
.apm-head-sub   { color: rgba(255,255,255,.4); font-size: 10px; margin-top: 1px; }
.apm-close { width: 26px; height: 26px; border-radius: 50%; background: rgba(255,255,255,.1); border: none; cursor: pointer; color: #ccc; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: background .15s; }
.apm-close:hover { background: #ef4444; color: #fff; }
.apm-search-area { padding: 12px 16px 10px; border-bottom: 1px solid #f1f5f9; background: #fafafa; flex-shrink: 0; }
.apm-jenis-tabs { display: flex; gap: 6px; margin-bottom: 9px; }
.apm-jenis-tab  { padding: 4px 13px; border-radius: 14px; font-size: 11px; font-weight: 700; border: 1.5px solid #e2e8f0; background: #f8fafc; color: #64748b; cursor: pointer; transition: all .14s; user-select: none; }
.apm-jenis-tab:hover             { border-color: #94a3b8; }
.apm-jenis-tab.active-all        { background: #1e293b; color: #fff; border-color: #1e293b; }
.apm-jenis-tab.active-medis      { background: #ec4899; color: #fff; border-color: #ec4899; }
.apm-jenis-tab.active-non        { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.apm-search-wrap { display: flex; border: 1.5px solid #d1d5db; border-radius: 8px; overflow: hidden; background: #fff; transition: border .18s, box-shadow .18s; }
.apm-search-wrap:focus-within { border-color: #26B99A; box-shadow: 0 0 0 3px rgba(38,185,154,.1); }
.apm-search-inp { flex: 1; padding: 9px 12px; border: none; outline: none; font-size: 13px; font-family: inherit; }
.apm-search-btn { padding: 9px 15px; background: #26B99A; border: none; color: #fff; cursor: pointer; font-size: 13px; transition: background .15s; }
.apm-search-btn:hover { background: #1a7a5e; }
.apm-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 9px; }
.apm-chip  { padding: 3px 11px; border-radius: 14px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; cursor: pointer; white-space: nowrap; transition: all .14px; user-select: none; }
.apm-chip:hover  { border-color: #26B99A; color: #0f766e; background: #f0fdf9; }
.apm-chip.active { background: #26B99A; color: #fff; border-color: #26B99A; }
.apm-list { flex: 1; overflow-y: auto; min-height: 220px; }
.apm-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #f3f4f6; cursor: pointer; transition: background .13s; }
.apm-item:hover { background: #f0fdf9; }
.apm-item:last-child { border-bottom: none; }
.apm-item.selected { background: #dcfce7; }
.apm-item-ic   { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.apm-item-body { flex: 1; min-width: 0; }
.apm-item-nama { font-size: 12.5px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.apm-item-inv  { display: inline-block; font-family: 'Courier New', monospace; font-size: 10px; background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; padding: 1px 6px; border-radius: 3px; margin-top: 2px; }
.apm-item-meta { font-size: 10.5px; color: #94a3b8; margin-top: 2px; }
.apm-item-lok  { font-size: 10px; color: #b0bec5; margin-top: 2px; }
.apm-item-right { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.apm-kond      { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 10px; font-size: 10.5px; font-weight: 700; white-space: nowrap; }
.apm-k-baik    { background: #dcfce7; color: #166534; }
.apm-k-serv    { background: #fef9c3; color: #854d0e; }
.apm-jenis-m   { display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;background:#fce7f3;color:#9d174d;white-space:nowrap; }
.apm-jenis-n   { display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;background:#dbeafe;color:#1e40af;white-space:nowrap; }
.apm-state { padding: 40px 20px; text-align: center; color: #94a3b8; }
.apm-state i { font-size: 34px; display: block; margin-bottom: 10px; }
.apm-state-title { font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px; }
.apm-state-sub   { font-size: 11px; }
.apm-foot { padding: 10px 16px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; flex-shrink: 0; }
.apm-count { font-size: 11px; color: #94a3b8; }
.apm-hint  { font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 5px; }
.apm-hint i { color: #26B99A; }
mark.apm-hl { background: #fef9c3; color: #854d0e; border-radius: 2px; padding: 0 1px; font-weight: 700; font-style: normal; }
</style>


<div class="page-header">
  <h4><i class="fa fa-plus-circle" style="color:var(--primary);"></i> &nbsp;Buat Tiket IPSRS</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Beranda</a><span class="sep">/</span>
    <span class="cur">Buat Tiket IPSRS</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <div class="g3">
    <!-- ════ FORM PANEL ════ -->
    <div class="panel">
      <div class="panel-hd">
        <h5><i class="fa fa-edit" style="color:var(--primary);"></i> &nbsp;Form Pengajuan Tiket IPSRS</h5>
      </div>
      <div class="panel-bd">
        <form method="POST">

          <!-- ① Jenis Tiket (Medis / Non-Medis) -->
          <div class="form-group">
            <label>Jenis Permintaan <span class="req">*</span></label>
            <div class="jenis-selector">
              <button type="button" id="btn-jenis-medis" class="jenis-card" onclick="setJenis('Medis')">
                <div class="jenis-card-ic">🏥</div>
                <div>
                  <div class="jenis-card-label">Medis</div>
                  <div class="jenis-card-sub">Peralatan elektromedis & kalibrasi</div>
                </div>
              </button>
              <button type="button" id="btn-jenis-non" class="jenis-card active-non" onclick="setJenis('Non-Medis')">
                <div class="jenis-card-ic">🔧</div>
                <div>
                  <div class="jenis-card-label">Non-Medis</div>
                  <div class="jenis-card-sub">Sarana, prasarana & utilitas</div>
                </div>
              </button>
            </div>
            <input type="hidden" name="jenis_tiket" id="inp-jenis" value="Non-Medis">
          </div>

          <!-- ② Judul -->
          <div class="form-group">
            <label>Judul Keluhan / Permintaan <span class="req">*</span></label>
            <input type="text" name="judul" class="form-control"
                   id="inp-judul"
                   placeholder="Contoh: AC bocor, Genset tidak menyala, Kalibrasi ventilator…"
                   value="<?= clean($_POST['judul'] ?? '') ?>" required>
            <div class="form-hint">Tuliskan judul yang singkat dan jelas.</div>
          </div>

          <!-- ③ Kategori + Prioritas -->
          <div class="form-row">
            <div class="form-group">
              <label>Kategori <span class="req">*</span></label>
              <select name="kategori_id" id="sel-kategori" class="form-control" required>
                <option value="">-- Pilih Kategori --</option>
                <?php
                $cur_jenis = '';
                foreach ($kategori_list as $k):
                    if ($k['jenis'] !== $cur_jenis) {
                        $cur_jenis = $k['jenis'];
                        echo '<optgroup label="── ' . clean($k['jenis']) . ' ──">';
                    }
                ?>
                <option value="<?= $k['id'] ?>"
                        data-jenis="<?= clean($k['jenis']) ?>"
                        <?= ($_POST['kategori_id'] ?? '') == $k['id'] ? 'selected' : '' ?>>
                  <?= clean($k['nama']) ?> — Target <?= $k['sla_jam'] ?> jam
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Tingkat Prioritas</label>
              <select name="prioritas" class="form-control">
                <option value="Rendah" <?= ($_POST['prioritas'] ?? '') === 'Rendah' ? 'selected' : '' ?>>🟢 Rendah — Tidak mendesak</option>
                <option value="Sedang" <?= ($_POST['prioritas'] ?? 'Sedang') === 'Sedang' ? 'selected' : '' ?>>🟡 Sedang — Perlu segera</option>
                <option value="Tinggi" <?= ($_POST['prioritas'] ?? '') === 'Tinggi' ? 'selected' : '' ?>>🔴 Tinggi — Sangat mendesak</option>
              </select>
            </div>
          </div>

          <!-- ④ ASET IPSRS PICKER -->
          <div class="form-group">
            <label>
              <i class="fa fa-toolbox" style="color:var(--primary);margin-right:3px;"></i>
              Peralatan / Aset IPSRS yang Bermasalah
              <span style="font-size:10.5px;font-weight:400;color:#94a3b8;margin-left:4px;">(opsional)</span>
            </label>

            <input type="hidden" name="aset_id"   id="inp-aset-id"   value="<?= (int)($_POST['aset_id'] ?? 0) ?: '' ?>">
            <input type="hidden" name="aset_info"  id="inp-aset-info" value="<?= clean($_POST['aset_info'] ?? '') ?>">

            <button type="button" id="ap-trig-btn" class="ap-trigger" onclick="apmBuka()">
              <div class="ap-trig-icon">
                <i class="fa fa-magnifying-glass" style="color:#64748b;" id="ap-trig-ico"></i>
              </div>
              <div class="ap-trig-txt">
                <div class="ap-trig-name" id="ap-trig-name">Cari dan pilih dari daftar aset IPSRS…</div>
                <div class="ap-trig-sub"  id="ap-trig-sub">Klik untuk membuka daftar peralatan</div>
              </div>
              <i class="fa fa-chevron-right" style="font-size:10px;color:#cbd5e1;margin-left:auto;flex-shrink:0;"></i>
            </button>

            <!-- Kartu aset terpilih -->
            <div class="ap-selected-card" id="ap-selected-card">
              <button type="button" class="asc-remove" onclick="apmReset()" title="Hapus pilihan aset">
                <i class="fa fa-times"></i>
              </button>
              <div class="asc-row">
                <div class="asc-icon">
                  <i class="fa fa-toolbox" style="color:#fff;font-size:15px;" id="asc-ico"></i>
                </div>
                <div class="asc-body">
                  <div class="asc-nama" id="asc-nama">—</div>
                  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:3px;">
                    <div class="asc-inv" id="asc-inv">—</div>
                    <span id="asc-jenis-badge"></span>
                  </div>
                  <div class="asc-meta">
                    <div class="asc-meta-i" id="asc-row-merek">
                      <i class="fa fa-tag"></i>
                      <span id="asc-merek">—</span>
                    </div>
                    <div class="asc-meta-i" id="asc-row-serial">
                      <i class="fa fa-barcode"></i>
                      <span id="asc-serial" style="font-family:monospace;font-size:10.5px;">—</span>
                    </div>
                    <div class="asc-meta-i" id="asc-row-lokasi">
                      <i class="fa fa-location-dot"></i>
                      <span id="asc-lokasi">—</span>
                    </div>
                    <div class="asc-meta-i" id="asc-row-pj">
                      <i class="fa fa-user"></i>
                      <span id="asc-pj">—</span>
                    </div>
                    <div class="asc-meta-i">
                      <i class="fa fa-circle-half-stroke"></i>
                      <span class="asc-kond asc-k-baik" id="asc-kond">—</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="form-hint">
              <i class="fa fa-lightbulb" style="color:#f59e0b;"></i>
              Pilih peralatan yang bermasalah agar teknisi IPSRS langsung tahu aset mana yang harus ditangani.
            </div>
          </div>

          <!-- ⑤ Deskripsi -->
          <div class="form-group">
            <label>Deskripsi Masalah / Permintaan <span class="req">*</span></label>
            <textarea name="deskripsi" class="form-control" style="min-height:110px;"
                      placeholder="Jelaskan masalah secara detail:&#10;- Sejak kapan terjadi?&#10;- Apa yang sudah dicoba?&#10;- Kondisi peralatan saat ini (jika ada)"
                      required><?= clean($_POST['deskripsi'] ?? '') ?></textarea>
          </div>

          <!-- ⑥ Lokasi -->
          <div class="form-group">
            <label>Lokasi / Ruangan <span class="req">*</span></label>
            <select name="lokasi_pilih" id="sel-lokasi" class="form-control"
                    onchange="toggleManual(this.value)" required>
              <option value="">-- Pilih Ruangan / Bagian --</option>
              <?php foreach ($lokasi_list as $l): ?>
              <option value="<?= clean($l['lokasi']) ?>"
                      <?= $cur_pilih === $l['lokasi'] ? 'selected' : '' ?>>
                <?= clean($l['nama']) ?><?= $l['kode'] ? ' ('.$l['kode'].')' : '' ?>
                &nbsp;—&nbsp;<?= clean($l['lokasi']) ?>
              </option>
              <?php endforeach; ?>
              <option value="__manual__" <?= $cur_pilih === '__manual__' ? 'selected' : '' ?>>
                ✏️ Lainnya (ketik manual)
              </option>
            </select>

            <div id="box-manual" style="margin-top:8px;<?= $cur_pilih === '__manual__' ? '' : 'display:none' ?>">
              <input type="text" name="lokasi_manual" id="inp-manual"
                     class="form-control"
                     placeholder="Contoh: Gudang Lt.2, Lobby Utama, R.Mesin..."
                     value="<?= clean($cur_manual) ?>"
                     <?= $cur_pilih === '__manual__' ? 'required' : '' ?>>
              <div class="form-hint"><i class="fa fa-info-circle"></i> Ketik lokasi spesifik jika tidak ada di daftar.</div>
            </div>
          </div>

          <div style="display:flex;gap:8px;margin-top:4px;">
            <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Kirim Tiket IPSRS</button>
            <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-default">Batal</a>
          </div>
        </form>
      </div>
    </div>

    <!-- ════ INFO PANEL KANAN ════ -->
    <div>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-info-circle" style="color:var(--primary);"></i> &nbsp;Alur Penanganan IPSRS</h5></div>
        <div class="panel-bd">
          <?php
          $steps = [
            ['fa-paper-plane','var(--primary)','Anda Kirim Tiket','Tiket masuk ke antrian IPSRS'],
            ['fa-clock','var(--yellow)','Menunggu','Tim IPSRS akan segera memproses'],
            ['fa-cogs','var(--blue)','Diproses','Teknisi IPSRS sedang menangani'],
            ['fa-check-circle','var(--green)','Selesai','Masalah berhasil diselesaikan'],
          ];
          foreach ($steps as $i => [$ic,$cl,$t,$d]):
          ?>
          <div style="display:flex;gap:10px;margin-bottom:<?= $i<3?'14px':'0' ?>;<?= $i<3?'padding-bottom:14px;border-bottom:1px solid #f5f5f5;':'' ?>">
            <div style="width:30px;height:30px;border-radius:50%;background:<?= $cl ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa <?= $ic ?>" style="color:#fff;font-size:12px;"></i>
            </div>
            <div>
              <div style="font-size:12px;font-weight:700;color:#333;"><?= $t ?></div>
              <div style="font-size:11px;color:#aaa;margin-top:1px;"><?= $d ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <hr class="divider">
          <p style="font-size:11px;color:#aaa;line-height:1.7;">
            <i class="fa fa-ban" style="color:var(--red);"></i> <strong>Ditolak:</strong> Permintaan tidak sesuai kebijakan.<br>
            <i class="fa fa-ban" style="color:var(--purple);"></i> <strong>Tidak Bisa:</strong> Di luar kemampuan IPSRS internal.
          </p>
        </div>
      </div>

      <?php if (!empty($lokasi_list)): ?>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-map-marker-alt" style="color:var(--primary);"></i> &nbsp;Lokasi per Bagian</h5></div>
        <div class="panel-bd" style="padding:10px 14px;">
          <?php foreach ($lokasi_list as $l): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;padding-bottom:7px;border-bottom:1px solid #f5f5f5;font-size:12px;">
            <span style="font-weight:600;">
              <?= clean($l['nama']) ?>
              <?php if ($l['kode']): ?>
              <span style="font-size:10px;background:#f0f0f0;padding:1px 5px;border-radius:3px;color:#888;margin-left:3px;"><?= clean($l['kode']) ?></span>
              <?php endif; ?>
            </span>
            <span style="color:#aaa;font-size:11px;"><i class="fa fa-map-marker-alt" style="color:var(--primary);"></i> <?= clean($l['lokasi']) ?></span>
          </div>
          <?php endforeach; ?>
          <p style="font-size:11px;color:#bbb;margin-top:2px;"><i class="fa fa-info-circle"></i> Pilih "Lainnya" jika lokasi tidak ada di daftar.</p>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($kategori_list)): ?>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-tags" style="color:var(--primary);"></i> &nbsp;Target SLA per Kategori</h5></div>
        <div class="panel-bd" style="padding:10px 14px;">
          <?php
          $cur_j = '';
          foreach ($kategori_list as $k):
              if ($k['jenis'] !== $cur_j) {
                  $cur_j = $k['jenis'];
                  $bwarna = $cur_j === 'Medis' ? '#fce7f3' : '#dbeafe';
                  $twarna = $cur_j === 'Medis' ? '#9d174d' : '#1e40af';
                  echo "<div style='font-size:10px;font-weight:700;color:{$twarna};background:{$bwarna};padding:3px 8px;border-radius:4px;margin-bottom:6px;margin-top:".($cur_j!=='Medis'?'10px':'0')."'>{$cur_j}</div>";
              }
          ?>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:12px;">
            <span><i class="fa <?= clean($k['icon']) ?>" style="color:var(--primary);width:16px;"></i> <?= clean($k['nama']) ?></span>
            <span style="color:#aaa;"><?= $k['sla_jam'] ?> jam</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /g3 -->
</div><!-- /content -->


<!-- ════ MODAL ASET PICKER IPSRS ════ -->
<div class="apm-overlay" id="apm-overlay" onclick="if(event.target===this)apmTutup()">
  <div class="apm-box">

    <div class="apm-head">
      <div class="apm-head-l">
        <div class="apm-head-ic">
          <i class="fa fa-toolbox" style="color:#26B99A;font-size:13px;"></i>
        </div>
        <div>
          <div class="apm-head-title">Pilih Peralatan / Aset IPSRS</div>
          <div class="apm-head-sub">Cari berdasarkan nama, no. inventaris, merek, atau serial</div>
        </div>
      </div>
      <button type="button" class="apm-close" onclick="apmTutup()">
        <i class="fa fa-times"></i>
      </button>
    </div>

    <div class="apm-search-area">
      <div class="apm-jenis-tabs">
        <span class="apm-jenis-tab active-all" onclick="apmSetJenis('', this)">Semua</span>
        <span class="apm-jenis-tab" onclick="apmSetJenis('Medis', this)">🏥 Medis</span>
        <span class="apm-jenis-tab" onclick="apmSetJenis('Non-Medis', this)">🔧 Non-Medis</span>
      </div>
      <div class="apm-search-wrap">
        <input type="text" id="apm-inp" class="apm-search-inp"
               placeholder="Ketik nama aset, no. inventaris, merek, serial…"
               oninput="apmDebounce(this.value)" autocomplete="off">
        <button type="button" class="apm-search-btn"
                onclick="apmLoad(document.getElementById('apm-inp').value)">
          <i class="fa fa-search"></i>
        </button>
      </div>
      <div class="apm-chips" id="apm-chips">
        <span class="apm-chip active" onclick="apmSetKat('', this)">
          <i class="fa fa-layer-group" style="font-size:10px;margin-right:2px;"></i> Semua Kategori
        </span>
        <?php foreach ($kat_aset_opts as $ka): ?>
        <span class="apm-chip" onclick="apmSetKat('<?= addslashes(clean($ka)) ?>', this)">
          <?= clean($ka) ?>
        </span>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="apm-list" id="apm-list">
      <div class="apm-state">
        <i class="fa fa-magnifying-glass" style="color:#e2e8f0;"></i>
        <div class="apm-state-title">Ketik untuk mencari aset IPSRS</div>
        <div class="apm-state-sub">Atau klik salah satu filter di atas untuk melihat semua</div>
      </div>
    </div>

    <div class="apm-foot">
      <div class="apm-count" id="apm-count">—</div>
      <div class="apm-hint">
        <i class="fa fa-circle-info"></i>
        Hanya menampilkan aset Baik &amp; Dalam Perbaikan
      </div>
    </div>

  </div>
</div>
<!-- ════ END MODAL ASET PICKER ════ -->


<script>
const APP_URL = '<?= APP_URL ?>';

/* ══ JENIS TIKET ══ */
function setJenis(jenis) {
    document.getElementById('inp-jenis').value = jenis;
    const bM = document.getElementById('btn-jenis-medis');
    const bN = document.getElementById('btn-jenis-non');
    bM.className = 'jenis-card' + (jenis === 'Medis'     ? ' active-medis' : '');
    bN.className = 'jenis-card' + (jenis === 'Non-Medis' ? ' active-non'   : '');
    const sel = document.getElementById('sel-kategori');
    for (const opt of sel.options) {
        if (!opt.value) continue;
        const j = opt.dataset.jenis || '';
        opt.hidden = (jenis !== '' && j !== jenis);
    }
    const cur = sel.options[sel.selectedIndex];
    if (cur && cur.hidden) sel.value = '';
}

/* ══ LOKASI TOGGLE ══ */
function toggleManual(val) {
    const box = document.getElementById('box-manual');
    const inp = document.getElementById('inp-manual');
    if (val === '__manual__') {
        box.style.display = 'block'; inp.required = true; inp.focus();
    } else {
        box.style.display = 'none'; inp.required = false; inp.value = '';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('sel-lokasi');
    if (sel && sel.value) toggleManual(sel.value);
});

/* ══ ASET PICKER ══ */
let _apmKat = '', _apmJenis = '', _apmTimer = null, _apmSelId = null;

const KAT_STYLE = {
    'Peralatan Diagnostik'   : ['fa-stethoscope',       '#0891b2','#ecfeff'],
    'Ventilator / Respirator': ['fa-lungs',              '#7c3aed','#faf5ff'],
    'Infus Pump / Syringe'   : ['fa-syringe',            '#db2777','#fdf2f8'],
    'Sterilisasi / Autoclave': ['fa-flask-vial',         '#059669','#ecfdf5'],
    'Peralatan Bedah'        : ['fa-bandage',            '#dc2626','#fef2f2'],
    'Peralatan Laboratorium' : ['fa-flask-vial',         '#0f766e','#f0fdfa'],
    'Radiologi / Imaging'    : ['fa-radiation',          '#b45309','#fef3c7'],
    'Peralatan Gigi'         : ['fa-tooth',              '#6d28d9','#faf5ff'],
    'Peralatan Mata'         : ['fa-eye',                '#0369a1','#f0f9ff'],
    'Inkubator / Infant'     : ['fa-baby',               '#be185d','#fdf2f8'],
    'Lainnya (Medis)'        : ['fa-kit-medical',        '#64748b','#f8fafc'],
    'Instalasi Listrik'      : ['fa-bolt',               '#ca8a04','#fefce8'],
    'Generator / UPS'        : ['fa-plug-circle-bolt',  '#92400e','#fef3c7'],
    'HVAC / AC'              : ['fa-wind',               '#0284c7','#f0f9ff'],
    'Sanitasi / Plumbing'    : ['fa-droplet',            '#0891b2','#ecfeff'],
    'Pompa / Kompresor'      : ['fa-gear',               '#4b5563','#f9fafb'],
    'Dapur / Gizi'           : ['fa-utensils',           '#92400e','#fef9c3'],
    'Laundry'                : ['fa-shirt',              '#6d28d9','#faf5ff'],
    'Kebersihan'             : ['fa-broom',              '#16a34a','#f0fdf4'],
    'Kendaraan / Ambulans'   : ['fa-car',                '#1d4ed8','#eff6ff'],
    'Keamanan / CCTV'        : ['fa-camera',             '#dc2626','#fef2f2'],
    'Alat Angkat / Angkut'   : ['fa-dolly',              '#7c3aed','#faf5ff'],
    'Lainnya (Non-Medis)'    : ['fa-toolbox',            '#64748b','#f8fafc'],
};

function apmBuka()  { document.getElementById('apm-overlay').classList.add('open'); apmLoad(''); setTimeout(() => document.getElementById('apm-inp').focus(), 160); }
function apmTutup() { document.getElementById('apm-overlay').classList.remove('open'); }

function apmSetJenis(jenis, el) {
    _apmJenis = jenis;
    document.querySelectorAll('.apm-jenis-tab').forEach(t => t.className = 'apm-jenis-tab');
    if (jenis === '')           el.classList.add('active-all');
    else if (jenis === 'Medis') el.classList.add('active-medis');
    else                        el.classList.add('active-non');
    apmLoad(document.getElementById('apm-inp').value);
}
function apmSetKat(kat, el) {
    _apmKat = kat;
    document.querySelectorAll('.apm-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    apmLoad(document.getElementById('apm-inp').value);
}
function apmDebounce(q) { clearTimeout(_apmTimer); _apmShowLoading(); _apmTimer = setTimeout(() => apmLoad(q), 300); }
function apmLoad(q) {
    let url = `${APP_URL}/pages/buat_tiket_sarpras.php?aset_picker_search=1&q=${encodeURIComponent(q||'')}`;
    if (_apmKat)   url += `&kat=${encodeURIComponent(_apmKat)}`;
    if (_apmJenis) url += `&jenis=${encodeURIComponent(_apmJenis)}`;
    fetch(url).then(r => { if (!r.ok) throw new Error(); return r.json(); }).then(d => _apmRender(d, q)).catch(() => _apmShowError());
}
function _apmRender(data, q) {
    const list = document.getElementById('apm-list');
    const cnt  = document.getElementById('apm-count');
    if (!data || !data.length) {
        cnt.textContent = '0 aset';
        list.innerHTML = `<div class="apm-state"><i class="fa fa-box-open" style="color:#e2e8f0;"></i><div class="apm-state-title">Tidak ada aset ditemukan</div><div class="apm-state-sub">Coba kata kunci lain atau ubah filter</div></div>`;
        return;
    }
    cnt.textContent = data.length + (data.length >= 60 ? '+' : '') + ' aset';
    list.innerHTML = data.map(a => {
        const [ico, col, bg] = KAT_STYLE[a.kategori] || ['fa-toolbox','#64748b','#f1f5f9'];
        const kCls   = a.kondisi === 'Baik' ? 'apm-k-baik' : 'apm-k-serv';
        const kIco   = a.kondisi === 'Baik' ? 'fa-circle-check' : 'fa-wrench';
        const jBadge = a.jenis_aset === 'Medis'
            ? `<span class="apm-jenis-m"><i class="fa fa-kit-medical" style="font-size:9px;"></i> Medis</span>`
            : `<span class="apm-jenis-n"><i class="fa fa-screwdriver-wrench" style="font-size:9px;"></i> Non-Medis</span>`;
        const namaHL    = _hl(_esc(a.nama_aset), q);
        const invHL     = _hl(_esc(a.no_inventaris), q);
        const merekModel= [a.merek, a.model_aset].filter(Boolean).map(_esc).join(' · ');
        const lokasiStr = [a.bagian_kode?'['+a.bagian_kode+']':'', a.bagian_nama, a.bagian_lokasi].filter(Boolean).join(' ');
        const js = s => (s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/\n/g,' ');
        return `<div class="apm-item${_apmSelId==a.id?' selected':''}"
             onclick="apmPilih(${a.id},'${js(a.no_inventaris)}','${js(a.nama_aset)}','${js(a.merek||'')}','${js(a.model_aset||'')}','${js(a.kondisi)}','${js(a.serial_number||'')}','${js(lokasiStr)}','${js(a.bagian_lokasi||'')}','${js(a.kategori||'')}','${js(a.pj_nama||'')}','${js(a.jenis_aset||'')}')">
          <div class="apm-item-ic" style="background:${bg};"><i class="fa ${ico}" style="color:${col};"></i></div>
          <div class="apm-item-body">
            <div class="apm-item-nama">${namaHL}</div>
            <div class="apm-item-inv">${invHL}</div>
            <div class="apm-item-meta">${merekModel||_esc(a.kategori||'—')}</div>
            ${lokasiStr?`<div class="apm-item-lok"><i class="fa fa-location-dot" style="color:#26B99A;font-size:9px;"></i> ${_esc(lokasiStr)}</div>`:''}
          </div>
          <div class="apm-item-right">
            <div class="apm-kond ${kCls}"><i class="fa ${kIco}" style="font-size:9px;"></i> ${_esc(a.kondisi)}</div>
            ${jBadge}
          </div>
        </div>`;
    }).join('');
}
function _apmShowLoading() {
    document.getElementById('apm-count').textContent = '…';
    document.getElementById('apm-list').innerHTML = `<div class="apm-state"><i class="fa fa-spinner fa-spin" style="color:#26B99A;"></i><div class="apm-state-title">Mencari aset…</div></div>`;
}
function _apmShowError() {
    document.getElementById('apm-count').textContent = '—';
    document.getElementById('apm-list').innerHTML = `<div class="apm-state"><i class="fa fa-triangle-exclamation" style="color:#f59e0b;"></i><div class="apm-state-title">Gagal memuat data</div><div class="apm-state-sub">Periksa koneksi jaringan Anda</div></div>`;
}
function apmPilih(id, inv, nama, merek, model, kond, serial, lokasiStr, bagianLokasi, kat, pj, jenis) {
    _apmSelId = id;
    document.getElementById('inp-aset-id').value   = id;
    document.getElementById('inp-aset-info').value = [inv, nama, merek, model].filter(Boolean).join(' | ');
    const [ico] = KAT_STYLE[kat] || ['fa-toolbox'];
    const btn = document.getElementById('ap-trig-btn');
    btn.classList.add('has-aset');
    document.getElementById('ap-trig-ico').className    = 'fa ' + ico;
    document.getElementById('ap-trig-name').textContent  = nama + '  (' + inv + ')';
    document.getElementById('ap-trig-sub').textContent   = [merek,model].filter(Boolean).join(' · ') || 'Klik untuk ganti pilihan';
    document.getElementById('asc-ico').className  = 'fa ' + ico;
    document.getElementById('asc-nama').textContent = nama;
    document.getElementById('asc-inv').textContent  = inv;
    const jb = document.getElementById('asc-jenis-badge');
    jb.innerHTML = jenis === 'Medis'
        ? '<span class="asc-jenis-m"><i class="fa fa-kit-medical" style="font-size:9px;"></i> Medis</span>'
        : '<span class="asc-jenis-n"><i class="fa fa-screwdriver-wrench" style="font-size:9px;"></i> Non-Medis</span>';
    const kondEl = document.getElementById('asc-kond');
    kondEl.textContent = kond;
    kondEl.className   = 'asc-kond ' + (kond === 'Baik' ? 'asc-k-baik' : 'asc-k-serv');
    const merekRow = document.getElementById('asc-row-merek');
    if (merek || model) { document.getElementById('asc-merek').textContent = [merek,model].filter(Boolean).join(' · '); merekRow.style.display=''; } else merekRow.style.display='none';
    const serialRow = document.getElementById('asc-row-serial');
    if (serial) { document.getElementById('asc-serial').textContent = serial; serialRow.style.display=''; } else serialRow.style.display='none';
    const lokasiRow = document.getElementById('asc-row-lokasi');
    if (lokasiStr) {
        document.getElementById('asc-lokasi').textContent = lokasiStr;
        lokasiRow.style.display = '';
        const selLok = document.getElementById('sel-lokasi');
        if (!selLok.value && bagianLokasi) {
            for (const opt of selLok.options) {
                if (opt.value && opt.value.trim() === bagianLokasi.trim()) { selLok.value = opt.value; toggleManual(opt.value); break; }
            }
        }
    } else lokasiRow.style.display='none';
    const pjRow = document.getElementById('asc-row-pj');
    if (pj) { document.getElementById('asc-pj').textContent = pj; pjRow.style.display=''; } else pjRow.style.display='none';
    document.getElementById('ap-selected-card').classList.add('show');
    apmTutup();
}
function apmReset() {
    _apmSelId = null;
    document.getElementById('inp-aset-id').value   = '';
    document.getElementById('inp-aset-info').value = '';
    const btn = document.getElementById('ap-trig-btn');
    btn.classList.remove('has-aset');
    document.getElementById('ap-trig-ico').className   = 'fa fa-magnifying-glass';
    document.getElementById('ap-trig-name').textContent = 'Cari dan pilih dari daftar aset IPSRS…';
    document.getElementById('ap-trig-sub').textContent  = 'Klik untuk membuka daftar peralatan';
    document.getElementById('ap-selected-card').classList.remove('show');
}
function _esc(s) { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function _hl(html, q) { if (!q||!q.trim()) return html; const safe = q.trim().replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); return html.replace(new RegExp(`(${safe})`,'gi'),'<mark class="apm-hl">$1</mark>'); }
</script>

<?php include '../includes/footer.php'; ?>