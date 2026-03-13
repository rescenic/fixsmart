<?php
// pages/ajax_sdm.php  — COMPLETE VERSION
session_start();
require_once '../config.php';
requireLogin();

if (!hasRole(['admin', 'hrd'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

header('Content-Type: application/json');

$uid = (int)($_GET['user_id'] ?? 0);
if (!$uid) {
    echo json_encode(null);
    exit;
}

try {
    $stm = $pdo->prepare("SELECT * FROM sdm_karyawan WHERE user_id = ? LIMIT 1");
    $stm->execute([$uid]);
    $row = $stm->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Belum ada data, kembalikan null
        echo json_encode(null);
        exit;
    }

    // Format tanggal agar sesuai input type="date" (Y-m-d)
    $date_fields = [
        'tgl_lahir', 'tgl_masuk', 'tgl_kontrak_mulai', 'tgl_kontrak_selesai',
        'tgl_pengangkatan', 'tgl_exp_str', 'tgl_exp_sip', 'tgl_exp_sik'
    ];
    foreach ($date_fields as $f) {
        if (!empty($row[$f]) && $row[$f] !== '0000-00-00') {
            $row[$f] = date('Y-m-d', strtotime($row[$f]));
        } else {
            $row[$f] = '';
        }
    }

    echo json_encode($row);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}