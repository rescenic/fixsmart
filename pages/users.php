<?php
// pages/users.php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin','hrd'])) { setFlash('danger', 'Akses ditolak.'); redirect(APP_URL . '/dashboard.php'); }
$page_title  = 'Pengguna';
$active_menu = 'users';

// ── Definisi role valid ───────────────────────────────────────────────────────
define('VALID_ROLES', ['user','teknisi','teknisi_ipsrs','admin','hrd','akreditasi']);

$role_labels = [
    'admin'         => 'Admin',
    'teknisi'       => 'Teknisi IT',
    'teknisi_ipsrs' => 'Teknisi IPSRS',
    'hrd'           => 'HRD',
    'akreditasi'    => 'Tim Akreditasi',
    'user'          => 'User',
];

$is_hrd   = ($_SESSION['user_role'] ?? '') === 'hrd';
$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';

// HRD tidak bisa assign role admin & akreditasi
$assignable_roles = $is_hrd
    ? ['user','teknisi','teknisi_ipsrs','hrd']
    : VALID_ROLES;

// ── Daftar Pokja ──────────────────────────────────────────────────────────────
$pokja_list = [];
try {
    $pokja_list = $pdo->query("SELECT id,kode,nama FROM master_pokja WHERE status='aktif' ORDER BY urutan,kode")->fetchAll();
} catch (Exception $e) {}

// ── POST Handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'tambah') {
        if ($is_hrd && !hasRole('admin')) {
            setFlash('danger', 'HRD tidak berwenang menambah akun Admin atau Tim Akreditasi.');
            redirect(APP_URL . '/pages/users.php');
        }
        $n  = trim($_POST['nama']     ?? '');
        $u  = trim($_POST['username'] ?? '');
        $e  = trim($_POST['email']    ?? '');
        $p  = $_POST['password']      ?? '';
        $r  = $_POST['role']          ?? 'user';
        $d  = $_POST['divisi']        ?? '';
        $hp = trim($_POST['no_hp']    ?? '');
        $is_akr_flag = isset($_POST['is_akreditasi']) ? 1 : 0;
        $pokja_id = ($r === 'akreditasi' || $is_akr_flag) ? ((int)($_POST['pokja_id'] ?? 0) ?: null) : null;

        if (!in_array($r, $assignable_roles)) $r = 'user';

        if ($n && $u && $e && strlen($p) >= 6) {
            $st = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
            $st->execute([$u, $e]);
            if ($st->fetch()) {
                setFlash('warning', 'Username atau email sudah digunakan.');
            } else {
                $pdo->prepare("INSERT INTO users (nama,username,email,password,role,pokja_id,is_akreditasi,status) VALUES (?,?,?,?,?,?,?,'aktif')")
                    ->execute([$n,$u,$e,password_hash($p,PASSWORD_BCRYPT),$r,$pokja_id,$is_akr_flag]);
                setFlash('success', "Pengguna <strong>" . clean($n) . "</strong> berhasil ditambahkan.");
            }
        } else {
            setFlash('danger', 'Harap isi semua field wajib (password min 6 karakter).');
        }
    }

    elseif ($act === 'edit') {
        $id  = (int)($_POST['id']    ?? 0);
        $n   = trim($_POST['nama']   ?? '');
        $e   = trim($_POST['email']  ?? '');
        $r   = $_POST['role']        ?? '';
        $is_akr_flag = isset($_POST['is_akreditasi']) ? 1 : 0;
        // pokja_id wajib jika role akreditasi ATAU is_akreditasi flag aktif
        $pokja_id = ($r === 'akreditasi' || $is_akr_flag) ? ((int)($_POST['pokja_id'] ?? 0) ?: null) : null;

        if ($is_hrd) {
            $tgt = $pdo->prepare("SELECT role FROM users WHERE id=?");
            $tgt->execute([$id]);
            $tgt_role = $tgt->fetchColumn();
            if ($tgt_role === 'admin') {
                setFlash('danger', 'HRD tidak berwenang mengubah akun Admin.');
                redirect(APP_URL . '/pages/users.php');
            }
        }
        if (!in_array($r, $assignable_roles)) {
            setFlash('danger', 'Role tidak valid.');
            redirect(APP_URL . '/pages/users.php');
        }
        if (!$id || !$n || !$e || !$r) {
            setFlash('danger', 'Data tidak lengkap.');
        } elseif ($id === (int)$_SESSION['user_id'] && $r !== $_SESSION['user_role']) {
            setFlash('warning', 'Tidak bisa mengubah role akun Anda sendiri.');
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $chk->execute([$e, $id]);
            if ($chk->fetch()) {
                setFlash('warning', 'Email sudah digunakan akun lain.');
            } else {
                $pdo->prepare("UPDATE users SET nama=?,email=?,role=?,pokja_id=?,is_akreditasi=? WHERE id=?")
                    ->execute([$n,$e,$r,$pokja_id,$is_akr_flag,$id]);
                setFlash('success', "Data pengguna <strong>" . clean($n) . "</strong> berhasil diperbarui.");
            }
        }
    }

    elseif ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            setFlash('warning', 'Tidak bisa menonaktifkan akun Anda sendiri.');
        } else {
            $pdo->prepare("UPDATE users SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
            setFlash('success', 'Status pengguna berhasil diubah.');
        }
    }

    elseif ($act === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $p  = $_POST['np'] ?? '';
        if (!$id) setFlash('danger','ID tidak valid.');
        elseif (strlen($p) < 6) setFlash('danger','Password minimal 6 karakter.');
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($p,PASSWORD_BCRYPT),$id]);
            setFlash('success','Password berhasil direset.');
        }
    }

    redirect(APP_URL . '/pages/users.php');
}

