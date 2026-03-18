<?php
// pages/data_dokumen.php
session_start();
require_once '../config.php';
requireLogin();
// Cek akses: admin, role akreditasi, ATAU flag is_akreditasi=1
if (!hasRole(['admin', 'akreditasi']) && (int)($_SESSION['is_akreditasi'] ?? 0) !== 1) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}
$page_title  = 'Data Dokumen Akreditasi';
$active_menu = 'data_dokumen';

$_cur_role = $_SESSION['user_role'] ?? 'user';
$_uid      = (int)($_SESSION['user_id'] ?? 0);
$_is_admin = ($_cur_role === 'admin');
$_pokja_id = (int)($_SESSION['pokja_id'] ?? 0);

// ── Filter ───────────────────────────────────────────────────────────────────
$f_pokja  = (int)($_GET['pokja_id'] ?? 0);
$f_status = trim($_GET['status']    ?? '');
$f_kat    = trim($_GET['kategori']  ?? '');
$f_cari   = trim($_GET['cari']      ?? '');

// Non-admin hanya bisa lihat Pokja-nya sendiri
if (!$_is_admin) $f_pokja = $_pokja_id;

// ── POST Handler (edit, hapus) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'edit') {
        $id        = (int)($_POST['id']         ?? 0);
        $judul            = trim($_POST['judul']            ?? '');
        $nomor            = trim($_POST['nomor_doc']        ?? '');
        $kat              = trim($_POST['kategori']         ?? '');
        $elemen_penilaian = trim($_POST['elemen_penilaian'] ?? '');
        $ket              = trim($_POST['keterangan']       ?? '');
        // Status & tanggal tidak ada di form edit — ambil dari DB agar tidak overwrite
        $existing = $pdo->prepare("SELECT status, tgl_terbit, tgl_exp FROM dokumen_akreditasi WHERE id=?");
        $existing->execute([$id]);
        $existing_data = $existing->fetch() ?: [];
        $status     = $existing_data['status']     ?? 'aktif';
        $tgl_terbit = $existing_data['tgl_terbit'] ?? null;
        $tgl_exp    = $existing_data['tgl_exp']    ?? null;
        $pokja_id_edit = (int)($_POST['pokja_id'] ?? 0);

        // Keamanan: non-admin hanya bisa edit dokumen Pokja-nya
        if (!$_is_admin) {
            $cek = $pdo->prepare("SELECT pokja_id FROM dokumen_akreditasi WHERE id=?");
            $cek->execute([$id]);
            $row_pokja = (int)$cek->fetchColumn();
            if ($row_pokja !== $_pokja_id) {
                setFlash('danger', 'Anda tidak berwenang mengedit dokumen ini.');
                redirect(APP_URL . '/pages/data_dokumen.php');
            }
            $pokja_id_edit = $_pokja_id; // paksa pokja sendiri
        }

        if ($id && $judul && $pokja_id_edit) {
            // Cek ada file baru?
            $upload_dir = dirname(__DIR__) . '/uploads/akreditasi/';
            $set_file   = '';
            $params     = [];

            if (!empty($_FILES['file']['name'])) {
                $orig    = $_FILES['file']['name'];
                $tmp     = $_FILES['file']['tmp_name'];
                $size    = $_FILES['file']['size'];
                $ext     = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','zip'];
                if (!in_array($ext, $allowed)) {
                    setFlash('danger', 'Tipe file tidak diizinkan.');
                    redirect(APP_URL . '/pages/data_dokumen.php');
                }
                if ($size > 20 * 1024 * 1024) {
                    setFlash('danger', 'Ukuran file maksimal 20 MB.');
                    redirect(APP_URL . '/pages/data_dokumen.php');
                }
                $safe_name = date('Ymd_His') . '_' . $pokja_id_edit . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
                if (move_uploaded_file($tmp, $upload_dir . $safe_name)) {
                    // Hapus file lama
                    $old = $pdo->prepare("SELECT file_path FROM dokumen_akreditasi WHERE id=?");
                    $old->execute([$id]);
                    $old_path = $old->fetchColumn();
                    if ($old_path && file_exists(dirname(__DIR__) . '/' . $old_path)) {
                        @unlink(dirname(__DIR__) . '/' . $old_path);
                    }
                    $set_file = ', file_path=?, file_name=?, file_size=?';
                    $params   = ['uploads/akreditasi/' . $safe_name, $orig, $size];
                }
            }

            $sql    = "UPDATE dokumen_akreditasi SET pokja_id=?,judul=?,nomor_doc=?,kategori=?,elemen_penilaian=?,keterangan=?,status=?,tgl_terbit=?,tgl_exp=?$set_file WHERE id=?";
            $values = [$pokja_id_edit, $judul, $nomor ?: null, $kat ?: null,
                       $elemen_penilaian ?: null, $ket ?: null,
                       $status, $tgl_terbit, $tgl_exp, ...$params, $id];
            $pdo->prepare($sql)->execute($values);
            setFlash('success', "Dokumen <strong>" . clean($judul) . "</strong> berhasil diperbarui.");
        } else {
            setFlash('danger', 'Data tidak lengkap.');
        }
    }

    elseif ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$_is_admin) {
            $cek = $pdo->prepare("SELECT pokja_id FROM dokumen_akreditasi WHERE id=?");
            $cek->execute([$id]);
            if ((int)$cek->fetchColumn() !== $_pokja_id) {
                setFlash('danger', 'Anda tidak berwenang menghapus dokumen ini.');
                redirect(APP_URL . '/pages/data_dokumen.php');
            }
        }
        // Hapus file fisik
        $f = $pdo->prepare("SELECT file_path FROM dokumen_akreditasi WHERE id=?");
        $f->execute([$id]);
        $fp = $f->fetchColumn();
        if ($fp && file_exists(dirname(__DIR__) . '/' . $fp)) @unlink(dirname(__DIR__) . '/' . $fp);
        $pdo->prepare("DELETE FROM dokumen_akreditasi WHERE id=?")->execute([$id]);
        setFlash('success', 'Dokumen berhasil dihapus.');
    }

    redirect(APP_URL . '/pages/data_dokumen.php?' . http_build_query([
        'pokja_id' => $f_pokja, 'status' => $f_status,
        'kategori' => $f_kat,   'cari'   => $f_cari,
    ]));
}

