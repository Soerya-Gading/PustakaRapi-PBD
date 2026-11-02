<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Manajemen Vendor
 */

auth_require_page('vendor');

$canManage = auth_can_manage('vendor');

$stmt = $pdo->query('
    SELECT v.idvendor, v.nama_vendor, v.badan_hukum, v.status, COUNT(p.idpengadaan) AS total_pengadaan
    FROM vendor v
    LEFT JOIN pengadaan p ON v.idvendor = p.vendor_idvendor
    GROUP BY v.idvendor, v.nama_vendor, v.badan_hukum, v.status
    ORDER BY v.idvendor ASC
');
$daftarVendor = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Data Vendor & Penerbit</h2>
            <p class="card-subtitle">
                Mitra pemasok dan penerbit buku. Vendor yang telah digunakan dalam transaksi pengadaan tidak dapat dihapus sembarangan.
            </p>
        </div>
        <?php if ($canManage): ?>
            <button type="button" class="btn btn-primary" data-open-modal="modal-vendor" data-item='{"idvendor":0,"nama_vendor":"","badan_hukum":"P","status":"A","action_title":"Tambah Vendor Baru"}'>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Tambah Vendor
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Nama Vendor</th>
                    <th style="width: 130px;">Badan Hukum</th>
                    <th style="width: 160px;">Histori Pengadaan</th>
                    <th style="width: 120px;">Status</th>
                    <?php if ($canManage): ?>
                        <th style="width: 140px; text-align:right;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarVendor)): ?>
                    <tr>
                        <td colspan="<?= $canManage ? '6' : '5' ?>">
                            <div class="empty-state">
                                <h3>Belum Ada Data Vendor</h3>
                                <p>Silakan daftarkan rekanan penerbit atau distributor pengadaan.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarVendor as $v): ?>
                        <?php
                        $editData = json_encode([
                            'idvendor'     => (int)$v['idvendor'],
                            'nama_vendor'  => (string)$v['nama_vendor'],
                            'badan_hukum'  => (string)$v['badan_hukum'],
                            'status'       => (string)$v['status'],
                            'action_title' => 'Ubah Vendor: ' . $v['nama_vendor'],
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                        $isTerpakai = (int)$v['total_pengadaan'] > 0;
                        ?>
                        <tr>
                            <td class="mono" style="font-weight:600; color:var(--text-subtle);">#<?= (int)$v['idvendor'] ?></td>
                            <td style="font-weight:600; color:var(--text-main);"><?= e($v['nama_vendor']) ?></td>
                            <td>
                                <span class="badge badge-muted">
                                    <?= e(BADAN_HUKUM[$v['badan_hukum']] ?? $v['badan_hukum']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $isTerpakai ? 'badge-info' : 'badge-muted' ?>">
                                    <?= (int)$v['total_pengadaan'] ?> Transaksi
                                </span>
                            </td>
                            <td><?= render_badge_status((string)$v['status'], 'aktif_nonaktif') ?></td>
                            <?php if ($canManage): ?>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                data-open-modal="modal-vendor"
                                                data-item='<?= e($editData) ?>'>
                                            Ubah
                                        </button>
                                        <?php if (!$isTerpakai): ?>
                                            <form method="post" action="?action=vendor_hapus" data-confirm="Hapus vendor &quot;<?= e($v['nama_vendor']) ?>&quot;?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="idvendor" value="<?= (int)$v['idvendor'] ?>">
                                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Hapus</button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--text-subtle); cursor:not-allowed;" title="Vendor tercatat di pengadaan">Terkunci</button>
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

<!-- ================= Modal Form Vendor ================= -->
<?php if ($canManage): ?>
<div class="modal-backdrop" id="modal-vendor" role="dialog" aria-modal="true" aria-labelledby="modal-vendor-title">
    <div class="modal-dialog">
        <form method="post" action="?action=vendor_simpan">
            <?= csrf_field() ?>
            <input type="hidden" name="idvendor" value="0">

            <div class="modal-header">
                <div>
                    <span class="micro-label">Referensi Master</span>
                    <h3 class="modal-title" id="modal-vendor-title">Tambah Vendor</h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="vendor_nama">Nama Vendor / Perusahaan <span class="req">*</span></label>
                    <input type="text" name="nama_vendor" id="vendor_nama" class="form-control" required maxlength="100" placeholder="Contoh: Gramedia Pustaka Utama">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="vendor_badan">Badan Hukum <span class="req">*</span></label>
                        <select name="badan_hukum" id="vendor_badan" class="form-control" required>
                            <?php foreach (BADAN_HUKUM as $k => $lbl): ?>
                                <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="vendor_status">Status Kerjasama</label>
                        <select name="status" id="vendor_status" class="form-control">
                            <option value="A">Aktif</option>
                            <option value="N">Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Vendor</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

