<?php
/**
 * aset_detail_publik.php
 * Halaman publik detail aset — tidak butuh login.
 * Bisa diakses langsung via QR code scan dari HP.
 *
 * URL: /fixsmart/aset_detail_publik.php?id=6
 */
require_once __DIR__ . '/config.php';

$id   = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(404);
    die('ID aset tidak valid.');
}

$stm = $pdo->prepare("
    SELECT  a.*,
            b.nama      AS bagian_nama,
            b.kode      AS bagian_kode,
            b.lokasi    AS bagian_lokasi,
            u.nama      AS pj_nama_db,
            u.divisi    AS pj_divisi,
            u.no_hp     AS pj_hp
    FROM    aset_it a
    LEFT JOIN bagian b ON b.id = a.bagian_id
    LEFT JOIN users  u ON u.id = a.pj_user_id
    WHERE   a.id = ?
    LIMIT 1
");
$stm->execute([$id]);
$a = $stm->fetch(PDO::FETCH_ASSOC);

if (!$a) {
    http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Aset Tidak Ditemukan</title>
<style>
  body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
       min-height:100vh;margin:0;background:#0f172a;color:#fff;}
  .box{text-align:center;padding:40px;}
  .ico{font-size:64px;margin-bottom:16px;}
  h1{font-size:20px;margin-bottom:8px;}
  p{color:#94a3b8;font-size:14px;}
</style>
</head>
<body>
<div class="box">
  <div class="ico">🔍</div>
  <h1>Aset Tidak Ditemukan</h1>
  <p>ID #<?= $id ?> tidak ada dalam sistem.</p>
</div>
</body>
</html>
<?php
    exit;
}

// ── Helper ────────────────────────────────────────────────────────────────────
function clean2($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

function kondisiStyle($k): array {
    return match($k) {
        'Baik'            => ['bg'=>'#d1fae5','fg'=>'#065f46','dot'=>'#16a34a','ico'=>'✅'],
        'Rusak'           => ['bg'=>'#fee2e2','fg'=>'#991b1b','dot'=>'#dc2626','ico'=>'❌'],
        'Dalam Perbaikan' => ['bg'=>'#fef9c3','fg'=>'#854d0e','dot'=>'#d97706','ico'=>'🔧'],
        default           => ['bg'=>'#f1f5f9','fg'=>'#475569','dot'=>'#94a3b8','ico'=>'⛔'],
    };
}

function statusStyle($s): array {
    return match($s) {
        'Tidak Terpakai'  => ['bg'=>'#d1fae5','fg'=>'#065f46','dot'=>'#16a34a','ico'=>'🟢'],
        'Dipinjam'        => ['bg'=>'#fef3c7','fg'=>'#92400e','dot'=>'#f59e0b','ico'=>'🟡'],
        default           => ['bg'=>'#dbeafe','fg'=>'#1e40af','dot'=>'#3b82f6','ico'=>'🔵'],
    };
}

$kStyle = kondisiStyle($a['kondisi']     ?? 'Baik');
$sStyle = statusStyle($a['status_pakai'] ?? 'Terpakai');

$lokasi = $a['bagian_nama']
    ? (($a['bagian_kode'] ? '[' . clean2($a['bagian_kode']) . '] ' : '') . clean2($a['bagian_nama']))
    : clean2($a['lokasi'] ?? '—');

$pj_nama = $a['pj_nama_db'] ?: ($a['penanggung_jawab'] ?? '—');

$garansi_html = '—';
if ($a['garansi_sampai']) {
    $ts = strtotime($a['garansi_sampai']);
    $tgl = date('d M Y', $ts);
    if ($ts < time()) {
        $garansi_html = "<span style='color:#ef4444;font-weight:700;'>⚠️ Expired – {$tgl}</span>";
    } elseif ($ts < strtotime('+30 days')) {
        $garansi_html = "<span style='color:#f59e0b;font-weight:700;'>⏰ Segera – {$tgl}</span>";
    } else {
        $garansi_html = "<span style='color:#16a34a;font-weight:700;'>✅ {$tgl}</span>";
    }
}

$harga_html = $a['harga_beli']
    ? 'Rp ' . number_format($a['harga_beli'], 0, ',', '.')
    : '—';

$tgl_beli = $a['tanggal_beli'] ? date('d M Y', strtotime($a['tanggal_beli'])) : '—';
$tgl_input = $a['created_at']  ? date('d M Y, H:i', strtotime($a['created_at'])) : '—';
$tgl_update= $a['updated_at']  ? date('d M Y, H:i', strtotime($a['updated_at'])) : '—';

$page_title = clean2($a['nama_aset']) . ' — FixSmart';

// QR url untuk halaman ini sendiri (buat QR di halaman)
$self_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST']
          . $_SERVER['REQUEST_URI'];
$qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&ecc=M&data=' . rawurlencode($self_url);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="theme-color" content="#0a0f14">
<title><?= $page_title ?></title>
<style>
/* ══════════════════════════════════════════════
   BASE
══════════════════════════════════════════════ */
:root {
  --navy:   #0a0f14;
  --navy2:  #0d1a2a;
  --teal:   #00e5b0;
  --teal2:  #00c896;
  --card:   #ffffff;
  --bg:     #f0f4f8;
  --border: #e2e8f0;
  --text:   #1e293b;
  --muted:  #64748b;
  --faint:  #94a3b8;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: -apple-system, 'Segoe UI', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  padding-bottom: 40px;
}

/* ── Hero header ── */
.hero {
  background: linear-gradient(160deg, var(--navy) 0%, var(--navy2) 60%, #0f2744 100%);
  padding: 20px 18px 50px;
  position: relative;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute;
  top: -60px; right: -60px;
  width: 220px; height: 220px;
  background: radial-gradient(circle, rgba(0,229,176,.12) 0%, transparent 70%);
  border-radius: 50%;
}
.hero::after {
  content: '';
  position: absolute;
  bottom: -30px; left: 30%;
  width: 160px; height: 160px;
  background: radial-gradient(circle, rgba(0,229,176,.07) 0%, transparent 70%);
  border-radius: 50%;
}

.hero-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
}
.hero-brand-dot {
  width: 8px; height: 8px;
  background: var(--teal);
  border-radius: 50%;
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0%,100%{opacity:1;transform:scale(1);}
  50%{opacity:.5;transform:scale(1.4);}
}
.hero-brand-txt {
  font-size: 12px;
  font-weight: 700;
  color: var(--teal);
  letter-spacing: 1px;
  text-transform: uppercase;
}

.hero-no-inv {
  display: inline-block;
  font-family: 'Courier New', monospace;
  font-size: 11px;
  font-weight: 700;
  color: #93c5fd;
  background: rgba(147,197,253,.12);
  border: 1px solid rgba(147,197,253,.25);
  padding: 3px 10px;
  border-radius: 20px;
  margin-bottom: 10px;
  letter-spacing: .5px;
}

.hero-nama {
  font-size: 22px;
  font-weight: 800;
  color: #fff;
  line-height: 1.2;
  margin-bottom: 10px;
}

.hero-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  margin-top: 12px;
}
.badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
}

/* ── Card float ── */
.card-float {
  background: var(--card);
  border-radius: 16px 16px 0 0;
  margin-top: -28px;
  position: relative;
  z-index: 10;
  box-shadow: 0 -4px 24px rgba(0,0,0,.08);
  padding: 0 0 16px;
}

/* ── Section ── */
.section {
  padding: 20px 18px 0;
}
.section-title {
  font-size: 11px;
  font-weight: 700;
  color: var(--faint);
  letter-spacing: 1.5px;
  text-transform: uppercase;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 7px;
}
.section-title::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

/* ── Info rows ── */
.info-row {
  display: flex;
  align-items: flex-start;
  padding: 11px 0;
  border-bottom: 1px solid var(--border);
  gap: 12px;
}
.info-row:last-child { border-bottom: none; }

.info-icon {
  width: 34px; height: 34px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
  margin-top: 1px;
}

.info-label {
  font-size: 11px;
  color: var(--faint);
  margin-bottom: 2px;
  font-weight: 600;
}
.info-value {
  font-size: 14px;
  color: var(--text);
  font-weight: 600;
  line-height: 1.4;
}
.info-value.mono {
  font-family: 'Courier New', monospace;
  font-size: 13px;
  color: #475569;
}

/* ── Highlight box ── */
.highlight-box {
  margin: 14px 18px 0;
  padding: 14px 16px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.highlight-box .hb-ico {
  font-size: 24px;
  flex-shrink: 0;
}
.highlight-box .hb-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .5px;
  text-transform: uppercase;
  opacity: .7;
  margin-bottom: 2px;
}
.highlight-box .hb-val {
  font-size: 16px;
  font-weight: 800;
}

/* ── QR section ── */
.qr-section {
  margin: 18px 18px 0;
  background: linear-gradient(135deg, var(--navy), var(--navy2));
  border-radius: 14px;
  padding: 18px;
  display: flex;
  align-items: center;
  gap: 16px;
}
.qr-section img {
  border: 2px solid rgba(0,229,176,.3);
  border-radius: 10px;
  background: #fff;
  padding: 4px;
  flex-shrink: 0;
}
.qr-section .qr-text {
  flex: 1;
}
.qr-section .qr-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--teal);
  margin-bottom: 4px;
}
.qr-section .qr-sub {
  font-size: 11px;
  color: rgba(255,255,255,.45);
  line-height: 1.5;
}

