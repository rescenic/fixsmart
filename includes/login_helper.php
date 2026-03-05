<?php
// includes/login_helper.php
// Khusus untuk pencatatan log login ke database.
// Rate limiting tetap ditangani oleh session di login.php.
// Tidak ada define() di sini agar tidak konflik dengan MAX_ATTEMPTS di login.php.

// ══════════════════════════════════════════════════════════════════════════
// Ambil IP address yang sebenarnya (handle proxy / Cloudflare / load balancer)
// ══════════════════════════════════════════════════════════════════════════
function getClientIP(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// ══════════════════════════════════════════════════════════════════════════
// Parse User-Agent → device type, browser, OS
// ══════════════════════════════════════════════════════════════════════════
function parseUserAgent(string $ua): array {
    $device  = 'Desktop';
    $browser = 'Unknown';
    $os      = 'Unknown';

    if (preg_match('/Mobile|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
        $device = 'Mobile';
    } elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
        $device = 'Tablet';
    }

    if      (preg_match('/Edg\/([0-9]+)/i',    $ua, $m)) $browser = 'Edge '.$m[1];
    elseif  (preg_match('/OPR\/([0-9]+)/i',    $ua, $m)) $browser = 'Opera '.$m[1];
    elseif  (preg_match('/Chrome\/([0-9]+)/i', $ua, $m)) $browser = 'Chrome '.$m[1];
    elseif  (preg_match('/Firefox\/([0-9]+)/i',$ua, $m)) $browser = 'Firefox '.$m[1];
    elseif  (preg_match('/Safari\//i', $ua) && !preg_match('/Chrome/i', $ua)) $browser = 'Safari';
    elseif  (preg_match('/MSIE ([0-9]+)|Trident/i', $ua, $m)) $browser = 'IE '.($m[1] ?? '11');

    if      (preg_match('/Windows NT 10/i', $ua))           $os = 'Windows 10/11';
    elseif  (preg_match('/Windows NT 6\.3/i', $ua))         $os = 'Windows 8.1';
    elseif  (preg_match('/Windows NT 6\.1/i', $ua))         $os = 'Windows 7';
    elseif  (preg_match('/Windows/i', $ua))                 $os = 'Windows';
    elseif  (preg_match('/Android ([0-9.]+)/i', $ua, $m))  $os = 'Android '.$m[1];
    elseif  (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m))$os = 'iOS '.str_replace('_','.',$m[1]);
    elseif  (preg_match('/iPad.*OS ([0-9_]+)/i', $ua, $m)) $os = 'iPadOS '.str_replace('_','.',$m[1]);
    elseif  (preg_match('/Mac OS X ([0-9_]+)/i', $ua, $m)) $os = 'macOS '.str_replace('_','.',$m[1]);
    elseif  (preg_match('/Ubuntu/i', $ua))                  $os = 'Ubuntu';
    elseif  (preg_match('/Linux/i', $ua))                   $os = 'Linux';

    return compact('device', 'browser', 'os');
}

// ══════════════════════════════════════════════════════════════════════════
// Cek apakah IP ini baru untuk user tertentu (belum pernah login berhasil)
// ══════════════════════════════════════════════════════════════════════════
function isNewIPForUser(PDO $pdo, int $user_id, string $ip): bool {
    try {
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM login_log
            WHERE user_id = ? AND ip_address = ? AND status = 'berhasil'
        ");
        $st->execute([$user_id, $ip]);
        return (int)$st->fetchColumn() === 0;
    } catch (Exception $e) {
        return false;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Tulis satu record ke tabel login_log
// ══════════════════════════════════════════════════════════════════════════
function recordLoginLog(PDO $pdo, array $data): void {
    try {
        $pdo->prepare("
            INSERT INTO login_log
                (user_id, username_input, status, ip_address, user_agent,
                 device_type, browser, os, keterangan, is_new_ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $data['user_id']       ?? null,
            $data['username_input'],
            $data['status'],
            $data['ip_address'],
            $data['user_agent']    ?? null,
            $data['device_type']   ?? null,
            $data['browser']       ?? null,
            $data['os']            ?? null,
            $data['keterangan']    ?? null,
            $data['is_new_ip']     ?? 0,
        ]);
    } catch (Exception $e) {
        error_log('[login_log] ' . $e->getMessage());
    }
}