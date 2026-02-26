<?php
/**
 * debug_maintenance.php — v2
 * HAPUS file ini setelah selesai debug!
 * Akses: http://localhost/fixsmart/pages/debug_maintenance.php?id=5
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<pre style="font-family:monospace;font-size:12px;padding:16px;background:#f8fafc;">';
echo '<strong style="font-size:14px;">🔍 DIAGNOSIS v2 — Cek Session Conflict</strong>' . "\n\n";

// ① Status session SEBELUM config
echo "▶ Status session SEBELUM require config.php:\n";
$status_before = session_status();
echo "   session_status() = $status_before  (1=belum, 2=sudah aktif)\n\n";

// ② Load config
require_once '../config.php';

// ③ Status SETELAH config
echo "▶ Status session SETELAH require config.php:\n";
echo "   session_status() = " . session_status() . "\n";
echo "   _SESSION[user_id]   = " . ($_SESSION['user_id']   ?? '(kosong)') . "\n";
echo "   _SESSION[user_nama] = " . ($_SESSION['user_nama'] ?? '(kosong)') . "\n";
echo "   _SESSION[user_role] = " . ($_SESSION['user_role'] ?? '(kosong)') . "\n\n";

// ④ Apakah config.php panggil session_start?
echo "▶ Cek apakah config.php mengandung session_start:\n";
$cfg = file_get_contents(__DIR__ . '/../config.php');
if (strpos($cfg, 'session_start') !== false) {
    echo "   ⚠️  YA — config.php sudah panggil session_start()\n";
    echo "   Jadi jangan panggil session_start() lagi di file page.\n\n";
} else {
    echo "   Tidak — config.php tidak panggil session_start()\n\n";
}

// ⑤ Cookie session
echo "▶ Cookie PHPSESSID di browser:\n";
if (isset($_COOKIE['PHPSESSID'])) {
    echo "   ✅ Ada: " . $_COOKIE['PHPSESSID'] . "\n\n";
} else {
    echo "   ❌ Tidak ada — kemungkinan kamu buka tab baru / incognito / belum login\n\n";
}

// ⑥ Test query
$id = (int)($_GET['id'] ?? 0);
echo "▶ Test query maintenance id=$id:\n";
if ($id && isset($pdo)) {
    try {
        $r = $pdo->prepare("SELECT id,no_maintenance,aset_nama,status FROM maintenance_it WHERE id=?");
        $r->execute([$id]);
        $d = $r->fetch();
        if ($d) echo "   ✅ Ditemukan: {$d['no_maintenance']} | {$d['status']}\n";
        else    echo "   ⚠️  Tidak ditemukan id=$id\n";
    } catch(PDOException $e) {
        echo "   ❌ " . $e->getMessage() . "\n";
    }
} else {
    echo "   Lewat (id=0 atau \$pdo tidak ada)\n";
}

echo "\n═══════════════════════════════════\n";
echo "KESIMPULAN:\n";
if (empty($_SESSION['user_id'])) {
    echo "❌ SESSION KOSONG — Penyebab white screen sudah ketemu!\n\n";
    echo "Artinya: saat kamu klik tombol cetak dari tabel,\n";
    echo "browser membuka TAB BARU dan session tidak terbawa.\n\n";
    echo "SOLUSI YANG SUDAH DITERAPKAN DI cetak_maintenance.php TERBARU:\n";
    echo "- session_status() dicek dulu sebelum session_start()\n";
    echo "- Tidak pakai requireLogin() yang bisa redirect silent\n\n";
    echo "PASTIKAN: Login dulu di browser, lalu klik cetak dari halaman maintenance_it.\n";
} else {
    echo "✅ Session OK. Masalah bukan di session.\n";
}
echo '</pre>';