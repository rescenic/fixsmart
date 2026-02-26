<?php
// pages/users.php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole('admin')) { setFlash('danger', 'Akses ditolak.'); redirect(APP_URL . '/dashboard.php'); }
$page_title  = 'Pengguna';
$active_menu = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'tambah') {
        $n=$trim_POST_nama=trim($_POST['nama']??''); $u=trim($_POST['username']??''); $e=trim($_POST['email']??'');
        $p=$_POST['password']??''; $r=$_POST['role']??'user'; $d=$_POST['divisi']??''; $hp=trim($_POST['no_hp']??'');
        if ($n&&$u&&$e&&strlen($p)>=6) {
            $st=$pdo->prepare("SELECT id FROM users WHERE username=? OR email=?"); $st->execute([$u,$e]);
            if ($st->fetch()) setFlash('warning','Username atau email sudah digunakan.');
            else { $pdo->prepare("INSERT INTO users (nama,username,email,password,role,divisi,no_hp) VALUES (?,?,?,?,?,?,?)")->execute([$n,$u,$e,password_hash($p,PASSWORD_BCRYPT),$r,$d,$hp]); setFlash('success',"Pengguna <strong>$n</strong> berhasil ditambahkan."); }
        } else setFlash('danger','Harap isi semua field wajib (password min 6 karakter).');
    }

    elseif ($act === 'edit') {
        $id=(int)($_POST['id']??0); $n=trim($_POST['nama']??''); $e=trim($_POST['email']??'');
        $r=$_POST['role']??''; $d=$_POST['divisi']??''; $hp=trim($_POST['no_hp']??'');
        if (!$id||!$n||!$e||!$r) { setFlash('danger','Data tidak lengkap.'); }
        elseif ($id===(int)$_SESSION['user_id'] && $r!==$_SESSION['user_role']) { setFlash('warning','Tidak bisa mengubah role akun Anda sendiri.'); }
        else {
            $chk=$pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?"); $chk->execute([$e,$id]);
            if ($chk->fetch()) setFlash('warning','Email sudah digunakan akun lain.');
            else { $pdo->prepare("UPDATE users SET nama=?,email=?,role=?,divisi=?,no_hp=? WHERE id=?")->execute([$n,$e,$r,$d,$hp,$id]); setFlash('success',"Data pengguna <strong>$n</strong> berhasil diperbarui."); }
        }
    }

    elseif ($act === 'toggle') {
        $id=(int)$_POST['id'];
        if ($id===(int)$_SESSION['user_id']) setFlash('warning','Tidak bisa menonaktifkan akun Anda sendiri.');
        else { $pdo->prepare("UPDATE users SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]); setFlash('success','Status pengguna berhasil diubah.'); }
    }

    elseif ($act === 'reset') {
        $id=(int)$_POST['id']; $p=$_POST['np']??'';
        if (strlen($p)<6) setFlash('danger','Password minimal 6 karakter.');
        else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($p,PASSWORD_BCRYPT),$id]); setFlash('success','Password berhasil direset.'); }
    }

    redirect(APP_URL.'/pages/users.php');
}

$users = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM tiket WHERE user_id=u.id) as req,
                (SELECT COUNT(*) FROM tiket WHERE teknisi_id=u.id) as handle
    FROM users u ORDER BY FIELD(u.role,'admin','teknisi','user'), u.nama
")->fetchAll();

$divs = getBagianList($pdo);

