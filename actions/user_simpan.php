<?php
declare(strict_types=1);

/**
 * Action: Simpan / Ubah Pengguna
 * Menjamin keamanan password dengan password_hash() dan prepared statement.
 */

if (auth_role_name() !== 'Administrator') {
    flash('gagal', 'Hanya Administrator yang memiliki wewenang mengelola pengguna.');
    redirect('?page=user');
}

$iduser   = (int)($_POST['iduser'] ?? 0);
$username = strtolower(sanitize_input($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$idrole   = (int)($_POST['idrole'] ?? 0);

if ($username === '') {
    flash('gagal', 'Username wajib diisi.');
    redirect('?page=user');
}

if ($idrole <= 0) {
    flash('gagal', 'Role otorisasi wajib dipilih.');
    redirect('?page=user');
}

// Cek validitas idrole
$cekRole = $pdo->prepare('SELECT 1 FROM role WHERE idrole = ?');
$cekRole->execute([$idrole]);
if (!$cekRole->fetchColumn()) {
    flash('gagal', 'Role yang dipilih tidak valid.');
    redirect('?page=user');
}

// Cek duplikasi username
if ($iduser > 0) {
    $cekUser = $pdo->prepare('SELECT iduser FROM `user` WHERE username = ? AND iduser != ?');
    $cekUser->execute([$username, $iduser]);
} else {
    $cekUser = $pdo->prepare('SELECT iduser FROM `user` WHERE username = ?');
    $cekUser->execute([$username]);
}
if ($cekUser->fetchColumn()) {
    flash('gagal', 'Username "' . $username . '" sudah digunakan. Gunakan username lain.');
    redirect('?page=user');
}

try {
    if ($iduser > 0) {
        if ($password !== '') {
            if (strlen($password) < 6) {
                flash('gagal', 'Password minimal 6 karakter.');
                redirect('?page=user');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE `user` SET username = ?, password = ?, idrole = ? WHERE iduser = ?');
            $stmt->execute([$username, $hash, $idrole, $iduser]);
        } else {
            // Jangan ubah password jika input dikosongkan saat edit
            $stmt = $pdo->prepare('UPDATE `user` SET username = ?, idrole = ? WHERE iduser = ?');
            $stmt->execute([$username, $idrole, $iduser]);
        }
        flash('ok', 'Akun pengguna "' . $username . '" berhasil diperbarui.');
    } else {
        if ($password === '') {
            flash('gagal', 'Password wajib diisi untuk pengguna baru.');
            redirect('?page=user');
        }
        if (strlen($password) < 6) {
            flash('gagal', 'Password minimal 6 karakter.');
            redirect('?page=user');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO `user` (username, password, idrole) VALUES (?, ?, ?)');
        $stmt->execute([$username, $hash, $idrole]);
        flash('ok', 'Pengguna baru "' . $username . '" berhasil ditambahkan.');
    }
} catch (PDOException $ex) {
    error_log('[User Simpan Error] ' . $ex->getMessage());
    flash('gagal', 'Gagal menyimpan data pengguna ke database.');
}

redirect('?page=user');

