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
    $nama   = trim($_POST['nama']      ?? '');
    $kode   = strtoupper(trim($_POST['kode'] ?? ''));
    $desc   = trim($_POST['deskripsi'] ?? '');
    $lokasi = trim($_POST['lokasi']    ?? '');
    $urutan = (int)($_POST['urutan']   ?? 0);
    $status = $_POST['status'] ?? 'aktif';

    if ($act === 'tambah') {
        if (!$nama) { setFlash('danger', 'Nama bagian wajib diisi.'); }
        else {
            $chk = $pdo->prepare("SELECT id FROM bagian WHERE nama=?");
            $chk->execute([$nama]);
            if ($chk->fetch()) { setFlash('warning', "Bagian '<strong>$nama</strong>' sudah ada."); }
            else {
                $pdo->prepare("INSERT INTO bagian (nama,kode,deskripsi,lokasi,urutan,status) VALUES (?,?,?,?,?,?)")
                    ->execute([$nama, $kode ?: null, $desc ?: null, $lokasi ?: null, $urutan, $status]);
                setFlash('success', "Bagian <strong>$nama</strong> berhasil ditambahkan.");
            }
        }
    } elseif ($act === 'edit') {
        $id = (int)$_POST['id'];
        if (!$nama || !$id) { setFlash('danger', 'Data tidak lengkap.'); }
        else {
            $pdo->prepare("UPDATE bagian SET nama=?,kode=?,deskripsi=?,lokasi=?,urutan=?,status=? WHERE id=?")
                ->execute([$nama, $kode ?: null, $desc ?: null, $lokasi ?: null, $urutan, $status, $id]);
            setFlash('success', "Bagian <strong>$nama</strong> berhasil diperbarui.");
        }
    } elseif ($act === 'hapus') {
        $id = (int)$_POST['id'];
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE divisi=(SELECT nama FROM bagian WHERE id=?)");
        $chk->execute([$id]);
        if ($chk->fetchColumn() > 0) {
            setFlash('warning', 'Bagian tidak dapat dihapus karena masih digunakan oleh pengguna.');
        } else {
            $pdo->prepare("DELETE FROM bagian WHERE id=?")->execute([$id]);
            setFlash('success', 'Bagian berhasil dihapus.');
        }
    } elseif ($act === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE bagian SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
        setFlash('success', 'Status bagian diubah.');
    }

    redirect(APP_URL . '/pages/bagian.php');
}

// ── FETCH DATA ──
$list = $pdo->query("
    SELECT b.*, COUNT(u.id) as jml_user
    FROM bagian b
    LEFT JOIN users u ON u.divisi = b.nama AND u.status='aktif'
    GROUP BY b.id
    ORDER BY b.urutan ASC, b.nama ASC
")->fetchAll();

// Stats
$total     = count($list);
$aktif     = count(array_filter($list, fn($b) => $b['status'] === 'aktif'));
$nonaktif  = $total - $aktif;
$total_usr = array_sum(array_column($list, 'jml_user'));

// Palette warna per baris
$palettes = [
    ['#dbeafe','#1d4ed8'],
    ['#d1fae5','#065f46'],
    ['#fef3c7','#d97706'],
    ['#ede9fe','#5b21b6'],
    ['#fce7f3','#9d174d'],
    ['#f0fdf4','#15803d'],
    ['#fff7ed','#c2410c'],
    ['#f0f9ff','#0369a1'],
];

include '../includes/header.php';
?>

<style>
/* ── Modal bulletproof ── */
#bagianModal {
    visibility: hidden !important;
    opacity: 0 !important;
    position: fixed !important;
    top: 0 !important; left: 0 !important;
    width: 100% !important; height: 100% !important;
    background: rgba(0,0,0,.5) !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: opacity .22s ease, visibility .22s ease !important;
    pointer-events: none !important;
}
#bagianModal.bm-open {
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}
#bagianModal .bm-box {
    background: #fff !important;
    border-radius: 12px !important;
    width: 100% !important;
    max-width: 500px !important;
    margin: 16px !important;
    box-shadow: 0 12px 48px rgba(0,0,0,.22) !important;
    overflow: hidden !important;
    transform: translateY(22px) scale(.98);
    transition: transform .22s ease !important;
    max-height: 90vh !important;
    display: flex !important;
    flex-direction: column !important;
}
#bagianModal.bm-open .bm-box {
    transform: translateY(0) scale(1) !important;
}
#bagianModal .bm-hd {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 16px 22px !important;
    border-bottom: 1px solid #f0f0f0 !important;
    background: #fafafa !important;
    flex-shrink: 0 !important;
}
#bagianModal .bm-hd h5 {
    margin: 0 !important; font-size: 15px !important;
    font-weight: 700 !important; color: #1e293b !important;
    display: flex !important; align-items: center !important; gap: 8px !important;
}
#bagianModal .bm-close {
    background: none !important; border: none !important;
    font-size: 22px !important; line-height: 1 !important; color: #94a3b8 !important;
    cursor: pointer !important; padding: 0 !important;
    width: 32px !important; height: 32px !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    border-radius: 6px !important; transition: background .15s, color .15s !important;
}
#bagianModal .bm-close:hover { background: #fee2e2 !important; color: #dc2626 !important; }
#bagianModal .bm-body {
    padding: 22px !important;
    overflow-y: auto !important;
    flex: 1 !important;
}
#bagianModal .bm-foot {
    padding: 14px 22px !important;
    border-top: 1px solid #f0f0f0 !important;
    display: flex !important; gap: 8px !important;
    justify-content: flex-end !important;
    background: #fafafa !important;
    flex-shrink: 0 !important;
}