$cnt_roles = ['admin'=>0,'teknisi'=>0,'user'=>0,'nonaktif'=>0];
foreach ($users as $u) {
    if ($u['status']==='nonaktif') $cnt_roles['nonaktif']++;
    else $cnt_roles[$u['role']] = ($cnt_roles[$u['role']]??0) + 1;
}

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-users text-primary"></i> &nbsp;Pengguna</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">Pengguna</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Summary Cards -->
  <div style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;">
    <?php foreach ([
      ['admin',   'Admin',    'fa-shield-alt','#ede9fe','#7c3aed'],
      ['teknisi', 'Teknisi',  'fa-wrench',    '#dbeafe','#1d4ed8'],
      ['user',    'User',     'fa-user',      '#f3f4f6','#374151'],
      ['nonaktif','Nonaktif', 'fa-ban',       '#fee2e2','#991b1b'],
    ] as [$key,$lbl,$ic,$bg,$col]): ?>
    <div style="background:#fff;border-radius:4px;padding:10px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.06);min-width:130px;">
      <div style="width:34px;height:34px;border-radius:50%;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;">
        <i class="fa <?= $ic ?>" style="color:<?= $col ?>;font-size:14px;"></i>
      </div>
      <div><div style="font-size:20px;font-weight:700;color:#333;line-height:1;"><?= $cnt_roles[$key] ?></div>
      <div style="font-size:11px;color:#aaa;"><?= $lbl ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="panel-hd">
      <h5>Daftar Pengguna <span style="color:#aaa;font-weight:400;">(<?= count($users) ?>)</span></h5>
      <button class="btn btn-primary btn-sm" onclick="openModal('m-add')"><i class="fa fa-plus"></i> Tambah Pengguna</button>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead><tr><th>#</th><th>Nama & Email</th><th>Username</th><th>Bagian</th><th>Role</th><th>Aktivitas</th><th>Status</th><th style="text-align:center;">Aksi</th></tr></thead>
        <tbody>
          <?php $no=1; foreach ($users as $u):
            $rstyle = ['admin'=>['#ede9fe','#7c3aed','av-purple','fa-shield-alt'],'teknisi'=>['#dbeafe','#1d4ed8','av-blue','fa-wrench'],'user'=>['#f3f4f6','#374151','','fa-user']];
            [$rbg,$rc,$rav,$ric] = $rstyle[$u['role']] ?? $rstyle['user'];
          ?>
          <tr>
            <td style="color:#bbb;"><?= $no++ ?></td>
            <td>
              <div class="d-flex ai-c gap6">
                <div class="av av-sm <?= $rav ?>"><?= getInitials($u['nama']) ?></div>
                <div><div style="font-weight:600;"><?= clean($u['nama']) ?></div>
                <div style="font-size:11px;color:#aaa;"><?= clean($u['email']) ?></div></div>
              </div>
            </td>
            <td style="font-family:monospace;font-size:12px;color:#666;">@<?= clean($u['username']) ?></td>
            <td style="font-size:12px;"><?= clean($u['divisi']??'—') ?></td>
            <td>
              <span style="font-size:11px;font-weight:700;padding:3px 9px;border-radius:3px;background:<?= $rbg ?>;color:<?= $rc ?>;">
                <i class="fa <?= $ric ?>" style="font-size:10px;"></i> <?= ucfirst($u['role']) ?>
              </span>
            </td>
            <td style="font-size:12px;">
              <?= $u['role']==='teknisi' ? '<span style="color:var(--primary);"><i class="fa fa-wrench"></i> '.$u['handle'].' handle</span>' : '<span style="color:var(--blue);"><i class="fa fa-paper-plane"></i> '.$u['req'].' tiket</span>' ?>
            </td>
            <td>
              <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:<?= $u['status']==='aktif'?'#d1fae5':'#fee2e2' ?>;color:<?= $u['status']==='aktif'?'#065f46':'#991b1b' ?>;">
                <?= $u['status']==='aktif'?'● Aktif':'● Nonaktif' ?>
              </span>
            </td>
            <td style="text-align:center;white-space:nowrap;">
              <?php if ($u['id']!=(int)$_SESSION['user_id']): ?>
              <button class="btn btn-warning btn-sm" title="Edit data & role"
                onclick='editUser(<?= json_encode(["id"=>$u["id"],"nama"=>$u["nama"],"email"=>$u["email"],"role"=>$u["role"],"divisi"=>$u["divisi"]??"","no_hp"=>$u["no_hp"]??""]) ?>)'>
                <i class="fa fa-edit"></i> Edit
              </button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-sm <?= $u['status']==='aktif'?'btn-default':'btn-success' ?>" title="<?= $u['status']==='aktif'?'Nonaktifkan':'Aktifkan' ?>">
                  <i class="fa <?= $u['status']==='aktif'?'fa-ban':'fa-check' ?>"></i>
                </button>
              </form>
              <button class="btn btn-info btn-sm" onclick="resetPwd(<?= $u['id'] ?>,'<?= clean($u['nama']) ?>')" title="Reset password"><i class="fa fa-key"></i></button>
              <?php else: ?>
              <span style="font-size:11px;color:#bbb;font-style:italic;">Akun Anda</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Tambah -->
