<?php
// pages/master_pokja.php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin'])) {
    setFlash('danger', 'Akses ditolak. Hanya Admin yang dapat mengelola Master Pokja.');
    redirect(APP_URL . '/dashboard.php');
}
$page_title  = 'Master Pokja';
$active_menu = 'master_pokja';

// ── Pastikan tabel ada & kolom ketua_id tersedia ────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `master_pokja` (
      `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `kode`       VARCHAR(20)  NOT NULL,
      `nama`       VARCHAR(150) NOT NULL,
      `deskripsi`  TEXT         DEFAULT NULL,
      `ketua`      VARCHAR(100) DEFAULT NULL,
      `ketua_id`   INT UNSIGNED DEFAULT NULL,
      `status`     ENUM('aktif','nonaktif') DEFAULT 'aktif',
      `urutan`     TINYINT UNSIGNED DEFAULT 0,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Tambah kolom ketua_id jika belum ada (upgrade dari versi lama)
    $pdo->exec("ALTER TABLE master_pokja ADD COLUMN IF NOT EXISTS `ketua_id` INT UNSIGNED DEFAULT NULL AFTER `ketua`");
} catch (Exception $e) {}

// ── Ambil daftar karyawan aktif untuk dropdown PIC ─────────────────────────
// Sumber: sdm_karyawan JOIN users (nama dari users, detail dari sdm_karyawan)
$karyawan_list = [];
try {
    $karyawan_list = $pdo->query("
        SELECT
            s.id,
            u.nama,
            s.nik_rs                                  AS nik,
            s.no_hp,
            s.divisi,
            COALESCE(j.nama, s.jenis_tenaga, s.divisi, '') AS jabatan_divisi
        FROM sdm_karyawan s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN jabatan j ON j.id = s.jabatan_id
        WHERE s.status = 'aktif'
          AND u.status = 'aktif'
        ORDER BY u.nama
    ")->fetchAll();
} catch (Exception $e) {
    // Fallback ke tabel users jika sdm_karyawan belum ada data
    try {
        $karyawan_list = $pdo->query("
            SELECT id, nama,
                   COALESCE(CONCAT(role, ' — ', divisi), role) AS jabatan_divisi,
                   divisi, no_hp, '' AS nik
            FROM users
            WHERE status = 'aktif'
            ORDER BY nama
        ")->fetchAll();
    } catch (Exception $e2) {}
}

// ── POST Handler ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'tambah') {
        $kode     = strtoupper(trim($_POST['kode']  ?? ''));
        $nama     = trim($_POST['nama']  ?? '');
        $desk     = trim($_POST['deskripsi'] ?? '');
        $urt      = (int)($_POST['urutan'] ?? 0);
        $ketua_id = (int)($_POST['ketua_id'] ?? 0) ?: null;

        // Ambil nama ketua dari sdm_karyawan → users
        $ketua_nama = null;
        if ($ketua_id) {
            try {
                $kq = $pdo->prepare("
                    SELECT u.nama FROM sdm_karyawan s
                    JOIN users u ON u.id = s.user_id
                    WHERE s.id = ? AND s.status = 'aktif' AND u.status = 'aktif'
                    LIMIT 1
                ");
                $kq->execute([$ketua_id]);
                $ketua_nama = $kq->fetchColumn() ?: null;
            } catch (Exception $e) {
                // fallback: ambil langsung dari users jika query di atas gagal
                try {
                    $kq2 = $pdo->prepare("SELECT nama FROM users WHERE id=? AND status='aktif' LIMIT 1");
                    $kq2->execute([$ketua_id]);
                    $ketua_nama = $kq2->fetchColumn() ?: null;
                } catch (Exception $e2) {}
            }
        }

        if ($kode && $nama) {
            $cek = $pdo->prepare("SELECT id FROM master_pokja WHERE kode=?");
            $cek->execute([$kode]);
            if ($cek->fetch()) {
                setFlash('warning', "Kode Pokja <strong>$kode</strong> sudah digunakan.");
            } else {
                $pdo->prepare("INSERT INTO master_pokja (kode,nama,deskripsi,ketua,ketua_id,urutan,status) VALUES (?,?,?,?,?,?,'aktif')")
                    ->execute([$kode, $nama, $desk ?: null, $ketua_nama, $ketua_id, $urt]);
                setFlash('success', "Pokja <strong>" . clean($nama) . "</strong> berhasil ditambahkan.");
            }
        } else {
            setFlash('danger', 'Kode dan Nama Pokja wajib diisi.');
        }
    }

    elseif ($act === 'edit') {
        $id       = (int)($_POST['id']    ?? 0);
        $kode     = strtoupper(trim($_POST['kode']  ?? ''));
        $nama     = trim($_POST['nama']  ?? '');
        $desk     = trim($_POST['deskripsi'] ?? '');
        $urt      = (int)($_POST['urutan'] ?? 0);
        $stat     = in_array($_POST['status'] ?? '', ['aktif','nonaktif']) ? $_POST['status'] : 'aktif';
        $ketua_id = (int)($_POST['ketua_id'] ?? 0) ?: null;

        // Ambil nama ketua dari sdm_karyawan → users
        $ketua_nama = null;
        if ($ketua_id) {
            try {
                $kq = $pdo->prepare("
                    SELECT u.nama FROM sdm_karyawan s
                    JOIN users u ON u.id = s.user_id
                    WHERE s.id = ? AND s.status = 'aktif' AND u.status = 'aktif'
                    LIMIT 1
                ");
                $kq->execute([$ketua_id]);
                $ketua_nama = $kq->fetchColumn() ?: null;
            } catch (Exception $e) {
                try {
                    $kq2 = $pdo->prepare("SELECT nama FROM users WHERE id=? AND status='aktif' LIMIT 1");
                    $kq2->execute([$ketua_id]);
                    $ketua_nama = $kq2->fetchColumn() ?: null;
                } catch (Exception $e2) {}
            }
        }

        if ($id && $kode && $nama) {
            $cek = $pdo->prepare("SELECT id FROM master_pokja WHERE kode=? AND id!=?");
            $cek->execute([$kode, $id]);
            if ($cek->fetch()) {
                setFlash('warning', "Kode Pokja <strong>$kode</strong> sudah digunakan.");
            } else {
                $pdo->prepare("UPDATE master_pokja SET kode=?,nama=?,deskripsi=?,ketua=?,ketua_id=?,urutan=?,status=? WHERE id=?")
                    ->execute([$kode, $nama, $desk ?: null, $ketua_nama, $ketua_id, $urt, $stat, $id]);
                setFlash('success', "Pokja <strong>" . clean($nama) . "</strong> berhasil diperbarui.");
            }
        } else {
            setFlash('danger', 'Data tidak lengkap.');
        }
    }

    elseif ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        // Cek apakah pokja masih dipakai oleh user atau dokumen
        $cek_user = $pdo->prepare("SELECT COUNT(*) FROM users WHERE pokja_id=?");
        $cek_user->execute([$id]);
        $cek_dok  = $pdo->prepare("SELECT COUNT(*) FROM dokumen_akreditasi WHERE pokja_id=?");
        $cek_dok->execute([$id]);
        if ((int)$cek_user->fetchColumn() > 0) {
            setFlash('danger', 'Pokja tidak dapat dihapus karena masih digunakan oleh anggota tim.');
        } elseif ((int)$cek_dok->fetchColumn() > 0) {
            setFlash('danger', 'Pokja tidak dapat dihapus karena masih memiliki dokumen. Hapus atau pindahkan dokumen terlebih dahulu.');
        } else {
            $pdo->prepare("DELETE FROM master_pokja WHERE id=?")->execute([$id]);
            setFlash('success', 'Pokja berhasil dihapus.');
        }
    }

    redirect(APP_URL . '/pages/master_pokja.php');
}

// ── Ambil data ───────────────────────────────────────────────────────────────
$pokjas = $pdo->query("
    SELECT p.*,
           COUNT(DISTINCT u.id)           AS jml_anggota,
           COUNT(DISTINCT d.id)           AS jml_dokumen,
           s.divisi                       AS ketua_divisi,
           s.no_hp                        AS ketua_hp,
           s.nik_rs                       AS ketua_nik,
           COALESCE(j.nama, s.jenis_tenaga, s.divisi) AS ketua_jabatan
    FROM master_pokja p
    LEFT JOIN users              u  ON u.pokja_id = p.id AND u.role='akreditasi' AND u.status='aktif'
    LEFT JOIN dokumen_akreditasi d  ON d.pokja_id = p.id
    LEFT JOIN sdm_karyawan       s  ON s.id = p.ketua_id AND s.status = 'aktif'
    LEFT JOIN jabatan             j  ON j.id = s.jabatan_id
    GROUP BY p.id
    ORDER BY p.urutan, p.kode
")->fetchAll();

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-layer-group text-primary"></i> &nbsp;Master Pokja</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Master Pokja</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Summary -->
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <?php
    $total  = count($pokjas);
    $aktif  = count(array_filter($pokjas, fn($p) => $p['status'] === 'aktif'));
    $tot_anggota = array_sum(array_column($pokjas, 'jml_anggota'));
    $tot_dok     = array_sum(array_column($pokjas, 'jml_dokumen'));
    foreach ([
      [$total,       'Total Pokja',   'fa-layer-group', '#ede9fe','#7c3aed'],
      [$aktif,       'Pokja Aktif',   'fa-circle-check','#d1fae5','#065f46'],
      [$tot_anggota, 'Total Anggota', 'fa-users',       '#dbeafe','#1d4ed8'],
      [$tot_dok,     'Total Dokumen', 'fa-folder-open', '#fef9c3','#854d0e'],
    ] as [$val,$lbl,$ic,$bg,$col]):
    ?>
    <div style="background:#fff;border:1px solid #f0f0f0;border-radius:8px;padding:10px 16px;
                display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.05);min-width:130px;">
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

  <!-- Tabel -->
  <div class="panel">
    <div class="panel-hd">
      <h5>Daftar Pokja <span style="color:#94a3b8;font-weight:400;">(<?= count($pokjas) ?>)</span></h5>
      <button class="btn btn-primary btn-sm" onclick="openModal('m-add')">
        <i class="fa fa-plus"></i> Tambah Pokja
      </button>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th>Kode</th>
            <th>Nama Pokja</th>
            <th>Ketua / PIC</th>
            <th style="text-align:center;">Anggota</th>
            <th style="text-align:center;">Dokumen</th>
            <th>Status</th>
            <th style="text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pokjas)): ?>
          <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:30px;">
            Belum ada Pokja. Klik <strong>Tambah Pokja</strong> untuk memulai.
          </td></tr>
          <?php endif; ?>
          <?php $no = 1; foreach ($pokjas as $p): ?>
          <tr>
            <td style="color:#cbd5e1;"><?= $no++ ?></td>
            <td>
              <span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 10px;
                           border-radius:6px;background:#fef9c3;color:#854d0e;
                           font-family:monospace;letter-spacing:.5px;">
                <?= clean($p['kode']) ?>
              </span>
            </td>
            <td>
              <div style="font-weight:600;color:#1e293b;"><?= clean($p['nama']) ?></div>
              <?php if (!empty($p['deskripsi'])): ?>
              <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                <?= clean(mb_substr($p['deskripsi'], 0, 80)) ?><?= mb_strlen($p['deskripsi']) > 80 ? '…' : '' ?>
              </div>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($p['ketua'])): ?>
              <div style="display:flex;align-items:center;gap:7px;">
                <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#fde68a,#fbbf24);
                            display:flex;align-items:center;justify-content:center;flex-shrink:0;
                            font-size:10px;font-weight:800;color:#78350f;">
                  <?= mb_substr($p['ketua'], 0, 1) ?>
                </div>
                <div>
                  <div style="font-size:12.5px;font-weight:600;color:#1e293b;"><?= clean($p['ketua']) ?></div>
                  <?php if (!empty($p['ketua_jabatan'])): ?>
                  <div style="font-size:10.5px;color:#94a3b8;margin-top:1px;"><?= clean($p['ketua_jabatan']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($p['ketua_hp'])): ?>
                  <div style="font-size:10px;color:#94a3b8;"><i class="fa fa-phone" style="font-size:9px;"></i> <?= clean($p['ketua_hp']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
              <?php else: ?>
              <span style="color:#d1d5db;font-size:12px;">— Belum ditentukan</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <span style="font-size:13px;font-weight:700;color:#1d4ed8;">
                <?= (int)$p['jml_anggota'] ?>
              </span>
            </td>
            <td style="text-align:center;">
              <span style="font-size:13px;font-weight:700;color:#854d0e;">
                <?= (int)$p['jml_dokumen'] ?>
              </span>
            </td>
            <td>
              <?php if ($p['status'] === 'aktif'): ?>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;
                           font-weight:600;padding:2px 10px;border-radius:20px;
                           background:#d1fae5;color:#065f46;">
                <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;"></span> Aktif
              </span>
              <?php else: ?>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;
                           font-weight:600;padding:2px 10px;border-radius:20px;
                           background:#fee2e2;color:#991b1b;">
                <span style="width:6px;height:6px;border-radius:50%;background:#ef4444;"></span> Nonaktif
              </span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;white-space:nowrap;">
              <button class="btn btn-warning btn-sm" title="Edit"
                onclick='editPokja(<?= json_encode([
                  "id"        => (int)$p["id"],
                  "kode"      => $p["kode"],
                  "nama"      => $p["nama"],
                  "deskripsi" => $p["deskripsi"] ?? "",
                  "ketua_id"  => (int)($p["ketua_id"] ?? 0),
                  "urutan"    => (int)$p["urutan"],
                  "status"    => $p["status"],
                ]) ?>)'>
                <i class="fa fa-edit"></i> Edit
              </button>
              <?php if ((int)$p['jml_anggota'] === 0 && (int)$p['jml_dokumen'] === 0): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                  onclick="return confirm('Hapus Pokja <?= addslashes(clean($p['nama'])) ?>?')">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
              <?php else: ?>
              <button class="btn btn-sm btn-default" disabled title="Masih ada anggota/dokumen" style="opacity:.4;cursor:not-allowed;">
                <i class="fa fa-trash"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══ MODAL: Tambah Pokja ══ -->
<div class="modal-ov" id="m-add">
  <div class="modal-box">
    <div class="modal-hd">
      <h5><i class="fa fa-layer-group"></i> Tambah Pokja</h5>
      <button class="mc" onclick="closeModal('m-add')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah">
      <div class="modal-bd">
        <div class="form-row">
          <div class="form-group" style="flex:0 0 140px;">
            <label>Kode <span class="req">*</span></label>
            <input type="text" name="kode" class="form-control" placeholder="TKRS"
                   style="text-transform:uppercase;font-weight:700;letter-spacing:.5px;" required maxlength="20"
                   oninput="this.value=this.value.toUpperCase()">
          </div>
          <div class="form-group">
            <label>Nama Pokja <span class="req">*</span></label>
            <input type="text" name="nama" class="form-control" placeholder="Nama Kelompok Kerja" required>
          </div>
        </div>

        <div class="form-group">
          <label>Deskripsi</label>
          <textarea name="deskripsi" class="form-control" rows="2"
                    placeholder="Deskripsi singkat Pokja…"></textarea>
        </div>

        <!-- Ketua / PIC dari data karyawan -->
        <div class="form-group">
          <label>Ketua / PIC <span style="font-size:10px;color:#9ca3af;">(pilih dari data karyawan)</span></label>
          <input type="hidden" name="ketua_id" id="add-ketua-id" value="">
          <div style="position:relative;">
            <input type="text" id="add-ketua-search" class="form-control"
                   placeholder="&#xf002; Ketik nama untuk mencari karyawan…"
                   autocomplete="off"
                   oninput="searchKaryawan(this.value,'add-ketua-dropdown','add-ketua-id','add-ketua-preview')"
                   onfocus="showDropdown('add-ketua-dropdown')"
                   style="padding-right:32px;">
            <button type="button" onclick="clearKaryawan('add')"
                    id="add-clear-btn" style="display:none;position:absolute;right:8px;top:50%;
                    transform:translateY(-50%);background:none;border:none;cursor:pointer;
                    color:#9ca3af;font-size:13px;padding:0;">
              <i class="fa fa-times-circle"></i>
            </button>
            <div id="add-ketua-dropdown" class="karyawan-dropdown" style="display:none;"></div>
          </div>
          <!-- Preview karyawan terpilih -->
          <div id="add-ketua-preview" class="karyawan-preview" style="display:none;"></div>
        </div>

        <div class="form-group" style="width:130px;">
          <label>Urutan Tampil</label>
          <input type="number" name="urutan" class="form-control" value="0" min="0" max="99">
        </div>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-add')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>


<!-- ══ MODAL: Edit Pokja ══ -->
<div class="modal-ov" id="m-edit">
  <div class="modal-box">
    <div class="modal-hd">
      <h5><i class="fa fa-edit"></i> Edit Pokja</h5>
      <button class="mc" onclick="closeModal('m-edit')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e-id">
      <div class="modal-bd">
        <div class="form-row">
          <div class="form-group" style="flex:0 0 140px;">
            <label>Kode <span class="req">*</span></label>
            <input type="text" name="kode" id="e-kode" class="form-control"
                   style="text-transform:uppercase;font-weight:700;letter-spacing:.5px;" required maxlength="20"
                   oninput="this.value=this.value.toUpperCase()">
          </div>
          <div class="form-group">
            <label>Nama Pokja <span class="req">*</span></label>
            <input type="text" name="nama" id="e-nama" class="form-control" required>
          </div>
        </div>

        <div class="form-group">
          <label>Deskripsi</label>
          <textarea name="deskripsi" id="e-desk" class="form-control" rows="2"></textarea>
        </div>

        <!-- Ketua / PIC -->
        <div class="form-group">
          <label>Ketua / PIC <span style="font-size:10px;color:#9ca3af;">(pilih dari data karyawan)</span></label>
          <input type="hidden" name="ketua_id" id="edit-ketua-id" value="">
          <div style="position:relative;">
            <input type="text" id="edit-ketua-search" class="form-control"
                   placeholder="&#xf002; Ketik nama untuk mencari karyawan…"
                   autocomplete="off"
                   oninput="searchKaryawan(this.value,'edit-ketua-dropdown','edit-ketua-id','edit-ketua-preview')"
                   onfocus="showDropdown('edit-ketua-dropdown')"
                   style="padding-right:32px;">
            <button type="button" onclick="clearKaryawan('edit')"
                    id="edit-clear-btn" style="display:none;position:absolute;right:8px;top:50%;
                    transform:translateY(-50%);background:none;border:none;cursor:pointer;
                    color:#9ca3af;font-size:13px;padding:0;">
              <i class="fa fa-times-circle"></i>
            </button>
            <div id="edit-ketua-dropdown" class="karyawan-dropdown" style="display:none;"></div>
          </div>
          <div id="edit-ketua-preview" class="karyawan-preview" style="display:none;"></div>
        </div>

        <div class="form-row">
          <div class="form-group" style="flex:0 0 130px;">
            <label>Urutan Tampil</label>
            <input type="number" name="urutan" id="e-urutan" class="form-control" min="0" max="99">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="e-status" class="form-control">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-edit')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>


<style>
/* ── Dropdown karyawan ── */
.karyawan-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0; right: 0;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,.12);
  max-height: 240px;
  overflow-y: auto;
  z-index: 9999;
  scrollbar-width: thin;
}
.karyawan-dropdown::-webkit-scrollbar { width: 4px; }
.karyawan-dropdown::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 99px; }
.karyawan-opt {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px;
  cursor: pointer;
  transition: background .12s;
  border-bottom: 1px solid #f3f4f6;
}
.karyawan-opt:last-child { border-bottom: none; }
.karyawan-opt:hover { background: #f0fdf4; }
.karyawan-opt-av {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg,#fde68a,#fbbf24);
  color: #78350f;
  font-size: 11px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.karyawan-opt-nama { font-size: 12.5px; font-weight: 600; color: #1e293b; }
.karyawan-opt-sub  { font-size: 10.5px; color: #9ca3af; margin-top: 1px; }
.karyawan-opt-empty {
  padding: 14px 12px;
  font-size: 12px; color: #9ca3af;
  text-align: center;
}

/* ── Preview karyawan terpilih ── */
.karyawan-preview {
  display: flex; align-items: center; gap: 10px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 10px 12px;
  margin-top: 8px;
}
.karyawan-preview-av {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg,#00e5b0,#00c896);
  color: #0a0f14;
  font-size: 12px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.karyawan-preview-nama { font-size: 13px; font-weight: 700; color: #065f46; }
.karyawan-preview-sub  { font-size: 11px; color: #059669; margin-top: 1px; }
</style>


<script>
// ── Data karyawan dari PHP — sumber: sdm_karyawan JOIN users ──
// id = sdm_karyawan.id (bukan user_id), dipakai sebagai ketua_id di master_pokja
const karyawanData = <?= json_encode(array_map(fn($k) => [
    'id'     => (int)$k['id'],
    'nama'   => $k['nama'],
    'sub'    => trim(($k['jabatan_divisi'] ?? '') . (!empty($k['no_hp']) ? ' · ' . $k['no_hp'] : '')),
    'divisi' => $k['divisi'] ?? '',
    'hp'     => $k['no_hp']  ?? '',
    'nik'    => $k['nik']    ?? '',  // nik_rs dari sdm_karyawan
], $karyawan_list), JSON_UNESCAPED_UNICODE) ?>;

// ── Fungsi inisial avatar ──
function initials(nama) {
    return nama.trim().split(/\s+/).slice(0,2).map(w=>w[0]).join('').toUpperCase();
}

// ── Live search ──
function searchKaryawan(q, dropdownId, hiddenId, previewId) {
    const dd = document.getElementById(dropdownId);
    const term = q.trim().toLowerCase();

    // Jika input dikosongkan, clear selection
    if (!term) {
        document.getElementById(hiddenId).value = '';
        document.getElementById(previewId).style.display = 'none';
        const prefix = dropdownId.startsWith('add') ? 'add' : 'edit';
        const btn = document.getElementById(prefix + '-clear-btn');
        if (btn) btn.style.display = 'none';
    }

    const matches = term.length < 1 ? karyawanData.slice(0,10)
        : karyawanData.filter(k =>
            k.nama.toLowerCase().includes(term) ||
            k.nik.toLowerCase().includes(term)  ||
            k.divisi.toLowerCase().includes(term)
        ).slice(0, 10);

    if (matches.length === 0) {
        dd.innerHTML = '<div class="karyawan-opt-empty"><i class="fa fa-search"></i> Tidak ditemukan</div>';
    } else {
        dd.innerHTML = matches.map(k => `
            <div class="karyawan-opt" onclick="pilihKaryawan(${k.id},'${k.nama.replace(/'/g,"\\'")}','${k.sub.replace(/'/g,"\\'")}','${dropdownId}','${hiddenId}','${previewId}')">
                <div class="karyawan-opt-av">${initials(k.nama)}</div>
                <div>
                    <div class="karyawan-opt-nama">${k.nama}</div>
                    ${k.sub ? `<div class="karyawan-opt-sub">${k.sub}</div>` : ''}
                </div>
            </div>
        `).join('');
    }
    dd.style.display = 'block';
}

function showDropdown(dropdownId) {
    const dd = document.getElementById(dropdownId);
    const searchId = dropdownId.replace('-dropdown', '-search');
    const q = document.getElementById(searchId)?.value || '';
    if (!q) {
        // Tampilkan 10 pertama saat fokus tanpa input
        dd.innerHTML = karyawanData.slice(0,10).map(k => {
            const hiddenId  = dropdownId.replace('-dropdown', '-id');
            const previewId = dropdownId.replace('-dropdown', '-preview');
            return `
                <div class="karyawan-opt" onclick="pilihKaryawan(${k.id},'${k.nama.replace(/'/g,"\\'")}','${k.sub.replace(/'/g,"\\'")}','${dropdownId}','${hiddenId}','${previewId}')">
                    <div class="karyawan-opt-av">${initials(k.nama)}</div>
                    <div>
                        <div class="karyawan-opt-nama">${k.nama}</div>
                        ${k.sub ? `<div class="karyawan-opt-sub">${k.sub}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        if (karyawanData.length > 10) {
            dd.innerHTML += `<div class="karyawan-opt-empty" style="font-style:italic;">Ketik untuk cari lebih...</div>`;
        }
    }
    dd.style.display = 'block';
}

function pilihKaryawan(id, nama, sub, dropdownId, hiddenId, previewId) {
    document.getElementById(hiddenId).value = id;

    // Update search input
    const searchId = dropdownId.replace('-dropdown', '-search');
    document.getElementById(searchId).value = nama;

    // Tampilkan preview
    const prev = document.getElementById(previewId);
    prev.innerHTML = `
        <div class="karyawan-preview-av">${initials(nama)}</div>
        <div>
            <div class="karyawan-preview-nama"><i class="fa fa-circle-check" style="color:#22c55e;font-size:11px;"></i> ${nama}</div>
            ${sub ? `<div class="karyawan-preview-sub">${sub}</div>` : ''}
        </div>
    `;
    prev.style.display = 'flex';

    // Tampilkan tombol clear
    const prefix = dropdownId.startsWith('add') ? 'add' : 'edit';
    const btn = document.getElementById(prefix + '-clear-btn');
    if (btn) btn.style.display = 'block';

    // Tutup dropdown
    document.getElementById(dropdownId).style.display = 'none';
}

function clearKaryawan(prefix) {
    document.getElementById(prefix + '-ketua-id').value = '';
    document.getElementById(prefix + '-ketua-search').value = '';
    document.getElementById(prefix + '-ketua-preview').style.display = 'none';
    document.getElementById(prefix + '-clear-btn').style.display = 'none';
    document.getElementById(prefix + '-ketua-dropdown').style.display = 'none';
}

// Tutup dropdown jika klik di luar
document.addEventListener('click', function(e) {
    if (!e.target.closest('.form-group')) {
        document.querySelectorAll('.karyawan-dropdown').forEach(d => d.style.display = 'none');
    }
});

// ── Isi form Edit ──
function editPokja(p) {
    document.getElementById('e-id').value     = p.id;
    document.getElementById('e-kode').value   = p.kode;
    document.getElementById('e-nama').value   = p.nama;
    document.getElementById('e-desk').value   = p.deskripsi || '';
    document.getElementById('e-urutan').value = p.urutan || 0;
    document.getElementById('e-status').value = p.status  || 'aktif';

    // Reset dulu
    clearKaryawan('edit');

    // Set ketua jika ada
    if (p.ketua_id) {
        const kar = karyawanData.find(k => k.id === p.ketua_id);
        if (kar) {
            pilihKaryawan(
                kar.id, kar.nama, kar.sub,
                'edit-ketua-dropdown','edit-ketua-id','edit-ketua-preview'
            );
        }
    }

    openModal('m-edit');
}
</script>

<?php include '../includes/footer.php'; ?>