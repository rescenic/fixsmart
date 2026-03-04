<?php
session_start();
require_once '../config.php';
requireLogin();
$page_title  = 'Tiket IPSRS Saya';
$active_menu = 'tiket_saya_ipsrs';

$uid      = $_SESSION['user_id'];
$fs       = $_GET['status'] ?? '';
$search   = $_GET['q'] ?? '';
$page     = max(1,(int)($_GET['page']??1));
$per_page = 10;

$where = ['t.user_id = ?']; $params = [$uid];
if ($fs)     { $where[] = 't.status = ?'; $params[] = $fs; }
if ($search) { $where[] = '(t.nomor LIKE ? OR t.judul LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$wsql = implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) FROM tiket_ipsrs t WHERE $wsql");
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages  = max(1, ceil($total/$per_page));
$page   = min($page, $pages);
$offset = ($page-1)*$per_page;

$st = $pdo->prepare("
    SELECT t.*, k.nama AS kat_nama, tek.nama AS tek_nama
    FROM tiket_ipsrs t
    LEFT JOIN kategori_ipsrs k  ON k.id  = t.kategori_id
    LEFT JOIN users          tek ON tek.id = t.teknisi_id
    WHERE $wsql
    ORDER BY t.created_at DESC
    LIMIT $per_page OFFSET $offset");
$st->execute($params);
$tikets = $st->fetchAll();

// Stats tiket IPSRS milik user
$my_stats = [];
$st2 = $pdo->prepare("SELECT status, COUNT(*) n FROM tiket_ipsrs WHERE user_id=? GROUP BY status");
$st2->execute([$uid]);
foreach ($st2->fetchAll() as $r) $my_stats[$r['status']] = $r['n'];

include '../includes/header.php';
?>
<div class="page-header">
  <h4><i class="fa fa-toolbox" style="color:#f97316;"></i> &nbsp;Tiket IPSRS Saya</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Beranda</a><span class="sep">/</span>
    <span class="cur">Tiket IPSRS Saya</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Filter tabs status -->
  <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
    <?php
    $filter_tabs = [
        ''            => 'Semua',
        'menunggu'    => 'Menunggu',
        'diproses'    => 'Diproses',
        'selesai'     => 'Selesai',
        'ditolak'     => 'Ditolak',
        'tidak_bisa'  => 'Tidak Bisa',
    ];
    foreach ($filter_tabs as $v => $l):
        $cnt        = $v === '' ? array_sum($my_stats) : ($my_stats[$v] ?? 0);
        $active_tab = $fs === $v;
    ?>
    <a href="?status=<?= $v ?><?= $search ? '&q='.urlencode($search) : '' ?>"
       class="btn <?= $active_tab ? 'btn-primary' : 'btn-default' ?>"
       style="font-size:12px;<?= $active_tab ? 'background:linear-gradient(135deg,#f97316,#ea580c);border-color:#ea580c;' : '' ?>">
      <?= $l ?>
      <span style="background:<?= $active_tab ? 'rgba(255,255,255,.3)' : '#ddd' ?>;border-radius:9px;padding:0 6px;font-size:10px;font-weight:700;"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
    <a href="<?= APP_URL ?>/pages/buat_tiket_sarpras.php"
       class="btn btn-primary"
       style="margin-left:auto;background:linear-gradient(135deg,#f97316,#ea580c);border-color:#ea580c;">
      <i class="fa fa-plus"></i> Buat Tiket IPSRS
    </a>
  </div>

  <div class="panel">
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf">
          <?php if ($fs): ?><input type="hidden" name="status" value="<?= clean($fs) ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= clean($search) ?>"
                 class="inp-search" placeholder="Cari tiket IPSRS..."
                 onchange="document.getElementById('sf').submit()">
          <?php if ($search): ?>
          <a href="?status=<?= $fs ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Reset</a>
          <?php endif; ?>
        </form>
      </div>
      <span class="tbl-info"><?= $total ?> tiket ditemukan</span>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>No. Tiket</th>
            <th>Judul</th>
            <th>Kategori</th>
            <th>Prioritas</th>
            <th>Status</th>
            <th>Teknisi IPSRS</th>
            <th>Tgl. Masuk</th>
            <th>Durasi</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tikets)): ?>
          <tr>
            <td colspan="10" class="td-empty">
              <i class="fa fa-toolbox" style="color:#f97316;"></i>
              <?= $fs ? 'Tidak ada tiket dengan status tersebut.' : 'Belum ada tiket IPSRS.' ?>
              <br>
              <a href="<?= APP_URL ?>/pages/buat_tiket_sarpras.php" style="font-size:12px;color:#f97316;">
                + Buat tiket IPSRS baru
              </a>
            </td>
          </tr>
          <?php else: $no = $offset + 1; foreach ($tikets as $t):
            $is_final = in_array($t['status'], ['selesai','ditolak','tidak_bisa']);
            $dur      = $is_final ? $t['durasi_selesai_menit'] : durasiSekarang($t['waktu_submit']);
          ?>
          <tr>
            <td style="color:#bbb;"><?= $no++ ?></td>

            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                 style="color:#f97316;font-weight:700;">
                <?= clean($t['nomor']) ?>
              </a>
            </td>

            <td style="max-width:180px;">
              <a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                 style="color:#333;" title="<?= clean($t['judul']) ?>">
                <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= clean($t['judul']) ?>
                </span>
              </a>
            </td>

            <td><?= clean($t['kat_nama'] ?? '-') ?></td>
            <td><?= badgePrioritas($t['prioritas']) ?></td>
            <td><?= badgeStatus($t['status']) ?></td>

            <td>
              <?= $t['tek_nama']
                ? '<div class="d-flex ai-c gap6"><div class="av av-xs" style="background:#fff7ed;color:#c2410c;">'.getInitials($t['tek_nama']).'</div>'.clean($t['tek_nama']).'</div>'
                : '<span class="text-muted">—</span>' ?>
            </td>

            <td style="font-size:11px;color:#aaa;white-space:nowrap;">
              <?= formatTanggal($t['waktu_submit'], true) ?>
            </td>

            <td style="font-size:11px;<?= !$is_final && $dur > 480 ? 'color:var(--orange);font-weight:700;' : '' ?>">
              <?= formatDurasi($dur) ?>
            </td>

            <td>
              <div style="display:flex;gap:4px;">
                <!-- Lihat Detail -->
                <a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                   class="btn btn-info btn-sm" title="Lihat Detail">
                  <i class="fa fa-eye"></i>
                </a>
                <!-- Cetak Tiket -->
                <a href="<?= APP_URL ?>/pages/cetak_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                   target="_blank"
                   class="btn btn-default btn-sm"
                   title="Cetak / Print Tiket"
                   style="color:#f97316;border-color:#fed7aa;">
                  <i class="fa fa-print"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="tbl-footer">
      <span class="tbl-info">
        Menampilkan <?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?>
      </span>
      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pag-btn">
          <i class="fa fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"
           class="pag-btn <?= $i === $page ? 'active' : '' ?>">
          <?= $i ?>
        </a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pag-btn">
          <i class="fa fa-chevron-right"></i>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>