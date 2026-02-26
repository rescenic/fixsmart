<?php
// pages/setting_telegram.php — Pengaturan Notifikasi Telegram
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole('admin')) { setFlash('danger', 'Akses ditolak. Hanya Admin.'); redirect(APP_URL . '/dashboard.php'); }
$page_title  = 'Setting Telegram';
$active_menu = 'setting_telegram';

// ── HANDLE POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'save') {
        $fields = [
            'telegram_enabled'          => isset($_POST['telegram_enabled']) ? '1' : '0',
            'telegram_bot_token'        => trim($_POST['telegram_bot_token'] ?? ''),
            'telegram_chat_id'          => trim($_POST['telegram_chat_id']   ?? ''),
            'telegram_notif_tiket_baru' => isset($_POST['notif_tiket_baru']) ? '1' : '0',
            'telegram_notif_diproses'   => isset($_POST['notif_diproses'])   ? '1' : '0',
            'telegram_notif_selesai'    => isset($_POST['notif_selesai'])    ? '1' : '0',
            'telegram_notif_ditolak'    => isset($_POST['notif_ditolak'])    ? '1' : '0',
            'telegram_notif_komentar'   => isset($_POST['notif_komentar'])   ? '1' : '0',
        ];
        $stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        foreach ($fields as $k => $v) $stmt->execute([$k, $v]);

        setFlash('success', 'Pengaturan Telegram berhasil disimpan.');
        redirect(APP_URL . '/pages/setting_telegram.php');
    }

    elseif ($act === 'test') {
        // Simpan dulu setting terbaru sebelum test
        $fields = [
            'telegram_enabled'   => '1', // force aktif saat test
            'telegram_bot_token' => trim($_POST['telegram_bot_token'] ?? ''),
            'telegram_chat_id'   => trim($_POST['telegram_chat_id']   ?? ''),
        ];
        $stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        foreach ($fields as $k => $v) $stmt->execute([$k, $v]);

        $msg = "🔔 <b>FixSmart Helpdesk — Test Notifikasi</b>\n\n"
             . "✅ Koneksi Telegram berhasil!\n"
             . "📅 Waktu: " . date('d/m/Y H:i:s') . "\n"
             . "👤 Dikirim oleh: " . $_SESSION['user_nama'] . "\n\n"
             . "<i>Notifikasi sistem FixSmart akan dikirim ke grup/channel ini.</i>";

        $ok = sendTelegram($pdo, $msg);
        if ($ok) setFlash('success', '✅ Pesan test berhasil dikirim ke Telegram! Cek chat Anda.');
        else      setFlash('danger',  '❌ Gagal mengirim ke Telegram. Periksa kembali Bot Token dan Chat ID.');

        redirect(APP_URL . '/pages/setting_telegram.php');
    }
}

