<?php
// pages/setting_telegram.php — Notifikasi Telegram IT & IPSRS
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole('admin')) { setFlash('danger', 'Akses ditolak. Hanya Admin.'); redirect(APP_URL . '/dashboard.php'); }
$page_title  = 'Setting Telegram';
$active_menu = 'setting_telegram';

// ── HANDLE POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $tab = in_array($_POST['tab'] ?? '', ['it','ipsrs']) ? $_POST['tab'] : 'it';

    if ($act === 'save') {
        $prefix = $tab === 'ipsrs' ? 'ipsrs_' : '';
        $fields = [
            $prefix.'telegram_enabled'          => isset($_POST['telegram_enabled']) ? '1' : '0',
            $prefix.'telegram_bot_token'        => trim($_POST['telegram_bot_token'] ?? ''),
            $prefix.'telegram_chat_id'          => trim($_POST['telegram_chat_id']   ?? ''),
            $prefix.'telegram_notif_tiket_baru' => isset($_POST['notif_tiket_baru']) ? '1' : '0',
            $prefix.'telegram_notif_diproses'   => isset($_POST['notif_diproses'])   ? '1' : '0',
            $prefix.'telegram_notif_selesai'    => isset($_POST['notif_selesai'])    ? '1' : '0',
            $prefix.'telegram_notif_ditolak'    => isset($_POST['notif_ditolak'])    ? '1' : '0',
            $prefix.'telegram_notif_komentar'   => isset($_POST['notif_komentar'])   ? '1' : '0',
        ];
        $stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        foreach ($fields as $k => $v) $stmt->execute([$k, $v]);
        $label = $tab === 'ipsrs' ? 'IPSRS' : 'IT';
        setFlash('success', "Pengaturan Telegram {$label} berhasil disimpan.");
        redirect(APP_URL . '/pages/setting_telegram.php?tab=' . $tab);
    }

    elseif ($act === 'test') {
        $prefix = $tab === 'ipsrs' ? 'ipsrs_' : '';
        $label  = $tab === 'ipsrs' ? 'IPSRS' : 'IT';
        // Simpan token & chat_id dulu sebelum test
        $stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $stmt->execute([$prefix.'telegram_enabled',   '1']);
        $stmt->execute([$prefix.'telegram_bot_token', trim($_POST['telegram_bot_token'] ?? '')]);
        $stmt->execute([$prefix.'telegram_chat_id',   trim($_POST['telegram_chat_id']   ?? '')]);

        $msg = "🔔 <b>FixSmart Helpdesk — Test Notifikasi {$label}</b>\n\n"
             . "✅ Koneksi Telegram berhasil!\n"
             . "📅 Waktu: " . date('d/m/Y H:i:s') . "\n"
             . "👤 Dikirim oleh: " . ($_SESSION['user_nama'] ?? 'Admin') . "\n"
             . "🏷️ Saluran: <b>{$label}</b>\n\n"
             . "<i>Notifikasi sistem FixSmart akan dikirim ke grup/channel ini.</i>";

        // Kirim langsung pakai token dari POST (tidak perlu reload settings)
        $token   = trim($_POST['telegram_bot_token'] ?? '');
        $chat_id = trim($_POST['telegram_chat_id']   ?? '');
        $ok = false;
        if ($token && $chat_id) {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $res = @file_get_contents($url, false, stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query(['chat_id'=>$chat_id,'text'=>$msg,'parse_mode'=>'HTML']),
                'timeout' => 8,
            ]]));
            $ok = ($res !== false);
        }
        if ($ok) setFlash('success', "✅ Pesan test {$label} berhasil dikirim ke Telegram!");
        else      setFlash('danger',  "❌ Gagal mengirim ke Telegram {$label}. Periksa Bot Token dan Chat ID.");
        redirect(APP_URL . '/pages/setting_telegram.php?tab=' . $tab);
    }
}

// ── LOAD SETTINGS ─────────────────────────────────────────────────────────────
$cfg = getSettings($pdo);
// Tab aktif dari URL — default 'it'
$tab = in_array($_GET['tab'] ?? '', ['it','ipsrs']) ? $_GET['tab'] : 'it';

