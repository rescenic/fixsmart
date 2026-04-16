<?php
// pages/users.php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin','hrd'])) { setFlash('danger', 'Akses ditolak.'); redirect(APP_URL . '/dashboard.php'); }
$page_title  = 'Pengguna';
$active_menu = 'users';

// ── Tambahkan 'keuangan' ke daftar role valid ──────────────────────────────
define('VALID_ROLES', ['user','teknisi','teknisi_ipsrs','admin','hrd','keuangan','akreditasi']);

$role_labels = [
    'admin'         => 'Admin',
    'teknisi'       => 'Teknisi IT',
    'teknisi_ipsrs' => 'Teknisi IPSRS',
    'hrd'           => 'HRD',
    'keuangan'      => 'Keuangan',
    'akreditasi'    => 'Tim Akreditasi',
    'user'          => 'User',
];

$is_hrd   = ($_SESSION['user_role'] ?? '') === 'hrd';
$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';

// HRD tidak bisa assign role admin, akreditasi, atau keuangan
$assignable_roles = $is_hrd
    ? ['user','teknisi','teknisi_ipsrs','hrd']
    : VALID_ROLES;

$pokja_list = [];
try { $pokja_list = $pdo->query("SELECT id,kode,nama FROM master_pokja WHERE status='aktif' ORDER BY urutan,kode")->fetchAll(); } catch (Exception $e) {}