// ── LOAD SETTINGS ──
$cfg = getSettings($pdo);

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-paper-plane" style="color:#0088cc;"></i> &nbsp;Setting Notifikasi Telegram</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
    <span class="cur">Setting Telegram</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <div class="g2">

    <!-- ── FORM UTAMA ── -->
    <div>
      <form method="POST" id="form-tg">
        <input type="hidden" name="action" value="save" id="f-action">

        <!-- Koneksi Bot -->
        <div class="panel">
          <div class="panel-hd">
            <h5>
              <i class="fa fa-robot" style="color:#0088cc;"></i> &nbsp;Konfigurasi Bot Telegram
            </h5>
            <!-- Toggle aktif / nonaktif -->
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;font-weight:700;color:#555;">
              <div class="toggle-wrap" onclick="toggleSwitch(this)">
                <input type="checkbox" name="telegram_enabled" id="tg-enabled"
                       <?= ($cfg['telegram_enabled'] ?? '0') == '1' ? 'checked' : '' ?> style="display:none;">
                <div class="toggle-track" id="toggle-track">
                  <div class="toggle-thumb"></div>
                </div>
              </div>
              <span id="lbl-status"><?= ($cfg['telegram_enabled'] ?? '0') == '1' ? 'Aktif' : 'Nonaktif' ?></span>
            </label>
          </div>
          <div class="panel-bd">

            <!-- Status bar -->
            <div id="status-bar" style="padding:10px 13px;border-radius:4px;margin-bottom:16px;font-size:12px;display:flex;align-items:center;gap:8px;
              background:<?= ($cfg['telegram_enabled']??'0')=='1'?'#d1fae5':'#f3f4f6' ?>;
              color:<?= ($cfg['telegram_enabled']??'0')=='1'?'#065f46':'#6b7280' ?>;
              border:1px solid <?= ($cfg['telegram_enabled']??'0')=='1'?'#a7f3d0':'#e5e7eb' ?>;">
              <i class="fa <?= ($cfg['telegram_enabled']??'0')=='1'?'fa-check-circle':'fa-pause-circle' ?>"></i>
              <span id="status-text">
                <?= ($cfg['telegram_enabled']??'0')=='1'
                    ? 'Notifikasi Telegram <strong>aktif</strong>. Sistem akan mengirim pesan otomatis saat ada update tiket.'
                    : 'Notifikasi Telegram <strong>nonaktif</strong>. Aktifkan untuk mulai menerima notifikasi.' ?>
              </span>
            </div>

            <div class="form-group">
              <label>Bot Token <span class="req">*</span></label>
              <div style="position:relative;">
                <input type="password" name="telegram_bot_token" id="inp-token"
                       class="form-control" style="padding-right:40px;"
                       value="<?= clean($cfg['telegram_bot_token'] ?? '') ?>"
                       placeholder="Contoh: 123456789:AABBCCDDEEFFaabbccddeeff...">
                <button type="button" onclick="toggleVis('inp-token','eye-token')"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#aaa;">
                  <i class="fa fa-eye" id="eye-token"></i>
                </button>
              </div>
              <div class="form-hint">
                Dapatkan dari <strong>@BotFather</strong> di Telegram. Buat bot baru dengan perintah <code>/newbot</code>.
              </div>
            </div>

            <div class="form-group">
              <label>Chat ID <span class="req">*</span></label>
              <input type="text" name="telegram_chat_id" class="form-control"
                     value="<?= clean($cfg['telegram_chat_id'] ?? '') ?>"
                     placeholder="Contoh: -1001234567890 (grup) atau 123456789 (personal)">
              <div class="form-hint">
                Chat ID grup/channel (diawali <code>-100...</code>) atau Chat ID personal.
                Gunakan <strong>@userinfobot</strong> untuk mengetahui Chat ID Anda.
              </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <button type="submit" class="btn btn-primary">
                <i class="fa fa-save"></i> Simpan Pengaturan
              </button>
              <button type="button" class="btn btn-info" onclick="testKirim()">
                <i class="fa fa-paper-plane"></i> Kirim Pesan Test
              </button>
            </div>
          </div>
        </div>

        <!-- Pilihan Notifikasi -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-bell" style="color:#0088cc;"></i> &nbsp;Pilih Event yang Dikirim</h5>
          </div>
          <div class="panel-bd">
            <p style="font-size:12px;color:#aaa;margin-bottom:14px;">
              Centang event yang ingin mendapat notifikasi Telegram secara otomatis:
            </p>

            <?php
            $notif_items = [
              ['notif_tiket_baru', 'telegram_notif_tiket_baru', '📥', 'Tiket Baru Masuk',
               'Dikirim saat user membuat tiket baru. Berguna agar tim IT langsung tahu.'],
              ['notif_diproses',   'telegram_notif_diproses',   '⚙️', 'Tiket Mulai Diproses',
               'Dikirim saat teknisi mengambil dan memulai penanganan tiket.'],
              ['notif_selesai',    'telegram_notif_selesai',    '✅', 'Tiket Selesai',
               'Dikirim saat tiket ditandai selesai oleh teknisi.'],
              ['notif_ditolak',    'telegram_notif_ditolak',    '❌', 'Tiket Ditolak / Tidak Bisa',
               'Dikirim saat tiket ditolak atau tidak dapat ditangani.'],
              ['notif_komentar',   'telegram_notif_komentar',   '💬', 'Komentar Baru',
               'Dikirim saat ada komentar/diskusi baru di tiket.'],
            ];
            ?>

            <?php foreach ($notif_items as [$field, $key, $emoji, $judul, $desc]): ?>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #f5f5f5;">
              <label class="chk-toggle" style="position:relative;display:inline-block;width:38px;height:22px;flex-shrink:0;margin-top:2px;">
                <input type="checkbox" name="<?= $field ?>"
                       <?= ($cfg[$key] ?? '0') == '1' ? 'checked' : '' ?>>
                <span class="chk-slider"></span>
              </label>
              <div>
                <div style="font-size:12px;font-weight:700;color:#333;">
                  <?= $emoji ?> <?= $judul ?>
                </div>
                <div style="font-size:11px;color:#aaa;margin-top:2px;line-height:1.5;"><?= $desc ?></div>
              </div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:14px;">
              <button type="submit" class="btn btn-primary">
                <i class="fa fa-save"></i> Simpan Semua Pengaturan
              </button>
            </div>
          </div>
        </div>

      </form>
    </div>

    <!-- ── PANEL KANAN: PANDUAN ── -->
    <div>

      <!-- Status koneksi -->
      <div class="panel">
        <div class="panel-hd">
          <h5><i class="fa fa-signal" style="color:#0088cc;"></i> &nbsp;Status Koneksi</h5>
        </div>
        <div class="panel-bd" style="text-align:center;padding:20px;">
          <?php
          $has_token   = !empty($cfg['telegram_bot_token']);
          $has_chat_id = !empty($cfg['telegram_chat_id']);
          $is_enabled  = ($cfg['telegram_enabled'] ?? '0') == '1';
          $is_ready    = $has_token && $has_chat_id && $is_enabled;
          ?>
          <div style="width:60px;height:60px;border-radius:50%;margin:0 auto 12px;
               background:<?= $is_ready ? '#d1fae5' : ($has_token&&$has_chat_id ? '#fef3c7' : '#f3f4f6') ?>;
               display:flex;align-items:center;justify-content:center;">
            <i class="fa <?= $is_ready ? 'fa-check-circle' : ($has_token&&$has_chat_id ? 'fa-exclamation-circle' : 'fa-times-circle') ?>"
               style="font-size:28px;color:<?= $is_ready ? '#059669' : ($has_token&&$has_chat_id ? '#d97706' : '#9ca3af') ?>;"></i>
          </div>
          <div style="font-size:13px;font-weight:700;color:#333;">
            <?php if ($is_ready): ?>✅ Siap Mengirim
            <?php elseif ($has_token && $has_chat_id): ?>⚠️ Terkonfigurasi, Belum Aktif
            <?php else: ?>⚙️ Belum Dikonfigurasi<?php endif; ?>
          </div>
          <div style="font-size:11px;color:#aaa;margin-top:5px;">
            <?php if ($is_ready): ?>Bot dan Chat ID tersimpan. Notifikasi aktif.
            <?php elseif ($has_token && $has_chat_id): ?>Aktifkan toggle di atas untuk mulai.
            <?php else: ?>Isi Bot Token dan Chat ID untuk memulai.<?php endif; ?>
          </div>

          <?php if ($has_token && $has_chat_id): ?>
          <button type="button" class="btn btn-info btn-sm" style="margin-top:12px;" onclick="testKirim()">
            <i class="fa fa-paper-plane"></i> Test Sekarang
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Panduan Setup -->
      <div class="panel">
        <div class="panel-hd">
          <h5><i class="fa fa-book-open" style="color:#0088cc;"></i> &nbsp;Cara Setup Bot Telegram</h5>
        </div>
        <div class="panel-bd" style="padding:14px 15px;">

          <?php
          $steps = [
            ['1', '#0088cc', 'Buat Bot via @BotFather',
             'Buka Telegram → cari <strong>@BotFather</strong> → kirim <code>/newbot</code> → ikuti instruksi → salin <strong>Bot Token</strong>.'],
            ['2', '#0088cc', 'Tambahkan Bot ke Grup/Channel',
             'Buat grup atau gunakan grup yang ada → tambahkan bot sebagai <strong>anggota</strong> (untuk channel, jadikan bot sebagai admin).'],
            ['3', '#0088cc', 'Dapatkan Chat ID',
             'Cara cepat: kirim pesan di grup → buka <code>https://api.telegram.org/bot<b>TOKEN</b>/getUpdates</code> → cari nilai <strong>"chat":{"id":</strong>...}. Atau tambah <strong>@userinfobot</strong> ke grup dan ketik <code>/start</code>.'],
            ['4', '#27ae60', 'Isi & Simpan',
             'Masukkan Bot Token dan Chat ID di form ini → klik <strong>Simpan</strong> → klik <strong>Kirim Test</strong> untuk verifikasi.'],
          ];
          foreach ($steps as [$no, $col, $judul, $desc]):
          ?>
          <div style="display:flex;gap:10px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #f5f5f5;">
            <div style="width:26px;height:26px;border-radius:50%;background:<?= $col ?>;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <?= $no ?>
            </div>
            <div>
              <div style="font-size:12px;font-weight:700;color:#333;margin-bottom:3px;"><?= $judul ?></div>
              <div style="font-size:11px;color:#888;line-height:1.6;"><?= $desc ?></div>
            </div>
          </div>
          <?php endforeach; ?>

          <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:4px;padding:10px 12px;margin-top:2px;">
            <p style="font-size:11px;color:#0369a1;line-height:1.7;margin:0;">
              <i class="fa fa-lightbulb" style="color:#0284c7;"></i>
              <strong>Tips:</strong> Chat ID grup biasanya diawali <code>-100</code>.
              Jika notifikasi tidak masuk, pastikan bot sudah di-invite ke grup dan memiliki izin kirim pesan.
            </p>
          </div>
        </div>
      </div>

      <!-- Format Pesan -->
      <div class="panel">
        <div class="panel-hd">
          <h5><i class="fa fa-comment-dots" style="color:#0088cc;"></i> &nbsp;Contoh Format Pesan</h5>
        </div>
        <div class="panel-bd" style="padding:12px 15px;">
          <div style="background:#1e2a38;border-radius:6px;padding:13px 15px;font-family:monospace;font-size:11px;color:#e2e8f0;line-height:1.8;">
            🔔 <span style="color:#63b3ed;font-weight:700;">Tiket Baru Masuk</span><br>
            <br>
            📋 <span style="color:#fbd38d;">No:</span> TKT-00042<br>
            📌 <span style="color:#fbd38d;">Judul:</span> PC tidak bisa menyala<br>
            🏷️ <span style="color:#fbd38d;">Kategori:</span> Hardware<br>
            🔴 <span style="color:#fbd38d;">Prioritas:</span> Tinggi<br>
            📍 <span style="color:#fbd38d;">Lokasi:</span> Lt.2, R.Keuangan<br>
            👤 <span style="color:#fbd38d;">Pemohon:</span> Anisa Rahma<br>
            🕐 <span style="color:#fbd38d;">Waktu:</span> 25/02/2026 09:15
          </div>
          <p style="font-size:11px;color:#aaa;margin-top:8px;">
            <i class="fa fa-info-circle"></i> Format pesan otomatis mengikuti data tiket yang tersimpan.
          </p>
        </div>
      </div>

    </div><!-- /.col-kanan -->
  </div><!-- /.g2 -->
