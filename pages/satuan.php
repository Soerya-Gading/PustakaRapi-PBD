<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Manajemen Satuan
 */

auth_require_page('satuan');

$canManage = auth_can_manage('satuan');

$stmt = $pdo->query('
    SELECT s.idsatuan, s.nama_satuan, s.status, COUNT(b.idbarang) AS total_buku
    FROM satuan s
    LEFT JOIN barang b ON s.idsatuan = b.idsatuan
    GROUP BY s.idsatuan, s.nama_satuan, s.status
    ORDER BY s.idsatuan ASC
');
$daftarSatuan = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Data Satuan Barang</h2>
            <p class="card-subtitle">
                Satuan ukuran buku dan barang. Satuan yang telah terasosiasi dengan data buku tidak dapat dihapus demi integritas relasi database.
            </p>
        </div>
        <?php if ($canManage): ?>
            <button type="button" class="btn btn-primary" data-open-modal="modal-satuan" data-item='{"idsatuan":0,"nama_satuan":"","status":1,"action_title":"Tambah Satuan Baru"}'>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Tambah Satuan
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Nama Satuan</th>
                    <th style="width: 150px;">Buku Terkait</th>
                    <th style="width: 120px;">Status</th>
                    <?php if ($canManage): ?>
                        <th style="width: 140px; text-align:right;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarSatuan)): ?>
                    <tr>
                        <td colspan="<?= $canManage ? '5' : '4' ?>">
                            <div class="empty-state">
                                <h3>Belum Ada Data Satuan</h3>
                                <p>Silakan tambahkan satuan baru untuk mulai mengkatalogkan buku.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarSatuan as $s): ?>
                        <?php
                        $editData = json_encode([
                            'idsatuan'     => (int)$s['idsatuan'],
                            'nama_satuan'  => (string)$s['nama_satuan'],
                            'status'       => (int)$s['status'],
                            'action_title' => 'Ubah Satuan: ' . $s['nama_satuan'],
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                        $isTerpakai = (int)$s['total_buku'] > 0;
                        ?>
                        <tr>
                            <td class="mono" style="font-weight:600; color:var(--text-subtle);">#<?= (int)$s['idsatuan'] ?></td>
                            <td style="font-weight:600; color:var(--text-main);"><?= e($s['nama_satuan']) ?></td>
                            <td>
                                <span class="badge <?= $isTerpakai ? 'badge-info' : 'badge-muted' ?>">
                                    <?= (int)$s['total_buku'] ?> Judul Buku
                                </span>
                            </td>
                            <td><?= render_badge_status((string)$s['status'], 'aktif_nonaktif') ?></td>
                            <?php if ($canManage): ?>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                data-open-modal="modal-satuan"
                                                data-item='<?= e($editData) ?>'>
                                            Ubah
                                        </button>
                                        <?php if (!$isTerpakai): ?>
                                            <form method="post" action="?action=satuan_hapus" data-confirm="Hapus satuan &quot;<?= e($s['nama_satuan']) ?>&quot;?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="idsatuan" value="<?= (int)$s['idsatuan'] ?>">
                                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Hapus</button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--text-subtle); cursor:not-allowed;" title="Satuan sedang digunakan buku">Terkunci</button>
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

<!-- ================= Modal Form Satuan ================= -->
<?php if ($canManage): ?>
<div class="modal-backdrop" id="modal-satuan" role="dialog" aria-modal="true" aria-labelledby="modal-satuan-title">
    <div class="modal-dialog">
        <form method="post" action="?action=satuan_simpan">
            <?= csrf_field() ?>
            <input type="hidden" name="idsatuan" value="0">

            <div class="modal-header">
                <div>
                    <span class="micro-label">Referensi Master</span>
                    <h3 class="modal-title" id="modal-satuan-title">Tambah Satuan</h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="satuan_nama">Nama Satuan <span class="req">*</span></label>
                    <input type="text" name="nama_satuan" id="satuan_nama" class="form-control" required maxlength="45" placeholder="Contoh: Exemplar, Box, Pcs">
                </div>

                <div class="form-group">
                    <label class="form-label" for="satuan_status">Status</label>
                    <select name="status" id="satuan_status" class="form-control">
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Satuan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

