<?php
declare(strict_types=1);

/**
 * Action: Simpan / Ubah Satuan
 */

if (!auth_can_manage('satuan')) {
    flash('gagal', 'Anda tidak memiliki hak akses untuk memodifikasi satuan.');
    redirect('?page=satuan');
}

$idsatuan    = (int)($_POST['idsatuan'] ?? 0);
$nama_satuan = sanitize_input($_POST['nama_satuan'] ?? '');
$status      = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;

if ($nama_satuan === '') {
    flash('gagal', 'Nama satuan wajib diisi.');
    redirect('?page=satuan');
}

try {
    if ($idsatuan > 0) {
        $stmt = $pdo->prepare('UPDATE satuan SET nama_satuan = ?, status = ? WHERE idsatuan = ?');
        $stmt->execute([$nama_satuan, $status, $idsatuan]);
        flash('ok', 'Satuan "' . $nama_satuan . '" berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO satuan (nama_satuan, status) VALUES (?, ?)');
        $stmt->execute([$nama_satuan, $status]);
        flash('ok', 'Satuan baru "' . $nama_satuan . '" berhasil ditambahkan.');
    }
} catch (PDOException $ex) {
    error_log('[Satuan Simpan Error] ' . $ex->getMessage());
    flash('gagal', 'Gagal menyimpan data satuan.');
}

redirect('?page=satuan');

