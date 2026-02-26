<?php
session_start();
require_once '../config.php';
requireLogin();
$page_title = 'Buat Tiket Baru';
$active_menu = 'buat_tiket';

$kategori_list = $pdo->query("SELECT * FROM kategori ORDER BY nama")->fetchAll();

// Ambil lokasi dari master bagian
$lokasi_list = $pdo->query("
    SELECT nama, kode, lokasi FROM bagian
    WHERE status='aktif' AND lokasi IS NOT NULL AND lokasi != ''
    ORDER BY urutan ASC, nama ASC
")->fetchAll();

// Auto-select lokasi sesuai divisi user yang login
$user_lokasi_default = '';
if (!empty($_SESSION['user_divisi'])) {
    $stLok = $pdo->prepare("SELECT lokasi FROM bagian WHERE nama=? AND lokasi IS NOT NULL LIMIT 1");
    $stLok->execute([$_SESSION['user_divisi']]);
    $user_lokasi_default = $stLok->fetchColumn() ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul  = trim($_POST['judul']        ?? '');
    $kat_id = (int)($_POST['kategori_id'] ?? 0);
    $desc   = trim($_POST['deskripsi']    ?? '');
    $prio   = $_POST['prioritas']         ?? 'Sedang';

    $lokasi_pilih  = trim($_POST['lokasi_pilih']  ?? '');
    $lokasi_manual = trim($_POST['lokasi_manual'] ?? '');
    $lokasi = ($lokasi_pilih === '__manual__') ? $lokasi_manual : $lokasi_pilih;

    if (!$judul || !$kat_id || !$desc) {
        setFlash('danger', 'Harap isi semua field yang wajib diisi (*).');
    } else {
        $nomor = generateNomor($pdo);
        $pdo->prepare("INSERT INTO tiket (nomor,judul,deskripsi,kategori_id,prioritas,user_id,lokasi,waktu_submit)
                       VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([$nomor, $judul, $desc, $kat_id, $prio, $_SESSION['user_id'], $lokasi]);
        $new_id = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,NULL,'menunggu',?)")
            ->execute([$new_id, $_SESSION['user_id'], 'Tiket dibuat oleh '.$_SESSION['user_nama']]);

        // ── NOTIFIKASI TELEGRAM ──────────────────────────────────────────────
        $cfg_tg = getSettings($pdo);
        if (
            ($cfg_tg['telegram_enabled']          ?? '0') === '1' &&
            ($cfg_tg['telegram_notif_tiket_baru'] ?? '0') === '1'
        ) {
            // Ambil nama kategori dari list yang sudah di-query
            $kat_nama = '-';
            foreach ($kategori_list as $k) {
                if ((int)$k['id'] === $kat_id) {
                    $kat_nama = $k['nama'];
                    break;
                }
            }

            // Emoji prioritas
            $emoji_prio = ['Rendah' => '🟢', 'Sedang' => '🟡', 'Tinggi' => '🔴'];
            $ep = $emoji_prio[$prio] ?? '⚪';

            $msg = "🔔 <b>Tiket Baru Masuk — FixSmart Helpdesk</b>\n\n"
                 . "📋 <b>No Tiket :</b> {$nomor}\n"
                 . "📌 <b>Judul    :</b> " . htmlspecialchars($judul, ENT_QUOTES) . "\n"
                 . "🏷️ <b>Kategori :</b> " . htmlspecialchars($kat_nama, ENT_QUOTES) . "\n"
                 . "{$ep} <b>Prioritas:</b> {$prio}\n"
                 . "📍 <b>Lokasi   :</b> " . htmlspecialchars($lokasi ?: '-', ENT_QUOTES) . "\n"
                 . "👤 <b>Pemohon  :</b> " . htmlspecialchars($_SESSION['user_nama'], ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n\n"
                 . "💬 <b>Deskripsi:</b>\n"
                 . htmlspecialchars(mb_substr($desc, 0, 200, 'UTF-8') . (mb_strlen($desc, 'UTF-8') > 200 ? '...' : ''), ENT_QUOTES) . "\n\n"
                 . "<i>Silakan cek dashboard untuk segera menangani tiket ini.</i>";

            sendTelegram($pdo, $msg);
        }
        // ── END NOTIFIKASI TELEGRAM ──────────────────────────────────────────

        setFlash('success', "Tiket <strong>$nomor</strong> berhasil dibuat. Tim IT akan segera menghubungi Anda.");
        redirect(APP_URL . '/pages/detail_tiket.php?id=' . $new_id);
    }
}

$cur_pilih  = $_POST['lokasi_pilih']  ?? $user_lokasi_default;
$cur_manual = $_POST['lokasi_manual'] ?? '';

include '../includes/header.php';
?>
<div class="page-header">
  <h4><i class="fa fa-plus-circle text-primary"></i> &nbsp;Buat Tiket Baru</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Beranda</a><span class="sep">/</span>
    <span class="cur">Buat Tiket</span>
  </div>
</div>
<div class="content">
  <?= showFlash() ?>
  <div class="g3">
    <div class="panel">
      <div class="panel-hd"><h5><i class="fa fa-edit text-primary"></i> &nbsp;Form Pengajuan Tiket</h5></div>
      <div class="panel-bd">
        <form method="POST">
          <div class="form-group">
            <label>Judul Keluhan <span class="req">*</span></label>
            <input type="text" name="judul" class="form-control"
                   placeholder="Contoh: PC tidak bisa menyala, Internet lambat, dll..."
                   value="<?= clean($_POST['judul'] ?? '') ?>" required>
            <div class="form-hint">Tuliskan judul yang singkat dan jelas.</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Kategori <span class="req">*</span></label>
              <select name="kategori_id" class="form-control" required>
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($kategori_list as $k): ?>
                <option value="<?= $k['id'] ?>" <?= ($_POST['kategori_id'] ?? '') == $k['id'] ? 'selected' : '' ?>>
                  <?= clean($k['nama']) ?> — Target <?= $k['sla_jam'] ?> jam
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Tingkat Prioritas</label>
              <select name="prioritas" class="form-control">
                <option value="Rendah" <?= ($_POST['prioritas'] ?? '') === 'Rendah' ? 'selected' : '' ?>>🟢 Rendah — Tidak mendesak</option>
                <option value="Sedang" <?= ($_POST['prioritas'] ?? 'Sedang') === 'Sedang' ? 'selected' : '' ?>>🟡 Sedang — Perlu segera</option>
                <option value="Tinggi" <?= ($_POST['prioritas'] ?? '') === 'Tinggi' ? 'selected' : '' ?>>🔴 Tinggi — Sangat mendesak</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Deskripsi Masalah <span class="req">*</span></label>
            <textarea name="deskripsi" class="form-control" style="min-height:110px;"
                      placeholder="Jelaskan masalah secara detail:&#10;- Sejak kapan terjadi?&#10;- Apa yang sudah dicoba?&#10;- Pesan error yang muncul (jika ada)"
                      required><?= clean($_POST['deskripsi'] ?? '') ?></textarea>
          </div>

          <!-- LOKASI DROPDOWN -->
          <div class="form-group">
            <label>Lokasi / Ruangan <span class="req">*</span></label>
            <select name="lokasi_pilih" id="sel-lokasi" class="form-control"
                    onchange="toggleManual(this.value)" required>
              <option value="">-- Pilih Ruangan / Bagian --</option>
              <?php foreach ($lokasi_list as $l): ?>
              <option value="<?= clean($l['lokasi']) ?>"
                      <?= $cur_pilih === $l['lokasi'] ? 'selected' : '' ?>>
                <?= clean($l['nama']) ?><?= $l['kode'] ? ' ('.$l['kode'].')' : '' ?>
                &nbsp;—&nbsp;<?= clean($l['lokasi']) ?>
              </option>
              <?php endforeach; ?>
              <option value="__manual__" <?= $cur_pilih === '__manual__' ? 'selected' : '' ?>>
                ✏️ Lainnya (ketik manual)
              </option>
            </select>

            <!-- Muncul hanya saat pilih "Lainnya" -->
            <div id="box-manual" style="margin-top:8px;<?= $cur_pilih === '__manual__' ? '' : 'display:none' ?>">
              <input type="text" name="lokasi_manual" id="inp-manual"
                     class="form-control"
                     placeholder="Contoh: Gudang Lt.2, Lobby Utama, R.Server Cabang..."
                     value="<?= clean($cur_manual) ?>"
                     <?= $cur_pilih === '__manual__' ? 'required' : '' ?>>
              <div class="form-hint"><i class="fa fa-info-circle"></i> Ketik lokasi spesifik jika tidak ada di daftar.</div>
            </div>
          </div>

          <div style="display:flex;gap:8px;margin-top:4px;">
            <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Kirim Tiket</button>
            <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-default">Batal</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Info Panel -->
    <div>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-info-circle text-primary"></i> &nbsp;Alur Penanganan</h5></div>
        <div class="panel-bd">
          <?php
          $steps = [
            ['fa-paper-plane','var(--primary)','Anda Kirim Tiket','Tiket masuk ke antrian IT'],
            ['fa-clock','var(--yellow)','Menunggu','Tim IT akan segera memproses'],
            ['fa-cogs','var(--blue)','Diproses','Teknisi sedang menangani'],
            ['fa-check-circle','var(--green)','Selesai','Masalah berhasil diselesaikan'],
          ];
          foreach ($steps as $i=>[$ic,$cl,$t,$d]):
          ?>
          <div style="display:flex;gap:10px;margin-bottom:<?= $i<3?'14px':'0' ?>;<?= $i<3?'padding-bottom:14px;border-bottom:1px solid #f5f5f5;':'' ?>">
            <div style="width:30px;height:30px;border-radius:50%;background:<?= $cl ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa <?= $ic ?>" style="color:#fff;font-size:12px;"></i>
            </div>
            <div>
              <div style="font-size:12px;font-weight:700;color:#333;"><?= $t ?></div>
              <div style="font-size:11px;color:#aaa;margin-top:1px;"><?= $d ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <hr class="divider">
          <p style="font-size:11px;color:#aaa;line-height:1.7;">
            <i class="fa fa-ban" style="color:var(--red);"></i> <strong>Ditolak:</strong> Permintaan tidak sesuai kebijakan.<br>
            <i class="fa fa-ban" style="color:var(--purple);"></i> <strong>Tidak Bisa:</strong> Di luar kemampuan IT internal.
          </p>
        </div>
      </div>

      <!-- Referensi Lokasi -->
      <?php if (!empty($lokasi_list)): ?>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-map-marker-alt text-primary"></i> &nbsp;Lokasi per Bagian</h5></div>
        <div class="panel-bd" style="padding:10px 14px;">
          <?php foreach ($lokasi_list as $l): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;padding-bottom:7px;border-bottom:1px solid #f5f5f5;font-size:12px;">
            <span style="font-weight:600;">
              <?= clean($l['nama']) ?>
              <?php if ($l['kode']): ?>
              <span style="font-size:10px;background:#f0f0f0;padding:1px 5px;border-radius:3px;color:#888;margin-left:3px;"><?= clean($l['kode']) ?></span>
              <?php endif; ?>
            </span>
            <span style="color:#aaa;font-size:11px;">
              <i class="fa fa-map-marker-alt" style="color:var(--primary);"></i> <?= clean($l['lokasi']) ?>
            </span>
          </div>
          <?php endforeach; ?>
          <p style="font-size:11px;color:#bbb;margin-top:2px;">
            <i class="fa fa-info-circle"></i> Pilih "Lainnya" jika lokasi tidak ada di daftar.
          </p>
        </div>
      </div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-tags text-primary"></i> &nbsp;Target SLA per Kategori</h5></div>
        <div class="panel-bd" style="padding:10px 14px;">
          <?php foreach ($kategori_list as $k): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:12px;">
            <span><i class="fa <?= clean($k['icon']) ?>" style="color:var(--primary);width:16px;"></i> <?= clean($k['nama']) ?></span>
            <span style="color:#aaa;"><?= $k['sla_jam'] ?> jam</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleManual(val) {
  const box = document.getElementById('box-manual');
  const inp = document.getElementById('inp-manual');
  if (val === '__manual__') {
    box.style.display = 'block';
    inp.required = true;
    inp.focus();
  } else {
    box.style.display = 'none';
    inp.required = false;
    inp.value = '';
  }
}
document.addEventListener('DOMContentLoaded', function() {
  const sel = document.getElementById('sel-lokasi');
  if (sel.value) toggleManual(sel.value);
});
</script>

<?php include '../includes/footer.php'; ?>