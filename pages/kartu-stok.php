<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Kartu Stok & Buku Mutasi
 * Terintegrasi dengan BackendAdapter::getRingkasanStok() dan tabel kartu_stok.
 */

auth_require_page('kartu-stok');

$idBukuFilter   = !empty($_GET['id_buku']) ? (int)$_GET['id_buku'] : null;
$jenisFilter    = sanitize_input($_GET['jenis'] ?? '');
$tglFilter      = sanitize_input($_GET['tanggal'] ?? '');

// Ambil ringkasan stok dari adapter
$ringkasanRes = BackendAdapter::getRingkasanStok($pdo, $idBukuFilter);
$daftarStok   = $ringkasanRes['items'];
$viewAda      = $ringkasanRes['view_available'];

// Ambil riwayat log kartu stok
$whereMutasi = [];
$paramsMutasi = [];

if ($idBukuFilter !== null && $idBukuFilter > 0) {
    $whereMutasi[] = 'ks.idbarang = :idBuku';
    $paramsMutasi[':idBuku'] = $idBukuFilter;
}
if ($jenisFilter !== '') {
    $whereMutasi[] = 'ks.jenis_transaksi = :jenis';
    $paramsMutasi[':jenis'] = $jenisFilter;
}
if ($tglFilter !== '') {
    $whereMutasi[] = 'DATE(ks.created_at) = :tgl';
    $paramsMutasi[':tgl'] = $tglFilter;
}

$sqlMutasi = '
    SELECT 
        ks.idkartu_stok, ks.jenis_transaksi, ks.masuk, ks.keluar, ks.stock,
        ks.created_at, ks.idtransaksi, ks.idbarang,
        b.nama AS judul_buku, s.nama_satuan
    FROM kartu_stok ks
    LEFT JOIN barang b ON ks.idbarang = b.idbarang
    LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
';
if ($whereMutasi) {
    $sqlMutasi .= ' WHERE ' . implode(' AND ', $whereMutasi);
}
$sqlMutasi .= ' ORDER BY ks.idkartu_stok DESC LIMIT 50';

$stmtMutasi = $pdo->prepare($sqlMutasi);
$stmtMutasi->execute($paramsMutasi);
$riwayatMutasi = $stmtMutasi->fetchAll();

// Label kamus jenis transaksi pada kartu stok
const JENIS_MUTASI = [
    'M' => ['Masuk (Penerimaan)', 'badge-success'],
    'K' => ['Keluar (Penjualan)', 'badge-danger'],
    'R' => ['Retur Pengembalian', 'badge-warning'],
];

$daftarBuku = $pdo->query('SELECT idbarang, nama FROM barang ORDER BY nama ASC')->fetchAll();

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<!-- Banner Transparansi Integrasi View Database -->
<div class="alert-banner <?= $viewAda ? 'alert-ok' : 'alert-info' ?>" style="margin-bottom:24px;">
    <div>
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
    </div>
    <div>
        <?php if ($viewAda): ?>
            <strong>View Database Aktif:</strong> Data ringkasan stok dimuat dari MySQL View <code>v_ringkasan_stok</code>.
        <?php else: ?>
            <strong>Status Kontrak View:</strong> MySQL View <code>v_ringkasan_stok</code> belum dibuat di skema database. Sistem saat ini menghitung saldo mutasi real-time langsung dari tabel ledger <code>kartu_stok</code> secara transparan tanpa angka buatan.
        <?php endif; ?>
    </div>
</div>

