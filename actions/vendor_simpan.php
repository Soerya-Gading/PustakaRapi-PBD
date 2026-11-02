<?php
declare(strict_types=1);

/**
 * Action: Simpan / Ubah Vendor
 */

if (!auth_can_manage('vendor')) {
    flash('gagal', 'Anda tidak memiliki hak akses untuk memodifikasi vendor.');
    redirect('?page=vendor');
}

$idvendor    = (int)($_POST['idvendor'] ?? 0);
$nama_vendor = sanitize_input($_POST['nama_vendor'] ?? '');
$badan_hukum = sanitize_input($_POST['badan_hukum'] ?? 'P');
$status      = sanitize_input($_POST['status'] ?? 'A');

if ($nama_vendor === '') {
    flash('gagal', 'Nama vendor / penerbit wajib diisi.');
    redirect('?page=vendor');
}

if (!isset(BADAN_HUKUM[$badan_hukum])) {
    flash('gagal', 'Bentuk badan hukum tidak valid.');
    redirect('?page=vendor');
}

if (!in_array($status, ['A', 'N'], true)) {
    $status = 'A';
}

try {
    if ($idvendor > 0) {
        $stmt = $pdo->prepare('UPDATE vendor SET nama_vendor = ?, badan_hukum = ?, status = ? WHERE idvendor = ?');
        $stmt->execute([$nama_vendor, $badan_hukum, $status, $idvendor]);
        flash('ok', 'Data vendor "' . $nama_vendor . '" berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO vendor (nama_vendor, badan_hukum, status) VALUES (?, ?, ?)');
        $stmt->execute([$nama_vendor, $badan_hukum, $status]);
        flash('ok', 'Vendor baru "' . $nama_vendor . '" berhasil ditambahkan.');
    }
} catch (PDOException $ex) {
    error_log('[Vendor Simpan Error] ' . $ex->getMessage());
    flash('gagal', 'Gagal menyimpan vendor ke database.');
}

redirect('?page=vendor');

