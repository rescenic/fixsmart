<?php
// pages/jabatan.php — Master Jabatan
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'hrd'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Master Jabatan';
$active_menu = 'jabatan';

// ── BUAT TABEL JIKA BELUM ADA ──────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS jabatan (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama        VARCHAR(100) NOT NULL,
            kode        VARCHAR(20)  DEFAULT NULL,
            deskripsi   TEXT         DEFAULT NULL,
            level       TINYINT UNSIGNED NOT NULL DEFAULT 1,
            status      ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
            urutan      INT NOT NULL DEFAULT 0,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
} catch (Exception $e) {}

// ── POST ACTIONS ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['action']     ?? '';
    $nama   = trim($_POST['nama']      ?? '');
    $kode   = strtoupper(trim($_POST['kode'] ?? ''));
    $desc   = trim($_POST['deskripsi'] ?? '');
    $level  = max(1, min(5, (int)($_POST['level']  ?? 1)));
    $urutan = (int)($_POST['urutan']   ?? 0);
    $status = $_POST['status']  ?? 'aktif';

    if ($act === 'tambah') {
        if (!$nama) {
            setFlash('danger', 'Nama jabatan wajib diisi.');
        } else {
            $chk = $pdo->prepare("SELECT id FROM jabatan WHERE nama = ?");
            $chk->execute([$nama]);
            if ($chk->fetch()) {
                setFlash('warning', "Jabatan '<strong>$nama</strong>' sudah ada.");
            } else {
                $kode_ok = true;
                if ($kode !== '') {
                    $ck = $pdo->prepare("SELECT id FROM jabatan WHERE kode = ?");
                    $ck->execute([$kode]);
                    if ($ck->fetch()) { $kode_ok = false; setFlash('warning', "Kode '<strong>$kode</strong>' sudah digunakan jabatan lain."); }
                }
                if ($kode_ok) {
                    $pdo->prepare("INSERT INTO jabatan (nama,kode,deskripsi,level,urutan,status) VALUES (?,?,?,?,?,?)")
                        ->execute([$nama, $kode ?: null, $desc ?: null, $level, $urutan, $status]);
                    setFlash('success', "Jabatan <strong>$nama</strong> berhasil ditambahkan.");
                }
            }
        }
    }

    elseif ($act === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$nama || !$id) {
            setFlash('danger', 'Data tidak lengkap.');
        } else {
            $kode_ok = true;
            if ($kode !== '') {
                $ck = $pdo->prepare("SELECT id FROM jabatan WHERE kode=? AND id!=?");
                $ck->execute([$kode, $id]);
                if ($ck->fetch()) { $kode_ok = false; setFlash('warning', "Kode '<strong>$kode</strong>' sudah digunakan jabatan lain."); }
            }
            if ($kode_ok) {
                $pdo->prepare("UPDATE jabatan SET nama=?,kode=?,deskripsi=?,level=?,urutan=?,status=? WHERE id=?")
                    ->execute([$nama, $kode ?: null, $desc ?: null, $level, $urutan, $status, $id]);
                setFlash('success', "Jabatan <strong>$nama</strong> berhasil diperbarui.");
            }
        }
    }

    elseif ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $in_use = false;
            try {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE jabatan_id = ?");
                $chk->execute([$id]);
                $in_use = $chk->fetchColumn() > 0;
            } catch (Exception $e) {}
            try {
                if (!$in_use) {
                    $chk2 = $pdo->prepare("SELECT COUNT(*) FROM sdm_karyawan WHERE jabatan_id = ?");
                    $chk2->execute([$id]);
                    $in_use = $chk2->fetchColumn() > 0;
                }
            } catch (Exception $e) {}

            if ($in_use) {
                setFlash('warning', 'Jabatan tidak dapat dihapus karena masih digunakan oleh karyawan.');
            } else {
                $r = $pdo->prepare("SELECT nama FROM jabatan WHERE id=?"); $r->execute([$id]);
                $nm = $r->fetchColumn();
                $pdo->prepare("DELETE FROM jabatan WHERE id=?")->execute([$id]);
                setFlash('success', "Jabatan <strong>" . htmlspecialchars($nm) . "</strong> berhasil dihapus.");
            }
        }
    }

    elseif ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE jabatan SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
            setFlash('success', 'Status jabatan berhasil diubah.');
        }
    }

    redirect(APP_URL . '/pages/jabatan.php');
}

// ── FETCH DATA ─────────────────────────────────────────────
$users_has_jabatan = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'jabatan_id'")->fetch();
    $users_has_jabatan = (bool)$col;
} catch (Exception $e) {}

