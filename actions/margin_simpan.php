<?php
declare(strict_types=1);

/**
 * Action: Simpan / Ubah Margin Penjualan
 */

if (!auth_can_manage('margin')) {
    flash('gagal', 'Anda tidak memiliki hak akses untuk memodifikasi margin penjualan.');
    redirect('?page=margin');
}

$idmargin = (int)($_POST['idmargin_penjualan'] ?? 0);
$persen   = (float)($_POST['persen'] ?? 0);
$status   = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;
$iduser   = auth_id() ?? 1;

if ($persen < 0 || $persen > 100) {
    flash('gagal', 'Persentase margin harus di antara 0% hingga 100%.');
    redirect('?page=margin');
}

try {
    if ($idmargin > 0) {
        $stmt = $pdo->prepare('
            UPDATE margin_penjualan 
            SET persen = ?, status = ?, iduser = ?, updated_at = NOW() 
            WHERE idmargin_penjualan = ?
        ');
        $stmt->execute([$persen, $status, $iduser, $idmargin]);
        flash('ok', 'Margin penjualan ' . $persen . '% berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO margin_penjualan (created_at, persen, status, iduser, updated_at) 
            VALUES (NOW(), ?, ?, ?, NOW())
        ');
        $stmt->execute([$persen, $status, $iduser]);
        flash('ok', 'Margin penjualan baru ' . $persen . '% berhasil ditambahkan.');
    }
} catch (PDOException $ex) {
    error_log('[Margin Simpan Error] ' . $ex->getMessage());
    flash('gagal', 'Gagal menyimpan margin penjualan ke database.');
}

redirect('?page=margin');

