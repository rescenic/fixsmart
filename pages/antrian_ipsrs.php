<?php
// pages/antrian_ipsrs.php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title  = 'Antrian Tiket IPSRS';
$active_menu = 'antrian_ipsrs';

$page     = max(1, (int)($_GET['page']      ?? 1));
$per_page = 15;
$search   = $_GET['q']        ?? '';
$fp       = $_GET['prioritas'] ?? '';
$fk       = $_GET['kat']       ?? '';
$fj       = $_GET['jenis']     ?? ''; // Medis / Non-Medis

$where  = ["t.status='menunggu'"]; $params = [];
if ($search) {
    $where[]  = '(t.nomor LIKE ? OR t.judul LIKE ? OR u.nama LIKE ?)';
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($fp) { $where[] = 't.prioritas=?';    $params[] = $fp; }
if ($fk) { $where[] = 't.kategori_id=?'; $params[] = $fk; }
if ($fj) { $where[] = 't.jenis_tiket=?'; $params[] = $fj; }
$wsql = implode(' AND ', $where);

// Total
$st = $pdo->prepare("SELECT COUNT(*) FROM tiket_ipsrs t LEFT JOIN users u ON u.id=t.user_id WHERE $wsql");
$st->execute($params);
$total  = (int)$st->fetchColumn();
$pages  = max(1, ceil($total / $per_page));
$page   = min($page, $pages);
$offset = ($page - 1) * $per_page;

// Data
$st = $pdo->prepare("
    SELECT t.*,
           k.nama  AS kat_nama, k.sla_jam, k.jenis AS kat_jenis,
           u.nama  AS req_nama, u.divisi,
           a.nama_aset, a.no_inventaris AS aset_inv
    FROM tiket_ipsrs t
    LEFT JOIN kategori_ipsrs k ON k.id = t.kategori_id
    LEFT JOIN users           u ON u.id = t.user_id
    LEFT JOIN aset_ipsrs      a ON a.id = t.aset_id
    WHERE $wsql
    ORDER BY
        CASE t.prioritas WHEN 'Tinggi' THEN 1 WHEN 'Sedang' THEN 2 ELSE 3 END,
        t.waktu_submit ASC
    LIMIT $per_page OFFSET $offset
");
$st->execute($params);
$tikets = $st->fetchAll();

// Kategori IPSRS untuk filter
try {
    $kat_list = $pdo->query("SELECT id, nama, jenis FROM kategori_ipsrs ORDER BY jenis, nama")->fetchAll();
} catch (Exception $e) { $kat_list = []; }

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-inbox" style="color:var(--primary);"></i> &nbsp;Antrian Tiket IPSRS</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Antrian IPSRS</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- ── Stats bar ── -->
  <?php
  try {
      $stats_j = $pdo->query("
          SELECT jenis_tiket, COUNT(*) AS cnt
          FROM tiket_ipsrs WHERE status='menunggu'
          GROUP BY jenis_tiket
      ")->fetchAll(PDO::FETCH_KEY_PAIR);
  } catch (Exception $e) { $stats_j = []; }
  $cnt_medis = $stats_j['Medis']     ?? 0;
  $cnt_non   = $stats_j['Non-Medis'] ?? 0;
  ?>
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;flex:1;min-width:140px;">
      <div style="width:36px;height:36px;border-radius:8px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-inbox" style="color:#d97706;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $total ?></div>
        <div style="font-size:11px;color:#94a3b8;">Total Antrian</div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:#fdf2f8;border:1px solid #f9a8d4;border-radius:8px;flex:1;min-width:140px;cursor:pointer;"
         onclick="document.querySelector('[name=jenis]').value='Medis';document.getElementById('sf').submit()">
      <div style="width:36px;height:36px;border-radius:8px;background:#fce7f3;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-kit-medical" style="color:#db2777;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#9d174d;"><?= $cnt_medis ?></div>
        <div style="font-size:11px;color:#db2777;">Medis</div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;flex:1;min-width:140px;cursor:pointer;"
         onclick="document.querySelector('[name=jenis]').value='Non-Medis';document.getElementById('sf').submit()">
      <div style="width:36px;height:36px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-screwdriver-wrench" style="color:#1d4ed8;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#1e40af;"><?= $cnt_non ?></div>
        <div style="font-size:11px;color:#1d4ed8;">Non-Medis</div>
      </div>
    </div>
    <?php
    // Prioritas Tinggi menunggu
    try {
        $cnt_tinggi = (int)$pdo->query("SELECT COUNT(*) FROM tiket_ipsrs WHERE status='menunggu' AND prioritas='Tinggi'")->fetchColumn();
    } catch (Exception $e) { $cnt_tinggi = 0; }
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:<?= $cnt_tinggi ? '#fef2f2' : '#fff' ?>;border:1px solid <?= $cnt_tinggi ? '#fca5a5' : '#e2e8f0' ?>;border-radius:8px;flex:1;min-width:140px;cursor:pointer;"
         onclick="document.querySelector('[name=prioritas]').value='Tinggi';document.getElementById('sf').submit()">
      <div style="width:36px;height:36px;border-radius:8px;background:<?= $cnt_tinggi ? '#fee2e2' : '#f1f5f9' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-circle-exclamation" style="color:<?= $cnt_tinggi ? '#dc2626' : '#94a3b8' ?>;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:<?= $cnt_tinggi ? '#dc2626' : '#1e293b' ?>;"><?= $cnt_tinggi ?></div>
        <div style="font-size:11px;color:<?= $cnt_tinggi ? '#dc2626' : '#94a3b8' ?>;">Prioritas Tinggi</div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-hd">
      <h5>Menunggu Penanganan <span style="color:#aaa;font-weight:400;">(<?= $total ?>)</span></h5>
    </div>

    <!-- Filter -->
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf" style="display:flex;gap:7px;flex-wrap:wrap;">
          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search"
                 placeholder="Cari no. tiket, judul, pemohon…"
                 onchange="document.getElementById('sf').submit()">

          <!-- Filter Jenis -->
          <select name="jenis" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Semua Jenis</option>
            <option value="Medis"     <?= $fj==='Medis'?'selected':'' ?>>🏥 Medis</option>
            <option value="Non-Medis" <?= $fj==='Non-Medis'?'selected':'' ?>>🔧 Non-Medis</option>
          </select>

          <!-- Filter Prioritas -->
          <select name="prioritas" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Semua Prioritas</option>
            <option value="Tinggi" <?= $fp==='Tinggi'?'selected':'' ?>>🔴 Tinggi</option>
            <option value="Sedang" <?= $fp==='Sedang'?'selected':'' ?>>🟡 Sedang</option>
            <option value="Rendah" <?= $fp==='Rendah'?'selected':'' ?>>🟢 Rendah</option>
          </select>

          <!-- Filter Kategori -->
          <select name="kat" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Semua Kategori</option>
            <?php
            $cur_j = '';
            foreach ($kat_list as $k):
                if ($k['jenis'] !== $cur_j) {
                    if ($cur_j !== '') echo '</optgroup>';
                    echo '<optgroup label="── ' . clean($k['jenis']) . ' ──">';
                    $cur_j = $k['jenis'];
                }
            ?>
            <option value="<?= $k['id'] ?>" <?= $fk == $k['id'] ? 'selected' : '' ?>><?= clean($k['nama']) ?></option>
            <?php endforeach; if ($cur_j) echo '</optgroup>'; ?>
          </select>

          <?php if ($search || $fp || $fk || $fj): ?>
          <a href="?" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Reset</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Tabel -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>No. Tiket</th>
            <th>Judul</th>
            <th>Jenis / Kategori</th>
            <th>Prioritas</th>
            <th>Pemohon</th>
            <th>Aset Terkait</th>
            <th>Lokasi</th>
            <th>Masuk</th>
            <th>Menunggu</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tikets)): ?>
          <tr>
            <td colspan="11" class="td-empty">
              <i class="fa fa-check-circle" style="color:var(--green);"></i>
              Tidak ada antrian tiket IPSRS!
            </td>
          </tr>
          <?php else:
            $no = $offset + 1;
            foreach ($tikets as $t):
              $durasi = durasiSekarang($t['waktu_submit']);
              $over   = $t['sla_jam'] && $durasi > $t['sla_jam'] * 60;
              $is_medis = ($t['jenis_tiket'] === 'Medis');
          ?>
          <tr style="<?= $over ? 'background:#fff8f8;' : '' ?>">

            <td style="color:#bbb;"><?= $no++ ?></td>

            <!-- No. Tiket -->
            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                 style="color:var(--primary);font-weight:700;font-family:monospace;font-size:11.5px;">
                <?= clean($t['nomor']) ?>
              </a>
            </td>

            <!-- Judul -->
            <td style="max-width:170px;">
              <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= clean($t['judul']) ?>">
                <?= clean($t['judul']) ?>
              </span>
            </td>

            <!-- Jenis / Kategori -->
            <td>
              <?php if ($is_medis): ?>
              <span style="display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#fce7f3;color:#9d174d;margin-bottom:3px;">
                <i class="fa fa-kit-medical" style="font-size:9px;"></i> Medis
              </span>
              <?php else: ?>
              <span style="display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#dbeafe;color:#1e40af;margin-bottom:3px;">
                <i class="fa fa-screwdriver-wrench" style="font-size:9px;"></i> Non-Medis
              </span>
              <?php endif; ?>
              <br>
              <span style="font-size:11px;color:#64748b;"><?= clean($t['kat_nama'] ?? '-') ?></span>
            </td>

            <!-- Prioritas -->
            <td><?= badgePrioritas($t['prioritas']) ?></td>

            <!-- Pemohon -->
            <td>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs"><?= getInitials($t['req_nama']) ?></div>
                <div>
                  <?= clean($t['req_nama']) ?>
                  <br><span class="text-muted text-sm"><?= clean($t['divisi'] ?? '') ?></span>
                </div>
              </div>
            </td>

            <!-- Aset Terkait -->
            <td style="font-size:11px;">
              <?php if ($t['nama_aset']): ?>
              <span style="font-weight:600;color:#374151;"><?= clean($t['nama_aset']) ?></span>
              <br><span style="font-family:monospace;font-size:10px;color:#6d28d9;background:#f5f3ff;padding:1px 5px;border-radius:3px;"><?= clean($t['aset_inv']) ?></span>
              <?php else: ?>
              <span style="color:#d1d5db;">—</span>
              <?php endif; ?>
            </td>

            <!-- Lokasi -->
            <td style="font-size:11px;color:#888;max-width:120px;">
              <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= clean($t['lokasi'] ?? '-') ?>
              </span>
            </td>

            <!-- Waktu masuk -->
            <td style="font-size:11px;color:#aaa;white-space:nowrap;">
              <?= formatTanggal($t['waktu_submit'], true) ?>
            </td>

            <!-- Durasi menunggu -->
            <td>
              <span style="font-size:12px;font-weight:700;color:<?= $over ? 'var(--red)' : ($durasi > 60 ? 'var(--orange)' : 'var(--green)') ?>;">
                <i class="fa fa-clock"></i> <?= formatDurasi($durasi) ?>
              </span>
              <?php if ($over): ?>
              <br><span style="font-size:10px;color:var(--red);">⚠ SLA Terlewat!</span>
              <?php endif; ?>
            </td>

            <!-- Aksi -->
            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                 class="btn btn-primary btn-sm">
                <i class="fa fa-wrench"></i> Tangani
              </a>
            </td>

          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="tbl-footer">
      <span class="tbl-info">
        Menampilkan <?= min($offset + 1, $total) ?>–<?= min($offset + $per_page, $total) ?> dari <?= $total ?>
      </span>
      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pag-btn">
          <i class="fa fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
           class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pag-btn">
          <i class="fa fa-chevron-right"></i>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /panel -->
</div><!-- /content -->

<?php include '../includes/footer.php'; ?>