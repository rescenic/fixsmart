<?php
// pages/kategori_ipsrs.php
error_reporting(E_ALL); ini_set('display_errors', 1); // hapus setelah selesai debug
session_start(); require_once '../config.php'; requireLogin();
if (!hasRole(['admin','teknisi'])) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title = 'Kategori IPSRS'; $active_menu = 'kategori_ipsrs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action']          ?? '';
    $nama  = trim($_POST['nama']       ?? '');
    $desc  = trim($_POST['deskripsi']  ?? '');
    $icon  = $_POST['icon']            ?? 'fa-toolbox';
    $jenis = $_POST['jenis']           ?? 'Non-Medis'; // Medis / Non-Medis
    $sla   = (int)($_POST['sla_jam']         ?? 24);
    $sla_r = (int)($_POST['sla_respon_jam']  ?? 4);

    if ($act === 'tambah' && $nama) {
        $pdo->prepare("INSERT INTO kategori_ipsrs (nama, deskripsi, icon, jenis, sla_jam, sla_respon_jam)
                       VALUES (?,?,?,?,?,?)")
            ->execute([$nama, $desc, $icon, $jenis, $sla, $sla_r]);
        setFlash('success', 'Kategori IPSRS berhasil ditambahkan.');
    } elseif ($act === 'edit' && $nama) {
        $pdo->prepare("UPDATE kategori_ipsrs SET nama=?, deskripsi=?, icon=?, jenis=?, sla_jam=?, sla_respon_jam=? WHERE id=?")
            ->execute([$nama, $desc, $icon, $jenis, $sla, $sla_r, (int)$_POST['id']]);
        setFlash('success', 'Kategori IPSRS berhasil diperbarui.');
    } elseif ($act === 'hapus') {
        $cek = 0;
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM tiket_ipsrs WHERE kategori_id=?");
            $st->execute([(int)$_POST['id']]);
            $cek = (int)$st->fetchColumn();
        } catch (Exception $e) { $cek = 0; }
        if ($cek > 0) {
            setFlash('warning', 'Kategori tidak dapat dihapus, masih ada tiket yang menggunakan kategori ini.');
        } else {
            $pdo->prepare("DELETE FROM kategori_ipsrs WHERE id=?")->execute([(int)$_POST['id']]);
            setFlash('success', 'Kategori IPSRS berhasil dihapus.');
        }
    }
    redirect(APP_URL . '/pages/kategori_ipsrs.php');
}