// ── Migrate: pastikan kolom role sudah mendukung 'keuangan' ──────────────────
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN `role` ENUM('user','teknisi','teknisi_ipsrs','admin','hrd','keuangan','akreditasi') NOT NULL DEFAULT 'user'");
} catch (Exception $e) {} // Abaikan jika sudah ada

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'tambah') {
        if ($is_hrd && !hasRole('admin')) {
            setFlash('danger','HRD tidak berwenang menambah akun Admin, Keuangan, atau Tim Akreditasi.');
            redirect(APP_URL.'/pages/users.php');
        }
        $n = trim($_POST['nama']     ?? '');
        $u = trim($_POST['username'] ?? '');
        $e = trim($_POST['email']    ?? '');
        $p = $_POST['password']      ?? '';
        $r = $_POST['role']          ?? 'user';
        $is_akr_flag = isset($_POST['is_akreditasi']) ? 1 : 0;
        $pokja_id    = ($r==='akreditasi'||$is_akr_flag) ? ((int)($_POST['pokja_id']??0)?:null) : null;
        if (!in_array($r, $assignable_roles)) $r = 'user';
        if ($n && $u && $e && strlen($p) >= 6) {
            $st = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
            $st->execute([$u, $e]);
            if ($st->fetch()) {
                setFlash('warning','Username atau email sudah digunakan.');
            } else {
                $pdo->prepare("INSERT INTO users (nama,username,email,password,role,pokja_id,is_akreditasi,status) VALUES (?,?,?,?,?,?,?,'aktif')")
                    ->execute([$n,$u,$e,password_hash($p,PASSWORD_BCRYPT),$r,$pokja_id,$is_akr_flag]);
                setFlash('success',"Pengguna <strong>".clean($n)."</strong> berhasil ditambahkan.");
            }
        } else {
            setFlash('danger','Harap isi semua field wajib (password min 6 karakter).');
        }
    }

    elseif ($act === 'edit') {
        $id          = (int)($_POST['id']   ?? 0);
        $n           = trim($_POST['nama']  ?? '');
        $e           = trim($_POST['email'] ?? '');
        $r           = $_POST['role']       ?? '';
        $is_akr_flag = isset($_POST['is_akreditasi']) ? 1 : 0;
        $pokja_id    = ($r==='akreditasi'||$is_akr_flag) ? ((int)($_POST['pokja_id']??0)?:null) : null;

        if ($is_hrd) {
            $tgt = $pdo->prepare("SELECT role FROM users WHERE id=?");
            $tgt->execute([$id]);
            $tgt_role = $tgt->fetchColumn();
            if (in_array($tgt_role, ['admin','keuangan'])) {
                setFlash('danger','HRD tidak berwenang mengubah akun Admin atau Keuangan.');
                redirect(APP_URL.'/pages/users.php');
            }
        }
        if (!in_array($r, $assignable_roles)) {
            setFlash('danger','Role tidak valid.');
            redirect(APP_URL.'/pages/users.php');
        }
        if (!$id || !$n || !$e || !$r) {
            setFlash('danger','Data tidak lengkap.');
        } elseif ($id === (int)$_SESSION['user_id'] && $r !== $_SESSION['user_role']) {
            setFlash('warning','Tidak bisa mengubah role akun Anda sendiri.');
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $chk->execute([$e,$id]);
            if ($chk->fetch()) {
                setFlash('warning','Email sudah digunakan akun lain.');
            } else {
                $pdo->prepare("UPDATE users SET nama=?,email=?,role=?,pokja_id=?,is_akreditasi=? WHERE id=?")
                    ->execute([$n,$e,$r,$pokja_id,$is_akr_flag,$id]);
                setFlash('success',"Data pengguna <strong>".clean($n)."</strong> berhasil diperbarui.");
            }
        }
    }

    elseif ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            setFlash('warning','Tidak bisa menonaktifkan akun Anda sendiri.');
        } else {
            $pdo->prepare("UPDATE users SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
            setFlash('success','Status pengguna berhasil diubah.');
        }
    }

    elseif ($act === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $p  = $_POST['np'] ?? '';
        if (!$id)            setFlash('danger','ID tidak valid.');
        elseif (strlen($p)<6) setFlash('danger','Password minimal 6 karakter.');
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($p,PASSWORD_BCRYPT),$id]);
            setFlash('success','Password berhasil direset.');
        }
    }

    redirect(APP_URL.'/pages/users.php');
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$users = $pdo->query("
    SELECT u.*, pk.kode AS pokja_kode, pk.nama AS pokja_nama,
           COALESCE(u.status,'aktif')       AS status,
           COALESCE(u.is_akreditasi,0)      AS is_akreditasi,
           (SELECT COUNT(*) FROM tiket       WHERE user_id    = u.id) AS req,
           (SELECT COUNT(*) FROM tiket       WHERE teknisi_id = u.id) AS handle,
           (SELECT COUNT(*) FROM tiket_ipsrs WHERE teknisi_id = u.id) AS handle_ipsrs,
           (SELECT COUNT(*) FROM tiket_ipsrs WHERE user_id    = u.id) AS req_ipsrs
    FROM users u
    LEFT JOIN master_pokja pk ON pk.id = u.pokja_id
    ORDER BY FIELD(u.role,'admin','hrd','keuangan','akreditasi','teknisi','teknisi_ipsrs','user'), u.nama
")->fetchAll();

$cnt_roles = ['admin'=>0,'teknisi'=>0,'teknisi_ipsrs'=>0,'hrd'=>0,'keuangan'=>0,'akreditasi'=>0,'user'=>0,'nonaktif'=>0];
foreach ($users as $u) {
    if (($u['status']??'aktif') === 'nonaktif') { $cnt_roles['nonaktif']++; continue; }
    if (isset($cnt_roles[$u['role']])) $cnt_roles[$u['role']]++;
}

$rstyle = [
    'admin'         => ['bg'=>'#ede9fe','color'=>'#7c3aed','av'=>'av-purple','icon'=>'fa-shield-alt'],
    'teknisi'       => ['bg'=>'#dbeafe','color'=>'#1d4ed8','av'=>'av-blue',  'icon'=>'fa-wrench'],
    'teknisi_ipsrs' => ['bg'=>'#d1fae5','color'=>'#065f46','av'=>'av-green', 'icon'=>'fa-screwdriver-wrench'],
    'hrd'           => ['bg'=>'#fce7f3','color'=>'#9d174d','av'=>'av-pink',  'icon'=>'fa-people-group'],
    'keuangan'      => ['bg'=>'#ecfeff','color'=>'#0e7490','av'=>'av-cyan',  'icon'=>'fa-coins'],
    'akreditasi'    => ['bg'=>'#fef9c3','color'=>'#854d0e','av'=>'',         'icon'=>'fa-medal'],
    'user'          => ['bg'=>'#f3f4f6','color'=>'#374151','av'=>'',         'icon'=>'fa-user'],
];

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-users text-primary"></i> &nbsp;Pengguna</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Pengguna</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Stat cards -->
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach ([
      ['admin',        'Admin',          'fa-shield-alt',          '#ede9fe','#7c3aed'],
      ['teknisi',      'Teknisi IT',     'fa-wrench',              '#dbeafe','#1d4ed8'],
      ['teknisi_ipsrs','Teknisi IPSRS',  'fa-screwdriver-wrench',  '#d1fae5','#065f46'],
      ['hrd',          'HRD',            'fa-people-group',        '#fce7f3','#9d174d'],
      ['keuangan',     'Keuangan',       'fa-coins',               '#ecfeff','#0e7490'],
      ['akreditasi',   'Tim Akreditasi', 'fa-medal',               '#fef9c3','#854d0e'],
      ['user',         'User',           'fa-user',                '#f3f4f6','#374151'],
      ['nonaktif',     'Nonaktif',       'fa-ban',                 '#fee2e2','#991b1b'],
    ] as [$key,$lbl,$ic,$bg,$col]): ?>
    <div style="background:#fff;border:1px solid #f0f0f0;border-radius:8px;padding:10px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.05);min-width:115px;">
      <div style="width:36px;height:36px;border-radius:50%;background:<?=$bg?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa <?=$ic?>" style="color:<?=$col?>;font-size:14px;"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;color:#1e293b;line-height:1;"><?=$cnt_roles[$key]??0?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:1px;"><?=$lbl?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tabel -->
  <div class="panel">
    <div class="panel-hd">
      <h5>Daftar Pengguna <span style="color:#94a3b8;font-weight:400;">(<?=count($users)?>)</span></h5>
      <button class="btn btn-primary btn-sm" onclick="openModal('m-add')"><i class="fa fa-plus"></i> Tambah Pengguna</button>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Nama &amp; Email</th><th>Username</th><th>Bagian</th>
            <th>Role</th><th>Aktivitas</th><th>Status</th>
            <th style="text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php $no=1; foreach ($users as $u):
            $role   = $u['role']   ?? 'user';
            $status = $u['status'] ?? 'aktif';
            $rs     = $rstyle[$role] ?? $rstyle['user'];
            $rl     = $role_labels[$role] ?? ucfirst($role);
            $can_edit = !($is_hrd && in_array($role, ['admin','keuangan']));
          ?>
          <tr>
            <td style="color:#cbd5e1;"><?=$no++?></td>
            <td>
              <div class="d-flex ai-c gap6">
                <div class="av av-sm <?=$rs['av']?>"><?=getInitials($u['nama'])?></div>
                <div>
                  <div style="font-weight:600;color:#1e293b;"><?=clean($u['nama'])?></div>
                  <div style="font-size:11px;color:#94a3b8;"><?=clean($u['email'])?></div>
                </div>
              </div>
            </td>
            <td style="font-family:monospace;font-size:12px;color:#64748b;">@<?=clean($u['username'])?></td>
            <td style="font-size:12px;color:#475569;"><?=clean($u['divisi']??'—')?:'—'?></td>
            <td>
              <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:<?=$rs['bg']?>;color:<?=$rs['color']?>;">
                <i class="fa <?=$rs['icon']?>" style="font-size:10px;"></i> <?=$rl?>
              </span>
            </td>
            <td style="font-size:12px;">
              <?php if ($role === 'akreditasi'): ?>
                <?php if (!empty($u['pokja_kode'])): ?>
                <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:6px;background:#fef9c3;color:#854d0e;">
                  <i class="fa fa-medal" style="font-size:10px;"></i><?=clean($u['pokja_kode']).' — '.clean($u['pokja_nama'])?>
                </span>
                <?php else: ?><span style="color:#fbbf24;font-size:11px;"><i class="fa fa-triangle-exclamation"></i> Belum ada Pokja</span><?php endif; ?>
              <?php elseif ($role === 'keuangan'): ?>
                <span style="color:#0e7490;"><i class="fa fa-coins"></i> Penggajian &amp; Keuangan</span>
                <?php if ((int)($u['is_akreditasi']??0)===1 && !empty($u['pokja_kode'])): ?>
                <br><span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;background:#fef9c3;color:#854d0e;width:fit-content;margin-top:3px;">
                  <i class="fa fa-medal" style="font-size:9px;"></i><?=clean($u['pokja_kode']).' — '.clean($u['pokja_nama'])?>
                </span>
                <?php endif; ?>
              <?php elseif ((int)($u['is_akreditasi']??0)===1): ?>
                <div style="display:flex;flex-direction:column;gap:3px;">
                  <?php if ($role==='teknisi'): ?><span style="color:#1d4ed8;"><i class="fa fa-wrench"></i> <?=(int)$u['handle']?> tiket IT</span>
                  <?php elseif ($role==='teknisi_ipsrs'): ?><span style="color:#065f46;"><i class="fa fa-screwdriver-wrench"></i> <?=(int)$u['handle_ipsrs']?> tiket IPSRS</span>
                  <?php elseif ($role==='hrd'): ?><span style="color:#9d174d;"><i class="fa fa-people-group"></i> SDM &amp; Kepegawaian</span>
                  <?php else: ?><span style="color:#1d4ed8;"><i class="fa fa-paper-plane"></i> <?=(int)$u['req']+(int)$u['req_ipsrs']?> tiket</span><?php endif; ?>
                  <?php if (!empty($u['pokja_kode'])): ?>
                  <span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;background:#fef9c3;color:#854d0e;width:fit-content;">
                    <i class="fa fa-medal" style="font-size:9px;"></i><?=clean($u['pokja_kode']).' — '.clean($u['pokja_nama'])?>
                  </span>
                  <?php else: ?><span style="font-size:10px;color:#fbbf24;"><i class="fa fa-medal"></i> Akreditasi (belum ada Pokja)</span><?php endif; ?>
                </div>
              <?php elseif ($role==='teknisi'): ?><span style="color:#1d4ed8;"><i class="fa fa-wrench"></i> <?=(int)$u['handle']?> tiket IT</span>
              <?php elseif ($role==='teknisi_ipsrs'): ?><span style="color:#065f46;"><i class="fa fa-screwdriver-wrench"></i> <?=(int)$u['handle_ipsrs']?> tiket IPSRS</span>
              <?php elseif ($role==='admin'): ?><span style="color:#7c3aed;"><i class="fa fa-shield-alt"></i> Administrator</span>
              <?php elseif ($role==='hrd'): ?><span style="color:#9d174d;"><i class="fa fa-people-group"></i> SDM &amp; Kepegawaian</span>
              <?php else: ?><span style="color:#1d4ed8;"><i class="fa fa-paper-plane"></i> <?=(int)$u['req']+(int)$u['req_ipsrs']?> tiket</span><?php endif; ?>
            </td>
            <td>
              <?php if ($status==='aktif'): ?>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;background:#d1fae5;color:#065f46;">
                <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;"></span> Aktif
              </span>
              <?php else: ?>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;background:#fee2e2;color:#991b1b;">
                <span style="width:6px;height:6px;border-radius:50%;background:#ef4444;"></span> Nonaktif
              </span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;white-space:nowrap;">
              <?php if ($u['id'] != (int)$_SESSION['user_id'] && $can_edit): ?>
              <button class="btn btn-warning btn-sm" onclick='editUser(<?=json_encode([
                "id"           => (int)$u["id"],
                "nama"         => $u["nama"],
                "email"        => $u["email"],
                "role"         => $u["role"],
                "pokja_id"     => (int)($u["pokja_id"]??0),
                "is_akreditasi"=> (int)($u["is_akreditasi"]??0),
              ])?>)'>
                <i class="fa fa-edit"></i> Edit
              </button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?=(int)$u['id']?>">
                <button type="submit" class="btn btn-sm <?=$status==='aktif'?'btn-default':'btn-success'?>"
                  onclick="return confirm('<?=$status==='aktif'?'Nonaktifkan':'Aktifkan'?> pengguna ini?')">
                  <i class="fa <?=$status==='aktif'?'fa-ban':'fa-check'?>"></i>
                </button>
              </form>
              <button class="btn btn-info btn-sm" onclick="resetPwd(<?=(int)$u['id']?>,'<?=addslashes(clean($u['nama']))?>')">
                <i class="fa fa-key"></i>
              </button>
              <?php elseif ($u['id'] == (int)$_SESSION['user_id']): ?>
              <span style="font-size:11px;color:#94a3b8;font-style:italic;">Akun Anda</span>
              <?php else: ?>
              <span style="font-size:11px;color:#cbd5e1;"><i class="fa fa-lock"></i></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
          <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:30px;">Belum ada pengguna.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<!-- ══ MODAL: Tambah (landscape) ══ -->
