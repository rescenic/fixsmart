<?php
// pages/ajax_sdm.php
session_start();
require_once '../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if (!hasRole(['admin', 'hrd'])) {
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

$uid = (int)($_GET['user_id'] ?? 0);
if (!$uid) {
    echo json_encode(['error' => 'user_id tidak valid']);
    exit;
}

$row = null;
try {
    $st = $pdo->prepare("SELECT * FROM sdm_karyawan WHERE user_id = ? LIMIT 1");
    $st->execute([$uid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$row) {
    echo json_encode((object)[]);
    exit;
}

$date_fields = [
    'tgl_lahir','tgl_masuk','tgl_kontrak_mulai','tgl_kontrak_selesai',
    'tgl_pengangkatan','tgl_exp_str','tgl_terbit_str',
    'tgl_exp_sip','tgl_terbit_sip','tgl_exp_sik','tgl_resign',
];
foreach ($date_fields as $f) {
    if (isset($row[$f])) {
        $row[$f] = (!empty($row[$f]) && $row[$f] !== '0000-00-00')
            ? date('Y-m-d', strtotime($row[$f])) : '';
    }
}

foreach (['id','user_id','updated_by','created_at','updated_at'] as $k) {
    unset($row[$k]);
}

foreach ($row as $k => $v) {
    if ($v === null) $row[$k] = '';
}

echo json_encode($row, JSON_UNESCAPED_UNICODE);
exit;