// Hanya render satu tab sesuai $tab (tidak render dua-duanya sekaligus)
$prefix = $tab === 'ipsrs' ? 'ipsrs_' : '';
$color  = $tab === 'ipsrs' ? '#f97316' : '#0088cc';
$label  = $tab === 'ipsrs' ? 'IPSRS'   : 'IT';

$enabled  = ($cfg[$prefix.'telegram_enabled'] ?? '0') == '1';
$token    = $cfg[$prefix.'telegram_bot_token'] ?? '';
$chat_id  = $cfg[$prefix.'telegram_chat_id']   ?? '';
$has_both = $token && $chat_id;
$is_ready = $enabled && $has_both;

$it_on    = ($cfg['telegram_enabled']       ?? '0') == '1';
$ipsrs_on = ($cfg['ipsrs_telegram_enabled'] ?? '0') == '1';

$notif_items = [
    ['notif_tiket_baru', $prefix.'telegram_notif_tiket_baru', 'Tiket Baru Masuk',        'Dikirim saat user membuat tiket baru.'],
    ['notif_diproses',   $prefix.'telegram_notif_diproses',   'Tiket Mulai Diproses',    'Dikirim saat teknisi mengambil tiket.'],
    ['notif_selesai',    $prefix.'telegram_notif_selesai',    'Tiket Selesai',            'Dikirim saat tiket ditandai selesai.'],
    ['notif_ditolak',    $prefix.'telegram_notif_ditolak',    'Tiket Ditolak/Tidak Bisa','Dikirim saat tiket ditolak.'],
    ['notif_komentar',   $prefix.'telegram_notif_komentar',   'Komentar Baru',            'Dikirim saat ada komentar baru di tiket.'],
];

include '../includes/header.php';
?>

