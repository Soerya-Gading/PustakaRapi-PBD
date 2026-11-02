<?php
declare(strict_types=1);

/**
 * Action: Simpan / Ubah Buku
 * Menulis langsung ke tabel master `barang` menggunakan prepared statement.
 */

if (!auth_can_manage('buku')) {
    flash('gagal', 'Anda tidak memiliki hak akses untuk memodifikasi data buku.');
    redirect('?page=buku');
}

$idbarang = (int)($_POST['idbarang'] ?? 0);
$nama     = sanitize_input($_POST['nama'] ?? '');
$jenis    = sanitize_input($_POST['jenis'] ?? 'F');
$idsatuan = (int)($_POST['idsatuan'] ?? 0);
$harga    = (int)($_POST['harga'] ?? 0);
$status   = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;

if ($nama === '') {
    flash('gagal', 'Judul buku wajib diisi.');
    redirect('?page=buku');
}

if (!isset(GENRE[$jenis])) {
    flash('gagal', 'Kategori buku tidak valid.');
    redirect('?page=buku');
}

if ($idsatuan <= 0) {
    flash('gagal', 'Satuan buku wajib dipilih.');
    redirect('?page=buku');
}

if ($harga < 0) {
    flash('gagal', 'Harga buku tidak boleh negatif.');
    redirect('?page=buku');
}

// Cek foreign key satuan
$cekSatuan = $pdo->prepare('SELECT 1 FROM satuan WHERE idsatuan = ?');
$cekSatuan->execute([$idsatuan]);
if (!$cekSatuan->fetchColumn()) {
    flash('gagal', 'Satuan yang dipilih tidak valid dalam sistem.');
    redirect('?page=buku');
}

try {
    if ($idbarang > 0) {
        $stmt = $pdo->prepare('
            UPDATE barang 
            SET nama = ?, jenis = ?, idsatuan = ?, harga = ?, status = ? 
            WHERE idbarang = ?
        ');
        $stmt->execute([$nama, $jenis, $idsatuan, $harga, $status, $idbarang]);
        flash('ok', 'Buku "' . $nama . '" berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO barang (nama, jenis, idsatuan, harga, status) 
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$nama, $jenis, $idsatuan, $harga, $status]);
        flash('ok', 'Buku "' . $nama . '" berhasil ditambahkan ke katalog.');
    }
} catch (PDOException $ex) {
    error_log('[Buku Simpan Error] ' . $ex->getMessage());
    flash('gagal', 'Gagal menyimpan buku ke database.');
}

redirect('?page=buku');

