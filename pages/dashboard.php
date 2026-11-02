<?php
declare(strict_types=1);

/**
 * PustakaRapi — Modern Operational Dashboard
 */

auth_require_page('dashboard');

// Ambil metrik ringkasan nyata dari database
$totalBuku      = (int)$pdo->query('SELECT COUNT(*) FROM barang')->fetchColumn();
$totalVendor    = (int)$pdo->query('SELECT COUNT(*) FROM vendor')->fetchColumn();
$totalSatuan    = (int)$pdo->query('SELECT COUNT(*) FROM satuan')->fetchColumn();
$totalUser      = (int)$pdo->query('SELECT COUNT(*) FROM `user`')->fetchColumn();
$totalRole      = (int)$pdo->query('SELECT COUNT(*) FROM role')->fetchColumn();
$totalPenjualan = (int)$pdo->query('SELECT COUNT(*) FROM penjualan')->fetchColumn();
$omzetPenjualan = (int)$pdo->query('SELECT COALESCE(SUM(total_nilai), 0) FROM penjualan')->fetchColumn();
$totalPengadaan = (int)$pdo->query('SELECT COUNT(*) FROM pengadaan')->fetchColumn();
$totalPenerimaan= (int)$pdo->query('SELECT COUNT(*) FROM penerimaan')->fetchColumn();

// Ambil buku dengan stok menipis / habis dari kartu_stok
$stokAdapter = BackendAdapter::getRingkasanStok($pdo);
$bukuStokKritis = array_filter($stokAdapter['items'], function($item) {
    return $item['tercatat'] && $item['sisa_stok'] <= 5;
});
$totalBelumTercatat = count(array_filter($stokAdapter['items'], fn($item) => !$item['tercatat']));

