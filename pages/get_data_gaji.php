<?php
// ajax/get_data_gaji.php
session_start();
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['error' => 'invalid']); exit; }

try {
    $dg = $pdo->prepare("SELECT dg.*, p.kode AS ptkp_kode, p.nama AS ptkp_nama
                          FROM data_gaji dg
                          LEFT JOIN master_pph21_ptkp p ON p.id = dg.ptkp_id
                          WHERE dg.id = ?");
    $dg->execute([$id]);
    $row = $dg->fetch(PDO::FETCH_ASSOC);

    if (!$row) { echo json_encode(['error' => 'not found']); exit; }

    // Detail penerimaan
    $sp = $pdo->prepare("SELECT d.penerimaan_id, d.nilai, p.kode, p.nama
                          FROM data_gaji_detail d
                          LEFT JOIN master_penerimaan p ON p.id = d.penerimaan_id
                          WHERE d.data_gaji_id = ? AND d.tipe = 'penerimaan'
                          ORDER BY d.id");
    $sp->execute([$id]);
    $penerimaan = $sp->fetchAll(PDO::FETCH_ASSOC);

    // Detail potongan
    $st = $pdo->prepare("SELECT d.potongan_id, d.nilai, p.kode, p.nama
                          FROM data_gaji_detail d
                          LEFT JOIN master_potongan p ON p.id = d.potongan_id
                          WHERE d.data_gaji_id = ? AND d.tipe = 'potongan'
                          ORDER BY d.id");
    $st->execute([$id]);
    $potongan = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'id'             => $row['id'],
        'user_id'        => $row['user_id'],
        'gaji_pokok'     => $row['gaji_pokok'],
        'ptkp_id'        => $row['ptkp_id'],
        'ptkp_kode'      => $row['ptkp_kode'],
        'pph21'          => $row['pph21'],
        'bank_nama'      => $row['bank_nama'],
        'bank_rekening'  => $row['bank_rekening'],
        'bank_atas_nama' => $row['bank_atas_nama'],
        'catatan'        => $row['catatan'],
        'penerimaan'     => $penerimaan,
        'potongan'       => $potongan,
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}