<div class="modal-ov" id="m-add"><div class="modal-box">
  <div class="modal-hd"><h5><i class="fa fa-user-plus"></i> Tambah Pengguna</h5><button class="mc" onclick="closeModal('m-add')"><i class="fa fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="tambah"><div class="modal-bd">
    <div class="form-row">
      <div class="form-group"><label>Nama <span class="req">*</span></label><input type="text" name="nama" class="form-control" required></div>
      <div class="form-group"><label>Username <span class="req">*</span></label><input type="text" name="username" class="form-control" required></div>
    </div>
    <div class="form-group"><label>Email <span class="req">*</span></label><input type="email" name="email" class="form-control" required></div>
    <div class="form-row">
      <div class="form-group"><label>Role</label>
        <select name="role" class="form-control">
          <option value="user">User — Pemohon tiket</option>
          <option value="teknisi">Teknisi — Staff IT</option>
          <option value="admin">Admin — Akses penuh</option>
        </select>
      </div>
      <div class="form-group"><label>Bagian / Divisi</label>
        <select name="divisi" class="form-control">
          <option value="">— Pilih —</option>
          <?php foreach ($divs as $dv): ?>
          <option value="<?= clean($dv['nama']) ?>"><?= clean($dv['nama']) ?><?= $dv['kode']?' ('.$dv['kode'].')':'' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>No. HP</label><input type="text" name="no_hp" class="form-control" placeholder="08xx..."></div>
      <div class="form-group"><label>Password <span class="req">*</span></label><input type="password" name="password" class="form-control" placeholder="Min. 6 karakter" required></div>
    </div>
  </div>
  <div class="modal-ft"><button type="button" class="btn btn-default" onclick="closeModal('m-add')">Batal</button><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button></div>
  </form>
</div></div>

<!-- Modal Edit (termasuk ubah role) -->
<div class="modal-ov" id="m-edit"><div class="modal-box">
  <div class="modal-hd"><h5><i class="fa fa-user-edit"></i> Edit Pengguna</h5><button class="mc" onclick="closeModal('m-edit')"><i class="fa fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="e-id">
  <div class="modal-bd">
    <!-- Alert info -->
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:4px;padding:9px 12px;margin-bottom:14px;font-size:12px;color:#92400e;">
      <i class="fa fa-info-circle"></i> Mengubah <strong>Role</strong> akan langsung mengubah hak akses pengguna di seluruh sistem.
    </div>
    <div class="form-row">
      <div class="form-group"><label>Nama Lengkap <span class="req">*</span></label><input type="text" name="nama" id="e-nama" class="form-control" required></div>
      <div class="form-group"><label>Email <span class="req">*</span></label><input type="email" name="email" id="e-email" class="form-control" required></div>
    </div>

    <!-- Role pilihan kartu -->
    <div class="form-group">
      <label>Role <span class="req">*</span></label>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php foreach ([
          ['user',    'User',    'fa-user',       '#f3f4f6','#374151','Buat & pantau tiket sendiri'],
          ['teknisi', 'Teknisi', 'fa-wrench',      '#dbeafe','#1d4ed8','Proses & selesaikan tiket IT'],
          ['admin',   'Admin',   'fa-shield-alt',  '#ede9fe','#7c3aed','Akses penuh ke semua fitur'],
        ] as [$rv,$rl,$ri,$rbg,$rc,$rdesc]): ?>
        <label id="lbl-<?= $rv ?>" style="flex:1;min-width:110px;border:2px solid #e5e7eb;border-radius:5px;padding:10px;cursor:pointer;transition:all .18s;display:flex;gap:8px;align-items:flex-start;">
          <input type="radio" name="role" value="<?= $rv ?>" style="margin-top:3px;flex-shrink:0;" onchange="hilightRole()">
          <div>
            <div style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700;">
              <span style="width:20px;height:20px;border-radius:50%;background:<?= $rbg ?>;display:inline-flex;align-items:center;justify-content:center;">
                <i class="fa <?= $ri ?>" style="color:<?= $rc ?>;font-size:9px;"></i>
              </span>
              <span style="color:<?= $rc ?>;"><?= $rl ?></span>
            </div>
            <div style="font-size:10px;color:#aaa;margin-top:3px;line-height:1.4;"><?= $rdesc ?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Bagian / Divisi</label>
        <select name="divisi" id="e-divisi" class="form-control">
          <option value="">— Pilih —</option>
          <?php foreach ($divs as $dv): ?>
          <option value="<?= clean($dv['nama']) ?>"><?= clean($dv['nama']) ?><?= $dv['kode']?' ('.$dv['kode'].')':'' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>No. HP</label><input type="text" name="no_hp" id="e-nohp" class="form-control" placeholder="08xx..."></div>
    </div>
  </div>
  <div class="modal-ft"><button type="button" class="btn btn-default" onclick="closeModal('m-edit')">Batal</button><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button></div>
  </form>
