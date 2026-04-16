<?php
// pages/master_penerimaan.php
ob_start();
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'keuangan'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Master Penerimaan';
$active_menu = 'master_penerimaan';

// ── Auto-create tabel ─────────────────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `master_penerimaan` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `kode`          VARCHAR(20)   NOT NULL UNIQUE,
        `nama`          VARCHAR(100)  NOT NULL,
        `jenis`         ENUM('tetap','variable') NOT NULL DEFAULT 'tetap',
        `nilai_default` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `aktif`         TINYINT(1)    NOT NULL DEFAULT 1,
        `keterangan`    VARCHAR(255)  DEFAULT NULL,
        `created_by`    INT UNSIGNED  DEFAULT NULL,
        `created_at`    DATETIME      DEFAULT NULL,
        `updated_by`    INT UNSIGNED  DEFAULT NULL,
        `updated_at`    DATETIME      DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'tambah' || $act === 'edit') {
        $nama          = trim($_POST['nama'] ?? '');
        $kode          = strtoupper(trim($_POST['kode'] ?? ''));
        $jenis         = $_POST['jenis'] ?? 'tetap';
        $nilai_default = (float)str_replace(',', '', $_POST['nilai_default'] ?? 0);
        $aktif         = isset($_POST['aktif']) ? 1 : 0;
        $keterangan    = trim($_POST['keterangan'] ?? '');

        if (!$nama || !$kode) {
            setFlash('danger', 'Nama dan Kode wajib diisi.');
        } else {
            try {
                if ($act === 'tambah') {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM master_penerimaan WHERE kode=?");
                    $chk->execute([$kode]);
                    if ($chk->fetchColumn() > 0) {
                        setFlash('danger', "Kode <strong>$kode</strong> sudah digunakan.");
                    } else {
                        $pdo->prepare("INSERT INTO master_penerimaan
                            (kode,nama,jenis,nilai_default,aktif,keterangan,created_by,created_at)
                            VALUES (?,?,?,?,?,?,?,NOW())")
                            ->execute([$kode,$nama,$jenis,$nilai_default,$aktif,$keterangan,$_SESSION['user_id']]);
                        setFlash('success', 'Komponen penerimaan berhasil ditambahkan.');
                    }
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $pdo->prepare("UPDATE master_penerimaan
                        SET kode=?,nama=?,jenis=?,nilai_default=?,aktif=?,keterangan=?,updated_by=?,updated_at=NOW()
                        WHERE id=?")
                        ->execute([$kode,$nama,$jenis,$nilai_default,$aktif,$keterangan,$_SESSION['user_id'],$id]);
                    setFlash('success', 'Komponen penerimaan berhasil diperbarui.');
                }
            } catch (Exception $e) {
                setFlash('danger', 'Gagal menyimpan: ' . htmlspecialchars($e->getMessage()));
            }
        }
        redirect(APP_URL . '/pages/master_penerimaan.php');
    }

    if ($act === 'hapus') {
        $id   = (int)($_POST['id'] ?? 0);
        $used = 0;
        try {
            $cu = $pdo->prepare("SELECT COUNT(*) FROM data_gaji_detail WHERE penerimaan_id=?");
            $cu->execute([$id]);
            $used = (int)$cu->fetchColumn();
        } catch (Exception $e) {}
        if ($used > 0) {
            setFlash('danger', "Tidak bisa dihapus, komponen sudah digunakan di $used data gaji.");
        } else {
            $pdo->prepare("DELETE FROM master_penerimaan WHERE id=?")->execute([$id]);
            setFlash('success', 'Komponen penerimaan berhasil dihapus.');
        }
        redirect(APP_URL . '/pages/master_penerimaan.php');
    }

    if ($act === 'toggle_aktif') {
        $id  = (int)($_POST['id'] ?? 0);
        $val = (int)($_POST['val'] ?? 0);
        $pdo->prepare("UPDATE master_penerimaan SET aktif=? WHERE id=?")->execute([$val,$id]);
        setFlash('success', 'Status berhasil diubah.');
        redirect(APP_URL . '/pages/master_penerimaan.php');
    }
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE nama LIKE ? OR kode LIKE ?" : '';
$params = $search ? ["%$search%", "%$search%"] : [];
$stmt   = $pdo->prepare("SELECT * FROM master_penerimaan $where ORDER BY kode ASC");
$stmt->execute($params);
$rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-circle-plus" style="color:#22c55e;"></i> &nbsp;Master Penerimaan</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Master Penerimaan</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Toolbar -->
  <div class="panel" style="margin-bottom:14px;">
    <div class="panel-bd">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                 placeholder="Cari nama / kode…" class="form-control" style="width:200px;height:34px;">
          <button type="submit" class="btn btn-default" style="height:34px;"><i class="fa fa-search"></i> Cari</button>
          <?php if ($search): ?><a href="master_penerimaan.php" class="btn btn-default" style="height:34px;">Reset</a><?php endif; ?>
        </form>
        <button onclick="openModal('m-tambah')" class="btn btn-success">
          <i class="fa fa-plus"></i> Tambah Komponen
        </button>
      </div>
    </div>
  </div>

  <!-- Tabel -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-circle-plus" style="color:#22c55e;"></i> &nbsp;Daftar Komponen Penerimaan
        <span style="color:#aaa;font-weight:400;">(<?= count($rows) ?>)</span>
      </h5>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th>Kode</th>
            <th>Nama Komponen</th>
            <th>Jenis</th>
            <th style="text-align:right;">Nilai Default</th>
            <th style="text-align:center;">Status</th>
            <th style="text-align:center;width:120px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="7" class="td-empty"><i class="fa fa-circle-plus"></i> Belum ada komponen penerimaan</td></tr>
          <?php else: foreach ($rows as $i => $r): ?>
          <tr>
            <td style="color:#bbb;font-size:12px;"><?= $i+1 ?></td>
            <td>
              <code style="background:#f0fdf4;color:#166534;padding:2px 8px;border-radius:5px;font-size:12px;font-weight:700;">
                <?= htmlspecialchars($r['kode']) ?>
              </code>
            </td>
            <td>
              <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['nama']) ?></div>
              <?php if ($r['keterangan']): ?>
              <div style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($r['keterangan']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['jenis'] === 'tetap'): ?>
              <span style="background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:600;padding:2px 9px;border-radius:20px;">Tetap</span>
              <?php else: ?>
              <span style="background:#fef9c3;color:#854d0e;font-size:11px;font-weight:600;padding:2px 9px;border-radius:20px;">Variable</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;font-weight:500;color:#166534;">
              <?= $r['nilai_default'] > 0 ? 'Rp ' . number_format($r['nilai_default'],0,',','.') : '<span style="color:#bbb">—</span>' ?>
            </td>
            <td style="text-align:center;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="act" value="toggle_aktif">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="hidden" name="val" value="<?= $r['aktif'] ? 0 : 1 ?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
                  <?php if ($r['aktif']): ?>
                  <span style="background:#d1fae5;color:#065f46;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;border:1px solid #6ee7b7;">● Aktif</span>
                  <?php else: ?>
                  <span style="background:#f3f4f6;color:#9ca3af;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;border:1px solid #e5e7eb;">○ Nonaktif</span>
                  <?php endif; ?>
                </button>
              </form>
            </td>
            <td style="text-align:center;white-space:nowrap;">
              <button onclick="editPenerimaan(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)" class="btn btn-primary btn-sm" title="Edit">
                <i class="fa fa-pen"></i>
              </button>
              <button onclick="hapusPenerimaan(<?= $r['id'] ?>, '<?= addslashes(htmlspecialchars($r['nama'])) ?>')" class="btn btn-danger btn-sm" title="Hapus">
                <i class="fa fa-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal-ov" id="m-tambah">
  <div style="background:#fff;width:100%;max-width:480px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;overflow:hidden;">
    <div style="padding:15px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:14px;font-weight:700;"><i class="fa fa-circle-plus" style="color:#22c55e;"></i> Tambah Komponen Penerimaan</div>
      <button onclick="closeModal('m-tambah')" class="btn btn-sm btn-default"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="tambah">
      <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Kode *</label>
            <input type="text" name="kode" placeholder="cth: TUNJAB" maxlength="20" required class="form-control" style="text-transform:uppercase;">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Jenis</label>
            <select name="jenis" class="form-control">
              <option value="tetap">Tetap</option>
              <option value="variable">Variable</option>
            </select>
          </div>
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Nama Komponen *</label>
          <input type="text" name="nama" placeholder="cth: Tunjangan Jabatan" required class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Nilai Default (Rp)</label>
          <input type="number" name="nilai_default" placeholder="0" min="0" step="1000" class="form-control">
          <small style="color:#9ca3af;font-size:10.5px;">Kosongkan jika nilai berbeda tiap karyawan</small>
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Keterangan</label>
          <input type="text" name="keterangan" placeholder="Keterangan singkat…" class="form-control">
        </div>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
          <input type="checkbox" name="aktif" value="1" checked> Aktif
        </label>
      </div>
      <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" onclick="closeModal('m-tambah')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-ov" id="m-edit">
  <div style="background:#fff;width:100%;max-width:480px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;overflow:hidden;">
    <div style="padding:15px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:14px;font-weight:700;"><i class="fa fa-pen" style="color:#3b82f6;"></i> Edit Komponen Penerimaan</div>
      <button onclick="closeModal('m-edit')" class="btn btn-sm btn-default"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Kode *</label>
            <input type="text" name="kode" id="edit-kode" maxlength="20" required class="form-control" style="text-transform:uppercase;">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Jenis</label>
            <select name="jenis" id="edit-jenis" class="form-control">
              <option value="tetap">Tetap</option>
              <option value="variable">Variable</option>
            </select>
          </div>
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Nama Komponen *</label>
          <input type="text" name="nama" id="edit-nama" required class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Nilai Default (Rp)</label>
          <input type="number" name="nilai_default" id="edit-nilai" min="0" step="1000" class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Keterangan</label>
          <input type="text" name="keterangan" id="edit-ket" class="form-control">
        </div>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
          <input type="checkbox" name="aktif" id="edit-aktif" value="1"> Aktif
        </label>
      </div>
      <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" onclick="closeModal('m-edit')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Perbarui</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL HAPUS -->
<div class="modal-ov" id="m-hapus">
  <div style="background:#fff;width:100%;max-width:380px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;">
    <div style="padding:24px;text-align:center;">
      <div style="width:52px;height:52px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="fa fa-trash" style="color:#ef4444;font-size:20px;"></i>
      </div>
      <div style="font-size:15px;font-weight:700;margin-bottom:6px;">Hapus Komponen?</div>
      <div style="font-size:13px;color:#6b7280;" id="hapus-nama-label">Komponen ini akan dihapus permanen.</div>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="hapus">
      <input type="hidden" name="id" id="hapus-id">
      <div style="padding:0 20px 20px;display:flex;gap:8px;justify-content:center;">
        <button type="button" onclick="closeModal('m-hapus')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>

<script>
function editPenerimaan(r) {
  document.getElementById('edit-id').value    = r.id;
  document.getElementById('edit-kode').value  = r.kode;
  document.getElementById('edit-nama').value  = r.nama;
  document.getElementById('edit-jenis').value = r.jenis;
  document.getElementById('edit-nilai').value = r.nilai_default;
  document.getElementById('edit-ket').value   = r.keterangan || '';
  document.getElementById('edit-aktif').checked = r.aktif == 1;
  openModal('m-edit');
}
function hapusPenerimaan(id, nama) {
  document.getElementById('hapus-id').value = id;
  document.getElementById('hapus-nama-label').textContent = 'Hapus komponen: ' + nama + '?';
  openModal('m-hapus');
}
</script>

<?php include '../includes/footer.php'; ?>