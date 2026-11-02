<?php
declare(strict_types=1);

/**
 * Action: Simpan Transaksi Pengadaan Baru
 * Menyimpan header ke tabel `pengadaan` dan multi-item ke `detail_pengadaan` secara atomik.
 */

if (!auth_can_manage('pengadaan')) {
    flash('gagal', 'Anda tidak memiliki hak akses untuk membuat transaksi pengadaan.');
    redirect('?page=pengadaan');
}

$idvendor = (int)($_POST['idvendor'] ?? 0);
$iduser   = auth_id() ?? 1;
$itemsRaw = $_POST['items'] ?? [];

if ($idvendor <= 0) {
    flash('gagal', 'Vendor pengadaan wajib dipilih.');
    redirect('?page=pengadaan');
}

if (!is_array($itemsRaw) || empty($itemsRaw)) {
    flash('gagal', 'Pilih minimal satu buku dalam daftar pengadaan.');
    redirect('?page=pengadaan');
}

// Validasi vendor
$cekVendor = $pdo->prepare('SELECT 1 FROM vendor WHERE idvendor = ?');
$cekVendor->execute([$idvendor]);
if (!$cekVendor->fetchColumn()) {
    flash('gagal', 'Vendor yang dipilih tidak valid.');
    redirect('?page=pengadaan');
}

// Bersihkan dan validasi baris detail
$validItems = [];
$subtotalNilai = 0;

foreach ($itemsRaw as $row) {
    $idbarang = (int)($row['idbarang'] ?? 0);
    $jumlah   = (int)($row['jumlah'] ?? 0);
    $harga    = (int)($row['harga_satuan'] ?? 0);

    if ($idbarang <= 0 || $jumlah <= 0 || $harga < 0) {
        continue;
    }

    $subtotal = $jumlah * $harga;
    $subtotalNilai += $subtotal;

    $validItems[] = [
        'idbarang'     => $idbarang,
        'jumlah'       => $jumlah,
        'harga_satuan' => $harga,
        'sub_total'    => $subtotal,
    ];
}

if (empty($validItems)) {
    flash('gagal', 'Baris rincian buku tidak valid atau jumlah bernilai 0.');
    redirect('?page=pengadaan');
}

// Hitung PPN 11% dan Total
$totalCalc = BackendAdapter::hitungTotal($pdo, $subtotalNilai, 11.0);
$ppn = $totalCalc['ppn'];
$totalNilai = $totalCalc['total'];

$pdo->beginTransaction();
try {
    // 1. Insert header pengadaan
    $stmt = $pdo->prepare('
        INSERT INTO pengadaan (timestamp, user_iduser, status, vendor_idvendor, subtotal_nilai, ppn, total_nilai)
        VALUES (NOW(), ?, "P", ?, ?, ?, ?)
    ');
    $stmt->execute([$iduser, $idvendor, $subtotalNilai, $ppn, $totalNilai]);
    $idpengadaan = (int)$pdo->lastInsertId();

    // 2. Insert detail pengadaan
    $stmtDetail = $pdo->prepare('
        INSERT INTO detail_pengadaan (harga_satuan, jumlah, sub_total, idbarang, idpengadaan)
        VALUES (?, ?, ?, ?, ?)
    ');

    foreach ($validItems as $item) {
        $stmtDetail->execute([
            $item['harga_satuan'],
            $item['jumlah'],
            $item['sub_total'],
            $item['idbarang'],
            $idpengadaan,
        ]);
    }

    $pdo->commit();
    flash('ok', 'Transaksi Pengadaan PO #' . $idpengadaan . ' berhasil dibuat dengan status Pending.');
} catch (Exception $ex) {
    $pdo->rollBack();
    error_log('[Pengadaan Simpan Error] ' . $ex->getMessage());
    flash('gagal', 'Gagal memproses pengadaan: ' . $ex->getMessage());
}

redirect('?page=pengadaan');