$jml_per_jabatan = [];
if ($users_has_jabatan) {
    try {
        $s = $pdo->query("SELECT jabatan_id, COUNT(*) FROM users WHERE jabatan_id IS NOT NULL GROUP BY jabatan_id");
        $jml_per_jabatan = $s->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {}
}
// Juga hitung dari sdm_karyawan
try {
    $s2 = $pdo->query("SELECT jabatan_id, COUNT(*) FROM sdm_karyawan WHERE jabatan_id IS NOT NULL GROUP BY jabatan_id");
    foreach ($s2->fetchAll(PDO::FETCH_KEY_PAIR) as $jid => $cnt) {
        $jml_per_jabatan[$jid] = max($jml_per_jabatan[$jid] ?? 0, $cnt);
    }
} catch (Exception $e) {}

$list = $pdo->query("SELECT * FROM jabatan ORDER BY urutan ASC, level ASC, nama ASC")->fetchAll();

$level_labels = [1=>'Staff', 2=>'Supervisor', 3=>'Manager', 4=>'Direktur', 5=>'Eksekutif'];
$level_colors = [
    1 => ['#f1f5f9','#475569'],
    2 => ['#dbeafe','#1d4ed8'],
    3 => ['#d1fae5','#065f46'],
    4 => ['#ede9fe','#5b21b6'],
    5 => ['#fef3c7','#92400e'],
];

// Stats
$total     = count($list);
$aktif     = count(array_filter($list, fn($j) => $j['status'] === 'aktif'));
$nonaktif  = $total - $aktif;
$total_kry = array_sum($jml_per_jabatan);

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-briefcase text-primary"></i> &nbsp;Master Jabatan</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Master Jabatan</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Stats row -->
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach([
      [$total,     'Total Jabatan',  'fa-briefcase',   '#f0fdf4','#00c896'],
      [$aktif,     'Aktif',          'fa-circle-check','#d1fae5','#065f46'],
      [$nonaktif,  'Nonaktif',       'fa-circle-xmark','#f3f4f6','#6b7280'],
      [$total_kry, 'Total Karyawan', 'fa-users',       '#dbeafe','#1d4ed8'],
    ] as [$val,$lbl,$ico,$bg,$clr]): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:12px;min-width:130px;">
      <div style="width:38px;height:38px;border-radius:9px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa <?= $ico ?>" style="color:<?= $clr ?>;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;color:#111827;line-height:1;"><?= $val ?></div>
        <div style="font-size:11px;color:#9ca3af;margin-top:2px;"><?= $lbl ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Panel tabel -->
  <div class="panel">
    <div class="panel-hd">
      <h5>
        <i class="fa fa-list text-primary"></i> &nbsp;Daftar Jabatan
        <span style="color:#aaa;font-weight:400;">(<?= $total ?>)</span>
      </h5>
      <button class="btn btn-primary btn-sm" onclick="openModal('m-jabatan'); setAdd()">
        <i class="fa fa-plus"></i> Tambah Jabatan
      </button>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:60px;text-align:center;">Urutan</th>
            <th>Nama Jabatan</th>
            <th style="width:90px;text-align:center;">Kode</th>
            <th style="width:150px;">Level</th>
            <th style="width:90px;text-align:center;">Karyawan</th>
            <th style="width:90px;text-align:center;">Status</th>
            <th style="width:120px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($list)): ?>
          <tr>
            <td colspan="7" class="td-empty">
              <i class="fa fa-briefcase"></i> Belum ada data jabatan.
              <br><small style="color:#aaa;">Klik tombol <strong>+ Tambah Jabatan</strong> untuk memulai.</small>
            </td>
          </tr>
          <?php else: foreach ($list as $jb):
            $lv      = (int)$jb['level'];
            $lv_lbl  = $level_labels[$lv] ?? 'Level '.$lv;
            $jml     = (int)($jml_per_jabatan[$jb['id']] ?? 0);
            [$lv_bg, $lv_tc] = $level_colors[$lv] ?? ['#f3f4f6','#374151'];
            $in_use  = $jml > 0;
            $is_aktif = $jb['status'] === 'aktif';
          ?>
          <tr>
            <!-- Urutan -->
            <td style="text-align:center;">
              <span style="font-size:13px;font-weight:700;color:#d1d5db;"><?= (int)$jb['urutan'] ?></span>
            </td>

            <!-- Nama + deskripsi -->
            <td>
              <div style="font-weight:600;font-size:13px;color:#111827;"><?= htmlspecialchars($jb['nama']) ?></div>
              <?php if ($jb['deskripsi']): ?>
              <div style="font-size:11px;color:#9ca3af;margin-top:2px;"><?= htmlspecialchars($jb['deskripsi']) ?></div>
              <?php endif; ?>
            </td>

            <!-- Kode -->
            <td style="text-align:center;">
              <?php if ($jb['kode']): ?>
              <span style="font-family:monospace;font-size:11px;font-weight:700;
                           background:#f0fdf4;border:1px solid #bbf7d0;
                           padding:2px 8px;border-radius:4px;color:#16a34a;letter-spacing:.5px;">
                <?= htmlspecialchars($jb['kode']) ?>
              </span>
              <?php else: ?>
              <span style="color:#e5e7eb;font-size:13px;">—</span>
              <?php endif; ?>
            </td>

            <!-- Level -->
            <td>
              <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;
                           padding:3px 10px;border-radius:20px;background:<?= $lv_bg ?>;color:<?= $lv_tc ?>;">
                <span style="font-size:14px;font-weight:900;line-height:1;"><?= $lv ?></span>
                <?= $lv_lbl ?>
              </span>
            </td>

            <!-- Karyawan -->
            <td style="text-align:center;">
              <?php if ($jml > 0): ?>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:#00c896;">
                <i class="fa fa-users" style="font-size:10px;"></i> <?= $jml ?>
              </span>
              <?php else: ?>
              <span style="color:#e5e7eb;font-size:12px;">0</span>
              <?php endif; ?>
            </td>

            <!-- Status -->
            <td style="text-align:center;">
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;
                           padding:2px 10px;border-radius:20px;
                           background:<?= $is_aktif ? '#d1fae5' : '#f3f4f6' ?>;
                           color:<?= $is_aktif ? '#065f46' : '#6b7280' ?>;">
                <span style="width:5px;height:5px;border-radius:50%;background:<?= $is_aktif ? '#22c55e' : '#9ca3af' ?>;"></span>
                <?= $is_aktif ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>

            <!-- Aksi -->
            <td style="text-align:center;white-space:nowrap;">
              <!-- Edit -->
              <button class="btn btn-warning btn-sm"
                onclick='openModal("m-jabatan"); setEdit(<?= json_encode([
                  "id"        => (int)$jb["id"],
                  "nama"      => $jb["nama"],
                  "kode"      => $jb["kode"] ?? "",
                  "deskripsi" => $jb["deskripsi"] ?? "",
                  "level"     => (int)$jb["level"],
                  "urutan"    => (int)$jb["urutan"],
                  "status"    => $jb["status"],
                ]) ?>)'
                title="Edit jabatan">
                <i class="fa fa-edit"></i>
              </button>

              <!-- Toggle status -->
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$jb['id'] ?>">
                <button type="submit"
                  class="btn btn-sm <?= $is_aktif ? 'btn-default' : 'btn-success' ?>"
                  title="<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?>"
                  onclick="return confirm('<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?> jabatan ini?')">
                  <i class="fa <?= $is_aktif ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                </button>
              </form>

              <!-- Hapus -->
              <?php if (!$in_use): ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Hapus jabatan <?= addslashes(htmlspecialchars($jb['nama'])) ?>?')">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= (int)$jb['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" title="Hapus jabatan">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
              <?php else: ?>
              <button class="btn btn-sm btn-default" disabled
                title="Tidak bisa dihapus — masih ada <?= $jml ?> karyawan">
                <i class="fa fa-lock" style="color:#d1d5db;"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!$users_has_jabatan): ?>
  <!-- SQL hint -->
  <div style="background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:14px 18px;margin-top:14px;">
    <div style="font-size:10px;font-weight:700;color:#8b949e;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
      <i class="fa fa-database" style="color:#00e5b0;margin-right:5px;"></i>SQL — Hubungkan ke Tabel Users
    </div>
    <code id="jb-sql" style="display:block;color:#79c0ff;font-size:12px;line-height:2;font-family:monospace;">ALTER TABLE users
  ADD COLUMN jabatan_id INT UNSIGNED DEFAULT NULL AFTER divisi,
  ADD FOREIGN KEY (jabatan_id) REFERENCES jabatan(id) ON DELETE SET NULL;</code>
    <button onclick="copySql(event)" style="margin-top:8px;display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:6px;border:1px solid #30363d;background:#21262d;color:#79c0ff;font-size:11px;cursor:pointer;font-family:inherit;transition:all .15s;">
      <i class="fa fa-copy"></i> Salin SQL
    </button>
  </div>
  <?php endif; ?>

