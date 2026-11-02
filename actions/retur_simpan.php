<?php
declare(strict_types=1);

/**
 * Action: Simpan Transaksi Retur Penerimaan
 * Menyimpan data retur ke tabel `retur`, `detail_retur`, dan mencatat mutasi keluar pada `kartu_stok`.
 */

if (!auth_can_manage('retur')) {
    flash('gagal', 'Anda tidak memiliki hak akses untuk memproses retur.');
    redirect('?page=retur');
}

$idpenerimaan = (int)($_POST['idpenerimaan'] ?? 0);
$iduser       = auth_id() ?? 1;
$itemsRaw     = $_POST['items'] ?? [];

if ($idpenerimaan <= 0) {
    flash('gagal', 'Nomor penerimaan barang wajib dipilih.');
    redirect('?page=retur');
}

if (!is_array($itemsRaw) || empty($itemsRaw)) {
    flash('gagal', 'Tidak ada barang yang dipilih untuk diretur.');
    redirect('?page=retur');
}

// Validasi keberadaan penerimaan
$cekPenerimaan = $pdo->prepare('SELECT idpenerimaan FROM penerimaan WHERE idpenerimaan = ?');
$cekPenerimaan->execute([$idpenerimaan]);
if (!$cekPenerimaan->fetchColumn()) {
    flash('gagal', 'Penerimaan barang tidak ditemukan.');
    redirect('?page=retur');
}

$validItems = [];
foreach ($itemsRaw as $row) {
    $iddetail_terima = (int)($row['iddetail_penerimaan'] ?? 0);
    $jumlah          = (int)($row['jumlah'] ?? 0);
    $alasan          = sanitize_input($row['alasan'] ?? 'Cacat / Rusak');
    $idbarang        = (int)($row['idbarang'] ?? 0);

    if ($iddetail_terima <= 0 || $jumlah <= 0 || $idbarang <= 0) {
        continue;
    }

    $validItems[] = [
        'iddetail_penerimaan' => $iddetail_terima,
        'jumlah'              => $jumlah,
        'alasan'              => $alasan,
        'idbarang'            => $idbarang,
    ];
}

if (empty($validItems)) {
    flash('gagal', 'Jumlah barang yang diretur harus lebih dari 0.');
    redirect('?page=retur');
}

require_once dirname(__DIR__) . '/includes/retur-service.php';
try {
    $idretur = simpan_retur($pdo, $idpenerimaan, $iduser, $validItems);
    flash('ok', 'Retur #RET-' . $idretur . ' tersimpan dan stok disesuaikan.');
} catch (DomainException $ex) {
    flash('gagal', $ex->getMessage());
} catch (Throwable $ex) {
    error_log('[Retur Simpan Error] ' . $ex->getMessage());
    flash('gagal', 'Retur gagal diproses. Muat ulang data lalu coba kembali.');
}

redirect('?page=retur');

