<?php
// pages/sla_ipsrs.php
session_start();
require_once '../config.php';
requireLogin();

// ── Akses: hanya admin dan teknisi_ipsrs ─────────────────────────────────────
if (!hasRole(['admin', 'teknisi_ipsrs'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Laporan SLA IPSRS';
$active_menu = 'sla_ipsrs';

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

// Validasi jenis — whitelist untuk cegah SQL injection
$valid_jenis = ['', 'Medis', 'Non-Medis'];
$fj = in_array($_GET['jenis'] ?? '', $valid_jenis) ? ($_GET['jenis'] ?? '') : '';

// Validasi bulan & tahun
$bulan = max(1, min(12, $bulan));
$tahun = max(2020, min((int)date('Y') + 1, $tahun));

// ── Query param: jenis filter ─────────────────────────────────────────────────
// Gunakan prepared statement, bukan string interpolation
$has_jenis = ($fj !== '');

// ── SLA per kategori ──────────────────────────────────────────────────────────
try {
    $sql_kat = "
        SELECT k.nama, k.jenis, k.sla_jam, k.sla_respon_jam,
            COUNT(t.id)                                                          AS total,
            SUM(t.status IN ('selesai','ditolak','tidak_bisa'))                  AS selesai,
            SUM(t.status = 'selesai')                                            AS solved,
            AVG(t.durasi_respon_menit)                                           AS avg_respon,
            AVG(t.durasi_selesai_menit)                                          AS avg_selesai,
            SUM(t.status='selesai' AND t.durasi_selesai_menit <= k.sla_jam*60)  AS sla_met,
            SUM(t.status IN ('menunggu','diproses'))                             AS aktif
        FROM kategori_ipsrs k
        LEFT JOIN tiket_ipsrs t
            ON  t.kategori_id = k.id
            AND MONTH(t.created_at) = ?
            AND YEAR(t.created_at)  = ?
            " . ($has_jenis ? "AND t.jenis_tiket = ?" : "") . "
        GROUP BY k.id
        ORDER BY k.jenis, k.nama
    ";
    $p_kat = $has_jenis ? [$bulan, $tahun, $fj] : [$bulan, $tahun];
    $sla_kat_st = $pdo->prepare($sql_kat);
    $sla_kat_st->execute($p_kat);
    $sla_kat = $sla_kat_st->fetchAll();
} catch (Exception $e) { $sla_kat = []; }

// ── SLA per teknisi IPSRS ─────────────────────────────────────────────────────
// PERBAIKAN: WHERE u.role = 'teknisi_ipsrs' (sebelumnya salah: 'teknisi')
try {
    $sql_tek = "
        SELECT u.nama,
            COUNT(t.id)                  AS total,
            SUM(t.status='selesai')      AS selesai,
            SUM(t.status='ditolak')      AS ditolak,
            SUM(t.status='tidak_bisa')   AS tdk_bisa,
            AVG(t.durasi_respon_menit)   AS avg_respon,
            AVG(t.durasi_selesai_menit)  AS avg_selesai,
            SUM(
                t.status='selesai'
                AND t.durasi_selesai_menit <= (
                    SELECT k2.sla_jam * 60 FROM kategori_ipsrs k2 WHERE k2.id = t.kategori_id
                )
            ) AS sla_met
        FROM users u
        LEFT JOIN tiket_ipsrs t
            ON  t.teknisi_id = u.id
            AND (
                    (t.status = 'selesai'  AND MONTH(t.waktu_selesai) = ? AND YEAR(t.waktu_selesai) = ?)
                OR  (t.status != 'selesai' AND MONTH(t.created_at)    = ? AND YEAR(t.created_at)    = ?)
            )
            " . ($has_jenis ? "AND t.jenis_tiket = ?" : "") . "
        WHERE u.role = 'teknisi_ipsrs' AND u.status = 'aktif'
        GROUP BY u.id
        ORDER BY selesai DESC
    ";
    $p_tek = $has_jenis
             ? [$bulan, $tahun, $bulan, $tahun, $fj]
             : [$bulan, $tahun, $bulan, $tahun];
    $sla_tek_st = $pdo->prepare($sql_tek);
    $sla_tek_st->execute($p_tek);
    $sla_tek = $sla_tek_st->fetchAll();
} catch (Exception $e) { $sla_tek = []; }

// ── Overall ───────────────────────────────────────────────────────────────────
try {
    $sql_ov = "
        SELECT
            COUNT(*)                                                              AS total,
            SUM(status IN ('selesai','ditolak','tidak_bisa'))                    AS selesai,
            SUM(status = 'menunggu')                                             AS menunggu,
            SUM(status = 'diproses')                                             AS diproses,
            SUM(status = 'selesai')                                              AS solved,
            SUM(status = 'ditolak')                                              AS ditolak,
            SUM(status = 'tidak_bisa')                                           AS tidak_bisa,
            AVG(durasi_respon_menit)                                             AS avg_respon,
            AVG(durasi_selesai_menit)                                            AS avg_selesai,
            SUM(
                status = 'selesai'
                AND durasi_selesai_menit <= (
                    SELECT k.sla_jam * 60 FROM kategori_ipsrs k WHERE k.id = kategori_id
                )
            ) AS sla_met
        FROM tiket_ipsrs
        WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
        " . ($has_jenis ? "AND jenis_tiket = ?" : "");
    $p_ov = $has_jenis ? [$bulan, $tahun, $fj] : [$bulan, $tahun];
    $ov_st = $pdo->prepare($sql_ov);
    $ov_st->execute($p_ov);
    $ov = $ov_st->fetch();
} catch (Exception $e) {
    $ov = ['total'=>0,'selesai'=>0,'menunggu'=>0,'diproses'=>0,'solved'=>0,
           'ditolak'=>0,'tidak_bisa'=>0,'avg_respon'=>0,'avg_selesai'=>0,'sla_met'=>0];
}

// Null-safe
$ov['avg_respon']   = (float)($ov['avg_respon']   ?? 0);
$ov['avg_selesai']  = (float)($ov['avg_selesai']  ?? 0);
$ov['solved']       = (int)($ov['solved']         ?? 0);
$ov['sla_met']      = (int)($ov['sla_met']        ?? 0);
$ov['menunggu']     = (int)($ov['menunggu']       ?? 0);
$ov['diproses']     = (int)($ov['diproses']       ?? 0);

$sla_pct = $ov['solved'] > 0 ? round($ov['sla_met'] / $ov['solved'] * 100) : 0;

// ── Stats jenis (Medis vs Non-Medis) ─────────────────────────────────────────
try {
    $st_j = $pdo->prepare("
        SELECT jenis_tiket, COUNT(*) n, SUM(status='selesai') solved
        FROM tiket_ipsrs
        WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
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
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
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
          <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $m == $bulan ? 'selected' : '' ?>><?= $nama_bulan[$m] ?></option>
          <?php endfor; ?>
        </select>
        <select name="tahun" class="sel-filter">
          <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
          <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <select name="jenis" class="sel-filter">
          <option value="">Semua Jenis</option>
          <option value="Medis"     <?= $fj === 'Medis'     ? 'selected' : '' ?>>🏥 Medis</option>
          <option value="Non-Medis" <?= $fj === 'Non-Medis' ? 'selected' : '' ?>>🔧 Non-Medis</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Tampilkan</button>
        <?php if ($fj): ?>
        <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-default btn-sm">
          <i class="fa fa-times"></i> Reset Filter
        </a>
        <?php endif; ?>
      </form>

      <a href="<?= APP_URL ?>/pages/cetak_sla_ipsrs.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?><?= $fj ? '&jenis=' . urlencode($fj) : '' ?>"
         class="btn btn-danger btn-sm" target="_blank">
        <i class="fa fa-file-pdf"></i> &nbsp;Cetak PDF
      </a>
    </div>
  </div>

  <!-- ── Periode aktif ── -->
  <div style="font-size:12px;color:#94a3b8;margin-bottom:12px;padding:0 2px;">
    Menampilkan data: <strong style="color:#374151;"><?= $nama_bulan[$bulan] . ' ' . $tahun ?></strong>
    <?php if ($fj): ?>
    &nbsp;—&nbsp;
    <?php if ($fj === 'Medis'): ?>
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

  <!-- ── Kartu Medis vs Non-Medis (hanya tampil jika tidak filter jenis) ── -->
  <?php if (!$fj): ?>
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <?php
    $jenis_cfg = [
      'Medis'     => ['icon'=>'fa-kit-medical',       'bg'=>'#fdf2f8','border'=>'#f9a8d4','ibg'=>'#fce7f3','ic'=>'#db2777','tc'=>'#9d174d'],
      'Non-Medis' => ['icon'=>'fa-screwdriver-wrench','bg'=>'#eff6ff','border'=>'#93c5fd','ibg'=>'#dbeafe','ic'=>'#1d4ed8','tc'=>'#1e40af'],
    ];
    foreach ($jenis_cfg as $jn => $cfg):
      $jd = $stats_jenis[$jn] ?? ['n' => 0, 'solved' => 0];
    ?>
    <div style="flex:1;min-width:180px;background:<?= $cfg['bg'] ?>;border:1px solid <?= $cfg['border'] ?>;border-radius:10px;padding:14px 16px;cursor:pointer;transition:box-shadow .18s;"
         onclick="location.href='?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&jenis=<?= urlencode($jn) ?>'"
         onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,.1)'"
         onmouseout="this.style.boxShadow='none'">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:40px;height:40px;border-radius:9px;background:<?= $cfg['ibg'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fa <?= $cfg['icon'] ?>" style="color:<?= $cfg['ic'] ?>;font-size:17px;"></i>
        </div>
        <div>
          <div style="font-size:11px;font-weight:700;color:<?= $cfg['tc'] ?>;margin-bottom:2px;"><?= $jn ?></div>
          <div style="font-size:22px;font-weight:800;color:<?= $cfg['tc'] ?>;line-height:1;"><?= (int)($jd['n'] ?? 0) ?></div>
          <div style="font-size:10px;color:<?= $cfg['ic'] ?>;">
            tiket bulan ini &nbsp;·&nbsp; <?= (int)($jd['solved'] ?? 0) ?> selesai
          </div>
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
      <div><div class="sc-num"><?= (int)($ov['total'] ?? 0) ?></div><div class="sc-lbl">Total Tiket</div></div>
    </div>
    <div class="stat-card c-selesai">
      <i class="fa fa-check-circle sc-icon"></i>
      <div><div class="sc-num"><?= $ov['solved'] ?></div><div class="sc-lbl">Selesai</div></div>
    </div>
    <div class="stat-card c-ditolak">
      <i class="fa fa-ban sc-icon"></i>
      <div><div class="sc-num"><?= (int)($ov['ditolak']??0) + (int)($ov['tidak_bisa']??0) ?></div><div class="sc-lbl">Ditolak / Tdk Bisa</div></div>
    </div>
    <div class="stat-card c-diproses">
      <i class="fa fa-stopwatch sc-icon"></i>
      <div><div class="sc-num"><?= formatDurasi((int)round($ov['avg_respon'])) ?></div><div class="sc-lbl">Avg. Respon</div></div>
    </div>
    <div class="stat-card c-menunggu">
      <i class="fa fa-clock sc-icon"></i>
      <div><div class="sc-num"><?= formatDurasi((int)round($ov['avg_selesai'])) ?></div><div class="sc-lbl">Avg. Selesai</div></div>
    </div>
  </div>

  <!-- ── SLA Gauge + Teknisi ── -->
  <div class="g2">

    <!-- Gauge -->
    <div class="panel">
      <div class="panel-hd">
        <h5>
          <i class="fa fa-tachometer-alt text-primary"></i>
          &nbsp;Pencapaian SLA <?= $fj ? "— <span style='font-size:11px;font-weight:600;'>$fj</span>" : 'Bulan Ini' ?>
        </h5>
      </div>
      <div class="panel-bd" style="text-align:center;padding:20px;">
        <?php $sla_color = $sla_pct >= 90 ? 'var(--green)' : ($sla_pct >= 70 ? 'var(--orange)' : 'var(--red)'); ?>
        <div style="font-size:52px;font-weight:800;color:<?= $sla_color ?>;line-height:1;">
          <?= $sla_pct ?>%
        </div>
        <div style="font-size:13px;color:#94a3b8;margin:6px 0 15px;">
          <?= $ov['sla_met'] ?> dari <?= $ov['solved'] ?> tiket selesai dalam target SLA
        </div>
        <div class="progress" style="height:12px;max-width:300px;margin:0 auto;">
          <div class="progress-fill <?= $sla_pct>=90?'pg-green':($sla_pct>=70?'pg-orange':'pg-red') ?>"
               style="width:<?= $sla_pct ?>%;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;max-width:300px;margin:5px auto 0;font-size:10px;color:#cbd5e1;">
          <span>0%</span><span>Target ≥ 90%</span><span>100%</span>
        </div>

        <?php if ($ov['menunggu'] || $ov['diproses']): ?>
        <div style="display:flex;gap:10px;justify-content:center;margin-top:16px;">
          <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-weight:700;">
            <i class="fa fa-clock"></i> <?= $ov['menunggu'] ?> menunggu
          </span>
          <span style="font-size:11px;background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:20px;font-weight:700;">
            <i class="fa fa-cogs"></i> <?= $ov['diproses'] ?> diproses
          </span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- SLA per Teknisi IPSRS -->
    <div class="panel">
      <div class="panel-hd">
        <h5><i class="fa fa-users text-primary"></i> &nbsp;SLA per Teknisi IPSRS</h5>
      </div>
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
            <tr><td colspan="6" class="td-empty"><i class="fa fa-users"></i> Tidak ada data teknisi IPSRS</td></tr>
            <?php else:
              foreach ($sla_tek as $tek):
                $tek['selesai']    = (int)($tek['selesai']    ?? 0);
                $tek['sla_met']    = (int)($tek['sla_met']    ?? 0);
                $tek['avg_selesai']= (float)($tek['avg_selesai'] ?? 0);
                $tek_sla = $tek['selesai'] > 0 ? round($tek['sla_met'] / $tek['selesai'] * 100) : 0;
            ?>
            <tr>
              <td>
                <div class="d-flex ai-c gap6">
                  <div class="av av-xs av-blue"><?= getInitials($tek['nama']) ?></div>
                  <span><?= clean($tek['nama']) ?></span>
                </div>
              </td>
              <td style="font-weight:700;"><?= (int)$tek['total'] ?></td>
              <td style="color:var(--green);font-weight:700;"><?= $tek['selesai'] ?></td>
              <td style="color:var(--red);"><?= (int)($tek['ditolak']??0) + (int)($tek['tdk_bisa']??0) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:6px;">
                  <div class="progress" style="width:50px;">
                    <div class="progress-fill <?= $tek_sla>=90?'pg-green':($tek_sla>=70?'pg-orange':'pg-red') ?>"
                         style="width:<?= $tek_sla ?>%;"></div>
                  </div>
                  <span style="font-size:11px;font-weight:700;"><?= $tek_sla ?>%</span>
                </div>
              </td>
              <td style="font-size:12px;"><?= formatDurasi((int)round($tek['avg_selesai'])) ?></td>
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
          <tr><td colspan="10" class="td-empty"><i class="fa fa-tags"></i> Tidak ada data kategori</td></tr>
          <?php else:
            $cur_j = '';
            foreach ($sla_kat as $k):
              $k['solved']       = (int)($k['solved']       ?? 0);
              $k['sla_met']      = (int)($k['sla_met']      ?? 0);
              $k['avg_respon']   = (float)($k['avg_respon'] ?? 0);
              $k['avg_selesai']  = (float)($k['avg_selesai']?? 0);
              $k_sla   = $k['solved'] > 0 ? round($k['sla_met'] / $k['solved'] * 100) : 0;
              $sla_cls = $k_sla >= 90
                         ? '#d1fae5;color:#065f46'
                         : ($k_sla >= 70 ? '#fef3c7;color:#92400e' : '#fee2e2;color:#991b1b');
              $is_m = ($k['jenis'] === 'Medis');

              // Row separator per jenis (hanya jika tidak filter jenis)
              if (!$fj && $k['jenis'] !== $cur_j):
                $cur_j = $k['jenis'];
          ?>
          <tr style="background:<?= $is_m?'#fdf2f8':'#eff6ff' ?>;">
            <td colspan="10" style="font-weight:700;font-size:11px;padding:6px 12px;color:<?= $is_m?'#9d174d':'#1e40af' ?>;">
              <i class="fa <?= $is_m?'fa-kit-medical':'fa-screwdriver-wrench' ?>"></i>
              <?= $is_m ? 'Medis' : 'Non-Medis' ?>
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
            <td><span style="font-size:11px;color:#64748b;"><?= (int)$k['sla_jam'] ?> jam</span></td>
            <td style="font-weight:700;"><?= (int)($k['total'] ?? 0) ?></td>
            <td style="color:var(--green);font-weight:700;"><?= (int)($k['selesai'] ?? 0) ?></td>
            <td><?= $k['sla_met'] ?></td>
            <td>
              <span class="sla-badge" style="background:<?= $sla_cls ?>;"><?= $k_sla ?>%</span>
            </td>
            <td style="font-size:12px;"><?= formatDurasi((int)round($k['avg_respon'])) ?></td>
            <td style="font-size:12px;"><?= formatDurasi((int)round($k['avg_selesai'])) ?></td>
            <td><?= (int)($k['aktif'] ?? 0) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /content -->
<?php include '../includes/footer.php'; ?>