// Query aman — fallback jika tabel tiket_ipsrs belum ada
try {
    $list = $pdo->query("
        SELECT k.*, COUNT(t.id) AS total
        FROM kategori_ipsrs k
        LEFT JOIN tiket_ipsrs t ON t.kategori_id = k.id
        GROUP BY k.id
        ORDER BY k.jenis, k.nama
    ")->fetchAll();
} catch (Exception $e) {
    $list = $pdo->query("
        SELECT k.*, 0 AS total
        FROM kategori_ipsrs k
        ORDER BY k.jenis, k.nama
    ")->fetchAll();
}

// Ikon yang relevan untuk IPSRS (medis & non-medis)
$icons_medis = [
    'fa-kit-medical'      => 'Medis Umum',
    'fa-heart-pulse'      => 'Jantung / Monitor',
    'fa-lungs'            => 'Paru / Ventilator',
    'fa-flask-vial'       => 'Laboratorium',
    'fa-radiation'        => 'Radiologi',
    'fa-syringe'          => 'Infus / Suntik',
    'fa-stethoscope'      => 'Diagnostik',
    'fa-tooth'            => 'Peralatan Gigi',
    'fa-eye'              => 'Peralatan Mata',
    'fa-baby'             => 'Inkubator / Bayi',
    'fa-bandage'          => 'Bedah',
];
$icons_non_medis = [
    'fa-toolbox'          => 'Umum / Servis',
    'fa-bolt'             => 'Listrik',
    'fa-plug-circle-bolt' => 'Generator / Panel',
    'fa-wind'             => 'HVAC / AC',
    'fa-droplet'          => 'Sanitasi / Plumbing',
    'fa-fire-extinguisher'=> 'Proteksi Kebakaran',
    'fa-utensils'         => 'Dapur / Gizi',
    'fa-shirt'            => 'Laundry',
    'fa-broom'            => 'Kebersihan',
    'fa-car'              => 'Kendaraan / Ambulans',
    'fa-dolly'            => 'Alat Angkat',
    'fa-gear'             => 'Mekanikal',
    'fa-camera'           => 'Keamanan / CCTV',
    'fa-tag'              => 'Lainnya',
];
$all_icons = array_merge(array_keys($icons_medis), array_keys($icons_non_medis));

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-tags" style="color:var(--primary);"></i> &nbsp;Kategori IPSRS</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Kategori IPSRS</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <div class="g2">

    <!-- ══ TABEL DAFTAR KATEGORI ══ -->
    <div class="panel">
      <div class="panel-hd">
        <h5>Daftar Kategori IPSRS</h5>
        <div style="display:flex;gap:6px;">
          <button onclick="filterJenis('')"   id="fb-all"   class="btn btn-primary  btn-sm">Semua</button>
          <button onclick="filterJenis('Medis')"     id="fb-medis" class="btn btn-default btn-sm">🏥 Medis</button>
          <button onclick="filterJenis('Non-Medis')" id="fb-non"   class="btn btn-default btn-sm">🔧 Non-Medis</button>
        </div>
      </div>
      <div class="panel-bd np tbl-wrap">
        <table id="tbl-kat">
          <thead>
            <tr>
              <th>Ikon</th>
              <th>Nama Kategori</th>
              <th>Jenis</th>
              <th>Target SLA</th>
              <th>Target Respon</th>
              <th>Tiket</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($list)): ?>
            <tr><td colspan="7" class="td-empty"><i class="fa fa-tags"></i> Belum ada kategori IPSRS</td></tr>
            <?php else: foreach ($list as $k): ?>
            <tr data-jenis="<?= clean($k['jenis']) ?>">
              <td>
                <i class="fa <?= clean($k['icon']) ?>" style="color:var(--primary);font-size:16px;"></i>
              </td>
              <td>
                <strong><?= clean($k['nama']) ?></strong>
                <?php if ($k['deskripsi']): ?>
                <br><small style="color:#aaa;"><?= clean($k['deskripsi']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($k['jenis'] === 'Medis'): ?>
                  <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;font-size:10.5px;font-weight:700;background:#fce7f3;color:#9d174d;">
                    <i class="fa fa-kit-medical" style="font-size:10px;"></i> Medis
                  </span>
                <?php else: ?>
                  <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;font-size:10.5px;font-weight:700;background:#eff6ff;color:#1e40af;">
                    <i class="fa fa-screwdriver-wrench" style="font-size:10px;"></i> Non-Medis
                  </span>
                <?php endif; ?>
              </td>
              <td><?= $k['sla_jam'] ?> jam</td>
              <td><?= $k['sla_respon_jam'] ?> jam</td>
              <td><span style="font-weight:700;"><?= $k['total'] ?></span></td>
              <td>
                <button class="btn btn-warning btn-sm" onclick='editKat(<?= json_encode($k) ?>)' title="Edit">
                  <i class="fa fa-edit"></i>
                </button>
                <?php if (!$k['total']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="hapus">
                  <input type="hidden" name="id"     value="<?= $k['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus kategori ini?')" title="Hapus">
                    <i class="fa fa-trash"></i>
                  </button>
                </form>
                <?php else: ?>
                <button class="btn btn-default btn-sm" disabled title="Tidak bisa dihapus, ada tiket">
                  <i class="fa fa-trash"></i>
                </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ FORM TAMBAH / EDIT ══ -->
    <div class="panel">
      <div class="panel-hd">
        <h5 id="form-title">Tambah Kategori IPSRS</h5>
      </div>
      <div class="panel-bd">
        <form method="POST" id="kf">
          <input type="hidden" name="action" value="tambah" id="fa">
          <input type="hidden" name="id"     value=""        id="fid">

          <!-- Nama -->
          <div class="form-group">
            <label>Nama Kategori <span class="req">*</span></label>
            <input type="text" name="nama" id="fn" class="form-control" required placeholder="Contoh: Ventilator, Pompa Air, HVAC">
          </div>

          <!-- Deskripsi -->
          <div class="form-group">
            <label>Deskripsi</label>
            <textarea name="deskripsi" id="fd" class="form-control" rows="2" placeholder="Keterangan singkat kategori ini…"></textarea>
          </div>

          <!-- Jenis (Medis / Non-Medis) -->
          <div class="form-group">
            <label>Jenis <span class="req">*</span></label>
            <div style="display:flex;gap:8px;">
              <button type="button" id="btn-jenis-medis" class="btn btn-default btn-sm" onclick="setJenis('Medis')"
                style="flex:1;padding:8px;border-radius:6px;transition:all .18s;">
                🏥 Medis
              </button>
              <button type="button" id="btn-jenis-non" class="btn btn-primary btn-sm" onclick="setJenis('Non-Medis')"
                style="flex:1;padding:8px;border-radius:6px;transition:all .18s;">
                🔧 Non-Medis
              </button>
            </div>
            <input type="hidden" name="jenis" id="fj" value="Non-Medis">
          </div>

          <!-- SLA -->
          <div class="form-row">
            <div class="form-group">
              <label>Target SLA (jam)</label>
              <input type="number" name="sla_jam" id="fs" class="form-control" value="24" min="1">
            </div>
            <div class="form-group">
              <label>Target Respon (jam)</label>
              <input type="number" name="sla_respon_jam" id="fr" class="form-control" value="4" min="1">
            </div>
          </div>

          <!-- Ikon -->
          <div class="form-group">
            <label>Ikon</label>
            <div style="display:flex;gap:8px;align-items:center;">
              <select name="icon" id="fi" class="form-control" style="flex:1;" onchange="updateIconPreview(this.value)">
                <optgroup label="── Medis ──">
                  <?php foreach ($icons_medis as $ic => $label): ?>
                  <option value="<?= $ic ?>"><?= $ic ?> — <?= $label ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="── Non-Medis ──">
                  <?php foreach ($icons_non_medis as $ic => $label): ?>
                  <option value="<?= $ic ?>" <?= $ic === 'fa-toolbox' ? 'selected' : '' ?>><?= $ic ?> — <?= $label ?></option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
              <div style="width:38px;height:38px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa fa-toolbox" id="fp" style="color:var(--primary);font-size:17px;"></i>
              </div>
            </div>
            <!-- Grid preview ikon -->
            <div id="icon-grid" style="display:grid;grid-template-columns:repeat(6,1fr);gap:5px;margin-top:8px;">
              <?php foreach ($all_icons as $ic): ?>
              <div onclick="selectIcon('<?= $ic ?>')" data-icon="<?= $ic ?>"
                style="border:1.5px solid #e2e8f0;border-radius:5px;padding:7px 4px;text-align:center;cursor:pointer;transition:all .15s;"
                title="<?= $ic ?>" onmouseover="this.style.borderColor='var(--primary)';this.style.background='#f0fdf4';"
                onmouseout="updateGridHover(this)">
                <i class="fa <?= $ic ?>" style="color:var(--primary);font-size:14px;"></i>
                <div style="font-size:8px;color:#94a3b8;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= str_replace('fa-','',$ic) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div style="display:flex;gap:7px;margin-top:4px;">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
            <button type="button" class="btn btn-default" onclick="resetF()"><i class="fa fa-times"></i> Reset</button>
          </div>
        </form>
      </div>
    </div>

  </div><!-- /g2 -->
</div><!-- /content -->

<script>
/* ── Filter tabel by jenis ── */
function filterJenis(jenis) {
    const rows = document.querySelectorAll('#tbl-kat tbody tr[data-jenis]');
    rows.forEach(r => r.style.display = (!jenis || r.dataset.jenis === jenis) ? '' : 'none');
    ['fb-all','fb-medis','fb-non'].forEach(id => {
        document.getElementById(id).className = 'btn btn-default btn-sm';
    });
    const map = {'':'fb-all','Medis':'fb-medis','Non-Medis':'fb-non'};
    document.getElementById(map[jenis]).className = 'btn btn-primary btn-sm';
}

/* ── Pilih Jenis (Medis / Non-Medis) ── */
function setJenis(jenis) {
    document.getElementById('fj').value = jenis;
    const bM = document.getElementById('btn-jenis-medis');
    const bN = document.getElementById('btn-jenis-non');
    if (jenis === 'Medis') {
        bM.className = 'btn btn-primary btn-sm'; bM.style.flex = '1'; bM.style.padding = '8px'; bM.style.borderRadius = '6px';
        bN.className = 'btn btn-default btn-sm'; bN.style.flex = '1'; bN.style.padding = '8px'; bN.style.borderRadius = '6px';
    } else {
        bN.className = 'btn btn-primary btn-sm'; bN.style.flex = '1'; bN.style.padding = '8px'; bN.style.borderRadius = '6px';
        bM.className = 'btn btn-default btn-sm'; bM.style.flex = '1'; bM.style.padding = '8px'; bM.style.borderRadius = '6px';
    }
}

/* ── Preview ikon ── */
function updateIconPreview(icon) {
    document.getElementById('fp').className = 'fa ' + icon;
    // Highlight di grid
    document.querySelectorAll('#icon-grid div[data-icon]').forEach(el => {
        const active = el.dataset.icon === icon;
        el.style.borderColor  = active ? 'var(--primary)' : '#e2e8f0';
        el.style.background   = active ? '#ecfdf5'        : '';
    });
}

function selectIcon(icon) {
    document.getElementById('fi').value = icon;
    updateIconPreview(icon);
}

function updateGridHover(el) {
    const active = el.dataset.icon === document.getElementById('fi').value;
    el.style.borderColor = active ? 'var(--primary)' : '#e2e8f0';
    el.style.background  = active ? '#ecfdf5' : '';
}

/* ── Edit kategori ── */
function editKat(k) {
    document.getElementById('fa').value         = 'edit';
    document.getElementById('fid').value        = k.id;
    document.getElementById('fn').value         = k.nama;
    document.getElementById('fd').value         = k.deskripsi || '';
    document.getElementById('fs').value         = k.sla_jam;
    document.getElementById('fr').value         = k.sla_respon_jam;
    document.getElementById('form-title').textContent = 'Edit: ' + k.nama;
    setJenis(k.jenis || 'Non-Medis');
    selectIcon(k.icon || 'fa-toolbox');
    document.getElementById('kf').scrollIntoView({ behavior: 'smooth' });
}

/* ── Reset form ── */
function resetF() {
    document.getElementById('fa').value               = 'tambah';
    document.getElementById('fid').value              = '';
    document.getElementById('fn').value               = '';
    document.getElementById('fd').value               = '';
    document.getElementById('fs').value               = 24;
    document.getElementById('fr').value               = 4;
    document.getElementById('form-title').textContent = 'Tambah Kategori IPSRS';
    setJenis('Non-Medis');
    selectIcon('fa-toolbox');
}

// Init — highlight ikon default saat load
document.addEventListener('DOMContentLoaded', () => updateIconPreview('fa-toolbox'));
</script>

<?php include '../includes/footer.php'; ?>