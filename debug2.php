<?php
// debug2.php — taruh di ROOT project, buka di browser
// Tidak butuh config.php atau session
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Upload IPSRS</h2>";
echo "<style>body{font-family:monospace;padding:20px;font-size:13px;} .ok{color:green} .err{color:red} .warn{color:orange} table{border-collapse:collapse;margin-bottom:20px;} td,th{border:1px solid #ccc;padding:6px 10px;}</style>";

// ── 1. PHP Config ─────────────────────────────────────────────────
echo "<h3>1. PHP Upload Config</h3><table>";
foreach (['file_uploads','upload_max_filesize','post_max_size','max_file_uploads','upload_tmp_dir'] as $k) {
    $v = ini_get($k);
    echo "<tr><td>$k</td><td><b>".($v===''?'(kosong/default)':$v)."</b></td></tr>";
}
echo "</table>";

// ── 2. Path & Folder ─────────────────────────────────────────────
echo "<h3>2. Path & Folder</h3><table>";
$root = __DIR__;
echo "<tr><td>__DIR__ (root project)</td><td>$root</td></tr>";

$folders = [
    'uploads/'                  => $root.'/uploads/',
    'uploads/tiket_foto/'       => $root.'/uploads/tiket_foto/',
    'uploads/tiket_ipsrs_foto/' => $root.'/uploads/tiket_ipsrs_foto/',
];
foreach ($folders as $label => $path) {
    $e = is_dir($path)      ? "<span class='ok'>✅ ada</span>"       : "<span class='err'>❌ tidak ada</span>";
    $w = is_writable($path) ? "<span class='ok'>✅ writable</span>"  : "<span class='err'>❌ tidak writable</span>";
    echo "<tr><td>$label</td><td>$e</td><td>$w</td></tr>";
}
echo "</table>";

// ── 3. Buat folder & test tulis ───────────────────────────────────
echo "<h3>3. Buat Folder & Test Tulis</h3>";
$dir = $root.'/uploads/tiket_ipsrs_foto/';
if (!is_dir($dir)) {
    echo is_dir(dirname($dir)) ? "" : "<span class='warn'>⚠️ Parent folder uploads/ juga tidak ada!</span><br>";
    $ok = @mkdir($dir, 0755, true);
    echo $ok ? "<span class='ok'>✅ Folder berhasil dibuat</span><br>" : "<span class='err'>❌ Gagal buat folder — cek permission folder uploads/</span><br>";
}
$tf = $dir.'_test_'.time().'.txt';
$ok = @file_put_contents($tf, 'test');
if ($ok !== false) {
    echo "<span class='ok'>✅ Berhasil tulis file ke folder</span><br>";
    unlink($tf);
} else {
    echo "<span class='err'>❌ GAGAL tulis file! Jalankan di server: chmod -R 755 uploads/</span><br>";
}

// ── 4. Test Upload (POST) ─────────────────────────────────────────
echo "<h3>4. Test Upload Langsung</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<table><tr><th>Info</th><th>Nilai</th></tr>";
    echo "<tr><td>FILES tersedia?</td><td>".(empty($_FILES)?'<span class="err">❌ KOSONG — form tidak mengirim file</span>':'<span class="ok">✅ Ada</span>')."</td></tr>";

    if (!empty($_FILES['foto'])) {
        $f = $_FILES['foto'];
        $err_msg = [0=>'UPLOAD_ERR_OK',1=>'ERR_INI_SIZE',2=>'ERR_FORM_SIZE',3=>'ERR_PARTIAL',4=>'ERR_NO_FILE',6=>'ERR_NO_TMP_DIR',7=>'ERR_CANT_WRITE',8=>'ERR_EXTENSION'];
        echo "<tr><td>nama</td><td>".$f['name'][0]."</td></tr>";
        echo "<tr><td>ukuran</td><td>".number_format($f['size'][0])." bytes</td></tr>";
        echo "<tr><td>error</td><td>".($f['error'][0]==0?"<span class='ok'>0 = OK</span>":"<span class='err'>".$f['error'][0]." = ".($err_msg[$f['error'][0]]??'unknown')."</span>")."</td></tr>";

        if ($f['error'][0] === 0) {
            $mime = mime_content_type($f['tmp_name'][0]);
            echo "<tr><td>MIME</td><td>$mime</td></tr>";
            $dest = $dir.'test_upload_'.time().'.'.pathinfo($f['name'][0],PATHINFO_EXTENSION);
            $ok   = move_uploaded_file($f['tmp_name'][0], $dest);
            echo "<tr><td>move_uploaded_file</td><td>".($ok?"<span class='ok'>✅ BERHASIL<br>$dest</span>":"<span class='err'>❌ GAGAL</span>")."</td></tr>";
            if ($ok) { echo "<tr><td>Cleanup</td><td>"; unlink($dest); echo "<span class='ok'>✅ file test dihapus</span></td></tr>"; }
        }
    } else {
        echo "<tr><td>foto[]</td><td><span class='err'>❌ Tidak ada di \$_FILES</span></td></tr>";
        echo "<tr><td>RAW \$_FILES</td><td><pre>".print_r($_FILES,true)."</pre></td></tr>";
    }
    echo "</table>";
}
?>
<form method="POST" enctype="multipart/form-data" style="background:#f0f9ff;border:1px solid #93c5fd;padding:16px;border-radius:8px;">
    <p><b>Pilih foto dan klik Upload:</b></p>
    <input type="file" name="foto[]" accept="image/*" required>
    <br><br>
    <button type="submit" style="background:#2563eb;color:#fff;padding:8px 24px;border:none;border-radius:5px;cursor:pointer;font-size:14px;">
        Upload Test
    </button>
</form>
<hr>
<p style="color:red"><b>⚠️ Hapus file debug2.php ini setelah selesai!</b></p>