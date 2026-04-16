<?php
// ajax/get_data_gaji.php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'keuangan'])) {
    echo json_encode(['error' => 'Akses ditolak']); exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'ID tidak valid']); exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM data_gaji WHERE id = ?");
    $stmt->execute([$id]);
    $dg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dg) {
        echo json_encode(['error' => 'Data tidak ditemukan']); exit;
    }

    // Detail penerimaan
    $s_pen = $pdo->prepare("
        SELECT dgd.penerimaan_id, dgd.nilai, mp.kode, mp.nama
        FROM data_gaji_detail dgd
        LEFT JOIN master_penerimaan mp ON mp.id = dgd.penerimaan_id
        WHERE dgd.data_gaji_id = ? AND dgd.tipe = 'penerimaan'
        ORDER BY mp.kode ASC
    ");
    $s_pen->execute([$id]);
    $dg['penerimaan'] = $s_pen->fetchAll(PDO::FETCH_ASSOC);

    // Detail potongan
    $s_pot = $pdo->prepare("
        SELECT dgd.potongan_id, dgd.nilai, mp.kode, mp.nama
        FROM data_gaji_detail dgd
        LEFT JOIN master_potongan mp ON mp.id = dgd.potongan_id
        WHERE dgd.data_gaji_id = ? AND dgd.tipe = 'potongan'
        ORDER BY mp.kode ASC
    ");
    $s_pot->execute([$id]);
    $dg['potongan'] = $s_pot->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($dg);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}