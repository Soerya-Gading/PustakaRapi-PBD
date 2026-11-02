<?php
declare(strict_types=1);

/**
 * Action: Hapus Vendor
 * Menolak penghapusan secara aman jika vendor sudah pernah terlibat dalam pengadaan.
 */

if (!auth_can_manage('vendor')) {
    flash('gagal', 'Anda tidak memiliki otorisasi untuk menghapus vendor.');
    redirect('?page=vendor');
}

$idvendor = (int)($_POST['idvendor'] ?? 0);
if ($idvendor <= 0) {
    flash('gagal', 'ID vendor tidak valid.');
    redirect('?page=vendor');
}

// Cek keterkaitan di tabel pengadaan
$cek = $pdo->prepare('SELECT COUNT(*) FROM pengadaan WHERE vendor_idvendor = ?');
$cek->execute([$idvendor]);
$terpakai = (int)$cek->fetchColumn();

if ($terpakai > 0) {
    flash('gagal', 'Vendor tidak dapat dihapus karena tercatat dalam ' . $terpakai . ' berkas pengadaan.');
    redirect('?page=vendor');
}

try {
    $stmt = $pdo->prepare('DELETE FROM vendor WHERE idvendor = ?');
    $stmt->execute([$idvendor]);
    flash('ok', 'Vendor berhasil dihapus.');
} catch (PDOException $ex) {
    $kode = (int)($ex->errorInfo[1] ?? 0);
    if ($kode === 1451) {
        flash('gagal', 'Penghapusan ditolak database: Data vendor masih terhubung ke tabel relasional.');
    } else {
        error_log('[Vendor Hapus Error] ' . $ex->getMessage());
        flash('gagal', 'Terjadi kesalahan sistem saat menghapus vendor.');
    }
}

redirect('?page=vendor');