</div><!-- /.content -->

<!-- Toggle CSS -->
<style>
/* Toggle switch utama */
.toggle-wrap { cursor:pointer; }
.toggle-track {
  width:42px;height:22px;background:#ddd;border-radius:11px;
  position:relative;transition:background .25s;
}
.toggle-track.on { background:#26B99A; }
.toggle-thumb {
  width:18px;height:18px;background:#fff;border-radius:50%;
  position:absolute;top:2px;left:2px;transition:left .25s;
  box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.toggle-track.on .toggle-thumb { left:22px; }

/* Checkbox slider untuk notif items */
.chk-toggle input { opacity:0;width:0;height:0;position:absolute; }
.chk-slider {
  position:absolute;inset:0;background:#ddd;border-radius:22px;
  cursor:pointer;transition:.25s;
}
.chk-slider:before {
  content:'';position:absolute;width:16px;height:16px;background:#fff;
  border-radius:50%;left:3px;top:3px;transition:.25s;
  box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.chk-toggle input:checked + .chk-slider { background:#0088cc; }
.chk-toggle input:checked + .chk-slider:before { transform:translateX(16px); }
</style>

<script>
// Toggle switch aktif/nonaktif
function toggleSwitch(wrap) {
  const cb    = wrap.querySelector('input[type=checkbox]');
  const track = document.getElementById('toggle-track');
  const lbl   = document.getElementById('lbl-status');
  const bar   = document.getElementById('status-bar');
  const stxt  = document.getElementById('status-text');
  const ic    = bar.querySelector('i');

  cb.checked = !cb.checked;

  if (cb.checked) {
    track.classList.add('on');
    lbl.textContent = 'Aktif';
    bar.style.background='#d1fae5'; bar.style.color='#065f46'; bar.style.borderColor='#a7f3d0';
    ic.className='fa fa-check-circle';
    stxt.innerHTML='Notifikasi Telegram <strong>aktif</strong>. Sistem akan mengirim pesan otomatis saat ada update tiket.';
  } else {
    track.classList.remove('on');
    lbl.textContent = 'Nonaktif';
    bar.style.background='#f3f4f6'; bar.style.color='#6b7280'; bar.style.borderColor='#e5e7eb';
    ic.className='fa fa-pause-circle';
    stxt.innerHTML='Notifikasi Telegram <strong>nonaktif</strong>. Aktifkan untuk mulai menerima notifikasi.';
  }
}

// Init toggle state dari checkbox
document.addEventListener('DOMContentLoaded', function() {
  const cb = document.getElementById('tg-enabled');
  const track = document.getElementById('toggle-track');
  if (cb && cb.checked) track.classList.add('on');
});

// Show/hide password token
function toggleVis(inputId, iconId) {
  const inp = document.getElementById(inputId);
  const ic  = document.getElementById(iconId);
  if (inp.type === 'password') { inp.type='text';  ic.className='fa fa-eye-slash'; }
  else                         { inp.type='password'; ic.className='fa fa-eye'; }
}

// Kirim test
function testKirim() {
  document.getElementById('f-action').value = 'test';
  document.getElementById('form-tg').submit();
}
</script>

<?php include '../includes/footer.php'; ?>
