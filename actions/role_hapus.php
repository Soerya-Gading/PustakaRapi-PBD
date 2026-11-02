<?php
declare(strict_types=1);

/**
 * Action: Hapus Role
 * Menolak penghapusan jika role masih terikat pada akun pengguna.
 */

if (auth_role_name() !== 'Administrator') {
    flash('gagal', 'Hanya Administrator yang memiliki akses menghapus role.');
    redirect('?page=role');
}

$idrole = (int)($_POST['idrole'] ?? 0);
if ($idrole <= 0) {
    flash('gagal', 'ID role tidak valid.');
    redirect('?page=role');
}

$cek = $pdo->prepare('SELECT COUNT(*) FROM `user` WHERE idrole = ?');
$cek->execute([$idrole]);
$terpakai = (int)$cek->fetchColumn();

if ($terpakai > 0) {
    flash('gagal', 'Role tidak dapat dihapus karena masih digunakan oleh ' . $terpakai . ' akun pengguna.');
    redirect('?page=role');
}

try {
    $stmt = $pdo->prepare('DELETE FROM role WHERE idrole = ?');
    $stmt->execute([$idrole]);
    flash('ok', 'Role berhasil dihapus.');
} catch (PDOException $ex) {
    error_log('[Role Hapus Error] ' . $ex->getMessage());
    flash('gagal', 'Gagal menghapus role dari database.');
}

redirect('?page=role');

