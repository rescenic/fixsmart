<?php
// buat_folder.php — taruh di fixsmart/ (root project), jalankan SEKALI lalu HAPUS
$root = __DIR__; // = /Applications/XAMPP/xamppfiles/htdocs/fixsmart

$folders = [
    $root . '/uploads/',
    $root . '/uploads/tiket_foto/',
    $root . '/uploads/tiket_ipsrs_foto/',
];

echo "<pre>";
foreach ($folders as $dir) {
    if (is_dir($dir)) {
        echo "✅ Sudah ada  : $dir\n";
    } else {
        $ok = mkdir($dir, 0777, true);
        echo ($ok ? "✅ Dibuat     : " : "❌ GAGAL buat : ") . "$dir\n";
    }
    // Paksa permission
    chmod($dir, 0777);
    echo "   Writable   : " . (is_writable($dir) ? "YA ✅" : "TIDAK ❌") . "\n\n";
}

// Test tulis file
$test = $root . '/uploads/tiket_ipsrs_foto/_test.txt';
file_put_contents($test, 'ok');
if (file_exists($test)) {
    echo "✅ Test tulis ke tiket_ipsrs_foto: BERHASIL\n";
    unlink($test);
} else {
    echo "❌ Test tulis GAGAL — jalankan di terminal:\n";
    echo "   chmod -R 777 " . $root . "/uploads/\n";
}
echo "</pre>";
echo "<p style='color:red'><b>Hapus file buat_folder.php ini sekarang!</b></p>";