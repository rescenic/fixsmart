<?php
// pages/input_dokumen.php
session_start();
require_once '../config.php';
requireLogin();
// Cek akses: admin, role akreditasi, ATAU flag is_akreditasi=1
if (!hasRole(['admin', 'akreditasi']) && (int)($_SESSION['is_akreditasi'] ?? 0) !== 1) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}
$page_title  = 'Input Dokumen Akreditasi';
$active_menu = 'input_dokumen';

$_cur_role   = $_SESSION['user_role'] ?? 'user';
$_uid        = (int)($_SESSION['user_id'] ?? 0);
$_is_admin   = ($_cur_role === 'admin');
$_pokja_id   = (int)($_SESSION['pokja_id'] ?? 0);

// ── Pastikan tabel & folder ada ─────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `dokumen_akreditasi` (
      `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `pokja_id`  INT UNSIGNED NOT NULL,
      `user_id`   INT UNSIGNED NOT NULL,
      `judul`     VARCHAR(255) NOT NULL,
      `nomor_doc` VARCHAR(100) DEFAULT NULL,
      `kategori`  VARCHAR(100) DEFAULT NULL,
      `file_path` VARCHAR(500) DEFAULT NULL,
      `file_name` VARCHAR(255) DEFAULT NULL,
      `file_size` INT UNSIGNED DEFAULT NULL,
      `keterangan` TEXT        DEFAULT NULL,
      `status`    ENUM('draft','aktif','kadaluarsa') DEFAULT 'draft',
      `tgl_terbit` DATE        DEFAULT NULL,
      `tgl_exp`   DATE         DEFAULT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY `idx_pokja`  (`pokja_id`),
      KEY `idx_user`   (`user_id`),
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Tambah kolom elemen_penilaian jika belum ada
    try {
        $pdo->exec("ALTER TABLE dokumen_akreditasi ADD COLUMN IF NOT EXISTS `elemen_penilaian` VARCHAR(255) DEFAULT NULL AFTER `kategori`");
    } catch (Exception $e) {}
} catch (Exception $e) {}

// Direktori upload dokumen
$upload_dir = dirname(__DIR__) . '/uploads/akreditasi/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// ── Daftar Pokja untuk dropdown ───────────────────────────────────────────
$pokja_list = [];
try {
    if ($_is_admin) {
        $pokja_list = $pdo->query("SELECT id,kode,nama FROM master_pokja WHERE status='aktif' ORDER BY urutan,kode")->fetchAll();
    } else {
        // Anggota hanya bisa input ke Pokja-nya sendiri
        $ps = $pdo->prepare("SELECT id,kode,nama FROM master_pokja WHERE id=? AND status='aktif'");
        $ps->execute([$_pokja_id]);
        $pokja_list = $ps->fetchAll();
    }
} catch (Exception $e) {}

// ── Kategori dokumen ─────────────────────────────────────────────────────────
$kategori_list = [
    'Kebijakan', 'Pedoman', 'Panduan', 'SPO', 'Program Kerja',
    'Laporan', 'Sertifikat', 'SK / Surat Keputusan',
    'Notulen / Risalah', 'Formulir', 'Lain-lain',
];

// ── POST Handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'simpan') {
        $pokja_id  = (int)($_POST['pokja_id']  ?? 0);
        $judul     = trim($_POST['judul']       ?? '');
        $nomor     = trim($_POST['nomor_doc']   ?? '');
        $kat       = trim($_POST['kategori']    ?? '');
        $ket       = trim($_POST['keterangan']  ?? '');
        $status     = 'aktif';
        $tgl_terbit = null;
        $tgl_exp    = null;

        // Validasi: akreditasi hanya boleh input ke pokja sendiri
        if (!$_is_admin && $pokja_id !== $_pokja_id) {
            setFlash('danger', 'Anda hanya dapat menginput dokumen untuk Pokja Anda sendiri.');
            redirect(APP_URL . '/pages/input_dokumen.php');
        }

        if (!$pokja_id || !$judul) {
            setFlash('danger', 'Pokja dan Judul Dokumen wajib diisi.');
            redirect(APP_URL . '/pages/input_dokumen.php');
        }

        // Upload file
        $file_path = null;
        $file_name = null;
        $file_size = null;

        if (!empty($_FILES['file']['name'])) {
            $orig      = $_FILES['file']['name'];
            $tmp       = $_FILES['file']['tmp_name'];
            $size      = $_FILES['file']['size'];
            $ext       = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allowed   = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','zip'];

            if (!in_array($ext, $allowed)) {
                setFlash('danger', 'Tipe file tidak diizinkan. Gunakan: ' . implode(', ', $allowed));
                redirect(APP_URL . '/pages/input_dokumen.php');
            }
            if ($size > 20 * 1024 * 1024) { // 20 MB
                setFlash('danger', 'Ukuran file maksimal 20 MB.');
                redirect(APP_URL . '/pages/input_dokumen.php');
            }

            $safe_name = date('Ymd_His') . '_' . $pokja_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
            $dest      = $upload_dir . $safe_name;

            if (move_uploaded_file($tmp, $dest)) {
                $file_path = 'uploads/akreditasi/' . $safe_name;
                $file_name = $orig;
                $file_size = $size;
            } else {
                setFlash('danger', 'Gagal mengupload file. Periksa permission folder.');
                redirect(APP_URL . '/pages/input_dokumen.php');
            }
        }

        $pdo->prepare("INSERT INTO dokumen_akreditasi
            (pokja_id,user_id,judul,nomor_doc,kategori,elemen_penilaian,file_path,file_name,file_size,keterangan,status,tgl_terbit,tgl_exp)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $pokja_id, $_uid, $judul,
                $nomor ?: null, $kat ?: null, $elemen_penilaian ?: null,
                $file_path, $file_name, $file_size ?: null,
                $ket ?: null, $status, $tgl_terbit, $tgl_exp,
            ]);

        setFlash('success', "Dokumen <strong>" . clean($judul) . "</strong> berhasil disimpan.");
        redirect(APP_URL . '/pages/data_dokumen.php');
    }
}

include '../includes/header.php';
?>

<style>
.upload-zone {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 28px 20px;
  text-align: center;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  background: #fafafa;
  position: relative;
}
.upload-zone:hover, .upload-zone.dragover {
  border-color: #00e5b0;
  background: #f0fdf4;
}
.upload-zone input[type=file] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
  width: 100%;
  height: 100%;
}
.file-preview {
  display: none;
  align-items: center;
  gap: 10px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 10px 14px;
  margin-top: 10px;
  font-size: 12.5px;
  color: #065f46;
}
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 20px;
}
</style>

<div class="page-header">
  <h4><i class="fa fa-file-arrow-up text-primary"></i> &nbsp;Input Dokumen Akreditasi</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <a href="<?= APP_URL ?>/pages/data_dokumen.php">Akreditasi</a>
    <span class="sep">/</span>
    <span class="cur">Input Dokumen</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <?php if (empty($pokja_list)): ?>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:18px 20px;
              display:flex;align-items:center;gap:12px;color:#92400e;font-size:13px;">
    <i class="fa fa-triangle-exclamation" style="font-size:20px;color:#f59e0b;flex-shrink:0;"></i>
    <div>
      <?php if ($_is_admin): ?>
        Belum ada Pokja yang aktif. <a href="<?= APP_URL ?>/pages/master_pokja.php" style="color:#b45309;font-weight:700;">Buat Pokja terlebih dahulu.</a>
      <?php else: ?>
        Anda belum ditugaskan ke Pokja manapun. Hubungi Admin untuk penugasan Pokja.
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>

  <!-- Info Pokja untuk anggota -->
  <?php if (!$_is_admin && !empty($pokja_list[0])): ?>
  <?php $my_pokja = $pokja_list[0]; ?>
  <div style="background:linear-gradient(135deg,#fef9c3,#fefce8);border:1px solid #fde047;
              border-radius:10px;padding:14px 18px;margin-bottom:20px;
              display:flex;align-items:center;gap:12px;">
    <div style="width:40px;height:40px;border-radius:10px;background:#fef9c3;border:1.5px solid #fde047;
                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa fa-medal" style="color:#854d0e;font-size:18px;"></i>
    </div>
    <div>
      <div style="font-size:12px;font-weight:700;color:#854d0e;">Pokja Anda</div>
      <div style="font-size:14px;font-weight:800;color:#1e293b;margin-top:1px;">
        <span style="font-family:monospace;background:#fef9c3;padding:1px 7px;border-radius:4px;
                     font-size:12px;color:#854d0e;margin-right:6px;"><?= clean($my_pokja['kode']) ?></span>
        <?= clean($my_pokja['nama']) ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-file-arrow-up"></i> Form Input Dokumen</h5>
      <a href="<?= APP_URL ?>/pages/data_dokumen.php" class="btn btn-default btn-sm">
        <i class="fa fa-arrow-left"></i> Kembali ke Daftar
      </a>
    </div>
    <div class="panel-bd" style="padding:24px;">
      <form method="POST" enctype="multipart/form-data" id="form-dokumen">
        <input type="hidden" name="action" value="simpan">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

          <!-- Pokja -->
          <div class="form-group" style="<?= !$_is_admin ? 'pointer-events:none;opacity:.75;' : '' ?>">
            <label>Pokja <span class="req">*</span></label>
            <select name="pokja_id" class="form-control" required>
              <option value="">— Pilih Pokja —</option>
              <?php foreach ($pokja_list as $pk): ?>
              <option value="<?= (int)$pk['id'] ?>"
                <?= (!$_is_admin && $pk['id'] == $_pokja_id) ? 'selected' : '' ?>>
                <?= clean($pk['kode']) ?> — <?= clean($pk['nama']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (!$_is_admin): ?>
            <small style="color:#9ca3af;font-size:10.5px;margin-top:3px;display:block;">
              <i class="fa fa-lock"></i> Otomatis sesuai Pokja Anda
            </small>
            <?php endif; ?>
          </div>

          <!-- Kategori Dokumen -->
          <div class="form-group">
            <label>Kategori Dokumen</label>
            <select name="kategori" class="form-control">
              <option value="">— Pilih Kategori —</option>
              <?php foreach ($kategori_list as $kat): ?>
              <option value="<?= $kat ?>"><?= $kat ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Elemen Penilaian | Judul Dokumen -->
          <div class="form-group">
            <label>Elemen Penilaian</label>
            <input type="text" name="elemen_penilaian" class="form-control"
                   placeholder="Contoh: EP 1.A, TKRS 1.1">
            <small style="color:#9ca3af;font-size:10.5px;margin-top:3px;display:block;">
              <i class="fa fa-info-circle"></i> Elemen penilaian yang terkait
            </small>
          </div>

          <div class="form-group">
            <label>Judul Dokumen <span class="req">*</span></label>
            <input type="text" name="judul" class="form-control"
                   placeholder="Contoh: SPO Pengelolaan Rekam Medis" required>
          </div>

          <!-- Nomor Dokumen | Keterangan -->
          <div class="form-group">
            <label>Nomor Dokumen
              <span style="font-size:10px;color:#9ca3af;font-weight:400;">(kosongkan jika tidak ada)</span>
            </label>
            <input type="text" name="nomor_doc" class="form-control"
                   placeholder="SPO/RM/001/2024">
          </div>

          <div class="form-group">
            <label>Keterangan</label>
            <textarea name="keterangan" class="form-control" rows="3"
                      placeholder="Catatan tambahan…"></textarea>
          </div>

          <!-- Upload File -->
          <div class="form-group" style="grid-column:span 2;">
            <label>Upload File <span style="font-size:10px;color:#9ca3af;">PDF, Word, Excel, PPT, Gambar, ZIP — maks. 20 MB</span></label>
            <div class="upload-zone" id="upload-zone">
              <input type="file" name="file" id="file-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
              <div id="upload-placeholder">
                <i class="fa fa-cloud-arrow-up" style="font-size:32px;color:#00e5b0;margin-bottom:8px;display:block;"></i>
                <div style="font-size:13px;font-weight:600;color:#374151;">Klik atau seret file ke sini</div>
                <div style="font-size:11px;color:#9ca3af;margin-top:4px;">
                  PDF, Word, Excel, PPT, Gambar, ZIP — Maks. 20 MB
                </div>
              </div>
            </div>
            <div class="file-preview" id="file-preview">
              <i class="fa fa-file-circle-check" style="font-size:22px;flex-shrink:0;"></i>
              <div style="flex:1;min-width:0;">
                <div id="file-name" style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
                <div id="file-size" style="font-size:11px;color:#059669;margin-top:1px;"></div>
              </div>
              <button type="button" onclick="clearFile()"
                      style="background:none;border:none;cursor:pointer;color:#dc2626;font-size:14px;flex-shrink:0;">
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>

        </div><!-- /grid -->

        <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;
                    border-top:1px solid #e5e7eb;margin-top:8px;">
          <a href="<?= APP_URL ?>/pages/data_dokumen.php" class="btn btn-default">
            <i class="fa fa-times"></i> Batal
          </a>
          <button type="submit" class="btn btn-primary" id="btn-simpan">
            <i class="fa fa-save"></i> Simpan Dokumen
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  const zone     = document.getElementById('upload-zone');
  const input    = document.getElementById('file-input');
  const preview  = document.getElementById('file-preview');
  const holder   = document.getElementById('upload-placeholder');
  const nameEl   = document.getElementById('file-name');
  const sizeEl   = document.getElementById('file-size');

  function formatSize(b) {
    if (b < 1024)       return b + ' B';
    if (b < 1048576)    return (b / 1024).toFixed(1) + ' KB';
    return (b / 1048576).toFixed(1) + ' MB';
  }

  function showPreview(file) {
    if (!file) return;
    nameEl.textContent = file.name;
    sizeEl.textContent = formatSize(file.size);
    preview.style.display = 'flex';
    holder.style.display  = 'none';
  }

  input.addEventListener('change', function () {
    if (this.files[0]) showPreview(this.files[0]);
  });

  ['dragenter','dragover'].forEach(ev => zone.addEventListener(ev, e => {
    e.preventDefault(); zone.classList.add('dragover');
  }));
  ['dragleave','drop'].forEach(ev => zone.addEventListener(ev, e => {
    e.preventDefault(); zone.classList.remove('dragover');
  }));
  zone.addEventListener('drop', function (e) {
    const f = e.dataTransfer.files[0];
    if (f) {
      // inject ke input
      const dt = new DataTransfer();
      dt.items.add(f);
      input.files = dt.files;
      showPreview(f);
    }
  });

  window.clearFile = function () {
    input.value = '';
    preview.style.display = 'none';
    holder.style.display  = 'block';
  };

  // Confirm sebelum submit
  document.getElementById('form-dokumen').addEventListener('submit', function (e) {
    const btn = document.getElementById('btn-simpan');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Menyimpan…';
  });
})();
</script>

<?php include '../includes/footer.php'; ?>