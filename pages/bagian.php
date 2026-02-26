<?php
// pages/bagian.php — Master Bagian / Divisi / Departemen
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole('admin')) { setFlash('danger', 'Akses ditolak. Hanya Admin.'); redirect(APP_URL . '/dashboard.php'); }
$page_title  = 'Master Bagian';
$active_menu = 'bagian';

// ── POST ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['action'] ?? '';
    $nama   = trim($_POST['nama']    ?? '');
    $kode   = strtoupper(trim($_POST['kode'] ?? ''));
    $desc   = trim($_POST['deskripsi'] ?? '');
    $lokasi = trim($_POST['lokasi']  ?? '');
    $urutan = (int)($_POST['urutan'] ?? 0);
    $status = $_POST['status'] ?? 'aktif';

    if ($act === 'tambah') {
        if (!$nama) { setFlash('danger', 'Nama bagian wajib diisi.'); }
        else {
            // Cek duplikat nama
            $chk = $pdo->prepare("SELECT id FROM bagian WHERE nama=?");
            $chk->execute([$nama]);
            if ($chk->fetch()) { setFlash('warning', "Bagian '$nama' sudah ada."); }
            else {
                $pdo->prepare("INSERT INTO bagian (nama,kode,deskripsi,lokasi,urutan,status) VALUES (?,?,?,?,?,?)")
                    ->execute([$nama, $kode ?: null, $desc ?: null, $lokasi ?: null, $urutan, $status]);
                setFlash('success', "Bagian <strong>$nama</strong> berhasil ditambahkan.");
            }
        }
    }

    elseif ($act === 'edit') {
        $id = (int)$_POST['id'];
        if (!$nama || !$id) { setFlash('danger', 'Data tidak lengkap.'); }
        else {
            $pdo->prepare("UPDATE bagian SET nama=?,kode=?,deskripsi=?,lokasi=?,urutan=?,status=? WHERE id=?")
                ->execute([$nama, $kode ?: null, $desc ?: null, $lokasi ?: null, $urutan, $status, $id]);
            setFlash('success', "Bagian <strong>$nama</strong> berhasil diperbarui.");
        }
    }

    elseif ($act === 'hapus') {
        $id = (int)$_POST['id'];
        // Cek apakah ada user yang pakai bagian ini
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE divisi=(SELECT nama FROM bagian WHERE id=?)");
        $chk->execute([$id]);
        if ($chk->fetchColumn() > 0) {
            setFlash('warning', 'Bagian tidak dapat dihapus karena masih digunakan oleh pengguna.');
        } else {
            $pdo->prepare("DELETE FROM bagian WHERE id=?")->execute([$id]);
            setFlash('success', 'Bagian berhasil dihapus.');
        }
    }

    elseif ($act === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE bagian SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
        setFlash('success', 'Status bagian diubah.');
    }

    redirect(APP_URL . '/pages/bagian.php');
}