// ── Ambil data pengguna dengan JOIN pokja ─────────────────────────────────────
// Pastikan kolom is_akreditasi ada
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `is_akreditasi` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `pokja_id`");
} catch (Exception $e) {}

$users = $pdo->query("
    SELECT u.*,
           pk.kode  AS pokja_kode,
           pk.nama  AS pokja_nama,
           COALESCE(u.status,'aktif') AS status,
           COALESCE(u.is_akreditasi,0) AS is_akreditasi,
           (SELECT COUNT(*) FROM tiket       WHERE user_id    = u.id) AS req,
           (SELECT COUNT(*) FROM tiket       WHERE teknisi_id = u.id) AS handle,
           (SELECT COUNT(*) FROM tiket_ipsrs WHERE teknisi_id = u.id) AS handle_ipsrs,
           (SELECT COUNT(*) FROM tiket_ipsrs WHERE user_id    = u.id) AS req_ipsrs
    FROM users u
    LEFT JOIN master_pokja pk ON pk.id = u.pokja_id
    ORDER BY FIELD(u.role,'admin','hrd','akreditasi','teknisi','teknisi_ipsrs','user'), u.nama
")->fetchAll();

// Badge per role
$cnt_roles = ['admin'=>0,'teknisi'=>0,'teknisi_ipsrs'=>0,'hrd'=>0,'akreditasi'=>0,'user'=>0,'nonaktif'=>0];
foreach ($users as $u) {
    if (($u['status'] ?? 'aktif') === 'nonaktif') { $cnt_roles['nonaktif']++; continue; }
    $key = $u['role'];
    if (isset($cnt_roles[$key])) $cnt_roles[$key]++;
}

// Style per role
$rstyle = [
    'admin'         => ['bg'=>'#ede9fe','color'=>'#7c3aed','av'=>'av-purple','icon'=>'fa-shield-alt'],
    'teknisi'       => ['bg'=>'#dbeafe','color'=>'#1d4ed8','av'=>'av-blue',  'icon'=>'fa-wrench'],
    'teknisi_ipsrs' => ['bg'=>'#d1fae5','color'=>'#065f46','av'=>'av-green', 'icon'=>'fa-screwdriver-wrench'],
    'hrd'           => ['bg'=>'#fce7f3','color'=>'#9d174d','av'=>'av-pink',  'icon'=>'fa-people-group'],
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

  <!-- Summary Cards -->
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach ([
      ['admin',        'Admin',          'fa-shield-alt',         '#ede9fe','#7c3aed'],
      ['teknisi',      'Teknisi IT',     'fa-wrench',             '#dbeafe','#1d4ed8'],
      ['teknisi_ipsrs','Teknisi IPSRS',  'fa-screwdriver-wrench', '#d1fae5','#065f46'],
      ['hrd',          'HRD',            'fa-people-group',       '#fce7f3','#9d174d'],
      ['akreditasi',   'Tim Akreditasi', 'fa-medal',              '#fef9c3','#854d0e'],
      ['user',         'User',           'fa-user',               '#f3f4f6','#374151'],
      ['nonaktif',     'Nonaktif',       'fa-ban',                '#fee2e2','#991b1b'],
    ] as [$key,$lbl,$ic,$bg,$col]): ?>
    <div style="background:#fff;border:1px solid #f0f0f0;border-radius:8px;padding:10px 16px;
                display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.05);min-width:120px;">
      <div style="width:36px;height:36px;border-radius:50%;background:<?= $bg ?>;
                  display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa <?= $ic ?>" style="color:<?= $col ?>;font-size:14px;"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;color:#1e293b;line-height:1;"><?= $cnt_roles[$key] ?? 0 ?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:1px;"><?= $lbl ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tabel Pengguna -->
  <div class="panel">
    <div class="panel-hd">
      <h5>Daftar Pengguna <span style="color:#94a3b8;font-weight:400;">(<?= count($users) ?>)</span></h5>
      <button class="btn btn-primary btn-sm" onclick="openModal('m-add')">
        <i class="fa fa-plus"></i> Tambah Pengguna
      </button>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Nama &amp; Email</th>
            <th>Username</th>
            <th>Bagian</th>
            <th>Role</th>
            <th>Pokja / Aktivitas</th>
            <th>Status</th>
            <th style="text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php $no = 1; foreach ($users as $u):
            $role   = $u['role']   ?? 'user';
            $status = $u['status'] ?? 'aktif';
            $rs     = $rstyle[$role] ?? $rstyle['user'];
            $rl     = $role_labels[$role] ?? ucfirst($role);
            $can_edit = !($is_hrd && $role === 'admin');
          ?>
          <tr>
            <td style="color:#cbd5e1;"><?= $no++ ?></td>
            <td>
              <div class="d-flex ai-c gap6">
                <div class="av av-sm <?= $rs['av'] ?>"><?= getInitials($u['nama']) ?></div>
                <div>
                  <div style="font-weight:600;color:#1e293b;"><?= clean($u['nama']) ?></div>
                  <div style="font-size:11px;color:#94a3b8;"><?= clean($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-family:monospace;font-size:12px;color:#64748b;">@<?= clean($u['username']) ?></td>
            <td style="font-size:12px;color:#475569;"><?= clean($u['divisi'] ?? '—') ?: '—' ?></td>
            <td>
              <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;
                           padding:3px 10px;border-radius:20px;background:<?= $rs['bg'] ?>;color:<?= $rs['color'] ?>;">
                <i class="fa <?= $rs['icon'] ?>" style="font-size:10px;"></i> <?= $rl ?>
              </span>
            </td>
            <td style="font-size:12px;">
              <?php if ($role === 'akreditasi'): ?>
                <?php if (!empty($u['pokja_kode'])): ?>
                <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;
                             padding:3px 9px;border-radius:6px;background:#fef9c3;color:#854d0e;">
                  <i class="fa fa-medal" style="font-size:10px;"></i>
                  <?= clean($u['pokja_kode']) ?> — <?= clean($u['pokja_nama']) ?>
                </span>
                <?php else: ?>
                <span style="color:#fbbf24;font-size:11px;">
                  <i class="fa fa-triangle-exclamation"></i> Belum ada Pokja
                </span>
                <?php endif; ?>
              <?php elseif ((int)($u['is_akreditasi'] ?? 0) === 1): ?>
                <!-- Role lain tapi punya akses akreditasi -->
                <div style="display:flex;flex-direction:column;gap:3px;">
                  <?php if ($role === 'teknisi'): ?>
                  <span style="color:#1d4ed8;"><i class="fa fa-wrench"></i> <?= (int)$u['handle'] ?> tiket IT</span>
                  <?php elseif ($role === 'teknisi_ipsrs'): ?>
                  <span style="color:#065f46;"><i class="fa fa-screwdriver-wrench"></i> <?= (int)$u['handle_ipsrs'] ?> tiket IPSRS</span>
                  <?php elseif ($role === 'hrd'): ?>
                  <span style="color:#9d174d;"><i class="fa fa-people-group"></i> SDM &amp; Kepegawaian</span>
                  <?php else: ?>
                  <span style="color:#1d4ed8;"><i class="fa fa-paper-plane"></i> <?= (int)$u['req'] + (int)$u['req_ipsrs'] ?> tiket</span>
                  <?php endif; ?>
                  <?php if (!empty($u['pokja_kode'])): ?>
                  <span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;
                               padding:2px 7px;border-radius:4px;background:#fef9c3;color:#854d0e;width:fit-content;">
                    <i class="fa fa-medal" style="font-size:9px;"></i>
                    <?= clean($u['pokja_kode']) ?> — <?= clean($u['pokja_nama']) ?>
                  </span>
                  <?php else: ?>
                  <span style="font-size:10px;color:#fbbf24;">
                    <i class="fa fa-medal"></i> Akreditasi (belum ada Pokja)
                  </span>
                  <?php endif; ?>
                </div>
              <?php elseif ($role === 'teknisi'): ?>
                <span style="color:#1d4ed8;"><i class="fa fa-wrench"></i> <?= (int)$u['handle'] ?> tiket IT</span>
              <?php elseif ($role === 'teknisi_ipsrs'): ?>
                <span style="color:#065f46;"><i class="fa fa-screwdriver-wrench"></i> <?= (int)$u['handle_ipsrs'] ?> tiket IPSRS</span>
              <?php elseif ($role === 'admin'): ?>
                <span style="color:#7c3aed;"><i class="fa fa-shield-alt"></i> Administrator</span>
              <?php elseif ($role === 'hrd'): ?>
                <span style="color:#9d174d;"><i class="fa fa-people-group"></i> SDM &amp; Kepegawaian</span>
              <?php else: ?>
                <span style="color:#1d4ed8;">
                  <i class="fa fa-paper-plane"></i>
                  <?= (int)$u['req'] + (int)$u['req_ipsrs'] ?> tiket
                </span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($status === 'aktif'): ?>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;
                           padding:2px 10px;border-radius:20px;background:#d1fae5;color:#065f46;">
                <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;"></span> Aktif
              </span>
              <?php else: ?>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;
                           padding:2px 10px;border-radius:20px;background:#fee2e2;color:#991b1b;">
                <span style="width:6px;height:6px;border-radius:50%;background:#ef4444;"></span> Nonaktif
              </span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;white-space:nowrap;">
              <?php if ($u['id'] != (int)$_SESSION['user_id'] && $can_edit): ?>
              <button class="btn btn-warning btn-sm" title="Edit"
                onclick='editUser(<?= json_encode([
                  "id"            => (int)$u["id"],
                  "nama"          => $u["nama"],
                  "email"         => $u["email"],
                  "role"          => $u["role"],
                  "pokja_id"      => (int)($u["pokja_id"] ?? 0),
                  "is_akreditasi" => (int)($u["is_akreditasi"] ?? 0),
                ]) ?>)'>
                <i class="fa fa-edit"></i> Edit
              </button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit"
                  class="btn btn-sm <?= $status === 'aktif' ? 'btn-default' : 'btn-success' ?>"
                  onclick="return confirm('<?= $status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?> pengguna ini?')">
                  <i class="fa <?= $status === 'aktif' ? 'fa-ban' : 'fa-check' ?>"></i>
                </button>
              </form>
              <button class="btn btn-info btn-sm" title="Reset password"
                onclick="resetPwd(<?= (int)$u['id'] ?>,'<?= addslashes(clean($u['nama'])) ?>')">
                <i class="fa fa-key"></i>
              </button>
              <?php elseif ($u['id'] == (int)$_SESSION['user_id']): ?>
              <span style="font-size:11px;color:#94a3b8;font-style:italic;">Akun Anda</span>
              <?php else: ?>
              <span style="font-size:11px;color:#cbd5e1;" title="Tidak berwenang"><i class="fa fa-lock"></i></span>
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