<div class="modal-ov" id="m-add">
  <div style="background:#fff;width:100%;max-width:820px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.22);animation:mIn .2s ease;overflow:hidden;display:flex;flex-direction:column;">
    <div class="modal-hd">
      <h5><i class="fa fa-user-plus"></i> Tambah Pengguna</h5>
      <button class="mc" onclick="closeModal('m-add')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah">
      <!-- Layout landscape: kiri info dasar, kanan pilih role -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;min-height:0;">

        <!-- Kolom Kiri: Info Dasar -->
        <div style="padding:20px;border-right:1px solid #f1f5f9;display:flex;flex-direction:column;gap:12px;">
          <div style="font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;padding-bottom:6px;border-bottom:1.5px solid #f1f5f9;">
            <i class="fa fa-id-card" style="color:#3b82f6;"></i> &nbsp;Informasi Akun
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" class="form-control" required placeholder="Nama lengkap">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Username <span class="req">*</span></label>
            <input type="text" name="username" class="form-control" required autocomplete="off" placeholder="username (tanpa spasi)">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Email <span class="req">*</span></label>
            <input type="email" name="email" class="form-control" required placeholder="email@contoh.com">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Password <span class="req">*</span></label>
            <div style="position:relative;">
              <input type="password" name="password" id="a-pwd" class="form-control" placeholder="Min. 6 karakter" required autocomplete="new-password">
              <button type="button" onclick="togglePwd('a-pwd','a-eye')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:13px;">
                <i class="fa fa-eye" id="a-eye"></i>
              </button>
            </div>
          </div>

          <!-- Akreditasi extra -->
          <div id="add-akreditasi-extra" style="display:none;">
            <div style="background:#fefce8;border:1px dashed #fbbf24;border-radius:8px;padding:10px 12px;">
              <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;margin:0;">
                <input type="checkbox" name="is_akreditasi" id="add-is-akr" value="1" onchange="togglePokjaAdd()" style="width:15px;height:15px;cursor:pointer;margin-top:2px;accent-color:#d97706;flex-shrink:0;">
                <div>
                  <div style="font-size:12px;font-weight:700;color:#854d0e;"><i class="fa fa-medal" style="font-size:10px;"></i> Tambahkan akses Akreditasi</div>
                  <div style="font-size:10px;color:#a16207;margin-top:2px;line-height:1.4;">Agar orang ini <strong>juga bisa input dokumen akreditasi</strong> tanpa ganti role.</div>
                </div>
              </label>
            </div>
          </div>
          <div id="add-pokja-group" style="display:none;">
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Pokja Akreditasi <span class="req">*</span></label>
            <select name="pokja_id" class="form-control">
              <option value="">— Pilih Pokja —</option>
              <?php foreach ($pokja_list as $pk): ?>
              <option value="<?=(int)$pk['id']?>"><?=clean($pk['kode'])?> — <?=clean($pk['nama'])?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="background:#f0fdf9;border:1px solid #a7f3d0;border-radius:7px;padding:9px 12px;font-size:11px;color:#065f46;display:flex;align-items:flex-start;gap:7px;margin-top:auto;">
            <i class="fa fa-user-tie" style="margin-top:1px;flex-shrink:0;"></i>
            <div><strong>Atasan Langsung</strong> tidak diisi di sini. Karyawan memilih sendiri via <strong>Profil Saya</strong>.</div>
          </div>
        </div>

        <!-- Kolom Kanan: Pilih Role -->
        <div style="padding:20px;background:#fafbfc;display:flex;flex-direction:column;gap:10px;">
          <div style="font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;padding-bottom:6px;border-bottom:1.5px solid #f1f5f9;">
            <i class="fa fa-shield-halved" style="color:#7c3aed;"></i> &nbsp;Pilih Role <span class="req">*</span>
          </div>
          <div style="display:flex;flex-direction:column;gap:7px;">
            <?php
            $role_opts = [
              ['user',         'User',           'fa-user',               '#f3f4f6','#374151','Buat & pantau tiket sendiri',       true],
              ['teknisi',      'Teknisi IT',     'fa-wrench',             '#dbeafe','#1d4ed8','Proses & selesaikan tiket IT',      false],
              ['teknisi_ipsrs','Teknisi IPSRS',  'fa-screwdriver-wrench', '#d1fae5','#065f46','Proses tiket Sarpras/IPSRS',        false],
              ['hrd',          'HRD',            'fa-people-group',       '#fce7f3','#9d174d','Manajemen SDM & kepegawaian',       false],
              ['keuangan',     'Keuangan',       'fa-coins',              '#ecfeff','#0e7490','Akses modul penggajian & keuangan', false],
              ['akreditasi',   'Tim Akreditasi', 'fa-medal',              '#fef9c3','#854d0e','Hanya akses akreditasi',            false],
              ['admin',        'Admin',          'fa-shield-alt',         '#ede9fe','#7c3aed','Akses penuh ke semua fitur',        false],
            ];
            if ($is_hrd) {
                $role_opts = array_filter($role_opts, fn($r) => !in_array($r[0], ['admin','keuangan','akreditasi']));
            }
            foreach ($role_opts as [$rv,$rl,$ri,$rbg,$rc,$rdesc,$checked]):
            ?>
            <label id="albl-<?=$rv?>" style="border:2px solid <?=$checked?'#e5e7eb':'#e5e7eb'?>;border-radius:8px;padding:8px 10px;cursor:pointer;transition:all .15s;display:flex;gap:9px;align-items:center;background:<?=$checked?$rbg:'#fff'?>;">
              <input type="radio" name="role" value="<?=$rv?>" <?=$checked?'checked':''?> style="flex-shrink:0;" onchange="hilightRoleAdd();togglePokjaAdd()">
              <span style="width:28px;height:28px;border-radius:8px;background:<?=$rbg?>;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa <?=$ri?>" style="color:<?=$rc?>;font-size:11px;"></i>
              </span>
              <div style="min-width:0;">
                <div style="font-size:12px;font-weight:700;color:<?=$rc?>;"><?=$rl?></div>
                <div style="font-size:10px;color:#94a3b8;line-height:1.3;"><?=$rdesc?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /grid -->
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-add')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>


