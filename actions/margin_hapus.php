<?php
declare(strict_types=1);

/**
 * Action: Hapus Margin Penjualan
 * Menolak penghapusan jika margin sudah pernah digunakan pada transaksi penjualan.
 */

if (!auth_can_manage('margin')) {
    flash('gagal', 'Anda tidak memiliki hak akses untuk menghapus margin penjualan.');
    redirect('?page=margin');
}

$idmargin = (int)($_POST['idmargin_penjualan'] ?? 0);
if ($idmargin <= 0) {
    flash('gagal', 'ID margin tidak valid.');
    redirect('?page=margin');
}

// Cek apakah digunakan oleh tabel penjualan
$cek = $pdo->prepare('SELECT COUNT(*) FROM penjualan WHERE idmargin_penjualan = ?');
$cek->execute([$idmargin]);
$terpakai = (int)$cek->fetchColumn();

if ($terpakai > 0) {
    flash('gagal', 'Margin penjualan tidak dapat dihapus karena tercatat dalam ' . $terpakai . ' transaksi penjualan.');
    redirect('?page=margin');
}

try {
    $stmt = $pdo->prepare('DELETE FROM margin_penjualan WHERE idmargin_penjualan = ?');
    $stmt->execute([$idmargin]);
    flash('ok', 'Margin penjualan berhasil dihapus.');
} catch (PDOException $ex) {
    error_log('[Margin Hapus Error] ' . $ex->getMessage());
    flash('gagal', 'Terjadi kesalahan sistem saat menghapus margin.');
}

redirect('?page=margin');