// ── Build query ───────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($f_pokja) {
    $where[]  = 'd.pokja_id = ?';
    $params[] = $f_pokja;
}
if ($f_status) {
    $where[]  = 'd.status = ?';
    $params[] = $f_status;
}
if ($f_kat) {
    $where[]  = 'd.kategori = ?';
    $params[] = $f_kat;
}
if ($f_cari) {
    $where[]  = '(d.judul LIKE ? OR d.nomor_doc LIKE ?)';
    $params[] = "%$f_cari%";
    $params[] = "%$f_cari%";
}

$sql = "
    SELECT d.*,
           p.kode  AS pokja_kode,
           p.nama  AS pokja_nama,
           u.nama  AS uploader_nama
    FROM dokumen_akreditasi d
    LEFT JOIN master_pokja p ON p.id = d.pokja_id
    LEFT JOIN users        u ON u.id = d.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY d.created_at DESC
";
$st = $pdo->prepare($sql);
$st->execute($params);
$dokumens = $st->fetchAll();

// ── Data pendukung untuk filter & modal ──────────────────────────────────────
$pokja_list = [];
try {
    if ($_is_admin) {
        $pokja_list = $pdo->query("SELECT id,kode,nama FROM master_pokja WHERE status='aktif' ORDER BY urutan,kode")->fetchAll();
    } else {
        $ps = $pdo->prepare("SELECT id,kode,nama FROM master_pokja WHERE id=? AND status='aktif'");
        $ps->execute([$_pokja_id]);
        $pokja_list = $ps->fetchAll();
    }
} catch (Exception $e) {}

$kategori_list = ['Kebijakan','Pedoman','Panduan','SPO','Program Kerja',
                  'Laporan','Sertifikat','SK / Surat Keputusan',
                  'Notulen / Risalah','Formulir','Lain-lain'];