<!-- ══ MODAL: Edit (landscape) ══ -->
<div class="modal-ov" id="m-edit">
  <div style="background:#fff;width:100%;max-width:820px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.22);animation:mIn .2s ease;overflow:hidden;display:flex;flex-direction:column;">
    <div class="modal-hd">
      <h5><i class="fa fa-user-edit"></i> Edit Pengguna</h5>
      <button class="mc" onclick="closeModal('m-edit')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e-id">
      <!-- Layout landscape -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;min-height:0;">

        <!-- Kolom Kiri: Info Dasar -->
        <div style="padding:20px;border-right:1px solid #f1f5f9;display:flex;flex-direction:column;gap:12px;">
          <div style="font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;padding-bottom:6px;border-bottom:1.5px solid #f1f5f9;">
            <i class="fa fa-id-card" style="color:#3b82f6;"></i> &nbsp;Informasi Akun
          </div>
          <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:8px 11px;font-size:11px;color:#92400e;">
            <i class="fa fa-triangle-exclamation"></i> Mengubah <strong>Role</strong> akan langsung mengubah hak akses di seluruh sistem.
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" id="e-nama" class="form-control" required>
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Email <span class="req">*</span></label>
            <input type="email" name="email" id="e-email" class="form-control" required>
          </div>

          <!-- Akreditasi extra -->
          <div id="edit-akreditasi-extra" style="display:none;">
            <div style="background:#fefce8;border:1px dashed #fbbf24;border-radius:8px;padding:10px 12px;">
              <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;margin:0;">
                <input type="checkbox" name="is_akreditasi" id="edit-is-akr" value="1" onchange="togglePokjaEdit()" style="width:15px;height:15px;cursor:pointer;margin-top:2px;accent-color:#d97706;flex-shrink:0;">
                <div>
                  <div style="font-size:12px;font-weight:700;color:#854d0e;"><i class="fa fa-medal" style="font-size:10px;"></i> Tambahkan akses Akreditasi</div>
                  <div style="font-size:10px;color:#a16207;margin-top:2px;line-height:1.4;">Agar orang ini <strong>juga bisa input dokumen akreditasi</strong> tanpa ganti role.</div>
                </div>
              </label>
            </div>
          </div>
          <div id="edit-pokja-group" style="display:none;">
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Pokja Akreditasi <span class="req">*</span></label>
            <select name="pokja_id" id="e-pokja" class="form-control">
              <option value="">— Pilih Pokja —</option>
              <?php foreach ($pokja_list as $pk): ?>
              <option value="<?=(int)$pk['id']?>"><?=clean($pk['kode'])?> — <?=clean($pk['nama'])?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="background:#f0fdf9;border:1px solid #a7f3d0;border-radius:7px;padding:9px 12px;font-size:11px;color:#065f46;display:flex;align-items:flex-start;gap:7px;margin-top:auto;">
            <i class="fa fa-user-tie" style="margin-top:1px;flex-shrink:0;"></i>
            <div><strong>Atasan Langsung</strong> diatur mandiri via <strong>Profil Saya → Data Kepegawaian</strong>.</div>
          </div>
        </div>

        <!-- Kolom Kanan: Pilih Role -->
        <div style="padding:20px;background:#fafbfc;display:flex;flex-direction:column;gap:10px;">
          <div style="font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;padding-bottom:6px;border-bottom:1.5px solid #f1f5f9;">
            <i class="fa fa-shield-halved" style="color:#7c3aed;"></i> &nbsp;Pilih Role <span class="req">*</span>
          </div>
          <div style="display:flex;flex-direction:column;gap:7px;" id="edit-role-grid">
            <?php
            $edit_role_opts = [
              ['user',         'User',           'fa-user',               '#f3f4f6','#374151','Buat & pantau tiket sendiri'],
              ['teknisi',      'Teknisi IT',     'fa-wrench',             '#dbeafe','#1d4ed8','Proses & selesaikan tiket IT'],
              ['teknisi_ipsrs','Teknisi IPSRS',  'fa-screwdriver-wrench', '#d1fae5','#065f46','Proses tiket Sarpras/IPSRS'],
              ['hrd',          'HRD',            'fa-people-group',       '#fce7f3','#9d174d','Manajemen SDM & kepegawaian'],
              ['keuangan',     'Keuangan',       'fa-coins',              '#ecfeff','#0e7490','Akses modul penggajian & keuangan'],
              ['akreditasi',   'Tim Akreditasi', 'fa-medal',              '#fef9c3','#854d0e','Hanya akses akreditasi'],
              ['admin',        'Admin',          'fa-shield-alt',         '#ede9fe','#7c3aed','Akses penuh ke semua fitur'],
            ];
            if ($is_hrd) {
                $edit_role_opts = array_filter($edit_role_opts, fn($r) => !in_array($r[0], ['admin','keuangan','akreditasi']));
            }
            foreach ($edit_role_opts as [$rv,$rl,$ri,$rbg,$rc,$rdesc]):
            ?>
            <label id="lbl-<?=$rv?>" style="border:2px solid #e5e7eb;border-radius:8px;padding:8px 10px;cursor:pointer;transition:all .15s;display:flex;gap:9px;align-items:center;background:#fff;">
              <input type="radio" name="role" value="<?=$rv?>" style="flex-shrink:0;" onchange="hilightRole();togglePokjaEdit()">
              <span style="width:28px;height:28px;border-radius:8px;background:<?=$rbg?>;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa <?=$ri?>" style="color:<?=$rc?>;font-size:11px;"></i>
              </span>
              <div style="min-width:0;">
                <div style="font-size:12px;font-weight:700;color:<?=$rc?>;"><?=$rl?></div>
                <div style="font-size:10px;color:#94a3b8;line-height:1.3;"><?=$rdesc?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /grid -->
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-edit')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>


