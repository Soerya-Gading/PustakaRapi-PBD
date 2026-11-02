<?php
declare(strict_types=1);

/**
 * Action: Proses Otentikasi Masuk (Login)
 */

$username = sanitize_input($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');

$hasil = auth_login($pdo, $username, $password);

if ($hasil['ok']) {
    flash('ok', 'Selamat datang kembali, ' . $hasil['user']['username'] . ' (' . $hasil['user']['nama_role'] . ')!');
    redirect('?page=dashboard');
} else {
    flash('gagal', $hasil['pesan']);
    redirect('?page=login');
}