<style>
.tg-tabs-wrap {
  display: flex;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 18px;
}
.tg-tab-link {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 13px 26px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  color: #94a3b8;
  border-bottom: 3px solid transparent;
  transition: all .18s;
  white-space: nowrap;
}
.tg-tab-link:hover  { color: #334155; background: #f8fafc; text-decoration: none; }
.tg-tab-link.active { color: #0f172a; border-bottom-color: #00e5b0; background: #f0fdf4; }
.tg-dot { width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0; }
.tg-badge { font-size:10px;padding:2px 9px;border-radius:20px;font-weight:700; }
/* Toggle switch */
.toggle-wrap  { cursor:pointer; }
.toggle-track { width:42px;height:22px;background:#ddd;border-radius:11px;position:relative;transition:background .25s; }
.toggle-track.on { background:#26B99A; }
.toggle-thumb { width:18px;height:18px;background:#fff;border-radius:50%;position:absolute;top:2px;left:2px;transition:left .25s;box-shadow:0 1px 3px rgba(0,0,0,.2); }
.toggle-track.on .toggle-thumb { left:22px; }
/* Checkbox slider */
.chk-toggle input { opacity:0;width:0;height:0;position:absolute; }
.chk-slider { position:absolute;inset:0;background:#ddd;border-radius:22px;cursor:pointer;transition:.25s; }
.chk-slider:before { content:'';position:absolute;width:16px;height:16px;background:#fff;border-radius:50%;left:3px;top:3px;transition:.25s;box-shadow:0 1px 3px rgba(0,0,0,.2); }
.chk-toggle input:checked + .chk-slider { background:#0088cc; }
.chk-toggle input:checked + .chk-slider:before { transform:translateX(16px); }
</style>

<div class="page-header">
  <h4><i class="fa fa-paper-plane" style="color:#0088cc;"></i> &nbsp;Setting Notifikasi Telegram</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
    <span class="cur">Setting Telegram</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- ══════════════════════════════════════════════════════
       TAB NAVIGATION — pakai <a href> bukan button/JS
       Klik tab = full page navigation ke ?tab=it atau ?tab=ipsrs
       100% tidak bisa kena masalah form submit
       ══════════════════════════════════════════════════════ -->
  <div class="tg-tabs-wrap">

    <a href="<?= APP_URL ?>/pages/setting_telegram.php?tab=it"
       class="tg-tab-link <?= $tab === 'it' ? 'active' : '' ?>">
      <span class="tg-dot" style="background:<?= $it_on?'#10b981':'#d1d5db' ?>;box-shadow:<?= $it_on?'0 0 5px #10b981':'none' ?>;"></span>
      <i class="fa fa-desktop" style="color:#0088cc;"></i>
      Telegram IT
      <span class="tg-badge" style="background:<?= $it_on?'#d1fae5':'#f3f4f6' ?>;color:<?= $it_on?'#065f46':'#6b7280' ?>;">
        <?= $it_on ? 'Aktif' : 'Nonaktif' ?>
      </span>
    </a>

    <a href="<?= APP_URL ?>/pages/setting_telegram.php?tab=ipsrs"
       class="tg-tab-link <?= $tab === 'ipsrs' ? 'active' : '' ?>">
      <span class="tg-dot" style="background:<?= $ipsrs_on?'#10b981':'#d1d5db' ?>;box-shadow:<?= $ipsrs_on?'0 0 5px #10b981':'none' ?>;"></span>
      <i class="fa fa-toolbox" style="color:#f97316;"></i>
      Telegram IPSRS
      <span class="tg-badge" style="background:<?= $ipsrs_on?'#d1fae5':'#f3f4f6' ?>;color:<?= $ipsrs_on?'#065f46':'#6b7280' ?>;">
        <?= $ipsrs_on ? 'Aktif' : 'Nonaktif' ?>
      </span>
    </a>

  </div>

  <!-- ══════════════════════════════════════════════════════
       KONTEN — hanya render tab yang aktif ($tab)
       ══════════════════════════════════════════════════════ -->
  <form method="POST">
    <input type="hidden" name="tab" value="<?= $tab ?>">

    <div style="display:grid;grid-template-columns:1fr 340px;gap:18px;">

      <!-- ── KIRI ── -->
      <div>

        <!-- Konfigurasi Bot -->
        <div class="panel">
          <div class="panel-hd">
            <h5>
              <i class="fa fa-robot" style="color:<?= $color ?>;"></i>
              &nbsp;Konfigurasi Bot — <span style="color:<?= $color ?>;"><?= $label ?></span>
            </h5>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;font-weight:700;color:#555;">
              <div class="toggle-wrap" onclick="toggleSwitch(this)">
                <input type="checkbox" name="telegram_enabled" id="tg-enabled"
                       <?= $enabled ? 'checked' : '' ?> style="display:none;">
                <div class="toggle-track <?= $enabled ? 'on' : '' ?>" id="tg-track">
                  <div class="toggle-thumb"></div>
                </div>
              </div>
              <span id="tg-lbl"><?= $enabled ? 'Aktif' : 'Nonaktif' ?></span>
            </label>
          </div>
          <div class="panel-bd">

            <div id="tg-sbar" style="padding:10px 13px;border-radius:4px;margin-bottom:16px;font-size:12px;display:flex;align-items:center;gap:8px;
                 background:<?= $is_ready?'#d1fae5':'#f3f4f6' ?>;
                 color:<?= $is_ready?'#065f46':'#6b7280' ?>;
                 border:1px solid <?= $is_ready?'#a7f3d0':'#e5e7eb' ?>;">
              <i class="fa <?= $is_ready?'fa-check-circle':'fa-pause-circle' ?>" id="tg-sbar-ic"></i>
              <span id="tg-stxt">
                <?= $is_ready ? "Notifikasi <strong>{$label}</strong> aktif."
                              : "Notifikasi <strong>{$label}</strong> nonaktif." ?>
              </span>
            </div>

            <div class="form-group">
              <label>Bot Token <span class="req">*</span></label>
              <div style="position:relative;">
                <input type="password" name="telegram_bot_token" id="inp-token"
                       class="form-control" style="padding-right:40px;"
                       value="<?= htmlspecialchars($token) ?>"
                       placeholder="123456789:AABBCCDDeeff...">
                <button type="button" onclick="toggleVis()"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#aaa;">
                  <i class="fa fa-eye" id="eye-ic"></i>
                </button>
              </div>
              <div class="form-hint">Dapatkan dari <strong>@BotFather</strong> → <code>/newbot</code></div>
            </div>

            <div class="form-group">
              <label>Chat ID <span class="req">*</span></label>
              <input type="text" name="telegram_chat_id" class="form-control"
                     value="<?= htmlspecialchars($chat_id) ?>"
                     placeholder="-1001234567890 (grup) atau 123456789 (personal)">
              <div class="form-hint">Gunakan <strong>@userinfobot</strong> untuk mengetahui Chat ID.</div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <button type="submit" name="action" value="save" class="btn btn-primary">
                <i class="fa fa-save"></i> Simpan
              </button>
              <button type="submit" name="action" value="test" class="btn btn-info">
                <i class="fa fa-paper-plane"></i> Kirim Test
              </button>
            </div>
          </div>
        </div>

        <!-- Event Notifikasi -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-bell" style="color:<?= $color ?>;"></i> &nbsp;Event Notifikasi — <?= $label ?></h5>
          </div>
          <div class="panel-bd">
            <p style="font-size:12px;color:#aaa;margin-bottom:14px;">
              Pilih event yang akan mengirim notifikasi ke grup <strong><?= $label ?></strong>:
            </p>
            <?php foreach ($notif_items as [$field, $key, $judul, $desc]): ?>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #f5f5f5;">
              <label class="chk-toggle" style="position:relative;display:inline-block;width:38px;height:22px;flex-shrink:0;margin-top:2px;">
                <input type="checkbox" name="<?= $field ?>" <?= ($cfg[$key]??'0')=='1'?'checked':'' ?>>
                <span class="chk-slider"></span>
              </label>
              <div>
                <div style="font-size:12px;font-weight:700;color:#333;"><?= $judul ?></div>
                <div style="font-size:11px;color:#aaa;margin-top:2px;line-height:1.5;"><?= $desc ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:14px;">
              <button type="submit" name="action" value="save" class="btn btn-primary">
                <i class="fa fa-save"></i> Simpan Semua
              </button>
            </div>
          </div>
        </div>

      </div><!-- /kiri -->

      <!-- ── KANAN ── -->
      <div>

        <!-- Status Koneksi -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-signal" style="color:<?= $color ?>;"></i> &nbsp;Status — <?= $label ?></h5>
          </div>
          <div class="panel-bd" style="text-align:center;padding:18px;">
            <div style="width:56px;height:56px;border-radius:50%;margin:0 auto 10px;
                 background:<?= $is_ready?'#d1fae5':($has_both?'#fef3c7':'#f3f4f6') ?>;
                 display:flex;align-items:center;justify-content:center;">
              <i class="fa <?= $is_ready?'fa-check-circle':($has_both?'fa-exclamation-circle':'fa-times-circle') ?>"
                 style="font-size:26px;color:<?= $is_ready?'#059669':($has_both?'#d97706':'#9ca3af') ?>;"></i>
            </div>
            <div style="font-size:13px;font-weight:700;color:#333;margin-bottom:4px;">
              <?php if ($is_ready): ?>Siap Mengirim
              <?php elseif ($has_both): ?>Terkonfigurasi, Belum Aktif
              <?php else: ?>Belum Dikonfigurasi<?php endif; ?>
            </div>
            <div style="font-size:11px;color:#aaa;">
              <?php if ($is_ready): ?>Bot aktif. Notifikasi <?= $label ?> berjalan.
              <?php elseif ($has_both): ?>Aktifkan toggle di atas untuk mulai.
              <?php else: ?>Isi Bot Token &amp; Chat ID terlebih dahulu.<?php endif; ?>
            </div>
            <?php if ($has_both): ?>
            <button type="submit" name="action" value="test" class="btn btn-info btn-sm" style="margin-top:12px;">
              <i class="fa fa-paper-plane"></i> Test Sekarang
            </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Panduan -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-book-open" style="color:<?= $color ?>;"></i> &nbsp;Cara Setup</h5>
          </div>
          <div class="panel-bd" style="padding:12px 14px;">
            <?php foreach ([
              ['1', 'Buat Bot via @BotFather',   'Buka Telegram → <strong>@BotFather</strong> → <code>/newbot</code> → salin <strong>Bot Token</strong>.'],
              ['2', 'Tambah Bot ke Grup '.$label, 'Buat grup khusus '.$label.' → invite bot sebagai anggota.'],
              ['3', 'Dapatkan Chat ID',            'Kirim pesan di grup → buka <code>getUpdates</code> API → cari <code>"chat":{"id":...</code>'],
              ['4', 'Isi &amp; Simpan',            'Masukkan token &amp; chat ID → Simpan → klik Test.'],
            ] as [$no, $judul, $desc]): ?>
            <div style="display:flex;gap:9px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f5f5f5;">
              <div style="width:24px;height:24px;border-radius:50%;background:<?= $color ?>;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= $no ?></div>
              <div>
                <div style="font-size:11.5px;font-weight:700;color:#333;margin-bottom:2px;"><?= $judul ?></div>
                <div style="font-size:11px;color:#888;line-height:1.6;"><?= $desc ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Contoh Pesan -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-comment-dots" style="color:<?= $color ?>;"></i> &nbsp;Contoh Pesan</h5>
          </div>
          <div class="panel-bd" style="padding:10px 14px;">
            <div style="background:#1e2a38;border-radius:6px;padding:11px 13px;font-family:monospace;font-size:10.5px;color:#e2e8f0;line-height:1.8;">
              🔔 <span style="color:#63b3ed;font-weight:700;">Tiket <?= $label ?> Baru</span><br><br>
              📋 No: <?= $tab==='ipsrs'?'IPS-00012':'TKT-00042' ?><br>
              📌 Judul: <?= $tab==='ipsrs'?'AC ruangan bocor':'PC tidak bisa menyala' ?><br>
              🏷️ Kategori: <?= $tab==='ipsrs'?'AC / Ventilasi':'Hardware' ?><br>
              🔴 Prioritas: Sedang<br>
              📍 Lokasi: Lt.2, R.Keuangan<br>
              👤 Pemohon: Anisa Rahma<br>
              🕐 Waktu: <?= date('d/m/Y H:i') ?>
            </div>
          </div>
        </div>

      </div><!-- /kanan -->
    </div><!-- /grid -->
  </form>

</div><!-- /.content -->

<script>
function toggleSwitch(wrap) {
  var cb    = wrap.querySelector('input[type=checkbox]');
  var track = document.getElementById('tg-track');
  var lbl   = document.getElementById('tg-lbl');
  var bar   = document.getElementById('tg-sbar');
  var ic    = document.getElementById('tg-sbar-ic');
  var stxt  = document.getElementById('tg-stxt');
  cb.checked = !cb.checked;
  if (cb.checked) {
    track.classList.add('on');
    lbl.textContent      = 'Aktif';
    bar.style.background  = '#d1fae5';
    bar.style.color       = '#065f46';
    bar.style.borderColor = '#a7f3d0';
    ic.className = 'fa fa-check-circle';
    stxt.innerHTML = 'Notifikasi <strong>aktif</strong>.';
  } else {
    track.classList.remove('on');
    lbl.textContent      = 'Nonaktif';
    bar.style.background  = '#f3f4f6';
    bar.style.color       = '#6b7280';
    bar.style.borderColor = '#e5e7eb';
    ic.className = 'fa fa-pause-circle';
    stxt.innerHTML = 'Notifikasi <strong>nonaktif</strong>.';
  }
}

function toggleVis() {
  var inp = document.getElementById('inp-token');
  var ic  = document.getElementById('eye-ic');
  if (inp.type === 'password') { inp.type = 'text';     ic.className = 'fa fa-eye-slash'; }
  else                         { inp.type = 'password'; ic.className = 'fa fa-eye'; }
}
</script>

<?php include '../includes/footer.php'; ?>