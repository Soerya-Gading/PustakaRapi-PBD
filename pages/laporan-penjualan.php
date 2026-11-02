<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Laporan Penjualan
 * Menggunakan BackendAdapter::getLaporanPenjualan() yang terintegrasi dengan View v_laporan_penjualan.
 */

auth_require_page('laporan-penjualan');

$tglMulai   = sanitize_input($_GET['tgl_mulai'] ?? '');
$tglSelesai = sanitize_input($_GET['tgl_selesai'] ?? '');
$idKasir    = !empty($_GET['id_kasir']) ? (int)$_GET['id_kasir'] : null;
$idBuku     = !empty($_GET['id_buku']) ? (int)$_GET['id_buku'] : null;

// Eksekusi query laporan via Adapter
$laporanRes   = BackendAdapter::getLaporanPenjualan($pdo, $tglMulai, $tglSelesai, $idKasir, $idBuku);
$laporanItems = $laporanRes['items'];
$viewAda      = $laporanRes['view_available'];

// Hitung metrik ringkasan
$totalTransaksi = 0;
$totalUnit      = 0;
$totalOmzet     = 0;
$totalPpn       = 0;
$uniqueTrx      = [];

foreach ($laporanItems as $row) {
    $trxId = (int)($row['id_penjualan'] ?? 0);
    if (!isset($uniqueTrx[$trxId])) {
        $uniqueTrx[$trxId] = true;
        $totalTransaksi++;
        $totalPpn += (int)($row['ppn'] ?? 0);
        $totalOmzet += (int)($row['total_nilai'] ?? 0);
    }
    $totalUnit += (int)($row['jumlah'] ?? 0);
}

