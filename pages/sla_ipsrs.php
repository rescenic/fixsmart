<?php
// pages/sla_ipsrs.php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title  = 'Laporan SLA IPSRS';
$active_menu = 'sla_ipsrs';

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$fj    = $_GET['jenis'] ?? ''; // Medis / Non-Medis / ''

$where_j        = $fj ? "AND t.jenis_tiket = '$fj'" : '';
$where_j_plain  = $fj ? "AND jenis_tiket   = '$fj'" : '';

// ── SLA per kategori ──────────────────────────────────────────────────────────
try {
    $sla_kat = $pdo->prepare("
        SELECT k.nama, k.jenis, k.sla_jam, k.sla_respon_jam,
            COUNT(t.id)                                                         AS total,
            SUM(t.status IN ('selesai','ditolak','tidak_bisa'))                 AS selesai,
            SUM(t.status = 'selesai')                                           AS solved,
            AVG(t.durasi_respon_menit)                                          AS avg_respon,
            AVG(t.durasi_selesai_menit)                                         AS avg_selesai,
            SUM(t.status='selesai' AND t.durasi_selesai_menit <= k.sla_jam*60) AS sla_met,
            SUM(t.status IN ('menunggu','diproses'))                            AS aktif
        FROM kategori_ipsrs k
        LEFT JOIN tiket_ipsrs t
            ON  t.kategori_id = k.id
            AND MONTH(t.created_at) = ?
            AND YEAR(t.created_at)  = ?
            $where_j
        GROUP BY k.id
        ORDER BY k.jenis, k.nama
    ");
    $sla_kat->execute([$bulan, $tahun]);
    $sla_kat = $sla_kat->fetchAll();
} catch (Exception $e) { $sla_kat = []; }

// ── SLA per teknisi ───────────────────────────────────────────────────────────
try {
    $sla_tek = $pdo->prepare("
        SELECT u.nama,
            COUNT(t.id)                  AS total,
            SUM(t.status='selesai')      AS selesai,
            SUM(t.status='ditolak')      AS ditolak,
            SUM(t.status='tidak_bisa')   AS tdk_bisa,
            AVG(t.durasi_respon_menit)   AS avg_respon,
            AVG(t.durasi_selesai_menit)  AS avg_selesai,
            SUM(t.status='selesai'
                AND t.durasi_selesai_menit <= (
                    SELECT k2.sla_jam*60 FROM kategori_ipsrs k2 WHERE k2.id=t.kategori_id
                )
            ) AS sla_met
        FROM users u
        LEFT JOIN tiket_ipsrs t
            ON  t.teknisi_id = u.id
            AND (
                    (t.status='selesai'  AND MONTH(t.waktu_selesai) = ? AND YEAR(t.waktu_selesai) = ?)
                OR  (t.status!='selesai' AND MONTH(t.created_at)    = ? AND YEAR(t.created_at)    = ?)
            )
            $where_j
        WHERE u.role='teknisi' AND u.status='aktif'
        GROUP BY u.id
        ORDER BY selesai DESC
    ");
    $sla_tek->execute([$bulan, $tahun, $bulan, $tahun]);
    $sla_tek = $sla_tek->fetchAll();
} catch (Exception $e) { $sla_tek = []; }

// ── Overall ───────────────────────────────────────────────────────────────────
try {
    $overall = $pdo->prepare("
        SELECT
            COUNT(*)                                                            AS total,
            SUM(status IN ('selesai','ditolak','tidak_bisa'))                  AS selesai,
            SUM(status='menunggu')                                             AS menunggu,
            SUM(status='diproses')                                             AS diproses,
            SUM(status='selesai')                                              AS solved,
            SUM(status='ditolak')                                              AS ditolak,
            SUM(status='tidak_bisa')                                           AS tidak_bisa,
            AVG(durasi_respon_menit)                                           AS avg_respon,
            AVG(durasi_selesai_menit)                                          AS avg_selesai,
            SUM(status='selesai'
                AND durasi_selesai_menit <= (
                    SELECT k.sla_jam*60 FROM kategori_ipsrs k WHERE k.id=kategori_id
                )
            ) AS sla_met
        FROM tiket_ipsrs
        WHERE MONTH(created_at)=? AND YEAR(created_at)=?
        $where_j_plain
    ");
    $overall->execute([$bulan, $tahun]);
    $ov = $overall->fetch();
} catch (Exception $e) {
    $ov = ['total'=>0,'selesai'=>0,'menunggu'=>0,'diproses'=>0,'solved'=>0,
           'ditolak'=>0,'tidak_bisa'=>0,'avg_respon'=>0,'avg_selesai'=>0,'sla_met'=>0];
}
$sla_pct = ($ov['solved'] > 0) ? round($ov['sla_met'] / $ov['solved'] * 100) : 0;

// ── Stats jenis (Medis vs Non-Medis bulan ini) ────────────────────────────────
try {
    $st_j = $pdo->prepare("
        SELECT jenis_tiket, COUNT(*) n, SUM(status='selesai') solved
        FROM tiket_ipsrs
        WHERE MONTH(created_at)=? AND YEAR(created_at)=?
        GROUP BY jenis_tiket
    ");
    $st_j->execute([$bulan, $tahun]);
    $stats_jenis = [];
    foreach ($st_j->fetchAll() as $r) $stats_jenis[$r['jenis_tiket']] = $r;
} catch (Exception $e) { $stats_jenis = []; }

$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
               'Juli','Agustus','September','Oktober','November','Desember'];

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-chart-line text-primary"></i> &nbsp;Laporan SLA IPSRS</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
    <span class="cur">SLA IPSRS</span>
  </div>
</div>

<div class="content">

  <!-- ── Filter + Cetak ── -->
  <div class="panel">
    <div class="panel-bd" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:space-between;">
      <form method="GET" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <label style="font-size:12px;font-weight:700;">Periode:</label>
        <select name="bulan" class="sel-filter">
          <?php for ($m=1;$m<=12;$m++): ?>
          <option value="<?= $m ?>" <?= $m==$bulan?'selected':'' ?>><?= $nama_bulan[$m] ?></option>
          <?php endfor; ?>
        </select>
        <select name="tahun" class="sel-filter">
          <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?>
          <option value="<?= $y ?>" <?= $y==$tahun?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <!-- Filter Jenis -->
        <select name="jenis" class="sel-filter">
          <option value="">Semua Jenis</option>
          <option value="Medis"     <?= $fj==='Medis'?'selected':'' ?>>🏥 Medis</option>
          <option value="Non-Medis" <?= $fj==='Non-Medis'?'selected':'' ?>>🔧 Non-Medis</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Tampilkan</button>
        <?php if ($fj): ?>
        <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-default btn-sm">
          <i class="fa fa-times"></i> Reset Filter
        </a>
        <?php endif; ?>
      </form>

      <a href="<?= APP_URL ?>/pages/cetak_sla_ipsrs.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?><?= $fj?"&jenis=".urlencode($fj):'' ?>"
         class="btn btn-danger btn-sm" target="_blank">
        <i class="fa fa-file-pdf"></i> &nbsp;Cetak PDF
      </a>
    </div>
  </div>

  <!-- Periode aktif -->
  <div style="font-size:12px;color:#888;margin-bottom:12px;padding:0 2px;">
    Menampilkan data: <strong><?= $nama_bulan[$bulan].' '.$tahun ?></strong>
    <?php if ($fj): ?>
    &nbsp;—&nbsp;
    <?php if ($fj==='Medis'): ?>
    <span style="background:#fce7f3;color:#9d174d;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:700;">
      <i class="fa fa-kit-medical"></i> Medis
    </span>
    <?php else: ?>
    <span style="background:#dbeafe;color:#1e40af;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:700;">
      <i class="fa fa-screwdriver-wrench"></i> Non-Medis
    </span>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- ── Kartu Medis vs Non-Medis ── -->
  <?php if (!$fj): ?>
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <?php
    $jenis_cfg = [
      'Medis'     => ['icon'=>'fa-kit-medical',      'bg'=>'#fdf2f8','border'=>'#f9a8d4','ibg'=>'#fce7f3','ic'=>'#db2777','tc'=>'#9d174d'],
      'Non-Medis' => ['icon'=>'fa-screwdriver-wrench','bg'=>'#eff6ff','border'=>'#93c5fd','ibg'=>'#dbeafe','ic'=>'#1d4ed8','tc'=>'#1e40af'],
    ];
    foreach ($jenis_cfg as $jn => $cfg):
      $jd = $stats_jenis[$jn] ?? ['n'=>0,'solved'=>0];
    ?>
    <div style="flex:1;min-width:180px;background:<?= $cfg['bg'] ?>;border:1px solid <?= $cfg['border'] ?>;border-radius:10px;padding:14px 16px;cursor:pointer;"
         onclick="location.href='?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenis=<?= urlencode($jn) ?>'">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:40px;height:40px;border-radius:9px;background:<?= $cfg['ibg'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fa <?= $cfg['icon'] ?>" style="color:<?= $cfg['ic'] ?>;font-size:17px;"></i>
        </div>
        <div>
          <div style="font-size:11px;font-weight:700;color:<?= $cfg['tc'] ?>;margin-bottom:2px;"><?= $jn ?></div>
          <div style="font-size:22px;font-weight:800;color:<?= $cfg['tc'] ?>;line-height:1;"><?= $jd['n'] ?? 0 ?></div>
          <div style="font-size:10px;color:<?= $cfg['ic'] ?>;">tiket bulan ini &nbsp;·&nbsp; <?= $jd['solved'] ?? 0 ?> selesai</div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Overall stats ── -->
  <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
    <div class="stat-card c-total">
      <i class="fa fa-ticket-alt sc-icon"></i>
      <div><div class="sc-num"><?= $ov['total'] ?? 0 ?></div><div class="sc-lbl">Total Tiket</div></div>
    </div>
    <div class="stat-card c-selesai">
      <i class="fa fa-check-circle sc-icon"></i>
      <div><div class="sc-num"><?= $ov['solved'] ?? 0 ?></div><div class="sc-lbl">Selesai</div></div>
    </div>
    <div class="stat-card c-ditolak">
      <i class="fa fa-ban sc-icon"></i>
      <div><div class="sc-num"><?= ($ov['ditolak']??0) + ($ov['tidak_bisa']??0) ?></div><div class="sc-lbl">Ditolak / Tdk Bisa</div></div>
    </div>
    <div class="stat-card c-diproses">
      <i class="fa fa-stopwatch sc-icon"></i>
      <div><div class="sc-num"><?= formatDurasi(round($ov['avg_respon'] ?? 0)) ?></div><div class="sc-lbl">Avg. Respon</div></div>
    </div>
    <div class="stat-card c-menunggu">
      <i class="fa fa-clock sc-icon"></i>
      <div><div class="sc-num"><?= formatDurasi(round($ov['avg_selesai'] ?? 0)) ?></div><div class="sc-lbl">Avg. Selesai</div></div>
    </div>
  </div>

  <!-- ── SLA Gauge + Teknisi ── -->
  <div class="g2">

    <!-- Gauge -->
    <div class="panel">
      <div class="panel-hd">
        <h5><i class="fa fa-tachometer-alt text-primary"></i> &nbsp;Pencapaian SLA
          <?= $fj ? "— <span style='font-size:11px;'>$fj</span>" : 'Bulan Ini' ?>
        </h5>
      </div>
      <div class="panel-bd" style="text-align:center;padding:20px;">
        <div style="font-size:52px;font-weight:700;color:<?= $sla_pct>=90?'var(--green)':($sla_pct>=70?'var(--orange)':'var(--red)') ?>;">
          <?= $sla_pct ?>%
        </div>
        <div style="font-size:13px;color:#aaa;margin-bottom:15px;">
          <?= $ov['sla_met'] ?? 0 ?> dari <?= $ov['solved'] ?? 0 ?> tiket selesai dalam target SLA
        </div>
        <div class="progress" style="height:12px;max-width:300px;margin:0 auto;">
          <div class="progress-fill <?= $sla_pct>=90?'pg-green':($sla_pct>=70?'pg-orange':'pg-red') ?>"
               style="width:<?= $sla_pct ?>%;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;max-width:300px;margin:5px auto 0;font-size:10px;color:#bbb;">
          <span>0%</span><span>Target ≥ 90%</span><span>100%</span>
        </div>

        <!-- Breakdown aktif -->
        <?php if (($ov['menunggu']??0) || ($ov['diproses']??0)): ?>
        <div style="display:flex;gap:10px;justify-content:center;margin-top:16px;">
          <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-weight:700;">
            <i class="fa fa-clock"></i> <?= $ov['menunggu']??0 ?> menunggu
          </span>
          <span style="font-size:11px;background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:20px;font-weight:700;">
            <i class="fa fa-cogs"></i> <?= $ov['diproses']??0 ?> diproses
          </span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- SLA per Teknisi -->
    <div class="panel">
      <div class="panel-hd"><h5><i class="fa fa-users text-primary"></i> &nbsp;SLA per Teknisi IPSRS</h5></div>
      <div class="panel-bd np">
        <table>
          <thead>
            <tr>
              <th>Teknisi</th>
              <th>Total</th>
              <th>Selesai</th>
              <th>Ditolak</th>
              <th>SLA Met</th>
              <th>Avg. Selesai</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($sla_tek)): ?>
            <tr><td colspan="6" class="td-empty"><i class="fa fa-users"></i> Tidak ada data</td></tr>
            <?php else: foreach ($sla_tek as $tek):
              $tek_sla = ($tek['selesai'] > 0) ? round($tek['sla_met'] / $tek['selesai'] * 100) : 0;
            ?>
            <tr>
              <td>
                <div class="d-flex ai-c gap6">
                  <div class="av av-xs av-blue"><?= getInitials($tek['nama']) ?></div>
                  <?= clean($tek['nama']) ?>
                </div>
              </td>
              <td style="font-weight:700;"><?= $tek['total'] ?></td>
              <td style="color:var(--green);font-weight:700;"><?= $tek['selesai'] ?></td>
              <td style="color:var(--red);"><?= ($tek['ditolak']??0)+($tek['tdk_bisa']??0) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:6px;">
                  <div class="progress" style="width:50px;">
                    <div class="progress-fill <?= $tek_sla>=90?'pg-green':($tek_sla>=70?'pg-orange':'pg-red') ?>"
                         style="width:<?= $tek_sla ?>%;"></div>
                  </div>
                  <span style="font-size:11px;font-weight:700;"><?= $tek_sla ?>%</span>
                </div>
              </td>
              <td style="font-size:12px;"><?= formatDurasi(round($tek['avg_selesai'] ?? 0)) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── SLA per Kategori ── -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-tags text-primary"></i> &nbsp;SLA per Kategori IPSRS</h5>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Jenis</th>
            <th>Kategori</th>
            <th>Target SLA</th>
            <th>Total</th>
            <th>Selesai</th>
            <th>Dalam Target</th>
            <th>% SLA</th>
            <th>Avg. Respon</th>
            <th>Avg. Selesai</th>
            <th>Aktif</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sla_kat)): ?>
          <tr><td colspan="10" class="td-empty"><i class="fa fa-tags"></i> Tidak ada data</td></tr>
          <?php else:
            $cur_j = '';
            foreach ($sla_kat as $k):
              $k_sla   = ($k['solved'] > 0) ? round($k['sla_met'] / $k['solved'] * 100) : 0;
              $sla_cls = $k_sla>=90 ? '#d1fae5;color:#065f46' : ($k_sla>=70 ? '#fef3c7;color:#92400e' : '#fee2e2;color:#991b1b');
              $is_m    = ($k['jenis'] === 'Medis');

              // Separator baris jenis
              if ($k['jenis'] !== $cur_j && !$fj):
                $cur_j = $k['jenis'];
          ?>
          <tr style="background:<?= $is_m?'#fdf2f8':'#eff6ff' ?>;">
            <td colspan="10" style="font-weight:700;font-size:11px;padding:6px 12px;color:<?= $is_m?'#9d174d':'#1e40af' ?>;">
              <?php if ($is_m): ?>
              <i class="fa fa-kit-medical"></i> Medis
              <?php else: ?>
              <i class="fa fa-screwdriver-wrench"></i> Non-Medis
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
          <tr>
            <td>
              <?php if ($is_m): ?>
              <span style="display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#fce7f3;color:#9d174d;">
                <i class="fa fa-kit-medical" style="font-size:9px;"></i> Medis
              </span>
              <?php else: ?>
              <span style="display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:700;background:#dbeafe;color:#1e40af;">
                <i class="fa fa-screwdriver-wrench" style="font-size:9px;"></i> Non-Medis
              </span>
              <?php endif; ?>
            </td>
            <td style="font-weight:600;"><?= clean($k['nama']) ?></td>
            <td><span style="font-size:11px;color:#888;"><?= $k['sla_jam'] ?> jam</span></td>
            <td style="font-weight:700;"><?= $k['total'] ?? 0 ?></td>
            <td style="color:var(--green);font-weight:700;"><?= $k['selesai'] ?? 0 ?></td>
            <td><?= $k['sla_met'] ?? 0 ?></td>
            <td>
              <span class="sla-badge" style="background:<?= $sla_cls ?>;"><?= $k_sla ?>%</span>
            </td>
            <td style="font-size:12px;"><?= formatDurasi(round($k['avg_respon'] ?? 0)) ?></td>
            <td style="font-size:12px;"><?= formatDurasi(round($k['avg_selesai'] ?? 0)) ?></td>
            <td><?= $k['aktif'] ?? 0 ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /content -->
<?php include '../includes/footer.php'; ?>