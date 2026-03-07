<?php
// pages/backup.php — Manajemen Backup Database
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole('admin')) { setFlash('danger', 'Akses ditolak. Hanya Admin.'); redirect(APP_URL . '/dashboard.php'); }
$page_title  = 'Backup Database';
$active_menu = 'backup';

$backup_dir = __DIR__ . '/../backups/';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);

// ── HANDLE POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ── BACKUP MANUAL ─────────────────────────────────────────────────────────
    if ($act === 'manual_backup') {
        $filename = 'backup_manual_' . date('Ymd_His') . '.sql';
        $filepath = $backup_dir . $filename;

        $host = DB_HOST;
        $user = DB_USER;
        $pass = DB_PASS;
        $db   = DB_NAME;

        $backup_ok    = false;
        $error_detail = '';

        // ── Coba cara 1: mysqldump via exec ───────────────────────────────────
        if (function_exists('exec')) {
            // Cari path mysqldump
            $mysqldump_path = 'mysqldump';
            foreach ([
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                '/opt/lampp/bin/mysqldump',
                '/Applications/MAMP/Library/bin/mysqldump',
                'C:/xampp/mysql/bin/mysqldump',
                'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump',
                'C:/laragon/bin/mysql/mysql-5.7.33-winx64/bin/mysqldump',
            ] as $path) {
                if (file_exists($path)) { $mysqldump_path = $path; break; }
            }

            $pass_arg = $pass !== '' ? '--password=' . escapeshellarg($pass) : '--password=';
            $command  = "{$mysqldump_path} --host=" . escapeshellarg($host)
                      . " --user=" . escapeshellarg($user)
                      . " {$pass_arg}"
                      . " --single-transaction --routines --triggers"
                      . " " . escapeshellarg($db)
                      . " > " . escapeshellarg($filepath) . " 2>&1";

            exec($command, $output, $return_code);

            if ($return_code === 0 && file_exists($filepath) && filesize($filepath) > 100) {
                $backup_ok = true;
            } else {
                $error_detail = implode("\n", $output);
            }
        }

        // ── Coba cara 2: PHP PDO fallback (jika mysqldump gagal/tidak ada) ────
        if (!$backup_ok) {
            try {
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $sql    = "-- FixSmart Helpdesk Backup\n"
                        . "-- Dibuat: " . date('Y-m-d H:i:s') . "\n"
                        . "-- Database: {$db}\n\n"
                        . "SET FOREIGN_KEY_CHECKS=0;\n\n";

                foreach ($tables as $table) {
                    $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                    $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                    $sql .= $create['Create Table'] . ";\n\n";

                    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                    if ($rows) {
                        $cols  = '`' . implode('`, `', array_keys($rows[0])) . '`';
                        $sql  .= "INSERT INTO `{$table}` ({$cols}) VALUES\n";
                        $chunks = [];
                        foreach ($rows as $row) {
                            $vals = array_map(function($v) use ($pdo) {
                                return $v === null ? 'NULL' : $pdo->quote($v);
                            }, array_values($row));
                            $chunks[] = '(' . implode(', ', $vals) . ')';
                        }
                        $sql .= implode(",\n", $chunks) . ";\n\n";
                    }
                }

                $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
                file_put_contents($filepath, $sql);

                if (file_exists($filepath) && filesize($filepath) > 50) {
                    $backup_ok    = true;
                    $error_detail = '';
                }
            } catch (Exception $e) {
                $error_detail .= "\nPHP fallback error: " . $e->getMessage();
            }
        }

        // ── Simpan log & flash ─────────────────────────────────────────────────
        if ($backup_ok) {
            $stmt = $pdo->prepare("INSERT INTO backup_logs (filename, filesize, type, status, created_by, created_at) VALUES (?,?,?,?,?,NOW())");
            $stmt->execute([$filename, filesize($filepath), 'manual', 'success', $_SESSION['user_nama'] ?? 'Admin']);
            setFlash('success', "✅ Backup manual berhasil dibuat: <strong>{$filename}</strong> (" . formatBytes(filesize($filepath)) . ")");
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO backup_logs (filename, filesize, type, status, keterangan, created_by, created_at) VALUES (?,?,?,?,?,?,NOW())");
                $stmt->execute([$filename, 0, 'manual', 'failed', $error_detail, $_SESSION['user_nama'] ?? 'Admin']);
            } catch(Exception $e) {}
            if (file_exists($filepath) && filesize($filepath) < 10) @unlink($filepath);
            setFlash('danger', "❌ Backup gagal.<br><small style='font-family:monospace;'>" . nl2br(htmlspecialchars($error_detail ?: 'exec() tidak tersedia atau mysqldump tidak ditemukan.')) . "</small>");
        }
        redirect(APP_URL . '/pages/backup.php');
    }

    // ── SIMPAN PENGATURAN AUTO BACKUP ─────────────────────────────────────────
    elseif ($act === 'save_auto') {
        $fields = [
            'backup_auto_enabled'   => isset($_POST['backup_auto_enabled'])   ? '1' : '0',
            'backup_auto_schedule'  => $_POST['backup_auto_schedule']  ?? 'daily',
            'backup_auto_time'      => $_POST['backup_auto_time']      ?? '02:00',
            'backup_auto_retention' => (int)($_POST['backup_auto_retention'] ?? 7),
            'backup_notif_telegram' => isset($_POST['backup_notif_telegram']) ? '1' : '0',
            'backup_notif_email'    => isset($_POST['backup_notif_email'])    ? '1' : '0',
            'backup_storage_local'  => isset($_POST['backup_storage_local'])  ? '1' : '0',
            'backup_compress'       => isset($_POST['backup_compress'])       ? '1' : '0',
        ];
        $stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        foreach ($fields as $k => $v) $stmt->execute([$k, $v]);
        setFlash('success', "✅ Pengaturan Auto Backup berhasil disimpan.");
        redirect(APP_URL . '/pages/backup.php');
    }

    // ── HAPUS BACKUP ──────────────────────────────────────────────────────────
    elseif ($act === 'delete') {
        $file = basename($_POST['filename'] ?? '');
        $path = $backup_dir . $file;
        if ($file && file_exists($path) && pathinfo($path, PATHINFO_EXTENSION) === 'sql') {
            unlink($path);
            $pdo->prepare("DELETE FROM backup_logs WHERE filename=?")->execute([$file]);
            setFlash('success', "🗑️ File backup <strong>{$file}</strong> berhasil dihapus.");
        } else {
            setFlash('danger', "❌ File tidak ditemukan atau tidak valid.");
        }
        redirect(APP_URL . '/pages/backup.php');
    }
}