<!-- ══ MODAL: Tambah Pengguna ══ -->
<div class="modal-ov" id="m-add">
  <div class="modal-box">
    <div class="modal-hd">
      <h5><i class="fa fa-user-plus"></i> Tambah Pengguna</h5>
      <button class="mc" onclick="closeModal('m-add')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah">
      <div class="modal-bd">
        <div class="form-row">
          <div class="form-group">
            <label>Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" class="form-control" required placeholder="Nama lengkap">
          </div>
          <div class="form-group">
            <label>Username <span class="req">*</span></label>
            <input type="text" name="username" class="form-control" required autocomplete="off">
          </div>
        </div>
        <div class="form-group">
          <label>Email <span class="req">*</span></label>
          <input type="email" name="email" class="form-control" required>
        </div>

        <!-- Role cards -->
        <div class="form-group">
          <label>Role <span class="req">*</span></label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <?php
            $role_opts = [
              ['user',         'User',           'fa-user',               '#f3f4f6','#374151','Buat & pantau tiket sendiri',  true],
              ['teknisi',      'Teknisi IT',     'fa-wrench',             '#dbeafe','#1d4ed8','Proses & selesaikan tiket IT', false],
              ['teknisi_ipsrs','Teknisi IPSRS',  'fa-screwdriver-wrench', '#d1fae5','#065f46','Proses tiket Sarpras/IPSRS',  false],
              ['hrd',          'HRD',            'fa-people-group',       '#fce7f3','#9d174d','Manajemen SDM & kepegawaian',  false],
              ['akreditasi',   'Tim Akreditasi', 'fa-medal',              '#fef9c3','#854d0e','Hanya akses akreditasi (tanpa tiket)',    false],
              ['admin',        'Admin',          'fa-shield-alt',         '#ede9fe','#7c3aed','Akses penuh ke semua fitur',   false],
            ];
            if ($is_hrd) $role_opts = array_filter($role_opts, fn($r) => !in_array($r[0], ['admin','akreditasi']));
            foreach ($role_opts as [$rv,$rl,$ri,$rbg,$rc,$rdesc,$checked]):
            ?>
            <label id="albl-<?= $rv ?>" style="border:2px solid #e5e7eb;border-radius:8px;padding:10px;
                   cursor:pointer;transition:all .18s;display:flex;gap:8px;align-items:flex-start;
                   background:<?= $checked ? $rbg : '#fff' ?>;">
              <input type="radio" name="role" value="<?= $rv ?>" <?= $checked ? 'checked' : '' ?>
                     style="margin-top:3px;flex-shrink:0;" onchange="hilightRoleAdd();togglePokjaAdd()">
              <div>
                <div style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700;">
                  <span style="width:20px;height:20px;border-radius:50%;background:<?= $rbg ?>;
                               display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa <?= $ri ?>" style="color:<?= $rc ?>;font-size:9px;"></i>
                  </span>
                  <span style="color:<?= $rc ?>;"><?= $rl ?></span>
                </div>
                <div style="font-size:10px;color:#94a3b8;margin-top:3px;line-height:1.4;"><?= $rdesc ?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Akses Akreditasi tambahan (untuk role selain 'akreditasi') -->
        <div class="form-group" id="add-akreditasi-extra" style="display:none;">
          <div style="background:#fefce8;border:1px dashed #fbbf24;border-radius:8px;padding:10px 13px;">
            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;margin:0;">
              <input type="checkbox" name="is_akreditasi" id="add-is-akr" value="1"
                     onchange="togglePokjaAdd()"
                     style="width:16px;height:16px;cursor:pointer;margin-top:2px;accent-color:#d97706;">
              <div>
                <div style="font-size:12.5px;font-weight:700;color:#854d0e;">
                  <i class="fa fa-medal" style="font-size:11px;"></i>
                  Tambahkan akses Akreditasi
                </div>
                <div style="font-size:10.5px;color:#a16207;margin-top:2px;line-height:1.5;">
                  Centang ini agar orang ini <strong>juga bisa input & lihat dokumen akreditasi</strong>,
                  tanpa mengganti role utamanya. Pilih Pokja yang sesuai.
                </div>
              </div>
            </label>
          </div>
        </div>

        <!-- Pokja (muncul jika role = akreditasi ATAU checkbox akreditasi dicentang) -->
        <div class="form-group" id="add-pokja-group" style="display:none;">
          <label>Pokja Akreditasi <span class="req">*</span></label>
          <select name="pokja_id" id="add-pokja-sel" class="form-control">
            <option value="">— Pilih Pokja —</option>
            <?php foreach ($pokja_list as $pk): ?>
            <option value="<?= (int)$pk['id'] ?>"><?= clean($pk['kode']) ?> — <?= clean($pk['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <small style="color:#9ca3af;font-size:10.5px;margin-top:3px;display:block;">
            <i class="fa fa-info-circle"></i> Satu orang hanya bisa di satu Pokja
          </small>
        </div>

        <div class="form-group">
          <label>Password <span class="req">*</span></label>
          <div style="position:relative;">
            <input type="password" name="password" id="a-pwd" class="form-control"
                   placeholder="Min. 6 karakter" required autocomplete="new-password">
            <button type="button" onclick="togglePwd('a-pwd','a-eye')"
                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                           background:none;border:none;cursor:pointer;color:#94a3b8;font-size:13px;">
              <i class="fa fa-eye" id="a-eye"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-add')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>


<!-- ══ MODAL: Edit Pengguna ══ -->
<div class="modal-ov" id="m-edit">
  <div class="modal-box">
    <div class="modal-hd">
      <h5><i class="fa fa-user-edit"></i> Edit Pengguna</h5>
      <button class="mc" onclick="closeModal('m-edit')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e-id">
      <div class="modal-bd">
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;
                    padding:9px 12px;margin-bottom:14px;font-size:12px;color:#92400e;">
          <i class="fa fa-triangle-exclamation"></i>
          Mengubah <strong>Role</strong> akan langsung mengubah hak akses pengguna di seluruh sistem.
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" id="e-nama" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Email <span class="req">*</span></label>
            <input type="email" name="email" id="e-email" class="form-control" required>
          </div>
        </div>

        <!-- Role cards -->
        <div class="form-group">
          <label>Role <span class="req">*</span></label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;" id="edit-role-grid">
            <?php
            $edit_role_opts = [
              ['user',         'User',           'fa-user',               '#f3f4f6','#374151','Buat & pantau tiket sendiri'],
              ['teknisi',      'Teknisi IT',     'fa-wrench',             '#dbeafe','#1d4ed8','Proses & selesaikan tiket IT'],
              ['teknisi_ipsrs','Teknisi IPSRS',  'fa-screwdriver-wrench', '#d1fae5','#065f46','Proses tiket Sarpras/IPSRS'],
              ['hrd',          'HRD',            'fa-people-group',       '#fce7f3','#9d174d','Manajemen SDM & kepegawaian'],
              ['akreditasi',   'Tim Akreditasi', 'fa-medal',              '#fef9c3','#854d0e','Hanya akses akreditasi (tanpa tiket)'],
              ['admin',        'Admin',          'fa-shield-alt',         '#ede9fe','#7c3aed','Akses penuh ke semua fitur'],
            ];
            if ($is_hrd) $edit_role_opts = array_filter($edit_role_opts, fn($r) => !in_array($r[0], ['admin','akreditasi']));
            foreach ($edit_role_opts as [$rv,$rl,$ri,$rbg,$rc,$rdesc]):
            ?>
            <label id="lbl-<?= $rv ?>" style="border:2px solid #e5e7eb;border-radius:8px;padding:10px;
                   cursor:pointer;transition:border-color .18s,background .18s;
                   display:flex;gap:8px;align-items:flex-start;background:#fff;">
              <input type="radio" name="role" value="<?= $rv ?>"
                     style="margin-top:3px;flex-shrink:0;"
                     onchange="hilightRole();togglePokjaEdit()">
              <div>
                <div style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700;">
                  <span style="width:20px;height:20px;border-radius:50%;background:<?= $rbg ?>;
                               display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa <?= $ri ?>" style="color:<?= $rc ?>;font-size:9px;"></i>
                  </span>
                  <span style="color:<?= $rc ?>;"><?= $rl ?></span>
                </div>
                <div style="font-size:10px;color:#94a3b8;margin-top:3px;line-height:1.4;"><?= $rdesc ?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Akses Akreditasi tambahan (untuk role selain 'akreditasi') -->
        <div class="form-group" id="edit-akreditasi-extra" style="display:none;">
          <div style="background:#fefce8;border:1px dashed #fbbf24;border-radius:8px;padding:10px 13px;">
            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;margin:0;">
              <input type="checkbox" name="is_akreditasi" id="edit-is-akr" value="1"
                     onchange="togglePokjaEdit()"
                     style="width:16px;height:16px;cursor:pointer;margin-top:2px;accent-color:#d97706;">
              <div>
                <div style="font-size:12.5px;font-weight:700;color:#854d0e;">
                  <i class="fa fa-medal" style="font-size:11px;"></i>
                  Tambahkan akses Akreditasi
                </div>
                <div style="font-size:10.5px;color:#a16207;margin-top:2px;line-height:1.5;">
                  Centang ini agar orang ini <strong>juga bisa input & lihat dokumen akreditasi</strong>,
                  tanpa mengganti role utamanya. Pilih Pokja yang sesuai.
                </div>
              </div>
            </label>
          </div>
        </div>

        <!-- Pokja (muncul jika role = akreditasi ATAU checkbox dicentang) -->
        <div class="form-group" id="edit-pokja-group" style="display:none;">
          <label>Pokja Akreditasi <span class="req">*</span></label>
          <select name="pokja_id" id="e-pokja" class="form-control">
            <option value="">— Pilih Pokja —</option>
            <?php foreach ($pokja_list as $pk): ?>
            <option value="<?= (int)$pk['id'] ?>"><?= clean($pk['kode']) ?> — <?= clean($pk['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <small style="color:#9ca3af;font-size:10.5px;margin-top:3px;display:block;">
            <i class="fa fa-info-circle"></i> Satu orang hanya bisa di satu Pokja
          </small>
        </div>

      </div>
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
          <i class="fa fa-user" style="color:var(--primary);"></i>
          Reset password untuk: <strong id="r-nama"></strong>
        </p>
        <div class="form-group">
          <label>Password Baru <span class="req">*</span></label>
          <div style="position:relative;">
            <input type="password" name="np" id="r-pwd" class="form-control"
                   placeholder="Min. 6 karakter" required autocomplete="new-password">
            <button type="button" onclick="togglePwd('r-pwd','r-eye')"
                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                           background:none;border:none;cursor:pointer;color:#94a3b8;font-size:13px;">
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
  user:         { border:'#d1d5db', bg:'#f9fafb' },
  teknisi:      { border:'#93c5fd', bg:'#eff6ff' },
  teknisi_ipsrs:{ border:'#6ee7b7', bg:'#f0fdf4' },
  hrd:          { border:'#f9a8d4', bg:'#fdf2f8' },
  akreditasi:   { border:'#fde047', bg:'#fefce8' },
  admin:        { border:'#c4b5fd', bg:'#faf5ff' },
};

function editUser(u) {
  document.getElementById('e-id').value    = u.id;
  document.getElementById('e-nama').value  = u.nama;
  document.getElementById('e-email').value = u.email;

  // Set radio role DULU sebelum toggle supaya isAkrRole terbaca benar
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

  // Set checkbox is_akreditasi (hanya relevan jika role bukan 'akreditasi')
  const isAkrChk = document.getElementById('edit-is-akr');
  if (isAkrChk) isAkrChk.checked = (u.is_akreditasi === 1);

  // Tampilkan/sembunyikan kotak extra & pokja berdasarkan role + checkbox
  togglePokjaEdit();

  // Set pokja setelah toggle (supaya select sudah visible)
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
    const c = roleColors[r.value] || { border:'#e5e7eb', bg:'#fff' };
    lbl.style.borderColor = r.checked ? c.border : '#e5e7eb';
    lbl.style.background  = r.checked ? c.bg      : '#fff';
  });
}

function hilightRoleAdd() {
  document.querySelectorAll('#m-add [name=role]').forEach(function(r) {
    const lbl = document.getElementById('albl-' + r.value);
    if (!lbl) return;
    const c = roleColors[r.value] || { border:'#e5e7eb', bg:'#fff' };
    lbl.style.borderColor = r.checked ? c.border : '#e5e7eb';
    lbl.style.background  = r.checked ? c.bg      : '#fff';
  });
}
hilightRoleAdd();

// Tampilkan/sembunyikan field Pokja & checkbox akreditasi berdasarkan role
function togglePokjaEdit() {
  const roleChecked = document.querySelector('#m-edit [name=role]:checked');
  const isAkrRole   = roleChecked && roleChecked.value === 'akreditasi';
  const extraBox    = document.getElementById('edit-akreditasi-extra');
  const chk         = document.getElementById('edit-is-akr');
  const grp         = document.getElementById('edit-pokja-group');

  // Tampilkan checkbox "Juga Tim Akreditasi" hanya jika role BUKAN akreditasi
  if (extraBox) extraBox.style.display = isAkrRole ? 'none' : 'block';

  // Pokja tampil jika: role=akreditasi ATAU checkbox dicentang
  const needPokja = isAkrRole || (chk && chk.checked);
  if (grp) grp.style.display = needPokja ? 'block' : 'none';

  // Jika role berubah ke akreditasi, un-check & sembunyikan checkbox
  if (isAkrRole && chk) chk.checked = false;
}
function togglePokjaAdd() {
  const roleChecked = document.querySelector('#m-add [name=role]:checked');
  const isAkrRole   = roleChecked && roleChecked.value === 'akreditasi';
  const extraBox    = document.getElementById('add-akreditasi-extra');
  const chk         = document.getElementById('add-is-akr');
  const grp         = document.getElementById('add-pokja-group');

  if (extraBox) extraBox.style.display = isAkrRole ? 'none' : 'block';

  const needPokja = isAkrRole || (chk && chk.checked);
  if (grp) grp.style.display = needPokja ? 'block' : 'none';

  if (isAkrRole && chk) chk.checked = false;
}

function resetPwd(id, nama) {
  document.getElementById('r-id').value = id;
  document.getElementById('r-nama').textContent = nama;
  document.getElementById('r-pwd').value = '';
  openModal('m-reset');
}

function togglePwd(inputId, iconId) {
  const inp  = document.getElementById(inputId);
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