</div></div>

<!-- Modal Reset Password -->
<div class="modal-ov" id="m-reset"><div class="modal-box sm">
  <div class="modal-hd"><h5><i class="fa fa-key"></i> Reset Password</h5><button class="mc" onclick="closeModal('m-reset')"><i class="fa fa-times"></i></button></div>
  <form method="POST"><input type="hidden" name="action" value="reset"><input type="hidden" name="id" id="r-id">
  <div class="modal-bd">
    <p style="font-size:13px;margin-bottom:13px;"><i class="fa fa-user" style="color:var(--primary);"></i> Reset password untuk: <strong id="r-nama"></strong></p>
    <div class="form-group"><label>Password Baru <span class="req">*</span></label><input type="password" name="np" class="form-control" placeholder="Min. 6 karakter" required></div>
  </div>
  <div class="modal-ft"><button type="button" class="btn btn-default" onclick="closeModal('m-reset')">Batal</button><button type="submit" class="btn btn-primary"><i class="fa fa-key"></i> Reset</button></div>
  </form>
</div></div>

<script>
function editUser(u) {
  document.getElementById('e-id').value    = u.id;
  document.getElementById('e-nama').value  = u.nama;
  document.getElementById('e-email').value = u.email;
  document.getElementById('e-nohp').value  = u.no_hp || '';
  // Set divisi
  const sel = document.getElementById('e-divisi');
  for (let i = 0; i < sel.options.length; i++)
    sel.options[i].selected = (sel.options[i].value === u.divisi);
  // Set radio role
  document.querySelectorAll('[name=role]').forEach(r => { r.checked = (r.value === u.role); });
  hilightRole();
  openModal('m-edit');
}

const roleHL = {
  user:    {border:'#d1d5db',bg:'#f9fafb'},
  teknisi: {border:'#93c5fd',bg:'#eff6ff'},
  admin:   {border:'#c4b5fd',bg:'#faf5ff'},
};
function hilightRole() {
  document.querySelectorAll('[name=role]').forEach(r => {
    const lbl = document.getElementById('lbl-'+r.value);
    if (!lbl) return;
    if (r.checked) { lbl.style.borderColor=roleHL[r.value].border; lbl.style.background=roleHL[r.value].bg; }
    else { lbl.style.borderColor='#e5e7eb'; lbl.style.background='#fff'; }
  });
}

function resetPwd(id, nama) {
  document.getElementById('r-id').value = id;
  document.getElementById('r-nama').textContent = nama;
  openModal('m-reset');
}
</script>
<?php include '../includes/footer.php'; ?>
