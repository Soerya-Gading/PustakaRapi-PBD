<?php
declare(strict_types=1);

/**
 * Action: Hapus Satuan
 * Menolak penghapusan secara aman jika satuan sedang digunakan oleh buku.
 */

if (!auth_can_manage('satuan')) {
    flash('gagal', 'Anda tidak memiliki otorisasi untuk menghapus satuan.');
    redirect('?page=satuan');
}

$idsatuan = (int)($_POST['idsatuan'] ?? 0);
if ($idsatuan <= 0) {
    flash('gagal', 'ID satuan tidak valid.');
    redirect('?page=satuan');
}

// Cek apakah masih digunakan tabel barang
$cek = $pdo->prepare('SELECT COUNT(*) FROM barang WHERE idsatuan = ?');
$cek->execute([$idsatuan]);
$terpakai = (int)$cek->fetchColumn();

if ($terpakai > 0) {
    flash('gagal', 'Satuan tidak dapat dihapus karena masih digunakan oleh ' . $terpakai . ' buku di katalog.');
    redirect('?page=satuan');
}

try {
    $stmt = $pdo->prepare('DELETE FROM satuan WHERE idsatuan = ?');
    $stmt->execute([$idsatuan]);
    flash('ok', 'Satuan berhasil dihapus.');
} catch (PDOException $ex) {
    $kode = (int)($ex->errorInfo[1] ?? 0);
    if ($kode === 1451) {
        flash('gagal', 'Penghapusan ditolak database: Satuan ini memiliki keterkaitan dengan rekaman lain.');
    } else {
        error_log('[Satuan Hapus Error] ' . $ex->getMessage());
        flash('gagal', 'Terjadi kesalahan sistem saat menghapus satuan.');
    }
}

redirect('?page=satuan');

