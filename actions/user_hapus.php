<?php
declare(strict_types=1);

/**
 * Action: Hapus Pengguna
 */

if (auth_role_name() !== 'Administrator') {
    flash('gagal', 'Hanya Administrator yang memiliki wewenang menghapus pengguna.');
    redirect('?page=user');
}

$iduser = (int)($_POST['iduser'] ?? 0);
if ($iduser <= 0) {
    flash('gagal', 'ID pengguna tidak valid.');
    redirect('?page=user');
}

if ($iduser === auth_id()) {
    flash('gagal', 'Anda tidak dapat menghapus akun Anda sendiri saat sedang masuk.');
    redirect('?page=user');
}

try {
    $stmt = $pdo->prepare('DELETE FROM `user` WHERE iduser = ?');
    $stmt->execute([$iduser]);
    flash('ok', 'Pengguna berhasil dihapus.');
} catch (PDOException $ex) {
    $kode = (int)($ex->errorInfo[1] ?? 0);
    if ($kode === 1451) {
        flash('gagal', 'Pengguna tidak dapat dihapus karena telah tercatat dalam histori transaksi sistem.');
    } else {
        error_log('[User Hapus Error] ' . $ex->getMessage());
        flash('gagal', 'Terjadi kesalahan sistem saat menghapus data pengguna.');
    }
}

redirect('?page=user');