</div><!-- /.content -->


<!-- ══════════════════════════════════════════════
     MODAL TAMBAH / EDIT JABATAN
══════════════════════════════════════════════ -->
<div class="modal-ov" id="m-jabatan">
  <div class="modal-box">
    <div class="modal-hd">
      <h5 id="modal-title"><i class="fa fa-plus-circle text-primary"></i> Tambah Jabatan</h5>
      <button class="mc" onclick="closeModal('m-jabatan')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" id="form-jabatan">
      <input type="hidden" name="action" value="tambah" id="f-action">
      <input type="hidden" name="id"     value=""       id="f-id">

      <div class="modal-bd">

        <div class="form-row">
          <div class="form-group" style="flex:2;">
            <label>Nama Jabatan <span class="req">*</span></label>
            <input type="text" name="nama" id="f-nama" class="form-control"
                   placeholder="Contoh: Staff IT, Kepala Bagian…" required>
          </div>
          <div class="form-group">
            <label>Kode <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
            <input type="text" name="kode" id="f-kode" class="form-control"
                   placeholder="MGR / STF / SPV" maxlength="20"
                   style="text-transform:uppercase;font-family:monospace;font-weight:700;letter-spacing:1px;">
          </div>
        </div>

        <div class="form-group">
          <label>Deskripsi <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
          <textarea name="deskripsi" id="f-desc" class="form-control"
                    placeholder="Uraian singkat tanggung jawab jabatan…" rows="2"
                    style="resize:vertical;"></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Level</label>
            <select name="level" id="f-level" class="form-control">
              <?php foreach ($level_labels as $lv => $ll): ?>
              <option value="<?= $lv ?>"><?= $lv ?> — <?= $ll ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Urutan Tampil</label>
            <input type="number" name="urutan" id="f-urutan" class="form-control" value="0" min="0">
            <div class="form-hint">Angka kecil tampil lebih atas.</div>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="f-status" class="form-control">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>
        </div>

        <!-- Info level -->
        <div style="background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;padding:10px 14px;">
          <div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">
            <i class="fa fa-lightbulb" style="color:#f59e0b;"></i> Keterangan Level
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 12px;">
            <?php foreach ($level_labels as $lv => $ll):
              [$lbg, $ltc] = $level_colors[$lv];
            ?>
            <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#374151;">
              <span style="font-weight:800;padding:1px 7px;border-radius:4px;background:<?= $lbg ?>;color:<?= $ltc ?>;font-size:10px;"><?= $lv ?></span>
              <?= $ll ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /modal-bd -->

      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-jabatan')">Batal</button>
        <button type="submit" class="btn btn-primary" id="f-btn">
          <i class="fa fa-save"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>


