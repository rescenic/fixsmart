<?php
// pages/ajax_berkas.php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole(['admin', 'hrd'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$uid = (int)($_GET['user_id'] ?? 0);
if (!$uid) { echo json_encode([]); exit; }

try {
    $rows = $pdo->prepare("
        SELECT b.id, b.jenis_berkas_id, b.nama_file, b.nama_asli,
               b.ukuran, b.mime_type, b.keterangan,
               b.tgl_dokumen, b.tgl_exp, b.status_verif,
               b.catatan_verif, b.created_at
        FROM berkas_karyawan b
        WHERE b.user_id = ?
        ORDER BY b.jenis_berkas_id
    ");
    $rows->execute([$uid]);
    $all = $rows->fetchAll(PDO::FETCH_ASSOC);

    // Key by jenis_berkas_id untuk lookup di JS
    $result = [];
    foreach ($all as $row) {
        $result[$row['jenis_berkas_id']] = $row;
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}