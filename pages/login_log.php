<?php
// pages/login_log.php
session_start();
require_once '../config.php';
requireLogin();

// Hanya admin
if (!hasRole('admin')) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Log Login';
$active_menu = 'login_log';

// ── Filter & Pagination ───────────────────────────────────────────────────────
$page     = max(1, (int)($_GET['page']   ?? 1));
$per_page = 20;
$fs       = $_GET['status']  ?? '';   // berhasil|gagal|terkunci
$fu       = (int)($_GET['uid']    ?? 0);
$fi       = trim($_GET['ip']      ?? '');
$fd_dari  = $_GET['dari']    ?? '';
$fd_sampai= $_GET['sampai']  ?? '';
$search   = trim($_GET['q']       ?? '');

$valid_status = ['', 'berhasil', 'gagal', 'terkunci'];
if (!in_array($fs, $valid_status)) $fs = '';

$where  = ['1=1'];
$params = [];

if ($fs)       { $where[] = 'l.status = ?';          $params[] = $fs; }
if ($fu)       { $where[] = 'l.user_id = ?';         $params[] = $fu; }
if ($fi)       { $where[] = 'l.ip_address LIKE ?';   $params[] = "%$fi%"; }
if ($fd_dari)  { $where[] = 'DATE(l.created_at) >= ?'; $params[] = $fd_dari; }
if ($fd_sampai){ $where[] = 'DATE(l.created_at) <= ?'; $params[] = $fd_sampai; }
if ($search)   { $where[] = '(l.username_input LIKE ? OR u.nama LIKE ? OR l.ip_address LIKE ?)';
                 $params   = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$wsql = implode(' AND ', $where);

// ── Hitung total ──────────────────────────────────────────────────────────────
$st = $pdo->prepare("
    SELECT COUNT(*) FROM login_log l LEFT JOIN users u ON u.id=l.user_id WHERE $wsql
");
$st->execute($params);
$total  = (int)$st->fetchColumn();
$pages  = max(1, ceil($total / $per_page));
$page   = min($page, $pages);
$offset = ($page - 1) * $per_page;

// ── Ambil data ────────────────────────────────────────────────────────────────
$st = $pdo->prepare("
    SELECT l.*, u.nama AS user_nama, u.role AS user_role
    FROM login_log l
    LEFT JOIN users u ON u.id = l.user_id
    WHERE $wsql
    ORDER BY l.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$st->execute($params);
$logs = $st->fetchAll();

// ── Stats ringkasan ───────────────────────────────────────────────────────────
$stats = ['berhasil' => 0, 'gagal' => 0, 'terkunci' => 0];
try {
    $st2 = $pdo->query("
        SELECT status, COUNT(*) n FROM login_log
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY status
    ");
    foreach ($st2->fetchAll() as $r) $stats[$r['status']] = (int)$r['n'];
} catch (Exception $e) {}

// ── Top IP dengan gagal terbanyak ─────────────────────────────────────────────
$top_fail_ip = [];
try {
    $top_fail_ip = $pdo->query("
        SELECT ip_address, COUNT(*) n,
               MAX(created_at) AS last_attempt
        FROM login_log
        WHERE status = 'gagal' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY ip_address
        HAVING n >= 3
        ORDER BY n DESC LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {}

// ── Daftar user untuk filter dropdown ────────────────────────────────────────
$user_list = $pdo->query("SELECT id, nama FROM users ORDER BY nama")->fetchAll();

// ── IP yang saat ini diblokir ─────────────────────────────────────────────────
$blocked_ips = [];
try {
    $blocked_ips = $pdo->query("
        SELECT ip_address, attempts, blocked_until,
               CEIL(TIMESTAMPDIFF(SECOND, NOW(), blocked_until)/60) AS sisa_menit
        FROM login_attempts
        WHERE blocked_until > NOW()
        ORDER BY blocked_until DESC
    ")->fetchAll();
} catch (Exception $e) {}

// ── Handle unblock IP (POST action) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'unblock' && !empty($_POST['ip'])) {
        $ip_unblock = $_POST['ip'];
        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip_unblock]);
        setFlash('success', "IP $ip_unblock berhasil di-unblock.");
        redirect(APP_URL . '/pages/login_log.php');
    }
}

include '../includes/header.php';
?>

<style>
.log-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700; }
.log-b { background:#d1fae5;color:#065f46; }
.log-g { background:#fee2e2;color:#991b1b; }
.log-t { background:#fef3c7;color:#92400e; }
.device-icon { font-size:13px;margin-right:4px; }
.ip-chip { display:inline-block;font-family:monospace;font-size:11px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:1px 7px;color:#334155; }
.ip-chip.new { background:#fef3c7;border-color:#fde68a;color:#92400e; }
.ip-chip.blocked { background:#fee2e2;border-color:#fecaca;color:#991b1b; }
</style>

<div class="page-header">
  <h4><i class="fa fa-shield-halved text-primary"></i> &nbsp;Log Login</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Log Login</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- ── Stat cards 30 hari terakhir ── -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:16px;">
    <div style="background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:14px 16px;display:flex;align-items:center;gap:12px;">
      <div style="width:40px;height:40px;background:#d1fae5;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-check-circle" style="color:#059669;font-size:17px;"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;color:#1e293b;line-height:1;"><?= $stats['berhasil'] ?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Login Berhasil</div>
        <div style="font-size:10px;color:#94a3b8;">30 hari terakhir</div>
      </div>
    </div>
    <div style="background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:14px 16px;display:flex;align-items:center;gap:12px;">
      <div style="width:40px;height:40px;background:#fee2e2;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-times-circle" style="color:#dc2626;font-size:17px;"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;color:#dc2626;line-height:1;"><?= $stats['gagal'] ?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Login Gagal</div>
        <div style="font-size:10px;color:#94a3b8;">30 hari terakhir</div>
      </div>
    </div>
    <div style="background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:14px 16px;display:flex;align-items:center;gap:12px;">
      <div style="width:40px;height:40px;background:#fef3c7;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-lock" style="color:#d97706;font-size:17px;"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;color:#d97706;line-height:1;"><?= $stats['terkunci'] ?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Percobaan Terkunci</div>
        <div style="font-size:10px;color:#94a3b8;">30 hari terakhir</div>
      </div>
    </div>
    <div style="background:#fff;border:1px solid <?= !empty($blocked_ips)?'#fecaca':'#e8ecf0' ?>;border-radius:8px;padding:14px 16px;display:flex;align-items:center;gap:12px;">
      <div style="width:40px;height:40px;background:<?= !empty($blocked_ips)?'#fee2e2':'#f8fafc' ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-ban" style="color:<?= !empty($blocked_ips)?'#dc2626':'#94a3b8' ?>;font-size:17px;"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;color:<?= !empty($blocked_ips)?'#dc2626':'#1e293b' ?>;line-height:1;"><?= count($blocked_ips) ?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">IP Diblokir</div>
        <div style="font-size:10px;color:#94a3b8;">saat ini</div>
      </div>
    </div>
  </div>

  <!-- ── IP diblokir saat ini ── -->
  <?php if (!empty($blocked_ips)): ?>
  <div class="panel" style="border-left:3px solid #ef4444;margin-bottom:14px;">
    <div class="panel-hd">
      <h5><i class="fa fa-ban" style="color:#ef4444;"></i> &nbsp;IP Diblokir Saat Ini</h5>
    </div>
    <div class="panel-bd" style="display:flex;flex-wrap:wrap;gap:8px;">
      <?php foreach ($blocked_ips as $b): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:#fff1f2;border:1px solid #fecaca;border-radius:7px;">
        <div>
          <div class="ip-chip blocked"><?= htmlspecialchars($b['ip_address']) ?></div>
          <span style="font-size:11px;color:#94a3b8;margin-left:6px;"><?= $b['attempts'] ?>x gagal &nbsp;·&nbsp; sisa <?= $b['sisa_menit'] ?> menit</span>
        </div>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="unblock">
          <input type="hidden" name="ip" value="<?= htmlspecialchars($b['ip_address']) ?>">
          <button type="submit"
                  onclick="return confirm('Unblock IP <?= htmlspecialchars($b['ip_address']) ?>?')"
                  style="padding:4px 10px;background:#fff;border:1px solid #fecaca;border-radius:5px;font-size:11px;cursor:pointer;color:#dc2626;font-weight:600;">
            <i class="fa fa-unlock"></i> Unblock
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── IP mencurigakan (banyak gagal 24 jam) ── -->
  <?php if (!empty($top_fail_ip)): ?>
  <div class="panel" style="border-left:3px solid #f59e0b;margin-bottom:14px;">
    <div class="panel-hd">
      <h5><i class="fa fa-triangle-exclamation" style="color:#d97706;"></i> &nbsp;IP Mencurigakan (24 Jam Terakhir)</h5>
    </div>
    <div class="panel-bd" style="display:flex;flex-wrap:wrap;gap:8px;">
      <?php foreach ($top_fail_ip as $f): ?>
      <div style="padding:6px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;font-size:12px;">
        <span class="ip-chip"><?= htmlspecialchars($f['ip_address']) ?></span>
        <span style="color:#92400e;font-weight:700;margin-left:6px;"><?= $f['n'] ?>× gagal</span>
        <span style="color:#94a3b8;font-size:11px;"> &nbsp;·&nbsp; terakhir <?= date('H:i', strtotime($f['last_attempt'])) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Filter & Tabel ── -->
  <div class="panel">
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf" style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                 class="inp-search" placeholder="Cari user, IP…"
                 onchange="this.form.submit()">

          <select name="status" class="sel-filter" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="berhasil"  <?= $fs==='berhasil'?'selected':''  ?>>✅ Berhasil</option>
            <option value="gagal"     <?= $fs==='gagal'?'selected':''     ?>>❌ Gagal</option>
            <option value="terkunci"  <?= $fs==='terkunci'?'selected':''  ?>>🔒 Terkunci</option>
          </select>

          <select name="uid" class="sel-filter" onchange="this.form.submit()">
            <option value="">Semua User</option>
            <?php foreach ($user_list as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $fu==$u['id']?'selected':'' ?>><?= clean($u['nama']) ?></option>
            <?php endforeach; ?>
          </select>

          <input type="text" name="ip" value="<?= htmlspecialchars($fi) ?>"
                 class="sel-filter" placeholder="Filter IP…" style="width:130px;"
                 onchange="this.form.submit()">

          <input type="date" name="dari" value="<?= htmlspecialchars($fd_dari) ?>"
                 class="sel-filter" onchange="this.form.submit()">
          <span style="font-size:11px;color:#94a3b8;">s.d.</span>
          <input type="date" name="sampai" value="<?= htmlspecialchars($fd_sampai) ?>"
                 class="sel-filter" onchange="this.form.submit()">

          <?php if ($fs||$fu||$fi||$fd_dari||$fd_sampai||$search): ?>
          <a href="?" class="btn btn-default btn-sm" title="Reset filter"><i class="fa fa-times"></i></a>
          <?php endif; ?>
        </form>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="tbl-info"><?= $total ?> record</span>
        <!-- Export CSV -->
        <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>"
           class="btn btn-default btn-sm" style="border-color:#16a34a;color:#15803d;font-weight:600;">
          <i class="fa fa-file-csv"></i> Export CSV
        </a>
      </div>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Waktu</th>
            <th>User</th>
            <th>Input Username</th>
            <th>Status</th>
            <th>IP Address</th>
            <th>Perangkat</th>
            <th>Browser / OS</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
          <tr><td colspan="9" class="td-empty"><i class="fa fa-shield-halved"></i> Tidak ada data log</td></tr>
          <?php else:
            $no = $offset + 1;
            foreach ($logs as $l):
              $is_new = (bool)$l['is_new_ip'];
          ?>
          <tr style="<?= $l['status']==='terkunci'?'background:#fffbeb;':($is_new&&$l['status']==='berhasil'?'background:#fffaf0;':'') ?>">
            <td style="color:#cbd5e1;"><?= $no++ ?></td>

            <!-- Waktu -->
            <td style="white-space:nowrap;">
              <div style="font-size:12px;font-weight:600;color:#1e293b;"><?= date('d M Y', strtotime($l['created_at'])) ?></div>
              <div style="font-size:10.5px;color:#94a3b8;"><?= date('H:i:s', strtotime($l['created_at'])) ?></div>
            </td>

            <!-- User -->
            <td>
              <?php if ($l['user_nama']): ?>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs <?= $l['user_role']==='admin'?'av-purple':($l['user_role']==='teknisi'?'av-blue':($l['user_role']==='teknisi_ipsrs'?'av-green':'')) ?>">
                  <?= getInitials($l['user_nama']) ?>
                </div>
                <div>
                  <div style="font-size:12px;font-weight:600;"><?= clean($l['user_nama']) ?></div>
                  <div style="font-size:10px;color:#94a3b8;"><?= ucfirst($l['user_role'] ?? '') ?></div>
                </div>
              </div>
              <?php else: ?>
              <span style="color:#94a3b8;font-size:11px;font-style:italic;">— tidak dikenal —</span>
              <?php endif; ?>
            </td>

            <!-- Input username -->
            <td style="font-family:monospace;font-size:12px;color:#475569;"><?= clean($l['username_input']) ?></td>

            <!-- Status -->
            <td>
              <?php if ($l['status'] === 'berhasil'): ?>
                <span class="log-badge log-b"><i class="fa fa-check"></i> Berhasil</span>
                <?php if ($is_new): ?>
                <br><span style="font-size:10px;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:3px;font-weight:600;margin-top:3px;display:inline-block;">
                  <i class="fa fa-star"></i> IP Baru
                </span>
                <?php endif; ?>
              <?php elseif ($l['status'] === 'gagal'): ?>
                <span class="log-badge log-g"><i class="fa fa-times"></i> Gagal</span>
              <?php else: ?>
                <span class="log-badge log-t"><i class="fa fa-lock"></i> Terkunci</span>
              <?php endif; ?>
            </td>

            <!-- IP Address -->
            <td>
              <span class="ip-chip <?= $is_new&&$l['status']==='berhasil'?'new':'' ?>">
                <?= htmlspecialchars($l['ip_address']) ?>
              </span>
              <?php if ($is_new && $l['status'] === 'berhasil'): ?>
              <br><span style="font-size:9.5px;color:#d97706;">IP Baru</span>
              <?php endif; ?>
            </td>

            <!-- Perangkat -->
            <td>
              <?php
              $dev_icon = match($l['device_type'] ?? '') {
                  'Mobile'  => 'fa-mobile-screen',
                  'Tablet'  => 'fa-tablet-screen-button',
                  default   => 'fa-display',
              };
              ?>
              <span style="font-size:12px;color:#64748b;">
                <i class="fa <?= $dev_icon ?> device-icon"></i><?= htmlspecialchars($l['device_type'] ?? '—') ?>
              </span>
            </td>

            <!-- Browser / OS -->
            <td style="font-size:11px;color:#64748b;">
              <div><?= htmlspecialchars($l['browser'] ?? '—') ?></div>
              <div style="color:#94a3b8;"><?= htmlspecialchars($l['os'] ?? '—') ?></div>
            </td>

            <!-- Keterangan -->
            <td style="font-size:11px;color:<?= $l['status']==='gagal'?'#dc2626':'#64748b' ?>;">
              <?= htmlspecialchars($l['keterangan'] ?? '—') ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="tbl-footer">
      <span class="tbl-info">
        Menampilkan <?= $total ? min($offset+1,$total) : 0 ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?>
      </span>
      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pag-btn"><i class="fa fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php
        // Tampilkan max 7 halaman
        $p_start = max(1, $page-3);
        $p_end   = min($pages, $page+3);
        if ($p_start > 1) echo '<span class="pag-btn" style="pointer-events:none;">…</span>';
        for ($i = $p_start; $i <= $p_end; $i++):
        ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"
           class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor;
        if ($p_end < $pages) echo '<span class="pag-btn" style="pointer-events:none;">…</span>';
        ?>
        <?php if ($page < $pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pag-btn"><i class="fa fa-chevron-right"></i></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
// ── Export CSV ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Ambil semua data tanpa limit
    $st_exp = $pdo->prepare("
        SELECT l.created_at, u.nama AS user_nama, u.role AS user_role,
               l.username_input, l.status, l.ip_address,
               l.device_type, l.browser, l.os, l.keterangan, l.is_new_ip
        FROM login_log l
        LEFT JOIN users u ON u.id=l.user_id
        WHERE $wsql
        ORDER BY l.created_at DESC
    ");
    $st_exp->execute($params);
    $rows_exp = $st_exp->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="login_log_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['Waktu','Nama User','Role','Input Username','Status','IP Address','Perangkat','Browser','OS','Keterangan','IP Baru?']);
    foreach ($rows_exp as $r) {
        fputcsv($out, [
            $r['created_at'],
            $r['user_nama']    ?? '',
            $r['user_role']    ?? '',
            $r['username_input'],
            $r['status'],
            $r['ip_address'],
            $r['device_type']  ?? '',
            $r['browser']      ?? '',
            $r['os']           ?? '',
            $r['keterangan']   ?? '',
            $r['is_new_ip'] ? 'Ya' : 'Tidak',
        ]);
    }
    fclose($out);
    exit;
}
?>

<?php include '../includes/footer.php'; ?>