<script>
/* ── Mode Tambah ─────────────────────────────────── */
function setAdd() {
  document.getElementById('f-action').value  = 'tambah';
  document.getElementById('f-id').value      = '';
  document.getElementById('f-nama').value    = '';
  document.getElementById('f-kode').value    = '';
  document.getElementById('f-desc').value    = '';
  document.getElementById('f-level').value   = '1';
  document.getElementById('f-urutan').value  = '0';
  document.getElementById('f-status').value  = 'aktif';
  document.getElementById('modal-title').innerHTML =
    '<i class="fa fa-plus-circle" style="color:var(--primary);"></i> Tambah Jabatan';
  document.getElementById('f-btn').innerHTML =
    '<i class="fa fa-save"></i> Simpan';
}

/* ── Mode Edit ───────────────────────────────────── */
function setEdit(b) {
  document.getElementById('f-action').value  = 'edit';
  document.getElementById('f-id').value      = b.id;
  document.getElementById('f-nama').value    = b.nama;
  document.getElementById('f-kode').value    = b.kode      || '';
  document.getElementById('f-desc').value    = b.deskripsi || '';
  document.getElementById('f-level').value   = b.level;
  document.getElementById('f-urutan').value  = b.urutan;
  document.getElementById('f-status').value  = b.status;
  document.getElementById('modal-title').innerHTML =
    '<i class="fa fa-edit" style="color:var(--orange);"></i> Edit: ' + b.nama;
  document.getElementById('f-btn').innerHTML =
    '<i class="fa fa-save"></i> Update';
}

/* ── Auto uppercase kode ─────────────────────────── */
document.getElementById('f-kode').addEventListener('input', function () {
  this.value = this.value.toUpperCase();
});

/* ── Salin SQL ───────────────────────────────────── */
function copySql(e) {
  var el  = document.getElementById('jb-sql');
  var btn = e.target.closest('button');
  if (!el || !btn) return;
  navigator.clipboard.writeText(el.textContent.trim()).then(function () {
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-check"></i> Disalin!';
    setTimeout(function () { btn.innerHTML = orig; }, 2000);
  });
}
</script>

<?php include '../includes/footer.php'; ?>