// Transaksi penjualan terbaru (limit 5)
$stmtRecentSales = $pdo->query('
    SELECT p.idpenjualan, p.created_at, p.total_nilai, u.username
    FROM penjualan p
    LEFT JOIN `user` u ON p.iduser = u.iduser
    ORDER BY p.idpenjualan DESC
    LIMIT 5
');
$recentSales = $stmtRecentSales->fetchAll();

// Aktivitas penerimaan terbaru (limit 5)
$stmtRecentReceipts = $pdo->query('
    SELECT pr.idpenerimaan, pr.created_at, pr.status, pr.idpengadaan, u.username, v.nama_vendor
    FROM penerimaan pr
    LEFT JOIN `user` u ON pr.iduser = u.iduser
    LEFT JOIN pengadaan pg ON pr.idpengadaan = pg.idpengadaan
    LEFT JOIN vendor v ON pg.vendor_idvendor = v.idvendor
    ORDER BY pr.idpenerimaan DESC
    LIMIT 5
');
$recentReceipts = $stmtRecentReceipts->fetchAll();

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<!-- ================= Header Sapaan ================= -->
<div style="margin-bottom: 28px;">
    <span class="micro-label">Ringkasan Operasional</span>
    <h1>Selamat Datang, <?= e(auth_username()) ?></h1>
    <p style="color:var(--text-muted); font-size:0.95rem; margin-top:4px;">
        Sistem Persediaan dan Penjualan Toko Buku PustakaRapi.
    </p>
</div>

<!-- ================= Kartu Statistik Utama ================= -->
<div class="stat-grid">
    <div class="stat-card">
        <div>
            <span class="stat-label">Katalog Buku</span>
            <div class="stat-val"><?= format_angka($totalBuku) ?></div>
            <div class="stat-desc"><?= format_angka($totalSatuan) ?> satuan ukuran</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <span class="stat-label">Mitra Vendor</span>
            <div class="stat-val"><?= format_angka($totalVendor) ?></div>
            <div class="stat-desc">Penerbit & distributor terdaftar</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
        </div>
    </div>

    <div class="stat-card accent-stat">
        <div>
            <span class="stat-label">Total Penjualan</span>
            <div class="stat-val"><?= rupiah($omzetPenjualan) ?></div>
            <div class="stat-desc"><?= format_angka($totalPenjualan) ?> transaksi kasir</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <span class="stat-label">Pengadaan & Terima</span>
            <div class="stat-val"><?= format_angka($totalPenerimaan) ?> / <?= format_angka($totalPengadaan) ?></div>
            <div class="stat-desc">Penerimaan dari total pengadaan</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        </div>
    </div>
</div>

<!-- ================= Grid Konten Aktivitas & Stok ================= -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px;">

    <!-- Card 1: Stok Buku Kritis -->
    <div class="card">
        <div class="card-header">
            <div class="card-title-group">
                <h3>Peringatan Stok Rendah</h3>
                <p class="card-subtitle">Buku yang memerlukan pengadaan ulang segera.</p>
            </div>
            <a href="?page=kartu-stok" class="btn btn-secondary btn-sm">Lihat Kartu Stok</a>
        </div>

        <?php if (empty($bukuStokKritis)): ?>
            <div class="empty-state" style="padding: 32px 16px;">
                <div class="empty-state-icon" style="background:var(--success-soft); color:var(--success-text);">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <h3>Stok dalam Kondisi Aman</h3>
                <p style="margin-bottom:0;">Tidak ada buku dengan sisa stok kritis atau habis saat ini.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Judul Buku</th>
                            <th style="width:100px;">Sisa Stok</th>
                            <th style="width:100px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($bukuStokKritis, 0, 5) as $bKritis): ?>
                            <tr>
                                <td style="font-weight:600;"><?= e($bKritis['judul_buku']) ?></td>
                                <td class="mono" style="font-weight:700; color:var(--danger);"><?= $bKritis['sisa_stok'] ?> <?= e($bKritis['nama_satuan']) ?></td>
                                <td><span class="badge badge-danger"><?= e($bKritis['status_stok']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Card 2: Transaksi Penjualan Terbaru -->
    <div class="card">
        <div class="card-header">
            <div class="card-title-group">
                <h3>Transaksi Penjualan Terkini</h3>
                <p class="card-subtitle">Aktivitas struk kasir yang baru tercatat.</p>
            </div>
            <a href="?page=penjualan" class="btn btn-secondary btn-sm">Buka Kasir</a>
        </div>

        <?php if (empty($recentSales)): ?>
            <div class="empty-state" style="padding: 32px 16px;">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <h3>Belum Ada Transaksi Penjualan</h3>
                <p style="margin-bottom:0;">Data riwayat transaksi kasir belum tercatat di database.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th>Waktu</th>
                            <th>Kasir</th>
                            <th style="text-align:right;">Total Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td class="mono" style="color:var(--text-subtle);">#<?= (int)$sale['idpenjualan'] ?></td>
                                <td style="font-size:0.84rem;"><?= format_tanggal($sale['created_at']) ?></td>
                                <td style="font-weight:600;"><?= e($sale['username']) ?></td>
                                <td class="mono" style="text-align:right; font-weight:700; color:var(--accent-primary);"><?= rupiah($sale['total_nilai']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Card 3: Aktivitas Penerimaan Barang -->
    <div class="card" style="grid-column: 1 / -1;">
        <div class="card-header">
            <div class="card-title-group">
                <h3>Histori Penerimaan Barang Terkini</h3>
                <p class="card-subtitle">Penerimaan pasokan buku dari vendor yang memperbarui kartu stok.</p>
            </div>
            <a href="?page=penerimaan" class="btn btn-secondary btn-sm">Lihat Semua Penerimaan</a>
        </div>

        <?php if (empty($recentReceipts)): ?>
            <div class="empty-state" style="padding: 32px 16px;">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    </svg>
                </div>
                <h3>Belum Ada Penerimaan Barang</h3>
                <p style="margin-bottom:0;">Belum ada pengadaan buku yang diproses menjadi tanda terima penerimaan.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th>Tanggal Terima</th>
                            <th>Vendor Pemasok</th>
                            <th>Pengadaan Reff</th>
                            <th>Penerima</th>
                            <th style="width:110px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReceipts as $rec): ?>
                            <tr>
                                <td class="mono" style="font-weight:600; color:var(--text-subtle);">#<?= (int)$rec['idpenerimaan'] ?></td>
                                <td style="font-size:0.84rem;"><?= format_tanggal($rec['created_at']) ?></td>
                                <td style="font-weight:600;"><?= e($rec['nama_vendor'] ?? '—') ?></td>
                                <td class="mono">PO #<?= (int)$rec['idpengadaan'] ?></td>
                                <td><?= e($rec['username']) ?></td>
                                <td><?= render_badge_status((string)$rec['status'], 'transaksi') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