// ── Statistik ringkas ─────────────────────────────────────────────────────────
$stat_where  = $_is_admin ? '' : 'WHERE pokja_id=' . $_pokja_id;
$stat_aktif  = 0; $stat_draft = 0; $stat_exp = 0; $stat_total = 0;
try {
    $rows = $pdo->query("SELECT status, COUNT(*) n FROM dokumen_akreditasi $stat_where GROUP BY status")->fetchAll();
    foreach ($rows as $r) {
        $stat_total += $r['n'];
        if ($r['status'] === 'aktif')      $stat_aktif += $r['n'];
        if ($r['status'] === 'draft')      $stat_draft += $r['n'];
        if ($r['status'] === 'kadaluarsa') $stat_exp   += $r['n'];
    }
} catch (Exception $e) {}

// Dokumen akan segera exp (30 hari ke depan)
$soon_exp = 0;
try {
    $se = $pdo->prepare("SELECT COUNT(*) FROM dokumen_akreditasi
        WHERE status='aktif' AND tgl_exp IS NOT NULL
        AND tgl_exp BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)
        " . (!$_is_admin ? "AND pokja_id=$_pokja_id" : ""));
    $se->execute();
    $soon_exp = (int)$se->fetchColumn();
} catch (Exception $e) {}

// Ext → icon & warna
function fileIcon(string $path): array {
    $ext = strtolower(pathinfo($path ?? '', PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'             => ['fa-file-pdf',       '#ef4444'],
        'doc','docx'      => ['fa-file-word',      '#1d4ed8'],
        'xls','xlsx'      => ['fa-file-excel',     '#065f46'],
        'ppt','pptx'      => ['fa-file-powerpoint','#ea580c'],
        'jpg','jpeg','png'=> ['fa-file-image',     '#7c3aed'],
        'zip'             => ['fa-file-zipper',    '#6b7280'],
        default           => ['fa-file',           '#94a3b8'],
    };
}

include '../includes/header.php';
?>

<style>
.doc-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  transition: box-shadow .15s, border-color .15s;
  display: flex;
  flex-direction: column;
  gap: 10px;
  height: 100%;
}
.doc-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,.09);
  border-color: #d1d5db;
}
.doc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 14px;
}
.status-badge {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 11px; font-weight: 700;
  padding: 2px 9px; border-radius: 20px;
}
.badge-aktif      { background:#d1fae5; color:#065f46; }
.badge-draft      { background:#e0f2fe; color:#0369a1; }
.badge-kadaluarsa { background:#fee2e2; color:#991b1b; }
.kat-badge {
  display: inline-block;
  font-size: 10px; font-weight: 600;
  padding: 2px 8px; border-radius: 4px;
  background: #f1f5f9; color: #475569;
}
.filter-bar {
  display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;
  margin-bottom: 16px;
}
.filter-bar select,
.filter-bar input[type=text] {
  height: 36px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 0 10px;
  font-size: 12.5px;
  font-family: inherit;
  background: #fff;
  color: #374151;
  min-width: 140px;
}
.filter-bar input[type=text] { min-width: 200px; }
.view-btn {
  background: none; border: 1px solid #e5e7eb;
  border-radius: 7px; width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: #6b7280;
  transition: all .15s;
}
.view-btn.active, .view-btn:hover { background: #00e5b0; color: #0a0f14; border-color: #00e5b0; }
</style>

<div class="page-header">
  <h4><i class="fa fa-folder-open text-primary"></i> &nbsp;Data Dokumen Akreditasi</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Data Dokumen</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Statistik -->
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach ([
      [$stat_total, 'Total Dokumen',  'fa-folder-open',   '#f3f4f6','#374151'],
      [$stat_aktif, 'Aktif',          'fa-circle-check',  '#d1fae5','#065f46'],
    ] as [$val,$lbl,$ic,$bg,$col]):
    ?>
    <div style="background:#fff;border:1px solid #f0f0f0;border-radius:8px;padding:10px 16px;
                display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.05);min-width:120px;">
      <div style="width:36px;height:36px;border-radius:50%;background:<?= $bg ?>;
                  display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa <?= $ic ?>" style="color:<?= $col ?>;font-size:14px;"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;color:#1e293b;line-height:1;"><?= $val ?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:1px;"><?= $lbl ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filter -->
  <form method="GET" action="">
    <div class="filter-bar">
      <input type="text" name="cari" value="<?= clean($f_cari) ?>" placeholder="&#xf002; Cari judul / nomor…">

      <?php if ($_is_admin): ?>
      <select name="pokja_id">
        <option value="">Semua Pokja</option>
        <?php foreach ($pokja_list as $pk): ?>
        <option value="<?= (int)$pk['id'] ?>" <?= $f_pokja == $pk['id'] ? 'selected' : '' ?>>
          <?= clean($pk['kode']) ?> — <?= clean($pk['nama']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>

      <select name="kategori">
        <option value="">Semua Kategori</option>
        <?php foreach ($kategori_list as $k): ?>
        <option value="<?= $k ?>" <?= $f_kat === $k ? 'selected' : '' ?>><?= $k ?></option>
        <?php endforeach; ?>
      </select>

 

      <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Filter</button>
      <a href="<?= APP_URL ?>/pages/data_dokumen.php" class="btn btn-default btn-sm">
        <i class="fa fa-rotate-left"></i> Reset
      </a>

      <div style="margin-left:auto;display:flex;gap:5px;">
        <button type="button" class="view-btn active" id="btn-grid" onclick="setView('grid')" title="Grid">
          <i class="fa fa-grip"></i>
        </button>
        <button type="button" class="view-btn" id="btn-list" onclick="setView('list')" title="List">
          <i class="fa fa-list"></i>
        </button>
      </div>

      <a href="<?= APP_URL ?>/pages/input_dokumen.php" class="btn btn-primary btn-sm">
        <i class="fa fa-plus"></i> Input Dokumen
      </a>
    </div>
  </form>

  <!-- GRID VIEW -->
  <div id="view-grid">
    <?php if (empty($dokumens)): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:48px;
                text-align:center;color:#94a3b8;">
      <i class="fa fa-folder-open" style="font-size:40px;margin-bottom:12px;display:block;"></i>
      <div style="font-size:14px;font-weight:600;">Belum ada dokumen</div>
      <div style="font-size:12px;margin-top:4px;">
        <?= $f_cari || $f_status || $f_kat || $f_pokja
          ? 'Coba ubah filter pencarian.'
          : 'Klik <strong>Input Dokumen</strong> untuk menambahkan.' ?>
      </div>
    </div>
    <?php else: ?>
    <div class="doc-grid">
      <?php foreach ($dokumens as $d):
        [$fic, $fcol] = fileIcon($d['file_path'] ?? '');
        $is_mine = ((int)$d['user_id'] === $_uid) || $_is_admin;
        $near_exp = !empty($d['tgl_exp'])
                    && strtotime($d['tgl_exp']) < strtotime('+30 days')
                    && $d['status'] === 'aktif';
      ?>
      <div class="doc-card">
        <!-- Header -->
        <div style="display:flex;align-items:flex-start;gap:10px;">
          <div style="width:40px;height:40px;border-radius:9px;background:#f8fafc;
                      border:1.5px solid #e5e7eb;display:flex;align-items:center;
                      justify-content:center;flex-shrink:0;">
            <i class="fa <?= $fic ?>" style="color:<?= $fcol ?>;font-size:18px;"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:700;color:#111827;
                        overflow:hidden;text-overflow:ellipsis;
                        display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
              <?= clean($d['judul']) ?>
            </div>
            <?php if (!empty($d['nomor_doc'])): ?>
            <div style="font-size:10.5px;color:#9ca3af;margin-top:2px;font-family:monospace;">
              <?= clean($d['nomor_doc']) ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Badges -->
        <div style="display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
          <span class="status-badge badge-<?= $d['status'] ?>">
            <?= $d['status'] === 'aktif' ? '<span style="width:5px;height:5px;border-radius:50%;background:#22c55e;"></span>' : '' ?>
            <?= ucfirst($d['status']) ?>
          </span>
          <?php if (!empty($d['kategori'])): ?>
          <span class="kat-badge"><?= clean($d['kategori']) ?></span>
          <?php endif; ?>
          <?php if (!empty($d['elemen_penilaian'])): ?>
          <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;
                       background:#ede9fe;color:#6d28d9;font-family:monospace;">
            <i class="fa fa-bullseye" style="font-size:9px;"></i> <?= clean($d['elemen_penilaian']) ?>
          </span>
          <?php endif; ?>
          <?php if ($_is_admin && !empty($d['pokja_kode'])): ?>
          <span style="font-size:10px;font-weight:800;padding:2px 7px;border-radius:4px;
                       background:#fef9c3;color:#854d0e;font-family:monospace;">
            <?= clean($d['pokja_kode']) ?>
          </span>
          <?php endif; ?>
          <?php if ($near_exp): ?>
          <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;
                       background:#fef3c7;color:#92400e;">
            <i class="fa fa-triangle-exclamation"></i> Segera exp.
          </span>
          <?php endif; ?>
        </div>

        <!-- Keterangan -->
        <?php if (!empty($d['keterangan'])): ?>
        <div style="font-size:11.5px;color:#64748b;line-height:1.5;
                    background:#f8fafc;border-left:3px solid #e2e8f0;
                    padding:7px 10px;border-radius:0 6px 6px 0;">
          <?= clean(mb_substr($d['keterangan'], 0, 120)) ?><?= mb_strlen($d['keterangan']) > 120 ? '…' : '' ?>
        </div>
        <?php endif; ?>

        <!-- Meta -->
        <div style="font-size:11px;color:#94a3b8;display:flex;flex-direction:column;gap:3px;">
          <?php if (!empty($d['tgl_terbit'])): ?>
          <span><i class="fa fa-calendar" style="width:12px;"></i>
            Terbit: <?= date('d M Y', strtotime($d['tgl_terbit'])) ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($d['tgl_exp'])): ?>
          <span style="<?= $near_exp ? 'color:#dc2626;font-weight:600;' : '' ?>">
            <i class="fa fa-clock" style="width:12px;"></i>
            Exp: <?= date('d M Y', strtotime($d['tgl_exp'])) ?>
          </span>
          <?php endif; ?>
          <span><i class="fa fa-user" style="width:12px;"></i> <?= clean($d['uploader_nama'] ?? '—') ?></span>
          <span><i class="fa fa-clock" style="width:12px;"></i>
            <?= date('d M Y H:i', strtotime($d['created_at'])) ?>
          </span>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:6px;margin-top:auto;padding-top:8px;border-top:1px solid #f3f4f6;">
          <?php if (!empty($d['file_path'])): ?>
          <a href="<?= APP_URL ?>/<?= clean($d['file_path']) ?>" target="_blank"
             class="btn btn-sm btn-primary" style="flex:1;text-align:center;">
            <i class="fa fa-download"></i> Download
          </a>
          <?php else: ?>
          <span class="btn btn-sm btn-default" style="flex:1;text-align:center;opacity:.4;cursor:default;">
            <i class="fa fa-file-slash"></i> No File
          </span>
          <?php endif; ?>

          <?php if ($is_mine): ?>
          <button class="btn btn-warning btn-sm" title="Edit"
            onclick='editDok(<?= json_encode([
              "id"         => (int)$d["id"],
              "pokja_id"   => (int)$d["pokja_id"],
              "judul"      => $d["judul"],
              "nomor_doc"  => $d["nomor_doc"] ?? "",
              "kategori"   => $d["kategori"]  ?? "",
              "keterangan"       => $d["keterangan"]        ?? "",
              "elemen_penilaian" => $d["elemen_penilaian"] ?? "",
              "status"           => $d["status"],
              "tgl_terbit"       => $d["tgl_terbit"] ?? "",
              "tgl_exp"          => $d["tgl_exp"]    ?? "",
            ]) ?>)'>
            <i class="fa fa-edit"></i>
          </button>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm" title="Hapus"
              onclick="return confirm('Hapus dokumen ini? File juga akan dihapus.')">
              <i class="fa fa-trash"></i>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- LIST VIEW -->
  <div id="view-list" style="display:none;">
    <div class="panel">
      <div class="panel-bd np tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Dokumen</th>
              <?php if ($_is_admin): ?><th>Pokja</th><?php endif; ?>
              <th>Kategori / Elemen</th>
              <th>Status</th>
              <th>Uploader</th>
              <th>Keterangan</th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($dokumens)): ?>
            <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:30px;">
              Belum ada dokumen.
            </td></tr>
            <?php endif; ?>
            <?php $no = 1; foreach ($dokumens as $d):
              [$fic,$fcol] = fileIcon($d['file_path'] ?? '');
              $is_mine = ((int)$d['user_id'] === $_uid) || $_is_admin;
              $near_exp = !empty($d['tgl_exp'])
                          && strtotime($d['tgl_exp']) < strtotime('+30 days')
                          && $d['status'] === 'aktif';
            ?>
            <tr>
              <td style="color:#cbd5e1;"><?= $no++ ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <i class="fa <?= $fic ?>" style="color:<?= $fcol ?>;font-size:16px;flex-shrink:0;"></i>
                  <div>
                    <div style="font-weight:600;color:#1e293b;"><?= clean($d['judul']) ?></div>
                    <?php if (!empty($d['nomor_doc'])): ?>
                    <div style="font-size:10.5px;color:#9ca3af;font-family:monospace;">
                      <?= clean($d['nomor_doc']) ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <?php if ($_is_admin): ?>
              <td>
                <span style="font-size:11px;font-weight:800;padding:2px 7px;border-radius:4px;
                             background:#fef9c3;color:#854d0e;font-family:monospace;">
                  <?= clean($d['pokja_kode'] ?? '—') ?>
                </span>
              </td>
              <?php endif; ?>
              <td style="font-size:12px;">
                <?= !empty($d['kategori']) ? '<span class="kat-badge">' . clean($d['kategori']) . '</span>' : '—' ?>
                <?php if (!empty($d['elemen_penilaian'])): ?>
                <div style="font-size:10.5px;color:#6d28d9;font-family:monospace;margin-top:2px;font-weight:600;">
                  <i class="fa fa-bullseye" style="font-size:9px;"></i> <?= clean($d['elemen_penilaian']) ?>
                </div>
                <?php endif; ?>
              </td>
              <td>
                <span class="status-badge badge-<?= $d['status'] ?>">
                  <?= ucfirst($d['status']) ?>
                </span>
              </td>

              <td style="font-size:12px;color:#475569;"><?= clean($d['uploader_nama'] ?? '—') ?></td>
              <td style="font-size:12px;color:#64748b;max-width:200px;">
                <?php if (!empty($d['keterangan'])): ?>
                <span title="<?= htmlspecialchars($d['keterangan']) ?>">
                  <?= clean(mb_substr($d['keterangan'], 0, 60)) ?><?= mb_strlen($d['keterangan']) > 60 ? '…' : '' ?>
                </span>
                <?php else: ?>
                <span style="color:#d1d5db;">—</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;white-space:nowrap;">
                <?php if (!empty($d['file_path'])): ?>
                <a href="<?= APP_URL ?>/<?= clean($d['file_path']) ?>" target="_blank"
                   class="btn btn-primary btn-sm" title="Download">
                  <i class="fa fa-download"></i>
                </a>
                <?php endif; ?>
                <?php if ($is_mine): ?>
                <button class="btn btn-warning btn-sm" title="Edit"
                  onclick='editDok(<?= json_encode([
                    "id"         => (int)$d["id"],
                    "pokja_id"   => (int)$d["pokja_id"],
                    "judul"      => $d["judul"],
                    "nomor_doc"  => $d["nomor_doc"] ?? "",
                    "kategori"   => $d["kategori"]  ?? "",
                    "keterangan"       => $d["keterangan"]        ?? "",
                    "elemen_penilaian" => $d["elemen_penilaian"] ?? "",
                    "status"           => $d["status"],
                    "tgl_terbit"       => $d["tgl_terbit"] ?? "",
                    "tgl_exp"          => $d["tgl_exp"]    ?? "",
                  ]) ?>)'>
                  <i class="fa fa-edit"></i>
                </button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="hapus">
                  <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('Hapus dokumen ini?')">
                    <i class="fa fa-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Edit Dokumen -->