<!-- ══ MODAL: Reset Password ══ -->
<div class="modal-ov" id="m-reset">
  <div class="modal-box sm">
    <div class="modal-hd">
      <h5><i class="fa fa-key"></i> Reset Password</h5>
      <button class="mc" onclick="closeModal('m-reset')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reset">
      <input type="hidden" name="id" id="r-id">
      <div class="modal-bd">
        <p style="font-size:13px;margin-bottom:13px;color:#475569;">
          <i class="fa fa-user" style="color:var(--primary);"></i> Reset password untuk: <strong id="r-nama"></strong>
        </p>
        <div class="form-group">
          <label>Password Baru <span class="req">*</span></label>
          <div style="position:relative;">
            <input type="password" name="np" id="r-pwd" class="form-control" placeholder="Min. 6 karakter" required autocomplete="new-password">
            <button type="button" onclick="togglePwd('r-pwd','r-eye')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:13px;">
              <i class="fa fa-eye" id="r-eye"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-reset')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-key"></i> Reset</button>
      </div>
    </form>
  </div>
</div>


<script>
const roleColors = {
    user:         {border:'#d1d5db', bg:'#f9fafb'},
    teknisi:      {border:'#93c5fd', bg:'#eff6ff'},
    teknisi_ipsrs:{border:'#6ee7b7', bg:'#f0fdf4'},
    hrd:          {border:'#f9a8d4', bg:'#fdf2f8'},
    keuangan:     {border:'#67e8f9', bg:'#ecfeff'},
    akreditasi:   {border:'#fde047', bg:'#fefce8'},
    admin:        {border:'#c4b5fd', bg:'#faf5ff'},
};

