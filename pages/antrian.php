<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title = 'Antrian Tiket';
$active_menu = 'antrian';

$page = max(1,(int)($_GET['page']??1)); $per_page = 15;
$search = $_GET['q']??''; $fp = $_GET['prioritas']??''; $fk = $_GET['kat']??'';

$where = ["t.status='menunggu'"]; $params = [];
if ($search) { $where[] = '(t.nomor LIKE ? OR t.judul LIKE ? OR u.nama LIKE ?)'; $params = array_merge($params,["%$search%","%$search%","%$search%"]); }
if ($fp) { $where[] = 't.prioritas=?'; $params[] = $fp; }
if ($fk) { $where[] = 't.kategori_id=?'; $params[] = $fk; }
$wsql = implode(' AND ',$where);

$st = $pdo->prepare("SELECT COUNT(*) FROM tiket t LEFT JOIN users u ON u.id=t.user_id WHERE $wsql"); $st->execute($params); $total = (int)$st->fetchColumn();
$pages = max(1,ceil($total/$per_page)); $page=min($page,$pages); $offset=($page-1)*$per_page;

$st = $pdo->prepare("SELECT t.*,k.nama as kat_nama,k.sla_jam,u.nama as req_nama,u.divisi
    FROM tiket t LEFT JOIN kategori k ON k.id=t.kategori_id LEFT JOIN users u ON u.id=t.user_id
    WHERE $wsql
    ORDER BY CASE t.prioritas WHEN 'Tinggi' THEN 1 WHEN 'Sedang' THEN 2 ELSE 3 END, t.waktu_submit ASC
    LIMIT $per_page OFFSET $offset");
$st->execute($params); $tikets = $st->fetchAll();

$kat_list = $pdo->query("SELECT * FROM kategori ORDER BY nama")->fetchAll();
include '../includes/header.php';
?>
<div class="page-header">
  <h4><i class="fa fa-inbox text-primary"></i> &nbsp;Antrian Tiket Masuk</h4>
  <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">Antrian</span></div>
</div>
<div class="content">
  <?= showFlash() ?>
  <div class="panel">
    <div class="panel-hd">
      <h5>Menunggu Penanganan <span style="color:#aaa;font-weight:400;">(<?= $total ?>)</span></h5>
    </div>
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf" style="display:flex;gap:7px;flex-wrap:wrap;">
          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search" placeholder="Cari tiket..." onchange="document.getElementById('sf').submit()">
          <select name="prioritas" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Semua Prioritas</option>
            <option value="Tinggi" <?= $fp==='Tinggi'?'selected':'' ?>>🔴 Tinggi</option>
            <option value="Sedang" <?= $fp==='Sedang'?'selected':'' ?>>🟡 Sedang</option>
            <option value="Rendah" <?= $fp==='Rendah'?'selected':'' ?>>🟢 Rendah</option>
          </select>
          <select name="kat" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Semua Kategori</option>
            <?php foreach ($kat_list as $k): ?><option value="<?= $k['id'] ?>" <?= $fk==$k['id']?'selected':'' ?>><?= clean($k['nama']) ?></option><?php endforeach; ?>
          </select>
          <?php if ($search||$fp||$fk): ?><a href="?" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Reset</a><?php endif; ?>
        </form>
      </div>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>#</th><th>No. Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Pemohon</th><th>Lokasi</th><th>Masuk</th><th>Menunggu</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (empty($tikets)): ?>
          <tr><td colspan="10" class="td-empty"><i class="fa fa-check-circle" style="color:var(--green);"></i> Tidak ada antrian tiket!</td></tr>
          <?php else: $no=$offset+1; foreach ($tikets as $t):
            $durasi = durasiSekarang($t['waktu_submit']);
            $over = $t['sla_jam'] && $durasi > $t['sla_jam']*60;
          ?>
          <tr style="<?= $over?'background:#fff8f8;':'' ?>">
            <td style="color:#bbb;"><?= $no++ ?></td>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
            <td style="max-width:180px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($t['judul']) ?>"><?= clean($t['judul']) ?></span></td>
            <td><?= clean($t['kat_nama']??'-') ?></td>
            <td><?= badgePrioritas($t['prioritas']) ?></td>
            <td>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs"><?= getInitials($t['req_nama']) ?></div>
                <div><?= clean($t['req_nama']) ?><br><span class="text-muted text-sm"><?= clean($t['divisi']??'') ?></span></div>
              </div>
            </td>
            <td style="font-size:11px;color:#888;"><?= clean($t['lokasi']??'-') ?></td>
            <td style="font-size:11px;color:#aaa;white-space:nowrap;"><?= formatTanggal($t['waktu_submit'],true) ?></td>
            <td>
              <span style="font-size:12px;font-weight:700;color:<?= $over?'var(--red)':($durasi>60?'var(--orange)':'var(--green)') ?>;">
                <i class="fa fa-clock"></i> <?= formatDurasi($durasi) ?>
              </span>
              <?php if ($over): ?><br><span style="font-size:10px;color:var(--red);">⚠ SLA Terlewat!</span><?php endif; ?>
            </td>
            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fa fa-wrench"></i> Tangani</a>
            </td>
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