<div class="modal-ov" id="m-edit">
  <div class="modal-box" style="max-width:640px;">
    <div class="modal-hd">
      <h5><i class="fa fa-file-pen"></i> Edit Dokumen</h5>
      <button class="mc" onclick="closeModal('m-edit')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e-id">
      <div class="modal-bd">
        <div class="form-row">
          <!-- Pokja -->
          <div class="form-group" id="e-pokja-wrap" style="<?= !$_is_admin ? 'display:none;' : '' ?>">
            <label>Pokja <span class="req">*</span></label>
            <select name="pokja_id" id="e-pokja" class="form-control">
              <?php foreach ($pokja_list as $pk): ?>
              <option value="<?= (int)$pk['id'] ?>"><?= clean($pk['kode']) ?> — <?= clean($pk['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Kategori</label>
            <select name="kategori" id="e-kat" class="form-control">
              <option value="">— Pilih —</option>
              <?php foreach ($kategori_list as $k): ?>
              <option value="<?= $k ?>"><?= $k ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Elemen Penilaian</label>
            <input type="text" name="elemen_penilaian" id="e-elemen" class="form-control"
                   placeholder="EP 1.A, TKRS 1.1">
          </div>
          <div class="form-group">
            <label>Judul Dokumen <span class="req">*</span></label>
            <input type="text" name="judul" id="e-judul" class="form-control" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Nomor Dokumen <span style="font-size:10px;color:#9ca3af;">(kosongkan jika tidak ada)</span></label>
            <input type="text" name="nomor_doc" id="e-nomor" class="form-control"
                   placeholder="SPO/RM/001/2024">
          </div>
          <div class="form-group">
            <label>Keterangan</label>
            <textarea name="keterangan" id="e-ket" class="form-control" rows="3"
                      placeholder="Catatan tambahan…"></textarea>
          </div>
        </div>

        <div class="form-group">
          <label>Ganti File <span style="font-size:10px;color:#9ca3af;">(kosongkan jika tidak ingin ganti)</span></label>
          <input type="file" name="file" class="form-control"
                 accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
        </div>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-edit')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>
function editDok(d) {
  document.getElementById('e-id').value     = d.id;
  document.getElementById('e-judul').value  = d.judul;
  document.getElementById('e-nomor').value  = d.nomor_doc || '';
  const eel = document.getElementById('e-elemen');
  if (eel) eel.value = d.elemen_penilaian || '';
  document.getElementById('e-ket').value    = d.keterangan || '';
  const katSel = document.getElementById('e-kat');
  for (let i = 0; i < katSel.options.length; i++)
    katSel.options[i].selected = (katSel.options[i].value === (d.kategori || ''));

  const pokjaSel = document.getElementById('e-pokja');
  if (pokjaSel) {
    for (let i = 0; i < pokjaSel.options.length; i++)
      pokjaSel.options[i].selected = (pokjaSel.options[i].value == d.pokja_id);
  }

  openModal('m-edit');
}

// Toggle grid / list view
function setView(v) {
  document.getElementById('view-grid').style.display = v === 'grid' ? 'block' : 'none';
  document.getElementById('view-list').style.display = v === 'list' ? 'block' : 'none';
  document.getElementById('btn-grid').classList.toggle('active', v === 'grid');
  document.getElementById('btn-list').classList.toggle('active', v === 'list');
  try { localStorage.setItem('dok_view', v); } catch(e) {}
}
// Restore view preference
(function() {
  try { const v = localStorage.getItem('dok_view'); if (v) setView(v); } catch(e) {}
})();
</script>

<?php include '../includes/footer.php'; ?>