// Roles yang TIDAK menampilkan opsi tambah akreditasi
const NO_AKREDITASI_ROLES = ['akreditasi','admin','keuangan'];

function editUser(u) {
    document.getElementById('e-id').value    = u.id;
    document.getElementById('e-nama').value  = u.nama;
    document.getElementById('e-email').value = u.email;
    let found = false;
    document.querySelectorAll('#m-edit [name=role]').forEach(function(r) {
        r.checked = (r.value === u.role);
        if (r.checked) found = true;
    });
    if (!found) {
        const fb = document.querySelector('#m-edit [name=role][value=user]');
        if (fb) fb.checked = true;
    }
    hilightRole();
    const isAkrChk = document.getElementById('edit-is-akr');
    if (isAkrChk) isAkrChk.checked = (u.is_akreditasi === 1);
    togglePokjaEdit();
    const pokjaSel = document.getElementById('e-pokja');
    if (pokjaSel && u.pokja_id) {
        for (let i = 0; i < pokjaSel.options.length; i++)
            pokjaSel.options[i].selected = (pokjaSel.options[i].value == u.pokja_id);
    }
    openModal('m-edit');
}

function hilightRole() {
    document.querySelectorAll('#m-edit [name=role]').forEach(function(r) {
        const lbl = document.getElementById('lbl-' + r.value);
        if (!lbl) return;
        const c = roleColors[r.value] || {border:'#e5e7eb', bg:'#fff'};
        lbl.style.borderColor = r.checked ? c.border : '#e5e7eb';
        lbl.style.background  = r.checked ? c.bg     : '#fff';
    });
}