// Data filter
$daftarKasir = $pdo->query('
    SELECT DISTINCT u.iduser, u.username 
    FROM `user` u 
    INNER JOIN penjualan p ON u.iduser = p.iduser 
    ORDER BY u.username ASC
')->fetchAll();

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
            <strong>View Database Aktif:</strong> Data laporan berhasil dimuat langsung dari MySQL View <code>v_laporan_penjualan</code>.
        <?php else: ?>
            <strong>Status Kontrak View:</strong> MySQL View <code>v_laporan_penjualan</code> belum dibuat di skema database. Sistem saat ini beroperasi menggunakan <strong>Adapter Fallback Engine</strong> dengan relasi eksplisit antar-tabel transaksi tanpa rekayasa data.
        <?php endif; ?>
    </div>
</div>

<!-- ================= Ringkasan Statistik Laporan ================= -->
<div class="stat-grid">
    <div class="stat-card">
        <div>
            <span class="stat-label">Total Transaksi</span>
            <div class="stat-val"><?= format_angka($totalTransaksi) ?></div>
            <div class="stat-desc">Struk kasir diterbitkan</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            </svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <span class="stat-label">Buku Terjual</span>
            <div class="stat-val"><?= format_angka($totalUnit) ?></div>
            <div class="stat-desc">Volume eksemplar keluar</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
            </svg>
        </div>
    </div>

    <div class="stat-card accent-stat">
        <div>
            <span class="stat-label">Total Nilai Penjualan</span>
            <div class="stat-val"><?= rupiah($totalOmzet) ?></div>
            <div class="stat-desc">Penerimaan bruto dari kasir</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M12 6v12M8 10h8"/>
            </svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <span class="stat-label">Kontribusi PPN</span>
            <div class="stat-val"><?= rupiah($totalPpn) ?></div>
            <div class="stat-desc">Pajak Pertambahan Nilai 11%</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
    </div>
</div>

<!-- ================= Filter & Tabel Laporan ================= -->
<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Laporan Penjualan Toko</h2>
            <p class="card-subtitle">Filter berdasarkan rentang tanggal, petugas kasir, atau judul buku tertentu.</p>
        </div>
    </div>

    <form method="get" action="" class="filter-toolbar" style="background:var(--bg-surface-subtle); padding:16px; border-radius:var(--radius-md); margin-bottom:24px;">
        <input type="hidden" name="page" value="laporan-penjualan">

        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="filter_mulai" style="font-size:0.75rem;">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" id="filter_mulai" class="form-control" value="<?= e($tglMulai) ?>">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label" for="filter_selesai" style="font-size:0.75rem;">Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" id="filter_selesai" class="form-control" value="<?= e($tglSelesai) ?>">
            </div>

            <div class="form-group" style="margin:0; min-width:160px;">
                <label class="form-label" for="filter_kasir" style="font-size:0.75rem;">Kasir</label>
                <select name="id_kasir" id="filter_kasir" class="form-control">
                    <option value="">-- Semua Kasir --</option>
                    <?php foreach ($daftarKasir as $k): ?>
                        <option value="<?= (int)$k['iduser'] ?>" <?= $idKasir === (int)$k['iduser'] ? 'selected' : '' ?>>
                            <?= e($k['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin:0; min-width:200px;">
                <label class="form-label" for="filter_buku" style="font-size:0.75rem;">Buku</label>
                <select name="id_buku" id="filter_buku" class="form-control">
                    <option value="">-- Semua Buku --</option>
                    <?php foreach ($daftarBuku as $b): ?>
                        <option value="<?= (int)$b['idbarang'] ?>" <?= $idBuku === (int)$b['idbarang'] ? 'selected' : '' ?>>
                            <?= e($b['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm" style="min-height:38px;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Terapkan Filter
            </button>

            <?php if ($tglMulai !== '' || $tglSelesai !== '' || $idKasir !== null || $idBuku !== null): ?>
                <a href="?page=laporan-penjualan" class="btn btn-secondary btn-sm" style="min-height:38px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">No. TRX</th>
                    <th>Waktu</th>
                    <th>Petugas Kasir</th>
                    <th>Judul Buku</th>
                    <th style="width: 80px; text-align:center;">Jumlah</th>
                    <th style="width: 130px; text-align:right;">Harga Satuan</th>
                    <th style="width: 140px; text-align:right;">Subtotal</th>
                    <th style="width: 120px; text-align:right;">PPN (11%)</th>
                    <th style="width: 150px; text-align:right;">Total Transaksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($laporanItems)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <h3>Tidak Ada Data Penjualan</h3>
                                <p>Tidak ditemukan rekaman transaksi kasir untuk kriteria filter yang diterapkan.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($laporanItems as $row): ?>
                        <?php
                        $trxId      = (int)($row['id_penjualan'] ?? 0);
                        $trxTanggal = (string)($row['tanggal'] ?? '');
                        $kasirNama  = (string)($row['kasir'] ?? '—');
                        $judulBuku  = (string)($row['judul_buku'] ?? '—');
                        $qty        = (int)($row['jumlah'] ?? 0);
                        $harga      = (int)($row['harga_satuan'] ?? 0);
                        $sub        = (int)($row['subtotal'] ?? 0);
                        $ppnVal     = (int)($row['ppn'] ?? 0);
                        $total      = (int)($row['total_nilai'] ?? 0);
                        ?>
                        <tr>
                            <td class="mono" style="font-weight:700; color:var(--text-subtle);">#TRX-<?= $trxId ?></td>
                            <td style="font-size:0.86rem;"><?= format_tanggal($trxTanggal) ?></td>
                            <td style="font-weight:600;"><?= e($kasirNama) ?></td>
                            <td style="font-weight:500; color:var(--text-main);"><?= e($judulBuku) ?></td>
                            <td class="mono" style="text-align:center; font-weight:700;"><?= $qty ?></td>
                            <td class="mono" style="text-align:right;"><?= rupiah($harga) ?></td>
                            <td class="mono" style="text-align:right;"><?= rupiah($sub) ?></td>
                            <td class="mono" style="text-align:right;"><?= rupiah($ppnVal) ?></td>
                            <td class="mono" style="text-align:right; font-weight:700; color:var(--accent-primary);">
                                <?= rupiah($total) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

