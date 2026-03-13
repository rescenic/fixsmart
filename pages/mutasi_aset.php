<?php
// pages/mutasi_aset.php — Mutasi Aset IT
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger', 'Akses ditolak.'); redirect(APP_URL . '/dashboard.php'); }

$page_title  = 'Mutasi Aset IT';
$active_menu = 'mutasi_aset';

// ── Buat tabel mutasi jika belum ada ────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mutasi_aset (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            no_mutasi        VARCHAR(30)  NOT NULL UNIQUE,
            aset_id          INT UNSIGNED NOT NULL,
            tanggal_mutasi   DATE         NOT NULL,
            jenis            ENUM('pindah_lokasi','pindah_pic','keduanya') NOT NULL DEFAULT 'keduanya',

            -- PEMBERI (asal)
            dari_bagian_id   INT UNSIGNED DEFAULT NULL,
            dari_bagian_nama VARCHAR(100) DEFAULT NULL,
            dari_pic_id      INT UNSIGNED DEFAULT NULL,
            dari_pic_nama    VARCHAR(100) DEFAULT NULL,

            -- PENERIMA (tujuan)
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

// ── AJAX: get aset data ────────────────────────────────────────────────────
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

// ── AJAX: search aset ─────────────────────────────────────────────────────
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

// ── AJAX: preview no mutasi ────────────────────────────────────────────────
if (isset($_GET['preview_no_mut'])) {
    header('Content-Type: application/json');
    echo json_encode(['no' => generateNoMutasi($pdo)]);
    exit;
}

// ── POST: simpan mutasi ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'simpan') {
    $aset_id        = (int)($_POST['aset_id']       ?? 0);
    $tgl            = $_POST['tanggal_mutasi']       ?? date('Y-m-d');
    $jenis          = $_POST['jenis']                ?? 'keduanya';
    $ke_bagian_id   = (int)($_POST['ke_bagian_id']   ?? 0) ?: null;
    $ke_pic_id      = (int)($_POST['ke_pic_id']      ?? 0) ?: null;
    $kondisi_sesudah= trim($_POST['kondisi_sesudah'] ?? '');
    $status_pakai   = trim($_POST['status_pakai']    ?? 'Terpakai');
    $keterangan     = trim($_POST['keterangan']      ?? '');

    if (!$aset_id) {
        setFlash('danger', 'Pilih aset terlebih dahulu.');
        redirect(APP_URL . '/pages/mutasi_aset.php');
    }

    // Ambil data aset saat ini (posisi SEBELUM mutasi)
    $aset = $pdo->prepare("SELECT a.*, b.nama AS bagian_nama, u.nama AS pj_nama_db FROM aset_it a LEFT JOIN bagian b ON b.id=a.bagian_id LEFT JOIN users u ON u.id=a.pj_user_id WHERE a.id=?");
    $aset->execute([$aset_id]);
    $aset = $aset->fetch();

    if (!$aset) {
        setFlash('danger', 'Aset tidak ditemukan.');
        redirect(APP_URL . '/pages/mutasi_aset.php');
    }

    // Resolve nama tujuan
    $ke_bagian_nama = '';
    if ($ke_bagian_id) {
        $s = $pdo->prepare("SELECT nama FROM bagian WHERE id=?"); $s->execute([$ke_bagian_id]);
        $ke_bagian_nama = $s->fetchColumn() ?: '';
    }
    $ke_pic_nama = '';
    if ($ke_pic_id) {
        $s = $pdo->prepare("SELECT nama FROM users WHERE id=?"); $s->execute([$ke_pic_id]);
        $ke_pic_nama = $s->fetchColumn() ?: '';
    }

    $no_mutasi       = generateNoMutasi($pdo);
    $kondisi_final   = $kondisi_sesudah ?: $aset['kondisi'];
    $dibuat_nama     = $_SESSION['nama'] ?? '';
    if (!$dibuat_nama) {
        $u = $pdo->prepare("SELECT nama FROM users WHERE id=?"); $u->execute([$_SESSION['user_id']]);
        $dibuat_nama = $u->fetchColumn() ?: '';
    }

    // Simpan mutasi
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
        $aset['bagian_id'], $aset['bagian_nama'], $aset['pj_user_id'], $aset['pj_nama_db'],
        $ke_bagian_id, $ke_bagian_nama, $ke_pic_id, $ke_pic_nama,
        $aset['kondisi'], $kondisi_final, $status_pakai, $keterangan,
        $_SESSION['user_id'], $dibuat_nama,
    ]);

    // Update aset_it
    $update_fields = ['kondisi=?', 'status_pakai=?', 'updated_at=NOW()'];
    $update_params = [$kondisi_final, $status_pakai];

    if ($ke_bagian_id !== null) {
        $update_fields[] = 'bagian_id=?'; $update_params[] = $ke_bagian_id;
        $update_fields[] = 'lokasi=?';    $update_params[] = $ke_bagian_nama;
    }
    if ($ke_pic_id !== null) {
        $update_fields[] = 'pj_user_id=?';       $update_params[] = $ke_pic_id;
        $update_fields[] = 'penanggung_jawab=?';  $update_params[] = $ke_pic_nama;
    }
    $update_params[] = $aset_id;
    $pdo->prepare("UPDATE aset_it SET " . implode(',', $update_fields) . " WHERE id=?")->execute($update_params);

    setFlash('success', "Mutasi <strong>{$no_mutasi}</strong> berhasil disimpan. Aset <strong>" . htmlspecialchars($aset['nama_aset']) . "</strong> telah dipindahkan.");
    redirect(APP_URL . '/pages/mutasi_aset.php');
}