// ── DOWNLOAD ──────────────────────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $path = $backup_dir . $file;
    if ($file && file_exists($path) && pathinfo($path, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// ── LOAD DATA ─────────────────────────────────────────────────────────────────
$cfg = getSettings($pdo);
$auto_enabled   = ($cfg['backup_auto_enabled']   ?? '0') == '1';
$auto_schedule  = $cfg['backup_auto_schedule']   ?? 'daily';
$auto_time      = $cfg['backup_auto_time']        ?? '02:00';
$auto_retention = (int)($cfg['backup_auto_retention'] ?? 7);
$notif_telegram = ($cfg['backup_notif_telegram'] ?? '0') == '1';
$notif_email    = ($cfg['backup_notif_email']    ?? '0') == '1';
$backup_local   = ($cfg['backup_storage_local']  ?? '1') == '1';
$compress       = ($cfg['backup_compress']        ?? '0') == '1';

// Ambil daftar file backup dari folder
$backup_files = [];
foreach (glob($backup_dir . '*.sql') as $f) {
    $backup_files[] = [
        'name'    => basename($f),
        'size'    => filesize($f),
        'time'    => filemtime($f),
        'type'    => strpos(basename($f), 'manual') !== false ? 'manual' : 'auto',
    ];
}
usort($backup_files, fn($a,$b) => $b['time'] <=> $a['time']);

// Statistik
$total_files = count($backup_files);
$total_size  = array_sum(array_column($backup_files, 'size'));
$last_backup = $total_files > 0 ? date('d/m/Y H:i', $backup_files[0]['time']) : 'Belum ada';

function formatBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes/1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes/1024, 2) . ' KB';
    return $bytes . ' B';
}

include '../includes/header.php';
?>

<style>
/* ── TAB STYLE (sama persis dengan setting_telegram) ── */
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

