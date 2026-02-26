<?php
// pages/kategori.php
session_start(); require_once '../config.php'; requireLogin();
if (!hasRole(['admin','teknisi'])) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title='Kategori'; $active_menu='kategori';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act=$_POST['action']??''; $nama=trim($_POST['nama']??''); $desc=trim($_POST['deskripsi']??'');
    $icon=$_POST['icon']??'fa-tag'; $sla=(int)($_POST['sla_jam']??24); $sla_r=(int)($_POST['sla_respon_jam']??4);
    if ($act==='tambah'&&$nama) {
        $pdo->prepare("INSERT INTO kategori (nama,deskripsi,icon,sla_jam,sla_respon_jam) VALUES (?,?,?,?,?)")->execute([$nama,$desc,$icon,$sla,$sla_r]);
        setFlash('success','Kategori berhasil ditambahkan.');
    } elseif ($act==='edit'&&$nama) {
        $pdo->prepare("UPDATE kategori SET nama=?,deskripsi=?,icon=?,sla_jam=?,sla_respon_jam=? WHERE id=?")->execute([$nama,$desc,$icon,$sla,$sla_r,(int)$_POST['id']]);
        setFlash('success','Kategori berhasil diperbarui.');
    } elseif ($act==='hapus') {
        $st=$pdo->prepare("SELECT COUNT(*) FROM tiket WHERE kategori_id=?"); $st->execute([(int)$_POST['id']]);
        if ($st->fetchColumn()>0) setFlash('warning','Kategori tidak dapat dihapus, masih ada tiket.');
        else { $pdo->prepare("DELETE FROM kategori WHERE id=?")->execute([(int)$_POST['id']]); setFlash('success','Kategori dihapus.'); }
    }
    redirect(APP_URL.'/pages/kategori.php');
}
$list=$pdo->query("SELECT k.*,COUNT(t.id) as total FROM kategori k LEFT JOIN tiket t ON t.kategori_id=k.id GROUP BY k.id ORDER BY k.nama")->fetchAll();
$icons=['fa-desktop','fa-laptop-code','fa-network-wired','fa-envelope','fa-print','fa-video','fa-server','fa-tag','fa-question-circle','fa-wifi','fa-hdd','fa-mouse'];
include '../includes/header.php';
?>
<div class="page-header"><h4><i class="fa fa-tags text-primary"></i> &nbsp;Kategori</h4>
<div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">Kategori</span></div></div>
<div class="content"><?= showFlash() ?>
<div class="g2">
  <div class="panel"><div class="panel-hd"><h5>Daftar Kategori</h5></div><div class="panel-bd np tbl-wrap">
    <table><thead><tr><th>Ikon</th><th>Nama</th><th>Target SLA</th><th>Respon</th><th>Tiket</th><th>Aksi</th></tr></thead><tbody>
      <?php foreach ($list as $k): ?>
      <tr>
        <td><i class="fa <?= clean($k['icon']) ?>" style="color:var(--primary);font-size:16px;"></i></td>
        <td><strong><?= clean($k['nama']) ?></strong><br><small style="color:#aaa;"><?= clean($k['deskripsi']??'') ?></small></td>
        <td><?= $k['sla_jam'] ?> jam</td><td><?= $k['sla_respon_jam'] ?> jam</td>
        <td><span style="font-weight:700;"><?= $k['total'] ?></span></td>
        <td>
          <button class="btn btn-warning btn-sm" onclick='editKat(<?= json_encode($k) ?>)'><i class="fa fa-edit"></i></button>
          <?php if (!$k['total']): ?><form method="POST" style="display:inline;"><input type="hidden" name="action" value="hapus"><input type="hidden" name="id" value="<?= $k['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus?')"><i class="fa fa-trash"></i></button></form><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table>
  </div></div>
  <div class="panel"><div class="panel-hd"><h5 id="ft">Tambah Kategori</h5></div><div class="panel-bd">
    <form method="POST" id="kf"><input type="hidden" name="action" value="tambah" id="fa"><input type="hidden" name="id" id="fid">
      <div class="form-group"><label>Nama <span class="req">*</span></label><input type="text" name="nama" id="fn" class="form-control" required></div>
      <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" id="fd" class="form-control"></textarea></div>
      <div class="form-row">
        <div class="form-group"><label>Target SLA (jam)</label><input type="number" name="sla_jam" id="fs" class="form-control" value="24" min="1"></div>
        <div class="form-group"><label>Target Respon (jam)</label><input type="number" name="sla_respon_jam" id="fr" class="form-control" value="4" min="1"></div>
      </div>
      <div class="form-group"><label>Ikon</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <select name="icon" id="fi" class="form-control" style="flex:1;" onchange="document.getElementById('fp').className='fa '+this.value">
            <?php foreach ($icons as $ic): ?><option value="<?= $ic ?>"><?= $ic ?></option><?php endforeach; ?>
          </select>
          <div style="width:34px;height:34px;background:#f0f0f0;border-radius:3px;display:flex;align-items:center;justify-content:center;">
            <i class="fa fa-tag" id="fp" style="color:var(--primary);font-size:16px;"></i>
          </div>
        </div>
      </div>
      <div style="display:flex;gap:7px;"><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
      <button type="button" class="btn btn-default" onclick="resetF()"><i class="fa fa-times"></i> Reset</button></div>
    </form>
  </div></div>
</div></div>
<script>
function editKat(k){
  document.getElementById('fa').value='edit'; document.getElementById('fid').value=k.id;
  document.getElementById('fn').value=k.nama; document.getElementById('fd').value=k.deskripsi||'';
  document.getElementById('fs').value=k.sla_jam; document.getElementById('fr').value=k.sla_respon_jam;
  document.getElementById('fi').value=k.icon||'fa-tag';
  document.getElementById('fp').className='fa '+(k.icon||'fa-tag');
  document.getElementById('ft').textContent='Edit: '+k.nama;
  document.getElementById('kf').scrollIntoView({behavior:'smooth'});
}
function resetF(){
  document.getElementById('fa').value='tambah'; document.getElementById('fid').value='';
  ['fn','fd'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('fs').value=24; document.getElementById('fr').value=4;
  document.getElementById('fi').value='fa-tag'; document.getElementById('fp').className='fa fa-tag';
  document.getElementById('ft').textContent='Tambah Kategori';
}
</script>
<?php include '../includes/footer.php'; ?>
