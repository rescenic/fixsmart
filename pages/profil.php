<?php
// pages/profil.php
session_start(); require_once '../config.php'; requireLogin();
$page_title='Profil Saya'; $active_menu='profil';
$divs = getBagianList($pdo); // dari master bagian

$st=$pdo->prepare("SELECT * FROM users WHERE id=?"); $st->execute([$_SESSION['user_id']]); $user=$st->fetch();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act=$_POST['action']??'';
    if ($act==='profil') {
        $n=trim($_POST['nama']??''); $e=trim($_POST['email']??''); $d=$_POST['divisi']??''; $hp=trim($_POST['no_hp']??'');
        if ($n&&$e) { $pdo->prepare("UPDATE users SET nama=?,email=?,divisi=?,no_hp=? WHERE id=?")->execute([$n,$e,$d,$hp,$_SESSION['user_id']]); $_SESSION['user_nama']=$n; setFlash('success','Profil diperbarui.'); }
        else setFlash('danger','Nama dan email wajib diisi.');
    }
    if ($act==='password') {
        $old=$_POST['old']??''; $new=$_POST['new']??''; $cnf=$_POST['cnf']??'';
        if (!password_verify($old,$user['password'])) setFlash('danger','Password lama salah.');
        elseif (strlen($new)<6) setFlash('danger','Password baru min 6 karakter.');
        elseif ($new!==$cnf) setFlash('danger','Konfirmasi tidak cocok.');
        else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$_SESSION['user_id']]); setFlash('success','Password berhasil diubah.'); }
    }
    redirect(APP_URL.'/pages/profil.php');
}
$st->execute([$_SESSION['user_id']]); $user=$st->fetch();

$my=$pdo->prepare("SELECT status,COUNT(*) n FROM tiket WHERE user_id=? GROUP BY status"); $my->execute([$_SESSION['user_id']]); $ms=[];
foreach ($my->fetchAll() as $r) $ms[$r['status']]=$r['n'];
include '../includes/header.php';
?>
<div class="page-header"><h4><i class="fa fa-user-circle text-primary"></i> &nbsp;Profil Saya</h4>
<div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">Profil</span></div></div>
<div class="content"><?= showFlash() ?>
<div class="g2">
  <div>
    <div class="panel" style="text-align:center;padding:20px 14px;">
      <div class="av av-lg" style="margin:0 auto 10px;"><?= getInitials($user['nama']) ?></div>
      <h4 style="font-size:15px;color:#333;"><?= clean($user['nama']) ?></h4>
      <p style="color:#aaa;font-size:12px;margin-top:3px;"><?= clean($user['email']) ?></p>
      <p style="margin-top:7px;">
        <span style="font-size:11px;padding:2px 8px;border-radius:3px;background:<?= $user['role']==='admin'?'#ede9fe':($user['role']==='teknisi'?'#dbeafe':'#f3f4f6') ?>;color:<?= $user['role']==='admin'?'#7c3aed':($user['role']==='teknisi'?'#1d4ed8':'#374151') ?>;font-weight:700;"><?= ucfirst($user['role']) ?></span>
        &nbsp;<span style="font-size:11px;color:#aaa;"><?= clean($user['divisi']??'') ?></span>
      </p>
    </div>
    <div class="panel"><div class="panel-hd"><h5>Statistik Tiket</h5></div><div class="panel-bd">
      <?php foreach (['menunggu'=>['Menunggu','var(--yellow)'],'diproses'=>['Diproses','var(--blue)'],'selesai'=>['Selesai','var(--green)'],'ditolak'=>['Ditolak','var(--red)']] as $s=>[$l,$c]): ?>
      <div class="d-flex ai-c" style="justify-content:space-between;margin-bottom:8px;font-size:12px;">
        <span style="color:#888;"><?= $l ?></span><strong style="color:<?= $c ?>;"><?= $ms[$s]??0 ?></strong>
      </div>
      <?php endforeach; ?>
    </div></div>
  </div>
  <div class="panel">
    <div class="panel-hd" style="padding:0 15px;"><div class="tabs" style="margin:0;">
      <button class="tab-btn active" onclick="switchTab(this,'tp-p')">Edit Profil</button>
      <button class="tab-btn" onclick="switchTab(this,'tp-pw')">Ganti Password</button>
    </div></div>
    <div class="panel-bd">
      <div id="tp-p" class="tab-c active">
        <form method="POST"><input type="hidden" name="action" value="profil">
          <div class="form-group"><label>Nama Lengkap <span class="req">*</span></label><input type="text" name="nama" class="form-control" value="<?= clean($user['nama']) ?>" required></div>
          <div class="form-group"><label>Email <span class="req">*</span></label><input type="email" name="email" class="form-control" value="<?= clean($user['email']) ?>" required></div>
          <div class="form-row">
            <div class="form-group"><label>Divisi</label><select name="divisi" class="form-control"><option value="">—</option><?php foreach ($divs as $dv): ?><option value="<?= clean($dv['nama']) ?>" <?= $user['divisi']===$dv['nama']?'selected':'' ?>><?= clean($dv['nama']) ?><?= $dv['kode'] ? ' ('.$dv['kode'].')' : '' ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>No. HP</label><input type="text" name="no_hp" class="form-control" value="<?= clean($user['no_hp']??'') ?>"></div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
        </form>
      </div>
      <div id="tp-pw" class="tab-c">
        <form method="POST"><input type="hidden" name="action" value="password">
          <div class="form-group"><label>Password Lama <span class="req">*</span></label><input type="password" name="old" class="form-control" required></div>
          <div class="form-group"><label>Password Baru <span class="req">*</span></label><input type="password" name="new" class="form-control" placeholder="Min. 6 karakter" required></div>
          <div class="form-group"><label>Konfirmasi <span class="req">*</span></label><input type="password" name="cnf" class="form-control" required></div>
          <button type="submit" class="btn btn-primary"><i class="fa fa-lock"></i> Ganti Password</button>
        </form>
      </div>
    </div>
  </div>
</div></div>
<?php include '../includes/footer.php'; ?>