/* ── Footer ── */
.page-footer {
  margin: 20px 18px 0;
  padding: 14px;
  background: var(--border);
  border-radius: 10px;
  text-align: center;
}
.page-footer p {
  font-size: 11px;
  color: var(--faint);
  line-height: 1.7;
}

/* ── Responsive desktop ── */
@media (min-width: 640px) {
  body { background: #e2e8f0; }
  .page-wrap {
    max-width: 480px;
    margin: 30px auto;
    background: var(--bg);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.15);
  }
}
</style>
</head>
<body>
<div class="page-wrap">

  <!-- ═══ HERO ═══ -->
  <div class="hero">
    <div class="hero-brand">
      <div class="hero-brand-dot"></div>
      <span class="hero-brand-txt">FixSmart Helpdesk — Aset IT</span>
    </div>

    <div class="hero-no-inv"><?= clean2($a['no_inventaris']) ?></div>
    <div class="hero-nama"><?= clean2($a['nama_aset']) ?></div>

    <div class="hero-badges">
      <!-- Kondisi -->
      <span class="badge" style="background:<?= $kStyle['bg'] ?>;color:<?= $kStyle['fg'] ?>;">
        <?= $kStyle['ico'] ?> <?= clean2($a['kondisi']) ?>
      </span>
      <!-- Status Pakai -->
      <span class="badge" style="background:<?= $sStyle['bg'] ?>;color:<?= $sStyle['fg'] ?>;">
        <?= $sStyle['ico'] ?> <?= clean2($a['status_pakai'] ?? 'Terpakai') ?>
      </span>
      <!-- Kategori -->
      <?php if ($a['kategori']): ?>
      <span class="badge" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.15);">
        🏷 <?= clean2($a['kategori']) ?>
      </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═══ CARD FLOAT ═══ -->
  <div class="card-float">

    <!-- Lokasi & PJ highlight -->
    <?php if ($a['bagian_nama'] || $pj_nama !== '—'): ?>
    <div class="highlight-box" style="background:#f0fdf4;border:1px solid #bbf7d0;">
      <div class="hb-ico">📍</div>
      <div>
        <div class="hb-label" style="color:#065f46;">Lokasi</div>
        <div class="hb-val" style="color:#065f46;font-size:14px;"><?= $lokasi ?></div>
        <?php if ($a['bagian_lokasi']): ?>
        <div style="font-size:11px;color:#16a34a;margin-top:2px;">📌 <?= clean2($a['bagian_lokasi']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($pj_nama !== '—'): ?>
    <div class="highlight-box" style="background:#eff6ff;border:1px solid #bfdbfe;margin-top:10px;">
      <div class="hb-ico">👤</div>
      <div>
        <div class="hb-label" style="color:#1e40af;">Penanggung Jawab</div>
        <div class="hb-val" style="color:#1e40af;font-size:14px;"><?= clean2($pj_nama) ?></div>
        <?php if ($a['pj_divisi']): ?>
        <div style="font-size:11px;color:#3b82f6;margin-top:2px;">🏢 <?= clean2($a['pj_divisi']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- SPESIFIKASI TEKNIS -->
    <div class="section">
      <div class="section-title">🔧 Spesifikasi Teknis</div>

      <?php if ($a['merek'] || $a['model_aset']): ?>
      <div class="info-row">
        <div class="info-icon" style="background:#eff6ff;">💻</div>
        <div>
          <div class="info-label">Merek / Model</div>
          <div class="info-value">
            <?= clean2($a['merek'] ?: '—') ?>
            <?php if ($a['model_aset']): ?>
            <span style="color:var(--muted);font-weight:400;"> / <?= clean2($a['model_aset']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($a['serial_number']): ?>
      <div class="info-row">
        <div class="info-icon" style="background:#f8fafc;">🔢</div>
        <div>
          <div class="info-label">Serial Number</div>
          <div class="info-value mono"><?= clean2($a['serial_number']) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($a['kategori']): ?>
      <div class="info-row">
        <div class="info-icon" style="background:#fdf4ff;">🏷️</div>
        <div>
          <div class="info-label">Kategori</div>
          <div class="info-value"><?= clean2($a['kategori']) ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- INFORMASI PEMBELIAN -->
    <div class="section">
      <div class="section-title">🛒 Informasi Pembelian</div>

      <div class="info-row">
        <div class="info-icon" style="background:#f0fdf4;">📅</div>
        <div>
          <div class="info-label">Tanggal Beli</div>
          <div class="info-value"><?= $tgl_beli ?></div>
        </div>
      </div>

      <div class="info-row">
        <div class="info-icon" style="background:#f0fdf4;">💰</div>
        <div>
          <div class="info-label">Harga Beli</div>
          <div class="info-value" style="color:#16a34a;"><?= $harga_html ?></div>
        </div>
      </div>

      <div class="info-row">
        <div class="info-icon" style="background:#fff7ed;">🛡️</div>
        <div>
          <div class="info-label">Garansi Sampai</div>
          <div class="info-value"><?= $garansi_html ?></div>
        </div>
      </div>
    </div>

    <!-- KETERANGAN -->
    <?php if ($a['keterangan']): ?>
    <div class="section">
      <div class="section-title">📝 Keterangan</div>
      <div style="padding:12px 14px;background:#f8fafc;border-radius:10px;
                  border:1px solid var(--border);font-size:13px;color:var(--muted);
                  line-height:1.7;">
        <?= nl2br(clean2($a['keterangan'])) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- RIWAYAT INPUT -->
    <div class="section">
      <div class="section-title">📋 Riwayat Data</div>
      <div class="info-row">
        <div class="info-icon" style="background:#f8fafc;">🕐</div>
        <div>
          <div class="info-label">Pertama Diinput</div>
          <div class="info-value" style="font-size:13px;"><?= $tgl_input ?></div>
        </div>
      </div>
      <?php if ($a['updated_at']): ?>
      <div class="info-row">
        <div class="info-icon" style="background:#f8fafc;">🔄</div>
        <div>
          <div class="info-label">Terakhir Diperbarui</div>
          <div class="info-value" style="font-size:13px;"><?= $tgl_update ?></div>
        </div>
      </div>
      <?php endif; ?>
      <div class="info-row">
        <div class="info-icon" style="background:#f8fafc;">🆔</div>
        <div>
          <div class="info-label">ID Sistem</div>
          <div class="info-value mono">#<?= $a['id'] ?></div>
        </div>
      </div>
    </div>

    <!-- QR CODE -->
    <div class="qr-section">
      <img src="<?= $qr_src ?>" width="80" height="80" alt="QR Code halaman ini">
      <div class="qr-text">
        <div class="qr-title">QR Code Aset Ini</div>
        <div class="qr-sub">
          Scan QR ini untuk membuka halaman detail aset kapan saja dari perangkat apa pun.
        </div>
      </div>
    </div>

    <!-- FOOTER -->
    <div class="page-footer">
      <p>
        <strong style="color:var(--text);">FixSmart Helpdesk</strong><br>
        Sistem Manajemen Aset IT<br>
        Data diperbarui: <?= date('d M Y') ?>
      </p>
    </div>

  </div><!-- /card-float -->
</div><!-- /page-wrap -->
</body>
</html>