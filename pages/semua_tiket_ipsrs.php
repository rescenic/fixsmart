<?php
// pages/semua_tiket_ipsrs.php
session_start();
require_once '../config.php';
requireLogin();

// ── Akses: hanya admin dan teknisi_ipsrs ─────────────────────────────────────
if (!hasRole(['admin', 'teknisi_ipsrs'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Semua Tiket IPSRS';
$active_menu = 'semua_tiket_ipsrs';

$page     = max(1, (int)($_GET['page']     ?? 1));
$per_page = 15;
$fs       = $_GET['status']    ?? '';
$fp       = $_GET['prioritas'] ?? '';
$fk       = (int)($_GET['kat'] ?? 0);
$fj       = $_GET['jenis']     ?? '';
$search   = trim($_GET['q']    ?? '');

$valid_status   = ['', 'menunggu', 'diproses', 'selesai', 'ditolak', 'tidak_bisa'];
$valid_prioritas= ['', 'Tinggi', 'Sedang', 'Rendah'];
$valid_jenis    = ['', 'Medis', 'Non-Medis'];
if (!in_array($fs, $valid_status))    $fs = '';
if (!in_array($fp, $valid_prioritas)) $fp = '';
if (!in_array($fj, $valid_jenis))     $fj = '';

$where  = ['1=1'];
$params = [];

if ($fs)  { $where[] = 't.status = ?';      $params[] = $fs; }
if ($fp)  { $where[] = 't.prioritas = ?';   $params[] = $fp; }
if ($fk)  { $where[] = 't.kategori_id = ?'; $params[] = $fk; }
if ($fj)  { $where[] = 't.jenis_tiket = ?'; $params[] = $fj; }
if ($search) {
    $where[]  = '(t.nomor LIKE ? OR t.judul LIKE ? OR u.nama LIKE ?)';
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$wsql = implode(' AND ', $where);

$st = $pdo->prepare("
    SELECT COUNT(*)
    FROM tiket_ipsrs t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE $wsql
");
$st->execute($params);
$total  = (int)$st->fetchColumn();
$pages  = max(1, ceil($total / $per_page));
$page   = min($page, $pages);
$offset = ($page - 1) * $per_page;

$st = $pdo->prepare("
    SELECT t.*, k.nama AS kat_nama, k.jenis AS kat_jenis,
           u.nama AS req_nama, u.divisi,
           tek.nama AS tek_nama,
           a.nama_aset, a.no_inventaris AS aset_inv
    FROM tiket_ipsrs t
    LEFT JOIN kategori_ipsrs k ON k.id    = t.kategori_id
    LEFT JOIN users           u ON u.id   = t.user_id
    LEFT JOIN users         tek ON tek.id = t.teknisi_id
    LEFT JOIN aset_ipsrs      a ON a.id   = t.aset_id
    WHERE $wsql
    ORDER BY t.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$st->execute($params);
$tikets = $st->fetchAll();

try {
    $kat_list = $pdo->query("SELECT id, nama, jenis FROM kategori_ipsrs ORDER BY jenis, nama")->fetchAll();
} catch (Exception $e) { $kat_list = []; }

$stats = [];
try {
    $st2 = $pdo->query("SELECT status, COUNT(*) n FROM tiket_ipsrs GROUP BY status");
    foreach ($st2->fetchAll() as $r) $stats[$r['status']] = $r['n'];
} catch (Exception $e) {}

$stats_j = [];
try {
    $st3 = $pdo->query("SELECT jenis_tiket, COUNT(*) n FROM tiket_ipsrs GROUP BY jenis_tiket");
    foreach ($st3->fetchAll() as $r) $stats_j[$r['jenis_tiket']] = $r['n'];
} catch (Exception $e) {}

include '../includes/header.php';
?>

<style>
/* ── Modal Cetak ── */
.mc-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;align-items:center;justify-content:center;}
.mc-overlay.open{display:flex;}
.mc-box{background:#fff;width:100%;max-width:520px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;}
@keyframes mIn{from{opacity:0;transform:scale(.95) translateY(-10px);}to{opacity:1;transform:scale(1) translateY(0);}}
.mc-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:linear-gradient(135deg,#1a2e3f,#2a3f54);}
.mc-head-icon{width:30px;height:30px;background:rgba(38,185,154,.25);border:1px solid rgba(38,185,154,.5);border-radius:6px;display:flex;align-items:center;justify-content:center;}
.mc-head-title{color:#fff;font-size:13.5px;font-weight:700;margin-left:10px;}
.mc-head-sub{color:rgba(255,255,255,.4);font-size:10px;margin-left:10px;}
.mc-close{width:25px;height:25px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:12px;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.mc-close:hover{background:#ef4444;color:#fff;}
.mc-body{padding:18px 20px;}
.mc-section{margin-bottom:16px;}
.mc-section-label{font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.mc-section-label i{color:#26B99A;}
.mc-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.mc-label{font-size:11px;font-weight:600;color:#475569;display:block;margin-bottom:4px;}
.mc-inp{width:100%;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box;font-family:inherit;transition:border .15s;}
.mc-inp:focus{outline:none;border-color:#26B99A;box-shadow:0 0 0 3px rgba(38,185,154,.1);}
.period-chips{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;}
.period-chip{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;cursor:pointer;transition:all .14s;white-space:nowrap;}
.period-chip:hover,.period-chip.active{background:#26B99A;color:#fff;border-color:#26B99A;}
.report-opts{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.report-opt{border:2px solid #e2e8f0;border-radius:8px;padding:10px 12px;cursor:pointer;transition:all .15s;background:#fff;}
.report-opt:hover{border-color:#26B99A;background:#f0fdf9;}
.report-opt.selected{border-color:#26B99A;background:#f0fdf9;}
.report-opt-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:6px;}
.report-opt-title{font-size:12px;font-weight:700;color:#1e293b;}
.report-opt-sub{font-size:10px;color:#94a3b8;margin-top:2px;}
.mc-foot{padding:12px 18px;border-top:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;}
.mc-preview-bar{font-size:10.5px;color:#64748b;background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;padding:5px 10px;margin:0 18px 12px;display:none;}
.mc-preview-bar.show{display:block;}
.bj-m{display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#fce7f3;color:#9d174d;}
.bj-n{display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#dbeafe;color:#1e40af;}
</style>

<div class="page-header">
  <h4><i class="fa fa-ticket-alt text-primary"></i> &nbsp;Semua Tiket IPSRS</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Semua Tiket IPSRS</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- ── Info bar Medis / Non-Medis ── -->
  <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
    <?php
    $base_params = array_filter(['status' => $fs, 'prioritas' => $fp, 'kat' => $fk ?: '', 'q' => $search]);
    ?>
    <a href="?<?= http_build_query(array_merge($base_params, ['jenis' => 'Medis'])) ?>"
       style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:<?= $fj==='Medis'?'#fce7f3':'#fdf2f8' ?>;border:1px solid <?= $fj==='Medis'?'#f472b6':'#f9a8d4' ?>;border-radius:8px;text-decoration:none;">
      <i class="fa fa-kit-medical" style="color:#db2777;"></i>
      <span style="font-size:12px;font-weight:700;color:#9d174d;"><?= $stats_j['Medis'] ?? 0 ?> Medis</span>
    </a>
    <a href="?<?= http_build_query(array_merge($base_params, ['jenis' => 'Non-Medis'])) ?>"
       style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:<?= $fj==='Non-Medis'?'#dbeafe':'#eff6ff' ?>;border:1px solid <?= $fj==='Non-Medis'?'#60a5fa':'#93c5fd' ?>;border-radius:8px;text-decoration:none;">
      <i class="fa fa-screwdriver-wrench" style="color:#1d4ed8;"></i>
      <span style="font-size:12px;font-weight:700;color:#1e40af;"><?= $stats_j['Non-Medis'] ?? 0 ?> Non-Medis</span>
    </a>
    <?php if ($fj): ?>
    <a href="?<?= http_build_query($base_params) ?>"
       style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;text-decoration:none;">
      <span style="font-size:11px;color:#64748b;">Filter aktif: <strong><?= clean($fj) ?></strong></span>
      <i class="fa fa-times" style="color:#ef4444;font-size:11px;"></i>
    </a>
    <?php endif; ?>
  </div>

  <!-- ── Status quick filter ── -->
  <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
    <?php foreach ([''=>'Semua','menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai','ditolak'=>'Ditolak','tidak_bisa'=>'Tidak Bisa'] as $v => $l):
      $cnt = ($v === '') ? array_sum($stats) : ($stats[$v] ?? 0);
      $tab_params = array_filter(['jenis' => $fj, 'prioritas' => $fp, 'kat' => $fk ?: '', 'q' => $search]);
      if ($v !== '') $tab_params['status'] = $v;
    ?>
    <a href="?<?= http_build_query($tab_params) ?>"
       class="btn <?= $fs === $v ? 'btn-primary' : 'btn-default' ?>" style="font-size:12px;">
      <?= $l ?>
      <span style="background:<?= $fs===$v?'rgba(255,255,255,.3)':'#ddd' ?>;border-radius:9px;padding:0 6px;font-size:10px;"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf" style="display:flex;gap:7px;flex-wrap:wrap;">
          <?php if ($fs): ?><input type="hidden" name="status" value="<?= clean($fs) ?>"><?php endif; ?>
          <?php if ($fj): ?><input type="hidden" name="jenis"  value="<?= clean($fj) ?>"><?php endif; ?>

          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search"
                 placeholder="Cari tiket, judul, pemohon…"
                 onchange="this.form.submit()">

          <?php if (!$fj): ?>
          <select name="jenis" class="sel-filter" onchange="this.form.submit()">
            <option value="">Semua Jenis</option>
            <option value="Medis">🏥 Medis</option>
            <option value="Non-Medis">🔧 Non-Medis</option>
          </select>
          <?php endif; ?>

          <select name="prioritas" class="sel-filter" onchange="this.form.submit()">
            <option value="">Prioritas</option>
            <option value="Tinggi"  <?= $fp==='Tinggi'?'selected':'' ?>>Tinggi</option>
            <option value="Sedang"  <?= $fp==='Sedang'?'selected':'' ?>>Sedang</option>
            <option value="Rendah"  <?= $fp==='Rendah'?'selected':'' ?>>Rendah</option>
          </select>

          <select name="kat" class="sel-filter" onchange="this.form.submit()">
            <option value="">Kategori</option>
            <?php $cur_j = ''; foreach ($kat_list as $k):
              if ($k['jenis'] !== $cur_j) {
                if ($cur_j) echo '</optgroup>';
                echo '<optgroup label="── ' . htmlspecialchars($k['jenis']) . ' ──">';
                $cur_j = $k['jenis'];
              }
            ?>
            <option value="<?= $k['id'] ?>" <?= $fk == $k['id'] ? 'selected' : '' ?>><?= clean($k['nama']) ?></option>
            <?php endforeach; if ($cur_j) echo '</optgroup>'; ?>
          </select>

          <?php if ($search || $fp || $fk): ?>
          <a href="?<?= http_build_query(array_filter(['status'=>$fs,'jenis'=>$fj])) ?>"
             class="btn btn-default btn-sm" title="Reset filter">
            <i class="fa fa-times"></i>
          </a>
          <?php endif; ?>
        </form>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="tbl-info"><?= $total ?> tiket</span>
        <button type="button" onclick="bukaModalCetak()"
                class="btn btn-default btn-sm"
                style="border-color:#26B99A;color:#0f766e;font-weight:600;">
          <i class="fa fa-print"></i> Cetak / Export
        </button>
      </div>
    </div>

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
            <th>Teknisi</th>
            <th>Aset</th>
            <th>Status</th>
            <th>Masuk</th>
            <th>Durasi</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tikets)): ?>
          <tr><td colspan="12" class="td-empty"><i class="fa fa-inbox"></i> Tidak ada data</td></tr>
          <?php else:
            $no = $offset + 1;
            foreach ($tikets as $t):
              $is_final = in_array($t['status'], ['selesai', 'ditolak', 'tidak_bisa']);
              $dur      = $is_final
                          ? (int)($t['durasi_selesai_menit'] ?? 0)
                          : durasiSekarang($t['waktu_submit']);
              $is_medis = ($t['jenis_tiket'] === 'Medis');
          ?>
          <tr>
            <td style="color:#cbd5e1;"><?= $no++ ?></td>

            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                 style="color:var(--primary);font-weight:700;font-family:monospace;font-size:11.5px;">
                <?= clean($t['nomor']) ?>
              </a>
            </td>

            <td style="max-width:160px;">
              <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= clean($t['judul']) ?>"><?= clean($t['judul']) ?></span>
              <small style="color:#94a3b8;"><?= clean($t['divisi'] ?? '') ?></small>
            </td>

            <td>
              <?php if ($is_medis): ?>
              <span class="bj-m"><i class="fa fa-kit-medical" style="font-size:9px;"></i> Medis</span>
              <?php else: ?>
              <span class="bj-n"><i class="fa fa-screwdriver-wrench" style="font-size:9px;"></i> Non-Medis</span>
              <?php endif; ?>
              <br><span style="font-size:11px;color:#64748b;"><?= clean($t['kat_nama'] ?? '—') ?></span>
            </td>

            <td><?= badgePrioritas($t['prioritas']) ?></td>

            <td>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs"><?= getInitials($t['req_nama']) ?></div>
                <span><?= clean($t['req_nama']) ?></span>
              </div>
            </td>

            <td>
              <?php if ($t['tek_nama']): ?>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs av-blue"><?= getInitials($t['tek_nama']) ?></div>
                <span><?= clean($t['tek_nama']) ?></span>
              </div>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>

            <td style="font-size:11px;">
              <?php if ($t['nama_aset']): ?>
              <span style="font-weight:600;color:#374151;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:110px;"
                    title="<?= clean($t['nama_aset']) ?>"><?= clean($t['nama_aset']) ?></span>
              <span style="font-family:monospace;font-size:10px;color:#6d28d9;background:#f5f3ff;padding:1px 5px;border-radius:3px;">
                <?= clean($t['aset_inv']) ?>
              </span>
              <?php else: ?>
              <span style="color:#d1d5db;">—</span>
              <?php endif; ?>
            </td>

            <td><?= badgeStatus($t['status']) ?></td>

            <td style="font-size:11px;color:#94a3b8;white-space:nowrap;"><?= formatTanggal($t['waktu_submit']) ?></td>

            <td style="font-size:12px;font-weight:700;"><?= formatDurasi($dur) ?></td>

            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                 class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="tbl-footer">
      <span class="tbl-info">
        Menampilkan <?= $total ? min($offset + 1, $total) : 0 ?>–<?= min($offset + $per_page, $total) ?> dari <?= $total ?>
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
  </div>
</div>


<!-- ════════════════════════════════════════════════════
     MODAL CETAK / EXPORT LAPORAN IPSRS
════════════════════════════════════════════════════════ -->
<div class="mc-overlay" id="mc-overlay" onclick="if(event.target===this)tutupModalCetak()">
  <div class="mc-box">

    <div class="mc-head">
      <div style="display:flex;align-items:center;">
        <div class="mc-head-icon"><i class="fa fa-print" style="color:#26B99A;font-size:13px;"></i></div>
        <div>
          <div class="mc-head-title">Cetak / Export Laporan IPSRS</div>
          <div class="mc-head-sub">Atur filter dan periode laporan</div>
        </div>
      </div>
      <button class="mc-close" onclick="tutupModalCetak()"><i class="fa fa-times"></i></button>
    </div>

    <div class="mc-body">
      <div class="mc-section">
        <div class="mc-section-label"><i class="fa fa-calendar-days"></i> Pilihan Cepat Periode</div>
        <div class="period-chips">
          <span class="period-chip" onclick="setPeriod('bulan_ini',this)">Bulan Ini</span>
          <span class="period-chip" onclick="setPeriod('bulan_lalu',this)">Bulan Lalu</span>
          <span class="period-chip" onclick="setPeriod('3_bulan',this)">3 Bulan Terakhir</span>
          <span class="period-chip" onclick="setPeriod('6_bulan',this)">6 Bulan Terakhir</span>
          <span class="period-chip" onclick="setPeriod('tahun_ini',this)">Tahun Ini</span>
          <span class="period-chip" onclick="setPeriod('semua',this)">Semua Waktu</span>
        </div>
      </div>

      <div class="mc-section">
        <div class="mc-section-label"><i class="fa fa-calendar-range"></i> Rentang Tanggal</div>
        <div class="mc-grid2">
          <div>
            <label class="mc-label">Tanggal Mulai</label>
            <input type="date" id="mc-tgl-dari" class="mc-inp" value="<?= date('Y-m-01') ?>" onchange="updatePreview()">
          </div>
          <div>
            <label class="mc-label">Tanggal Sampai</label>
            <input type="date" id="mc-tgl-sampai" class="mc-inp" value="<?= date('Y-m-t') ?>" onchange="updatePreview()">
          </div>
        </div>
      </div>

      <div class="mc-section">
        <div class="mc-section-label"><i class="fa fa-filter"></i> Filter Tambahan</div>
        <div class="mc-grid2" style="margin-bottom:8px;">
          <div>
            <label class="mc-label">Jenis Tiket</label>
            <select id="mc-jenis" class="mc-inp" onchange="updatePreview()">
              <option value="">Semua Jenis</option>
              <option value="Medis"     <?= $fj==='Medis'?'selected':'' ?>>🏥 Medis</option>
              <option value="Non-Medis" <?= $fj==='Non-Medis'?'selected':'' ?>>🔧 Non-Medis</option>
            </select>
          </div>
          <div>
            <label class="mc-label">Status</label>
            <select id="mc-status" class="mc-inp" onchange="updatePreview()">
              <option value="">Semua Status</option>
              <option value="menunggu">Menunggu</option>
              <option value="diproses">Diproses</option>
              <option value="selesai">Selesai</option>
              <option value="ditolak">Ditolak</option>
              <option value="tidak_bisa">Tidak Bisa</option>
            </select>
          </div>
        </div>
        <div class="mc-grid2">
          <div>
            <label class="mc-label">Kategori</label>
            <select id="mc-kat" class="mc-inp" onchange="updatePreview()">
              <option value="">Semua Kategori</option>
              <?php $cur_j = ''; foreach ($kat_list as $k):
                if ($k['jenis'] !== $cur_j) {
                  if ($cur_j) echo '</optgroup>';
                  echo '<optgroup label="── ' . htmlspecialchars($k['jenis']) . ' ──">';
                  $cur_j = $k['jenis'];
                }
              ?>
              <option value="<?= $k['id'] ?>"><?= clean($k['nama']) ?></option>
              <?php endforeach; if ($cur_j) echo '</optgroup>'; ?>
            </select>
          </div>
          <div>
            <label class="mc-label">Prioritas</label>
            <select id="mc-prioritas" class="mc-inp" onchange="updatePreview()">
              <option value="">Semua Prioritas</option>
              <option value="Tinggi">Tinggi</option>
              <option value="Sedang">Sedang</option>
              <option value="Rendah">Rendah</option>
            </select>
          </div>
        </div>
      </div>

      <div class="mc-section" style="margin-bottom:0;">
        <div class="mc-section-label"><i class="fa fa-file-lines"></i> Jenis Laporan</div>
        <div class="report-opts">
          <div class="report-opt selected" id="opt-semua" onclick="pilihJenis('semua')">
            <div class="report-opt-icon" style="background:#eff6ff;"><i class="fa fa-list" style="color:#1d4ed8;"></i></div>
            <div class="report-opt-title">Semua Kategori</div>
            <div class="report-opt-sub">Laporan lengkap semua kategori IPSRS</div>
          </div>
          <div class="report-opt" id="opt-kat" onclick="pilihJenis('kat')">
            <div class="report-opt-icon" style="background:#f0fdf9;"><i class="fa fa-tag" style="color:#26B99A;"></i></div>
            <div class="report-opt-title">Per Kategori Terpilih</div>
            <div class="report-opt-sub">Hanya kategori yang dipilih di atas</div>
          </div>
        </div>
      </div>
    </div>

    <div class="mc-preview-bar" id="mc-preview-bar">
      <i class="fa fa-circle-info" style="color:#1d4ed8;"></i>
      <span id="mc-preview-text"></span>
    </div>

    <!-- ══ FOOTER — PDF + Excel ══ -->
    <div class="mc-foot">
      <div style="font-size:10.5px;color:#94a3b8;">
        <i class="fa fa-circle-info" style="color:#94a3b8;"></i>
        PDF terbuka di tab baru &nbsp;·&nbsp; Excel langsung diunduh
      </div>
      <div style="display:flex;gap:8px;">
        <button type="button" onclick="tutupModalCetak()"
          style="padding:7px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;">
          Batal
        </button>
        <button type="button" onclick="cetakLaporan()"
          style="padding:7px 14px;background:linear-gradient(135deg,#ef4444,#b91c1c);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;">
          <i class="fa fa-file-pdf"></i> PDF
        </button>
        <button type="button" onclick="exportExcel()" id="btn-excel-ipsrs"
          style="padding:7px 14px;background:linear-gradient(135deg,#16a34a,#15803d);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;">
          <i class="fa fa-file-excel"></i> Excel
        </button>
      </div>
    </div>
    <!-- ══ END FOOTER ══ -->

  </div>
</div>
<!-- ════ END MODAL ════ -->


<script>
const APP_URL = '<?= APP_URL ?>';
let jenisLaporan = 'semua';

function bukaModalCetak() {
    document.getElementById('mc-overlay').classList.add('open');
    updatePreview();
}
function tutupModalCetak() {
    document.getElementById('mc-overlay').classList.remove('open');
}
function pilihJenis(j) {
    jenisLaporan = j;
    document.getElementById('opt-semua').classList.toggle('selected', j === 'semua');
    document.getElementById('opt-kat').classList.toggle('selected',   j === 'kat');
    updatePreview();
}
function setPeriod(type, el) {
    document.querySelectorAll('.period-chip').forEach(c => c.classList.remove('active'));
    if (el) el.classList.add('active');
    const now = new Date();
    let dari, sampai;
    if      (type === 'bulan_ini')  { dari = new Date(now.getFullYear(), now.getMonth(),   1); sampai = new Date(now.getFullYear(), now.getMonth()+1, 0); }
    else if (type === 'bulan_lalu') { dari = new Date(now.getFullYear(), now.getMonth()-1, 1); sampai = new Date(now.getFullYear(), now.getMonth(),   0); }
    else if (type === '3_bulan')    { dari = new Date(now.getFullYear(), now.getMonth()-2, 1); sampai = new Date(now.getFullYear(), now.getMonth()+1, 0); }
    else if (type === '6_bulan')    { dari = new Date(now.getFullYear(), now.getMonth()-5, 1); sampai = new Date(now.getFullYear(), now.getMonth()+1, 0); }
    else if (type === 'tahun_ini')  { dari = new Date(now.getFullYear(), 0, 1);               sampai = new Date(now.getFullYear(), 11, 31); }
    else                            { dari = new Date(2020, 0, 1);                            sampai = new Date(now.getFullYear(), 11, 31); }
    document.getElementById('mc-tgl-dari').value   = fmtDate(dari);
    document.getElementById('mc-tgl-sampai').value = fmtDate(sampai);
    updatePreview();
}
function fmtDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function updatePreview() {
    const dari   = document.getElementById('mc-tgl-dari').value;
    const sampai = document.getElementById('mc-tgl-sampai').value;
    const jenis  = document.getElementById('mc-jenis');
    const kat    = document.getElementById('mc-kat');
    const status = document.getElementById('mc-status');
    const bar    = document.getElementById('mc-preview-bar');
    const text   = document.getElementById('mc-preview-text');
    if (dari && sampai) {
        const diff = Math.round((new Date(sampai) - new Date(dari)) / (1000*60*60*24)) + 1;
        text.innerHTML = `<strong>Periode:</strong> ${dari} s.d. ${sampai} (${diff} hari) &nbsp;|&nbsp; `
                       + `<strong>Jenis:</strong> ${jenis.options[jenis.selectedIndex].text} &nbsp;|&nbsp; `
                       + `<strong>Kategori:</strong> ${kat.options[kat.selectedIndex].text} &nbsp;|&nbsp; `
                       + `<strong>Status:</strong> ${status.options[status.selectedIndex].text}`;
        bar.classList.add('show');
    } else {
        bar.classList.remove('show');
    }
}

/* ── Bangun query params (dipakai oleh PDF & Excel) ── */
function buildParams() {
    const dari   = document.getElementById('mc-tgl-dari').value;
    const sampai = document.getElementById('mc-tgl-sampai').value;
    const jenis  = document.getElementById('mc-jenis').value;
    const kat    = document.getElementById('mc-kat').value;
    const status = document.getElementById('mc-status').value;
    const prior  = document.getElementById('mc-prioritas').value;

    if (!dari || !sampai) {
        alert('Harap isi tanggal mulai dan tanggal sampai.'); return null;
    }
    if (new Date(dari) > new Date(sampai)) {
        alert('Tanggal mulai tidak boleh lebih besar dari tanggal sampai.'); return null;
    }
    const p = new URLSearchParams({ tgl_dari: dari, tgl_sampai: sampai });
    if (jenis)  p.set('jenis',     jenis);
    if (kat)    p.set('kat',       kat);
    if (status) p.set('status',    status);
    if (prior)  p.set('prioritas', prior);
    return p;
}

/* ── Cetak PDF ── */
function cetakLaporan() {
    const p = buildParams(); if (!p) return;
    window.open(`${APP_URL}/pages/cetak_semua_tiket_ipsrs.php?${p.toString()}`, '_blank');
    tutupModalCetak();
}

/* ── Export Excel ── */
function exportExcel() {
    const p = buildParams(); if (!p) return;
    const btn = document.getElementById('btn-excel-ipsrs');
    const ori = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
    btn.disabled  = true;
    const a  = document.createElement('a');
    a.href   = `${APP_URL}/pages/export_semua_tiket_ipsrs.php?${p.toString()}`;
    a.target = '_blank';
    a.click();
    setTimeout(() => { btn.innerHTML = ori; btn.disabled = false; }, 4000);
    tutupModalCetak();
}

document.addEventListener('DOMContentLoaded', updatePreview);
</script>

<?php include '../includes/footer.php'; ?>