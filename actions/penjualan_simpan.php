<?php
declare(strict_types=1);

/**
 * Action: Simpan Transaksi Penjualan Kasir (POS)
 * Menggunakan BackendAdapter::simpanPenjualan() untuk memvalidasi stok dan menyimpan
 * data secara atomik ke tabel penjualan, detail_penjualan, dan kartu_stok.
 */

if (!auth_can_manage('penjualan')) {
    flash('gagal', 'Anda tidak memiliki hak akses untuk memproses transaksi kasir.');
    redirect('?page=penjualan');
}

$iduser   = auth_id() ?? 1;
$idmargin = (int)($_POST['idmargin_penjualan'] ?? 0);
$itemsRaw = $_POST['items'] ?? [];

if ($idmargin <= 0) {
    flash('gagal', 'Margin penjualan wajib dipilih.');
    redirect('?page=penjualan');
}

if (!is_array($itemsRaw) || empty($itemsRaw)) {
    flash('gagal', 'Keranjang kasir kosong. Pilih minimal satu buku.');
    redirect('?page=penjualan');
}

$items = [];
foreach ($itemsRaw as $row) {
    $idbarang = (int)($row['idbarang'] ?? 0);
    $qty      = (int)($row['jumlah'] ?? 0);
    $harga    = (int)($row['harga_satuan'] ?? 0);

    if ($idbarang <= 0 || $qty <= 0) {
        continue;
    }

    $subtotal = $qty * $harga;
    $items[] = [
        'idbarang'     => $idbarang,
        'jumlah'       => $qty,
        'harga_satuan' => $harga,
        'subtotal'     => $subtotal,
    ];
}

if (empty($items)) {
    flash('gagal', 'Data keranjang kasir tidak valid.');
    redirect('?page=penjualan');
}

// Eksekusi transaksi via BackendAdapter
$hasil = BackendAdapter::simpanPenjualan($pdo, $iduser, $idmargin, $items);

if ($hasil['status'] === 'berhasil') {
    flash('ok', $hasil['pesan']);
    // Arahkan kembali dengan flag receipt ID agar kasir bisa mencetak struk
    redirect('?page=penjualan&struk=' . (int)$hasil['idpenjualan']);
} elseif ($hasil['status'] === 'stok_tidak_cukup') {
    flash('gagal', 'Transaksi Ditolak: ' . $hasil['pesan']);
    redirect('?page=penjualan');
} else {
    flash('gagal', 'Gagal memproses penjualan: ' . ($hasil['pesan'] ?? 'Terjadi kesalahan internal.'));
    redirect('?page=penjualan');
}