// ── FETCH DATA ──
$list = $pdo->query("
    SELECT b.*, 
           COUNT(u.id) as jml_user
    FROM bagian b
    LEFT JOIN users u ON u.divisi = b.nama AND u.status='aktif'
    GROUP BY b.id
    ORDER BY b.urutan ASC, b.nama ASC
")->fetchAll();

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-building text-primary"></i> &nbsp;Master Bagian / Divisi</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
    <span class="cur">Master Bagian</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <div class="g2">

    <!-- ── TABEL DAFTAR ── -->
    <div class="panel">
      <div class="panel-hd">
        <h5><i class="fa fa-list text-primary"></i> &nbsp;Daftar Bagian <span style="color:#aaa;font-weight:400;">(<?= count($list) ?>)</span></h5>
      </div>
      <div class="panel-bd np tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Urutan</th>
              <th>Nama Bagian</th>
              <th>Kode</th>
              <th>Lokasi</th>
              <th>Pengguna</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($list)): ?>
            <tr><td colspan="7" class="td-empty"><i class="fa fa-building"></i> Belum ada data bagian</td></tr>
            <?php else: foreach ($list as $b): ?>
            <tr>
              <td style="text-align:center;color:#bbb;font-size:12px;"><?= $b['urutan'] ?></td>
              <td>
                <strong style="font-size:13px;"><?= clean($b['nama']) ?></strong>
                <?php if ($b['deskripsi']): ?>
                <br><small style="color:#aaa;"><?= clean($b['deskripsi']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($b['kode']): ?>
                <span style="font-family:monospace;font-size:11px;font-weight:700;background:#f0f0f0;padding:2px 7px;border-radius:3px;color:#555;"><?= clean($b['kode']) ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td style="font-size:11px;color:#888;"><?= clean($b['lokasi'] ?? '—') ?></td>
              <td style="text-align:center;">
                <span style="font-weight:700;color:<?= $b['jml_user']>0?'var(--primary)':'#bbb' ?>;">
                  <i class="fa fa-users" style="font-size:10px;"></i> <?= $b['jml_user'] ?>
                </span>
              </td>
              <td>
                <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;
                  background:<?= $b['status']==='aktif'?'#d1fae5':'#f3f4f6' ?>;
                  color:<?= $b['status']==='aktif'?'#065f46':'#6b7280' ?>;">
                  <?= $b['status']==='aktif' ? 'Aktif' : 'Nonaktif' ?>
                </span>
              </td>
              <td style="white-space:nowrap;">
                <button class="btn btn-warning btn-sm" onclick='editBagian(<?= json_encode($b) ?>)' title="Edit">
                  <i class="fa fa-edit"></i>
                </button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $b['status']==='aktif'?'btn-default':'btn-success' ?>" title="<?= $b['status']==='aktif'?'Nonaktifkan':'Aktifkan' ?>">
                    <i class="fa <?= $b['status']==='aktif'?'fa-eye-slash':'fa-eye' ?>"></i>
                  </button>
                </form>
                <?php if ($b['jml_user'] == 0): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus bagian <?= clean($b['nama']) ?>?')">
                  <input type="hidden" name="action" value="hapus">
                  <input type="hidden" name="id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" title="Hapus"><i class="fa fa-trash"></i></button>
                </form>
                <?php else: ?>
                <button class="btn btn-sm btn-default" disabled title="Tidak bisa dihapus, ada <?= $b['jml_user'] ?> pengguna"><i class="fa fa-lock" style="color:#bbb;"></i></button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── FORM TAMBAH / EDIT ── -->
    <div class="panel">
      <div class="panel-hd">
        <h5 id="form-title"><i class="fa fa-plus-circle text-primary"></i> &nbsp;Tambah Bagian</h5>
      </div>
      <div class="panel-bd">
        <form method="POST" id="form-bagian">
          <input type="hidden" name="action" value="tambah" id="f-action">
          <input type="hidden" name="id"     value=""        id="f-id">

          <div class="form-group">
            <label>Nama Bagian <span class="req">*</span></label>
            <input type="text" name="nama" id="f-nama" class="form-control"
                   placeholder="Contoh: Keuangan, Marketing, HRD..." required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Kode Singkat</label>
              <input type="text" name="kode" id="f-kode" class="form-control"
                     placeholder="Contoh: FIN, MKT, HRD" maxlength="10"
                     style="text-transform:uppercase;">
              <div class="form-hint">Opsional. Max 10 huruf.</div>
            </div>
            <div class="form-group">
              <label>Urutan Tampil</label>
              <input type="number" name="urutan" id="f-urutan" class="form-control"
                     value="0" min="0" placeholder="0 = paling atas">
              <div class="form-hint">Angka kecil tampil lebih atas.</div>
            </div>
          </div>

          <div class="form-group">
            <label>Deskripsi</label>
            <textarea name="deskripsi" id="f-desc" class="form-control"
                      placeholder="Deskripsi singkat fungsi bagian ini..." rows="2"></textarea>
          </div>

          <div class="form-group">
            <label>Lokasi / Ruangan Utama</label>
            <input type="text" name="lokasi" id="f-lokasi" class="form-control"
                   placeholder="Contoh: Lt.2, Gedung A, Ruang Marketing">
            <div class="form-hint">Akan ditampilkan saat user pilih bagian ini.</div>
          </div>

          <div class="form-group">
            <label>Status</label>
            <select name="status" id="f-status" class="form-control">
              <option value="aktif">Aktif — Tampil di dropdown</option>
              <option value="nonaktif">Nonaktif — Disembunyikan</option>
            </select>
          </div>

          <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary" id="f-btn">
              <i class="fa fa-save"></i> Simpan
            </button>
            <button type="button" class="btn btn-default" onclick="resetForm()">
              <i class="fa fa-times"></i> Reset
            </button>
          </div>
        </form>

        <!-- Info -->
        <div style="margin-top:18px;padding:12px;background:#f9f9f9;border-radius:4px;border:1px dashed #e0e0e0;">
          <p style="font-size:11px;color:#888;line-height:1.8;">
            <i class="fa fa-lightbulb" style="color:var(--yellow);"></i>
            <strong>Tips penggunaan:</strong><br>
            &bull; Bagian aktif akan muncul di dropdown saat <strong>Register</strong>, <strong>Edit Profil</strong>, dan <strong>Tambah Pengguna</strong>.<br>
            &bull; Bagian yang sudah dipakai pengguna tidak bisa dihapus.<br>
            &bull; Atur urutan tampil dengan angka di kolom <em>Urutan</em>.<br>
            &bull; Nonaktifkan bagian agar tidak tampil tanpa harus menghapus.
          </p>
        </div>
      </div>
    </div>

  </div><!-- /.g2 -->
</div><!-- /.content -->

<script>
function editBagian(b) {
  document.getElementById('f-action').value  = 'edit';
  document.getElementById('f-id').value      = b.id;
  document.getElementById('f-nama').value    = b.nama;
  document.getElementById('f-kode').value    = b.kode   || '';
  document.getElementById('f-desc').value    = b.deskripsi || '';
  document.getElementById('f-lokasi').value  = b.lokasi || '';
  document.getElementById('f-urutan').value  = b.urutan;
  document.getElementById('f-status').value  = b.status;

  document.getElementById('form-title').innerHTML =
    '<i class="fa fa-edit" style="color:var(--orange);"></i> &nbsp;Edit: ' + b.nama;
  document.getElementById('f-btn').innerHTML =
    '<i class="fa fa-save"></i> Update';

  document.getElementById('form-bagian').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
  document.getElementById('f-action').value  = 'tambah';
  document.getElementById('f-id').value      = '';
  document.getElementById('f-nama').value    = '';
  document.getElementById('f-kode').value    = '';
  document.getElementById('f-desc').value    = '';
  document.getElementById('f-lokasi').value  = '';
  document.getElementById('f-urutan').value  = '0';
  document.getElementById('f-status').value  = 'aktif';

  document.getElementById('form-title').innerHTML =
    '<i class="fa fa-plus-circle" style="color:var(--primary);"></i> &nbsp;Tambah Bagian';
  document.getElementById('f-btn').innerHTML =
    '<i class="fa fa-save"></i> Simpan';
}

// Auto uppercase kode
document.getElementById('f-kode').addEventListener('input', function() {
  this.value = this.value.toUpperCase();
});
</script>

<?php include '../includes/footer.php'; ?>