/* ── TOGGLE SWITCH ── */
.toggle-wrap  { cursor:pointer; }
.toggle-track { width:42px;height:22px;background:#ddd;border-radius:11px;position:relative;transition:background .25s; }
.toggle-track.on { background:#26B99A; }
.toggle-thumb { width:18px;height:18px;background:#fff;border-radius:50%;position:absolute;top:2px;left:2px;transition:left .25s;box-shadow:0 1px 3px rgba(0,0,0,.2); }
.toggle-track.on .toggle-thumb { left:22px; }
.chk-toggle input { opacity:0;width:0;height:0;position:absolute; }
.chk-slider { position:absolute;inset:0;background:#ddd;border-radius:22px;cursor:pointer;transition:.25s; }
.chk-slider:before { content:'';position:absolute;width:16px;height:16px;background:#fff;border-radius:50%;left:3px;top:3px;transition:.25s;box-shadow:0 1px 3px rgba(0,0,0,.2); }
.chk-toggle input:checked + .chk-slider { background:#26B99A; }
.chk-toggle input:checked + .chk-slider:before { transform:translateX(16px); }

/* ── STAT CARDS ── */
.stat-cards { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px; }
.stat-card {
  background:#fff;
  border:1px solid #e2e8f0;
  border-radius:8px;
  padding:16px 18px;
  display:flex;align-items:center;gap:14px;
}
.stat-icon {
  width:44px;height:44px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;
}
.stat-label { font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.5px; }
.stat-val   { font-size:20px;font-weight:800;color:#1e293b;line-height:1.2; }
.stat-sub   { font-size:11px;color:#94a3b8;margin-top:1px; }

/* ── BACKUP TABLE ── */
.backup-table { width:100%;border-collapse:collapse;font-size:12px; }
.backup-table th { background:#f8fafc;padding:9px 12px;font-weight:700;color:#64748b;text-align:left;border-bottom:2px solid #e2e8f0;font-size:11px;text-transform:uppercase;letter-spacing:.4px; }
.backup-table td { padding:10px 12px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle; }
.backup-table tr:last-child td { border-bottom:none; }
.backup-table tr:hover td { background:#f8fafc; }
.type-badge { display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700; }
.type-manual { background:#dbeafe;color:#1e40af; }
.type-auto   { background:#d1fae5;color:#065f46; }

/* ── SCHEDULE SELECT ── */
.schedule-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:8px 0 14px; }
.sch-opt {
  border:2px solid #e2e8f0;border-radius:8px;padding:12px 10px;text-align:center;cursor:pointer;transition:all .18s;
}
.sch-opt:hover   { border-color:#26B99A;background:#f0fdf4; }
.sch-opt.active  { border-color:#26B99A;background:#f0fdf4; }
.sch-opt i       { font-size:20px;margin-bottom:6px;display:block;color:#94a3b8; }
.sch-opt.active i{ color:#26B99A; }
.sch-opt span    { font-size:11px;font-weight:700;color:#64748b; }
.sch-opt.active span { color:#0f172a; }

/* ── PROGRESS ── */
.progress-wrap { background:#f1f5f9;border-radius:6px;height:8px;overflow:hidden;margin-top:6px; }
.progress-bar  { height:100%;border-radius:6px;background:linear-gradient(90deg,#26B99A,#00e5b0);transition:width .6s; }

/* ── MANUAL BACKUP BUTTON BIG ── */
.btn-backup-big {
  display:flex;align-items:center;justify-content:center;gap:10px;
  width:100%;padding:16px;border-radius:8px;font-size:14px;font-weight:700;
  background:linear-gradient(135deg,#1e3a5f,#26B99A);color:#fff;border:none;
  cursor:pointer;transition:all .2s;box-shadow:0 4px 15px rgba(38,185,154,.3);
}
.btn-backup-big:hover { transform:translateY(-1px);box-shadow:0 6px 20px rgba(38,185,154,.4); }
.btn-backup-big:active { transform:translateY(0); }

/* ── EMPTY STATE ── */
.empty-state { text-align:center;padding:40px 20px;color:#94a3b8; }
.empty-state i { font-size:40px;margin-bottom:12px;display:block;opacity:.4; }
</style>

<div class="page-header">
  <h4><i class="fa fa-database" style="color:#26B99A;"></i> &nbsp;Manajemen Backup Database</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
    <span class="cur">Backup Database</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- ══ TAB NAVIGATION (style sama dengan setting_telegram) ══ -->
  <div class="tg-tabs-wrap">
    <a href="<?= APP_URL ?>/pages/backup.php?tab=manual"
       class="tg-tab-link <?= (($_GET['tab']??'manual')==='manual')?'active':'' ?>">
      <span class="tg-dot" style="background:#3b82f6;box-shadow:0 0 5px #3b82f6;"></span>
      <i class="fa fa-hand-pointer" style="color:#3b82f6;"></i>
      Backup Manual
    </a>
    <a href="<?= APP_URL ?>/pages/backup.php?tab=auto"
       class="tg-tab-link <?= (($_GET['tab']??'')==='auto')?'active':'' ?>">
      <span class="tg-dot" style="background:<?= $auto_enabled?'#10b981':'#d1d5db' ?>;box-shadow:<?= $auto_enabled?'0 0 5px #10b981':'none' ?>;"></span>
      <i class="fa fa-clock" style="color:#26B99A;"></i>
      Auto Backup
      <span class="tg-badge" style="background:<?= $auto_enabled?'#d1fae5':'#f3f4f6' ?>;color:<?= $auto_enabled?'#065f46':'#6b7280' ?>;">
        <?= $auto_enabled ? 'Aktif' : 'Nonaktif' ?>
      </span>
    </a>
    <a href="<?= APP_URL ?>/pages/backup.php?tab=history"
       class="tg-tab-link <?= (($_GET['tab']??'')==='history')?'active':'' ?>">
      <span class="tg-dot" style="background:#8b5cf6;box-shadow:0 0 5px #8b5cf6;"></span>
      <i class="fa fa-history" style="color:#8b5cf6;"></i>
      Riwayat Backup
      <span class="tg-badge" style="background:#ede9fe;color:#5b21b6;"><?= $total_files ?> File</span>
    </a>
  </div>

  <!-- ══ STAT CARDS ══ -->
  <div class="stat-cards">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;">
        <i class="fa fa-archive" style="color:#3b82f6;"></i>
      </div>
      <div>
        <div class="stat-label">Total Backup</div>
        <div class="stat-val"><?= $total_files ?></div>
        <div class="stat-sub">file tersimpan</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;">
        <i class="fa fa-hdd" style="color:#10b981;"></i>
      </div>
      <div>
        <div class="stat-label">Total Ukuran</div>
        <div class="stat-val"><?= formatBytes($total_size) ?></div>
        <div class="stat-sub">ruang digunakan</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;">
        <i class="fa fa-clock" style="color:#d97706;"></i>
      </div>
      <div>
        <div class="stat-label">Backup Terakhir</div>
        <div class="stat-val" style="font-size:13px;margin-top:2px;"><?= $last_backup ?></div>
        <div class="stat-sub">waktu backup</div>
      </div>
    </div>
  </div>

  <?php $active_tab = $_GET['tab'] ?? 'manual'; ?>

  <!-- ══════════════════ TAB: MANUAL BACKUP ══════════════════ -->
  <?php if ($active_tab === 'manual'): ?>
  <div style="display:grid;grid-template-columns:1fr 340px;gap:18px;">

    <!-- KIRI -->
    <div>
      <div class="panel">
        <div class="panel-hd">
          <h5><i class="fa fa-database" style="color:#3b82f6;"></i> &nbsp;Backup Manual Sekarang</h5>
        </div>
        <div class="panel-bd">

          <div style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1px solid #bfdbfe;border-radius:8px;padding:18px;margin-bottom:18px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
              <div style="width:40px;height:40px;background:#3b82f6;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-shield-alt" style="color:#fff;font-size:18px;"></i>
              </div>
              <div>
                <div style="font-size:13px;font-weight:700;color:#1e40af;">Backup Database Penuh</div>
                <div style="font-size:11px;color:#3b82f6;">Semua tabel akan dicadangkan ke file .sql</div>
              </div>
            </div>
            <div style="font-size:11.5px;color:#1e40af;line-height:1.8;">
              ✔ Semua tabel dan data tercadangkan<br>
              ✔ File disimpan di folder <code style="background:#dbeafe;padding:1px 5px;border-radius:3px;">/backups/</code><br>
              ✔ Format: <code style="background:#dbeafe;padding:1px 5px;border-radius:3px;">backup_manual_YYYYMMDD_HHMMSS.sql</code>
            </div>
          </div>

          <form method="POST" onsubmit="return confirmBackup(this)">
            <input type="hidden" name="action" value="manual_backup">
            <button type="submit" class="btn-backup-big" id="btn-backup">
              <i class="fa fa-database"></i>
              Mulai Backup Manual Sekarang
            </button>
          </form>

          <div style="margin-top:16px;background:#f8fafc;border-radius:6px;padding:12px 14px;font-size:11.5px;color:#64748b;line-height:1.8;">
            <strong style="color:#334155;">ℹ️ Informasi:</strong><br>
            Proses backup membutuhkan waktu beberapa detik tergantung ukuran database.
            Jangan tutup halaman selama proses berlangsung.
            Backup otomatis tersedia di tab <strong>Auto Backup</strong>.
          </div>

        </div>
      </div>

      <!-- 5 Backup Terbaru -->
      <div class="panel">
        <div class="panel-hd">
          <h5><i class="fa fa-list" style="color:#3b82f6;"></i> &nbsp;5 Backup Terbaru</h5>
          <a href="?tab=history" style="font-size:11px;color:#26B99A;font-weight:600;">Lihat Semua →</a>
        </div>
        <div class="panel-bd" style="padding:0;">
          <?php if (empty($backup_files)): ?>
          <div class="empty-state"><i class="fa fa-folder-open"></i>Belum ada file backup.</div>
          <?php else: ?>
          <table class="backup-table">
            <thead>
              <tr>
                <th>Nama File</th>
                <th>Ukuran</th>
                <th>Tipe</th>
                <th>Waktu</th>
                <th style="text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($backup_files, 0, 5) as $f): ?>
              <tr>
                <td>
                  <i class="fa fa-file-code" style="color:#94a3b8;margin-right:6px;"></i>
                  <span style="font-family:monospace;font-size:11px;"><?= htmlspecialchars($f['name']) ?></span>
                </td>
                <td><?= formatBytes($f['size']) ?></td>
                <td>
                  <span class="type-badge <?= $f['type']==='manual'?'type-manual':'type-auto' ?>">
                    <i class="fa <?= $f['type']==='manual'?'fa-hand-pointer':'fa-clock' ?>"></i>
                    <?= ucfirst($f['type']) ?>
                  </span>
                </td>
                <td><?= date('d/m/Y H:i', $f['time']) ?></td>
                <td style="text-align:center;">
                  <a href="?download=<?= urlencode($f['name']) ?>" class="btn btn-xs btn-info" title="Download">
                    <i class="fa fa-download"></i>
                  </a>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus file ini?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="filename" value="<?= htmlspecialchars($f['name']) ?>">
                    <button type="submit" class="btn btn-xs btn-danger" title="Hapus">
                      <i class="fa fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- KANAN -->
    <div>
      <!-- Status DB -->
      <div class="panel">
        <div class="panel-hd">
          <h5><i class="fa fa-server" style="color:#3b82f6;"></i> &nbsp;Info Database</h5>
        </div>
        <div class="panel-bd" style="padding:14px;">
          <?php
          try {
            $tables = $pdo->query("SHOW TABLE STATUS")->fetchAll();
            $db_size = array_sum(array_map(fn($t)=>$t['Data_length']+$t['Index_length'], $tables));
            $tbl_count = count($tables);
          } catch(Exception $e) { $db_size=0; $tbl_count=0; }
          ?>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ([
              ['Database',   DB_NAME ?? '-',          'fa-database',   '#3b82f6'],
              ['Host',       DB_HOST ?? 'localhost',   'fa-server',     '#8b5cf6'],
              ['Tabel',      $tbl_count . ' tabel',    'fa-table',      '#10b981'],
              ['Ukuran DB',  formatBytes($db_size),    'fa-hdd',        '#d97706'],
            ] as [$lbl, $val, $ic, $col]): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:#f8fafc;border-radius:6px;">
              <i class="fa <?= $ic ?>" style="color:<?= $col ?>;width:16px;text-align:center;"></i>
              <span style="font-size:11px;color:#64748b;flex:1;"><?= $lbl ?></span>
              <strong style="font-size:11.5px;color:#1e293b;"><?= htmlspecialchars($val) ?></strong>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Tips -->
      <div class="panel">
        <div class="panel-hd">
          <h5><i class="fa fa-lightbulb" style="color:#d97706;"></i> &nbsp;Tips & Saran</h5>
        </div>
        <div class="panel-bd" style="padding:12px 14px;">
          <?php foreach ([
            ['💡', 'Backup Rutin',        'Lakukan backup minimal 1x sehari, terutama setelah aktivitas tinggi.'],
            ['🔒', 'Simpan di Tempat Aman','Simpan salinan backup di cloud atau drive eksternal.'],
            ['🗓️', 'Gunakan Auto Backup', 'Aktifkan jadwal otomatis agar tidak lupa backup manual.'],
            ['🗑️', 'Bersihkan File Lama', 'Hapus backup lebih dari 30 hari untuk hemat penyimpanan.'],
          ] as [$em, $judul, $desc]): ?>
          <div style="display:flex;gap:9px;margin-bottom:11px;padding-bottom:11px;border-bottom:1px solid #f5f5f5;">
            <span style="font-size:16px;flex-shrink:0;"><?= $em ?></span>
            <div>
              <div style="font-size:11.5px;font-weight:700;color:#333;margin-bottom:2px;"><?= $judul ?></div>
              <div style="font-size:11px;color:#888;line-height:1.5;"><?= $desc ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div><!-- /grid manual -->

  <!-- ══════════════════ TAB: AUTO BACKUP ══════════════════ -->
  <?php elseif ($active_tab === 'auto'): ?>
  <form method="POST">
    <input type="hidden" name="action" value="save_auto">

    <div style="display:grid;grid-template-columns:1fr 340px;gap:18px;">

      <!-- KIRI -->
      <div>

        <!-- Toggle Auto Backup -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-magic" style="color:#26B99A;"></i> &nbsp;Pengaturan Auto Backup</h5>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;font-weight:700;color:#555;">
              <div class="toggle-wrap" onclick="toggleAutoSwitch(this)">
                <input type="checkbox" name="backup_auto_enabled" id="auto-enabled"
                       <?= $auto_enabled ? 'checked' : '' ?> style="display:none;">
                <div class="toggle-track <?= $auto_enabled ? 'on' : '' ?>" id="auto-track">
                  <div class="toggle-thumb"></div>
                </div>
              </div>
              <span id="auto-lbl"><?= $auto_enabled ? 'Aktif' : 'Nonaktif' ?></span>
            </label>
          </div>
          <div class="panel-bd">

            <div id="auto-sbar" style="padding:10px 13px;border-radius:4px;margin-bottom:16px;font-size:12px;display:flex;align-items:center;gap:8px;
                 background:<?= $auto_enabled?'#d1fae5':'#f3f4f6' ?>;
                 color:<?= $auto_enabled?'#065f46':'#6b7280' ?>;
                 border:1px solid <?= $auto_enabled?'#a7f3d0':'#e5e7eb' ?>;">
              <i class="fa <?= $auto_enabled?'fa-check-circle':'fa-pause-circle' ?>" id="auto-sbar-ic"></i>
              <span id="auto-stxt">
                <?= $auto_enabled
                  ? "Auto backup <strong>aktif</strong>. Berjalan setiap <strong>{$auto_schedule}</strong> pukul <strong>{$auto_time}</strong>."
                  : "Auto backup <strong>nonaktif</strong>. Aktifkan untuk menjadwalkan backup otomatis." ?>
              </span>
            </div>

            <!-- Jadwal -->
            <div class="form-group">
              <label style="font-size:12px;font-weight:700;color:#334155;margin-bottom:8px;display:block;">
                Frekuensi Backup
              </label>
              <div class="schedule-grid" id="sch-grid">
                <?php foreach ([
                  ['daily',   'fa-calendar-day',   'Harian'],
                  ['weekly',  'fa-calendar-week',  'Mingguan'],
                  ['monthly', 'fa-calendar-alt',   'Bulanan'],
                ] as [$val, $ic, $lbl]): ?>
                <div class="sch-opt <?= $auto_schedule===$val?'active':'' ?>"
                     onclick="selectSchedule('<?= $val ?>', this)">
                  <i class="fa <?= $ic ?>"></i>
                  <span><?= $lbl ?></span>
                </div>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="backup_auto_schedule" id="inp-schedule" value="<?= htmlspecialchars($auto_schedule) ?>">
            </div>

            <!-- Waktu -->
            <div class="form-group">
              <label>Waktu Eksekusi</label>
              <input type="time" name="backup_auto_time" class="form-control"
                     value="<?= htmlspecialchars($auto_time) ?>" style="max-width:180px;">
              <div class="form-hint">Disarankan di luar jam kerja, misalnya <strong>02:00</strong> dini hari.</div>
            </div>

            <!-- Retensi -->
            <div class="form-group">
              <label>Retensi Backup (hari) — simpan <strong id="ret-val"><?= $auto_retention ?></strong> hari terakhir</label>
              <input type="range" name="backup_auto_retention" id="ret-slider"
                     min="1" max="30" value="<?= $auto_retention ?>"
                     oninput="document.getElementById('ret-val').textContent=this.value"
                     style="width:100%;accent-color:#26B99A;">
              <div style="display:flex;justify-content:space-between;font-size:10px;color:#94a3b8;margin-top:4px;">
                <span>1 hari</span><span>15 hari</span><span>30 hari</span>
              </div>
              <div class="form-hint">Backup lebih lama dari rentang ini akan dihapus otomatis.</div>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="fa fa-save"></i> Simpan Pengaturan
            </button>
          </div>
        </div>

        <!-- Opsi Notifikasi -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-bell" style="color:#26B99A;"></i> &nbsp;Notifikasi Backup</h5>
          </div>
          <div class="panel-bd">
            <p style="font-size:12px;color:#aaa;margin-bottom:14px;">Kirim notifikasi saat backup otomatis selesai atau gagal:</p>
            <?php foreach ([
              ['backup_notif_telegram', $notif_telegram, 'fa-paper-plane', '#0088cc', 'Notifikasi Telegram', 'Kirim ke grup Telegram IT saat backup selesai/gagal.'],
              ['backup_notif_email',    $notif_email,    'fa-envelope',    '#ef4444', 'Notifikasi Email',    'Kirim email ke admin saat backup selesai/gagal.'],
              ['backup_storage_local',  $backup_local,   'fa-hdd',         '#8b5cf6', 'Simpan Lokal',        'Simpan file .sql di folder /backups/ server.'],
              ['backup_compress',       $compress,       'fa-file-archive', '#d97706', 'Kompresi .gz',       'Kompres file backup untuk hemat penyimpanan.'],
            ] as [$name, $checked, $ic, $col, $judul, $desc]): ?>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #f5f5f5;">
              <label class="chk-toggle" style="position:relative;display:inline-block;width:38px;height:22px;flex-shrink:0;margin-top:2px;">
                <input type="checkbox" name="<?= $name ?>" <?= $checked?'checked':'' ?>>
                <span class="chk-slider"></span>
              </label>
              <div style="display:flex;align-items:flex-start;gap:8px;">
                <i class="fa <?= $ic ?>" style="color:<?= $col ?>;margin-top:1px;width:14px;text-align:center;flex-shrink:0;"></i>
                <div>
                  <div style="font-size:12px;font-weight:700;color:#333;"><?= $judul ?></div>
                  <div style="font-size:11px;color:#aaa;margin-top:2px;line-height:1.5;"><?= $desc ?></div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:14px;">
              <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Semua</button>
            </div>
          </div>
        </div>

      </div><!-- /kiri auto -->

      <!-- KANAN auto -->
      <div>

        <!-- Status Auto -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-signal" style="color:#26B99A;"></i> &nbsp;Status Auto Backup</h5>
          </div>
          <div class="panel-bd" style="text-align:center;padding:18px;">
            <div style="width:56px;height:56px;border-radius:50%;margin:0 auto 10px;
                 background:<?= $auto_enabled?'#d1fae5':'#f3f4f6' ?>;
                 display:flex;align-items:center;justify-content:center;">
              <i class="fa <?= $auto_enabled?'fa-check-circle':'fa-pause-circle' ?>"
                 style="font-size:26px;color:<?= $auto_enabled?'#059669':'#9ca3af' ?>;"></i>
            </div>
            <div style="font-size:13px;font-weight:700;color:#333;margin-bottom:4px;">
              <?= $auto_enabled ? 'Auto Backup Berjalan' : 'Auto Backup Nonaktif' ?>
            </div>
            <div style="font-size:11px;color:#aaa;line-height:1.6;">
              <?php if ($auto_enabled): ?>
                Jadwal: <strong><?= ucfirst($auto_schedule) ?></strong><br>
                Pukul: <strong><?= $auto_time ?></strong><br>
                Retensi: <strong><?= $auto_retention ?> hari</strong>
              <?php else: ?>
                Aktifkan toggle di atas<br>untuk memulai jadwal backup.
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Cron Command -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-terminal" style="color:#334155;"></i> &nbsp;Cron Job (Linux)</h5>
          </div>
          <div class="panel-bd" style="padding:12px 14px;">
            <p style="font-size:11px;color:#94a3b8;margin-bottom:10px;">
              Tambahkan di crontab server (<code>crontab -e</code>):
            </p>
            <?php
            $cron_time = match($auto_schedule) {
                'weekly'  => '0 ' . intval($auto_time) . ' * * 0',
                'monthly' => '0 ' . intval($auto_time) . ' 1 * *',
                default   => '0 ' . intval($auto_time) . ' * * *',
            };
            ?>
            <div style="background:#1e2a38;border-radius:6px;padding:10px 12px;font-family:monospace;font-size:10.5px;color:#e2e8f0;line-height:1.8;word-break:break-all;">
              <?= $cron_time ?> php <?= realpath(__DIR__.'/../cron/backup.php') ?> >> /var/log/backup.log 2>&1
            </div>
            <div style="margin-top:10px;font-size:11px;color:#94a3b8;">
              Atau gunakan cPanel → <strong>Cron Jobs</strong> → tambahkan command di atas.
            </div>
          </div>
        </div>

        <!-- Panduan -->
        <div class="panel">
          <div class="panel-hd">
            <h5><i class="fa fa-book-open" style="color:#26B99A;"></i> &nbsp;Cara Setup Auto</h5>
          </div>
          <div class="panel-bd" style="padding:12px 14px;">
            <?php foreach ([
              ['1', 'Aktifkan Toggle',    'Geser toggle di atas ke posisi Aktif.'],
              ['2', 'Pilih Jadwal',       'Pilih frekuensi: Harian, Mingguan, atau Bulanan.'],
              ['3', 'Atur Waktu',         'Pilih jam eksekusi, disarankan dini hari.'],
              ['4', 'Setup Cron Job',     'Tambahkan perintah cron di server untuk menjalankan otomatis.'],
            ] as [$no, $judul, $desc]): ?>
            <div style="display:flex;gap:9px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f5f5f5;">
              <div style="width:24px;height:24px;border-radius:50%;background:#26B99A;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= $no ?></div>
              <div>
                <div style="font-size:11.5px;font-weight:700;color:#333;margin-bottom:2px;"><?= $judul ?></div>
                <div style="font-size:11px;color:#888;line-height:1.6;"><?= $desc ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /kanan auto -->
    </div>
  </form>

  <!-- ══════════════════ TAB: RIWAYAT ══════════════════ -->
  <?php elseif ($active_tab === 'history'): ?>
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-history" style="color:#8b5cf6;"></i> &nbsp;Semua File Backup (<?= $total_files ?> file)</h5>
      <div style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:11px;color:#94a3b8;">Total: <?= formatBytes($total_size) ?></span>
        <form method="POST" style="margin:0;" onsubmit="return confirm('Hapus semua backup? Tindakan ini tidak bisa dibatalkan!')">
          <input type="hidden" name="action" value="delete_all">
          <?php if ($total_files > 0): ?>
          <button type="button" class="btn btn-xs btn-danger" onclick="alert('Fitur hapus semua: implementasikan sesuai kebutuhan.')">
            <i class="fa fa-trash"></i> Hapus Semua
          </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
    <div class="panel-bd" style="padding:0;">
      <?php if (empty($backup_files)): ?>
      <div class="empty-state">
        <i class="fa fa-folder-open"></i>
        <strong style="display:block;margin-bottom:6px;">Belum ada file backup</strong>
        <span>Buat backup pertama Anda melalui tab <a href="?tab=manual" style="color:#26B99A;">Backup Manual</a>.</span>
      </div>
      <?php else: ?>
      <!-- Filter -->
      <div style="padding:12px 14px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:10px;">
        <span style="font-size:11px;color:#64748b;font-weight:600;">Filter:</span>
        <select onchange="filterTable(this.value)" style="font-size:12px;padding:4px 8px;border:1px solid #e2e8f0;border-radius:5px;color:#334155;">
          <option value="all">Semua Tipe</option>
          <option value="manual">Manual</option>
          <option value="auto">Otomatis</option>
        </select>
        <input type="text" placeholder="Cari nama file..." oninput="searchTable(this.value)"
               style="font-size:12px;padding:4px 10px;border:1px solid #e2e8f0;border-radius:5px;color:#334155;width:200px;">
      </div>
      <table class="backup-table" id="hist-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama File</th>
            <th>Ukuran</th>
            <th>Tipe</th>
            <th>Tanggal Dibuat</th>
            <th style="text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($backup_files as $i => $f): ?>
          <tr data-type="<?= $f['type'] ?>" data-name="<?= strtolower($f['name']) ?>">
            <td style="color:#94a3b8;font-size:11px;"><?= $i+1 ?></td>
            <td>
              <i class="fa fa-file-code" style="color:#8b5cf6;margin-right:6px;"></i>
              <span style="font-family:monospace;font-size:11px;"><?= htmlspecialchars($f['name']) ?></span>
              <?php if ($i===0): ?>
              <span style="margin-left:6px;font-size:9px;background:#fef3c7;color:#d97706;padding:2px 6px;border-radius:10px;font-weight:700;">TERBARU</span>
              <?php endif; ?>
            </td>
            <td><?= formatBytes($f['size']) ?></td>
            <td>
              <span class="type-badge <?= $f['type']==='manual'?'type-manual':'type-auto' ?>">
                <i class="fa <?= $f['type']==='manual'?'fa-hand-pointer':'fa-clock' ?>"></i>
                <?= $f['type']==='manual' ? 'Manual' : 'Otomatis' ?>
              </span>
            </td>
            <td><?= date('d/m/Y H:i:s', $f['time']) ?></td>
            <td style="text-align:center;">
              <a href="?download=<?= urlencode($f['name']) ?>" class="btn btn-xs btn-info" title="Download">
                <i class="fa fa-download"></i> Download
              </a>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus file ini?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="filename" value="<?= htmlspecialchars($f['name']) ?>">
                <button type="submit" class="btn btn-xs btn-danger" title="Hapus">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Storage Usage -->
  <?php if ($total_files > 0):
    $max_size = 500 * 1024 * 1024; // asumsikan 500MB max
    $pct = min(100, round($total_size / $max_size * 100));
  ?>
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-hdd" style="color:#8b5cf6;"></i> &nbsp;Penggunaan Penyimpanan</h5>
    </div>
    <div class="panel-bd" style="padding:14px 16px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:12px;">
        <span style="color:#64748b;">Digunakan: <strong><?= formatBytes($total_size) ?></strong></span>
        <span style="color:#94a3b8;">Estimasi max: 500 MB</span>
        <span style="color:<?= $pct>80?'#ef4444':($pct>50?'#d97706':'#10b981') ?>;font-weight:700;"><?= $pct ?>%</span>
      </div>
      <div class="progress-wrap">
        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>80?'linear-gradient(90deg,#ef4444,#f87171)':($pct>50?'linear-gradient(90deg,#d97706,#fbbf24)':'linear-gradient(90deg,#26B99A,#00e5b0)') ?>;"></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?><!-- /tab history -->

</div><!-- /.content -->

<script>
function confirmBackup(form) {
  if (!confirm('Mulai backup database sekarang?\n\nProses ini mungkin memakan waktu beberapa detik.')) return false;
  var btn = document.getElementById('btn-backup');
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sedang Backup...';
  btn.disabled = true;
  return true;
}

function toggleAutoSwitch(wrap) {
  var cb    = wrap.querySelector('input[type=checkbox]');
  var track = document.getElementById('auto-track');
  var lbl   = document.getElementById('auto-lbl');
  var bar   = document.getElementById('auto-sbar');
  var ic    = document.getElementById('auto-sbar-ic');
  var stxt  = document.getElementById('auto-stxt');
  cb.checked = !cb.checked;
  if (cb.checked) {
    track.classList.add('on');
    lbl.textContent      = 'Aktif';
    bar.style.background  = '#d1fae5';
    bar.style.color       = '#065f46';
    bar.style.borderColor = '#a7f3d0';
    ic.className = 'fa fa-check-circle';
    stxt.innerHTML = 'Auto backup <strong>aktif</strong>.';
  } else {
    track.classList.remove('on');
    lbl.textContent      = 'Nonaktif';
    bar.style.background  = '#f3f4f6';
    bar.style.color       = '#6b7280';
    bar.style.borderColor = '#e5e7eb';
    ic.className = 'fa fa-pause-circle';
    stxt.innerHTML = 'Auto backup <strong>nonaktif</strong>.';
  }
}

function selectSchedule(val, el) {
  document.querySelectorAll('.sch-opt').forEach(o => o.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('inp-schedule').value = val;
}

function filterTable(type) {
  document.querySelectorAll('#hist-table tbody tr').forEach(tr => {
    tr.style.display = (type === 'all' || tr.dataset.type === type) ? '' : 'none';
  });
}

function searchTable(q) {
  document.querySelectorAll('#hist-table tbody tr').forEach(tr => {
    tr.style.display = tr.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
  });
}
</script>

<?php include '../includes/footer.php'; ?>