/* ── Tips box ── */
.tips-box {
    margin-top: 6px;
    padding: 11px 14px;
    background: #f8fafc;
    border: 1px dashed #e2e8f0;
    border-radius: 8px;
    font-size: 11px;
    color: #64748b;
    line-height: 1.9;
}
.form-hint { font-size: 11px; color: #9ca3af; margin-top: 3px; }
.sla-bar { display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:#475569; }
</style>

<div class="page-header">
    <h4><i class="fa fa-building text-primary"></i> &nbsp;Master Bagian / Divisi</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <span class="sep">/</span>
        <span class="cur">Master Bagian</span>
    </div>
</div>

<div class="content">
    <?= showFlash() ?>

    <!-- ── Stats Row ── -->
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
        <?php foreach([
            [$total,     'Total Bagian',    'fa-building',  '#f0fdf4','#00c896'],
            [$aktif,     'Aktif',           'fa-circle-check','#d1fae5','#065f46'],
            [$nonaktif,  'Nonaktif',        'fa-circle-xmark','#f3f4f6','#6b7280'],
            [$total_usr, 'Total Pengguna',  'fa-users',     '#dbeafe','#1d4ed8'],
        ] as [$val,$lbl,$ico,$bg,$clr]): ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:12px;min-width:140px;">
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

    <!-- ── Panel Tabel ── -->
    <div class="panel">
        <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;">
            <h5>
                <i class="fa fa-list text-primary"></i> &nbsp;Daftar Bagian
                <span style="color:#aaa;font-weight:400;">(<?= $total ?>)</span>
            </h5>
            <button class="btn btn-primary btn-sm" onclick="bmOpen()">
                <i class="fa fa-plus"></i> Tambah Bagian
            </button>
        </div>
        <div class="panel-bd np tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;text-align:center;">Urutan</th>
                        <th>Nama Bagian</th>
                        <th style="width:90px;text-align:center;">Kode</th>
                        <th style="width:160px;">Lokasi</th>
                        <th style="width:90px;text-align:center;">Pengguna</th>
                        <th style="width:100px;text-align:center;">Status</th>
                        <th style="width:120px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:#aaa;padding:40px 20px;">
                            <div style="width:52px;height:52px;background:#f1f5f9;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                <i class="fa fa-building" style="font-size:22px;color:#cbd5e1;"></i>
                            </div>
                            <div style="font-weight:600;color:#64748b;margin-bottom:4px;">Belum ada data bagian</div>
                            <small style="color:#94a3b8;">Klik <strong>+ Tambah Bagian</strong> untuk memulai.</small>
                        </td>
                    </tr>
                    <?php else: foreach ($list as $i => $b):
                        [$pal_bg, $pal_tc] = $palettes[$i % count($palettes)];
                        $is_aktif = $b['status'] === 'aktif';
                        $in_use   = (int)$b['jml_user'] > 0;
                    ?>
                    <tr>
                        <!-- Urutan -->
                        <td style="text-align:center;">
                            <span style="font-size:13px;font-weight:700;color:#d1d5db;"><?= (int)$b['urutan'] ?></span>
                        </td>

                        <!-- Nama + deskripsi + ikon warna -->
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;background:<?= $pal_bg ?>;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa fa-building" style="color:<?= $pal_tc ?>;font-size:14px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:13px;color:#111827;"><?= clean($b['nama']) ?></div>
                                    <?php if ($b['deskripsi']): ?>
                                    <div style="font-size:11px;color:#9ca3af;margin-top:1px;"><?= clean($b['deskripsi']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Kode -->
                        <td style="text-align:center;">
                            <?php if ($b['kode']): ?>
                            <span style="font-family:monospace;font-size:11px;font-weight:700;
                                         background:#f0fdf4;border:1px solid #bbf7d0;
                                         padding:2px 8px;border-radius:4px;color:#16a34a;letter-spacing:.5px;">
                                <?= clean($b['kode']) ?>
                            </span>
                            <?php else: ?>
                            <span style="color:#e5e7eb;font-size:13px;">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Lokasi -->
                        <td>
                            <?php if ($b['lokasi']): ?>
                            <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#64748b;">
                                <i class="fa fa-location-dot" style="color:#94a3b8;font-size:10px;"></i>
                                <?= clean($b['lokasi']) ?>
                            </div>
                            <?php else: ?>
                            <span style="color:#e5e7eb;font-size:13px;">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Pengguna -->
                        <td style="text-align:center;">
                            <?php if ($in_use): ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:#00c896;">
                                <i class="fa fa-users" style="font-size:10px;"></i> <?= $b['jml_user'] ?>
                            </span>
                            <?php else: ?>
                            <span style="color:#e2e8f0;font-size:12px;">0</span>
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
                                onclick='bmOpen(<?= json_encode([
                                    "id"        => (int)$b["id"],
                                    "nama"      => $b["nama"],
                                    "kode"      => $b["kode"] ?? "",
                                    "deskripsi" => $b["deskripsi"] ?? "",
                                    "lokasi"    => $b["lokasi"] ?? "",
                                    "urutan"    => (int)$b["urutan"],
                                    "status"    => $b["status"],
                                ]) ?>)'
                                title="Edit bagian">
                                <i class="fa fa-edit"></i>
                            </button>

                            <!-- Toggle status -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button type="submit"
                                    class="btn btn-sm <?= $is_aktif ? 'btn-default' : 'btn-success' ?>"
                                    title="<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                    onclick="return confirm('<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?> bagian ini?')">
                                    <i class="fa <?= $is_aktif ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                </button>
                            </form>

                            <!-- Hapus -->
                            <?php if (!$in_use): ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Hapus bagian <?= addslashes(clean($b['nama'])) ?>?')">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Hapus bagian">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm btn-default" disabled
                                title="Tidak bisa dihapus — masih ada <?= $b['jml_user'] ?> pengguna">
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

