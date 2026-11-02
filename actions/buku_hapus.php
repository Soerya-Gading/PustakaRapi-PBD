<?php
declare(strict_types=1);

/**
 * Action: Hapus Buku
 * Menghapus data dari tabel master `barang`.
 * Dilindungi pengecekan foreign key (jika sudah ada relasi di pengadaan/penjualan/kartu_stok).
 */

if (!auth_can_manage('buku')) {
    flash('gagal', 'Anda tidak memiliki otorisasi untuk menghapus buku.');
    redirect('?page=buku');
}

$idbarang = (int)($_POST['idbarang'] ?? 0);
if ($idbarang <= 0) {
    flash('gagal', 'ID buku tidak valid.');
    redirect('?page=buku');
}

try {
    $stmt = $pdo->prepare('DELETE FROM barang WHERE idbarang = ?');
    $stmt->execute([$idbarang]);
    flash('ok', 'Buku ID #' . $idbarang . ' berhasil dihapus.');
} catch (PDOException $ex) {
    $kode = (int)($ex->errorInfo[1] ?? 0);
    if ($kode === 1451) {
        flash('gagal', 'Buku tidak dapat dihapus karena telah terhubung ke transaksi pengadaan, penerimaan, atau penjualan.');
    } else {
        error_log('[Buku Hapus Error] ' . $ex->getMessage());
        flash('gagal', 'Terjadi kesalahan sistem saat menghapus data buku.');
    }
}

redirect('?page=buku');