// ── POST: batal mutasi ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'batal') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id && hasRole('admin')) {
        $pdo->prepare("UPDATE mutasi_aset SET status_mutasi='batal' WHERE id=?")->execute([$id]);
        setFlash('warning', 'Mutasi dibatalkan.');
    }
    redirect(APP_URL . '/pages/mutasi_aset.php');
}

// ── Filter & Pagination ────────────────────────────────────────────────────
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
.mut-no { font-family:'Courier New',monospace; font-size:11px; font-weight:700;
          background:linear-gradient(135deg,#fdf4ff,#ede9fe); color:#5b21b6;
          border:1px solid #ddd6fe; padding:2px 8px; border-radius:5px; white-space:nowrap; }

.arrow-mut { display:inline-flex; align-items:center; gap:5px; font-size:11px; }
.arrow-mut .loc-from { color:#64748b; }
.arrow-mut .arr { color:#00c896; font-size:13px; }
.arrow-mut .loc-to { color:#0f172a; font-weight:700; }

.mut-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:9px;font-size:11px;font-weight:700; }
.mb-selesai { background:#d1fae5; color:#065f46; }
.mb-draft   { background:#fef3c7; color:#92400e; }
.mb-batal   { background:#fee2e2; color:#991b1b; }

.pihak-box { display:flex; gap:0; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.pihak-pemberi { flex:1; padding:11px 14px; background:linear-gradient(135deg,#fff7ed,#fef3c7); border-right:1px solid #e2e8f0; }
.pihak-penerima { flex:1; padding:11px 14px; background:linear-gradient(135deg,#f0fdf4,#d1fae5); }
.pihak-label { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.8px; margin-bottom:5px; }
.pihak-pemberi  .pihak-label { color:#92400e; }
.pihak-penerima .pihak-label { color:#065f46; }
.pihak-icon { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
.pihak-pemberi  .pihak-icon { background:#fed7aa; color:#c2410c; }
.pihak-penerima .pihak-icon { background:#a7f3d0; color:#047857; }

.mut-mid-arrow {
    display:flex; align-items:center; justify-content:center;
    width:36px; background:#f8fafc; flex-shrink:0;
    border-left:1px dashed #e2e8f0; border-right:1px dashed #e2e8f0;
}

/* Search aset dropdown */
.aset-search-wrap { position:relative; }
.aset-suggestions {
    display:none; position:fixed;
    background:#fff; border:1px solid #d1d5db;
    border-radius:8px; max-height:260px; overflow-y:auto;
    z-index:99999; box-shadow:0 8px 28px rgba(0,0,0,.18);
    min-width:200px;
}
.aset-suggestions.show { display:block; }
.aset-sug-item {
    padding:9px 13px; cursor:pointer; border-bottom:1px solid #f8fafc;
    transition:background .12s; display:flex; align-items:center; gap:10px;
}
.aset-sug-item:hover { background:#f0fdf9; }
.aset-sug-item:last-child { border-bottom:none; }
.aset-sug-inv { font-family:monospace; font-size:10.5px; font-weight:700; background:#eff6ff; color:#1e40af; padding:1px 6px; border-radius:3px; flex-shrink:0; }

/* Aset terpilih preview card */
.aset-selected-card {
    display:none; background:linear-gradient(135deg,#f0fdf9,#e6fdf6);
    border:1.5px solid #6ee7b7; border-radius:10px; padding:12px 14px;
    margin-top:8px; position:relative;
}
.aset-selected-card.show { display:block; }

/* Kondisi badge warna */
.kb-baik      { background:#dcfce7; color:#166534; }
.kb-rusak     { background:#fee2e2; color:#991b1b; }
.kb-perbaikan { background:#fef9c3; color:#854d0e; }
.kb-tidak     { background:#f1f5f9; color:#64748b; }

.f-label { font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px; }
.f-inp { width:100%; padding:8px 11px; border:1px solid #d1d5db; border-radius:6px; font-size:12.5px; box-sizing:border-box; font-family:inherit; transition:border .18s; }
.f-inp:focus { outline:none; border-color:#00c896; box-shadow:0 0 0 3px rgba(0,200,150,.1); }

.section-title {
    font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px;
    color:#94a3b8; margin:16px 0 10px; display:flex; align-items:center; gap:7px;
}
.section-title::after { content:''; flex:1; height:1px; background:#f1f5f9; }
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
      [$total_all,          'Total Mutasi',   'fa-right-left',  '#f5f3ff','#7c3aed'],
      [$stats['selesai']??0,'Selesai',         'fa-circle-check','#d1fae5','#059669'],
      [$stats['draft']??0,  'Draft',           'fa-clock',       '#fef9c3','#d97706'],
      [$stats['batal']??0,  'Dibatalkan',      'fa-ban',         '#fee2e2','#dc2626'],
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

  <div style="display:grid;grid-template-columns:1fr 400px;gap:16px;align-items:start;">

    <!-- ══ TABEL RIWAYAT MUTASI ══ -->
    <div class="panel">
      <div class="panel-hd">
        <h5><i class="fa fa-list text-primary"></i> Riwayat Mutasi <span style="color:#aaa;font-weight:400;">(<?= $total ?>)</span></h5>
        <button onclick="openModal('m-mutasi')" class="btn btn-primary btn-sm">
          <i class="fa fa-plus"></i> Buat Mutasi
        </button>
      </div>

      <!-- Toolbar filter -->
      <div style="padding:10px 14px;border-bottom:1px solid #f0f0f0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <form method="GET" id="sf-mut" style="display:flex;gap:7px;align-items:center;flex-wrap:wrap;">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="inp-search" placeholder="Cari no. mutasi, aset, PIC…" onchange="document.getElementById('sf-mut').submit()">
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
              <th style="width:60px;">Aksi</th>
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
                <?php if ($m['kategori']): ?><span style="font-size:10px;background:#f1f5f9;color:#64748b;padding:1px 6px;border-radius:3px;"><?= htmlspecialchars($m['kategori']) ?></span><?php endif; ?>
              </td>
              <td style="font-size:11.5px;color:#374151;white-space:nowrap;"><?= date('d M Y', strtotime($m['tanggal_mutasi'])) ?></td>
              <td>
                <!-- Pemberi -->
                <div style="font-size:11px;color:#92400e;margin-bottom:3px;">
                  <i class="fa fa-arrow-up-from-bracket" style="font-size:9px;"></i>
                  <strong>Dari:</strong>
                  <?= htmlspecialchars($m['dari_bagian_nama'] ?: '—') ?>
                  <?php if ($m['dari_pic_nama']): ?>
                  <span style="color:#94a3b8;"> · <?= htmlspecialchars($m['dari_pic_nama']) ?></span>
                  <?php endif; ?>
                </div>
                <!-- Pemisah -->
                <div style="display:flex;align-items:center;gap:4px;margin:3px 0;">
                  <div style="flex:1;height:1px;background:linear-gradient(90deg,#fde68a,#00c896);"></div>
                  <i class="fa fa-arrow-down" style="color:#00c896;font-size:9px;"></i>
                  <div style="flex:1;height:1px;background:linear-gradient(90deg,#00c896,#a7f3d0);"></div>
                </div>
                <!-- Penerima -->
                <div style="font-size:11px;color:#065f46;">
                  <i class="fa fa-arrow-down-to-bracket" style="font-size:9px;"></i>
                  <strong>Ke:</strong>
                  <?= htmlspecialchars($m['ke_bagian_nama'] ?: '—') ?>
                  <?php if ($m['ke_pic_nama']): ?>
                  <span style="color:#94a3b8;"> · <?= htmlspecialchars($m['ke_pic_nama']) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <?php if ($m['kondisi_sesudah']): ?>
                <span class="mut-badge <?= $kc_ses ?>"><?= htmlspecialchars($m['kondisi_sesudah']) ?></span>
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
                <div style="display:flex;gap:3px;align-items:center;">
                  <button onclick="detailMutasi(<?= htmlspecialchars(json_encode($m)) ?>)"
                    class="btn btn-default btn-sm" title="Detail">
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

    <!-- ══ PANEL INFO ══ -->
    <div>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-circle-info text-primary"></i> Tentang Mutasi Aset</h5></div>
        <div class="panel-bd">
          <div style="font-size:12.5px;color:#374151;line-height:1.7;">
            Mutasi aset mencatat <strong>perpindahan aset IT</strong> dari satu lokasi atau penanggung jawab ke yang lain. Setiap mutasi otomatis mengupdate data aset.
          </div>
          <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
            <?php foreach ([
              ['fa-right-left','#7c3aed','#f5f3ff','Pindah Lokasi + PIC','Pindah ke bagian dan PJ baru sekaligus'],
              ['fa-building',  '#1d4ed8','#eff6ff','Pindah Lokasi Saja', 'Hanya pindah ruangan/bagian'],
              ['fa-user',      '#059669','#f0fdf4','Pindah PIC Saja',    'Hanya ganti penanggung jawab'],
            ] as [$ico,$clr,$bg,$t,$s]): ?>
            <div style="display:flex;align-items:flex-start;gap:9px;padding:8px 10px;background:<?= $bg ?>;border-radius:7px;">
              <i class="fa <?= $ico ?>" style="color:<?= $clr ?>;margin-top:2px;font-size:13px;flex-shrink:0;"></i>
              <div><div style="font-size:12px;font-weight:700;color:#1e293b;"><?= $t ?></div><div style="font-size:11px;color:#64748b;"><?= $s ?></div></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Mutasi terakhir ringkas -->
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
              <div style="font-size:10.5px;color:#94a3b8;"><?= htmlspecialchars($m['ke_bagian_nama'] ?: ($m['ke_pic_nama'] ?: '—')) ?> · <?= date('d M', strtotime($m['tanggal_mutasi'])) ?></div>
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
     MODAL BUAT MUTASI
══════════════════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-mutasi" style="align-items:flex-start;justify-content:center;padding-top:24px;">
  <div style="background:#fff;width:100%;max-width:640px;border-radius:12px;
              box-shadow:0 24px 64px rgba(0,0,0,.22);overflow:hidden;animation:mIn .2s ease;">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;
                padding:15px 20px;background:linear-gradient(135deg,#1a0a2e,#2a1a4e);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;background:rgba(124,58,237,.3);border:1px solid rgba(167,139,250,.4);border-radius:8px;display:flex;align-items:center;justify-content:center;">
          <i class="fa fa-right-left" style="color:#a78bfa;font-size:14px;"></i>
        </div>
        <div>
          <div style="color:#fff;font-size:14px;font-weight:700;">Buat Mutasi Aset</div>
          <div style="color:rgba(255,255,255,.4);font-size:10.5px;">Catat perpindahan aset IT</div>
        </div>
      </div>
      <button onclick="closeModal('m-mutasi')"
        style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:13px;display:flex;align-items:center;justify-content:center;"
        onmouseover="this.style.background='#ef4444';" onmouseout="this.style.background='rgba(255,255,255,.1)';">
        <i class="fa fa-times"></i>
      </button>
    </div>

    <form method="POST" id="form-mutasi">
      <input type="hidden" name="_action" value="simpan">
      <input type="hidden" name="aset_id" id="m-aset-id" value="">

      <div style="padding:18px 20px;max-height:74vh;overflow-y:auto;">

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
            style="position:absolute;top:8px;right:8px;width:20px;height:20px;border-radius:50%;
                   background:rgba(0,0,0,.08);border:none;cursor:pointer;font-size:10px;
                   display:flex;align-items:center;justify-content:center;color:#64748b;">✕</button>
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
          <!-- Posisi saat ini (PEMBERI) -->
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

        <!-- ② Info Mutasi -->
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
              <option value="pindah_lokasi">🏢 Pindah Lokasi Saja</option>
              <option value="pindah_pic">👤 Pindah PIC Saja</option>
            </select>
          </div>
        </div>

        <!-- ③ Penerima -->
        <div style="background:linear-gradient(135deg,#f0fdf4,#d1fae5);border:1.5px solid #6ee7b7;border-radius:10px;padding:13px 14px;margin-bottom:12px;">
          <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#065f46;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
            <i class="fa fa-arrow-down-to-bracket"></i> Penerima (Tujuan)
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div id="wrap-bagian">
              <label class="f-label">Lokasi / Bagian Tujuan</label>
              <select name="ke_bagian_id" id="m-ke-bagian" class="f-inp">
                <option value="">— Pilih Lokasi —</option>
                <?php foreach ($bagian_list as $b): ?>
                <option value="<?= $b['id'] ?>"><?= ($b['kode']?'['.$b['kode'].'] ':'').htmlspecialchars($b['nama']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div id="wrap-pic">
              <label class="f-label">Penanggung Jawab Baru</label>
              <select name="ke_pic_id" id="m-ke-pic" class="f-inp">
                <option value="">— Pilih PIC —</option>
                <?php foreach ($users_list as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama']) ?><?= $u['divisi'] ? ' — '.$u['divisi'] : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- ④ Kondisi & Status setelah mutasi -->
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
            placeholder="Contoh: Rotasi perangkat antar ruangan, perbaikan selesai, dll…"
            style="resize:vertical;"></textarea>
        </div>

      </div><!-- /scroll body -->

      <!-- Footer -->
      <div style="padding:12px 20px;border-top:1px solid #f0f0f0;background:#f8fafc;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:11px;color:#94a3b8;"><i class="fa fa-circle-info" style="color:#7c3aed;"></i> Data aset akan otomatis diperbarui</span>
        <div style="display:flex;gap:8px;">
          <button type="button" onclick="closeModal('m-mutasi')" class="btn btn-default">Batal</button>
          <button type="submit" class="btn btn-primary" id="btn-simpan-mut" disabled>
            <i class="fa fa-save"></i> Simpan Mutasi
          </button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL DETAIL MUTASI
══════════════════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-detail" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:520px;border-radius:12px;
              box-shadow:0 24px 64px rgba(0,0,0,.2);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:14px 18px;background:linear-gradient(135deg,#1a0a2e,#2a1a4e);display:flex;align-items:center;justify-content:space-between;">
      <div style="color:#fff;font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:8px;">
        <i class="fa fa-right-left" style="color:#a78bfa;"></i>
        Detail Mutasi — <span id="d-no" style="font-family:monospace;font-size:12px;color:#a78bfa;"></span>
      </div>
      <button onclick="closeModal('m-detail')" style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:12px;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='#ef4444';" onmouseout="this.style.background='rgba(255,255,255,.1)';"><i class="fa fa-times"></i></button>
    </div>
    <div style="padding:18px 20px;" id="d-body"></div>
    <div style="padding:10px 20px;border-top:1px solid #f0f0f0;background:#f8fafc;display:flex;justify-content:flex-end;">
      <button onclick="closeModal('m-detail')" class="btn btn-default btn-sm">Tutup</button>
    </div>
  </div>
</div>


<!-- JS -->
<script>
const APP_URL = '<?= APP_URL ?>';
let _searchTimer;

/* ── Posisikan suggestion dropdown tepat di bawah input (fixed, agar tidak terpotong modal overflow) ── */
function positionSuggestions() {
    const inp = document.getElementById("aset-search-inp");
    const sug = document.getElementById("aset-suggestions");
    if (!inp || !sug) return;
    const r = inp.getBoundingClientRect();
    sug.style.top   = (r.bottom + 2) + "px";
    sug.style.left  = r.left + "px";
    sug.style.width = r.width + "px";
}


/* ── Cari Aset ── */
function cariAset(q) {
    clearTimeout(_searchTimer);
    const sug = document.getElementById('aset-suggestions');
    if (q.length < 2) { sug.classList.remove('show'); return; }
    positionSuggestions();
    _searchTimer = setTimeout(() => {
        fetch(APP_URL + '/pages/mutasi_aset.php?search_aset=1&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                sug.innerHTML = '';
                if (!data.length) {
                    sug.innerHTML = '<div style="padding:10px 13px;font-size:12px;color:#94a3b8;"><i class="fa fa-inbox"></i> Tidak ditemukan</div>';
                } else {
                    data.forEach(a => {
                        const el = document.createElement('div');
                        el.className = 'aset-sug-item';
                        const kmap = {'Baik':'#16a34a','Rusak':'#dc2626','Dalam Perbaikan':'#d97706','Tidak Aktif':'#64748b'};
                        el.innerHTML = `
                            <span class="aset-sug-inv">${escH(a.no_inventaris)}</span>
                            <div style="flex:1;">
                                <div style="font-size:12.5px;font-weight:600;color:#1e293b;">${escH(a.nama_aset)}</div>
                                <div style="font-size:10.5px;color:#94a3b8;">${escH(a.merek||'')} ${escH(a.kategori||'')} · ${escH(a.bagian_nama||'Tanpa Lokasi')}</div>
                            </div>
                            <span style="font-size:10px;font-weight:700;color:${kmap[a.kondisi]||'#64748b'}">${escH(a.kondisi||'')}</span>`;
                        el.onclick = () => pilihAset(a);
                        sug.appendChild(el);
                    });
                }
                sug.classList.add('show');
            });
    }, 280);
}

/* ── Pilih aset dari suggestions ── */
function pilihAset(a) {
    document.getElementById('m-aset-id').value  = a.id;
    document.getElementById('aset-search-inp').value = a.nama_aset + ' · ' + a.no_inventaris;
    document.getElementById('aset-suggestions').classList.remove('show');

    // Isi preview card
    document.getElementById('ac-nama').textContent = a.nama_aset || '—';
    document.getElementById('ac-inv').textContent  = a.no_inventaris || '—';
    document.getElementById('ac-lokasi').textContent = a.bagian_nama || 'Tanpa Lokasi';
    document.getElementById('ac-pj').textContent   = a.pj_nama_db || 'Tanpa PIC';
    document.getElementById('ac-status').textContent = a.status_pakai || 'Terpakai';

    // Kondisi badge
    const kEl = document.getElementById('ac-kondisi');
    kEl.textContent = a.kondisi || 'Baik';
    const kmap2 = {'Baik':'kb-baik','Rusak':'kb-rusak','Dalam Perbaikan':'kb-perbaikan','Tidak Aktif':'kb-tidak'};
    kEl.className = 'mut-badge ' + (kmap2[a.kondisi] || 'kb-tidak');

    // Set default kondisi sesudah
    document.getElementById('m-kondisi').value = '';

    document.getElementById('aset-card').classList.add('show');
    document.getElementById('btn-simpan-mut').disabled = false;
}

/* ── Clear aset pilihan ── */
function clearAset() {
    document.getElementById('m-aset-id').value = '';
    document.getElementById('aset-search-inp').value = '';
    document.getElementById('aset-card').classList.remove('show');
    document.getElementById('btn-simpan-mut').disabled = true;
}

/* ── Update jenis mutasi (tampilkan/sembunyikan field) ── */
function updateJenis(val) {
    const wBagian = document.getElementById('wrap-bagian');
    const wPic    = document.getElementById('wrap-pic');
    if (val === 'pindah_lokasi') {
        wBagian.style.display = ''; wPic.style.display = 'none';
        document.getElementById('m-ke-pic').value = '';
    } else if (val === 'pindah_pic') {
        wBagian.style.display = 'none'; wPic.style.display = '';
        document.getElementById('m-ke-bagian').value = '';
    } else {
        wBagian.style.display = ''; wPic.style.display = '';
    }
}

/* ── Tutup suggestions saat klik di luar ── */
document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.aset-search-wrap');
    if (wrap && !wrap.contains(e.target))
        document.getElementById('aset-suggestions').classList.remove('show');
});

/* Reposisi dropdown saat scroll atau resize (agar tidak bergeser) */
window.addEventListener("scroll", positionSuggestions, true);
window.addEventListener("resize", function() {
    document.getElementById("aset-suggestions").classList.remove("show");
});

/* ── Detail mutasi ── */
function detailMutasi(m) {
    document.getElementById('d-no').textContent = m.no_mutasi;

    const kmap3 = {'Baik':'kb-baik','Rusak':'kb-rusak','Dalam Perbaikan':'kb-perbaikan','Tidak Aktif':'kb-tidak'};
    const skmap = {'selesai':'mb-selesai','draft':'mb-draft','batal':'mb-batal'};

    document.getElementById('d-body').innerHTML = `
        <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
            <span class="mut-badge ${skmap[m.status_mutasi]||'mb-selesai'}">${ucFirst(m.status_mutasi)}</span>
            <span style="font-size:11.5px;color:#64748b;"><i class="fa fa-calendar" style="font-size:10px;"></i> ${fmtDate(m.tanggal_mutasi)}</span>
            <span style="font-size:11.5px;color:#7c3aed;font-weight:600;">${jenisMut(m.jenis)}</span>
        </div>

        <div style="margin-bottom:12px;padding:10px 12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
            <div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;">Aset</div>
            <div style="font-size:13px;font-weight:700;color:#1e293b;">${escH(m.nama_aset||'—')}</div>
            <div style="font-family:monospace;font-size:10.5px;color:#1e40af;">${escH(m.no_inventaris||'')}</div>
        </div>

        <div class="pihak-box">
            <div class="pihak-pemberi">
                <div class="pihak-label"><i class="fa fa-arrow-up-from-bracket"></i> Pemberi (Asal)</div>
                <div style="display:flex;align-items:center;gap:7px;">
                    <div class="pihak-icon"><i class="fa fa-building"></i></div>
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#1e293b;">${escH(m.dari_bagian_nama||'—')}</div>
                        <div style="font-size:10.5px;color:#92400e;">${escH(m.dari_pic_nama||'—')}</div>
                    </div>
                </div>
            </div>
            <div class="mut-mid-arrow"><i class="fa fa-right-left" style="color:#00c896;font-size:12px;transform:rotate(90deg);"></i></div>
            <div class="pihak-penerima">
                <div class="pihak-label"><i class="fa fa-arrow-down-to-bracket"></i> Penerima (Tujuan)</div>
                <div style="display:flex;align-items:center;gap:7px;">
                    <div class="pihak-icon"><i class="fa fa-building"></i></div>
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#1e293b;">${escH(m.ke_bagian_nama||'—')}</div>
                        <div style="font-size:10.5px;color:#065f46;">${escH(m.ke_pic_nama||'—')}</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
            ${m.kondisi_sesudah ? `<span class="mut-badge ${kmap3[m.kondisi_sesudah]||'kb-tidak'}">Kondisi: ${escH(m.kondisi_sesudah)}</span>` : ''}
            ${m.status_pakai ? `<span style="font-size:11px;background:#dbeafe;color:#1e40af;padding:2px 9px;border-radius:9px;font-weight:700;">${escH(m.status_pakai)}</span>` : ''}
        </div>

        ${m.keterangan ? `<div style="margin-top:10px;padding:9px 12px;background:#f8fafc;border-radius:7px;border-left:3px solid #7c3aed;font-size:12px;color:#374151;">"${escH(m.keterangan)}"</div>` : ''}

        <div style="margin-top:10px;font-size:10.5px;color:#94a3b8;">
            <i class="fa fa-user" style="font-size:9px;"></i> Dibuat oleh ${escH(m.dibuat_nama||'—')} · ${fmtDatetime(m.created_at)}
        </div>`;

    openModal('m-detail');
}

/* ── Helpers ── */
function escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function ucFirst(s) { return s ? s.charAt(0).toUpperCase()+s.slice(1) : ''; }
function fmtDate(d) { if (!d) return '—'; const dt=new Date(d); return dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}); }
function fmtDatetime(d) { if (!d) return '—'; const dt=new Date(d); return dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'})+' '+dt.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'}); }
function jenisMut(j) { return j==='pindah_lokasi'?'Pindah Lokasi':j==='pindah_pic'?'Pindah PIC':'Pindah Lokasi + PIC'; }
</script>

<?php include '../includes/footer.php'; ?>