</div><!-- /.content -->


<!-- ══════════════════════════════════════════════
     MODAL TAMBAH / EDIT BAGIAN
══════════════════════════════════════════════ -->
<div id="bagianModal">
    <div class="bm-box">
        <div class="bm-hd">
            <h5>
                <i class="fa fa-building" id="bm-ico" style="color:var(--primary,#2563eb);"></i>
                <span id="bm-title">Tambah Bagian</span>
            </h5>
            <button class="bm-close" onclick="bmClose()" title="Tutup">&times;</button>
        </div>

        <div class="bm-body">
            <form method="POST" id="bm-form">
                <input type="hidden" name="action" value="tambah" id="f-action">
                <input type="hidden" name="id"     value=""        id="f-id">

                <!-- Nama -->
                <div class="form-group">
                    <label>Nama Bagian <span class="req">*</span></label>
                    <input type="text" name="nama" id="f-nama" class="form-control" required
                           placeholder="Contoh: Keuangan, Marketing, HRD…">
                </div>

                <!-- Kode & Urutan -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Kode Singkat <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
                        <input type="text" name="kode" id="f-kode" class="form-control"
                               placeholder="FIN / MKT / HRD" maxlength="10"
                               style="text-transform:uppercase;font-family:monospace;font-weight:700;letter-spacing:1px;">
                        <div class="form-hint">Maks. 10 huruf.</div>
                    </div>
                    <div class="form-group">
                        <label>Urutan Tampil</label>
                        <input type="number" name="urutan" id="f-urutan" class="form-control" value="0" min="0">
                        <div class="form-hint">Angka kecil tampil lebih atas.</div>
                    </div>
                </div>

                <!-- Deskripsi -->
                <div class="form-group">
                    <label>Deskripsi <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
                    <textarea name="deskripsi" id="f-desc" class="form-control" rows="2"
                              placeholder="Deskripsi singkat fungsi bagian ini…" style="resize:vertical;"></textarea>
                </div>

                <!-- Lokasi -->
                <div class="form-group">
                    <label>Lokasi / Ruangan Utama <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;">
                            <i class="fa fa-location-dot"></i>
                        </span>
                        <input type="text" name="lokasi" id="f-lokasi" class="form-control"
                               placeholder="Contoh: Lt.2, Gedung A, Ruang Marketing"
                               style="padding-left:30px;">
                    </div>
                    <div class="form-hint">Ditampilkan saat pengguna memilih bagian ini.</div>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="f-status" class="form-control">
                        <option value="aktif">Aktif — Tampil di dropdown</option>
                        <option value="nonaktif">Nonaktif — Disembunyikan</option>
                    </select>
                </div>

                <!-- Tips -->
                <div class="tips-box">
                    <i class="fa fa-lightbulb" style="color:#f59e0b;"></i>
                    <strong>Tips:</strong><br>
                    &bull; Bagian aktif muncul di dropdown Register, Edit Profil, dan Tambah Pengguna.<br>
                    &bull; Bagian yang sudah dipakai pengguna tidak bisa dihapus.<br>
                    &bull; Nonaktifkan bagian agar tidak tampil tanpa harus menghapus.
                </div>

            </form>
        </div>

        <div class="bm-foot">
            <button type="button" class="btn btn-default" onclick="bmClose()">
                <i class="fa fa-times"></i> Batal
            </button>
            <button type="submit" form="bm-form" class="btn btn-primary" id="bm-btn">
                <i class="fa fa-save"></i> Simpan
            </button>
        </div>
    </div>
