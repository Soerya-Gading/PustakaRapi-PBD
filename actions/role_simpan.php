<?php
declare(strict_types=1);

/**
 * Action: Simpan / Ubah Role
 */

if (auth_role_name() !== 'Administrator') {
    flash('gagal', 'Hanya Administrator yang memiliki akses mengubah role.');
    redirect('?page=role');
}

$idrole    = (int)($_POST['idrole'] ?? 0);
$nama_role = sanitize_input($_POST['nama_role'] ?? '');

if ($nama_role === '') {
    flash('gagal', 'Nama role wajib diisi.');
    redirect('?page=role');
}

try {
    if ($idrole > 0) {
        $stmt = $pdo->prepare('UPDATE role SET nama_role = ? WHERE idrole = ?');
        $stmt->execute([$nama_role, $idrole]);
        flash('ok', 'Role "' . $nama_role . '" berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO role (nama_role) VALUES (?)');
        $stmt->execute([$nama_role]);
        flash('ok', 'Role baru "' . $nama_role . '" berhasil ditambahkan.');
    }
} catch (PDOException $ex) {
    error_log('[Role Simpan Error] ' . $ex->getMessage());
    flash('gagal', 'Gagal menyimpan role ke database.');
}

redirect('?page=role');

