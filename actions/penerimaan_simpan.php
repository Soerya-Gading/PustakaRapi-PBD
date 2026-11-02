<?php
declare(strict_types=1);

/**
 * Action: Simpan Penerimaan Barang
 * Memproses penerimaan pengadaan dan memperbarui kartu stok melalui BackendAdapter.
 */

if (!auth_can_manage('penerimaan')) {
    flash('gagal', 'Anda tidak memiliki hak akses untuk memproses penerimaan barang.');
    redirect('?page=penerimaan');
}

$idpengadaan = (int)($_POST['idpengadaan'] ?? 0);
$iduser      = auth_id() ?? 1;
$itemsRaw    = $_POST['items'] ?? [];

if ($idpengadaan <= 0) {
    flash('gagal', 'Nomor pengadaan wajib dipilih.');
    redirect('?page=penerimaan');
}

if (!is_array($itemsRaw) || empty($itemsRaw)) {
    flash('gagal', 'Tidak ada data buku yang diterima.');
    redirect('?page=penerimaan');
}

// Bersihkan item
$itemsTerima = [];
foreach ($itemsRaw as $row) {
    $idbarang = (int)($row['idbarang'] ?? 0);
    $qty      = (int)($row['jumlah_terima'] ?? 0);
    $harga    = (int)($row['harga_satuan'] ?? 0);

    if ($idbarang <= 0 || $qty <= 0) {
        continue;
    }

    $itemsTerima[] = [
        'idbarang'     => $idbarang,
        'jumlah_terima'=> $qty,
        'harga_satuan' => $harga,
    ];
}

if (empty($itemsTerima)) {
    flash('gagal', 'Jumlah barang yang diterima harus lebih dari 0.');
    redirect('?page=penerimaan');
}

// Panggil BackendAdapter
$hasil = BackendAdapter::terimaPengadaan($pdo, $idpengadaan, $iduser, $itemsTerima);

if ($hasil['status'] === 'berhasil') {
    flash('ok', $hasil['pesan']);
} else {
    flash('gagal', $hasil['pesan']);
}

redirect('?page=penerimaan');