function hilightRoleAdd() {
    document.querySelectorAll('#m-add [name=role]').forEach(function(r) {
        const lbl = document.getElementById('albl-' + r.value);
        if (!lbl) return;
        const c = roleColors[r.value] || {border:'#e5e7eb', bg:'#fff'};
        lbl.style.borderColor = r.checked ? c.border : '#e5e7eb';
        lbl.style.background  = r.checked ? c.bg     : '#fff';
    });
}
hilightRoleAdd();

function togglePokjaEdit() {
    const rc    = document.querySelector('#m-edit [name=role]:checked');
    const role  = rc ? rc.value : 'user';
    const isAkr = role === 'akreditasi';
    const noAkr = NO_AKREDITASI_ROLES.includes(role);
    const ex    = document.getElementById('edit-akreditasi-extra');
    const chk   = document.getElementById('edit-is-akr');
    const grp   = document.getElementById('edit-pokja-group');
    if (ex)  ex.style.display  = (noAkr) ? 'none' : 'block';
    if (grp) grp.style.display = (isAkr || (chk && chk.checked)) ? 'block' : 'none';
    if ((isAkr || noAkr) && chk) chk.checked = false;
}

function togglePokjaAdd() {
    const rc    = document.querySelector('#m-add [name=role]:checked');
    const role  = rc ? rc.value : 'user';
    const isAkr = role === 'akreditasi';
    const noAkr = NO_AKREDITASI_ROLES.includes(role);
    const ex    = document.getElementById('add-akreditasi-extra');
    const chk   = document.getElementById('add-is-akr');
    const grp   = document.getElementById('add-pokja-group');
    if (ex)  ex.style.display  = (noAkr) ? 'none' : 'block';
    if (grp) grp.style.display = (isAkr || (chk && chk.checked)) ? 'block' : 'none';
    if ((isAkr || noAkr) && chk) chk.checked = false;
}

function resetPwd(id, nama) {
    document.getElementById('r-id').value = id;
    document.getElementById('r-nama').textContent = nama;
    document.getElementById('r-pwd').value = '';
    openModal('m-reset');
}

function togglePwd(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
    } else {
        inp.type = 'password';
        if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
    }
}
</script>

<?php include '../includes/footer.php'; ?>