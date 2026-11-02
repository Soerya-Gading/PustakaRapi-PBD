<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Margin Penjualan
 */

auth_require_page('margin');

$canManage = auth_can_manage('margin');

$stmt = $pdo->query('
    SELECT m.idmargin_penjualan, m.persen, m.status, m.created_at, m.updated_at, u.username,
           COUNT(p.idpenjualan) AS total_transaksi
    FROM margin_penjualan m
    LEFT JOIN `user` u ON m.iduser = u.iduser
    LEFT JOIN penjualan p ON m.idmargin_penjualan = p.idmargin_penjualan
    GROUP BY m.idmargin_penjualan, m.persen, m.status, m.created_at, m.updated_at, u.username
    ORDER BY m.persen ASC
');
$daftarMargin = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Margin Penjualan</h2>
            <p class="card-subtitle">
                Persentase keuntungan yang diaplikasikan pada transaksi penjualan kasir.
            </p>
        </div>
        <?php if ($canManage): ?>
            <button type="button" class="btn btn-primary" data-open-modal="modal-margin" data-item='{"idmargin_penjualan":0,"persen":10,"status":1,"action_title":"Tambah Margin Baru"}'>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Tambah Margin
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 150px;">Persentase (%)</th>
                    <th>Terakhir Diubah Oleh</th>
                    <th>Waktu Dibuat / Diubah</th>
                    <th style="width: 150px;">Histori Penjualan</th>
                    <th style="width: 120px;">Status</th>
                    <?php if ($canManage): ?>
                        <th style="width: 140px; text-align:right;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarMargin)): ?>
                    <tr>
                        <td colspan="<?= $canManage ? '7' : '6' ?>">
                            <div class="empty-state">
                                <h3>Belum Ada Data Margin</h3>
                                <p>Tambahkan persentase margin keuntungan untuk transaksi kasir.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarMargin as $m): ?>
                        <?php
                        $editData = json_encode([
                            'idmargin_penjualan' => (int)$m['idmargin_penjualan'],
                            'persen'             => (float)$m['persen'],
                            'status'             => (int)$m['status'],
                            'action_title'       => 'Ubah Margin: ' . $m['persen'] . '%',
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                        $isTerpakai = (int)$m['total_transaksi'] > 0;
                        ?>
                        <tr>
                            <td class="mono" style="font-weight:600; color:var(--text-subtle);">#<?= (int)$m['idmargin_penjualan'] ?></td>
                            <td>
                                <span class="badge badge-accent" style="font-size:0.95rem; font-family:var(--font-mono); padding:4px 12px;">
                                    <?= (float)$m['persen'] ?>%
                                </span>
                            </td>
                            <td style="color:var(--text-main); font-weight:500;">
                                <?= e($m['username'] ?? 'Sistem') ?>
                            </td>
                            <td style="font-size:0.84rem; color:var(--text-muted);">
                                <?= format_tanggal($m['updated_at'] ?: $m['created_at']) ?>
                            </td>
                            <td>
                                <span class="badge <?= $isTerpakai ? 'badge-info' : 'badge-muted' ?>">
                                    <?= (int)$m['total_transaksi'] ?> Transaksi
                                </span>
                            </td>
                            <td><?= render_badge_status((string)$m['status'], 'aktif_nonaktif') ?></td>
                            <?php if ($canManage): ?>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                data-open-modal="modal-margin"
                                                data-item='<?= e($editData) ?>'>
                                            Ubah
                                        </button>
                                        <?php if (!$isTerpakai): ?>
                                            <form method="post" action="?action=margin_hapus" data-confirm="Hapus margin <?= (float)$m['persen'] ?>%?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="idmargin_penjualan" value="<?= (int)$m['idmargin_penjualan'] ?>">
                                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Hapus</button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--text-subtle); cursor:not-allowed;" title="Margin tercatat di transaksi penjualan">Terkunci</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= Modal Form Margin ================= -->
<?php if ($canManage): ?>
<div class="modal-backdrop" id="modal-margin" role="dialog" aria-modal="true" aria-labelledby="modal-margin-title">
    <div class="modal-dialog">
        <form method="post" action="?action=margin_simpan">
            <?= csrf_field() ?>
            <input type="hidden" name="idmargin_penjualan" value="0">

            <div class="modal-header">
                <div>
                    <span class="micro-label">Konfigurasi Harga</span>
                    <h3 class="modal-title" id="modal-margin-title">Tambah Margin</h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="margin_persen">Persentase Margin (%) <span class="req">*</span></label>
                    <input type="number" name="persen" id="margin_persen" class="form-control mono" required min="0" max="100" step="0.1" placeholder="Contoh: 12.5">
                    <small class="form-help">Nilai persentase keuntungan antara 0% hingga 100%.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="margin_status">Status Penggunaan</label>
                    <select name="status" id="margin_status" class="form-control">
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Margin</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