<!-- ================= Bagian 1: Ringkasan Stok per Buku ================= -->
<div class="card" style="margin-bottom: 28px;">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Ringkasan Persediaan Buku</h2>
            <p class="card-subtitle">Status stok terkini dan kalkulasi total barang masuk vs keluar.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Judul Buku</th>
                    <th style="width: 120px;">Satuan</th>
                    <th style="width: 130px; text-align:center;">Total Masuk</th>
                    <th style="width: 130px; text-align:center;">Total Keluar</th>
                    <th style="width: 140px; text-align:center;">Sisa Stok Fisik</th>
                    <th style="width: 140px; text-align:center;">Status Stok</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarStok)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <h3>Data Persediaan Kosong</h3>
                                <p>Belum ada rekaman persediaan buku di database.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarStok as $s): ?>
                        <tr>
                            <td class="mono" style="font-weight:600; color:var(--text-subtle);">#<?= (int)$s['id_buku'] ?></td>
                            <td style="font-weight:600; color:var(--text-main);"><?= e($s['judul_buku']) ?></td>
                            <td><?= e($s['nama_satuan'] ?? '—') ?></td>
                            <td class="mono" style="text-align:center; color:var(--success-text); font-weight:600;">
                                +<?= (int)$s['total_masuk'] ?>
                            </td>
                            <td class="mono" style="text-align:center; color:var(--danger-text); font-weight:600;">
                                -<?= (int)$s['total_keluar'] ?>
                            </td>
                            <td class="mono" style="text-align:center; font-weight:800; font-size:1.05rem;">
                                <?php if ($s['tercatat']): ?>
                                    <?= (int)$s['sisa_stok'] ?>
                                <?php else: ?>
                                    <span style="font-size:0.82rem; font-weight:500; color:var(--text-subtle);">Stok belum tercatat</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php
                                $badgeClass = 'badge-muted';
                                if ($s['status_stok'] === 'Aman') $badgeClass = 'badge-success';
                                elseif ($s['status_stok'] === 'Menipis') $badgeClass = 'badge-warning';
                                elseif ($s['status_stok'] === 'Habis') $badgeClass = 'badge-danger';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= e($s['status_stok']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= Bagian 2: Log Mutasi Kartu Stok ================= -->
<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Log Mutasi Kartu Stok</h2>
            <p class="card-subtitle">Setiap pergerakan barang (penerimaan, kasir, atau retur) dicatat secara kronologis.</p>
        </div>
    </div>

    <!-- Toolbar Filter Mutasi -->
    <form method="get" action="" class="filter-toolbar" style="background:var(--bg-surface-subtle); padding:16px; border-radius:var(--radius-md); margin-bottom:20px;">
        <input type="hidden" name="page" value="kartu-stok">

        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div class="form-group" style="margin:0; min-width:200px;">
                <label class="form-label" for="mutasi_buku" style="font-size:0.75rem;">Pilih Buku</label>
                <select name="id_buku" id="mutasi_buku" class="form-control">
                    <option value="">-- Semua Buku --</option>
                    <?php foreach ($daftarBuku as $b): ?>
                        <option value="<?= (int)$b['idbarang'] ?>" <?= $idBukuFilter === (int)$b['idbarang'] ? 'selected' : '' ?>>
                            <?= e($b['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin:0; min-width:160px;">
                <label class="form-label" for="mutasi_jenis" style="font-size:0.75rem;">Jenis Mutasi</label>
                <select name="jenis" id="mutasi_jenis" class="form-control">
                    <option value="">-- Semua Jenis --</option>
                    <option value="M" <?= $jenisFilter === 'M' ? 'selected' : '' ?>>Masuk (Penerimaan)</option>
                    <option value="K" <?= $jenisFilter === 'K' ? 'selected' : '' ?>>Keluar (Penjualan)</option>
                    <option value="R" <?= $jenisFilter === 'R' ? 'selected' : '' ?>>Retur Pengembalian</option>
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label" for="mutasi_tanggal" style="font-size:0.75rem;">Tanggal</label>
                <input type="date" name="tanggal" id="mutasi_tanggal" class="form-control" value="<?= e($tglFilter) ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-sm" style="min-height:38px;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Filter Mutasi
            </button>

            <?php if ($idBukuFilter !== null || $jenisFilter !== '' || $tglFilter !== ''): ?>
                <a href="?page=kartu-stok" class="btn btn-secondary btn-sm" style="min-height:38px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 70px;">ID Log</th>
                    <th>Waktu Mutasi</th>
                    <th>Judul Buku</th>
                    <th style="width: 170px;">Jenis Transaksi</th>
                    <th style="width: 110px; text-align:center;">Masuk</th>
                    <th style="width: 110px; text-align:center;">Keluar</th>
                    <th style="width: 130px; text-align:center;">Saldo Berjalan</th>
                    <th style="width: 130px; text-align:right;">ID Ref Transaksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($riwayatMutasi)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <h3>Belum Ada Log Mutasi Stok</h3>
                                <p>Semua mutasi barang dari penerimaan, penjualan kasir, atau retur akan tercatat di sini.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riwayatMutasi as $m): ?>
                        <?php
                        $jCode = (string)$m['jenis_transaksi'];
                        $jInfo = JENIS_MUTASI[$jCode] ?? ['Kode ' . $jCode, 'badge-muted'];
                        ?>
                        <tr>
                            <td class="mono" style="color:var(--text-subtle);">#<?= (int)$m['idkartu_stok'] ?></td>
                            <td style="font-size:0.86rem;"><?= format_tanggal($m['created_at']) ?></td>
                            <td style="font-weight:600;"><?= e($m['judul_buku']) ?></td>
                            <td><span class="badge <?= $jInfo[1] ?>"><?= e($jInfo[0]) ?></span></td>
                            <td class="mono" style="text-align:center; color:var(--success-text); font-weight:700;">
                                <?= (int)$m['masuk'] > 0 ? ('+' . (int)$m['masuk']) : '—' ?>
                            </td>
                            <td class="mono" style="text-align:center; color:var(--danger-text); font-weight:700;">
                                <?= (int)$m['keluar'] > 0 ? ('-' . (int)$m['keluar']) : '—' ?>
                            </td>
                            <td class="mono" style="text-align:center; font-weight:800; color:var(--text-main);">
                                <?= (int)$m['stock'] ?> <?= e($m['nama_satuan']) ?>
                            </td>
                            <td class="mono" style="text-align:right; color:var(--text-subtle);">
                                Ref #<?= (int)$m['idtransaksi'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

