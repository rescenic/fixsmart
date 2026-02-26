<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title = 'Semua Tiket';
$active_menu = 'semua_tiket';

$page=max(1,(int)($_GET['page']??1)); $per_page=15;
$fs=$_GET['status']??''; $fp=$_GET['prioritas']??''; $fk=$_GET['kat']??''; $search=$_GET['q']??'';

$where=['1=1']; $params=[];
if ($fs) { $where[]='t.status=?'; $params[]=$fs; }
if ($fp) { $where[]='t.prioritas=?'; $params[]=$fp; }
if ($fk) { $where[]='t.kategori_id=?'; $params[]=$fk; }
if ($search) { $where[]='(t.nomor LIKE ? OR t.judul LIKE ? OR u.nama LIKE ?)'; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }
$wsql=implode(' AND ',$where);

$st=$pdo->prepare("SELECT COUNT(*) FROM tiket t LEFT JOIN users u ON u.id=t.user_id WHERE $wsql"); $st->execute($params); $total=(int)$st->fetchColumn();
$pages=max(1,ceil($total/$per_page)); $page=min($page,$pages); $offset=($page-1)*$per_page;

$st=$pdo->prepare("SELECT t.*,k.nama as kat_nama,u.nama as req_nama,u.divisi,tek.nama as tek_nama
    FROM tiket t LEFT JOIN kategori k ON k.id=t.kategori_id
    LEFT JOIN users u ON u.id=t.user_id LEFT JOIN users tek ON tek.id=t.teknisi_id
    WHERE $wsql ORDER BY t.created_at DESC LIMIT $per_page OFFSET $offset");
$st->execute($params); $tikets=$st->fetchAll();

$kat_list=$pdo->query("SELECT * FROM kategori ORDER BY nama")->fetchAll();

// Stats
$stats=[];
$pdo->query("SELECT status,COUNT(*) n FROM tiket GROUP BY status")->fetchAll();
$st2=$pdo->query("SELECT status,COUNT(*) n FROM tiket GROUP BY status");
foreach ($st2->fetchAll() as $r) $stats[$r['status']]=$r['n'];

include '../includes/header.php';
?>
<div class="page-header">
  <h4><i class="fa fa-ticket-alt text-primary"></i> &nbsp;Semua Tiket</h4>
  <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">Semua Tiket</span></div>
</div>
<div class="content">
  <?= showFlash() ?>

  <!-- Status quick filter -->
  <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
    <?php foreach ([''=>'Semua','menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai','ditolak'=>'Ditolak','tidak_bisa'=>'Tidak Bisa'] as $v=>$l):
      $cnt=$v===''?array_sum($stats):($stats[$v]??0); ?>
    <a href="?status=<?= $v ?>" class="btn <?= $fs===$v?'btn-primary':'btn-default' ?>" style="font-size:12px;">
      <?= $l ?> <span style="background:<?= $fs===$v?'rgba(255,255,255,.3)':'#ddd' ?>;border-radius:9px;padding:0 6px;font-size:10px;"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf" style="display:flex;gap:7px;flex-wrap:wrap;">
          <?php if ($fs): ?><input type="hidden" name="status" value="<?= clean($fs) ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search" placeholder="Cari..." onchange="document.getElementById('sf').submit()">
          <select name="prioritas" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Prioritas</option>
            <option value="Tinggi" <?= $fp==='Tinggi'?'selected':'' ?>>Tinggi</option>
            <option value="Sedang" <?= $fp==='Sedang'?'selected':'' ?>>Sedang</option>
            <option value="Rendah" <?= $fp==='Rendah'?'selected':'' ?>>Rendah</option>
          </select>
          <select name="kat" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Kategori</option>
            <?php foreach ($kat_list as $k): ?><option value="<?= $k['id'] ?>" <?= $fk==$k['id']?'selected':'' ?>><?= clean($k['nama']) ?></option><?php endforeach; ?>
          </select>
          <?php if ($search||$fp||$fk): ?><a href="?status=<?= $fs ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i></a><?php endif; ?>
        </form>
      </div>
      <span class="tbl-info"><?= $total ?> tiket</span>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>#</th><th>No. Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Pemohon</th><th>Teknisi</th><th>Status</th><th>Masuk</th><th>Durasi</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (empty($tikets)): ?><tr><td colspan="11" class="td-empty"><i class="fa fa-inbox"></i> Tidak ada data</td></tr>
          <?php else: $no=$offset+1; foreach ($tikets as $t):
            $is_final=in_array($t['status'],['selesai','ditolak','tidak_bisa']);
            $dur=$is_final?$t['durasi_selesai_menit']:durasiSekarang($t['waktu_submit']);
          ?>
          <tr>
            <td style="color:#bbb;"><?= $no++ ?></td>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
            <td style="max-width:160px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($t['judul']) ?>"><?= clean($t['judul']) ?></span>
              <small style="color:#aaa;"><?= clean($t['divisi']??'') ?></small></td>
            <td style="font-size:11px;"><?= clean($t['kat_nama']??'-') ?></td>
            <td><?= badgePrioritas($t['prioritas']) ?></td>
            <td><div class="d-flex ai-c gap6"><div class="av av-xs"><?= getInitials($t['req_nama']) ?></div><?= clean($t['req_nama']) ?></div></td>
            <td><?= $t['tek_nama']?'<div class="d-flex ai-c gap6"><div class="av av-xs av-blue">'.getInitials($t['tek_nama']).'</div>'.clean($t['tek_nama']).'</div>':'<span class="text-muted">—</span>' ?></td>
            <td><?= badgeStatus($t['status']) ?></td>
            <td style="font-size:11px;color:#aaa;white-space:nowrap;"><?= formatTanggal($t['waktu_submit']) ?></td>
            <td style="font-size:12px;font-weight:700;"><?= formatDurasi($dur) ?></td>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="tbl-footer">
      <span class="tbl-info">Menampilkan <?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?></span>
      <?php if ($pages>1): ?><div class="pagination">
        <?php if ($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pag-btn"><i class="fa fa-chevron-left"></i></a><?php endif; ?>
        <?php for($i=1;$i<=$pages;$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if ($page<$pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pag-btn"><i class="fa fa-chevron-right"></i></a><?php endif; ?>
      </div><?php endif; ?>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