</div>

<script>
var _bm = document.getElementById('bagianModal');

/* ── Buka modal ── */
function bmOpen(b) {
    if (b) {
        document.getElementById('bm-title').textContent = 'Edit: ' + b.nama;
        document.getElementById('bm-ico').style.color   = 'var(--orange,#f59e0b)';
        document.getElementById('f-action').value = 'edit';
        document.getElementById('f-id').value     = b.id;
        document.getElementById('f-nama').value   = b.nama;
        document.getElementById('f-kode').value   = b.kode      || '';
        document.getElementById('f-desc').value   = b.deskripsi || '';
        document.getElementById('f-lokasi').value = b.lokasi    || '';
        document.getElementById('f-urutan').value = b.urutan;
        document.getElementById('f-status').value = b.status;
        document.getElementById('bm-btn').innerHTML = '<i class="fa fa-save"></i> Update';
    } else {
        document.getElementById('bm-title').textContent = 'Tambah Bagian';
        document.getElementById('bm-ico').style.color   = 'var(--primary,#2563eb)';
        document.getElementById('f-action').value = 'tambah';
        document.getElementById('f-id').value     = '';
        document.getElementById('f-nama').value   = '';
        document.getElementById('f-kode').value   = '';
        document.getElementById('f-desc').value   = '';
        document.getElementById('f-lokasi').value = '';
        document.getElementById('f-urutan').value = '0';
        document.getElementById('f-status').value = 'aktif';
        document.getElementById('bm-btn').innerHTML = '<i class="fa fa-save"></i> Simpan';
    }
    _bm.classList.add('bm-open');
    setTimeout(function(){ document.getElementById('f-nama').focus(); }, 80);
}

/* ── Tutup modal ── */
function bmClose() { _bm.classList.remove('bm-open'); }
_bm.addEventListener('click', function(e){ if (e.target === _bm) bmClose(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') bmClose(); });

/* ── Auto uppercase kode ── */
document.getElementById('f-kode').addEventListener('input', function(){
    this.value = this.value.toUpperCase();
});
</script>

<?php include '../includes/footer.php'; ?>